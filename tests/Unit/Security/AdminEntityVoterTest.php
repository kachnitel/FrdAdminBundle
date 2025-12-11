<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Security;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminEntityVoterTest extends TestCase
{
    private EntityDiscoveryService $entityDiscovery;
    private TokenInterface $token;
    private UserInterface $user;

    protected function setUp(): void
    {
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->token = $this->createMock(TokenInterface::class);
        $this->user = $this->createMock(UserInterface::class);
    }

    public function testGrantsAccessWhenRequiredRoleIsNull(): void
    {
        // Configure voter with null as default required role
        $voter = new AdminEntityVoter(
            $this->entityDiscovery,
            null,
            'App\\Entity\\'
        );

        // Mock entity discovery to return null (no specific permissions)
        $this->entityDiscovery->expects($this->once())
            ->method('resolveEntityClass')
            ->with('Product', 'App\\Entity\\')
            ->willReturn('App\\Entity\\Product');

        $this->entityDiscovery->expects($this->once())
            ->method('getAdminAttribute')
            ->with('App\\Entity\\Product')
            ->willReturn(null);

        // Token should not need to return a user when required role is null
        $this->token->expects($this->never())
            ->method('getUser');

        // Test that access is granted
        $result = $voter->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesAccessWhenRequiredRoleSetAndUserNotAuthenticated(): void
    {
        // Configure voter with ROLE_ADMIN as default required role
        $voter = new AdminEntityVoter(
            $this->entityDiscovery,
            'ROLE_ADMIN',
            'App\\Entity\\'
        );

        // Mock entity discovery
        $this->entityDiscovery->expects($this->once())
            ->method('resolveEntityClass')
            ->with('Product', 'App\\Entity\\')
            ->willReturn('App\\Entity\\Product');

        $this->entityDiscovery->expects($this->once())
            ->method('getAdminAttribute')
            ->with('App\\Entity\\Product')
            ->willReturn(null);

        // Token returns no user (anonymous)
        $this->token->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        // Test that access is denied
        $result = $voter->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testGrantsAccessWhenUserHasRequiredRole(): void
    {
        // Configure voter with ROLE_ADMIN as default required role
        $voter = new AdminEntityVoter(
            $this->entityDiscovery,
            'ROLE_ADMIN',
            'App\\Entity\\'
        );

        // Mock entity discovery
        $this->entityDiscovery->expects($this->once())
            ->method('resolveEntityClass')
            ->with('Product', 'App\\Entity\\')
            ->willReturn('App\\Entity\\Product');

        $this->entityDiscovery->expects($this->once())
            ->method('getAdminAttribute')
            ->with('App\\Entity\\Product')
            ->willReturn(null);

        // Token returns a user with ROLE_ADMIN
        $this->token->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);

        $this->token->expects($this->once())
            ->method('getRoleNames')
            ->willReturn(['ROLE_ADMIN']);

        // Test that access is granted
        $result = $voter->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsAccessWithEntitySpecificPermissionOverride(): void
    {
        // Configure voter with ROLE_ADMIN as default required role
        $voter = new AdminEntityVoter(
            $this->entityDiscovery,
            'ROLE_ADMIN',
            'App\\Entity\\'
        );

        // Mock Admin attribute with custom permission
        $adminAttr = $this->createMock(Admin::class);
        $adminAttr->expects($this->once())
            ->method('getPermissionForAction')
            ->with('index')
            ->willReturn('ROLE_USER'); // Override: only needs ROLE_USER

        // Mock entity discovery
        $this->entityDiscovery->expects($this->once())
            ->method('resolveEntityClass')
            ->with('Product', 'App\\Entity\\')
            ->willReturn('App\\Entity\\Product');

        $this->entityDiscovery->expects($this->once())
            ->method('getAdminAttribute')
            ->with('App\\Entity\\Product')
            ->willReturn($adminAttr);

        // Token returns a user with ROLE_USER (not ROLE_ADMIN)
        $this->token->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);

        $this->token->expects($this->once())
            ->method('getRoleNames')
            ->willReturn(['ROLE_USER']);

        // Test that access is granted (entity-specific permission overrides default)
        $result = $voter->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsAccessWithEntitySpecificPermissionSetToNull(): void
    {
        // Configure voter with null as default required role (disabled globally)
        $voter = new AdminEntityVoter(
            $this->entityDiscovery,
            null, // Global auth disabled
            'App\\Entity\\'
        );

        // Mock Admin attribute with null permission (falls back to global default)
        $adminAttr = $this->createMock(Admin::class);
        $adminAttr->expects($this->once())
            ->method('getPermissionForAction')
            ->with('index')
            ->willReturn(null); // Falls back to global default (null)

        // Mock entity discovery
        $this->entityDiscovery->expects($this->once())
            ->method('resolveEntityClass')
            ->with('Product', 'App\\Entity\\')
            ->willReturn('App\\Entity\\Product');

        $this->entityDiscovery->expects($this->once())
            ->method('getAdminAttribute')
            ->with('App\\Entity\\Product')
            ->willReturn($adminAttr);

        // Token should not need to return a user when global auth is disabled
        $this->token->expects($this->never())
            ->method('getUser');

        // Test that access is granted
        $result = $voter->vote($this->token, 'Product', [AdminEntityVoter::ADMIN_INDEX]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

}
