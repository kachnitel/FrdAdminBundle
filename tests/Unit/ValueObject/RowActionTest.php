<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\ValueObject;

use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\TestCase;

/**
 * @group row-actions
 */
class RowActionTest extends TestCase
{
    /** @test */
    public function itCreatesActionWithRequiredFieldsOnly(): void
    {
        $action = new RowAction(name: 'show', label: 'Show');

        $this->assertSame('show', $action->name);
        $this->assertSame('Show', $action->label);
        $this->assertNull($action->icon);
        $this->assertNull($action->route);
        $this->assertSame([], $action->routeParams);
        $this->assertNull($action->url);
        $this->assertNull($action->permission);
        $this->assertNull($action->voterAttribute);
        $this->assertNull($action->condition);
        $this->assertNull($action->cssClass);
        $this->assertNull($action->confirmMessage);
        $this->assertFalse($action->openInNewTab);
        $this->assertSame(100, $action->priority);
        $this->assertNull($action->method);
        $this->assertNull($action->template);
    }

    /** @test */
    public function itAcceptsStringExpressionCondition(): void
    {
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.status == "pending"',
        );

        $this->assertSame('entity.status == "pending"', $action->condition);
        $this->assertFalse($action->hasDiCondition());
    }

    /** @test */
    public function itAcceptsDiTupleCondition(): void
    {
        /** @var array{class-string, string} $condition */
        $condition = [ApprovalService::class, 'canApprove'];

        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: $condition,
        );

        $this->assertSame($condition, $action->condition);
        $this->assertTrue($action->hasDiCondition());
    }

    /** @test */
    public function hasDiConditionReturnsFalseForStringCondition(): void
    {
        $action = new RowAction(name: 'show', label: 'Show', condition: '!entity.archived');
        $this->assertFalse($action->hasDiCondition());
    }

    /** @test */
    public function hasDiConditionReturnsFalseWhenNoCondition(): void
    {
        $action = new RowAction(name: 'show', label: 'Show');
        $this->assertFalse($action->hasDiCondition());
    }

    /** @test */
    public function withCreatesModifiedCopy(): void
    {
        $original = new RowAction(name: 'show', label: 'Show', icon: '👀');
        $modified = $original->with(['label' => 'View', 'icon' => '🔍']);

        $this->assertSame('show', $modified->name);
        $this->assertSame('View', $modified->label);
        $this->assertSame('🔍', $modified->icon);

        // Original is unchanged
        $this->assertSame('Show', $original->label);
        $this->assertSame('👀', $original->icon);
    }

    /** @test */
    public function withCanExplicitlySetNull(): void
    {
        $original = new RowAction(name: 'show', label: 'Show', icon: '👀');
        $modified = $original->with(['icon' => null]);

        $this->assertNull($modified->icon);
        $this->assertSame('👀', $original->icon);
    }

    /** @test */
    public function withCanReplaceDiConditionWithExpression(): void
    {
        /** @var array{class-string, string} $condition */
        $condition = [ApprovalService::class, 'canApprove'];
        $original = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: $condition,
        );

        $modified = $original->with(['condition' => 'entity.status == "pending"']);

        $this->assertSame('entity.status == "pending"', $modified->condition);
        $this->assertFalse($modified->hasDiCondition());
    }

    /** @test */
    public function mergeKeepsOriginalValuesWhenOverrideHasNulls(): void
    {
        $original = new RowAction(
            name: 'show',
            label: 'Show',
            icon: '👀',
            priority: 10,
            voterAttribute: 'ADMIN_SHOW',
        );

        $override = new RowAction(
            name: 'show',
            label: 'View Details',
            icon: '🔍',
            condition: 'entity.active',
        );

        $merged = $original->merge($override);

        $this->assertSame('show', $merged->name);     // always from original
        $this->assertSame('View Details', $merged->label);
        $this->assertSame('🔍', $merged->icon);
        $this->assertSame('entity.active', $merged->condition);
        $this->assertSame(10, $merged->priority);          // kept from original (override has default 100)
        $this->assertSame('ADMIN_SHOW', $merged->voterAttribute); // kept from original
    }

    /** @test */
    public function mergePropagatesDiTupleConditionFromOverride(): void
    {
        $original = new RowAction(name: 'approve', label: 'Approve', priority: 10);
        /** @var array{class-string, string} $condition */
        $condition = [ApprovalService::class, 'canApprove'];
        $override = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: $condition,
        );

        $merged = $original->merge($override);

        $this->assertSame([ApprovalService::class, 'canApprove'], $merged->condition);
        $this->assertTrue($merged->hasDiCondition());
    }

    /** @test */
    public function hasRouteReturnsTrueWhenRouteIsSet(): void
    {
        $action = new RowAction(name: 'edit', label: 'Edit', route: 'app_edit');
        $this->assertTrue($action->hasRoute());
    }

    /** @test */
    public function hasRouteReturnsFalseWhenNoRoute(): void
    {
        $action = new RowAction(name: 'edit', label: 'Edit');
        $this->assertFalse($action->hasRoute());
    }

    /** @test */
    public function requiresConfirmationReturnsTrueWhenMessageSet(): void
    {
        $action = new RowAction(name: 'delete', label: 'Delete', confirmMessage: 'Are you sure?');
        $this->assertTrue($action->requiresConfirmation());
    }

    /** @test */
    public function requiresConfirmationReturnsFalseWhenNoMessage(): void
    {
        $action = new RowAction(name: 'edit', label: 'Edit');
        $this->assertFalse($action->requiresConfirmation());
    }

    /** @test */
    public function isFormActionReturnsTrueWhenMethodIsSet(): void
    {
        $action = new RowAction(name: 'delete', label: 'Delete', method: 'DELETE');
        $this->assertTrue($action->isFormAction());
    }

    /** @test */
    public function isFormActionReturnsFalseWhenNoMethod(): void
    {
        $action = new RowAction(name: 'show', label: 'Show');
        $this->assertFalse($action->isFormAction());
    }
}

class ApprovalService
{
    public function canApprove(): bool
    {
        return true;
    }
}
