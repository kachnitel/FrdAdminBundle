<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\AdminBundle\DataSource\DoctrineFilterConverter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\DataSource\DoctrineFilterConverter
 */
class DoctrineFilterConverterTest extends TestCase
{
    private DoctrineFilterConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new DoctrineFilterConverter();
    }

    /** @test */
    public function convertsBasicTextFilter(): void
    {
        $config = ['type' => 'text', 'operator' => 'LIKE', 'label' => 'Name', 'enabled' => true];

        $filter = $this->converter->convert('name', $config);

        $this->assertSame('name', $filter->name);
        $this->assertSame('text', $filter->type);
        $this->assertSame('LIKE', $filter->operator);
        $this->assertSame('Name', $filter->label);
        $this->assertTrue($filter->enabled);
    }

    /** @test */
    public function usesDefaultsWhenConfigKeysAbsent(): void
    {
        $filter = $this->converter->convert('status', []);

        $this->assertSame('text', $filter->type);
        $this->assertSame('=', $filter->operator);
        $this->assertSame(999, $filter->priority);
        $this->assertTrue($filter->enabled);
        $this->assertFalse($filter->excludeFromGlobalSearch);
    }

    /** @test */
    public function returnsNullEnumOptionsWhenNoEnumKeysPresent(): void
    {
        $config = ['type' => 'text'];

        $filter = $this->converter->convert('name', $config);

        $this->assertNull($filter->getOptions());
        $this->assertNull($filter->getEnumClass());
    }

    /** @test */
    public function setsEnumOptionsFromEnumClass(): void
    {
        $config = [
            'type'      => 'enum',
            'enumClass' => 'App\\Enum\\Status',
            'operator'  => '=',
        ];

        $filter = $this->converter->convert('status', $config);

        $this->assertSame('App\\Enum\\Status', $filter->getEnumClass());
    }

    /** @test */
    public function setsEnumOptionsFromValuesArray(): void
    {
        $config = [
            'type'    => 'enum',
            'options' => ['active', 'inactive'],
        ];

        $filter = $this->converter->convert('status', $config);

        $this->assertSame(['active', 'inactive'], $filter->getOptions());
    }

    /** @test */
    public function propagatesMultipleFlag(): void
    {
        $config = ['type' => 'enum', 'options' => ['a', 'b'], 'multiple' => true];

        $filter = $this->converter->convert('status', $config);

        $this->assertTrue($filter->isMultiple());
    }

    /** @test */
    public function propagatesShowAllOptionFalse(): void
    {
        $config = ['type' => 'enum', 'options' => ['a'], 'showAllOption' => false];

        $filter = $this->converter->convert('status', $config);

        $this->assertFalse($filter->getShowAllOption());
    }

    /** @test */
    public function propagatesExcludeFromGlobalSearch(): void
    {
        $config = ['type' => 'collection', 'excludeFromGlobalSearch' => true];

        $filter = $this->converter->convert('tags', $config);

        $this->assertTrue($filter->excludeFromGlobalSearch);
    }

    /** @test */
    public function propagatesSearchFields(): void
    {
        $config = ['type' => 'relation', 'searchFields' => ['name', 'email']];

        $filter = $this->converter->convert('customer', $config);

        $this->assertSame(['name', 'email'], $filter->searchFields);
    }

    /** @test */
    public function propagatesPriority(): void
    {
        $config = ['priority' => 5];

        $filter = $this->converter->convert('name', $config);

        $this->assertSame(5, $filter->priority);
    }

    /** @test */
    public function propagatesTargetClass(): void
    {
        $config = ['type' => 'relation', 'targetClass' => 'App\\Entity\\User'];

        $filter = $this->converter->convert('user', $config);

        $array = $filter->toArray();
        // targetClass is stored but not directly exposed; verify through toArray or construction
        $this->assertSame('App\\Entity\\User', $array['targetClass'] ?? null);
    }

    /** @test */
    public function showAllOptionKeyAloneTriggersEnumOptions(): void
    {
        // showAllOption without values/enumClass should still produce FilterEnumOptions
        $config = ['type' => 'boolean', 'showAllOption' => true];

        $filter = $this->converter->convert('active', $config);

        $this->assertTrue($filter->getShowAllOption());
    }
}
