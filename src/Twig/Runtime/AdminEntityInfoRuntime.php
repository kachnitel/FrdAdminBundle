<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Utils\ObjectHelper;

class AdminEntityInfoRuntime
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        private EntityManagerInterface $em
    ) {}

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
        $entityClass = ObjectHelper::getRealClass($entity);
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
        $entityClass = ObjectHelper::getRealClass($entity);

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

    /**
     * Get the #[AdminColumn] attribute for a property of a Doctrine entity, or null
     * if the property does not exist or carries no #[AdminColumn] attribute.
     *
     * Returns null for non-Doctrine rows (custom data source items) since the entity
     * class cannot be resolved to Doctrine metadata in that case.
     */
    public function getColumnAttribute(object $entity, string $column): ?AdminColumn
    {
        try {
            return $this->attributeHelper->getPropertyAttribute($entity, $column, AdminColumn::class);
        } catch (\ReflectionException) {
            return null;
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

    private function isDateTimeFieldType(?string $fieldType): bool
    {
        return in_array($fieldType, [
            'date', 'datetime', 'datetimetz', 'time',
            'date_immutable', 'datetime_immutable', 'datetimetz_immutable', 'time_immutable',
        ], true);
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param class-string $entityClass
     */
    private function isEnumField(
        ClassMetadata $metadata,
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
}
