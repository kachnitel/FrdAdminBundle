<?php

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\Attribute\AdminRoutes;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AdminRouteRuntimeTest extends TestCase
{
    private RouterInterface&MockObject $router;
    private AttributeHelper&MockObject $attributeHelper;
    private AdminRouteRuntime $runtime;
    private ?AuthorizationCheckerInterface $authChecker = null;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $this->runtime = new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            $this->authChecker
        );
    }

    public function testHasRouteReturnsTrueWhenRouteExists(): void
    {
        $routes = new AdminRoutes([
            'index' => 'app_product_index',
            'edit' => 'app_product_edit',
        ]);

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($routes);

        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'index'));
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'edit'));
    }

    public function testHasRouteReturnsFalseWhenRouteDoesNotExist(): void
    {
        $routes = new AdminRoutes([
            'index' => 'app_product_index',
        ]);

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($routes);

        // Use a non-standard route name that's not in the generic admin routes
        $this->assertFalse($this->runtime->hasRoute('TestEntity', 'custom_action'));
    }

    public function testHasRouteReturnsFalseWhenNoRoutesAttribute(): void
    {
        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        // Use a non-standard route name that's not in the generic admin routes
        $this->assertFalse($this->runtime->hasRoute('TestEntity', 'custom_action'));
    }

    public function testGetRouteReturnsRouteNameWhenExists(): void
    {
        $routes = new AdminRoutes([
            'index' => 'app_product_index',
            'edit' => 'app_product_edit',
        ]);

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($routes);

        $this->assertEquals('app_product_index', $this->runtime->getRoute('TestEntity', 'index'));
        $this->assertEquals('app_product_edit', $this->runtime->getRoute('TestEntity', 'edit'));
    }

    public function testGetRouteReturnsNullWhenRouteDoesNotExist(): void
    {
        $routes = new AdminRoutes([
            'index' => 'app_product_index',
        ]);

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($routes);

        $this->assertNull($this->runtime->getRoute('TestEntity', 'delete'));
    }

    public function testGetPathGeneratesPathForRoute(): void
    {
        $routes = new AdminRoutes([
            'index' => 'app_product_index',
        ]);

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($routes);

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/products');

        $routeCollection = new RouteCollection();
        $routeCollection->add('app_product_index', $route);

        $this->router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('app_product_index', [])
            ->willReturn('/products');

        $path = $this->runtime->getPath('TestEntity', 'index');
        $this->assertEquals('/products', $path);
    }

    public function testGetPathAutoFillsIdParameter(): void
    {
        $entity = new class {
            public function getId(): int
            {
                return 42;
            }
        };

        $routes = new AdminRoutes([
            'edit' => 'app_product_edit',
        ]);

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($routes);

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/products/{id}/edit');

        $routeCollection = new RouteCollection();
        $routeCollection->add('app_product_edit', $route);

        $this->router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('app_product_edit', ['id' => 42])
            ->willReturn('/products/42/edit');

        $path = $this->runtime->getPath($entity, 'edit');
        $this->assertEquals('/products/42/edit', $path);
    }

    public function testGetPathAutoFillsClassParameter(): void
    {
        $routes = new AdminRoutes([
            'new' => 'app_product_new',
        ]);

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($routes);

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/admin/{class}/new');

        $routeCollection = new RouteCollection();
        $routeCollection->add('app_product_new', $route);

        $this->router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('app_product_new', ['class' => 'TestEntity'])
            ->willReturn('/admin/TestEntity/new');

        $path = $this->runtime->getPath(TestEntity::class, 'new', []);
        $this->assertEquals('/admin/TestEntity/new', $path);
    }

    public function testGetPathThrowsExceptionWhenRouteNotFound(): void
    {
        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Route "custom_action" not found');

        // Use a non-standard route name that's not in the generic admin routes
        $this->runtime->getPath('TestEntity', 'custom_action');
    }

    public function testIsRouteAccessibleReturnsTrueWhenRouteExists(): void
    {
        $route = $this->createMock(Route::class);
        $routeCollection = new RouteCollection();
        $routeCollection->add('app_product_index', $route);

        $this->router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->assertTrue($this->runtime->isRouteAccessible('app_product_index'));
    }

    public function testIsRouteAccessibleReturnsFalseWhenRouteDoesNotExist(): void
    {
        $routeCollection = new RouteCollection();

        $this->router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->assertFalse($this->runtime->isRouteAccessible('nonexistent_route'));
    }

    public function testIsRouteAccessibleReturnsTrueWhenNoAuthChecker(): void
    {
        $runtime = new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            null
        );

        $route = $this->createMock(Route::class);
        $routeCollection = new RouteCollection();
        $routeCollection->add('app_product_index', $route);

        $this->router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->assertTrue($runtime->isRouteAccessible('app_product_index'));
    }
}
