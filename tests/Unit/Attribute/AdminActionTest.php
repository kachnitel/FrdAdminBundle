<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Attribute;
use Kachnitel\AdminBundle\Attribute\AdminAction;
use PHPUnit\Framework\TestCase;

class AdminActionTest extends TestCase
{
    /**
     * @test
     */
    public function itCreatesAttributeWithRequiredFields(): void
    {
        $action = new AdminAction(name: 'duplicate', label: 'Duplicate');

        $this->assertSame('duplicate', $action->name);
        $this->assertSame('Duplicate', $action->label);
        $this->assertNull($action->icon);
        $this->assertNull($action->route);
        $this->assertSame([], $action->routeParams);
        $this->assertNull($action->url);
        $this->assertNull($action->permission);
        $this->assertNull($action->voterAttribute);
        $this->assertNull($action->condition);
        $this->assertNull($action->cssClass);
        $this->assertNull($action->confirmMessage);
        $this->assertFalse($action->openInNewTab);
        $this->assertSame(100, $action->priority);
        $this->assertNull($action->method);
        $this->assertNull($action->template);
        $this->assertFalse($action->override);
    }

    /**
     * @test
     */
    public function itCreatesAttributeWithAllFields(): void
    {
        $action = new AdminAction(
            name: 'archive',
            label: 'Archive',
            icon: '📦',
            route: 'app_product_archive',
            routeParams: ['confirm' => '1'],
            url: null,
            permission: 'ROLE_EDITOR',
            voterAttribute: 'ADMIN_EDIT',
            condition: 'entity.status != "archived"',
            cssClass: 'btn-warning',
            confirmMessage: 'Archive this item?',
            openInNewTab: false,
            priority: 50,
            method: 'POST',
            template: 'custom/archive_button.html.twig',
            override: true,
        );

        $this->assertSame('archive', $action->name);
        $this->assertSame('Archive', $action->label);
        $this->assertSame('📦', $action->icon);
        $this->assertSame('app_product_archive', $action->route);
        $this->assertSame(['confirm' => '1'], $action->routeParams);
        $this->assertNull($action->url);
        $this->assertSame('ROLE_EDITOR', $action->permission);
        $this->assertSame('ADMIN_EDIT', $action->voterAttribute);
        $this->assertSame('entity.status != "archived"', $action->condition);
        $this->assertSame('btn-warning', $action->cssClass);
        $this->assertSame('Archive this item?', $action->confirmMessage);
        $this->assertFalse($action->openInNewTab);
        $this->assertSame(50, $action->priority);
        $this->assertSame('POST', $action->method);
        $this->assertSame('custom/archive_button.html.twig', $action->template);
        $this->assertTrue($action->override);
    }

    /**
     * @test
     */
    public function itIsRepeatableAttribute(): void
    {
        $reflection = new \ReflectionClass(AdminAction::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);
        $attrInstance = $attributes[0]->newInstance();
        $this->assertTrue(($attrInstance->flags & Attribute::IS_REPEATABLE) !== 0);
    }

    /**
     * @test
     */
    public function itCanTargetClasses(): void
    {
        $reflection = new \ReflectionClass(AdminAction::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $attrInstance = $attributes[0]->newInstance();
        $this->assertTrue(($attrInstance->flags & Attribute::TARGET_CLASS) !== 0);
    }

    /**
     * @test
     */
    public function overrideDefaultsToFalse(): void
    {
        $action = new AdminAction(name: 'test', label: 'Test');
        $this->assertFalse($action->override);
    }

    /**
     * @test
     */
    public function overrideCanBeSetToTrue(): void
    {
        $action = new AdminAction(name: 'edit', label: 'Modify', override: true);
        $this->assertTrue($action->override);
    }
}
