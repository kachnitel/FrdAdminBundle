<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\DataSourceContracts\ColumnGroup;
use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\DataSourceContracts\PaginatedResult;
use Kachnitel\DataSourceContracts\SearchAwareDataSourceInterface;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Service\Preferences\ColumnVisibilityPreferenceTrait;
use Kachnitel\DataSourceContracts\PaginationInfo;
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
 * @SuppressWarnings(PHPMD.ExcessivePublicCount) LiveComponent requires public LiveProps and LiveActions;
 *     each #[LiveProp] is a URL-synchronised state variable and must be public for the framework to
 *     hydrate it. Core logic is already decomposed into EntityListQueryService,
 *     EntityListBatchService, EntityListPermissionService, etc.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Component bridges UI, data sources, and services
 * @SuppressWarnings(PHPMD.TooManyFields) Each LiveProp is a URL-synchronised state variable; the
 *     count is an architectural consequence of LiveComponent, not avoidable design debt.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) WMC is inflated by many small public
 *     LiveAction methods that the framework requires to be public. Core logic is already
 *     decomposed into EntityListQueryService, EntityListBatchService, EntityListPermissionService, etc.
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
     *
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

    /**
     * Integer PK of the row currently in edit mode. Null = no row is being edited.
     * ?int — nullable because no row is selected by default. Not a union type.
     */
    #[LiveProp(writable: true)]
    public ?int $editingRowId = null;

    /** @var array<int> Allowed items per page options */
    public array $allowedItemsPerPage;

    /**
     * Internal cache for query results and resolved data source.
     *
     * @var array{
     *     queryResult?: PaginatedResult,
     *     filterMetadata?: array<string, array<string, mixed>>,
     *     columns?: array<int|string, string>,
     *     columnSlots?: list<string|ColumnGroup>,
     *     dataSource?: DataSourceInterface,
     *     dataSourceResolved?: bool,
     *     visibilityLoaded?: bool
     * }
     */
    private array $cache = [];

    public function __construct(
        public readonly EntityListPermissionService $permissionService,
        private EntityListConfig $config,
        private DataSourceRegistry $dataSourceRegistry,
        private EntityListBatchService $batchService,
        private AdminPreferencesStorageInterface $preferencesStorage,
        private EntityListColumnService $columnService,
    ) {
        $this->itemsPerPage = $this->config->defaultItemsPerPage;
        $this->allowedItemsPerPage = $this->config->allowedItemsPerPage;
    }

    // ── Security ───────────────────────────────────────────────────────────────

    /**
     * Check permissions after component hydration.
     */
    #[PostHydrate]
    public function checkPermissions(): void
    {
        $identifier = $this->dataSourceId ?? $this->entityShortClass;

        if (!$this->permissionService->canViewList($identifier)) {
            throw new AccessDeniedException(sprintf(
                'Access denied to view %s.',
                $identifier
            ));
        }
    }

    // ── Data Source Resolution ─────────────────────────────────────────────────

    private function resolveDataSource(): DataSourceInterface
    {
        return $this->cache['dataSource'] ??= $this->dataSourceRegistry->resolve(
            $this->dataSourceId,
            $this->entityShortClass,
            $this->entityClass,
        );
    }

    public function getDataSource(): DataSourceInterface
    {
        return $this->resolveDataSource();
    }

    public function isDoctrineEntity(): bool
    {
        return $this->entityClass !== '';
    }

    public function canBatchDelete(): bool
    {
        if (!$this->supportsBatchActions()) {
            return false;
        }

        return $this->permissionService->canBatchDelete(
            $this->entityClass,
            $this->entityShortClass,
            $this->dataSourceId,
        );
    }

    /**
     * Returns human-readable labels for columns included in global search.
     *
     * Delegates to the resolved DataSource when it implements
     * SearchAwareDataSourceInterface. Returns an empty array for custom
     * DataSources that do not implement the interface, so no tooltip is rendered.
     *
     * @return array<string>
     */
    public function getGlobalSearchColumnLabels(): array
    {
        $dataSource = $this->getDataSource();

        if (!$dataSource instanceof SearchAwareDataSourceInterface) {
            return [];
        }

        return $dataSource->getGlobalSearchColumnLabels();
    }

    // ── Queries ────────────────────────────────────────────────────────────────

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

        if (!$this->isSortableColumn($this->sortBy)) {
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

        $this->page = $this->cache['queryResult']->currentPage;

        return $this->cache['queryResult']->items;
    }

    public function getPaginationInfo(): PaginationInfo
    {
        if (!isset($this->cache['queryResult'])) {
            $this->getEntities();
        }

        return $this->cache['queryResult']->toPaginationInfo();
    }

    // ── LiveActions: sorting / pagination ─────────────────────────────────────

    #[LiveAction]
    public function sort(#[LiveArg] string $column): void
    {
        if (!$this->isSortableColumn($column)) {
            return;
        }

        if ($column === $this->sortBy) {
            $this->sortDirection = match ($this->sortDirection) {
                self::SORT_ASC => self::SORT_DESC,
                default        => self::SORT_ASC,
            };
        }
        $this->sortBy = $column;
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

    // ── LiveListeners ──────────────────────────────────────────────────────────

    #[LiveListener('filter:updated')]
    public function onFilterUpdated(#[LiveArg] string $column, #[LiveArg] mixed $value): void
    {
        $this->columnFilters[$column] = $value;
        $this->page = 1;
        unset($this->cache['queryResult']);
    }

    public function onColumnFiltersUpdated(): void
    {
        $this->page = 1;
        unset($this->cache['queryResult']);
    }

    // ── LiveActions: batch operations ─────────────────────────────────────────

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

    #[LiveAction]
    public function selectAll(): void
    {
        $newIds = $this->batchService->getEntityIds(
            $this->getEntities(),
            $this->getDataSource(),
        );

        $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $newIds)));
    }

    #[LiveAction]
    public function deselectAll(): void
    {
        $this->selectedIds = [];
    }

    // ── LiveActions: inline row editing ───────────────────────────────────────

    /**
     * Whether the current user may open rows of this entity type for inline editing.
     *
     * Delegated entirely to EntityListPermissionService, which checks both the
     * #[Admin(enableInlineEdit: true)] flag and the ADMIN_EDIT voter.
     *
     * Always returns false for non-Doctrine data sources.
     */
    public function canEditRow(): bool
    {
        return $this->permissionService->canInlineEdit(
            $this->entityClass,
            $this->entityShortClass,
        );
    }

    /**
     * Open a row for editing. Closes any currently open row first.
     */
    #[LiveAction]
    public function editRow(#[LiveArg] int $id): void
    {
        if (!$this->canEditRow()) {
            throw new AccessDeniedException('Access denied for inline editing.');
        }

        $this->editingRowId = $id;
    }

    /**
     * Whether a specific entity row is currently open for editing.
     */
    public function isRowEditing(object $entity): bool
    {
        if ($this->editingRowId === null) {
            return false;
        }

        if (!method_exists($entity, 'getId')) {
            return false;
        }

        return $entity->getId() === $this->editingRowId;
    }

    // ── Column / filter helpers ────────────────────────────────────────────────

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFilterMetadata(): array
    {
        return $this->cache['filterMetadata'] ??= $this->columnService->getPermittedFilters(
            $this->getDataSource(),
            $this->entityClass,
        );
    }

    /**
     * @return array<int|string, string>
     */
    public function getColumns(): array
    {
        return $this->cache['columns'] ??= $this->columnService->getPermittedColumns(
            $this->getDataSource(),
            $this->entityClass,
        );
    }

    public function supportsBatchActions(): bool
    {
        return $this->getDataSource()->supportsAction('batch_delete');
    }

    public function supportsColumnVisibility(): bool
    {
        return $this->getDataSource()->supportsAction('column_visibility');
    }

    /**
     * @return array<int|string, string>
     */
    public function getVisibleColumns(): array
    {
        $allColumns = $this->getColumns();

        if ($this->supportsColumnVisibility() && empty($this->hiddenColumns) && !isset($this->cache['visibilityLoaded'])) {
            $this->hiddenColumns = $this->loadHiddenColumns();
            $this->cache['visibilityLoaded'] = true;
        }

        if (empty($this->hiddenColumns)) {
            return $allColumns;
        }

        return array_values(array_filter(
            $allColumns,
            fn (string $col) => !in_array($col, $this->hiddenColumns, true)
        ));
    }

    /**
     * Return the ordered list of display slots for the list table header and body.
     *
     * Each slot is either:
     * - A plain column name (string) — renders as a regular <th>/<td>.
     * - A ColumnGroup — renders as a composite stacked <th>/<td>.
     *
     * Hidden columns are removed. For groups, only visible sub-columns are included.
     * A group with ALL sub-columns hidden is removed from the result entirely.
     *
     * @return list<string|ColumnGroup>
     */
    public function getColumnSlots(): array
    {
        if (isset($this->cache['columnSlots'])) {
            return $this->cache['columnSlots'];
        }

        $visibleColumns = $this->getVisibleColumns();
        $allSlots = $this->getDataSource()->getColumnGroups();

        $result = [];
        foreach ($allSlots as $slot) {
            if (is_string($slot)) {
                if (in_array($slot, $visibleColumns, true)) {
                    $result[] = $slot;
                }
            } else {
                // Filter sub-columns to those that are visible
                $visibleSubColumns = array_filter(
                    $slot->columns,
                    fn (ColumnMetadata $col) => in_array($col->name, $visibleColumns, true)
                );

                if (count($visibleSubColumns) > 0) {
                    $result[] = new ColumnGroup(
                        id: $slot->id,
                        label: $slot->label,
                        columns: $visibleSubColumns,
                        subLabels: $slot->subLabels,
                        header: $slot->header,
                    );
                }
            }
        }

        return $this->cache['columnSlots'] = $result;
    }

    /**
     * Returns true when the given slot is a ColumnGroup (composite), false for a plain column name.
     *
     * Used in Twig as `this.isColumnGroup(slot)` to branch between composite
     * and regular cell rendering.
     *
     * @param string|ColumnGroup $slot
     */
    public function isColumnGroup(string|ColumnGroup $slot): bool
    {
        return $slot instanceof ColumnGroup;
    }

    #[LiveAction]
    public function toggleColumnVisibility(#[LiveArg] string $column): void
    {
        if (in_array($column, $this->hiddenColumns, true)) {
            $this->hiddenColumns = array_values(array_diff($this->hiddenColumns, [$column]));
        } else {
            $this->hiddenColumns[] = $column;
        }

        $this->saveHiddenColumns($this->hiddenColumns);
        unset($this->cache['queryResult']);
        unset($this->cache['columnSlots']);
    }

    #[PostHydrate]
    public function loadColumnVisibility(): void
    {
        if ($this->supportsColumnVisibility() && empty($this->hiddenColumns)) {
            $this->hiddenColumns = $this->loadHiddenColumns();
        }
        $this->cache['visibilityLoaded'] = true;
    }

    protected function getPreferencesStorage(): AdminPreferencesStorageInterface
    {
        return $this->preferencesStorage;
    }

    protected function getListIdentifier(): string
    {
        return $this->dataSourceId ?? $this->entityShortClass;
    }

    public function getEntityValue(object $entity, string $field): mixed
    {
        return $this->getDataSource()->getItemValue($entity, $field);
    }

    public function getEntityId(object $entity): string|int
    {
        return $this->getDataSource()->getItemId($entity);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Return true only if the given column is known to the data source AND marked sortable.
     *
     * Used as a guard in sort() and getEntities() to prevent DQL errors when a caller
     * (URL parameter, UI button) requests sorting by an association or custom column.
     */
    private function isSortableColumn(string $column): bool
    {
        $columns = $this->getDataSource()->getColumns();

        if (!isset($columns[$column])) {
            return false;
        }

        return $columns[$column]->sortable;
    }
}
