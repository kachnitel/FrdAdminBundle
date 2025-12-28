<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Kachnitel\AdminBundle\ValueObject\PaginationInfo;

/**
 * Result container for paginated data source queries.
 *
 * Wraps the items and pagination metadata returned by a DataSourceInterface::query() call.
 */
readonly class PaginatedResult
{
    /**
     * @param array<object> $items The items for the current page
     * @param int $totalItems Total number of items across all pages
     * @param int $currentPage Current page number (1-indexed)
     * @param int $itemsPerPage Number of items per page
     */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $currentPage,
        public int $itemsPerPage,
    ) {}

    /**
     * Get total number of pages.
     */
    public function getTotalPages(): int
    {
        if ($this->totalItems === 0) {
            return 0;
        }

        return (int) ceil($this->totalItems / $this->itemsPerPage);
    }

    /**
     * Check if there are more pages after the current one.
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getTotalPages();
    }

    /**
     * Check if there are pages before the current one.
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Get the starting item number for current page (1-indexed).
     */
    public function getStartItem(): int
    {
        if ($this->totalItems === 0) {
            return 0;
        }

        return (($this->currentPage - 1) * $this->itemsPerPage) + 1;
    }

    /**
     * Get the ending item number for current page.
     */
    public function getEndItem(): int
    {
        if ($this->totalItems === 0) {
            return 0;
        }

        return min($this->currentPage * $this->itemsPerPage, $this->totalItems);
    }

    /**
     * Convert to PaginationInfo for compatibility with existing templates.
     */
    public function toPaginationInfo(): PaginationInfo
    {
        return new PaginationInfo(
            $this->totalItems,
            $this->currentPage,
            $this->itemsPerPage
        );
    }

    /**
     * Create from existing query result array.
     *
     * @param array{entities: array<object>, total: int, page: int} $result
     */
    public static function fromQueryResult(array $result, int $itemsPerPage): self
    {
        return new self(
            items: $result['entities'],
            totalItems: $result['total'],
            currentPage: $result['page'],
            itemsPerPage: $itemsPerPage,
        );
    }
}
