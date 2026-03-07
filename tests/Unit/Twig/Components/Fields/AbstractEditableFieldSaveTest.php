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
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests for the save() lifecycle in AbstractEditableField:
 *  - canEdit() guard fires before any entity mutation (security fix, item 1)
 *  - ValidatorInterface integration: errorMessage set on violation, no flush (item 2)
 *  - saveSuccess set to true after a successful flush (item 4)
 *  - errorMessage cleared on the next activateEditing() call
 *
 * @group inline-edit
 * @group inline-edit-save
 */
class AbstractEditableFieldSaveTest extends TestCase
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

    /** @var ValidatorInterface&MockObject */
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->entityManager      = $this->createMock(EntityManagerInterface::class);
        $this->authChecker        = $this->createMock(AuthorizationCheckerInterface::class);
        $this->propertyAccessor   = $this->createMock(PropertyAccessorInterface::class);
        $this->attributeHelper    = $this->createMock(AttributeHelper::class);
        $this->expressionLanguage = $this->createMock(RowActionExpressionLanguage::class);
        $this->validator          = $this->createMock(ValidatorInterface::class);
    }

    // ── canEdit() guard ────────────────────────────────────────────────────────

    /** @test */
    public function saveThrowsAccessDeniedWhenCanEditReturnsFalse(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeFieldWithPersistEdit($entity, 'name');

        // resolveEditable → false (editable: false)
        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: false));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $field->save();
    }

    /** @test */
    public function saveThrowsAccessDeniedWhenVoterDenies(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeFieldWithPersistEdit($entity, 'name');

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: true));
        $this->authChecker->method('isGranted')->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $field->save();
    }

    /** @test */
    public function persistEditIsNeverCalledWhenCanEditReturnsFalse(): void
    {
        $entity      = new \stdClass();
        $persistCalled = false;
        $field       = $this->makeFieldWithCallbackPersist($entity, 'name', function () use (&$persistCalled): void {
            $persistCalled = true;
        });

        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: false));

        try {
            $field->save();
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertFalse($persistCalled, 'persistEdit() must not be called when canEdit() returns false');
    }

    // ── Validation ─────────────────────────────────────────────────────────────

    /** @test */
    public function savePopulatesErrorMessageWhenValidationFails(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeEditableField($entity, 'name');

        $this->stubCanEdit();

        $violation = new ConstraintViolation('Name is too short.', null, [], $entity, 'name', '');
        $violations = new ConstraintViolationList([$violation]);

        $this->validator->method('validateProperty')->willReturn($violations);

        $field->save();

        $this->assertSame('Name is too short.', $field->errorMessage);
    }

    /** @test */
    public function saveDoesNotFlushWhenValidationFails(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeEditableField($entity, 'name');

        $this->stubCanEdit();

        $violation = new ConstraintViolation('Too long.', null, [], $entity, 'name', '');
        $violations = new ConstraintViolationList([$violation]);

        $this->validator->method('validateProperty')->willReturn($violations);
        $this->entityManager->expects($this->never())->method('flush');

        $field->save();
    }

    /** @test */
    public function saveKeepsEditModeOnValidationFailure(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeEditableField($entity, 'name');
        $field->editMode = true;

        $this->stubCanEdit();

        $violation = new ConstraintViolation('Invalid.', null, [], $entity, 'name', '');
        $violations = new ConstraintViolationList([$violation]);

        $this->validator->method('validateProperty')->willReturn($violations);

        $field->save();

        $this->assertTrue($field->editMode, 'editMode must stay true when validation fails');
    }

    /** @test */
    public function saveRefreshesEntityWhenValidationFails(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeEditableField($entity, 'name');

        $this->stubCanEdit();

        $violation = new ConstraintViolation('Bad.', null, [], $entity, 'name', '');
        $this->validator->method('validateProperty')
            ->willReturn(new ConstraintViolationList([$violation]));

        // refresh() is called directly on the cached resolvedEntity — no find() needed
        $this->entityManager->expects($this->never())->method('find');
        $this->entityManager->expects($this->once())->method('refresh')->with($entity);

        $field->save();
    }

    // ── Successful save ────────────────────────────────────────────────────────

    /** @test */
    public function saveFlushesAndSetsSuccessOnValidSave(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeEditableField($entity, 'name');

        $this->stubCanEdit();
        $this->validator->method('validateProperty')
            ->willReturn(new ConstraintViolationList([]));

        $this->entityManager->expects($this->once())->method('flush');

        $field->save();

        $this->assertTrue($field->saveSuccess);
        $this->assertFalse($field->editMode);
        $this->assertSame('', $field->errorMessage);
    }

    /** @test */
    public function activateEditingClearsErrorAndSaveSuccess(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeEditableField($entity, 'name');
        $field->errorMessage = 'Previous error';
        $field->saveSuccess  = true;

        $this->stubCanEdit();

        $field->activateEditing();

        $this->assertSame('', $field->errorMessage);
        $this->assertFalse($field->saveSuccess);
        $this->assertTrue($field->editMode);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Build a field where persistEdit() does nothing (default no-op base implementation).
     */
    private function makeEditableField(object $entity, string $property): AbstractEditableField
    {
        $field = new class(
            $this->entityManager,
            $this->propertyAccessor,
            $this->authChecker,
            $this->attributeHelper,
            $this->expressionLanguage,
            $this->validator,
        ) extends AbstractEditableField {};

        $field->entityClass    = $entity::class;
        $field->entityId       = 1;
        $field->property       = $property;
        $field->resolvedEntity = $entity;

        return $field;
    }

    /**
     * Build a field where persistEdit() sets a flag, to verify it is (or is not) called.
     *
     * @param \Closure(): void $callback
     */
    private function makeFieldWithCallbackPersist(object $entity, string $property, \Closure $callback): AbstractEditableField
    {
        $field = new class(
            $this->entityManager,
            $this->propertyAccessor,
            $this->authChecker,
            $this->attributeHelper,
            $this->expressionLanguage,
            $this->validator,
            $callback,
        ) extends AbstractEditableField {
            private \Closure $onPersist;

            public function __construct(
                EntityManagerInterface $em,
                PropertyAccessorInterface $pa,
                AuthorizationCheckerInterface $ac,
                AttributeHelper $ah,
                RowActionExpressionLanguage $el,
                ValidatorInterface $v,
                \Closure $onPersist,
            ) {
                parent::__construct($em, $pa, $ac, $ah, $el, $v);
                $this->onPersist = $onPersist;
            }

            protected function persistEdit(): void
            {
                ($this->onPersist)();
            }
        };

        $field->entityClass    = $entity::class;
        $field->entityId       = 1;
        $field->property       = $property;
        $field->resolvedEntity = $entity;

        return $field;
    }

    /**
     * Build a field where persistEdit() writes a value (verifies the write path).
     */
    private function makeFieldWithPersistEdit(object $entity, string $property): AbstractEditableField
    {
        return $this->makeFieldWithCallbackPersist($entity, $property, function (): void {});
    }

    /**
     * Stub mocks so canEdit() returns true: editable:true column, voter grants, property writable.
     */
    private function stubCanEdit(): void
    {
        $this->attributeHelper->method('getPropertyAttribute')
            ->willReturn(new AdminColumn(editable: true));
        $this->authChecker->method('isGranted')->willReturn(true);
        $this->propertyAccessor->method('isWritable')->willReturn(true);
    }
}
