<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\ValueObject;

use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('icons')]
final class ActionIconClassTest extends TestCase
{
    #[Test]
    public function rowActionDefaultsToInheritedIconClass(): void
    {
        $this->assertNull((new RowAction(name: 'a', label: 'A'))->iconClass);
    }

    #[Test]
    public function batchActionDefaultsToInheritedIconClass(): void
    {
        $this->assertNull((new BatchAction(name: 'a', label: 'A'))->iconClass);
    }

    #[Test]
    public function withOverridesAndKeepsIconClass(): void
    {
        $action = new RowAction(name: 'a', label: 'A', iconClass: 'x');

        $this->assertSame('x', $action->with(['label' => 'B'])->iconClass);
        $this->assertSame('y', $action->with(['iconClass' => 'y'])->iconClass);
        $this->assertNull($action->with(['iconClass' => null])->iconClass);
    }

    #[Test]
    public function mergeKeepsOriginalIconClassWhenOtherChangesOnlyLabel(): void
    {
        $original = new RowAction(name: 'a', label: 'A', icon: '👀', iconClass: RowAction::ICON_CLASS_NONE);
        $merged = $original->merge(new RowAction(name: 'a', label: 'B'));

        $this->assertSame('👀', $merged->icon);
        $this->assertSame(RowAction::ICON_CLASS_NONE, $merged->iconClass);
    }

    #[Test]
    public function mergeResetsIconClassWhenOtherSuppliesIconWithoutClass(): void
    {
        $original = new RowAction(name: 'a', label: 'A', icon: '👀', iconClass: RowAction::ICON_CLASS_NONE);
        $merged = $original->merge(new RowAction(name: 'a', label: 'A', icon: 'visibility'));

        $this->assertSame('visibility', $merged->icon);
        $this->assertNull($merged->iconClass, 'App icon must inherit the global class, not the bundle opt-out');
    }

    #[Test]
    public function mergeUsesOtherIconClassWhenSet(): void
    {
        $original = new RowAction(name: 'a', label: 'A', icon: '👀', iconClass: RowAction::ICON_CLASS_NONE);
        $merged = $original->merge(new RowAction(name: 'a', label: 'A', icon: 'edit', iconClass: 'mi'));

        $this->assertSame('mi', $merged->iconClass);
    }
}
