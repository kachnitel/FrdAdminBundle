<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithoutBatchActions;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Functional tests for the EntityList batch actions feature.
 *
 * Delete logic now lives in DeleteButton (K:Admin:Action:Delete) — see
 * Tests\Twig\Components\AdminAction\DeleteButtonTest for delete-specific assertions.
 * These tests cover: UI rendering, checkbox behaviour, selection state, selectAll/deselectAll,
 * and the onActionCompleted listener on EntityList.
 *
 * @group batch-actions
 */
class EntityListBatchActionsTest extends ComponentTestCase
{
    // ── UI rendering ──────────────────────────────────────────────────────────

    public function testBatchActionsUIRendersWhenEnabled(): void
    {
        $em = $this->em();
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $rendered = (string) $this->entityListComponent()->render();

        $this->assertStringContainsString('Delete Selected', $rendered);
        $this->assertMatchesRegularExpression('/data-controller="[^"]*batch-select[^"]*"/', $rendered);
        $this->assertStringContainsString('data-batch-select-target="checkbox"', $rendered);
        $this->assertStringContainsString('data-batch-select-target="master"', $rendered);
        $this->assertStringContainsString('data-action="change->batch-select#toggleAll"', $rendered);
    }

    public function testBatchActionsCheckboxesRenderedForEachEntity(): void
    {
        $em = $this->em();
        for ($i = 1; $i <= 3; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $rendered = (string) $this->entityListComponent()->render();

        $this->assertSame(3, substr_count($rendered, 'data-batch-select-target="checkbox"'));
    }

    public function testDeleteSelectedCountReflectsSelectedIds(): void
    {
        $em = $this->em();
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
        }
        $em->flush();

        $rendered = (string) $this->entityListComponent(['selectedIds' => [1, 2, 3]])->render();

        $this->assertStringContainsString('Delete Selected (3)', $rendered);
    }

    public function testSelectedCheckboxesArePreservedAfterRerender(): void
    {
        $em = $this->em();
        $ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
            $em->flush();
            $ids[] = $entity->getId();
        }

        $rendered = (string) $this->entityListComponent(['selectedIds' => [$ids[0], $ids[1]]])->render();

        $this->assertStringContainsString('value="' . $ids[0] . '"', $rendered);
        $this->assertMatchesRegularExpression('/value="' . $ids[0] . '"[^>]*checked/', $rendered);
        $this->assertMatchesRegularExpression('/value="' . $ids[1] . '"[^>]*checked/', $rendered);
    }

    // ── selectedIds LiveProp ──────────────────────────────────────────────────

    public function testSelectedIdsLivePropTracksSelection(): void
    {
        $testComponent = $this->entityListComponent(['selectedIds' => [1, 2, 3]]);

        /** @var EntityList $component */
        $component = $testComponent->component();
        $this->assertSame([1, 2, 3], $component->selectedIds);
    }

    public function testSelectedIdsPersistedViaDataModelBinding(): void
    {
        $em = $this->em();
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
            $em->flush();
            $ids[] = $entity->getId();
        }

        $testComponent = $this->entityListComponent();
        $testComponent->set('selectedIds', [$ids[0], $ids[1]]);

        /** @var EntityList $component */
        $component = $testComponent->component();
        $this->assertCount(2, $component->selectedIds);
        $this->assertContains($ids[0], $component->selectedIds);
        $this->assertContains($ids[1], $component->selectedIds);
    }

    // ── selectAll / deselectAll ───────────────────────────────────────────────

    public function testSelectAllAddsCurrentPageEntitiesToSelection(): void
    {
        $em = $this->em();
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $em->persist($entity);
            $em->flush();
            $ids[] = $entity->getId();
        }

        $testComponent = $this->entityListComponent();
        $testComponent->call('selectAll');

        /** @var EntityList $component */
        $component = $testComponent->component();
        $this->assertCount(5, $component->selectedIds);
        foreach ($ids as $id) {
            $this->assertContains($id, $component->selectedIds);
        }
    }

    public function testDeselectAllClearsSelection(): void
    {
        $testComponent = $this->entityListComponent(['selectedIds' => [1, 2, 3]]);
        $testComponent->call('deselectAll');

        /** @var EntityList $component */
        $component = $testComponent->component();
        $this->assertEmpty($component->selectedIds);
    }

    // ── onActionCompleted listener ────────────────────────────────────────────

    public function testOnActionCompletedRemovesAffectedIdsFromSelection(): void
    {
        $testComponent = $this->entityListComponent(['selectedIds' => [1, 2, 3, 4, 5]]);
        $testComponent->call('onActionCompleted', ['affectedIds' => [1, 3, 5]]);

        /** @var EntityList $component */
        $component = $testComponent->component();
        $this->assertEqualsCanonicalizing([2, 4], $component->selectedIds);
    }

    public function testOnActionCompletedWithEmptyAffectedIdsPreservesSelection(): void
    {
        $testComponent = $this->entityListComponent(['selectedIds' => [1, 2, 3]]);
        $testComponent->call('onActionCompleted', ['affectedIds' => []]);

        /** @var EntityList $component */
        $component = $testComponent->component();
        $this->assertSame([1, 2, 3], $component->selectedIds);
    }

    public function testOnActionCompletedInvalidatesQueryCache(): void
    {
        $em = $this->em();
        $entity = new TestEntity();
        $entity->setName('Cached');
        $em->persist($entity);
        $em->flush();
        $id = $entity->getId();

        $testComponent = $this->entityListComponent(['selectedIds' => [$id]]);

        // Prime the cache by fetching entities
        /** @var EntityList $component */
        $component = $testComponent->component();
        $before = count($component->getEntities());

        // Simulate another process deleting the entity
        $subj = $em->find(TestEntity::class, $id);
        $this->assertNotNull($subj);
        $em->remove($subj);
        $em->flush();
        $em->clear();

        // Fire the listener — should invalidate cache
        $testComponent->call('onActionCompleted', ['affectedIds' => [$id]]);

        $component = $testComponent->component();
        $after = count($component->getEntities());

        $this->assertLessThan($before, $after, 'Cache should be invalidated after action completed');
    }

    // ── canBatchDelete / isBatchActionsEnabled ────────────────────────────────

    public function testCanBatchDeleteReturnsTrueWhenPermitted(): void
    {
        /** @var EntityList $component */
        $component = $this->entityListComponent()->component();
        $this->assertTrue($component->canBatchDelete());
    }

    public function testIsBatchActionsEnabledReturnsFalseByDefault(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithoutBatchActions::class,
                'entityShortClass' => 'EntityWithoutBatchActions',
            ],
        );

        /** @var EntityList $component */
        $component = $testComponent->component();
        $this->assertFalse(
            $component->permissionService->isBatchActionsEnabled($component->entityClass)
        );
    }

    public function testCheckboxesHaveProperDataModelBinding(): void
    {
        $em = $this->em();
        $entity = new TestEntity();
        $entity->setName('Test');
        $em->persist($entity);
        $em->flush();

        $rendered = (string) $this->entityListComponent()->render();

        $this->assertStringContainsString('data-model="selectedIds[]"', $rendered);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function em(): EntityManagerInterface
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = static::getContainer()->get('doctrine');
        /** @var EntityManagerInterface $em */
        $em = $doctrine->getManager();
        return $em;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function entityListComponent(array $data = []): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: array_merge(
                ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
                $data,
            ),
        );
    }
}
