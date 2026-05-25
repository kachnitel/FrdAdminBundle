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

        // Grant all voter checks by default.
        // $this->authChecker->method('isGranted')->willReturn(true);
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

    // ── new action requires FormType ───────────────────────────────────────────

    public function testNewRequiresFormTypeEvenWhenEnableInlineEditIsTrue(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(EntityWithInlineEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(false);

        $checker = $this->makeChecker();

        $this->assertFalse($checker->isActionAccessible('EntityWithInlineEdit', 'new', true));
    }

    public function testNewAccessibleWhenFormTypeExists(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(EntityWithInlineEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(true);

        $checker = $this->makeChecker();

        $this->assertTrue($checker->isActionAccessible('EntityWithInlineEdit', 'new', true));
    }

    // ── edit action: FormType path ─────────────────────────────────────────────

    public function testEditAccessibleWhenFormTypeExists(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(EntityWithNoInlineEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(true);

        $checker = $this->makeChecker();

        $this->assertTrue($checker->isActionAccessible('EntityWithNoInlineEdit', 'edit', true));
    }

    public function testEditInaccessibleWhenNoFormTypeAndNoInlineEdit(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(EntityWithNoInlineEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $checker = $this->makeChecker();

        $this->assertFalse($checker->isActionAccessible('EntityWithNoInlineEdit', 'edit', true));
    }

    // ── edit action: auto-form path ────────────────────────────────────────────

    public function testEditAccessibleWhenNoFormTypeButEnableInlineEditTrue(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(EntityWithInlineEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(false);

        $checker = $this->makeChecker();

        $this->assertTrue($checker->isActionAccessible('EntityWithInlineEdit', 'edit', true));
    }

    public function testEditAccessibleWhenNoFormTypeButPropertyHasEditableTrue(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(EntityWithEditableColumn::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());  // enableInlineEdit: false
        $this->formRegistry->method('hasType')->willReturn(false);

        $checker = $this->makeChecker();

        $this->assertTrue($checker->isActionAccessible('EntityWithEditableColumn', 'edit', true));
    }

    public function testEditInaccessibleWhenPropertyHasEditableFalseOnly(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(EntityWithEditableFalse::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $checker = $this->makeChecker();

        $this->assertFalse($checker->isActionAccessible('EntityWithEditableFalse', 'edit', true));
    }

    // ── route not existing ─────────────────────────────────────────────────────

    public function testReturnsFalseWhenRouteDoesNotExist(): void
    {
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(EntityWithInlineEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(false);

        $checker = $this->makeChecker();

        $this->assertFalse($checker->isActionAccessible('EntityWithInlineEdit', 'edit', false));
    }

    // ── voter denied ──────────────────────────────────────────────────────────

    public function testReturnsFalseWhenVoterDenies(): void
    {
        $this->authChecker->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_EDIT, 'EntityWithInlineEdit')
            ->willReturn(false);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(EntityWithInlineEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(enableInlineEdit: true));
        $this->formRegistry->method('hasType')->willReturn(false);

        $checker = $this->makeChecker();

        self::assertFalse($checker->isActionAccessible('EntityWithInlineEdit', 'edit', true));
    }

    // ── non-edit/new actions unaffected ───────────────────────────────────────

    /**
     * @param string $action
     */
    #[DataProvider('nonFormActionsProvider')]
    public function testNonFormActionsUnaffectedByFormAvailability(string $action): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->entityDiscovery->method('resolveEntityClass')->willReturn(EntityWithNoInlineEdit::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->formRegistry->method('hasType')->willReturn(false);

        $checker = $this->makeChecker();

        $this->assertTrue($checker->isActionAccessible('EntityWithNoInlineEdit', $action, true));
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

// ── Fixture classes ────────────────────────────────────────────────────────────

#[Admin(label: 'With Inline Edit', enableInlineEdit: true)]
class EntityWithInlineEdit
{
    private string $title = '';
}

#[Admin(label: 'No Inline Edit')]
class EntityWithNoInlineEdit
{
    private string $title = '';
}

#[Admin(label: 'Editable Column')]
class EntityWithEditableColumn
{
    #[AdminColumn(editable: true)]
    private string $title = '';
}

#[Admin(label: 'Editable False')]
class EntityWithEditableFalse
{
    #[AdminColumn(editable: false)]
    private string $title = '';
}
