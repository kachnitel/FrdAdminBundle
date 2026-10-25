<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Service\CollectionScopeResolver;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\DataSourceContracts\PaginatedResult;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityList::class)]
#[UsesClass(EntityListConfig::class)]
#[AllowMockObjectsWithoutExpectations]
final class EntityListNavigationTest extends TestCase
{
    /** @var DataSourceInterface&MockObject */
    private DataSourceInterface $dataSource;

    private EntityList $component;

    protected function setUp(): void
    {
        $this->dataSource = $this->createMock(DataSourceInterface::class);
        $this->dataSource->method('getColumns')->willReturn([
            'id' => new ColumnMetadata('id', 'ID'),
            'name' => new ColumnMetadata('name', 'Name'),
            'relation' => new ColumnMetadata('relation', 'Relation', sortable: false),
        ]);

        $registry = $this->createStub(DataSourceRegistry::class);
        $registry->method('resolve')->willReturn($this->dataSource);

        $permissionService = $this->createStub(EntityListPermissionService::class);
        $permissionService->method('canViewList')->willReturn(true);

        $this->component = new EntityList(
            $permissionService,
            new EntityListConfig(),
            $registry,
            $this->createStub(EntityListBatchService::class),
            $this->createStub(AdminPreferencesStorageInterface::class),
            $this->createStub(EntityListColumnService::class),
            $this->createStub(ArchiveService::class),
            $this->createStub(ObjectAuthorizationChecker::class),
            $this->createStub(CollectionScopeResolver::class),
        );
        $this->component->dataSourceId = 'test';
    }

    public function testNextPageAdvancesUntilTheLastPage(): void
    {
        $this->dataSource->expects($this->exactly(2))
            ->method('query')
            ->willReturnOnConsecutiveCalls(
                new PaginatedResult([], 20, 1, 10),
                new PaginatedResult([], 20, 2, 10),
            );
        $this->component->itemsPerPage = 10;

        $this->component->nextPage();
        self::assertSame(2, $this->component->page);

        $this->component->nextPage();
        self::assertSame(2, $this->component->page);
    }

    public function testNextPageDoesNothingWhenThereAreNoResults(): void
    {
        $this->dataSource->expects($this->once())
            ->method('query')
            ->willReturn(new PaginatedResult([], 0, 1, 20));

        $this->component->nextPage();

        self::assertSame(1, $this->component->page);
    }

    public function testPreviousPageOnlyMovesBackWhenNotOnTheFirstPage(): void
    {
        $this->component->previousPage();
        self::assertSame(1, $this->component->page);

        $this->component->page = 3;
        $this->component->previousPage();
        self::assertSame(2, $this->component->page);
    }

    public function testGoToPageClampsRequestedPageToAvailableRange(): void
    {
        $this->dataSource->expects($this->exactly(2))
            ->method('query')
            ->willReturnOnConsecutiveCalls(
                new PaginatedResult([], 25, 1, 10),
                new PaginatedResult([], 25, 3, 10),
            );
        $this->component->itemsPerPage = 10;

        $this->component->goToPage(0);
        self::assertSame(1, $this->component->page);

        $this->component->goToPage(100);
        self::assertSame(3, $this->component->page);
    }

    public function testGoToPageKeepsPageOneWhenThereAreNoResults(): void
    {
        $this->dataSource->expects($this->once())
            ->method('query')
            ->willReturn(new PaginatedResult([], 0, 1, 20));

        $this->component->goToPage(5);

        self::assertSame(1, $this->component->page);
    }

    public function testFilterUpdatesResetPageAndRetainFilterValue(): void
    {
        $this->component->page = 4;
        $this->component->onFilterUpdated('status', '');

        self::assertSame(1, $this->component->page);
        self::assertSame(['status' => ''], $this->component->columnFilters);

        $this->component->page = 3;
        $this->component->columnFilters = ['status' => 'active'];
        $this->component->onColumnFiltersUpdated();

        self::assertSame(1, $this->component->page);
        self::assertSame(['status' => 'active'], $this->component->columnFilters);
    }

    public function testSortIgnoresNonSortableColumnAndNormalizesUnknownDirection(): void
    {
        $this->component->page = 3;
        $this->component->sortDirection = EntityList::SORT_DESC;
        $this->component->sort('relation');

        self::assertSame('id', $this->component->sortBy);
        self::assertSame(EntityList::SORT_DESC, $this->component->sortDirection);
        self::assertSame(3, $this->component->page);

        $this->component->sortDirection = 'unexpected';
        $this->component->sort('id');

        self::assertSame('id', $this->component->sortBy);
        self::assertSame(EntityList::SORT_ASC, $this->component->sortDirection);
        self::assertSame(1, $this->component->page);
    }

    public function testSortIgnoresUnknownColumn(): void
    {
        $this->component->page = 3;
        $this->component->sortBy = 'id';
        $this->component->sortDirection = EntityList::SORT_DESC;

        $this->component->sort('missing');

        self::assertSame('id', $this->component->sortBy);
        self::assertSame(EntityList::SORT_DESC, $this->component->sortDirection);
        self::assertSame(3, $this->component->page);
    }

    public function testSortChangesColumnWithoutChangingDirection(): void
    {
        $this->component->page = 3;
        $this->component->sortDirection = EntityList::SORT_DESC;

        $this->component->sort('name');

        self::assertSame('name', $this->component->sortBy);
        self::assertSame(EntityList::SORT_DESC, $this->component->sortDirection);
        self::assertSame(1, $this->component->page);
    }

    public function testSortTogglesAscendingColumnToDescending(): void
    {
        $this->component->sortBy = 'id';
        $this->component->sortDirection = EntityList::SORT_ASC;

        $this->component->sort('id');

        self::assertSame(EntityList::SORT_DESC, $this->component->sortDirection);
    }
}
