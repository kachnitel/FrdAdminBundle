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
use Kachnitel\AdminBundle\DataSource\DoctrineFilterConverter;
use Kachnitel\AdminBundle\DataSource\DoctrineItemValueResolver;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\DataSourceContracts\FilterMetadata;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('data-source')]
#[Group('doctrine')]
#[AllowMockObjectsWithoutExpectations]
final class DoctrineDataSourceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

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
        $this->filterMetadataProvider = $this->createMock(FilterMetadataProvider::class);
        $this->metadata = $this->createMock(ClassMetadata::class);

        $this->customColumnProvider = $this->createMock(DoctrineCustomColumnProvider::class);
        $this->customColumnProvider->method('getCustomColumns')->willReturn([]);

        $this->columnAttrProvider = $this->createMock(DoctrineColumnAttributeProvider::class);
        $this->columnAttrProvider->method('getColumnAttributes')->willReturn([]);
        $this->columnAttrProvider->method('getGroupAttributes')->willReturn([]);

        $this->columnTypeMapper = $this->createMock(DoctrineColumnTypeMapper::class);
        $this->columnTypeMapper->method('getColumnType')->willReturn('string');

        $this->em->method('getClassMetadata')
            ->willReturn($this->metadata);
    }

    private function createDataSource(?Admin $admin = null): DoctrineDataSource
    {
        return new DoctrineDataSource(
            entityClass: TestEntity::class,
            adminAttribute: $admin ?? new Admin(),
            em: $this->em,
            queryService: $this->createStub(EntityListQueryService::class),
            filterMetadataProvider: $this->filterMetadataProvider,
            customColumnProvider: $this->customColumnProvider,
            columnAttributeProvider: $this->columnAttrProvider,
            columnTypeMapper: $this->columnTypeMapper,
            filterConverter: new DoctrineFilterConverter(),
            itemValueResolver: new DoctrineItemValueResolver(),
        );
    }

    #[Test]
    public function testGetIdentifierReturnsShortClassName(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $dataSource = $this->createDataSource();

        $this->assertSame('TestEntity', $dataSource->getIdentifier());
    }

    #[Test]
    public function testGetLabelReturnsAdminAttributeLabel(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $admin = new Admin(label: 'Test Entities');
        $dataSource = $this->createDataSource($admin);

        $this->assertSame('Test Entities', $dataSource->getLabel());
    }

    #[Test]
    public function testGetLabelFallsBackToShortClassName(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $dataSource = $this->createDataSource();

        $this->assertSame('TestEntity', $dataSource->getLabel());
    }

    #[Test]
    public function testGetIconReturnsAdminAttributeIcon(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $admin = new Admin(icon: 'fa-test');
        $dataSource = $this->createDataSource($admin);

        $this->assertSame('fa-test', $dataSource->getIcon());
    }

    #[Test]
    public function testGetIconReturnsNullByDefault(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $dataSource = $this->createDataSource();

        $this->assertNull($dataSource->getIcon());
    }

    #[Test]
    public function testGetColumnsReturnsColumnMetadata(): void
    {
        $this->em->expects($this->once())->method('getClassMetadata')->with(TestEntity::class);

        $this->metadata->method('getFieldNames')->willReturn(['id', 'name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturnCallback(fn (string $f): string => match ($f) {
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

    #[Test]
    public function testGetColumnsUsesAdminAttributeColumns(): void
    {
        $this->em->expects($this->once())->method('getClassMetadata')->with(TestEntity::class);

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

    #[Test]
    public function testGetFiltersReturnsFilterMetadata(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

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

    #[Test]
    public function testGetFiltersWithAllowedColumnsFiltersResult(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

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

    #[Test]
    public function testGetFiltersWithEmptyFilterableColumnsReturnsNoFilters(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

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

    #[Test]
    public function testGetFiltersPassesMultipleOptionToEnumOptions(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

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

    #[Test]
    public function testGetDefaultSortByReturnsAdminAttributeValue(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $admin = new Admin(sortBy: 'name');
        $dataSource = $this->createDataSource($admin);

        $this->assertSame('name', $dataSource->getDefaultSortBy());
    }

    #[Test]
    public function testGetDefaultSortByFallsBackToId(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $dataSource = $this->createDataSource();

        $this->assertSame('id', $dataSource->getDefaultSortBy());
    }

    #[Test]
    public function testGetDefaultSortDirectionReturnsAdminAttributeValue(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $admin = new Admin(sortDirection: 'ASC');
        $dataSource = $this->createDataSource($admin);

        $this->assertSame('ASC', $dataSource->getDefaultSortDirection());
    }

    #[Test]
    public function testGetDefaultSortDirectionFallsBackToDESC(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $dataSource = $this->createDataSource();

        $this->assertSame('DESC', $dataSource->getDefaultSortDirection());
    }

    #[Test]
    public function testGetDefaultItemsPerPageReturnsAdminAttributeValue(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $admin = new Admin(itemsPerPage: 50);
        $dataSource = $this->createDataSource($admin);

        $this->assertSame(50, $dataSource->getDefaultItemsPerPage());
    }

    #[Test]
    public function testGetDefaultItemsPerPageReturnsNullByDefault(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $dataSource = $this->createDataSource();

        // No itemsPerPage on Admin — falls back to the built-in default of 20
        $this->assertSame(20, $dataSource->getDefaultItemsPerPage());
    }

    #[Test]
    public function testFindReturnsEntity(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $entity = new TestEntity();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('find')->with(123)->willReturn($entity);

        $this->em->expects($this->once())->method('getRepository')
            ->with(TestEntity::class)
            ->willReturn($repository);

        $dataSource = $this->createDataSource();
        $result = $dataSource->find(123);

        $this->assertSame($entity, $result);
    }

    #[Test]
    public function testFindReturnsNullWhenNotFound(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn(null);

        $this->em->expects($this->once())->method('getRepository')
            ->with(TestEntity::class)
            ->willReturn($repository);

        $dataSource = $this->createDataSource();
        $result = $dataSource->find(999);

        $this->assertNull($result);
    }

    #[Test]
    public function testSupportsActionForBasicActions(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $dataSource = $this->createDataSource();

        $this->assertTrue($dataSource->supportsAction('index'));
        $this->assertTrue($dataSource->supportsAction('show'));
        $this->assertTrue($dataSource->supportsAction('new'));
        $this->assertTrue($dataSource->supportsAction('edit'));
        $this->assertTrue($dataSource->supportsAction('delete'));
    }

    #[Test]
    public function testSupportsActionBatchDeleteWhenEnabled(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $admin = new Admin(enableBatchActions: true);
        $dataSource = $this->createDataSource($admin);

        $this->assertTrue($dataSource->supportsAction('batch_delete'));
    }

    #[Test]
    public function testSupportsActionBatchDeleteWhenDisabled(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $admin = new Admin(enableBatchActions: false);
        $dataSource = $this->createDataSource($admin);

        $this->assertFalse($dataSource->supportsAction('batch_delete'));
    }

    #[Test]
    public function testSupportsActionColumnVisibilityWhenEnabled(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $admin = new Admin(enableColumnVisibility: true);
        $dataSource = $this->createDataSource($admin);

        $this->assertTrue($dataSource->supportsAction('column_visibility'));
    }

    #[Test]
    public function testSupportsActionUnknownReturnsFalse(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $dataSource = $this->createDataSource();

        $this->assertFalse($dataSource->supportsAction('unknown_action'));
    }

    #[Test]
    public function testGetIdFieldReturnsIdentifierFieldName(): void
    {
        $this->em->expects($this->once())->method('getClassMetadata')->with(TestEntity::class);

        $this->metadata->method('getSingleIdentifierFieldName')->willReturn('id');

        $dataSource = $this->createDataSource();

        $this->assertSame('id', $dataSource->getIdField());
    }

    #[Test]
    public function testGetItemIdReturnsEntityId(): void
    {
        $this->em->expects($this->once())->method('getClassMetadata')->with(TestEntity::class);

        $entity = new TestEntity();

        $this->metadata->method('getSingleIdentifierFieldName')->willReturn('id');
        $this->metadata->expects($this->once())->method('getFieldValue')
            ->with($entity, 'id')
            ->willReturn(42);

        $dataSource = $this->createDataSource();

        $this->assertSame(42, $dataSource->getItemId($entity));
    }

    #[Test]
    public function testGetItemValueForField(): void
    {
        $this->em->expects($this->once())->method('getClassMetadata')->with(TestEntity::class);

        $entity = new TestEntity();

        $this->metadata->expects($this->once())->method('hasField')->with('name')->willReturn(true);
        $this->metadata->expects($this->once())->method('getFieldValue')
            ->with($entity, 'name')
            ->willReturn('Test Name');

        $dataSource = $this->createDataSource();

        $this->assertSame('Test Name', $dataSource->getItemValue($entity, 'name'));
    }

    #[Test]
    public function testGetItemValueForAssociation(): void
    {
        $this->em->expects($this->once())->method('getClassMetadata')->with(TestEntity::class);

        $entity = new TestEntity();
        $related = new \stdClass();

        $this->metadata->expects($this->once())->method('hasField')->with('category')->willReturn(false);
        $this->metadata->expects($this->once())->method('hasAssociation')->with('category')->willReturn(true);
        $this->metadata->expects($this->once())->method('getFieldValue')
            ->with($entity, 'category')
            ->willReturn($related);

        $dataSource = $this->createDataSource();

        $this->assertSame($related, $dataSource->getItemValue($entity, 'category'));
    }

    #[Test]
    public function testGetItemValueFallsBackToGetter(): void
    {
        $this->em->expects($this->once())->method('getClassMetadata')->with(TestEntity::class);

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

    #[Test]
    public function testGetItemValueReturnsNullWhenNotFound(): void
    {
        $this->em->expects($this->once())->method('getClassMetadata')->with(TestEntity::class);

        $entity = new TestEntity();

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $dataSource = $this->createDataSource();

        $this->assertNull($dataSource->getItemValue($entity, 'nonExistentProperty'));
    }

    #[Test]
    public function testGetEntityClassReturnsCorrectClass(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $dataSource = $this->createDataSource();

        $this->assertSame(TestEntity::class, $dataSource->getEntityClass());
    }

    #[Test]
    public function testGetAdminAttributeReturnsAttribute(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $admin = new Admin(label: 'Test');
        $dataSource = $this->createDataSource($admin);

        $this->assertSame($admin, $dataSource->getAdminAttribute());
    }

    #[Test]
    public function testGetShortNameReturnsClassName(): void
    {
        $this->em->expects($this->never())->method('getClassMetadata');

        $dataSource = $this->createDataSource();

        $this->assertSame('TestEntity', $dataSource->getShortName());
    }
}
