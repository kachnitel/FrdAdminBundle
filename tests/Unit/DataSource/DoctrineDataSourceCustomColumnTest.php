<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnAttributeProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineCustomColumnProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests DoctrineDataSource #[AdminCustomColumn] integration.
 *
 * The existing DoctrineDataSourceTest covers the Doctrine-backed column logic;
 * this class focuses exclusively on the #[AdminCustomColumn] integration.
 *
 * @group custom-columns
 */
class DoctrineDataSourceCustomColumnTest extends TestCase
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

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->queryService = $this->createMock(EntityListQueryService::class);
        $this->filterMetadataProvider = $this->createMock(FilterMetadataProvider::class);
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->customColumnProvider = $this->createMock(DoctrineCustomColumnProvider::class);

        $this->columnAttrProvider = $this->createMock(DoctrineColumnAttributeProvider::class);
        $this->columnAttrProvider->method('getColumnAttributes')->willReturn([]);

        $this->em->method('getClassMetadata')
            ->with(TestEntity::class)
            ->willReturn($this->metadata);

        $this->filterMetadataProvider->method('getFilters')->willReturn([]);
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
        );
    }

    // -------------------------------------------------------------------------
    // getColumns() — custom column merging
    // -------------------------------------------------------------------------

    /** @test */
    public function getColumnsAppendsCustomColumnsAfterDoctrineColumns(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $customMeta = new ColumnMetadata(
            name: 'fullName',
            label: 'Full Name',
            type: 'custom',
            sortable: false,
            template: 'admin/cols/full_name.html.twig',
        );
        $this->customColumnProvider->method('getCustomColumns')->willReturn(['fullName' => $customMeta]);

        $dataSource = $this->createDataSource();
        $columns = $dataSource->getColumns();

        $keys = array_keys($columns);
        $this->assertSame(['id', 'name', 'fullName'], $keys);
    }

    /** @test */
    public function getColumnsUsesCustomColumnMetadataDirectly(): void
    {
        // The custom column metadata should win (it's checked first in the loop).
        $this->metadata->method('getFieldNames')->willReturn(['id', 'badge']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $customMeta = new ColumnMetadata('badge', 'Badge', 'custom', false, 'badge.html.twig');
        $this->customColumnProvider->method('getCustomColumns')->willReturn(['badge' => $customMeta]);

        $dataSource = $this->createDataSource();
        $columns = $dataSource->getColumns();

        // Should appear only once; custom metadata wins
        $this->assertCount(2, $columns);
        $this->assertSame($customMeta, $columns['badge']);
    }

    // -------------------------------------------------------------------------
    // getItemValue() — custom column returns null
    // -------------------------------------------------------------------------

    /** @test */
    public function getItemValueReturnsNullForCustomColumn(): void
    {
        $customMeta = new ColumnMetadata('fullName', 'Full Name', 'custom', false, 'full_name.html.twig');
        $this->customColumnProvider->method('getCustomColumns')->willReturn(['fullName' => $customMeta]);

        $dataSource = $this->createDataSource();

        $entity = new TestEntity();
        $entity->setName('Alice');

        $value = $dataSource->getItemValue($entity, 'fullName');

        $this->assertNull($value);
    }

    /** @test */
    public function getItemValueReturnsNormalValueForDoctrineColumn(): void
    {
        $this->customColumnProvider->method('getCustomColumns')->willReturn([]);

        $this->metadata->method('hasField')->willReturnCallback(fn ($f) => $f === 'name');
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getFieldValue')->with($this->anything(), 'name')->willReturn('Bob');

        $dataSource = $this->createDataSource();

        $entity = new TestEntity();
        $value = $dataSource->getItemValue($entity, 'name');

        $this->assertSame('Bob', $value);
    }
}
