<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Registry for all available data sources.
 *
 * Collects data sources from:
 * 1. Doctrine entities with #[Admin] attribute (via DoctrineDataSourceFactory)
 * 2. Custom data sources tagged with 'admin.data_source'
 * 3. Data source providers tagged with 'admin.data_source_provider'
 */
class DataSourceRegistry
{
    /** @var array<string, DataSourceInterface>|null Cached data sources */
    private ?array $dataSources = null;

    /**
     * @param iterable<DataSourceInterface> $customDataSources Custom data sources (tagged services)
     * @param iterable<DataSourceProviderInterface> $dataSourceProviders Providers of multiple data sources
     * @param DoctrineDataSourceFactory $doctrineFactory Factory for Doctrine entity data sources
     */
    public function __construct(
        #[TaggedIterator('admin.data_source')]
        private readonly iterable $customDataSources,
        #[TaggedIterator('admin.data_source_provider')]
        private readonly iterable $dataSourceProviders,
        private readonly DoctrineDataSourceFactory $doctrineFactory,
    ) {}

    /**
     * Get all available data sources.
     *
     * @return array<string, DataSourceInterface> Map of identifier => data source
     */
    public function all(): array
    {
        if ($this->dataSources !== null) {
            return $this->dataSources;
        }

        $this->dataSources = [];

        // Add Doctrine entity data sources
        foreach ($this->doctrineFactory->createAll() as $dataSource) {
            $this->dataSources[$dataSource->getIdentifier()] = $dataSource;
        }

        // Add data sources from providers (e.g., audit data sources)
        foreach ($this->dataSourceProviders as $provider) {
            foreach ($provider->getDataSources() as $dataSource) {
                $this->dataSources[$dataSource->getIdentifier()] = $dataSource;
            }
        }

        // Add custom data sources (can override Doctrine and provider ones)
        foreach ($this->customDataSources as $dataSource) {
            $this->dataSources[$dataSource->getIdentifier()] = $dataSource;
        }

        return $this->dataSources;
    }

    /**
     * Get a data source by identifier.
     */
    public function get(string $identifier): ?DataSourceInterface
    {
        return $this->all()[$identifier] ?? null;
    }

    /**
     * Check if a data source exists.
     */
    public function has(string $identifier): bool
    {
        return isset($this->all()[$identifier]);
    }

    /**
     * Get all data source identifiers.
     *
     * @return array<string>
     */
    public function getIdentifiers(): array
    {
        return array_keys($this->all());
    }

    /**
     * Clear the cached data sources.
     *
     * Useful for testing or when data sources change dynamically.
     */
    public function clearCache(): void
    {
        $this->dataSources = null;
        $this->doctrineFactory->clearCache();
    }
}
