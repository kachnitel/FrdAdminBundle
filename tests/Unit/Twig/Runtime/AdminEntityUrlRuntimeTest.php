<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityUrlRuntime;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

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
        bool $debug = false,
    ): AdminEntityUrlRuntime {
        return new AdminEntityUrlRuntime(
            $this->router,
            $this->adminRouteRuntime,
            $entityDiscovery,
            $em,
            $debug,
        );
    }

    /** @test */
    public function getEntityAdminUrlReturnsNullWithoutDependencies(): void
    {
        $runtime = $this->createRuntime();

        $result = $runtime->getEntityAdminUrl(new TestEntity());
        $this->assertNull($result);
    }

    /** @test */
    public function getEntityAdminUrlReturnsNullWhenEntityHasNoAdmin(): void
    {
        $this->entityDiscovery->method('isAdminEntity')->willReturn(false);

        $runtime = $this->createRuntime($this->entityDiscovery);

        $result = $runtime->getEntityAdminUrl(new TagEntity());
        $this->assertNull($result);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

        $runtime = $this->createRuntime($this->entityDiscovery);

        $entity = new class { public function getId(): int { return 7; } };

        $result = $runtime->getEntityAdminUrl($entity);
        $this->assertNotNull($result);
        $this->assertStringContainsString('columnFilters', $result);
    }

    /** @test */
    public function getCollectionAdminUrlReturnsNullWithoutDependencies(): void
    {
        $runtime = $this->createRuntime($this->entityDiscovery);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'tags');
        $this->assertNull($result);
    }

    /** @test */
    public function getCollectionAdminUrlReturnsNullForNonCollectionProperty(): void
    {
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('isCollectionValuedAssociation')->with('name')->willReturn(false);

        $this->em->method('getClassMetadata')->willReturn($metadata);

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'name');
        $this->assertNull($result);
    }

    /** @test */
    public function getCollectionAdminUrlReturnsNullWhenTargetHasNoAdmin(): void
    {
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);
        $metadata->method('getAssociationTargetClass')->with('tags')->willReturn(TagEntity::class);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->method('isAdminEntity')->with(TagEntity::class)->willReturn(false);

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'tags');
        $this->assertNull($result);
    }

    /** @test */
    public function getCollectionAdminUrlReturnsUrlWithFilterForOneToMany(): void
    {
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);
        $metadata->method('getAssociationTargetClass')->with('tags')->willReturn(TagEntity::class);
        $oneToManyMapping = new OneToManyAssociationMapping('tags', TestEntity::class, TagEntity::class);
        $oneToManyMapping->mappedBy = 'testEntity'; // TagEntity::$testEntity has #[ColumnFilter]
        $metadata->method('getAssociationMapping')->with('tags')->willReturn($oneToManyMapping);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->method('isAdminEntity')->with(TagEntity::class)->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                $this->callback(fn (array $params) =>
                    $params['entitySlug'] === 'tag-entity'
                    && isset($params['columnFilters']['testEntity'])
                    && $params['columnFilters']['testEntity'] === '42'
                )
            )
            ->willReturn('/admin/tag-entity?columnFilters%5BtestEntity%5D=42');

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $entity = new class { public function getId(): int { return 42; } };

        $result = $runtime->getCollectionAdminUrl($entity, 'tags');
        $this->assertNotNull($result);
        $this->assertStringContainsString('tag-entity', $result);
    }

    /** @test */
    public function getCollectionAdminUrlReturnsUrlWithFilterForManyToManyOwningSideWithInversedBy(): void
    {
        // Owning side with inversedBy pointing to TagEntity::$testEntity (has #[ColumnFilter])
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('isCollectionValuedAssociation')->with('relatedTags')->willReturn(true);
        $metadata->method('getAssociationTargetClass')->with('relatedTags')->willReturn(TagEntity::class);
        $manyToManyMapping = new ManyToManyOwningSideMapping('relatedTags', TestEntity::class, TagEntity::class);
        $manyToManyMapping->inversedBy = 'testEntity'; // TagEntity::$testEntity has #[ColumnFilter]
        $metadata->method('getAssociationMapping')->with('relatedTags')->willReturn($manyToManyMapping);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->method('isAdminEntity')->with(TagEntity::class)->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                $this->callback(fn (array $params) =>
                    $params['entitySlug'] === 'tag-entity'
                    && isset($params['columnFilters']['testEntity'])
                    && $params['columnFilters']['testEntity'] === '7'
                )
            )
            ->willReturn('/admin/tag-entity?columnFilters%5BtestEntity%5D=7');

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $entity = new class { public function getId(): int { return 7; } };

        $result = $runtime->getCollectionAdminUrl($entity, 'relatedTags');
        $this->assertNotNull($result);
    }

    /** @test */
    public function getCollectionAdminUrlReturnsUrlWithoutFilterForManyToManyWithNoInversedBy(): void
    {
        // Unidirectional ManyToMany — no mappedBy, no inversedBy → no filter possible
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $metadata->method('getAssociationTargetClass')->with('items')->willReturn(TagEntity::class);
        $manyToManyMapping = new ManyToManyOwningSideMapping('items', TestEntity::class, TagEntity::class);
        // inversedBy intentionally not set
        $metadata->method('getAssociationMapping')->with('items')->willReturn($manyToManyMapping);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->method('isAdminEntity')->with(TagEntity::class)->willReturn(true);
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

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'items');
        $this->assertNotNull($result);
    }

    /** @test */
    public function getCollectionAdminUrlThrowsInDebugModeWhenTargetFilterFieldMissingColumnFilter(): void
    {
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(TestEntity::class);
        $metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);
        $metadata->method('getAssociationTargetClass')->with('tags')->willReturn(TagEntity::class);
        // mappedBy points to a field that exists on TagEntity but has no #[ColumnFilter]
        $oneToManyMapping = new OneToManyAssociationMapping('tags', TestEntity::class, TagEntity::class);
        $oneToManyMapping->mappedBy = 'name'; // TagEntity::$name exists but has no #[ColumnFilter]
        $metadata->method('getAssociationMapping')->with('tags')->willReturn($oneToManyMapping);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->method('isAdminEntity')->with(TagEntity::class)->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em, debug: true);

        $entity = new class { public function getId(): int { return 1; } };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/#\[ColumnFilter\]/');
        $this->expectExceptionMessageMatches('/name/');

        $runtime->getCollectionAdminUrl($entity, 'tags');
    }

    /** @test */
    public function getCollectionAdminUrlReturnsUrlWithoutFilterInProductionWhenTargetFilterFieldMissingColumnFilter(): void
    {
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(TestEntity::class);
        $metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);
        $metadata->method('getAssociationTargetClass')->with('tags')->willReturn(TagEntity::class);
        $oneToManyMapping = new OneToManyAssociationMapping('tags', TestEntity::class, TagEntity::class);
        $oneToManyMapping->mappedBy = 'name'; // TagEntity::$name has no #[ColumnFilter]
        $metadata->method('getAssociationMapping')->with('tags')->willReturn($oneToManyMapping);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityDiscovery->method('isAdminEntity')->with(TagEntity::class)->willReturn(true);
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');
        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);

        // Router IS called but without a columnFilters parameter — link works, just unfiltered
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

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em, debug: false);

        $entity = new class { public function getId(): int { return 1; } };

        $result = $runtime->getCollectionAdminUrl($entity, 'tags');
        $this->assertNotNull($result); // link still generated, just without filter
    }
}
