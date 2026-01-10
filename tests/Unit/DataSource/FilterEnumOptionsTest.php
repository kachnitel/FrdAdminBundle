<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\AdminBundle\DataSource\FilterEnumOptions;
use PHPUnit\Framework\TestCase;

enum TestEnumStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum TestEnumPriority: int
{
    case Low = 1;
    case High = 2;
}

class FilterEnumOptionsTest extends TestCase
{
    /**
     * @test
     */
    public function defaultValuesAreNull(): void
    {
        $options = new FilterEnumOptions();

        $this->assertNull($options->values);
        $this->assertNull($options->enumClass);
        $this->assertTrue($options->showAllOption);
    }

    /**
     * @test
     */
    public function valuesCanBeSet(): void
    {
        $values = ['active', 'inactive', 'pending'];
        $options = new FilterEnumOptions(values: $values);

        $this->assertSame($values, $options->values);
        $this->assertNull($options->enumClass);
    }

    /**
     * @test
     */
    public function enumClassCanBeSet(): void
    {
        $options = new FilterEnumOptions(enumClass: 'App\\Enum\\Status');

        $this->assertNull($options->values);
        $this->assertSame('App\\Enum\\Status', $options->enumClass);
    }

    /**
     * @test
     */
    public function showAllOptionCanBeDisabled(): void
    {
        $options = new FilterEnumOptions(showAllOption: false);

        $this->assertFalse($options->showAllOption);
    }

    /**
     * @test
     */
    public function allParametersCanBeSetTogether(): void
    {
        $values = ['option1', 'option2'];
        $options = new FilterEnumOptions(
            values: $values,
            enumClass: 'App\\Enum\\CustomType',
            showAllOption: false
        );

        $this->assertSame($values, $options->values);
        $this->assertSame('App\\Enum\\CustomType', $options->enumClass);
        $this->assertFalse($options->showAllOption);
    }

    /**
     * @test
     */
    public function fromValuesCreatesInstanceWithValues(): void
    {
        $values = ['red', 'green', 'blue'];
        $options = FilterEnumOptions::fromValues($values);

        $this->assertSame($values, $options->values);
        $this->assertNull($options->enumClass);
        $this->assertTrue($options->showAllOption);
    }

    /**
     * @test
     */
    public function fromValuesCanDisableShowAllOption(): void
    {
        $values = ['yes', 'no'];
        $options = FilterEnumOptions::fromValues($values, showAllOption: false);

        $this->assertSame($values, $options->values);
        $this->assertFalse($options->showAllOption);
    }

    /**
     * @test
     */
    public function fromEnumClassCreatesInstanceWithEnumClass(): void
    {
        $options = FilterEnumOptions::fromEnumClass(TestEnumStatus::class);

        $this->assertNull($options->values);
        $this->assertSame(TestEnumStatus::class, $options->enumClass);
        $this->assertTrue($options->showAllOption);
    }

    /**
     * @test
     */
    public function fromEnumClassCanDisableShowAllOption(): void
    {
        $options = FilterEnumOptions::fromEnumClass(TestEnumPriority::class, showAllOption: false);

        $this->assertSame(TestEnumPriority::class, $options->enumClass);
        $this->assertFalse($options->showAllOption);
    }

    /**
     * @test
     */
    public function isReadonly(): void
    {
        $reflection = new \ReflectionClass(FilterEnumOptions::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    /**
     * @test
     */
    public function valuesCanBeEmptyArray(): void
    {
        $options = FilterEnumOptions::fromValues([]);

        $this->assertSame([], $options->values);
    }

    /**
     * @test
     */
    public function bothValuesAndEnumClassCanBeSet(): void
    {
        // Although typically you'd use one or the other,
        // the class allows both to be set
        $options = new FilterEnumOptions(
            values: ['manual', 'options'],
            enumClass: 'App\\Enum\\Status'
        );

        $this->assertSame(['manual', 'options'], $options->values);
        $this->assertSame('App\\Enum\\Status', $options->enumClass);
    }
}
