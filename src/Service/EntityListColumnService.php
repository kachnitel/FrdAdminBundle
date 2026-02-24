<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Kachnitel\AdminBundle\DataSource\DataSourceInterface;

/**
 * Handles column permission filtering for entity lists.
 *
 * Combines data source column/filter metadata with per-column permission
 * checks to produce the set of columns and filters a user is allowed to see.
 */
class EntityListColumnService
{
    public function __construct(
        private readonly ColumnPermissionService $columnPermissionService,
    ) {}

    /**
     * Get columns filtered by user permissions.
     *
     * For non-Doctrine data sources (entityClass = ''), all columns are returned.
     * For Doctrine entities, columns denied by #[ColumnPermission] are excluded.
     *
     * @return array<int|string, string>
     */
    public function getPermittedColumns(DataSourceInterface $dataSource, string $entityClass): array
    {
        $allColumns = array_keys($dataSource->getColumns());

        if ($entityClass === '') {
            return $allColumns;
        }

        $denied = $this->columnPermissionService->getDeniedColumns($entityClass);

        return array_values(array_filter(
            $allColumns,
            fn(string $col) => !in_array($col, $denied, true)
        ));
    }

    /**
     * Get filter metadata filtered by user permissions.
     *
     * Only returns filters for columns the user is permitted to see.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPermittedFilters(DataSourceInterface $dataSource, string $entityClass): array
    {
        $permittedColumns = $this->getPermittedColumns($dataSource, $entityClass);
        $result = [];

        foreach ($dataSource->getFilters() as $name => $filter) {
            if (in_array($name, $permittedColumns, true)) {
                $result[$name] = $filter->toArray();
            }
        }

        return $result;
    }
}
