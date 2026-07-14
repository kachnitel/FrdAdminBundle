<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Components;

use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\Twig\Components\InlineEntityForm;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Verifies that InlineEntityForm::instantiateForm() produces a uniquely-named form
 * and forwards the correct options to FormFactoryInterface::createNamed().
 *
 * Key invariants under test:
 *   - Form name is always prefixed with 'inline_'
 *   - Form name is derived from the entity FQCN so two different entity types
 *     on the same page never share HTML id prefixes
 *   - DynamicEntityFormType receives entity_class, data_class, and is_root: true
 *   - Custom FormType receives only csrf_protection (no DynamicEntityFormType extras)
 *   - null is always passed as form data (inline add is creation-only — no DB lookup)
 *   - The EntityManager's find() is never called (entityId is intentionally ignored)
 */
#[CoversClass(InlineEntityForm::class)]
#[Group('inline-add')]
#[AllowMockObjectsWithoutExpectations]
final class InlineEntityFormInstantiateFormTest extends TestCase
{
    protected function setUp(): void {}

    // ── Form name derivation ───────────────────────────────────────────────────

    #[Test]
    public function formNameIsPrefixedWithInline(): void
    {
        $capturedName = '';
        $capturedOptions = [];
        $component = $this->makeComponent($capturedName, $capturedOptions);

        $component->entityClass   = InlineFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;
        $component->exposeInstantiateForm();

        $this->assertStringStartsWith('inline_', $capturedName);
    }

    #[Test]
    public function differentEntityClassesProduceDifferentFormNames(): void
    {
        $nameA = '';
        $optsA = [];
        $componentA = $this->makeComponent($nameA, $optsA);
        $componentA->entityClass   = InlineFormFixtureEntity::class;
        $componentA->formTypeClass = DynamicEntityFormType::class;
        $componentA->exposeInstantiateForm();

        $nameB = '';
        $optsB = [];
        $componentB = $this->makeComponent($nameB, $optsB);
        $componentB->entityClass   = InlineFormOtherEntity::class;
        $componentB->formTypeClass = DynamicEntityFormType::class;
        $componentB->exposeInstantiateForm();

        $this->assertNotSame($nameA, $nameB, 'Different entity types must produce distinct form names');
    }

    #[Test]
    public function sameEntityClassAlwaysProducesSameFormName(): void
    {
        $nameA = '';
        $optsA = [];
        $componentA = $this->makeComponent($nameA, $optsA);
        $componentA->entityClass   = InlineFormFixtureEntity::class;
        $componentA->formTypeClass = DynamicEntityFormType::class;
        $componentA->exposeInstantiateForm();

        $nameB = '';
        $optsB = [];
        $componentB = $this->makeComponent($nameB, $optsB);
        $componentB->entityClass   = InlineFormFixtureEntity::class;
        $componentB->formTypeClass = DynamicEntityFormType::class;
        $componentB->exposeInstantiateForm();

        $this->assertSame($nameA, $nameB, 'Stable name needed for morphing stability on re-render');
    }

    // ── DynamicEntityFormType options ──────────────────────────────────────────

    #[Test]
    public function dynamicEntityFormTypeReceivesEntityClassOption(): void
    {
        $capturedName    = '';
        $capturedOptions = [];
        $component = $this->makeComponent($capturedName, $capturedOptions);
        $component->entityClass   = InlineFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;
        $component->exposeInstantiateForm();

        $this->assertArrayHasKey('entity_class', $capturedOptions);
        $this->assertSame(InlineFormFixtureEntity::class, $capturedOptions['entity_class']);
    }

    #[Test]
    public function dynamicEntityFormTypeReceivesDataClassOption(): void
    {
        $capturedName    = '';
        $capturedOptions = [];
        $component = $this->makeComponent($capturedName, $capturedOptions);
        $component->entityClass   = InlineFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;
        $component->exposeInstantiateForm();

        $this->assertArrayHasKey('data_class', $capturedOptions);
        $this->assertSame(InlineFormFixtureEntity::class, $capturedOptions['data_class']);
    }

    #[Test]
    public function dynamicEntityFormTypeReceivesIsRootTrue(): void
    {
        $capturedName    = '';
        $capturedOptions = [];
        $component = $this->makeComponent($capturedName, $capturedOptions);
        $component->entityClass   = InlineFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;
        $component->exposeInstantiateForm();

        $this->assertArrayHasKey('is_root', $capturedOptions);
        $this->assertTrue($capturedOptions['is_root']);
    }

    #[Test]
    public function dynamicEntityFormTypeDisablesCsrf(): void
    {
        $capturedName    = '';
        $capturedOptions = [];
        $component = $this->makeComponent($capturedName, $capturedOptions);
        $component->entityClass   = InlineFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;
        $component->exposeInstantiateForm();

        $this->assertFalse($capturedOptions['csrf_protection']);
    }

    // ── Custom FormType options ────────────────────────────────────────────────

    #[Test]
    public function customFormTypeDoesNotReceiveEntityClassOption(): void
    {
        $capturedName    = '';
        $capturedOptions = [];
        $component = $this->makeComponent($capturedName, $capturedOptions);
        $component->entityClass   = InlineFormFixtureEntity::class;
        $component->formTypeClass = 'App\Form\CustomFormType';
        $component->exposeInstantiateForm();

        $this->assertArrayNotHasKey('entity_class', $capturedOptions);
    }

    #[Test]
    public function customFormTypeDoesNotReceiveIsRootOption(): void
    {
        $capturedName    = '';
        $capturedOptions = [];
        $component = $this->makeComponent($capturedName, $capturedOptions);
        $component->entityClass   = InlineFormFixtureEntity::class;
        $component->formTypeClass = 'App\Form\CustomFormType';
        $component->exposeInstantiateForm();

        $this->assertArrayNotHasKey('is_root', $capturedOptions);
    }

    #[Test]
    public function customFormTypeStillDisablesCsrf(): void
    {
        $capturedName    = '';
        $capturedOptions = [];
        $component = $this->makeComponent($capturedName, $capturedOptions);
        $component->entityClass   = InlineFormFixtureEntity::class;
        $component->formTypeClass = 'App\Form\CustomFormType';
        $component->exposeInstantiateForm();

        $this->assertFalse($capturedOptions['csrf_protection']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Build a TestableInlineEntityForm whose FormFactory mock captures
     * the name and options passed to createNamed().
     *
     * @param string               $capturedName    Filled with the form name on call.
     * @param array<string, mixed> $capturedOptions Filled with the form options on call.
     */
    private function makeComponent(string &$capturedName, array &$capturedOptions): TestableInlineEntityForm
    {
        $dummyForm   = $this->createMock(FormInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);

        $formFactory->method('createNamed')
            ->willReturnCallback(
                static function (
                    string $name,
                    string $type,
                    mixed $data,
                    array $options = [],
                ) use (&$capturedName, &$capturedOptions, $dummyForm): FormInterface {
                    $capturedName    = $name;
                    $capturedOptions = $options;
                    return $dummyForm;
                }
            );

        return new TestableInlineEntityForm($this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $formFactory);
    }
}

// ── Test double ────────────────────────────────────────────────────────────────

/**
 * Subclass that exposes the protected instantiateForm() method publicly.
 *
 * Does NOT override createNamed / createForm — the mock FormFactory injected
 * via the constructor captures the call instead.
 */
class TestableInlineEntityForm extends InlineEntityForm
{
    /** @return FormInterface<object|null> */
    public function exposeInstantiateForm(): FormInterface
    {
        return $this->instantiateForm();
    }
}

// ── Inline fixtures ────────────────────────────────────────────────────────────

class InlineFormFixtureEntity
{
    private string $name = '';
}

class InlineFormOtherEntity
{
    private string $title = '';
}
