<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Represents a group of columns that share a single composite table cell.
 *
 * When `DataSourceInterface::getColumnGroups()` returns a `ColumnGroup` slot,
 * the EntityList renders a single `<th>`/`<td>` containing stacked rows — one
 * per sub-column — instead of individual cells for each column.
 *
 * Groups are built automatically by `DoctrineDataSource` from the
 * `#[AdminColumn(group: '...')]` attribute. Custom data sources may construct
 * and return `ColumnGroup` instances directly from `getColumnGroups()`.
 *
 * @see DataSourceInterface::getColumnGroups()
 */
readonly class ColumnGroup
{
    /**
     * @param string                        $id      Group identifier (e.g. 'name_block')
     * @param string                        $label   Human-readable group label for the composite header
     * @param array<string, ColumnMetadata> $columns Sub-columns keyed by column name, in display order
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $columns,
    ) {}
}
