<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Utils;

use ReflectionClass;
use ReflectionProperty;

/**
 * Property reflection that includes PRIVATE properties of ancestors.
 *
 * ReflectionClass::getProperties() / getProperty() on a subclass skip private properties
 * declared on a parent (e.g. a #[ORM\MappedSuperclass]), so attributes such as
 * #[ColumnPermission] or #[AdminColumn] placed there would be silently ignored. Here the
 * hierarchy is walked and the most-derived declaration of a name wins.
 */
final class PropertyReflection
{
    /**
    * @param ReflectionClass<*> $class
     * @return array<string, ReflectionProperty> keyed by property name
     */
    public static function all(ReflectionClass $class): array
    {
        $properties = [];

        for ($current = $class; $current !== false; $current = $current->getParentClass()) {
            foreach ($current->getProperties() as $property) {
                $properties[$property->getName()] ??= $property;
            }
        }

        return $properties;
    }

    /**
    * @param ReflectionClass<*> $class
     * @throws \ReflectionException when no class in the hierarchy declares the property
     */
    public static function get(ReflectionClass $class, string $name): ReflectionProperty
    {
        return self::find($class, $name)
            ?? throw new \ReflectionException(sprintf('Property %s::$%s does not exist', $class->getName(), $name));
    }

    /**
    * @param ReflectionClass<*> $class
     */
    public static function find(ReflectionClass $class, string $name): ?ReflectionProperty
    {
        return self::all($class)[$name] ?? null;
    }
}
