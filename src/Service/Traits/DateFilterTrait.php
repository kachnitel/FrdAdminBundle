<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service\Traits;

use Doctrine\ORM\QueryBuilder;

/**
 * Handles date and daterange filter types.
 */
trait DateFilterTrait
{
    /**
     * Get filter context (operator and parameter name).
     *
     * @param array<string, mixed> $metadata
     * @return array{0: string, 1: string}
     */
    abstract protected function getFilterContext(string $column, array $metadata): array;

    /**
     * Applies filtering logic for date/time columns.
     * For single date selection, matches the exact day (from 00:00:00 to 23:59:59).
     *
     * @param array<string, mixed> $metadata
     */
    private function applyDateFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [, $paramName] = $this->getFilterContext($column, $metadata);

        $dateValue = $value instanceof \DateTimeInterface ? $value : new \DateTime((string) $value);

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
     * @param array<string, mixed> $metadata
     */
    private function applyDateRangeFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        [, $paramName] = $this->getFilterContext($column, $metadata);

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
}
