<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntityFormType;
use Kachnitel\AdminBundle\Twig\Components\InlineEntityForm;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Functional tests for the InlineEntityForm LiveComponent.
 *
 * Covers:
 *   - Component renders an empty form (no entity loaded from DB)
 *   - save() with valid data persists the entity and assigns a positive ID
 *   - save() with invalid data re-renders with validation errors (no persist)
 *
 * ## Form name constant
 *
 * InlineEntityForm derives the form name from the entity FQCN:
 *   'Kachnitel\AdminBundle\Tests\Fixtures\TestEntity'
 *   → preg_replace('[^a-z0-9]+', '_'): 'Kachnitel_AdminBundle_Tests_Fixtures_TestEntity'
 *   → mb_strtolower:                  'kachnitel_adminbundle_tests_fixtures_testentity'
 *   → prepend 'inline_':              'inline_kachnitel_adminbundle_tests_fixtures_testentity'
 *
 * This unique prefix prevents HTML id collisions when the same entity type
 * appears in both the page form (K:Admin:EntityForm) and the inline dialog
 * simultaneously.
 *
 * @group inline-add
 * @group admin-entity-form
 */
final class InlineEntityFormTest extends ComponentTestCase
{
    private const FORM_NAME = 'inline_kachnitel_adminbundle_tests_fixtures_testentity';

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

    private function mountForm(): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: InlineEntityForm::class,
            data: [
                'entityClass'   => TestEntity::class,
                'formTypeClass' => TestEntityFormType::class,
                // entityId intentionally omitted — inline creation only.
            ],
        );
    }

    // ── Render ────────────────────────────────────────────────────────────────

    #[Test]
    public function formRendersWithFormElementAndSaveButton(): void
    {
        $rendered = (string) $this->mountForm()->render();

        $this->assertStringContainsString('<form', $rendered);
        $this->assertStringContainsString('name=', $rendered);
        // Save & Close button
        $this->assertStringContainsString('data-live-action-param="save"', $rendered);
    }

    public function testFormRootDoesNotHaveDataAdminFormAttribute(): void
    {
        $rendered = (string) $this->mountForm()->render();

        $this->assertStringNotContainsString('data-admin-form', $rendered);
    }

    public function testFormFieldIdsArePrefixedWithInline(): void
    {
        $rendered = (string) $this->mountForm()->render();

        $this->assertStringContainsString('id="inline_', $rendered);
    }

    // ── Save: valid data ──────────────────────────────────────────────────────

    #[Test]
    public function saveWithValidDataCreatesEntity(): void
    {
        $component = $this->mountForm();

        $component->set(self::FORM_NAME, ['name' => 'Inline Entity']);
        $component->call('save');

        $entities = $this->em->getRepository(TestEntity::class)->findBy(['name' => 'Inline Entity']);
        $this->assertCount(1, $entities);
    }

    #[Test]
    public function savedEntityHasPositiveId(): void
    {
        $component = $this->mountForm();

        $component->set(self::FORM_NAME, ['name' => 'New Entity']);
        $component->call('save');

        $entities = $this->em->getRepository(TestEntity::class)->findBy(['name' => 'New Entity']);
        $this->assertCount(1, $entities);
        $this->assertNotNull($entities[0]->getId());
        $this->assertGreaterThan(0, $entities[0]->getId());
    }

    // ── Save: invalid data ────────────────────────────────────────────────────

    #[Test]
    public function saveWithBlankNameShowsValidationError(): void
    {
        $component = $this->mountForm();

        $component->set(self::FORM_NAME, ['name' => '']);
        $component->call('save');

        $rendered = (string) $component->render();
        $this->assertStringContainsString('Name is required.', $rendered);
    }

    #[Test]
    public function saveWithBlankNameDoesNotInsertRow(): void
    {
        $component = $this->mountForm();

        $component->set(self::FORM_NAME, ['name' => '']);
        $component->call('save');

        $all = $this->em->getRepository(TestEntity::class)->findAll();
        $this->assertCount(0, $all);
    }

    public function testSaveButtonRemainsVisibleAfterValidationFailure(): void
    {
        $component = $this->mountForm();

        $component->set(self::FORM_NAME, ['name' => '']);
        $component->call('save');

        $rendered = (string) $component->render();
        $this->assertStringContainsString('data-live-action-param="save"', $rendered);
    }
}
