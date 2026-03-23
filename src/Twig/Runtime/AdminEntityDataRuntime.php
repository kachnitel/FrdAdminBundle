<?php

namespace Kachnitel\AdminBundle\Twig\Runtime;

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
     * @return array<string, mixed>
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
            : $metadata->getTypeOfField($property);
    }

    /**
     * Get template paths for a column in priority order.
     *
     * Returns an array of template paths to try for rendering a column value.
     * The template resolution follows this hierarchy:
     *
     * For DataSources (when dataSourceId is set):
     *   1. data/{dataSourceId}/{column}.html.twig - DataSource-specific property
     *   2. {columnType}/_preview.html.twig        - Type-specific
     *   3. _preview.html.twig                     - Default fallback
     *
     * For Doctrine Entities (when entityClass is set):
     *   1. {entityClass}/{column}.html.twig       - Entity-specific property
     *   2. {propertyType}/_preview.html.twig      - Type-specific
     *   3. _preview.html.twig                     - Default fallback
     *
     * @param string|null $dataSourceId Data source identifier (for custom datasources)
     * @param string|null $entityClass  Entity class name (for Doctrine entities)
     * @param string      $column       Column name
     * @param string      $columnType   Column type (string, boolean, integer, etc.)
     * @param bool        $isCollection Whether this is a collection field
     * @return list<string> Template paths in priority order
     */
    public function getColumnTemplates(
        ?string $dataSourceId,
        ?string $entityClass,
        string $column,
        string $columnType,
        bool $isCollection = false
    ): array {
        $templateName = $isCollection ? '_collection.html.twig' : '_preview.html.twig';
        $templates = [];

        if ($dataSourceId !== null && $entityClass === null) {
            // DataSource-specific: data/{dataSourceId}/{column}.html.twig
            $templates[] = '@KachnitelAdmin/types/data/' . $dataSourceId . '/' . $column . '.html.twig';
        } elseif ($entityClass !== null) {
            // Entity-specific: {entityClass}/{column}.html.twig
            $templates[] = '@KachnitelAdmin/types/' . str_replace('\\', '/', $entityClass) . '/' . $column . '.html.twig';
        }

        // Type-specific: {columnType}/_preview.html.twig
        $templates[] = '@KachnitelAdmin/types/' . str_replace('\\', '/', $columnType) . '/' . $templateName;

        // Default fallback: _preview.html.twig
        $templates[] = '@KachnitelAdmin/types/' . $templateName;

        return $templates;
    }

    /**
     * Get template paths for a Doctrine entity column in one call.
     *
     * Consolidates the three-step pattern used in templates:
     *   admin_is_collection + admin_get_property_type + admin_column_templates
     * into a single function, keeping all type-detection logic in PHP.
     *
     * @return list<string>
     */
    public function getEntityColumnTemplates(object $entity, string $column): array
    {
        $entityClass = $this->resolveEntityClass($entity);
        $metadata    = $this->em->getClassMetadata($entityClass);

        $isCollection = $metadata->isCollectionValuedAssociation($column);
        $propertyType = $metadata->hasAssociation($column)
            ? $metadata->getAssociationTargetClass($column)
            : ($metadata->getTypeOfField($column) ?? 'string');

        return $this->getColumnTemplates(null, $entityClass, $column, $propertyType, $isCollection);
    }

    /**
     * Resolve the LiveComponent name for a column's inline-edit field.
     *
     * Components are registered by kachnitel/entity-components-bundle under the
     * K:Entity:Field:* namespace.
     *
     * Resolution order:
     *   Doctrine association (collection)  → K:Entity:Field:Collection
     *   Doctrine association (single)      → K:Entity:Field:Relationship
     *   Date/time types                    → K:Entity:Field:Date
     *   PHP enum field                     → K:Entity:Field:Enum
     *   integer/bigint/smallint            → K:Entity:Field:Int
     *   float/decimal                      → K:Entity:Field:Float
     *   boolean                            → K:Entity:Field:Bool
     *   string / unknown / fallback        → K:Entity:Field:String
     */
    public function getFieldComponentName(object $entity, string $column): ?string
    {
        $entityClass = $this->resolveEntityClass($entity);

        try {
            $metadata = $this->em->getClassMetadata($entityClass);

            if ($metadata->hasAssociation($column)) {
                return $metadata->isSingleValuedAssociation($column)
                    ? 'K:Entity:Field:Relationship'
                    : 'K:Entity:Field:Collection';
            }

            if (!$metadata->hasField($column)) {
                return null;
            }

            $fieldType = $metadata->getTypeOfField($column);

            if ($this->isDateTimeFieldType($fieldType)) {
                return 'K:Entity:Field:Date';
            }

            if ($this->isEnumField($metadata, $entityClass, $column)) {
                return 'K:Entity:Field:Enum';
            }

            return $this->mapScalarFieldType($fieldType);
        } catch (\ReflectionException) {
            return null;
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function isDateTimeFieldType(?string $fieldType): bool
    {
        return in_array($fieldType, [
            'date', 'datetime', 'datetimetz', 'time',
            'date_immutable', 'datetime_immutable', 'datetimetz_immutable', 'time_immutable',
        ], true);
    }

    /**
     * @param \Doctrine\ORM\Mapping\ClassMetadata<object> $metadata
     */
    private function isEnumField(
        \Doctrine\ORM\Mapping\ClassMetadata $metadata,
        string $entityClass,
        string $column
    ): bool {
        if (!empty($metadata->getFieldMapping($column)->enumType)) {
            return true;
        }

        try {
            $reflProp = (new \ReflectionClass($entityClass))->getProperty($column);
            $type     = $reflProp->getType();
            return $type instanceof \ReflectionNamedType
                && !$type->isBuiltin()
                && enum_exists($type->getName());
        } catch (\ReflectionException) {
            return false;
        }
    }

    private function mapScalarFieldType(?string $fieldType): string
    {
        return match ($fieldType) {
            'integer', 'bigint', 'smallint' => 'K:Entity:Field:Int',
            'float', 'decimal'              => 'K:Entity:Field:Float',
            'boolean'                       => 'K:Entity:Field:Bool',
            default                         => 'K:Entity:Field:String',
        };
    }

    /**
     * Resolve the real entity class from an object, stripping Doctrine proxy prefixes.
     * Doctrine proxies are subclasses; get_parent_class() gives the mapped entity class.
     */
    private function resolveEntityClass(object $entity): string
    {
        $class = $entity::class;
        if (str_contains($class, 'Proxies\\__CG__\\')) {
            $parent = get_parent_class($entity);
            return $parent !== false ? $parent : $class;
        }

        return $class;
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
