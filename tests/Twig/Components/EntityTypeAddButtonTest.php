<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Twig\Components\EntityTypeAddButton;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @group inline-add
 */
#[CoversClass(EntityTypeAddButton::class)]
#[UsesClass(Admin::class)]
#[Group('inline-add')]
class EntityTypeAddButtonTest extends TestCase
{
    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    /** @var EntityDiscoveryService&MockObject */
    private EntityDiscoveryService $entityDiscovery;

    protected function setUp(): void
    {
        $this->authChecker     = $this->createMock(AuthorizationCheckerInterface::class);
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
    }

    private function makeButton(string $entityClass = 'App\Entity\Category'): EntityTypeAddButton
    {
        $button                    = new EntityTypeAddButton($this->authChecker, $this->entityDiscovery);
        $button->targetEntityClass = $entityClass;

        return $button;
    }

    // ── canCreate() ────────────────────────────────────────────────────────────

    /** @test */
    public function canCreateReturnsTrueWhenUserHasAdminNewPermission(): void
    {
        $this->authChecker->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_NEW, 'Category')
            ->willReturn(true);

        $this->assertTrue($this->makeButton()->canCreate());
    }

    /** @test */
    public function canCreateReturnsFalseWhenUserLacksPermission(): void
    {
        $this->authChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($this->makeButton()->canCreate());
    }

    /** @test */
    public function canCreateReturnsFalseWhenTargetEntityClassIsEmpty(): void
    {
        $button = new EntityTypeAddButton($this->authChecker, $this->entityDiscovery);
        // targetEntityClass left empty (default)

        $this->assertFalse($button->canCreate());
    }

    /** @test */
    public function canCreateReturnsFalseWhenAuthCheckerThrows(): void
    {
        $this->authChecker->method('isGranted')
            ->willThrowException(new \RuntimeException('No token'));

        $this->assertFalse($this->makeButton()->canCreate());
    }

    // ── getEntityShortName() ───────────────────────────────────────────────────

    /** @test */
    public function getEntityShortNameExtractsLastSegmentOfFqcn(): void
    {
        $this->assertSame('Category', $this->makeButton('App\Entity\Category')->getEntityShortName());
    }

    /** @test */
    public function getEntityShortNameWorksWithoutNamespace(): void
    {
        $this->assertSame('Category', $this->makeButton('Category')->getEntityShortName());
    }

    /** @test */
    public function getEntityShortNameWorksWithDeeplyNestedNamespace(): void
    {
        $this->assertSame(
            'ProductCategory',
            $this->makeButton('App\Domain\Catalog\Entity\ProductCategory')->getEntityShortName(),
        );
    }

    // ── getEntityLabel() ───────────────────────────────────────────────────────

    /** @test */
    public function getEntityLabelReturnsAdminAttributeLabel(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(label: 'Product Categories'));

        $this->assertSame('Product Categories', $this->makeButton()->getEntityLabel());
    }

    /** @test */
    public function getEntityLabelFallsBackToShortNameWhenNoAdminAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);

        $this->assertSame('Category', $this->makeButton()->getEntityLabel());
    }

    /** @test */
    public function getEntityLabelFallsBackToShortNameWhenAdminAttributeHasNoLabel(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin()); // label defaults to null

        $this->assertSame('Category', $this->makeButton()->getEntityLabel());
    }

    // ── getFormTypeClass() ────────────────────────────────────────────────────

    /** @test */
    public function getFormTypeClassReturnsExplicitFormTypeFromAdminAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(formType: 'App\Form\CategoryFormType'));

        $this->assertSame('App\Form\CategoryFormType', $this->makeButton()->getFormTypeClass());
    }

    /** @test */
    public function getFormTypeClassFallsBackToDynamicEntityFormTypeWhenNoAdminAttribute(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);

        $this->assertSame(DynamicEntityFormType::class, $this->makeButton()->getFormTypeClass());
    }

    /** @test */
    public function getFormTypeClassFallsBackToDynamicEntityFormTypeWhenAdminAttributeHasNoFormType(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin()); // formType defaults to null

        $this->assertSame(DynamicEntityFormType::class, $this->makeButton()->getFormTypeClass());
    }

    // ── Permission uses short name, not FQCN ──────────────────────────────────

    /** @test */
    public function canCreateChecksVoterWithShortNameNotFqcn(): void
    {
        $this->authChecker->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_NEW, 'ProductCategory') // short name, NOT FQCN
            ->willReturn(true);

        $this->makeButton('App\Entity\ProductCategory')->canCreate();
    }
}
