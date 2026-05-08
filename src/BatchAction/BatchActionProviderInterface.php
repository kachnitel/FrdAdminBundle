<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\BatchAction;

use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for services that provide batch actions for entity lists.
 *
 * Implement this interface to add custom batch action buttons programmatically.
 * Services implementing this interface are auto-discovered via autoconfigure.
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
 *                   route: 'app_product_bulk_publish',
 *                   confirmMessage: 'Publish %count% items?',
 *                   priority: 30,
 *               ),
 *           ];
 *       }
 *
 *       public function getPriority(): int { return 50; }
 *   }
 */
#[AutoconfigureTag]
interface BatchActionProviderInterface
{
    /**
     * Whether this provider applies to the given entity class.
     *
     * @param class-string $entityClass
     */
    public function supports(string $entityClass): bool;

    /**
     * Get the batch actions provided by this service.
     *
     * @param class-string $entityClass
     * @return array<BatchAction>
     */
    public function getActions(string $entityClass): array;

    /**
     * Provider priority. Lower values are processed first.
     */
    public function getPriority(): int;
}
