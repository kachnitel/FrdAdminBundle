<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Archive;

use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @group archive
 */
class ArchivePermissionTest extends TestCase
{
    /** @var Security&MockObject */
    private Security $security;

    private EntityListPermissionService $service;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);

        $this->service = new EntityListPermissionService(
            $this->createMock(EntityDiscoveryService::class),
            $this->security,
        );
    }

    private function makeConfig(?string $role): ArchiveConfig
    {
        return new ArchiveConfig('item.archived', 'archived', 'boolean', $role);
    }

    /** @test */
    public function canToggleArchiveReturnsFalseWhenNoConfig(): void
    {
        $this->assertFalse($this->service->canToggleArchive(null));
    }

    /** @test */
    public function canToggleArchiveReturnsTrueForAuthenticatedUserWhenNoRoleRequired(): void
    {
        $this->security->method('getUser')->willReturn($this->createMock(UserInterface::class));

        $this->assertTrue($this->service->canToggleArchive($this->makeConfig(null)));
    }

    /** @test */
    public function canToggleArchiveReturnsFalseForUnauthenticatedUserWhenNoRoleRequired(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->assertFalse($this->service->canToggleArchive($this->makeConfig(null)));
    }

    /** @test */
    public function canToggleArchiveReturnsTrueWhenUserHasRequiredRole(): void
    {
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $this->assertTrue($this->service->canToggleArchive($this->makeConfig('ROLE_ADMIN')));
    }

    /** @test */
    public function canToggleArchiveReturnsFalseWhenUserLacksRequiredRole(): void
    {
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $this->assertFalse($this->service->canToggleArchive($this->makeConfig('ROLE_ADMIN')));
    }
}
