<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use Kachnitel\AdminBundle\Twig\Runtime\RowActionVisibilityChecker;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

#[PHPUnit\CoversClass(RowActionVisibilityChecker::class)]
#[PHPUnit\UsesClass(RowAction::class)]
#[PHPUnit\UsesClass(RowActionExpressionLanguage::class)]
#[Group('row-actions')]
final class RowActionVisibilityCheckerArchiveTest extends TestCase
{
    /** @var AdminRouteRuntime&MockObject */
    private AdminRouteRuntime $routeRuntime;

    private RowActionVisibilityChecker $checker;

    protected function setUp(): void
    {
        $this->routeRuntime = $this->createMock(AdminRouteRuntime::class);

        $this->checker = new RowActionVisibilityChecker(
            routeRuntime: $this->routeRuntime,
            expressionLanguage: new RowActionExpressionLanguage(new ExpressionLanguage()),
        );
    }

    private function makeEntity(): object
    {
        return new \stdClass();
    }

    #[PHPUnit\Test]
    public function archiveActionIsVisibleWhenRouteAccessible(): void
    {
        $this->routeRuntime
            ->expects($this->once())->method('isActionAccessible')
            ->with('Product', 'archive')
            ->willReturn(true);

        $action = new RowAction(
            name: 'archive',
            label: 'Archive',
            voterAttribute: AdminEntityVoter::ADMIN_ARCHIVE,
        );

        $this->assertTrue($this->checker->isVisible($action, $this->makeEntity(), 'Product'));
    }

    #[PHPUnit\Test]
    public function archiveActionIsHiddenWhenRouteNotAccessible(): void
    {
        $this->routeRuntime
            ->expects($this->once())->method('isActionAccessible')
            ->with('Product', 'archive')
            ->willReturn(false);

        $action = new RowAction(
            name: 'archive',
            label: 'Archive',
            voterAttribute: AdminEntityVoter::ADMIN_ARCHIVE,
        );

        $this->assertFalse($this->checker->isVisible($action, $this->makeEntity(), 'Product'));
    }

    #[PHPUnit\Test]
    public function unarchiveActionRoutesUnderArchivePermission(): void
    {
        // Both archive and unarchive use ADMIN_ARCHIVE voter, which maps to 'archive' action name
        $this->routeRuntime
            ->expects($this->once())
            ->method('isActionAccessible')
            ->with('Product', 'archive')
            ->willReturn(true);

        $action = new RowAction(
            name: 'unarchive',
            label: 'Unarchive',
            voterAttribute: AdminEntityVoter::ADMIN_ARCHIVE,
        );

        $this->assertTrue($this->checker->isVisible($action, $this->makeEntity(), 'Product'));
    }

    #[PHPUnit\Test]
    public function adminArchiveVoterAttributeDoesNotFallThroughToStrtolower(): void
    {
        // If mapVoterAttributeToActionName falls through to strtolower, it would call
        // isActionAccessible with 'admin_archive' (which has no route) instead of 'archive'.
        // This test verifies the correct 'archive' name is used.
        $this->routeRuntime
            ->expects($this->once())
            ->method('isActionAccessible')
            ->with(
                $this->anything(),
                $this->logicalNot($this->equalTo('admin_archive')) // must NOT be 'admin_archive'
            )
            ->willReturn(false);

        $action = new RowAction(
            name: 'archive',
            label: 'Archive',
            voterAttribute: AdminEntityVoter::ADMIN_ARCHIVE,
        );

        $this->checker->isVisible($action, $this->makeEntity(), 'Product');
    }
}
