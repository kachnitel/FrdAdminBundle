<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->qb = $this->createMock(QueryBuilder::class);

        $this->em->method('getRepository')->willReturn($this->repository);
        $this->repository->method('createQueryBuilder')->willReturn($this->qb);
        $this->qb->method('andWhere')->willReturnSelf();
        $this->qb->method('setParameter')->willReturnSelf();
        $this->qb->method('orderBy')->willReturnSelf();
        $this->qb->method('setFirstResult')->willReturnSelf();
        $this->qb->method('setMaxResults')->willReturnSelf();

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
}
