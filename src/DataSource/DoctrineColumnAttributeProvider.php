<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\AdminColumnGroup;
use Kachnitel\AdminBundle\Utils\Text;
use Kachnitel\DataSourceContracts\ColumnGroup;
use Kachnitel\DataSourceContracts\ColumnMetadata;

/**
 * Reads `#[AdminColumn]` and `#[AdminColumnGroup]` attributes from entity classes
 * and returns them in structured form for use by the data source layer.
 *
 * Also builds the ordered list of column slots (strings and ColumnGroup objects)
 * used by EntityList's header and body rendering.
 *
 * @see DoctrineCustomColumnProvider for the parallel service that reads #[AdminCustomColumn]
 */
class DoctrineColumnAttributeProvider
{
    /**
     * Return the `#[AdminColumn]` attribute for every property that declares one,
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

    /**
     * Return the `#[AdminColumnGroup]` attributes declared on the entity class,
     * keyed by group id. When the same group id is declared more than once the
     * last declaration wins.
     *
     * @param class-string $entityClass
     * @return array<string, AdminColumnGroup>
     */
    public function getGroupAttributes(string $entityClass): array
    {
        $reflection = new \ReflectionClass($entityClass);
        $attributes = $reflection->getAttributes(AdminColumnGroup::class);

        $result = [];
        foreach ($attributes as $attribute) {
            /** @var AdminColumnGroup $groupAttr */
            $groupAttr = $attribute->newInstance();
            $result[$groupAttr->id] = $groupAttr;
        }

        return $result;
    }

    /**
     * Build the ordered list of column slots for the entity list header and body.
     *
     * Each slot is either:
     * - a plain column name (string) — rendered as a single <th>/<td>
     * - a ColumnGroup value object  — rendered as a composite <th>/<td> that stacks sub-columns
     *
     * Group members appear at the position of their first member in the column order.
     * Non-contiguous members are merged into the group opened at the first-member position.
     *
     * @param array<string, ColumnMetadata>   $columns    Column map from DoctrineDataSource::getColumns()
     * @param array<string, AdminColumnGroup> $groupAttrs Group attribute map from getGroupAttributes()
     * @return list<string|ColumnGroup>
     */
    public function build(array $columns, array $groupAttrs): array
    {
        /** @var list<string|ColumnGroup> $slots */
        $slots = [];
        /** @var array<string, int> $groupSlotIndex Map of group ID => slot index */
        $groupSlotIndex = [];

        foreach ($columns as $name => $metadata) {
            if ($metadata->group === null) {
                $slots[] = $name;
                continue;
            }

            $groupId   = $metadata->group;
            $groupAttr = $groupAttrs[$groupId] ?? null;

            if (!isset($groupSlotIndex[$groupId])) {
                $groupSlotIndex[$groupId] = count($slots);
                $slots[] = new ColumnGroup(
                    id: $groupId,
                    label: Text::humanize($groupId),
                    columns: [$name => $metadata],
                    subLabels: $groupAttr?->subLabels ?? ColumnGroup::SUB_LABELS_SHOW, // @phpstan-ignore nullsafe.neverNull
                    header: $groupAttr?->header ?? ColumnGroup::HEADER_TEXT, // @phpstan-ignore nullsafe.neverNull
                );
            } else {
                $idx = $groupSlotIndex[$groupId];
                /** @var ColumnGroup $existing */
                $existing    = $slots[$idx];
                $slots[$idx] = new ColumnGroup(
                    id: $existing->id,
                    label: $existing->label,
                    columns: array_merge($existing->columns, [$name => $metadata]),
                    subLabels: $existing->subLabels,
                    header: $existing->header,
                );
            }
        }

        return $slots;
    }
}
