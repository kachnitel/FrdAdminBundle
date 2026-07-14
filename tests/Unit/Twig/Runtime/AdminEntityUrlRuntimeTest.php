<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\PropertyFilterabilityService;
use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityUrlRuntime;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
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
        PropertyFilterabilityService $filterabilityService,
        ?EntityDiscoveryService $entityDiscovery = null,
        ?EntityManagerInterface $em = null,
    ): AdminEntityUrlRuntime {
        return new AdminEntityUrlRuntime(
            router: $this->router,
            adminRouteRuntime: $this->adminRouteRuntime,
            entityDiscovery: $entityDiscovery,
            filterabilityService: $filterabilityService,
            em: $em,
        );
    }

    /**
     * Returns a PropertyFilterabilityService mock that approves any field as filterable.
     * Use for tests where the field filterability is not what's under test.
     *
     * @return PropertyFilterabilityService&MockObject
     */
    private function filterableProvider(): PropertyFilterabilityService
    {
        $provider = $this->createMock(PropertyFilterabilityService::class);
        $provider->method('buildCollectionFilterEntry')
            ->willReturnCallback(function (object $entity, string $field, string $class): ?array {
                if (method_exists($entity, 'getId') && $entity->getId() !== null) {
                    return [$field => (string) $entity->getId()];
                }
                return null;
            });
        return $provider;
    }

    /**
     * Returns a PropertyFilterabilityService mock that rejects all fields as not filterable.
     *
     * @return PropertyFilterabilityService&MockObject
     */
    private function unfilteredProvider(): PropertyFilterabilityService
    {
        $provider = $this->createMock(PropertyFilterabilityService::class);
        $provider->method('buildCollectionFilterEntry')
            ->willReturnCallback(function (object $entity, string $field, string $class): never {
                throw new \LogicException(sprintf(
                    'A collection link targets "%s::$%s", but that property has #[ColumnFilter(enabled: false)].',
                    $class,
                    $field,
                ));
            });
        return $provider;
    }

    #[Test]
    public function getEntityAdminUrlReturnsNullWithoutDependencies(): void
    {
        $runtime = $this->createRuntime($this->filterableProvider());

        $result = $runtime->getEntityAdminUrl(new TestEntity());
        $this->assertNull($result);
    }

    #[Test]
    public function getEntityAdminUrlReturnsNullWhenEntityHasNoAdmin(): void
    {
        $this->entityDiscovery->method('isAdminEntity')->willReturn(false);

        $runtime = $this->createRuntime($this->filterableProvider(), $this->entityDiscovery);

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

        $runtime = $this->createRuntime($this->filterableProvider(), $this->entityDiscovery);

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

        $runtime = $this->createRuntime($this->filterableProvider(), $this->entityDiscovery);

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
                $this->callback(fn (array $params) =>
                    isset($params['entitySlug'], $params['columnFilters']['id'])
                    && $params['columnFilters']['id'] === '7'
                )
            )
            ->willReturn('/admin/test-entity?columnFilters%5Bid%5D=7');

        $runtime = $this->createRuntime($this->filterableProvider(), $this->entityDiscovery);

        $entity = new class { public function getId(): int { return 7; } };

        $result = $runtime->getEntityAdminUrl($entity);
        $this->assertNotNull($result);
        $this->assertStringContainsString('columnFilters', $result);
    }

    #[Test]
    public function getCollectionAdminUrlReturnsNullWithoutDependencies(): void
    {
        $runtime = $this->createRuntime($this->filterableProvider(), $this->entityDiscovery);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'tags');
        $this->assertNull($result);
    }

    #[Test]
    public function getCollectionAdminUrlReturnsNullForNonCollectionProperty(): void
    {
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('name')->willReturn(false);

        $this->em->method('getClassMetadata')->willReturn($metadata);

        $runtime = $this->createRuntime($this->filterableProvider(), $this->entityDiscovery, $this->em);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'name');
        $this->assertNull($result);
    }

    #[Test]
    public function getCollectionAdminUrlReturnsNullWhenTargetHasNoAdmin(): void
    {
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);
        $metadata->expects($this->once())->method('getAssociationTargetClass')->with('tags')->willReturn(TagEntity::class);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->expects($this->once())->method('isAdminEntity')->with(TagEntity::class)->willReturn(false);

        $runtime = $this->createRuntime($this->filterableProvider(), $this->entityDiscovery, $this->em);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'tags');
        $this->assertNull($result);
    }

    #[Test]
    public function getCollectionAdminUrlReturnsUrlWithFilterForOneToMany(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(TestEntity::class);
        $metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('tagEntities')->willReturn(true);
        $metadata->expects($this->once())->method('getAssociationTargetClass')->with('tagEntities')->willReturn(TagEntity::class);

        $oneToManyMapping = new OneToManyAssociationMapping('tagEntities', TestEntity::class, TagEntity::class);
        $oneToManyMapping->mappedBy = 'testEntity';
        $metadata->expects($this->once())->method('getAssociationMapping')->with('tagEntities')->willReturn($oneToManyMapping);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->expects($this->once())->method('isAdminEntity')->with(TagEntity::class)->willReturn(true);
        $this->adminRouteRuntime->expects($this->once())->method('getRoute')->with(TagEntity::class, 'index')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->expects($this->once())->method('isActionAccessible')->with('TagEntity', 'index')->willReturn(true);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                $this->callback(fn (array $params) =>
                    isset($params['columnFilters']['testEntity'])
                    && $params['columnFilters']['testEntity'] === '1'
                )
            )
            ->willReturn('/admin/tag-entity?columnFilters[testEntity]=1');

        $runtime = $this->createRuntime(
            $this->filterableProvider(),
            $this->entityDiscovery,
            $this->em,
        );

        $entity = new class { public function getId(): int { return 1; } };
        $result = $runtime->getCollectionAdminUrl($entity, 'tagEntities');
        $this->assertIsString($result);
    }

    #[Test]
    public function getCollectionAdminUrlReturnsUrlWithFilterForManyToManyOwningSideWithInversedBy(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(TestEntity::class);
        $metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('tagEntities')->willReturn(true);
        $metadata->expects($this->once())->method('getAssociationTargetClass')->with('tagEntities')->willReturn(TagEntity::class);

        $manyToManyMapping = new ManyToManyOwningSideMapping('tagEntities', TestEntity::class, TagEntity::class);
        $manyToManyMapping->inversedBy = 'testEntities';
        $metadata->expects($this->once())->method('getAssociationMapping')->with('tagEntities')->willReturn($manyToManyMapping);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->expects($this->once())->method('isAdminEntity')->with(TagEntity::class)->willReturn(true);
        $this->adminRouteRuntime->expects($this->once())->method('getRoute')->with(TagEntity::class, 'index')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->expects($this->once())->method('isActionAccessible')->with('TagEntity', 'index')->willReturn(true);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                $this->callback(fn (array $params) =>
                    isset($params['columnFilters']['testEntities'])
                    && $params['columnFilters']['testEntities'] === '1'
                )
            )
            ->willReturn('/admin/tag-entity?columnFilters[testEntities]=1');

        $runtime = $this->createRuntime(
            $this->filterableProvider(),
            $this->entityDiscovery,
            $this->em,
        );

        $entity = new class { public function getId(): int { return 1; } };
        $result = $runtime->getCollectionAdminUrl($entity, 'tagEntities');
        $this->assertIsString($result);
    }

    #[Test]
    public function getCollectionAdminUrlReturnsUrlWithoutFilterForManyToManyWithNoInversedBy(): void
    {
        // Unidirectional ManyToMany — no mappedBy, no inversedBy → no filter possible
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $metadata->expects($this->once())->method('getAssociationTargetClass')->with('items')->willReturn(TagEntity::class);
        $manyToManyMapping = new ManyToManyOwningSideMapping('items', TestEntity::class, TagEntity::class);
        // inversedBy intentionally not set
        $metadata->expects($this->once())->method('getAssociationMapping')->with('items')->willReturn($manyToManyMapping);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->expects($this->once())->method('isAdminEntity')->with(TagEntity::class)->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                $this->callback(fn (array $params) =>
                    $params['entitySlug'] === 'tag-entity'
                    && !isset($params['columnFilters'])
                )
            )
            ->willReturn('/admin/tag-entity');

        $runtime = $this->createRuntime($this->filterableProvider(), $this->entityDiscovery, $this->em);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'items');
        $this->assertNotNull($result);
    }

    #[Test]
    public function getCollectionAdminUrlThrowsInDebugModeWhenTargetFilterFieldExplicitlyDisabled(): void
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(SourceEntityStub::class);
        $metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $metadata->expects($this->once())->method('getAssociationTargetClass')->with('items')->willReturn(TargetWithDisabledFilterStub::class);
        $oneToManyMapping = new OneToManyAssociationMapping('items', SourceEntityStub::class, TargetWithDisabledFilterStub::class);
        $oneToManyMapping->mappedBy = 'owner'; // has #[ColumnFilter(enabled: false)]
        $metadata->expects($this->once())->method('getAssociationMapping')->with('items')->willReturn($oneToManyMapping);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->expects($this->once())->method('isAdminEntity')->with(TargetWithDisabledFilterStub::class)->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);

        $runtime = $this->createRuntime(
            $this->unfilteredProvider(),
            $this->entityDiscovery,
            $this->em,
        );

        $entity = new class { public function getId(): int { return 1; } };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/enabled: false/');
        $this->expectExceptionMessageMatches('/owner/');

        $runtime->getCollectionAdminUrl($entity, 'items');
    }

    #[Test]
    public function getCollectionAdminUrlIncludesFilterWhenTargetFieldIsAutoDetectable(): void
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(SourceEntityStub::class);
        $metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $metadata->expects($this->once())->method('getAssociationTargetClass')->with('items')->willReturn(TargetWithAutoFilterStub::class);
        $oneToManyMapping = new OneToManyAssociationMapping('items', SourceEntityStub::class, TargetWithAutoFilterStub::class);
        $oneToManyMapping->mappedBy = 'name'; // no #[ColumnFilter] but auto-detected
        $metadata->expects($this->once())->method('getAssociationMapping')->with('items')->willReturn($oneToManyMapping);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->expects($this->once())->method('isAdminEntity')->with(TargetWithAutoFilterStub::class)->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                $this->callback(fn (array $params) =>
                    isset($params['columnFilters']['name'])
                    && $params['columnFilters']['name'] === '1'
                )
            )
            ->willReturn('/admin/target?columnFilters[name]=1');

        $runtime = $this->createRuntime(
            $this->filterableProvider(),
            $this->entityDiscovery,
            $this->em,
        );

        $entity = new class { public function getId(): int { return 1; } };

        $result = $runtime->getCollectionAdminUrl($entity, 'items');
        $this->assertNotNull($result);
    }
}
class SourceEntityStub
{
    public function getId(): int { return 1; }
}

/** Target entity where the inverse field is explicitly disabled for filtering. */
class TargetWithDisabledFilterStub
{
    #[ColumnFilter(enabled: false)]
    public ?SourceEntityStub $owner = null;
}

/** Target entity where the inverse field has no ColumnFilter (auto-detectable). */
class TargetWithAutoFilterStub
{
    public string $name = '';
}
