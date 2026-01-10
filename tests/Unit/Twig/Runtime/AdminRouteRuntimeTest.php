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

    /**
     * @test
     */
    public function canPerformActionChecksPermission(): void
    {
        $entity = new class {
            public function getId(): int { return 1; }
        };

        // Generic routes exist for 'index' action
        $result = $this->runtime->hasRoute($entity, 'index');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleChecksRouteExistence(): void
    {
        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        // Should return false for routes that don't exist in generic admin routes
        $result = $this->runtime->isActionAccessible('Product', 'unknown_action');
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleWithValidAction(): void
    {
        // Generic routes exist for standard admin actions
        $result = $this->runtime->hasRoute('Product', 'index');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function getPathWithEntitySlugParameter(): void
    {
        $entity = new class {
            public function getId(): int { return 99; }
        };

        $routes = new AdminRoutes([
            'show' => 'app_admin_entity_show',
        ]);

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($routes);

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/admin/{entitySlug}/{id}');

        $routeCollection = new RouteCollection();
        $routeCollection->add('app_admin_entity_show', $route);

        $this->router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->willReturnCallback(function ($name, $params) {
                return '/admin/' . ($params['entitySlug'] ?? 'unknown') . '/' . ($params['id'] ?? '');
            });

        // Using a class without getId() to test entity slug parameter
        $path = $this->runtime->getPath(TestEntity::class, 'show', []);
        $this->assertStringContainsString('test-entity', $path);
    }

    /**
     * @test
     */
    public function hasRouteReturnsTrueForGenericAdminRoutes(): void
    {
        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        // Generic admin routes should be accessible
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'index'));
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'show'));
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'edit'));
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'new'));
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'delete'));
    }

    /**
     * @test
     */
    public function getRouteReturnsFallbackGenericAdminRoute(): void
    {
        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        // Should return generic admin routes when no AdminRoutes attribute
        $this->assertEquals('app_admin_entity_index', $this->runtime->getRoute('TestEntity', 'index'));
        $this->assertEquals('app_admin_entity_show', $this->runtime->getRoute('TestEntity', 'show'));
        $this->assertEquals('app_admin_entity_edit', $this->runtime->getRoute('TestEntity', 'edit'));
    }

    /**
     * @test
     */
    public function getPathWithMultipleParameters(): void
    {
        $entity = new class {
            public function getId(): int { return 5; }
        };

        $routes = new AdminRoutes([
            'edit' => 'app_entity_edit',
        ]);

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($routes);

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/admin/{class}/{id}/edit');

        $routeCollection = new RouteCollection();
        $routeCollection->add('app_entity_edit', $route);

        $this->router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->willReturnCallback(function ($name, $params) {
                // Just verify the parameters are passed
                return '/admin/' . ($params['class'] ?? 'unknown') . '/' . ($params['id'] ?? '0') . '/edit';
            });

        $path = $this->runtime->getPath($entity, 'edit', []);
        $this->assertStringContainsString('/edit', $path);
        $this->assertStringContainsString('5', $path);
    }

    /**
     * @test
     */
    public function getPathPreservesExistingParameters(): void
    {
        $routes = new AdminRoutes([
            'custom' => 'app_custom_action',
        ]);

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($routes);

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/admin/{custom}');

        $routeCollection = new RouteCollection();
        $routeCollection->add('app_custom_action', $route);

        $this->router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('app_custom_action', ['custom' => 'value123'])
            ->willReturn('/admin/value123');

        $path = $this->runtime->getPath('TestEntity', 'custom', ['custom' => 'value123']);
        $this->assertEquals('/admin/value123', $path);
    }

    /**
     * @test
     */
    public function canPerformActionReturnsTrueForAccessibleAction(): void
    {
        $entity = new class {
            public function getId(): int { return 1; }
        };

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $this->authChecker
            ->method('isGranted')
            ->willReturn(true);

        $result = $this->runtime->canPerformAction($entity, 'index');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleReturnsFalseWhenAuthDenied(): void
    {
        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $this->authChecker
            ->method('isGranted')
            ->willReturn(false);

        $result = $this->runtime->isActionAccessible('Product', 'show');
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleReturnsTrueForIndexWithPermission(): void
    {
        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $this->authChecker
            ->method('isGranted')
            ->willReturn(true);

        $result = $this->runtime->isActionAccessible('Product', 'index');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleReturnsTrueForShowWithPermission(): void
    {
        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $this->authChecker
            ->method('isGranted')
            ->willReturn(true);

        $result = $this->runtime->isActionAccessible('Product', 'show');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleReturnsTrueForDeleteWithPermission(): void
    {
        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $this->authChecker
            ->method('isGranted')
            ->willReturn(true);

        $result = $this->runtime->isActionAccessible('Product', 'delete');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleReturnsTrueForNewWithFormAndPermission(): void
    {
        $formRegistry = $this->createMock(\Symfony\Component\Form\FormRegistryInterface::class);
        $entityDiscovery = $this->createMock(\Kachnitel\AdminBundle\Service\EntityDiscoveryService::class);

        $runtime = new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            $this->authChecker,
            $entityDiscovery,
            $formRegistry
        );

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $this->authChecker
            ->method('isGranted')
            ->willReturn(true);

        $entityDiscovery
            ->method('resolveEntityClass')
            ->willReturn(TestEntity::class);

        $entityDiscovery
            ->method('getAdminAttribute')
            ->willReturn(null);

        $formRegistry
            ->method('hasType')
            ->willReturn(true);

        $result = $runtime->isActionAccessible('TestEntity', 'new');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleReturnsFalseForNewWithoutForm(): void
    {
        $formRegistry = $this->createMock(\Symfony\Component\Form\FormRegistryInterface::class);
        $entityDiscovery = $this->createMock(\Kachnitel\AdminBundle\Service\EntityDiscoveryService::class);

        $runtime = new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            $this->authChecker,
            $entityDiscovery,
            $formRegistry
        );

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $this->authChecker
            ->method('isGranted')
            ->willReturn(true);

        $entityDiscovery
            ->method('resolveEntityClass')
            ->willReturn(TestEntity::class);

        $entityDiscovery
            ->method('getAdminAttribute')
            ->willReturn(null);

        $formRegistry
            ->method('hasType')
            ->willReturn(false);

        $result = $runtime->isActionAccessible('TestEntity', 'new');
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleReturnsTrueForEditWithFormAndPermission(): void
    {
        $formRegistry = $this->createMock(\Symfony\Component\Form\FormRegistryInterface::class);
        $entityDiscovery = $this->createMock(\Kachnitel\AdminBundle\Service\EntityDiscoveryService::class);

        $runtime = new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            $this->authChecker,
            $entityDiscovery,
            $formRegistry
        );

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $this->authChecker
            ->method('isGranted')
            ->willReturn(true);

        $entityDiscovery
            ->method('resolveEntityClass')
            ->willReturn(TestEntity::class);

        $entityDiscovery
            ->method('getAdminAttribute')
            ->willReturn(null);

        $formRegistry
            ->method('hasType')
            ->willReturn(true);

        $result = $runtime->isActionAccessible('TestEntity', 'edit');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleReturnsFalseForEditWithoutForm(): void
    {
        $formRegistry = $this->createMock(\Symfony\Component\Form\FormRegistryInterface::class);
        $entityDiscovery = $this->createMock(\Kachnitel\AdminBundle\Service\EntityDiscoveryService::class);

        $runtime = new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            $this->authChecker,
            $entityDiscovery,
            $formRegistry
        );

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $this->authChecker
            ->method('isGranted')
            ->willReturn(true);

        $entityDiscovery
            ->method('resolveEntityClass')
            ->willReturn(TestEntity::class);

        $entityDiscovery
            ->method('getAdminAttribute')
            ->willReturn(null);

        $formRegistry
            ->method('hasType')
            ->willReturn(false);

        $result = $runtime->isActionAccessible('TestEntity', 'edit');
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleReturnsTrueWithNoAuthChecker(): void
    {
        $runtime = new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            null  // No auth checker
        );

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $result = $runtime->isActionAccessible('Product', 'index');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isActionAccessibleReturnsTrueForNewEditWithNoFormDependencies(): void
    {
        $runtime = new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            null,  // No auth checker
            null,  // No entity discovery
            null   // No form registry
        );

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        // Should assume form exists when dependencies are not available
        $result = $runtime->isActionAccessible('Product', 'new');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function hasFormReturnsTrueWhenEntityDiscoveryThrowsException(): void
    {
        $formRegistry = $this->createMock(\Symfony\Component\Form\FormRegistryInterface::class);
        $entityDiscovery = $this->createMock(\Kachnitel\AdminBundle\Service\EntityDiscoveryService::class);

        $runtime = new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            null,
            $entityDiscovery,
            $formRegistry
        );

        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn(null);

        $entityDiscovery
            ->method('resolveEntityClass')
            ->willThrowException(new \Exception('Entity not found'));

        $formRegistry
            ->method('hasType')
            ->willReturn(true);

        $result = $runtime->isActionAccessible('UnknownEntity', 'new');
        $this->assertTrue($result);
    }
}
