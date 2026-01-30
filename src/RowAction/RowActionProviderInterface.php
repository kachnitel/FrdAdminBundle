<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\ValueObject\RowAction;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for services that provide row actions.
 *
 * Implement this interface to add custom actions programmatically.
 * The service will be auto-discovered via autoconfigure.
 */
#[AutoconfigureTag('kachnitel_admin.row_action_provider')]
interface RowActionProviderInterface
{
    /**
     * Check if this provider applies to a given entity class.
     *
     * @param class-string $entityClass
     */
    public function supports(string $entityClass): bool;

    /**
     * Get actions provided by this service.
     *
     * @param class-string $entityClass
     * @return array<RowAction>
     */
    public function getActions(string $entityClass): array;

    /**
     * Get priority (lower priority providers are processed first).
     * Default providers should return 0, custom providers return higher numbers.
     */
    public function getPriority(): int;
}
