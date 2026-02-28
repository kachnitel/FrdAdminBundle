<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Replaces the default page-navigation edit action with an inline edit component.
 *
 * Priority 15 sits between DefaultRowActionProvider (0) and AttributeRowActionProvider (50),
 * so the merge order is: Default → InlineEdit (merge adds liveComponent) → Attribute (user overrides).
 *
 * The RowAction produced here intentionally omits priority (DEFAULT_PRIORITY = "unset")
 * so that RowAction::merge() preserves DefaultRowActionProvider's explicit priority of 20.
 *
 * voterAttribute: ADMIN_EDIT is set so RowActionRuntime hides the button for users
 * without edit permission, using a direct voter check (not isActionAccessible) because
 * component actions have no route or form to check.
 */
class InlineEditRowActionProvider implements RowActionProviderInterface
{
    public function supports(string $entityClass): bool
    {
        return true;
    }

    /**
     * @return array<RowAction>
     */
    public function getActions(string $entityClass): array
    {
        return [
            new RowAction(
                name: 'edit',
                label: 'Edit',
                icon: '✏️',
                voterAttribute: AdminEntityVoter::ADMIN_EDIT,
                liveComponent: 'K:Admin:RowAction:InlineEdit',
                // priority intentionally omitted — merge() keeps DefaultRowActionProvider's 20
            ),
        ];
    }

    public function getPriority(): int
    {
        return 15;
    }
}
