<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Security;

use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Object-subject authorization on top of AdminEntityVoter's class-level checks.
 *
 * AdminEntityVoter answers "may this user perform ADMIN_EDIT on the Contact
 * entity type at all" using the entity's short class name as the voter
 * subject. This service answers the narrower, row-level question — "may
 * this user perform ADMIN_EDIT on *this particular* Contact instance" — by
 * passing the loaded (or freshly bound, for new/edit) entity object itself
 * as the voter subject, so an ordinary application-defined Symfony voter
 * whose supports() accepts an object subject gets a say. No bundle-specific
 * interface is required of that voter — it's a plain
 * Symfony\Component\Security\Core\Authorization\Voter\Voter.
 *
 * ## Opt-in, not automatic
 *
 * Object-level checks only run for entities carrying
 * #[Admin(enableObjectAuthorization: true)]. For every other entity,
 * isGranted() short-circuits to true and denyAccessUnlessGranted() is a
 * no-op — existing class-level-only behaviour is unchanged for anyone who
 * doesn't opt in.
 *
 * ## The gotcha: opting in without a voter denies everything
 *
 * Symfony's default AccessDecisionManager (affirmative strategy,
 * allow_if_all_abstain: false) denies when every voter abstains. Once
 * enableObjectAuthorization is true for an entity, isGranted() calls
 * AuthorizationCheckerInterface::isGranted($attribute, $entity) for real —
 * if no application voter's supports() matches that object subject, every
 * request for that entity is denied, not silently allowed. Turning this
 * flag on requires also registering a voter that accepts the entity as
 * subject. See docs/OBJECT_AUTHORIZATION.md.
 *
 * ## Independent of AdminEntityVoter, not merged into it
 *
 * Class-level (string-subject) and object-level (object-subject) checks
 * are deliberately two separate, AND'ed gates, called from two separate
 * call sites, rather than one voter that handles both subject types.
 * AdminEntityVoter voting "grant" for an object subject would let the
 * affirmative strategy grant overall regardless of what an application's
 * object-subject voter says, silently neutering this feature for anyone
 * who enables it — so AdminEntityVoter must never vote on object subjects.
 * It doesn't: its supports() requires is_string($subject).
 *
 * @see AdminEntityVoter
 */
class ObjectAuthorizationChecker
{
    public function __construct(
        private readonly EntityDiscoveryService $entityDiscovery,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {}

    /**
     * Whether object-level authorization is configured for the entity's class.
     *
     * Resolves the real class (unwrapping Doctrine proxies) before looking up
     * the #[Admin] attribute, matching every other admin-attribute lookup in
     * the bundle.
     */
    public function isEnabledFor(object $entity): bool
    {
        $adminAttribute = $this->entityDiscovery->getAdminAttribute(ObjectHelper::getRealClass($entity));

        return $adminAttribute !== null && $adminAttribute->isEnableObjectAuthorization();
    }

    /**
     * Whether the current user is granted $attribute on this specific entity
     * instance. Always true when object authorization is not enabled for the
     * entity's class — see class docblock.
     *
     * @param string $attribute An AdminEntityVoter::ADMIN_* constant, or any
     *   other attribute string an application voter chooses to support.
     */
    public function isGranted(string $attribute, object $entity): bool
    {
        if (!$this->isEnabledFor($entity)) {
            return true;
        }

        return $this->authorizationChecker->isGranted($attribute, $entity);
    }

    /**
     * @throws AccessDeniedException when object authorization is enabled for
     *   the entity's class and the current user is not granted $attribute on it.
     */
    public function denyAccessUnlessGranted(string $attribute, object $entity): void
    {
        if ($this->isGranted($attribute, $entity)) {
            return;
        }

        throw new AccessDeniedException(sprintf(
            'Access denied: %s on this %s instance.',
            $attribute,
            (new \ReflectionClass(ObjectHelper::getRealClass($entity)))->getShortName(),
        ));
    }
}
