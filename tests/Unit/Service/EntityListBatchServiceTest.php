<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Group('entity-list')]
#[Group('batch')]
#[Group('object-authorization')]
#[AllowMockObjectsWithoutExpectations]
final class EntityListBatchServiceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EntityListPermissionService&MockObject */
    private EntityListPermissionService $permissionService;

    /** @var ObjectAuthorizationChecker&MockObject */
    private ObjectAuthorizationChecker $objectAuthChecker;

    private EntityListBatchService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->permissionService = $this->createMock(EntityListPermissionService::class);

        // Permissive by default so every pre-existing test in this class —
        // none of which care about object-level authorization — keeps
        // passing unchanged. Tests that DO care override this per-test.
        $this->objectAuthChecker = $this->createMock(ObjectAuthorizationChecker::class);
        $this->objectAuthChecker->method('isGranted')->willReturn(true);

        $this->service = new EntityListBatchService(
            $this->em,
            $this->permissionService,
            $this->objectAuthChecker,
        );
    }

    #[Test]
    public function batchDeleteThrowsExceptionWhenDataSourceDoesNotSupportBatchDelete(): void
    {
        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->expects($this->once())->method('supportsAction')
            ->with('batch_delete')
            ->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Batch delete not supported for this data source.');

        $this->service->batchDelete(
            [1, 2, 3],
            $dataSource,
            'App\\Entity\\TestEntity',
            'TestEntity'
        );
    }

    #[Test]
    public function batchDeleteThrowsExceptionWhenPermissionDenied(): void
    {
        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->expects($this->once())->method('supportsAction')
            ->with('batch_delete')
            ->willReturn(true);

        $this->permissionService->expects($this->once())->method('canBatchDelete')
            ->with('App\\Entity\\TestEntity', 'TestEntity')
            ->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Batch delete not allowed for this entity.');

        $this->service->batchDelete(
            [1, 2, 3],
            $dataSource,
            'App\\Entity\\TestEntity',
            'TestEntity'
        );
    }

    #[Test]
    public function batchDeleteThrowsExceptionForNonDoctrineDataSource(): void
    {
        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->expects($this->once())->method('supportsAction')
            ->with('batch_delete')
            ->willReturn(true);

        $this->permissionService->method('canBatchDelete')
            ->willReturn(true);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Batch delete only supported for Doctrine entities.');

        $this->service->batchDelete(
            [1, 2, 3],
            $dataSource,
            'App\\Entity\\TestEntity',
            'TestEntity'
        );
    }

    #[Test]
    public function batchDeleteDoesNothingWithEmptyIds(): void
    {
        $doctrineDataSource = $this->createDoctrineDataSourceMock();
        $doctrineDataSource->expects($this->once())->method('supportsAction')
            ->with('batch_delete')
            ->willReturn(true);
        $doctrineDataSource->method('getEntityClass')
            ->willReturn('App\\Entity\\TestEntity');

        $this->permissionService->method('canBatchDelete')
            ->willReturn(true);

        // EntityManager should never be called
        $this->em->expects($this->never())->method('getRepository');
        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->never())->method('flush');

        $this->service->batchDelete(
            [],
            $doctrineDataSource,
            'App\\Entity\\TestEntity',
            'TestEntity'
        );
    }

    #[Test]
    public function batchDeleteRemovesEntities(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();

        $doctrineDataSource = $this->createDoctrineDataSourceMock();
        $doctrineDataSource->expects($this->once())->method('supportsAction')
            ->with('batch_delete')
            ->willReturn(true);
        $doctrineDataSource->method('getEntityClass')
            ->willReturn('App\\Entity\\TestEntity');

        $this->permissionService->method('canBatchDelete')
            ->willReturn(true);

        /** @var EntityRepository<object>&MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->atLeast(2))->method('find')
            ->willReturnMap([
                [1, null, null, $entity1],
                [2, null, null, $entity2],
            ]);

        $this->em->expects($this->once())->method('getRepository')
            ->with('App\\Entity\\TestEntity')
            ->willReturn($repository);

        $this->em->expects($this->exactly(2))
            ->method('remove')
            ->willReturnCallback(function ($entity) use ($entity1, $entity2) {
                $this->assertContains($entity, [$entity1, $entity2]);
            });

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->batchDelete(
            [1, 2],
            $doctrineDataSource,
            'App\\Entity\\TestEntity',
            'TestEntity'
        );

        $this->assertSame([1, 2], $result);
    }

    #[Test]
    public function batchDeleteSkipsNullEntities(): void
    {
        $entity1 = new \stdClass();

        $doctrineDataSource = $this->createDoctrineDataSourceMock();
        $doctrineDataSource->expects($this->once())->method('supportsAction')
            ->with('batch_delete')
            ->willReturn(true);
        $doctrineDataSource->method('getEntityClass')
            ->willReturn('App\\Entity\\TestEntity');

        $this->permissionService->method('canBatchDelete')
            ->willReturn(true);

        /** @var EntityRepository<object>&MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->atLeast(2))->method('find')
            ->willReturnMap([
                [1, null, null, $entity1],
                [999, null, null, null], // Entity not found
            ]);

        $this->em->method('getRepository')
            ->willReturn($repository);

        // Only entity1 should be removed
        $this->em->expects($this->once())
            ->method('remove')
            ->with($entity1);

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->batchDelete(
            [1, 999],
            $doctrineDataSource,
            'App\\Entity\\TestEntity',
            'TestEntity'
        );

        $this->assertSame([1], $result);
    }

    #[Test]
    public function batchDeleteSkipsEntitiesDeniedByObjectAuthorizationButStillRemovesGrantedOnes(): void
    {
        $allowedEntity = new \stdClass();
        $deniedEntity = new \stdClass();

        $doctrineDataSource = $this->createDoctrineDataSourceMock();
        $doctrineDataSource->method('supportsAction')->willReturn(true);
        $doctrineDataSource->method('getEntityClass')->willReturn('App\\Entity\\TestEntity');

        $this->permissionService->method('canBatchDelete')->willReturn(true);

        /** @var EntityRepository<object>&MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturnMap([
            [1, null, null, $allowedEntity],
            [2, null, null, $deniedEntity],
        ]);
        $this->em->method('getRepository')->willReturn($repository);

        $this->objectAuthChecker = $this->createMock(ObjectAuthorizationChecker::class);
        $this->objectAuthChecker->method('isGranted')->willReturnMap([
            [AdminEntityVoter::ADMIN_DELETE, $allowedEntity, true],
            [AdminEntityVoter::ADMIN_DELETE, $deniedEntity, false],
        ]);
        $this->service = new EntityListBatchService($this->em, $this->permissionService, $this->objectAuthChecker);

        // Only the granted entity is removed — the denied one is skipped,
        // not thrown for, so the rest of the batch still goes through.
        $this->em->expects($this->once())->method('remove')->with($allowedEntity);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->batchDelete(
            [1, 2],
            $doctrineDataSource,
            'App\\Entity\\TestEntity',
            'TestEntity',
        );

        // The denied ID is absent from the result — DeleteButton passes
        // this on to completeAction(), so a denied row stays selected
        // rather than being reported as removed.
        $this->assertSame([1], $result);
    }

    #[Test]
    public function getEntityIdsReturnsArrayOfIds(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();

        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->expects($this->atLeast(2))->method('getItemId')
            ->willReturnMap([
                [$entity1, 1],
                [$entity2, 2],
            ]);

        $ids = $this->service->getEntityIds([$entity1, $entity2], $dataSource);

        $this->assertSame([1, 2], $ids);
    }

    #[Test]
    public function getEntityIdsReturnsEmptyArrayForEmptyEntities(): void
    {
        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createStub(DataSourceInterface::class);

        $ids = $this->service->getEntityIds([], $dataSource);

        $this->assertSame([], $ids);
    }

    #[Test]
    public function getEntityIdsHandlesStringIds(): void
    {
        $entity1 = new \stdClass();

        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->expects($this->once())->method('getItemId')
            ->with($entity1)
            ->willReturn('uuid-123-456');

        $ids = $this->service->getEntityIds([$entity1], $dataSource);

        $this->assertSame(['uuid-123-456'], $ids);
    }

    /**
     * @return DoctrineDataSource&MockObject
     */
    private function createDoctrineDataSourceMock(): DoctrineDataSource
    {
        return $this->createMock(DoctrineDataSource::class);
    }
}
