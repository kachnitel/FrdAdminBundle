<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Service\Traits\AssociationFilterTrait;
use Kachnitel\AdminBundle\Service\Traits\DateFilterTrait;
use Kachnitel\AdminBundle\Service\Traits\ScalarFilterTrait;

/**
 * Service responsible for building database queries for entity lists.
 */
class EntityListQueryService
{
    use ScalarFilterTrait;
    use DateFilterTrait;
    use AssociationFilterTrait;

    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Get filtered, sorted, and paginated entities.
     *
     * @param string $entityClass Full entity class name
     * @param array<string, mixed> $columnFilters
     * @param array<string, array<string, mixed>> $filterMetadata
     * @param string|null $archiveDqlCondition Optional pre-built DQL WHERE fragment from ArchiveService
     * @return array{entities: array<object>, total: int, page: int}
     */
    public function getEntities(
        string $entityClass,
        ?string $repositoryMethod,
        string $search,
        array $columnFilters,
        array $filterMetadata,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage,
        ?string $archiveDqlCondition = null,
    ): array {
        $qb = $this->buildQuery(
            $entityClass,
            $repositoryMethod,
            $search,
            $columnFilters,
            $filterMetadata,
            $sortBy,
            $sortDirection,
            $archiveDqlCondition,
        );

        $page = max(1, $page);

        $qb->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = $paginator->count();

        $totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
            $qb->setFirstResult(($page - 1) * $itemsPerPage);
            $paginator = new Paginator($qb, fetchJoinCollection: true);
        }

        return [
            'entities' => iterator_to_array($paginator->getIterator()),
            'total' => $total,
            'page' => $page,
        ];
    }

    /**
     * Build the base query with filters and sorting.
     *
     * @param array<string, mixed> $columnFilters
     * @param array<string, array<string, mixed>> $filterMetadata
     * @param string|null $archiveDqlCondition Optional pre-built DQL WHERE fragment from ArchiveService
     */
    public function buildQuery(
        string $entityClass,
        ?string $repositoryMethod,
        string $search,
        array $columnFilters,
        array $filterMetadata,
        string $sortBy,
        string $sortDirection,
        ?string $archiveDqlCondition = null,
    ): QueryBuilder {
        /** @var \Doctrine\ORM\EntityRepository<object> $repository */
        $repository = $this->em->getRepository($entityClass);

        if ($repositoryMethod && method_exists($repository, $repositoryMethod)) {
            /** @var QueryBuilder $qb */
            $qb = $repository->{$repositoryMethod}();
        } else {
            $qb = $repository->createQueryBuilder('e');
        }

        if ($search) {
            $this->applyGlobalSearch($qb, $entityClass, $search);
        }

        foreach ($columnFilters as $column => $value) {
            if (isset($filterMetadata[$column]) && $value !== null && $value !== '') {
                $this->applyColumnFilter($qb, $column, $value, $filterMetadata[$column]);
            }
        }

        // Apply archive condition (e.g. 'e.archived = false' or 'e.deletedAt IS NULL')
        if ($archiveDqlCondition !== null) {
            $qb->andWhere($archiveDqlCondition);
        }

        $qb->orderBy('e.' . $sortBy, $sortDirection);

        return $qb;
    }

    /**
     * Return the names of entity fields included in global search.
     *
     * @return array<string>
     */
    public function getSearchableFieldNames(string $entityClass): array
    {
        $metadata = $this->em->getClassMetadata($entityClass);
        $fields = [];

        foreach ($metadata->getFieldNames() as $field) {
            $type = $metadata->getTypeOfField($field);
            if (in_array($type, ['string', 'text'], true)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Apply global search across all text fields.
     */
    private function applyGlobalSearch(QueryBuilder $qb, string $entityClass, string $search): void
    {
        $metadata = $this->em->getClassMetadata($entityClass);
        $searchableFields = [];

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
            ->setParameter('globalSearch', '%' . $search . '%');
    }

    /**
     * Apply a column-specific filter.
     *
     * @param array<string, mixed> $metadata
     */
    private function applyColumnFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        $type = $metadata['type'];

        switch ($type) {
            case ColumnFilter::TYPE_TEXT:
                $this->applyTextFilter($qb, $column, $value, $metadata);
                break;

            case ColumnFilter::TYPE_NUMBER:
            case ColumnFilter::TYPE_BOOLEAN:
            case ColumnFilter::TYPE_ENUM:
                $this->applySimpleFilter($qb, $column, $value, $metadata);
                break;

            case ColumnFilter::TYPE_DATE:
                $this->applyDateFilter($qb, $column, $value, $metadata);
                break;

            case ColumnFilter::TYPE_DATERANGE:
                $this->applyDateRangeFilter($qb, $column, $value, $metadata);
                break;

            case ColumnFilter::TYPE_RELATION:
                $this->applyRelationFilter($qb, $column, $value, $metadata);
                break;

            case ColumnFilter::TYPE_COLLECTION:
                $this->applyCollectionFilter($qb, $column, $value, $metadata);
                break;
        }
    }

    /**
     * @param string $column
     * @param array<string, mixed> $metadata
     * @return array{0: string, 1: string}
     */
    protected function getFilterContext(string $column, array $metadata): array
    {
        $operator = $metadata['operator'] ?? '=';
        $paramName = 'filter_' . str_replace('.', '_', $column);

        return [$operator, $paramName];
    }
}
