<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\RowAction;

use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;
use Kachnitel\AdminBundle\RowAction\AttributeRowActionProvider;
use Kachnitel\AdminBundle\RowAction\DefaultRowActionProvider;
use Kachnitel\AdminBundle\RowAction\RowActionProviderInterface;
use Kachnitel\AdminBundle\RowAction\RowActionRegistry;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\TestCase;

class RowActionRegistryTest extends TestCase
{
    /** @var class-string */
    private const PRODUCT_CLASS = 'App\\Entity\\Product'; // @phpstan-ignore classConstant.phpDocType

    /** @var class-string */
    private const AUDIT_LOG_CLASS = 'App\\Entity\\AuditLog'; // @phpstan-ignore classConstant.phpDocType

    /**
     * @test
     */
    public function itReturnsDefaultActionsForEntityWithoutCustomActions(): void
    {
        $attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $attributeProvider->method('getActionsConfig')->willReturn(null);
        $attributeProvider->method('supports')->willReturn(true);
        $attributeProvider->method('getActions')->willReturn([]);
        $attributeProvider->method('getPriority')->willReturn(50);
        $attributeProvider->method('isOverride')->willReturn(false);

        $defaultProvider = new DefaultRowActionProvider();

        $registry = new RowActionRegistry(
            [$defaultProvider, $attributeProvider],
            $attributeProvider
        );

        $actions = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertCount(2, $actions);
        $this->assertSame('show', $actions[0]->name);
        $this->assertSame('edit', $actions[1]->name);
    }

    /**
     * @test
     */
    public function itMergesCustomActionsWithDefaults(): void
    {
        $customAction = new RowAction(
            name: 'duplicate',
            label: 'Duplicate',
            icon: '📋',
            priority: 30,
        );

        $attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $attributeProvider->method('getActionsConfig')->willReturn(null);
        $attributeProvider->method('supports')->willReturn(true);
        $attributeProvider->method('getActions')->willReturn([$customAction]);
        $attributeProvider->method('getPriority')->willReturn(50);
        $attributeProvider->method('isOverride')->willReturn(false);

        $defaultProvider = new DefaultRowActionProvider();

        $registry = new RowActionRegistry(
            [$defaultProvider, $attributeProvider],
            $attributeProvider
        );

        $actions = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertCount(3, $actions);
        // Actions should be sorted by priority: show(10), edit(20), duplicate(30)
        $this->assertSame('show', $actions[0]->name);
        $this->assertSame('edit', $actions[1]->name);
        $this->assertSame('duplicate', $actions[2]->name);
    }

    /**
     * @test
     */
    public function itMergesActionPropertiesWhenSameNameWithoutOverride(): void
    {
        // Custom action with same name as default, but without override flag
        $customShowAction = new RowAction(
            name: 'show',
            label: 'View Details',
            icon: '🔍',
            condition: 'entity.active',
        );

        $attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $attributeProvider->method('getActionsConfig')->willReturn(null);
        $attributeProvider->method('supports')->willReturn(true);
        $attributeProvider->method('getActions')->willReturn([$customShowAction]);
        $attributeProvider->method('getPriority')->willReturn(50);
        $attributeProvider->method('isOverride')->willReturn(false);

        $defaultProvider = new DefaultRowActionProvider();

        $registry = new RowActionRegistry(
            [$defaultProvider, $attributeProvider],
            $attributeProvider
        );

        $actions = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertCount(2, $actions);

        $showAction = $actions[0];
        $this->assertSame('show', $showAction->name);
        // Merged from custom
        $this->assertSame('View Details', $showAction->label);
        $this->assertSame('🔍', $showAction->icon);
        $this->assertSame('entity.active', $showAction->condition);
        // Kept from default
        $this->assertSame('ADMIN_SHOW', $showAction->voterAttribute);
    }

    /**
     * @test
     */
    public function itReplacesActionWithOverrideFlag(): void
    {
        $customShowAction = new RowAction(
            name: 'show',
            label: 'Custom Show',
            icon: '🔎',
            priority: 50,
        );

        $attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $attributeProvider->method('getActionsConfig')->willReturn(null);
        $attributeProvider->method('supports')->willReturn(true);
        $attributeProvider->method('getActions')->willReturn([$customShowAction]);
        $attributeProvider->method('getPriority')->willReturn(50);
        // This action has override flag
        $attributeProvider->method('isOverride')->with(self::PRODUCT_CLASS, 'show')->willReturn(true);

        $defaultProvider = new DefaultRowActionProvider();

        $registry = new RowActionRegistry(
            [$defaultProvider, $attributeProvider],
            $attributeProvider
        );

        $actions = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertCount(2, $actions);

        // Find the show action
        $showAction = null;
        foreach ($actions as $action) {
            if ($action->name === 'show') {
                $showAction = $action;
                break;
            }
        }

        $this->assertNotNull($showAction);
        // Completely replaced - no voterAttribute from default
        $this->assertSame('Custom Show', $showAction->label);
        $this->assertSame('🔎', $showAction->icon);
        $this->assertNull($showAction->voterAttribute);
    }

    /**
     * @test
     */
    public function itAppliesExcludeConfig(): void
    {
        $config = new AdminActionsConfig(exclude: ['edit']);

        $attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $attributeProvider->method('getActionsConfig')->willReturn($config);
        $attributeProvider->method('supports')->willReturn(true);
        $attributeProvider->method('getActions')->willReturn([]);
        $attributeProvider->method('getPriority')->willReturn(50);

        $defaultProvider = new DefaultRowActionProvider();

        $registry = new RowActionRegistry(
            [$defaultProvider, $attributeProvider],
            $attributeProvider
        );

        $actions = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertCount(1, $actions);
        $this->assertSame('show', $actions[0]->name);
    }

    /**
     * @test
     */
    public function itAppliesIncludeConfig(): void
    {
        $customAction = new RowAction(name: 'duplicate', label: 'Duplicate', priority: 30);
        $config = new AdminActionsConfig(include: ['show', 'duplicate']);

        $attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $attributeProvider->method('getActionsConfig')->willReturn($config);
        $attributeProvider->method('supports')->willReturn(true);
        $attributeProvider->method('getActions')->willReturn([$customAction]);
        $attributeProvider->method('getPriority')->willReturn(50);
        $attributeProvider->method('isOverride')->willReturn(false);

        $defaultProvider = new DefaultRowActionProvider();

        $registry = new RowActionRegistry(
            [$defaultProvider, $attributeProvider],
            $attributeProvider
        );

        $actions = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertCount(2, $actions);
        $actionNames = array_map(fn ($a) => $a->name, $actions);
        $this->assertContains('show', $actionNames);
        $this->assertContains('duplicate', $actionNames);
        $this->assertNotContains('edit', $actionNames);
    }

    /**
     * @test
     */
    public function itAppliesDisableDefaultsConfig(): void
    {
        $customAction = new RowAction(name: 'details', label: 'View Details', icon: '🔍', priority: 10);
        $config = new AdminActionsConfig(disableDefaults: true);

        $attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $attributeProvider->method('getActionsConfig')->willReturn($config);
        $attributeProvider->method('supports')->willReturn(true);
        $attributeProvider->method('getActions')->willReturn([$customAction]);
        $attributeProvider->method('getPriority')->willReturn(50);
        $attributeProvider->method('isOverride')->willReturn(false);

        $defaultProvider = new DefaultRowActionProvider();

        $registry = new RowActionRegistry(
            [$defaultProvider, $attributeProvider],
            $attributeProvider
        );

        $actions = $registry->getActions(self::AUDIT_LOG_CLASS); // @phpstan-ignore argument.type

        $this->assertCount(1, $actions);
        $this->assertSame('details', $actions[0]->name);
    }

    /**
     * @test
     */
    public function itCachesResultsForSameEntityClass(): void
    {
        $attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $attributeProvider->method('getActionsConfig')->willReturn(null);
        $attributeProvider->method('supports')->willReturn(true);
        $attributeProvider->method('getActions')->willReturn([]);
        $attributeProvider->method('getPriority')->willReturn(50);

        $defaultProvider = $this->createMock(RowActionProviderInterface::class);
        $defaultProvider->method('supports')->willReturn(true);
        $defaultProvider->expects($this->once())->method('getActions')->willReturn([
            new RowAction(name: 'show', label: 'Show'),
        ]);
        $defaultProvider->method('getPriority')->willReturn(0);

        $registry = new RowActionRegistry(
            [$defaultProvider, $attributeProvider],
            $attributeProvider
        );

        // First call
        $actions1 = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type
        // Second call - should use cache
        $actions2 = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertSame($actions1, $actions2);
    }

    /**
     * @test
     */
    public function clearCacheRemovesCachedResults(): void
    {
        $callCount = 0;

        $attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $attributeProvider->method('getActionsConfig')->willReturn(null);
        $attributeProvider->method('supports')->willReturn(true);
        $attributeProvider->method('getActions')->willReturn([]);
        $attributeProvider->method('getPriority')->willReturn(50);

        $defaultProvider = $this->createMock(RowActionProviderInterface::class);
        $defaultProvider->method('supports')->willReturn(true);
        $defaultProvider->method('getActions')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            return [new RowAction(name: 'show', label: 'Show')];
        });
        $defaultProvider->method('getPriority')->willReturn(0);

        $registry = new RowActionRegistry(
            [$defaultProvider, $attributeProvider],
            $attributeProvider
        );

        $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type
        $this->assertSame(1, $callCount);

        $registry->clearCache();
        $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type
        $this->assertSame(2, $callCount); // @phpstan-ignore method.impossibleType
    }

    /**
     * @test
     */
    public function itSortsActionsByPriority(): void
    {
        $customActions = [
            new RowAction(name: 'archive', label: 'Archive', priority: 5),
            new RowAction(name: 'duplicate', label: 'Duplicate', priority: 25),
        ];

        $attributeProvider = $this->createMock(AttributeRowActionProvider::class);
        $attributeProvider->method('getActionsConfig')->willReturn(null);
        $attributeProvider->method('supports')->willReturn(true);
        $attributeProvider->method('getActions')->willReturn($customActions);
        $attributeProvider->method('getPriority')->willReturn(50);
        $attributeProvider->method('isOverride')->willReturn(false);

        $defaultProvider = new DefaultRowActionProvider();

        $registry = new RowActionRegistry(
            [$defaultProvider, $attributeProvider],
            $attributeProvider
        );

        $actions = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertCount(4, $actions);
        // Should be sorted: archive(5), show(10), edit(20), duplicate(25)
        $this->assertSame('archive', $actions[0]->name);
        $this->assertSame('show', $actions[1]->name);
        $this->assertSame('edit', $actions[2]->name);
        $this->assertSame('duplicate', $actions[3]->name);
    }
}
