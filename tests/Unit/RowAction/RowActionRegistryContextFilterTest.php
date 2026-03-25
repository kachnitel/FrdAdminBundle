<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\RowAction;

use Kachnitel\AdminBundle\RowAction\AttributeRowActionProvider;
use Kachnitel\AdminBundle\RowAction\RowActionProviderInterface;
use Kachnitel\AdminBundle\RowAction\RowActionRegistry;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that RowActionRegistry applies context filtering BEFORE merging actions.
 *
 * The critical invariant: a CONTEXT_INDEX-only action from a higher-priority provider
 * must NOT leak into (overwrite or merge with) the same-named action when resolving
 * for 'show' or 'edit' contexts.
 *
 * This matters for security: the InlineEditButton liveComponent fires live#action on
 * the parent EntityList — which does not exist on show/edit pages. If it appeared there
 * it would both be non-functional and could mask the navigable Edit link.
 *
 * @covers \Kachnitel\AdminBundle\RowAction\RowActionRegistry::getActions
 * @group row-actions
 */
class RowActionRegistryContextFilterTest extends TestCase
{
    /** @var AttributeRowActionProvider&MockObject */
    private AttributeRowActionProvider $attributeProvider;

    protected function setUp(): void
    {
        $this->attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $this->attributeProvider->method('supports')->willReturn(true);
        $this->attributeProvider->method('getActions')->willReturn([]);
        $this->attributeProvider->method('getActionsConfig')->willReturn(null);
        $this->attributeProvider->method('isOverride')->willReturn(false);
        $this->attributeProvider->method('getPriority')->willReturn(50);
    }

    /**
     * @param iterable<RowActionProviderInterface> $providers
     */
    private function makeRegistry(iterable $providers): RowActionRegistry
    {
        return new RowActionRegistry($providers, $this->attributeProvider);
    }

    // ── Context filtering before merge ────────────────────────────────────────

    /** @test */
    public function indexOnlyActionIsAbsentFromShowContext(): void
    {
        /** @var RowActionProviderInterface&MockObject $provider */
        $provider = $this->createMock(RowActionProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('getPriority')->willReturn(0);
        $provider->method('getActions')->willReturn([
            new RowAction(
                name: 'edit',
                label: 'Edit (inline)',
                liveComponent: 'K:Admin:RowAction:InlineEdit',
                contexts: [RowAction::CONTEXT_INDEX],
            ),
        ]);

        $registry = $this->makeRegistry([$provider]);
        $actions  = $registry->getActions('App\\Entity\\Product', RowAction::CONTEXT_SHOW);

        $names = array_map(fn (RowAction $a) => $a->name, $actions);
        $this->assertNotContains('edit', $names, 'CONTEXT_INDEX-only action must not appear in show context.');
    }

    /** @test */
    public function indexOnlyActionIsAbsentFromEditContext(): void
    {
        /** @var RowActionProviderInterface&MockObject $provider */
        $provider = $this->createMock(RowActionProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('getPriority')->willReturn(0);
        $provider->method('getActions')->willReturn([
            new RowAction(
                name: 'edit',
                label: 'Edit (inline)',
                liveComponent: 'K:Admin:RowAction:InlineEdit',
                contexts: [RowAction::CONTEXT_INDEX],
            ),
        ]);

        $registry = $this->makeRegistry([$provider]);
        $actions  = $registry->getActions('App\\Entity\\Product', RowAction::CONTEXT_EDIT);

        $names = array_map(fn (RowAction $a) => $a->name, $actions);
        $this->assertNotContains('edit', $names, 'CONTEXT_INDEX-only action must not appear in edit context.');
    }

    /** @test */
    public function indexOnlyActionDoesNotOverwriteUniversalActionInShowContext(): void
    {
        // Low-priority provider: universal plain-link edit (all contexts)
        /** @var RowActionProviderInterface&MockObject $lowPriority */
        $lowPriority = $this->createMock(RowActionProviderInterface::class);
        $lowPriority->method('supports')->willReturn(true);
        $lowPriority->method('getPriority')->willReturn(0);
        $lowPriority->method('getActions')->willReturn([
            new RowAction(
                name: 'edit',
                label: 'Edit',
                icon: '🖊',
                priority: 20,
                contexts: [], // all contexts
            ),
        ]);

        // Higher-priority provider: index-only inline-edit component
        /** @var RowActionProviderInterface&MockObject $highPriority */
        $highPriority = $this->createMock(RowActionProviderInterface::class);
        $highPriority->method('supports')->willReturn(true);
        $highPriority->method('getPriority')->willReturn(15);
        $highPriority->method('getActions')->willReturn([
            new RowAction(
                name: 'edit',
                label: 'Edit',
                icon: '✏️',
                liveComponent: 'K:Admin:RowAction:InlineEdit',
                contexts: [RowAction::CONTEXT_INDEX], // index only
            ),
        ]);

        $registry = $this->makeRegistry([$lowPriority, $highPriority]);

        $showActions = $registry->getActions('App\\Entity\\Product', RowAction::CONTEXT_SHOW);

        $editAction = null;
        foreach ($showActions as $action) {
            if ($action->name === 'edit') {
                $editAction = $action;
            }
        }

        $this->assertNotNull($editAction, 'Edit action must still be present in show context.');
        $this->assertNull(
            $editAction->liveComponent,
            'The plain-link edit action must survive — the index-only liveComponent must not have merged into it.'
        );
        $this->assertSame('🖊', $editAction->icon, 'Plain-link edit icon must be preserved.');
    }

    /** @test */
    public function indexOnlyActionDoesNotOverwriteUniversalActionInEditContext(): void
    {
        /** @var RowActionProviderInterface&MockObject $lowPriority */
        $lowPriority = $this->createMock(RowActionProviderInterface::class);
        $lowPriority->method('supports')->willReturn(true);
        $lowPriority->method('getPriority')->willReturn(0);
        $lowPriority->method('getActions')->willReturn([
            new RowAction(name: 'edit', label: 'Edit', icon: '🖊', contexts: []),
        ]);

        /** @var RowActionProviderInterface&MockObject $highPriority */
        $highPriority = $this->createMock(RowActionProviderInterface::class);
        $highPriority->method('supports')->willReturn(true);
        $highPriority->method('getPriority')->willReturn(15);
        $highPriority->method('getActions')->willReturn([
            new RowAction(
                name: 'edit',
                label: 'Edit',
                liveComponent: 'K:Admin:RowAction:InlineEdit',
                contexts: [RowAction::CONTEXT_INDEX],
            ),
        ]);

        $registry = $this->makeRegistry([$lowPriority, $highPriority]);

        $editActions = $registry->getActions('App\\Entity\\Product', RowAction::CONTEXT_EDIT);

        $editAction = null;
        foreach ($editActions as $action) {
            if ($action->name === 'edit') {
                $editAction = $action;
            }
        }

        $this->assertNotNull($editAction);
        $this->assertNull($editAction->liveComponent);
    }

    /** @test */
    public function indexOnlyActionIsVisibleInIndexContext(): void
    {
        /** @var RowActionProviderInterface&MockObject $provider */
        $provider = $this->createMock(RowActionProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('getPriority')->willReturn(0);
        $provider->method('getActions')->willReturn([
            new RowAction(
                name: 'edit',
                label: 'Edit (inline)',
                liveComponent: 'K:Admin:RowAction:InlineEdit',
                contexts: [RowAction::CONTEXT_INDEX],
            ),
        ]);

        $registry = $this->makeRegistry([$provider]);
        $actions  = $registry->getActions('App\\Entity\\Product', RowAction::CONTEXT_INDEX);

        $names = array_map(fn (RowAction $a) => $a->name, $actions);
        $this->assertContains('edit', $names, 'CONTEXT_INDEX action must appear in index context.');
    }

    /** @test */
    public function inIndexContextBothUniversalAndIndexOnlyActionsAreReturnedAndMerged(): void
    {
        /** @var RowActionProviderInterface&MockObject $lowPriority */
        $lowPriority = $this->createMock(RowActionProviderInterface::class);
        $lowPriority->method('supports')->willReturn(true);
        $lowPriority->method('getPriority')->willReturn(0);
        $lowPriority->method('getActions')->willReturn([
            new RowAction(name: 'edit', label: 'Edit', icon: '🖊', priority: 20, contexts: []),
        ]);

        /** @var RowActionProviderInterface&MockObject $highPriority */
        $highPriority = $this->createMock(RowActionProviderInterface::class);
        $highPriority->method('supports')->willReturn(true);
        $highPriority->method('getPriority')->willReturn(15);
        $highPriority->method('getActions')->willReturn([
            new RowAction(
                name: 'edit',
                label: 'Edit',
                liveComponent: 'K:Admin:RowAction:InlineEdit',
                icon: '✏️',
                contexts: [RowAction::CONTEXT_INDEX],
            ),
        ]);

        $registry = $this->makeRegistry([$lowPriority, $highPriority]);

        $indexActions = $registry->getActions('App\\Entity\\Product', RowAction::CONTEXT_INDEX);

        $editAction = null;
        foreach ($indexActions as $action) {
            if ($action->name === 'edit') {
                $editAction = $action;
            }
        }

        $this->assertNotNull($editAction);
        // In index context both are present → merge applies → liveComponent from high priority
        $this->assertSame('K:Admin:RowAction:InlineEdit', $editAction->liveComponent);
    }

    /** @test */
    public function noContextFilterReturnsAllActionsRegardlessOfContext(): void
    {
        /** @var RowActionProviderInterface&MockObject $provider */
        $provider = $this->createMock(RowActionProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('getPriority')->willReturn(0);
        $provider->method('getActions')->willReturn([
            new RowAction(name: 'show', label: 'Show', contexts: []),
            new RowAction(name: 'inline', label: 'Inline', contexts: [RowAction::CONTEXT_INDEX]),
        ]);

        $registry = $this->makeRegistry([$provider]);

        // Empty string = no context filter (returns all)
        $allActions = $registry->getActions('App\\Entity\\Product', '');

        $names = array_map(fn (RowAction $a) => $a->name, $allActions);
        $this->assertContains('show', $names);
        $this->assertContains('inline', $names);
    }

    /** @test */
    public function cacheKeyIncludesContext(): void
    {
        /** @var RowActionProviderInterface&MockObject $provider */
        $provider = $this->createMock(RowActionProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('getPriority')->willReturn(0);
        $provider->method('getActions')->willReturn([
            new RowAction(name: 'edit', label: 'Edit', contexts: [RowAction::CONTEXT_INDEX]),
        ]);

        $registry = $this->makeRegistry([$provider]);

        $showActions  = $registry->getActions('App\\Entity\\Product', RowAction::CONTEXT_SHOW);
        $indexActions = $registry->getActions('App\\Entity\\Product', RowAction::CONTEXT_INDEX);

        $showNames  = array_map(fn (RowAction $a) => $a->name, $showActions);
        $indexNames = array_map(fn (RowAction $a) => $a->name, $indexActions);

        $this->assertNotContains('edit', $showNames);
        $this->assertContains('edit', $indexNames);
    }
}
