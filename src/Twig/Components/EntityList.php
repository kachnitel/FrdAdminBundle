<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\ValueObject\PaginationInfo;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;

/**
 * LiveComponent for reactive entity lists with per-column search/filter, sorting, and pagination.
 *
 * Supports two modes:
 * 1. Entity mode (legacy): Pass entityClass and entityShortClass for Doctrine entities
 * 2. DataSource mode: Pass dataSourceId for any DataSourceInterface implementation
 *
 * Security: Requires ADMIN_INDEX permission for the entity/data source being displayed.
 * Permission is checked using AdminEntityVoter against the entityShortClass in PostHydrate.
 *
 * Note: #[IsGranted] cannot be used with subject='entityShortClass' because security
 * is checked before LiveProps are hydrated. PostHydrate ensures permissions are checked
 * after the component data is available, for both initial render and all LiveActions.
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
    #[LiveProp(writable: true, url: true)]
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
     * Entity class (legacy, for backward compatibility).
     * Ignored when dataSourceId is set.
     */
    #[LiveProp]
    public string $entityClass = '';

    /**
     * Entity short class (legacy, for backward compatibility).
     * Ignored when dataSourceId is set.
     */
    #[LiveProp]
    public string $entityShortClass = '';

    #[LiveProp]
    public ?string $repositoryMethod = null;

    /** @var array<int> Allowed items per page options */
    public array $allowedItemsPerPage;

    /** @var PaginatedResult|null Cached query result */
    private ?PaginatedResult $queryResult = null;

    /** @var array<string, array<string, mixed>>|null Column filter metadata (lazy-loaded) */
    private ?array $filterMetadataCache = null;

    /** @var array<int|string, string>|null Cached columns */
    private ?array $columnsCache = null;

    /** @var DataSourceInterface|null Resolved data source */
    private ?DataSourceInterface $dataSource = null;

    public function __construct(
        private EntityManagerInterface $em,
        private FilterMetadataProvider $filterMetadataProvider,
        private EntityDiscoveryService $entityDiscovery,
        private EntityListQueryService $queryService,
        public readonly EntityListPermissionService $permissionService,
        private Security $security,
        private EntityListConfig $config,
        private DataSourceRegistry $dataSourceRegistry,
    ) {
        $this->itemsPerPage = $this->config->defaultItemsPerPage;
        $this->allowedItemsPerPage = $this->config->allowedItemsPerPage;
    }

    // --- Security ---

    /**
     * Check permissions after component hydration.
     *
     * This ensures permission is checked for both initial render and all LiveActions,
     * using the entity-specific permissions from the AdminEntityVoter.
     */
    #[PostHydrate]
    public function checkPermissions(): void
    {
        // Resolve data source
        $this->resolveDataSource();

        // For Doctrine entities, check permission by short class name
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
    private function resolveDataSource(): void
    {
        if ($this->dataSource !== null) {
            return;
        }

        // Try to resolve by dataSourceId first
        if ($this->dataSourceId !== null) {
            $this->dataSource = $this->dataSourceRegistry->get($this->dataSourceId);
            if ($this->dataSource === null) {
                throw new \RuntimeException(sprintf('Data source "%s" not found.', $this->dataSourceId));
            }
            return;
        }

        // Fall back to entityShortClass (which is the identifier for Doctrine entities)
        if ($this->entityShortClass !== '') {
            $this->dataSource = $this->dataSourceRegistry->get($this->entityShortClass);
        }

        // If still not found, we're in legacy mode without data source support
        // Continue with direct Doctrine access
    }

    /**
     * Get the resolved data source, if available.
     */
    public function getDataSource(): ?DataSourceInterface
    {
        $this->resolveDataSource();
        return $this->dataSource;
    }

    /**
     * Check if using a data source (vs legacy mode).
     */
    public function isUsingDataSource(): bool
    {
        return $this->getDataSource() !== null;
    }

    // --- UI ---

    /**
     * Get filtered, sorted, and paginated entities.
     *
     * @return array<object>
     */
    public function getEntities(): array
    {
        if ($this->queryResult !== null) {
            return $this->queryResult->items;
        }

        $dataSource = $this->getDataSource();

        if ($dataSource !== null) {
            // Use data source for query
            $this->queryResult = $dataSource->query(
                search: $this->search,
                filters: $this->columnFilters,
                sortBy: $this->sortBy,
                sortDirection: $this->sortDirection,
                page: $this->page,
                itemsPerPage: $this->itemsPerPage
            );

            // Update page if clamped by query
            $this->page = $this->queryResult->currentPage;

            return $this->queryResult->items;
        }

        // Legacy mode: use query service directly
        $result = $this->queryService->getEntities(
            $this->entityClass,
            $this->repositoryMethod,
            $this->search,
            $this->columnFilters,
            $this->getFilterMetadata(),
            $this->sortBy,
            $this->sortDirection,
            $this->page,
            $this->itemsPerPage
        );

        $this->queryResult = PaginatedResult::fromQueryResult($result, $this->itemsPerPage);
        $this->page = $result['page'];

        return $result['entities'];
    }

    /**
     * Get total number of items (with filters applied).
     */
    private function getTotalItems(): int
    {
        if ($this->queryResult === null) {
            $this->getEntities();
        }

        return $this->queryResult->totalItems;
    }

    /**
     * Get pagination information (pages, start/end items).
     */
    public function getPaginationInfo(): PaginationInfo
    {
        if ($this->queryResult === null) {
            $this->getEntities();
        }

        return $this->queryResult->toPaginationInfo();
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
        $this->queryResult = null;
    }

    #[LiveAction]
    public function nextPage(): void
    {
        if ($this->page < $this->getPaginationInfo()->getTotalPages()) {
            $this->page++;
            $this->queryResult = null;
        }
    }

    #[LiveAction]
    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->queryResult = null;
        }
    }

    #[LiveAction]
    public function goToPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, min($page, $this->getPaginationInfo()->getTotalPages()));
        $this->queryResult = null;
    }

    /**
     * Listener for the child component's event.
     * Updates the specific filter key and triggers a re-render.
     */
    #[LiveListener('filter:updated')]
    public function onFilterUpdated(#[LiveArg] string $column, #[LiveArg] mixed $value): void
    {
        $this->columnFilters[$column] = $value;

        // Reset to first page when filters change
        $this->page = 1;
        $this->queryResult = null;
    }

    /**
     * Batch delete selected entities.
     */
    #[LiveAction]
    public function batchDelete(): void
    {
        $dataSource = $this->getDataSource();

        // Check if batch delete is supported
        if ($dataSource !== null && !$dataSource->supportsAction('batch_delete')) {
            throw new AccessDeniedException('Batch delete not supported for this data source.');
        }

        if (!$this->permissionService->canBatchDelete($this->entityClass, $this->entityShortClass)) {
            throw new AccessDeniedException('Batch delete not allowed for this entity.');
        }

        if (empty($this->selectedIds)) {
            return;
        }

        // For non-Doctrine data sources, batch delete is not supported
        if ($dataSource !== null && !($dataSource instanceof DoctrineDataSource)) {
            throw new AccessDeniedException('Batch delete only supported for Doctrine entities.');
        }

        $entityClass = $dataSource instanceof DoctrineDataSource
            ? $dataSource->getEntityClass()
            : $this->entityClass;

        $repository = $this->em->getRepository($entityClass);

        foreach ($this->selectedIds as $id) {
            $entity = $repository->find($id);
            if ($entity !== null) {
                $this->em->remove($entity);
            }
        }

        $this->em->flush();

        // Clear selections after deletion
        $this->selectedIds = [];
        $this->queryResult = null;
    }

    /**
     * Select all entities on current page.
     */
    #[LiveAction]
    public function selectAll(): void
    {
        $entities = $this->getEntities();
        $dataSource = $this->getDataSource();

        if ($dataSource !== null) {
            $this->selectedIds = array_values(array_unique(array_merge(
                $this->selectedIds,
                array_map(fn($entity) => $dataSource->getItemId($entity), $entities)
            )));
        } else {
            $metadata = $this->em->getClassMetadata($this->entityClass);
            $idField = $metadata->getSingleIdentifierFieldName();

            $this->selectedIds = array_values(array_unique(array_merge(
                $this->selectedIds,
                array_map(fn($entity) => $metadata->getFieldValue($entity, $idField), $entities)
            )));
        }
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
     * Lazy-loads metadata to avoid accessing entityClass before hydration.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFilterMetadata(): array
    {
        if ($this->filterMetadataCache === null) {
            $dataSource = $this->getDataSource();

            if ($dataSource !== null) {
                // Convert FilterMetadata objects to legacy array format
                $this->filterMetadataCache = [];
                foreach ($dataSource->getFilters() as $name => $filter) {
                    $this->filterMetadataCache[$name] = $filter->toArray();
                }
            } else {
                $this->filterMetadataCache = $this->filterMetadataProvider->getFilters($this->entityClass);
            }
        }
        return $this->filterMetadataCache;
    }

    /**
     * Get columns for display.
     *
     * Respects the #[Admin] attribute configuration:
     * - If columns are explicitly set, use only those columns in that order
     * - If excludeColumns is set, filter out those columns
     * - Otherwise, use all entity fields and associations
     *
     * @return array<int|string, string>
     */
    public function getColumns(): array
    {
        if ($this->columnsCache !== null) {
            return $this->columnsCache;
        }

        $dataSource = $this->getDataSource();

        if ($dataSource !== null) {
            // Get column names from data source
            $this->columnsCache = array_keys($dataSource->getColumns());
            return $this->columnsCache;
        }

        // Legacy mode
        $metadata = $this->em->getClassMetadata($this->entityClass);
        $adminAttr = $this->entityDiscovery->getAdminAttribute($this->entityClass);

        // If columns are explicitly configured, use only those (respecting order)
        if ($adminAttr?->getColumns() !== null) {
            $this->columnsCache = $adminAttr->getColumns();
            return $this->columnsCache;
        }

        // Get all available columns
        $allColumns = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

        // If excludeColumns is configured, filter them out
        if ($adminAttr?->getExcludeColumns() !== null) {
            $excludeColumns = $adminAttr->getExcludeColumns();
            $this->columnsCache = array_values(array_filter($allColumns, fn($col) => !in_array($col, $excludeColumns)));
            return $this->columnsCache;
        }

        $this->columnsCache = $allColumns;
        return $this->columnsCache;
    }

    /**
     * Check if batch actions are supported for this data source.
     */
    public function supportsBatchActions(): bool
    {
        $dataSource = $this->getDataSource();

        if ($dataSource !== null) {
            return $dataSource->supportsAction('batch_delete');
        }

        return true;
    }

    /**
     * Get the value of a field for an entity.
     */
    public function getEntityValue(object $entity, string $field): mixed
    {
        $dataSource = $this->getDataSource();

        if ($dataSource !== null) {
            return $dataSource->getItemValue($entity, $field);
        }

        // Legacy mode
        $metadata = $this->em->getClassMetadata($this->entityClass);

        if ($metadata->hasField($field)) {
            return $metadata->getFieldValue($entity, $field);
        }

        if ($metadata->hasAssociation($field)) {
            return $metadata->getFieldValue($entity, $field);
        }

        $getter = 'get' . ucfirst($field);
        if (method_exists($entity, $getter)) {
            return $entity->$getter();
        }

        return null;
    }

    /**
     * Get the ID of an entity.
     */
    public function getEntityId(object $entity): string|int
    {
        $dataSource = $this->getDataSource();

        if ($dataSource !== null) {
            return $dataSource->getItemId($entity);
        }

        // Legacy mode
        $metadata = $this->em->getClassMetadata($this->entityClass);
        $idField = $metadata->getSingleIdentifierFieldName();
        return $metadata->getFieldValue($entity, $idField);
    }
}
