<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Tests\Functional\TestKernel;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithCollectionFilter;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithNestedFilter;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Tests\Fixtures\ItemEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\MiddleEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies that dot-notation searchFields produce the correct LEFT JOINs in DQL
 * for both relation and collection filter types.
 *
 * Relation filters:   nested JOINs appear in the outer query.
 * Collection filters: nested JOINs are embedded inside the EXISTS subquery string,
 *                     keeping them scoped to the subquery and avoiding row multiplication.
 *
 * @group nested-filter
 */
final class NestedRelationFilterDqlTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    // ── relation filter ───────────────────────────────────────────────────────

    public function testNestedSearchFieldProducesLeftJoinInDql(): void
    {
        self::bootKernel();

        /** @var EntityListQueryService $queryService */
        $queryService = self::getContainer()->get(EntityListQueryService::class);

        $filterMetadata = [
            'middle' => [
                'type'         => 'relation',
                'operator'     => 'LIKE',
                'searchFields' => ['title', 'deep.label'],
                'targetClass'  => MiddleEntity::class,
            ],
        ];

        $qb = $queryService->buildQuery(
            entityClass: EntityWithNestedFilter::class,
            repositoryMethod: null,
            search: '',
            columnFilters: ['middle' => 'needle'],
            filterMetadata: $filterMetadata,
            sortBy: 'id',
            sortDirection: 'DESC',
        );

        $dql = $qb->getDQL();

        // Should join the primary relation
        $this->assertStringContainsString('LEFT JOIN e.middle', $dql,
            'Primary LEFT JOIN on the filtered relation must be present');

        // Should join the nested relation through the primary alias
        $this->assertStringContainsString('LEFT JOIN rel_middle.deep', $dql,
            'Nested LEFT JOIN through the primary alias must be present for deep.label');

        // The WHERE clause should reference the nested alias field
        $this->assertStringContainsString('rel_middle_deep.label', $dql,
            'WHERE condition must use the nested alias for the deep field');

        // The WHERE clause should also reference the direct field
        $this->assertStringContainsString('rel_middle.title', $dql,
            'WHERE condition must still include the direct field');
    }

    public function testDirectOnlySearchFieldsProduceNoExtraJoins(): void
    {
        self::bootKernel();

        /** @var EntityListQueryService $queryService */
        $queryService = self::getContainer()->get(EntityListQueryService::class);

        $filterMetadata = [
            'middle' => [
                'type'         => 'relation',
                'operator'     => 'LIKE',
                'searchFields' => ['title'],
                'targetClass'  => MiddleEntity::class
            ],
        ];

        $qb = $queryService->buildQuery(
            entityClass: EntityWithNestedFilter::class,
            repositoryMethod: null,
            search: '',
            columnFilters: ['middle' => 'needle'],
            filterMetadata: $filterMetadata,
            sortBy: 'id',
            sortDirection: 'DESC',
        );

        $dql = $qb->getDQL();

        $this->assertStringContainsString('LEFT JOIN e.middle', $dql);

        $this->assertStringNotContainsString('rel_middle.deep', $dql,
            'No nested JOIN should be emitted when searchFields contains only direct fields');
    }

    // ── collection filter ─────────────────────────────────────────────────────

    public function testCollectionNestedSearchFieldProducesExistsWithEmbeddedJoin(): void
    {
        self::bootKernel();

        /** @var EntityListQueryService $queryService */
        $queryService = self::getContainer()->get(EntityListQueryService::class);

        $filterMetadata = [
            'items' => [
                'type'         => 'collection',
                'operator'     => 'LIKE',
                'searchFields' => ['name', 'tag.name'],
                'targetClass'  => ItemEntity::class,
            ],
        ];

        $qb = $queryService->buildQuery(
            entityClass: EntityWithCollectionFilter::class,
            repositoryMethod: null,
            search: '',
            columnFilters: ['items' => 'needle'],
            filterMetadata: $filterMetadata,
            sortBy: 'id',
            sortDirection: 'DESC',
        );

        $dql = $qb->getDQL();

        // The EXISTS subquery must be present
        $this->assertStringContainsString('EXISTS', $dql,
            'Collection filter must use an EXISTS subquery');

        // The MEMBER OF anchor must be present
        $this->assertStringContainsString('MEMBER OF e.items', $dql,
            'EXISTS subquery must anchor via MEMBER OF e.items');

        // The nested JOIN must be embedded inside the EXISTS string, not in the outer query
        $this->assertStringContainsString('LEFT JOIN sub_items.tag sub_items_tag', $dql,
            'Nested LEFT JOIN must be embedded inside the EXISTS subquery for tag.name');

        // The direct field condition must be present
        $this->assertStringContainsString('sub_items.name LIKE', $dql,
            'Direct field condition must be present in EXISTS subquery');

        // The nested alias field condition must be present
        $this->assertStringContainsString('sub_items_tag.name LIKE', $dql,
            'Nested alias field condition must be present in EXISTS subquery');

        // The nested JOIN must NOT appear as an outer-query JOIN (it must stay inside EXISTS)
        $outerDql = preg_replace('/EXISTS\s*\(.*?\)/s', '', $dql);        $this->assertNotNull($outerDql, 'Failed to extract outer DQL for JOIN leakage check');
        $this->assertStringNotContainsString('LEFT JOIN sub_items', $outerDql,
            'Nested collection JOIN must not leak into the outer query');
    }

    public function testCollectionDirectOnlySearchFieldsProduceNoEmbeddedJoin(): void
    {
        self::bootKernel();

        /** @var EntityListQueryService $queryService */
        $queryService = self::getContainer()->get(EntityListQueryService::class);

        $filterMetadata = [
            'items' => [
                'type'         => 'collection',
                'operator'     => 'LIKE',
                'searchFields' => ['name'],
                'targetClass'  => ItemEntity::class,
            ],
        ];

        $qb = $queryService->buildQuery(
            entityClass: EntityWithCollectionFilter::class,
            repositoryMethod: null,
            search: '',
            columnFilters: ['items' => 'needle'],
            filterMetadata: $filterMetadata,
            sortBy: 'id',
            sortDirection: 'DESC',
        );

        $dql = $qb->getDQL();

        $this->assertStringContainsString('EXISTS', $dql);
        $this->assertStringContainsString('sub_items.name LIKE', $dql);

        $this->assertStringNotContainsString('LEFT JOIN sub_items', $dql,
            'No embedded JOIN should appear when searchFields contains only direct fields');
    }

    public function testCollectionSharedIntermediateIsNotJoinedTwice(): void
    {
        self::bootKernel();

        /** @var EntityListQueryService $queryService */
        $queryService = self::getContainer()->get(EntityListQueryService::class);

        // Two fields sharing the same intermediate association ('tag')
        $filterMetadata = [
            'items' => [
                'type'         => 'collection',
                'operator'     => 'LIKE',
                'searchFields' => ['tag.name', 'tag.code'],
                'targetClass'  => ItemEntity::class,
            ],
        ];

        $qb = $queryService->buildQuery(
            entityClass: EntityWithCollectionFilter::class,
            repositoryMethod: null,
            search: '',
            columnFilters: ['items' => 'needle'],
            filterMetadata: $filterMetadata,
            sortBy: 'id',
            sortDirection: 'DESC',
        );

        $dql = $qb->getDQL();

        $joinCount = substr_count($dql, 'LEFT JOIN sub_items.tag sub_items_tag');

        $this->assertSame(1, $joinCount,
            'The shared intermediate JOIN (sub_items.tag) must appear exactly once even when two fields reference it');
    }

    // ── three-level nesting ───────────────────────────────────────────────────

    /**
     * Relation path: entity → middle → deep → source (three hops).
     *
     * Expected outer-query JOINs for 'deep.source.code':
     *   LEFT JOIN e.middle          rel_middle
     *   LEFT JOIN rel_middle.deep   rel_middle_deep
     *   LEFT JOIN rel_middle_deep.source  rel_middle_deep_source
     *
     * WHERE: rel_middle_deep_source.code LIKE :p
     */
    public function testRelationThreeLevelNestedSearchFieldProducesChainedJoins(): void
    {
        self::bootKernel();

        /** @var EntityListQueryService $queryService */
        $queryService = self::getContainer()->get(EntityListQueryService::class);

        $filterMetadata = [
            'middle' => [
                'type'         => 'relation',
                'operator'     => 'LIKE',
                'searchFields' => ['title', 'deep.label', 'deep.source.code'],
                'targetClass'  => MiddleEntity::class,
            ],
        ];

        $qb = $queryService->buildQuery(
            entityClass: EntityWithNestedFilter::class,
            repositoryMethod: null,
            search: '',
            columnFilters: ['middle' => 'needle'],
            filterMetadata: $filterMetadata,
            sortBy: 'id',
            sortDirection: 'DESC',
        );

        $dql = $qb->getDQL();

        // All three outer JOINs must appear
        $this->assertStringContainsString('LEFT JOIN e.middle rel_middle', $dql,
            'Primary JOIN must be present');
        $this->assertStringContainsString('LEFT JOIN rel_middle.deep rel_middle_deep', $dql,
            'Second-level JOIN must be present for deep.source.code');
        $this->assertStringContainsString('LEFT JOIN rel_middle_deep.source rel_middle_deep_source', $dql,
            'Third-level JOIN must be present for the source association');

        // The leaf condition must use the fully-resolved alias
        $this->assertStringContainsString('rel_middle_deep_source.code', $dql,
            'WHERE condition must reference the three-level alias for the leaf field');

        // Earlier levels must still appear in WHERE
        $this->assertStringContainsString('rel_middle.title', $dql);
        $this->assertStringContainsString('rel_middle_deep.label', $dql);
    }

    /**
     * Relation path: shared intermediate across three-level fields.
     *
     * Both 'deep.label' and 'deep.source.code' cross through 'deep'.
     * The JOIN to rel_middle.deep must appear exactly once.
     */
    public function testRelationSharedIntermediateAcrossThreeLevelFieldIsNotJoinedTwice(): void
    {
        self::bootKernel();

        /** @var EntityListQueryService $queryService */
        $queryService = self::getContainer()->get(EntityListQueryService::class);

        $filterMetadata = [
            'middle' => [
                'type'         => 'relation',
                'operator'     => 'LIKE',
                'searchFields' => ['deep.label', 'deep.source.code'],
                'targetClass'  => MiddleEntity::class,
            ],
        ];

        $qb = $queryService->buildQuery(
            entityClass: EntityWithNestedFilter::class,
            repositoryMethod: null,
            search: '',
            columnFilters: ['middle' => 'needle'],
            filterMetadata: $filterMetadata,
            sortBy: 'id',
            sortDirection: 'DESC',
        );

        $dql = $qb->getDQL();

        $this->assertSame(
            1,
            substr_count($dql, 'LEFT JOIN rel_middle.deep rel_middle_deep'),
            'Shared intermediate JOIN (rel_middle.deep) must appear exactly once'
        );

        // Third-level JOIN must still be present
        $this->assertStringContainsString('LEFT JOIN rel_middle_deep.source rel_middle_deep_source', $dql);
    }

    /**
     * Collection path: items → tag → testEntity (three hops).
     *
     * Expected EXISTS body for 'tag.testEntity.name':
     *   LEFT JOIN sub_items.tag             sub_items_tag
     *   LEFT JOIN sub_items_tag.testEntity  sub_items_tag_testEntity
     *   … sub_items_tag_testEntity.name LIKE :p
     */
    public function testCollectionThreeLevelNestedSearchFieldProducesChainedEmbeddedJoins(): void
    {
        self::bootKernel();

        /** @var EntityListQueryService $queryService */
        $queryService = self::getContainer()->get(EntityListQueryService::class);

        $filterMetadata = [
            'items' => [
                'type'         => 'collection',
                'operator'     => 'LIKE',
                'searchFields' => ['name', 'tag.name', 'tag.testEntity.name'],
                'targetClass'  => ItemEntity::class,
            ],
        ];

        $qb = $queryService->buildQuery(
            entityClass: EntityWithCollectionFilter::class,
            repositoryMethod: null,
            search: '',
            columnFilters: ['items' => 'needle'],
            filterMetadata: $filterMetadata,
            sortBy: 'id',
            sortDirection: 'DESC',
        );

        $dql = $qb->getDQL();

        // Both embedded JOINs must be inside the EXISTS string
        $this->assertStringContainsString('LEFT JOIN sub_items.tag sub_items_tag', $dql,
            'First embedded JOIN (sub_items → tag) must be present');
        $this->assertStringContainsString('LEFT JOIN sub_items_tag.testEntity sub_items_tag_testEntity', $dql,
            'Second embedded JOIN (sub_items_tag → testEntity) must be present');

        // Leaf condition must use the fully-resolved alias
        $this->assertStringContainsString('sub_items_tag_testEntity.name LIKE', $dql,
            'WHERE condition must reference the three-level alias for the leaf field');

        // All three conditions must be present
        $this->assertStringContainsString('sub_items.name LIKE', $dql);
        $this->assertStringContainsString('sub_items_tag.name LIKE', $dql);

        // None of the embedded JOINs must leak into the outer query
        $outerDql = preg_replace('/EXISTS\s*\(.*?\)/s', '', $dql);
        $this->assertIsString($outerDql, 'Failed to extract outer DQL for JOIN leakage check');
        $this->assertStringNotContainsString('LEFT JOIN sub_items', $outerDql,
            'No collection JOIN must appear outside the EXISTS subquery');
    }

    /**
     * Collection path: shared intermediate across three-level fields.
     *
     * Both 'tag.name' and 'tag.testEntity.name' cross through 'tag'.
     * The embedded JOIN to sub_items.tag must appear exactly once.
     */
    public function testCollectionSharedIntermediateAcrossThreeLevelFieldIsNotJoinedTwice(): void
    {
        self::bootKernel();

        /** @var EntityListQueryService $queryService */
        $queryService = self::getContainer()->get(EntityListQueryService::class);

        $filterMetadata = [
            'items' => [
                'type'         => 'collection',
                'operator'     => 'LIKE',
                'searchFields' => ['tag.name', 'tag.testEntity.name'],
                'targetClass'  => ItemEntity::class,
            ],
        ];

        $qb = $queryService->buildQuery(
            entityClass: EntityWithCollectionFilter::class,
            repositoryMethod: null,
            search: '',
            columnFilters: ['items' => 'needle'],
            filterMetadata: $filterMetadata,
            sortBy: 'id',
            sortDirection: 'DESC',
        );

        $dql = $qb->getDQL();

        $this->assertSame(
            1,
            substr_count($dql, 'LEFT JOIN sub_items.tag sub_items_tag'),
            'Shared embedded intermediate JOIN (sub_items.tag) must appear exactly once'
        );

        // Third-level JOIN must still be present
        $this->assertStringContainsString('LEFT JOIN sub_items_tag.testEntity sub_items_tag_testEntity', $dql);
    }
}
