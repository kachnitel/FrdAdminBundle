<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;

/**
 * Always-permissive stand-in for ObjectAuthorizationChecker, for test doubles
 * (e.g. GenericAdminControllerTestDouble) that are constructed directly via
 * `new` rather than through the DI container.
 *
 * Deliberately does not call parent::__construct() — none of
 * ObjectAuthorizationChecker's real dependencies (EntityDiscoveryService,
 * AuthorizationCheckerInterface) are needed, since every method below is
 * overridden and never touches them.
 *
 * Existing tests that don't care about object-level authorization can use
 * this as-is (it's GenericAdminControllerTestDouble's default); tests that
 * do (see GenericAdminControllerObjectAuthorizationTest) pass their own
 * mock/stub in via the $objectAuthChecker constructor argument instead.
 */
final class PermissiveObjectAuthorizationChecker extends ObjectAuthorizationChecker
{
    public function __construct() {}

    public function isEnabledFor(object $entity): bool
    {
        return false;
    }

    public function isGranted(string $attribute, object $entity): bool
    {
        return true;
    }

    public function denyAccessUnlessGranted(string $attribute, object $entity): void
    {
        // no-op — always granted
    }
}
