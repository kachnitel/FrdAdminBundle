<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;

/**
 * Factory for creating DoctrineDataSource instances.
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
        private readonly DoctrineCustomColumnProvider $customColumnProvider,
        private readonly DoctrineColumnAttributeProvider $columnAttributeProvider,
        private readonly DoctrineColumnTypeMapper $columnTypeMapper,
        private readonly DoctrineFilterConverter $filterConverter,
        private readonly DoctrineItemValueResolver $itemValueResolver,
    ) {}

    /**
     * @return array<DoctrineDataSource>
     */
    public function createAll(): array
    {
        if ($this->dataSourcesCache !== null) {
            return array_values($this->dataSourcesCache);
        }

        $this->dataSourcesCache = [];

        foreach ($this->entityDiscovery->getAdminEntities() as $entityClass => $adminAttribute) {
            $dataSource = $this->buildDataSource($entityClass, $adminAttribute);
            $this->dataSourcesCache[$dataSource->getIdentifier()] = $dataSource;
        }

        return array_values($this->dataSourcesCache);
    }

    /**
     * @param class-string $entityClass
     */
    public function create(string $entityClass): ?DoctrineDataSource
    {
        $adminAttribute = $this->entityDiscovery->getAdminAttribute($entityClass);

        if ($adminAttribute === null) {
            return null;
        }

        return $this->buildDataSource($entityClass, $adminAttribute);
    }

    /**
     * @param class-string $entityClass
     */
    public function createForClass(string $entityClass): DoctrineDataSource
    {
        $adminAttribute = $this->entityDiscovery->getAdminAttribute($entityClass)
            ?? new Admin();

        return $this->buildDataSource($entityClass, $adminAttribute);
    }

    public function getByShortName(string $shortName): ?DoctrineDataSource
    {
        $this->createAll();

        return $this->dataSourcesCache[$shortName] ?? null;
    }

    public function clearCache(): void
    {
        $this->dataSourcesCache = null;
    }

    /** @param class-string $entityClass */
    private function buildDataSource(string $entityClass, Admin $adminAttribute): DoctrineDataSource
    {
        return new DoctrineDataSource(
            entityClass: $entityClass,
            adminAttribute: $adminAttribute,
            em: $this->em,
            queryService: $this->queryService,
            filterMetadataProvider: $this->filterMetadataProvider,
            customColumnProvider: $this->customColumnProvider,
            columnAttributeProvider: $this->columnAttributeProvider,
            columnTypeMapper: $this->columnTypeMapper,
            filterConverter: $this->filterConverter,
            itemValueResolver: $this->itemValueResolver,
        );
    }
}
