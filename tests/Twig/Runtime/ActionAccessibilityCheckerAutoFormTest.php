<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Runtime;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Twig\Runtime\ActionAccessibilityChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[CoversClass(ActionAccessibilityChecker::class)]
#[UsesClass(Admin::class)]
#[Group('auto-form')]
class ActionAccessibilityCheckerAutoFormTest extends TestCase
{
    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    /** @var EntityDiscoveryService&MockObject */
    private EntityDiscoveryService $entityDiscovery;

    /** @var FormRegistryInterface&MockObject */
    private FormRegistryInterface $formRegistry;

    protected function setUp(): void
    {
        $this->authChecker     = $this->createMock(AuthorizationCheckerInterface::class);
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->formRegistry    = $this->createMock(FormRegistryInterface::class);
    }

    private function makeChecker(): ActionAccessibilityChecker
    {
        return new ActionAccessibilityChecker(
            authChecker: $this->authChecker,
            entityDiscovery: $this->entityDiscovery,
            formRegistry: $this->formRegistry,
            formNamespace: 'App\\Form\\',
            formSuffix: 'FormType',
            entityNamespace: 'App\\Entity\\',
        );
    }

    // ── No form at all (DynamicEntityFormType also not registered) ────────────

    /**
     * Edge case: no form available and DynamicEntityFormType itself is not registered.
     * In practice this can't happen in the bundle, but verifies the fallback logic.
     */
    public function testNewBlockedWhenNoFormTypeAndNoInlineEditAndNoDynamicType(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerNoEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse($this->makeChecker()->isActionAccessible('AcCheckerNoEditEntity', 'new', true));
    }

    public function testEditBlockedWhenNoFormTypeAndNoInlineEditAndNoDynamicType(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerNoEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse($this->makeChecker()->isActionAccessible('AcCheckerNoEditEntity', 'edit', true));
    }

    // ── DynamicEntityFormType as universal Doctrine fallback ──────────────────

    /**
     * With DynamicEntityFormType registered and a resolvable Doctrine entity,
     * new/edit are accessible even without a hand-written FormType or inline-edit config.
     */
    public function testNewAccessibleViaDynamicFormTypeWhenNoCustomFormType(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerNoEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')
            ->willReturnCallback(fn (string $type): bool => $type === DynamicEntityFormType::class);

        $this->assertTrue($this->makeChecker()->isActionAccessible('AcCheckerNoEditEntity', 'new', true));
    }

    public function testEditAccessibleViaDynamicFormTypeWhenNoCustomFormType(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerNoEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')
            ->willReturnCallback(fn (string $type): bool => $type === DynamicEntityFormType::class);

        $this->assertTrue($this->makeChecker()->isActionAccessible('AcCheckerNoEditEntity', 'edit', true));
    }

    public function testDynamicFormTypeNotUsedWhenEntityDoesNotResolve(): void
    {
        // Non-Doctrine / unknown entity — entity class doesn't resolve
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);
        // DynamicEntityFormType IS registered, but entity doesn't resolve
        $this->formRegistry->method('hasType')
            ->willReturnCallback(fn (string $type): bool => $type === DynamicEntityFormType::class);

        $this->assertFalse($this->makeChecker()->isActionAccessible('Unknown', 'new', true));
    }

    // ── FormType satisfies both new and edit ───────────────────────────────────

    public function testNewAccessibleWhenFormTypeExists(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerNoEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(true);

        $this->assertTrue($this->makeChecker()->isActionAccessible('AcCheckerNoEditEntity', 'new', true));
    }

    public function testEditAccessibleWhenFormTypeExists(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerNoEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(true);

        $this->assertTrue($this->makeChecker()->isActionAccessible('AcCheckerNoEditEntity', 'edit', true));
    }

    // ── editable:false only → AutoForm doesn't activate; DynamicEntityFormType still covers it ──

    public function testNewAccessibleViaDynamicFormTypeWhenOnlyEditableFalseProperties(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerEditableFalseEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')
            ->willReturnCallback(fn (string $type): bool => $type === DynamicEntityFormType::class);

        // AutoForm says no (editable:false), but DynamicEntityFormType covers the entity.
        $this->assertTrue($this->makeChecker()->isActionAccessible('AcCheckerEditableFalseEntity', 'new', true));
    }

    // ── Route missing blocks regardless of form ────────────────────────────────

    public function testReturnsFalseWhenRouteDoesNotExist(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerInlineEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse($this->makeChecker()->isActionAccessible('AcCheckerInlineEditEntity', 'new', false));
        $this->assertFalse($this->makeChecker()->isActionAccessible('AcCheckerInlineEditEntity', 'edit', false));
    }

    // ── Voter denied blocks regardless of form ─────────────────────────────────

    public function testReturnsFalseWhenVoterDenies(): void
    {
        $this->authChecker->method('isGranted')->willReturn(false);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerInlineEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse($this->makeChecker()->isActionAccessible('AcCheckerInlineEditEntity', 'new', true));
        $this->assertFalse($this->makeChecker()->isActionAccessible('AcCheckerInlineEditEntity', 'edit', true));
    }

    // ── Non-form actions unaffected ───────────────────────────────────────────

    #[DataProvider('nonFormActionsProvider')]
    public function testNonFormActionsUnaffectedByFormAvailability(string $action): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerNoEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertTrue($this->makeChecker()->isActionAccessible('AcCheckerNoEditEntity', $action, true));
    }

    /** @return array<string, array{string}> */
    public static function nonFormActionsProvider(): array
    {
        return [
            'index'  => ['index'],
            'show'   => ['show'],
            'delete' => ['delete'],
        ];
    }
}

// ── Fixtures ───────────────────────────────────────────────────────────────────

#[Admin(label: 'No Edit')]
class AcCheckerNoEditEntity
{
    private string $title = '';
}

#[Admin(label: 'Inline Edit', enableInlineEdit: true)]
class AcCheckerInlineEditEntity
{
    private string $title = '';
}

#[Admin(label: 'Editable Col')]
class AcCheckerEditableColEntity
{
    #[AdminColumn(editable: true)]
    private string $title = '';
}

#[Admin(label: 'Editable False')]
class AcCheckerEditableFalseEntity
{
    #[AdminColumn(editable: false)]
    private string $title = '';
}
