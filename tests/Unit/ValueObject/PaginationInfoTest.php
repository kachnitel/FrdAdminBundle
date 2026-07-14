<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\ValueObject;

use Kachnitel\DataSourceContracts\PaginationInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginationInfoTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 100,
            currentPage: 2,
            itemsPerPage: 20
        );

        $this->assertSame(100, $pagination->totalItems);
        $this->assertSame(2, $pagination->currentPage);
        $this->assertSame(20, $pagination->itemsPerPage);
    }

    #[Test]
    public function getTotalPagesCalculatesCorrectly(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 100,
            currentPage: 1,
            itemsPerPage: 20
        );

        $this->assertSame(5, $pagination->getTotalPages());
    }

    #[Test]
    public function getTotalPagesRoundsUp(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 101,
            currentPage: 1,
            itemsPerPage: 20
        );

        $this->assertSame(6, $pagination->getTotalPages());
    }

    #[Test]
    public function getTotalPagesReturnsZeroForEmptyResults(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 0,
            currentPage: 1,
            itemsPerPage: 20
        );

        $this->assertSame(0, $pagination->getTotalPages());
    }

    #[Test]
    public function getTotalPagesHandlesSinglePage(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 15,
            currentPage: 1,
            itemsPerPage: 20
        );

        $this->assertSame(1, $pagination->getTotalPages());
    }

    #[Test]
    public function getTotalPagesHandlesExactMultiple(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 60,
            currentPage: 1,
            itemsPerPage: 20
        );

        $this->assertSame(3, $pagination->getTotalPages());
    }

    #[Test]
    public function getStartItemCalculatesCorrectlyForFirstPage(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 100,
            currentPage: 1,
            itemsPerPage: 20
        );

        $this->assertSame(1, $pagination->getStartItem());
    }

    #[Test]
    public function getStartItemCalculatesCorrectlyForMiddlePage(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 100,
            currentPage: 3,
            itemsPerPage: 20
        );

        $this->assertSame(41, $pagination->getStartItem());
    }

    #[Test]
    public function getStartItemReturnsZeroForEmptyResults(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 0,
            currentPage: 1,
            itemsPerPage: 20
        );

        $this->assertSame(0, $pagination->getStartItem());
    }

    #[Test]
    public function getEndItemCalculatesCorrectlyForFullPage(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 100,
            currentPage: 1,
            itemsPerPage: 20
        );

        $this->assertSame(20, $pagination->getEndItem());
    }

    #[Test]
    public function getEndItemCalculatesCorrectlyForPartialLastPage(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 45,
            currentPage: 3,
            itemsPerPage: 20
        );

        $this->assertSame(45, $pagination->getEndItem());
    }

    #[Test]
    public function getEndItemDoesNotExceedTotalItems(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 55,
            currentPage: 3,
            itemsPerPage: 20
        );

        // Page 3 would normally be 41-60, but only 55 items exist
        $this->assertSame(55, $pagination->getEndItem());
    }

    #[Test]
    public function getEndItemReturnsZeroForEmptyResults(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 0,
            currentPage: 1,
            itemsPerPage: 20
        );

        $this->assertSame(0, $pagination->getEndItem());
    }

    #[Test]
    public function differentItemsPerPageValues(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 100,
            currentPage: 2,
            itemsPerPage: 10
        );

        $this->assertSame(10, $pagination->getTotalPages());
        $this->assertSame(11, $pagination->getStartItem());
        $this->assertSame(20, $pagination->getEndItem());
    }

    #[Test]
    public function largeDataset(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 10000,
            currentPage: 50,
            itemsPerPage: 100
        );

        $this->assertSame(100, $pagination->getTotalPages());
        $this->assertSame(4901, $pagination->getStartItem());
        $this->assertSame(5000, $pagination->getEndItem());
    }

    #[Test]
    public function singleItem(): void
    {
        $pagination = new PaginationInfo(
            totalItems: 1,
            currentPage: 1,
            itemsPerPage: 20
        );

        $this->assertSame(1, $pagination->getTotalPages());
        $this->assertSame(1, $pagination->getStartItem());
        $this->assertSame(1, $pagination->getEndItem());
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new \ReflectionClass(PaginationInfo::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
