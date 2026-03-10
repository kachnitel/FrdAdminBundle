<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Default implementation of `DataSourceInterface::getColumnGroups()` for data sources
 * that do not need composite grouped columns.
 *
 * Returns all column names as a flat list of strings — the same rendering
 * behaviour as before composite columns were introduced.
 *
 * ## Usage
 *
 * Any class implementing `DataSourceInterface` that does not declare grouped columns
 * can include this trait to satisfy the interface contract without additional code:
 *
 * ```php
 * class MyDataSource implements DataSourceInterface
 * {
 *     use FlatColumnGroupsTrait;
 *
 *     // ... other required methods
 * }
 * ```
 *
 * @see DataSourceInterface::getColumnGroups()
 */
trait FlatColumnGroupsTrait
{
    /**
     * @return list<string>
     */
    public function getColumnGroups(): array
    {
        return array_keys($this->getColumns());
    }
}
