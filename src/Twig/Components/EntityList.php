<?php

namespace Frd\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Frd\AdminBundle\Service\Filter\FilterInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * LiveComponent for reactive entity lists with search, filter, and sorting.
 */
#[AsLiveComponent('AdminEntityList', template: '@FrdAdmin/components/EntityList.html.twig')]
class EntityList
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public string $sortBy = 'id';

    #[LiveProp(writable: true)]
    public string $sortDirection = 'DESC';

    #[LiveProp(writable: true)]
    public array $filters = [];

    #[LiveProp]
    public string $entityClass;

    #[LiveProp]
    public ?string $repositoryMethod = null;

    /** @var FilterInterface[] */
    private array $availableFilters = [];

    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Set available filters for this list.
     */
    public function setFilters(array $filters): void
    {
        $this->availableFilters = $filters;
    }

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

        // Apply search
        if ($this->search) {
            $this->applySearch($qb);
        }

        // Apply filters
        foreach ($this->filters as $filterName => $filterValue) {
            if (isset($this->availableFilters[$filterName]) && $filterValue !== null && $filterValue !== '') {
                $this->availableFilters[$filterName]->apply($qb, $filterValue);
            }
        }

        // Apply sorting
        $qb->orderBy('e.' . $this->sortBy, $this->sortDirection);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get available filters.
     */
    public function getAvailableFilters(): array
    {
        return $this->availableFilters;
    }

    /**
     * Apply search across searchable fields.
     */
    private function applySearch(QueryBuilder $qb): void
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
            $orX->add($qb->expr()->like('e.' . $field, ':search'));
        }

        $qb->andWhere($orX)
            ->setParameter('search', '%' . $this->search . '%');
    }
}
