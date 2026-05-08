<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\BatchAction;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\BatchAction\AttributeBatchActionProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithRowActions;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\ValueObject\BatchAction;
use PHPUnit\Framework\TestCase;

/**
 * @group batch-actions
 */
class AttributeBatchActionProviderTest extends TestCase
{
    private AttributeBatchActionProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new AttributeBatchActionProvider();
    }

    /** @test */
    public function supportsAnyExistingClass(): void
    {
        $this->assertTrue($this->provider->supports(TestEntity::class));
    }

    /** @test */
    public function doesNotSupportNonExistentClass(): void
    {
        /** @var class-string $missing */
        $missing = 'App\\Entity\\DoesNotExist'; // @phpstan-ignore varTag.nativeType
        $this->assertFalse($this->provider->supports($missing));
    }

    /** @test */
    public function returnsEmptyArrayForEntityWithNoBatchActions(): void
    {
        // TestEntity has no batch AdminAction attributes
        $this->assertSame([], $this->provider->getActions(TestEntity::class));
    }

    /** @test */
    public function returnsEmptyArrayForEntityWithOnlyRowActions(): void
    {
        // EntityWithRowActions has row-type actions only
        $this->assertSame([], $this->provider->getActions(EntityWithRowActions::class));
    }

    /** @test */
    public function readsBatchActionAttributesFromEntityClass(): void
    {
        $actions = $this->provider->getActions(EntityWithBatchActions::class);

        $this->assertCount(1, $actions);
        $this->assertContainsOnlyInstancesOf(BatchAction::class, $actions);
    }

    /** @test */
    public function actionNameAndLabelAreReadCorrectly(): void
    {
        $actions = $this->provider->getActions(EntityWithBatchActions::class);

        $this->assertCount(1, $actions);
        $this->assertSame('bulk-publish', $actions[0]->name);
        $this->assertSame('Publish All', $actions[0]->label);
    }

    /** @test */
    public function actionWithBothTypeIsIncluded(): void
    {
        $actions = $this->provider->getActions(EntityWithBothTypeAction::class);

        $this->assertCount(1, $actions);
        $this->assertSame('manage', $actions[0]->name);
    }

    /** @test */
    public function priorityIs50(): void
    {
        $this->assertSame(50, $this->provider->getPriority());
    }

    /** @test */
    public function resultIsCachedOnSecondCall(): void
    {
        $first = $this->provider->getActions(EntityWithBatchActions::class);
        $second = $this->provider->getActions(EntityWithBatchActions::class);

        $this->assertSame($first, $second);
    }
}

// ── Test fixtures ─────────────────────────────────────────────────────────────

#[AdminAction(
    name: 'bulk-publish',
    label: 'Publish All',
    icon: '🚀',
    url: '/admin/test/bulk-publish',
    confirmMessage: 'Publish %count% items?',
    priority: 30,
    actionType: AdminAction::ACTION_TYPE_BATCH,
)]
class EntityWithBatchActions
{
    public function getId(): int { return 1; }
}

#[AdminAction(
    name: 'manage',
    label: 'Manage',
    url: '/admin/test/manage',
    actionType: AdminAction::ACTION_TYPE_BOTH,
)]
class EntityWithBothTypeAction
{
    public function getId(): int { return 1; }
}
