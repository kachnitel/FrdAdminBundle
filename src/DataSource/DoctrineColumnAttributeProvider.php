<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Kachnitel\AdminBundle\Attribute\AdminColumn;

/**
 * Reads #[AdminColumn] attributes from entity properties and returns them
 * keyed by property name.
 *
 * Extracted to a dedicated service so it can be independently unit-tested and
 * injected wherever per-property AdminColumn metadata is needed, without
 * coupling callers to raw reflection APIs.
 *
 * @see DoctrineCustomColumnProvider for the parallel service that reads #[AdminCustomColumn]
 */
class DoctrineColumnAttributeProvider
{
    /**
     * Return the #[AdminColumn] attribute for every property that declares one,
     * keyed by property name. Properties without the attribute are omitted.
     *
     * Scans all properties declared by the class and its parents.
     *
     * @param class-string $entityClass
     * @return array<string, AdminColumn>
     */
    public function getColumnAttributes(string $entityClass): array
    {
        $reflection = new \ReflectionClass($entityClass);
        $result = [];

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(AdminColumn::class);

            if ($attributes === []) {
                continue;
            }

            /** @var AdminColumn $attr */
            $attr = $attributes[0]->newInstance();
            $result[$property->getName()] = $attr;
        }

        return $result;
    }
}
