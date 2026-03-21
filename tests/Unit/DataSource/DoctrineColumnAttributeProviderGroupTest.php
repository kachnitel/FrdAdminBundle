<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\AdminColumnGroup;
use Kachnitel\DataSourceContracts\ColumnGroup;
use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnAttributeProvider;
use PHPUnit\Framework\TestCase;

/**
 * @group composite-columns
 */
class DoctrineColumnAttributeProviderGroupTest extends TestCase
{
    private DoctrineColumnAttributeProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new DoctrineColumnAttributeProvider();
    }

    /** @test */
    public function returnsEmptyArrayWhenNoGroupAttributesDeclared(): void
    {
        $entity = new class {};

        $groups = $this->provider->getGroupAttributes($entity::class);

        $this->assertSame([], $groups);
    }

    /** @test */
    public function returnsSingleGroupAttributeWithDefaults(): void
    {
        $entity = new #[AdminColumnGroup(id: 'name_block')] class {};

        $groups = $this->provider->getGroupAttributes($entity::class);

        $this->assertCount(1, $groups);
        $this->assertArrayHasKey('name_block', $groups);
        $this->assertSame(ColumnGroup::SUB_LABELS_SHOW, $groups['name_block']->subLabels);
        $this->assertSame(ColumnGroup::HEADER_TEXT, $groups['name_block']->header);
    }

    /** @test */
    public function returnsGroupAttributeWithCollapsibleHeader(): void
    {
        $entity = new #[AdminColumnGroup(
            id: 'delivery',
            subLabels: ColumnGroup::SUB_LABELS_ICON,
            header: ColumnGroup::HEADER_COLLAPSIBLE,
        )] class {};

        $groups = $this->provider->getGroupAttributes($entity::class);

        $this->assertSame(ColumnGroup::SUB_LABELS_ICON, $groups['delivery']->subLabels);
        $this->assertSame(ColumnGroup::HEADER_COLLAPSIBLE, $groups['delivery']->header);
    }

    /** @test */
    public function returnsGroupAttributeWithFullHeader(): void
    {
        $entity = new #[AdminColumnGroup(
            id: 'addr',
            header: ColumnGroup::HEADER_FULL,
        )] class {};

        $groups = $this->provider->getGroupAttributes($entity::class);

        $this->assertSame(ColumnGroup::HEADER_FULL, $groups['addr']->header);
    }

    /** @test */
    public function returnsMultipleGroupAttributesKeyedById(): void
    {
        $entity = new #[AdminColumnGroup(id: 'name_block')]
                       #[AdminColumnGroup(id: 'address_block', header: ColumnGroup::HEADER_COLLAPSIBLE)]
                       class {};

        $groups = $this->provider->getGroupAttributes($entity::class);

        $this->assertCount(2, $groups);
        $this->assertArrayHasKey('name_block', $groups);
        $this->assertArrayHasKey('address_block', $groups);
        $this->assertSame(ColumnGroup::HEADER_COLLAPSIBLE, $groups['address_block']->header);
    }

    /** @test */
    public function lastDeclarationWinsForDuplicateGroupId(): void
    {
        $entity = new #[AdminColumnGroup(id: 'name', header: ColumnGroup::HEADER_TEXT)]
                       #[AdminColumnGroup(id: 'name', header: ColumnGroup::HEADER_FULL)]
                       class {};

        $groups = $this->provider->getGroupAttributes($entity::class);

        $this->assertCount(1, $groups);
        $this->assertSame(ColumnGroup::HEADER_FULL, $groups['name']->header);
    }

    /** @test */
    public function getColumnAttributesStillWorksAlongsideGroupAttributes(): void
    {
        $entity = new #[AdminColumnGroup(id: 'name_block')] class {
            #[AdminColumn(group: 'name_block')]
            public string $firstName = '';
        };

        $columnAttrs = $this->provider->getColumnAttributes($entity::class);
        $groupAttrs = $this->provider->getGroupAttributes($entity::class);

        $this->assertArrayHasKey('firstName', $columnAttrs);
        $this->assertArrayHasKey('name_block', $groupAttrs);
    }

    // ── build() — slot construction ───────────────────────────────────────────

    /** @test */
    public function buildReturnsPlainStringSlotForUngroupedColumn(): void
    {
        $columns = [
            'id'   => ColumnMetadata::create('id'),
            'name' => ColumnMetadata::create('name'),
        ];

        $slots = $this->provider->build($columns, []);

        $this->assertSame(['id', 'name'], $slots);
    }

    /** @test */
    public function buildGroupsColumnsWithSameGroupId(): void
    {
        $columns = [
            'firstName' => ColumnMetadata::create('firstName', group: 'name_block'),
            'lastName'  => ColumnMetadata::create('lastName', group: 'name_block'),
        ];

        $slots = $this->provider->build($columns, []);

        $this->assertCount(1, $slots);
        $this->assertInstanceOf(ColumnGroup::class, $slots[0]);
        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertSame('name_block', $group->id);
        $this->assertArrayHasKey('firstName', $group->columns);
        $this->assertArrayHasKey('lastName', $group->columns);
    }

    /** @test */
    public function buildHumanisesGroupLabelFromIdentifier(): void
    {
        $columns = ['firstName' => ColumnMetadata::create('firstName', group: 'name_block')];

        $slots = $this->provider->build($columns, []);

        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertSame('Name block', $group->label);
    }

    /** @test */
    public function buildAppliesGroupAttributeSubLabelsAndHeader(): void
    {
        $columns = [
            'city'    => ColumnMetadata::create('city', group: 'addr'),
            'country' => ColumnMetadata::create('country', group: 'addr'),
        ];
        $groupAttrs = [
            'addr' => new AdminColumnGroup(
                id: 'addr',
                subLabels: ColumnGroup::SUB_LABELS_ICON,
                header: ColumnGroup::HEADER_COLLAPSIBLE,
            ),
        ];

        $slots = $this->provider->build($columns, $groupAttrs);

        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertSame(ColumnGroup::SUB_LABELS_ICON, $group->subLabels);
        $this->assertSame(ColumnGroup::HEADER_COLLAPSIBLE, $group->header);
    }

    /** @test */
    public function buildDefaultsToShowSubLabelsAndTextHeader(): void
    {
        $columns = ['firstName' => ColumnMetadata::create('firstName', group: 'name')];

        $slots = $this->provider->build($columns, []);

        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertSame(ColumnGroup::SUB_LABELS_SHOW, $group->subLabels);
        $this->assertSame(ColumnGroup::HEADER_TEXT, $group->header);
    }

    /** @test */
    public function buildGroupAppearsAtPositionOfFirstMember(): void
    {
        $columns = [
            'id'        => ColumnMetadata::create('id'),
            'firstName' => ColumnMetadata::create('firstName', group: 'name'),
            'email'     => ColumnMetadata::create('email'),
            'lastName'  => ColumnMetadata::create('lastName', group: 'name'),
        ];

        $slots = $this->provider->build($columns, []);

        // id, [group: name], email — lastName appended to the group at slot[1]
        $this->assertCount(3, $slots);
        $this->assertSame('id', $slots[0]);
        $this->assertInstanceOf(ColumnGroup::class, $slots[1]);
        $this->assertSame('email', $slots[2]);
        /** @var ColumnGroup $group */
        $group = $slots[1];
        $this->assertArrayHasKey('firstName', $group->columns);
        $this->assertArrayHasKey('lastName', $group->columns);
    }

    /** @test */
    public function buildPreservesColumnOrderWithinGroup(): void
    {
        $columns = [
            'firstName' => ColumnMetadata::create('firstName', group: 'name'),
            'lastName'  => ColumnMetadata::create('lastName', group: 'name'),
        ];

        $slots = $this->provider->build($columns, []);

        /** @var ColumnGroup $group */
        $group = $slots[0];
        $this->assertSame(['firstName', 'lastName'], array_keys($group->columns));
    }

    /** @test */
    public function buildHandlesMultipleIndependentGroups(): void
    {
        $columns = [
            'firstName' => ColumnMetadata::create('firstName', group: 'name'),
            'lastName'  => ColumnMetadata::create('lastName', group: 'name'),
            'city'      => ColumnMetadata::create('city', group: 'address'),
            'country'   => ColumnMetadata::create('country', group: 'address'),
        ];

        $slots = $this->provider->build($columns, []);

        $this->assertCount(2, $slots);
        $this->assertInstanceOf(ColumnGroup::class, $slots[0]);
        $this->assertInstanceOf(ColumnGroup::class, $slots[1]);
        /** @var ColumnGroup $nameGroup */
        $nameGroup = $slots[0];
        /** @var ColumnGroup $addrGroup */
        $addrGroup = $slots[1];
        $this->assertSame('name', $nameGroup->id);
        $this->assertSame('address', $addrGroup->id);
    }

    /** @test */
    public function buildReturnsEmptySlotsForEmptyColumns(): void
    {
        $slots = $this->provider->build([], []);

        $this->assertSame([], $slots);
    }
}
