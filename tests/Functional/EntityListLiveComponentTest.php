<?php

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Tests\Fixtures\RelatedEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;

class EntityListLiveComponentTest extends ComponentTestCase
{

    public function testInitialRenderAndState(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList', // The AsLiveComponent name
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'], // Required LiveProp
                    );

        $rendered = $testComponent->render();
        $this->assertStringContainsString('<table', $rendered);
        $this->assertStringContainsString('Global search across all columns', $rendered);
        $this->assertStringContainsString('No TestEntity found.', $rendered);

        $component = $testComponent->component();
        $this->assertSame(TestEntity::class, $component->entityClass);
        $this->assertSame('id', $component->sortBy);
    }

    public function testSearchPropUpdatesState(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
            );

        $searchQuery = 'Test Search Value';

        // This triggers a re-render internally, effectively testing the DB connection
        $testComponent->set('search', $searchQuery);

        $component = $testComponent->component();
        $this->assertSame($searchQuery, $component->search);

        $rendered = $testComponent->render();
        $this->assertStringContainsString('value="'.$searchQuery.'"', $rendered);
    }

    public function testLiveActionChangesSortDirection(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity', 'sortBy' => 'name', 'sortDirection' => 'ASC'],
            );

        // Initial check
        $this->assertSame('name', $testComponent->component()->sortBy);
        $this->assertSame('ASC', $testComponent->component()->sortDirection);

        // Set the property directly to simulate the action's result
        $testComponent->set('sortDirection', 'DESC');

        $this->assertSame('DESC', $testComponent->component()->sortDirection);
    }

    public function testRenderingEntityWithRelationDoesNotThrowStringConversionError(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create a related entity
        $related = new RelatedEntity();
        $related->setName('Related Item');
        $related->setEmail('test@example.com');
        $em->persist($related);

        // Create test entity with relation
        $entity = new TestEntity();
        $entity->setName('Test Entity with Relation');
        $entity->setRelatedEntity($related); // Set the relation
        $em->persist($entity);

        $em->flush();
        $em->clear(); // Clear to ensure proxy loading

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
            );

        // This should not throw "Object could not be converted to string" error
        // The key assertion is that render() completes without throwing an exception
        try {
            $rendered = (string) $testComponent->render();
            $this->assertIsString($rendered); // @phpstan-ignore method.alreadyNarrowedType
            // Verify no error messages in output
            $this->assertStringNotContainsString('could not be converted to string', $rendered);

            // Verify the related entity name is rendered
            $this->assertStringContainsString('Related Item', $rendered);

            // Verify the table structure is correct
            $this->assertStringContainsString('<table', $rendered);
            $this->assertStringContainsString('Test Entity with Relation', $rendered);
        } catch (\Throwable $e) {
            $this->fail('Rendering entity with Doctrine proxy relation threw exception: ' . $e->getMessage());
        }
    }

    public function testRenderingEntityWithProxyRelationIncludesAllTemplateElements(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create related entity without __toString method
        $related = new RelatedEntity();
        $related->setName('Proxy Test Related');
        $related->setEmail('proxy@test.com');
        $em->persist($related);

        // Create test entity with relation
        $entity = new TestEntity();
        $entity->setName('Entity with Proxy Relation');
        $entity->setRelatedEntity($related);
        $em->persist($entity);

        $em->flush();
        $em->clear(); // Force Doctrine to use proxies on next access

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
            );

        $rendered = (string) $testComponent->render();

        // Verify related entity is rendered using its 'name' property
        $this->assertStringContainsString('Proxy Test Related', $rendered);

        // Verify no Doctrine proxy class names appear in the output
        $this->assertStringNotContainsString('Proxies\\__CG__', $rendered);
        $this->assertStringNotContainsString('could not be converted', $rendered);

        // The rendered output should contain table cells with entity data
        $this->assertStringContainsString('Entity with Proxy Relation', $rendered);
    }

    public function testRenderingEntityWithCollectionShowsCount(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entity with collection of tags
        $entity = new TestEntity();
        $entity->setName('Entity with Tags');
        $em->persist($entity);

        // Add several tags to the entity
        for ($i = 1; $i <= 3; $i++) {
            $tag = new TagEntity();
            $tag->setName('Tag ' . $i);
            $entity->addTag($tag);
            $em->persist($tag);
        }

        $em->flush();
        $em->clear(); // Clear to ensure proxy loading

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
            );

        $rendered = (string) $testComponent->render();

        // Verify the table has a column header for tags
        $this->assertStringContainsString('Tags', $rendered);

        // Verify the entity is rendered
        $this->assertStringContainsString('Entity with Tags', $rendered);

        // Verify collection count is displayed (not empty cell)
        $this->assertStringContainsString('3 items', $rendered);

        // Verify no errors
        $this->assertStringNotContainsString('could not be converted', $rendered);
    }

    public function testPaginationDefaultsToFirstPage(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create 25 test entities
        for ($i = 1; $i <= 25; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
            );

        $component = $testComponent->component();
        $this->assertSame(1, $component->page);
        $this->assertSame(20, $component->itemsPerPage); // Default

        $entities = $component->getEntities();
        $this->assertCount(20, $entities); // First page should have 20 items

        // Verify pagination info
        $this->assertSame(25, $component->getPaginationInfo()->totalItems);
        $this->assertSame(2, $component->getPaginationInfo()->getTotalPages());
    }

    public function testPaginationNavigationBetweenPages(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create 25 test entities
        for ($i = 1; $i <= 25; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            $em->persist($entity);
        }
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity', 'page' => 1],
            );

        // First page (default sort is ID DESC, so latest entities first)
        $entities = $testComponent->component()->getEntities();
        $this->assertCount(20, $entities);
        $this->assertStringContainsString('Entity 025', $entities[0]->getName()); // Highest ID first

        // Navigate to page 2
        $testComponent->set('page', 2);
        $entities = $testComponent->component()->getEntities();
        $this->assertCount(5, $entities); // Remaining 5 items
        $this->assertStringContainsString('Entity 005', $entities[0]->getName()); // Items 5-1
    }

    public function testPaginationWithCustomItemsPerPage(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create 15 test entities
        for ($i = 1; $i <= 15; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity', 'itemsPerPage' => 5],
            );

        $component = $testComponent->component();
        $this->assertSame(5, $component->itemsPerPage);

        $entities = $component->getEntities();
        $this->assertCount(5, $entities);

        $this->assertSame(15, $component->getPaginationInfo()->totalItems);
        $this->assertSame(3, $component->getPaginationInfo()->getTotalPages());
    }

    public function testPaginationWithSearchFiltersResults(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create 30 test entities, half with "Special" in name
        for ($i = 1; $i <= 30; $i++) {
            $entity = new TestEntity();
            $entity->setName($i % 2 === 0 ? 'Special Entity ' . $i : 'Regular Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'search' => 'Special',
                'itemsPerPage' => 10
            ],
            );

        $component = $testComponent->component();
        $entities = $component->getEntities();

        // Should only get 10 items on first page of filtered results
        $this->assertCount(10, $entities);

        // Total should be 15 (half of 30)
        $this->assertSame(15, $component->getPaginationInfo()->totalItems);
        $this->assertSame(2, $component->getPaginationInfo()->getTotalPages());

        // All entities should contain "Special"
        foreach ($entities as $entity) {
            $this->assertStringContainsString('Special', $entity->getName());
        }
    }

    public function testPaginationWithEmptyResults(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
            );

        $component = $testComponent->component();
        $this->assertSame(0, $component->getPaginationInfo()->totalItems);
        $this->assertSame(0, $component->getPaginationInfo()->getTotalPages());
        $this->assertCount(0, $component->getEntities());
    }

    public function testPaginationRendersPaginationControls(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create 25 test entities to trigger pagination
        for ($i = 1; $i <= 25; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
            );

        $rendered = (string) $testComponent->render();

        // Verify pagination controls are rendered
        $this->assertStringContainsString('Page 1 of 2', $rendered);
        $this->assertStringContainsString('Showing 1-20 of 25', $rendered);
    }

    public function testPaginationInvalidPageDefaultsToOne(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create 10 test entities
        for ($i = 1; $i <= 10; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        // Try to access page 999
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity', 'page' => 999],
            );

        $component = $testComponent->component();
        // Should clamp to valid page
        $this->assertGreaterThanOrEqual(1, $component->page);
        $this->assertLessThanOrEqual($component->getPaginationInfo()->getTotalPages(), $component->page);
    }

    public function testComponentInitializesFromUrlSearchParam(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        for ($i = 1; $i <= 10; $i++) {
            $entity = new TestEntity();
            $entity->setName($i % 2 === 0 ? 'SearchMe ' . $i : 'Other ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        // Initialize component with search parameter
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'search' => 'SearchMe'
            ],
            );

        $component = $testComponent->component();
        $this->assertSame('SearchMe', $component->search);

        $entities = $component->getEntities();
        $this->assertCount(5, $entities);

        // All returned entities should match search
        foreach ($entities as $entity) {
            $this->assertStringContainsString('SearchMe', $entity->getName());
        }
    }

    public function testComponentInitializesFromUrlSortParams(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
            $em->persist($entity);
        }
        $em->flush();

        // Initialize component with sort parameters
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'sortBy' => 'name',
                'sortDirection' => 'ASC'
            ],
            );

        $component = $testComponent->component();
        $this->assertSame('name', $component->sortBy);
        $this->assertSame('ASC', $component->sortDirection);

        $entities = $component->getEntities();
        // Should be sorted by name ASC
        $this->assertStringContainsString('Entity 01', $entities[0]->getName());
        $this->assertStringContainsString('Entity 05', $entities[4]->getName());
    }

    public function testComponentInitializesFromUrlPaginationParams(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create 25 test entities
        for ($i = 1; $i <= 25; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
            $em->persist($entity);
        }
        $em->flush();

        // Initialize component with pagination parameters
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'page' => 2,
                'itemsPerPage' => 10
            ],
            );

        $component = $testComponent->component();
        $this->assertSame(2, $component->page);
        $this->assertSame(10, $component->itemsPerPage);

        $entities = $component->getEntities();
        $this->assertCount(10, $entities); // Second page of 10
        $this->assertSame(25, $component->getPaginationInfo()->totalItems);
        $this->assertSame(3, $component->getPaginationInfo()->getTotalPages());
    }

    public function testComponentInitializesFromUrlColumnFiltersParam(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities with different names
        for ($i = 1; $i <= 10; $i++) {
            $entity = new TestEntity();
            $entity->setName($i <= 5 ? 'Alpha' . $i : 'Beta' . $i);
            $em->persist($entity);
        }
        $em->flush();

        // Initialize component with column filter
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'columnFilters' => ['name' => 'Alpha']
            ]
        , );

        $component = $testComponent->component();
        $this->assertArrayHasKey('name', $component->columnFilters);
        $this->assertSame('Alpha', $component->columnFilters['name']);

        $entities = $component->getEntities();
        $this->assertCount(5, $entities);

        // All entities should have Alpha in name
        foreach ($entities as $entity) {
            $this->assertStringContainsString('Alpha', $entity->getName());
        }
    }

    public function testComponentInitializesFromMultipleUrlParams(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        for ($i = 1; $i <= 30; $i++) {
            $entity = new TestEntity();
            $entity->setName($i % 2 === 0 ? 'Even' . $i : 'Odd' . $i);
            $em->persist($entity);
        }
        $em->flush();

        // Initialize with multiple URL params at once
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'search' => 'Even',
                'sortBy' => 'name',
                'sortDirection' => 'ASC',
                'page' => 1,
                'itemsPerPage' => 5
            ],
            );

        $component = $testComponent->component();

        // Verify all params were set
        $this->assertSame('Even', $component->search);
        $this->assertSame('name', $component->sortBy);
        $this->assertSame('ASC', $component->sortDirection);
        $this->assertSame(1, $component->page);
        $this->assertSame(5, $component->itemsPerPage);

        // Verify filtering works with all params
        $entities = $component->getEntities();
        $this->assertCount(5, $entities); // First page of 5
        $this->assertSame(15, $component->getPaginationInfo()->totalItems); // 15 even numbers
        $this->assertSame(3, $component->getPaginationInfo()->getTotalPages());
    }
}