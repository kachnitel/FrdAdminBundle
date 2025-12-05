<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Security;

use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security voter for admin entity access control.
 *
 * Supports attributes: ADMIN_INDEX, ADMIN_SHOW, ADMIN_NEW, ADMIN_EDIT, ADMIN_DELETE
 * Subject must be the entity short name (e.g., 'Product')
 *
 * Permission resolution order:
 * 1. Entity-specific permission from #[Admin(permissions: ['action' => 'ROLE_X'])]
 * 2. Global required_role configuration (default: 'ROLE_ADMIN')
 * 3. If required_role is null/false, grants access (authentication disabled)
 *
 * Usage in controllers:
 *   #[IsGranted('ADMIN_INDEX', subject: 'entityName')]
 *   #[IsGranted('ADMIN_EDIT', subject: 'entityName')]
 *
 * @extends Voter<string, string>
 */
class AdminEntityVoter extends Voter
{
    public const ADMIN_INDEX = 'ADMIN_INDEX';
    public const ADMIN_SHOW = 'ADMIN_SHOW';
    public const ADMIN_NEW = 'ADMIN_NEW';
    public const ADMIN_EDIT = 'ADMIN_EDIT';
    public const ADMIN_DELETE = 'ADMIN_DELETE';

    /**
     * Map voter attributes to Admin attribute action names.
     */
    private const ACTION_MAP = [
        self::ADMIN_INDEX => 'index',
        self::ADMIN_SHOW => 'show',
        self::ADMIN_NEW => 'new',
        self::ADMIN_EDIT => 'edit',
        self::ADMIN_DELETE => 'delete',
    ];

    public function __construct(
        private readonly EntityDiscoveryService $entityDiscovery,
        private readonly ?string $defaultRequiredRole,
        private readonly string $entityNamespace = 'App\\Entity\\',
        private readonly ?RoleHierarchyInterface $roleHierarchy = null,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Check if attribute is one we support
        if (!in_array($attribute, [
            self::ADMIN_INDEX,
            self::ADMIN_SHOW,
            self::ADMIN_NEW,
            self::ADMIN_EDIT,
            self::ADMIN_DELETE,
        ], true)) {
            return false;
        }

        // Subject must be a string (entity short name)
        return is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // Get the action name from our map
        $action = self::ACTION_MAP[$attribute];

        // Resolve required role for this entity and action
        $requiredRole = $this->getRequiredRole($subject, $action);

        // If no role is required (null), grant access (authentication disabled)
        if ($requiredRole === null) {
            return true;
        }

        $user = $token->getUser();

        // User must be logged in
        if (!$user instanceof UserInterface) {
            return false;
        }

        // Check if user has the required role
        return $this->hasRole($token, $requiredRole);
    }

    /**
     * Get the required role for a specific entity action.
     *
     * @param string $entityName Entity short name (e.g., 'Product')
     * @param string $action Action name ('index', 'show', 'new', 'edit', 'delete')
     * @return string|null Required role (e.g., 'ROLE_ADMIN') or null if authentication disabled
     */
    private function getRequiredRole(string $entityName, string $action): ?string
    {
        // Resolve full entity class name
        $entityClass = $this->entityDiscovery->resolveEntityClass($entityName, $this->entityNamespace);

        if (!$entityClass) {
            // Entity not found, use default role
            return $this->defaultRequiredRole;
        }

        // Get Admin attribute
        $adminAttr = $this->entityDiscovery->getAdminAttribute($entityClass);

        if (!$adminAttr) {
            // No Admin attribute, use default role
            return $this->defaultRequiredRole;
        }

        // Check for action-specific permission
        $actionPermission = $adminAttr->getPermissionForAction($action);

        if ($actionPermission !== null) {
            return $actionPermission;
        }

        // Fall back to default role
        return $this->defaultRequiredRole;
    }

    /**
     * Check if user has the specified role.
     * Uses RoleHierarchy if available to support role inheritance (e.g., ROLE_ADMIN includes ROLE_USER).
     */
    private function hasRole(TokenInterface $token, string $role): bool
    {
        // If role hierarchy is available, use it to get all reachable roles
        if ($this->roleHierarchy !== null) {
            $reachableRoles = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());
            return in_array($role, $reachableRoles, true);
        }

        // Fall back to simple string comparison (no hierarchy support)
        foreach ($token->getRoleNames() as $tokenRole) {
            if ($tokenRole === $role) {
                return true;
            }
        }

        return false;
    }
}
