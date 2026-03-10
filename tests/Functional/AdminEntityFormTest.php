<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntityFormType;
use Kachnitel\AdminBundle\Twig\Components\AdminEntityForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Functional tests for the AdminEntityForm LiveComponent.
 *
 * Covers:
 *  - Component mounts and renders a Symfony form
 *  - Edit: instantiateForm() loads entity from DB and pre-fills values
 *  - New: instantiateForm() creates a fresh entity instance
 *  - save() with valid data persists the entity
 *  - save() with invalid data re-renders with validation errors (no persist)
 *  - New entity row exists in DB after first successful save
 *
 * ## Form data LiveProp key
 *
 * In this version of LiveComponent, ComponentWithFormTrait stores form values under
 * the form name as the LiveProp key (e.g. `test_entity_form`), not under a generic
 * `formValues` key. Use `$component->set('<formName>', [...])` to set form data in tests.
 * The form name is derived from the form type class name by Symfony's naming convention:
 * strip the `Type` suffix, convert to snake_case. `TestEntityFormType` → `test_entity_form`.
 *
 * @group admin-entity-form
 */
class AdminEntityFormTest extends ComponentTestCase
{
    /**
     * Form name derived from TestEntityFormType by Symfony's block prefix convention:
     * strip `Type` suffix → `TestEntityForm` → snake_case → `test_entity_form`.
     */
    private const FORM_NAME = 'test_entity_form';

    protected function setUp(): void
    {
        parent::setUp();

        // CSRF token storage requires a session on the request stack.
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        self::getContainer()->get('request_stack')->push($request);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createEntity(string $name = 'Original Name'): TestEntity
    {
        $entity = new TestEntity();
        $entity->setName($name);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    private function mountEditForm(TestEntity $entity): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: AdminEntityForm::class,
            data: [
                'entityClass'   => TestEntity::class,
                'entityId'      => $entity->getId(),
                'formTypeClass' => TestEntityFormType::class,
            ],
        );
    }

    private function mountNewForm(): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: AdminEntityForm::class,
            data: [
                'entityClass'   => TestEntity::class,
                'formTypeClass' => TestEntityFormType::class,
            ],
        );
    }

    // ── Render ────────────────────────────────────────────────────────────────

    /**
     * @test
     */
    public function editFormRendersWithEntityValue(): void
    {
        $entity = $this->createEntity('My Product');

        $rendered = (string) $this->mountEditForm($entity)->render();

        $this->assertStringContainsString('<form', $rendered);
        $this->assertStringContainsString('My Product', $rendered);
    }

    /**
     * @test
     */
    public function newFormRendersEmptyForm(): void
    {
        $rendered = (string) $this->mountNewForm()->render();

        $this->assertStringContainsString('<form', $rendered);
        $this->assertStringContainsString('name=', $rendered);
    }

    // ── Edit: save with valid data ────────────────────────────────────────────

    /**
     * @test
     */
    public function saveEditPersistsChangedValue(): void
    {
        $entity = $this->createEntity('Before');
        $component = $this->mountEditForm($entity);

        $component->set(self::FORM_NAME, ['name' => 'After']);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(TestEntity::class, $entity->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('After', $reloaded->getName());
    }

    /**
     * @test
     */
    public function saveEditEntityIdRemainsUnchanged(): void
    {
        $entity = $this->createEntity();
        $originalId = $entity->getId();
        $component = $this->mountEditForm($entity);

        $component->set(self::FORM_NAME, ['name' => 'Updated']);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(TestEntity::class, $originalId);
        $this->assertNotNull($reloaded);
        $this->assertSame('Updated', $reloaded->getName());
    }

    // ── Edit: save with invalid data ──────────────────────────────────────────

    /**
     * @test
     */
    public function saveEditWithBlankNameShowsValidationError(): void
    {
        $entity = $this->createEntity('Keep Me');
        $component = $this->mountEditForm($entity);

        $component->set(self::FORM_NAME, ['name' => '']);
        $component->call('save');

        $rendered = (string) $component->render();
        $this->assertStringContainsString('Name is required.', $rendered);
    }

    /**
     * @test
     */
    public function saveEditWithBlankNameDoesNotPersist(): void
    {
        $entity = $this->createEntity('Keep Me');
        $component = $this->mountEditForm($entity);

        $component->set(self::FORM_NAME, ['name' => '']);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(TestEntity::class, $entity->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('Keep Me', $reloaded->getName());
    }

    // ── New: save with valid data ─────────────────────────────────────────────

    /**
     * @test
     */
    public function saveNewCreatesEntity(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, ['name' => 'Brand New']);
        $component->call('save');

        $entities = $this->em->getRepository(TestEntity::class)->findBy(['name' => 'Brand New']);
        $this->assertCount(1, $entities);
    }

    /**
     * @test
     *
     * Verifies a persisted row exists with a positive ID after a new-entity save.
     *
     * Note: component()->entityId is NOT asserted because component() re-hydrates
     * from the original serialised LiveProps after call(), discarding mutations made
     * during the action — a known LiveComponent test limitation.
     */
    public function saveNewSetsEntityIdAfterPersist(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, ['name' => 'New Entity']);
        $component->call('save');

        $entities = $this->em->getRepository(TestEntity::class)->findBy(['name' => 'New Entity']);
        $this->assertCount(1, $entities);
        $this->assertNotNull($entities[0]->getId());
        $this->assertGreaterThan(0, $entities[0]->getId());
    }

    // ── New: save with invalid data ───────────────────────────────────────────

    /**
     * @test
     */
    public function saveNewWithBlankNameShowsValidationError(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, ['name' => '']);
        $component->call('save');

        $rendered = (string) $component->render();
        $this->assertStringContainsString('Name is required.', $rendered);
    }

    /**
     * @test
     */
    public function saveNewWithBlankNameDoesNotInsertRow(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, ['name' => '']);
        $component->call('save');

        $all = $this->em->getRepository(TestEntity::class)->findAll();
        $this->assertCount(0, $all);
    }
}
