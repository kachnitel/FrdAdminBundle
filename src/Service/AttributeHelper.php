<?php

namespace Kachnitel\AdminBundle\Service;

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
     *
     * @template T of object
     * @param object|class-string $entity
     * @param class-string<T> $attributeClass
     * @return T|null
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
     *
     * @param class-string|string $class
     * @return class-string
     */
    private function getRealClass(string $class): string
    {
        if (!class_exists($class)) {
            /** @var class-string $class */
            return $class;
        }

        if (is_subclass_of($class, Proxy::class, true)) {
            $parent = get_parent_class($class);
            if ($parent !== false) {
                return $parent;
            }
        }

        /** @var class-string $class */
        return $class;
    }
}
