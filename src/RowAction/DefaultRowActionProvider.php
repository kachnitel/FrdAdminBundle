<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Provides default show/edit actions for all entities.
 *
 * These are the actions that were previously hardcoded in the template.
 * Priority is 0 (lowest) so custom providers can add/merge actions.
 */
class DefaultRowActionProvider implements RowActionProviderInterface
{
    public function supports(string $entityClass): bool
    {
        return true; // Applies to all entities
    }

    /**
     * @return array<RowAction>
     */
    public function getActions(string $entityClass): array
    {
        return [
            new RowAction(
                name: 'show',
                label: 'Show',
                icon: '👀',
                route: null, // Uses admin_object_path automatic route resolution
                voterAttribute: AdminEntityVoter::ADMIN_SHOW,
                priority: 10,
            ),
            new RowAction(
                name: 'edit',
                label: 'Edit',
                icon: '🖊',
                route: null,
                voterAttribute: AdminEntityVoter::ADMIN_EDIT,
                priority: 20,
            ),
        ];
    }

    public function getPriority(): int
    {
        return 0;
    }
}
