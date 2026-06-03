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
 *   - Collection-valued associations are excluded for now
 *   - Fields whose Doctrine type has no Symfony form equivalent (e.g. json) are silently skipped
 *   - All other scalar fields and single-valued associations are included
 *
 * The form type does NOT set `data_class` — that is the caller's responsibility.
 * AdminEntityForm passes `data_class` explicitly via form options so that the form
 * is bound to the correct entity instance.
 *
 * Required option:
 *   entity_class (string) — fully-qualified class name of the entity to build for

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

        // Single-valued associations (ManyToOne / OneToOne).
        // Collection associations are intentionally deferred.
        foreach ($metadata->getAssociationNames() as $assocName) {
            if (!$metadata->isSingleValuedAssociation($assocName)) {
                continue;
            }

            if ($this->isEditableBlocked($entityClass, $assocName)) {
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
