<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service\Traits;

use Doctrine\ORM\Query\Expr\Orx;
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
     * Supports dot-notation nested paths (e.g. 'deep.label') by emitting additional
     * LEFT JOINs through intermediate associations and referencing the leaf field alias.
     *
     * Example for searchFields: ['title', 'deep.label']:
     *   LEFT JOIN e.middle rel_middle
     *   LEFT JOIN rel_middle.deep rel_middle_deep
     *   WHERE rel_middle.title LIKE :p OR rel_middle_deep.label LIKE :p
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

        $primaryAlias = 'rel_' . $column;

        $qb->leftJoin('e.' . $column, $primaryAlias);

        $orX = $qb->expr()->orX();

        foreach ($searchFields as $field) {
            if (str_contains($field, '.')) {
                $this->addNestedFieldCondition($qb, $orX, $primaryAlias, $field, $paramName);
            } else {
                $orX->add($qb->expr()->like($primaryAlias . '.' . $field, ':' . $paramName));
            }
        }

        // Also match by exact ID when value is numeric (supports collection admin links)
        if (is_numeric($value)) {
            $orX->add($qb->expr()->eq($primaryAlias . '.id', ':' . $paramName . '_id'));
            $qb->setParameter($paramName . '_id', (int) $value);
        }

        $qb->andWhere($orX)
            ->setParameter($paramName, '%' . $value . '%');
    }

    /**
     * Recursively resolves a dot-notation path, emitting LEFT JOINs for each intermediate
     * association and adding a LIKE condition on the leaf field.
     *
     * Example: field='deep.label', baseAlias='rel_middle'
     *   → LEFT JOIN rel_middle.deep rel_middle_deep
     *   → orX: rel_middle_deep.label LIKE :param
     *
     * Deeper nesting (a.b.c.field) is handled recursively:
     *   → LEFT JOIN baseAlias.a  base_a
     *   → LEFT JOIN base_a.b     base_a_b
     *   → orX: base_a_b.c LIKE :param   (if 'c' is the leaf field)
     */
    private function addNestedFieldCondition(
        QueryBuilder $qb,
        Orx $orX,
        string $baseAlias,
        string $dotPath,
        string $paramName
    ): void {
        $parts = explode('.', $dotPath);
        $leafField = array_pop($parts);

        $currentAlias = $baseAlias;

        foreach ($parts as $assocSegment) {
            $nestedAlias = $currentAlias . '_' . $assocSegment;

            // Only add the JOIN if it has not been registered yet (avoids duplicate JOINs
            // when multiple search fields share the same intermediate association)
            if (!$this->hasJoin($qb, $nestedAlias)) {
                $qb->leftJoin($currentAlias . '.' . $assocSegment, $nestedAlias);
            }

            $currentAlias = $nestedAlias;
        }

        $orX->add($qb->expr()->like($currentAlias . '.' . $leafField, ':' . $paramName));
    }

    /**
     * Check whether a LEFT JOIN to the given alias is already registered on the QueryBuilder.
     *
     * Prevents duplicate JOIN declarations when multiple dot-notation fields share the same
     * intermediate association (e.g. 'deep.label' and 'deep.code' both join through 'deep').
     */
    private function hasJoin(QueryBuilder $qb, string $alias): bool
    {
        foreach ($qb->getDQLPart('join') as $joins) {
            foreach ($joins as $join) {
                if ($join->getAlias() === $alias) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Applies filtering logic for collection associations using EXISTS subquery.
     *
     * Uses EXISTS for performance: no row multiplication, works with pagination,
     * and efficiently uses indexes on join tables.
     *
     * Supports dot-notation nested paths (e.g. 'relatedItem.display') by embedding
     * LEFT JOINs directly inside the EXISTS DQL string, keeping them scoped to the
     * subquery and avoiding conflicts with the outer QueryBuilder.
     *
     * Example for searchFields: ['name', 'relatedItem.display']:
     *   EXISTS (
     *     SELECT 1 FROM App\Entity\Item sub_items
     *     LEFT JOIN sub_items.relatedItem sub_items_relatedItem
     *     WHERE sub_items MEMBER OF e.items
     *       AND (sub_items.name LIKE :p OR sub_items_relatedItem.display LIKE :p)
     *   )
     *
     * @param array<string, mixed> $metadata
     */
    private function applyCollectionFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [, $paramName] = $this->getFilterContext($column, $metadata);

        $searchFields = $metadata['searchFields'] ?? [];
        $targetClass  = $metadata['targetClass'] ?? null;

        if ($targetClass === null) {
            return;
        }

        $subAlias           = 'sub_' . $column;
        $subqueryConditions = [];
        $subqueryJoins      = []; // keyed by alias to deduplicate shared intermediates

        foreach ($searchFields as $field) {
            if (str_contains($field, '.')) {
                [$joins, $condition] = $this->buildNestedCollectionCondition(
                    $subAlias,
                    $field,
                    $paramName,
                );

                // Merge joins, deduplicating by alias (same key = same JOIN string)
                $subqueryJoins      = array_merge($subqueryJoins, $joins);
                $subqueryConditions[] = $condition;
            } else {
                $subqueryConditions[] = sprintf('%s.%s LIKE :%s', $subAlias, $field, $paramName);
            }
        }

        // Also match by exact ID when value is numeric.
        // Essential for collection admin links generated by admin_collection_url(), which passes
        // the source entity's ID as the filter value, so 'id' need not be in every searchFields.
        if (is_numeric($value)) {
            $subqueryConditions[] = sprintf('%s.id = :%s_id', $subAlias, $paramName);
            $qb->setParameter($paramName . '_id', (int) $value);
        }

        if (empty($subqueryConditions)) {
            return;  // no searchFields AND non-numeric value → nothing to filter on
        }

        $joinsStr      = $subqueryJoins ? ' ' . implode(' ', $subqueryJoins) : '';
        $subqueryWhere = implode(' OR ', $subqueryConditions);

        $qb->andWhere(sprintf(
            'EXISTS (SELECT 1 FROM %s %s%s WHERE %s MEMBER OF e.%s AND (%s))',
            $targetClass,
            $subAlias,
            $joinsStr,
            $subAlias,
            $column,
            $subqueryWhere,
        ));

        $qb->setParameter($paramName, '%' . $value . '%');
    }

    /**
     * Resolves a dot-notation field path relative to a collection sub-alias, producing:
     *   - an ordered, alias-keyed map of DQL LEFT JOIN strings (deduplication-safe)
     *   - a LIKE condition string on the leaf field
     *
     * Example: field='relatedItem.display', subAlias='sub_items', paramName='p'
     *   joins:     ['sub_items_relatedItem' => 'LEFT JOIN sub_items.relatedItem sub_items_relatedItem']
     *   condition: 'sub_items_relatedItem.display LIKE :p'
     *
     * Deeper nesting (a.b.c) is handled iteratively:
     *   joins:     ['sub_items_a' => 'LEFT JOIN sub_items.a sub_items_a',
     *               'sub_items_a_b' => 'LEFT JOIN sub_items_a.b sub_items_a_b']
     *   condition: 'sub_items_a_b.c LIKE :p'
     *
     * The returned JOIN strings are embedded directly into the EXISTS subquery DQL so that
     * Doctrine resolves them within the correct scope — NOT added to the outer QueryBuilder.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function buildNestedCollectionCondition(
        string $subAlias,
        string $dotPath,
        string $paramName,
    ): array {
        $parts        = explode('.', $dotPath);
        $leafField    = array_pop($parts);
        $currentAlias = $subAlias;
        $joins        = [];

        foreach ($parts as $assocSegment) {
            $nestedAlias         = $currentAlias . '_' . $assocSegment;
            $joins[$nestedAlias] = sprintf('LEFT JOIN %s.%s %s', $currentAlias, $assocSegment, $nestedAlias);
            $currentAlias        = $nestedAlias;
        }

        $condition = sprintf('%s.%s LIKE :%s', $currentAlias, $leafField, $paramName);

        return [$joins, $condition];
    }
}
