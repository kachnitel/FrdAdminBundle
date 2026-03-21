<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Resolves a property value from a Doctrine entity using a multi-path fallback strategy:
 *
 *   1. Doctrine field        — ClassMetadata::getFieldValue()
 *   2. Doctrine association  — ClassMetadata::getFieldValue()
 *   3. getX() getter method  — conventional PHP getter
 *   4. isX() getter method   — boolean getter convention
 *   5. null                  — no resolution path found
 *
 * The caller is responsible for the custom-column null-short-circuit
 * (custom columns have no backing Doctrine field; their value is always null
 * and rendered entirely by a Twig template).
 *
 * Extracted from DoctrineDataSource to isolate the value-resolution concern.
 */
class DoctrineItemValueResolver
{
    /**
     * Resolve a field value from an entity instance.
     *
     * @param ClassMetadata<object> $metadata
     */
    public function resolve(object $item, string $field, ClassMetadata $metadata): mixed
    {
        if ($metadata->hasField($field)) {
            return $metadata->getFieldValue($item, $field);
        }

        if ($metadata->hasAssociation($field)) {
            return $metadata->getFieldValue($item, $field);
        }

        $getter = 'get' . ucfirst($field);
        if (method_exists($item, $getter)) {
            return $item->$getter();
        }

        $isGetter = 'is' . ucfirst($field);
        if (method_exists($item, $isGetter)) {
            return $item->$isGetter();
        }

        return null;
    }
}
