<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\DeepEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithCollectionFilter;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithNestedFilter;
use Kachnitel\AdminBundle\Tests\Fixtures\ItemEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\MiddleEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\SourceEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;

/**
 * Tests filtering via dot-notation search fields for both relation and collection types:
 *   relation:   #[ColumnFilter(searchFields: ['title', 'deep.label', 'deep.source.code'])]
 *   collection: #[ColumnFilter(type: 'collection', searchFields: ['name', 'tag.label', 'tag.group.name'], ...)]
 *
 * @group nested-filter
 */
class FilterNestedRelationTest extends ComponentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->em = self::getContainer()->get('doctrine')->getManager();
    }

    // ── relation helpers ──────────────────────────────────────────────────────

    private function createEntity(string $name, ?MiddleEntity $middle = null): EntityWithNestedFilter
    {
        $e = new EntityWithNestedFilter();
        $e->setName($name);
        $e->setMiddle($middle);
        $this->em->persist($e);
        return $e;
    }

    private function createMiddle(string $title, ?DeepEntity $deep = null): MiddleEntity
    {
        $m = new MiddleEntity();
        $m->setTitle($title);
        $m->setDeep($deep);
        $this->em->persist($m);
        return $m;
    }

    private function createDeep(string $label): DeepEntity
    {
        $d = new DeepEntity();
        $d->setLabel($label);
        $this->em->persist($d);
        return $d;
    }

    // ── collection helpers ────────────────────────────────────────────────────

    private function createCollectionEntity(string $name, ItemEntity ...$items): EntityWithCollectionFilter
    {
        $e = new EntityWithCollectionFilter();
        $e->setName($name);
        foreach ($items as $item) {
            $e->addItem($item);
        }
        $this->em->persist($e);
        return $e;
    }

    private function createItem(string $name, ?TagEntity $tag = null): ItemEntity
    {
        $i = new ItemEntity();
        $i->setName($name);
        $i->setTag($tag);
        $this->em->persist($i);
        return $i;
    }

    private function createTag(string $name, ?TestEntity $testEntity = null): TagEntity
    {
        $t = new TagEntity();
        $t->setName($name);
        $t->setTestEntity($testEntity);
        $this->em->persist($t);
        return $t;
    }

    // ── filter metadata validation ────────────────────────────────────────────

    public function testNestedSearchFieldSurvivesValidation(): void
    {
        $filterProvider = self::getContainer()->get(
            'Kachnitel\AdminBundle\Service\FilterMetadataProvider'
        );

        $filters = $filterProvider->getFilters(EntityWithNestedFilter::class);

        $this->assertArrayHasKey('middle', $filters, 'middle column must have a filter');
        $this->assertContains('title', $filters['middle']['searchFields'],
            'Direct field "title" must be in searchFields');
        $this->assertContains('deep.label', $filters['middle']['searchFields'],
            'Nested field "deep.label" must survive validation and be in searchFields');
    }

    public function testCollectionNestedSearchFieldSurvivesValidation(): void
    {
        $filterProvider = self::getContainer()->get(
            'Kachnitel\AdminBundle\Service\FilterMetadataProvider'
        );

        $filters = $filterProvider->getFilters(EntityWithCollectionFilter::class);

        $this->assertArrayHasKey('items', $filters, 'items column must have a filter');
        $this->assertContains('name', $filters['items']['searchFields'],
            'Direct field "name" must be in searchFields');
        $this->assertContains('tag.name', $filters['items']['searchFields'],
            'Nested field "tag.name" must survive validation and be in searchFields');
    }

    // ── relation: direct field filtering ─────────────────────────────────────

    public function testFilterByDirectSearchField(): void
    {
        $deep = $this->createDeep('ignored-deep');
        $matchMiddle = $this->createMiddle('acme-title', $deep);
        $otherMiddle = $this->createMiddle('other-title', $deep);

        $this->createEntity('Alpha', $matchMiddle);
        $this->createEntity('Beta', $otherMiddle);
        $this->createEntity('Gamma', null);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithNestedFilter::class,
                'entityShortClass' => 'EntityWithNestedFilter',
                'columnFilters'    => ['middle' => 'acme'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered);
        $this->assertStringNotContainsString('Beta', $rendered);
        $this->assertStringNotContainsString('Gamma', $rendered);
    }

    // ── relation: nested field filtering ─────────────────────────────────────

    public function testFilterByNestedDotNotationField(): void
    {
        $matchDeep  = $this->createDeep('zephyr-label');
        $otherDeep  = $this->createDeep('nothing-special');

        $matchMiddle = $this->createMiddle('some-title', $matchDeep);
        $otherMiddle = $this->createMiddle('some-title', $otherDeep);

        $this->createEntity('Alpha', $matchMiddle);
        $this->createEntity('Beta', $otherMiddle);
        $this->createEntity('Gamma', null);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithNestedFilter::class,
                'entityShortClass' => 'EntityWithNestedFilter',
                'columnFilters'    => ['middle' => 'zephyr'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered);
        $this->assertStringNotContainsString('Beta', $rendered);
        $this->assertStringNotContainsString('Gamma', $rendered);
    }

    public function testFilterMatchesEitherDirectOrNestedField(): void
    {
        $deepA = $this->createDeep('unique-nested-xyz');
        $deepB = $this->createDeep('other-deep');

        $middleA = $this->createMiddle('unique-direct-xyz', $deepB); // matches by title
        $middleB = $this->createMiddle('plain-title', $deepA);       // matches by deep.label
        $middleC = $this->createMiddle('plain-title', $deepB);       // no match

        $this->createEntity('Alpha', $middleA);
        $this->createEntity('Beta', $middleB);
        $this->createEntity('Gamma', $middleC);
        $this->createEntity('Delta', null);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithNestedFilter::class,
                'entityShortClass' => 'EntityWithNestedFilter',
                'columnFilters'    => ['middle' => 'unique'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered, 'Alpha matches via direct title field');
        $this->assertStringContainsString('Beta', $rendered, 'Beta matches via nested deep.label field');
        $this->assertStringNotContainsString('Gamma', $rendered, 'Gamma matches neither');
        $this->assertStringNotContainsString('Delta', $rendered, 'Delta has no middle relation');
    }

    public function testNoResultsWhenNothingMatchesNestedField(): void
    {
        $deep   = $this->createDeep('specific-label');
        $middle = $this->createMiddle('specific-title', $deep);
        $this->createEntity('Alpha', $middle);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithNestedFilter::class,
                'entityShortClass' => 'EntityWithNestedFilter',
                'columnFilters'    => ['middle' => 'absolutely-no-match'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringNotContainsString('Alpha', $rendered);
    }

    // ── collection: direct field filtering ───────────────────────────────────

    public function testCollectionFilterByDirectField(): void
    {
        $tag = $this->createTag('ignored-tag');

        $matchItem = $this->createItem('acme-widget', $tag);
        $otherItem = $this->createItem('other-widget', $tag);

        $this->createCollectionEntity('Alpha', $matchItem);
        $this->createCollectionEntity('Beta', $otherItem);
        $this->createCollectionEntity('Gamma');
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithCollectionFilter::class,
                'entityShortClass' => 'EntityWithCollectionFilter',
                'columnFilters'    => ['items' => 'acme'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered);
        $this->assertStringNotContainsString('Beta', $rendered);
        $this->assertStringNotContainsString('Gamma', $rendered);
    }

    // ── collection: nested field filtering ───────────────────────────────────

    public function testCollectionFilterByNestedDotNotationField(): void
    {
        $matchTag = $this->createTag('zephyr-label');
        $otherTag = $this->createTag('nothing-special');

        $matchItem = $this->createItem('some-widget', $matchTag);
        $otherItem = $this->createItem('some-widget', $otherTag);

        $this->createCollectionEntity('Alpha', $matchItem);
        $this->createCollectionEntity('Beta', $otherItem);
        $this->createCollectionEntity('Gamma');
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithCollectionFilter::class,
                'entityShortClass' => 'EntityWithCollectionFilter',
                'columnFilters'    => ['items' => 'zephyr'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered);
        $this->assertStringNotContainsString('Beta', $rendered);
        $this->assertStringNotContainsString('Gamma', $rendered);
    }

    public function testCollectionFilterMatchesEitherDirectOrNestedField(): void
    {
        $tagA = $this->createTag('unique-nested-xyz');
        $tagB = $this->createTag('other-tag');

        $itemA = $this->createItem('unique-direct-xyz', $tagB); // matches by name
        $itemB = $this->createItem('plain-widget', $tagA);      // matches by tag.label
        $itemC = $this->createItem('plain-widget', $tagB);      // no match

        $this->createCollectionEntity('Alpha', $itemA);
        $this->createCollectionEntity('Beta', $itemB);
        $this->createCollectionEntity('Gamma', $itemC);
        $this->createCollectionEntity('Delta');                  // empty collection
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithCollectionFilter::class,
                'entityShortClass' => 'EntityWithCollectionFilter',
                'columnFilters'    => ['items' => 'unique'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered, 'Alpha matches via direct item name');
        $this->assertStringContainsString('Beta', $rendered, 'Beta matches via nested tag.label');
        $this->assertStringNotContainsString('Gamma', $rendered, 'Gamma matches neither');
        $this->assertStringNotContainsString('Delta', $rendered, 'Delta has an empty collection');
    }

    public function testCollectionFilterMatchesAcrossMultipleItems(): void
    {
        $tag = $this->createTag('other-tag');

        // Alpha has two items; the match is on the second one
        $itemA1 = $this->createItem('no-match-widget', $tag);
        $itemA2 = $this->createItem('needle-widget', $tag);

        $this->createCollectionEntity('Alpha', $itemA1, $itemA2);
        $this->createCollectionEntity('Beta', $itemA1); // only the non-matching item
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithCollectionFilter::class,
                'entityShortClass' => 'EntityWithCollectionFilter',
                'columnFilters'    => ['items' => 'needle'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered,
            'Alpha must match because one of its items contains the needle');
        $this->assertStringNotContainsString('Beta', $rendered,
            'Beta must not match because none of its items contain the needle');
    }

    public function testCollectionNoResultsWhenNothingMatches(): void
    {
        $tag  = $this->createTag('specific-label');
        $item = $this->createItem('specific-name', $tag);
        $this->createCollectionEntity('Alpha', $item);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithCollectionFilter::class,
                'entityShortClass' => 'EntityWithCollectionFilter',
                'columnFilters'    => ['items' => 'absolutely-no-match'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringNotContainsString('Alpha', $rendered);
    }

    public function testCollectionFilterByNestedFieldAcrossSharedTag(): void
    {
        // One tag is shared between two items owned by different entities.
        // Only the entity whose item has the matching tag should appear.
        $matchTag = $this->createTag('rare-xyz-label');
        $otherTag = $this->createTag('common-label');

        $matchItem = $this->createItem('widget', $matchTag);
        $otherItem = $this->createItem('widget', $otherTag); // same item name, different tag

        $this->createCollectionEntity('Alpha', $matchItem);
        $this->createCollectionEntity('Beta', $otherItem);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithCollectionFilter::class,
                'entityShortClass' => 'EntityWithCollectionFilter',
                'columnFilters'    => ['items' => 'rare-xyz'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered,
            'Alpha must match because its item\'s tag contains the needle');
        $this->assertStringNotContainsString('Beta', $rendered,
            'Beta must not match even though its item name is identical');
    }

    // ── three-level nesting helpers ───────────────────────────────────────────

    private function createTestEntity(string $name): TestEntity
    {
        $e = new TestEntity();
        $e->setName($name);
        $this->em->persist($e);
        return $e;
    }

    private function createSource(string $code): SourceEntity
    {
        $s = new SourceEntity();
        $s->setCode($code);
        $this->em->persist($s);
        return $s;
    }

    // ── relation: three-level nesting ─────────────────────────────────────────

    /**
     * Chain: entity → middle → deep → source
     * Needle is buried three hops away in deep.source.code.
     */
    public function testRelationFilterByThreeLevelNestedField(): void
    {
        $matchSource = $this->createSource('zephyr-source-code');
        $otherSource = $this->createSource('nothing-special');

        $matchDeep = $this->createDeep('some-label');
        $matchDeep->setSource($matchSource);

        $otherDeep = $this->createDeep('some-label');
        $otherDeep->setSource($otherSource);

        $matchMiddle = $this->createMiddle('some-title', $matchDeep);
        $otherMiddle = $this->createMiddle('some-title', $otherDeep);

        $this->createEntity('Alpha', $matchMiddle);
        $this->createEntity('Beta', $otherMiddle);
        $this->createEntity('Gamma', null);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithNestedFilter::class,
                'entityShortClass' => 'EntityWithNestedFilter',
                'columnFilters'    => ['middle' => 'zephyr'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered,
            'Alpha must match via deep.source.code three levels deep');
        $this->assertStringNotContainsString('Beta', $rendered);
        $this->assertStringNotContainsString('Gamma', $rendered);
    }

    /**
     * The OR logic spans all three levels: direct title, two-level deep.label,
     * and three-level deep.source.code. Each should independently match.
     */
    public function testRelationFilterMatchesAcrossAllThreeLevels(): void
    {
        $sourceA = $this->createSource('unique-source-xyz');
        $sourceB = $this->createSource('other-source');

        $deepA = $this->createDeep('unique-deep-xyz');  // matches by label
        $deepA->setSource($sourceB);

        $deepB = $this->createDeep('plain-label');      // matches by source.code
        $deepB->setSource($sourceA);

        $deepC = $this->createDeep('plain-label');      // no match
        $deepC->setSource($sourceB);

        $middleA = $this->createMiddle('unique-title-xyz', $deepC); // matches by title
        $middleB = $this->createMiddle('plain-title', $deepA);      // matches by deep.label
        $middleC = $this->createMiddle('plain-title', $deepB);      // matches by deep.source.code
        $middleD = $this->createMiddle('plain-title', $deepC);      // no match

        $this->createEntity('Alpha', $middleA);
        $this->createEntity('Beta', $middleB);
        $this->createEntity('Gamma', $middleC);
        $this->createEntity('Delta', $middleD);
        $this->createEntity('Epsilon', null);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithNestedFilter::class,
                'entityShortClass' => 'EntityWithNestedFilter',
                'columnFilters'    => ['middle' => 'unique'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered, 'Alpha matches via direct middle.title');
        $this->assertStringContainsString('Beta', $rendered, 'Beta matches via deep.label');
        $this->assertStringContainsString('Gamma', $rendered, 'Gamma matches via deep.source.code');
        $this->assertStringNotContainsString('Delta', $rendered, 'Delta matches nothing');
        $this->assertStringNotContainsString('Epsilon', $rendered, 'Epsilon has no relation');
    }

    // ── collection: three-level nesting ───────────────────────────────────────

    /**
     * Chain: entity → items → tag → testEntity
     * Needle is buried three hops away in tag.testEntity.name.
     */
    public function testCollectionFilterByThreeLevelNestedField(): void
    {
        $matchTestEntity = $this->createTestEntity('zephyr-group');
        $otherTestEntity = $this->createTestEntity('nothing-special');

        $matchTag = $this->createTag('some-name', $matchTestEntity);
        $otherTag = $this->createTag('some-name', $otherTestEntity);

        $matchItem = $this->createItem('some-widget', $matchTag);
        $otherItem = $this->createItem('some-widget', $otherTag);

        $this->createCollectionEntity('Alpha', $matchItem);
        $this->createCollectionEntity('Beta', $otherItem);
        $this->createCollectionEntity('Gamma');
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithCollectionFilter::class,
                'entityShortClass' => 'EntityWithCollectionFilter',
                'columnFilters'    => ['items' => 'zephyr'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered,
            'Alpha must match via tag.testEntity.name three levels deep');
        $this->assertStringNotContainsString('Beta', $rendered);
        $this->assertStringNotContainsString('Gamma', $rendered);
    }

    /**
     * The OR logic spans all three levels: direct item.name, two-level tag.name,
     * and three-level tag.testEntity.name. Each should independently match.
     */
    public function testCollectionFilterMatchesAcrossAllThreeLevels(): void
    {
        $matchTestEntity = $this->createTestEntity('unique-group-xyz');
        $otherTestEntity = $this->createTestEntity('other-group');

        $tagA = $this->createTag('unique-tag-xyz', $otherTestEntity); // matches by tag.name
        $tagB = $this->createTag('plain-name', $matchTestEntity);     // matches by tag.testEntity.name
        $tagC = $this->createTag('plain-name', $otherTestEntity);     // no match

        $itemA = $this->createItem('unique-item-xyz', $tagC); // matches by item.name
        $itemB = $this->createItem('plain-widget', $tagA);    // matches by tag.name
        $itemC = $this->createItem('plain-widget', $tagB);    // matches by tag.testEntity.name
        $itemD = $this->createItem('plain-widget', $tagC);    // no match

        $this->createCollectionEntity('Alpha', $itemA);
        $this->createCollectionEntity('Beta', $itemB);
        $this->createCollectionEntity('Gamma', $itemC);
        $this->createCollectionEntity('Delta', $itemD);
        $this->createCollectionEntity('Epsilon');              // empty collection
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithCollectionFilter::class,
                'entityShortClass' => 'EntityWithCollectionFilter',
                'columnFilters'    => ['items' => 'unique'],
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alpha', $rendered, 'Alpha matches via direct item.name');
        $this->assertStringContainsString('Beta', $rendered, 'Beta matches via tag.name');
        $this->assertStringContainsString('Gamma', $rendered, 'Gamma matches via tag.testEntity.name');
        $this->assertStringNotContainsString('Delta', $rendered, 'Delta matches nothing');
        $this->assertStringNotContainsString('Epsilon', $rendered, 'Epsilon has an empty collection');
    }
}
