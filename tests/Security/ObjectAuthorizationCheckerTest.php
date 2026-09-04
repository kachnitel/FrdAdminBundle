<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Security;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Covers ObjectAuthorizationChecker in isolation: the opt-in short-circuit
 * (no #[Admin] attribute, or enableObjectAuth: false), delegation
 * to AuthorizationCheckerInterface once enabled, and denyAccessUnlessGranted()'s
 * no-op-vs-throw behaviour.
 *
 * Controller and LiveComponent integration (the actual call sites, and
 * grant/deny behaviour against a real Symfony voter) are covered by
 * GenericAdminControllerObjectAuthorizationTest, AdminEntityFormObjectAuthorizationTest,
 * and InlineEntityFormObjectAuthorizationTest.
 */
#[PHPUnit\CoversClass(ObjectAuthorizationChecker::class)]
#[PHPUnit\UsesClass(ObjectHelper::class)]
#[PHPUnit\UsesClass(Admin::class)]
#[PHPUnit\Group('security')]
#[PHPUnit\Group('object-authorization')]
final class ObjectAuthorizationCheckerTest extends TestCase
{
    private Stub&EntityDiscoveryService $entityDiscovery;
    private Stub&AuthorizationCheckerInterface $authorizationChecker;

    protected function setUp(): void
    {
        $this->entityDiscovery = $this->createStub(EntityDiscoveryService::class);
        $this->authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
    }

    private function makeChecker(): ObjectAuthorizationChecker
    {
        return new ObjectAuthorizationChecker($this->entityDiscovery, $this->authorizationChecker);
    }

    // ── isEnabledFor() ───────────────────────────────────────────────────────

    #[PHPUnit\Test]
    public function isEnabledForIsFalseWithNoAdminAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);

        $this->assertFalse($this->makeChecker()->isEnabledFor(new \stdClass()));
    }

    #[PHPUnit\Test]
    public function isEnabledForIsFalseWhenFlagNotSetOnAdminAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableObjectAuth: false));

        $this->assertFalse($this->makeChecker()->isEnabledFor(new \stdClass()));
    }

    #[PHPUnit\Test]
    public function isEnabledForIsTrueWhenFlagSet(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableObjectAuth: true));

        $this->assertTrue($this->makeChecker()->isEnabledFor(new \stdClass()));
    }

    // ── isGranted(): opt-in short-circuit ────────────────────────────────────

    #[PHPUnit\Test]
    public function isGrantedIsTrueWithNoAdminAttributeEvenWhenAuthorizationCheckerWouldDeny(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $this->assertTrue($this->makeChecker()->isGranted('ADMIN_EDIT', new \stdClass()));
    }

    #[PHPUnit\Test]
    public function isGrantedIsTrueWhenNotEnabledEvenWhenAuthorizationCheckerWouldDeny(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableObjectAuth: false));
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $this->assertTrue($this->makeChecker()->isGranted('ADMIN_EDIT', new \stdClass()));
    }

    #[PHPUnit\Test]
    public function isGrantedDoesNotCallAuthorizationCheckerWhenNotEnabled(): void
    {
        $entityDiscovery = $this->createStub(EntityDiscoveryService::class);
        $entityDiscovery->method('getAdminAttribute')->willReturn(null);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->never())->method('isGranted');

        $checker = new ObjectAuthorizationChecker($entityDiscovery, $authorizationChecker);
        $checker->isGranted('ADMIN_EDIT', new \stdClass());
    }

    // ── isGranted(): delegation once enabled ─────────────────────────────────

    #[PHPUnit\Test]
    public function isGrantedDelegatesToAuthorizationCheckerWithSameAttributeAndEntityWhenEnabled(): void
    {
        $entity = new \stdClass();

        $entityDiscovery = $this->createStub(EntityDiscoveryService::class);
        $entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableObjectAuth: true));

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('ADMIN_EDIT', $entity)
            ->willReturn(true);

        $checker = new ObjectAuthorizationChecker($entityDiscovery, $authorizationChecker);

        $this->assertTrue($checker->isGranted('ADMIN_EDIT', $entity));
    }

    #[PHPUnit\Test]
    public function isGrantedReturnsFalseWhenAuthorizationCheckerDeniesAndEnabled(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableObjectAuth: true));
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($this->makeChecker()->isGranted('ADMIN_EDIT', new \stdClass()));
    }

    /**
     * Pins down the specific real-world failure mode this class exists to make
     * safe rather than silent: an entity opts in via
     * #[Admin(enableObjectAuth: true)] but the application never
     * registers a voter whose supports() accepts that entity as an object
     * subject. Symfony's AccessDecisionManager denies when every voter
     * abstains (allow_if_all_abstain defaults to false), so
     * AuthorizationCheckerInterface::isGranted() returns false here — not
     * because a voter said no, but because nothing said yes. This must stay
     * false: a future "fix" that made an all-abstain vote grant would make
     * enableObjectAuth a no-op security theater flag for anyone who
     * forgets to also write a voter, with no error at boot or at request time.
     */
    #[PHPUnit\Test]
    public function isGrantedDeniesWhenEnabledButNoVoterSupportsTheObjectSubject(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableObjectAuth: true));
        // Simulates Symfony's real AccessDecisionManager output for an
        // all-abstain vote (allow_if_all_abstain: false) — not a mock of a
        // specific voter's decision, but of what isGranted() returns in that
        // situation regardless of which voters exist.
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($this->makeChecker()->isGranted('ADMIN_EDIT', new \stdClass()));
    }

    // ── denyAccessUnlessGranted() ─────────────────────────────────────────────

    #[PHPUnit\Test]
    public function denyAccessUnlessGrantedIsNoOpWhenNotEnabled(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);

        $this->makeChecker()->denyAccessUnlessGranted('ADMIN_EDIT', new \stdClass());

        $this->addToAssertionCount(1); // success criterion: no exception thrown
    }

    #[PHPUnit\Test]
    public function denyAccessUnlessGrantedIsNoOpWhenEnabledAndGranted(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableObjectAuth: true));
        $this->authorizationChecker->method('isGranted')->willReturn(true);

        $this->makeChecker()->denyAccessUnlessGranted('ADMIN_EDIT', new \stdClass());

        $this->addToAssertionCount(1);
    }

    #[PHPUnit\Test]
    public function denyAccessUnlessGrantedThrowsAccessDeniedExceptionWhenEnabledAndDenied(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableObjectAuth: true));
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access denied: ADMIN_EDIT on this stdClass instance.');

        $this->makeChecker()->denyAccessUnlessGranted('ADMIN_EDIT', new \stdClass());
    }
}
