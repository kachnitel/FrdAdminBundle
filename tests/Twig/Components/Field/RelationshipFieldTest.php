<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\RelatedEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\EntityComponentsBundle\Components\Field\RelationshipField;

/**
 * Functional tests for RelationshipField LiveComponent (now in entity-components-bundle).
 *
 * @group inline-edit
 * @group inline-edit-field
 */
class RelationshipFieldTest extends ComponentTestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @return array{product: TestEntity, cat1: RelatedEntity, cat2: RelatedEntity}
     */
    private function createFixtures(): array
    {
        $cat1 = new RelatedEntity();
        $cat1->setName('Electronics');
        $cat1->setEmail('electronics@example.com');

        $cat2 = new RelatedEntity();
        $cat2->setName('Books');
        $cat2->setEmail('books@example.com');

        $this->em->persist($cat1);
        $this->em->persist($cat2);

        $product = new TestEntity();
        $product->setName('Product');
        $product->setRelatedEntity($cat1);

        $this->em->persist($product);
        $this->em->flush();

        return [
            'product' => $product,
            'cat1' => $cat1,
            'cat2' => $cat2,
        ];
    }

    private function getComponent(): RelationshipField
    {
        /** @var RelationshipField $component */
        $component = static::getContainer()->get(RelationshipField::class);

        return $component;
    }

    // ── mount() ───────────────────────────────────────────────────────────────

    public function testMountInitializesSelectedIdFromAssociation(): void
    {
        $fixtures = $this->createFixtures();
        $product = $fixtures['product'];

        $component = $this->getComponent();
        $component->mount($product, 'relatedEntity');

        $this->assertSame($fixtures['cat1']->getId(), $component->selectedId);
    }

    public function testMountWithNullAssociationSetsSelectedIdNull(): void
    {
        $product = new TestEntity();
        $product->setName('No relation');
        $this->em->persist($product);
        $this->em->flush();

        $component = $this->getComponent();
        $component->mount($product, 'relatedEntity');

        $this->assertNull($component->selectedId);
    }

    // ── getSelectedLabel() ────────────────────────────────────────────────────

    public function testGetSelectedLabelReturnsResolvedName(): void
    {
        $fixtures = $this->createFixtures();
        $component = $this->getComponent();
        $component->mount($fixtures['product'], 'relatedEntity');

        $this->assertSame('Electronics', $component->getSelectedLabel());
    }

    // ── LiveActions: select() & clear() ───────────────────────────────────────

    public function testSelectUpdatesIdAndClearsSearch(): void
    {
        $fixtures = $this->createFixtures();
        $component = $this->getComponent();
        $component->mount($fixtures['product'], 'relatedEntity');

        $component->searchQuery = 'Books';
        $component->select($fixtures['cat2']->getId());

        $this->assertSame($fixtures['cat2']->getId(), $component->selectedId);
        $this->assertSame('', $component->searchQuery);
    }

    public function testClearNullifiesSelectionAndClearsSearch(): void
    {
        $fixtures = $this->createFixtures();
        $component = $this->getComponent();
        $component->mount($fixtures['product'], 'relatedEntity');

        $component->searchQuery = 'Some query';
        $component->clear();

        $this->assertNull($component->selectedId);
        $this->assertSame('', $component->searchQuery);
    }

    // ── LiveAction: save() ────────────────────────────────────────────────────

    public function testSavePersistsNewRelationship(): void
    {
        $fixtures = $this->createFixtures();
        $productId = $fixtures['product']->getId();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($fixtures['product'], 'relatedEntity');

        $component->select($fixtures['cat2']->getId());
        $component->save();

        $this->em->clear();
        $reloadedProduct = $this->em->find(TestEntity::class, $productId);

        $this->assertNotNull($reloadedProduct->getRelatedEntity());
        $this->assertSame($fixtures['cat2']->getId(), $reloadedProduct->getRelatedEntity()->getId());
    }

    // ── LiveAction: cancelEdit() ──────────────────────────────────────────────

    public function testCancelEditRevertsToOriginalId(): void
    {
        $fixtures = $this->createFixtures();
        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($fixtures['product'], 'relatedEntity');

        $component->select($fixtures['cat2']->getId());
        $component->cancelEdit();

        $this->assertFalse($component->editMode);
        $this->assertSame($fixtures['cat1']->getId(), $component->selectedId);
    }
}
