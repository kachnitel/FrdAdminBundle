<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\Twig\Components\AdminAction\BatchActionTrait;
use Kachnitel\AdminBundle\Twig\Components\ColumnFilter;
use Kachnitel\AdminBundle\Twig\Components\DateRangeFilter;
use Kachnitel\AdminBundle\Twig\Components\EmitsToListTrait;
use Kachnitel\AdminBundle\Twig\Components\EnumMultiFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\UX\LiveComponent\LiveResponder;

/**
 * Filters and batch actions inside a collection dialog sit inside the outer list's
 * element. UX's emitUp() reaches EVERY ancestor component, so without a component name
 * the outer list would also receive the dialog's filter / batch events. Each emitter
 * therefore targets the component name of the list that rendered it.
 *
 * @see https://symfony.com/bundles/ux-live-component/current/index.html#scoping-events
 */
#[CoversClass(ColumnFilter::class)]
#[CoversClass(DateRangeFilter::class)]
#[CoversClass(EnumMultiFilter::class)]
#[CoversTrait(EmitsToListTrait::class)]
#[CoversTrait(BatchActionTrait::class)]
#[Group('collection-dialog')]
#[Group('collection-scope')]
final class ListEventScopingTest extends TestCase
{
    private const EMBEDDED = 'K:Admin:EntityListEmbedded';

    #[Test]
    public function columnFilterTargetsItsListComponent(): void
    {
        $filter = new ColumnFilter();
        $filter->column = 'name';
        $filter->value = 'abc';
        $filter->listComponent = self::EMBEDDED;
        $filter->setLiveResponder($this->expectEmitUp('filter:updated', ['column' => 'name', 'value' => 'abc'], self::EMBEDDED));

        $filter->onUpdated();
    }

    #[Test]
    public function columnFilterWithoutListComponentKeepsLegacyBroadcast(): void
    {
        // Custom templates that don't pass listComponent behave exactly as before.
        $filter = new ColumnFilter();
        $filter->column = 'name';
        $filter->setLiveResponder($this->expectEmitUp('filter:updated', ['column' => 'name', 'value' => ''], null));

        $filter->onUpdated();
    }

    #[Test]
    public function dateRangeFilterTargetsItsListComponent(): void
    {
        $filter = new DateRangeFilter();
        $filter->column = 'createdAt';
        $filter->listComponent = self::EMBEDDED;
        $filter->setLiveResponder($this->expectEmitUp('filter:updated', ['column' => 'createdAt', 'value' => ''], self::EMBEDDED));

        $filter->clear();
    }

    #[Test]
    public function enumMultiFilterTargetsItsListComponent(): void
    {
        $filter = new EnumMultiFilter();
        $filter->column = 'status';
        $filter->listComponent = self::EMBEDDED;
        $filter->setLiveResponder($this->expectEmitUp('filter:updated', ['column' => 'status', 'value' => ''], self::EMBEDDED));

        $filter->clear();
    }

    #[Test]
    public function batchActionsTargetTheirListComponent(): void
    {
        $action = new class {
            use BatchActionTrait;

            public function run(): void
            {
                $this->completeAction('delete', [1, 2]);
            }
        };
        $action->listComponent = self::EMBEDDED;
        $action->setLiveResponder(
            $this->expectEmitUp('admin:action:completed', ['action' => 'delete', 'affectedIds' => [1, 2]], self::EMBEDDED),
        );

        $action->run();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function expectEmitUp(string $event, array $data, ?string $componentName): LiveResponder&MockObject
    {
        $responder = $this->createMock(LiveResponder::class);
        $responder->expects($this->once())->method('emitUp')->with($event, $data, $componentName);

        return $responder;
    }
}
