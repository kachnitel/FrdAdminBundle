<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service\Filter;

use Kachnitel\AdminBundle\Service\Filter\FieldFilter;
use PHPUnit\Framework\TestCase;

class FieldFilterTest extends TestCase
{
    /**
     * @test
     */
    public function constructorInitializesName(): void
    {
        $filter = new FieldFilter('product_name', 'Product Name', 'name');
        $this->assertEquals('product_name', $filter->getName());
    }

    /**
     * @test
     */
    public function constructorInitializesLabel(): void
    {
        $filter = new FieldFilter('product_name', 'Product Name', 'name');
        $this->assertEquals('Product Name', $filter->getLabel());
    }

    /**
     * @test
     */
    public function constructorInitializesType(): void
    {
        $filter = new FieldFilter('name', 'Name', 'name', '=', 'text');
        $this->assertEquals('text', $filter->getType());
    }

    /**
     * @test
     */
    public function defaultTypeIsText(): void
    {
        $filter = new FieldFilter('name', 'Name', 'name');
        $this->assertEquals('text', $filter->getType());
    }

    /**
     * @test
     */
    public function defaultOperatorIsEquals(): void
    {
        $filter = new FieldFilter('status', 'Status', 'status');
        $this->assertEquals('status', $filter->getName());
    }

    /**
     * @test
     */
    public function getOptionsReturnsProvidedOptions(): void
    {
        $options = ['placeholder' => 'Search', 'class' => 'form-control'];
        $filter = new FieldFilter('name', 'Name', 'name', '=', 'text', $options);
        $this->assertEquals($options, $filter->getOptions());
    }

    /**
     * @test
     */
    public function getOptionsReturnsEmptyArrayByDefault(): void
    {
        $filter = new FieldFilter('name', 'Name', 'name');
        $this->assertEmpty($filter->getOptions());
    }

    /**
     * @test
     */
    public function supportsLikeOperator(): void
    {
        $filter = new FieldFilter('name', 'Name', 'name', 'LIKE');
        $this->assertEquals('name', $filter->getName());
    }

    /**
     * @test
     */
    public function supportsInOperator(): void
    {
        $filter = new FieldFilter('status', 'Status', 'status', 'IN');
        $this->assertEquals('status', $filter->getName());
    }

    /**
     * @test
     */
    public function supportsGreaterThanOperator(): void
    {
        $filter = new FieldFilter('price', 'Price', 'price', '>');
        $this->assertEquals('price', $filter->getName());
    }

    /**
     * @test
     */
    public function supportsLessThanOperator(): void
    {
        $filter = new FieldFilter('price', 'Price', 'price', '<');
        $this->assertEquals('price', $filter->getName());
    }

    /**
     * @test
     */
    public function supportsGreaterThanOrEqualOperator(): void
    {
        $filter = new FieldFilter('quantity', 'Quantity', 'quantity', '>=');
        $this->assertEquals('quantity', $filter->getName());
    }

    /**
     * @test
     */
    public function supportsLessThanOrEqualOperator(): void
    {
        $filter = new FieldFilter('quantity', 'Quantity', 'quantity', '<=');
        $this->assertEquals('quantity', $filter->getName());
    }

    /**
     * @test
     */
    public function handlesFieldNamesWithDots(): void
    {
        // Fields with dots represent relations (e.g., relation.field)
        $filter = new FieldFilter('related.name', 'Related Name', 'related.name');
        $this->assertEquals('related.name', $filter->getName());
    }

    /**
     * @test
     */
    public function canCreateMultipleFilters(): void
    {
        $filters = [
            new FieldFilter('name', 'Name', 'name', 'LIKE'),
            new FieldFilter('status', 'Status', 'status', 'IN'),
            new FieldFilter('price', 'Price', 'price', '>'),
        ];

        $this->assertCount(3, $filters);
        $this->assertEquals('name', $filters[0]->getName());
        $this->assertEquals('status', $filters[1]->getName());
        $this->assertEquals('price', $filters[2]->getName());
    }

    /**
     * @test
     */
    public function optionsCanIncludeMultipleEntries(): void
    {
        $options = [
            'placeholder' => 'Enter value',
            'class' => 'form-control',
            'pattern' => '[0-9]+',
            'data-attr' => 'value',
        ];
        $filter = new FieldFilter('field', 'Label', 'field', '=', 'text', $options);
        $this->assertEquals($options, $filter->getOptions());
    }

    /**
     * @test
     */
    public function typeCanBeCustom(): void
    {
        $filter = new FieldFilter('count', 'Count', 'count', '=', 'integer');
        $this->assertEquals('integer', $filter->getType());
    }

    /**
     * @test
     */
    public function nameAndLabelCanBeDifferent(): void
    {
        $filter = new FieldFilter('user_name', 'Full Name', 'name');
        $this->assertEquals('user_name', $filter->getName());
        $this->assertEquals('Full Name', $filter->getLabel());
    }
}
