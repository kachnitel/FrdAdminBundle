<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Kachnitel\AdminBundle\RowAction\RowActionRegistry;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for row action rendering.
 *
 * The actual visibility decision — class-level voter/route access,
 * object-level authorization, direct permission checks, and conditions —
 * lives in RowActionVisibilityChecker, extracted specifically to keep this
 * class's coupling and that logic's own cyclomatic complexity under
 * PHPMD's thresholds. This class is now just registry access plus a thin
 * delegate to the checker.
 *
 * @see RowActionVisibilityChecker
 */
class RowActionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RowActionRegistry $registry,
        private readonly RowActionVisibilityChecker $visibilityChecker,
    ) {}

    /**
     * Get all registered actions for an entity class (unfiltered).
     *
     * @param class-string|string $entityClass
     * @return array<RowAction>
     */
    public function getRowActions(string $entityClass): array
    {
        /** @var class-string $entityClass */
        return $this->registry->getActions($entityClass);
    }

    /**
     * Get actions visible for a specific entity instance.
     * Filters by permissions, voter attributes, and conditions.
     *
     * @param class-string|string $entityClass
     * @return array<RowAction>
     */
    public function getVisibleRowActions(string $entityClass, object $entity, string $entityShortClass, string $context = ''): array
    {
        /** @var class-string $entityClass */
        $visible = [];

        foreach ($this->registry->getActions($entityClass, $context) as $action) {
            if ($this->isActionVisible($action, $entity, $entityShortClass)) {
                $visible[] = $action;
            }
        }

        return $visible;
    }

    /**
     * Check if a single action should be visible for a specific entity.
     */
    public function isActionVisible(RowAction $action, object $entity, string $entityShortClass): bool
    {
        return $this->visibilityChecker->isVisible($action, $entity, $entityShortClass);
    }
}
