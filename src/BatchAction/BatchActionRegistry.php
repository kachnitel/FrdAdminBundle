<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\BatchAction;

use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Registry for batch actions.
 *
 * Discovers batch action providers via #[AutowireIterator], manages merging and override logic,
 * and caches results per entity class.
 *
 * Merge strategy: non-null properties from higher-priority providers merge into existing actions.
 * Priority: Lower values are processed first (lower override priority).
 *           Higher values are processed last (higher override priority).
 */
class BatchActionRegistry
{
    /** @var array<BatchActionProviderInterface>|null */
    private ?array $sortedProviders = null;

    /** @var array<string, array<string, BatchAction>> */
    private array $cache = [];

    /**
     * @param iterable<BatchActionProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(BatchActionProviderInterface::class)]
        private readonly iterable $providers,
    ) {}

    /**
     * Get batch actions for an entity class, merging from all providers.
     *
     * @param class-string $entityClass
     * @return array<string, BatchAction> Actions keyed by name
     */
    public function getActions(string $entityClass): array
    {
        $cacheKey = $entityClass;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $this->ensureProvidersSorted();

        /** @var array<string, BatchAction> $actionsByName */
        $actionsByName = [];

        foreach ($this->sortedProviders as $provider) { // @phpstan-ignore foreach.nonIterable
            if (!$provider->supports($entityClass)) {
                continue;
            }

            foreach ($provider->getActions($entityClass) as $action) {
                if (isset($actionsByName[$action->name])) {
                    // Merge: higher-priority provider's action overrides lower-priority
                    $actionsByName[$action->name] = $actionsByName[$action->name]->merge($action);
                } else {
                    $actionsByName[$action->name] = $action;
                }
            }
        }

        // Sort by priority (lower = earlier)
        uasort($actionsByName, static fn (BatchAction $a, BatchAction $b) => $a->priority <=> $b->priority);

        $this->cache[$cacheKey] = $actionsByName;

        return $actionsByName;
    }

    /**
     * Get a single batch action by name for an entity class.
     *
     * @param class-string $entityClass
     */
    public function getAction(string $entityClass, string $actionName): ?BatchAction
    {
        $actions = $this->getActions($entityClass);
        return $actions[$actionName] ?? null;
    }

    /**
     * Check if an entity class has any batch actions.
     *
     * @param class-string $entityClass
     */
    public function hasActions(string $entityClass): bool
    {
        return !empty($this->getActions($entityClass));
    }

    /**
     * Sort providers by priority.
     * Lower values (negative) are processed first, so they have lower priority in merges.
     * Higher values (positive) are processed last, so they override lower-priority providers.
     */
    private function ensureProvidersSorted(): void
    {
        if ($this->sortedProviders !== null) {
            return;
        }

        $providers = $this->providers instanceof \Traversable ? iterator_to_array($this->providers) : $this->providers;

        usort(
            $providers,
            static fn (BatchActionProviderInterface $a, BatchActionProviderInterface $b) => $a->getPriority() <=> $b->getPriority()
        );

        $this->sortedProviders = $providers;
    }
}
