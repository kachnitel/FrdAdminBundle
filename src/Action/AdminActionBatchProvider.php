<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Action;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\BatchAction\BatchActionProviderInterface;
use Kachnitel\AdminBundle\ValueObject\BatchAction;

/**
 * Provides batch actions declared via #[AdminAction] attributes.
 *
 * Filters AdminAction attributes where type is TYPE_BATCH or TYPE_BOTH.
 * Replaces AttributeBatchActionProvider for actions defined with the unified AdminAction attribute.
 *
 * This provider reads the new AdminAction attribute (which supports both row and batch actions)
 * and provides only batch actions to the BatchActionRegistry.
 */
class AdminActionBatchProvider implements BatchActionProviderInterface
{
    /** @var array<string, array<BatchAction>> */
    private array $batchActionsCache = [];

    public function supports(string $entityClass): bool
    {
        return class_exists($entityClass);
    }

    /**
     * Get batch actions for an entity class.
     * Filters AdminAction attributes where type is TYPE_BATCH or TYPE_BOTH.
     *
     * @return array<BatchAction>
     */
    public function getActions(string $entityClass): array
    {
        if (isset($this->batchActionsCache[$entityClass])) {
            return $this->batchActionsCache[$entityClass];
        }

        $actions = [];

        try {
            $reflectionClass = new \ReflectionClass($entityClass);
            $attributes = $reflectionClass->getAttributes(AdminAction::class);

            foreach ($attributes as $attribute) {
                /** @var AdminAction $adminAction */
                $adminAction = $attribute->newInstance();

                // Skip if this is a row-only action
                if ($adminAction->type === AdminAction::TYPE_ROW) {
                    continue;
                }

                // Validate batch action configuration
                $this->validateBatchAction($entityClass, $adminAction);

                $batchAction = new BatchAction(
                    name: $adminAction->name,
                    label: $adminAction->label,
                    icon: $adminAction->icon,
                    liveAction: $adminAction->batchLiveAction,
                    route: $adminAction->route,
                    url: $adminAction->url,
                    permission: $adminAction->permission,
                    voterAttribute: $adminAction->voterAttribute,
                    condition: $adminAction->condition,
                    cssClass: $adminAction->cssClass,
                    confirmMessage: $adminAction->confirmMessage,
                    openInNewTab: $adminAction->openInNewTab,
                    priority: $adminAction->priority,
                );

                $actions[] = $batchAction;
            }
        } catch (\ReflectionException) {
            // Class not found — return empty
        }

        $this->batchActionsCache[$entityClass] = $actions;
        return $actions;
    }

    public function getPriority(): int
    {
        return 50;
    }

    /**
     * Validate batch action configuration to catch common misconfigurations.
     */
    private function validateBatchAction(string $entityClass, AdminAction $action): void
    {
        // At least one handler must be specified
        if ($action->batchLiveAction === null && $action->route === null && $action->url === null) {
            throw new \InvalidArgumentException(
                "Batch action '{$action->name}' on {$entityClass} must specify one of: "
                . "batchLiveAction, route, or url"
            );
        }

        // LiveAction and route are mutually exclusive in practice
        if ($action->batchLiveAction !== null && $action->route !== null) {
            trigger_error(
                "Batch action '{$action->name}' on {$entityClass} specifies both batchLiveAction "
                . "and route; route will be ignored when batchLiveAction is present",
                E_USER_WARNING
            );
        }
    }
}
