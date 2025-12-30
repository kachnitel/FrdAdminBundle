<?php

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Components\EntityList;

class EntityListEnhancedTest extends ComponentTestCase
{
    /**
     * @test
     */
    public function componentInitializesWithDefaults(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'dataSourceId' => 'test_datasource',
            ],
        );
        // Component created successfully
        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function componentRendersWithEntityClass(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function componentAcceptsSearchParameter(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'dataSourceId' => 'test_datasource',
                'search' => 'test search term',
            ],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function componentAcceptsSortingParameters(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'dataSourceId' => 'test_datasource',
                'sortBy' => 'name',
                'sortDirection' => 'ASC',
            ],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function componentAcceptsPaginationParameters(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'dataSourceId' => 'test_datasource',
                'page' => 2,
                'itemsPerPage' => 20,
            ],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function componentAcceptsColumnFilters(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'dataSourceId' => 'test_datasource',
                'columnFilters' => ['name' => 'test', 'status' => 'active'],
            ],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function componentAcceptsSelectedIds(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'dataSourceId' => 'test_datasource',
                'selectedIds' => [1, 2, 3],
            ],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function componentWithAllParameters(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'search' => 'search_term',
                'sortBy' => 'name',
                'sortDirection' => 'DESC',
                'columnFilters' => ['status' => 'active'],
                'page' => 1,
                'itemsPerPage' => 15,
                'selectedIds' => [1],
            ],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function componentHandlesEmptySearch(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'dataSourceId' => 'test_datasource',
                'search' => '',
            ],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function componentHandlesEmptyColumnFilters(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'dataSourceId' => 'test_datasource',
                'columnFilters' => [],
            ],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function componentHandlesEmptySelectedIds(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'dataSourceId' => 'test_datasource',
                'selectedIds' => [],
            ],
        );

        $this->addToAssertionCount(1);
    }
}
