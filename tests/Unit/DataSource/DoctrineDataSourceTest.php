<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DoctrineDataSourceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EntityListQueryService&MockObject */
    private EntityListQueryService $queryService;

    /** @var FilterMetadataProvider&MockObject */
    private FilterMetadataProvider $filterMetadataProvider;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->queryService = $this->createMock(EntityListQueryService::class);
        $this->filterMetadataProvider = $this->createMock(FilterMetadataProvider::class);
        $this->metadata = $this->createMock(ClassMetadata::class);

        $this->em->method('getClassMetadata')
            ->with(TestEntity::class)
            ->willReturn($this->metadata);
    }

    private function createDataSource(?Admin $admin = null): DoctrineDataSource
    {
        return new DoctrineDataSource(
            TestEntity::class,
            $admin ?? new Admin(),
            $this->em,
            $this->queryService,
            $this->filterMetadataProvider
        );
    }

    public function testGetIdentifierReturnsShortClassName(): void
    {
        $dataSource = $this->createDataSource();

        $this->assertSame('TestEntity', $dataSource->getIdentifier());
    }

    public function testGetLabelReturnsAdminAttributeLabel(): void
    {
        $admin = new Admin(label: 'Test Entities');
        $dataSource = $this->createDataSource($admin);

        $this->assertSame('Test Entities', $dataSource->getLabel());
    }

    public function testGetLabelFallsBackToShortClassName(): void
    {
        $dataSource = $this->createDataSource();

        $this->assertSame('TestEntity', $dataSource->getLabel());
    }

    public function testGetIconReturnsAdminAttributeIcon(): void
    {
        $admin = new Admin(icon: 'fa-test');
        $dataSource = $this->createDataSource($admin);

        $this->assertSame('fa-test', $dataSource->getIcon());
    }

    public function testGetIconReturnsNullByDefault(): void
    {
        $dataSource = $this->createDataSource();

        $this->assertNull($dataSource->getIcon());
    }

    public function testGetColumnsReturnsColumnMetadata(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturnCallback(fn($f) => match($f) {
            'id' => 'integer',
            'name' => 'string',
        });

        $dataSource = $this->createDataSource();
        $columns = $dataSource->getColumns();

        $this->assertCount(2, $columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertInstanceOf(ColumnMetadata::class, $columns['id']);
        $this->assertInstanceOf(ColumnMetadata::class, $columns['name']);
    }

    public function testGetColumnsUsesAdminAttributeColumns(): void
    {
        $admin = new Admin(columns: ['name', 'status']);
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'status', 'createdAt']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $dataSource = $this->createDataSource($admin);
        $columns = $dataSource->getColumns();

        $this->assertCount(2, $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('status', $columns);
        $this->assertArrayNotHasKey('id', $columns);
        $this->assertArrayNotHasKey('createdAt', $columns);
    }

    public function testGetColumnsExcludesConfiguredColumns(): void
    {
        $admin = new Admin(excludeColumns: ['createdAt', 'updatedAt']);
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'createdAt', 'updatedAt']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $dataSource = $this->createDataSource($admin);
        $columns = $dataSource->getColumns();

        $this->assertCount(2, $columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayNotHasKey('createdAt', $columns);
        $this->assertArrayNotHasKey('updatedAt', $columns);
    }

    public function testGetColumnsDetectsCorrectTypes(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([
            'id', 'name', 'active', 'createdAt', 'price', 'data'
        ]);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturnCallback(fn($f) => match($f) {
            'id' => 'integer',
            'name' => 'string',
            'active' => 'boolean',
            'createdAt' => 'datetime',
            'price' => 'decimal',
            'data' => 'json',
        });

        $dataSource = $this->createDataSource();
        $columns = $dataSource->getColumns();

        $this->assertSame('integer', $columns['id']->type);
        $this->assertSame('string', $columns['name']->type);
        $this->assertSame('boolean', $columns['active']->type);
        $this->assertSame('datetime', $columns['createdAt']->type);
        $this->assertSame('decimal', $columns['price']->type);
        $this->assertSame('json', $columns['data']->type);
    }

    public function testGetColumnsHandlesAssociations(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn(['category', 'tags']);
        $this->metadata->method('hasField')->willReturnCallback(fn($f) => $f === 'id');
        $this->metadata->method('hasAssociation')->willReturnCallback(fn($f) => in_array($f, ['category', 'tags']));
        $this->metadata->method('isCollectionValuedAssociation')->willReturnCallback(fn($f) => $f === 'tags');
        $this->metadata->method('getTypeOfField')->willReturn('integer');

        $dataSource = $this->createDataSource();
        $columns = $dataSource->getColumns();

        $this->assertSame('relation', $columns['category']->type);
        $this->assertSame('collection', $columns['tags']->type);
    }

    public function testGetColumnsCollectionsAreNotSortable(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['tags']);
        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(true);

        $dataSource = $this->createDataSource();
        $columns = $dataSource->getColumns();

        $this->assertFalse($columns['tags']->sortable);
    }

    public function testGetColumnsCachesResults(): void
    {
        $this->metadata->expects($this->once())
            ->method('getFieldNames')
            ->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('integer');

        $dataSource = $this->createDataSource();

        // Call multiple times
        $dataSource->getColumns();
        $dataSource->getColumns();
        $dataSource->getColumns();
    }

    public function testGetFiltersReturnsFilterMetadata(): void
    {
        $this->filterMetadataProvider->method('getFilters')
            ->with(TestEntity::class)
            ->willReturn([
                'name' => ['type' => 'text', 'label' => 'Name', 'operator' => 'LIKE'],
                'status' => ['type' => 'enum', 'label' => 'Status', 'operator' => '='],
            ]);

        $dataSource = $this->createDataSource();
        $filters = $dataSource->getFilters();

        $this->assertCount(2, $filters);
        $this->assertArrayHasKey('name', $filters);
        $this->assertArrayHasKey('status', $filters);
        $this->assertInstanceOf(FilterMetadata::class, $filters['name']);
        $this->assertInstanceOf(FilterMetadata::class, $filters['status']);
    }

    public function testGetFiltersCachesResults(): void
    {
        $this->filterMetadataProvider->expects($this->once())
            ->method('getFilters')
            ->willReturn([]);

        $dataSource = $this->createDataSource();

        // Call multiple times
        $dataSource->getFilters();
        $dataSource->getFilters();
        $dataSource->getFilters();
    }

    public function testGetFiltersReturnsAllFiltersWhenFilterableColumnsIsNull(): void
    {
        $this->filterMetadataProvider->method('getFilters')
            ->willReturn([
                'name' => ['type' => 'text', 'operator' => 'LIKE'],
                'status' => ['type' => 'enum', 'operator' => '='],
                'createdAt' => ['type' => 'date', 'operator' => '>='],
            ]);

        $admin = new Admin(filterableColumns: null);
        $dataSource = $this->createDataSource($admin);
        $filters = $dataSource->getFilters();

        $this->assertCount(3, $filters);
        $this->assertArrayHasKey('name', $filters);
        $this->assertArrayHasKey('status', $filters);
        $this->assertArrayHasKey('createdAt', $filters);
    }

    public function testGetFiltersReturnsOnlySpecifiedFilterableColumns(): void
    {
        $this->filterMetadataProvider->method('getFilters')
            ->willReturn([
                'name' => ['type' => 'text', 'operator' => 'LIKE'],
                'status' => ['type' => 'enum', 'operator' => '='],
                'createdAt' => ['type' => 'date', 'operator' => '>='],
                'price' => ['type' => 'text', 'operator' => '='],
            ]);

        $admin = new Admin(filterableColumns: ['name', 'status']);
        $dataSource = $this->createDataSource($admin);
        $filters = $dataSource->getFilters();

        $this->assertCount(2, $filters);
        $this->assertArrayHasKey('name', $filters);
        $this->assertArrayHasKey('status', $filters);
        $this->assertArrayNotHasKey('createdAt', $filters);
        $this->assertArrayNotHasKey('price', $filters);
    }

    public function testGetFiltersWithEmptyFilterableColumnsReturnsNoFilters(): void
    {
        $this->filterMetadataProvider->method('getFilters')
            ->willReturn([
                'name' => ['type' => 'text', 'operator' => 'LIKE'],
                'status' => ['type' => 'enum', 'operator' => '='],
            ]);

        $admin = new Admin(filterableColumns: []);
        $dataSource = $this->createDataSource($admin);
        $filters = $dataSource->getFilters();

        $this->assertCount(0, $filters);
    }

    public function testGetDefaultSortByReturnsAdminAttributeValue(): void
    {
        $admin = new Admin(sortBy: 'name');
        $dataSource = $this->createDataSource($admin);

        $this->assertSame('name', $dataSource->getDefaultSortBy());
    }

    public function testGetDefaultSortByFallsBackToId(): void
    {
        $dataSource = $this->createDataSource();

        $this->assertSame('id', $dataSource->getDefaultSortBy());
    }

    public function testGetDefaultSortDirectionReturnsAdminAttributeValue(): void
    {
        $admin = new Admin(sortDirection: 'ASC');
        $dataSource = $this->createDataSource($admin);

        $this->assertSame('ASC', $dataSource->getDefaultSortDirection());
    }

    public function testGetDefaultSortDirectionFallsBackToDESC(): void
    {
        $dataSource = $this->createDataSource();

        $this->assertSame('DESC', $dataSource->getDefaultSortDirection());
    }

    public function testGetDefaultItemsPerPageReturnsAdminAttributeValue(): void
    {
        $admin = new Admin(itemsPerPage: 50);
        $dataSource = $this->createDataSource($admin);

        $this->assertSame(50, $dataSource->getDefaultItemsPerPage());
    }

    public function testGetDefaultItemsPerPageFallsBackTo20(): void
    {
        $dataSource = $this->createDataSource();

        $this->assertSame(20, $dataSource->getDefaultItemsPerPage());
    }

    public function testQueryDelegatestoQueryService(): void
    {
        $this->filterMetadataProvider->method('getFilters')->willReturn([]);

        $entities = [new TestEntity(), new TestEntity()];
        $this->queryService->method('getEntities')
            ->willReturn([
                'entities' => $entities,
                'total' => 100,
                'page' => 2,
            ]);

        $dataSource = $this->createDataSource();
        $result = $dataSource->query(
            search: 'test',
            filters: ['name' => 'foo'],
            sortBy: 'name',
            sortDirection: 'ASC',
            page: 2,
            itemsPerPage: 20
        );

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertSame($entities, $result->items);
        $this->assertSame(100, $result->totalItems);
        $this->assertSame(2, $result->currentPage);
    }

    public function testQueryPassesCorrectParametersToQueryService(): void
    {
        $this->filterMetadataProvider->method('getFilters')->willReturn([
            'name' => ['type' => 'text', 'operator' => 'LIKE'],
        ]);

        $this->queryService->expects($this->once())
            ->method('getEntities')
            ->with(
                TestEntity::class,
                null, // repositoryMethod
                'search term',
                ['name' => 'filter value'],
                $this->isType('array'), // filterMetadata
                'sortField',
                'DESC',
                3,
                25
            )
            ->willReturn(['entities' => [], 'total' => 0, 'page' => 1]);

        $dataSource = $this->createDataSource();
        $dataSource->query(
            search: 'search term',
            filters: ['name' => 'filter value'],
            sortBy: 'sortField',
            sortDirection: 'DESC',
            page: 3,
            itemsPerPage: 25
        );
    }

    public function testFindDelegatesToRepository(): void
    {
        $entity = new TestEntity();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($entity);

        $this->em->method('getRepository')
            ->with(TestEntity::class)
            ->willReturn($repository);

        $dataSource = $this->createDataSource();
        $result = $dataSource->find(123);

        $this->assertSame($entity, $result);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn(null);

        $this->em->method('getRepository')
            ->with(TestEntity::class)
            ->willReturn($repository);

        $dataSource = $this->createDataSource();
        $result = $dataSource->find(999);

        $this->assertNull($result);
    }

    public function testSupportsActionForBasicActions(): void
    {
        $dataSource = $this->createDataSource();

        $this->assertTrue($dataSource->supportsAction('index'));
        $this->assertTrue($dataSource->supportsAction('show'));
        $this->assertTrue($dataSource->supportsAction('new'));
        $this->assertTrue($dataSource->supportsAction('edit'));
        $this->assertTrue($dataSource->supportsAction('delete'));
        $this->assertFalse($dataSource->supportsAction('unknown'));
    }

    public function testSupportsActionBatchDeleteWhenEnabled(): void
    {
        $admin = new Admin(enableBatchActions: true);
        $dataSource = $this->createDataSource($admin);

        $this->assertTrue($dataSource->supportsAction('batch_delete'));
    }

    public function testSupportsActionBatchDeleteWhenDisabled(): void
    {
        $admin = new Admin(enableBatchActions: false);
        $dataSource = $this->createDataSource($admin);

        $this->assertFalse($dataSource->supportsAction('batch_delete'));
    }

    public function testGetIdFieldReturnsIdentifierFieldName(): void
    {
        $this->metadata->method('getSingleIdentifierFieldName')->willReturn('id');

        $dataSource = $this->createDataSource();

        $this->assertSame('id', $dataSource->getIdField());
    }

    public function testGetItemIdReturnsEntityId(): void
    {
        $entity = new TestEntity();

        $this->metadata->method('getSingleIdentifierFieldName')->willReturn('id');
        $this->metadata->method('getFieldValue')
            ->with($entity, 'id')
            ->willReturn(42);

        $dataSource = $this->createDataSource();

        $this->assertSame(42, $dataSource->getItemId($entity));
    }

    public function testGetItemValueForField(): void
    {
        $entity = new TestEntity();

        $this->metadata->method('hasField')->with('name')->willReturn(true);
        $this->metadata->method('getFieldValue')
            ->with($entity, 'name')
            ->willReturn('Test Name');

        $dataSource = $this->createDataSource();

        $this->assertSame('Test Name', $dataSource->getItemValue($entity, 'name'));
    }

    public function testGetItemValueForAssociation(): void
    {
        $entity = new TestEntity();
        $related = new \stdClass();

        $this->metadata->method('hasField')->with('category')->willReturn(false);
        $this->metadata->method('hasAssociation')->with('category')->willReturn(true);
        $this->metadata->method('getFieldValue')
            ->with($entity, 'category')
            ->willReturn($related);

        $dataSource = $this->createDataSource();

        $this->assertSame($related, $dataSource->getItemValue($entity, 'category'));
    }

    public function testGetItemValueFallsBackToGetter(): void
    {
        // Create anonymous class with a getter method
        $entity = new class {
            public function getCustomProperty(): string
            {
                return 'custom value';
            }
        };

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $dataSource = $this->createDataSource();

        $this->assertSame('custom value', $dataSource->getItemValue($entity, 'customProperty'));
    }

    public function testGetItemValueReturnsNullWhenNotFound(): void
    {
        $entity = new TestEntity();

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $dataSource = $this->createDataSource();

        $this->assertNull($dataSource->getItemValue($entity, 'nonExistentProperty'));
    }

    public function testGetEntityClassReturnsCorrectClass(): void
    {
        $dataSource = $this->createDataSource();

        $this->assertSame(TestEntity::class, $dataSource->getEntityClass());
    }

    public function testGetAdminAttributeReturnsAttribute(): void
    {
        $admin = new Admin(label: 'Test');
        $dataSource = $this->createDataSource($admin);

        $this->assertSame($admin, $dataSource->getAdminAttribute());
    }

    public function testGetShortNameReturnsClassName(): void
    {
        $dataSource = $this->createDataSource();

        $this->assertSame('TestEntity', $dataSource->getShortName());
    }
}
