<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Security;

use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security voter for admin entity access control.
 *
 * Supports attributes: ADMIN_INDEX, ADMIN_SHOW, ADMIN_NEW, ADMIN_EDIT, ADMIN_ARCHIVE, ADMIN_DELETE
 * Subject must be the entity short name (e.g., 'Product')
 *
 * Permission resolution order:
 * 1. Entity-specific permission from #[Admin(permissions: ['action' => 'ROLE_X'])]
 * 2. Global required_role configuration (default: 'ROLE_ADMIN')
 * 3. If required_role is null, grants access (authentication disabled)
 *
 * Usage in controllers:
 *   #[IsGranted('ADMIN_INDEX', subject: 'entityName')]
 *   #[IsGranted('ADMIN_ARCHIVE', subject: 'entityName')]
 *
 * @extends Voter<string, string>
 */
class AdminEntityVoter extends Voter
{
    public const ADMIN_INDEX = 'ADMIN_INDEX';
    public const ADMIN_SHOW = 'ADMIN_SHOW';
    public const ADMIN_NEW = 'ADMIN_NEW';
    public const ADMIN_EDIT = 'ADMIN_EDIT';
    public const ADMIN_ARCHIVE = 'ADMIN_ARCHIVE';
    public const ADMIN_DELETE = 'ADMIN_DELETE';

    /**
     * Map voter attributes to Admin attribute action names.
     */
    private const ACTION_MAP = [
        self::ADMIN_INDEX   => 'index',
        self::ADMIN_SHOW    => 'show',
        self::ADMIN_NEW     => 'new',
        self::ADMIN_EDIT    => 'edit',
        self::ADMIN_ARCHIVE => 'archive',
        self::ADMIN_DELETE  => 'delete',
    ];

    public function __construct(
        private readonly EntityDiscoveryService $entityDiscovery,
        private readonly ?string $defaultRequiredRole,
        private readonly string $entityNamespace = 'App\\Entity\\',
        private readonly ?AccessDecisionManagerInterface $decisionManager = null
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [
            self::ADMIN_INDEX,
            self::ADMIN_SHOW,
            self::ADMIN_NEW,
            self::ADMIN_EDIT,
            self::ADMIN_ARCHIVE,
            self::ADMIN_DELETE,
        ], true)) {
            return false;
        }

        return is_string($subject);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("vote"))
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $action = self::ACTION_MAP[$attribute];
        $requiredRole = $this->getRequiredRole($subject, $action);

        if ($requiredRole === null) {
            return true;
        }

        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        return $this->hasRole($token, $requiredRole);
    }

    /**
     * Get the required role for a specific entity action.
     *
     * @param string $entityName Entity short name (e.g., 'Product')
     * @param string $action Action name ('index', 'show', 'new', 'edit', 'archive', 'delete')
     * @return string|null Required role or null if authentication disabled
     */
    private function getRequiredRole(string $entityName, string $action): ?string
    {
        $entityClass = $this->entityDiscovery->resolveEntityClass($entityName, $this->entityNamespace);

        if (!$entityClass) {
            return $this->defaultRequiredRole;
        }

        $adminAttr = $this->entityDiscovery->getAdminAttribute($entityClass);

        if (!$adminAttr) {
            return $this->defaultRequiredRole;
        }

        $actionPermission = $adminAttr->getPermissionForAction($action);

        if ($actionPermission !== null) {
            return $actionPermission;
        }

        return $this->defaultRequiredRole;
    }

    private function hasRole(TokenInterface $token, string $role): bool
    {
        if (null !== $this->decisionManager) {
            return $this->decisionManager->decide($token, [$role]);
        }

        foreach ($token->getRoleNames() as $tokenRole) {
            if ($tokenRole === $role) {
                return true;
            }
        }

        return false;
    }
}
