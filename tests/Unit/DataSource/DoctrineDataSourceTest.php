<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnAttributeProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnTypeMapper;
use Kachnitel\AdminBundle\DataSource\DoctrineCustomColumnProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\DataSourceContracts\FilterMetadata;
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

    /** @var DoctrineCustomColumnProvider&MockObject */
    private DoctrineCustomColumnProvider $customColumnProvider;

    /** @var DoctrineColumnAttributeProvider&MockObject */
    private DoctrineColumnAttributeProvider $columnAttrProvider;

    /** @var DoctrineColumnTypeMapper&MockObject */
    private DoctrineColumnTypeMapper $columnTypeMapper;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->queryService = $this->createMock(EntityListQueryService::class);
        $this->filterMetadataProvider = $this->createMock(FilterMetadataProvider::class);
        $this->metadata = $this->createMock(ClassMetadata::class);

        $this->customColumnProvider = $this->createMock(DoctrineCustomColumnProvider::class);
        $this->customColumnProvider->method('getCustomColumns')->willReturn([]);

        $this->columnAttrProvider = $this->createMock(DoctrineColumnAttributeProvider::class);
        $this->columnAttrProvider->method('getColumnAttributes')->willReturn([]);

        $this->columnTypeMapper = $this->createMock(DoctrineColumnTypeMapper::class);
        $this->columnTypeMapper->method('getColumnType')->willReturn('string');

        $this->em->method('getClassMetadata')
            ->with(TestEntity::class)
            ->willReturn($this->metadata);
    }

    private function createDataSource(?Admin $admin = null): DoctrineDataSource
    {
        return new DoctrineDataSource(
            entityClass: TestEntity::class,
            adminAttribute: $admin ?? new Admin(),
            em: $this->em,
            queryService: $this->queryService,
            filterMetadataProvider: $this->filterMetadataProvider,
            customColumnProvider: $this->customColumnProvider,
            columnAttributeProvider: $this->columnAttrProvider,
            columnTypeMapper: $this->columnTypeMapper,
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
        $this->metadata->method('getTypeOfField')->willReturnCallback(fn ($f) => match ($f) {
            'id'   => 'integer',
            'name' => 'string',
        });

        $dataSource = $this->createDataSource();
        $columns = $dataSource->getColumns();

        $this->assertCount(2, $columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
        // @phpstan-ignore-next-line method.alreadyNarrowedType
        $this->assertInstanceOf(ColumnMetadata::class, $columns['id']);
        // @phpstan-ignore-next-line method.alreadyNarrowedType
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

    public function testGetFiltersReturnsFilterMetadata(): void
    {
        $this->filterMetadataProvider->method('getFilters')
            ->willReturn([
                'name' => ['type' => 'text', 'operator' => 'LIKE'],
            ]);

        $dataSource = $this->createDataSource();
        $filters = $dataSource->getFilters();

        $this->assertArrayHasKey('name', $filters);
        // @phpstan-ignore-next-line method.alreadyNarrowedType
        $this->assertInstanceOf(FilterMetadata::class, $filters['name']);
    }

    public function testGetFiltersWithAllowedColumnsFiltersResult(): void
    {
        $this->filterMetadataProvider->method('getFilters')
            ->willReturn([
                'name'   => ['type' => 'text', 'operator' => 'LIKE'],
                'status' => ['type' => 'enum', 'operator' => '='],
                'price'  => ['type' => 'number', 'operator' => '='],
            ]);

        $admin = new Admin(filterableColumns: ['name', 'status']);
        $dataSource = $this->createDataSource($admin);
        $filters = $dataSource->getFilters();

        $this->assertCount(2, $filters);
        $this->assertArrayHasKey('name', $filters);
        $this->assertArrayHasKey('status', $filters);
        $this->assertArrayNotHasKey('price', $filters);
    }

    public function testGetFiltersWithEmptyFilterableColumnsReturnsNoFilters(): void
    {
        $this->filterMetadataProvider->method('getFilters')
            ->willReturn([
                'name'   => ['type' => 'text', 'operator' => 'LIKE'],
                'status' => ['type' => 'enum', 'operator' => '='],
            ]);

        $admin = new Admin(filterableColumns: []);
        $dataSource = $this->createDataSource($admin);
        $filters = $dataSource->getFilters();

        $this->assertCount(0, $filters);
    }

    public function testGetFiltersPassesMultipleOptionToEnumOptions(): void
    {
        $this->filterMetadataProvider->method('getFilters')
            ->willReturn([
                'status' => [
                    'type'      => 'enum',
                    'enumClass' => 'App\\Enum\\Status',
                    'operator'  => 'IN',
                    'multiple'  => true,
                ],
            ]);

        $dataSource = $this->createDataSource();
        $filters = $dataSource->getFilters();

        $this->assertArrayHasKey('status', $filters);
        $this->assertTrue($filters['status']->isMultiple());

        $array = $filters['status']->toArray();
        $this->assertArrayHasKey('multiple', $array);
        $this->assertTrue($array['multiple']);
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

    public function testGetDefaultItemsPerPageReturnsNullByDefault(): void
    {
        $dataSource = $this->createDataSource();

        // No itemsPerPage on Admin — falls back to the built-in default of 20
        $this->assertEquals(20, $dataSource->getDefaultItemsPerPage());
    }

    public function testFindReturnsEntity(): void
    {
        $entity = new TestEntity();
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(123)->willReturn($entity);

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

    public function testSupportsActionColumnVisibilityWhenEnabled(): void
    {
        $admin = new Admin(enableColumnVisibility: true);
        $dataSource = $this->createDataSource($admin);

        $this->assertTrue($dataSource->supportsAction('column_visibility'));
    }

    public function testSupportsActionUnknownReturnsFalse(): void
    {
        $dataSource = $this->createDataSource();

        $this->assertFalse($dataSource->supportsAction('unknown_action'));
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
