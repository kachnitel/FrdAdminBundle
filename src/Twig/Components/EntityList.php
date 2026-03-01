<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
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
use Symfony\Contracts\Service\Attribute\Required;

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
        private EntityListColumnService $columnService,
    ) {
        $this->itemsPerPage = $this->config->defaultItemsPerPage;
        $this->allowedItemsPerPage = $this->config->allowedItemsPerPage;
    }

    // ── Injected via #[Required] — avoids adding an 8th constructor arg ──────

    private ?AttributeHelper $attributeHelper = null;

    #[Required]
    public function setAttributeHelper(AttributeHelper $attributeHelper): void
    {
        $this->attributeHelper = $attributeHelper;
    }

    // ── Security ───────────────────────────────────────────────────────────────

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
     * Check whether the current user may open rows of this entity type for inline editing.
     *
     * Two conditions must both pass:
     *   1. The entity has opted into inline editing via #[Admin(enableInlineEdit: true)].
     *   2. The current user is granted ADMIN_EDIT for the entity type.
     *
     * Condition 1 is a UX feature flag that prevents the ✏️ button from appearing
     * for entities that have not opted in. Condition 2 is the security gate.
     *
     * IMPORTANT: Always pass $this->entityShortClass (string) as voter subject.
     */
    public function canEditRow(): bool
    {
        if (!$this->isDoctrineEntity()) {
            return false;
        }

        // Check entity-level enableInlineEdit flag first (cheap, no voter call)
        if ($this->attributeHelper !== null) {
            /** @var Admin|null $admin */
            $admin = $this->attributeHelper->getAttribute($this->entityClass, Admin::class);
            if ($admin === null || !$admin->isEnableInlineEdit()) {
                return false;
            }
        }

        return $this->security->isGranted(AdminEntityVoter::ADMIN_EDIT, $this->entityShortClass);
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
     * Exit row edit mode. Any field component currently open will silently
     * discard its unsaved input (LiveComponents are isolated per-request).
     */
    // #[LiveAction]
    // public function exitRowEdit(): void
    // {
    //     $this->editingRowId = null;
    // }

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
            fn(string $col) => !in_array($col, $this->hiddenColumns, true)
        ));
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
}
