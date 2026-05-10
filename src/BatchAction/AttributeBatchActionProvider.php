<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\BatchAction;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\ValueObject\BatchAction;

/**
 * Provides batch actions declared via #[AdminAction] attributes on entity classes.
 *
 * Only reads actions with `actionType` set to `AdminAction::ACTION_TYPE_BATCH`
 * or `AdminAction::ACTION_TYPE_BOTH`.
 */
class AttributeBatchActionProvider implements BatchActionProviderInterface
{
    /** @var array<string, array<BatchAction>> */
    private array $cache = [];

    public function supports(string $entityClass): bool
    {
        return class_exists($entityClass);
    }

    /**
     * @param class-string $entityClass
     * @return array<BatchAction>
     */
    public function getActions(string $entityClass): array
    {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        $actions = [];

        try {
            $reflectionClass = new \ReflectionClass($entityClass);
            $attributes = $reflectionClass->getAttributes(AdminAction::class);

            foreach ($attributes as $attribute) {
                /** @var AdminAction $adminAction */
                $adminAction = $attribute->newInstance();

                if (!$this->isBatchType($adminAction->actionType)) {
                    continue;
                }

                $actions[] = new BatchAction(
                    name: $adminAction->name,
                    label: $adminAction->label,
                    icon: $adminAction->icon,
                    route: $adminAction->route,
                    url: $adminAction->url,
                    liveComponent: $adminAction->liveComponent,
                    permission: $adminAction->permission,
                    voterAttribute: $adminAction->voterAttribute,
                    cssClass: $adminAction->cssClass,
                    confirmMessage: $adminAction->confirmMessage,
                    priority: $adminAction->priority,
                );
            }
        } catch (\ReflectionException) {
            // Class not found — return empty
        }

        $this->cache[$entityClass] = $actions;
        return $actions;
    }

    public function getPriority(): int
    {
        return 50;
    }

    private function isBatchType(?string $actionType): bool
    {
        return $actionType === AdminAction::ACTION_TYPE_BATCH
            || $actionType === AdminAction::ACTION_TYPE_BOTH;
    }
}
