<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithCustomColumns;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithRelationInColumns;
use Kachnitel\AdminBundle\Twig\Components\EntityList;

/**
 * Verifies that the EntityList component handles non-sortable columns correctly:
 * - Sort buttons are not rendered for non-sortable columns
 * - Sorting by a non-sortable column (e.g. via URL manipulation) silently falls back to default
 *
 * @group sorting
 */
class EntityListSortingTest extends ComponentTestCase
{
    /**
     * Sorting by a ManyToOne association column must fall back to the default
     * sort field rather than throwing a DQL error.
     *
     * @test
     */
    public function sortByAssociationColumnFallsBackToDefault(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithRelationInColumns::class,
                'entityShortClass' => 'EntityWithRelationInColumns',
                'sortBy' => 'relatedEntity', // non-sortable association column
                'sortDirection' => 'ASC',
            ],
        );

        // Capture the component reference ONCE — calling component() multiple times
        // may return a fresh hydrated instance, discarding mutations made by getEntities().
        $entityList = $testComponent->component();

        // getEntities() must not throw and must silently reset sortBy to default
        $entityList->getEntities();

        $this->assertNotSame(
            'relatedEntity',
            $entityList->sortBy,
            'sortBy must be reset to default when column is not sortable',
        );
    }

    /**
     * Sorting by a custom (#[AdminCustomColumn]) column must fall back gracefully.
     *
     * @test
     */
    public function sortByCustomColumnFallsBackToDefault(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithCustomColumns::class,
                'entityShortClass' => 'EntityWithCustomColumns',
                'sortBy' => 'fullName', // custom column — not sortable by default
                'sortDirection' => 'ASC',
            ],
        );

        $entityList = $testComponent->component();
        $entityList->getEntities();

        $this->assertNotSame(
            'fullName',
            $entityList->sortBy,
            'sortBy must be reset when targeting a non-sortable custom column',
        );
    }

    /**
     * Invoking the sort LiveAction on a non-sortable column must be silently ignored.
     *
     * @test
     */
    public function sortActionOnNonSortableColumnIsIgnored(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithRelationInColumns::class,
                'entityShortClass' => 'EntityWithRelationInColumns',
                'sortBy' => 'id',
                'sortDirection' => 'DESC',
            ],
        );

        $testComponent->call('sort', ['column' => 'relatedEntity']);

        $entityList = $testComponent->component();

        $this->assertSame(
            'id',
            $entityList->sortBy,
            'sort() action must not update sortBy to a non-sortable column',
        );
        $this->assertSame(
            'DESC',
            $entityList->sortDirection,
            'sortDirection must remain unchanged when sort is rejected',
        );
    }

    /**
     * Sort buttons must not be rendered for association (non-sortable) columns.
     *
     * @test
     */
    public function sortButtonIsAbsentForAssociationColumn(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithRelationInColumns::class,
                'entityShortClass' => 'EntityWithRelationInColumns',
            ],
        );

        $rendered = (string) $testComponent->render();

        // The "relatedEntity" header must appear as plain text, not wrapped in a sort button
        $this->assertStringContainsString('Related entity', $rendered);

        // The sort button for relatedEntity must NOT be present
        $this->assertStringNotContainsString('data-live-column-param="relatedEntity"', $rendered);
    }

    /**
     * Sort buttons must still be rendered for regular sortable fields.
     *
     * @test
     */
    public function sortButtonIsPresentForRegularField(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithRelationInColumns::class,
                'entityShortClass' => 'EntityWithRelationInColumns',
            ],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('data-live-column-param="id"', $rendered);
        $this->assertStringContainsString('data-live-column-param="title"', $rendered);
    }
}
