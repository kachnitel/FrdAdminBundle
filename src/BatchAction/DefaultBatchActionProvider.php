<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\BatchAction;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\ValueObject\BatchAction;

/**
 * Provides the default batch delete action for all entities.
 *
 * This replaces the previous built-in batch delete behavior,
 * now integrated into the provider system for consistency and extensibility.
 *
 * Priority 0 (lowest) so custom providers can override with priority > 0.
 */
class DefaultBatchActionProvider implements BatchActionProviderInterface
{
    public function supports(string $entityClass): bool
    {
        return true;
    }

    /**
     * @return array<BatchAction>
     */
    public function getActions(string $entityClass): array
    {
        return [
            new BatchAction(
                name: 'batch_delete',
                label: 'Delete Selected',
                icon: '🗑️',
                liveAction: 'batchDelete',
                voterAttribute: AdminEntityVoter::ADMIN_DELETE,
                confirmMessage: 'Are you sure you want to delete the selected %count% items?',
                priority: 0,
            ),
        ];
    }

    public function getPriority(): int
    {
        return 0;  // Lowest priority - custom providers should use higher values
    }
}
