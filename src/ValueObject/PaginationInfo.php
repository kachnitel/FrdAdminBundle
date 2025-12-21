<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\ValueObject;

/**
 * Value object representing pagination information.
 */
readonly class PaginationInfo
{
    public function __construct(
        public int $totalItems,
        public int $currentPage,
        public int $itemsPerPage
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
     * Get the starting item number for current page.
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
}
