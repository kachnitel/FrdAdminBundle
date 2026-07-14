<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnAttributeProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnTypeMapper;
use Kachnitel\AdminBundle\DataSource\DoctrineCustomColumnProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSourceFactory;
use Kachnitel\AdminBundle\DataSource\DoctrineFilterConverter;
use Kachnitel\AdminBundle\DataSource\DoctrineItemValueResolver;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\ConfiguredEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class DoctrineDataSourceFactoryTest extends TestCase
{
    /** @var EntityDiscoveryService&MockObject */
    private EntityDiscoveryService $entityDiscovery;

    private DoctrineDataSourceFactory $factory;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $queryService = $this->createStub(EntityListQueryService::class);
        $filterMetadataProvider = $this->createStub(FilterMetadataProvider::class);

        $customColumnProvider = $this->createMock(DoctrineCustomColumnProvider::class);
        $customColumnProvider->method('getCustomColumns')->willReturn([]);

        $columnAttrProvider = $this->createMock(DoctrineColumnAttributeProvider::class);
        $columnAttrProvider->method('getColumnAttributes')->willReturn([]);

        $columnTypeMapper = $this->createMock(DoctrineColumnTypeMapper::class);
        $columnTypeMapper->method('getColumnType')->willReturn('string');

        $this->factory = new DoctrineDataSourceFactory(
            $em,
            $this->entityDiscovery,
            $queryService,
            $filterMetadataProvider,
            $customColumnProvider,
            $columnAttrProvider,
            $columnTypeMapper,
            new DoctrineFilterConverter(),
            new DoctrineItemValueResolver(),
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
            TestEntity::class      => $admin1,
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
            TestEntity::class      => $admin1,
            ConfiguredEntity::class => $admin2,
        ]);

        $result = $this->factory->createAll();

        $identifiers = array_map(fn ($ds) => $ds->getIdentifier(), $result);

        $this->assertContains('TestEntity', $identifiers);
        $this->assertContains('ConfiguredEntity', $identifiers);
    }

    public function testCreateAllCachesResults(): void
    {
        $admin = new Admin();

        $this->entityDiscovery->expects($this->once())
            ->method('getAdminEntities')
            ->willReturn([TestEntity::class => $admin]);

        // Call twice — discovery should only be called once
        $this->factory->createAll();
        $this->factory->createAll();
    }

    public function testClearCacheAllowsRecreation(): void
    {
        $admin = new Admin();

        $this->entityDiscovery->expects($this->exactly(2))
            ->method('getAdminEntities')
            ->willReturn([TestEntity::class => $admin]);

        $this->factory->createAll();
        $this->factory->clearCache();
        $this->factory->createAll();
    }

    public function testCreateReturnsNullForEntityWithoutAdminAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);

        $result = $this->factory->create(TestEntity::class);

        $this->assertNotInstanceOf(\Kachnitel\AdminBundle\DataSource\DoctrineDataSource::class, $result);
    }

    public function testCreateReturnsDataSourceForEntityWithAdminAttribute(): void
    {
        $admin = new Admin(label: 'Test');
        $this->entityDiscovery->method('getAdminAttribute')->willReturn($admin);

        $result = $this->factory->create(TestEntity::class);

        $this->assertInstanceOf(DoctrineDataSource::class, $result);
    }

    public function testCreateForClassCreatesDataSourceWithDefaultAdminWhenNoAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);

        $result = $this->factory->createForClass(TestEntity::class);

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertInstanceOf(DoctrineDataSource::class, $result);
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

        $this->assertNotInstanceOf(\Kachnitel\AdminBundle\DataSource\DoctrineDataSource::class, $result);
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

        $this->entityDiscovery->expects($this->once())->method('getAdminAttribute')
            ->with(TestEntity::class)
            ->willReturn($admin);

        $dataSource = $this->factory->create(TestEntity::class);

        $this->assertInstanceOf(\Kachnitel\AdminBundle\DataSource\DoctrineDataSource::class, $dataSource);
        $this->assertSame(TestEntity::class, $dataSource->getEntityClass());
    }

    public function testCreatedDataSourceHasCorrectAdminAttribute(): void
    {
        $admin = new Admin(
            label: 'Custom Label',
            icon: 'fa-custom',
            sortBy: 'name',
            sortDirection: 'ASC'
        );

        $this->entityDiscovery->expects($this->once())->method('getAdminAttribute')
            ->with(TestEntity::class)
            ->willReturn($admin);

        $dataSource = $this->factory->create(TestEntity::class);

        $this->assertInstanceOf(\Kachnitel\AdminBundle\DataSource\DoctrineDataSource::class, $dataSource);
        $this->assertSame('Custom Label', $dataSource->getLabel());
        $this->assertSame('fa-custom', $dataSource->getIcon());
        $this->assertSame('name', $dataSource->getDefaultSortBy());
        $this->assertSame('ASC', $dataSource->getDefaultSortDirection());
    }
}
