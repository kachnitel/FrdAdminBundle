<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\DataSource\FilterEnumOptions;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use PHPUnit\Framework\TestCase;

class FilterMetadataTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $filter = new FilterMetadata(name: 'testFilter');

        $this->assertSame('testFilter', $filter->name);
        $this->assertSame(ColumnFilter::TYPE_TEXT, $filter->type);
        $this->assertNull($filter->label);
        $this->assertNull($filter->placeholder);
        $this->assertSame('=', $filter->operator);
        $this->assertNull($filter->getOptions());
        $this->assertNull($filter->getEnumClass());
        $this->assertTrue($filter->getShowAllOption());
        $this->assertNull($filter->searchFields);
        $this->assertSame(999, $filter->priority);
        $this->assertTrue($filter->enabled);
    }

    public function testConstructorWithAllParameters(): void
    {
        $filter = new FilterMetadata(
            name: 'status',
            type: ColumnFilter::TYPE_ENUM,
            label: 'Status',
            placeholder: 'Select status',
            operator: '=',
            enumOptions: new FilterEnumOptions(values: ['active', 'inactive'], showAllOption: false),
            searchFields: null,
            priority: 10,
            enabled: true
        );

        $this->assertSame('status', $filter->name);
        $this->assertSame(ColumnFilter::TYPE_ENUM, $filter->type);
        $this->assertSame('Status', $filter->label);
        $this->assertSame('Select status', $filter->placeholder);
        $this->assertSame('=', $filter->operator);
        $this->assertSame(['active', 'inactive'], $filter->getOptions());
        $this->assertFalse($filter->getShowAllOption());
        $this->assertSame(10, $filter->priority);
    }

    public function testTextFactory(): void
    {
        $filter = FilterMetadata::text(
            name: 'name',
            label: 'Name',
            placeholder: 'Search...',
            priority: 5
        );

        $this->assertSame('name', $filter->name);
        $this->assertSame(ColumnFilter::TYPE_TEXT, $filter->type);
        $this->assertSame('Name', $filter->label);
        $this->assertSame('Search...', $filter->placeholder);
        $this->assertSame('LIKE', $filter->operator);
        $this->assertSame(5, $filter->priority);
    }

    public function testTextFactoryWithDefaults(): void
    {
        $filter = FilterMetadata::text(name: 'createdBy');

        $this->assertSame('createdBy', $filter->name);
        $this->assertSame('Created by', $filter->label);
        $this->assertNull($filter->placeholder);
        $this->assertSame(999, $filter->priority);
    }

    public function testNumberFactory(): void
    {
        $filter = FilterMetadata::number(
            name: 'quantity',
            label: 'Quantity',
            operator: '>=',
            priority: 10
        );

        $this->assertSame('quantity', $filter->name);
        $this->assertSame(ColumnFilter::TYPE_NUMBER, $filter->type);
        $this->assertSame('Quantity', $filter->label);
        $this->assertSame('>=', $filter->operator);
        $this->assertSame(10, $filter->priority);
    }

    public function testNumberFactoryDefaults(): void
    {
        $filter = FilterMetadata::number(name: 'price');

        $this->assertSame('=', $filter->operator);
        $this->assertSame('Price', $filter->label);
    }

    public function testDateFactory(): void
    {
        $filter = FilterMetadata::date(
            name: 'createdAt',
            label: 'Created At',
            operator: '<=',
            priority: 20
        );

        $this->assertSame('createdAt', $filter->name);
        $this->assertSame(ColumnFilter::TYPE_DATE, $filter->type);
        $this->assertSame('Created At', $filter->label);
        $this->assertSame('<=', $filter->operator);
        $this->assertSame(20, $filter->priority);
    }

    public function testDateFactoryDefaults(): void
    {
        $filter = FilterMetadata::date(name: 'updatedAt');

        $this->assertSame('>=', $filter->operator);
        $this->assertSame('Updated at', $filter->label);
    }

    public function testDateRangeFactory(): void
    {
        $filter = FilterMetadata::dateRange(
            name: 'dateRange',
            label: 'Date Range',
            priority: 15
        );

        $this->assertSame('dateRange', $filter->name);
        $this->assertSame(ColumnFilter::TYPE_DATERANGE, $filter->type);
        $this->assertSame('Date Range', $filter->label);
        $this->assertSame('BETWEEN', $filter->operator);
        $this->assertSame(15, $filter->priority);
    }

    public function testEnumFactory(): void
    {
        $options = ['pending', 'approved', 'rejected'];
        $filter = FilterMetadata::enum(
            name: 'status',
            options: $options,
            label: 'Status',
            showAllOption: false,
            priority: 5
        );

        $this->assertSame('status', $filter->name);
        $this->assertSame(ColumnFilter::TYPE_ENUM, $filter->type);
        $this->assertSame('Status', $filter->label);
        $this->assertSame('=', $filter->operator);
        $this->assertSame($options, $filter->getOptions());
        $this->assertFalse($filter->getShowAllOption());
        $this->assertSame(5, $filter->priority);
    }

    public function testEnumFactoryDefaults(): void
    {
        $filter = FilterMetadata::enum(name: 'type', options: ['a', 'b']);

        $this->assertTrue($filter->getShowAllOption());
        $this->assertSame('Type', $filter->label);
    }

    public function testEnumClassFactory(): void
    {
        $filter = FilterMetadata::enumClass(
            name: 'status',
            enumClass: 'App\\Enum\\Status', // @phpstan-ignore argument.type
            label: 'Status',
            showAllOption: true,
            priority: 10
        );

        $this->assertSame('status', $filter->name);
        $this->assertSame(ColumnFilter::TYPE_ENUM, $filter->type);
        $this->assertSame('App\\Enum\\Status', $filter->getEnumClass());
        $this->assertTrue($filter->getShowAllOption());
    }

    public function testBooleanFactory(): void
    {
        $filter = FilterMetadata::boolean(
            name: 'isActive',
            label: 'Is Active',
            showAllOption: false,
            priority: 25
        );

        $this->assertSame('isActive', $filter->name);
        $this->assertSame(ColumnFilter::TYPE_BOOLEAN, $filter->type);
        $this->assertSame('Is Active', $filter->label);
        $this->assertSame('=', $filter->operator);
        $this->assertFalse($filter->getShowAllOption());
        $this->assertSame(25, $filter->priority);
    }

    public function testBooleanFactoryDefaults(): void
    {
        $filter = FilterMetadata::boolean(name: 'enabled');

        $this->assertTrue($filter->getShowAllOption());
        $this->assertSame('Enabled', $filter->label);
    }

    public function testToArrayBasic(): void
    {
        $filter = FilterMetadata::text(name: 'name', label: 'Name');
        $array = $filter->toArray();

        $this->assertSame('name', $array['property']);
        $this->assertSame('Name', $array['label']);
        $this->assertSame(ColumnFilter::TYPE_TEXT, $array['type']);
        $this->assertSame('LIKE', $array['operator']);
        $this->assertTrue($array['enabled']);
        $this->assertSame(999, $array['priority']);
    }

    public function testToArrayWithPlaceholder(): void
    {
        $filter = FilterMetadata::text(name: 'search', placeholder: 'Type to search...');
        $array = $filter->toArray();

        $this->assertArrayHasKey('placeholder', $array);
        $this->assertSame('Type to search...', $array['placeholder']);
    }

    public function testToArrayWithOptions(): void
    {
        $filter = FilterMetadata::enum(name: 'status', options: ['a', 'b', 'c']);
        $array = $filter->toArray();

        $this->assertArrayHasKey('options', $array);
        $this->assertSame(['a', 'b', 'c'], $array['options']);
    }

    public function testToArrayWithEnumClass(): void
    {
        // @phpstan-ignore-next-line (intentionally testing with non-existent enum)
        $filter = FilterMetadata::enumClass(name: 'status', enumClass: 'App\\Enum\\Status');
        $array = $filter->toArray();

        $this->assertArrayHasKey('enumClass', $array);
        $this->assertSame('App\\Enum\\Status', $array['enumClass']);
    }

    public function testToArrayWithShowAllOptionFalse(): void
    {
        $filter = FilterMetadata::boolean(name: 'active', showAllOption: false);
        $array = $filter->toArray();

        $this->assertArrayHasKey('showAllOption', $array);
        $this->assertFalse($array['showAllOption']);
    }

    public function testToArrayOmitsShowAllOptionWhenTrue(): void
    {
        $filter = FilterMetadata::boolean(name: 'active', showAllOption: true);
        $array = $filter->toArray();

        // Should not be included when true (default)
        $this->assertArrayNotHasKey('showAllOption', $array);
    }

    public function testToArrayWithSearchFields(): void
    {
        $filter = new FilterMetadata(
            name: 'relation',
            searchFields: ['name', 'email']
        );
        $array = $filter->toArray();

        $this->assertArrayHasKey('searchFields', $array);
        $this->assertSame(['name', 'email'], $array['searchFields']);
    }

    public function testHumanizeConvertsPropertyNames(): void
    {
        $testCases = [
            'name' => 'Name',
            'createdAt' => 'Created at',
            'updatedByUser' => 'Updated by user',
            'isActive' => 'Is active',
            'XMLParser' => 'X m l parser', // Edge case
        ];

        foreach ($testCases as $input => $expected) {
            $filter = FilterMetadata::text(name: $input);
            $this->assertSame($expected, $filter->toArray()['label'], "Failed for input: $input");
        }
    }

    public function testReadonlyClass(): void
    {
        $filter = new FilterMetadata(name: 'test');

        $reflection = new \ReflectionClass($filter);
        $this->assertTrue($reflection->isReadOnly());
    }
}
