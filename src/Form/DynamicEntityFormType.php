<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Form;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Auto-generates a Symfony form for any Doctrine entity without requiring a hand-written FormType.
 *
 * Field inclusion rules:
 *   - The identifier field (single PK) is always excluded
 *   - Fields/associations with #[AdminColumn(editable: false)] are excluded
 *   - Inverse-side associations (mappedBy set) are skipped by default — they are managed
 *     by the owning side. Opt back in with #[AdminColumn(editable: true)].
 *   - Fields whose Doctrine type has no Symfony form equivalent (e.g. json) are silently skipped
 *   - All scalar fields and owning-side associations are always included
 *   - Collection-valued associations (ManyToMany, OneToMany) are included by default
 *     when is_root is true; skipped when is_root is false to prevent infinite recursion
 *
 * Collection mapping:
 *   - ManyToMany → EntityType with multiple: true (multi-select)
 *   - OneToMany  → LiveCollectionType with recursive DynamicEntityFormType as entry_type
 *
 * @see docs/DYNAMIC_FORM_COLLECTIONS.md for requirements (cascade, orphanRemoval, inverse side hiding)
 *
 * The is_root option (default: true) controls whether this is a top-level form or a
 * child entry inside a LiveCollectionType. Child forms skip collection associations
 * to prevent infinite recursion in bidirectional relationships.
 *
 * The form type does NOT set `data_class` — that is the caller's responsibility.
 * AdminEntityForm passes `data_class` explicitly via form options so that the form
 * is bound to the correct entity instance.
 *
 * Required option:
 *   entity_class (string) — fully-qualified class name of the entity to build for
 *
 * Optional option:
 *   is_root (bool, default: true) — set to false for child forms inside LiveCollectionType
 *
 * @extends AbstractType<object>
 */
class DynamicEntityFormType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DoctrineFormTypeMapper $mapper,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var class-string $entityClass */
        $entityClass = $options['entity_class'];
        $isRoot      = (bool) ($options['is_root'] ?? true);
        $metadata    = $this->em->getClassMetadata($entityClass);
        $idField     = $metadata->getSingleIdentifierFieldName();

        // ── Scalar fields ──────────────────────────────────────────────────────

        foreach ($metadata->getFieldNames() as $fieldName) {
            $this->addScalarField($builder, $metadata, $entityClass, $fieldName, $idField);
        }

        // ── Associations ───────────────────────────────────────────────────────

        foreach ($metadata->getAssociationNames() as $assocName) {
            $this->addAssociationField($builder, $metadata, $entityClass, $assocName, $isRoot);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('entity_class');
        $resolver->setAllowedTypes('entity_class', 'string');

        $resolver->setDefault('is_root', true);
        $resolver->setAllowedTypes('is_root', 'bool');

        // data_class is intentionally NOT set here — the caller sets it
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * @param FormBuilderInterface<object|null> $builder
     * @param ClassMetadata<object> $metadata
     * @param class-string $entityClass
     */
    private function addScalarField(FormBuilderInterface $builder, ClassMetadata $metadata, string $entityClass, string $fieldName, ?string $idField): void
    {
        if ($fieldName === $idField) {
            return;
        }

        if ($this->isEditableBlocked($entityClass, $fieldName)) {
            return;
        }

        $config = $this->mapper->getFieldConfig($metadata, $fieldName);
        if ($config === null) {
            return; // unsupported type — skip silently
        }

        $builder->add($fieldName, $config['type'], $config['options']);
    }

    /**
     * @param FormBuilderInterface<object|null> $builder
     * @param ClassMetadata<object> $metadata
     * @param class-string $entityClass
     */
    private function addAssociationField(FormBuilderInterface $builder, ClassMetadata $metadata, string $entityClass, string $assocName, bool $isRoot): void
    {
        if ($this->isEditableBlocked($entityClass, $assocName)) {
            return;
        }

        $isCollection = $metadata->isCollectionValuedAssociation($assocName);

        if ($isCollection && !$isRoot) {
            return;
        }

        if ($this->shouldSkipInverseSide($metadata, $assocName, $entityClass, $isCollection, $isRoot)) {
            return;
        }

        if ($this->isBackReferenceToParent($metadata, $assocName, $entityClass) && !$isCollection) {
            return;
        }

        $config = $this->mapper->getAssociationConfig($metadata, $assocName);
        if ($config === null) {
            return;
        }

        $builder->add($assocName, $config['type'], $config['options']);
    }

    /**
     * Returns true when an inverse-side association should be skipped.
     *
     * Inverse-side associations (mappedBy set) are skipped by default to avoid
     * redundant controls, EXCEPT OneToMany collections in root forms are kept.
     *
     * @param ClassMetadata<object> $metadata
     * @param class-string $entityClass
     */
    private function shouldSkipInverseSide(ClassMetadata $metadata, string $assocName, string $entityClass, bool $isCollection, bool $isRoot): bool
    {
        if (!$metadata->hasAssociation($assocName)) {
            return false;
        }

        $mapping = $metadata->getAssociationMapping($assocName);
        $mappedBy = $mapping->mappedBy ?? null;

        // No mappedBy → this is an owning-side association, don't skip
        if ($mappedBy === null || $mappedBy === '') {
            return false;
        }

        // This is an inverse-side association (has mappedBy)
        // Keep OneToMany collections in root forms; skip everything else
        if ($isCollection && $isRoot && $mapping instanceof OneToManyAssociationMapping) {
            return false; // OneToMany in root form: include
        }

        // For anything else (OneToOne inverse, ManyToMany inverse, collections in child forms),
        // check for explicit opt-in
        return !$this->isEditableExplicitlyEnabled($entityClass, $assocName);
    }

    /**
     * Returns true when a single-valued association is a back-reference to a parent entity.
     *
     * A back-reference occurs when:
     *   - The association is single-valued (ManyToOne, OneToOne inverse)
     *   - It has `inversedBy` set (ManyToOne pointing to a parent's OneToMany collection)
     *
     * Such associations are managed by the parent form and should not be included
     * in child forms to avoid confusing UI. Opt back in with #[AdminColumn(editable: true)].
     *
     * @param ClassMetadata<object> $metadata
     * @param class-string $entityClass
     */
    private function isBackReferenceToParent(ClassMetadata $metadata, string $assocName, string $entityClass): bool
    {
        if (!$metadata->hasAssociation($assocName)) {
            return false;
        }

        $mapping = $metadata->getAssociationMapping($assocName);

        // Check for inversedBy (ManyToOne pointing to a parent's OneToMany collection)
        $inversedBy = $mapping->inversedBy ?? null;
        if ($inversedBy !== null && $inversedBy !== '') {
            return !$this->isEditableExplicitlyEnabled($entityClass, $assocName);
        }

        return false;
    }

    /**
     * Returns true when a property carries #[AdminColumn(editable: true)],
     * meaning the developer explicitly wants this field included even if it
     * would otherwise be skipped (e.g. inverse side of a bidirectional association).
     *
     * @param class-string $entityClass
     */
    private function isEditableExplicitlyEnabled(string $entityClass, string $property): bool
    {
        try {
            $reflection = new \ReflectionClass($entityClass);

            if (!$reflection->hasProperty($property)) {
                return false;
            }

            $attributes = $reflection->getProperty($property)->getAttributes(AdminColumn::class);

            if (empty($attributes)) {
                return false;
            }

            /** @var AdminColumn $col */
            $col = $attributes[0]->newInstance();

            return $col->editable === true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Returns true when a property carries #[AdminColumn(editable: false)],
     * meaning it must be excluded from the generated form.
     *
     * No attribute, or editable: null/true/'expression', all return false (include).
     *
     * @param class-string $entityClass
     */
    private function isEditableBlocked(string $entityClass, string $property): bool
    {
        try {
            $reflection = new \ReflectionClass($entityClass);

            if (!$reflection->hasProperty($property)) {
                return false;
            }

            $attributes = $reflection->getProperty($property)->getAttributes(AdminColumn::class);

            if (empty($attributes)) {
                return false;
            }

            /** @var AdminColumn $col */
            $col = $attributes[0]->newInstance();

            return $col->editable === false;
        } catch (\Throwable) {
            return false;
        }
    }
}
