<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Runtime;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Twig\Runtime\ActionAccessibilityChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @covers \Kachnitel\AdminBundle\Twig\Runtime\ActionAccessibilityChecker
 * @group auto-form
 */
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

    // ── No form, no inline edit → both new and edit blocked ───────────────────

    public function testNewBlockedWhenNoFormTypeAndNoInlineEdit(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerNoEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse($this->makeChecker()->isActionAccessible('AcCheckerNoEditEntity', 'new', true));
    }

    public function testEditBlockedWhenNoFormTypeAndNoInlineEdit(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerNoEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse($this->makeChecker()->isActionAccessible('AcCheckerNoEditEntity', 'edit', true));
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

    // ── enableInlineEdit satisfies both new and edit ───────────────────────────

    public function testNewAccessibleWhenNoFormTypeButEnableInlineEditTrue(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerInlineEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertTrue($this->makeChecker()->isActionAccessible('AcCheckerInlineEditEntity', 'new', true));
    }

    public function testEditAccessibleWhenNoFormTypeButEnableInlineEditTrue(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerInlineEditEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertTrue($this->makeChecker()->isActionAccessible('AcCheckerInlineEditEntity', 'edit', true));
    }

    // ── Per-property editable:true satisfies both new and edit ────────────────

    public function testNewAccessibleWhenPropertyHasEditableTrue(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerEditableColEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertTrue($this->makeChecker()->isActionAccessible('AcCheckerEditableColEntity', 'new', true));
    }

    public function testEditAccessibleWhenPropertyHasEditableTrue(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerEditableColEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertTrue($this->makeChecker()->isActionAccessible('AcCheckerEditableColEntity', 'edit', true));
    }

    // ── editable:false only → still no auto-form ──────────────────────────────

    public function testNewBlockedWhenOnlyEditableFalseProperties(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerEditableFalseEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse($this->makeChecker()->isActionAccessible('AcCheckerEditableFalseEntity', 'new', true));
    }

    public function testEditBlockedWhenOnlyEditableFalseProperties(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(AcCheckerEditableFalseEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse($this->makeChecker()->isActionAccessible('AcCheckerEditableFalseEntity', 'edit', true));
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
