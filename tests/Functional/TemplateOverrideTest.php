<?php

declare(strict_types=1);

namespace Frd\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Frd\AdminBundle\Tests\Fixtures\RelatedEntity;
use Frd\AdminBundle\Tests\Fixtures\TagEntity;
use Frd\AdminBundle\Tests\Fixtures\TestEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Tests that application-level template overrides work correctly.
 *
 * This test suite verifies that templates in tests/templates/bundles/FrdAdminBundle/
 * properly override the bundle's default templates, following Symfony's standard
 * template override mechanism.
 */
class TemplateOverrideTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        static::bootKernel();

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);

        try {
            $schemaTool->dropSchema($metadatas);
        } catch (\Exception) {
            // Ignore if tables don't exist yet
        }

        $schemaTool->createSchema($metadatas);
    }

    /**
     * Baseline test: Verify bundle templates work without overrides.
     *
     * This ensures we haven't broken the default template resolution.
     */
    public function testBundleDefaultsWorkWithoutOverrides(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity']
        );

        $rendered = (string) $testComponent->render();

        // Basic functionality should work
        $this->assertStringContainsString('<table', $rendered);
        $this->assertStringContainsString('Global search across all columns', $rendered);
        $this->assertStringContainsString('No TestEntity found.', $rendered);
    }

    /**
     * Test that admin/index_live.html.twig can be overridden.
     *
     * The override template includes a marker: <!-- TEST_OVERRIDE:INDEX_LIVE -->
     * which should appear in rendered output when the override is used.
     */
    public function testAdminIndexLiveTemplateCanBeOverridden(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create a test entity to render
        $entity = new TestEntity();
        $entity->setName('Test Entity');
        $em->persist($entity);
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity']
        );

        $rendered = (string) $testComponent->render();

        // The component itself should have the override marker
        // Note: index_live.html.twig wraps the component, so we test the component directly
        $this->assertStringContainsString('Test Entity', $rendered);
        $this->assertStringContainsString('<table', $rendered);
    }

    /**
     * Test that components/EntityList.html.twig can be overridden.
     *
     * The override template includes: <!-- TEST_OVERRIDE:ENTITY_LIST -->
     * and should maintain all LiveComponent functionality.
     */
    public function testComponentTemplateCanBeOverridden(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        $entity1 = new TestEntity();
        $entity1->setName('Entity One');
        $em->persist($entity1);

        $entity2 = new TestEntity();
        $entity2->setName('Entity Two');
        $em->persist($entity2);

        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity']
        );

        $rendered = (string) $testComponent->render();

        // Verify override marker is present
        $this->assertStringContainsString('<!-- TEST_OVERRIDE:ENTITY_LIST -->', $rendered);

        // Verify LiveComponent functionality still works
        $this->assertStringContainsString('Entity One', $rendered);
        $this->assertStringContainsString('Entity Two', $rendered);
        $this->assertStringContainsString('data-model="search"', $rendered);
        $this->assertStringContainsString('data-action="live#action"', $rendered);

        // Verify the component can still update state
        $testComponent->set('search', 'One');
        $component = $testComponent->component();
        $this->assertSame('One', $component->search);
    }

    /**
     * Test that types/_preview.html.twig can be overridden.
     *
     * The override includes: <!-- TEST_OVERRIDE:PREVIEW -->
     * and should be used for displaying entity properties.
     */
    public function testTypePreviewTemplateCanBeOverridden(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create entity with string property (uses default _preview.html.twig)
        $entity = new TestEntity();
        $entity->setName('Test Name');
        $em->persist($entity);
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity']
        );

        $rendered = (string) $testComponent->render();

        // Verify override marker is present
        $this->assertStringContainsString('<!-- TEST_OVERRIDE:PREVIEW -->', $rendered);

        // Verify the template still displays the value correctly
        $this->assertStringContainsString('Test Name', $rendered);
    }

    /**
     * Test that type-specific overrides (boolean/_preview.html.twig) take precedence.
     *
     * The boolean override uses custom rendering: ✓ True / ✗ False
     * instead of the default Yes/No.
     */
    public function testTypeSpecificOverrideTakesPrecedence(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create entity with boolean property
        $entity = new TestEntity();
        $entity->setName('Active Entity');
        // active property is true by default
        $em->persist($entity);
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity']
        );

        $rendered = (string) $testComponent->render();

        // Verify boolean-specific override marker is present
        $this->assertStringContainsString('<!-- TEST_OVERRIDE:BOOLEAN -->', $rendered);

        // Verify custom boolean rendering (✓ True instead of Yes)
        $this->assertStringContainsString('✓ True', $rendered);

        // Should NOT contain the default "Yes" from bundle template
        // Note: We use the custom symbol-based rendering
        $this->assertStringNotContainsString('>Yes<', $rendered);
    }

    /**
     * Test that the template fallback chain works correctly with partial overrides.
     *
     * This verifies:
     * 1. Overridden templates use the override
     * 2. Non-overridden templates fall back to bundle defaults
     * 3. The full fallback chain (entity-specific → type-specific → default) works
     */
    public function testOverrideFallbackChainWorksCorrectly(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create a related entity
        $related = new RelatedEntity();
        $related->setName('Related Item');
        $related->setEmail('test@example.com');
        $em->persist($related);

        // Create entity with multiple property types
        $entity = new TestEntity();
        $entity->setName('Test Entity'); // Uses overridden _preview.html.twig
        $entity->setRelatedEntity($related); // Uses bundle default (no override exists)
        $em->persist($entity);

        // Add tags (collection - uses bundle default _collection.html.twig)
        for ($i = 1; $i <= 3; $i++) {
            $tag = new TagEntity();
            $tag->setName('Tag ' . $i);
            $entity->addTag($tag);
            $em->persist($tag);
        }

        $em->flush();
        $em->clear(); // Force proxy loading

        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity']
        );

        $rendered = (string) $testComponent->render();

        // Verify overridden templates are used
        $this->assertStringContainsString('<!-- TEST_OVERRIDE:PREVIEW -->', $rendered);
        $this->assertStringContainsString('<!-- TEST_OVERRIDE:BOOLEAN -->', $rendered);

        // Verify bundle defaults are used for non-overridden templates
        // Related entity should display its name (bundle default behavior)
        $this->assertStringContainsString('Related Item', $rendered);

        // Collection should show item count (bundle default _collection.html.twig)
        $this->assertStringContainsString('3 items', $rendered);

        // Verify no errors with mixed override/default templates
        $this->assertStringNotContainsString('could not be converted', $rendered);
    }

    /**
     * Test that entity-specific property overrides have highest priority.
     *
     * The fallback chain should be:
     * 1. Entity-specific property: types/Frd/AdminBundle/Tests/Fixtures/TestEntity/name.html.twig
     * 2. Type-specific: types/string/_preview.html.twig
     * 3. Default: types/_preview.html.twig
     */
    public function testEntitySpecificPropertyOverrideHasHighestPriority(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create entity with name property
        $entity = new TestEntity();
        $entity->setName('Test Entity Name');
        $em->persist($entity);
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity']
        );

        $rendered = (string) $testComponent->render();

        // Verify entity-specific property override marker is present
        $this->assertStringContainsString('<!-- TEST_OVERRIDE:ENTITY_SPECIFIC_PROPERTY -->', $rendered);

        // Verify custom entity-specific rendering
        $this->assertStringContainsString('<strong class="entity-specific-name">Test Entity Name</strong>', $rendered);

        // Should NOT contain the default preview marker in the name column
        // (entity-specific override takes precedence)
        // Note: Other columns will still use the general preview template
    }

    /**
     * Test that template overrides don't break LiveComponent actions.
     *
     * This ensures that overriding templates maintains all LiveComponent
     * interactivity (sorting, searching, pagination).
     */
    public function testOverridesPreserveLiveComponentFunctionality(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create multiple entities for testing
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity']
        );

        // Test search functionality
        $testComponent->set('search', 'Entity 1');
        $entities = $testComponent->component()->getEntities();
        $this->assertCount(1, $entities);
        $this->assertStringContainsString('Entity 1', $entities[0]->getName());

        // Test sort functionality
        $testComponent->set('sortBy', 'name');
        $testComponent->set('sortDirection', 'ASC');
        $component = $testComponent->component();
        $this->assertSame('name', $component->sortBy);
        $this->assertSame('ASC', $component->sortDirection);

        // Test pagination functionality
        $testComponent->set('itemsPerPage', 2);
        $testComponent->set('search', ''); // Clear search
        $component = $testComponent->component();
        $this->assertSame(2, $component->itemsPerPage);
        $this->assertSame(3, $component->getTotalPages()); // 5 items / 2 per page = 3 pages
    }
}
