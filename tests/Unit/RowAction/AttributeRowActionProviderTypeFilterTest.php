<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\RowAction;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\RowAction\AttributeRowActionProvider;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AdminAction.type filtering in AttributeRowActionProvider.
 *
 * Verifies that row-type and both-type actions are included while
 * batch-type actions are excluded from row action results.
 *
 * @group row-actions
 */
#[CoversClass(AttributeRowActionProvider::class)]
class AttributeRowActionProviderTypeFilterTest extends TestCase
{
    private AttributeRowActionProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new AttributeRowActionProvider();
    }

    /** @test */
    public function includesRowTypeActionByDefault(): void
    {
        $actions = $this->provider->getActions(RowOnlyEntity::class);

        $this->assertCount(1, $actions);
        $this->assertSame('approve', $actions[0]->name);
    }

    /** @test */
    public function includesExplicitRowTypeAction(): void
    {
        $actions = $this->provider->getActions(ExplicitRowTypeEntity::class);

        $this->assertCount(1, $actions);
        $this->assertSame('preview', $actions[0]->name);
    }

    /** @test */
    public function includesBothTypeActionInRowResults(): void
    {
        $actions = $this->provider->getActions(BothTypeActionEntity::class);

        $this->assertCount(1, $actions);
        $this->assertSame('archive', $actions[0]->name);
    }

    /** @test */
    public function excludesBatchTypeActionFromRowResults(): void
    {
        $actions = $this->provider->getActions(BatchOnlyActionEntity::class);

        $this->assertEmpty($actions, 'Batch-only actions must not appear in row action results');
    }

    /** @test */
    public function mixedTypesOnlyReturnsRowAndBoth(): void
    {
        $actions = $this->provider->getActions(MixedTypeActionEntity::class);

        $names = array_map(fn (RowAction $a) => $a->name, $actions);
        $this->assertContains('row_action', $names);
        $this->assertContains('both_action', $names);
        $this->assertNotContains('batch_only', $names);
        $this->assertCount(2, $actions);
    }

    /** @test */
    public function returnsInstancesOfRowAction(): void
    {
        $actions = $this->provider->getActions(BothTypeActionEntity::class);

        $this->assertContainsOnlyInstancesOf(RowAction::class, $actions);
    }
}

// ── Test fixture entities ───────────────────────────────────────────────────

#[AdminAction(
    name: 'approve',
    label: 'Approve',
    url: '/approve',
    // No type — defaults to TYPE_ROW
)]
class RowOnlyEntity {}

#[AdminAction(
    name: 'preview',
    label: 'Preview',
    type: AdminAction::TYPE_ROW,
    url: '/preview',
)]
class ExplicitRowTypeEntity {}

#[AdminAction(
    name: 'archive',
    label: 'Archive',
    type: AdminAction::TYPE_BOTH,
    route: 'app_archive',
)]
class BothTypeActionEntity {}

#[AdminAction(
    name: 'bulk_publish',
    label: 'Bulk Publish',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'bulkPublish',
    voterAttribute: 'ADMIN_EDIT',
)]
class BatchOnlyActionEntity {}

#[AdminAction(
    name: 'row_action',
    label: 'Row Action',
    url: '/row',
    // defaults to TYPE_ROW
)]
#[AdminAction(
    name: 'both_action',
    label: 'Both Action',
    type: AdminAction::TYPE_BOTH,
    route: 'app_both',
)]
#[AdminAction(
    name: 'batch_only',
    label: 'Batch Only',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'doBatch',
    voterAttribute: 'ADMIN_EDIT',
)]
class MixedTypeActionEntity {}
