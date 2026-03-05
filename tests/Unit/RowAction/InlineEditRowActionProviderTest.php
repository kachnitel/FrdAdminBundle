<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\RowAction;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\RowAction\DefaultRowActionProvider;
use Kachnitel\AdminBundle\RowAction\InlineEditRowActionProvider;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for InlineEditRowActionProvider.
 *
 * @group inline-edit
 */
class InlineEditRowActionProviderTest extends TestCase
{
    /** @var AttributeHelper&MockObject */
    private AttributeHelper $attributeHelper;

    private InlineEditRowActionProvider $provider;

    protected function setUp(): void
    {
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->provider = new InlineEditRowActionProvider($this->attributeHelper);
    }

    // ── supports() — feature flag ─────────────────────────────────────────────

    public function testSupportsReturnsFalseByDefault(): void
    {
        // Admin attribute with enableInlineEdit: false (the default)
        $admin = new Admin(label: 'Items');
        $this->attributeHelper->method('getAttribute')->willReturn($admin);

        $this->assertFalse($this->provider->supports(\stdClass::class));
    }

    public function testSupportsReturnsTrueWhenEnableInlineEditIsTrue(): void
    {
        $admin = new Admin(enableInlineEdit: true);
        $this->attributeHelper->method('getAttribute')->willReturn($admin);

        $this->assertTrue($this->provider->supports(\stdClass::class));
    }

    public function testSupportsReturnsFalseWhenNoAdminAttribute(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(null);

        $this->assertFalse($this->provider->supports(\stdClass::class));
    }

    public function testSupportsReturnsFalseForNonExistentClass(): void
    {
        // @phpstan-ignore-next-line argument.type
        $this->assertFalse($this->provider->supports('App\\Entity\\DoesNotExist'));
    }

    // ── getPriority() ────────────────────────────────────────────────────────

    public function testPriorityIsFifteen(): void
    {
        $this->assertSame(15, $this->provider->getPriority());
    }

    public function testPriorityIsHigherThanDefaultRowActionProvider(): void
    {
        $default = new DefaultRowActionProvider();
        $this->assertGreaterThan($default->getPriority(), $this->provider->getPriority());
    }

    public function testPriorityIsLowerThanAttributeProviderConvention(): void
    {
        $this->assertLessThan(50, $this->provider->getPriority());
    }

    // ── getActions() ─────────────────────────────────────────────────────────

    public function testGetActionsReturnsNonEmptyArray(): void
    {
        $actions = $this->provider->getActions(\stdClass::class);

        $this->assertNotEmpty($actions);
    }

    public function testInlineEditActionUsesLiveComponent(): void
    {
        $actions = $this->provider->getActions(\stdClass::class);

        foreach ($actions as $action) {
            $this->assertTrue($action->isComponentAction(), sprintf(
                'Action "%s" must be a component action (liveComponent must not be null).',
                $action->name,
            ));
            $this->assertSame('K:Admin:RowAction:InlineEdit', $action->liveComponent);
        }
    }

    public function testInlineEditActionHasNoTemplate(): void
    {
        // Template and liveComponent are mutually exclusive rendering modes.
        // The inline edit button uses liveComponent, not a custom template.
        $actions = $this->provider->getActions(\stdClass::class);

        foreach ($actions as $action) {
            $this->assertNull($action->template, sprintf(
                'Action "%s" must not declare a template — it uses liveComponent.',
                $action->name,
            ));
        }
    }

    public function testInlineEditActionHasDefaultPriority(): void
    {
        // Priority is intentionally omitted (DEFAULT_PRIORITY = 100) so that
        // RowAction::merge() preserves DefaultRowActionProvider's explicit priority of 20.
        $actions = $this->provider->getActions(\stdClass::class);

        foreach ($actions as $action) {
            $this->assertSame(RowAction::DEFAULT_PRIORITY, $action->priority, sprintf(
                'Action "%s" must use DEFAULT_PRIORITY so merge() keeps the default priority of 20.',
                $action->name,
            ));
        }
    }

    public function testGetActionsIsIdempotentAcrossCalls(): void
    {
        $first  = $this->provider->getActions(\stdClass::class);
        $second = $this->provider->getActions(\stdClass::class);

        $this->assertCount(count($first), $second);

        foreach ($first as $i => $action) {
            $this->assertSame($action->name, $second[$i]->name);
        }
    }
}
