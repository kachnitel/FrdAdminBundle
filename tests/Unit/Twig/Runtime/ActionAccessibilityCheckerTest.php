<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Twig\Runtime\ActionAccessibilityChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @covers \Kachnitel\AdminBundle\Twig\Runtime\ActionAccessibilityChecker
 */
class ActionAccessibilityCheckerTest extends TestCase
{
    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    /** @var EntityDiscoveryService&MockObject */
    private EntityDiscoveryService $entityDiscovery;

    /** @var FormRegistryInterface&MockObject */
    private FormRegistryInterface $formRegistry;

    private ActionAccessibilityChecker $checker;

    protected function setUp(): void
    {
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->formRegistry = $this->createMock(FormRegistryInterface::class);

        $this->checker = new ActionAccessibilityChecker(
            authChecker: $this->authChecker,
            entityDiscovery: $this->entityDiscovery,
            formRegistry: $this->formRegistry,
            formNamespace: 'App\\Form\\',
            formSuffix: 'FormType',
            entityNamespace: 'App\\Entity\\',
        );
    }

    // ── isActionAccessible: route check ───────────────────────────────────────

    /** @test */
    public function returnsFalseWhenRouteDoesNotExist(): void
    {
        $this->assertFalse(
            $this->checker->isActionAccessible('Product', 'show', routeExists: false)
        );
    }

    // ── isActionAccessible: voter check ──────────────────────────────────────

    /** @test */
    public function returnsFalseWhenVoterDeniesAccess(): void
    {
        $this->authChecker->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_SHOW, 'Product')
            ->willReturn(false);

        $this->assertFalse(
            $this->checker->isActionAccessible('Product', 'show', routeExists: true)
        );
    }

    /** @test */
    public function returnsTrueWhenVoterGrantsAndNoFormRequired(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $this->assertTrue(
            $this->checker->isActionAccessible('Product', 'show', routeExists: true)
        );
    }

    /** @test */
    public function returnsTrueWithNullAuthCheckerGrantsEveryone(): void
    {
        $checker = new ActionAccessibilityChecker(
            authChecker: null,
            entityDiscovery: $this->entityDiscovery,
            formRegistry: $this->formRegistry,
            formNamespace: 'App\\Form\\',
            formSuffix: 'FormType',
            entityNamespace: 'App\\Entity\\',
        );

        $this->assertTrue(
            $checker->isActionAccessible('Product', 'show', routeExists: true)
        );
    }

    // ── isActionAccessible: form check for new/edit ───────────────────────────

    /** @test */
    public function returnsFalseForNewActionWhenFormTypeMissing(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);
        $this->formRegistry->method('hasType')->with('App\\Form\\ProductFormType')->willReturn(false);

        $this->assertFalse(
            $this->checker->isActionAccessible('Product', 'new', routeExists: true)
        );
    }

    /** @test */
    public function returnsFalseForEditActionWhenFormTypeMissing(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);
        $this->formRegistry->method('hasType')->willReturn(false);

        $this->assertFalse(
            $this->checker->isActionAccessible('Product', 'edit', routeExists: true)
        );
    }

    /** @test */
    public function returnsTrueForNewActionWhenFormTypeExists(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);
        $this->formRegistry->method('hasType')->with('App\\Form\\ProductFormType')->willReturn(true);

        $this->assertTrue(
            $this->checker->isActionAccessible('Product', 'new', routeExists: true)
        );
    }

    /** @test */
    public function showActionDoesNotRequireFormType(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);
        // formRegistry should NOT be called for show/delete/index
        $this->formRegistry->expects($this->never())->method('hasType');

        $this->assertTrue(
            $this->checker->isActionAccessible('Product', 'show', routeExists: true)
        );
    }

    /** @test */
    public function deleteActionDoesNotRequireFormType(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->formRegistry->expects($this->never())->method('hasType');

        $this->assertTrue(
            $this->checker->isActionAccessible('Product', 'delete', routeExists: true)
        );
    }

    // ── voter attribute mapping ───────────────────────────────────────────────

    /** @test */
    public function indexActionUsesAdminIndexVoterAttribute(): void
    {
        $this->authChecker->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_INDEX, 'Product')
            ->willReturn(true);

        $this->checker->isActionAccessible('Product', 'index', routeExists: true);
    }

    /** @test */
    public function editActionUsesAdminEditVoterAttribute(): void
    {
        $this->authChecker->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_EDIT, 'Product')
            ->willReturn(true);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);
        $this->formRegistry->method('hasType')->willReturn(true);

        $this->checker->isActionAccessible('Product', 'edit', routeExists: true);
    }

    /** @test */
    public function archiveActionUsesAdminArchiveVoterAttribute(): void
    {
        $this->authChecker->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_ARCHIVE, 'Product')
            ->willReturn(true);

        $this->checker->isActionAccessible('Product', 'archive', routeExists: true);
    }

    /** @test */
    public function unarchiveActionUsesAdminArchiveVoterAttribute(): void
    {
        $this->authChecker->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_ARCHIVE, 'Product')
            ->willReturn(true);

        $this->checker->isActionAccessible('Product', 'unarchive', routeExists: true);
    }

    // ── getVoterAttribute ─────────────────────────────────────────────────────

    /** @test */
    public function getVoterAttributeReturnsCorrectConstantForKnownActions(): void
    {
        $this->assertSame(AdminEntityVoter::ADMIN_INDEX, $this->checker->getVoterAttribute('index'));
        $this->assertSame(AdminEntityVoter::ADMIN_SHOW, $this->checker->getVoterAttribute('show'));
        $this->assertSame(AdminEntityVoter::ADMIN_NEW, $this->checker->getVoterAttribute('new'));
        $this->assertSame(AdminEntityVoter::ADMIN_EDIT, $this->checker->getVoterAttribute('edit'));
        $this->assertSame(AdminEntityVoter::ADMIN_ARCHIVE, $this->checker->getVoterAttribute('archive'));
        $this->assertSame(AdminEntityVoter::ADMIN_ARCHIVE, $this->checker->getVoterAttribute('unarchive'));
        $this->assertSame(AdminEntityVoter::ADMIN_DELETE, $this->checker->getVoterAttribute('delete'));
    }

    /** @test */
    public function getVoterAttributeReturnsNullForUnknownAction(): void
    {
        $this->assertNull($this->checker->getVoterAttribute('unknown_action'));
    }

    // ── mapVoterAttributeToAction ─────────────────────────────────────────────

    /** @test */
    public function mapVoterAttributeToActionReturnsCorrectActionName(): void
    {
        $this->assertSame('index', $this->checker->mapVoterAttributeToAction(AdminEntityVoter::ADMIN_INDEX));
        $this->assertSame('show', $this->checker->mapVoterAttributeToAction(AdminEntityVoter::ADMIN_SHOW));
        $this->assertSame('edit', $this->checker->mapVoterAttributeToAction(AdminEntityVoter::ADMIN_EDIT));
        $this->assertSame('delete', $this->checker->mapVoterAttributeToAction(AdminEntityVoter::ADMIN_DELETE));
    }

    // ── custom formType from Admin attribute ──────────────────────────────────

    /** @test */
    public function usesCustomFormTypeFromAdminAttributeWhenAvailable(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true);

        $adminAttr = $this->createMock(\Kachnitel\AdminBundle\Attribute\Admin::class);
        $adminAttr->method('getFormType')->willReturn('App\\Form\\CustomProductFormType');

        $this->entityDiscovery->method('resolveEntityClass')->willReturn('App\\Entity\\Product');
        $this->entityDiscovery->method('getAdminAttribute')->willReturn($adminAttr);

        $this->formRegistry->expects($this->once())
            ->method('hasType')
            ->with('App\\Form\\CustomProductFormType')
            ->willReturn(true);

        $this->assertTrue(
            $this->checker->isActionAccessible('Product', 'edit', routeExists: true)
        );
    }

    // ── null formRegistry ─────────────────────────────────────────────────────

    /** @test */
    public function returnsTrueForNewActionWhenFormRegistryIsNull(): void
    {
        $checker = new ActionAccessibilityChecker(
            authChecker: null,
            entityDiscovery: null,
            formRegistry: null,
            formNamespace: 'App\\Form\\',
            formSuffix: 'FormType',
            entityNamespace: 'App\\Entity\\',
        );

        $this->assertTrue(
            $checker->isActionAccessible('Product', 'new', routeExists: true)
        );
    }
}
