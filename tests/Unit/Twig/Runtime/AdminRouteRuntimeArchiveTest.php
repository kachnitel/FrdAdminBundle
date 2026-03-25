<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\Attribute\AdminRoutes;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Covers archive/unarchive action routing in AdminRouteRuntimeTest.
 *
 *   - isActionAccessible('Product', 'archive')   → maps to ADMIN_ARCHIVE voter
 *   - isActionAccessible('Product', 'unarchive') → maps to ADMIN_ARCHIVE voter
 *   - Both actions have generic routes defined in getGenericAdminRoute()
 *   - The voter attribute constant ADMIN_ARCHIVE must be used (not 'admin_archive')
 *
 * @covers \Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime::isActionAccessible
 * @covers \Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime::getGenericAdminRoute
 * @group archive
 */
class AdminRouteRuntimeArchiveTest extends TestCase
{
    /** @var RouterInterface&MockObject */
    private RouterInterface $router;

    /** @var AttributeHelper&MockObject */
    private AttributeHelper $attributeHelper;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    protected function setUp(): void
    {
        $this->router          = $this->createMock(RouterInterface::class);
        $this->attributeHelper = $this->createMock(AttributeHelper::class);
        $this->authChecker     = $this->createMock(AuthorizationCheckerInterface::class);
        // getAttribute() returns null by default for unstubbed mock methods — no explicit
        // stub needed here. Adding willReturn(null) in setUp() would prevent individual tests
        // from overriding it, because PHPUnit resolves the FIRST registered stub when there
        // is no argument matcher.
    }

    private function makeRuntime(): AdminRouteRuntime
    {
        return new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            $this->authChecker,
        );
    }

    private function stubRouteCollection(string ...$routeNames): void
    {
        $collection = new RouteCollection();
        foreach ($routeNames as $name) {
            $collection->add($name, $this->createMock(Route::class));
        }
        $this->router->method('getRouteCollection')->willReturn($collection);
    }

    // ── hasRoute / getRoute for archive + unarchive ───────────────────────────

    /** @test */
    public function hasRouteReturnsTrueForArchiveAction(): void
    {
        $runtime = $this->makeRuntime();

        $this->assertTrue($runtime->hasRoute('Product', 'archive'));
    }

    /** @test */
    public function hasRouteReturnsTrueForUnarchiveAction(): void
    {
        $runtime = $this->makeRuntime();

        $this->assertTrue($runtime->hasRoute('Product', 'unarchive'));
    }

    /** @test */
    public function getRouteReturnsGenericArchiveRoute(): void
    {
        $runtime = $this->makeRuntime();

        $this->assertSame('app_admin_entity_archive', $runtime->getRoute('Product', 'archive'));
    }

    /** @test */
    public function getRouteReturnsGenericUnarchiveRoute(): void
    {
        $runtime = $this->makeRuntime();

        $this->assertSame('app_admin_entity_unarchive', $runtime->getRoute('Product', 'unarchive'));
    }

    /** @test */
    public function getRoutePrefersCustomArchiveRouteFromAttributeOverGeneric(): void
    {
        $routes = new AdminRoutes(['archive' => 'app_product_archive']);

        $this->attributeHelper->method('getAttribute')->willReturn($routes);

        $runtime = $this->makeRuntime();

        $this->assertSame('app_product_archive', $runtime->getRoute('Product', 'archive'));
    }

    // ── isActionAccessible for archive ────────────────────────────────────────

    /** @test */
    public function isActionAccessibleReturnsTrueForArchiveWhenVoterGranted(): void
    {
        $this->stubRouteCollection('app_admin_entity_archive');

        $this->authChecker
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_ARCHIVE, 'Product')
            ->willReturn(true);

        $this->assertTrue($this->makeRuntime()->isActionAccessible('Product', 'archive'));
    }

    /** @test */
    public function isActionAccessibleReturnsFalseForArchiveWhenVoterDenies(): void
    {
        $this->stubRouteCollection('app_admin_entity_archive');

        $this->authChecker
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_ARCHIVE, 'Product')
            ->willReturn(false);

        $this->assertFalse($this->makeRuntime()->isActionAccessible('Product', 'archive'));
    }

    /** @test */
    public function isActionAccessibleReturnsTrueForUnarchiveWhenVoterGranted(): void
    {
        $this->stubRouteCollection('app_admin_entity_unarchive');

        $this->authChecker
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_ARCHIVE, 'Product') // same voter attribute as archive
            ->willReturn(true);

        $this->assertTrue($this->makeRuntime()->isActionAccessible('Product', 'unarchive'));
    }

    /** @test */
    public function isActionAccessibleReturnsFalseForUnarchiveWhenVoterDenies(): void
    {
        $this->stubRouteCollection('app_admin_entity_unarchive');

        $this->authChecker
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_ARCHIVE, 'Product')
            ->willReturn(false);

        $this->assertFalse($this->makeRuntime()->isActionAccessible('Product', 'unarchive'));
    }

    /** @test */
    public function voterIsCalledWithAdminArchiveNotLowercasedString(): void
    {
        // Guard: must NOT call isGranted('admin_archive', ...) — that's the fallback strtolower
        // path, which would be wrong. Must use the ADMIN_ARCHIVE constant value.
        $this->stubRouteCollection('app_admin_entity_archive');

        $this->authChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with(
                $this->logicalNot($this->equalTo('admin_archive')), // must NOT be lowercase
                $this->anything(),
            )
            ->willReturn(true);

        $this->makeRuntime()->isActionAccessible('Product', 'archive');
    }

    /** @test */
    public function isActionAccessibleReturnsTrueForArchiveWithNoAuthChecker(): void
    {
        $runtime = new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            null, // no auth checker
        );

        $this->stubRouteCollection('app_admin_entity_archive');

        $this->assertTrue($runtime->isActionAccessible('Product', 'archive'));
    }

    /** @test */
    public function isActionAccessibleReturnsFalseWhenArchiveRouteDoesNotExist(): void
    {
        // Route collection is empty — no archive route registered
        $this->router->method('getRouteCollection')->willReturn(new RouteCollection());

        $this->assertFalse($this->makeRuntime()->isActionAccessible('Product', 'archive'));
    }
}
