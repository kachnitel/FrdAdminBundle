<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Components\Field;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Twig\Components\Field\AbstractEditableField;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Tests for the editable expression / flag / entity-default logic in AbstractEditableField.
 *
 * @group inline-edit
 */
class AbstractEditableFieldEditableTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    /** @var PropertyAccessorInterface&MockObject */
    private PropertyAccessorInterface $propertyAccessor;

    /** @var AttributeHelper&MockObject */
    private AttributeHelper $attributeHelper;

    /** @var RowActionExpressionLanguage&MockObject */
    private RowActionExpressionLanguage $expressionLanguage;

    protected function setUp(): void
    {
        $this->entityManager      = $this->createMock(EntityManagerInterface::class);
        $this->authChecker        = $this->createMock(AuthorizationCheckerInterface::class);
        $this->propertyAccessor   = $this->createMock(PropertyAccessorInterface::class);
        $this->attributeHelper    = $this->createMock(AttributeHelper::class);
        $this->expressionLanguage = $this->createMock(RowActionExpressionLanguage::class);
    }

    // ── No #[AdminColumn] — falls back to entity-level flag ───────────────────

    /** @test */
    public function canEditReturnsTrueWhenNoColumnAttrAndEntityEnablesInlineEdit(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper->method('getPropertyAttribute')->willReturn(null);
        $this->attributeHelper->method('getAttribute')
            ->with($entity::class, Admin::class)
            ->willReturn(new Admin(enableInlineEdit: true));

        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($field->canEdit());
    }

    /** @test */
    public function canEditReturnsFalseWhenNoColumnAttrAndEntityDisablesInlineEdit(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper->method('getPropertyAttribute')->willReturn(null);
        $this->attributeHelper->method('getAttribute')
            ->with($entity::class, Admin::class)
            ->willReturn(new Admin(enableInlineEdit: false)); // default

        // Voter and PropertyAccess must NOT be consulted when entity flag is false
        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');

        $this->assertFalse($field->canEdit());
    }

    /** @test */
    public function canEditReturnsFalseWhenNoAdminAttribute(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper->method('getPropertyAttribute')->willReturn(null);
        $this->attributeHelper->method('getAttribute')->willReturn(null);

        $this->assertFalse($field->canEdit());
    }

    // ── #[AdminColumn(editable: null)] — same as no attribute ────────────────

    /** @test */
    public function canEditWithNullColumnAttrFallsBackToEntityFlag(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: null));
        $this->attributeHelper->method('getAttribute')
            ->with($entity::class, Admin::class)
            ->willReturn(new Admin(enableInlineEdit: true));

        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($field->canEdit());
    }

    /** @test */
    public function canEditWithNullColumnAttrReturnsFalseWhenEntityDisabled(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: null));
        $this->attributeHelper->method('getAttribute')
            ->willReturn(new Admin(enableInlineEdit: false));

        $this->assertFalse($field->canEdit());
    }

    // ── #[AdminColumn(editable: false)] — short-circuits everything ──────────

    /** @test */
    public function canEditReturnsFalseWhenEditableFalseRegardlessOfEntityFlag(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'computed');

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: false));

        // Entity flag, voter, and PropertyAccess must NOT be consulted
        $this->attributeHelper->expects($this->never())
            ->method('getAttribute');
        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');

        $this->assertFalse($field->canEdit());
    }

    // ── #[AdminColumn(editable: true)] — opt-in overrides entity default ──────

    /** @test */
    public function canEditReturnsTrueWhenEditableTrueEvenIfEntityDisabled(): void
    {
        // Column explicitly opts in, bypassing entity's enableInlineEdit: false
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: true));

        // Entity flag must NOT be consulted when column is explicitly true
        $this->attributeHelper->expects($this->never())
            ->method('getAttribute');

        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($field->canEdit());
    }

    /** @test */
    public function canEditReturnsFalseWhenEditableTrueButVoterDenied(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: true));
        $this->authChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($field->canEdit());
    }

    // ── #[AdminColumn(editable: 'expression')] ────────────────────────────────

    /** @test */
    public function canEditEvaluatesExpressionWhenEditableIsString(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'status');

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: 'entity.status != "locked"'));

        $this->expressionLanguage
            ->expects($this->once())
            ->method('evaluate')
            ->with('entity.status != "locked"', $entity, $this->authChecker)
            ->willReturn(true);

        // Entity flag must NOT be consulted when expression is provided
        $this->attributeHelper->expects($this->never())
            ->method('getAttribute');

        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($field->canEdit());
    }

    /** @test */
    public function canEditReturnsFalseWhenExpressionEvaluatesToFalse(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'status');

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: 'entity.status != "locked"'));

        $this->expressionLanguage->method('evaluate')->willReturn(false);

        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');

        $this->assertFalse($field->canEdit());
    }

    /** @test */
    public function canEditReturnsFalseWhenExpressionTrueButVoterDenied(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'status');

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: 'entity.active'));

        $this->expressionLanguage->method('evaluate')->willReturn(true);
        $this->authChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($field->canEdit());
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeField(object $entity, string $property): AbstractEditableField
    {
        $field = new class (
            $this->entityManager,
            $this->propertyAccessor,
            $this->authChecker,
            $this->attributeHelper,
            $this->expressionLanguage,
        ) extends AbstractEditableField {};

        $field->entityClass    = $entity::class;
        $field->entityId       = 1;
        $field->property       = $property;
        $field->resolvedEntity = $entity;

        return $field;
    }
}
