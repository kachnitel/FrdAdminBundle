<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSourceFactory;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
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
     *     dataSourceResolved?: bool
     * }
     */
    private array $cache = [];

    public function __construct(
        public readonly EntityListPermissionService $permissionService,
        private Security $security,
        private EntityListConfig $config,
        private DataSourceRegistry $dataSourceRegistry,
        private DoctrineDataSourceFactory $doctrineFactory,
        private EntityListBatchService $batchService,
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
        if (isset($this->cache['dataSource'])) {
            return $this->cache['dataSource'];
        }

        // Try to resolve by dataSourceId first
        if ($this->dataSourceId !== null) {
            $dataSource = $this->dataSourceRegistry->get($this->dataSourceId);
            if ($dataSource === null) {
                throw new \RuntimeException(sprintf('Data source "%s" not found.', $this->dataSourceId));
            }
            $this->cache['dataSource'] = $dataSource;
            return $dataSource;
        }

        // Try to resolve by entityShortClass from registry
        if ($this->entityShortClass !== '') {
            $dataSource = $this->dataSourceRegistry->get($this->entityShortClass);
            if ($dataSource !== null) {
                $this->cache['dataSource'] = $dataSource;
                return $dataSource;
            }
        }

        // Create DoctrineDataSource on-demand for the entity class
        if ($this->entityClass !== '') {
            $this->cache['dataSource'] = $this->doctrineFactory->createForClass($this->entityClass);
            return $this->cache['dataSource'];
        }

        throw new \RuntimeException('No data source or entity class configured.');
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
     * Get filter metadata for template rendering.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFilterMetadata(): array
    {
        if (!isset($this->cache['filterMetadata'])) {
            $this->cache['filterMetadata'] = [];
            foreach ($this->getDataSource()->getFilters() as $name => $filter) {
                $this->cache['filterMetadata'][$name] = $filter->toArray();
            }
        }
        return $this->cache['filterMetadata'];
    }

    /**
     * Get columns for display.
     *
     * @return array<int|string, string>
     */
    public function getColumns(): array
    {
        if (!isset($this->cache['columns'])) {
            $this->cache['columns'] = array_keys($this->getDataSource()->getColumns());
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
