<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithBatchActions;

/**
 * @group batch-actions
 */
class EntityListCustomBatchActionsTest extends ComponentTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
    }

    private function createEntities(int $count = 3): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $entity = new EntityWithBatchActions();
            $entity->setName('Item ' . $i);
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
    }

    // ── Custom batch action buttons rendered in batch bar ─────────────────────

    public function testBatchActivateButtonRendersInBatchBar(): void
    {
        $this->createEntities();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithBatchActions::class,
                'entityShortClass' => 'EntityWithBatchActions',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Activate Selected', $rendered);
        $this->assertActionRendered('✅', $rendered);
    }

    public function testBatchArchiveButtonRendersInBatchBar(): void
    {
        $this->createEntities();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithBatchActions::class,
                'entityShortClass' => 'EntityWithBatchActions',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Archive', $rendered);
        $this->assertActionRendered('📦', $rendered);
    }

    // ── Default delete button still present ──────────────────────────────────

    public function testDefaultDeleteButtonIsStillPresent(): void
    {
        $this->createEntities();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithBatchActions::class,
                'entityShortClass' => 'EntityWithBatchActions',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Delete Selected', $rendered);
    }

    // ── Row-only action does NOT appear as a batch bar button ─────────────────

    public function testRowOnlyActionDoesNotAppearInBatchBar(): void
    {
        $this->createEntities();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithBatchActions::class,
                'entityShortClass' => 'EntityWithBatchActions',
            ],
        );

        $rendered = (string) $component->render();

        // The test template marks batch action buttons with data-batch-action="{name}".
        // The row-only 'show' action must not appear in the batch bar.
        $this->assertStringNotContainsString('data-batch-action="show"', $rendered);
    }

    // ── Confirm message with %count% placeholder ─────────────────────────────

    public function testConfirmMessageCountPlaceholderIsRenderedForSelectedItems(): void
    {
        $this->createEntities();

        $entities = $this->entityManager->getRepository(EntityWithBatchActions::class)->findAll();
        $ids = array_map(fn ($e) => $e->getId(), $entities);

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithBatchActions::class,
                'entityShortClass' => 'EntityWithBatchActions',
                'selectedIds' => $ids,
            ],
        );

        $rendered = (string) $component->render();

        $count = count($ids);
        // Button label includes current selection count
        $this->assertStringContainsString('Activate Selected (' . $count . ')', $rendered);
    }

    // ── Both-type action appears in row actions ───────────────────────────────

    public function testBothTypeActionAppearsInRowActions(): void
    {
        $entity = new EntityWithBatchActions();
        $entity->setName('Both Type Entity');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithBatchActions::class,
                'entityShortClass' => 'EntityWithBatchActions',
            ],
        );

        $rendered = (string) $component->render();

        // bulk-archive is ACTION_TYPE_BOTH — must appear as a row action link in <td class="actions">
        $this->assertStringContainsString('href="/admin/test/batch/archive"', $rendered);
        $this->assertActionRendered('📦', $rendered);
    }

    // ── Both-type action also appears in batch bar ────────────────────────────

    public function testBothTypeActionAppearsInBatchBar(): void
    {
        $this->createEntities();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithBatchActions::class,
                'entityShortClass' => 'EntityWithBatchActions',
            ],
        );

        $rendered = (string) $component->render();

        // Test template marks batch bar buttons with data-batch-action="{name}"
        $this->assertStringContainsString('data-batch-action="bulk-archive"', $rendered);
    }
}
