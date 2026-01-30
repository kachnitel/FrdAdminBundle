<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Registry that collects row actions from all providers and resolves the final action list.
 *
 * Actions are merged based on priority and the override flag:
 * - Without override: non-null properties from higher-priority providers merge into existing actions
 * - With override: completely replaces the existing action
 */
class RowActionRegistry
{
    /** @var array<RowActionProviderInterface>|null */
    private ?array $sortedProviders = null;

    /** @var array<string, array<RowAction>> */
    private array $cache = [];

    /**
     * @param iterable<RowActionProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly AttributeRowActionProvider $attributeProvider,
    ) {}

    /**
     * Get all actions for an entity class.
     *
     * @return array<RowAction>
     */
    public function getActions(string $entityClass): array
    {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        $this->ensureProvidersSorted();

        // Collect actions from all providers (sorted by priority)
        /** @var array<string, RowAction> $actionsByName */
        $actionsByName = [];

        /** @var array<string, RowAction> $overrides */
        $overrides = [];

        foreach ($this->sortedProviders as $provider) {
            if (!$provider->supports($entityClass)) {
                continue;
            }

            foreach ($provider->getActions($entityClass) as $action) {
                // Check if this is a full override (from attribute provider)
                if ($provider instanceof AttributeRowActionProvider) {
                    if ($this->attributeProvider->isOverride($entityClass, $action->name)) {
                        $overrides[$action->name] = $action;
                        continue;
                    }
                }

                // Merge or add the action
                if (isset($actionsByName[$action->name])) {
                    // Merge non-null properties from new action into existing
                    $actionsByName[$action->name] = $actionsByName[$action->name]->merge($action);
                } else {
                    $actionsByName[$action->name] = $action;
                }
            }
        }

        // Apply full overrides
        foreach ($overrides as $name => $action) {
            $actionsByName[$name] = $action;
        }

        // Apply AdminActionsConfig filters
        $config = $this->attributeProvider->getActionsConfig($entityClass);
        $actions = $this->applyConfig($actionsByName, $config);

        // Sort by priority
        usort($actions, fn (RowAction $a, RowAction $b) => $a->priority <=> $b->priority);

        $this->cache[$entityClass] = $actions;
        return $actions;
    }

    /**
     * Clear the cache (useful for testing).
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    private function ensureProvidersSorted(): void
    {
        if ($this->sortedProviders !== null) {
            return;
        }

        $providers = iterator_to_array($this->providers);
        usort($providers, fn ($a, $b) => $a->getPriority() <=> $b->getPriority());
        $this->sortedProviders = $providers;
    }

    /**
     * @param array<string, RowAction> $actionsByName
     * @return array<RowAction>
     */
    private function applyConfig(array $actionsByName, ?AdminActionsConfig $config): array
    {
        if ($config === null) {
            return array_values($actionsByName);
        }

        // Remove defaults if disabled
        if ($config->disableDefaults) {
            unset($actionsByName['show'], $actionsByName['edit']);
        }

        // Apply exclude filter
        if ($config->exclude !== null) {
            foreach ($config->exclude as $excluded) {
                unset($actionsByName[$excluded]);
            }
        }

        // Apply include filter (whitelist)
        if ($config->include !== null) {
            $actionsByName = array_filter(
                $actionsByName,
                fn (string $name) => in_array($name, $config->include, true),
                ARRAY_FILTER_USE_KEY
            );
        }

        return array_values($actionsByName);
    }
}
