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
     * Whether batch delete is allowed.
     *
     * For Doctrine entities (entityClass non-empty): checks #[Admin] attribute + ADMIN_DELETE voter.
     * For non-Doctrine data sources: checks ADMIN_DELETE voter on the identifier.
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
     *
     * Both conditions must pass:
     *   1. The entity has opted in via #[Admin(enableInlineEdit: true)] — cheap flag check first.
     *   2. The current user is granted ADMIN_EDIT — security gate.
     *
     * Always returns false for non-Doctrine data sources (empty entityClass).
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
     * Whether the current user may view the list for the given identifier.
     *
     * The identifier is the dataSourceId when present, otherwise the entityShortClass.
     */
    public function canViewList(string $identifier): bool
    {
        return $this->security->isGranted(AdminEntityVoter::ADMIN_INDEX, $identifier);
    }
}
