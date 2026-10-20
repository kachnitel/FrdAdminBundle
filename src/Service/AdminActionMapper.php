<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Single place that translates a #[AdminAction] attribute into its runtime value objects.
 *
 * Both attribute providers delegate here so a field added to AdminAction (icon, cssClass, …)
 * only has to be mapped once per target type instead of once per provider.
 */
final class AdminActionMapper
{
    public function toRowAction(AdminAction $action): RowAction
    {
        return new RowAction(
            name: $action->name,
            label: $action->label,
            icon: $action->icon,
            route: $action->route,
            routeParams: $action->routeParams,
            url: $action->url,
            permission: $action->permission,
            voterAttribute: $action->voterAttribute,
            condition: $action->condition,
            cssClass: $action->cssClass,
            confirmMessage: $action->confirmMessage,
            openInNewTab: $action->openInNewTab,
            priority: $action->priority,
            method: $action->method,
            template: $action->template,
            liveComponent: $action->liveComponent,
            contexts: $action->contexts,
        );
    }

    public function toBatchAction(AdminAction $action): BatchAction
    {
        return new BatchAction(
            name: $action->name,
            label: $action->label,
            icon: $action->icon,
            route: $action->route,
            url: $action->url,
            liveComponent: $action->liveComponent,
            permission: $action->permission,
            voterAttribute: $action->voterAttribute,
            cssClass: $action->cssClass,
            confirmMessage: $action->confirmMessage,
            priority: $action->priority,
        );
    }
}
