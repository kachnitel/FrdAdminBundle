<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Provides row actions declared via #[AdminAction] attributes on entity classes.
 */
class AttributeRowActionProvider implements RowActionProviderInterface
{
    /** @var array<string, array<RowAction>> */
    private array $actionsCache = [];

    /** @var array<string, AdminActionsConfig|null> */
    private array $configCache = [];

    /** @var array<string, AdminAction> */
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
                    liveComponent: $adminAction->liveComponent,
                );

                $actions[] = $rowAction;
                $this->adminActionsCache[$entityClass . '::' . $adminAction->name] = $adminAction;
            }
        } catch (\ReflectionException) {
            // Class not found — return empty
        }

        $this->actionsCache[$entityClass] = $actions;
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

            if (!empty($attributes)) {
                /** @var AdminActionsConfig $config */
                $config = $attributes[0]->newInstance();
            }
        } catch (\ReflectionException) {
            // Class not found
        }

        $this->configCache[$entityClass] = $config;
        return $config;
    }

    /**
     * Get the raw #[AdminAction] attribute instance for a specific action name, or null if absent.
     *
     * @param class-string $entityClass
     */
    public function getAdminActionAttribute(string $entityClass, string $actionName): ?AdminAction
    {
        $this->getActions($entityClass); // ensure cache is populated
        return $this->adminActionsCache[$entityClass . '::' . $actionName] ?? null;
    }

    /**
     * Whether the #[AdminAction] for the given entity+action name has override: true.
     *
     * @param class-string $entityClass
     */
    public function isOverride(string $entityClass, string $actionName): bool
    {
        $adminAction = $this->getAdminActionAttribute($entityClass, $actionName);
        return $adminAction !== null && $adminAction->override;
    }
}
