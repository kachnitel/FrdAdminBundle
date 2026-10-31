<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * ADMIN_SHOW-denied columns must not be searchable, filterable or sortable:
 * `search`, `sortBy` and `columnFilters` are client-writable, so the query layer is
 * where the decision is enforced.
 */
#[CoversClass(EntityListQueryService::class)]
#[Group('column-permission-query')]
#[Group('entity-list')]
#[AllowMockObjectsWithoutExpectations]
final class EntityListQueryServiceColumnPermissionTest extends TestCase
{
    private const ENTITY = 'App\\Entity\\Employee';

    private EntityListQueryService $service;

    /** @var ColumnPermissionService&MockObject */
    private ColumnPermissionService $permissions;

    /** @var list<string> */
    private array $orderBy = [];

    /** @var list<mixed> */
    private array $andWhere = [];

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $qb = $this->createMock(QueryBuilder::class);
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);

        $em->method('getRepository')->willReturn($repository);
        $em->method('getClassMetadata')->willReturn($metadata);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $qb->method('orderBy')->willReturnCallback(function (string $sort) use ($qb): QueryBuilder {
            $this->orderBy[] = $sort;

            return $qb;
        });
        $qb->method('andWhere')->willReturnCallback(function (mixed $where) use ($qb): QueryBuilder {
            $this->andWhere[] = (string) $where;

            return $qb;
        });
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('expr')->willReturn(new Expr());

        $metadata->method('getFieldNames')->willReturn(['id', 'name', 'salary', 'internalNotes']);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $metadata->method('getTypeOfField')->willReturnCallback(
            fn (string $field): string => $field === 'id' ? 'integer' : 'string',
        );

        $this->permissions = $this->createMock(ColumnPermissionService::class);
        $this->service = new EntityListQueryService($em, $this->permissions);
    }

    /**
     * @param list<string> $denied
     */
    private function denyOnShow(array $denied): void
    {
        $this->permissions->method('getDeniedColumnsForAction')
            ->with(self::ENTITY, AdminEntityVoter::ADMIN_SHOW)
            ->willReturn($denied);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function query(string $search, array $filters, string $sortBy): void
    {
        $this->service->buildQuery(
            self::ENTITY,
            null,
            $search,
            $filters,
            array_map(fn (): array => ['type' => 'text'], $filters),
            $sortBy,
            'ASC',
        );
    }

    #[Test]
    public function searchableFieldsExcludeDeniedColumns(): void
    {
        $this->denyOnShow(['salary', 'internalNotes']);

        $this->assertSame(['name'], $this->service->getSearchableFieldNames(self::ENTITY));
    }

    #[Test]
    public function searchableFieldsIncludeEverythingWhenNothingIsDenied(): void
    {
        $this->denyOnShow([]);

        $this->assertSame(
            ['name', 'salary', 'internalNotes'],
            $this->service->getSearchableFieldNames(self::ENTITY),
            'control: permitted columns are still searchable',
        );
    }

    #[Test]
    public function globalSearchDoesNotMatchDeniedColumns(): void
    {
        $this->denyOnShow(['salary']);

        $this->query('needle', [], 'id');

        $this->assertCount(1, $this->andWhere);
        $this->assertStringContainsString('e.name', $this->andWhere[0]);
        $this->assertStringContainsString('e.internalNotes', $this->andWhere[0], 'control: permitted field is searched');
        $this->assertStringNotContainsString('e.salary', $this->andWhere[0]);
    }

    #[Test]
    public function globalSearchIsSkippedWhenEverySearchableColumnIsDenied(): void
    {
        $this->denyOnShow(['name', 'salary', 'internalNotes']);

        $this->query('needle', [], 'id');

        $this->assertSame([], $this->andWhere);
    }

    #[Test]
    public function columnFilterOnDeniedColumnIsDropped(): void
    {
        $this->denyOnShow(['salary']);

        $this->query('', ['salary' => '90000'], 'id');

        $this->assertSame([], $this->andWhere);
    }

    #[Test]
    public function columnFilterOnPermittedColumnStillApplies(): void
    {
        $this->denyOnShow(['salary']);

        $this->query('', ['salary' => '90000', 'name' => 'Bob'], 'id');

        $this->assertCount(1, $this->andWhere, 'only the permitted filter is applied');
        $this->assertStringContainsString('e.name', $this->andWhere[0]);
    }

    #[Test]
    public function sortByDeniedColumnFallsBackToIdentifier(): void
    {
        $this->denyOnShow(['salary']);

        $this->query('', [], 'salary');

        $this->assertSame(['e.id'], $this->orderBy);
    }

    #[Test]
    public function sortByDottedPathOnDeniedColumnFallsBackToIdentifier(): void
    {
        $this->denyOnShow(['salary']);

        $this->query('', [], 'salary.amount');

        $this->assertSame(['e.id'], $this->orderBy);
    }

    #[Test]
    public function sortByPermittedColumnIsUnchanged(): void
    {
        $this->denyOnShow(['salary']);

        $this->query('', [], 'name');

        $this->assertSame(['e.name'], $this->orderBy);
    }

    #[Test]
    public function permissionsAreOnlyAskedAboutShow(): void
    {
        $this->permissions->expects($this->atLeastOnce())
            ->method('getDeniedColumnsForAction')
            ->with(self::ENTITY, AdminEntityVoter::ADMIN_SHOW)
            ->willReturn([]);

        $this->query('x', ['name' => 'a'], 'name');
    }
}
