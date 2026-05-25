<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
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
 *  3. Form availability for new/edit actions:
 *     - Satisfied by a registered Symfony FormType, OR
 *     - Satisfied by AutoEntityForm (entity has inline-edit attributes)
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
     * @param string $action          Action name
     * @param bool   $routeExists     Whether the route for this action exists
     */
    public function isActionAccessible(string $entityShortName, string $action, bool $routeExists): bool
    {
        if (!$routeExists) {
            return false;
        }

        if (!$this->isGrantedForAction($entityShortName, $action)) {
            return false;
        }

        if (in_array($action, ['new', 'edit'], true)) {
            if (!$this->hasFormType($entityShortName) && !$this->hasAutoForm($entityShortName)) {
                return false;
            }
        }

        return true;
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

    /**
     * Whether a Symfony FormType is registered for this entity.
     */
    private function hasFormType(string $entityShortName): bool
    {
        if ($this->formRegistry === null || $this->entityDiscovery === null) {
            return true;
        }

        try {
            $entityClass = $this->entityDiscovery->resolveEntityClass($entityShortName, $this->entityNamespace);
            if ($entityClass !== null) {
                $adminAttr = $this->entityDiscovery->getAdminAttribute($entityClass);
                $formType  = $adminAttr?->getFormType()
                    ?: $this->formNamespace . $entityShortName . $this->formSuffix;
                return $this->formRegistry->hasType($formType);
            }
        } catch (\Exception) {
            // Fall through to default behaviour
        }

        return $this->formRegistry->hasType($this->formNamespace . $entityShortName . $this->formSuffix);
    }

    /**
     * Whether AutoEntityForm can render a form for this entity (new or edit).
     *
     * Uses pure attribute inspection — no entity instantiation, no Doctrine query.
     * Returns true when the entity opts in to inline editing at class or property level.
     *
     * Voter and setter checks are skipped here; they are evaluated per-field at
     * render time inside AutoEntityForm. This method only determines whether the
     * New/Edit button should appear at all.
     */
    private function hasAutoForm(string $entityShortName): bool
    {
        if ($this->entityDiscovery === null) {
            return false;
        }

        try {
            /** @var null|class-string $entityClass */
            $entityClass = $this->entityDiscovery->resolveEntityClass($entityShortName, $this->entityNamespace);
            if ($entityClass === null) {
                return false;
            }

            $adminAttr = $this->entityDiscovery->getAdminAttribute($entityClass);

            // Entity-level opt-in.
            if ($adminAttr !== null && $adminAttr->isEnableInlineEdit()) {
                return true;
            }

            // Per-property explicit opt-in.
            $reflection = new \ReflectionClass($entityClass);
            foreach ($reflection->getProperties() as $property) {
                $attributes = $property->getAttributes(AdminColumn::class);
                if (empty($attributes)) {
                    continue;
                }

                /** @var AdminColumn $col */
                $col = $attributes[0]->newInstance();
                if ($col->editable === true) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
