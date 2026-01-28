<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service\Traits;

use Doctrine\ORM\QueryBuilder;

/**
 * Handles association filter types: relation and collection.
 */
trait AssociationFilterTrait
{
    /**
     * Get filter context (operator and parameter name).
     *
     * @param array<string, mixed> $metadata
     * @return array{0: string, 1: string}
     */
    abstract protected function getFilterContext(string $column, array $metadata): array;

    /**
     * Applies filtering logic for related entities, searching across configured fields using LIKE.
     *
     * @param array<string, mixed> $metadata
     */
    private function applyRelationFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [, $paramName] = $this->getFilterContext($column, $metadata);

        $searchFields = $metadata['searchFields'] ?? ['id'];

        if (empty($searchFields)) {
            $searchFields = ['id'];
        }

        $alias = 'rel_' . $column;

        $qb->leftJoin('e.' . $column, $alias);

        $orX = $qb->expr()->orX();
        foreach ($searchFields as $field) {
            $orX->add($qb->expr()->like($alias . '.' . $field, ':' . $paramName));
        }

        $qb->andWhere($orX)
            ->setParameter($paramName, '%' . $value . '%');
    }

    /**
     * Applies filtering logic for collection associations using EXISTS subquery.
     *
     * Uses EXISTS for performance: no row multiplication, works with pagination,
     * and efficiently uses indexes on join tables.
     *
     * @param array<string, mixed> $metadata
     */
    private function applyCollectionFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [, $paramName] = $this->getFilterContext($column, $metadata);

        $searchFields = $metadata['searchFields'] ?? [];
        if (empty($searchFields)) {
            return;
        }

        $targetClass = $metadata['targetClass'] ?? null;
        if ($targetClass === null) {
            return;
        }

        $subAlias = 'sub_' . $column;

        $subqueryConditions = [];
        foreach ($searchFields as $field) {
            $subqueryConditions[] = sprintf('%s.%s LIKE :%s', $subAlias, $field, $paramName);
        }

        $subqueryWhere = implode(' OR ', $subqueryConditions);

        $qb->andWhere(sprintf(
            'EXISTS (SELECT 1 FROM %s %s WHERE %s MEMBER OF e.%s AND (%s))',
            $targetClass,
            $subAlias,
            $subAlias,
            $column,
            $subqueryWhere
        ));

        $qb->setParameter($paramName, '%' . $value . '%');
    }
}
