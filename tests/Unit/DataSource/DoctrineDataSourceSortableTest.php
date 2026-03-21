<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\EntityManagerInterface;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group sorting
 */
class DoctrineDataSourceSortableTest extends TestCase
{
    /** @var ClassMetadata<object>&MockObject */
    private MockObject&ClassMetadata $metadata;
    /** @var EntityManagerInterface&MockObject */
    private MockObject&EntityManagerInterface $em;
    /** @var EntityListQueryService&MockObject */
    private MockObject&EntityListQueryService $queryService;
    /** @var FilterMetadataProvider&MockObject */
    private MockObject&FilterMetadataProvider $filterProvider;

    protected function setUp(): void
    {
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->queryService = $this->createMock(EntityListQueryService::class);
        $this->filterProvider = $this->createMock(FilterMetadataProvider::class);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);
        $this->filterProvider->method('getFilters')->willReturn([]);
    }

    private function createDataSource(
        ?Admin $admin = null,
        ?DoctrineCustomColumnProvider $customColumnProvider = null,
    ): DoctrineDataSource {
        if ($customColumnProvider === null) {
            $customColumnProvider = $this->createMock(DoctrineCustomColumnProvider::class);
            $customColumnProvider->method('getCustomColumns')->willReturn([]);
        }

        $columnAttrProvider = $this->createMock(DoctrineColumnAttributeProvider::class);
        $columnAttrProvider->method('getColumnAttributes')->willReturn([]);
        $columnAttrProvider->method('getGroupAttributes')->willReturn([]);

        $columnTypeMapper = $this->createMock(DoctrineColumnTypeMapper::class);
        $columnTypeMapper->method('getColumnType')->willReturn('string');

        return new DoctrineDataSource(
            entityClass: 'App\\Entity\\Dummy', // @phpstan-ignore argument.type
            adminAttribute: $admin ?? new Admin(),
            em: $this->em,
            queryService: $this->queryService,
            filterMetadataProvider: $this->filterProvider,
            customColumnProvider: $customColumnProvider,
            columnAttributeProvider: $columnAttrProvider,
            columnTypeMapper: $columnTypeMapper,
            filterConverter: new DoctrineFilterConverter(),
            itemValueResolver: new DoctrineItemValueResolver(),
        );
    }

    /** @test */
    public function regularFieldsAreSortable(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'createdAt']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $columns = $this->createDataSource()->getColumns();

        $this->assertTrue($columns['id']->sortable);
        $this->assertTrue($columns['name']->sortable);
        $this->assertTrue($columns['createdAt']->sortable);
    }

    /** @test */
    public function manyToOneAssociationIsNotSortable(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn(['category']);
        $this->metadata->method('hasField')->willReturnCallback(fn ($f) => $f === 'id');
        $this->metadata->method('hasAssociation')->willReturnCallback(fn ($f) => $f === 'category');
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('integer');

        $columns = $this->createDataSource()->getColumns();

        $this->assertArrayHasKey('category', $columns);
        $this->assertFalse($columns['category']->sortable, 'ManyToOne association must not be sortable');
    }

    /** @test */
    public function oneToManyAssociationIsNotSortable(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn(['tags']);
        $this->metadata->method('hasField')->willReturnCallback(fn ($f) => $f === 'id');
        $this->metadata->method('hasAssociation')->willReturnCallback(fn ($f) => $f === 'tags');
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('integer');

        $columns = $this->createDataSource()->getColumns();

        $this->assertArrayHasKey('tags', $columns);
        $this->assertFalse($columns['tags']->sortable, 'OneToMany/ManyToMany association must not be sortable');
    }

    /** @test */
    public function customColumnIsNotSortableByDefault(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('integer');

        $customMeta = new ColumnMetadata(
            name: 'fullName',
            label: 'Full Name',
            type: 'custom',
            sortable: false,
            template: 'admin/cols/full_name.html.twig',
        );

        $customColumnProvider = $this->createMock(DoctrineCustomColumnProvider::class);
        $customColumnProvider->method('getCustomColumns')->willReturn(['fullName' => $customMeta]);

        $dataSource = $this->createDataSource(customColumnProvider: $customColumnProvider);
        $columns = $dataSource->getColumns();

        $this->assertArrayHasKey('fullName', $columns, 'Custom column must appear in columns');
        $this->assertFalse($columns['fullName']->sortable, 'Custom columns must not be sortable by default');
    }
}
