<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\BatchAction;

use Kachnitel\AdminBundle\BatchAction\BatchActionProviderInterface;
use Kachnitel\AdminBundle\BatchAction\BatchActionRegistry;
use Kachnitel\AdminBundle\ValueObject\BatchAction;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('batch-actions')]
#[AllowMockObjectsWithoutExpectations]
final class BatchActionRegistryTest extends TestCase
{
    /** @var class-string */
    private const PRODUCT_CLASS = 'App\\Entity\\Product'; // @phpstan-ignore classConstant.phpDocType

    /**
     * @param array<BatchActionProviderInterface> $providers
     */
    private function makeRegistry(array $providers): BatchActionRegistry
    {
        return new BatchActionRegistry($providers);
    }

    #[Test]
    public function returnsEmptyArrayWhenNoProvidersSupport(): void
    {
        /** @var BatchActionProviderInterface&MockObject $provider */
        $provider = $this->createMock(BatchActionProviderInterface::class);
        $provider->method('supports')->willReturn(false);
        $provider->method('getActions')->willReturn([]);
        $provider->method('getPriority')->willReturn(0);

        $registry = $this->makeRegistry([$provider]);
        $this->assertSame([], $registry->getActions(self::PRODUCT_CLASS)); // @phpstan-ignore argument.type
    }

    #[Test]
    public function mergesActionsFromMultipleProviders(): void
    {
        $action1 = new BatchAction(name: 'publish', label: 'Publish', priority: 10);
        $action2 = new BatchAction(name: 'archive', label: 'Archive', priority: 20);

        /** @var BatchActionProviderInterface&MockObject $provider1 */
        $provider1 = $this->createMock(BatchActionProviderInterface::class);
        $provider1->method('supports')->willReturn(true);
        $provider1->method('getActions')->willReturn([$action1]);
        $provider1->method('getPriority')->willReturn(0);

        /** @var BatchActionProviderInterface&MockObject $provider2 */
        $provider2 = $this->createMock(BatchActionProviderInterface::class);
        $provider2->method('supports')->willReturn(true);
        $provider2->method('getActions')->willReturn([$action2]);
        $provider2->method('getPriority')->willReturn(50);

        $registry = $this->makeRegistry([$provider1, $provider2]);
        $actions = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertCount(2, $actions);
    }

    #[Test]
    public function sortsByPriority(): void
    {
        $action1 = new BatchAction(name: 'archive', label: 'Archive', priority: 30);
        $action2 = new BatchAction(name: 'publish', label: 'Publish', priority: 10);

        /** @var BatchActionProviderInterface&MockObject $provider */
        $provider = $this->createMock(BatchActionProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('getActions')->willReturn([$action1, $action2]);
        $provider->method('getPriority')->willReturn(0);

        $actions = $this->makeRegistry([$provider])->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertSame('publish', $actions[0]->name);
        $this->assertSame('archive', $actions[1]->name);
    }

    #[Test]
    public function cachesResultsForSameEntityClass(): void
    {
        /** @var BatchActionProviderInterface&MockObject $provider */
        $provider = $this->createMock(BatchActionProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->expects($this->once())->method('getActions')->willReturn([
            new BatchAction(name: 'publish', label: 'Publish'),
        ]);
        $provider->method('getPriority')->willReturn(0);

        $registry = $this->makeRegistry([$provider]);

        $first = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type
        $second = $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type

        $this->assertSame($first, $second);
    }

    #[Test]
    public function clearCacheRemovesCachedResults(): void
    {
        $callCount = 0;

        /** @var BatchActionProviderInterface&MockObject $provider */
        $provider = $this->createMock(BatchActionProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('getActions')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            return [new BatchAction(name: 'publish', label: 'Publish')];
        });
        $provider->method('getPriority')->willReturn(0);

        $registry = $this->makeRegistry([$provider]);

        $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type
        $this->assertSame(1, $callCount);

        $registry->clearCache();
        $registry->getActions(self::PRODUCT_CLASS); // @phpstan-ignore argument.type
        $this->assertSame(2, $callCount); // @phpstan-ignore method.impossibleType
    }

    #[Test]
    public function skipsProvidersThatDontSupport(): void
    {
        /** @var BatchActionProviderInterface&MockObject $unsupportedProvider */
        $unsupportedProvider = $this->createMock(BatchActionProviderInterface::class);
        $unsupportedProvider->method('supports')->willReturn(false);
        $unsupportedProvider->expects($this->never())->method('getActions');
        $unsupportedProvider->method('getPriority')->willReturn(0);

        $registry = $this->makeRegistry([$unsupportedProvider]);
        $this->assertSame([], $registry->getActions(self::PRODUCT_CLASS)); // @phpstan-ignore argument.type
    }
}
