<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\Twig\Components\AdminEntityForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;

/**
 * Verifies that AdminEntityForm::instantiateForm() passes the correct options
 * to createForm() when using DynamicEntityFormType vs a custom FormType.
 *
 * @group auto-form
 * @group collections
 */
#[CoversClass(AdminEntityForm::class)]
#[Group('auto-form')]
#[Group('collections')]
class AdminEntityFormInstantiateFormTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    // ── DynamicEntityFormType path ─────────────────────────────────────────────

    public function testDynamicEntityFormTypeReceivesEntityClassOption(): void
    {
        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass  = AdminFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;

        $component->exposeInstantiateForm();

        $this->assertArrayHasKey('entity_class', $capturedOptions);
        $this->assertSame(AdminFormFixtureEntity::class, $capturedOptions['entity_class']);
    }

    public function testDynamicEntityFormTypeReceivesDataClassOption(): void
    {
        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass   = AdminFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;

        $component->exposeInstantiateForm();

        $this->assertArrayHasKey('data_class', $capturedOptions);
        $this->assertSame(AdminFormFixtureEntity::class, $capturedOptions['data_class']);
    }

    public function testEntityClassAndDataClassMatchForDynamicFormType(): void
    {
        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass   = AdminFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;

        $component->exposeInstantiateForm();

        $this->assertSame(
            $capturedOptions['entity_class'],
            $capturedOptions['data_class'],
            'entity_class and data_class must be identical so Symfony binds the correct object type'
        );
    }

    public function testDynamicEntityFormTypeAlwaysDisablesCsrf(): void
    {
        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass   = AdminFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;

        $component->exposeInstantiateForm();

        $this->assertFalse($capturedOptions['csrf_protection']);
    }

    /**
     * is_root: true must be passed to DynamicEntityFormType so that collection
     * associations (ManyToMany, OneToMany) are included in the top-level form.
     */
    public function testDynamicEntityFormTypeReceivesIsRootTrue(): void
    {
        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass   = AdminFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;

        $component->exposeInstantiateForm();

        $this->assertArrayHasKey('is_root', $capturedOptions);
        $this->assertTrue($capturedOptions['is_root']);
    }

    // ── Custom / other FormType path ───────────────────────────────────────────

    public function testCustomFormTypeDoesNotReceiveEntityClassOption(): void
    {
        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass   = AdminFormFixtureEntity::class;
        $component->formTypeClass = 'App\\Form\\CustomFormType';

        $component->exposeInstantiateForm();

        $this->assertArrayNotHasKey(
            'entity_class',
            $capturedOptions,
            'entity_class must only be passed for DynamicEntityFormType'
        );
    }

    public function testCustomFormTypeDoesNotReceiveDataClassOption(): void
    {
        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass   = AdminFormFixtureEntity::class;
        $component->formTypeClass = 'App\\Form\\CustomFormType';

        $component->exposeInstantiateForm();

        $this->assertArrayNotHasKey(
            'data_class',
            $capturedOptions,
            'data_class must only be passed for DynamicEntityFormType'
        );
    }

    public function testCustomFormTypeDoesNotReceiveIsRootOption(): void
    {
        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass   = AdminFormFixtureEntity::class;
        $component->formTypeClass = 'App\\Form\\CustomFormType';

        $component->exposeInstantiateForm();

        $this->assertArrayNotHasKey(
            'is_root',
            $capturedOptions,
            'is_root must only be passed for DynamicEntityFormType'
        );
    }

    public function testCustomFormTypeStillDisablesCsrf(): void
    {
        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass   = AdminFormFixtureEntity::class;
        $component->formTypeClass = 'App\\Form\\CustomFormType';

        $component->exposeInstantiateForm();

        $this->assertFalse($capturedOptions['csrf_protection']);
    }

    // ── New-entity path (no entityId) ──────────────────────────────────────────

    public function testNewEntityInstantiatesWithoutDatabaseLookup(): void
    {
        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass   = AdminFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;
        // entityId intentionally left null

        $this->em->expects($this->never())->method('find');

        $component->exposeInstantiateForm();
    }

    // ── Edit-entity path (with entityId) ──────────────────────────────────────

    public function testExistingEntityIsLoadedFromEmWhenEntityIdIsSet(): void
    {
        $entity = new AdminFormFixtureEntity();
        $this->em->expects($this->once())
            ->method('find')
            ->with(AdminFormFixtureEntity::class, 7)
            ->willReturn($entity);

        $capturedOptions = [];
        $component = $this->makeComponent($capturedOptions);

        $component->entityClass   = AdminFormFixtureEntity::class;
        $component->formTypeClass = DynamicEntityFormType::class;
        $component->entityId      = 7;

        $component->exposeInstantiateForm();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Build a testable subclass of AdminEntityForm whose createForm() captures
     * the options array instead of delegating to the Symfony container.
     *
     * @param array<string, mixed> $capturedOptions Reference filled on each createForm() call.
     */
    private function makeComponent(array &$capturedOptions): TestableAdminEntityForm
    {
        $dummyForm = $this->createMock(FormInterface::class);
        $component = new TestableAdminEntityForm($this->em, $capturedOptions, $dummyForm);

        return $component;
    }
}

// ── Test doubles ───────────────────────────────────────────────────────────────

/**
 * Subclass that overrides createForm() to capture options without needing
 * the Symfony container, and exposes instantiateForm() publicly for testing.
 */
class TestableAdminEntityForm extends AdminEntityForm
{
    /** @var array<string, mixed> */
    private array $capturedOptionsRef;

    /** @var FormInterface<object|null> $dummyForm */
    private FormInterface $dummyForm;

    /**
     * @param array<string, mixed> $capturedOptionsRef
     * @param FormInterface<object|null> $dummyForm
     */
    public function __construct(EntityManagerInterface $em, array &$capturedOptionsRef, FormInterface $dummyForm)
    {
        parent::__construct($em);
        $this->capturedOptionsRef = &$capturedOptionsRef;
        $this->dummyForm = $dummyForm;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function createForm(string $type, mixed $data = null, array $options = []): FormInterface
    {
        $this->capturedOptionsRef = $options;
        return $this->dummyForm; // @phpstan-ignore return.type
    }

    /** @return FormInterface<object|null> */
    public function exposeInstantiateForm(): FormInterface
    {
        return $this->instantiateForm();
    }
}

// ── Inline fixture ─────────────────────────────────────────────────────────────

class AdminFormFixtureEntity
{
    private string $name = '';

    public function getName(): string { return $this->name; }
}
