<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\RowAction\RowActionRegistry;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use Kachnitel\AdminBundle\Twig\Runtime\RowActionRuntime;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group archive
 * @covers \Kachnitel\AdminBundle\Twig\Runtime\RowActionRuntime
 */
class RowActionRuntimeArchiveTest extends TestCase
{
    /** @var RowActionRegistry&MockObject */
    private RowActionRegistry $registry;

    /** @var AdminRouteRuntime&MockObject */
    private AdminRouteRuntime $routeRuntime;

    private RowActionRuntime $runtime;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(RowActionRegistry::class);
        $this->routeRuntime = $this->createMock(AdminRouteRuntime::class);

        $this->runtime = new RowActionRuntime(
            registry: $this->registry,
            routeRuntime: $this->routeRuntime,
            expressionLanguage: new RowActionExpressionLanguage(),
        );
    }

    private function makeEntity(): object
    {
        return new \stdClass();
    }

    /** @test */
    public function archiveActionIsVisibleWhenRouteAccessible(): void
    {
        $this->routeRuntime
            ->method('isActionAccessible')
            ->with('Product', 'archive')
            ->willReturn(true);

        $action = new RowAction(
            name: 'archive',
            label: 'Archive',
            voterAttribute: AdminEntityVoter::ADMIN_ARCHIVE,
        );

        $this->assertTrue($this->runtime->isActionVisible($action, $this->makeEntity(), 'Product'));
    }

    /** @test */
    public function archiveActionIsHiddenWhenRouteNotAccessible(): void
    {
        $this->routeRuntime
            ->method('isActionAccessible')
            ->with('Product', 'archive')
            ->willReturn(false);

        $action = new RowAction(
            name: 'archive',
            label: 'Archive',
            voterAttribute: AdminEntityVoter::ADMIN_ARCHIVE,
        );

        $this->assertFalse($this->runtime->isActionVisible($action, $this->makeEntity(), 'Product'));
    }

    /** @test */
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

        $this->assertTrue($this->runtime->isActionVisible($action, $this->makeEntity(), 'Product'));
    }

    /** @test */
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

        $this->runtime->isActionVisible($action, $this->makeEntity(), 'Product');
    }
}
