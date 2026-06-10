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
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @group auto-form
 */
#[CoversClass(DynamicEntityFormType::class)]
#[UsesClass(AdminColumn::class)]
#[Group('auto-form')]
class DynamicEntityFormTypeTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var DoctrineFormTypeMapper&MockObject */
    private DoctrineFormTypeMapper $mapper;

    private DynamicEntityFormType $formType;

    protected function setUp(): void
    {
        $this->em      = $this->createMock(EntityManagerInterface::class);
        $this->mapper  = $this->createMock(DoctrineFormTypeMapper::class);
        $this->formType = new DynamicEntityFormType($this->em, $this->mapper);
    }

    // ── configureOptions ───────────────────────────────────────────────────────

    public function testEntityClassOptionIsRequired(): void
    {
        $resolver = $this->makeResolver();

        $this->expectException(MissingOptionsException::class);

        $resolver->resolve([]); // entity_class not supplied
    }

    public function testEntityClassMustBeAString(): void
    {
        $resolver = $this->makeResolver();

        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);

        $resolver->resolve(['entity_class' => 42]);
    }

    public function testDataClassIsNotSetByDynamicFormType(): void
    {
        $resolver = $this->makeResolver();

        // Simulate Symfony's FormType registering data_class with a null default.
        // DynamicEntityFormType must NOT override it — the caller (AdminEntityForm) passes
        // it explicitly, so the form type itself must leave it untouched.
        $options = $resolver->resolve(['entity_class' => 'App\\Entity\\Product']);

        $this->assertNull($options['data_class'], 'data_class must remain null — set by caller, not form type');
    }

    public function testEntityClassOptionIsAccepted(): void
    {
        $resolver = $this->makeResolver();

        $options = $resolver->resolve(['entity_class' => 'App\\Entity\\Product']);

        $this->assertSame('App\\Entity\\Product', $options['entity_class']);
    }

    // ── buildForm: ID field excluded ───────────────────────────────────────────

    public function testIdFieldIsNeverAdded(): void
    {
        $metadata = $this->makeMetadata(
            idField:    'id',
            fields:     ['id', 'name'],
            assocNames: [],
        );
        $this->em->method('getClassMetadata')->willReturn($metadata);

        // Mapper returns a valid config for 'name' but never for 'id'
        $this->mapper->method('getFieldConfig')
            ->willReturnCallback(fn (ClassMetadata $m, string $f) => match ($f) {
                'name'  => ['type' => 'Symfony\Component\Form\Extension\Core\Type\TextType', 'options' => []],
                default => null,
            });

        [$addedFields, $builder] = $this->makeBuilder();

        $this->formType->buildForm($builder, ['entity_class' => DynFormFixtureEntity::class]);

        $this->assertNotContains('id', $addedFields, 'ID field must never be added');
        $this->assertContains('name', $addedFields);
    }

    // ── buildForm: editable:false excluded ─────────────────────────────────────

    public function testEditableFalseFieldIsExcluded(): void
    {
        $metadata = $this->makeMetadata(
            idField: 'id',
            fields:  ['id', 'title', 'locked'],
        );
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $this->mapper->method('getFieldConfig')
            ->willReturn(['type' => 'Symfony\Component\Form\Extension\Core\Type\TextType', 'options' => []]);

        [$addedFields, $builder] = $this->makeBuilder();

        $this->formType->buildForm($builder, ['entity_class' => DynFormFixtureWithLockedField::class]);

        $this->assertContains('title', $addedFields);
        $this->assertNotContains('locked', $addedFields, 'Field with editable:false must be excluded');
    }

    public function testFieldWithNoAdminColumnAttributeIsIncluded(): void
    {
        $metadata = $this->makeMetadata(
            idField: 'id',
            fields:  ['id', 'description'],
        );
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $this->mapper->method('getFieldConfig')
            ->willReturn(['type' => 'Symfony\Component\Form\Extension\Core\Type\TextType', 'options' => []]);

        [$addedFields, $builder] = $this->makeBuilder();

        $this->formType->buildForm($builder, ['entity_class' => DynFormFixtureEntity::class]);

        $this->assertContains('description', $addedFields, 'No AdminColumn attribute means include by default');
    }

    // ── buildForm: unsupported mapper types silently skipped ──────────────────

    public function testUnsupportedFieldTypeIsSkipped(): void
    {
        $metadata = $this->makeMetadata(
            idField: 'id',
            fields:  ['id', 'jsonData', 'name'],
        );
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $this->mapper->method('getFieldConfig')
            ->willReturnCallback(fn (ClassMetadata $m, string $f) => match ($f) {
                'jsonData' => null, // unsupported type
                'name'     => ['type' => 'Symfony\Component\Form\Extension\Core\Type\TextType', 'options' => []],
                default    => null,
            });

        [$addedFields, $builder] = $this->makeBuilder();

        $this->formType->buildForm($builder, ['entity_class' => DynFormFixtureEntity::class]);

        $this->assertNotContains('jsonData', $addedFields, 'Mapper-unsupported types must be silently skipped');
        $this->assertContains('name', $addedFields);
    }

    // ── buildForm: associations ────────────────────────────────────────────────

    public function testSingleValuedAssociationIsIncluded(): void
    {
        $metadata = $this->makeMetadata(
            idField:    'id',
            fields:     ['id'],
            assocNames: ['category'],
            singleValuedAssocs: ['category'],
        );
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $this->mapper->method('getAssociationConfig')
            ->willReturn(['type' => 'Symfony\Bridge\Doctrine\Form\Type\EntityType', 'options' => ['class' => 'App\Entity\Category', 'required' => false]]);

        [$addedFields, $builder] = $this->makeBuilder();

        $this->formType->buildForm($builder, ['entity_class' => DynFormFixtureEntity::class]);

        $this->assertContains('category', $addedFields, 'Single-valued associations must be included');
    }

    /**
     * Collections are included by default in a root form (is_root: true / missing).
     * Opt out with #[AdminColumn(editable: false)].
     */
    public function testCollectionAssociationIsIncludedInRootFormByDefault(): void
    {
        $metadata = $this->makeMetadata(
            idField:              'id',
            fields:               ['id'],
            assocNames:           ['tags'],
            singleValuedAssocs:   [],
            collectionValuedAssocs: ['tags'],
        );
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $this->mapper->method('getAssociationConfig')
            ->with($metadata, 'tags')
            ->willReturn([
                'type'    => EntityType::class,
                'options' => ['class' => 'App\Entity\Tag', 'multiple' => true, 'required' => false],
            ]);

        [$addedFields, $builder] = $this->makeBuilder();

        // Default (no is_root key) → treated as root
        $this->formType->buildForm($builder, ['entity_class' => DynFormFixtureEntity::class]);

        $this->assertContains('tags', $addedFields, 'Collection associations are included by default in root forms');
    }

    /**
     * Collections are skipped in child forms (is_root: false) to prevent infinite
     * recursion in bidirectional relationships.
     */
    public function testCollectionAssociationIsExcludedInChildForm(): void
    {
        $metadata = $this->makeMetadata(
            idField:              'id',
            fields:               ['id'],
            assocNames:           ['tags'],
            singleValuedAssocs:   [],
            collectionValuedAssocs: ['tags'],
        );
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $this->mapper->expects($this->never())->method('getAssociationConfig');

        [$addedFields, $builder] = $this->makeBuilder();

        $this->formType->buildForm($builder, [
            'entity_class' => DynFormFixtureEntity::class,
            'is_root'      => false,
        ]);

        $this->assertNotContains('tags', $addedFields, 'Collections must be skipped in child forms');
    }

    public function testAssociationWithEditableFalseIsExcluded(): void
    {
        $metadata = $this->makeMetadata(
            idField:    'id',
            fields:     ['id'],
            assocNames: ['blockedAssoc'],
            singleValuedAssocs: ['blockedAssoc'],
        );
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $this->mapper->expects($this->never())->method('getAssociationConfig');

        [$addedFields, $builder] = $this->makeBuilder();

        $this->formType->buildForm($builder, ['entity_class' => DynFormFixtureWithLockedAssoc::class]);

        $this->assertNotContains('blockedAssoc', $addedFields);
    }

    // ── buildForm: form options are forwarded to builder.add() ────────────────

    public function testMapperOptionsArePassedToBuilderAdd(): void
    {
        $metadata = $this->makeMetadata(idField: 'id', fields: ['id', 'email']);
        $this->em->method('getClassMetadata')->willReturn($metadata);

        $expectedOptions = ['label' => 'Email', 'required' => true];
        $this->mapper->method('getFieldConfig')
            ->willReturn(['type' => 'Symfony\Component\Form\Extension\Core\Type\TextType', 'options' => $expectedOptions]);

        $capturedOptions = [];
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')
            ->willReturnCallback(function (string $name, string $type, array $opts) use ($builder, &$capturedOptions): FormBuilderInterface {
                $capturedOptions[$name] = $opts;
                return $builder;
            });

        $this->formType->buildForm($builder, ['entity_class' => DynFormFixtureEntity::class]);

        $this->assertSame($expectedOptions, $capturedOptions['email'] ?? []);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Build an OptionsResolver that simulates Symfony FormType registering data_class.
     */
    private function makeResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();

        // Simulate Symfony's base FormType registering data_class as nullable string.
        $resolver->setDefault('data_class', null);
        $resolver->setAllowedTypes('data_class', ['null', 'string']);

        $this->formType->configureOptions($resolver);

        return $resolver;
    }

    /**
     * Build a FormBuilderInterface mock and return it alongside a list of added field names.
     *
     * @return array{0: \ArrayObject<int, string>, 1: FormBuilderInterface<object|null>&MockObject}
     */
    private function makeBuilder(): array
    {
        $addedFields = new \ArrayObject();
        $builder     = $this->createMock(FormBuilderInterface::class);

        $builder->method('add')
            ->willReturnCallback(function (string $name) use ($builder, $addedFields): FormBuilderInterface {
                $addedFields->append($name);
                return $builder;
            });

        return [$addedFields, $builder];
    }

    /**
     * Build a ClassMetadata stub with the given configuration.
     *
     * @param array<string> $fields
     * @param array<string> $assocNames
     * @param array<string> $singleValuedAssocs
     * @param array<string> $collectionValuedAssocs
     * @return ClassMetadata<object>&MockObject
     */
    private function makeMetadata(
        string $idField = 'id',
        array $fields = [],
        array $assocNames = [],
        array $singleValuedAssocs = [],
        array $collectionValuedAssocs = [],
    ): ClassMetadata {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);

        $metadata->method('getSingleIdentifierFieldName')->willReturn($idField);
        $metadata->method('getFieldNames')->willReturn($fields);
        $metadata->method('getAssociationNames')->willReturn($assocNames);
        $metadata->method('isSingleValuedAssociation')
            ->willReturnCallback(fn (string $name): bool => in_array($name, $singleValuedAssocs, true));
        $metadata->method('isCollectionValuedAssociation')
            ->willReturnCallback(fn (string $name): bool => in_array($name, $collectionValuedAssocs, true));

        return $metadata;
    }
}

// ── Inline fixtures ────────────────────────────────────────────────────────────

/** Plain entity — no AdminColumn attributes on any property. */
class DynFormFixtureEntity
{
    private int    $id = 0;
    private string $name = '';
    private string $description = '';
    private string $email = '';
}

/** Entity where 'locked' field carries `editable: false`. */
class DynFormFixtureWithLockedField
{
    private int    $id = 0;
    private string $title = '';

    #[AdminColumn(editable: false)]
    private string $locked = '';
}

/** Entity where 'blockedAssoc' association carries `editable: false`. */
class DynFormFixtureWithLockedAssoc
{
    private int $id = 0;

    #[AdminColumn(editable: false)]
    private ?object $blockedAssoc = null; // @phpstan-ignore property.unusedType
}
