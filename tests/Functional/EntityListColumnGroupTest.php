<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\DataSource\ColumnGroup;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSourceFactory;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithGroupedColumns;
use Kachnitel\AdminBundle\Twig\Components\EntityList;

/**
 * Functional tests for composite/grouped columns feature.
 *
 * @group composite-columns
 */
class EntityListColumnGroupTest extends ComponentTestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // DataSource: getColumnGroups()
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function dataSourceReturnsColumnGroupForGroupedColumns(): void
    {
        /** @var DoctrineDataSourceFactory $factory */
        $factory = static::getContainer()->get(DoctrineDataSourceFactory::class);
        $ds = $factory->create(EntityWithGroupedColumns::class);

        $this->assertNotNull($ds);
        $slots = $ds->getColumnGroups();

        // Should have: id (ungrouped), ColumnGroup(firstName+lastName), email (ungrouped)
        $groupSlots = array_filter($slots, fn ($s) => $s instanceof ColumnGroup);
        $this->assertCount(1, $groupSlots);

        /** @var ColumnGroup $group */
        $group = array_values($groupSlots)[0];
        $this->assertSame('name_block', $group->id);
        $this->assertSame('Name block', $group->label);
        $this->assertArrayHasKey('firstName', $group->columns);
        $this->assertArrayHasKey('lastName', $group->columns);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // EntityList component: getColumnSlots()
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function componentGetColumnSlotsReturnsGroupForGroupedEntity(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithGroupedColumns::class,
                'entityShortClass' => 'EntityWithGroupedColumns',
            ],
        );

        $slots = $testComponent->component()->getColumnSlots();

        $groupSlots = array_filter($slots, fn ($s) => $s instanceof ColumnGroup);
        $this->assertCount(1, $groupSlots);
    }

    /** @test */
    public function componentGetColumnSlotsUngroupedColumnsRemainAsStrings(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithGroupedColumns::class,
                'entityShortClass' => 'EntityWithGroupedColumns',
            ],
        );

        $slots = $testComponent->component()->getColumnSlots();

        $stringSlots = array_filter($slots, fn ($s) => is_string($s));
        // id and email should be ungrouped strings
        $this->assertContains('id', $stringSlots);
        $this->assertContains('email', $stringSlots);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Template rendering
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function compositeHeaderIsRenderedForGroup(): void
    {
        $entity = new EntityWithGroupedColumns();
        $entity->setFirstName('Jane');
        $entity->setLastName('Doe');
        $entity->setEmail('jane@example.com');
        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithGroupedColumns::class,
                'entityShortClass' => 'EntityWithGroupedColumns',
            ],
        );

        $html = (string) $testComponent->render();

        // Group label should appear in header
        $this->assertStringContainsString('Name block', $html);
        // Sub-column labels should appear inside the composite header
        $this->assertStringContainsString('First name', $html);
        $this->assertStringContainsString('Last name', $html);
    }

    /** @test */
    public function compositeBodyCellRendersSubColumnValues(): void
    {
        $entity = new EntityWithGroupedColumns();
        $entity->setFirstName('Jane');
        $entity->setLastName('Doe');
        $entity->setEmail('jane@example.com');
        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithGroupedColumns::class,
                'entityShortClass' => 'EntityWithGroupedColumns',
            ],
        );

        $html = (string) $testComponent->render();

        $this->assertStringContainsString('Jane', $html);
        $this->assertStringContainsString('Doe', $html);
        $this->assertStringContainsString('jane@example.com', $html);
    }

    /** @test */
    public function ungroupedColumnAppearsAsRegularColumn(): void
    {
        $entity = new EntityWithGroupedColumns();
        $entity->setFirstName('Alice');
        $entity->setLastName('Smith');
        $entity->setEmail('alice@example.com');
        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithGroupedColumns::class,
                'entityShortClass' => 'EntityWithGroupedColumns',
            ],
        );

        $html = (string) $testComponent->render();

        // Email column header
        $this->assertStringContainsString('Email', $html);
        $this->assertStringContainsString('alice@example.com', $html);
    }

    /** @test */
    public function isColumnGroupHelperReturnsTrueForColumnGroup(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithGroupedColumns::class,
                'entityShortClass' => 'EntityWithGroupedColumns',
            ],
        );

        $component = $testComponent->component();

        $group = new ColumnGroup('test', 'Test', []);
        $this->assertTrue($component->isColumnGroup($group));
        $this->assertFalse($component->isColumnGroup('plain_column'));
    }
}
