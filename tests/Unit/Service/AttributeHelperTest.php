<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Service\AttributeHelper;
use PHPUnit\Framework\TestCase;

class AttributeHelperTest extends TestCase
{
    private AttributeHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new AttributeHelper();
    }

    /**
     * @test
     */
    public function getAttributeReturnsAttributeFromClass(): void
    {
        $result = $this->helper->getAttribute(TestEntityWithAttribute::class, TestAttribute::class);

        $this->assertInstanceOf(TestAttribute::class, $result);
        $this->assertSame('test_value', $result->value);
    }

    /**
     * @test
     */
    public function getAttributeReturnsAttributeFromObject(): void
    {
        $entity = new TestEntityWithAttribute();
        $result = $this->helper->getAttribute($entity, TestAttribute::class);

        $this->assertInstanceOf(TestAttribute::class, $result);
        $this->assertSame('test_value', $result->value);
    }

    /**
     * @test
     */
    public function getAttributeReturnsNullWhenAttributeNotPresent(): void
    {
        $result = $this->helper->getAttribute(TestEntityWithoutAttribute::class, TestAttribute::class);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function getAttributeReturnsNullForNonExistentClass(): void
    {
        $result = $this->helper->getAttribute('NonExistent\\Class\\Name', TestAttribute::class);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function getPropertyAttributeReturnsAttributeFromProperty(): void
    {
        $result = $this->helper->getPropertyAttribute(
            TestEntityWithPropertyAttribute::class,
            'name',
            TestAttribute::class
        );

        $this->assertInstanceOf(TestAttribute::class, $result);
        $this->assertSame('property_value', $result->value);
    }

    /**
     * @test
     */
    public function getPropertyAttributeReturnsAttributeFromObjectProperty(): void
    {
        $entity = new TestEntityWithPropertyAttribute();
        $result = $this->helper->getPropertyAttribute($entity, 'name', TestAttribute::class);

        $this->assertInstanceOf(TestAttribute::class, $result);
        $this->assertSame('property_value', $result->value);
    }

    /**
     * @test
     */
    public function getPropertyAttributeReturnsNullWhenAttributeNotPresent(): void
    {
        $result = $this->helper->getPropertyAttribute(
            TestEntityWithPropertyAttribute::class,
            'description',
            TestAttribute::class
        );

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function getPropertyAttributeReturnsNullForNonExistentClass(): void
    {
        $result = $this->helper->getPropertyAttribute(
            'NonExistent\\Class\\Name',
            'property',
            TestAttribute::class
        );

        $this->assertNull($result);
    }
}

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class TestAttribute
{
    public function __construct(public string $value = '')
    {
    }
}

#[TestAttribute(value: 'test_value')]
class TestEntityWithAttribute
{
}

class TestEntityWithoutAttribute
{
}

class TestEntityWithPropertyAttribute
{
    #[TestAttribute(value: 'property_value')]
    public string $name = '';

    public string $description = '';
}
