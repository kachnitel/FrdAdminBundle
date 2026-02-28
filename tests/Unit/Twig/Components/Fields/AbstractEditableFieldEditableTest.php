<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Components\Field;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Twig\Components\Field\AbstractEditableField;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Tests for the editable expression / flag logic in AbstractEditableField.
 *
 * The canEdit() method resolves the #[AdminColumn(editable: ...)] attribute
 * before falling through to the voter + isWritable checks.
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

    // -------------------------------------------------------------------------
    // No #[AdminColumn] attribute — existing behaviour unchanged
    // -------------------------------------------------------------------------

    /** @test */
    public function canEditReturnsTrueWhenNoAttributeAndVoterGrantedAndWritable(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper->method('getPropertyAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($field->canEdit());
    }

    /** @test */
    public function canEditReturnsFalseWhenNoAttributeAndVoterDenied(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper->method('getPropertyAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($field->canEdit());
    }

    /** @test */
    public function canEditReturnsFalseWhenNoAttributeAndPropertyNotWritable(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'readonly');

        $this->attributeHelper->method('getPropertyAttribute')->willReturn(null);
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(false);

        $this->assertFalse($field->canEdit());
    }

    // -------------------------------------------------------------------------
    // #[AdminColumn(editable: false)] — short-circuits everything
    // -------------------------------------------------------------------------

    /** @test */
    public function canEditReturnsFalseWhenEditableFalseRegardlessOfVoter(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'computed');

        $this->attributeHelper
            ->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: false));

        // Voter and PropertyAccess must NOT be consulted
        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');

        $this->assertFalse($field->canEdit());
    }

    // -------------------------------------------------------------------------
    // #[AdminColumn(editable: true)] — same as no attribute
    // -------------------------------------------------------------------------

    /** @test */
    public function canEditReturnsTrueWhenEditableTrueAndVoterGrantedAndWritable(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper
            ->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: true));

        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($field->canEdit());
    }

    // -------------------------------------------------------------------------
    // #[AdminColumn(editable: 'expression')] — expression evaluated
    // -------------------------------------------------------------------------

    /** @test */
    public function canEditEvaluatesExpressionWhenEditableIsString(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'status');

        $this->attributeHelper
            ->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: 'entity.status != "locked"'));

        $this->expressionLanguage
            ->expects($this->once())
            ->method('evaluate')
            ->with('entity.status != "locked"', $entity, $this->authChecker)
            ->willReturn(true);

        // Voter and PropertyAccess are still consulted after expression passes
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $this->assertTrue($field->canEdit());
    }

    /** @test */
    public function canEditReturnsFalseWhenExpressionEvaluatesToFalse(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'status');

        $this->attributeHelper
            ->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: 'entity.status != "locked"'));

        $this->expressionLanguage->method('evaluate')->willReturn(false);

        // Voter and PropertyAccess must NOT be consulted when expression returns false
        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');

        $this->assertFalse($field->canEdit());
    }

    /** @test */
    public function canEditReturnsFalseWhenExpressionTrueButVoterDenied(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'status');

        $this->attributeHelper
            ->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: 'entity.active'));

        $this->expressionLanguage->method('evaluate')->willReturn(true);
        $this->authChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($field->canEdit());
    }

    /** @test */
    public function canEditPassesAuthCheckerToExpression(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->attributeHelper
            ->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: 'is_granted("ROLE_EDITOR")'));

        $this->expressionLanguage
            ->expects($this->once())
            ->method('evaluate')
            ->with('is_granted("ROLE_EDITOR")', $entity, $this->authChecker)
            ->willReturn(true);

        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);

        $field->canEdit();
    }

    // -------------------------------------------------------------------------
    // Helper: build a concrete anonymous subclass with the entity pre-resolved
    // -------------------------------------------------------------------------

    private function makeField(object $entity, string $property): AbstractEditableField
    {
        $field = new class (
            $this->entityManager,
            $this->propertyAccessor,
            $this->authChecker,
            $this->attributeHelper,
            $this->expressionLanguage,
        ) extends AbstractEditableField {};

        // Pre-populate LiveProps and resolved entity so canEdit() doesn't call the EntityManager
        $field->entityClass    = $entity::class;
        $field->entityId       = 1;
        $field->property       = $property;
        $field->resolvedEntity = $entity;

        return $field;
    }
}
