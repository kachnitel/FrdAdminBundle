<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Tests\Fixtures\RelatedEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;

/**
 * Tests that application-level template overrides work correctly.
 */
class TemplateOverrideTest extends ComponentTestCase
{

    /**
     * Baseline test: Verify bundle templates work without overrides.
     */
    public function testBundleDefaultsWorkWithoutOverrides(): void
    {
        // Use BOTH client (Session) and actingAs (TokenStorage)
        // This covers both initial render (TokenStorage) and subsequent actions (Session)
        $client = self::getContainer()->get("test.client");

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('<table', $rendered);
        $this->assertStringContainsString('Global search across all columns', $rendered);
        $this->assertStringContainsString('No TestEntity found.', $rendered);
    }

    /**
     * Test that admin/index_live.html.twig can be overridden.
     */
    public function testAdminIndexLiveTemplateCanBeOverridden(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        $entity = new TestEntity();
        $entity->setName('Test Entity');
        $em->persist($entity);
        $em->flush();

        $client = self::getContainer()->get("test.client");

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('Test Entity', $rendered);
        $this->assertStringContainsString('<table', $rendered);
    }

    /**
     * Test that components/EntityList.html.twig can be overridden.
     */
    public function testComponentTemplateCanBeOverridden(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        $entity1 = new TestEntity();
        $entity1->setName('Entity One');
        $em->persist($entity1);

        $entity2 = new TestEntity();
        $entity2->setName('Entity Two');
        $em->persist($entity2);

        $em->flush();

        $client = self::getContainer()->get("test.client");

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('<!-- TEST_OVERRIDE:ENTITY_LIST -->', $rendered);
        $this->assertStringContainsString('Entity One', $rendered);
        $this->assertStringContainsString('Entity Two', $rendered);

        // This set() triggers a re-render/action. If TestLiveComponent uses the client
        // to simulate a request.
        $testComponent->set('search', 'One');
        $component = $testComponent->component();
        $this->assertSame('One', $component->search);
    }

    /**
     * Test that types/_preview.html.twig can be overridden.
     */
    public function testTypePreviewTemplateCanBeOverridden(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        $entity = new TestEntity();
        $entity->setName('Test Name');
        $em->persist($entity);
        $em->flush();

        $client = self::getContainer()->get("test.client");

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('<!-- TEST_OVERRIDE:PREVIEW -->', $rendered);
        $this->assertStringContainsString('Test Name', $rendered);
    }

    /**
     * Test that type-specific overrides (boolean/_preview.html.twig) take precedence.
     */
    public function testTypeSpecificOverrideTakesPrecedence(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        $entity = new TestEntity();
        $entity->setName('Active Entity');
        $em->persist($entity);
        $em->flush();

        $client = self::getContainer()->get("test.client");

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('<!-- TEST_OVERRIDE:BOOLEAN -->', $rendered);
        $this->assertStringContainsString('✓ True', $rendered);
        $this->assertStringNotContainsString('>Yes<', $rendered);
    }

    /**
     * Test that the template fallback chain works correctly with partial overrides.
     */
    public function testOverrideFallbackChainWorksCorrectly(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        $related = new RelatedEntity();
        $related->setName('Related Item');
        $related->setEmail('test@example.com');
        $em->persist($related);

        $entity = new TestEntity();
        $entity->setName('Test Entity');
        $entity->setRelatedEntity($related);
        $em->persist($entity);

        for ($i = 1; $i <= 3; $i++) {
            $tag = new TagEntity();
            $tag->setName('Tag ' . $i);
            $entity->addTag($tag);
            $em->persist($tag);
        }

        $em->flush();
        $em->clear();

        $client = self::getContainer()->get("test.client");

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('<!-- TEST_OVERRIDE:PREVIEW -->', $rendered);
        $this->assertStringContainsString('<!-- TEST_OVERRIDE:BOOLEAN -->', $rendered);
        $this->assertStringContainsString('Related Item', $rendered);
        $this->assertStringContainsString('3 items', $rendered);
    }

    /**
     * Test that entity-specific property overrides have highest priority.
     */
    public function testEntitySpecificPropertyOverrideHasHighestPriority(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        $entity = new TestEntity();
        $entity->setName('Test Entity Name');
        $em->persist($entity);
        $em->flush();

        $client = self::getContainer()->get("test.client");

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('<!-- TEST_OVERRIDE:ENTITY_SPECIFIC_PROPERTY -->', $rendered);
        $this->assertStringContainsString('<strong class="entity-specific-name">Test Entity Name</strong>', $rendered);
    }

    /**
     * Test that template overrides don't break LiveComponent actions.
     */
    public function testOverridesPreserveLiveComponentFunctionality(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        // Pass client to handle Session (needed if set() simulates a request)
        $client = self::getContainer()->get("test.client");

        // Chain actingAs() to handle TokenStorage (needed for initial direct render)
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
        );

        $testComponent->set('search', 'Entity 1');
        $entities = $testComponent->component()->getEntities();
        $this->assertCount(1, $entities);
        $this->assertStringContainsString('Entity 1', $entities[0]->getName());

        $testComponent->set('sortBy', 'name');
        $testComponent->set('sortDirection', 'ASC');
        $component = $testComponent->component();
        $this->assertSame('name', $component->sortBy);
        $this->assertSame('ASC', $component->sortDirection);

        $testComponent->set('itemsPerPage', 2);
        $testComponent->set('search', '');
        $component = $testComponent->component();
        $this->assertSame(2, $component->itemsPerPage);
        $this->assertSame(3, $component->getPaginationInfo()->getTotalPages());
    }
}
