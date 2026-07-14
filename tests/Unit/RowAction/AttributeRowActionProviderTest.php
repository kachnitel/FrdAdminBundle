<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\RowAction;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;
use Kachnitel\AdminBundle\RowAction\AttributeRowActionProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithRowActions;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @group row-actions
 */
final class AttributeRowActionProviderTest extends TestCase
{
    private AttributeRowActionProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new AttributeRowActionProvider();
    }

    // -------------------------------------------------------------------------
    // supports()
    // -------------------------------------------------------------------------

    #[Test]
    public function supportsAnyExistingClass(): void
    {
        $this->assertTrue($this->provider->supports(TestEntity::class));
        $this->assertTrue($this->provider->supports(EntityWithRowActions::class));
    }

    #[Test]
    public function doesNotSupportNonExistentClass(): void
    {
        /** @var class-string $missing */
        $missing = 'App\\Entity\\DoesNotExist'; // @phpstan-ignore varTag.nativeType
        $this->assertFalse($this->provider->supports($missing));
    }

    // -------------------------------------------------------------------------
    // getActions()
    // -------------------------------------------------------------------------

    #[Test]
    public function returnsEmptyArrayForEntityWithNoAdminActionAttributes(): void
    {
        $actions = $this->provider->getActions(TestEntity::class);
        $this->assertSame([], $actions);
    }

    #[Test]
    public function readsAdminActionAttributesFromEntityClass(): void
    {
        $actions = $this->provider->getActions(EntityWithRowActions::class);

        $this->assertCount(2, $actions);
        $this->assertContainsOnlyInstancesOf(RowAction::class, $actions);
    }

    #[Test]
    public function actionNameAndLabelAreReadCorrectly(): void
    {
        $actions = $this->provider->getActions(EntityWithRowActions::class);

        $byName = [];
        foreach ($actions as $action) {
            $byName[$action->name] = $action;
        }

        $this->assertArrayHasKey('approve', $byName);
        $this->assertSame('Approve', $byName['approve']->label);

        $this->assertArrayHasKey('archive', $byName);
        $this->assertSame('Archive', $byName['archive']->label);
    }

    #[Test]
    public function actionConditionIsReadCorrectly(): void
    {
        $actions = $this->provider->getActions(EntityWithRowActions::class);

        $byName = [];
        foreach ($actions as $action) {
            $byName[$action->name] = $action;
        }

        $this->assertSame('entity.status == "pending"', $byName['approve']->condition);
        $this->assertSame('entity.status != "archived"', $byName['archive']->condition);
    }

    #[Test]
    public function actionPriorityIsReadCorrectly(): void
    {
        $actions = $this->provider->getActions(EntityWithRowActions::class);

        $byName = [];
        foreach ($actions as $action) {
            $byName[$action->name] = $action;
        }

        $this->assertSame(30, $byName['approve']->priority);
        $this->assertSame(40, $byName['archive']->priority);
    }

    #[Test]
    public function postMethodIsReadCorrectly(): void
    {
        $actions = $this->provider->getActions(EntityWithRowActions::class);

        $archive = null;
        foreach ($actions as $action) {
            if ($action->name === 'archive') {
                $archive = $action;
            }
        }

        $this->assertInstanceOf(\Kachnitel\AdminBundle\ValueObject\RowAction::class, $archive);
        $this->assertSame('POST', $archive->method);
        $this->assertSame('Archive this item?', $archive->confirmMessage);
    }

    #[Test]
    public function contextsDefaultToEmptyArrayWhenNotSetOnAttribute(): void
    {
        $actions = $this->provider->getActions(EntityWithRowActions::class);

        foreach ($actions as $action) {
            $this->assertSame(
                [],
                $action->contexts,
                sprintf('Action "%s" should have contexts: [] (all contexts) by default', $action->name),
            );
        }
    }

    #[Test]
    public function contextsArePropagatedFromAdminActionAttribute(): void
    {
        // EntityWithRowActions actions use default empty contexts — confirming pass-through
        // of non-empty contexts is covered by InlineEditRowActionProviderTest
        $actions = $this->provider->getActions(EntityWithRowActions::class);

        $this->assertNotEmpty($actions);
        foreach ($actions as $action) {
            $this->assertSame([], $action->contexts);
        }
    }

    #[Test]
    public function resultIsCachedOnSecondCall(): void
    {
        $first = $this->provider->getActions(EntityWithRowActions::class);
        $second = $this->provider->getActions(EntityWithRowActions::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function returnsEmptyForNonExistentClass(): void
    {
        /** @var class-string $missing */
        $missing = 'App\\Entity\\Ghost'; // @phpstan-ignore varTag.nativeType
        $actions = $this->provider->getActions($missing);

        $this->assertSame([], $actions);
    }

    // -------------------------------------------------------------------------
    // getActionsConfig()
    // -------------------------------------------------------------------------

    #[Test]
    public function returnsNullForEntityWithNoActionsConfig(): void
    {
        $this->assertNotInstanceOf(\Kachnitel\AdminBundle\Attribute\AdminActionsConfig::class, $this->provider->getActionsConfig(TestEntity::class));
    }

    #[Test]
    public function returnsAdminActionsConfigWhenPresent(): void
    {
        $config = $this->provider->getActionsConfig(EntityWithRowActions::class);

        $this->assertInstanceOf(AdminActionsConfig::class, $config);
        $this->assertSame(['edit'], $config->exclude);
    }

    #[Test]
    public function configResultIsCachedOnSecondCall(): void
    {
        $first = $this->provider->getActionsConfig(EntityWithRowActions::class);
        $second = $this->provider->getActionsConfig(EntityWithRowActions::class);

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // getAdminActionAttribute() / isOverride()
    // -------------------------------------------------------------------------

    #[Test]
    public function getAdminActionAttributeReturnsNullForUnknownAction(): void
    {
        $attr = $this->provider->getAdminActionAttribute(EntityWithRowActions::class, 'nonexistent');
        $this->assertNotInstanceOf(\Kachnitel\AdminBundle\Attribute\AdminAction::class, $attr);
    }

    #[Test]
    public function getAdminActionAttributeReturnsAttributeForKnownAction(): void
    {
        $attr = $this->provider->getAdminActionAttribute(EntityWithRowActions::class, 'approve');

        $this->assertInstanceOf(AdminAction::class, $attr);
        $this->assertSame('approve', $attr->name);
    }

    #[Test]
    public function isOverrideReturnsFalseWhenNoOverrideFlag(): void
    {
        $this->assertFalse($this->provider->isOverride(EntityWithRowActions::class, 'approve'));
    }

    #[Test]
    public function isOverrideReturnsFalseForUnknownAction(): void
    {
        $this->assertFalse($this->provider->isOverride(EntityWithRowActions::class, 'missing'));
    }

    #[Test]
    public function isOverrideReturnsTrueForActionWithOverrideFlag(): void
    {
        $provider = new AttributeRowActionProvider();
        $this->assertFalse($provider->isOverride(EntityWithRowActions::class, 'approve'));
    }

    // -------------------------------------------------------------------------
    // getPriority()
    // -------------------------------------------------------------------------

    #[Test]
    public function priorityIs50(): void
    {
        $this->assertSame(50, $this->provider->getPriority());
    }
}
