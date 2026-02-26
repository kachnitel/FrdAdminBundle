<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Collects row actions from all providers and resolves the final action list for an entity.
 *
 * Merge strategy (per action name):
 *  - Without override flag: non-null properties from higher-priority providers merge into existing actions
 *  - With override flag (#[AdminAction(override: true)]): completely replaces the existing action
 *
 * Final filtering is applied via #[AdminActionsConfig] (disableDefaults, exclude, include).
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
        #[AutowireIterator(RowActionProviderInterface::class)]
        private readonly iterable $providers,
        private readonly AttributeRowActionProvider $attributeProvider,
    ) {}

    /**
     * Get the final resolved action list for an entity class.
     *
     * @param class-string $entityClass
     * @return array<RowAction>
     */
    public function getActions(string $entityClass): array
    {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        $this->ensureProvidersSorted();

        /** @var array<string, RowAction> $actionsByName */
        $actionsByName = [];

        /** @var array<string, RowAction> $overrides */
        $overrides = [];

        /** @var array<RowActionProviderInterface> $sortedProviders */
        $sortedProviders = $this->sortedProviders;

        foreach ($sortedProviders as $provider) {
            if (!$provider->supports($entityClass)) {
                continue;
            }

            foreach ($provider->getActions($entityClass) as $action) {
                // Full override: collect separately, applied after merging
                if ($provider instanceof AttributeRowActionProvider
                    && $this->attributeProvider->isOverride($entityClass, $action->name)) {
                    $overrides[$action->name] = $action;
                    continue;
                }

                if (isset($actionsByName[$action->name])) {
                    $actionsByName[$action->name] = $actionsByName[$action->name]->merge($action);
                } else {
                    $actionsByName[$action->name] = $action;
                }
            }
        }

        foreach ($overrides as $name => $action) {
            $actionsByName[$name] = $action;
        }

        $config = $this->attributeProvider->getActionsConfig($entityClass);
        $actions = $this->applyConfig($actionsByName, $config);

        usort($actions, fn (RowAction $a, RowAction $b) => $a->priority <=> $b->priority);

        $this->cache[$entityClass] = $actions;
        return $actions;
    }

    /**
     * Clear the resolved action cache (useful in tests).
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

        if ($config->disableDefaults) {
            unset($actionsByName['show'], $actionsByName['edit']);
        }

        if ($config->exclude !== null) {
            foreach ($config->exclude as $name) {
                unset($actionsByName[$name]);
            }
        }

        if ($config->include !== null) {
            $actionsByName = array_filter(
                $actionsByName,
                fn (string $name) => in_array($name, $config->include, true),
                ARRAY_FILTER_USE_KEY,
            );
        }

        return array_values($actionsByName);
    }
}
