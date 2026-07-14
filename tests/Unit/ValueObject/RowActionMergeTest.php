<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\ValueObject;

use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\ValueObject\RowAction::merge
 * @group row-actions
 */
#[CoversClass(RowAction::class)]
final class RowActionMergeTest extends TestCase
{
    // ── contexts OR logic ──────────────────────────────────────────────────────

    #[Test]
    public function mergePreservesOriginalContextsWhenOtherHasNone(): void
    {
        $original = new RowAction(
            name: 'edit',
            label: 'Edit',
            contexts: [RowAction::CONTEXT_INDEX],
        );

        $other = new RowAction(
            name: 'edit',
            label: 'Edit (updated)',
            contexts: [],
        );

        $merged = $original->merge($other);

        $this->assertSame([RowAction::CONTEXT_INDEX], $merged->contexts);
    }

    #[Test]
    public function mergeUsesOtherContextsWhenOtherHasNonEmpty(): void
    {
        $original = new RowAction(
            name: 'edit',
            label: 'Edit',
            contexts: [RowAction::CONTEXT_INDEX],
        );

        $other = new RowAction(
            name: 'edit',
            label: 'Edit (updated)',
            contexts: [RowAction::CONTEXT_SHOW, RowAction::CONTEXT_EDIT],
        );

        $merged = $original->merge($other);

        $this->assertSame([RowAction::CONTEXT_SHOW, RowAction::CONTEXT_EDIT], $merged->contexts);
    }

    #[Test]
    public function mergeUsesOtherContextsWhenBothHaveNonEmptyContexts(): void
    {
        $original = new RowAction(
            name: 'edit',
            label: 'Edit',
            contexts: [RowAction::CONTEXT_INDEX],
        );

        $other = new RowAction(
            name: 'edit',
            label: 'Edit via component',
            contexts: [RowAction::CONTEXT_SHOW],
        );

        $merged = $original->merge($other);

        // Other's contexts win when non-empty — doc says "prefer $other->contexts when non-empty"
        $this->assertSame([RowAction::CONTEXT_SHOW], $merged->contexts);
    }

    #[Test]
    public function mergeResultPreservesOriginalName(): void
    {
        $original = new RowAction(name: 'edit', label: 'Edit');
        $other    = new RowAction(name: 'edit', label: 'Different label');

        $merged = $original->merge($other);

        $this->assertSame('edit', $merged->name);
        $this->assertSame('Different label', $merged->label);
    }

    // ── DEFAULT_PRIORITY tie-break ─────────────────────────────────────────────

    #[Test]
    public function mergeKeepsOriginalPriorityWhenOtherHasDefaultPriority(): void
    {
        $original = new RowAction(
            name: 'edit',
            label: 'Edit',
            priority: 20, // explicitly set, non-default
        );

        $other = new RowAction(
            name: 'edit',
            label: 'Edit',
            priority: RowAction::DEFAULT_PRIORITY, // "not set" sentinel
        );

        $merged = $original->merge($other);

        $this->assertSame(20, $merged->priority, 'When other uses DEFAULT_PRIORITY, original priority must be preserved.');
    }

    #[Test]
    public function mergeUsesOtherPriorityWhenExplicitlySet(): void
    {
        $original = new RowAction(
            name: 'edit',
            label: 'Edit',
            priority: 20,
        );

        $other = new RowAction(
            name: 'edit',
            label: 'Edit',
            priority: 5, // explicitly lower priority
        );

        $merged = $original->merge($other);

        $this->assertSame(5, $merged->priority);
    }

    #[Test]
    public function mergeBothDefaultPriorityKeepsOriginal(): void
    {
        $original = new RowAction(
            name: 'edit',
            label: 'Edit',
            priority: RowAction::DEFAULT_PRIORITY,
        );

        $other = new RowAction(
            name: 'edit',
            label: 'Edit',
            priority: RowAction::DEFAULT_PRIORITY,
        );

        $merged = $original->merge($other);

        // Both at DEFAULT — original priority (100) preserved
        $this->assertSame(RowAction::DEFAULT_PRIORITY, $merged->priority);
    }

    #[Test]
    public function mergeBothHaveExplicitPriorityUsesOther(): void
    {
        $original = new RowAction(name: 'edit', label: 'Edit', priority: 50);
        $other    = new RowAction(name: 'edit', label: 'Edit', priority: 99);

        $merged = $original->merge($other);

        $this->assertSame(99, $merged->priority);
    }

    // ── null / non-null property merging ──────────────────────────────────────

    #[Test]
    public function mergePreservesOriginalIconWhenOtherHasNone(): void
    {
        $original = new RowAction(name: 'edit', label: 'Edit', icon: '✏️');
        $other    = new RowAction(name: 'edit', label: 'Edit', icon: null);

        $merged = $original->merge($other);

        $this->assertSame('✏️', $merged->icon);
    }

    #[Test]
    public function mergeOverridesIconWithOtherWhenSet(): void
    {
        $original = new RowAction(name: 'edit', label: 'Edit', icon: '✏️');
        $other    = new RowAction(name: 'edit', label: 'Edit', icon: '🖊');

        $merged = $original->merge($other);

        $this->assertSame('🖊', $merged->icon);
    }

    #[Test]
    public function mergeUsesOtherConditionWhenSet(): void
    {
        $original = new RowAction(name: 'edit', label: 'Edit', condition: 'entity.active');
        $other    = new RowAction(name: 'edit', label: 'Edit', condition: '!entity.locked');

        $merged = $original->merge($other);

        $this->assertSame('!entity.locked', $merged->condition);
    }

    #[Test]
    public function mergePreservesOriginalConditionWhenOtherHasNone(): void
    {
        $original = new RowAction(name: 'edit', label: 'Edit', condition: 'entity.active');
        $other    = new RowAction(name: 'edit', label: 'Edit', condition: null);

        $merged = $original->merge($other);

        $this->assertSame('entity.active', $merged->condition);
    }

    #[Test]
    public function mergeUsesOtherRouteParamsWhenNonEmpty(): void
    {
        $original = new RowAction(name: 'edit', label: 'Edit', routeParams: ['workspace' => 'main']);
        $other    = new RowAction(name: 'edit', label: 'Edit', routeParams: ['workspace' => 'secondary']);

        $merged = $original->merge($other);

        $this->assertSame(['workspace' => 'secondary'], $merged->routeParams);
    }

    #[Test]
    public function mergeKeepsOriginalRouteParamsWhenOtherEmpty(): void
    {
        $original = new RowAction(name: 'edit', label: 'Edit', routeParams: ['workspace' => 'main']);
        $other    = new RowAction(name: 'edit', label: 'Edit', routeParams: []);

        $merged = $original->merge($other);

        $this->assertSame(['workspace' => 'main'], $merged->routeParams);
    }

    // ── supportsContext() ─────────────────────────────────────────────────────

    #[Test]
    public function supportsContextReturnsTrueForEmptyContexts(): void
    {
        $action = new RowAction(name: 'show', label: 'Show', contexts: []);

        $this->assertTrue($action->supportsContext(RowAction::CONTEXT_INDEX));
        $this->assertTrue($action->supportsContext(RowAction::CONTEXT_SHOW));
        $this->assertTrue($action->supportsContext(RowAction::CONTEXT_EDIT));
    }

    #[Test]
    public function supportsContextReturnsTrueOnlyForDeclaredContext(): void
    {
        $action = new RowAction(
            name: 'inline-edit',
            label: 'Edit',
            contexts: [RowAction::CONTEXT_INDEX],
        );

        $this->assertTrue($action->supportsContext(RowAction::CONTEXT_INDEX));
        $this->assertFalse($action->supportsContext(RowAction::CONTEXT_SHOW));
        $this->assertFalse($action->supportsContext(RowAction::CONTEXT_EDIT));
    }

    #[Test]
    public function supportsContextReturnsTrueForMultipleDeclaredContexts(): void
    {
        $action = new RowAction(
            name: 'promote',
            label: 'Promote',
            contexts: [RowAction::CONTEXT_SHOW, RowAction::CONTEXT_EDIT],
        );

        $this->assertFalse($action->supportsContext(RowAction::CONTEXT_INDEX));
        $this->assertTrue($action->supportsContext(RowAction::CONTEXT_SHOW));
        $this->assertTrue($action->supportsContext(RowAction::CONTEXT_EDIT));
    }
}
