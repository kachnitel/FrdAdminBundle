<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[Group('entity-list')]
#[AllowMockObjectsWithoutExpectations]
final class EntityListPermissionServiceTest extends TestCase
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

    #[Test]
    public function isBatchActionsEnabledReturnsTrueWhenEnabled(): void
    {
        $admin = new Admin(enableBatchActions: true);

        $this->entityDiscovery->expects($this->once())->method('getAdminAttribute')
            ->with('App\\Entity\\TestEntity')
            ->willReturn($admin);

        $result = $this->service->isBatchActionsEnabled('App\\Entity\\TestEntity');

        $this->assertTrue($result);
    }

    #[Test]
    public function isBatchActionsEnabledReturnsFalseWhenDisabled(): void
    {
        $admin = new Admin(enableBatchActions: false);

        $this->entityDiscovery->expects($this->once())->method('getAdminAttribute')
            ->with('App\\Entity\\TestEntity')
            ->willReturn($admin);

        $result = $this->service->isBatchActionsEnabled('App\\Entity\\TestEntity');

        $this->assertFalse($result);
    }

    #[Test]
    public function isBatchActionsEnabledReturnsFalseWhenNoAttribute(): void
    {
        $this->entityDiscovery->expects($this->once())->method('getAdminAttribute')
            ->with('App\\Entity\\TestEntity')
            ->willReturn(null);

        $result = $this->service->isBatchActionsEnabled('App\\Entity\\TestEntity');

        $this->assertFalse($result);
    }

    #[Test]
    public function isBatchActionsEnabledReturnsFalseByDefault(): void
    {
        // Admin attribute with default values
        $admin = new Admin();

        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn($admin);

        $result = $this->service->isBatchActionsEnabled('App\\Entity\\TestEntity');

        $this->assertFalse($result);
    }

    #[Test]
    public function canBatchDeleteReturnsTrueWhenEnabledAndGranted(): void
    {
        $admin = new Admin(enableBatchActions: true);

        $this->entityDiscovery->expects($this->once())->method('getAdminAttribute')
            ->with('App\\Entity\\TestEntity')
            ->willReturn($admin);

        $this->security->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'TestEntity')
            ->willReturn(true);

        $result = $this->service->canBatchDelete('App\\Entity\\TestEntity', 'TestEntity');

        $this->assertTrue($result);
    }

    #[Test]
    public function canBatchDeleteReturnsFalseWhenNotEnabled(): void
    {
        $admin = new Admin(enableBatchActions: false);

        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn($admin);

        // Security check must not be called when batch actions are disabled
        $this->security->expects($this->never())->method('isGranted');

        $result = $this->service->canBatchDelete('App\\Entity\\TestEntity', 'TestEntity');

        $this->assertFalse($result);
    }

    #[Test]
    public function canBatchDeleteReturnsFalseWhenNotGranted(): void
    {
        $admin = new Admin(enableBatchActions: true);

        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn($admin);

        $this->security->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'TestEntity')
            ->willReturn(false);

        $result = $this->service->canBatchDelete('App\\Entity\\TestEntity', 'TestEntity');

        $this->assertFalse($result);
    }

    #[Test]
    public function canBatchDeleteReturnsFalseWhenNoAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(null);

        $this->security->expects($this->never())->method('isGranted');

        $result = $this->service->canBatchDelete('App\\Entity\\TestEntity', 'TestEntity');

        $this->assertFalse($result);
    }

    #[Test]
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

    #[Test]
    public function canBatchDeleteReturnsTrueForNonDoctrineWhenGranted(): void
    {
        $this->security->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'custom-source')
            ->willReturn(true);

        $result = $this->service->canBatchDelete('', '', 'custom-source');

        $this->assertTrue($result);
    }

    #[Test]
    public function canBatchDeleteReturnsFalseForNonDoctrineWhenNotGranted(): void
    {
        $this->security->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'custom-source')
            ->willReturn(false);

        $result = $this->service->canBatchDelete('', '', 'custom-source');

        $this->assertFalse($result);
    }

    #[Test]
    public function canBatchDeleteUsesEntityShortClassWhenNoDataSourceId(): void
    {
        $this->security->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'MyShortClass')
            ->willReturn(true);

        $this->service->canBatchDelete('', 'MyShortClass');
    }

    #[Test]
    public function canBatchDeletePrefersDataSourceIdOverEntityShortClass(): void
    {
        $this->security->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_DELETE, 'custom-source')
            ->willReturn(true);

        $this->service->canBatchDelete('', 'MyShortClass', 'custom-source');
    }

    #[Test]
    public function canBatchDeleteSkipsAttributeCheckForNonDoctrine(): void
    {
        $this->entityDiscovery->expects($this->never())->method('getAdminAttribute');

        $this->security->method('isGranted')->willReturn(true);

        $this->service->canBatchDelete('', '', 'custom-source');
    }

    // --- canInlineEdit tests ---

    #[Test]
    public function canInlineEditReturnsTrueWhenEnabledAndGranted(): void
    {
        $admin = new Admin(enableInlineEdit: true);

        $this->entityDiscovery->expects($this->once())->method('getAdminAttribute')
            ->with('App\\Entity\\Product')
            ->willReturn($admin);

        $this->security->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_EDIT, 'Product')
            ->willReturn(true);

        $this->assertTrue($this->service->canInlineEdit('App\\Entity\\Product', 'Product'));
    }

    #[Test]
    public function canInlineEditReturnsFalseWhenFlagDisabled(): void
    {
        $admin = new Admin(enableInlineEdit: false);

        $this->entityDiscovery->method('getAdminAttribute')->willReturn($admin);

        // Voter must not be called — the flag check is the cheap early exit
        $this->security->expects($this->never())->method('isGranted');

        $this->assertFalse($this->service->canInlineEdit('App\\Entity\\Product', 'Product'));
    }

    #[Test]
    public function canInlineEditReturnsFalseWhenNoAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);

        $this->security->expects($this->never())->method('isGranted');

        $this->assertFalse($this->service->canInlineEdit('App\\Entity\\Product', 'Product'));
    }

    #[Test]
    public function canInlineEditReturnsFalseWhenVoterDenies(): void
    {
        $admin = new Admin(enableInlineEdit: true);

        $this->entityDiscovery->method('getAdminAttribute')->willReturn($admin);

        $this->security->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_EDIT, 'Product')
            ->willReturn(false);

        $this->assertFalse($this->service->canInlineEdit('App\\Entity\\Product', 'Product'));
    }

    #[Test]
    public function canInlineEditReturnsFalseForEmptyEntityClass(): void
    {
        // Non-Doctrine data sources have no entityClass
        $this->entityDiscovery->expects($this->never())->method('getAdminAttribute');
        $this->security->expects($this->never())->method('isGranted');

        $this->assertFalse($this->service->canInlineEdit('', 'SomeDataSource'));
    }

    #[Test]
    public function canInlineEditChecksAdminEditVoterAttribute(): void
    {
        $admin = new Admin(enableInlineEdit: true);

        $this->entityDiscovery->method('getAdminAttribute')->willReturn($admin);

        $this->security->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_EDIT, 'Order')
            ->willReturn(true);

        $this->service->canInlineEdit('App\\Entity\\Order', 'Order');
    }

    // --- canViewList tests ---

    #[Test]
    public function canViewListReturnsTrueWhenGranted(): void
    {
        $this->security->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_INDEX, 'Product')
            ->willReturn(true);

        $this->assertTrue($this->service->canViewList('Product'));
    }

    #[Test]
    public function canViewListReturnsFalseWhenDenied(): void
    {
        $this->security->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_INDEX, 'Product')
            ->willReturn(false);

        $this->assertFalse($this->service->canViewList('Product'));
    }

    #[Test]
    public function canViewListUsesAdminIndexVoterAttribute(): void
    {
        $this->security->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_INDEX, 'custom-data-source')
            ->willReturn(true);

        $this->service->canViewList('custom-data-source');
    }
}
