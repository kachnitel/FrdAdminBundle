<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use PHPUnit\Framework\TestCase;

final class ColumnFilterTest extends TestCase
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
        $this->assertTrue($filter->excludeFromGlobalSearch);
    }

    public function testTextFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_TEXT,
            placeholder: 'Search by name...'
        );

        $this->assertSame(ColumnFilter::TYPE_TEXT, $filter->type);
        $this->assertSame('Search by name...', $filter->placeholder);
        $this->assertTrue($filter->enabled);
    }

    public function testNumberFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_NUMBER,
            operator: '>='
        );

        $this->assertSame(ColumnFilter::TYPE_NUMBER, $filter->type);
        $this->assertSame('>=', $filter->operator);
    }

    public function testDateFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_DATE,
            label: 'Created Date'
        );

        $this->assertSame(ColumnFilter::TYPE_DATE, $filter->type);
        $this->assertSame('Created Date', $filter->label);
    }

    public function testEnumFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_ENUM,
            showAllOption: false
        );

        $this->assertSame(ColumnFilter::TYPE_ENUM, $filter->type);
        $this->assertFalse($filter->showAllOption);
    }

    public function testRelationFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_RELATION,
            searchFields: ['name', 'email', 'phone'],
            deep: true
        );

        $this->assertSame(ColumnFilter::TYPE_RELATION, $filter->type);
        $this->assertSame(['name', 'email', 'phone'], $filter->searchFields);
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

        $this->assertSame(1, $filter->priority);
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

        $this->assertSame(ColumnFilter::TYPE_TEXT, $filter->type);
        $this->assertTrue($filter->enabled);
        $this->assertSame('Product Name', $filter->label);
        $this->assertSame(['name', 'sku'], $filter->searchFields);
        $this->assertFalse($filter->deep);
        $this->assertSame('LIKE', $filter->operator);
        $this->assertTrue($filter->showAllOption);
        $this->assertSame('Search products...', $filter->placeholder);
        $this->assertSame(10, $filter->priority);
    }

    public function testMultiplePropertyDefaultValue(): void
    {
        $filter = new ColumnFilter();

        $this->assertFalse($filter->multiple);
    }

    public function testMultiplePropertyExplicitTrue(): void
    {
        $filter = new ColumnFilter(multiple: true);

        $this->assertTrue($filter->multiple);
    }

    public function testMultipleEnumConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_ENUM,
            multiple: true,
            showAllOption: false
        );

        $this->assertSame(ColumnFilter::TYPE_ENUM, $filter->type);
        $this->assertTrue($filter->multiple);
        $this->assertFalse($filter->showAllOption);
    }

    public function testCollectionFilterConfiguration(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_COLLECTION,
            searchFields: ['name', 'display'],
            label: 'Tags'
        );

        $this->assertSame(ColumnFilter::TYPE_COLLECTION, $filter->type);
        $this->assertSame(['name', 'display'], $filter->searchFields);
        $this->assertSame('Tags', $filter->label);
        $this->assertTrue($filter->excludeFromGlobalSearch);
    }

    public function testCollectionFilterWithGlobalSearchEnabled(): void
    {
        $filter = new ColumnFilter(
            type: ColumnFilter::TYPE_COLLECTION,
            searchFields: ['name'],
            excludeFromGlobalSearch: false
        );

        $this->assertSame(ColumnFilter::TYPE_COLLECTION, $filter->type);
        $this->assertFalse($filter->excludeFromGlobalSearch);
    }

    public function testTypeCollectionConstantExists(): void
    {
        $this->assertSame('collection', ColumnFilter::TYPE_COLLECTION); // @phpstan-ignore method.alreadyNarrowedType
    }
}
