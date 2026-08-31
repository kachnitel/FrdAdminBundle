<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\RowAction\RowActionRegistry;
use Kachnitel\AdminBundle\Twig\Runtime\RowActionRuntime;
use Kachnitel\AdminBundle\Twig\Runtime\RowActionVisibilityChecker;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers RowActionRuntime after the visibility-decision logic was extracted
 * to RowActionVisibilityChecker: registry access (getRowActions()), and
 * that getVisibleRowActions()/isActionVisible() correctly delegate to the
 * checker rather than re-implementing any of its own decision logic.
 *
 * The actual visibility decisions (conditions, voter/permission checks,
 * object-level authorization, DI-tuple failure handling) are covered by
 * RowActionVisibilityCheckerTest, which this file deliberately does not
 * duplicate.
 */
#[PHPUnit\CoversClass(RowActionRuntime::class)]
#[PHPUnit\UsesClass(RowAction::class)]
#[PHPUnit\Group('row-actions')]
#[PHPUnit\AllowMockObjectsWithoutExpectations]
final class RowActionRuntimeTest extends TestCase
{
    /** @var RowActionRegistry&MockObject */
    private RowActionRegistry $registry;

    /** @var RowActionVisibilityChecker&MockObject */
    private RowActionVisibilityChecker $visibilityChecker;

    private RowActionRuntime $runtime;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(RowActionRegistry::class);
        $this->visibilityChecker = $this->createMock(RowActionVisibilityChecker::class);
        $this->runtime = new RowActionRuntime($this->registry, $this->visibilityChecker);
    }

    #[PHPUnit\Test]
    public function getRowActionsReturnsAllUnfiltered(): void
    {
        $actions = [
            new RowAction(name: 'show', label: 'Show'),
            new RowAction(name: 'edit', label: 'Edit'),
        ];
        $this->registry->method('getActions')->willReturn($actions);

        $this->assertSame($actions, $this->runtime->getRowActions('App\\Entity\\Product'));
    }

    #[PHPUnit\Test]
    public function isActionVisibleDelegatesToVisibilityChecker(): void
    {
        $entity = new \stdClass();
        $action = new RowAction(name: 'edit', label: 'Edit');

        $this->visibilityChecker->expects($this->once())
            ->method('isVisible')
            ->with($action, $entity, 'Product')
            ->willReturn(true);

        $this->assertTrue($this->runtime->isActionVisible($action, $entity, 'Product'));
    }

    #[PHPUnit\Test]
    public function getVisibleRowActionsFiltersUsingVisibilityChecker(): void
    {
        $entity = new \stdClass();
        $showAction = new RowAction(name: 'show', label: 'Show');
        $archiveAction = new RowAction(name: 'archive', label: 'Archive');

        $this->registry->method('getActions')->willReturn([$showAction, $archiveAction]);

        $this->visibilityChecker->method('isVisible')->willReturnMap([
            [$showAction, $entity, 'Product', true],
            [$archiveAction, $entity, 'Product', false],
        ]);

        $visible = $this->runtime->getVisibleRowActions('App\\Entity\\Product', $entity, 'Product');

        $this->assertCount(1, $visible);
        $this->assertSame('show', $visible[0]->name);
    }

    #[PHPUnit\Test]
    public function getVisibleRowActionsPassesContextThroughToRegistry(): void
    {
        $entity = new \stdClass();

        $this->registry->expects($this->once())
            ->method('getActions')
            ->with('App\\Entity\\Product', 'index')
            ->willReturn([]);

        $this->runtime->getVisibleRowActions('App\\Entity\\Product', $entity, 'Product', 'index');
    }
}
