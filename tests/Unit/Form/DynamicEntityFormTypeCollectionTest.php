<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Form\DoctrineFormTypeMapper;
use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

/**
 * Tests DynamicEntityFormType collection inclusion/exclusion logic.
 *
 * @covers \Kachnitel\AdminBundle\Form\DynamicEntityFormType
 * @group dynamic-form
 * @group collections
 */
#[CoversClass(DynamicEntityFormType::class)]
#[UsesClass(AdminColumn::class)]
#[Group('dynamic-form')]
#[Group('collections')]
class DynamicEntityFormTypeCollectionTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    /** @var DoctrineFormTypeMapper&MockObject */
    private DoctrineFormTypeMapper $mapper;

    /** @var FormBuilderInterface<mixed>&MockObject */
    private FormBuilderInterface $builder;

    protected function setUp(): void
    {
        $this->em       = $this->createMock(EntityManagerInterface::class);
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->mapper   = $this->createMock(DoctrineFormTypeMapper::class);
        $this->builder  = $this->createMock(FormBuilderInterface::class);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);
        $this->metadata->method('getSingleIdentifierFieldName')->willReturn('id');
    }

    // ── is_root: true (default) — collections ARE included ────────────────────

    /** @test */
    public function manyToManyIsIncludedInRootForm(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['tags']);
        $this->metadata->method('isSingleValuedAssociation')->with('tags')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);

        $this->mapper->expects($this->once())
            ->method('getAssociationConfig')
            ->with($this->metadata, 'tags')
            ->willReturn([
                'type'    => EntityType::class,
                'options' => ['class' => 'App\Entity\Tag', 'multiple' => true, 'required' => false],
            ]);

        $this->builder->expects($this->once())
            ->method('add')
            ->with('tags', EntityType::class, $this->anything());

        $formType = new DynamicEntityFormType($this->em, $this->mapper);
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormTaggableEntity::class,
            'is_root'      => true,
        ]);
    }

    /** @test */
    public function oneToManyIsIncludedInRootForm(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['items']);
        $this->metadata->method('isSingleValuedAssociation')->with('items')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);

        $this->mapper->expects($this->once())
            ->method('getAssociationConfig')
            ->with($this->metadata, 'items')
            ->willReturn([
                'type'    => LiveCollectionType::class,
                'options' => [
                    'entry_type'    => DynamicEntityFormType::class,
                    'entry_options' => ['entity_class' => 'App\Entity\Item', 'data_class' => 'App\Entity\Item', 'is_root' => false],
                    'allow_add'     => true,
                    'allow_delete'  => true,
                ],
            ]);

        $this->builder->expects($this->once())
            ->method('add')
            ->with('items', LiveCollectionType::class, $this->anything());

        $formType = new DynamicEntityFormType($this->em, $this->mapper);
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormOrderEntity::class,
            'is_root'      => true,
        ]);
    }

    // ── is_root: false (child form) — collections are SKIPPED ─────────────────

    /** @test */
    public function manyToManyIsSkippedInChildForm(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['tags']);
        $this->metadata->method('isSingleValuedAssociation')->with('tags')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);

        // getAssociationConfig must never be called for a child form collection
        $this->mapper->expects($this->never())->method('getAssociationConfig');
        $this->builder->expects($this->never())->method('add');

        $formType = new DynamicEntityFormType($this->em, $this->mapper);
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormTaggableEntity::class,
            'is_root'      => false,
        ]);
    }

    /** @test */
    public function oneToManyIsSkippedInChildForm(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['items']);
        $this->metadata->method('isSingleValuedAssociation')->with('items')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);

        $this->mapper->expects($this->never())->method('getAssociationConfig');
        $this->builder->expects($this->never())->method('add');

        $formType = new DynamicEntityFormType($this->em, $this->mapper);
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormOrderEntity::class,
            'is_root'      => false,
        ]);
    }

    /** @test */
    public function singleValuedAssociationIsIncludedInChildForm(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['category']);
        $this->metadata->method('isSingleValuedAssociation')->with('category')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->with('category')->willReturn(false);

        $this->mapper->expects($this->once())
            ->method('getAssociationConfig')
            ->with($this->metadata, 'category')
            ->willReturn([
                'type'    => EntityType::class,
                'options' => ['class' => 'App\Entity\Category', 'required' => false],
            ]);

        $this->builder->expects($this->once())
            ->method('add')
            ->with('category', EntityType::class, $this->anything());

        $formType = new DynamicEntityFormType($this->em, $this->mapper);
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormProductEntity::class,
            'is_root'      => false,
        ]);
    }

    // ── editable: false skips collection ──────────────────────────────────────

    /** @test */
    public function collectionWithEditableFalseIsSkippedInRootForm(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['hiddenTags']);
        $this->metadata->method('isSingleValuedAssociation')->with('hiddenTags')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('hiddenTags')->willReturn(true);

        // DynFormEntityWithBlockedCollection has #[AdminColumn(editable: false)] on hiddenTags
        $this->mapper->expects($this->never())->method('getAssociationConfig');
        $this->builder->expects($this->never())->method('add');

        $formType = new DynamicEntityFormType($this->em, $this->mapper);
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormEntityWithBlockedCollection::class,
            'is_root'      => true,
        ]);
    }

    /** @test */
    public function collectionWithNoAttributeIsIncludedInRootForm(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['tags']);
        $this->metadata->method('isSingleValuedAssociation')->with('tags')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);

        // No attribute → include by default (opt-out behaviour)
        $this->mapper->expects($this->once())
            ->method('getAssociationConfig')
            ->willReturn([
                'type'    => EntityType::class,
                'options' => ['class' => 'App\Entity\Tag', 'multiple' => true, 'required' => false],
            ]);

        $this->builder->expects($this->once())->method('add');

        $formType = new DynamicEntityFormType($this->em, $this->mapper);
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormTaggableEntity::class,
            'is_root'      => true,
        ]);
    }

    // ── configureOptions registers is_root ────────────────────────────────────

    /** @test */
    public function isRootOptionDefaultsToTrue(): void
    {
        $resolver = new OptionsResolver();

        $formType = new DynamicEntityFormType($this->em, $this->mapper);
        $formType->configureOptions($resolver);

        $resolved = $resolver->resolve(['entity_class' => 'App\Entity\Product']);

        $this->assertTrue($resolved['is_root']);
    }

    /** @test */
    public function isRootOptionCanBeSetToFalse(): void
    {
        $resolver = new OptionsResolver();

        $formType = new DynamicEntityFormType($this->em, $this->mapper);
        $formType->configureOptions($resolver);

        $resolved = $resolver->resolve(['entity_class' => 'App\Entity\Product', 'is_root' => false]);

        $this->assertFalse($resolved['is_root']);
    }
}

// ── Inline fixtures ────────────────────────────────────────────────────────────

class DynFormTaggableEntity
{
    /** @var array<int, mixed> */
    private array $tags = [];
}

class DynFormOrderEntity
{
    /** @var array<int, mixed> */
    private array $items = [];
}

class DynFormProductEntity
{
    private ?object $category = null; // @phpstan-ignore property.unusedType
}

class DynFormEntityWithBlockedCollection
{
    /** @var array<int, object> $hiddenTags */
    #[AdminColumn(editable: false)]
    private array $hiddenTags = [];
}
