<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Config\EntityListConfig;
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
 * Security: Requires ADMIN_INDEX permission for the entity being displayed.
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
     * @var array<int>
     */
    #[LiveProp(writable: true)]
    public array $selectedIds = [];

    #[LiveProp]
    public string $entityClass;

    #[LiveProp]
    public string $entityShortClass;

    #[LiveProp]
    public ?string $repositoryMethod = null;

    /** @var array<int> Allowed items per page options */
    public array $allowedItemsPerPage;

    /** @var int|null Cached total count of items */
    private ?int $totalItems = null;

    /** @var array<string, array<string, mixed>>|null Column filter metadata (lazy-loaded) */
    private ?array $filterMetadataCache = null;

    /** @var array<int|string, string>|null Cached columns */
    private ?array $columnsCache = null;

    public function __construct(
        private EntityManagerInterface $em,
        private FilterMetadataProvider $filterMetadataProvider,
        private EntityDiscoveryService $entityDiscovery,
        private EntityListQueryService $queryService,
        public readonly EntityListPermissionService $permissionService,
        private Security $security,
        private EntityListConfig $config
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
        // Check if user has permission to view this entity's index
        if (!$this->security->isGranted(AdminEntityVoter::ADMIN_INDEX, $this->entityShortClass)) {
            throw new AccessDeniedException(sprintf(
                'Access denied to view %s entities.',
                $this->entityShortClass
            ));
        }
    }

    // --- UI ---

    /**
     * Get filtered, sorted, and paginated entities.
     *
     * @return array<object>
     */
    public function getEntities(): array
    {
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

        // Update state from query service
        $this->totalItems = $result['total'];
        $this->page = $result['page'];

        return $result['entities'];
    }

    /**
     * Get total number of items (with filters applied).
     */
    private function getTotalItems(): int
    {
        if ($this->totalItems === null) {
            // Trigger getEntities to populate totalItems
            $this->getEntities();
        }

        return $this->totalItems ?? 0;
    }

    /**
     * Get pagination information (pages, start/end items).
     */
    public function getPaginationInfo(): PaginationInfo
    {
        return new PaginationInfo(
            $this->getTotalItems(),
            $this->page,
            $this->itemsPerPage
        );
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
        $this->totalItems = null;
    }

    #[LiveAction]
    public function nextPage(): void
    {
        if ($this->page < $this->getPaginationInfo()->getTotalPages()) {
            $this->page++;
        }
    }

    #[LiveAction]
    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    #[LiveAction]
    public function goToPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, min($page, $this->getPaginationInfo()->getTotalPages()));
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
        $this->totalItems = null;
    }

    /**
     * Batch delete selected entities.
     */
    #[LiveAction]
    public function batchDelete(): void
    {
        if (!$this->permissionService->canBatchDelete($this->entityClass, $this->entityShortClass)) {
            throw new AccessDeniedException('Batch delete not allowed for this entity.');
        }

        if (empty($this->selectedIds)) {
            return;
        }

        $repository = $this->em->getRepository($this->entityClass);

        foreach ($this->selectedIds as $id) {
            $entity = $repository->find($id);
            if ($entity !== null) {
                $this->em->remove($entity);
            }
        }

        $this->em->flush();

        // Clear selections after deletion
        $this->selectedIds = [];
        $this->totalItems = null;
    }

    /**
     * Select all entities on current page.
     */
    #[LiveAction]
    public function selectAll(): void
    {
        $entities = $this->getEntities();
        $metadata = $this->em->getClassMetadata($this->entityClass);
        $idField = $metadata->getSingleIdentifierFieldName();

        $this->selectedIds = array_values(array_unique(array_merge(
            $this->selectedIds,
            array_map(fn($entity) => $metadata->getFieldValue($entity, $idField), $entities)
        )));
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
            $this->filterMetadataCache = $this->filterMetadataProvider->getFilters($this->entityClass);
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
}
