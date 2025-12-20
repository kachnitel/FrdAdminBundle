<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithoutBatchActions;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class EntityListBatchActionsTest extends ComponentTestCase
{
    public function testBatchActionsUIRendersWhenEnabled(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        for ($i = 1; $i <= 5; $i++) {
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

        // Verify batch actions UI elements are present
        $this->assertStringContainsString('Select All', $rendered);
        $this->assertStringContainsString('Deselect All', $rendered);
        $this->assertStringContainsString('Delete Selected', $rendered);
        $this->assertStringContainsString('data-controller="batch-select"', $rendered);
        $this->assertStringContainsString('data-batch-select-target="checkbox"', $rendered);
    }

    public function testBatchActionsCheckboxesRenderedForEachEntity(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create 3 test entities
        for ($i = 1; $i <= 3; $i++) {
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

        // Count checkbox inputs (excluding the header "select all" checkbox)
        $checkboxCount = substr_count($rendered, 'data-batch-select-target="checkbox"');
        $this->assertSame(3, $checkboxCount, 'Should have one checkbox per entity');
    }

    public function testSelectedIdsLivePropTrackSelection(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'selectedIds' => [1, 2, 3],
            ],
        );

        $component = $testComponent->component();
        $this->assertSame([1, 2, 3], $component->selectedIds);
    }

    public function testBatchDeleteRemovesSelectedEntities(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        $entityIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
            $em->flush(); // Flush to get ID
            $entityIds[] = $entity->getId();
        }

        $em->clear();

        // Verify all entities exist
        $this->assertSame(5, $em->getRepository(TestEntity::class)->count([]));

        // Create component with selected IDs for deletion
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'selectedIds' => [$entityIds[0], $entityIds[1], $entityIds[2]],
            ],
        );

        // Execute batch delete action
        $testComponent->call('batchDelete');

        $em->clear();

        // Verify only 2 entities remain
        $this->assertSame(2, $em->getRepository(TestEntity::class)->count([]));

        // Verify the correct entities were deleted
        $this->assertNull($em->find(TestEntity::class, $entityIds[0]));
        $this->assertNull($em->find(TestEntity::class, $entityIds[1]));
        $this->assertNull($em->find(TestEntity::class, $entityIds[2]));

        // Verify remaining entities still exist
        $this->assertNotNull($em->find(TestEntity::class, $entityIds[3]));
        $this->assertNotNull($em->find(TestEntity::class, $entityIds[4]));
    }

    public function testBatchDeleteClearsSelectionAfterDeletion(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        $entityIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
            $em->flush();
            $entityIds[] = $entity->getId();
        }

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'selectedIds' => $entityIds,
            ],
        );

        // Execute batch delete
        $testComponent->call('batchDelete');

        $component = $testComponent->component();
        $this->assertEmpty($component->selectedIds, 'selectedIds should be cleared after batch delete');
    }

    public function testBatchDeleteWithEmptySelectionDoesNothing(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        for ($i = 1; $i <= 3; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $initialCount = $em->getRepository(TestEntity::class)->count([]);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'selectedIds' => [],
            ],
        );

        // Execute batch delete with empty selection
        $testComponent->call('batchDelete');

        $em->clear();

        // Verify no entities were deleted
        $this->assertSame($initialCount, $em->getRepository(TestEntity::class)->count([]));
    }

    public function testBatchDeleteHandlesNonExistentIds(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create one test entity
        $entity = new TestEntity();
        $entity->setName('Entity 1');
        $em->persist($entity);
        $em->flush();
        $validId = $entity->getId();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'selectedIds' => [$validId, 999, 1000], // Mix of valid and invalid IDs
            ],
        );

        // Should not throw exception
        $testComponent->call('batchDelete');

        $em->clear();

        // Verify the valid entity was deleted
        $this->assertNull($em->find(TestEntity::class, $validId));
    }

    public function testSelectAllActionAddsCurrentPageEntitiesToSelection(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        $entityIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
            $em->flush();
            $entityIds[] = $entity->getId();
        }

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        // Execute selectAll action
        $testComponent->call('selectAll');

        $component = $testComponent->component();

        // Verify all entity IDs are selected
        $this->assertCount(5, $component->selectedIds);
        foreach ($entityIds as $id) {
            $this->assertContains($id, $component->selectedIds);
        }
    }

    public function testDeselectAllActionClearsSelection(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        for ($i = 1; $i <= 3; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'selectedIds' => [1, 2, 3],
            ],
        );

        // Execute deselectAll action
        $testComponent->call('deselectAll');

        $component = $testComponent->component();
        $this->assertEmpty($component->selectedIds);
    }

    public function testCanBatchDeleteReturnsTrueWhenPermitted(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
        );

        $component = $testComponent->component();
        $this->assertTrue($component->canBatchDelete());
    }

    public function testIsBatchActionsEnabledReturnsFalseByDefault(): void
    {
        // Use EntityWithoutBatchActions which doesn't have enableBatchActions set (defaults to false)
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => EntityWithoutBatchActions::class, 'entityShortClass' => 'EntityWithoutBatchActions'],
        );

        $component = $testComponent->component();
        $this->assertFalse($component->isBatchActionsEnabled());
    }

    public function testBatchDeleteUpdatesTotalItemsCount(): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Create test entities
        $entityIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
            $em->flush();
            $entityIds[] = $entity->getId();
        }

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
                'selectedIds' => [$entityIds[0], $entityIds[1]],
            ],
        );

        $component = $testComponent->component();
        $this->assertSame(5, $component->getTotalItems());

        // Execute batch delete
        $testComponent->call('batchDelete');

        $component = $testComponent->component();
        $this->assertSame(3, $component->getTotalItems());
    }
}
