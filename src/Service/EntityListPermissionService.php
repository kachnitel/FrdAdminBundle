<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Service for checking permissions and capabilities for entity lists.
 */
class EntityListPermissionService
{
    public function __construct(
        private EntityDiscoveryService $entityDiscovery,
        private Security $security
    ) {}

    /**
     * Whether batch actions are enabled for this entity.
     */
    public function isBatchActionsEnabled(string $entityClass): bool
    {
        $adminAttr = $this->entityDiscovery->getAdminAttribute($entityClass);
        return $adminAttr?->isEnableBatchActions() ?? false;
    }

    /**
     * Whether to show batch delete button.
     */
    public function canBatchDelete(string $entityClass, string $entityShortClass): bool
    {
        return $this->isBatchActionsEnabled($entityClass)
            && $this->security->isGranted(AdminEntityVoter::ADMIN_DELETE, $entityShortClass);
    }
}
