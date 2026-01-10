<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class EntityListBatchServiceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EntityListPermissionService&MockObject */
    private EntityListPermissionService $permissionService;

    private EntityListBatchService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->permissionService = $this->createMock(EntityListPermissionService::class);

        $this->service = new EntityListBatchService(
            $this->em,
            $this->permissionService
        );
    }

    /**
     * @test
     */
    public function batchDeleteThrowsExceptionWhenDataSourceDoesNotSupportBatchDelete(): void
    {
        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('supportsAction')
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

    /**
     * @test
     */
    public function batchDeleteThrowsExceptionWhenPermissionDenied(): void
    {
        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('supportsAction')
            ->with('batch_delete')
            ->willReturn(true);

        $this->permissionService->method('canBatchDelete')
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

    /**
     * @test
     */
    public function batchDeleteThrowsExceptionForNonDoctrineDataSource(): void
    {
        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('supportsAction')
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

    /**
     * @test
     */
    public function batchDeleteDoesNothingWithEmptyIds(): void
    {
        $doctrineDataSource = $this->createDoctrineDataSourceMock();
        $doctrineDataSource->method('supportsAction')
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

    /**
     * @test
     */
    public function batchDeleteRemovesEntities(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();

        $doctrineDataSource = $this->createDoctrineDataSourceMock();
        $doctrineDataSource->method('supportsAction')
            ->with('batch_delete')
            ->willReturn(true);
        $doctrineDataSource->method('getEntityClass')
            ->willReturn('App\\Entity\\TestEntity');

        $this->permissionService->method('canBatchDelete')
            ->willReturn(true);

        /** @var EntityRepository<object>&MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')
            ->willReturnMap([
                [1, null, null, $entity1],
                [2, null, null, $entity2],
            ]);

        $this->em->method('getRepository')
            ->with('App\\Entity\\TestEntity')
            ->willReturn($repository);

        $this->em->expects($this->exactly(2))
            ->method('remove')
            ->willReturnCallback(function ($entity) use ($entity1, $entity2) {
                $this->assertContains($entity, [$entity1, $entity2]);
            });

        $this->em->expects($this->once())->method('flush');

        $this->service->batchDelete(
            [1, 2],
            $doctrineDataSource,
            'App\\Entity\\TestEntity',
            'TestEntity'
        );
    }

    /**
     * @test
     */
    public function batchDeleteSkipsNullEntities(): void
    {
        $entity1 = new \stdClass();

        $doctrineDataSource = $this->createDoctrineDataSourceMock();
        $doctrineDataSource->method('supportsAction')
            ->with('batch_delete')
            ->willReturn(true);
        $doctrineDataSource->method('getEntityClass')
            ->willReturn('App\\Entity\\TestEntity');

        $this->permissionService->method('canBatchDelete')
            ->willReturn(true);

        /** @var EntityRepository<object>&MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')
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

        $this->service->batchDelete(
            [1, 999],
            $doctrineDataSource,
            'App\\Entity\\TestEntity',
            'TestEntity'
        );
    }

    /**
     * @test
     */
    public function getEntityIdsReturnsArrayOfIds(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();

        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('getItemId')
            ->willReturnMap([
                [$entity1, 1],
                [$entity2, 2],
            ]);

        $ids = $this->service->getEntityIds([$entity1, $entity2], $dataSource);

        $this->assertSame([1, 2], $ids);
    }

    /**
     * @test
     */
    public function getEntityIdsReturnsEmptyArrayForEmptyEntities(): void
    {
        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);

        $ids = $this->service->getEntityIds([], $dataSource);

        $this->assertSame([], $ids);
    }

    /**
     * @test
     */
    public function getEntityIdsHandlesStringIds(): void
    {
        $entity1 = new \stdClass();

        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('getItemId')
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
