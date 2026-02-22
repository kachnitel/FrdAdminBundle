<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Test-driven development for ColumnPermissionService.
 *
 * Tests the service's ability to:
 * - Discover ColumnPermission attributes on entity properties
 * - Check user permissions for specific actions (ADMIN_SHOW, ADMIN_EDIT, ADMIN_DELETE)
 * - Cache permission maps for performance
 * - Handle entities without any column permissions
 * - Support role hierarchy through AuthorizationChecker
 *
 * @covers \Kachnitel\AdminBundle\Service\ColumnPermissionService
 */
class ColumnPermissionServiceTest extends TestCase
{
    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authorizationChecker;

    private ColumnPermissionService $service;

    protected function setUp(): void
    {
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->service = new ColumnPermissionService($this->authorizationChecker);
    }

    public function testGetColumnPermissionMapReturnsAttributeData(): void
    {
        $map = $this->service->getColumnPermissionMap(TestEntityWithPermissions::class);

        $this->assertArrayHasKey('salary', $map);
        $this->assertArrayHasKey('ssn', $map);
        $this->assertArrayNotHasKey('name', $map);
    }

    public function testColumnPermissionMapIncludesAllActions(): void
    {
        $map = $this->service->getColumnPermissionMap(TestEntityWithPermissions::class);

        $salaryPermissions = $map['salary'];
        $this->assertArrayHasKey(AdminEntityVoter::ADMIN_SHOW, $salaryPermissions);
        $this->assertArrayHasKey(AdminEntityVoter::ADMIN_EDIT, $salaryPermissions);
        $this->assertSame('ROLE_HR', $salaryPermissions[AdminEntityVoter::ADMIN_SHOW]);
        $this->assertSame('ROLE_HR_EDIT', $salaryPermissions[AdminEntityVoter::ADMIN_EDIT]);
    }

    public function testCanPerformActionReturnsTrueWhenGranted(): void
    {
        $this->authorizationChecker
            ->method('isGranted')
            ->with('ROLE_HR')
            ->willReturn(true);

        $result = $this->service->canPerformAction(
            TestEntityWithPermissions::class,
            'salary',
            AdminEntityVoter::ADMIN_SHOW
        );

        $this->assertTrue($result);
    }

    public function testCanPerformActionReturnsFalseWhenDenied(): void
    {
        $this->authorizationChecker
            ->method('isGranted')
            ->with('ROLE_HR')
            ->willReturn(false);

        $result = $this->service->canPerformAction(
            TestEntityWithPermissions::class,
            'salary',
            AdminEntityVoter::ADMIN_SHOW
        );

        $this->assertFalse($result);
    }

    public function testCanPerformActionReturnsTrueForUnrestrictedColumn(): void
    {
        $this->authorizationChecker
            ->expects($this->never())
            ->method('isGranted');

        $result = $this->service->canPerformAction(
            TestEntityWithPermissions::class,
            'name',
            AdminEntityVoter::ADMIN_SHOW
        );

        $this->assertTrue($result);
    }

    public function testCanPerformActionReturnsTrueForUnrestrictedAction(): void
    {
        $this->authorizationChecker
            ->expects($this->never())
            ->method('isGranted');

        $result = $this->service->canPerformAction(
            TestEntityWithPermissions::class,
            'salary',
            AdminEntityVoter::ADMIN_DELETE // Only SHOW and EDIT restricted on salary
        );

        $this->assertTrue($result);
    }

    public function testCanPerformActionSupportsArrayOfRoles(): void
    {
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(fn($role) => $role === 'ROLE_SECURITY_OFFICER');

        $result = $this->service->canPerformAction(
            TestEntityWithPermissions::class,
            'ssn', // Requires ROLE_HR OR ROLE_SECURITY_OFFICER
            AdminEntityVoter::ADMIN_SHOW
        );

        $this->assertTrue($result);
    }

    public function testCanPerformActionWithArrayReturnsFalseWhenNoRoleMatches(): void
    {
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturn(false);

        $result = $this->service->canPerformAction(
            TestEntityWithPermissions::class,
            'ssn',
            AdminEntityVoter::ADMIN_SHOW
        );

        $this->assertFalse($result);
    }

    public function testGetPermittedColumnsReturnsFilteredList(): void
    {
        // Use callback to cover all roles that may be queried (ROLE_HR, ROLE_SECURITY_OFFICER, etc.)
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(fn($role) => $role === 'ROLE_HR');

        $permitted = $this->service->getPermittedColumns(
            TestEntityWithPermissions::class,
            ['name', 'salary', 'ssn'],
            AdminEntityVoter::ADMIN_SHOW
        );

        $this->assertContains('name', $permitted);
        $this->assertContains('salary', $permitted);
        $this->assertContains('ssn', $permitted); // ROLE_HR matches one of required roles
    }

    public function testGetPermittedColumnsFiltersDeniedColumns(): void
    {
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturn(false);

        $permitted = $this->service->getPermittedColumns(
            TestEntityWithPermissions::class,
            ['name', 'salary', 'ssn'],
            AdminEntityVoter::ADMIN_EDIT
        );

        $this->assertContains('name', $permitted);
        $this->assertNotContains('salary', $permitted);
        $this->assertNotContains('ssn', $permitted);
    }

    public function testPermissionMapIsCached(): void
    {
        $map1 = $this->service->getColumnPermissionMap(TestEntityWithPermissions::class);
        $map2 = $this->service->getColumnPermissionMap(TestEntityWithPermissions::class);

        $this->assertSame($map1, $map2);
    }

    public function testGetColumnPermissionMapReturnsEmptyArrayForEntityWithoutPermissions(): void
    {
        $map = $this->service->getColumnPermissionMap(TestEntityWithoutPermissions::class);

        $this->assertEmpty($map);
    }

    public function testGetDeniedColumnsForActionReturnsCorrectList(): void
    {
        // willReturn(false) covers all roles including ROLE_HR, ROLE_SECURITY_OFFICER, etc.
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $denied = $this->service->getDeniedColumnsForAction(
            TestEntityWithPermissions::class,
            AdminEntityVoter::ADMIN_SHOW
        );

        $this->assertContains('salary', $denied);
        $this->assertContains('ssn', $denied);
        $this->assertNotContains('name', $denied);
    }

    public function testCanPerformActionOnEntityInstance(): void
    {
        $entity = new TestEntityWithPermissions();

        $this->authorizationChecker
            ->method('isGranted')
            ->with('ROLE_HR')
            ->willReturn(true);

        $result = $this->service->canPerformActionOnEntity(
            $entity,
            'salary',
            AdminEntityVoter::ADMIN_SHOW
        );

        $this->assertTrue($result);
    }

    public function testGetDeniedColumnsReturnsAllRestrictedColumnNames(): void
    {
        // getDeniedColumns returns all columns with ANY restriction, not user-filtered
        $denied = $this->service->getDeniedColumns(TestEntityWithPermissions::class);

        $this->assertContains('salary', $denied);
        $this->assertContains('ssn', $denied);
        $this->assertNotContains('name', $denied);
    }
}

/**
 * Test fixture: Entity with column permissions.
 */
class TestEntityWithPermissions
{
    private string $name = '';

    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
    ])]
    private float $salary = 0.0;

    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => ['ROLE_HR', 'ROLE_SECURITY_OFFICER'],
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_SECURITY_OFFICER',
        AdminEntityVoter::ADMIN_DELETE => 'ROLE_ADMIN',
    ])]
    private string $ssn = '';
}

/**
 * Test fixture: Entity without column permissions.
 */
class TestEntityWithoutPermissions
{
    private string $name = '';
    private string $email = '';
}