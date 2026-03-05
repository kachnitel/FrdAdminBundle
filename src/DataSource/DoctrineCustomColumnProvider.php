<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Kachnitel\AdminBundle\Attribute\AdminCustomColumn;

/**
 * Reads #[AdminCustomColumn] attributes from an entity class and converts them
 * to ColumnMetadata objects ready for use in DoctrineDataSource::getColumns().
 *
 * Extracted to a dedicated service so it can be independently unit-tested and
 * potentially reused by other services in the future.
 */
class DoctrineCustomColumnProvider
{
    /**
     * Return ColumnMetadata for every #[AdminCustomColumn] attribute on the class,
     * keyed by column name.
     *
     * @param class-string $entityClass
     * @return array<string, ColumnMetadata>
     */
    public function getCustomColumns(string $entityClass): array
    {
        $reflection = new \ReflectionClass($entityClass);
        $attributes = $reflection->getAttributes(AdminCustomColumn::class);

        if ($attributes === []) {
            return [];
        }

        $columns = [];
        foreach ($attributes as $attribute) {
            /** @var AdminCustomColumn $customColumn */
            $customColumn = $attribute->newInstance();

            $columns[$customColumn->name] = new ColumnMetadata(
                name: $customColumn->name,
                label: $customColumn->label ?? $this->humanize($customColumn->name),
                type: 'custom',
                sortable: $customColumn->sortable,
                template: $customColumn->template,
            );
        }

        return $columns;
    }

    /**
     * Convert a camelCase property name to a human-readable label.
     * Mirrors ColumnMetadata::humanize() — kept private here to avoid
     * coupling to the readonly class internals.
     */
    private function humanize(string $text): string
    {
        return ucfirst(trim(strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $text))));
    }
}
