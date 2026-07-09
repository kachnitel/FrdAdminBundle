<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\Tests\Fixtures\RequiredFieldsEntity;
use Kachnitel\AdminBundle\Twig\Components\AdminEntityForm;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Regression coverage for the live-form empty_data defaulting bug.
 *
 * ## Root cause
 *
 * Symfony UX Live Component's ComponentWithFormTrait::submitFormOnRender()
 * (#[PreReRender]) calls $form->submit($this->formValues) before *every*
 * render, including the very first one, before the user has touched
 * anything. A blank required field's empty_data used to resolve to a
 * hardcoded sentinel (0, an epoch date, the first enum case) — silently
 * writable, silently "valid", and visibly wrong the moment a sibling
 * field's edit triggered the next resubmit.
 *
 * The current fix: DoctrineFormTypeMapper sets `empty_data: ''` uniformly
 * for every non-boolean scalar type. Symfony's own core transformers
 * (NumberToLocalizedStringTransformer, DateTimeToHtml5LocalDateTimeTransformer,
 * ChoiceToValueTransformer) all resolve '' to null safely and correctly on
 * their own — confirmed by reading symfony/form 7.2's actual source rather
 * than assumed, after an earlier attempt using a literal `empty_data: null`
 * turned out to be unsafe for date/time/enum types specifically (their
 * transformers reject a literal null outright with a generic per-type
 * error; only '' reaches their documented "return null" branch).
 * RequiredValueTransformer, a model transformer attached whenever a field
 * is DB-required, then rejects that null during reverseTransform() —
 * routing a blank required field through Symfony's ordinary "not
 * synchronized" handling instead of letting it reach PropertyAccessor as a
 * literal null against a typed, non-nullable property.
 *
 *
 * @see RequiredFieldsEntity
 * @group auto-form
 * @group admin-entity-form
 * @group live-form-defaults
 */
class LiveFormEmptyDataRegressionTest extends ComponentTestCase
{
    /**
     * Form name derived from DynamicEntityFormType by Symfony's block prefix
     * convention: strip `Type` suffix -> `DynamicEntityForm` -> snake_case ->
     * `dynamic_entity_form`.
     */
    private const FORM_NAME = 'dynamic_entity_form';

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

    private function mountNewForm(): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: AdminEntityForm::class,
            data: [
                'entityClass'   => RequiredFieldsEntity::class,
                'formTypeClass' => DynamicEntityFormType::class,
            ],
        );
    }

    private function createEntity(string $name, int $priority, \DateTimeImmutable $scheduledAt): RequiredFieldsEntity
    {
        $entity = new RequiredFieldsEntity();
        $entity->setName($name);
        $entity->setPriority($priority);
        $entity->setScheduledAt($scheduledAt);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    private function mountEditForm(RequiredFieldsEntity $entity): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: AdminEntityForm::class,
            data: [
                'entityClass'   => RequiredFieldsEntity::class,
                'entityId'      => $entity->getId(),
                'formTypeClass' => DynamicEntityFormType::class,
            ],
        );
    }

    /**
     * Read a form field's rendered `value` attribute, normalising a missing
     * attribute and an empty string to the same "blank" result so the
     * assertion doesn't depend on which one a given widget/theme happens to
     * emit for an unset value.
     */
    private function renderedFieldValue(TestLiveComponent $component, string $fieldName): ?string
    {
        $crawler = $component->render()->crawler();
        $node    = $crawler->filter(sprintf('[name="%s[%s]"]', self::FORM_NAME, $fieldName));

        if (0 === $node->count()) {
            $this->fail(sprintf('Field "%s" was not found in the rendered form.', $fieldName));
        }

        $value = $node->attr('value');

        return ('' === $value) ? null : $value;
    }

    /**
     * Locate a field's row container by the id the admin_compact form theme
     * assigns it (`{form_name}_{field}`, Symfony's default id convention),
     * to check for validation error markup scoped to that specific field.
     */
    private function fieldRowErrors(TestLiveComponent $component, string $fieldName): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $component->render()->crawler();
        $row     = $crawler->filter('#' . self::FORM_NAME . '_' . $fieldName);

        $this->assertGreaterThan(
            0,
            $row->count(),
            sprintf('Could not locate the %s field row — selector may not match the current form theme.', $fieldName)
        );

        return $row->filter('li');
    }

    // ── First render, before any user interaction ──────────────────────────────

    #[Test]
    public function newFormFirstRenderLeavesRequiredIntegerFieldBlank(): void
    {
        $component = $this->mountNewForm();

        $value = $this->renderedFieldValue($component, 'priority');

        $this->assertNull(
            $value,
            'A required-but-unfilled integer field must render blank on the very first render, not "0".'
        );
    }

    #[Test]
    public function newFormFirstRenderLeavesRequiredDateTimeFieldBlank(): void
    {
        $component = $this->mountNewForm();

        $value = $this->renderedFieldValue($component, 'scheduledAt');

        $this->assertNull(
            $value,
            'A required-but-unfilled datetime field must render blank on the very first render, not the 1970-01-01 epoch sentinel.'
        );
    }

    // ── After editing an unrelated field ─────────────────────────────────────

    /**
     * @test
     *
     * Mirrors the exact scenario reported: the user edits one field (name);
     * the LiveComponent client echoes back the *current* (still blank) value
     * of every other field, exactly as symfony/ux-live-component's
     * data-model="on(change)|*" default binding does in the browser.
     */
    public function editingOneFieldDoesNotBackfillUntouchedIntegerFieldWithZero(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, [
            'name'        => 'Updated while priority is still blank',
            'priority'    => '',
            'scheduledAt' => '',
        ]);

        $value = $this->renderedFieldValue($component, 'priority');

        $this->assertNull(
            $value,
            'Editing the name field must not backfill the still-blank priority field with "0".'
        );
    }

    #[Test]
    public function editingOneFieldDoesNotBackfillUntouchedDateTimeFieldWithEpoch(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, [
            'name'        => 'Updated while scheduledAt is still blank',
            'priority'    => '',
            'scheduledAt' => '',
        ]);

        $value = $this->renderedFieldValue($component, 'scheduledAt');

        $this->assertNull(
            $value,
            'Editing the name field must not backfill the still-blank scheduledAt field with the 1970-01-01 epoch sentinel.'
        );
    }

    // ── Control: explicit values still work correctly ──────────────────────────

    /**
     * @test
     *
     * Sanity check that DynamicEntityFormType still maps and persists these
     * fields correctly when the user *does* provide values. Not expected to
     * fail before or after the fix — guards against a fix that "solves" the
     * regression by breaking normal submission instead.
     */
    public function savingWithExplicitValuesPersistsThemCorrectly(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, [
            'name'        => 'Fully Filled',
            'priority'    => '3',
            'scheduledAt' => '2030-06-15T10:00:00',
        ]);
        $component->call('save');

        $entities = $this->em->getRepository(RequiredFieldsEntity::class)->findBy(['name' => 'Fully Filled']);
        $this->assertCount(1, $entities);
        $this->assertSame(3, $entities[0]->getPriority());
        $this->assertSame('2030-06-15', $entities[0]->getScheduledAt()?->format('Y-m-d'));
    }

    // ── Editing an existing entity: fail, don't preserve ────────────────────────

    #[Test]
    public function editingAnExistingEntityFieldFailsValidationOnBlankResubmit(): void
    {
        $entity = $this->createEntity('Original', 5, new \DateTimeImmutable('2024-03-10'));
        $component = $this->mountEditForm($entity);

        $component->set(self::FORM_NAME, [
            'name'        => 'Renamed',
            'priority'    => '',
            'scheduledAt' => '',
        ]);
        $component->call('save');

        $rendered = (string) $component->render();
        $this->assertStringContainsString('is required.', $rendered);
    }

    // ── Genuinely blank required fields must fail validation, not persist ──────

    /**
     * @test
     *
     * With the sentinel gone, a *genuinely* blank required field (a new
     * entity where the user really did skip it) must be caught by
     * validation before it ever reaches $em->flush() — otherwise it would
     * either silently persist null into a NOT NULL column and crash at
     * flush, or (with the old sentinel) silently "succeed" with a fabricated
     * value the user never chose.
     */
    public function savingWithBlankRequiredFieldsShowsValidationErrorInsteadOfPersisting(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, [
            'name'        => 'Missing Required Fields',
            'priority'    => '',
            'scheduledAt' => '',
        ]);
        $component->call('save');

        $rendered = (string) $component->render();
        $this->assertStringContainsString('is required.', $rendered);

        $entities = $this->em->getRepository(RequiredFieldsEntity::class)->findBy(['name' => 'Missing Required Fields']);
        $this->assertCount(
            0,
            $entities,
            'A required field left genuinely blank must block persistence, not silently save with a fabricated value.'
        );
    }

    // ── Genuinely optional fields must never show a required-field error ───────

    /**
     * @test
     *
     * completedAt is nullable at both the Doctrine and PHP level — a
     * genuinely optional field, unlike scheduledAt (Doctrine-required with a
     * PHP-nullable workaround). Leaving it blank alongside otherwise-valid
     * data must never surface any error for it.
     */
    public function savingWithBlankGenuinelyOptionalDateFieldShowsNoErrorForIt(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, [
            'name'        => 'Valid, completedAt left blank on purpose',
            'priority'    => '3',
            'scheduledAt' => '2030-06-15T10:00:00',
            'completedAt' => '',
        ]);
        $component->call('save');

        $errors = $this->fieldRowErrors($component, 'completedAt');

        $this->assertCount(
            0,
            $errors,
            'completedAt is nullable at both the Doctrine and PHP level; leaving it blank must not produce a validation error.'
        );
    }
}
