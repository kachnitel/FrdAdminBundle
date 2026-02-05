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
        $this->qb->method('andWhere')->willReturnSelf();
        $this->qb->method('orWhere')->willReturnSelf();
        $this->qb->method('setParameter')->willReturnSelf();
        $this->qb->method('orderBy')->willReturnSelf();
        $this->qb->method('setFirstResult')->willReturnSelf();
        $this->qb->method('setMaxResults')->willReturnSelf();
        $this->qb->method('expr')->willReturn($this->expr);

        // Mock ClassMetadata methods
        $this->classMetadata->method('getFieldNames')->willReturn(['id', 'name', 'description']);
        $this->classMetadata->method('hasField')->willReturn(true);

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
    public function buildQueryWithEmptyFilters(): void
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
    public function buildQueryWithSingleFilter(): void
    {
        $filters = ['name' => 'Test Product'];
        $filterMetadata = ['name' => ['type' => 'text']];
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithMultipleFilters(): void
    {
        $filters = [
            'name' => 'Test',
            'status' => 'active',
            'category' => 1,
        ];
        $filterMetadata = [
            'name' => ['type' => 'text'],
            'status' => ['type' => 'simple'],
            'category' => ['type' => 'relation'],
        ];
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithGlobalSearch(): void
    {
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            'search term',
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
    public function buildQueryWithCustomRepositoryMethod(): void
    {
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            'findActive',
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
    public function buildQueryWithAscendingSort(): void
    {
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [],
            [],
            'name',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithDescendingSort(): void
    {
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [],
            [],
            'id',
            'DESC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithDotNotationSort(): void
    {
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            [],
            [],
            'category.name',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithNullValues(): void
    {
        $filters = ['name' => null];
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            $filters,
            [],
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithEmptyStringValues(): void
    {
        $filters = ['name' => ''];
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            $filters,
            [],
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithSearchAndFilters(): void
    {
        $filters = ['status' => 'active'];
        $filterMetadata = ['status' => ['type' => 'simple']];
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            'laptop',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithRepositoryMethodAndFilters(): void
    {
        $filters = ['status' => 'active'];
        $filterMetadata = ['status' => ['type' => 'simple']];
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            'findPublished',
            '',
            $filters,
            $filterMetadata,
            'createdAt',
            'DESC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithComplexFilterCombination(): void
    {
        $filters = [
            'name' => 'Product',
            'status' => 'active',
            'minPrice' => 10,
            'maxPrice' => 100,
        ];
        $filterMetadata = [
            'name' => ['type' => 'text'],
            'status' => ['type' => 'simple'],
            'minPrice' => ['type' => 'number'],
            'maxPrice' => ['type' => 'number'],
        ];
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            $filters,
            $filterMetadata,
            'name',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryPreservesEntityClass(): void
    {
        $entityClass = 'App\\Entity\\Order';
        $result = $this->service->buildQuery(
            $entityClass,
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
    public function buildQueryWithArrayFilterValues(): void
    {
        $filters = ['status' => ['active', 'pending']];
        $filterMetadata = ['status' => ['type' => 'multi']];
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithNumericFilterValues(): void
    {
        $filters = ['quantity' => 100, 'price' => 99.99];
        $filterMetadata = [
            'quantity' => ['type' => 'number'],
            'price' => ['type' => 'number'],
        ];
        $result = $this->service->buildQuery(
            'App\\Entity\\Product',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithInOperatorAndArrayValue(): void
    {
        $filters = ['status' => ['pending', 'approved']];
        $filterMetadata = [
            'status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true],
        ];
        $result = $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithInOperatorAndJsonStringValue(): void
    {
        $filters = ['status' => '["pending","approved","rejected"]'];
        $filterMetadata = [
            'status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true],
        ];
        $result = $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithInOperatorAndSingleStringValue(): void
    {
        // When JSON decode fails, should fall back to single value
        $filters = ['status' => 'pending'];
        $filterMetadata = [
            'status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true],
        ];
        $result = $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithInOperatorAndEmptyArraySkipsFilter(): void
    {
        $filters = ['status' => []];
        $filterMetadata = [
            'status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true],
        ];
        $result = $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithInOperatorAndEmptyJsonArraySkipsFilter(): void
    {
        $filters = ['status' => '[]'];
        $filterMetadata = [
            'status' => ['type' => 'enum', 'operator' => 'IN', 'multiple' => true],
        ];
        $result = $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithCollectionFilter(): void
    {
        $filters = ['tags' => 'important'];
        $filterMetadata = [
            'tags' => [
                'type' => 'collection',
                'searchFields' => ['name'],
                'targetClass' => 'App\\Entity\\Tag',
            ],
        ];
        $result = $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithCollectionFilterMultipleSearchFields(): void
    {
        $filters = ['attributes' => 'searchterm'];
        $filterMetadata = [
            'attributes' => [
                'type' => 'collection',
                'searchFields' => ['display', 'attr'],
                'targetClass' => 'App\\Entity\\Attribute',
            ],
        ];
        $result = $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    /**
     * @test
     */
    public function buildQueryWithCollectionFilterEmptySearchFieldsSkipsFilter(): void
    {
        $filters = ['tags' => 'searchterm'];
        $filterMetadata = [
            'tags' => [
                'type' => 'collection',
                'searchFields' => [],
                'targetClass' => 'App\\Entity\\Tag',
            ],
        ];
        // Should not throw, just skip the filter
        $result = $this->service->buildQuery(
            'App\\Entity\\Order',
            null,
            '',
            $filters,
            $filterMetadata,
            'id',
            'ASC'
        );
        $this->assertInstanceOf(QueryBuilder::class, $result);
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
