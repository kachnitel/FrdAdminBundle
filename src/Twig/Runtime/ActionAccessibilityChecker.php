<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Doctrine\Persistence\Proxy;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Determines whether a given admin action is accessible for an entity.
 *
 * Checks:
 *  1. Route existence (delegated to AdminRouteRuntime)
 *  2. Voter-based permission via AuthorizationCheckerInterface
 *  3. Form type existence for new/edit actions
 *
 * Extracted from AdminRouteRuntime to keep that class below the cyclomatic
 * complexity threshold.
 */
final class ActionAccessibilityChecker
{
    /**
     * Maps action names to AdminEntityVoter attribute constants.
     */
    private const VOTER_MAP = [
        'index'     => AdminEntityVoter::ADMIN_INDEX,
        'show'      => AdminEntityVoter::ADMIN_SHOW,
        'new'       => AdminEntityVoter::ADMIN_NEW,
        'edit'      => AdminEntityVoter::ADMIN_EDIT,
        'archive'   => AdminEntityVoter::ADMIN_ARCHIVE,
        'unarchive' => AdminEntityVoter::ADMIN_ARCHIVE,
        'delete'    => AdminEntityVoter::ADMIN_DELETE,
    ];

    public function __construct(
        private readonly ?AuthorizationCheckerInterface $authChecker,
        private readonly ?EntityDiscoveryService $entityDiscovery,
        private readonly ?FormRegistryInterface $formRegistry,
        private readonly string $formNamespace,
        private readonly string $formSuffix,
        private readonly string $entityNamespace,
    ) {}

    /**
     * Check if a user can perform an action on an entity type.
     *
     * @param string $entityShortName Entity short name (e.g., 'Product')
     * @param string $action          Action name ('index', 'show', 'new', 'edit', 'archive', 'unarchive', 'delete')
     * @param bool   $routeExists     Whether the route for this action exists (checked by AdminRouteRuntime)
     */
    public function isActionAccessible(string $entityShortName, string $action, bool $routeExists): bool
    {
        if (!$routeExists) {
            return false;
        }

        if (!$this->isGrantedForAction($entityShortName, $action)) {
            return false;
        }

        if (in_array($action, ['new', 'edit'], true) && !$this->hasForm($entityShortName)) {
            return false;
        }

        return true;
    }

    /**
     * Get the real class of an object, unwrapping Doctrine proxies.
     *
     * @return class-string
     */
    public function getRealClass(object $object): string
    {
        if ($object instanceof Proxy) {
            $parent = get_parent_class($object);
            if ($parent !== false) {
                return $parent;
            }
        }

        return $object::class;
    }

    /**
     * Map a voter attribute constant to the action name used elsewhere.
     */
    public function mapVoterAttributeToAction(string $voterAttribute): string
    {
        /** @var array<string, string> $flipped */
        $flipped = array_flip(array_unique(self::VOTER_MAP));
        return $flipped[$voterAttribute] ?? strtolower($voterAttribute);
    }

    /**
     * Get the voter attribute for an action name, or null if unknown.
     */
    public function getVoterAttribute(string $action): ?string
    {
        return self::VOTER_MAP[$action] ?? null;
    }

    private function isGrantedForAction(string $entityShortName, string $action): bool
    {
        if ($this->authChecker === null) {
            return true;
        }

        $voterAttribute = self::VOTER_MAP[$action] ?? null;

        if ($voterAttribute === null) {
            return true;
        }

        return $this->authChecker->isGranted($voterAttribute, $entityShortName);
    }

    private function hasForm(string $entityShortName): bool
    {
        if ($this->formRegistry === null || $this->entityDiscovery === null) {
            return true;
        }

        try {
            $entityClass = $this->entityDiscovery->resolveEntityClass($entityShortName, $this->entityNamespace);
            if ($entityClass !== null) {
                $adminAttr = $this->entityDiscovery->getAdminAttribute($entityClass);
                $formType = $adminAttr?->getFormType()
                    ?: $this->formNamespace . $entityShortName . $this->formSuffix;
                return $this->formRegistry->hasType($formType);
            }
        } catch (\Exception) {
            // Fall through to default behaviour
        }

        return $this->formRegistry->hasType($this->formNamespace . $entityShortName . $this->formSuffix);
    }
}
