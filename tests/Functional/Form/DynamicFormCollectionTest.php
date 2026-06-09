<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Form;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\Tests\Fixtures\OrderLineItem;
use Kachnitel\AdminBundle\Tests\Fixtures\OrderWithLines;
use Kachnitel\AdminBundle\Tests\Fixtures\TagFixture;
use Kachnitel\AdminBundle\Tests\Functional\TestKernel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

/**
 * Functional tests for DynamicEntityFormType collection support.
 *
 * Uses the real Symfony kernel so we get:
 *   - Real FormFactory (resolves actual form type classes, validates options)
 *   - Real Doctrine ClassMetadata (reads actual entity annotations/attributes)
 *   - Real OptionsResolver chain (DynamicEntityFormType → LiveCollectionType → entry_options)
 *
 * This catches issues that unit tests with mocked ClassMetadata cannot:
 *   - LiveCollectionType rejecting unknown entry_options keys
 *   - Doctrine not recognising an association as collection-valued
 *   - Form submission + Doctrine flush interactions
 *
 * @group dynamic-form
 * @group collections
 * @group functional
 */
class DynamicFormCollectionTest extends KernelTestCase
{
    /** @var array<class-string> */
    private const FIXTURE_CLASSES = [
        TagFixture::class,
        OrderWithLines::class,
        OrderLineItem::class,
    ];

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        // Create tables for our new fixture entities.
        // The TestKernel uses a shared file-based SQLite DB — tables for entities
        // added after the initial schema creation must be added explicitly here.
        // We use updateSchema() rather than createSchema() so this is idempotent
        // and does not interfere with already-existing tables from other fixtures.
        $em         = $this->getEm();
        $schemaTool = new SchemaTool($em);
        $classes    = array_map(
            fn (string $class) => $em->getClassMetadata($class),
            self::FIXTURE_CLASSES,
        );
        $schemaTool->updateSchema($classes);
    }

    protected function tearDown(): void
    {
        // Clean up rows inserted during this test, leaving the schema intact
        // so other tests in the same run are not affected by a dropped table.
        $em = $this->getEm();
        $connection = $em->getConnection();

        $connection->executeStatement('DELETE FROM test_order_tags');
        $connection->executeStatement('DELETE FROM test_order_blocked_tags');
        $connection->executeStatement('DELETE FROM test_order_line_item');
        $connection->executeStatement('DELETE FROM test_order_with_lines');
        $connection->executeStatement('DELETE FROM test_tag_fixture');

        $em->clear();

        parent::tearDown();
    }

    // ── Form creation — no exceptions thrown ──────────────────────────────────

    /**
     * The form factory must be able to build DynamicEntityFormType for an entity
     * with both OneToMany and ManyToMany associations without throwing.
     *
     * This validates that is_root: true flows cleanly through the real OptionsResolver
     * chain (DynamicEntityFormType → LiveCollectionType → entry_options).
     */
    public function testFormCreatesWithoutErrorForEntityWithCollections(): void
    {
        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, new OrderWithLines(), [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        // If we get here without an exception the OptionsResolver chain accepted all options
        $this->assertTrue($form->has('reference'), 'Scalar field must be present');
        $this->assertTrue($form->has('lineItems'), 'OneToMany field must be present in root form');
        $this->assertTrue($form->has('tags'), 'ManyToMany field must be present in root form');
    }

    /**
     * The child form (is_root: false) must not include collection fields.
     * This is the guard against infinite recursion in bidirectional relationships.
     */
    public function testChildFormDoesNotIncludeCollections(): void
    {
        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, new OrderWithLines(), [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => false,
        ]);

        $this->assertTrue($form->has('reference'), 'Scalar fields must still be present in child form');
        $this->assertFalse($form->has('lineItems'), 'OneToMany must be absent in child form');
        $this->assertFalse($form->has('tags'), 'ManyToMany must be absent in child form');
    }

    // ── Correct form types are resolved ───────────────────────────────────────

    /**
     * OneToMany → LiveCollectionType with DynamicEntityFormType as entry_type.
     */
    public function testOneToManyUsesLiveCollectionType(): void
    {
        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, new OrderWithLines(), [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $config = $form->get('lineItems')->getConfig();

        $this->assertInstanceOf(
            LiveCollectionType::class,
            $config->getType()->getInnerType(),
            'OneToMany association must use LiveCollectionType'
        );
    }

    /**
     * ManyToMany → EntityType with multiple: true.
     */
    public function testManyToManyUsesEntityTypeWithMultiple(): void
    {
        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, new OrderWithLines(), [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $config = $form->get('tags')->getConfig();

        $this->assertInstanceOf(
            EntityType::class,
            $config->getType()->getInnerType(),
            'ManyToMany association must use EntityType'
        );
        $this->assertTrue(
            $config->getOption('multiple'),
            'ManyToMany EntityType must have multiple: true'
        );
    }

    // ── editable: false is respected ──────────────────────────────────────────

    /**
     * A ManyToMany collection marked #[AdminColumn(editable: false)] must not
     * appear in the form, even in the root form.
     */
    public function testEditableFalseCollectionIsAbsentFromRootForm(): void
    {
        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, new OrderWithLines(), [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $this->assertFalse(
            $form->has('blockedTags'),
            '#[AdminColumn(editable: false)] collection must be absent from root form'
        );
    }

    /**
     * The inverse ManyToOne on OrderLineItem is marked #[AdminColumn(editable: false)].
     * The child sub-form must not contain an 'order' dropdown — that would create a
     * confusing field pointing back to the parent entity.
     */
    public function testInverseSideIsHiddenViaEditableFalseInChildEntryForm(): void
    {
        // Build a child form directly (simulates what LiveCollectionType does internally
        // for each entry when entry_type is DynamicEntityFormType with is_root: false)
        $childForm = $this->getFormFactory()->create(DynamicEntityFormType::class, new OrderLineItem(), [
            'entity_class'    => OrderLineItem::class,
            'data_class'      => OrderLineItem::class,
            'csrf_protection' => false,
            'is_root'         => false,
        ]);

        $this->assertTrue($childForm->has('description'), 'Scalar field must be present in child form');
        $this->assertTrue($childForm->has('quantity'), 'Scalar field must be present in child form');
        $this->assertFalse(
            $childForm->has('order'),
            'Inverse ManyToOne marked editable:false must be absent from child form'
        );
    }

    // ── Persistence round-trips ───────────────────────────────────────────────

    /**
     * Submitting the form with ManyToMany data must persist the association.
     *
     * Verifies that EntityType resolves tag IDs to managed entities and the
     * flush writes the join table row.
     */
    public function testManyToManyIsPersistedOnSubmit(): void
    {
        $em = $this->getEm();

        $tag1 = (new TagFixture())->setName('php');
        $tag2 = (new TagFixture())->setName('symfony');
        $em->persist($tag1);
        $em->persist($tag2);
        $em->flush();

        $order = new OrderWithLines();
        $order->setReference('ORD-001');

        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, $order, [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $form->submit([
            'reference' => 'ORD-001',
            'tags'      => [(string) $tag1->getId(), (string) $tag2->getId()],
            'lineItems' => [],
        ]);

        $this->assertTrue($form->isValid(), $this->formatErrors($form));

        $em->persist($order);
        $em->flush();
        $em->clear();

        $saved = $em->find(OrderWithLines::class, $order->getId());
        $this->assertNotNull($saved);
        $this->assertCount(2, $saved->getTags(), 'Both tags must be persisted via ManyToMany');
    }

    /**
     * New line items submitted via the form must be persisted through cascade,
     * without requiring an explicit $em->persist($lineItem) call.
     */
    public function testOneToManyNewItemsArePersistedViaCascade(): void
    {
        $em = $this->getEm();

        $order = new OrderWithLines();
        $order->setReference('ORD-002');

        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, $order, [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $form->submit([
            'reference' => 'ORD-002',
            'tags'      => [],
            'lineItems' => [
                ['description' => 'Widget A', 'quantity' => 3],
                ['description' => 'Widget B', 'quantity' => 1],
            ],
        ]);

        $this->assertTrue($form->isValid(), $this->formatErrors($form));

        $em->persist($order);
        $em->flush();
        $em->clear();

        $saved = $em->find(OrderWithLines::class, $order->getId());
        $this->assertNotNull($saved);
        $this->assertCount(2, $saved->getLineItems(), 'Both line items must be persisted via cascade');

        $descriptions = $saved->getLineItems()
            ->map(fn (OrderLineItem $i) => $i->getDescription())
            ->toArray();
        $this->assertContains('Widget A', $descriptions);
        $this->assertContains('Widget B', $descriptions);
    }

    /**
     * Removing all line items from an existing order must delete the child rows
     * via orphanRemoval — no manual $em->remove() required.
     */
    public function testOneToManyRemovedItemsAreDeletedViaOrphanRemoval(): void
    {
        $em = $this->getEm();

        $order = new OrderWithLines();
        $order->setReference('ORD-003');

        $item1 = (new OrderLineItem())->setDescription('To remove A')->setQuantity(1)->setOrder($order);
        $item2 = (new OrderLineItem())->setDescription('To remove B')->setQuantity(2)->setOrder($order);
        $order->addLineItem($item1);
        $order->addLineItem($item2);

        $em->persist($order);
        $em->flush();
        $orderId = $order->getId();
        $em->clear();

        $reloaded = $em->find(OrderWithLines::class, $orderId);
        $this->assertNotNull($reloaded);
        $this->assertCount(2, $reloaded->getLineItems());

        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, $reloaded, [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $form->submit([
            'reference' => 'ORD-003',
            'tags'      => [],
            'lineItems' => [],
        ]);

        $this->assertTrue($form->isValid(), $this->formatErrors($form));

        $em->flush();
        $em->clear();

        $saved = $em->find(OrderWithLines::class, $orderId);
        $this->assertNotNull($saved);
        $this->assertCount(0, $saved->getLineItems(), 'All line items must be removed via orphanRemoval');

        // Verify rows are deleted from DB, not just detached
        $remaining = $em->getRepository(OrderLineItem::class)->findBy(['order' => $orderId]);
        $this->assertCount(0, $remaining, 'Orphaned line items must be deleted from DB');
    }

    /**
     * Clearing all ManyToMany tags on resubmit must remove all join table rows.
     */
    public function testManyToManyTagsCanBeClearedOnResubmit(): void
    {
        $em = $this->getEm();

        $tag = (new TagFixture())->setName('to-remove');
        $em->persist($tag);

        $order = new OrderWithLines();
        $order->setReference('ORD-004');
        $order->setTags(new ArrayCollection([$tag]));
        $em->persist($order);
        $em->flush();
        $orderId = $order->getId();
        $em->clear();

        $reloaded = $em->find(OrderWithLines::class, $orderId);
        $this->assertNotNull($reloaded);
        $this->assertCount(1, $reloaded->getTags());

        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, $reloaded, [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $form->submit([
            'reference' => 'ORD-004',
            'tags'      => [],
            'lineItems' => [],
        ]);

        $this->assertTrue($form->isValid(), $this->formatErrors($form));

        $em->flush();
        $em->clear();

        $saved = $em->find(OrderWithLines::class, $orderId);
        $this->assertNotNull($saved);
        $this->assertCount(0, $saved->getTags(), 'All ManyToMany tags must be cleared');
    }

    /**
     * Submitting a mix of existing and new line items must update correctly —
     * existing items stay, new ones are added via cascade.
     */
    public function testOneToManyMixedUpdateAndAdd(): void
    {
        $em = $this->getEm();

        $order    = new OrderWithLines();
        $order->setReference('ORD-005');
        $existing = (new OrderLineItem())->setDescription('Existing item')->setQuantity(5)->setOrder($order);
        $order->addLineItem($existing);

        $em->persist($order);
        $em->flush();
        $orderId = $order->getId();
        $em->clear();

        $reloaded = $em->find(OrderWithLines::class, $orderId);
        $this->assertNotNull($reloaded);

        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, $reloaded, [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $form->submit([
            'reference' => 'ORD-005',
            'tags'      => [],
            'lineItems' => [
                ['description' => 'Existing item', 'quantity' => 10],
                ['description' => 'New item', 'quantity' => 2],
            ],
        ]);

        $this->assertTrue($form->isValid(), $this->formatErrors($form));

        $em->flush();
        $em->clear();

        $saved = $em->find(OrderWithLines::class, $orderId);
        $this->assertNotNull($saved);
        $this->assertCount(2, $saved->getLineItems());

        $descriptions = array_map(
            fn (OrderLineItem $i) => $i->getDescription(),
            $saved->getLineItems()->toArray()
        );
        $this->assertContains('Existing item', $descriptions);
        $this->assertContains('New item', $descriptions);
    }

    /**
     * The $order property on OrderLineItem is the inverse side of a OneToMany —
     * Doctrine sets mappedBy on it, so DynamicEntityFormType detects and skips it
     * automatically. No #[AdminColumn(editable: false)] is required.
     *
     * Tested both as a standalone child form (is_root: false) and a root form,
     * because the skip is driven by mappedBy detection, not by the is_root flag.
     */
    public function testInverseSideIsHiddenAutomaticallyWithoutAnyAttribute(): void
    {
        // Root form (is_root: true) — inverse side still skipped via mappedBy detection
        $rootForm = $this->getFormFactory()->create(DynamicEntityFormType::class, new OrderLineItem(), [
            'entity_class'    => OrderLineItem::class,
            'data_class'      => OrderLineItem::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $this->assertTrue($rootForm->has('description'), 'Scalar field must be present');
        $this->assertTrue($rootForm->has('quantity'), 'Scalar field must be present');
        $this->assertFalse(
            $rootForm->has('order'),
            'Inverse side (mappedBy set) must be skipped automatically in root form'
        );

        // Child form (is_root: false) — same result
        $childForm = $this->getFormFactory()->create(DynamicEntityFormType::class, new OrderLineItem(), [
            'entity_class'    => OrderLineItem::class,
            'data_class'      => OrderLineItem::class,
            'csrf_protection' => false,
            'is_root'         => false,
        ]);

        $this->assertFalse(
            $childForm->has('order'),
            'Inverse side (mappedBy set) must be skipped automatically in child form too'
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getFormFactory(): FormFactoryInterface
    {
        /** @var FormFactoryInterface */
        return static::getContainer()->get('form.factory');
    }

    private function getEm(): EntityManagerInterface
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = static::getContainer()->get('doctrine');
        /** @var \Doctrine\ORM\EntityManager */
        return $doctrine->getManager();
    }

    /**
     * Format form errors into a readable string for assertion failure messages.
     *
     * @param FormInterface<object> $form
     */
    private function formatErrors(FormInterface $form): string
    {
        return implode(', ', array_map(
            fn (\Symfony\Component\Form\FormError $e) => $e->getMessage(),
            iterator_to_array($form->getErrors(true))
        ));
    }
}
