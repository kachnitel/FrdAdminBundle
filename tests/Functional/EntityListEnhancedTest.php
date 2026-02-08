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
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        $inner = $component->component();
        $this->assertSame(TestEntity::class, $inner->entityClass);
        $this->assertSame('', $inner->search);
        $this->assertSame(1, $inner->page);
        $this->assertSame([], $inner->columnFilters);
        $this->assertSame([], $inner->selectedIds);
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

        $inner = $component->component();
        $this->assertSame(TestEntity::class, $inner->entityClass);
        $this->assertSame('TestEntity', $inner->entityShortClass);
        $this->assertTrue($inner->isDoctrineEntity());
    }

    /**
     * @test
     */
    public function componentAcceptsSearchParameter(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'search' => 'test search term',
            ],
        );

        $inner = $component->component();
        $this->assertSame('test search term', $inner->search);
    }

    /**
     * @test
     */
    public function componentAcceptsSortingParameters(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'sortBy' => 'name',
                'sortDirection' => 'ASC',
            ],
        );

        $inner = $component->component();
        $this->assertSame('name', $inner->sortBy);
        $this->assertSame('ASC', $inner->sortDirection);
    }

    /**
     * @test
     */
    public function componentAcceptsPaginationParameters(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'page' => 2,
                'itemsPerPage' => 50,
            ],
        );

        $inner = $component->component();
        $this->assertSame(2, $inner->page);
        $this->assertSame(50, $inner->itemsPerPage);
    }

    /**
     * @test
     */
    public function componentAcceptsColumnFilters(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'columnFilters' => ['name' => 'test', 'status' => 'active'],
            ],
        );

        $inner = $component->component();
        $this->assertSame(['name' => 'test', 'status' => 'active'], $inner->columnFilters);
    }

    /**
     * @test
     */
    public function componentAcceptsSelectedIds(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'selectedIds' => [1, 2, 3],
            ],
        );

        $inner = $component->component();
        $this->assertSame([1, 2, 3], $inner->selectedIds);
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

        $inner = $component->component();
        $this->assertSame('search_term', $inner->search);
        $this->assertSame('name', $inner->sortBy);
        $this->assertSame('DESC', $inner->sortDirection);
        $this->assertSame(['status' => 'active'], $inner->columnFilters);
        $this->assertSame(1, $inner->page);
        $this->assertSame(15, $inner->itemsPerPage);
        $this->assertSame([1], $inner->selectedIds);

        $rendered = (string) $component->render();
        $this->assertStringContainsString('<table', $rendered);
    }

    /**
     * @test
     */
    public function componentHandlesEmptySearch(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'search' => '',
            ],
        );

        $inner = $component->component();
        $this->assertSame('', $inner->search);
    }

    /**
     * @test
     */
    public function componentHandlesEmptyColumnFilters(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'columnFilters' => [],
            ],
        );

        $inner = $component->component();
        $this->assertSame([], $inner->columnFilters);
    }

    /**
     * @test
     */
    public function componentHandlesEmptySelectedIds(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'selectedIds' => [],
            ],
        );

        $inner = $component->component();
        $this->assertSame([], $inner->selectedIds);
    }
}
