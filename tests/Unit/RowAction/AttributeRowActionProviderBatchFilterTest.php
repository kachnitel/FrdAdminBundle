<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\RowAction;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\RowAction\AttributeRowActionProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests that AttributeRowActionProvider filters out batch-only actions.
 *
 * @group batch-actions
 * @group row-actions
 */
class AttributeRowActionProviderBatchFilterTest extends TestCase
{
    private AttributeRowActionProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new AttributeRowActionProvider();
    }

    /** @test */
    public function batchOnlyActionIsExcludedFromRowActions(): void
    {
        $actions = $this->provider->getActions(EntityWithMixedActionTypes::class);

        $names = array_map(fn ($a) => $a->name, $actions);
        $this->assertNotContains('bulk-publish', $names, 'BATCH-only action must not appear as row action');
    }

    /** @test */
    public function rowOnlyActionIsIncluded(): void
    {
        $actions = $this->provider->getActions(EntityWithMixedActionTypes::class);

        $names = array_map(fn ($a) => $a->name, $actions);
        $this->assertContains('approve', $names);
    }

    /** @test */
    public function bothTypeActionIsIncludedAsRowAction(): void
    {
        $actions = $this->provider->getActions(EntityWithMixedActionTypes::class);

        $names = array_map(fn ($a) => $a->name, $actions);
        $this->assertContains('archive', $names);
    }

    /** @test */
    public function defaultActionTypeIsRow(): void
    {
        $actions = $this->provider->getActions(EntityWithDefaultActionType::class);

        $this->assertCount(1, $actions);
        $this->assertSame('show', $actions[0]->name);
    }
}

// ── Test fixtures ─────────────────────────────────────────────────────────────

#[AdminAction(name: 'approve', label: 'Approve', url: '/approve',
    actionType: AdminAction::ACTION_TYPE_ROW)]
#[AdminAction(name: 'bulk-publish', label: 'Publish All', url: '/publish',
    actionType: AdminAction::ACTION_TYPE_BATCH)]
#[AdminAction(name: 'archive', label: 'Archive', url: '/archive',
    actionType: AdminAction::ACTION_TYPE_BOTH)]
class EntityWithMixedActionTypes
{
    public function getId(): int { return 1; }
}

#[AdminAction(name: 'show', label: 'Show', url: '/show')]
// No actionType set — defaults to ACTION_TYPE_ROW
class EntityWithDefaultActionType
{
    public function getId(): int { return 1; }
}
