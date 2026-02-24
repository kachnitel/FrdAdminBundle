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

class EntityListQueryServiceTest extends TestCase
{
    private EntityListQueryService $service;
    private MockObject&EntityManagerInterface $em;
    /** @var EntityRepository<object>&MockObject */
    private MockObject&EntityRepository $repository;
    private MockObject&QueryBuilder $qb;
    private Expr $expr;
    /** @var ClassMetadata<object>&MockObject */
    private MockObject&ClassMetadata $classMetadata;

    /** @var list<array{string, string}> */
    private array $orderByCalls = [];
    /** @var list<mixed> */
    private array $andWhereCalls = [];
    /** @var list<array{string, mixed}> */
    private array $setParameterCalls = [];

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->qb = $this->createMock(QueryBuilder::class);
        $this->expr = new Expr();
        $this->classMetadata = $this->createMock(ClassMetadata::class);

        $this->em->method('getRepository')->willReturn($this->repository);
        $this->em->method('getClassMetadata')->willReturn($this->classMetadata);
        $this->repository->method('createQueryBuilder')->willReturn($this->qb);

        $this->orderByCalls = [];
        $this->andWhereCalls = [];
        $this->setParameterCalls = [];

        $this->qb->method('orderBy')->willReturnCallback(function (string $sort, string $order) {
            $this->orderByCalls[] = [$sort, $order];
            return $this->qb;
        });
        $this->qb->method('andWhere')->willReturnCallback(function (mixed $where) {
            $this->andWhereCalls[] = $where;
            return $this->qb;
        });
        $this->qb->method('orWhere')->willReturnSelf();
        $this->qb->method('setParameter')->willReturnCallback(function (string $key, mixed $value) {
            $this->setParameterCalls[] = [$key, $value];
            return $this->qb;
        });
        $this->qb->method('setFirstResult')->willReturnSelf();
        $this->qb->method('setMaxResults')->willReturnSelf();
        $this->qb->method('expr')->willReturn($this->expr);

        // Mock ClassMetadata methods
        $this->classMetadata->method('getFieldNames')->willReturn(['id', 'name', 'description']);
        $this->classMetadata->method('hasField')->willReturn(true);
        $this->classMetadata->method('getTypeOfField')->willReturnCallback(fn (string $field) => match ($field) {
            'id' => 'integer',
            'name' => 'string',
            'description' => 'text',
            default => 'string',
        });

        $this->service = new EntityListQueryService($this->em);
    }

    /**
     * @test
     */
    public function serviceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(EntityListQueryService::class, $this->service);
    }

    /**
     * @test
     */
    public function buildQueryReturnsQueryBuilder(): void
    {
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [],
            [],
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithEmptyFiltersOnlyAppliesSort(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [],
            [],
            'id',
            'ASC'
        );

        $this->assertSame([['e.id', 'ASC']], $this->orderByCalls);
        $this->assertEmpty($this->andWhereCalls);
        $this->assertEmpty($this->setParameterCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithSingleFilterAppliesWhereClause(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            ['name' => 'Test Product'],
            ['name' => ['type' => 'text']],
            'id',
            'ASC'
        );

        $this->assertNotEmpty($this->andWhereCalls);
        $this->assertNotEmpty($this->setParameterCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithMultipleFiltersAppliesMultipleWhereClauses(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [
                'name' => 'Test',
                'status' => 'active',
            ],
            [
                'name' => ['type' => 'text'],
                'status' => ['type' => 'enum'],
            ],
            'id',
            'ASC'
        );

        // Each filter should produce an andWhere call
        $this->assertCount(2, $this->andWhereCalls);
        $this->assertCount(2, $this->setParameterCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithSingleEnumFilterAppliesEqualityWhere(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            ['status' => 'active'],
            ['status' => ['type' => 'enum']],
            'id',
            'ASC'
        );

        $this->assertCount(1, $this->andWhereCalls);
        $this->assertCount(1, $this->setParameterCalls);
        $this->assertSame('filter_status', $this->setParameterCalls[0][0]);
        $this->assertSame('active', $this->setParameterCalls[0][1]);
    }

    /**
     * @test
     */
    public function buildQueryWithGlobalSearchAppliesSearchFilter(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            'search term',
            [],
            [],
            'id',
            'ASC'
        );

        // Global search should add andWhere for the orX expression
        $this->assertNotEmpty($this->andWhereCalls);
        // Should set globalSearch parameter with wildcard wrapping
        $this->assertCount(1, $this->setParameterCalls);
        $this->assertSame('globalSearch', $this->setParameterCalls[0][0]);
        $this->assertSame('%search term%', $this->setParameterCalls[0][1]);
    }

    /**
     * @test
     */
    public function buildQueryWithCustomRepositoryMethodFallsBackToCreateQueryBuilder(): void
    {
        // Mock repository doesn't have 'findActive' method, so it falls back
        $this->service->buildQuery(
            'App\\Entity\\Product',
            'findActive',
            '',
            [],
            [],
            'id',
            'ASC'
        );

        $this->assertSame([['e.id', 'ASC']], $this->orderByCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithAscendingSortUsesASC(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [],
            [],
            'name',
            'ASC'
        );

        $this->assertSame([['e.name', 'ASC']], $this->orderByCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithDescendingSortUsesDESC(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [],
            [],
            'id',
            'DESC'
        );

        $this->assertSame([['e.id', 'DESC']], $this->orderByCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithDotNotationSortPrefixesAlias(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [],
            [],
            'category.name',
            'ASC'
        );

        $this->assertSame([['e.category.name', 'ASC']], $this->orderByCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithNullFilterValueSkipsFilter(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            ['name' => null],
            ['name' => ['type' => 'text']],
            'id',
            'ASC'
        );

        // Null values should be skipped — no where clause added
        $this->assertEmpty($this->andWhereCalls);
        $this->assertEmpty($this->setParameterCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithEmptyStringFilterValueSkipsFilter(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            ['name' => ''],
            ['name' => ['type' => 'text']],
            'id',
            'ASC'
        );

        // Empty string values should be skipped
        $this->assertEmpty($this->andWhereCalls);
        $this->assertEmpty($this->setParameterCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithSearchAndFiltersAppliesBoth(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            'laptop',
            ['status' => 'active'],
            ['status' => ['type' => 'enum']],
            'id',
            'ASC'
        );

        // Should have andWhere calls from both global search and column filter
        $this->assertGreaterThanOrEqual(2, count($this->andWhereCalls));
        // Global search parameter + filter parameter
        $this->assertGreaterThanOrEqual(2, count($this->setParameterCalls));
    }

    /**
     * @test
     */
    public function buildQueryWithRepositoryMethodAndFiltersCombinesBoth(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            'findPublished',
            '',
            ['status' => 'active'],
            ['status' => ['type' => 'enum']],
            'createdAt',
            'DESC'
        );

        $this->assertSame([['e.createdAt', 'DESC']], $this->orderByCalls);
        $this->assertNotEmpty($this->andWhereCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithComplexFilterCombinationAppliesAllFilters(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [
                'name' => 'Product',
                'status' => 'active',
                'minPrice' => 10,
                'maxPrice' => 100,
            ],
            [
                'name' => ['type' => 'text'],
                'status' => ['type' => 'enum'],
                'minPrice' => ['type' => 'number'],
                'maxPrice' => ['type' => 'number'],
            ],
            'name',
            'ASC'
        );

        $this->assertSame([['e.name', 'ASC']], $this->orderByCalls);
        // Each of the 4 filters should produce at least one andWhere
        $this->assertCount(4, $this->andWhereCalls);
        $this->assertCount(4, $this->setParameterCalls);
    }

    /**
     * @test
     */
    public function buildQueryPassesEntityClassToRepository(): void
    {
        $this->em->expects($this->once())
            ->method('getRepository')
            ->with('App\\Entity\\Order');

        $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            [],
            [],
            'id',
            'ASC'
        );
    }

    /**
     * @test
     */
    public function buildQueryWithArrayFilterValuesAppliesFilter(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            ['status' => ['active', 'pending']],
            ['status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true]],
            'id',
            'ASC'
        );

        $this->assertCount(1, $this->andWhereCalls);
        $this->assertCount(1, $this->setParameterCalls);
        $this->assertSame('filter_status', $this->setParameterCalls[0][0]);
        $this->assertSame(['active', 'pending'], $this->setParameterCalls[0][1]);
    }

    /**
     * @test
     */
    public function buildQueryWithNumericFilterValuesAppliesFilters(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            ['quantity' => 100, 'price' => 99.99],
            [
                'quantity' => ['type' => 'number'],
                'price' => ['type' => 'number'],
            ],
            'id',
            'ASC'
        );

        // Two numeric filters should each produce a where clause
        $this->assertCount(2, $this->andWhereCalls);
        $this->assertCount(2, $this->setParameterCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithInOperatorAndArrayValue(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            ['status' => ['pending', 'approved']],
            ['status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true]],
            'id',
            'ASC'
        );

        $this->assertCount(1, $this->andWhereCalls);
        $this->assertCount(1, $this->setParameterCalls);
        $this->assertSame('filter_status', $this->setParameterCalls[0][0]);
        $this->assertSame(['pending', 'approved'], $this->setParameterCalls[0][1]);
    }

    /**
     * @test
     */
    public function buildQueryWithInOperatorAndJsonStringValue(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            ['status' => '["pending","approved","rejected"]'],
            ['status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true]],
            'id',
            'ASC'
        );

        $this->assertCount(1, $this->andWhereCalls);
        $this->assertCount(1, $this->setParameterCalls);
        $this->assertSame('filter_status', $this->setParameterCalls[0][0]);
        $this->assertSame(['pending', 'approved', 'rejected'], $this->setParameterCalls[0][1]);
    }

    /**
     * @test
     */
    public function buildQueryWithInOperatorAndSingleStringValueFallsBack(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            ['status' => 'pending'],
            ['status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true]],
            'id',
            'ASC'
        );

        // Single string that's not valid JSON should fall back to single-value IN
        $this->assertCount(1, $this->andWhereCalls);
        $this->assertCount(1, $this->setParameterCalls);
        $this->assertSame('filter_status', $this->setParameterCalls[0][0]);
        $this->assertSame(['pending'], $this->setParameterCalls[0][1]);
    }

    /**
     * @test
     */
    public function buildQueryWithInOperatorAndEmptyArraySkipsFilter(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            ['status' => []],
            ['status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true]],
            'id',
            'ASC'
        );

        // Empty array should skip the filter entirely
        $this->assertEmpty($this->andWhereCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithInOperatorAndEmptyJsonArraySkipsFilter(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            ['status' => '[]'],
            ['status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true]],
            'id',
            'ASC'
        );

        // JSON "[]" should be decoded to empty array and skip the filter
        $this->assertEmpty($this->andWhereCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithCollectionFilter(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            ['tags' => 'important'],
            [
                'tags' => [
                    'type' => 'collection',
                    'searchFields' => ['name'],
                    'targetClass' => 'App\\Entity\\Tag',
                ],
            ],
            'id',
            'ASC'
        );

        $this->assertNotEmpty($this->andWhereCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithCollectionFilterMultipleSearchFields(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            ['attributes' => 'searchterm'],
            [
                'attributes' => [
                    'type' => 'collection',
                    'searchFields' => ['display', 'attr'],
                    'targetClass' => 'App\\Entity\\Attribute',
                ],
            ],
            'id',
            'ASC'
        );

        $this->assertNotEmpty($this->andWhereCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithCollectionFilterEmptySearchFieldsSkipsFilter(): void
    {
        $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            ['tags' => 'searchterm'],
            [
                'tags' => [
                    'type' => 'collection',
                    'searchFields' => [],
                    'targetClass' => 'App\\Entity\\Tag',
                ],
            ],
            'id',
            'ASC'
        );

        // Empty searchFields should skip the collection filter
        $this->assertEmpty($this->andWhereCalls);
    }

    /**
     * @test
     */
    public function buildQueryWithCollectionFilterGeneratesExistsWithMemberOf(): void
    {
        // Need real QueryBuilder to verify DQL generation
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);

        // Create a real QueryBuilder that we can inspect
        $realQb = new \Doctrine\ORM\QueryBuilder($em);
        // @phpstan-ignore argument.type (Test entity class doesn't exist)
        $realQb->select('e')->from('App\\Entity\\Order', 'e');

        $em->method('getRepository')->willReturn($repository);
        $repository->method('createQueryBuilder')->willReturn($realQb);

        $service = new EntityListQueryService($em);

        $filters = ['attributes' => 'searchterm'];
        $filterMetadata = [
            'attributes' => [
                'type' => 'collection',
                'searchFields' => ['display', 'attr'],
                'targetClass' => 'App\\Entity\\Attribute',
            ],
        ];

        $result = $service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );

        $dql = $result->getDQL();

        // Verify EXISTS clause with MEMBER OF syntax
        $this->assertStringContainsString('EXISTS', $dql);
        $this->assertStringContainsString('MEMBER OF', $dql);
        $this->assertStringContainsString('App\\Entity\\Attribute', $dql);
        $this->assertStringContainsString('e.attributes', $dql);
        $this->assertStringContainsString('sub_attributes.display LIKE', $dql);
        $this->assertStringContainsString('sub_attributes.attr LIKE', $dql);
    }

    /**
     * @test
     */
    public function buildQueryWithRelationFilterAlsoMatchesById(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $em->method('getExpressionBuilder')->willReturn(new \Doctrine\ORM\Query\Expr());

        $realQb = new \Doctrine\ORM\QueryBuilder($em);
        // @phpstan-ignore argument.type (Test entity class doesn't exist)
        $realQb->select('e')->from('App\\Entity\\Product', 'e');

        $em->method('getRepository')->willReturn($repository);
        $repository->method('createQueryBuilder')->willReturn($realQb);

        $service = new EntityListQueryService($em);

        $filters = ['category' => '42'];
        $filterMetadata = [
            'category' => [
                'type' => 'relation',
                'searchFields' => ['name'],
            ],
        ];

        $result = $service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );

        $dql = $result->getDQL();

        // Verify both LIKE and exact ID match are present
        $this->assertStringContainsString('rel_category.name LIKE', $dql);
        $this->assertStringContainsString('rel_category.id =', $dql);
    }

    /**
     * @test
     */
    public function buildQueryWithRelationFilterDoesNotMatchByIdForNonNumeric(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $em->method('getExpressionBuilder')->willReturn(new \Doctrine\ORM\Query\Expr());

        $realQb = new \Doctrine\ORM\QueryBuilder($em);
        // @phpstan-ignore argument.type (Test entity class doesn't exist)
        $realQb->select('e')->from('App\\Entity\\Product', 'e');

        $em->method('getRepository')->willReturn($repository);
        $repository->method('createQueryBuilder')->willReturn($realQb);

        $service = new EntityListQueryService($em);

        $filters = ['category' => 'Electronics'];
        $filterMetadata = [
            'category' => [
                'type' => 'relation',
                'searchFields' => ['name'],
            ],
        ];

        $result = $service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );

        $dql = $result->getDQL();

        // Verify LIKE is present but not exact ID match
        $this->assertStringContainsString('rel_category.name LIKE', $dql);
        $this->assertStringNotContainsString('rel_category.id =', $dql);
    }
}
