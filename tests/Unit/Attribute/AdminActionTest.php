<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Attribute;
use Kachnitel\AdminBundle\Attribute\AdminAction;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;

#[PHPUnit\Group('row-actions')]
final class AdminActionTest extends TestCase
{
    #[PHPUnit\Test]
    public function itCreatesAttributeWithRequiredFieldsOnly(): void
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

    #[PHPUnit\Test]
    public function itAcceptsStringExpressionCondition(): void
    {
        $action = new AdminAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.getStatus() == "pending"',
        );

        $this->assertSame('entity.getStatus() == "pending"', $action->condition);
    }

    #[PHPUnit\Test]
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
            condition: 'entity.getStatus() != "archived"',
            cssClass: 'btn-warning',
            confirmMessage: 'Archive this item?',
            openInNewTab: false,
            priority: 50,
            method: 'POST',
            template: 'custom/archive_button.html.twig',
            override: true,
        );

        $this->assertSame('archive', $action->name);
        $this->assertSame('entity.getStatus() != "archived"', $action->condition);
        $this->assertSame(50, $action->priority);
        $this->assertTrue($action->override);
    }

    #[PHPUnit\Test]
    public function itIsRepeatableAttribute(): void
    {
        $reflection = new \ReflectionClass(AdminAction::class);
        $attrInstance = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertNotSame(0, $attrInstance->flags & Attribute::IS_REPEATABLE);
    }

    #[PHPUnit\Test]
    public function itTargetsClasses(): void
    {
        $reflection = new \ReflectionClass(AdminAction::class);
        $attrInstance = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertNotSame(0, $attrInstance->flags & Attribute::TARGET_CLASS);
    }

    #[PHPUnit\Test]
    public function conditionTypeIsFlexible(): void
    {
        // Both forms must be accepted without type errors
        $withExpression = new AdminAction(name: 'a', label: 'A', condition: 'entity.active');
        $withTuple = new AdminAction(name: 'b', label: 'B', condition: [self::class, 'someMethod']);
        $withNull = new AdminAction(name: 'c', label: 'C');

        $this->assertIsString($withExpression->condition);
        $this->assertIsArray($withTuple->condition);
        $this->assertNull($withNull->condition);
    }
}
