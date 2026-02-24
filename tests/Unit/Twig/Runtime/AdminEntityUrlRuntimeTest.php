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
    ): AdminEntityUrlRuntime {
        return new AdminEntityUrlRuntime(
            $this->router,
            $this->adminRouteRuntime,
            $entityDiscovery,
            $em,
        );
    }

    /**
     * @test
     */
    public function getEntityAdminUrlReturnsNullWithoutDependencies(): void
    {
        $runtime = $this->createRuntime();

        $result = $runtime->getEntityAdminUrl(new TestEntity());
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function getEntityAdminUrlReturnsNullWhenEntityHasNoAdmin(): void
    {
        $this->entityDiscovery->method('isAdminEntity')->willReturn(false);

        $runtime = $this->createRuntime($this->entityDiscovery);

        $result = $runtime->getEntityAdminUrl(new TagEntity());
        $this->assertNull($result);
    }

    /**
     * @test
     */
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
                $this->callback(function (array $params) {
                    return isset($params['entitySlug'], $params['id'])
                        && $params['id'] === 42;
                })
            )
            ->willReturn('/admin/test-entity/42');

        $runtime = $this->createRuntime($this->entityDiscovery);

        $entity = new class {
            public function getId(): int
            {
                return 42;
            }
        };

        $result = $runtime->getEntityAdminUrl($entity);
        $this->assertNotNull($result);
        $this->assertStringContainsString('42', $result);
    }

    /**
     * @test
     */
    public function getEntityAdminUrlFallsBackToIndexWhenNoId(): void
    {
        $this->entityDiscovery->method('isAdminEntity')->willReturn(true);

        $this->adminRouteRuntime->method('isActionAccessible')->willReturn(true);
        // show route exists but entity has no getId(), so tryShowUrl returns null
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                $this->callback(function (array $params) {
                    return isset($params['entitySlug'])
                        && !isset($params['columnFilters']);
                })
            )
            ->willReturn('/admin/test-entity');

        $runtime = $this->createRuntime($this->entityDiscovery);

        $entity = new class {
            public function getName(): string
            {
                return 'Test';
            }
        };

        $result = $runtime->getEntityAdminUrl($entity);
        $this->assertNotNull($result);
    }

    /**
     * @test
     */
    public function getEntityAdminUrlFallsBackToIndexWithIdFilter(): void
    {
        $this->entityDiscovery->method('isAdminEntity')->willReturn(true);

        // show route is not accessible, index is accessible
        $this->adminRouteRuntime->method('isActionAccessible')
            ->willReturnCallback(function (string $shortName, string $action): bool {
                return $action === 'index';
            });
        $this->adminRouteRuntime->method('getRoute')->willReturn('app_admin_entity_index');

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'app_admin_entity_index',
                $this->callback(function (array $params) {
                    return isset($params['entitySlug'], $params['columnFilters']['id'])
                        && $params['columnFilters']['id'] === '7';
                })
            )
            ->willReturn('/admin/test-entity?columnFilters%5Bid%5D=7');

        $runtime = $this->createRuntime($this->entityDiscovery);

        $entity = new class {
            public function getId(): int
            {
                return 7;
            }
        };

        $result = $runtime->getEntityAdminUrl($entity);
        $this->assertNotNull($result);
        $this->assertStringContainsString('columnFilters', $result);
    }

    /**
     * @test
     */
    public function getCollectionAdminUrlReturnsNullWithoutDependencies(): void
    {
        $runtime = $this->createRuntime($this->entityDiscovery);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'tags');
        $this->assertNull($result);
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
    public function getCollectionAdminUrlReturnsUrlWithFilterForOneToMany(): void
    {
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);
        $metadata->method('getAssociationTargetClass')->with('tags')->willReturn(TagEntity::class);
        $oneToManyMapping = new OneToManyAssociationMapping('tags', TestEntity::class, TagEntity::class);
        $oneToManyMapping->mappedBy = 'testEntity';
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
                $this->callback(function (array $params) {
                    return $params['entitySlug'] === 'tag-entity'
                        && isset($params['columnFilters']['testEntity'])
                        && $params['columnFilters']['testEntity'] === '42';
                })
            )
            ->willReturn('/admin/tag-entity?columnFilters%5BtestEntity%5D=42');

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $entity = new class {
            public function getId(): int
            {
                return 42;
            }
        };

        $result = $runtime->getCollectionAdminUrl($entity, 'tags');
        $this->assertNotNull($result);
        $this->assertStringContainsString('tag-entity', $result);
    }

    /**
     * @test
     */
    public function getCollectionAdminUrlReturnsUrlWithoutFilterForManyToMany(): void
    {
        /** @var ClassMetadata<TestEntity>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $metadata->method('getAssociationTargetClass')->with('items')->willReturn(TagEntity::class);
        $manyToManyMapping = new ManyToManyOwningSideMapping('items', TestEntity::class, TagEntity::class);
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
                $this->callback(function (array $params) {
                    return $params['entitySlug'] === 'tag-entity'
                        && !isset($params['columnFilters']);
                })
            )
            ->willReturn('/admin/tag-entity');

        $runtime = $this->createRuntime($this->entityDiscovery, $this->em);

        $result = $runtime->getCollectionAdminUrl(new TestEntity(), 'items');
        $this->assertNotNull($result);
    }
}
