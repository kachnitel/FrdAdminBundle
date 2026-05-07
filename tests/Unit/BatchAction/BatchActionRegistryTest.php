<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\BatchAction;

use Kachnitel\AdminBundle\BatchAction\BatchActionProviderInterface;
use Kachnitel\AdminBundle\BatchAction\BatchActionRegistry;
use Kachnitel\AdminBundle\ValueObject\BatchAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BatchActionRegistry::class)]
class BatchActionRegistryTest extends TestCase
{
    /** @test */
    public function itReturnsCachedActionsOnSecondCall(): void
    {
        $provider = new InMemoryBatchActionProvider([
            'admin.product' => [
                new BatchAction(name: 'delete', label: 'Delete'),
            ],
        ]);

        $registry = new BatchActionRegistry([$provider]);

        // First call
        $actions1 = $registry->getActions('admin.product');
        // Second call (should use cache)
        $actions2 = $registry->getActions('admin.product');

        $this->assertSame($actions1, $actions2);
    }

    /** @test */
    public function itReturnsEmptyArrayWhenNoProvidersSupport(): void
    {
        $provider = new InMemoryBatchActionProvider([]);

        $registry = new BatchActionRegistry([$provider]);

        $actions = $registry->getActions('admin.article');

        $this->assertSame([], $actions);
    }

    /** @test */
    public function itMergesActionsFromMultipleProviders(): void
    {
        $provider1 = new InMemoryBatchActionProvider([
            'admin.product' => [
                new BatchAction(name: 'delete', label: 'Delete', icon: '🗑️', priority: 0),
            ],
        ]);

        $provider2 = new InMemoryBatchActionProvider([
            'admin.product' => [
                new BatchAction(name: 'delete', label: 'Delete Selected', priority: 10),
            ],
        ]);

        $registry = new BatchActionRegistry([$provider1, $provider2]);

        $actions = $registry->getActions('admin.product');

        $this->assertCount(1, $actions);
        $action = $actions['delete'];
        $this->assertSame('delete', $action->name);
        // Label from provider2 should override (both providers support, but provider2 is processed after)
        $this->assertSame('Delete Selected', $action->label);
        // Icon from provider1 should remain (provider2 has null)
        $this->assertSame('🗑️', $action->icon);
    }

    /** @test */
    public function itSortsByPriority(): void
    {
        $provider = new InMemoryBatchActionProvider([
            'admin.product' => [
                new BatchAction(name: 'export', label: 'Export', priority: 50),
                new BatchAction(name: 'delete', label: 'Delete', priority: 20),
                new BatchAction(name: 'archive', label: 'Archive', priority: 30),
            ],
        ]);

        $registry = new BatchActionRegistry([$provider]);

        $actions = $registry->getActions('admin.product');

        $actionNames = array_keys($actions);
        $this->assertSame(['delete', 'archive', 'export'], $actionNames);
    }

    /** @test */
    public function itPreservesActionNamesAsKeys(): void
    {
        $provider = new InMemoryBatchActionProvider([
            'admin.product' => [
                new BatchAction(name: 'delete', label: 'Delete'),
                new BatchAction(name: 'archive', label: 'Archive'),
            ],
        ]);

        $registry = new BatchActionRegistry([$provider]);

        $actions = $registry->getActions('admin.product');

        $this->assertArrayHasKey('delete', $actions);
        $this->assertArrayHasKey('archive', $actions);
    }

    /** @test */
    public function itReturnsSpecificActionByName(): void
    {
        $provider = new InMemoryBatchActionProvider([
            'admin.product' => [
                new BatchAction(name: 'delete', label: 'Delete'),
                new BatchAction(name: 'archive', label: 'Archive'),
            ],
        ]);

        $registry = new BatchActionRegistry([$provider]);

        $action = $registry->getAction('admin.product', 'delete');

        $this->assertNotNull($action);
        $this->assertSame('delete', $action->name);
        $this->assertSame('Delete', $action->label);
    }

    /** @test */
    public function itReturnsNullForNonexistentAction(): void
    {
        $provider = new InMemoryBatchActionProvider([
            'admin.product' => [
                new BatchAction(name: 'delete', label: 'Delete'),
            ],
        ]);

        $registry = new BatchActionRegistry([$provider]);

        $action = $registry->getAction('admin.product', 'nonexistent');

        $this->assertNull($action);
    }

    /** @test */
    public function itChecksIfEntityHasActions(): void
    {
        $provider = new InMemoryBatchActionProvider([
            'admin.product' => [
                new BatchAction(name: 'delete', label: 'Delete'),
            ],
        ]);

        $registry = new BatchActionRegistry([$provider]);

        $this->assertTrue($registry->hasActions('admin.product'));
        $this->assertFalse($registry->hasActions('admin.article'));
    }

    /** @test */
    public function itRespectsPriorityWhenMergingFromMultipleProviders(): void
    {
        // Provider with lower priority (processed first)
        $lowPriorityProvider = new InMemoryBatchActionProvider([
            'admin.product' => [
                new BatchAction(name: 'delete', label: 'Delete Low Priority', liveAction: 'batchDelete'),
            ],
        ]);
        $lowPriorityProvider->priority = 0;

        // Provider with higher priority (processed second, overrides)
        $highPriorityProvider = new InMemoryBatchActionProvider([
            'admin.product' => [
                new BatchAction(name: 'delete', label: 'Delete High Priority'),
            ],
        ]);
        $highPriorityProvider->priority = 10;

        $registry = new BatchActionRegistry([$lowPriorityProvider, $highPriorityProvider]);

        $action = $registry->getAction('admin.product', 'delete');

        $this->assertNotNull($action);
        // Label from high priority provider should be used
        $this->assertSame('Delete High Priority', $action->label);
        // liveAction from low priority provider should be preserved (high priority has null)
        $this->assertSame('batchDelete', $action->liveAction);
    }

    /** @test */
    public function itReturnsEmptyArrayWhenNoActionsExist(): void
    {
        $provider = new InMemoryBatchActionProvider([]);

        $registry = new BatchActionRegistry([$provider]);

        $actions = $registry->getActions('admin.product');

        $this->assertEmpty($actions);
    }
}

/**
 * Test helper: In-memory implementation of BatchActionProviderInterface.
 *
 * @internal
 */
class InMemoryBatchActionProvider implements BatchActionProviderInterface
{
    public int $priority = 0;

    /**
     * @param array<string, array<BatchAction>> $actionsByEntity
     */
    public function __construct(private array $actionsByEntity = [])
    {
    }

    public function supports(string $entityClass): bool
    {
        return isset($this->actionsByEntity[$entityClass]);
    }

    /**
     * @return array<BatchAction>
     */
    public function getActions(string $entityClass): array
    {
        return $this->actionsByEntity[$entityClass] ?? [];
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
