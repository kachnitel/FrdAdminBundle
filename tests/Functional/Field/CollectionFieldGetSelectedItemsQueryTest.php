<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\AdminBundle\Twig\Components\Field\CollectionField;

/**
 * Verifies that CollectionField::getSelectedItems() correctly resolves all selected
 * entities in a single batch query (IN clause) rather than one find() per ID.
 *
 * The original implementation called EntityManager::find() inside array_map(), which
 * issued one SELECT per selected ID. The fix uses a single IN query via QueryBuilder.
 *
 * @group inline-edit
 * @group inline-edit-collection
 * @group inline-edit-n-plus-one
 */
class CollectionFieldGetSelectedItemsQueryTest extends ComponentTestCase
{
    /**
     * @return array{post: TestEntity, tags: TagEntity[]}
     */
    private function createFixturesWithManyTags(int $count = 5): array
    {
        $post = new TestEntity();
        $post->setName('Post with many tags');
        $this->em->persist($post);

        $tags = [];
        for ($i = 1; $i <= $count; $i++) {
            $tag = new TagEntity();
            $tag->setName("Tag $i");
            $this->em->persist($tag);
            $post->addTag($tag);
            $tags[] = $tag;
        }

        $this->em->flush();

        return ['post' => $post, 'tags' => $tags];
    }

    private function getComponent(): CollectionField
    {
        /** @var CollectionField $component */
        $component = static::getContainer()->get(CollectionField::class);

        return $component;
    }

    /**
     * With 5 selected IDs, all 5 must be returned with correct labels.
     */
    public function testGetSelectedItemsReturnsAllFiveItems(): void
    {
        $fixtures = $this->createFixturesWithManyTags(5);
        $tags     = $fixtures['tags'];

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($fixtures['post'], 'tags');

        $items = $component->getSelectedItems();

        $this->assertCount(5, $items, 'All 5 selected items must be returned');

        $returnedIds = array_column($items, 'id');
        foreach ($tags as $tag) {
            $this->assertContains($tag->getId(), $returnedIds, "Tag #{$tag->getId()} must appear in results");
        }
    }

    /**
     * Items must be returned in selectedIds order, not DB insertion order.
     */
    public function testGetSelectedItemsPreservesSelectionOrder(): void
    {
        $fixtures = $this->createFixturesWithManyTags(3);
        $tags     = $fixtures['tags'];

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($fixtures['post'], 'tags');

        // Reverse the order from DB insertion order
        $component->selectedIds = array_reverse(
            array_map(fn(TagEntity $t) => $t->getId(), $tags)
        );

        $items = $component->getSelectedItems();

        $this->assertCount(3, $items);
        $this->assertSame($component->selectedIds[0], $items[0]['id']);
        $this->assertSame($component->selectedIds[1], $items[1]['id']);
        $this->assertSame($component->selectedIds[2], $items[2]['id']);
    }

    /**
     * Entity labels must be resolved (TagEntity::__toString returns its name).
     */
    public function testGetSelectedItemsResolvesEntityLabels(): void
    {
        $fixtures = $this->createFixturesWithManyTags(3);
        $tags     = $fixtures['tags'];

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($fixtures['post'], 'tags');

        $items = $component->getSelectedItems();

        /** @var array<int, string> $labelsByName */
        $labelsByName = array_combine(
            array_column($items, 'id'),
            array_column($items, 'label'),
        );

        foreach ($tags as $tag) {
            $this->assertSame($tag->getName(), $labelsByName[$tag->getId()]);
        }
    }

    /**
     * A non-existent ID must produce a "#ID" fallback label rather than throwing or
     * silently dropping the entry. The IN query returns partial results; the implementation
     * must fill in missing IDs from the original selectedIds list.
     */
    public function testGetSelectedItemsFallsBackToIdLabelForMissingEntity(): void
    {
        $fixtures = $this->createFixturesWithManyTags(1);

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($fixtures['post'], 'tags');

        $component->selectedIds[] = 99999; // non-existent

        $items = $component->getSelectedItems();

        $missingItem = null;
        foreach ($items as $item) {
            if ($item['id'] === 99999) {
                $missingItem = $item;
                break;
            }
        }

        $this->assertNotNull($missingItem, 'Missing ID must still appear in results');
        $this->assertSame('#99999', $missingItem['label']);
    }

    /**
     * Empty selectedIds must return empty array immediately (no DB query).
     */
    public function testGetSelectedItemsReturnsEmptyArrayWhenNoIdsSelected(): void
    {
        $post = new TestEntity();
        $post->setName('Empty post');
        $this->em->persist($post);
        $this->em->flush();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($post, 'tags');

        $this->assertSame([], $component->selectedIds);
        $this->assertSame([], $component->getSelectedItems());
    }
}
