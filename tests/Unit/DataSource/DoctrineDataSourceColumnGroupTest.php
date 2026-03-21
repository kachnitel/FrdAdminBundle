<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\DataSourceContracts\ColumnGroup;
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
 * @group composite-columns
 */
class DoctrineDataSourceColumnGroupTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;
    /** @var EntityListQueryService&MockObject */
    private EntityListQueryService $queryService;
    /** @var FilterMetadataProvider&MockObject */
    private FilterMetadataProvider $filterProvider;
    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;
    /** @var DoctrineCustomColumnProvider&MockObject */
    private DoctrineCustomColumnProvider $customColumnProvider;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->queryService = $this->createMock(EntityListQueryService::class);
        $this->filterProvider = $this->createMock(FilterMetadataProvider::class);
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->customColumnProvider = $this->createMock(DoctrineCustomColumnProvider::class);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);
        $this->filterProvider->method('getFilters')->willReturn([]);
        $this->customColumnProvider->method('getCustomColumns')->willReturn([]);
    }

    /**
     * @param array<string, AdminColumn> $columnAttributes
     */
    private function createDataSource(
        array $columnAttributes = [],
        ?Admin $admin = null,
    ): DoctrineDataSource {
        $columnAttrProvider = $this->createMock(DoctrineColumnAttributeProvider::class);
        $columnAttrProvider->method('getColumnAttributes')->willReturn($columnAttributes);
        $columnAttrProvider->method('getGroupAttributes')->willReturn([]);
        $columnAttrProvider->method('build')
            ->willReturnCallback(fn (array $cols, array $attrs) => (new DoctrineColumnAttributeProvider())->build($cols, $attrs));

        $columnTypeMapper = $this->createMock(DoctrineColumnTypeMapper::class);
        $columnTypeMapper->method('getColumnType')->willReturn('string');

        return new DoctrineDataSource(
            entityClass: 'App\\Entity\\Dummy', // @phpstan-ignore argument.type
            adminAttribute: $admin ?? new Admin(),
            em: $this->em,
            queryService: $this->queryService,
            filterMetadataProvider: $this->filterProvider,
            customColumnProvider: $this->customColumnProvider,
            columnAttributeProvider: $columnAttrProvider,
            columnTypeMapper: $columnTypeMapper,
            filterConverter: new DoctrineFilterConverter(),
            itemValueResolver: new DoctrineItemValueResolver(),
        );
    }

    /** @test */
    public function getColumnGroupsReturnsAllColumnsAsStringsWhenNoGroupsDefined(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'email']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturnCallback(fn ($f) => in_array($f, ['id', 'name', 'email']));
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $slots = $this->createDataSource()->getColumnGroups();

        $this->assertSame(['id', 'name', 'email'], $slots);
    }

    /** @test */
    public function getColumnGroupsGroupsConsecutiveColumnsWithSameGroupId(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'firstName', 'lastName', 'email']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $slots = $this->createDataSource(columnAttributes: [
            'firstName' => new AdminColumn(group: 'name_block'),
            'lastName'  => new AdminColumn(group: 'name_block'),
        ])->getColumnGroups();

        // id, [group: name_block], email
        $this->assertCount(3, $slots);
        $this->assertSame('id', $slots[0]);
        $this->assertInstanceOf(ColumnGroup::class, $slots[1]);
        $this->assertSame('email', $slots[2]);

        /** @var ColumnGroup $group */
        $group = $slots[1];
        $this->assertSame('name_block', $group->id);
        $this->assertSame('Name block', $group->label);
        $this->assertArrayHasKey('firstName', $group->columns);
        $this->assertArrayHasKey('lastName', $group->columns);
    }

    /** @test */
    public function getColumnGroupsPreservesColumnOrderWithinGroup(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['firstName', 'lastName']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $slots = $this->createDataSource(columnAttributes: [
            'firstName' => new AdminColumn(group: 'names'),
            'lastName'  => new AdminColumn(group: 'names'),
        ])->getColumnGroups();

        $this->assertCount(1, $slots);
        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertSame(['firstName', 'lastName'], array_keys($group->columns));
    }

    /** @test */
    public function getColumnGroupsHandlesNonContiguousColumnsInSameGroup(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['firstName', 'email', 'lastName']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $slots = $this->createDataSource(columnAttributes: [
            'firstName' => new AdminColumn(group: 'name_block'),
            'lastName'  => new AdminColumn(group: 'name_block'),
        ])->getColumnGroups();

        $this->assertCount(2, $slots);
        $this->assertInstanceOf(ColumnGroup::class, $slots[0]);
        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertArrayHasKey('firstName', $group->columns);
        $this->assertArrayHasKey('lastName', $group->columns);
        $this->assertSame('email', $slots[1]);
    }

    /** @test */
    public function columnMetadataGroupIsSetFromAttribute(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['firstName']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $ds = $this->createDataSource(columnAttributes: [
            'firstName' => new AdminColumn(group: 'name_block'),
        ]);
        $columns = $ds->getColumns();

        $this->assertSame('name_block', $columns['firstName']->group);
    }
}
