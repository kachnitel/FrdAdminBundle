<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Interface for services that provide multiple data sources.
 *
 * This allows external bundles to provide their own data sources
 * without needing to register each one individually.
 *
 * Example: auditor-bundle can implement this to provide
 * an AuditDataSource for each audited entity.
 */
interface DataSourceProviderInterface
{
    /**
     * Get all data sources provided by this service.
     *
     * @return iterable<DataSourceInterface>
     */
    public function getDataSources(): iterable;
}
