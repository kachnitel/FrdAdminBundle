<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\Attribute\AdminRoutes;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Runtime\ActionAccessibilityChecker;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AllowMockObjectsWithoutExpectations]
final class AdminRouteRuntimeTest extends TestCase
{
    /** @var RouterInterface&MockObject */
    private RouterInterface $router;

    /** @var AttributeHelper&MockObject */
    private AttributeHelper $attributeHelper;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    private AdminRouteRuntime $runtime;

    protected function setUp(): void
    {
        $this->router          = $this->createMock(RouterInterface::class);
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->authChecker     = $this->createMock(AuthorizationCheckerInterface::class);

        $this->runtime = $this->makeRuntime();
    }

    /**
     * Build an AdminRouteRuntime with an ActionAccessibilityChecker wired from the given dependencies.
     *
     * @param AuthorizationCheckerInterface|null|false $authChecker
     *   false (default) = use $this->authChecker; null = no auth checker
     */
    private function makeRuntime(
        AuthorizationCheckerInterface|null|false $authChecker = false,
        ?EntityDiscoveryService $entityDiscovery = null,
        ?FormRegistryInterface $formRegistry = null,
        string $formNamespace = 'App\\Form\\',
        string $formSuffix = 'FormType',
        string $entityNamespace = 'App\\Entity\\',
    ): AdminRouteRuntime {
        $auth    = $authChecker === false ? $this->authChecker : $authChecker;
        $checker = new ActionAccessibilityChecker(
            $auth,
            $entityDiscovery,
            $formRegistry,
            $formNamespace,
            $formSuffix,
            $entityNamespace,
        );

        return new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            $checker,
            $auth,
        );
    }

    public function testHasRouteReturnsTrueWhenRouteExists(): void
    {
        $routes = new AdminRoutes([
            'index' => 'app_product_index',
            'edit'  => 'app_product_edit',
        ]);

        $this->attributeHelper->method('getAttribute')->willReturn($routes);

        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'index'));
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'edit'));
    }

    public function testHasRouteReturnsFalseWhenRouteDoesNotExist(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(new AdminRoutes(['index' => 'app_product_index']));

        $this->assertFalse($this->runtime->hasRoute('TestEntity', 'custom_action'));
    }

    public function testHasRouteReturnsFalseWhenNoRoutesAttribute(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(null);

        $this->assertFalse($this->runtime->hasRoute('TestEntity', 'custom_action'));
    }

    public function testGetRouteReturnsConfiguredRoute(): void
    {
        $routes = new AdminRoutes([
            'index' => 'app_product_index',
            'edit'  => 'app_product_edit',
        ]);

        $this->attributeHelper->method('getAttribute')->willReturn($routes);

        $this->assertSame('app_product_index', $this->runtime->getRoute('TestEntity', 'index'));
        $this->assertSame('app_product_edit', $this->runtime->getRoute('TestEntity', 'edit'));
    }

    public function testGetRouteReturnsNullWhenRouteDoesNotExist(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(new AdminRoutes(['index' => 'app_product_index']));

        $this->assertNull($this->runtime->getRoute('TestEntity', 'delete'));
    }

    public function testGetPathGeneratesPathForRoute(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(new AdminRoutes(['index' => 'app_product_index']));

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/products');

        $collection = new RouteCollection();
        $collection->add('app_product_index', $route);
        $this->router->method('getRouteCollection')->willReturn($collection);

        $this->router->expects($this->once())->method('generate')
            ->with('app_product_index', [])
            ->willReturn('/products');

        $this->assertSame('/products', $this->runtime->getPath('TestEntity', 'index'));
    }

    public function testGetPathAutoFillsIdParameter(): void
    {
        $entity = new class {
            public function getId(): int { return 42; }
        };

        $this->attributeHelper->method('getAttribute')->willReturn(new AdminRoutes(['edit' => 'app_product_edit']));

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/products/{id}/edit');

        $collection = new RouteCollection();
        $collection->add('app_product_edit', $route);
        $this->router->method('getRouteCollection')->willReturn($collection);

        $this->router->expects($this->once())->method('generate')
            ->with('app_product_edit', ['id' => 42])
            ->willReturn('/products/42/edit');

        $this->assertSame('/products/42/edit', $this->runtime->getPath($entity, 'edit'));
    }

    public function testGetPathAutoFillsClassParameter(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(new AdminRoutes(['new' => 'app_product_new']));

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/admin/{class}/new');

        $collection = new RouteCollection();
        $collection->add('app_product_new', $route);
        $this->router->method('getRouteCollection')->willReturn($collection);

        $this->router->expects($this->once())->method('generate')
            ->with('app_product_new', ['class' => 'TestEntity'])
            ->willReturn('/admin/TestEntity/new');

        $this->assertSame('/admin/TestEntity/new', $this->runtime->getPath(TestEntity::class, 'new', []));
    }

    public function testGetPathThrowsExceptionWhenRouteNotFound(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Route "custom_action" not found');

        $this->runtime->getPath('TestEntity', 'custom_action');
    }

    public function testIsRouteAccessibleReturnsTrueWhenRouteExists(): void
    {
        $collection = new RouteCollection();
        $collection->add('app_product_index', $this->createStub(Route::class));
        $this->router->method('getRouteCollection')->willReturn($collection);

        $this->assertTrue($this->runtime->isRouteAccessible('app_product_index'));
    }

    public function testIsRouteAccessibleReturnsFalseWhenRouteDoesNotExist(): void
    {
        $this->router->method('getRouteCollection')->willReturn(new RouteCollection());

        $this->assertFalse($this->runtime->isRouteAccessible('nonexistent_route'));
    }

    public function testIsRouteAccessibleReturnsTrueWhenNoAuthChecker(): void
    {
        $runtime = $this->makeRuntime(authChecker: null);

        $collection = new RouteCollection();
        $collection->add('app_product_index', $this->createStub(Route::class));
        $this->router->method('getRouteCollection')->willReturn($collection);

        $this->assertTrue($runtime->isRouteAccessible('app_product_index'));
    }

    #[Test]
    public function canPerformActionChecksPermission(): void
    {
        $entity = new class {
            public function getId(): int { return 1; }
        };

        $this->assertTrue($this->runtime->hasRoute($entity, 'index'));
    }

    #[Test]
    public function isActionAccessibleChecksRouteExistence(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(null);

        $this->assertFalse($this->runtime->isActionAccessible('Product', 'unknown_action'));
    }

    #[Test]
    public function isActionAccessibleWithValidAction(): void
    {
        $this->assertTrue($this->runtime->hasRoute('Product', 'index'));
    }

    #[Test]
    public function getPathWithEntitySlugParameter(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(new AdminRoutes(['show' => 'app_admin_entity_show']));

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/admin/{entitySlug}/{id}');

        $collection = new RouteCollection();
        $collection->add('app_admin_entity_show', $route);
        $this->router->method('getRouteCollection')->willReturn($collection);

        $this->router->expects($this->once())->method('generate')
            ->willReturnCallback(fn ($name, $params) => '/admin/' . ($params['entitySlug'] ?? 'unknown') . '/' . ($params['id'] ?? ''));

        $path = $this->runtime->getPath(TestEntity::class, 'show', []);
        $this->assertStringContainsString('test-entity', $path);
    }

    #[Test]
    public function hasRouteReturnsTrueForGenericAdminRoutes(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(null);

        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'index'));
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'show'));
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'edit'));
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'new'));
        $this->assertTrue($this->runtime->hasRoute('TestEntity', 'delete'));
    }

    #[Test]
    public function getRouteReturnsFallbackGenericAdminRoute(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(null);

        $this->assertSame('app_admin_entity_index', $this->runtime->getRoute('TestEntity', 'index'));
        $this->assertSame('app_admin_entity_show', $this->runtime->getRoute('TestEntity', 'show'));
        $this->assertSame('app_admin_entity_edit', $this->runtime->getRoute('TestEntity', 'edit'));
    }

    #[Test]
    public function getPathWithMultipleParameters(): void
    {
        $entity = new class {
            public function getId(): int { return 5; }
        };

        $this->attributeHelper->method('getAttribute')->willReturn(new AdminRoutes(['edit' => 'app_entity_edit']));

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/admin/{class}/{id}/edit');

        $collection = new RouteCollection();
        $collection->add('app_entity_edit', $route);
        $this->router->method('getRouteCollection')->willReturn($collection);

        $this->router->expects($this->once())->method('generate')
            ->willReturnCallback(fn ($name, $params) => '/admin/' . ($params['class'] ?? 'unknown') . '/' . ($params['id'] ?? '0') . '/edit');

        $path = $this->runtime->getPath($entity, 'edit', []);
        $this->assertStringContainsString('/edit', $path);
        $this->assertStringContainsString('5', $path);
    }

    #[Test]
    public function getPathPreservesExistingParameters(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(new AdminRoutes(['custom' => 'app_custom_action']));

        $route = $this->createMock(Route::class);
        $route->method('getPath')->willReturn('/admin/{custom}');

        $collection = new RouteCollection();
        $collection->add('app_custom_action', $route);
        $this->router->method('getRouteCollection')->willReturn($collection);

        $this->router->expects($this->once())->method('generate')
            ->with('app_custom_action', ['custom' => 'value123'])
            ->willReturn('/admin/value123');

        $this->assertSame('/admin/value123', $this->runtime->getPath('TestEntity', 'custom', ['custom' => 'value123']));
    }

    #[Test]
    public function canPerformActionReturnsTrueForAccessibleAction(): void
    {
        $entity = new class {
            public function getId(): int { return 1; }
        };

        $this->attributeHelper->method('getAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->assertTrue($this->runtime->canPerformAction($entity, 'index'));
    }

    #[Test]
    public function isActionAccessibleReturnsFalseWhenAuthDenied(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($this->runtime->isActionAccessible('Product', 'show'));
    }

    #[Test]
    public function isActionAccessibleReturnsTrueForIndexWithPermission(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->assertTrue($this->runtime->isActionAccessible('Product', 'index'));
    }

    #[Test]
    public function isActionAccessibleReturnsTrueForShowWithPermission(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->assertTrue($this->runtime->isActionAccessible('Product', 'show'));
    }

    #[Test]
    public function isActionAccessibleReturnsTrueForDeleteWithPermission(): void
    {
        $this->attributeHelper->method('getAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->assertTrue($this->runtime->isActionAccessible('Product', 'delete'));
    }

    #[Test]
    public function isActionAccessibleReturnsTrueForNewWithFormAndPermission(): void
    {
        /** @var FormRegistryInterface&MockObject $formRegistry */
        $formRegistry    = $this->createMock(FormRegistryInterface::class);
        /** @var EntityDiscoveryService&MockObject $entityDiscovery */
        $entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $runtime         = $this->makeRuntime(formRegistry: $formRegistry, entityDiscovery: $entityDiscovery);

        $this->attributeHelper->method('getAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(true);
        $entityDiscovery->method('resolveEntityClass')->willReturn(TestEntity::class);
        $entityDiscovery->method('getAdminAttribute')->willReturn(null);
        $formRegistry->method('hasType')->willReturn(true);

        $this->assertTrue($runtime->isActionAccessible('TestEntity', 'new'));
    }

    #[Test]
    public function isActionAccessibleReturnsFalseForNewWithoutForm(): void
    {
        /** @var FormRegistryInterface&MockObject $formRegistry */
        $formRegistry    = $this->createMock(FormRegistryInterface::class);
        /** @var EntityDiscoveryService&MockObject $entityDiscovery */
        $entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $runtime         = $this->makeRuntime(formRegistry: $formRegistry, entityDiscovery: $entityDiscovery);

        $this->attributeHelper->method('getAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(true);
        $entityDiscovery->method('resolveEntityClass')->willReturn(TestEntity::class);
        $entityDiscovery->method('getAdminAttribute')->willReturn(null);
        $formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse($runtime->isActionAccessible('TestEntity', 'new'));
    }

    #[Test]
    public function isActionAccessibleReturnsTrueForEditWithFormAndPermission(): void
    {
        /** @var FormRegistryInterface&MockObject $formRegistry */
        $formRegistry    = $this->createMock(FormRegistryInterface::class);
        /** @var EntityDiscoveryService&MockObject $entityDiscovery */
        $entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $runtime         = $this->makeRuntime(formRegistry: $formRegistry, entityDiscovery: $entityDiscovery);

        $this->attributeHelper->method('getAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(true);
        $entityDiscovery->method('resolveEntityClass')->willReturn(TestEntity::class);
        $entityDiscovery->method('getAdminAttribute')->willReturn(null);
        $formRegistry->method('hasType')->willReturn(true);

        $this->assertTrue($runtime->isActionAccessible('TestEntity', 'edit'));
    }

    #[Test]
    public function isActionAccessibleReturnsFalseForEditWithoutForm(): void
    {
        /** @var FormRegistryInterface&MockObject $formRegistry */
        $formRegistry    = $this->createMock(FormRegistryInterface::class);
        /** @var EntityDiscoveryService&MockObject $entityDiscovery */
        $entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $runtime         = $this->makeRuntime(formRegistry: $formRegistry, entityDiscovery: $entityDiscovery);

        $this->attributeHelper->method('getAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(true);
        $entityDiscovery->method('resolveEntityClass')->willReturn(TestEntity::class);
        $entityDiscovery->method('getAdminAttribute')->willReturn(null);
        $formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse($runtime->isActionAccessible('TestEntity', 'edit'));
    }

    #[Test]
    public function isActionAccessibleReturnsTrueWithNoAuthChecker(): void
    {
        $runtime = $this->makeRuntime(authChecker: null);

        $this->attributeHelper->method('getAttribute')->willReturn(null);

        $this->assertTrue($runtime->isActionAccessible('Product', 'index'));
    }

    #[Test]
    public function isActionAccessibleReturnsTrueForNewEditWithNoFormDependencies(): void
    {
        // No formRegistry or entityDiscovery — should assume form exists
        $runtime = $this->makeRuntime(authChecker: null, formRegistry: null, entityDiscovery: null);

        $this->attributeHelper->method('getAttribute')->willReturn(null);

        $this->assertTrue($runtime->isActionAccessible('Product', 'new'));
    }

    #[Test]
    public function hasFormReturnsTrueWhenEntityDiscoveryThrowsException(): void
    {
        /** @var FormRegistryInterface&MockObject $formRegistry */
        $formRegistry    = $this->createMock(FormRegistryInterface::class);
        /** @var EntityDiscoveryService&MockObject $entityDiscovery */
        $entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $runtime         = $this->makeRuntime(authChecker: null, formRegistry: $formRegistry, entityDiscovery: $entityDiscovery);

        $this->attributeHelper->method('getAttribute')->willReturn(null);
        $entityDiscovery->method('resolveEntityClass')->willThrowException(new \Exception('Entity not found'));
        $formRegistry->method('hasType')->willReturn(true);

        $this->assertTrue($runtime->isActionAccessible('UnknownEntity', 'new'));
    }
}
