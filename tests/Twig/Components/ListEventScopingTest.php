<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\BatchAction\BatchActionComponentInterface;
use Kachnitel\AdminBundle\Twig\Components\AdminAction\BatchActionTrait;
use Kachnitel\AdminBundle\Twig\Components\ColumnFilter;
use Kachnitel\AdminBundle\Twig\Components\DateRangeFilter;
use Kachnitel\AdminBundle\Twig\Components\EmitsToListTrait;
use Kachnitel\AdminBundle\Twig\Components\EnumMultiFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
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
        $responder = new LiveResponder();
        $filter->setLiveResponder($responder);

        $filter->onUpdated();

        $this->assertEmitUp($responder, 'filter:updated', ['column' => 'name', 'value' => 'abc'], self::EMBEDDED);
    }

    #[Test]
    public function columnFilterWithoutListComponentKeepsLegacyBroadcast(): void
    {
        // Custom templates that don't pass listComponent behave exactly as before.
        $filter = new ColumnFilter();
        $filter->column = 'name';
        $responder = new LiveResponder();
        $filter->setLiveResponder($responder);

        $filter->onUpdated();

        $this->assertEmitUp($responder, 'filter:updated', ['column' => 'name', 'value' => ''], null);
    }

    #[Test]
    public function dateRangeFilterTargetsItsListComponent(): void
    {
        $filter = new DateRangeFilter();
        $filter->column = 'createdAt';
        $filter->listComponent = self::EMBEDDED;
        $responder = new LiveResponder();
        $filter->setLiveResponder($responder);

        $filter->clear();

        $this->assertEmitUp($responder, 'filter:updated', ['column' => 'createdAt', 'value' => ''], self::EMBEDDED);
    }

    #[Test]
    public function enumMultiFilterTargetsItsListComponent(): void
    {
        $filter = new EnumMultiFilter();
        $filter->column = 'status';
        $filter->listComponent = self::EMBEDDED;
        $responder = new LiveResponder();
        $filter->setLiveResponder($responder);

        $filter->clear();

        $this->assertEmitUp($responder, 'filter:updated', ['column' => 'status', 'value' => ''], self::EMBEDDED);
    }

    #[Test]
    public function batchActionsTargetTheirListComponent(): void
    {
        $action = new class implements BatchActionComponentInterface {
            use BatchActionTrait;

            public function run(): void
            {
                $this->completeAction('delete', [1, 2]);
            }
        };
        $action->listComponent = self::EMBEDDED;
        $responder = new LiveResponder();
        $action->setLiveResponder($responder);

        $action->run();

        $this->assertEmitUp($responder, 'admin:action:completed', ['action' => 'delete', 'affectedIds' => [1, 2]], self::EMBEDDED);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertEmitUp(LiveResponder $responder, string $event, array $data, ?string $componentName): void
    {
        $this->assertSame([[
            'event' => $event,
            'data' => $data,
            'target' => 'up',
            'componentName' => $componentName,
        ]], $responder->getEventsToEmit());
    }
}
