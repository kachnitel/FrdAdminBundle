<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Form\DoctrineFormTypeMapper;
use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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

    /** @var RowActionExpressionLanguage&MockObject */
    private RowActionExpressionLanguage $expressionLanguage;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authorizationChecker;

    /** @var FormBuilderInterface<mixed>&MockObject */
    private FormBuilderInterface $builder;

    protected function setUp(): void
    {
        $this->em       = $this->createMock(EntityManagerInterface::class);
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->mapper   = $this->createMock(DoctrineFormTypeMapper::class);
        $this->expressionLanguage = $this->createMock(RowActionExpressionLanguage::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->builder  = $this->createMock(FormBuilderInterface::class);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);
        $this->metadata->method('getSingleIdentifierFieldName')->willReturn('id');
    }

    private function createFormType(): DynamicEntityFormType
    {
        return new DynamicEntityFormType(
            $this->em,
            $this->mapper,
            $this->expressionLanguage,
            $this->authorizationChecker,
        );
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

        $formType = $this->createFormType();
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

        $formType = $this->createFormType();
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

        $formType = $this->createFormType();
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

        $formType = $this->createFormType();
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

        $formType = $this->createFormType();
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

        $formType = $this->createFormType();
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

        $formType = $this->createFormType();
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

        $formType = $this->createFormType();
        $formType->configureOptions($resolver);

        $resolved = $resolver->resolve(['entity_class' => 'App\Entity\Product']);

        $this->assertTrue($resolved['is_root']);
    }

    /** @test */
    public function isRootOptionCanBeSetToFalse(): void
    {
        $resolver = new OptionsResolver();

        $formType = $this->createFormType();
        $formType->configureOptions($resolver);

        $resolved = $resolver->resolve(['entity_class' => 'App\Entity\Product', 'is_root' => false]);

        $this->assertFalse($resolved['is_root']);
    }

    // ── Inverse-side auto-detection ───────────────────────────────────────────

    /**
     * OneToOne inverse-side associations (mappedBy set) must be skipped automatically.
     *
     * @test
     */
    public function inverseSideOneToOneAssociationIsSkippedAutomatically(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['profile']);
        $this->metadata->method('isSingleValuedAssociation')->with('profile')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->with('profile')->willReturn(false);
        $this->metadata->method('hasAssociation')->with('profile')->willReturn(true);

        $mapping = new \Doctrine\ORM\Mapping\OneToOneInverseSideMapping(
            'profile',
            'App\Entity\User',
            'App\Entity\UserProfile',
        );
        $mapping->mappedBy = 'user';
        $this->metadata->method('getAssociationMapping')->with('profile')->willReturn($mapping);

        $this->mapper->expects($this->never())->method('getAssociationConfig');
        $this->builder->expects($this->never())->method('add');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormUserEntity::class,
            'is_root'      => true,
        ]);
    }

    /**
     * ManyToMany inverse-side (mappedBy set) must be skipped automatically.
     *
     * @test
     */
    public function inverseSideManyToManyIsSkippedAutomatically(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['products']);
        $this->metadata->method('isSingleValuedAssociation')->with('products')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('products')->willReturn(true);
        $this->metadata->method('hasAssociation')->with('products')->willReturn(true);

        $mapping = new \Doctrine\ORM\Mapping\ManyToManyInverseSideMapping(
            'products',
            'App\Entity\Tag',
            'App\Entity\Product',
        );
        $mapping->mappedBy = 'tags';
        $this->metadata->method('getAssociationMapping')->with('products')->willReturn($mapping);

        $this->mapper->expects($this->never())->method('getAssociationConfig');
        $this->builder->expects($this->never())->method('add');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormTagEntity::class,
            'is_root'      => true,
        ]);
    }

    /**
     * ManyToOne without inversedBy (standalone relationship, e.g. Product → Category)
     * must always be included — it is not a parent back-reference.
     *
     * @test
     */
    public function owningSideManyToOneIsIncluded(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['category']);
        $this->metadata->method('isSingleValuedAssociation')->with('category')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->with('category')->willReturn(false);
        $this->metadata->method('hasAssociation')->with('category')->willReturn(true);

        // ManyToOne without inversedBy — standalone, always owning side
        $mapping = new \Doctrine\ORM\Mapping\ManyToOneAssociationMapping(
            'category',
            'App\Entity\Product',
            'App\Entity\Category',
        );
        // inversedBy intentionally absent
        $this->metadata->method('getAssociationMapping')->with('category')->willReturn($mapping);

        $this->mapper->expects($this->once())
            ->method('getAssociationConfig')
            ->willReturn([
                'type'    => \Symfony\Bridge\Doctrine\Form\Type\EntityType::class,
                'options' => ['class' => 'App\Entity\Category', 'required' => false],
            ]);

        $this->builder->expects($this->once())->method('add');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormProductEntity::class,
            'is_root'      => true,
        ]);
    }

    /**
     * ManyToMany owning side (inversedBy set, mappedBy absent) must be included.
     *
     * @test
     */
    public function owningSideManyToManyIsIncluded(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['tags']);
        $this->metadata->method('isSingleValuedAssociation')->with('tags')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);
        $this->metadata->method('hasAssociation')->with('tags')->willReturn(true);

        // ManyToMany owning side: mappedBy is absent
        $mapping = new \Doctrine\ORM\Mapping\ManyToManyOwningSideMapping(
            'tags',
            'App\Entity\Product',
            'App\Entity\Tag',
        );
        $this->metadata->method('getAssociationMapping')->with('tags')->willReturn($mapping);

        $this->mapper->expects($this->once())
            ->method('getAssociationConfig')
            ->willReturn([
                'type'    => \Symfony\Bridge\Doctrine\Form\Type\EntityType::class,
                'options' => ['class' => 'App\Entity\Tag', 'multiple' => true, 'required' => false],
            ]);

        $this->builder->expects($this->once())->method('add');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormTaggableEntity::class,
            'is_root'      => true,
        ]);
    }

    /**
     * An inverse-side association marked #[AdminColumn(editable: true)] must be
     * included despite having mappedBy set — explicit opt-in overrides auto-detection.
     *
     * @test
     */
    public function inverseSideWithEditableTrueIsIncluded(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['userInverse']);
        $this->metadata->method('isSingleValuedAssociation')->with('userInverse')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->with('userInverse')->willReturn(false);
        $this->metadata->method('hasAssociation')->with('userInverse')->willReturn(true);

        // OneToOne inverse side — but explicitly opted in via editable:true
        $mapping = new \Doctrine\ORM\Mapping\OneToOneInverseSideMapping(
            'userInverse',
            'App\Entity\UserProfile',
            'App\Entity\User',
        );
        $mapping->mappedBy = 'profile';
        $this->metadata->method('getAssociationMapping')->with('userInverse')->willReturn($mapping);

        $this->mapper->expects($this->once())
            ->method('getAssociationConfig')
            ->willReturn([
                'type'    => \Symfony\Bridge\Doctrine\Form\Type\EntityType::class,
                'options' => ['class' => 'App\Entity\User', 'required' => false],
            ]);

        $this->builder->expects($this->once())->method('add');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormEntityWithExplicitInverse::class,
            'is_root'      => true,
        ]);
    }

    // ── ManyToOne with inversedBy — parent back-reference detection ───────────

    /**
     * ManyToOne with inversedBy set is a back-reference to a parent entity's OneToMany
     * collection. It must be skipped in all forms (root and child alike) to avoid
     * exposing a confusing parent-selector widget.
     *
     * Concrete example: OrderLineItem::$order (ManyToOne, inversedBy: 'lineItems')
     * should never appear on the OrderLineItem form — the parent form manages the
     * relationship.
     *
     * @test
     */
    public function manyToOneWithInversedByIsSkippedAutomatically(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['order']);
        $this->metadata->method('isSingleValuedAssociation')->with('order')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->with('order')->willReturn(false);
        $this->metadata->method('hasAssociation')->with('order')->willReturn(true);

        // ManyToOne with inversedBy — back-reference to parent's OneToMany
        $mapping = new \Doctrine\ORM\Mapping\ManyToOneAssociationMapping(
            'order',
            DynFormLineItemEntity::class,
            'App\Entity\OrderWithLines',
        );
        $mapping->inversedBy = 'lineItems';
        $this->metadata->method('getAssociationMapping')->with('order')->willReturn($mapping);

        $this->mapper->expects($this->never())->method('getAssociationConfig');
        $this->builder->expects($this->never())->method('add');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormLineItemEntity::class,
            'is_root'      => true, // even in root form, back-reference is hidden
        ]);
    }

    /**
     * ManyToOne with inversedBy AND #[AdminColumn(editable: true)] must be included —
     * explicit opt-in overrides the automatic back-reference detection.
     *
     * @test
     */
    public function manyToOneWithInversedByAndEditableTrueIsIncluded(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['orderExplicit']);
        $this->metadata->method('isSingleValuedAssociation')->with('orderExplicit')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->with('orderExplicit')->willReturn(false);
        $this->metadata->method('hasAssociation')->with('orderExplicit')->willReturn(true);

        $mapping = new \Doctrine\ORM\Mapping\ManyToOneAssociationMapping(
            'orderExplicit',
            DynFormLineItemWithOptIn::class,
            'App\Entity\OrderWithLines',
        );
        $mapping->inversedBy = 'lineItems';
        $this->metadata->method('getAssociationMapping')->with('orderExplicit')->willReturn($mapping);

        $this->mapper->expects($this->once())
            ->method('getAssociationConfig')
            ->willReturn([
                'type'    => \Symfony\Bridge\Doctrine\Form\Type\EntityType::class,
                'options' => ['class' => 'App\Entity\OrderWithLines', 'required' => false],
            ]);

        $this->builder->expects($this->once())->method('add');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class' => DynFormLineItemWithOptIn::class,
            'is_root'      => true,
        ]);
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

// ── Additional fixtures for inverse-side tests ─────────────────────────────────

class DynFormUserEntity
{
    private ?object $profile = null; // @phpstan-ignore property.unusedType (OneToOne inverse side — skipped automatically)
}

class DynFormTagEntity
{
    /** @var array<int, mixed> */
    private array $products = []; // ManyToMany inverse side — skipped automatically
}

class DynFormEntityWithExplicitInverse
{
    #[\Kachnitel\AdminBundle\Attribute\AdminColumn(editable: true)]
    private ?object $userInverse = null; // @phpstan-ignore property.unusedType (inverse side but explicitly opted in)
}

// ── Fixtures for ManyToOne inversedBy tests ───────────────────────────────────

/** OrderLineItem analogue — has a ManyToOne back-reference to the parent. */
class DynFormLineItemEntity
{
    private ?object $order = null; // @phpstan-ignore property.unusedType (ManyToOne with inversedBy — skipped automatically)
}

/** Line item with explicit opt-in so the parent back-reference IS shown. */
class DynFormLineItemWithOptIn
{
    #[\Kachnitel\AdminBundle\Attribute\AdminColumn(editable: true)]
    private ?object $orderExplicit = null; // @phpstan-ignore property.unusedType (explicitly opted in)
}
