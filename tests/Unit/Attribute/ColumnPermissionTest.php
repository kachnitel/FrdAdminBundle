<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\Attribute\ColumnPermission
 */
class ColumnPermissionTest extends TestCase
{
    public function testEmptyPermissionsIsDefault(): void
    {
        $permission = new ColumnPermission();

        $this->assertSame([], $permission->getPermissions());
    }

    public function testSingleActionPermission(): void
    {
        $permission = new ColumnPermission([
            AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        ]);

        $this->assertSame('ROLE_HR', $permission->getPermission(AdminEntityVoter::ADMIN_SHOW));
    }

    public function testMultipleActionPermissions(): void
    {
        $permission = new ColumnPermission([
            AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
            AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
        ]);

        $this->assertSame('ROLE_HR', $permission->getPermission(AdminEntityVoter::ADMIN_SHOW));
        $this->assertSame('ROLE_HR_EDIT', $permission->getPermission(AdminEntityVoter::ADMIN_EDIT));
    }

    public function testArrayOfRolesPermission(): void
    {
        $roles = ['ROLE_HR', 'ROLE_SECURITY_OFFICER'];
        $permission = new ColumnPermission([
            AdminEntityVoter::ADMIN_SHOW => $roles,
        ]);

        $this->assertSame($roles, $permission->getPermission(AdminEntityVoter::ADMIN_SHOW));
    }

    public function testGetPermissionReturnsNullForUnknownAction(): void
    {
        $permission = new ColumnPermission([
            AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        ]);

        $this->assertNull($permission->getPermission(AdminEntityVoter::ADMIN_EDIT));
    }

    public function testHasPermission(): void
    {
        $permission = new ColumnPermission([
            AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        ]);

        $this->assertTrue($permission->hasPermission(AdminEntityVoter::ADMIN_SHOW));
        $this->assertFalse($permission->hasPermission(AdminEntityVoter::ADMIN_EDIT));
    }

    public function testIsPropertyLevelAttribute(): void
    {
        $reflection = new \ReflectionClass(ColumnPermission::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    public function testCanBeReadFromProperty(): void
    {
        $testClass = new class {
            #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => 'ROLE_MANAGER'])]
            public string $salary = '';
        };

        $reflection = new \ReflectionProperty($testClass, 'salary');
        $attributes = $reflection->getAttributes(ColumnPermission::class);

        $this->assertCount(1, $attributes);
        $permission = $attributes[0]->newInstance();
        $this->assertSame('ROLE_MANAGER', $permission->getPermission(AdminEntityVoter::ADMIN_SHOW));
    }
}
