<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\DataSourceContracts\ColumnGroup;
use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\DataSourceContracts\PaginatedResult;
use Kachnitel\DataSourceContracts\SearchAwareDataSourceInterface;
use Kachnitel\AdminBundle\Service\EntityListArchiveService;
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
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
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
     * @var array<string, mixed>
     */
    #[LiveProp(writable: true, url: true, onUpdated: 'onColumnFiltersUpdated')]
    public array $columnFilters = [];

    #[LiveProp(writable: true, url: true)]
    public int $page = 1;

    #[LiveProp(writable: true, url: true)]
    public int $itemsPerPage;

    /**
     * @var array<int|string>
     */
    #[LiveProp(writable: true)]
    public array $selectedIds = [];

    /**
     * @var array<string>
     */
    #[LiveProp(writable: true)]
    public array $hiddenColumns = [];

    #[LiveProp]
    public ?string $dataSourceId = null;

    #[LiveProp]
    public string $entityClass = '';

    #[LiveProp]
    public string $entityShortClass = '';

    #[LiveProp]
    public ?string $repositoryMethod = null;

    #[LiveProp(writable: true)]
    public ?int $editingRowId = null;

    /**
     * Whether to include archived (soft-deleted) rows in the list.
     * Default: false (archived rows hidden). Toggle via toggleArchive().
     */
    #[LiveProp(writable: true, url: true)]
    public bool $showArchived = false;

    /** @var array<int> */
    public array $allowedItemsPerPage;

    /**
     * @var array{
     *     queryResult?: PaginatedResult,
     *     filterMetadata?: array<string, array<string, mixed>>,
     *     columns?: array<int|string, string>,
     *     columnSlots?: list<string|ColumnGroup>,
     *     dataSource?: DataSourceInterface,
     *     archiveConfig?: ArchiveConfig|null,
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
        private EntityListArchiveService $archiveService,
    ) {
        $this->itemsPerPage = $this->config->defaultItemsPerPage;
        $this->allowedItemsPerPage = $this->config->allowedItemsPerPage;
    }

    // ── Security ───────────────────────────────────────────────────────────────

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

    // ── Archive ────────────────────────────────────────────────────────────────

    public function getArchiveConfig(): ?ArchiveConfig
    {
        if (!array_key_exists('archiveConfig', $this->cache)) {
            $this->cache['archiveConfig'] = $this->entityClass !== ''
                ? $this->archiveService->resolveConfig($this->entityClass)
                : null;
        }

        return $this->cache['archiveConfig'];
    }

    public function canToggleArchive(): bool
    {
        return $this->archiveService->canToggle($this->getArchiveConfig());
    }

    public function isArchivedRow(object $entity): bool
    {
        $config = $this->getArchiveConfig();
        if ($config === null) {
            return false;
        }

        return $this->archiveService->isArchivedRow($entity, $config);
    }

    // ── Queries ────────────────────────────────────────────────────────────────

    /**
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

        $filters = $this->columnFilters;
        if ($this->isDoctrineEntity()) {
            $config = $this->getArchiveConfig();
            if ($config !== null) {
                $condition = $this->archiveService->buildDqlCondition($config, $this->showArchived);
                if ($condition !== null) {
                    $filters['__archiveDqlCondition'] = $condition;
                }
            }
        }

        $this->cache['queryResult'] = $this->getDataSource()->query(
            search: $this->search,
            filters: $filters,
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

    /**
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

    // ── LiveActions: archive ───────────────────────────────────────────────────

    #[LiveAction]
    public function toggleArchive(): void
    {
        if (!$this->canToggleArchive()) {
            throw new AccessDeniedException('Access denied to toggle archive filter.');
        }

        $this->showArchived = !$this->showArchived;
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

    public function canEditRow(): bool
    {
        return $this->permissionService->canInlineEdit(
            $this->entityClass,
            $this->entityShortClass,
        );
    }

    #[LiveAction]
    public function editRow(#[LiveArg] int $id): void
    {
        if (!$this->canEditRow()) {
            throw new AccessDeniedException('Access denied for inline editing.');
        }

        $this->editingRowId = $id;
    }

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

    private function isSortableColumn(string $column): bool
    {
        $columns = $this->getDataSource()->getColumns();

        if (!isset($columns[$column])) {
            return false;
        }

        return $columns[$column]->sortable;
    }
}
