<?php

namespace Frd\AdminBundle\Tests\Unit\Attribute;

use Frd\AdminBundle\Attribute\ColumnFilter;
use PHPUnit\Framework\TestCase;

class ColumnFilterTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $filter = new ColumnFilter();

        $this->assertNull($filter->type);
        $this->assertTrue($filter->enabled);
        $this->assertNull($filter->label);
        $this->assertEmpty($filter->searchFields);
        $this->assertFalse($filter->deep);
        $this->assertNull($filter->operator);
        $this->assertTrue($filter->showAllOption);
        $this->assertNull($filter->placeholder);
        $this->assertNull($filter->priority);
    }

    public function testTextFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_TEXT,
            placeholder: 'Search by name...'
        );

        $this->assertEquals(ColumnFilter::TYPE_TEXT, $filter->type);
        $this->assertEquals('Search by name...', $filter->placeholder);
        $this->assertTrue($filter->enabled);
    }

    public function testNumberFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_NUMBER,
            operator: '>='
        );

        $this->assertEquals(ColumnFilter::TYPE_NUMBER, $filter->type);
        $this->assertEquals('>=', $filter->operator);
    }

    public function testDateFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_DATE,
            label: 'Created Date'
        );

        $this->assertEquals(ColumnFilter::TYPE_DATE, $filter->type);
        $this->assertEquals('Created Date', $filter->label);
    }

    public function testEnumFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_ENUM,
            showAllOption: false
        );

        $this->assertEquals(ColumnFilter::TYPE_ENUM, $filter->type);
        $this->assertFalse($filter->showAllOption);
    }

    public function testRelationFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_RELATION,
            searchFields: ['name', 'email', 'phone'],
            deep: true
        );

        $this->assertEquals(ColumnFilter::TYPE_RELATION, $filter->type);
        $this->assertEquals(['name', 'email', 'phone'], $filter->searchFields);
        $this->assertTrue($filter->deep);
    }

    public function testDisabledFilter(): void
    {
        $filter = new ColumnFilter(enabled: false);

        $this->assertFalse($filter->enabled);
    }

    public function testPriorityConfiguration(): void
    {
        $filter = new ColumnFilter(priority: 1);

        $this->assertEquals(1, $filter->priority);
    }

    public function testFullConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_TEXT,
            enabled: true,
            label: 'Product Name',
            searchFields: ['name', 'sku'],
            deep: false,
            operator: 'LIKE',
            showAllOption: true,
            placeholder: 'Search products...',
            priority: 10
        );

        $this->assertEquals(ColumnFilter::TYPE_TEXT, $filter->type);
        $this->assertTrue($filter->enabled);
        $this->assertEquals('Product Name', $filter->label);
        $this->assertEquals(['name', 'sku'], $filter->searchFields);
        $this->assertFalse($filter->deep);
        $this->assertEquals('LIKE', $filter->operator);
        $this->assertTrue($filter->showAllOption);
        $this->assertEquals('Search products...', $filter->placeholder);
        $this->assertEquals(10, $filter->priority);
    }
}
