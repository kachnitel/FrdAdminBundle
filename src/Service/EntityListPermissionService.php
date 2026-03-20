<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Kachnitel\AdminBundle\Archive\ArchiveConfig;
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
     * Whether batch delete is allowed.
     */
    public function canBatchDelete(
        string $entityClass,
        string $entityShortClass,
        ?string $dataSourceId = null,
    ): bool {
        if ($entityClass !== '') {
            return $this->isBatchActionsEnabled($entityClass)
                && $this->security->isGranted(AdminEntityVoter::ADMIN_DELETE, $entityShortClass);
        }

        $identifier = $dataSourceId ?? $entityShortClass;

        return $this->security->isGranted(AdminEntityVoter::ADMIN_DELETE, $identifier);
    }

    /**
     * Whether the current user may open rows of this entity type for inline editing.
     */
    public function canInlineEdit(string $entityClass, string $entityShortClass): bool
    {
        if ($entityClass === '') {
            return false;
        }

        $adminAttr = $this->entityDiscovery->getAdminAttribute($entityClass);

        if ($adminAttr === null || !$adminAttr->isEnableInlineEdit()) {
            return false;
        }

        return $this->security->isGranted(AdminEntityVoter::ADMIN_EDIT, $entityShortClass);
    }

    /**
     * Whether the current user may toggle the archive filter for this entity.
     *
     * Returns false when archive is not configured (no ArchiveConfig).
     * When a role is required, the user must have that role.
     * When no role is required, any authenticated user may toggle.
     */
    public function canToggleArchive(?ArchiveConfig $archiveConfig): bool
    {
        if ($archiveConfig === null) {
            return false;
        }

        if ($archiveConfig->role !== null) {
            return $this->security->isGranted($archiveConfig->role);
        }

        // No role required — any authenticated user
        return $this->security->getUser() !== null;
    }

    /**
     * Whether the current user may view the list for the given identifier.
     */
    public function canViewList(string $identifier): bool
    {
        return $this->security->isGranted(AdminEntityVoter::ADMIN_INDEX, $identifier);
    }
}
