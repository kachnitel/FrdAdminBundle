<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Maps Doctrine field/association types to admin column type strings.
 *
 * Extracted from DoctrineDataSource to keep that class within PHPMD's
 * ExcessiveClassComplexity threshold. This service is a pure, stateless
 * mapping concern with no framework dependencies.
 */
class DoctrineColumnTypeMapper
{
    /**
     * Resolve the admin column type for a given Doctrine field or association.
     *
     * Returns one of: integer, decimal, boolean, date, datetime, time, text,
     * json, array, string, collection, relation.
     *
     * @param ClassMetadata<object> $metadata
     */
    public function getColumnType(ClassMetadata $metadata, string $column): string
    {
        if ($metadata->hasField($column)) {
            return $this->mapFieldType($metadata->getTypeOfField($column) ?? 'string');
        }

        if ($metadata->hasAssociation($column)) {
            return $metadata->isCollectionValuedAssociation($column) ? 'collection' : 'relation';
        }

        return 'string';
    }

    /**
     * Map a raw Doctrine field type string to an admin column type string.
     */
    private function mapFieldType(string $type): string
    {
        return match ($type) {
            'integer', 'smallint', 'bigint'                                    => 'integer',
            'decimal', 'float'                                                 => 'decimal',
            'boolean'                                                          => 'boolean',
            'date', 'date_immutable'                                           => 'date',
            'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable' => 'datetime',
            'time', 'time_immutable'                                           => 'time',
            'text'                                                             => 'text',
            'json', 'json_array'                                               => 'json',
            'array', 'simple_array'                                            => 'array',
            default                                                            => 'string',
        };
    }
}
