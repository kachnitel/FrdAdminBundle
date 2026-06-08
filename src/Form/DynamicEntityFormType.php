<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Form;

use Doctrine\ORM\EntityManagerInterface;
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
 *   - Fields whose Doctrine type has no Symfony form equivalent (e.g. json) are silently skipped
 *   - All scalar fields and single-valued associations are always included
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
            if ($fieldName === $idField) {
                continue;
            }

            if ($this->isEditableBlocked($entityClass, $fieldName)) {
                continue;
            }

            $config = $this->mapper->getFieldConfig($metadata, $fieldName);
            if ($config === null) {
                continue; // unsupported type — skip silently
            }

            $builder->add($fieldName, $config['type'], $config['options']);
        }

        // ── Associations ───────────────────────────────────────────────────────

        foreach ($metadata->getAssociationNames() as $assocName) {
            if ($this->isEditableBlocked($entityClass, $assocName)) {
                continue;
            }

            $isCollection = $metadata->isCollectionValuedAssociation($assocName);

            // Skip collection associations in child forms to prevent infinite recursion.
            // Single-valued associations (ManyToOne, OneToOne) are always included.
            if ($isCollection && !$isRoot) {
                continue;
            }

            $config = $this->mapper->getAssociationConfig($metadata, $assocName);
            if ($config === null) {
                continue;
            }

            $builder->add($assocName, $config['type'], $config['options']);
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
