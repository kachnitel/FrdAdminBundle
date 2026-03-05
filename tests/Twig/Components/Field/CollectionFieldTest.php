<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\AdminBundle\Twig\Components\Field\CollectionField;

/**
 * Uses TestEntity (OneToMany → TagEntity) as the owning fixture.
 * TestEntity exposes addTag(TagEntity)/removeTag(TagEntity) which CollectionField
 * resolves via ReflectionExtractor — the same pattern tested by the original
 * InlineEditPostEntity/InlineEditTagEntity fixtures.
 *
 * @covers \Kachnitel\AdminBundle\Twig\Components\Field\CollectionField
 *
 * @group field
 * @group inline-edit
 */
class CollectionFieldTest extends ComponentTestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @return array{post: TestEntity, tag1: TagEntity, tag2: TagEntity, tag3: TagEntity}
     */
    private function createFixtures(): array
    {
        $tag1 = new TagEntity();
        $tag1->setName('Tag 1');
        $tag2 = new TagEntity();
        $tag2->setName('Tag 2');
        $tag3 = new TagEntity();
        $tag3->setName('Tag 3');

        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($tag3);

        $post = new TestEntity();
        $post->setName('Post');
        $post->addTag($tag1);
        $post->addTag($tag2);

        $this->em->persist($post);
        $this->em->flush();

        return [
            'post' => $post,
            'tag1' => $tag1,
            'tag2' => $tag2,
            'tag3' => $tag3,
        ];
    }

    private function getComponent(): CollectionField
    {
        /** @var CollectionField $component */
        $component = static::getContainer()->get(CollectionField::class);

        return $component;
    }

    // ── mount() ───────────────────────────────────────────────────────────────

    public function testMountInitializesSelectedIdsFromCollection(): void
    {
        $fixtures = $this->createFixtures();
        $post = $fixtures['post'];

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($post, 'tags');

        $this->assertCount(2, $component->selectedIds);
        $this->assertContains($fixtures['tag1']->getId(), $component->selectedIds);
        $this->assertContains($fixtures['tag2']->getId(), $component->selectedIds);
    }

    // ── getSelectedItems() ────────────────────────────────────────────────────

    public function testGetSelectedItemsReturnsArrayOfIdAndLabel(): void
    {
        $fixtures = $this->createFixtures();
        $post = $fixtures['post'];

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($post, 'tags');

        $items = $component->getSelectedItems();

        $this->assertCount(2, $items);
        $this->assertSame($fixtures['tag1']->getId(), $items[0]['id']);
        $this->assertArrayHasKey('label', $items[0]);
    }

    public function testGetSelectedItemsReturnsEmptyArrayWhenNoSelectedIds(): void
    {
        $post = new TestEntity();
        $post->setName('Empty');
        $this->em->persist($post);
        $this->em->flush();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($post, 'tags');

        $this->assertSame([], $component->getSelectedItems());
    }

    // ── LiveActions: addItem() & removeItem() ─────────────────────────────────

    public function testAddItemAppendsIdAndClearsSearchQuery(): void
    {
        $fixtures = $this->createFixtures();
        $post = $fixtures['post'];
        $newTag = $fixtures['tag3'];

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($post, 'tags');

        $component->searchQuery = 'Search text...';
        $component->addItem($newTag->getId());

        $this->assertContains($newTag->getId(), $component->selectedIds);
        $this->assertCount(3, $component->selectedIds);
        $this->assertSame('', $component->searchQuery, 'Search query should be cleared after adding an item.');
    }

    public function testAddItemIgnoresDuplicateIds(): void
    {
        $fixtures = $this->createFixtures();
        $post = $fixtures['post'];
        $existingTag = $fixtures['tag1'];

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($post, 'tags');

        $component->addItem($existingTag->getId());

        $this->assertCount(2, $component->selectedIds);
    }

    public function testRemoveItemRemovesIdFromSelection(): void
    {
        $fixtures = $this->createFixtures();
        $post = $fixtures['post'];
        $tagToRemove = $fixtures['tag1'];

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($post, 'tags');

        $component->removeItem($tagToRemove->getId());

        $this->assertCount(1, $component->selectedIds);
        $this->assertNotContains($tagToRemove->getId(), $component->selectedIds);
    }

    // ── LiveAction: save() ────────────────────────────────────────────────────

    public function testSaveAddsAndRemovesEntitiesCorrectly(): void
    {
        $fixtures = $this->createFixtures();
        $post = $fixtures['post'];
        $id = $post->getId();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($post, 'tags');

        // Remove tag1, keep tag2, add tag3
        $component->removeItem($fixtures['tag1']->getId());
        $component->addItem($fixtures['tag3']->getId());

        $component->save();

        $this->em->clear();
        $reloadedPost = $this->em->find(TestEntity::class, $id);

        $this->assertNotNull($reloadedPost);
        $reloadedTags = $reloadedPost->getTags();

        $this->assertCount(2, $reloadedTags);

        $reloadedTagIds = array_map(fn($t) => $t->getId(), $reloadedTags->toArray());

        $this->assertNotContains($fixtures['tag1']->getId(), $reloadedTagIds);
        $this->assertContains($fixtures['tag2']->getId(), $reloadedTagIds);
        $this->assertContains($fixtures['tag3']->getId(), $reloadedTagIds);
    }

    public function testSaveThrowsExceptionIfTargetClassUnresolvable(): void
    {
        $post = new TestEntity();
        $post->setName('Post');
        $this->em->persist($post);
        $this->em->flush();

        $component = $this->getComponent();
        $component->editMode = true;

        // 'name' is a string property, not a Doctrine association
        $component->mount($post, 'name');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not a recognised Doctrine association');

        $component->save();
    }

    // ── LiveAction: cancelEdit() ──────────────────────────────────────────────

    public function testCancelEditResetsSelectedIdsToPersistedState(): void
    {
        $fixtures = $this->createFixtures();
        $post = $fixtures['post'];

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($post, 'tags');

        // Modify the UI state
        $component->removeItem($fixtures['tag1']->getId());
        $component->addItem($fixtures['tag3']->getId());
        $component->searchQuery = 'some query';

        $component->cancelEdit();

        $this->assertFalse($component->editMode);
        $this->assertSame('', $component->searchQuery);
        $this->assertCount(2, $component->selectedIds);
        $this->assertContains($fixtures['tag1']->getId(), $component->selectedIds);
        $this->assertNotContains($fixtures['tag3']->getId(), $component->selectedIds);
    }
}
