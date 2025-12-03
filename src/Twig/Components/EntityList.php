<?php

namespace Frd\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Frd\AdminBundle\Attribute\ColumnFilter;
use Frd\AdminBundle\Service\FilterMetadataProvider;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveArg;

/**
 * LiveComponent for reactive entity lists with per-column search/filter and sorting.
 */
#[AsLiveComponent('FRD:Admin:EntityList', template: '@FrdAdmin/components/EntityList.html.twig')]
class EntityList
{
    public const SORT_ASC = 'ASC';
    public const SORT_DESC = 'DESC';

    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public string $sortBy = 'id';

    #[LiveProp(writable: true)]
    public string $sortDirection = self::SORT_DESC;

    /**
     * Column-specific filter values.
     * Format: ['columnName' => 'filterValue', ...]
     */
    #[LiveProp(writable: true)]
    public array $columnFilters = [];

    #[LiveProp]
    public string $entityClass;

    #[LiveProp]
    public string $entityShortClass;

    #[LiveProp]
    public ?string $repositoryMethod = null;

    /** @var array<string, array>|null Column filter metadata (lazy-loaded) */
    private ?array $filterMetadata = null;

    public function __construct(
        private EntityManagerInterface $em,
        private FilterMetadataProvider $filterMetadataProvider
    ) {}

    /**
     * Get filtered and sorted entities.
     */
    public function getEntities(): array
    {
        $repository = $this->em->getRepository($this->entityClass);

        // Use custom repository method if specified
        if ($this->repositoryMethod && method_exists($repository, $this->repositoryMethod)) {
            $qb = $repository->{$this->repositoryMethod}();
        } else {
            $qb = $repository->createQueryBuilder('e');
        }

        // Apply global search (searches all text fields)
        if ($this->search) {
            $this->applyGlobalSearch($qb);
        }

        // Apply column-specific filters
        $filterMetadata = $this->getFilterMetadata();
        foreach ($this->columnFilters as $column => $value) {
            if (isset($filterMetadata[$column]) && $value !== null && $value !== '') {
                $this->applyColumnFilter($qb, $column, $value, $filterMetadata[$column]);
            }
        }

        // Apply sorting
        $qb->orderBy('e.' . $this->sortBy, $this->sortDirection);

        return $qb->getQuery()->getResult();
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
    }

    /**
     * Listener for the child component's event.
     * Updates the specific filter key and triggers a re-render.
     */
    #[LiveListener('filter:updated')]
    public function onFilterUpdated(#[LiveArg] string $column, #[LiveArg] mixed $value): void
    {
        $this->columnFilters[$column] = $value;
    }

    /**
     * Get filter metadata for template rendering.
     * Lazy-loads metadata to avoid accessing entityClass before hydration.
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
     */
    public function getColumns(): array
    {
        $metadata = $this->em->getClassMetadata($this->entityClass);
        return array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());
    }

    /**
     * Apply global search across all text fields.
     */
    private function applyGlobalSearch(QueryBuilder $qb): void
    {
        $metadata = $this->em->getClassMetadata($this->entityClass);
        $searchableFields = [];

        // Search in string fields
        foreach ($metadata->getFieldNames() as $field) {
            $type = $metadata->getTypeOfField($field);
            if (in_array($type, ['string', 'text'])) {
                $searchableFields[] = $field;
            }
        }

        if (empty($searchableFields)) {
            return;
        }

        $orX = $qb->expr()->orX();
        foreach ($searchableFields as $field) {
            $orX->add($qb->expr()->like('e.' . $field, ':globalSearch'));
        }

        $qb->andWhere($orX)
            ->setParameter('globalSearch', '%' . $this->search . '%');
    }

    /**
     * Apply a column-specific filter.
     */
    private function applyColumnFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        $type = $metadata['type'];
        $operator = $metadata['operator'] ?? '=';
        $paramName = 'filter_' . str_replace('.', '_', $column);

        switch ($type) {
            case ColumnFilter::TYPE_TEXT:
                if ($operator === 'LIKE') {
                    $qb->andWhere($qb->expr()->like('e.' . $column, ':' . $paramName))
                        ->setParameter($paramName, '%' . $value . '%');
                } else {
                    $qb->andWhere('e.' . $column . ' ' . $operator . ' :' . $paramName)
                        ->setParameter($paramName, $value);
                }
                break;

            case ColumnFilter::TYPE_NUMBER:
            case ColumnFilter::TYPE_BOOLEAN:
            case ColumnFilter::TYPE_ENUM:
                $qb->andWhere('e.' . $column . ' ' . $operator . ' :' . $paramName)
                    ->setParameter($paramName, $value);
                break;

            case ColumnFilter::TYPE_DATE:
                // Parse date value
                $dateValue = $value instanceof \DateTimeInterface ? $value : new \DateTime($value);
                $qb->andWhere('e.' . $column . ' ' . $operator . ' :' . $paramName)
                    ->setParameter($paramName, $dateValue);
                break;

            case ColumnFilter::TYPE_RELATION:
                // Search in related entity's configured fields
                $searchFields = $metadata['searchFields'] ?? ['id'];
                $alias = 'rel_' . $column;

                $qb->leftJoin('e.' . $column, $alias);

                $orX = $qb->expr()->orX();
                foreach ($searchFields as $field) {
                    $orX->add($qb->expr()->like($alias . '.' . $field, ':' . $paramName));
                }

                $qb->andWhere($orX)
                    ->setParameter($paramName, '%' . $value . '%');
                break;
        }
    }
}
