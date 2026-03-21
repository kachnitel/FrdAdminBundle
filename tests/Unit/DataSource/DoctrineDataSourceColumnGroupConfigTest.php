<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\AdminColumnGroup;
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
class DoctrineDataSourceColumnGroupConfigTest extends TestCase
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
     * @param array<string, AdminColumn>      $columnAttributes
     * @param array<string, AdminColumnGroup> $groupAttributes
     */
    private function createDataSource(
        array $columnAttributes = [],
        array $groupAttributes = [],
    ): DoctrineDataSource {
        $columnAttrProvider = $this->createMock(DoctrineColumnAttributeProvider::class);
        $columnAttrProvider->method('getColumnAttributes')->willReturn($columnAttributes);
        $columnAttrProvider->method('getGroupAttributes')->willReturn($groupAttributes);
        $columnAttrProvider->method('build')
            ->willReturnCallback(fn (array $cols, array $attrs) => (new DoctrineColumnAttributeProvider())->build($cols, $attrs));

        $columnTypeMapper = $this->createMock(DoctrineColumnTypeMapper::class);
        $columnTypeMapper->method('getColumnType')->willReturn('string');

        return new DoctrineDataSource(
            entityClass: 'App\\Entity\\Dummy', // @phpstan-ignore argument.type
            adminAttribute: new Admin(),
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
    public function columnGroupDefaultsToTextHeader(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['firstName', 'lastName']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $slots = $this->createDataSource(columnAttributes: [
            'firstName' => new AdminColumn(group: 'name'),
            'lastName'  => new AdminColumn(group: 'name'),
        ])->getColumnGroups();

        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertSame(ColumnGroup::SUB_LABELS_SHOW, $group->subLabels);
        $this->assertSame(ColumnGroup::HEADER_TEXT, $group->header);
    }

    /** @test */
    public function collapsibleConfigIsApplied(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['firstName', 'lastName']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $slots = $this->createDataSource(
            columnAttributes: [
                'firstName' => new AdminColumn(group: 'name'),
                'lastName'  => new AdminColumn(group: 'name'),
            ],
            groupAttributes: [
                'name' => new AdminColumnGroup(
                    id: 'name',
                    subLabels: ColumnGroup::SUB_LABELS_ICON,
                    header: ColumnGroup::HEADER_COLLAPSIBLE,
                ),
            ],
        )->getColumnGroups();

        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertSame(ColumnGroup::SUB_LABELS_ICON, $group->subLabels);
        $this->assertSame(ColumnGroup::HEADER_COLLAPSIBLE, $group->header);
    }

    /** @test */
    public function fullConfigIsApplied(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['city', 'country']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $slots = $this->createDataSource(
            columnAttributes: [
                'city'    => new AdminColumn(group: 'address'),
                'country' => new AdminColumn(group: 'address'),
            ],
            groupAttributes: [
                'address' => new AdminColumnGroup(
                    id: 'address',
                    header: ColumnGroup::HEADER_FULL,
                ),
            ],
        )->getColumnGroups();

        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertSame(ColumnGroup::HEADER_FULL, $group->header);
    }

    /** @test */
    public function configIsPreservedWhenSubsequentMemberIsAppended(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['firstName', 'email', 'lastName']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $slots = $this->createDataSource(
            columnAttributes: [
                'firstName' => new AdminColumn(group: 'name'),
                'lastName'  => new AdminColumn(group: 'name'),
            ],
            groupAttributes: [
                'name' => new AdminColumnGroup(
                    id: 'name',
                    subLabels: ColumnGroup::SUB_LABELS_HIDDEN,
                    header: ColumnGroup::HEADER_COLLAPSIBLE,
                ),
            ],
        )->getColumnGroups();

        $groupSlot = array_values(array_filter($slots, fn ($s) => $s instanceof ColumnGroup))[0];
        /** @var ColumnGroup $groupSlot */
        $this->assertSame(ColumnGroup::SUB_LABELS_HIDDEN, $groupSlot->subLabels);
        $this->assertSame(ColumnGroup::HEADER_COLLAPSIBLE, $groupSlot->header);
        $this->assertArrayHasKey('firstName', $groupSlot->columns);
        $this->assertArrayHasKey('lastName', $groupSlot->columns);
    }

    /** @test */
    public function unconfiguredGroupGetsDefaultTextHeader(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['city', 'country']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $slots = $this->createDataSource(
            columnAttributes: [
                'city'    => new AdminColumn(group: 'address'),
                'country' => new AdminColumn(group: 'address'),
            ],
        )->getColumnGroups();

        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertSame(ColumnGroup::SUB_LABELS_SHOW, $group->subLabels);
        $this->assertSame(ColumnGroup::HEADER_TEXT, $group->header);
    }
}
