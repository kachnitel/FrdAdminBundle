<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\AdminColumnGroup;
use Kachnitel\DataSourceContracts\ColumnGroup;
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
}
