<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Action;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;
use Kachnitel\AdminBundle\RowAction\RowActionProviderInterface;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Provides row actions declared via #[AdminAction] attributes.
 *
 * Filters AdminAction attributes where type is TYPE_ROW or TYPE_BOTH.
 * Replaces AttributeRowActionProvider for actions defined with the unified AdminAction attribute.
 *
 * This provider reads the new AdminAction attribute (which supports both row and batch actions)
 * and provides only row actions to the RowActionRegistry.
 */
class AdminActionRowProvider implements RowActionProviderInterface
{
    /** @var array<string, array<RowAction>> */
    private array $rowActionsCache = [];

    /** @var array<string, AdminActionsConfig|null> */
    private array $configCache = [];

    public function supports(string $entityClass): bool
    {
        return class_exists($entityClass);
    }

    /**
     * Get row actions for an entity class.
     * Filters AdminAction attributes where type is TYPE_ROW or TYPE_BOTH.
     *
     * @return array<RowAction>
     */
    public function getActions(string $entityClass): array
    {
        if (isset($this->rowActionsCache[$entityClass])) {
            return $this->rowActionsCache[$entityClass];
        }

        $actions = [];

        try {
            $reflectionClass = new \ReflectionClass($entityClass);
            $attributes = $reflectionClass->getAttributes(AdminAction::class);

            foreach ($attributes as $attribute) {
                /** @var AdminAction $adminAction */
                $adminAction = $attribute->newInstance();

                // Skip if this is a batch-only action
                if ($adminAction->type === AdminAction::TYPE_BATCH) {
                    continue;
                }

                $rowAction = new RowAction(
                    name: $adminAction->name,
                    label: $adminAction->label,
                    icon: $adminAction->icon,
                    route: $adminAction->route,
                    routeParams: $adminAction->routeParams,
                    url: $adminAction->url,
                    permission: $adminAction->permission,
                    voterAttribute: $adminAction->voterAttribute,
                    condition: $adminAction->condition,
                    cssClass: $adminAction->cssClass,
                    confirmMessage: $adminAction->confirmMessage,
                    openInNewTab: $adminAction->openInNewTab,
                    priority: $adminAction->priority,
                    method: $adminAction->method,
                    template: $adminAction->template,
                    liveComponent: $adminAction->liveComponent,
                    contexts: $adminAction->contexts,
                );

                $actions[] = $rowAction;
            }
        } catch (\ReflectionException) {
            // Class not found — return empty
        }

        $this->rowActionsCache[$entityClass] = $actions;
        return $actions;
    }

    public function getPriority(): int
    {
        return 50;
    }

    /**
     * Get the #[AdminActionsConfig] attribute for an entity class, or null if absent.
     *
     * @param class-string $entityClass
     */
    public function getActionsConfig(string $entityClass): ?AdminActionsConfig
    {
        if (array_key_exists($entityClass, $this->configCache)) {
            return $this->configCache[$entityClass];
        }

        $config = null;

        try {
            $reflectionClass = new \ReflectionClass($entityClass);
            $attributes = $reflectionClass->getAttributes(AdminActionsConfig::class);

            if (count($attributes) > 0) {
                $config = $attributes[0]->newInstance();
            }
        } catch (\ReflectionException) {
            // Class not found
        }

        $this->configCache[$entityClass] = $config;
        return $config;
    }
}
