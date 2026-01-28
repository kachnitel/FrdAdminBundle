<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Service\Traits\AssociationFilterConfigTrait;
use Kachnitel\AdminBundle\Service\Traits\FieldFilterConfigTrait;
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
    use FieldFilterConfigTrait;
    use AssociationFilterConfigTrait;

    public function __construct(
        private EntityManagerInterface $em
    ) {}

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }

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

        foreach ($metadata->getFieldNames() as $fieldName) {
            $this->addFilterIfEnabled($filters, $reflection->getProperty($fieldName), $metadata, $fieldName);
        }

        foreach ($metadata->getAssociationNames() as $associationName) {
            $property = $reflection->getProperty($associationName);

            // Collection associations require explicit ColumnFilter attribute
            if ($metadata->isCollectionValuedAssociation($associationName)) {
                if (empty($property->getAttributes(ColumnFilter::class))) {
                    continue;
                }
            }

            $this->addFilterIfEnabled($filters, $property, $metadata, $associationName);
        }

        uasort($filters, fn(array $a, array $b): int => ($a['priority'] ?? 999) <=> ($b['priority'] ?? 999));

        return $filters;
    }

    /**
     * Add filter config to the filters array if enabled.
     *
     * @param array<string, array<string, mixed>> $filters
     * @param ClassMetadata<object> $metadata
     */
    private function addFilterIfEnabled(
        array &$filters,
        ReflectionProperty $property,
        ClassMetadata $metadata,
        string $propertyName
    ): void {
        $config = $this->getFilterConfig($property, $metadata, $propertyName);

        if ($config !== null && $config['enabled']) {
            $filters[$propertyName] = $config;
        }
    }

    /**
     * Get filter configuration for a specific property.
     *
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>|null
     */
    private function getFilterConfig(
        ReflectionProperty $property,
        ClassMetadata $metadata,
        string $propertyName
    ): ?array {
        $attribute = $this->getColumnFilterAttribute($property);

        if ($attribute !== null && !$attribute->enabled) {
            return null;
        }

        $config = [
            'property' => $propertyName,
            'label' => ($attribute !== null && $attribute->label !== null) ? $attribute->label : $this->humanize($propertyName),
            'enabled' => true,
        ];

        $config = array_merge($config, $this->getTypeSpecificConfig($metadata, $propertyName, $property, $attribute));

        if ($attribute !== null) {
            $config = $this->applyAttributeOverrides($config, $attribute, $property, $metadata, $propertyName);
        }

        return $config;
    }

    /**
     * Get the ColumnFilter attribute instance if present.
     */
    private function getColumnFilterAttribute(ReflectionProperty $property): ?ColumnFilter
    {
        $attributes = $property->getAttributes(ColumnFilter::class);

        return !empty($attributes) ? $attributes[0]->newInstance() : null;
    }

    /**
     * Get type-specific filter configuration based on property type.
     *
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>
     */
    private function getTypeSpecificConfig(
        ClassMetadata $metadata,
        string $propertyName,
        ReflectionProperty $property,
        ?ColumnFilter $attribute
    ): array {
        if (!$metadata->hasAssociation($propertyName)) {
            return $this->getFieldFilterConfig($metadata, $propertyName, $property, $attribute);
        }

        return $this->getAssociationFilterConfig($metadata, $propertyName, $attribute);
    }

    /**
     * Apply ColumnFilter attribute overrides to the configuration.
     *
     * @param array<string, mixed> $config
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>
     */
    private function applyAttributeOverrides(
        array $config,
        ColumnFilter $attribute,
        ReflectionProperty $property,
        ClassMetadata $metadata,
        string $propertyName
    ): array {
        if ($attribute->type !== null) {
            $config['type'] = $attribute->type;
            $config = $this->applyTypeSpecificOverrides($config, $attribute, $property, $metadata, $propertyName);
        }

        if ($attribute->operator !== null) {
            $config['operator'] = $attribute->operator;
        }

        if ($attribute->placeholder !== null) {
            $config['placeholder'] = $attribute->placeholder;
        }

        if ($attribute->priority !== null) {
            $config['priority'] = $attribute->priority;
        }

        return $config;
    }

    /**
     * Apply type-specific configuration overrides when type is manually set.
     *
     * @param array<string, mixed> $config
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>
     */
    private function applyTypeSpecificOverrides(
        array $config,
        ColumnFilter $attribute,
        ReflectionProperty $property,
        ClassMetadata $metadata,
        string $propertyName
    ): array {
        if ($attribute->type === ColumnFilter::TYPE_ENUM && !isset($config['enumClass'])) {
            $enumClass = $this->getEnumClass($property);
            if ($enumClass !== null) {
                $config['enumClass'] = $enumClass;
            }
        }

        $isAssociationType = in_array($attribute->type, [ColumnFilter::TYPE_RELATION, ColumnFilter::TYPE_COLLECTION], true);
        if ($isAssociationType && !isset($config['targetEntity']) && $metadata->hasAssociation($propertyName)) {
            $config = array_merge($config, $this->getAssociationFilterConfig($metadata, $propertyName, $attribute));
        }

        return $config;
    }

    /**
     * Convert property name to human-readable label.
     */
    private function humanize(string $text): string
    {
        return ucfirst(trim(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $text))));
    }
}
