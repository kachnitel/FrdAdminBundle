<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\AdminBundle\DataSource\PaginatedResult;
use PHPUnit\Framework\TestCase;

class PaginatedResultTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $items = [new \stdClass(), new \stdClass()];
        $result = new PaginatedResult($items, 100, 2, 20);

        $this->assertSame($items, $result->items);
        $this->assertSame(100, $result->totalItems);
        $this->assertSame(2, $result->currentPage);
        $this->assertSame(20, $result->itemsPerPage);
    }

    public function testGetTotalPagesWithMultiplePages(): void
    {
        $result = new PaginatedResult([], 100, 1, 20);

        $this->assertSame(5, $result->getTotalPages());
    }

    public function testGetTotalPagesWithPartialLastPage(): void
    {
        $result = new PaginatedResult([], 95, 1, 20);

        $this->assertSame(5, $result->getTotalPages());
    }

    public function testGetTotalPagesWithExactDivision(): void
    {
        $result = new PaginatedResult([], 60, 1, 20);

        $this->assertSame(3, $result->getTotalPages());
    }

    public function testGetTotalPagesWithZeroItems(): void
    {
        $result = new PaginatedResult([], 0, 1, 20);

        $this->assertSame(0, $result->getTotalPages());
    }

    public function testGetTotalPagesWithSinglePage(): void
    {
        $result = new PaginatedResult([], 15, 1, 20);

        $this->assertSame(1, $result->getTotalPages());
    }

    public function testHasNextPageReturnsTrueWhenMorePagesExist(): void
    {
        $result = new PaginatedResult([], 100, 2, 20);

        $this->assertTrue($result->hasNextPage());
    }

    public function testHasNextPageReturnsFalseOnLastPage(): void
    {
        $result = new PaginatedResult([], 100, 5, 20);

        $this->assertFalse($result->hasNextPage());
    }

    public function testHasNextPageReturnsFalseWithZeroItems(): void
    {
        $result = new PaginatedResult([], 0, 1, 20);

        $this->assertFalse($result->hasNextPage());
    }

    public function testHasPreviousPageReturnsTrueWhenNotOnFirstPage(): void
    {
        $result = new PaginatedResult([], 100, 2, 20);

        $this->assertTrue($result->hasPreviousPage());
    }

    public function testHasPreviousPageReturnsFalseOnFirstPage(): void
    {
        $result = new PaginatedResult([], 100, 1, 20);

        $this->assertFalse($result->hasPreviousPage());
    }

    public function testGetStartItemOnFirstPage(): void
    {
        $result = new PaginatedResult([], 100, 1, 20);

        $this->assertSame(1, $result->getStartItem());
    }

    public function testGetStartItemOnSecondPage(): void
    {
        $result = new PaginatedResult([], 100, 2, 20);

        $this->assertSame(21, $result->getStartItem());
    }

    public function testGetStartItemWithZeroItems(): void
    {
        $result = new PaginatedResult([], 0, 1, 20);

        $this->assertSame(0, $result->getStartItem());
    }

    public function testGetEndItemOnFirstPage(): void
    {
        $result = new PaginatedResult([], 100, 1, 20);

        $this->assertSame(20, $result->getEndItem());
    }

    public function testGetEndItemOnLastPage(): void
    {
        $result = new PaginatedResult([], 95, 5, 20);

        $this->assertSame(95, $result->getEndItem());
    }

    public function testGetEndItemWithZeroItems(): void
    {
        $result = new PaginatedResult([], 0, 1, 20);

        $this->assertSame(0, $result->getEndItem());
    }

    public function testToPaginationInfo(): void
    {
        $result = new PaginatedResult([], 100, 2, 20);
        $paginationInfo = $result->toPaginationInfo();

        $this->assertSame(100, $paginationInfo->totalItems);
        $this->assertSame(2, $paginationInfo->currentPage);
        $this->assertSame(20, $paginationInfo->itemsPerPage);
    }

    public function testFromQueryResult(): void
    {
        $entities = [new \stdClass(), new \stdClass()];
        $queryResult = [
            'entities' => $entities,
            'total' => 50,
            'page' => 3,
        ];

        $result = PaginatedResult::fromQueryResult($queryResult, 10);

        $this->assertSame($entities, $result->items);
        $this->assertSame(50, $result->totalItems);
        $this->assertSame(3, $result->currentPage);
        $this->assertSame(10, $result->itemsPerPage);
    }
}
