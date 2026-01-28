<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service\Traits;

use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use ReflectionProperty;

/**
 * Handles filter configuration for regular fields (non-associations).
 */
trait FieldFilterConfigTrait
{
    /**
     * Get filter config for a field (non-association property).
     *
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>
     */
    private function getFieldFilterConfig(
        ClassMetadata $metadata,
        string $fieldName,
        ReflectionProperty $property,
        ?ColumnFilter $attribute
    ): array {
        $enumClass = $this->getEnumClass($property);
        if ($enumClass !== null) {
            $multiple = $attribute !== null && $attribute->multiple;
            return [
                'type' => ColumnFilter::TYPE_ENUM,
                'enumClass' => $enumClass,
                'operator' => $multiple ? 'IN' : '=',
                'showAllOption' => $attribute === null || $attribute->showAllOption,
                'multiple' => $multiple,
            ];
        }

        $type = $this->mapDoctrineTypeToFilterType($metadata->getTypeOfField($fieldName));

        return [
            'type' => $type,
            'operator' => $this->getDefaultOperator($type),
        ];
    }

    /**
     * Get the enum class name if the property type is an enum.
     *
     * @return class-string|null
     */
    private function getEnumClass(ReflectionProperty $property): ?string
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $typeName = $type->getName();

        return enum_exists($typeName) ? $typeName : null;
    }

    /**
     * Map Doctrine field type to filter type.
     */
    private function mapDoctrineTypeToFilterType(?string $doctrineType): string
    {
        return match ($doctrineType) {
            'string', 'text' => ColumnFilter::TYPE_TEXT,
            'integer', 'smallint', 'bigint', 'decimal', 'float' => ColumnFilter::TYPE_NUMBER,
            'boolean' => ColumnFilter::TYPE_BOOLEAN,
            'date', 'datetime', 'datetimetz', 'time' => ColumnFilter::TYPE_DATE,
            default => ColumnFilter::TYPE_TEXT,
        };
    }

    /**
     * Get default operator for a filter type.
     */
    private function getDefaultOperator(string $filterType): string
    {
        return match ($filterType) {
            ColumnFilter::TYPE_TEXT => 'LIKE',
            ColumnFilter::TYPE_DATE => 'BETWEEN',
            default => '=',
        };
    }
}
