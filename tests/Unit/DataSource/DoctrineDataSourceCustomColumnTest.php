<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

// ============================================================================
// PATCH: Add these test methods to the existing DoctrineDataSourceTest class.
//
// Insert them into the class body of:
//   tests/Unit/DataSource/DoctrineDataSourceTest.php
//
// Also add these use statements at the top of that file (if not already present):
//   use Kachnitel\AdminBundle\Attribute\AdminCustomColumn;
//   use Kachnitel\AdminBundle\DataSource\DoctrineCustomColumnProvider;
//   use PHPUnit\Framework\MockObject\MockObject;  (already present)
// ============================================================================

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminCustomColumn;
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineCustomColumnProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Standalone test class for custom-column behaviour in DoctrineDataSource.
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

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->queryService = $this->createMock(EntityListQueryService::class);
        $this->filterMetadataProvider = $this->createMock(FilterMetadataProvider::class);
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->customColumnProvider = $this->createMock(DoctrineCustomColumnProvider::class);

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
        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('integer');

        $customMeta = new ColumnMetadata(
            name: 'badge',
            label: 'Badge',
            type: 'custom',
            sortable: false,
            template: 'admin/cols/badge.html.twig',
        );
        $this->customColumnProvider->method('getCustomColumns')->willReturn(['badge' => $customMeta]);

        $dataSource = $this->createDataSource();
        $columns = $dataSource->getColumns();

        $this->assertSame($customMeta, $columns['badge']);
    }

    /** @test */
    public function getColumnsPlacesCustomColumnAtPositionWhenInExplicitColumnsList(): void
    {
        // Admin::columns explicitly lists the custom column between two Doctrine columns
        $admin = new Admin(columns: ['id', 'fullName', 'name']);

        $this->metadata->method('getFieldNames')->willReturn(['id', 'name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturnCallback(fn ($f) => in_array($f, ['id', 'name']));
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $customMeta = new ColumnMetadata(
            name: 'fullName',
            label: 'Full Name',
            type: 'custom',
            sortable: false,
            template: 'admin/cols/full_name.html.twig',
        );
        $this->customColumnProvider->method('getCustomColumns')->willReturn(['fullName' => $customMeta]);

        $dataSource = $this->createDataSource($admin);
        $columns = $dataSource->getColumns();

        $keys = array_keys($columns);
        $this->assertSame(['id', 'fullName', 'name'], $keys);
    }

    /** @test */
    public function getColumnsHandlesMultipleCustomColumns(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('integer');

        $custom = [
            'colA' => new ColumnMetadata('colA', 'Col A', 'custom', false, 'a.html.twig'),
            'colB' => new ColumnMetadata('colB', 'Col B', 'custom', false, 'b.html.twig'),
        ];
        $this->customColumnProvider->method('getCustomColumns')->willReturn($custom);

        $dataSource = $this->createDataSource();
        $columns = $dataSource->getColumns();

        $this->assertArrayHasKey('colA', $columns);
        $this->assertArrayHasKey('colB', $columns);
        $this->assertSame('a.html.twig', $columns['colA']->template);
        $this->assertSame('b.html.twig', $columns['colB']->template);
    }

    /** @test */
    public function getColumnsDoesNotDuplicateCustomColumnAlreadyListedInDoctrineNames(): void
    {
        // Edge case: entity has a regular Doctrine field AND a custom column with the same name.
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
