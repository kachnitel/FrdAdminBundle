<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSourceFactory;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\ConfiguredEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DoctrineDataSourceFactoryTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EntityDiscoveryService&MockObject */
    private EntityDiscoveryService $entityDiscovery;

    /** @var EntityListQueryService&MockObject */
    private EntityListQueryService $queryService;

    /** @var FilterMetadataProvider&MockObject */
    private FilterMetadataProvider $filterMetadataProvider;

    private DoctrineDataSourceFactory $factory;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->queryService = $this->createMock(EntityListQueryService::class);
        $this->filterMetadataProvider = $this->createMock(FilterMetadataProvider::class);

        $this->factory = new DoctrineDataSourceFactory(
            $this->em,
            $this->entityDiscovery,
            $this->queryService,
            $this->filterMetadataProvider
        );
    }

    public function testCreateAllReturnsEmptyArrayWhenNoEntities(): void
    {
        $this->entityDiscovery->method('getAdminEntities')->willReturn([]);

        $result = $this->factory->createAll();

        $this->assertSame([], $result);
    }

    public function testCreateAllCreatesDataSourceForEachEntity(): void
    {
        $admin1 = new Admin();
        $admin2 = new Admin(label: 'Configured');

        $this->entityDiscovery->method('getAdminEntities')->willReturn([
            TestEntity::class => $admin1,
            ConfiguredEntity::class => $admin2,
        ]);

        $result = $this->factory->createAll();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(DoctrineDataSource::class, $result);
    }

    public function testCreateAllReturnsDataSourcesWithCorrectIdentifiers(): void
    {
        $admin1 = new Admin();
        $admin2 = new Admin();

        $this->entityDiscovery->method('getAdminEntities')->willReturn([
            TestEntity::class => $admin1,
            ConfiguredEntity::class => $admin2,
        ]);

        $result = $this->factory->createAll();

        $identifiers = array_map(fn($ds) => $ds->getIdentifier(), $result);

        $this->assertContains('TestEntity', $identifiers);
        $this->assertContains('ConfiguredEntity', $identifiers);
    }

    public function testCreateAllCachesResults(): void
    {
        $admin = new Admin();

        $this->entityDiscovery->expects($this->once())
            ->method('getAdminEntities')
            ->willReturn([TestEntity::class => $admin]);

        // Call multiple times
        $this->factory->createAll();
        $this->factory->createAll();
        $this->factory->createAll();

        // getAdminEntities should only be called once
    }

    public function testCreateReturnsDataSourceForEntityWithAdminAttribute(): void
    {
        $admin = new Admin(label: 'Test Entity');

        $this->entityDiscovery->method('getAdminAttribute')
            ->with(TestEntity::class)
            ->willReturn($admin);

        $result = $this->factory->create(TestEntity::class);

        $this->assertInstanceOf(DoctrineDataSource::class, $result);
        $this->assertSame('TestEntity', $result->getIdentifier());
        $this->assertSame('Test Entity', $result->getLabel());
    }

    public function testCreateReturnsNullForEntityWithoutAdminAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->with('App\\Entity\\NonAdminEntity')
            ->willReturn(null);

        /** @phpstan-ignore argument.type (intentionally testing with non-existent class) */
        $result = $this->factory->create('App\\Entity\\NonAdminEntity');

        $this->assertNull($result);
    }

    public function testGetByShortNameReturnsDataSource(): void
    {
        $admin = new Admin();

        $this->entityDiscovery->method('getAdminEntities')->willReturn([
            TestEntity::class => $admin,
        ]);

        $result = $this->factory->getByShortName('TestEntity');

        $this->assertInstanceOf(DoctrineDataSource::class, $result);
        $this->assertSame('TestEntity', $result->getIdentifier());
    }

    public function testGetByShortNameReturnsNullForNonExistent(): void
    {
        $this->entityDiscovery->method('getAdminEntities')->willReturn([]);

        $result = $this->factory->getByShortName('NonExistent');

        $this->assertNull($result);
    }

    public function testGetByShortNamePopulatesCache(): void
    {
        $admin = new Admin();

        $this->entityDiscovery->expects($this->once())
            ->method('getAdminEntities')
            ->willReturn([TestEntity::class => $admin]);

        // getByShortName should populate cache
        $this->factory->getByShortName('TestEntity');

        // Subsequent createAll should use cache
        $this->factory->createAll();
    }

    public function testClearCacheResetsCache(): void
    {
        $admin = new Admin();

        $this->entityDiscovery->expects($this->exactly(2))
            ->method('getAdminEntities')
            ->willReturn([TestEntity::class => $admin]);

        // First call
        $this->factory->createAll();

        // Clear cache
        $this->factory->clearCache();

        // Second call should hit getAdminEntities again
        $this->factory->createAll();
    }

    public function testCreatedDataSourceHasCorrectEntityClass(): void
    {
        $admin = new Admin();

        $this->entityDiscovery->method('getAdminAttribute')
            ->with(TestEntity::class)
            ->willReturn($admin);

        $result = $this->factory->create(TestEntity::class);

        $this->assertSame(TestEntity::class, $result->getEntityClass());
    }

    public function testCreatedDataSourceHasCorrectAdminAttribute(): void
    {
        $admin = new Admin(
            label: 'Custom Label',
            icon: 'fa-custom',
            sortBy: 'name',
            sortDirection: 'ASC'
        );

        $this->entityDiscovery->method('getAdminAttribute')
            ->with(TestEntity::class)
            ->willReturn($admin);

        $result = $this->factory->create(TestEntity::class);

        $this->assertSame('Custom Label', $result->getLabel());
        $this->assertSame('fa-custom', $result->getIcon());
        $this->assertSame('name', $result->getDefaultSortBy());
        $this->assertSame('ASC', $result->getDefaultSortDirection());
    }
}
