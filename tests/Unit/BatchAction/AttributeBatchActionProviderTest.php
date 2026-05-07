<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\BatchAction;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\BatchAction\AttributeBatchActionProvider;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\ValueObject\BatchAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @group batch-actions
 */
#[CoversClass(AttributeBatchActionProvider::class)]
class AttributeBatchActionProviderTest extends TestCase
{
    private AttributeBatchActionProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new AttributeBatchActionProvider();
    }

    // ── supports() ────────────────────────────────────────────────────────────

    /** @test */
    public function supportsAnyExistingClass(): void
    {
        $this->assertTrue($this->provider->supports(EntityWithBatchAction::class));
        $this->assertTrue($this->provider->supports(EntityWithRowOnlyAction::class));
    }

    /** @test */
    public function doesNotSupportNonExistentClass(): void
    {
        $this->assertFalse($this->provider->supports('NonExistent\Entity'));
    }

    // ── getActions() — type filtering ─────────────────────────────────────────

    /** @test */
    public function returnsBatchActionForBatchType(): void
    {
        $actions = $this->provider->getActions(EntityWithBatchAction::class);

        $this->assertCount(1, $actions);
        $this->assertSame('publish', $actions[0]->name);
        $this->assertSame('Publish Selected', $actions[0]->label);
    }

    /** @test */
    public function returnsBatchActionForBothType(): void
    {
        $actions = $this->provider->getActions(EntityWithBothAction::class);

        $this->assertCount(1, $actions);
        $this->assertSame('archive', $actions[0]->name);
    }

    /** @test */
    public function skipsRowOnlyActions(): void
    {
        $actions = $this->provider->getActions(EntityWithRowOnlyAction::class);

        $this->assertEmpty($actions);
    }

    /** @test */
    public function skipsDefaultTypeRowActions(): void
    {
        // AdminAction without explicit type defaults to TYPE_ROW
        $actions = $this->provider->getActions(EntityWithDefaultTypeAction::class);

        $this->assertEmpty($actions);
    }

    // ── getActions() — field mapping ──────────────────────────────────────────

    /** @test */
    public function mapsBatchLiveActionToLiveAction(): void
    {
        $actions = $this->provider->getActions(EntityWithBatchAction::class);

        $action = $actions[0];
        $this->assertSame('bulkPublish', $action->liveAction);
        $this->assertNull($action->route);
        $this->assertNull($action->url);
    }

    /** @test */
    public function mapsRouteForBothType(): void
    {
        $actions = $this->provider->getActions(EntityWithBothAction::class);

        $action = $actions[0];
        $this->assertSame('app_archive', $action->route);
        $this->assertNull($action->liveAction);
    }

    /** @test */
    public function mapsAllCommonFields(): void
    {
        $actions = $this->provider->getActions(EntityWithAllFields::class);

        $action = $actions[0];
        $this->assertSame('bulk_export', $action->name);
        $this->assertSame('Export', $action->label);
        $this->assertSame('📥', $action->icon);
        $this->assertSame(AdminEntityVoter::ADMIN_SHOW, $action->voterAttribute);
        $this->assertSame('Are you sure?', $action->confirmMessage);
        $this->assertSame('btn-info', $action->cssClass);
        $this->assertSame(10, $action->priority);
        $this->assertTrue($action->openInNewTab);
    }

    /** @test */
    public function returnsInstancesOfBatchAction(): void
    {
        $actions = $this->provider->getActions(EntityWithBatchAction::class);

        $this->assertContainsOnlyInstancesOf(BatchAction::class, $actions);
    }

    // ── getActions() — multiple actions ───────────────────────────────────────

    /** @test */
    public function returnsMultipleBatchActions(): void
    {
        $actions = $this->provider->getActions(EntityWithMultipleBatchActions::class);

        $this->assertCount(2, $actions);
        $names = array_map(fn (BatchAction $a) => $a->name, $actions);
        $this->assertContains('publish', $names);
        $this->assertContains('archive', $names);
    }

    // ── getActions() — caching ────────────────────────────────────────────────

    /** @test */
    public function cachesSameResultOnSubsequentCalls(): void
    {
        $first = $this->provider->getActions(EntityWithBatchAction::class);
        $second = $this->provider->getActions(EntityWithBatchAction::class);

        $this->assertSame($first, $second);
    }

    // ── getActions() — validation ─────────────────────────────────────────────

    /** @test */
    public function throwsWhenNoHandlerSpecified(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must specify one of: batchLiveAction, route, or url/');

        $this->provider->getActions(EntityWithNoHandler::class);
    }

    /** @test */
    public function throwsWhenMultipleHandlersSpecified(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exactly one of: batchLiveAction, route, or url/');

        $this->provider->getActions(EntityWithMultipleHandlers::class);
    }

    /** @test */
    public function throwsWhenNoPermissionSpecified(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must specify voterAttribute or permission/');

        $this->provider->getActions(EntityWithNoPermission::class);
    }

    // ── priority ──────────────────────────────────────────────────────────────

    /** @test */
    public function hasPriority50(): void
    {
        $this->assertSame(50, $this->provider->getPriority());
    }
}

// ── Test fixture entities ───────────────────────────────────────────────────

#[AdminAction(
    name: 'publish',
    label: 'Publish Selected',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'bulkPublish',
    voterAttribute: AdminEntityVoter::ADMIN_EDIT,
)]
class EntityWithBatchAction {}

#[AdminAction(
    name: 'archive',
    label: 'Archive',
    type: AdminAction::TYPE_BOTH,
    route: 'app_archive',
    voterAttribute: AdminEntityVoter::ADMIN_DELETE,
)]
class EntityWithBothAction {}

#[AdminAction(
    name: 'preview',
    label: 'Preview',
    url: '/preview',
)]
class EntityWithRowOnlyAction {}

#[AdminAction(
    name: 'show',
    label: 'Show',
    route: 'app_show',
)]
class EntityWithDefaultTypeAction {}

#[AdminAction(
    name: 'bulk_export',
    label: 'Export',
    icon: '📥',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'bulkExport',
    voterAttribute: AdminEntityVoter::ADMIN_SHOW,
    confirmMessage: 'Are you sure?',
    cssClass: 'btn-info',
    priority: 10,
    openInNewTab: true,
)]
class EntityWithAllFields {}

#[AdminAction(
    name: 'publish',
    label: 'Publish',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'bulkPublish',
    voterAttribute: AdminEntityVoter::ADMIN_EDIT,
    priority: 10,
)]
#[AdminAction(
    name: 'archive',
    label: 'Archive',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'bulkArchive',
    voterAttribute: AdminEntityVoter::ADMIN_DELETE,
    priority: 20,
)]
class EntityWithMultipleBatchActions {}

// Validation failures

#[AdminAction(
    name: 'broken',
    label: 'Broken',
    type: AdminAction::TYPE_BATCH,
    voterAttribute: AdminEntityVoter::ADMIN_EDIT,
    // missing handler: no batchLiveAction, route, or url
)]
class EntityWithNoHandler {}

#[AdminAction(
    name: 'broken',
    label: 'Broken',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'doSomething',
    route: 'app_something',
    voterAttribute: AdminEntityVoter::ADMIN_EDIT,
    // has both batchLiveAction AND route
)]
class EntityWithMultipleHandlers {}

#[AdminAction(
    name: 'broken',
    label: 'Broken',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'doSomething',
    // missing permission: no voterAttribute or permission
)]
class EntityWithNoPermission {}
