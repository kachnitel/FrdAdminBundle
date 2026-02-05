<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Tests\Fixtures\PermissionTestEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class ColumnPermissionServiceTest extends TestCase
{
    /** @var Security&MockObject */
    private Security $security;

    private ColumnPermissionService $service;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->service = new ColumnPermissionService($this->security);
    }

    /**
     * @test
     */
    public function getColumnPermissionMapReturnsCorrectMapping(): void
    {
        $map = $this->service->getColumnPermissionMap(PermissionTestEntity::class);

        $this->assertSame([
            'salary' => 'ROLE_HR',
            'internalNotes' => 'ROLE_MANAGER',
        ], $map);
    }

    /**
     * @test
     */
    public function getColumnPermissionMapReturnsEmptyForEntityWithoutPermissions(): void
    {
        $map = $this->service->getColumnPermissionMap(TestEntity::class);

        $this->assertSame([], $map);
    }

    /**
     * @test
     */
    public function getDeniedColumnsReturnsDeniedWhenUserLacksRole(): void
    {
        $this->security->method('isGranted')
            ->willReturn(false);

        $denied = $this->service->getDeniedColumns(PermissionTestEntity::class);

        $this->assertContains('salary', $denied);
        $this->assertContains('internalNotes', $denied);
        $this->assertCount(2, $denied);
    }

    /**
     * @test
     */
    public function getDeniedColumnsReturnsEmptyWhenUserHasAllRoles(): void
    {
        $this->security->method('isGranted')
            ->willReturn(true);

        $denied = $this->service->getDeniedColumns(PermissionTestEntity::class);

        $this->assertSame([], $denied);
    }

    /**
     * @test
     */
    public function getDeniedColumnsReturnsOnlyDeniedColumns(): void
    {
        $this->security->method('isGranted')
            ->willReturnCallback(fn(string $role): bool => $role === 'ROLE_HR');

        $denied = $this->service->getDeniedColumns(PermissionTestEntity::class);

        $this->assertSame(['internalNotes'], $denied);
    }

    /**
     * @test
     */
    public function getDeniedColumnsReturnsEmptyForEntityWithoutPermissions(): void
    {
        $denied = $this->service->getDeniedColumns(TestEntity::class);

        $this->assertSame([], $denied);
    }
}
