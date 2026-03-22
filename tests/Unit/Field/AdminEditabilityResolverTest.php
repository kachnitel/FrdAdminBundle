<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Field;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Field\AdminEditabilityResolver;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @covers \Kachnitel\AdminBundle\Field\AdminEditabilityResolver
 * @group inline-edit
 */
class AdminEditabilityResolverTest extends TestCase
{
    /** @var AttributeHelper&MockObject */
    private AttributeHelper $attributeHelper;

    /** @var RowActionExpressionLanguage&MockObject */
    private RowActionExpressionLanguage $expressionLanguage;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    /** @var PropertyAccessorInterface&MockObject */
    private PropertyAccessorInterface $propertyAccessor;

    private AdminEditabilityResolver $resolver;

    protected function setUp(): void
    {
        $this->attributeHelper    = $this->createMock(AttributeHelper::class);
        $this->expressionLanguage = $this->createMock(RowActionExpressionLanguage::class);
        $this->authChecker        = $this->createMock(AuthorizationCheckerInterface::class);
        $this->propertyAccessor   = $this->createMock(PropertyAccessorInterface::class);

        $this->resolver = new AdminEditabilityResolver(
            $this->attributeHelper,
            $this->expressionLanguage,
            $this->authChecker,
            $this->propertyAccessor,
        );
    }

    // ── editable: false — short-circuits everything ─────────────────────────

    /** @test */
    public function editableFalseReturnsFalseWithoutCheckingAnythingElse(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: false));

        // Should never reach voter or writable checks
        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');
        $this->attributeHelper->expects($this->never())->method('getAttribute');

        $this->assertFalse($this->resolver->canEdit($entity, 'name'));
    }

    // ── editable: 'expression' ──────────────────────────────────────────────

    /** @test */
    public function expressionFalseReturnsFalseWithoutVoterCheck(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: 'entity.active'));

        $this->expressionLanguage->method('evaluate')->willReturn(false);

        $this->authChecker->expects($this->never())->method('isGranted');

        $this->assertFalse($this->resolver->canEdit($entity, 'name'));
    }

    /** @test */
    public function expressionTrueStillChecksVoterAndWritable(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: 'entity.active'));

        $this->expressionLanguage->method('evaluate')->willReturn(true);
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($this->resolver->canEdit($entity, 'name'));
    }

    /** @test */
    public function expressionTrueButVoterDeniesReturnsFalse(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: 'entity.active'));

        $this->expressionLanguage->method('evaluate')->willReturn(true);
        $this->authChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($this->resolver->canEdit($entity, 'name'));
    }

    // ── editable: true — bypasses entity default ────────────────────────────

    /** @test */
    public function editableTrueBypassesEntityFlagAndChecksVoterAndWritable(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: true));

        // Entity-level attribute must NOT be consulted
        $this->attributeHelper->expects($this->never())->method('getAttribute');

        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($this->resolver->canEdit($entity, 'name'));
    }

    /** @test */
    public function editableTrueButVoterDeniesReturnsFalse(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: true));

        $this->authChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($this->resolver->canEdit($entity, 'name'));
    }

    /** @test */
    public function editableTrueButNotWritableReturnsFalse(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: true));

        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(false);

        $this->assertFalse($this->resolver->canEdit($entity, 'name'));
    }

    // ── editable: null / no attribute — falls back to entity flag ───────────

    /** @test */
    public function nullColumnAttrAndEntityEnabledAllowsEditWhenVoterGrantsAndWritable(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')->willReturn(null);
        $this->attributeHelper->method('getAttribute')
            ->willReturn(new Admin(enableInlineEdit: true));

        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($this->resolver->canEdit($entity, 'name'));
    }

    /** @test */
    public function nullColumnAttrAndEntityDisabledReturnsFalseWithoutVoterCheck(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')->willReturn(null);
        $this->attributeHelper->method('getAttribute')
            ->willReturn(new Admin(enableInlineEdit: false));

        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');

        $this->assertFalse($this->resolver->canEdit($entity, 'name'));
    }

    /** @test */
    public function noAdminAttributeReturnsFalse(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')->willReturn(null);
        $this->attributeHelper->method('getAttribute')->willReturn(null);

        $this->assertFalse($this->resolver->canEdit($entity, 'name'));
    }

    /** @test */
    public function adminColumnNullEditable_FallsBackToEntityFlag(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: null));
        $this->attributeHelper->method('getAttribute')
            ->willReturn(new Admin(enableInlineEdit: true));

        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($this->resolver->canEdit($entity, 'name'));
    }

    // ── Voter attribute uses entity short class ──────────────────────────────

    /** @test */
    public function voterIsCalledWithAdminEditAttributeAndShortClassName(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: true));

        $this->authChecker->expects($this->once())
            ->method('isGranted')
            ->with('ADMIN_EDIT', 'stdClass')
            ->willReturn(true);

        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->resolver->canEdit($entity, 'name');
    }
}
