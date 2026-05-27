<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Controller\GenericAdminController;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;

/**
 * @covers \Kachnitel\AdminBundle\Controller\GenericAdminController
 * @group auto-form
 */
#[UsesClass(Admin::class)]
#[UsesClass(AdminColumn::class)]
class GenericAdminControllerFormComponentTest extends TestCase
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
            em:               $this->createMock(EntityManagerInterface::class),
            entityDiscovery:  $this->entityDiscovery,
            entityNamespace:  'App\\Entity\\',
            formNamespace:    'App\\Form\\',
            formSuffix:       'FormType',
            dataSourceRegistry: $this->createMock(DataSourceRegistry::class),
            formRegistry:     $this->formRegistry,
        );
    }

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

    public function testAutoFormUsedWhenNoFormTypeButEnableInlineEdit(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureInlineEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(false);

        $component = $this->callGetFormComponentName('CtrlFixtureInlineEdit');

        $this->assertSame('K:Admin:AutoEntityForm', $component);
    }

    // ── Priority 3: auto-form via per-column editable:true ───────────────────

    public function testAutoFormUsedWhenNoFormTypeButEditableColumn(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureEditableCol::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $component = $this->callGetFormComponentName('CtrlFixtureEditableCol');

        $this->assertSame('K:Admin:AutoEntityForm', $component);
    }

    // ── Priority 4: fallback ──────────────────────────────────────────────────

    public function testFallsBackToEntityFormWhenNoFormTypeAndNoEditableFields(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturn(CtrlFixtureNoEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $component = $this->callGetFormComponentName('CtrlFixtureNoEdit');

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

    // ── Helper ────────────────────────────────────────────────────────────────

    private function callGetFormComponentName(string $class): string
    {
        $controller = $this->makeController();

        $method = new \ReflectionMethod($controller, 'getFormComponentName');

        return $method->invoke($controller, $class);
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
