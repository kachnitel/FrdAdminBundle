<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Service\DoctrineValueCoercer;
use Kachnitel\AdminBundle\Twig\Components\AutoEntityForm;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityInfoRuntime;
use Kachnitel\EntityComponentsBundle\Components\Field\EditabilityResolverInterface;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\UX\LiveComponent\LiveResponder;

/**
 * @covers \Kachnitel\AdminBundle\Twig\Components\AutoEntityForm
 * @group auto-form
 */
#[UsesClass(Admin::class)]
#[UsesClass(AdminColumn::class)]
class AutoEntityFormTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EditabilityResolverInterface&MockObject */
    private EditabilityResolverInterface $editabilityResolver;

    /** @var AdminEntityInfoRuntime&MockObject */
    private AdminEntityInfoRuntime $entityInfoRuntime;

    /** @var DoctrineValueCoercer&MockObject */
    private DoctrineValueCoercer $coercer;

    /** @var PropertyAccessorInterface&MockObject */
    private PropertyAccessorInterface $propertyAccessor;

    /** @var ValidatorInterface&MockObject */
    private ValidatorInterface $validator;

    /** @var LiveResponder */
    private LiveResponder $liveResponder;

    protected function setUp(): void
    {
        $this->em                  = $this->createMock(EntityManagerInterface::class);
        $this->editabilityResolver = $this->createMock(EditabilityResolverInterface::class);
        $this->entityInfoRuntime   = $this->createMock(AdminEntityInfoRuntime::class);
        $this->coercer             = $this->createMock(DoctrineValueCoercer::class);
        $this->propertyAccessor    = $this->createMock(PropertyAccessorInterface::class);
        $this->validator           = $this->createMock(ValidatorInterface::class);
        $this->liveResponder       = new LiveResponder;
    }

    private function makeComponent(): AutoEntityForm
    {
        $component = new AutoEntityForm(
            $this->em,
            $this->editabilityResolver,
            $this->entityInfoRuntime,
            $this->coercer,
            $this->propertyAccessor,
            $this->validator,
        );

        $component->setLiveResponder($this->liveResponder);

        return $component;
    }

    /**
     * @param ClassMetadata<object>&MockObject $metadata
     * @param array<string> $fields
     * @param array<string, mixed> $singleAssociations
     * @param array<string, mixed> $collectionAssociations
     */
    private function stubMetadata(
        MockObject $metadata,
        string $idField,
        array $fields,
        array $singleAssociations = [],
        array $collectionAssociations = [],
    ): void {
        $metadata->method('getSingleIdentifierFieldName')->willReturn($idField);
        $metadata->method('getFieldNames')->willReturn($fields);

        $allAssocs = array_merge(
            array_keys($singleAssociations),
            array_keys($collectionAssociations),
        );
        $metadata->method('getAssociationNames')->willReturn($allAssocs);

        $metadata->method('isSingleValuedAssociation')->willReturnCallback(
            fn (string $a) => isset($singleAssociations[$a])
        );
    }

    // ── isNew() ────────────────────────────────────────────────────────────────

    public function testIsNewTrueWhenEntityIdNull(): void
    {
        $component = $this->makeComponent();
        $this->assertTrue($component->isNew());
    }

    public function testIsNewFalseWhenEntityIdSet(): void
    {
        $component           = $this->makeComponent();
        $component->entityId = 1;
        $this->assertFalse($component->isNew());
    }

    // ── getEntity() ────────────────────────────────────────────────────────────

    public function testGetEntityReturnsNullWhenNew(): void
    {
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;
        $this->assertNull($component->getEntity());
    }

    public function testGetEntityLoadsFromEm(): void
    {
        $entity              = new AutoFormFixtureEntity();
        $component                   = $this->makeComponent();
        $component->entityClass      = AutoFormFixtureEntity::class;
        $component->entityId         = 1;

        $this->em->method('find')->with(AutoFormFixtureEntity::class, 1)->willReturn($entity);

        $this->assertSame($entity, $component->getEntity());
    }

    // ── getEditableFields() new mode ───────────────────────────────────────────

    public function testNewModeFieldsBasedOnEnableInlineEdit(): void
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id', 'title', 'score']);
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class; // has enableInlineEdit: true

        $fields = $component->getEditableFields();

        $this->assertContains('title', $fields);
        $this->assertContains('score', $fields);
        $this->assertNotContains('id', $fields);
    }

    public function testNewModeFieldsBasedOnEditableTrueColumn(): void
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id', 'name', 'locked']);
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormPartialEntity::class; // only 'name' has editable:true

        $fields = $component->getEditableFields();

        $this->assertContains('name', $fields);
        $this->assertNotContains('locked', $fields);
        $this->assertNotContains('id', $fields);
    }

    public function testNewModeExcludesCollectionAssociations(): void
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata(
            $metadata,
            'id',
            ['id', 'title'],
            ['owner' => true],       // single — included
            ['tags'  => false],      // collection — excluded
        );
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;

        $fields = $component->getEditableFields();

        $this->assertContains('owner', $fields);
        $this->assertNotContains('tags', $fields);
    }

    // ── getEditableFields() edit mode ──────────────────────────────────────────

    public function testEditModeUsesEditabilityResolver(): void
    {
        $entity              = new AutoFormFixtureEntity();
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id', 'title', 'score']);

        $this->em->method('find')->willReturn($entity);
        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');

        $this->editabilityResolver->method('canEdit')
            ->willReturnMap([
                [$entity, 'title', true],
                [$entity, 'score', false],
            ]);

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;
        $component->entityId    = 1;

        $fields = $component->getEditableFields();

        $this->assertContains('title', $fields);
        $this->assertNotContains('score', $fields);
    }

    public function testEditModeExcludesFieldsWithNoComponent(): void
    {
        $entity = new AutoFormFixtureEntity();
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id', 'title', 'rawJson']);

        $this->em->method('find')->willReturn($entity);
        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $this->entityInfoRuntime->method('getFieldComponentName')
            ->willReturnMap([
                [$entity, 'title',   'K:Entity:Field:String'],
                [$entity, 'rawJson', null],
            ]);

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;
        $component->entityId    = 1;

        $fields = $component->getEditableFields();

        $this->assertContains('title', $fields);
        $this->assertNotContains('rawJson', $fields);
    }

    // ── save() new mode ────────────────────────────────────────────────────────

    public function testSaveNewPersistsAndFlushesOnSuccess(): void
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id', 'title']);
        $metadata->method('getIdentifierValues')->willReturn(['id' => 99]);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->coercer->method('coerceAll')->willReturn(['title' => 'New item']);
        $this->propertyAccessor->method('setValue');
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;
        $component->formValues  = ['title' => 'New item'];

        $component->save();

        $this->assertTrue($component->saveSuccess);
        $this->assertFalse($component->hasErrors);
        $this->assertSame(99, $component->entityId, 'entityId set after persist so next render is edit mode');
    }

    public function testSaveNewSetsFormErrorsOnValidationFailure(): void
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id', 'title']);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->coercer->method('coerceAll')->willReturn(['title' => '']);
        $this->propertyAccessor->method('setValue');

        $violation = $this->createMock(ConstraintViolation::class);
        $violation->method('getPropertyPath')->willReturn('title');
        $violation->method('getMessage')->willReturn('Title must not be blank.');

        $violations = new ConstraintViolationList([$violation]);
        $this->validator->method('validate')->willReturn($violations);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;
        $component->formValues  = ['title' => ''];

        $component->save();

        $this->assertFalse($component->saveSuccess);
        $this->assertTrue($component->hasErrors);
        $this->assertArrayHasKey('title', $component->formErrors);
        $this->assertSame('Title must not be blank.', $component->formErrors['title']);
        $this->assertNull($component->entityId, 'entityId must stay null when validation fails');
    }

    public function testSaveNewResetsStateBeforeAttempt(): void
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id', 'title']);
        $metadata->method('getIdentifierValues')->willReturn(['id' => 1]);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->coercer->method('coerceAll')->willReturn([]);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;
        $component->hasErrors   = true;
        $component->formErrors  = ['title' => 'previous error'];

        $component->save();

        $this->assertFalse($component->hasErrors);
        $this->assertSame([], $component->formErrors);
    }

    public function testSaveNewClearsFormValuesOnSuccess(): void
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id', 'title']);
        $metadata->method('getIdentifierValues')->willReturn(['id' => 1]);

        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->coercer->method('coerceAll')->willReturn(['title' => 'Test']);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;
        $component->formValues  = ['title' => 'Test'];

        $component->save();

        $this->assertSame([], $component->formValues, 'formValues cleared after successful persist');
    }

    // ── save() edit mode ───────────────────────────────────────────────────────

    public function testSaveEditSetsSaveSuccessWhenNoFields(): void
    {
        $entity = new AutoFormFixtureEntity();
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id']);

        $this->em->method('find')->willReturn($entity);
        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->editabilityResolver->method('canEdit')->willReturn(false);
        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;
        $component->entityId    = 1;

        $component->save();

        $this->assertTrue($component->saveSuccess);
    }

    public function testSaveEditTracksFieldResponses(): void
    {
        $entity = new AutoFormFixtureEntity();
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id', 'title', 'score']);

        $this->em->method('find')->willReturn($entity);
        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->editabilityResolver->method('canEdit')->willReturn(true);
        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;
        $component->entityId    = 1;

        $component->save(); // expectedCount = 2

        $this->assertFalse($component->saveSuccess);

        $component->onFieldSaved();
        $this->assertFalse($component->saveSuccess, 'Only 1 of 2 responded');

        $component->onFieldSaved();
        $this->assertTrue($component->saveSuccess, '2 of 2 responded — should be successful');
    }

    public function testSaveEditSetsHasErrorsWhenFieldErrors(): void
    {
        $entity = new AutoFormFixtureEntity();
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->stubMetadata($metadata, 'id', ['id', 'title']);

        $this->em->method('find')->willReturn($entity);
        $this->em->method('getClassMetadata')->willReturn($metadata);
        $this->editabilityResolver->method('canEdit')->willReturn(true);
        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');

        $component              = $this->makeComponent();
        $component->entityClass = AutoFormFixtureEntity::class;
        $component->entityId    = 1;

        $component->save();
        $component->onFieldSaveError();

        $this->assertTrue($component->hasErrors);
        $this->assertFalse($component->saveSuccess);
    }
}

// ── Fixtures ───────────────────────────────────────────────────────────────────

#[Admin(label: 'Auto Form Fixture', enableInlineEdit: true)]
class AutoFormFixtureEntity
{
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[AdminColumn(editable: true)]
    private string $title = '';

    #[AdminColumn(editable: true)]
    private float $score = 0.0;

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): void { $this->title = $v; }
    public function getScore(): float { return $this->score; }
    public function setScore(float $v): void { $this->score = $v; }
}

#[Admin(label: 'Auto Form Partial')]
class AutoFormPartialEntity
{
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[AdminColumn(editable: true)]
    private string $name = '';

    // editable: false — should never appear
    #[AdminColumn(editable: false)]
    private string $locked = '';

    public function getId(): ?int { return $this->id; }
}
