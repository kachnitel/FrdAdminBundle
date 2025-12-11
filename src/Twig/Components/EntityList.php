<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormRegistryInterface;
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

    #[LiveProp]
    public string $entityClass;

    #[LiveProp]
    public string $entityShortClass;

    #[LiveProp]
    public ?string $repositoryMethod = null;

    /** @var array<string, array<string, mixed>>|null Column filter metadata (lazy-loaded) */
    private ?array $filterMetadata = null;

    /** @var int|null Cached total count of items */
    private ?int $totalItems = null;

    /**
     * @param array<int> $allowedItemsPerPage
     */
    public function __construct(
        private EntityManagerInterface $em,
        private FilterMetadataProvider $filterMetadataProvider,
        private EntityDiscoveryService $entityDiscovery,
        private EntityListQueryService $queryService,
        private Security $security,
        private FormRegistryInterface $formRegistry,
        private string $formNamespace,
        private string $formSuffix,
        private int $defaultItemsPerPage = 20,
        private array $allowedItemsPerPage = [10, 20, 50, 100]
    ) {
        $this->itemsPerPage = $this->defaultItemsPerPage;
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

    /**
     * Whether to show "edit" button
     * NOTE: can accept a parameter to decide by column in future release
     * Test:
     * - Does user have permission
     * - Does route exist
     * - Is route accessible (redundant? verify it's working)
     * - Does form exist (skip for show)
     */
    public function canEdit(): bool
    {
        return $this->hasForm()
            && $this->security->isGranted(AdminEntityVoter::ADMIN_EDIT, $this->entityShortClass);
    }

    /**
     * Whether to show "new" button
     * Test:
     * - Does user have permission
     * - Does form exist
     */
    public function canNew(): bool
    {
        return $this->hasForm()
            && $this->security->isGranted(AdminEntityVoter::ADMIN_NEW, $this->entityShortClass);
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
    public function getTotalItems(): int
    {
        if ($this->totalItems === null) {
            // Trigger getEntities to populate totalItems
            $this->getEntities();
        }

        return $this->totalItems ?? 0;
    }

    /**
     * Get total number of pages.
     */
    public function getTotalPages(): int
    {
        $total = $this->getTotalItems();
        if ($total === 0) {
            return 0;
        }

        return (int) ceil($total / $this->itemsPerPage);
    }

    /**
     * Get the starting item number for current page.
     */
    public function getStartItem(): int
    {
        $total = $this->getTotalItems();
        if ($total === 0) {
            return 0;
        }

        return (($this->page - 1) * $this->itemsPerPage) + 1;
    }

    /**
     * Get the ending item number for current page.
     */
    public function getEndItem(): int
    {
        $total = $this->getTotalItems();
        if ($total === 0) {
            return 0;
        }

        return min($this->page * $this->itemsPerPage, $total);
    }

    /**
     * Get allowed items per page options.
     *
     * @return array<int>
     */
    public function getAllowedItemsPerPage(): array
    {
        return $this->allowedItemsPerPage;
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
        if ($this->page < $this->getTotalPages()) {
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
        $this->page = max(1, min($page, $this->getTotalPages()));
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
     * Get filter metadata for template rendering.
     * Lazy-loads metadata to avoid accessing entityClass before hydration.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFilterMetadata(): array
    {
        if ($this->filterMetadata === null) {
            $this->filterMetadata = $this->filterMetadataProvider->getFilters($this->entityClass);
        }
        return $this->filterMetadata;
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
        $metadata = $this->em->getClassMetadata($this->entityClass);
        $adminAttr = $this->entityDiscovery->getAdminAttribute($this->entityClass);

        // If columns are explicitly configured, use only those (respecting order)
        if ($adminAttr?->getColumns() !== null) {
            return $adminAttr->getColumns();
        }

        // Get all available columns
        $allColumns = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

        // If excludeColumns is configured, filter them out
        if ($adminAttr?->getExcludeColumns() !== null) {
            $excludeColumns = $adminAttr->getExcludeColumns();
            return array_values(array_filter($allColumns, fn($col) => !in_array($col, $excludeColumns)));
        }

        return $allColumns;
    }

    private function hasForm(): bool
    {
        $type = $this->entityDiscovery->getAdminAttribute($this->entityClass)->getFormType()
            ?: $this->formNamespace . $this->entityShortClass . $this->formSuffix;
        return $this->formRegistry->hasType($type);
    }
}
