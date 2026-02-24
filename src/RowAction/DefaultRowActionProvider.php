<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Provides the default show/edit actions for all entities.
 *
 * These replace the actions that were previously hardcoded in _RowActions.html.twig.
 * Priority 0 (lowest) means custom providers can extend or merge on top of these.
 */
class DefaultRowActionProvider implements RowActionProviderInterface
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
                name: 'show',
                label: 'Show',
                icon: '👀',
                route: null, // null = admin_object_path auto-resolution in template
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
