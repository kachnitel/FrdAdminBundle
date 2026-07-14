<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\Attribute\AdminRoutes;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Twig\Runtime\ActionAccessibilityChecker;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
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
#[CoversClass(AdminRouteRuntime::class)]
#[UsesClass(ActionAccessibilityChecker::class)]
#[UsesClass(AdminRoutes::class)]
#[Group('archive')]
#[AllowMockObjectsWithoutExpectations]
final class AdminRouteRuntimeArchiveTest extends TestCase
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
    }

    /**
     * @param AuthorizationCheckerInterface|null|false $authChecker
     *   false (default) = use $this->authChecker; null = no auth checker
     */
    private function makeRuntime(AuthorizationCheckerInterface|null|false $authChecker = false): AdminRouteRuntime
    {
        $auth = $authChecker === false ? $this->authChecker : $authChecker;

        $checker = new ActionAccessibilityChecker(
            $auth,
            null,
            null,
            'App\\Form\\',
            'FormType',
            'App\\Entity\\',
        );

        return new AdminRouteRuntime(
            $this->router,
            $this->attributeHelper,
            $checker,
            $auth,
        );
    }

    private function stubRouteCollection(string $routeName): void
    {
        $collection = new RouteCollection();
        $collection->add($routeName, $this->createStub(Route::class));
        $this->router->method('getRouteCollection')->willReturn($collection);
    }

    // ── hasRoute ─────────────────────────────────────────────────────────────

    #[Test]
    public function hasRouteReturnsTrueForArchiveAction(): void
    {
        $this->assertTrue($this->makeRuntime()->hasRoute('Product', 'archive'));
    }

    #[Test]
    public function hasRouteReturnsTrueForUnarchiveAction(): void
    {
        $this->assertTrue($this->makeRuntime()->hasRoute('Product', 'unarchive'));
    }

    #[Test]
    public function getRouteReturnsGenericArchiveRoute(): void
    {
        $this->assertSame('app_admin_entity_archive', $this->makeRuntime()->getRoute('Product', 'archive'));
    }

    #[Test]
    public function getRouteReturnsGenericUnarchiveRoute(): void
    {
        $this->assertSame('app_admin_entity_unarchive', $this->makeRuntime()->getRoute('Product', 'unarchive'));
    }

    #[Test]
    public function getRouteReturnsCustomArchiveRouteWhenConfigured(): void
    {
        $routes = new AdminRoutes(['archive' => 'app_product_archive']);

        $this->attributeHelper->method('getAttribute')->willReturn($routes);

        $this->assertSame('app_product_archive', $this->makeRuntime()->getRoute('Product', 'archive'));
    }

    // ── isActionAccessible for archive ────────────────────────────────────────

    #[Test]
    public function isActionAccessibleReturnsTrueForArchiveWhenRouteExistsAndGranted(): void
    {
        $this->stubRouteCollection('app_admin_entity_archive');

        $this->authChecker
            ->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_ARCHIVE, 'Product')
            ->willReturn(true);

        $this->assertTrue($this->makeRuntime()->isActionAccessible('Product', 'archive'));
    }

    #[Test]
    public function isActionAccessibleReturnsFalseForArchiveWhenDenied(): void
    {
        $this->stubRouteCollection('app_admin_entity_archive');

        $this->authChecker
            ->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_ARCHIVE, 'Product')
            ->willReturn(false);

        $this->assertFalse($this->makeRuntime()->isActionAccessible('Product', 'archive'));
    }

    #[Test]
    public function isActionAccessibleReturnsTrueForUnarchiveWhenRouteExistsAndGranted(): void
    {
        $this->stubRouteCollection('app_admin_entity_unarchive');

        $this->authChecker
            ->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_ARCHIVE, 'Product')
            ->willReturn(true);

        $this->assertTrue($this->makeRuntime()->isActionAccessible('Product', 'unarchive'));
    }

    #[Test]
    public function isActionAccessibleReturnsFalseForUnarchiveWhenVoterDenies(): void
    {
        $this->stubRouteCollection('app_admin_entity_unarchive');

        $this->authChecker
            ->expects($this->once())->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_ARCHIVE, 'Product')
            ->willReturn(false);

        $this->assertFalse($this->makeRuntime()->isActionAccessible('Product', 'unarchive'));
    }

    #[Test]
    public function voterIsCalledWithAdminArchiveNotLowercasedString(): void
    {
        $this->stubRouteCollection('app_admin_entity_archive');

        $this->authChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with(
                $this->logicalNot($this->equalTo('admin_archive')),
                $this->anything(),
            )
            ->willReturn(true);

        $this->makeRuntime()->isActionAccessible('Product', 'archive');
    }

    #[Test]
    public function isActionAccessibleReturnsTrueForArchiveWithNoAuthChecker(): void
    {
        $this->stubRouteCollection('app_admin_entity_archive');

        $this->assertTrue($this->makeRuntime(null)->isActionAccessible('Product', 'archive'));
    }

    #[Test]
    public function isActionAccessibleReturnsFalseWhenArchiveRouteDoesNotExist(): void
    {
        $this->router->method('getRouteCollection')->willReturn(new RouteCollection());

        $this->assertFalse($this->makeRuntime()->isActionAccessible('Product', 'archive'));
    }
}
