<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithRowActions;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;

/**
 * @group row-actions
 */
final class EntityListRowActionsTest extends ComponentTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
    }

    // -------------------------------------------------------------------------
    // Default show/edit actions (TestEntity — no custom row action config)
    // -------------------------------------------------------------------------

    public function testDefaultShowAndEditButtonsRenderForNormalEntity(): void
    {
        $entity = new TestEntity();
        $entity->setName('Default Entity');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
        );

        $rendered = (string) $component->render();

        // Both default buttons should appear (ComponentTestKernel grants all access)
        $this->assertActionRendered('👀', $rendered);
        $this->assertActionRendered('✏️', $rendered);
    }

    // -------------------------------------------------------------------------
    // Custom actions — condition met
    // -------------------------------------------------------------------------

    public function testCustomApproveButtonAppearsWhenStatusIsPending(): void
    {
        $entity = new EntityWithRowActions();
        $entity->setName('Item');
        $entity->setStatus('pending');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithRowActions::class,
                'entityShortClass' => 'EntityWithRowActions',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertActionRendered('✅', $rendered);
        $this->assertStringContainsString('Approve', $rendered);
    }

    public function testArchiveFormButtonAppearsWhenNotArchived(): void
    {
        $entity = new EntityWithRowActions();
        $entity->setName('Item');
        $entity->setStatus('pending');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithRowActions::class,
                'entityShortClass' => 'EntityWithRowActions',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertActionRendered('📦', $rendered);
        $this->assertStringContainsString('Archive', $rendered);
    }

    // -------------------------------------------------------------------------
    // Custom actions — condition NOT met
    // -------------------------------------------------------------------------

    public function testApproveButtonIsHiddenWhenStatusIsNotPending(): void
    {
        $entity = new EntityWithRowActions();
        $entity->setName('Item');  // neutral name — avoids false positive on "Approve"
        $entity->setStatus('approved');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithRowActions::class,
                'entityShortClass' => 'EntityWithRowActions',
            ],
        );

        $rendered = (string) $component->render();

        // Assert on the icon, not the label — entity names can contain the label text
        $this->assertActionNotRendered('✅', $rendered);
    }

    public function testArchiveButtonIsHiddenWhenAlreadyArchived(): void
    {
        $entity = new EntityWithRowActions();
        $entity->setName('Item');  // neutral name — avoids false positive on "Archive"
        $entity->setStatus('archived');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithRowActions::class,
                'entityShortClass' => 'EntityWithRowActions',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertActionNotRendered('📦', $rendered);
    }

    // -------------------------------------------------------------------------
    // AdminActionsConfig(exclude: ['edit']) — no Edit button
    // -------------------------------------------------------------------------

    public function testEditButtonIsExcludedByAdminActionsConfig(): void
    {
        $entity = new EntityWithRowActions();
        $entity->setName('Item');
        $entity->setStatus('pending');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithRowActions::class,
                'entityShortClass' => 'EntityWithRowActions',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertActionNotRendered('✏️', $rendered);
    }

    public function testShowButtonIsStillPresentDespiteExcludingEdit(): void
    {
        $entity = new EntityWithRowActions();
        $entity->setName('Item');
        $entity->setStatus('pending');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithRowActions::class,
                'entityShortClass' => 'EntityWithRowActions',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertActionRendered('👀', $rendered);
    }

    // -------------------------------------------------------------------------
    // POST form action rendering
    // -------------------------------------------------------------------------

    public function testArchiveRendersAsFormNotLink(): void
    {
        $entity = new EntityWithRowActions();
        $entity->setName('Item');
        $entity->setStatus('pending');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithRowActions::class,
                'entityShortClass' => 'EntityWithRowActions',
            ],
        );

        $rendered = (string) $component->render();

        // Archive is a POST action — must render as <form>, not <a href>
        $this->assertStringContainsString('<form', $rendered);
        $this->assertStringContainsString('method="post"', $rendered);
        // Archive this item?
        $this->assertStringContainsString('Archive\\u0020this\\u0020item\\u003F', $rendered);
    }

    // -------------------------------------------------------------------------
    // Multiple rows — each evaluated independently
    // -------------------------------------------------------------------------

    public function testConditionsAreEvaluatedPerRow(): void
    {
        $pending = new EntityWithRowActions();
        $pending->setName('Item A');
        $pending->setStatus('pending');
        $this->entityManager->persist($pending);

        $archived = new EntityWithRowActions();
        $archived->setName('Item B');
        $archived->setStatus('archived');
        $this->entityManager->persist($archived);

        $this->entityManager->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithRowActions::class,
                'entityShortClass' => 'EntityWithRowActions',
            ],
        );

        $rendered = (string) $component->render();

        // Approve button should appear exactly once (only for the pending row)
        $this->assertActionRenderedCount('✅', 1, $rendered, 'Approve button should render only for the pending row');
    }
}
