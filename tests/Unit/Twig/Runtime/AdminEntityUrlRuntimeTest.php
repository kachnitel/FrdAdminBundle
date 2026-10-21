<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityUrlRuntime;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use Kachnitel\AdminBundle\Utils\CollectionScopeParameter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

#[AllowMockObjectsWithoutExpectations]
class AdminEntityUrlRuntimeTest extends TestCase
{
    private RouterInterface&MockObject $router;
    private AdminRouteRuntime&MockObject $adminRouteRuntime;
    private EntityDiscoveryService&MockObject $entityDiscovery;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->adminRouteRuntime = $this->createMock(AdminRouteRuntime::class);
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    private function createRuntime(
        ?EntityDiscoveryService $entityDiscovery = null,
        ?EntityManagerInterface $em = null,
    ): AdminEntityUrlRuntime {
        return new AdminEntityUrlRuntime(
            router: $this->router,
            adminRouteRuntime: $this->adminRouteRuntime,
            entityDiscovery: $entityDiscovery,
            em: $em,
        );
    }


    #[Test]
    public function getEntityAdminUrlReturnsNullWithoutDependencies(): void
    {
        $runtime = $this->createRuntime();

        $result = $runtime->getEntityAdminUrl(new TestEntity());
        $this->assertNull($result);
    }

    #[Test]
    public function getEntityAdminUrlReturnsNullWhenEntityHasNoAdmin(): void
    {
        $this->entityDiscovery->method('isAdminEntity')->willReturn(false);

        $runtime = $this->createRuntime($this->entityDiscovery);

        $result = $runtime->getEntityAdminUrl(new TagEntity());
        $this->assertNull($result);
    }

    #[Test]
    public function getEntityAdminUrlReturnsShowUrlForAdminEntity(): void
    {
        $this->entityDiscovery->method('isAdminEntity')->willReturn(true);
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_show');

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_show',
                $this->callback(fn (array $params) =>
                    isset($params['entitySlug'], $params['id']) && $params['id'] === 42
                )
            )
            ->willReturn('/admin/test-entity/42');

        $runtime = $this->createRuntime($this->entityDiscovery);

        $entity = new class { public function getId(): int { return 42; } };

        $result = $runtime->getEntityAdminUrl($entity);
        $this->assertNotNull($result);
        $this->assertStringContainsString('42', $result);
    }

    #[Test]
    public function getEntityAdminUrlFallsBackToIndexWhenNoId(): void
    {
        $this->entityDiscovery->method('isAdminEntity')->willReturn(true);
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                $this->callback(fn (array $params) =>
                    isset($params['entitySlug']) && !isset($params['columnFilters'])
                )
            )
            ->willReturn('/admin/test-entity');

        $runtime = $this->createRuntime($this->entityDiscovery);

        $entity = new class { public function getName(): string { return 'Test'; } };

        $result = $runtime->getEntityAdminUrl($entity);
        $this->assertNotNull($result);
    }

    #[Test]
    public function getEntityAdminUrlFallsBackToIndexWithIdFilter(): void
    {
        $this->entityDiscovery->method('isAdminEntity')->willReturn(true);
        $this->adminRouteRuntime->method('isActionAccessible')
            ->willReturnCallback(fn (string $shortName, string $action): bool => $action === 'index');
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                $this->callback(fn (array $params): bool => $this->hasColumnFilter($params, 'id', '7'))
            )
            ->willReturn('/admin/test-entity?columnFilters%5Bid%5D=7');

        $runtime = $this->createRuntime($this->entityDiscovery);

        $entity = new class { public function getId(): int { return 7; } };

        $result = $runtime->getEntityAdminUrl($entity);
        $this->assertNotNull($result);
        $this->assertStringContainsString('columnFilters', $result);
    }

    #[Test]
    #[Group('collection-scope')]
    public function getCollectionAdminUrlReturnsNullWithoutDependencies(): void
    {
        $runtime = $this->createRuntime($this->entityDiscovery);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'tags');
        $this->assertNull($result);
    }

    #[Test]
    #[Group('collection-scope')]
    public function getCollectionAdminUrlReturnsNullForNonCollectionProperty(): void
    {
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('name')->willReturn(false);

        $this->em->method('getClassMetadata')->willReturn($metadata);

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'name');
        $this->assertNull($result);
    }

    #[Test]
    #[Group('collection-scope')]
    public function getCollectionAdminUrlReturnsNullWhenTargetHasNoAdmin(): void
    {
        $this->stubCollectionMetadata(TagEntity::class);
        $this->entityDiscovery->expects($this->once())->method('isAdminEntity')->with(TagEntity::class)->willReturn(false);

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'tags');
        $this->assertNull($result);
    }

    #[Test]
    #[Group('collection-scope')]
    public function getCollectionAdminUrlReturnsNullWhenOwnerHasNoAdmin(): void
    {
        // The scope resolver 404s a non-admin owner, so a link to it would be dead.
        $this->stubCollectionMetadata(TagEntity::class);
        $this->entityDiscovery->method('isAdminEntity')
            ->willReturnCallback(static fn (string $class): bool => $class === TagEntity::class);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $this->assertNull($runtime->getCollectionAdminUrl($this->ownerWithId(1), 'tagEntities'));
    }

    #[Test]
    #[Group('collection-scope')]
    public function getCollectionAdminUrlReturnsNullWhenOwnerHasNoId(): void
    {
        $this->stubCollectionMetadata(TagEntity::class);
        $this->entityDiscovery->method('isAdminEntity')->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);
        $this->router->expects($this->never())->method('generate');

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $this->assertNull($runtime->getCollectionAdminUrl(new TestEntity(), 'tagEntities'));
    }

    #[Test]
    #[Group('collection-scope')]
    public function getCollectionAdminUrlReturnsNullWhenIndexNotAccessible(): void
    {
        $this->stubCollectionMetadata(TagEntity::class);
        $this->entityDiscovery->method('isAdminEntity')->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->expects($this->once())->method('isActionAccessible')->with('TagEntity', 'index')->willReturn(false);

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $this->assertNull($runtime->getCollectionAdminUrl($this->ownerWithId(1), 'tagEntities'));
    }

    /**
     * The link carries the same scope the dialog uses, so both paths list identical rows.
     * The association mapping (mappedBy/inversedBy) is deliberately not consulted — that
     * dependency is what used to break unidirectional ManyToMany.
     */
    #[Test]
    #[Group('collection-scope')]
    public function getCollectionAdminUrlCarriesScopeInsteadOfColumnFilter(): void
    {
        $this->stubCollectionMetadata(TagEntity::class);
        $this->entityDiscovery->method('isAdminEntity')->willReturn(true);
        $this->adminRouteRuntime->expects($this->once())->method('getRoute')->with(TagEntity::class, 'index')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                [
                    'entitySlug' => 'tag-entity',
                    CollectionScopeParameter::QUERY_KEY => 'TestEntity:1:tagEntities',
                ],
            )
            ->willReturn('/admin/tag-entity?collection=x');

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $this->assertSame('/admin/tag-entity?collection=x', $runtime->getCollectionAdminUrl($this->ownerWithId(1), 'tagEntities'));
    }

    /**
     * @param class-string $targetClass
     */
    private function stubCollectionMetadata(string $targetClass): void
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('isCollectionValuedAssociation')->willReturn(true);
        $metadata->method('getAssociationTargetClass')->willReturn($targetClass);

        $this->em->method('getClassMetadata')->willReturn($metadata);
    }

    /** @param array<int|string, mixed> $params */
    private function hasColumnFilter(array $params, string $field, string $expected): bool
    {
        $filters = $params['columnFilters'] ?? null;

        return is_array($filters) && ($filters[$field] ?? null) === $expected;
    }

    private function ownerWithId(int $id): object
    {
        return (new TestEntity())->setId($id);
    }
}
