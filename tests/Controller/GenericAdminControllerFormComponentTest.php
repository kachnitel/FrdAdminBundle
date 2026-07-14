<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Controller\GenericAdminController;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;

#[CoversClass(GenericAdminController::class)]
#[UsesClass(Admin::class)]
#[UsesClass(AdminColumn::class)]
#[Group('auto-form')]
#[AllowMockObjectsWithoutExpectations]
final class GenericAdminControllerFormComponentTest extends TestCase
{
    /** @var EntityDiscoveryService&MockObject */
    private EntityDiscoveryService $entityDiscovery;

    /** @var FormRegistryInterface&MockObject */
    private FormRegistryInterface $formRegistry;

    protected function setUp(): void
    {
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->formRegistry    = $this->createMock(FormRegistryInterface::class);
    }

    private function makeController(): GenericAdminController
    {
        return new GenericAdminController(
            em:               $this->createStub(EntityManagerInterface::class),
            entityDiscovery:  $this->entityDiscovery,
            entityNamespace:  'App\\Entity\\',
            formNamespace:    'App\\Form\\',
            formSuffix:       'FormType',
            dataSourceRegistry: $this->createStub(DataSourceRegistry::class),
            formRegistry:     $this->formRegistry,
        );
    }

    // ── getFormComponentName() ─────────────────────────────────────────────────

    // ── Priority 1: explicit formComponent ────────────────────────────────────

    public function testExplicitFormComponentTakesPriority(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureWithFormComponent::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(formComponent: 'App:Form:Custom'));
        $this->formRegistry->method('hasType')->willReturn(true); // would normally win

        $component = $this->callGetFormComponentName('CtrlFixtureWithFormComponent');

        $this->assertSame('App:Form:Custom', $component);
    }

    // ── Priority 2: registered FormType ───────────────────────────────────────

    public function testFormTypeWinsOverAutoForm(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureInlineEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(true);

        $component = $this->callGetFormComponentName('CtrlFixtureInlineEdit');

        $this->assertSame('K:Admin:EntityForm', $component);
    }

    // ── Priority 3: auto-form via enableInlineEdit ────────────────────────────

    // public function testAutoFormUsedWhenNoFormTypeButEnableInlineEditTrue(): void
    // {
    //     $this->entityDiscovery->method('resolveEntityClass')
    //         ->willReturn(CtrlFixtureInlineEdit::class);
    //     $this->entityDiscovery->method('getAdminAttribute')
    //         ->willReturn(new Admin(enableInlineEdit: true));
    //     $this->formRegistry->method('hasType')->willReturn(false);

    //     $component = $this->callGetFormComponentName('CtrlFixtureInlineEdit');

    //     $this->assertSame('K:Admin:AutoEntityForm', $component);
    // }

    // ── Priority 3: auto-form via per-column editable:true ───────────────────

    // public function testAutoFormUsedWhenNoFormTypeButEditableColumn(): void
    // {
    //     $this->entityDiscovery->method('resolveEntityClass')
    //         ->willReturn(CtrlFixtureEditableCol::class);
    //     $this->entityDiscovery->method('getAdminAttribute')
    //         ->willReturn(new Admin());
    //     $this->formRegistry->method('hasType')->willReturn(false);

    //     $component = $this->callGetFormComponentName('CtrlFixtureEditableCol');

        // $this->assertSame('K:Admin:AutoEntityForm', $component);
    // }

    // ── Priority 4: DynamicEntityFormType / fallback → still EntityForm component ──

    public function testFallsBackToEntityFormWhenNoFormTypeAndNoEditableFields(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureNoEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $component = $this->callGetFormComponentName('CtrlFixtureNoEdit');

        // Component is always EntityForm — what differs is the formTypeClass
        $this->assertSame('K:Admin:EntityForm', $component);
    }

    // ── editable:false does not trigger auto-form ─────────────────────────────

    public function testEditableFalseDoesNotTriggerAutoForm(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureEditableFalse::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $component = $this->callGetFormComponentName('CtrlFixtureEditableFalse');

        $this->assertSame('K:Admin:EntityForm', $component);
    }

    // ── entity not resolvable ─────────────────────────────────────────────────

    public function testFallsBackGracefullyWhenEntityClassNotResolvable(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);
        $this->formRegistry->method('hasType')->willReturn(false);

        $component = $this->callGetFormComponentName('Unknown');

        $this->assertSame('K:Admin:EntityForm', $component);
    }

    // ── getFormType() ──────────────────────────────────────────────────────────

    public function testGetFormTypeReturnsExplicitFormTypeFromAdminAttribute(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureNoEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(formType: 'App\\Form\\CustomType'));
        $this->formRegistry->method('hasType')->willReturn(false);

        $formType = $this->callGetFormType('CtrlFixtureNoEdit');

        $this->assertSame('App\\Form\\CustomType', $formType);
    }

    public function testGetFormTypeReturnsCustomFormTypeWhenRegistered(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureNoEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());
        $this->formRegistry->method('hasType')
            ->willReturnCallback(fn (string $type): bool => $type === 'App\\Form\\CtrlFixtureNoEditFormType');

        $formType = $this->callGetFormType('CtrlFixtureNoEdit');

        $this->assertSame('App\\Form\\CtrlFixtureNoEditFormType', $formType);
    }

    public function testGetFormTypeFallsBackToDynamicEntityFormTypeForDoctrineEntity(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureNoEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());
        // Custom type not registered
        $this->formRegistry->method('hasType')->willReturn(false);

        $formType = $this->callGetFormType('CtrlFixtureNoEdit');

        $this->assertSame(DynamicEntityFormType::class, $formType);
    }

    public function testGetFormTypeReturnsCustomTypeStringWhenEntityDoesNotResolve(): void
    {
        // Non-Doctrine / unknown entity
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);
        $this->formRegistry->method('hasType')->willReturn(false);

        $formType = $this->callGetFormType('Unknown');

        // Falls back to the default convention string (caller handles missing type)
        $this->assertSame('App\\Form\\UnknownFormType', $formType);
    }

    public function testExplicitFormTypeAttributeTakesPriorityOverCustomTypeInRegistry(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureNoEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(formType: 'App\\Form\\OverrideType'));
        // Even if the convention type is registered, the attribute wins
        $this->formRegistry->method('hasType')->willReturn(true);

        $formType = $this->callGetFormType('CtrlFixtureNoEdit');

        $this->assertSame('App\\Form\\OverrideType', $formType);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function callGetFormComponentName(string $class): string
    {
        $method = new \ReflectionMethod($this->makeController(), 'getFormComponentName');
        return $method->invoke($this->makeController(), $class);
    }

    private function callGetFormType(string $class): string
    {
        $method = new \ReflectionMethod($this->makeController(), 'getFormType');
        return $method->invoke($this->makeController(), $class);
    }
}

// ── Fixtures ───────────────────────────────────────────────────────────────────

#[Admin(label: 'With Form Component', formComponent: 'App:Form:Custom')]
class CtrlFixtureWithFormComponent {}

#[Admin(label: 'Inline Edit', enableInlineEdit: true)]
class CtrlFixtureInlineEdit
{
    private string $title = '';
}

#[Admin(label: 'Editable Col')]
class CtrlFixtureEditableCol
{
    #[AdminColumn(editable: true)]
    private string $title = '';
}

#[Admin(label: 'No Edit')]
class CtrlFixtureNoEdit
{
    private string $title = '';
}

#[Admin(label: 'Editable False')]
class CtrlFixtureEditableFalse
{
    #[AdminColumn(editable: false)]
    private string $title = '';
}
