<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\ValueObject\RowAction;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for services that provide row actions.
 *
 * Implement this interface to add custom actions programmatically.
 * Services implementing this interface are auto-discovered via autoconfigure.
 *
 * Example:
 * class ProductRowActionProvider implements RowActionProviderInterface
 * {
 *     public function supports(string $entityClass): bool
 *     {
 *         return $entityClass === Product::class;
 *     }
 *
 *     public function getActions(string $entityClass): array
 *     {
 *         return [
 *             new RowAction(name: 'duplicate', label: 'Duplicate', route: 'app_product_duplicate', priority: 30),
 *         ];
 *     }
 *
 *     public function getPriority(): int { return 50; }
 * }
 */
#[AutoconfigureTag('kachnitel_admin.row_action_provider')]
interface RowActionProviderInterface
{
    /**
     * Whether this provider applies to the given entity class.
     *
     * @param class-string $entityClass
     */
    public function supports(string $entityClass): bool;

    /**
     * Get the actions provided by this service.
     *
     * @param class-string $entityClass
     * @return array<RowAction>
     */
    public function getActions(string $entityClass): array;

    /**
     * Provider priority. Lower values are processed first.
     * Default providers return 0; custom providers should return higher values.
     */
    public function getPriority(): int;
}
