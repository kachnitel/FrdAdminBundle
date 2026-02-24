<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Attribute;
use Kachnitel\AdminBundle\Attribute\AdminAction;
use PHPUnit\Framework\TestCase;

/**
 * @group row-actions
 */
class AdminActionTest extends TestCase
{
    /** @test */
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

    /** @test */
    public function itAcceptsStringExpressionCondition(): void
    {
        $action = new AdminAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.status == "pending"',
        );

        $this->assertSame('entity.status == "pending"', $action->condition);
        $this->assertIsString($action->condition);
    }

    /** @test */
    public function itAcceptsDiTupleCondition(): void
    {
        $condition = ['App\\Service\\ApprovalService', 'canApprove'];

        $action = new AdminAction(
            name: 'approve',
            label: 'Approve',
            condition: $condition,
        );

        $this->assertSame($condition, $action->condition);
        $this->assertIsArray($action->condition);
    }

    /** @test */
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
        $this->assertSame('entity.status != "archived"', $action->condition);
        $this->assertSame(50, $action->priority);
        $this->assertTrue($action->override);
    }

    /** @test */
    public function itIsRepeatableAttribute(): void
    {
        $reflection = new \ReflectionClass(AdminAction::class);
        $attrInstance = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertTrue(($attrInstance->flags & Attribute::IS_REPEATABLE) !== 0);
    }

    /** @test */
    public function itTargetsClasses(): void
    {
        $reflection = new \ReflectionClass(AdminAction::class);
        $attrInstance = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertTrue(($attrInstance->flags & Attribute::TARGET_CLASS) !== 0);
    }

    /** @test */
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
