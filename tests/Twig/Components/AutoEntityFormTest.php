<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Twig\Components\AutoEntityForm;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityInfoRuntime;
use Kachnitel\EntityComponentsBundle\Components\Field\EditabilityResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\Twig\Components\AutoEntityForm
 * @group auto-form
 */
class AutoEntityFormTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EditabilityResolverInterface&MockObject */
    private EditabilityResolverInterface $editabilityResolver;

    /** @var AdminEntityInfoRuntime&MockObject */
    private AdminEntityInfoRuntime $entityInfoRuntime;

    protected function setUp(): void
    {
        $this->em                  = $this->createMock(EntityManagerInterface::class);
        $this->editabilityResolver = $this->createMock(EditabilityResolverInterface::class);
        $this->entityInfoRuntime   = $this->createMock(AdminEntityInfoRuntime::class);
    }

    private function makeComponent(): AutoEntityForm
    {
        return new AutoEntityForm(
            $this->em,
            $this->editabilityResolver,
            $this->entityInfoRuntime,
        );
    }

    /**
     * @param class-string $entityClass
     * @param array<string> $fields
     * @param array<string, mixed> $associations
     */
    private function stubMetadata(string $entityClass, string $idField, array $fields, array $associations = []): void
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getSingleIdentifierFieldName')->willReturn($idField);
        $metadata->method('getFieldNames')->willReturn($fields);
        $metadata->method('getAssociationNames')->willReturn(array_keys($associations));

        foreach (array_keys($associations) as $assoc) {
            $metadata->method('isSingleValuedAssociation')
                ->with($assoc)
                ->willReturn($associations[$assoc]);
        }

        $this->em->method('getClassMetadata')
            ->with($entityClass)
            ->willReturn($metadata);
    }

    // ── getEntity() ────────────────────────────────────────────────────────────

    public function testGetEntityReturnsNullWhenEntityClassEmpty(): void
    {
        $component = $this->makeComponent();

        $this->assertNull($component->getEntity());
    }

    public function testGetEntityReturnsNullWhenEntityIdNull(): void
    {
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = null;

        $this->assertNull($component->getEntity());
    }

    public function testGetEntityLoadsFromEntityManager(): void
    {
        $entity                 = new AutoFormTestEntity();
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')
            ->with(AutoFormTestEntity::class, 1)
            ->willReturn($entity);

        $this->assertSame($entity, $component->getEntity());
    }

    // ── getEditableFields() ────────────────────────────────────────────────────

    public function testGetEditableFieldsReturnsEmptyWhenEntityNull(): void
    {
        $component = $this->makeComponent();

        $this->assertSame([], $component->getEditableFields());
    }

    public function testGetEditableFieldsExcludesIdField(): void
    {
        $entity                 = new AutoFormTestEntity();
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn($entity);
        $this->stubMetadata(AutoFormTestEntity::class, 'id', ['id', 'title', 'score']);

        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');
        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $fields = $component->getEditableFields();

        $this->assertNotContains('id', $fields);
        $this->assertContains('title', $fields);
        $this->assertContains('score', $fields);
    }

    public function testGetEditableFieldsExcludesFieldsWithNoComponent(): void
    {
        $entity                 = new AutoFormTestEntity();
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn($entity);
        $this->stubMetadata(AutoFormTestEntity::class, 'id', ['id', 'title', 'rawJson']);

        $this->entityInfoRuntime->method('getFieldComponentName')
            ->willReturnMap([
                [$entity, 'title',   'K:Entity:Field:String'],
                [$entity, 'rawJson', null],               // no component for json
            ]);
        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $fields = $component->getEditableFields();

        $this->assertContains('title', $fields);
        $this->assertNotContains('rawJson', $fields);
    }

    public function testGetEditableFieldsExcludesFieldsEditabilityResolverDenies(): void
    {
        $entity                 = new AutoFormTestEntity();
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn($entity);
        $this->stubMetadata(AutoFormTestEntity::class, 'id', ['id', 'title', 'locked']);

        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');

        $this->editabilityResolver->method('canEdit')
            ->willReturnMap([
                [$entity, 'title',  true],
                [$entity, 'locked', false],
            ]);

        $fields = $component->getEditableFields();

        $this->assertContains('title', $fields);
        $this->assertNotContains('locked', $fields);
    }

    public function testGetEditableFieldsIncludesSingleValuedAssociations(): void
    {
        $entity                 = new AutoFormTestEntity();
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn($entity);
        $this->stubMetadata(
            AutoFormTestEntity::class,
            'id',
            ['id', 'title'],
            ['category' => true],  // single-valued → included
        );

        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');
        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $fields = $component->getEditableFields();

        $this->assertContains('category', $fields);
    }

    public function testGetEditableFieldsExcludesCollectionAssociations(): void
    {
        $entity                 = new AutoFormTestEntity();
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn($entity);
        $this->stubMetadata(
            AutoFormTestEntity::class,
            'id',
            ['id', 'title'],
            ['tags' => false],  // collection-valued → excluded
        );

        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');
        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $fields = $component->getEditableFields();

        $this->assertNotContains('tags', $fields);
    }

    public function testGetEditableFieldsIsCached(): void
    {
        $entity                 = new AutoFormTestEntity();
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn($entity);
        $this->stubMetadata(AutoFormTestEntity::class, 'id', ['id', 'title']);
        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');
        $this->editabilityResolver->method('canEdit')->willReturn(true);

        // Two calls — entity manager should only be asked for metadata once.
        $this->em->expects($this->once())->method('getClassMetadata');

        $component->getEditableFields();
        $component->getEditableFields();
    }

    // ── getFieldComponent() ────────────────────────────────────────────────────

    public function testGetFieldComponentReturnsNullWhenEntityNull(): void
    {
        $component = $this->makeComponent();

        $this->assertNull($component->getFieldComponent('title'));
    }

    public function testGetFieldComponentDelegatesToRuntime(): void
    {
        $entity                 = new AutoFormTestEntity();
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn($entity);
        $this->entityInfoRuntime->method('getFieldComponentName')
            ->with($entity, 'title')
            ->willReturn('K:Entity:Field:String');

        $this->assertSame('K:Entity:Field:String', $component->getFieldComponent('title'));
    }

    // ── save() state management ────────────────────────────────────────────────

    public function testSaveSetsSaveSuccessTrueWhenNoEditableFields(): void
    {
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn(new AutoFormTestEntity());
        $this->stubMetadata(AutoFormTestEntity::class, 'id', ['id']);

        // No editable fields after id excluded.
        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');
        $this->editabilityResolver->method('canEdit')->willReturn(false);

        $component->save();

        $this->assertTrue($component->saveSuccess);
        $this->assertFalse($component->hasErrors);
    }

    public function testSaveResetsPreviousState(): void
    {
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;
        $component->saveSuccess = true;
        $component->hasErrors   = true;

        $this->em->method('find')->willReturn(new AutoFormTestEntity());
        $this->stubMetadata(AutoFormTestEntity::class, 'id', ['id']);
        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');
        $this->editabilityResolver->method('canEdit')->willReturn(false);

        $component->save();

        $this->assertFalse($component->hasErrors);
    }

    // ── field event listeners ──────────────────────────────────────────────────

    public function testOnFieldSavedMarksSaveSuccessWhenAllResponded(): void
    {
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn(new AutoFormTestEntity());
        $this->stubMetadata(AutoFormTestEntity::class, 'id', ['id', 'title']);
        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');
        $this->editabilityResolver->method('canEdit')->willReturn(true);

        // Trigger save so expectedCount = 1.
        $component->save();

        $this->assertFalse($component->saveSuccess);

        // Simulate the one field responding.
        $component->onFieldSaved();

        $this->assertTrue($component->saveSuccess);
        $this->assertFalse($component->hasErrors);
    }

    public function testOnFieldSaveErrorSetsHasErrorsAndDoesNotSetSaveSuccess(): void
    {
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn(new AutoFormTestEntity());
        $this->stubMetadata(AutoFormTestEntity::class, 'id', ['id', 'title']);
        $this->entityInfoRuntime->method('getFieldComponentName')
            ->willReturn('K:Entity:Field:String');
        $this->editabilityResolver->method('canEdit')
            ->willReturn(true);

        $component->save();
        $component->onFieldSaveError();

        $this->assertTrue($component->hasErrors);
        $this->assertFalse($component->saveSuccess);
    }

    public function testSaveSuccessRequiresAllFieldsToRespond(): void
    {
        $component              = $this->makeComponent();
        $component->entityClass = AutoFormTestEntity::class;
        $component->entityId    = 1;

        $this->em->method('find')->willReturn(new AutoFormTestEntity());
        $this->stubMetadata(AutoFormTestEntity::class, 'id', ['id', 'title', 'score']);
        $this->entityInfoRuntime->method('getFieldComponentName')->willReturn('K:Entity:Field:String');
        $this->editabilityResolver->method('canEdit')->willReturn(true);

        $component->save(); // expectedCount = 2 (title + score)

        $component->onFieldSaved(); // 1 of 2 responded

        $this->assertFalse($component->saveSuccess, 'Should not be successful until both fields respond.');

        $component->onFieldSaved(); // 2 of 2 responded

        $this->assertTrue($component->saveSuccess);
    }
}

// ── Fixture ────────────────────────────────────────────────────────────────────

#[Admin(label: 'Auto Form Test', enableInlineEdit: true)]
class AutoFormTestEntity
{
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[AdminColumn(editable: true)]
    private string $title = '';

    #[AdminColumn(editable: true)]
    private float $score = 0.0;

    public function getId(): ?int { return $this->id; }
}
