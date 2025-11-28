<?php

namespace Frd\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Frd\AdminBundle\Attribute\ColumnFilter;
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

    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Get filter metadata for all filterable columns of an entity.
     *
     * @param string $entityClass Full class name
     * @return array<string, array> Map of property name => filter metadata
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
        uasort($filters, fn($a, $b) => ($a['priority'] ?? 999) <=> ($b['priority'] ?? 999));

        return $filters;
    }

    /**
     * Get filter configuration for a specific property.
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
            $instance = $columnFilter->newInstance();
            if ($instance->type) {
                $config['type'] = $instance->type;
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
        }

        return $config;
    }

    /**
     * Get filter config for a regular field.
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
        if ($propertyType && !$propertyType->isBuiltin()) {
            $typeName = $propertyType->getName();
            if (enum_exists($typeName)) {
                return [
                    'type' => ColumnFilter::TYPE_ENUM,
                    'enumClass' => $typeName,
                    'operator' => '=',
                    'showAllOption' => $columnFilter?->newInstance()->showAllOption ?? true,
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
            ColumnFilter::TYPE_DATE => '>=',
            default => '=',
        };

        return $config;
    }

    /**
     * Get filter config for a relationship.
     */
    private function getRelationFilterConfig(
        ClassMetadata $metadata,
        string $associationName,
        ?\ReflectionAttribute $columnFilter
    ): array {
        $targetClass = $metadata->getAssociationTargetClass($associationName);
        $targetEntity = (new ReflectionClass($targetClass))->getShortName();

        // Get searchable fields from attribute or defaults
        $searchFields = $columnFilter?->newInstance()->searchFields
            ?? self::DEFAULT_SEARCH_FIELDS[$targetEntity]
            ?? ['name', 'id'];

        return [
            'type' => ColumnFilter::TYPE_RELATION,
            'targetClass' => $targetClass,
            'targetEntity' => $targetEntity,
            'searchFields' => $searchFields,
            'operator' => 'LIKE',
        ];
    }

    /**
     * Convert property name to human-readable label.
     */
    private function humanize(string $text): string
    {
        return ucfirst(trim(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $text))));
    }
}
