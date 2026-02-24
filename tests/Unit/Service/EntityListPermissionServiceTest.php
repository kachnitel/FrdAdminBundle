<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class EntityListPermissionServiceTest extends TestCase
{
    /** @var EntityDiscoveryService&MockObject */
    private EntityDiscoveryService $entityDiscovery;

    /** @var Security&MockObject */
    private Security $security;

    private EntityListPermissionService $service;

    protected function setUp(): void
    {
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->security = $this->createMock(Security::class);

        $this->service = new EntityListPermissionService(
            $this->entityDiscovery,
            $this->security
        );
    }

    /**
     * @test
     */
    public function isBatchActionsEnabledReturnsTrueWhenEnabled(): void
    {
        $admin = new Admin(enableBatchActions: true);

        $this->entityDiscovery->method('getAdminAttribute')
            ->with('App\\Entity\\TestEntity')
            ->willReturn($admin);

        $result = $this->service->isBatchActionsEnabled('App\\Entity\\TestEntity');

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isBatchActionsEnabledReturnsFalseWhenDisabled(): void
    {
        $admin = new Admin(enableBatchActions: false);

        $this->entityDiscovery->method('getAdminAttribute')
            ->with('App\\Entity\\TestEntity')
            ->willReturn($admin);

        $result = $this->service->isBatchActionsEnabled('App\\Entity\\TestEntity');

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isBatchActionsEnabledReturnsFalseWhenNoAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->with('App\\Entity\\TestEntity')
            ->willReturn(null);

        $result = $this->service->isBatchActionsEnabled('App\\Entity\\TestEntity');

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isBatchActionsEnabledReturnsFalseByDefault(): void
    {
        // Admin attribute with default values
        $admin = new Admin();

        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn($admin);

        $result = $this->service->isBatchActionsEnabled('App\\Entity\\TestEntity');

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function canBatchDeleteReturnsTrueWhenEnabledAndGranted(): void
    {
        $admin = new Admin(enableBatchActions: true);

        $this->entityDiscovery->method('getAdminAttribute')
            ->with('App\\Entity\\TestEntity')
            ->willReturn($admin);

        $this->security->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'TestEntity')
            ->willReturn(true);

        $result = $this->service->canBatchDelete('App\\Entity\\TestEntity', 'TestEntity');

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function canBatchDeleteReturnsFalseWhenNotEnabled(): void
    {
        $admin = new Admin(enableBatchActions: false);

        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn($admin);

        // Security check should not even be called if batch actions are disabled
        $this->security->expects($this->never())->method('isGranted');

        $result = $this->service->canBatchDelete('App\\Entity\\TestEntity', 'TestEntity');

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function canBatchDeleteReturnsFalseWhenNotGranted(): void
    {
        $admin = new Admin(enableBatchActions: true);

        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn($admin);

        $this->security->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'TestEntity')
            ->willReturn(false);

        $result = $this->service->canBatchDelete('App\\Entity\\TestEntity', 'TestEntity');

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function canBatchDeleteReturnsFalseWhenNoAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(null);

        $this->security->expects($this->never())->method('isGranted');

        $result = $this->service->canBatchDelete('App\\Entity\\TestEntity', 'TestEntity');

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function canBatchDeleteUsesCorrectVoterAttribute(): void
    {
        $admin = new Admin(enableBatchActions: true);

        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn($admin);

        $this->security->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'Product')
            ->willReturn(true);

        $this->service->canBatchDelete('App\\Entity\\Product', 'Product');
    }

    // --- Non-Doctrine data source tests ---

    /**
     * @test
     */
    public function canBatchDeleteReturnsTrueForNonDoctrineWhenGranted(): void
    {
        $this->security->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'custom-source')
            ->willReturn(true);

        $result = $this->service->canBatchDelete('', '', 'custom-source');

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function canBatchDeleteReturnsFalseForNonDoctrineWhenNotGranted(): void
    {
        $this->security->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'custom-source')
            ->willReturn(false);

        $result = $this->service->canBatchDelete('', '', 'custom-source');

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function canBatchDeleteUsesEntityShortClassWhenNoDataSourceId(): void
    {
        $this->security->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'MyShortClass')
            ->willReturn(true);

        $this->service->canBatchDelete('', 'MyShortClass');
    }

    /**
     * @test
     */
    public function canBatchDeletePrefersDataSourceIdOverEntityShortClass(): void
    {
        $this->security->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'custom-source')
            ->willReturn(true);

        $this->service->canBatchDelete('', 'MyShortClass', 'custom-source');
    }

    /**
     * @test
     */
    public function canBatchDeleteSkipsAttributeCheckForNonDoctrine(): void
    {
        $this->entityDiscovery->expects($this->never())->method('getAdminAttribute');

        $this->security->method('isGranted')->willReturn(true);

        $this->service->canBatchDelete('', '', 'custom-source');
    }
}
