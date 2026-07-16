<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\DataSource\DoctrineItemValueResolver;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Runtime for entity data access in templates.
 */
class AdminEntityDataRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeHelper $attributeHelper,
        private DoctrineItemValueResolver $resolver,
        private ?NormalizerInterface $normalizer = null
    ) {}

    /**
     * Get all field and association data for an entity.
     *
     * Note: Collection-valued associations are excluded to avoid memory issues.
     * To display collections, use custom templates or the _collection.html.twig template.
     * @return array<string, mixed>
     */
    public function getData(object $entity): array
    {
        $metadata = $this->em->getClassMetadata(get_class($entity));
        $fields = $metadata->getFieldNames();

        $data = [];
        foreach ($fields as $field) {
            $value = $this->resolver->resolve($entity, $field, $metadata);
            $data[$field] = $this->normalizeValue($value);
        }

        $associations = $metadata->getAssociationNames();
        foreach ($associations as $association) {
            $value = $this->resolver->resolve($entity, $association, $metadata);

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
     * @return array<string>
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
            : ($metadata->getTypeOfField($property) ?? 'string');
    }

    /**
     * Get a human-readable label for an entity.
     *
     * Priority:
     *   1. Custom key/method (if $key is provided and exists)
     *   2. label / getLabel()
     *   3. name / getName()
     *   4. title / getTitle()
     *   5. __toString() (objects only)
     *   6. id / getId()
     *   7. Fallback to ClassName or "Array"
     */
    public function getEntityLabel(object $entity, ?string $getter = null): string
    {
        // Handle object methods and public properties
        foreach (array_filter([$getter, 'label', 'name', 'title']) as $prop) {
            // Use exact name for custom $getter, otherwise prepend 'get' and capitalize
            $method = $prop === $getter ? $getter : 'get' . ucfirst($prop);

            if (method_exists($entity, $method)) {
                return (string) $entity->$method();
            }

            if (isset($entity->$prop)) {
                return (string) $entity->$prop;
            }
        }

        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        // Fallbacks
        if (method_exists($entity, 'getId')) {
            return '#' . $entity->getId();
        }

        if (isset($entity->id)) {
            return '#' . $entity->id;
        }

        return (new \ReflectionClass($entity))->getShortName();
    }

    // ── Private helpers ────────────────────────────────────────────────────────

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
                'circular_reference_handler' => fn ($object) => (
                    is_object($object)
                    && method_exists($object, 'getId')
                 ) ? $object->getId() : null
            ]);
        } catch (\Exception) {
            return $value;
        }
    }
}
