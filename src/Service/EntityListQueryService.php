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
     * @param array<string, mixed> $columnFilters
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
            /** @var QueryBuilder $qb */
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

            case ColumnFilter::TYPE_DATERANGE:
                $this->applyDateRangeFilter($qb, $column, $value, $metadata);
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

        // Handle multi-select enum values (stored as JSON array)
        if ($operator === 'IN') {
            $values = $this->parseMultiSelectValue($value);
            if (empty($values)) {
                return; // No filter if no values selected
            }

            $qb->andWhere($qb->expr()->in('e.' . $column, ':' . $paramName))
                ->setParameter($paramName, $values);
            return;
        }

        // This structure is common for all simple comparisons (e.g., price > 10, isActive = true)
        $qb->andWhere('e.' . $column . ' ' . $operator . ' :' . $paramName)
            ->setParameter($paramName, $value);
    }

    /**
     * Parse multi-select value from JSON array string or array.
     *
     * @param mixed $value
     * @return array<string|int>
     */
    private function parseMultiSelectValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            // Try JSON decode first
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            // Fall back to single value
            return [$value];
        }

        return [];
    }

    /**
     * Applies filtering logic for date/time columns.
     * For single date selection, matches the exact day (from 00:00:00 to 23:59:59).
     *
     * @param QueryBuilder $qb
     * @param string $column
     * @param mixed $value
     * @param array<string, mixed> $metadata
     */
    private function applyDateFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [, $paramName] = $this->getFilterContext($column, $metadata);

        // Parse date value to a DateTime object if it isn't one already
        $dateValue = $value instanceof \DateTimeInterface ? $value : new \DateTime((string) $value);

        // Create start and end of day for exact day matching
        $startOfDay = \DateTime::createFromInterface($dateValue)->setTime(0, 0, 0);
        $endOfDay = \DateTime::createFromInterface($dateValue)->setTime(23, 59, 59);

        $qb->andWhere('e.' . $column . ' BETWEEN :' . $paramName . '_start AND :' . $paramName . '_end')
            ->setParameter($paramName . '_start', $startOfDay)
            ->setParameter($paramName . '_end', $endOfDay);
    }

    /**
     * Applies filtering logic for date range columns.
     * Expects value as JSON with 'from' and/or 'to' keys.
     *
     * @param QueryBuilder $qb
     * @param string $column
     * @param mixed $value
     * @param array<string, mixed> $metadata
     */
    private function applyDateRangeFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [, $paramName] = $this->getFilterContext($column, $metadata);

        // Parse the value - expected format: {"from": "2024-01-01", "to": "2024-01-31"}
        $range = is_string($value) ? json_decode($value, true) : $value;

        if (!is_array($range)) {
            return;
        }

        $from = $range['from'] ?? null;
        $to = $range['to'] ?? null;

        if ($from !== null && $from !== '') {
            $fromDate = $from instanceof \DateTimeInterface ? $from : new \DateTime((string) $from);
            $startOfDay = \DateTime::createFromInterface($fromDate)->setTime(0, 0, 0);
            $qb->andWhere('e.' . $column . ' >= :' . $paramName . '_from')
                ->setParameter($paramName . '_from', $startOfDay);
        }

        if ($to !== null && $to !== '') {
            $toDate = $to instanceof \DateTimeInterface ? $to : new \DateTime((string) $to);
            $endOfDay = \DateTime::createFromInterface($toDate)->setTime(23, 59, 59);
            $qb->andWhere('e.' . $column . ' <= :' . $paramName . '_to')
                ->setParameter($paramName . '_to', $endOfDay);
        }
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
        
        // Filter out any empty searchFields array
        if (empty($searchFields)) {
            $searchFields = ['id'];
        }
        
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
