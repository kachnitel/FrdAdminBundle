<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\PermissionTestEntity;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * #[ColumnPermission] ADMIN_SHOW must hold for the list QUERY, not only for what is rendered.
 *
 * `search`, `sortBy` and `columnFilters` are writable, URL-bound LiveProps: if they reached
 * a column the user may not see, its values could be inferred (sort order, filter bisection,
 * search hits) without ever being displayed.
 *
 * No user is authenticated, so PermissionTestEntity::$salary (ROLE_HR) and ::$internalNotes
 * (ROLE_MANAGER) are denied. Each "denied" assertion has a permitted-column control.
 */
#[Group('column-permission-query')]
#[Group('entity-list')]
final class ColumnPermissionListQueryTest extends ComponentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        self::getContainer()->get('request_stack')->push($request);

        // ids ascend Alice < Bob < Carol; salary order is Alice < Carol < Bob
        foreach ([
            ['Alice', '50000.00', 'alpha-secret'],
            ['Bob', '90000.00', 'beta-secret'],
            ['Carol', '70000.00', null],
        ] as [$name, $salary, $notes]) {
            $this->em->persist(
                (new PermissionTestEntity())->setName($name)->setSalary($salary)->setInternalNotes($notes),
            );
        }
        $this->em->flush();
    }

    #[Test]
    public function searchDoesNotMatchDeniedColumns(): void
    {
        $this->assertSame([], $this->names($this->list(['search' => 'alpha-secret'])));
    }

    #[Test]
    public function searchStillMatchesPermittedColumns(): void
    {
        $this->assertSame(['Alice'], $this->names($this->list(['search' => 'Alice'])));
    }

    #[Test]
    public function columnFilterOnDeniedColumnIsIgnored(): void
    {
        $this->assertCount(3, $this->names($this->list(['columnFilters' => ['internalNotes' => 'alpha']])));
        $this->assertCount(3, $this->names($this->list(['columnFilters' => ['salary' => '50000.00']])));
    }

    #[Test]
    public function columnFilterOnPermittedColumnStillApplies(): void
    {
        $this->assertSame(['Alice'], $this->names($this->list(['columnFilters' => ['name' => 'Alice']])));
    }

    #[Test]
    public function sortByDeniedColumnFallsBackToDefaultOrder(): void
    {
        // Sorting by salary ASC would yield Alice, Carol, Bob; the default order is id DESC.
        $list = $this->list(['sortBy' => 'salary', 'sortDirection' => 'ASC']);

        $this->assertSame(['Carol', 'Bob', 'Alice'], $this->names($list));
        $this->assertNotSame('salary', $list->sortBy);
    }

    #[Test]
    public function sortByPermittedColumnStillApplies(): void
    {
        $list = $this->list(['sortBy' => 'name', 'sortDirection' => 'ASC']);

        $this->assertSame(['Alice', 'Bob', 'Carol'], $this->names($list));
    }

    #[Test]
    public function searchHelpLabelsOmitDeniedColumns(): void
    {
        $labels = $this->list([])->getGlobalSearchColumnLabels();

        $this->assertContains('Name', $labels, 'control: permitted searchable column is listed');
        foreach ($labels as $label) {
            $this->assertStringNotContainsStringIgnoringCase('notes', $label);
        }
    }

    /**
     * @param array<string, mixed> $props writable LiveProps (search, sortBy, columnFilters, …)
     */
    private function list(array $props): EntityList
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => PermissionTestEntity::class,
                'entityShortClass' => 'PermissionTestEntity',
            ] + $props,
        );

        // Capture the component ONCE: component() may return a fresh hydrated instance.
        $list = $component->component();
        $this->assertInstanceOf(EntityList::class, $list);

        return $list;
    }

    /** @return list<string> */
    private function names(EntityList $list): array
    {
        return array_values(array_map(
            static fn (object $entity): string => $entity instanceof PermissionTestEntity ? $entity->getName() : '',
            $list->getEntities(),
        ));
    }
}
