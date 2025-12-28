<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;

/**
 * Factory for creating DoctrineDataSource instances.
 *
 * Creates a data source for each Doctrine entity with the #[Admin] attribute.
 */
class DoctrineDataSourceFactory
{
    /** @var array<string, DoctrineDataSource>|null */
    private ?array $dataSourcesCache = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EntityDiscoveryService $entityDiscovery,
        private readonly EntityListQueryService $queryService,
        private readonly FilterMetadataProvider $filterMetadataProvider,
    ) {}

    /**
     * Create data sources for all entities with #[Admin] attribute.
     *
     * @return array<DoctrineDataSource>
     */
    public function createAll(): array
    {
        if ($this->dataSourcesCache !== null) {
            return array_values($this->dataSourcesCache);
        }

        $this->dataSourcesCache = [];

        foreach ($this->entityDiscovery->getAdminEntities() as $entityClass => $adminAttribute) {
            $dataSource = new DoctrineDataSource(
                entityClass: $entityClass,
                adminAttribute: $adminAttribute,
                em: $this->em,
                queryService: $this->queryService,
                filterMetadataProvider: $this->filterMetadataProvider,
            );

            $this->dataSourcesCache[$dataSource->getIdentifier()] = $dataSource;
        }

        return array_values($this->dataSourcesCache);
    }

    /**
     * Create a data source for a specific entity class.
     *
     * @param class-string $entityClass
     */
    public function create(string $entityClass): ?DoctrineDataSource
    {
        $adminAttribute = $this->entityDiscovery->getAdminAttribute($entityClass);

        if ($adminAttribute === null) {
            return null;
        }

        return new DoctrineDataSource(
            entityClass: $entityClass,
            adminAttribute: $adminAttribute,
            em: $this->em,
            queryService: $this->queryService,
            filterMetadataProvider: $this->filterMetadataProvider,
        );
    }

    /**
     * Get a data source by entity short name.
     */
    public function getByShortName(string $shortName): ?DoctrineDataSource
    {
        // Ensure cache is populated
        $this->createAll();

        return $this->dataSourcesCache[$shortName] ?? null;
    }

    /**
     * Clear the cached data sources.
     */
    public function clearCache(): void
    {
        $this->dataSourcesCache = null;
    }
}
