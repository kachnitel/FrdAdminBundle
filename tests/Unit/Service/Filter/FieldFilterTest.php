<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service\Filter;

use Kachnitel\AdminBundle\Service\Filter\FieldFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FieldFilterTest extends TestCase
{
    #[Test]
    public function constructorInitializesName(): void
    {
        $filter = new FieldFilter('product_name', 'Product Name', 'name');
        $this->assertSame('product_name', $filter->getName());
    }

    #[Test]
    public function constructorInitializesLabel(): void
    {
        $filter = new FieldFilter('product_name', 'Product Name', 'name');
        $this->assertSame('Product Name', $filter->getLabel());
    }

    #[Test]
    public function constructorInitializesType(): void
    {
        $filter = new FieldFilter('name', 'Name', 'name', '=', 'text');
        $this->assertSame('text', $filter->getType());
    }

    #[Test]
    public function defaultTypeIsText(): void
    {
        $filter = new FieldFilter('name', 'Name', 'name');
        $this->assertSame('text', $filter->getType());
    }

    #[Test]
    public function defaultOperatorIsEquals(): void
    {
        $filter = new FieldFilter('status', 'Status', 'status');
        $this->assertSame('status', $filter->getName());
    }

    #[Test]
    public function getOptionsReturnsProvidedOptions(): void
    {
        $options = ['placeholder' => 'Search', 'class' => 'form-control'];
        $filter = new FieldFilter('name', 'Name', 'name', '=', 'text', $options);
        $this->assertSame($options, $filter->getOptions());
    }

    #[Test]
    public function getOptionsReturnsEmptyArrayByDefault(): void
    {
        $filter = new FieldFilter('name', 'Name', 'name');
        $this->assertEmpty($filter->getOptions());
    }

    #[Test]
    public function supportsLikeOperator(): void
    {
        $filter = new FieldFilter('name', 'Name', 'name', 'LIKE');
        $this->assertSame('name', $filter->getName());
    }

    #[Test]
    public function supportsInOperator(): void
    {
        $filter = new FieldFilter('status', 'Status', 'status', 'IN');
        $this->assertSame('status', $filter->getName());
    }

    #[Test]
    public function supportsGreaterThanOperator(): void
    {
        $filter = new FieldFilter('price', 'Price', 'price', '>');
        $this->assertSame('price', $filter->getName());
    }

    #[Test]
    public function supportsLessThanOperator(): void
    {
        $filter = new FieldFilter('price', 'Price', 'price', '<');
        $this->assertSame('price', $filter->getName());
    }

    #[Test]
    public function supportsGreaterThanOrEqualOperator(): void
    {
        $filter = new FieldFilter('quantity', 'Quantity', 'quantity', '>=');
        $this->assertSame('quantity', $filter->getName());
    }

    #[Test]
    public function supportsLessThanOrEqualOperator(): void
    {
        $filter = new FieldFilter('quantity', 'Quantity', 'quantity', '<=');
        $this->assertSame('quantity', $filter->getName());
    }

    #[Test]
    public function handlesFieldNamesWithDots(): void
    {
        // Fields with dots represent relations (e.g., relation.field)
        $filter = new FieldFilter('related.name', 'Related Name', 'related.name');
        $this->assertSame('related.name', $filter->getName());
    }

    #[Test]
    public function canCreateMultipleFilters(): void
    {
        $filters = [
            new FieldFilter('name', 'Name', 'name', 'LIKE'),
            new FieldFilter('status', 'Status', 'status', 'IN'),
            new FieldFilter('price', 'Price', 'price', '>'),
        ];

        // @phpstan-ignore-next-line method.alreadyNarrowedType
        $this->assertCount(3, $filters);
        $this->assertSame('name', $filters[0]->getName());
        $this->assertSame('status', $filters[1]->getName());
        $this->assertSame('price', $filters[2]->getName());
    }

    #[Test]
    public function optionsCanIncludeMultipleEntries(): void
    {
        $options = [
            'placeholder' => 'Enter value',
            'class' => 'form-control',
            'pattern' => '[0-9]+',
            'data-attr' => 'value',
        ];
        $filter = new FieldFilter('field', 'Label', 'field', '=', 'text', $options);
        $this->assertSame($options, $filter->getOptions());
    }

    #[Test]
    public function typeCanBeCustom(): void
    {
        $filter = new FieldFilter('count', 'Count', 'count', '=', 'integer');
        $this->assertSame('integer', $filter->getType());
    }

    #[Test]
    public function nameAndLabelCanBeDifferent(): void
    {
        $filter = new FieldFilter('user_name', 'Full Name', 'name');
        $this->assertSame('user_name', $filter->getName());
        $this->assertSame('Full Name', $filter->getLabel());
    }

    #[Test]
    public function applyWithDefaultEqualsOperator(): void
    {
        $filter = new FieldFilter('status', 'Status', 'status');

        $qb = $this->createQueryBuilderMock();
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
    public function applyWithLikeOperator(): void
    {
        $filter = new FieldFilter('name', 'Name', 'name', 'LIKE');

        $comparison = $this->createStub(\Doctrine\ORM\Query\Expr\Comparison::class);

        $expr = $this->createMock(\Doctrine\ORM\Query\Expr::class);
        $expr->expects($this->once())
            ->method('like')
            ->with('e.name', ':name')
            ->willReturn($comparison);

        $qb = $this->createQueryBuilderMock();
        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with($comparison)
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('name', '%test%')
            ->willReturnSelf();

        $filter->apply($qb, 'test');
    }

    #[Test]
    public function applyWithInOperatorAndArray(): void
    {
        $filter = new FieldFilter('status', 'Status', 'status', 'IN');

        $func = $this->createStub(\Doctrine\ORM\Query\Expr\Func::class);

        $expr = $this->createMock(\Doctrine\ORM\Query\Expr::class);
        $expr->expects($this->once())
            ->method('in')
            ->with('e.status', ':status')
            ->willReturn($func);

        $qb = $this->createQueryBuilderMock();
        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with($func)
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('status', ['active', 'pending'])
            ->willReturnSelf();

        $filter->apply($qb, ['active', 'pending']);
    }

    #[Test]
    public function applyWithInOperatorAndScalar(): void
    {
        $filter = new FieldFilter('status', 'Status', 'status', 'IN');

        $func = $this->createStub(\Doctrine\ORM\Query\Expr\Func::class);

        $expr = $this->createMock(\Doctrine\ORM\Query\Expr::class);
        $expr->expects($this->once())
            ->method('in')
            ->with('e.status', ':status')
            ->willReturn($func);

        $qb = $this->createQueryBuilderMock();
        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with($func)
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('status', ['active'])
            ->willReturnSelf();

        $filter->apply($qb, 'active');
    }

    #[Test]
    public function applyWithGreaterThanOrEqualOperator(): void
    {
        $filter = new FieldFilter('price', 'Min Price', 'price', '>=');

        $qb = $this->createQueryBuilderMock();
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.price >= :price')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('price', 100)
            ->willReturnSelf();

        $filter->apply($qb, 100);
    }

    #[Test]
    public function applyWithLessThanOrEqualOperator(): void
    {
        $filter = new FieldFilter('price', 'Max Price', 'price', '<=');

        $qb = $this->createQueryBuilderMock();
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.price <= :price')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('price', 500)
            ->willReturnSelf();

        $filter->apply($qb, 500);
    }

    #[Test]
    public function applyWithGreaterThanOperator(): void
    {
        $filter = new FieldFilter('quantity', 'Quantity', 'quantity', '>');

        $qb = $this->createQueryBuilderMock();
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.quantity > :quantity')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('quantity', 10)
            ->willReturnSelf();

        $filter->apply($qb, 10);
    }

    #[Test]
    public function applyWithLessThanOperator(): void
    {
        $filter = new FieldFilter('quantity', 'Quantity', 'quantity', '<');

        $qb = $this->createQueryBuilderMock();
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.quantity < :quantity')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('quantity', 50)
            ->willReturnSelf();

        $filter->apply($qb, 50);
    }

    #[Test]
    public function applyWithDottedFieldNameConvertsToUnderscoreInParam(): void
    {
        $filter = new FieldFilter('category_name', 'Category', 'category.name');

        $qb = $this->createQueryBuilderMock();
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.category.name = :category_name')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('category_name', 'Electronics')
            ->willReturnSelf();

        $filter->apply($qb, 'Electronics');
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createQueryBuilderMock(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createMock(\Doctrine\ORM\QueryBuilder::class);
    }
}
