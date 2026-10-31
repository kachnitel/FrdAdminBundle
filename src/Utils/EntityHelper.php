<?php

namespace Kachnitel\AdminBundle\Utils;

class EntityHelper
{

    /**
     * Get a human-readable label for an entity.
     *
     * Priority:
     *   1. label / getLabel()
     *   2. name / getName()
     *   3. title / getTitle()
     *   4. __toString() (objects only)
     *   5. id / getId()
     *   6. Fallback to ClassName or "Array"
     */
    public static function getEntityLabel(object $entity): string
    {
        // Handle object methods and public properties
        foreach (['label', 'name', 'title'] as $prop) {
            // Use exact name for custom $getter, otherwise prepend 'get' and capitalize
            $method = 'get' . ucfirst($prop);

            if (method_exists($entity, $method)) {
                return self::stringifyEntityValue($entity->$method());
            }

            if (isset($entity->$prop)) {
                return self::stringifyEntityValue($entity->$prop);
            }
        }

        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        // Fallbacks
        if (method_exists($entity, 'getId')) {
            return '#' . self::stringifyEntityValue($entity->getId());
        }

        if (isset($entity->id)) {
            return '#' . self::stringifyEntityValue($entity->id);
        }

        return (new \ReflectionClass($entity))->getShortName();
    }

    private static function stringifyEntityValue(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
