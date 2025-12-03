<?php

namespace Frd\AdminBundle\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Runtime for entity data access in templates.
 */
class AdminEntityDataRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ?NormalizerInterface $normalizer = null
    ) {}

    /**
     * Get all field and association data for an entity.
     *
     * Note: Collection-valued associations are excluded to avoid memory issues.
     * To display collections, use custom templates or the _collection.html.twig template.
     */
    public function getData(object $entity): array
    {
        $metadata = $this->em->getClassMetadata(get_class($entity));
        $fields = $metadata->getFieldNames();

        $data = [];
        foreach ($fields as $field) {
            $value = $this->getPropertyValue($entity, $field);
            $data[$field] = $this->normalizeValue($value);
        }

        $associations = $metadata->getAssociationNames();
        foreach ($associations as $association) {
            $value = $this->getPropertyValue($entity, $association);

            // For collection-valued associations, return the collection object itself
            // This allows templates to call count()/length without loading all entities
            // Doctrine collections implement Countable and can count efficiently via SQL
            if ($metadata->isCollectionValuedAssociation($association)) {
                $data[$association] = $value; // Return collection as-is, don't normalize
            } else {
                $data[$association] = $this->normalizeValue($value);
            }
        }

        return $data;
    }

    /**
     * Get column names for an entity class.
     *
     * Note: Collection-valued associations are excluded from columns.
     */
    public function getColumns(string $entityClass): array
    {
        $metadata = $this->em->getClassMetadata($entityClass);
        $columns = $metadata->getFieldNames();

        // Only include single-valued associations (ManyToOne, OneToOne)
        foreach ($metadata->getAssociationNames() as $association) {
            if (!$metadata->isCollectionValuedAssociation($association)) {
                $columns[] = $association;
            }
        }

        return $columns;
    }

    /**
     * Check if a property is a collection-valued association.
     */
    public function isCollection(object $entity, string $property): bool
    {
        $metadata = $this->em->getClassMetadata(get_class($entity));
        return $metadata->isCollectionValuedAssociation($property);
    }

    /**
     * Check if a property is an association (single or collection).
     */
    public function isAssociation(object $entity, string $property): bool
    {
        $metadata = $this->em->getClassMetadata(get_class($entity));
        return $metadata->isSingleValuedAssociation($property)
            || $metadata->isCollectionValuedAssociation($property);
    }

    /**
     * Get the target class for an association.
     */
    public function getAssociationType(object $entity, string $property): ?string
    {
        if (!$this->isAssociation($entity, $property)) {
            return null;
        }

        $metadata = $this->em->getClassMetadata(get_class($entity));
        return $metadata->getAssociationTargetClass($property);
    }

    /**
     * Get the type of a property (field type or association target class).
     */
    public function getPropertyType(object $entity, string $property): string
    {
        $metadata = $this->em->getClassMetadata(get_class($entity));
        return $this->isAssociation($entity, $property)
            ? $metadata->getAssociationTargetClass($property)
            : $metadata->getTypeOfField($property);
    }

    /**
     * Get property value using getter method.
     */
    private function getPropertyValue(object $entity, string $property): mixed
    {
        $getter = 'get' . ucfirst($property);
        if (!method_exists($entity, $getter)) {
            $getter = 'is' . ucfirst($property);
        }
        if (!method_exists($entity, $getter)) {
            return null;
        }

        return $entity->$getter();
    }

    /**
     * Normalize a value for display (uses Symfony Serializer if available).
     *
     * For related entities (objects), we return them as-is so they can be
     * rendered properly in templates with access to their properties.
     */
    private function normalizeValue(mixed $value): mixed
    {
        // Don't normalize objects - let the template handle them
        // This allows Doctrine proxies to work correctly
        if (is_object($value)) {
            return $value;
        }

        if ($this->normalizer === null) {
            return $value;
        }

        try {
            return $this->normalizer->normalize($value, 'array', [
                'circular_reference_handler' => function ($object) {
                    return method_exists($object, 'getId') ? $object->getId() : null;
                }
            ]);
        } catch (\Exception $e) {
            return $value;
        }
    }
}
