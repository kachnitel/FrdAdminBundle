<?php

namespace Frd\AdminBundle\Service;

use Doctrine\Persistence\Proxy;

/**
 * Helper service for working with PHP attributes on entities.
 */
class AttributeHelper
{
    /**
     * Get an attribute from an entity class.
     *
     * @template T
     * @param object|string $entity
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    public function getAttribute(object|string $entity, string $attributeClass): mixed
    {
        if (!is_object($entity) && !class_exists($entity)) {
            return null;
        }

        $class = is_object($entity) ? get_class($entity) : $entity;
        $realClass = $this->getRealClass($class);

        $reflectionClass = new \ReflectionClass($realClass);
        $reflectionAttr = $reflectionClass->getAttributes($attributeClass)[0] ?? null;

        return $reflectionAttr ? $reflectionAttr->newInstance() : null;
    }

    /**
     * Get an attribute from a property.
     */
    public function getPropertyAttribute(object|string $entity, string $property, string $attributeClass): mixed
    {
        if (!is_object($entity) && !class_exists($entity)) {
            return null;
        }

        $class = is_object($entity) ? get_class($entity) : $entity;
        $realClass = $this->getRealClass($class);

        $reflectionClass = new \ReflectionClass($realClass);
        $reflectionProperty = $reflectionClass->getProperty($property);
        $reflectionAttr = $reflectionProperty->getAttributes($attributeClass)[0] ?? null;

        return $reflectionAttr ? $reflectionAttr->newInstance() : null;
    }

    /**
     * Get the real class name (unwrap Doctrine proxies).
     */
    private function getRealClass(string|object $entityOrClass): string
    {
        $class = is_object($entityOrClass) ? $entityOrClass::class : $entityOrClass;

        if (is_subclass_of($class, Proxy::class, true)) {
            return get_parent_class($class);
        }

        return $class;
    }
}
