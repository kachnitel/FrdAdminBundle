<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\PermissionFilterOwner;
use Kachnitel\AdminBundle\Tests\Fixtures\PermissionFilterTicket;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * A relation filter searches fields of the TARGET entity, so the target's own
 * #[ColumnPermission] must apply: filtering tickets by an owner's restricted email must not
 * reveal whether that email exists.
 */
#[Group('column-permission-query')]
#[Group('entity-list')]
final class ColumnPermissionAssociationFilterTest extends ComponentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get('request_stack')->push($request);

        $alice = (new PermissionFilterOwner())->setName('Alice')->setEmail('alice@example.test');
        $bob = (new PermissionFilterOwner())->setName('Bob')->setEmail('bob@example.test');
        $this->em->persist($alice);
        $this->em->persist($bob);
        $this->em->persist((new PermissionFilterTicket())->setTitle('T1')->setOwner($alice));
        $this->em->persist((new PermissionFilterTicket())->setTitle('T2')->setOwner($bob));
        $this->em->flush();
    }

    #[Test]
    public function restrictedTargetFieldIsNotSearchedWithoutTheRole(): void
    {
        $titles = $this->titles('alice@example.test', new InMemoryUser('user', 'test', ['ROLE_USER']));

        // Only the permitted `name` is searched, so an existing email behaves exactly like an unknown one.
        $this->assertSame([], $titles);
    }

    #[Test]
    public function restrictedTargetFieldIsSearchedWithTheRole(): void
    {
        $titles = $this->titles('alice@example.test', new InMemoryUser('hr', 'test', ['ROLE_HR']));

        $this->assertSame(['T1'], $titles);
    }

    #[Test]
    public function permittedTargetFieldStillFiltersWithoutTheRole(): void
    {
        $titles = $this->titles('Bob', new InMemoryUser('user', 'test', ['ROLE_USER']));

        $this->assertSame(['T2'], $titles);
    }

    /** @return list<string> */
    private function titles(string $ownerFilter, InMemoryUser $user): array
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => PermissionFilterTicket::class,
                'entityShortClass' => 'PermissionFilterTicket',
                'columnFilters' => ['owner' => $ownerFilter],
                'sortDirection' => 'ASC',
            ],
        )->actingAs($user);

        $list = $component->component();
        $this->assertInstanceOf(EntityList::class, $list);

        return array_values(array_map(
            static fn (object $entity): string => $entity instanceof PermissionFilterTicket ? $entity->getTitle() : '',
            $list->getEntities(),
        ));
    }
}
