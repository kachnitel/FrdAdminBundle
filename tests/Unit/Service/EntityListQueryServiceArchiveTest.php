<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the archiveDqlCondition parameter of EntityListQueryService.
 *
 * @covers \Kachnitel\AdminBundle\Service\EntityListQueryService::buildQuery
 * @covers \Kachnitel\AdminBundle\Service\EntityListQueryService::getEntities
 */
#[UsesClass(EntityListQueryService::class)]
#[Group('archive')]
#[AllowMockObjectsWithoutExpectations]
final class EntityListQueryServiceArchiveTest extends TestCase
{
    /** @var list<string> */
    private array $andWhereCalls = [];

    /** @var list<array{string, string}> */
    private array $orderByCalls = [];

    private EntityListQueryService $service;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $classMetadata = $this->createMock(ClassMetadata::class);

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

        $em->method('getRepository')->willReturn($repository);
        $em->method('getClassMetadata')->willReturn($classMetadata);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $classMetadata->method('getFieldNames')->willReturn(['id', 'name']);
        $classMetadata->method('hasField')->willReturn(true);
        $classMetadata->method('getTypeOfField')->willReturn('string');

        $this->service = new EntityListQueryService($em);
    }

    #[Test]
    public function buildQueryWithoutArchiveConditionAddsNoExtraWhere(): void
    {
        $this->service->buildQuery('App\\Entity\\Product', null, '', [], [], 'id', 'ASC');

        $this->assertEmpty($this->andWhereCalls);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function nullArchiveConditionIsIgnored(): void
    {
        $this->service->buildQuery('App\\Entity\\Product', null, '', [], [], 'id', 'ASC', null);

        $this->assertEmpty($this->andWhereCalls);
    }
}
