<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Provides actions defined via #[AdminAction] attributes on entity classes.
 */
class AttributeRowActionProvider implements RowActionProviderInterface
{
    /** @var array<string, array<RowAction>> */
    private array $actionsCache = [];

    /** @var array<string, AdminActionsConfig|null> */
    private array $configCache = [];

    /** @var array<string, array{action: AdminAction, rowAction: RowAction}> */
    private array $adminActionsCache = [];

    public function supports(string $entityClass): bool
    {
        return class_exists($entityClass);
    }

    /**
     * @return array<RowAction>
     */
    public function getActions(string $entityClass): array
    {
        if (isset($this->actionsCache[$entityClass])) {
            return $this->actionsCache[$entityClass];
        }

        $actions = [];

        try {
            $reflectionClass = new \ReflectionClass($entityClass);
            $attributes = $reflectionClass->getAttributes(AdminAction::class);

            foreach ($attributes as $attribute) {
                /** @var AdminAction $adminAction */
                $adminAction = $attribute->newInstance();

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
                );

                $actions[] = $rowAction;

                // Cache the AdminAction attribute for override checking
                $this->adminActionsCache[$entityClass . '::' . $adminAction->name] = [
                    'action' => $adminAction,
                    'rowAction' => $rowAction,
                ];
            }
        } catch (\ReflectionException) {
            // Entity class doesn't exist, return empty
        }

        $this->actionsCache[$entityClass] = $actions;
        return $actions;
    }

    public function getPriority(): int
    {
        return 50; // Higher than default, actions can override defaults
    }

    /**
     * Get AdminActionsConfig for an entity class.
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

            if (!empty($attributes)) {
                /** @var AdminActionsConfig $config */
                $config = $attributes[0]->newInstance();
            }
        } catch (\ReflectionException) {
            // Entity class doesn't exist
        }

        $this->configCache[$entityClass] = $config;
        return $config;
    }

    /**
     * Get the AdminAction attribute for a specific action name.
     */
    public function getAdminActionAttribute(string $entityClass, string $actionName): ?AdminAction
    {
        // Ensure actions are loaded
        $this->getActions($entityClass);

        $cacheKey = $entityClass . '::' . $actionName;
        return $this->adminActionsCache[$cacheKey]['action'] ?? null;
    }

    /**
     * Check if an action has the override flag set.
     */
    public function isOverride(string $entityClass, string $actionName): bool
    {
        $adminAction = $this->getAdminActionAttribute($entityClass, $actionName);
        return $adminAction !== null && $adminAction->override;
    }
}
