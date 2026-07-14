<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service\Filter;

use Doctrine\ORM\QueryBuilder;
use Kachnitel\AdminBundle\Service\Filter\EnumFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EnumFilterTest extends TestCase
{
    #[Test]
    public function constructorInitializesNameAndLabel(): void
    {
        $filter = new EnumFilter('status', 'Status', 'status', TestStatus::class);

        $this->assertSame('status', $filter->getName());
        $this->assertSame('Status', $filter->getLabel());
    }

    #[Test]
    public function constructorSetsTypeToSelect(): void
    {
        $filter = new EnumFilter('status', 'Status', 'status', TestStatus::class);

        $this->assertSame('select', $filter->getType());
    }

    #[Test]
    public function constructorExtractsOptionsFromBackedEnum(): void
    {
        $filter = new EnumFilter('status', 'Status', 'status', TestStatus::class);
        $options = $filter->getOptions();

        $this->assertArrayHasKey('active', $options);
        $this->assertArrayHasKey('inactive', $options);
        $this->assertArrayHasKey('pending', $options);
    }

    #[Test]
    public function constructorUsesEnumNameAsOptionValue(): void
    {
        $filter = new EnumFilter('status', 'Status', 'status', TestStatus::class);
        $options = $filter->getOptions();

        // For backed enums, key is the backing value, value is the name
        $this->assertSame('Active', $options['active']);
        $this->assertSame('Inactive', $options['inactive']);
        $this->assertSame('Pending', $options['pending']);
    }

    #[Test]
    public function constructorUsesDisplayValueMethodIfAvailable(): void
    {
        $filter = new EnumFilter('status', 'Status', 'status', TestStatusWithDisplayValue::class);
        $options = $filter->getOptions();

        $this->assertSame('Active Status', $options['active']);
        $this->assertSame('Inactive Status', $options['inactive']);
    }

    #[Test]
    public function constructorHandlesUnitEnum(): void
    {
        $filter = new EnumFilter('priority', 'Priority', 'priority', TestPriority::class);
        $options = $filter->getOptions();

        // For unit enums, key is the name (no backing value)
        $this->assertArrayHasKey('Low', $options);
        $this->assertArrayHasKey('Medium', $options);
        $this->assertArrayHasKey('High', $options);
    }

    #[Test]
    public function constructorReturnsEmptyOptionsForNonExistentEnum(): void
    {
        $filter = new EnumFilter('field', 'Label', 'field', 'NonExistent\\Enum\\Class');
        $options = $filter->getOptions();

        $this->assertEmpty($options);
    }

    #[Test]
    public function applyAddsWhereClause(): void
    {
        $filter = new EnumFilter('status', 'Status', 'status', TestStatus::class);

        /** @var QueryBuilder&MockObject $qb */
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.status = :status')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('status', 'active')
            ->willReturnSelf();

        $filter->apply($qb, 'active');
    }

    #[Test]
    public function applyHandlesDottedFieldNames(): void
    {
        $filter = new EnumFilter('category_status', 'Category Status', 'category.status', TestStatus::class);

        /** @var QueryBuilder&MockObject $qb */
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.category.status = :category_status')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('category_status', 'pending')
            ->willReturnSelf();

        $filter->apply($qb, 'pending');
    }

    #[Test]
    public function applyWithIntegerBackedEnum(): void
    {
        $filter = new EnumFilter('level', 'Level', 'level', TestLevel::class);

        /** @var QueryBuilder&MockObject $qb */
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.level = :level')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('level', 1)
            ->willReturnSelf();

        $filter->apply($qb, 1);
    }
}

/**
 * Test enum for string-backed enum
 */
enum TestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}

/**
 * Test enum with displayValue method
 */
enum TestStatusWithDisplayValue: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function displayValue(): string
    {
        return match ($this) {
            self::Active => 'Active Status',
            self::Inactive => 'Inactive Status',
        };
    }
}

/**
 * Test unit enum (not backed)
 */
enum TestPriority
{
    case Low;
    case Medium;
    case High;
}

/**
 * Test integer-backed enum
 */
enum TestLevel: int
{
    case Beginner = 1;
    case Intermediate = 2;
    case Advanced = 3;
}
