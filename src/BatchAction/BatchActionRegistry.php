<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\BatchAction;

use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Collects batch actions from all providers and resolves the final action list.
 *
 * Actions from all providers that support the entity class are merged and sorted
 * by priority (lower = earlier).
 */
class BatchActionRegistry
{
    /** @var array<BatchActionProviderInterface>|null */
    private ?array $sortedProviders = null;

    /** @var array<string, array<BatchAction>> */
    private array $cache = [];

    /**
     * @param iterable<BatchActionProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(BatchActionProviderInterface::class)]
        private readonly iterable $providers,
    ) {}

    /**
     * Get all batch actions for the given entity class, sorted by priority.
     *
     * @param class-string $entityClass
     * @return array<BatchAction>
     */
    public function getActions(string $entityClass): array
    {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        $this->ensureProvidersSorted();

        $actions = [];

        /** @var array<BatchActionProviderInterface> $sortedProviders */
        $sortedProviders = $this->sortedProviders;

        foreach ($sortedProviders as $provider) {
            if (!$provider->supports($entityClass)) {
                continue;
            }

            foreach ($provider->getActions($entityClass) as $action) {
                $actions[] = $action;
            }
        }

        usort($actions, fn (BatchAction $a, BatchAction $b): int => $a->priority <=> $b->priority);

        $this->cache[$entityClass] = $actions;
        return $actions;
    }

    /**
     * Clear the cached action lists. Useful in tests.
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->sortedProviders = null;
    }

    private function ensureProvidersSorted(): void
    {
        if ($this->sortedProviders !== null) {
            return;
        }

        $providers = iterator_to_array($this->providers);
        usort($providers, fn ($a, $b): int => $a->getPriority() <=> $b->getPriority());
        $this->sortedProviders = $providers;
    }
}
