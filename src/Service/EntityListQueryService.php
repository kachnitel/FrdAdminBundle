<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

/**
 * Service responsible for building database queries for entity lists.
 *
 * This service abstracts away ORM-specific query building logic from the EntityList
 */
class EntityListQueryService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Get filtered, sorted, and paginated entities.
     *
     * @param string $entityClass Full entity class name
     * @param array<string, array<string, mixed>> $filterMetadata
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
        int $itemsPerPage
    ): array {
        $qb = $this->buildQuery(
            $entityClass,
            $repositoryMethod,
            $search,
            $columnFilters,
            $filterMetadata,
            $sortBy,
            $sortDirection
        );

        // Ensure valid page number
        $page = max(1, $page);

        // Apply pagination
        $qb->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        // Use Doctrine Paginator for efficient counting
        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = $paginator->count();

        // Clamp page to valid range
        $totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
            // Recalculate with corrected page
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
     */
    public function buildQuery(
        string $entityClass,
        ?string $repositoryMethod,
        string $search,
        array $columnFilters,
        array $filterMetadata,
        string $sortBy,
        string $sortDirection
    ): QueryBuilder {
        /** @var \Doctrine\ORM\EntityRepository<object> $repository */
        $repository = $this->em->getRepository($entityClass);

        // Use custom repository method if specified
        if ($repositoryMethod && method_exists($repository, $repositoryMethod)) {
            $qb = $repository->{$repositoryMethod}();
        } else {
            $qb = $repository->createQueryBuilder('e');
        }

        // Apply global search (searches all text fields)
        if ($search) {
            $this->applyGlobalSearch($qb, $entityClass, $search);
        }

        // Apply column-specific filters
        foreach ($columnFilters as $column => $value) {
            if (isset($filterMetadata[$column]) && $value !== null && $value !== '') {
                $this->applyColumnFilter($qb, $column, $value, $filterMetadata[$column]);
            }
        }

        // Apply sorting
        $qb->orderBy('e.' . $sortBy, $sortDirection);

        return $qb;
    }

    /**
     * Apply global search across all text fields.
     */
    private function applyGlobalSearch(QueryBuilder $qb, string $entityClass, string $search): void
    {
        $metadata = $this->em->getClassMetadata($entityClass);
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

            case ColumnFilter::TYPE_RELATION:
                $this->applyRelationFilter($qb, $column, $value, $metadata);
                break;
        }
    }

    /**
     * Applies filtering logic for text columns (handles LIKE and exact/custom operators).
     *
     * @param QueryBuilder $qb
     * @param string $column
     * @param mixed $value
     * @param array<string, mixed> $metadata
     */
    private function applyTextFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [$operator, $paramName] = $this->getFilterContext($column, $metadata);

        if ($operator === 'LIKE') {
            $qb->andWhere($qb->expr()->like('e.' . $column, ':' . $paramName))
                ->setParameter($paramName, '%' . $value . '%');
        } else {
            // Handles '=', '!=', or any other custom operator defined for text
            $qb->andWhere('e.' . $column . ' ' . $operator . ' :' . $paramName)
                ->setParameter($paramName, $value);
        }
    }

    /**
     * Applies simple equality/comparison filtering for Number, Boolean, and Enum types.
     *
     * @param QueryBuilder $qb
     * @param string $column
     * @param mixed $value
     * @param array<string, mixed> $metadata
     */
    private function applySimpleFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [$operator, $paramName] = $this->getFilterContext($column, $metadata);

        // This structure is common for all simple comparisons (e.g., price > 10, isActive = true)
        $qb->andWhere('e.' . $column . ' ' . $operator . ' :' . $paramName)
            ->setParameter($paramName, $value);
    }

    /**
     * Applies filtering logic for date/time columns, ensuring the value is a DateTime object.
     *
     * @param QueryBuilder $qb
     * @param string $column
     * @param mixed $value
     * @param array<string, mixed> $metadata
     */
    private function applyDateFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [$operator, $paramName] = $this->getFilterContext($column, $metadata);

        // Parse date value to a DateTime object if it isn't one already
        $dateValue = $value instanceof \DateTimeInterface ? $value : new \DateTime((string) $value);

        $qb->andWhere('e.' . $column . ' ' . $operator . ' :' . $paramName)
            ->setParameter($paramName, $dateValue);
    }

    /**
     * Applies filtering logic for related entities, searching across configured fields using LIKE.
     *
     * @param QueryBuilder $qb
     * @param string $column
     * @param mixed $value
     * @param array<string, mixed> $metadata
     */
    private function applyRelationFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [, $paramName] = $this->getFilterContext($column, $metadata);

        // Default to searching the 'id' field if not specified
        $searchFields = $metadata['searchFields'] ?? ['id'];
        $alias = 'rel_' . $column;

        // Perform a join to access the related entity fields
        $qb->leftJoin('e.' . $column, $alias);

        // Create an OR expression to search across multiple fields
        $orX = $qb->expr()->orX();
        foreach ($searchFields as $field) {
            $orX->add($qb->expr()->like($alias . '.' . $field, ':' . $paramName));
        }

        // Apply the overall OR condition to the query with a LIKE parameter
        $qb->andWhere($orX)
            ->setParameter($paramName, '%' . $value . '%');
    }

    /**
     * Helper to extract common filter context (parameter name and operator).
     *
     * @param string $column The entity column or association name.
     * @param array<string, mixed> $metadata Configuration metadata.
     * @return array{0: string, 1: string} [operator, paramName]
     */
    private function getFilterContext(string $column, array $metadata): array
    {
        $operator = $metadata['operator'] ?? '=';
        $paramName = 'filter_' . str_replace('.', '_', $column);

        return [$operator, $paramName];
    }
}
