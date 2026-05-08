<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Kachnitel\AdminBundle\BatchAction\BatchActionRegistry;
use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for batch action rendering.
 */
class BatchActionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly BatchActionRegistry $registry,
        private readonly ?AuthorizationCheckerInterface $authChecker = null,
    ) {}

    /**
     * Get all batch actions visible to the current user for the given entity class.
     *
     * @param class-string|string $entityClass
     * @return array<BatchAction>
     */
    public function getVisibleBatchActions(string $entityClass): array
    {
        /** @var class-string $entityClass */
        $visible = [];

        foreach ($this->registry->getActions($entityClass) as $action) {
            if ($this->isBatchActionVisible($action, $entityClass)) {
                $visible[] = $action;
            }
        }

        return $visible;
    }

    /**
     * @param class-string|string $entityClass
     */
    private function isBatchActionVisible(BatchAction $action, string $entityClass): bool
    {
        if ($this->authChecker === null) {
            return true;
        }

        // Direct role check
        if ($action->permission !== null) {
            if (!$this->authChecker->isGranted($action->permission)) {
                return false;
            }
        }

        // Voter attribute check
        if ($action->voterAttribute !== null) {
            $parts = explode('\\', ltrim($entityClass, '\\'));
            $shortClass = end($parts);
            if (!$this->authChecker->isGranted($action->voterAttribute, $shortClass)) {
                return false;
            }
        }

        return true;
    }
}
