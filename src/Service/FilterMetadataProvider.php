<?php

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use ReflectionClass;
use ReflectionProperty;

/**
 * Provides filter metadata for entity properties.
 *
 * Automatically detects appropriate filter types from Doctrine metadata
 * and respects ColumnFilter attribute overrides.
 */
class FilterMetadataProvider
{
    /**
     * Default searchable fields for common entity types.
     */
    private const DEFAULT_SEARCH_FIELDS = [
        'User' => ['name', 'email', 'firstName', 'lastName'],
        'Product' => ['name', 'sku', 'description'],
        'Order' => ['id', 'orderNumber', 'externalId'],
        'Customer' => ['name', 'email', 'phone', 'companyName'],
    ];

    /**
     * Fields to check for display value, in priority order.
     * This matches the logic in _preview.html.twig for relation values.
     */
    private const DISPLAY_FIELD_PRIORITY = ['name', 'label', 'title'];

    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Get filter metadata for all filterable columns of an entity.
     *
     * @param string $entityClass Full class name
     * @return array<string, array<string, mixed>> Map of property name => filter metadata
     */
    public function getFilters(string $entityClass): array
    {
        $metadata = $this->em->getClassMetadata($entityClass);
        $reflection = new ReflectionClass($entityClass);
        $filters = [];

        // Process regular fields
        foreach ($metadata->getFieldNames() as $fieldName) {
            $property = $reflection->getProperty($fieldName);
            $filterConfig = $this->getFilterConfig($property, $metadata, $fieldName);

            if ($filterConfig && $filterConfig['enabled']) {
                $filters[$fieldName] = $filterConfig;
            }
        }

        // Process associations
        foreach ($metadata->getAssociationNames() as $associationName) {
            // Skip collection associations in filters (too complex)
            if ($metadata->isCollectionValuedAssociation($associationName)) {
                continue;
            }

            $property = $reflection->getProperty($associationName);
            $filterConfig = $this->getFilterConfig($property, $metadata, $associationName);

            if ($filterConfig && $filterConfig['enabled']) {
                $filters[$associationName] = $filterConfig;
            }
        }

        // Sort by priority
        uasort($filters, fn(array $a, array $b): int => ($a['priority'] ?? 999) <=> ($b['priority'] ?? 999));

        return $filters;
    }

    /**
     * Get filter configuration for a specific property.
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>|null
     */
    private function getFilterConfig(
        ReflectionProperty $property,
        ClassMetadata $metadata,
        string $propertyName
    ): ?array {
        // Check for ColumnFilter attribute
        $attributes = $property->getAttributes(ColumnFilter::class);
        $columnFilter = $attributes[0] ?? null;

        // If explicitly disabled, skip
        if ($columnFilter && !$columnFilter->newInstance()->enabled) {
            return null;
        }

        $config = [
            'property' => $propertyName,
            'label' => $columnFilter?->newInstance()->label ?? $this->humanize($propertyName),
            'enabled' => true,
        ];

        // Determine filter type
        if ($metadata->hasAssociation($propertyName)) {
            $config = array_merge($config, $this->getRelationFilterConfig(
                $metadata,
                $propertyName,
                $columnFilter
            ));
        } else {
            $config = array_merge($config, $this->getFieldFilterConfig(
                $metadata,
                $propertyName,
                $property,
                $columnFilter
            ));
        }

        // Apply attribute overrides
        if ($columnFilter) {
            $config = $this->applyAttributeOverrides($config, $columnFilter->newInstance(), $property, $metadata, $propertyName);
        }

        return $config;
    }

    /**
     * Apply ColumnFilter attribute overrides to the configuration.
     * @param array<string, mixed> $config
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>
     */
    private function applyAttributeOverrides(
        array $config,
        ColumnFilter $instance,
        ReflectionProperty $property,
        ClassMetadata $metadata,
        string $propertyName
    ): array {
        if ($instance->type) {
            $config['type'] = $instance->type;
            $config = $this->applyTypeSpecificOverrides($config, $instance, $property, $metadata, $propertyName);
        }

        if ($instance->operator) {
            $config['operator'] = $instance->operator;
        }
        if ($instance->placeholder) {
            $config['placeholder'] = $instance->placeholder;
        }
        if ($instance->priority !== null) {
            $config['priority'] = $instance->priority;
        }
        // searchFields are validated in ensureRelationConfig or getRelationFilterConfig,
        // so we don't override them here to preserve the validation

        return $config;
    }

    /**
     * Apply type-specific configuration overrides.
     * @param array<string, mixed> $config
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>
     */
    private function applyTypeSpecificOverrides(
        array $config,
        ColumnFilter $instance,
        ReflectionProperty $property,
        ClassMetadata $metadata,
        string $propertyName
    ): array {
        // If manually set to enum, ensure enumClass is set
        if ($instance->type === ColumnFilter::TYPE_ENUM && !isset($config['enumClass'])) {
            $config = $this->ensureEnumClassConfig($config, $property);
        }

        // If manually set to relation, ensure relation metadata is set
        if ($instance->type === ColumnFilter::TYPE_RELATION && !isset($config['targetEntity'])) {
            $config = $this->ensureRelationConfig($config, $instance, $metadata, $propertyName);
        }

        return $config;
    }

    /**
     * Ensure enum class configuration is set.
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function ensureEnumClassConfig(array $config, ReflectionProperty $property): array
    {
        $propertyType = $property->getType();
        if ($propertyType instanceof \ReflectionNamedType && !$propertyType->isBuiltin()) {
            $typeName = $propertyType->getName();
            if (enum_exists($typeName)) {
                $config['enumClass'] = $typeName;
            }
        }
        return $config;
    }

    /**
     * Ensure relation configuration is set.
     * @param array<string, mixed> $config
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>
     */
    private function ensureRelationConfig(
        array $config,
        ColumnFilter $instance,
        ClassMetadata $metadata,
        string $propertyName
    ): array {
        // Get relation metadata if this is actually an association
        if ($metadata->hasAssociation($propertyName)) {
            $targetClass = $metadata->getAssociationTargetClass($propertyName);
            $config['targetEntity'] = (new \ReflectionClass($targetClass))->getShortName();
            $config['targetClass'] = $targetClass;
            $targetMetadata = $this->em->getClassMetadata($targetClass);

            // Get searchFields from attribute or auto-detect
            $searchFields = !empty($instance->searchFields)
                ? $instance->searchFields
                : $this->getAutoDetectedSearchFields($targetMetadata);

            // Validate that searchFields actually exist in the target entity
            $validSearchFields = [];
            foreach ($searchFields as $field) {
                // Only include fields that exist as actual database fields in the target entity
                if ($targetMetadata->hasField($field)) {
                    $validSearchFields[] = $field;
                }
            }

            // If no valid search fields remain, fall back to auto-detected fields
            if (empty($validSearchFields)) {
                $validSearchFields = $this->getAutoDetectedSearchFields($targetMetadata);
            }

            $config['searchFields'] = $validSearchFields;
        }
        return $config;
    }

    /**
     * Get filter config for a regular field.
     * @param ClassMetadata<object> $metadata
     * @param \ReflectionAttribute<ColumnFilter>|null $columnFilter
     * @return array<string, mixed>
     */
    private function getFieldFilterConfig(
        ClassMetadata $metadata,
        string $fieldName,
        ReflectionProperty $property,
        ?\ReflectionAttribute $columnFilter
    ): array {
        $type = $metadata->getTypeOfField($fieldName);
        $config = [];

        // Check if it's an enum
        $propertyType = $property->getType();
        if ($propertyType instanceof \ReflectionNamedType && !$propertyType->isBuiltin()) {
            $typeName = $propertyType->getName();
            if (enum_exists($typeName)) {
                $multiple = $columnFilter?->newInstance()->multiple ?? false;
                return [
                    'type' => ColumnFilter::TYPE_ENUM,
                    'enumClass' => $typeName,
                    'operator' => $multiple ? 'IN' : '=',
                    'showAllOption' => $columnFilter?->newInstance()->showAllOption ?? true,
                    'multiple' => $multiple,
                ];
            }
        }

        // Map Doctrine types to filter types
        $config['type'] = match ($type) {
            'string', 'text' => ColumnFilter::TYPE_TEXT,
            'integer', 'smallint', 'bigint', 'decimal', 'float' => ColumnFilter::TYPE_NUMBER,
            'boolean' => ColumnFilter::TYPE_BOOLEAN,
            'date', 'datetime', 'datetimetz', 'time' => ColumnFilter::TYPE_DATE,
            default => ColumnFilter::TYPE_TEXT,
        };

        $config['operator'] = match ($config['type']) {
            ColumnFilter::TYPE_TEXT => 'LIKE',
            ColumnFilter::TYPE_NUMBER => '=',
            ColumnFilter::TYPE_BOOLEAN => '=',
            ColumnFilter::TYPE_DATE => 'BETWEEN',
            default => '=',
        };

        return $config;
    }

    /**
     * Get filter config for a relationship.
     * @param ClassMetadata<object> $metadata
     * @param \ReflectionAttribute<ColumnFilter>|null $columnFilter
     * @return array<string, mixed>
     */
    private function getRelationFilterConfig(
        ClassMetadata $metadata,
        string $associationName,
        ?\ReflectionAttribute $columnFilter
    ): array {
        $targetClass = $metadata->getAssociationTargetClass($associationName);
        $targetEntity = (new ReflectionClass($targetClass))->getShortName();
        $targetMetadata = $this->em->getClassMetadata($targetClass);

        // Get searchable fields from attribute or defaults
        $searchFields = !empty($columnFilter?->newInstance()->searchFields)
            ? $columnFilter->newInstance()->searchFields
            : (self::DEFAULT_SEARCH_FIELDS[$targetEntity] ?? null);

        // If no explicit or default fields, auto-detect based on display field priority
        if ($searchFields === null) {
            $searchFields = $this->getAutoDetectedSearchFields($targetMetadata);
        }

        // Validate that searchFields actually exist in the target entity
        $validSearchFields = [];
        foreach ($searchFields as $field) {
            // Only include fields that exist as actual database fields in the target entity
            if ($targetMetadata->hasField($field)) {
                $validSearchFields[] = $field;
            }
        }

        // If no valid search fields remain, fall back to display field priority then 'id'
        if (empty($validSearchFields)) {
            $validSearchFields = $this->getAutoDetectedSearchFields($targetMetadata);
        }

        return [
            'type' => ColumnFilter::TYPE_RELATION,
            'targetClass' => $targetClass,
            'targetEntity' => $targetEntity,
            'searchFields' => $validSearchFields,
            'operator' => 'LIKE',
        ];
    }

    /**
     * Auto-detect searchable fields based on display field priority.
     * Matches the display logic in _preview.html.twig (name → label → title → id).
     *
     * @param ClassMetadata<object> $targetMetadata
     * @return list<string>
     */
    private function getAutoDetectedSearchFields(ClassMetadata $targetMetadata): array
    {
        // Check for display fields in priority order (same as _preview.html.twig)
        foreach (self::DISPLAY_FIELD_PRIORITY as $field) {
            if ($targetMetadata->hasField($field)) {
                return [$field];
            }
        }

        // No display field found, fall back to 'id'
        return ['id'];
    }

    /**
     * Convert property name to human-readable label.
     */
    private function humanize(string $text): string
    {
        return ucfirst(trim(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $text))));
    }
}
