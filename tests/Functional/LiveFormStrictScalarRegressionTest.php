<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\Tests\Fixtures\RequiredFieldsStrictEntity;
use Kachnitel\AdminBundle\Twig\Components\AdminEntityForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Regression coverage for the genuinely-non-nullable-PHP-property shape that
 * LiveFormEmptyDataRegressionTest's RequiredFieldsEntity cannot exercise.
 *
 * ## Two independent surfaces
 *
 * PropertyPathAccessor::setValue() (the write side, DataMapper.php) has
 * tolerated an uninitialized property during its own "read current value for
 * the by_reference equality check" step since a Symfony 4.4 fix
 * (symfony/symfony#45233, #47151) — confirmed against the DataAccessor/
 * DataMapper split this project's own stack traces show, not the older
 * deprecated PropertyPathMapper. That is not expected to be where this
 * fails.
 *
 * DataMapper::mapDataToForms() — called once from inside instantiateForm(),
 * to populate a freshly-mounted form's initial widget values, before any
 * submission at all — reads the same property but has no visible guard of
 * its own around that read. Whether that matters here is genuinely open:
 * testNewFormMountAndFirstRenderDoesNotCrash() exists to answer it
 * empirically. If it fails, the DataTransformer fix under discussion
 * (which only guards the reverse-transform/write direction via empty_data)
 * is not sufficient on its own — DynamicEntityFormType's initial binding
 * would need its own change too.
 *
 * ## Expected state right now
 *
 * Every test here is written to describe the fixed behaviour, not the bug —
 * per TDD, they are expected to fail RED against current
 * DoctrineFormTypeMapper (as uncaught PropertyAccess/TypeError, not merely a
 * failed assertion), and to turn GREEN once the guard DataTransformer lands.
 * No changes to DoctrineFormTypeMapper or DynamicEntityFormType are included
 * here — deliberately held back pending review of the transformer itself.
 *
 * @group auto-form
 * @group admin-entity-form
 * @group live-form-defaults
 * @group strict-nullability
 */
class LiveFormStrictScalarRegressionTest extends ComponentTestCase
{
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
                'entityClass'   => RequiredFieldsStrictEntity::class,
                'formTypeClass' => DynamicEntityFormType::class,
            ],
        );
    }

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

    // ── Surface 1: initial mount/render, before any submission ────────────────

    /**
     * @test
     *
     * Open question this test exists to answer, not a confirmed reproduction:
     * does DataMapper::mapDataToForms() reading the never-set $qty off a
     * freshly-constructed entity crash on its own, independently of
     * empty_data and independently of submitFormOnRender()?
     */
    public function newFormMountAndFirstRenderDoesNotCrash(): void
    {
        $component = $this->mountNewForm();

        $value = $this->renderedFieldValue($component, 'qty');

        $this->assertNull(
            $value,
            'A brand-new entity\'s never-set required int field must render blank on first mount, not crash and not show a fabricated value.'
        );
    }

    // ── Surface 2: the confirmed production crash ──────────────────────────────

    /**
     * @test
     *
     * Mirrors the actual trigger: the LiveComponent client echoes back every
     * field's current value on each model update, including fields the user
     * hasn't touched yet. Editing "name" resubmits the whole form —
     * submitFormOnRender() runs unconditionally on every render — and the
     * still-blank "qty" field's empty_data currently resolves to null against
     * a property typed `int`, not `?int`.
     */
    public function editingAnotherFieldWhileRequiredScalarIsBlankDoesNotCrash(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, [
            'name' => 'Touched while qty is still blank',
            'qty'  => '',
        ]);

        $value = $this->renderedFieldValue($component, 'qty');

        $this->assertNull(
            $value,
            'Editing an unrelated field must not crash, and must not backfill the still-blank required qty field with a fabricated value.'
        );
    }

    // ── Control: a real value still works ──────────────────────────────────────

    /**
     * @test
     *
     * Baseline, not itself testing the bug: guards against a fix that
     * "solves" the crash by also breaking normal submission of a value the
     * user actually provided.
     */
    public function savingWithAnExplicitValuePersistsCorrectly(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, [
            'name' => 'Fully Filled',
            'qty'  => '7',
        ]);
        $component->call('save');

        $entities = $this->em->getRepository(RequiredFieldsStrictEntity::class)->findBy(['name' => 'Fully Filled']);
        $this->assertCount(1, $entities);
        $this->assertSame(7, $entities[0]->getQty());
    }
}
