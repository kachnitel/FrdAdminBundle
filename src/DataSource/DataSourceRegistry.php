<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Kachnitel\DataSourceContracts\DataSourceInterface as ContractsDataSourceInterface;
use Kachnitel\DataSourceContracts\DataSourceProviderInterface as ContractsProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Registry for all available data sources.
 *
 * Collects data sources from:
 * 1. Doctrine entities with #[Admin] attribute (via DoctrineDataSourceFactory)
 * 2. Custom DataSourceInterface implementations (auto-discovered)
 * 3. DataSourceProviderInterface implementations (auto-discovered)
 */
class DataSourceRegistry
{
    /** @var array<string, ContractsDataSourceInterface>|null Cached data sources */
    private ?array $dataSources = null;

    /**
     * @param iterable<ContractsDataSourceInterface> $customDataSources Custom data source implementations
     * @param iterable<ContractsProviderInterface>   $dataSourceProviders Providers of multiple data sources
     * @param DoctrineDataSourceFactory              $doctrineFactory Factory for Doctrine entity data sources
     */
    public function __construct(
        #[AutowireIterator(ContractsDataSourceInterface::class, exclude: [DoctrineDataSource::class])]
        private readonly iterable $customDataSources,
        #[AutowireIterator(ContractsProviderInterface::class)]
        private readonly iterable $dataSourceProviders,
        private readonly DoctrineDataSourceFactory $doctrineFactory,
    ) {}

    /**
     * Get all available data sources.
     *
     * @return array<string, ContractsDataSourceInterface> Map of identifier => data source
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
    public function get(string $identifier): ?ContractsDataSourceInterface
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
     * Resolve a data source using a fallback chain:
     * 1. dataSourceId (explicit registry lookup)
     * 2. entityShortClass (registry lookup for Doctrine entities)
     * 3. entityClass (on-demand DoctrineDataSource creation)
     *
     * @throws \RuntimeException if no data source can be resolved
     */
    public function resolve(?string $dataSourceId, string $entityShortClass, string $entityClass): ContractsDataSourceInterface
    {
        // 1. Explicit data source ID
        if ($dataSourceId !== null) {
            $dataSource = $this->get($dataSourceId);
            if ($dataSource !== null) {
                return $dataSource;
            }
            throw new \RuntimeException(sprintf('Data source "%s" not found.', $dataSourceId));
        }

        // 2. Entity short class from registry
        if ($entityShortClass !== '') {
            $dataSource = $this->get($entityShortClass);
            if ($dataSource !== null) {
                return $dataSource;
            }
        }

        // 3. On-demand Doctrine data source
        if ($entityClass !== '') {
            return $this->doctrineFactory->createForClass($entityClass);
        }

        throw new \RuntimeException('No data source or entity class configured.');
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
