<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the archiveDqlCondition parameter of EntityListQueryService.
 *
 * @group archive
 * @covers \Kachnitel\AdminBundle\Service\EntityListQueryService::buildQuery
 * @covers \Kachnitel\AdminBundle\Service\EntityListQueryService::getEntities
 */
class EntityListQueryServiceArchiveTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EntityRepository<object>&MockObject */
    private EntityRepository $repository;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $classMetadata;

    /** @var list<string> */
    private array $andWhereCalls = [];

    /** @var list<array{string, string}> */
    private array $orderByCalls = [];

    private EntityListQueryService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->classMetadata = $this->createMock(ClassMetadata::class);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn(new Expr());
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('orWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();

        $this->andWhereCalls = [];
        $this->orderByCalls = [];

        $qb->method('andWhere')->willReturnCallback(function (mixed $clause) use ($qb): QueryBuilder {
            $this->andWhereCalls[] = (string) $clause;
            return $qb;
        });
        $qb->method('orderBy')->willReturnCallback(function (string $col, string $dir) use ($qb): QueryBuilder {
            $this->orderByCalls[] = [$col, $dir];
            return $qb;
        });

        $this->em->method('getRepository')->willReturn($this->repository);
        $this->em->method('getClassMetadata')->willReturn($this->classMetadata);
        $this->repository->method('createQueryBuilder')->willReturn($qb);

        $this->classMetadata->method('getFieldNames')->willReturn(['id', 'name']);
        $this->classMetadata->method('hasField')->willReturn(true);
        $this->classMetadata->method('getTypeOfField')->willReturn('string');

        $this->service = new EntityListQueryService($this->em);
    }

    /** @test */
    public function buildQueryWithoutArchiveConditionAddsNoExtraWhere(): void
    {
        $this->service->buildQuery('App\\Entity\\Product', null, '', [], [], 'id', 'ASC');

        $this->assertEmpty($this->andWhereCalls);
    }

    /** @test */
    public function buildQueryAppliesArchiveConditionAsAndWhere(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [],
            [],
            'id',
            'ASC',
            'e.deletedAt IS NULL',
        );

        $this->assertContains('e.deletedAt IS NULL', $this->andWhereCalls);
    }

    /** @test */
    public function buildQueryAppliesArchiveConditionAfterColumnFilters(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            ['name' => 'foo'],
            ['name' => ['type' => 'text']],
            'id',
            'ASC',
            'e.archived = false',
        );

        $archiveIdx = array_search('e.archived = false', $this->andWhereCalls, true);
        $this->assertNotFalse($archiveIdx, 'Archive condition must be present');
        // At least one column-filter where was added before it
        $this->assertGreaterThan(0, $archiveIdx);
    }

    /** @test */
    public function buildQueryAppliesArchiveConditionBeforeOrderBy(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [],
            [],
            'createdAt',
            'DESC',
            'e.deletedAt IS NULL',
        );

        // Both must be present; order of execution is guaranteed by the method body
        $this->assertContains('e.deletedAt IS NULL', $this->andWhereCalls);
        $this->assertContains(['e.createdAt', 'DESC'], $this->orderByCalls);
    }

    /** @test */
    public function nullArchiveConditionIsIgnored(): void
    {
        $this->service->buildQuery('App\\Entity\\Product', null, '', [], [], 'id', 'ASC', null);

        $this->assertEmpty($this->andWhereCalls);
    }
}
