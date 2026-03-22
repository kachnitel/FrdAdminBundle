<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Field;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\EntityComponentsBundle\Components\Field\AbstractEditableField;
use Kachnitel\EntityComponentsBundle\Components\Field\EditabilityResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests for the save() lifecycle in AbstractEditableField (now in entity-components-bundle):
 *  - canEdit() guard fires before any entity mutation
 *  - ValidatorInterface integration: errorMessage set on violation, no flush
 *  - saveSuccess set to true after a successful flush
 *  - errorMessage cleared on the next activateEditing() call
 *
 * @group inline-edit
 * @group inline-edit-save
 */
class AbstractEditableFieldSaveTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var PropertyAccessorInterface&MockObject */
    private PropertyAccessorInterface $propertyAccessor;

    /** @var EditabilityResolverInterface&MockObject */
    private EditabilityResolverInterface $editabilityResolver;

    /** @var ValidatorInterface&MockObject */
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->entityManager       = $this->createMock(EntityManagerInterface::class);
        $this->propertyAccessor    = $this->createMock(PropertyAccessorInterface::class);
        $this->editabilityResolver = $this->createMock(EditabilityResolverInterface::class);
        $this->validator           = $this->createMock(ValidatorInterface::class);
    }

    // ── canEdit() guard ────────────────────────────────────────────────────────

    /** @test */
    public function saveThrowsAccessDeniedWhenCanEditReturnsFalse(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeField($entity, 'name');

        $this->editabilityResolver->method('canEdit')->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $field->save();
    }

    /** @test */
    public function persistEditIsNeverCalledWhenCanEditReturnsFalse(): void
    {
        $entity        = new \stdClass();
        $persistCalled = false;

        $field = $this->makeFieldWithCallbackPersist($entity, 'name', function () use (&$persistCalled): void {
            $persistCalled = true;
        });

        $this->editabilityResolver->method('canEdit')->willReturn(false);

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

        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $violation  = new ConstraintViolation('Name is too short.', null, [], $entity, 'name', '');
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

        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $violation  = new ConstraintViolation('Too long.', null, [], $entity, 'name', '');
        $violations = new ConstraintViolationList([$violation]);
        $this->validator->method('validateProperty')->willReturn($violations);

        $this->entityManager->expects($this->never())->method('flush');

        $field->save();
    }

    /** @test */
    public function saveKeepsEditModeOnValidationFailure(): void
    {
        $entity          = new \stdClass();
        $field           = $this->makeEditableField($entity, 'name');
        $field->editMode = true;

        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $violation  = new ConstraintViolation('Invalid.', null, [], $entity, 'name', '');
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

        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $violation = new ConstraintViolation('Bad.', null, [], $entity, 'name', '');
        $this->validator->method('validateProperty')
            ->willReturn(new ConstraintViolationList([$violation]));

        $this->entityManager->expects($this->once())->method('refresh')->with($entity);

        $field->save();
    }

    // ── Successful save ────────────────────────────────────────────────────────

    /** @test */
    public function saveFlushesAndSetsSuccessOnValidSave(): void
    {
        $entity = new \stdClass();
        $field  = $this->makeEditableField($entity, 'name');

        $this->editabilityResolver->method('canEdit')->willReturn(true);
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
        $entity              = new \stdClass();
        $field               = $this->makeEditableField($entity, 'name');
        $field->errorMessage = 'Previous error';
        $field->saveSuccess  = true;

        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $field->activateEditing();

        $this->assertSame('', $field->errorMessage);
        $this->assertFalse($field->saveSuccess);
        $this->assertTrue($field->editMode);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeEditableField(object $entity, string $property): AbstractEditableField
    {
        $field = new class (
            $this->entityManager,
            $this->propertyAccessor,
            $this->editabilityResolver,
            $this->validator,
        ) extends AbstractEditableField {};

        $field->entityClass    = $entity::class;
        $field->entityId       = 1;
        $field->property       = $property;
        $field->resolvedEntity = $entity;

        return $field;
    }

    /**
     * @param \Closure(): void $callback
     */
    private function makeFieldWithCallbackPersist(object $entity, string $property, \Closure $callback): AbstractEditableField
    {
        $field = new class (
            $this->entityManager,
            $this->propertyAccessor,
            $this->editabilityResolver,
            $this->validator,
            $callback,
        ) extends AbstractEditableField {
            private \Closure $onPersist;

            public function __construct(
                EntityManagerInterface $em,
                PropertyAccessorInterface $pa,
                EditabilityResolverInterface $er,
                ValidatorInterface $v,
                \Closure $onPersist,
            ) {
                parent::__construct($em, $pa, $er, $v);
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

    private function makeField(object $entity, string $property): AbstractEditableField
    {
        return $this->makeFieldWithCallbackPersist($entity, $property, function (): void {});
    }
}
