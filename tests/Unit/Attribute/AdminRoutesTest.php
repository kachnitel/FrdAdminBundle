<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Kachnitel\AdminBundle\Attribute\AdminRoutes;
use PHPUnit\Framework\TestCase;

class AdminRoutesTest extends TestCase
{
    /**
     * @test
     */
    public function defaultRoutesIsEmptyArray(): void
    {
        $routes = new AdminRoutes();

        $this->assertSame([], $routes->all());
    }

    /**
     * @test
     */
    public function routesCanBeSetViaConstructor(): void
    {
        $routeMap = [
            'index' => 'app_product_index',
            'new' => 'app_product_new',
            'show' => 'app_product_show',
            'edit' => 'app_product_edit',
            'delete' => 'app_product_delete',
        ];

        $routes = new AdminRoutes($routeMap);

        $this->assertSame($routeMap, $routes->all());
    }

    /**
     * @test
     */
    public function getReturnsRouteForKey(): void
    {
        $routes = new AdminRoutes([
            'index' => 'app_product_index',
            'edit' => 'app_product_edit',
        ]);

        $this->assertSame('app_product_index', $routes->get('index'));
        $this->assertSame('app_product_edit', $routes->get('edit'));
    }

    /**
     * @test
     */
    public function getReturnsNullForUndefinedKey(): void
    {
        $routes = new AdminRoutes(['index' => 'app_product_index']);

        $this->assertNull($routes->get('delete'));
        $this->assertNull($routes->get('nonexistent'));
    }

    /**
     * @test
     */
    public function hasReturnsTrueForExistingKey(): void
    {
        $routes = new AdminRoutes([
            'index' => 'app_product_index',
            'edit' => 'app_product_edit',
        ]);

        $this->assertTrue($routes->has('index'));
        $this->assertTrue($routes->has('edit'));
    }

    /**
     * @test
     */
    public function hasReturnsFalseForMissingKey(): void
    {
        $routes = new AdminRoutes(['index' => 'app_product_index']);

        $this->assertFalse($routes->has('delete'));
        $this->assertFalse($routes->has('nonexistent'));
    }

    /**
     * @test
     */
    public function hasReturnsFalseForEmptyRoutes(): void
    {
        $routes = new AdminRoutes();

        $this->assertFalse($routes->has('index'));
    }

    /**
     * @test
     */
    public function allReturnsAllRoutes(): void
    {
        $routeMap = [
            'index' => 'app_product_index',
            'new' => 'app_product_new',
        ];

        $routes = new AdminRoutes($routeMap);

        $this->assertSame($routeMap, $routes->all());
        $this->assertCount(2, $routes->all());
    }

    /**
     * @test
     */
    public function attributeCanBeAppliedToClass(): void
    {
        $reflection = new \ReflectionClass(AdminRoutes::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);

        $attrInstance = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS, $attrInstance->flags);
    }

    /**
     * @test
     */
    public function canDefinePartialRoutes(): void
    {
        $routes = new AdminRoutes([
            'index' => 'custom_index',
            'show' => 'custom_show',
        ]);

        $this->assertTrue($routes->has('index'));
        $this->assertTrue($routes->has('show'));
        $this->assertFalse($routes->has('new'));
        $this->assertFalse($routes->has('edit'));
        $this->assertFalse($routes->has('delete'));
    }

    /**
     * @test
     */
    public function routeNamesCanContainAnyString(): void
    {
        $routes = new AdminRoutes([
            'index' => 'admin.products.list',
            'custom_action' => 'products:export:csv',
        ]);

        $this->assertSame('admin.products.list', $routes->get('index'));
        $this->assertSame('products:export:csv', $routes->get('custom_action'));
    }
}
