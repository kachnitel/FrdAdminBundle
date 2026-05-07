<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\ValueObject;

use Kachnitel\AdminBundle\ValueObject\BatchAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BatchAction::class)]
class BatchActionTest extends TestCase
{
    /** @test */
    public function itCreatesActionWithRequiredFieldsOnly(): void
    {
        $action = new BatchAction(name: 'delete', label: 'Delete');

        $this->assertSame('delete', $action->name);
        $this->assertSame('Delete', $action->label);
        $this->assertNull($action->icon);
        $this->assertNull($action->liveAction);
        $this->assertNull($action->route);
        $this->assertNull($action->url);
        $this->assertNull($action->permission);
        $this->assertNull($action->voterAttribute);
        $this->assertNull($action->condition);
        $this->assertNull($action->cssClass);
        $this->assertNull($action->confirmMessage);
        $this->assertFalse($action->openInNewTab);
        $this->assertSame(100, $action->priority);
    }

    /** @test */
    public function itAcceptsAllParameters(): void
    {
        $action = new BatchAction(
            name: 'archive',
            label: 'Archive',
            icon: '📦',
            liveAction: 'batchArchive',
            route: null,
            url: null,
            permission: null,
            voterAttribute: 'ADMIN_ARCHIVE',
            condition: 'entity.isActive()',
            cssClass: 'btn-warning',
            confirmMessage: 'Archive these items?',
            openInNewTab: false,
            priority: 50,
        );

        $this->assertSame('archive', $action->name);
        $this->assertSame('Archive', $action->label);
        $this->assertSame('📦', $action->icon);
        $this->assertSame('batchArchive', $action->liveAction);
        $this->assertNull($action->route);
        $this->assertNull($action->url);
        $this->assertNull($action->permission);
        $this->assertSame('ADMIN_ARCHIVE', $action->voterAttribute);
        $this->assertSame('entity.isActive()', $action->condition);
        $this->assertSame('btn-warning', $action->cssClass);
        $this->assertSame('Archive these items?', $action->confirmMessage);
        $this->assertFalse($action->openInNewTab);
        $this->assertSame(50, $action->priority);
    }

    /** @test */
    public function requiresConfirmationReturnsTrueWhenConfirmMessageIsSet(): void
    {
        $action = new BatchAction(
            name: 'delete',
            label: 'Delete',
            confirmMessage: 'Delete these items?',
        );

        $this->assertTrue($action->requiresConfirmation());
    }

    /** @test */
    public function requiresConfirmationReturnsFalseWhenConfirmMessageIsNull(): void
    {
        $action = new BatchAction(name: 'delete', label: 'Delete', confirmMessage: null);
        $this->assertFalse($action->requiresConfirmation());
    }

    /** @test */
    public function isLiveActionReturnsTrueWhenLiveActionIsSet(): void
    {
        $action = new BatchAction(name: 'delete', label: 'Delete', liveAction: 'batchDelete');
        $this->assertTrue($action->isLiveAction());
    }

    /** @test */
    public function isLiveActionReturnsFalseWhenLiveActionIsNull(): void
    {
        $action = new BatchAction(name: 'delete', label: 'Delete', liveAction: null);
        $this->assertFalse($action->isLiveAction());
    }

    /** @test */
    public function isRouteActionReturnsTrueWhenRouteIsSet(): void
    {
        $action = new BatchAction(name: 'export', label: 'Export', route: 'app_admin_export');
        $this->assertTrue($action->isRouteAction());
    }

    /** @test */
    public function isRouteActionReturnsFalseWhenRouteIsNull(): void
    {
        $action = new BatchAction(name: 'export', label: 'Export', route: null);
        $this->assertFalse($action->isRouteAction());
    }

    /** @test */
    public function isUrlActionReturnsTrueWhenUrlIsSet(): void
    {
        $action = new BatchAction(name: 'export', label: 'Export', url: 'https://example.com/export');
        $this->assertTrue($action->isUrlAction());
    }

    /** @test */
    public function isUrlActionReturnsFalseWhenUrlIsNull(): void
    {
        $action = new BatchAction(name: 'export', label: 'Export', url: null);
        $this->assertFalse($action->isUrlAction());
    }

    /** @test */
    public function hasDiConditionReturnsTrueWhenConditionIsTuple(): void
    {
        $condition = [BatchApprovalService::class, 'canArchive'];
        $action = new BatchAction(
            name: 'archive',
            label: 'Archive',
            condition: $condition,
        );

        $this->assertTrue($action->hasDiCondition());
    }

    /** @test */
    public function hasDiConditionReturnsFalseWhenConditionIsString(): void
    {
        $action = new BatchAction(
            name: 'archive',
            label: 'Archive',
            condition: 'items.count() > 0',
        );

        $this->assertFalse($action->hasDiCondition());
    }

    /** @test */
    public function hasDiConditionReturnsFalseWhenNoCondition(): void
    {
        $action = new BatchAction(name: 'delete', label: 'Delete');
        $this->assertFalse($action->hasDiCondition());
    }

    /** @test */
    public function withCreatesModifiedCopy(): void
    {
        $original = new BatchAction(
            name: 'delete',
            label: 'Delete',
            icon: '🗑️',
            liveAction: 'batchDelete',
        );

        $modified = $original->with([
            'label' => 'Delete All',
            'icon' => '💥',
            'confirmMessage' => 'Delete all items?',
        ]);

        $this->assertSame('delete', $modified->name);
        $this->assertSame('Delete All', $modified->label);
        $this->assertSame('💥', $modified->icon);
        $this->assertSame('batchDelete', $modified->liveAction);
        $this->assertSame('Delete all items?', $modified->confirmMessage);

        // Original unchanged
        $this->assertSame('Delete', $original->label);
        $this->assertSame('🗑️', $original->icon);
        $this->assertNull($original->confirmMessage);
    }

    /** @test */
    public function withCanExplicitlySetNull(): void
    {
        $original = new BatchAction(
            name: 'delete',
            label: 'Delete',
            icon: '🗑️',
            confirmMessage: 'Delete these items?',
        );

        $modified = $original->with([
            'icon' => null,
            'confirmMessage' => null,
        ]);

        $this->assertNull($modified->icon);
        $this->assertNull($modified->confirmMessage);

        // Original unchanged
        $this->assertSame('🗑️', $original->icon);
        $this->assertSame('Delete these items?', $original->confirmMessage);
    }

    /** @test */
    public function withCanModifyName(): void
    {
        $original = new BatchAction(name: 'delete', label: 'Delete');

        // The with() method allows overriding name
        $modified = $original->with(['name' => 'destroy']);

        $this->assertSame('destroy', $modified->name);
    }

    /** @test */
    public function mergePreservesOriginalPropertiesWhenOtherHasNulls(): void
    {
        $original = new BatchAction(
            name: 'delete',
            label: 'Delete',
            icon: '🗑️',
            confirmMessage: 'Delete these items?',
            priority: 100,
        );

        $other = new BatchAction(
            name: 'delete',
            label: 'Delete',
            icon: null,
            confirmMessage: null,
        );

        $merged = $original->merge($other);

        // Null values in $other don't override
        $this->assertSame('🗑️', $merged->icon);
        $this->assertSame('Delete these items?', $merged->confirmMessage);
        $this->assertSame(100, $merged->priority);
    }

    /** @test */
    public function mergeOverridesWithNonNullPropertiesFromOther(): void
    {
        $original = new BatchAction(
            name: 'delete',
            label: 'Delete',
            icon: '🗑️',
            confirmMessage: 'Delete these items?',
        );

        $other = new BatchAction(
            name: 'delete',
            label: 'Delete Selected',
            icon: '💥',
            voterAttribute: 'ADMIN_DELETE',
        );

        $merged = $original->merge($other);

        $this->assertSame('Delete Selected', $merged->label);
        $this->assertSame('💥', $merged->icon);
        $this->assertSame('ADMIN_DELETE', $merged->voterAttribute);
        // Original properties still present
        $this->assertSame('Delete these items?', $merged->confirmMessage);
    }

    /** @test */
    public function mergeKeepsOriginalPriorityWhenOtherUsesDefaultPriority(): void
    {
        $original = new BatchAction(name: 'delete', label: 'Delete', priority: 10);
        $other = new BatchAction(name: 'delete', label: 'Delete', priority: BatchAction::DEFAULT_PRIORITY);

        $merged = $original->merge($other);

        // Uses priority from original because $other uses DEFAULT_PRIORITY
        $this->assertSame(10, $merged->priority);
    }

    /** @test */
    public function mergeUsesOtherPriorityWhenOtherHasExplicitPriority(): void
    {
        $original = new BatchAction(name: 'delete', label: 'Delete', priority: 10);
        $other = new BatchAction(name: 'delete', label: 'Delete', priority: 50);

        $merged = $original->merge($other);

        // Uses priority from $other because it has an explicit priority (not DEFAULT_PRIORITY)
        $this->assertSame(50, $merged->priority);
    }
}

// Dummy class for type hints
class BatchApprovalService
{
    public function canArchive(): bool
    {
        return true;
    }
}
