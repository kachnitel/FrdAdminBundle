<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\Traits\DateFilterTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * DateFilterTrait has no public surface of its own — it's exercised entirely
 * through EntityListQueryService::buildQuery(), the only class that composes it.
 */
#[CoversClass(DateFilterTrait::class)]
#[UsesClass(EntityListQueryService::class)]
#[Group('entity-list')]
#[Group('query')]
#[Group('date-filter')]
final class EntityListQueryServiceDateFilterTest extends TestCase
{
    private EntityListQueryService $service;
    private Stub&QueryBuilder $qb;

    /** @var list<mixed> */
    private array $andWhereCalls = [];

    /** @var list<array{string, mixed}> */
    private array $setParameterCalls = [];

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $repository = $this->createStub(EntityRepository::class);
        $this->qb = $this->createStub(QueryBuilder::class);

        $em->method('getRepository')->willReturn($repository);
        $repository->method('createQueryBuilder')->willReturn($this->qb);

        $this->andWhereCalls = [];
        $this->setParameterCalls = [];

        $this->qb->method('andWhere')->willReturnCallback(function (mixed $where): QueryBuilder {
            $this->andWhereCalls[] = $where;

            return $this->qb;
        });
        $this->qb->method('setParameter')->willReturnCallback(function (string $key, mixed $value): QueryBuilder {
            $this->setParameterCalls[] = [$key, $value];

            return $this->qb;
        });
        $this->qb->method('orderBy')->willReturnSelf();

        $this->service = new EntityListQueryService($em);
    }

    private function buildQueryWithFilter(string $column, mixed $value, string $type): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Event',
            null,
            '',
            [$column => $value],
            [$column => ['type' => $type]],
            'id',
            'ASC',
        );
    }

    /**
     * @param list<array{string, mixed}> $calls
     */
    private function assertDateTimeParam(array $calls, int $index, string $expectedKey, string $expectedFormatted): void
    {
        [$key, $value] = $calls[$index];
        $this->assertSame($expectedKey, $key);
        $this->assertInstanceOf(\DateTimeInterface::class, $value);
        $this->assertSame($expectedFormatted, $value->format('Y-m-d H:i:s'));
    }

    // ── applyDateFilter (ColumnFilter::TYPE_DATE) ───────────────────────────

    #[Test]
    public function dateFilterWithStringValueMatchesFullDay(): void
    {
        $this->buildQueryWithFilter('eventDate', '2026-03-15', ColumnFilter::TYPE_DATE);

        $this->assertSame(
            ['e.eventDate BETWEEN :filter_eventDate_start AND :filter_eventDate_end'],
            $this->andWhereCalls,
        );
        $this->assertCount(2, $this->setParameterCalls);
        $this->assertDateTimeParam($this->setParameterCalls, 0, 'filter_eventDate_start', '2026-03-15 00:00:00');
        $this->assertDateTimeParam($this->setParameterCalls, 1, 'filter_eventDate_end', '2026-03-15 23:59:59');
    }

    #[Test]
    public function dateFilterAcceptsDateTimeInterfaceValueDirectly(): void
    {
        $this->buildQueryWithFilter('eventDate', new \DateTimeImmutable('2026-06-01 14:30:00'), ColumnFilter::TYPE_DATE);

        $this->assertDateTimeParam($this->setParameterCalls, 0, 'filter_eventDate_start', '2026-06-01 00:00:00');
        $this->assertDateTimeParam($this->setParameterCalls, 1, 'filter_eventDate_end', '2026-06-01 23:59:59');
    }

    #[Test]
    public function dateFilterDerivesParamNameFromColumn(): void
    {
        $this->buildQueryWithFilter('publishedAt', '2026-01-01', ColumnFilter::TYPE_DATE);

        $this->assertSame(
            ['e.publishedAt BETWEEN :filter_publishedAt_start AND :filter_publishedAt_end'],
            $this->andWhereCalls,
        );
    }

    // ── applyDateRangeFilter (ColumnFilter::TYPE_DATERANGE) ─────────────────

    #[Test]
    public function dateRangeFilterWithJsonStringAppliesBothBounds(): void
    {
        $this->buildQueryWithFilter(
            'eventDate',
            json_encode(['from' => '2026-01-01', 'to' => '2026-01-31']),
            ColumnFilter::TYPE_DATERANGE,
        );

        $this->assertSame(
            ['e.eventDate >= :filter_eventDate_from', 'e.eventDate <= :filter_eventDate_to'],
            $this->andWhereCalls,
        );
        $this->assertDateTimeParam($this->setParameterCalls, 0, 'filter_eventDate_from', '2026-01-01 00:00:00');
        $this->assertDateTimeParam($this->setParameterCalls, 1, 'filter_eventDate_to', '2026-01-31 23:59:59');
    }

    #[Test]
    public function dateRangeFilterAcceptsArrayValueDirectly(): void
    {
        $this->buildQueryWithFilter(
            'eventDate',
            ['from' => '2026-02-01', 'to' => '2026-02-10'],
            ColumnFilter::TYPE_DATERANGE,
        );

        $this->assertCount(2, $this->andWhereCalls);
    }

    #[Test]
    public function dateRangeFilterWithOnlyFromAppliesLowerBoundOnly(): void
    {
        $this->buildQueryWithFilter(
            'eventDate',
            json_encode(['from' => '2026-05-01']),
            ColumnFilter::TYPE_DATERANGE,
        );

        $this->assertSame(['e.eventDate >= :filter_eventDate_from'], $this->andWhereCalls);
        $this->assertCount(1, $this->setParameterCalls);
    }

    #[Test]
    public function dateRangeFilterWithOnlyToAppliesUpperBoundOnly(): void
    {
        $this->buildQueryWithFilter(
            'eventDate',
            json_encode(['to' => '2026-05-31']),
            ColumnFilter::TYPE_DATERANGE,
        );

        $this->assertSame(['e.eventDate <= :filter_eventDate_to'], $this->andWhereCalls);
        $this->assertCount(1, $this->setParameterCalls);
    }

    #[Test]
    public function dateRangeFilterTreatsEmptyStringBoundsAsNotProvided(): void
    {
        $this->buildQueryWithFilter(
            'eventDate',
            json_encode(['from' => '', 'to' => '']),
            ColumnFilter::TYPE_DATERANGE,
        );

        $this->assertEmpty($this->andWhereCalls);
        $this->assertEmpty($this->setParameterCalls);
    }

    #[Test]
    public function dateRangeFilterWithNonArrayValueIsNoOp(): void
    {
        // A JSON-encoded scalar decodes to a non-array — must not error, just skip.
        $this->buildQueryWithFilter('eventDate', json_encode('not-a-range'), ColumnFilter::TYPE_DATERANGE);

        $this->assertEmpty($this->andWhereCalls);
        $this->assertEmpty($this->setParameterCalls);
    }

    #[Test]
    public function dateRangeFilterAcceptsDateTimeInterfaceBoundsDirectly(): void
    {
        $this->buildQueryWithFilter(
            'eventDate',
            [
                'from' => new \DateTimeImmutable('2026-07-01 09:00:00'),
                'to' => new \DateTimeImmutable('2026-07-31 17:00:00'),
            ],
            ColumnFilter::TYPE_DATERANGE,
        );

        $this->assertDateTimeParam($this->setParameterCalls, 0, 'filter_eventDate_from', '2026-07-01 00:00:00');
        $this->assertDateTimeParam($this->setParameterCalls, 1, 'filter_eventDate_to', '2026-07-31 23:59:59');
    }
}
