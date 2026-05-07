<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\BatchAction;

use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Provide batch actions for entities.
 *
 * Implement this interface to contribute custom batch actions for any entity.
 * Services are auto-discovered via #[AutoconfigureTag] and sorted by priority.
 *
 * Example:
 *
 *   class ProductBatchActionProvider implements BatchActionProviderInterface
 *   {
 *       public function supports(string $entityClass): bool
 *       {
 *           return $entityClass === Product::class;
 *       }
 *
 *       public function getActions(string $entityClass): array
 *       {
 *           return [
 *               new BatchAction(
 *                   name: 'bulk-publish',
 *                   label: 'Publish Selected',
 *                   icon: '🚀',
 *                   liveAction: 'bulkPublish',
 *                   voterAttribute: AdminEntityVoter::ADMIN_EDIT,
 *                   confirmMessage: 'Publish %count% items?',
 *               ),
 *           ];
 *       }
 *   }
 *
 * @see BatchActionRegistry For how providers are discovered and merged
 */
#[AutoconfigureTag('kachnitel_admin.batch_action_provider')]
interface BatchActionProviderInterface
{
    /**
     * Whether this provider applies to the given entity class.
     *
     * @param class-string $entityClass
     */
    public function supports(string $entityClass): bool;

    /**
     * Get batch actions for the entity class.
     *
     * @param class-string $entityClass
     * @return array<BatchAction>
     */
    public function getActions(string $entityClass): array;

    /**
     * Sort priority for provider discovery.
     * Lower values are processed first (like RowActionProviderInterface).
     *
     * @return int Default 0. Custom providers should use positive values to override defaults.
     */
    public function getPriority(): int;
}
