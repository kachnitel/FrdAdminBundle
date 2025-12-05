<?php

namespace Kachnitel\AdminBundle\Service\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Interface for entity list filters.
 */
interface FilterInterface
{
    /**
     * Apply the filter to a query builder.
     *
     * @param QueryBuilder $qb The query builder to modify
     * @param mixed $value The filter value from user input
     */
    public function apply(QueryBuilder $qb, mixed $value): void;

    /**
     * Get the filter name/key.
     */
    public function getName(): string;

    /**
     * Get the filter label for display.
     */
    public function getLabel(): string;

    /**
     * Get the filter type for rendering (text, select, date, etc.).
     */
    public function getType(): string;

    /**
     * Get options for select-type filters.
     */
    public function getOptions(): array;
}
