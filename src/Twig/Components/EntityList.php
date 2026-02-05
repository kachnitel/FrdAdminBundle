<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Service\Preferences\ColumnVisibilityPreferenceTrait;
use Kachnitel\AdminBundle\ValueObject\PaginationInfo;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * LiveComponent for reactive entity lists with per-column search/filter, sorting, and pagination.
 *
 * Supports two modes:
 * 1. DataSource mode: Pass dataSourceId for any DataSourceInterface implementation
 * 2. Entity mode: Pass entityClass and entityShortClass for Doctrine entities
 *
 * Both modes use the DataSource abstraction. When using entity mode, a DoctrineDataSource
 * is resolved from the registry or created on-demand by the factory.
 *
 * Security: Requires ADMIN_INDEX permission for the entity/data source being displayed.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) LiveComponent requires public methods for LiveActions
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Component bridges UI, data sources, and services
 */
#[AsLiveComponent('K:Admin:EntityList', template: '@KachnitelAdmin/components/EntityList.html.twig')]
class EntityList
{
    public const SORT_ASC = 'ASC';
    public const SORT_DESC = 'DESC';

    use DefaultActionTrait;
    use ColumnVisibilityPreferenceTrait;

    #[LiveProp(writable: true, url: true)]
    public string $search = '';

    #[LiveProp(writable: true, url: true)]
    public string $sortBy = 'id';

    #[LiveProp(writable: true, url: true)]
    public string $sortDirection = self::SORT_DESC;

    /**
     * Column-specific filter values.
     * Format: ['columnName' => 'filterValue', ...]
     *
     * @var array<string, mixed>
     */
    #[LiveProp(writable: true, url: true, onUpdated: 'onColumnFiltersUpdated')]
    public array $columnFilters = [];

    #[LiveProp(writable: true, url: true)]
    public int $page = 1;

    #[LiveProp(writable: true, url: true)]
    public int $itemsPerPage;

    /**
     * Selected entity IDs for batch actions.
     *
     * @var array<int|string>
     */
    #[LiveProp(writable: true)]
    public array $selectedIds = [];

    /**
     * Column names currently hidden by the user.
     *
     * @var array<string>
     */
    #[LiveProp(writable: true)]
    public array $hiddenColumns = [];

    /**
     * Data source identifier (alternative to entityClass).
     * When set, the component uses DataSourceRegistry to resolve the data source.
     */
    #[LiveProp]
    public ?string $dataSourceId = null;

    /**
     * Entity class for Doctrine entities.
     * Used when dataSourceId is not set. A DoctrineDataSource will be resolved or created.
     */
    #[LiveProp]
    public string $entityClass = '';

    /**
     * Entity short class for Doctrine entities.
     * Used as the identifier for registry lookup.
     */
    #[LiveProp]
    public string $entityShortClass = '';

    #[LiveProp]
    public ?string $repositoryMethod = null;

    /** @var array<int> Allowed items per page options */
    public array $allowedItemsPerPage;

    /**
     * Internal cache for query results and resolved data source.
     *
     * @var array{
     *     queryResult?: PaginatedResult,
     *     filterMetadata?: array<string, array<string, mixed>>,
     *     columns?: array<int|string, string>,
     *     dataSource?: DataSourceInterface,
     *     dataSourceResolved?: bool,
     *     visibilityLoaded?: bool
     * }
     */
    private array $cache = [];

    public function __construct(
        public readonly EntityListPermissionService $permissionService,
        private Security $security,
        private EntityListConfig $config,
        private DataSourceRegistry $dataSourceRegistry,
        private EntityListBatchService $batchService,
        private AdminPreferencesStorageInterface $preferencesStorage,
        private ColumnPermissionService $columnPermissionService,
    ) {
        $this->itemsPerPage = $this->config->defaultItemsPerPage;
        $this->allowedItemsPerPage = $this->config->allowedItemsPerPage;
    }

    // --- Security ---

    /**
     * Check permissions after component hydration.
     */
    #[PostHydrate]
    public function checkPermissions(): void
    {
        $identifier = $this->dataSourceId ?? $this->entityShortClass;

        if (!$this->security->isGranted(AdminEntityVoter::ADMIN_INDEX, $identifier)) {
            throw new AccessDeniedException(sprintf(
                'Access denied to view %s.',
                $identifier
            ));
        }
    }

    // --- Data Source Resolution ---

    /**
     * Resolve the data source from registry or create from entity class.
     */
    private function resolveDataSource(): DataSourceInterface
    {
        return $this->cache['dataSource'] ??= $this->dataSourceRegistry->resolve(
            $this->dataSourceId,
            $this->entityShortClass,
            $this->entityClass,
        );
    }

    /**
     * Get the resolved data source.
     */
    public function getDataSource(): DataSourceInterface
    {
        return $this->resolveDataSource();
    }

    /**
     * Check if this is a Doctrine entity (for template rendering mode).
     */
    public function isDoctrineEntity(): bool
    {
        return $this->entityClass !== '';
    }

    /**
     * Check if user can perform batch delete on this data source.
     */
    public function canBatchDelete(): bool
    {
        if (!$this->supportsBatchActions()) {
            return false;
        }

        // For Doctrine entities, check permissions via permission service
        if ($this->entityClass !== '') {
            return $this->permissionService->canBatchDelete($this->entityClass, $this->entityShortClass);
        }

        // For non-Doctrine data sources, check ADMIN_DELETE permission on the identifier
        $identifier = $this->dataSourceId ?? $this->entityShortClass;
        return $this->security->isGranted(AdminEntityVoter::ADMIN_DELETE, $identifier);
    }

    // --- UI ---

    /**
     * Get filtered, sorted, and paginated entities.
     *
     * @return array<object>
     */
    public function getEntities(): array
    {
        if (isset($this->cache['queryResult'])) {
            return $this->cache['queryResult']->items;
        }

        // Fall back to default sort if current sortBy column is permission-denied
        if (!in_array($this->sortBy, $this->getColumns(), true)) {
            $this->sortBy = $this->getDataSource()->getDefaultSortBy();
            $this->sortDirection = $this->getDataSource()->getDefaultSortDirection();
        }

        $this->cache['queryResult'] = $this->getDataSource()->query(
            search: $this->search,
            filters: $this->columnFilters,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            page: $this->page,
            itemsPerPage: $this->itemsPerPage
        );

        // Update page if clamped by query
        $this->page = $this->cache['queryResult']->currentPage;

        return $this->cache['queryResult']->items;
    }

    /**
     * Get pagination information (pages, start/end items).
     */
    public function getPaginationInfo(): PaginationInfo
    {
        if (!isset($this->cache['queryResult'])) {
            $this->getEntities();
        }

        return $this->cache['queryResult']->toPaginationInfo();
    }

    #[LiveAction]
    public function sort(#[LiveArg] string $column): void
    {
        if ($column === $this->sortBy) {
            $this->sortDirection = match($this->sortDirection) {
                self::SORT_ASC => self::SORT_DESC,
                default => self::SORT_ASC
            };
        }
        $this->sortBy = $column;

        // Reset to first page when sorting changes
        $this->page = 1;
        unset($this->cache['queryResult']);
    }

    #[LiveAction]
    public function nextPage(): void
    {
        if ($this->page < $this->getPaginationInfo()->getTotalPages()) {
            $this->page++;
            unset($this->cache['queryResult']);
        }
    }

    #[LiveAction]
    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            unset($this->cache['queryResult']);
        }
    }

    #[LiveAction]
    public function goToPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, min($page, $this->getPaginationInfo()->getTotalPages()));
        unset($this->cache['queryResult']);
    }

    /**
     * Handles ColumnFilter emitUp event (for custom template overrides using the component).
     */
    #[LiveListener('filter:updated')]
    public function onFilterUpdated(#[LiveArg] string $column, #[LiveArg] mixed $value): void
    {
        $this->columnFilters[$column] = $value;
        $this->page = 1;
        unset($this->cache['queryResult']);
    }

    /**
     * Called when columnFilters LiveProp is updated via data-model binding.
     */
    public function onColumnFiltersUpdated(): void
    {
        $this->page = 1;
        unset($this->cache['queryResult']);
    }

    /**
     * Batch delete selected entities.
     */
    #[LiveAction]
    public function batchDelete(): void
    {
        $this->batchService->batchDelete(
            $this->selectedIds,
            $this->getDataSource(),
            $this->entityClass,
            $this->entityShortClass,
        );

        $this->selectedIds = [];
        unset($this->cache['queryResult']);
    }

    /**
     * Select all entities on current page.
     */
    #[LiveAction]
    public function selectAll(): void
    {
        $newIds = $this->batchService->getEntityIds(
            $this->getEntities(),
            $this->getDataSource(),
        );

        $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $newIds)));
    }

    /**
     * Deselect all entities.
     */
    #[LiveAction]
    public function deselectAll(): void
    {
        $this->selectedIds = [];
    }

    /**
     * Get filter metadata for template rendering (filtered by column permissions).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFilterMetadata(): array
    {
        if (!isset($this->cache['filterMetadata'])) {
            $permittedColumns = $this->getColumns();
            $this->cache['filterMetadata'] = [];
            foreach ($this->getDataSource()->getFilters() as $name => $filter) {
                if (in_array($name, $permittedColumns, true)) {
                    $this->cache['filterMetadata'][$name] = $filter->toArray();
                }
            }
        }
        return $this->cache['filterMetadata'];
    }

    /**
     * Get columns for display (filtered by column permissions).
     *
     * @return array<int|string, string>
     */
    public function getColumns(): array
    {
        if (!isset($this->cache['columns'])) {
            $allColumns = array_keys($this->getDataSource()->getColumns());

            if ($this->entityClass !== '') {
                $denied = $this->columnPermissionService->getDeniedColumns($this->entityClass);
                $allColumns = array_values(array_filter(
                    $allColumns,
                    fn(string $col) => !in_array($col, $denied, true)
                ));
            }

            $this->cache['columns'] = $allColumns;
        }
        return $this->cache['columns'];
    }

    /**
     * Check if batch actions are supported for this data source.
     */
    public function supportsBatchActions(): bool
    {
        return $this->getDataSource()->supportsAction('batch_delete');
    }

    /**
     * Check if column visibility toggle is supported for this data source.
     */
    public function supportsColumnVisibility(): bool
    {
        return $this->getDataSource()->supportsAction('column_visibility');
    }

    /**
     * Get columns that are currently visible (all columns minus hidden ones).
     *
     * Lazy-loads hidden columns from preferences storage on first access,
     * which handles both initial page render and LiveComponent re-renders.
     *
     * @return array<int|string, string>
     */
    public function getVisibleColumns(): array
    {
        $allColumns = $this->getColumns();

        // Lazy-load from storage if not yet loaded (covers initial mount where PostHydrate doesn't fire)
        if ($this->supportsColumnVisibility() && empty($this->hiddenColumns) && !isset($this->cache['visibilityLoaded'])) {
            $this->hiddenColumns = $this->loadHiddenColumns();
            $this->cache['visibilityLoaded'] = true;
        }

        if (empty($this->hiddenColumns)) {
            return $allColumns;
        }

        return array_values(array_filter(
            $allColumns,
            fn(string $col) => !in_array($col, $this->hiddenColumns, true)
        ));
    }

    /**
     * Toggle column visibility.
     */
    #[LiveAction]
    public function toggleColumnVisibility(#[LiveArg] string $column): void
    {
        if (in_array($column, $this->hiddenColumns, true)) {
            // Show the column
            $this->hiddenColumns = array_values(array_diff($this->hiddenColumns, [$column]));
        } else {
            // Hide the column
            $this->hiddenColumns[] = $column;
        }

        // Save to preferences storage
        $this->saveHiddenColumns($this->hiddenColumns);

        // Clear cached query result to trigger re-render
        unset($this->cache['queryResult']);
    }

    /**
     * Load column visibility preferences after hydration.
     *
     * On subsequent LiveComponent requests, hiddenColumns is hydrated from
     * the client (writable LiveProp). We only load from storage if empty.
     * The cache flag prevents getVisibleColumns() from double-loading.
     */
    #[PostHydrate]
    public function loadColumnVisibility(): void
    {
        if ($this->supportsColumnVisibility() && empty($this->hiddenColumns)) {
            $this->hiddenColumns = $this->loadHiddenColumns();
        }
        $this->cache['visibilityLoaded'] = true;
    }

    /**
     * Get the preferences storage instance (required by ColumnVisibilityPreferenceTrait).
     */
    protected function getPreferencesStorage(): AdminPreferencesStorageInterface
    {
        return $this->preferencesStorage;
    }

    /**
     * Get the list identifier for this component (required by ColumnVisibilityPreferenceTrait).
     */
    protected function getListIdentifier(): string
    {
        return $this->dataSourceId ?? $this->entityShortClass;
    }

    /**
     * Get the value of a field for an entity.
     */
    public function getEntityValue(object $entity, string $field): mixed
    {
        return $this->getDataSource()->getItemValue($entity, $field);
    }

    /**
     * Get the ID of an entity.
     */
    public function getEntityId(object $entity): string|int
    {
        return $this->getDataSource()->getItemId($entity);
    }
}
