<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Security;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminEntityVoterTest extends TestCase
{
    /** @var EntityDiscoveryService&MockObject */
    private EntityDiscoveryService $entityDiscovery;

    /** @var TokenInterface&MockObject */
    private TokenInterface $token;

    /** @var UserInterface&MockObject */
    private UserInterface $user;

    protected function setUp(): void
    {
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->token           = $this->createMock(TokenInterface::class);
        $this->user            = $this->createMock(UserInterface::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeVoter(
        ?string $defaultRole = 'ROLE_ADMIN',
        ?RoleHierarchyInterface $hierarchy = null,
    ): AdminEntityVoter {
        return new AdminEntityVoter(
            $this->entityDiscovery,
            $defaultRole,
            'App\\Entity\\',
            $hierarchy,
        );
    }

    /**
     * Configure entityDiscovery to resolve 'Product' → 'App\Entity\Product'
     * and return the given Admin attribute (or null).
     */
    private function stubDiscovery(?Admin $attr = null): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->with('Product', 'App\\Entity\\')
            ->willReturn('App\\Entity\\Product');

        $this->entityDiscovery->method('getAdminAttribute')
            ->with('App\\Entity\\Product')
            ->willReturn($attr);
    }

    /** Configure the token to return an authenticated user with the given roles. */
    private function stubAuthenticatedUser(string ...$roles): void
    {
        $this->token->method('getUser')->willReturn($this->user);
        $this->token->method('getRoleNames')->willReturn($roles);
    }

    // -------------------------------------------------------------------------
    // supports()
    // -------------------------------------------------------------------------

    public function testAbstainsForNonStringSubject(): void
    {
        $voter  = $this->makeVoter();
        $result = $voter->vote($this->token, 42, [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForUnsupportedAttribute(): void
    {
        $voter  = $this->makeVoter();
        $result = $voter->vote($this->token, 'Product', ['NOT_AN_ADMIN_ATTR']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    // -------------------------------------------------------------------------
    // All five ADMIN_* attributes are evaluated (not just INDEX)
    // -------------------------------------------------------------------------

    /**
     * @dataProvider allAdminAttributesProvider
     */
    public function testAllAdminAttributesGrantAccessWhenRoleMatches(string $attribute): void
    {
        $this->stubDiscovery();
        $this->stubAuthenticatedUser('ROLE_ADMIN');

        $result = $this->makeVoter()->vote($this->token, 'Product', [$attribute]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * @dataProvider allAdminAttributesProvider
     */
    public function testAllAdminAttributesDenyAccessWhenRoleMissing(string $attribute): void
    {
        $this->stubDiscovery();
        $this->stubAuthenticatedUser('ROLE_USER');

        $result = $this->makeVoter()->vote($this->token, 'Product', [$attribute]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    /** @return array<string, array{string}> */
    public static function allAdminAttributesProvider(): array
    {
        return [
            'ADMIN_INDEX'  => [AdminEntityVoter::ADMIN_INDEX],
            'ADMIN_SHOW'   => [AdminEntityVoter::ADMIN_SHOW],
            'ADMIN_NEW'    => [AdminEntityVoter::ADMIN_NEW],
            'ADMIN_EDIT'   => [AdminEntityVoter::ADMIN_EDIT],
            'ADMIN_DELETE' => [AdminEntityVoter::ADMIN_DELETE],
        ];
    }

    // -------------------------------------------------------------------------
    // Authentication disabled (defaultRequiredRole = null)
    // -------------------------------------------------------------------------

    public function testGrantsAccessWhenDefaultRequiredRoleIsNull(): void
    {
        $this->stubDiscovery();
        $this->token->expects($this->never())->method('getUser');

        $result = $this->makeVoter(defaultRole: null)->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsAccessWhenEntityPermissionNullOverridesGlobalRole(): void
    {
        // Admin attribute exists but has no permissions → falls back to defaultRole = null
        $this->stubDiscovery(new Admin());
        $this->token->expects($this->never())->method('getUser');

        $result = $this->makeVoter(defaultRole: null)->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    // -------------------------------------------------------------------------
    // Unauthenticated user
    // -------------------------------------------------------------------------

    public function testDeniesAccessWhenUserIsNotAuthenticated(): void
    {
        $this->stubDiscovery();
        $this->token->method('getUser')->willReturn(null);

        $result = $this->makeVoter()->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // -------------------------------------------------------------------------
    // Role matching – no hierarchy
    // -------------------------------------------------------------------------

    public function testGrantsAccessWhenUserHasExactRequiredRole(): void
    {
        $this->stubDiscovery();
        $this->stubAuthenticatedUser('ROLE_ADMIN');

        $result = $this->makeVoter()->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesAccessWhenUserHasWrongRole(): void
    {
        $this->stubDiscovery();
        $this->stubAuthenticatedUser('ROLE_USER');

        $result = $this->makeVoter()->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesAccessWhenUserHasInheritingRoleButNoHierarchyConfigured(): void
    {
        // Without a RoleHierarchyInterface, the voter does simple string comparison.
        // ROLE_SUPER_ADMIN does NOT satisfy ROLE_ADMIN in that mode.
        $this->stubDiscovery();
        $this->stubAuthenticatedUser('ROLE_SUPER_ADMIN');

        $result = $this->makeVoter(hierarchy: null)->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // -------------------------------------------------------------------------
    // Role hierarchy
    // -------------------------------------------------------------------------

    public function testGrantsAccessViaRoleHierarchy(): void
    {
        // ROLE_SUPER_ADMIN inherits ROLE_ADMIN via hierarchy.
        // User only has ROLE_SUPER_ADMIN but the required role is ROLE_ADMIN.
        /** @var RoleHierarchyInterface&MockObject $hierarchy */
        $hierarchy = $this->createMock(RoleHierarchyInterface::class);
        $hierarchy->method('getReachableRoleNames')
            ->with(['ROLE_SUPER_ADMIN'])
            ->willReturn(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER']);

        $this->stubDiscovery();
        $this->stubAuthenticatedUser('ROLE_SUPER_ADMIN');

        $result = $this->makeVoter(hierarchy: $hierarchy)->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesAccessWhenHierarchyDoesNotReachRequiredRole(): void
    {
        /** @var RoleHierarchyInterface&MockObject $hierarchy */
        $hierarchy = $this->createMock(RoleHierarchyInterface::class);
        $hierarchy->method('getReachableRoleNames')
            ->with(['ROLE_EDITOR'])
            ->willReturn(['ROLE_EDITOR', 'ROLE_USER']);

        $this->stubDiscovery();
        $this->stubAuthenticatedUser('ROLE_EDITOR');

        $result = $this->makeVoter(hierarchy: $hierarchy)->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testHierarchyReceivesRolesFromToken(): void
    {
        // Verify the voter passes token->getRoleNames() to the hierarchy, not a hardcoded list.
        /** @var RoleHierarchyInterface&MockObject $hierarchy */
        $hierarchy = $this->createMock(RoleHierarchyInterface::class);
        $hierarchy->expects($this->once())
            ->method('getReachableRoleNames')
            ->with(['ROLE_MANAGER', 'ROLE_EDITOR'])
            ->willReturn(['ROLE_MANAGER', 'ROLE_EDITOR', 'ROLE_ADMIN']);

        $this->stubDiscovery();
        $this->stubAuthenticatedUser('ROLE_MANAGER', 'ROLE_EDITOR');

        $this->makeVoter(hierarchy: $hierarchy)->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);
    }

    // -------------------------------------------------------------------------
    // Entity-specific permission overrides
    // -------------------------------------------------------------------------

    public function testEntitySpecificPermissionOverridesDefaultRole(): void
    {
        // Default requires ROLE_ADMIN, but entity index only requires ROLE_USER.
        $this->stubDiscovery(new Admin(permissions: ['index' => 'ROLE_USER']));
        $this->stubAuthenticatedUser('ROLE_USER');

        $result = $this->makeVoter()->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEntitySpecificPermissionDeniesWhenUserLacksOverrideRole(): void
    {
        // Entity edit requires ROLE_EDITOR — user only has ROLE_ADMIN (default).
        $this->stubDiscovery(new Admin(permissions: ['edit' => 'ROLE_EDITOR']));
        $this->stubAuthenticatedUser('ROLE_ADMIN');

        $result = $this->makeVoter()->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_EDIT]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEntitySpecificPermissionWorksWithHierarchy(): void
    {
        // Entity requires ROLE_EDITOR for edit; user has ROLE_SUPER_EDITOR which inherits it.
        /** @var RoleHierarchyInterface&MockObject $hierarchy */
        $hierarchy = $this->createMock(RoleHierarchyInterface::class);
        $hierarchy->method('getReachableRoleNames')
            ->with(['ROLE_SUPER_EDITOR'])
            ->willReturn(['ROLE_SUPER_EDITOR', 'ROLE_EDITOR']);

        $this->stubDiscovery(new Admin(permissions: ['edit' => 'ROLE_EDITOR']));
        $this->stubAuthenticatedUser('ROLE_SUPER_EDITOR');

        $result = $this->makeVoter(hierarchy: $hierarchy)->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    // -------------------------------------------------------------------------
    // Entity resolution edge cases
    // -------------------------------------------------------------------------

    public function testFallsBackToDefaultRoleWhenEntityNotResolvable(): void
    {
        // resolveEntityClass returns null → voter uses defaultRequiredRole
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);
        $this->entityDiscovery->expects($this->never())->method('getAdminAttribute');

        $this->stubAuthenticatedUser('ROLE_ADMIN');

        $result = $this->makeVoter()->vote($this->token, 'UnknownEntity', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testFallsBackToDefaultRoleWhenEntityHasNoAdminAttribute(): void
    {
        // Entity resolves but has no #[Admin] attribute
        $this->entityDiscovery->method('resolveEntityClass')->willReturn('App\\Entity\\Product');
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);

        $this->stubAuthenticatedUser('ROLE_ADMIN');

        $result = $this->makeVoter()->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesWhenEntityNotResolvableAndUserLacksDefaultRole(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);

        $this->stubAuthenticatedUser('ROLE_USER');

        $result = $this->makeVoter()->vote($this->token, 'UnknownEntity', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }
}
