<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use PHPUnit\Framework\TestCase;

class ColumnPermissionTest extends TestCase
{
    /**
     * @test
     */
    public function roleIsSetCorrectly(): void
    {
        $permission = new ColumnPermission('ROLE_HR');

        $this->assertSame('ROLE_HR', $permission->role);
    }

    /**
     * @test
     */
    public function isPropertyLevelAttribute(): void
    {
        $reflection = new \ReflectionClass(ColumnPermission::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    /**
     * @test
     */
    public function canBeReadFromProperty(): void
    {
        $testClass = new class {
            #[ColumnPermission('ROLE_MANAGER')]
            public string $salary;
        };

        $reflection = new \ReflectionProperty($testClass, 'salary');
        $attributes = $reflection->getAttributes(ColumnPermission::class);

        $this->assertCount(1, $attributes);
        $permission = $attributes[0]->newInstance();
        $this->assertSame('ROLE_MANAGER', $permission->role);
    }
}
