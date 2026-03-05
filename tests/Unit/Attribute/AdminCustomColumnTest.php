<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Attribute;
use Kachnitel\AdminBundle\Attribute\AdminCustomColumn;
use PHPUnit\Framework\TestCase;

/**
 * @group custom-columns
 */
class AdminCustomColumnTest extends TestCase
{
    /** @test */
    public function itCreatesAttributeWithRequiredFieldsOnly(): void
    {
        $column = new AdminCustomColumn(
            name: 'fullName',
            template: 'admin/columns/full_name.html.twig',
        );

        $this->assertSame('fullName', $column->name);
        $this->assertSame('admin/columns/full_name.html.twig', $column->template);
        $this->assertNull($column->label);
        $this->assertFalse($column->sortable);
    }

    /** @test */
    public function itCreatesAttributeWithAllFields(): void
    {
        $column = new AdminCustomColumn(
            name: 'activityBadge',
            template: 'admin/columns/activity_badge.html.twig',
            label: 'Activity',
            sortable: true,
        );

        $this->assertSame('activityBadge', $column->name);
        $this->assertSame('admin/columns/activity_badge.html.twig', $column->template);
        $this->assertSame('Activity', $column->label);
        $this->assertTrue($column->sortable);
    }

    /** @test */
    public function itIsRepeatableAttribute(): void
    {
        $reflection = new \ReflectionClass(AdminCustomColumn::class);
        $attrInstance = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertTrue(($attrInstance->flags & Attribute::IS_REPEATABLE) !== 0);
    }

    /** @test */
    public function itTargetsClasses(): void
    {
        $reflection = new \ReflectionClass(AdminCustomColumn::class);
        $attrInstance = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertTrue(($attrInstance->flags & Attribute::TARGET_CLASS) !== 0);
    }

    /** @test */
    public function sortableDefaultsToFalse(): void
    {
        $column = new AdminCustomColumn(name: 'computed', template: 'some/template.html.twig');

        $this->assertFalse($column->sortable);
    }

    /** @test */
    public function labelDefaultsToNull(): void
    {
        $column = new AdminCustomColumn(name: 'computed', template: 'some/template.html.twig');

        $this->assertNull($column->label);
    }
}
