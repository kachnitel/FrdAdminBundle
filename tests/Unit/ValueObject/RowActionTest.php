<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\ValueObject;

use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\TestCase;

class RowActionTest extends TestCase
{
    /**
     * @test
     */
    public function itCreatesActionWithRequiredFields(): void
    {
        $action = new RowAction(name: 'show', label: 'Show');

        $this->assertSame('show', $action->name);
        $this->assertSame('Show', $action->label);
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
    }

    /**
     * @test
     */
    public function itCreatesActionWithAllFields(): void
    {
        $action = new RowAction(
            name: 'duplicate',
            label: 'Duplicate',
            icon: '📋',
            route: 'app_product_duplicate',
            routeParams: ['source' => 'list'],
            url: null,
            permission: 'ROLE_ADMIN',
            voterAttribute: 'ADMIN_EDIT',
            condition: 'entity.status != "archived"',
            cssClass: 'btn-warning',
            confirmMessage: 'Are you sure?',
            openInNewTab: true,
            priority: 50,
            method: 'POST',
            template: 'custom/action.html.twig',
        );

        $this->assertSame('duplicate', $action->name);
        $this->assertSame('Duplicate', $action->label);
        $this->assertSame('📋', $action->icon);
        $this->assertSame('app_product_duplicate', $action->route);
        $this->assertSame(['source' => 'list'], $action->routeParams);
        $this->assertNull($action->url);
        $this->assertSame('ROLE_ADMIN', $action->permission);
        $this->assertSame('ADMIN_EDIT', $action->voterAttribute);
        $this->assertSame('entity.status != "archived"', $action->condition);
        $this->assertSame('btn-warning', $action->cssClass);
        $this->assertSame('Are you sure?', $action->confirmMessage);
        $this->assertTrue($action->openInNewTab);
        $this->assertSame(50, $action->priority);
        $this->assertSame('POST', $action->method);
        $this->assertSame('custom/action.html.twig', $action->template);
    }

    /**
     * @test
     */
    public function withCreatesModifiedCopy(): void
    {
        $original = new RowAction(name: 'show', label: 'Show', icon: '👀');
        $modified = $original->with(['label' => 'View', 'icon' => '🔍']);

        $this->assertSame('show', $modified->name);
        $this->assertSame('View', $modified->label);
        $this->assertSame('🔍', $modified->icon);

        // Original unchanged
        $this->assertSame('Show', $original->label);
        $this->assertSame('👀', $original->icon);
    }

    /**
     * @test
     */
    public function withCanSetNullValues(): void
    {
        $original = new RowAction(name: 'show', label: 'Show', icon: '👀');
        $modified = $original->with(['icon' => null]);

        $this->assertNull($modified->icon);
        $this->assertSame('👀', $original->icon);
    }

    /**
     * @test
     */
    public function mergeKeepsOriginalNonNullValues(): void
    {
        $original = new RowAction(
            name: 'show',
            label: 'Show',
            icon: '👀',
            priority: 10,
            voterAttribute: 'ADMIN_SHOW',
        );

        $override = new RowAction(
            name: 'show',
            label: 'View Details',
            icon: '🔍',
            condition: 'entity.active',
        );

        $merged = $original->merge($override);

        // Merged values from override
        $this->assertSame('View Details', $merged->label);
        $this->assertSame('🔍', $merged->icon);
        $this->assertSame('entity.active', $merged->condition);

        // Kept from original (override has null/default)
        $this->assertSame(10, $merged->priority);
        $this->assertSame('ADMIN_SHOW', $merged->voterAttribute);

        // Name is always kept from original
        $this->assertSame('show', $merged->name);
    }

    /**
     * @test
     */
    public function hasRouteReturnsTrueWhenRouteSet(): void
    {
        $action = new RowAction(name: 'edit', label: 'Edit', route: 'app_edit');
        $this->assertTrue($action->hasRoute());
    }

    /**
     * @test
     */
    public function hasRouteReturnsFalseWhenNoRoute(): void
    {
        $action = new RowAction(name: 'edit', label: 'Edit');
        $this->assertFalse($action->hasRoute());
    }

    /**
     * @test
     */
    public function requiresConfirmationReturnsTrueWhenMessageSet(): void
    {
        $action = new RowAction(name: 'delete', label: 'Delete', confirmMessage: 'Are you sure?');
        $this->assertTrue($action->requiresConfirmation());
    }

    /**
     * @test
     */
    public function requiresConfirmationReturnsFalseWhenNoMessage(): void
    {
        $action = new RowAction(name: 'edit', label: 'Edit');
        $this->assertFalse($action->requiresConfirmation());
    }

    /**
     * @test
     */
    public function isFormActionReturnsTrueWhenMethodSet(): void
    {
        $action = new RowAction(name: 'delete', label: 'Delete', method: 'DELETE');
        $this->assertTrue($action->isFormAction());
    }

    /**
     * @test
     */
    public function isFormActionReturnsFalseWhenNoMethod(): void
    {
        $action = new RowAction(name: 'show', label: 'Show');
        $this->assertFalse($action->isFormAction());
    }
}
