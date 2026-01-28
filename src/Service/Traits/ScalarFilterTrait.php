<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service\Traits;

use Doctrine\ORM\QueryBuilder;

/**
 * Handles scalar filter types: text, number, boolean, enum.
 */
trait ScalarFilterTrait
{
    /**
     * Get filter context (operator and parameter name).
     *
     * @param array<string, mixed> $metadata
     * @return array{0: string, 1: string}
     */
    abstract protected function getFilterContext(string $column, array $metadata): array;

    /**
     * Applies filtering logic for text columns (handles LIKE and exact/custom operators).
     *
     * @param array<string, mixed> $metadata
     */
    private function applyTextFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [$operator, $paramName] = $this->getFilterContext($column, $metadata);

        if ($operator === 'LIKE') {
            $qb->andWhere($qb->expr()->like('e.' . $column, ':' . $paramName))
                ->setParameter($paramName, '%' . $value . '%');
        } else {
            $qb->andWhere('e.' . $column . ' ' . $operator . ' :' . $paramName)
                ->setParameter($paramName, $value);
        }
    }

    /**
     * Applies simple equality/comparison filtering for Number, Boolean, and Enum types.
     *
     * @param array<string, mixed> $metadata
     */
    private function applySimpleFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [$operator, $paramName] = $this->getFilterContext($column, $metadata);

        if ($operator === 'IN') {
            $values = $this->parseMultiSelectValue($value);
            if (empty($values)) {
                return;
            }

            $qb->andWhere($qb->expr()->in('e.' . $column, ':' . $paramName))
                ->setParameter($paramName, $values);
            return;
        }

        $qb->andWhere('e.' . $column . ' ' . $operator . ' :' . $paramName)
            ->setParameter($paramName, $value);
    }

    /**
     * Parse multi-select value from JSON array string or array.
     *
     * @return array<string|int>
     */
    private function parseMultiSelectValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return [$value];
        }

        return [];
    }
}
