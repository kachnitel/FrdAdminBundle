<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;

/**
 * Data source for Doctrine entities.
 *
 * Wraps a Doctrine entity class and provides data access through the DataSourceInterface.
 * Uses the existing EntityListQueryService and FilterMetadataProvider for querying and filtering.
 *
 * Custom (virtual) columns can be declared via #[AdminCustomColumn] on the entity class.
 * These columns are appended after Doctrine-backed columns when no explicit `columns:`
 * list is set, or they are placed wherever the developer puts their name in `columns:`.
 *
 * Composite columns can be declared via #[AdminColumn(group: 'identifier')] on entity properties.
 * Properties sharing the same group identifier are rendered in a single stacked table cell.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DoctrineDataSource implements DataSourceInterface, SearchAwareDataSourceInterface
{
    private ?string $shortName = null;

    /** @var array<string, ColumnMetadata>|null */
    private ?array $columnsCache = null;

    /** @var array<string, FilterMetadata>|null */
    private ?array $filtersCache = null;

    /** @var list<string|ColumnGroup>|null */
    private ?array $columnGroupsCache = null;

    /**
     * @param class-string $entityClass Fully-qualified entity class name
     */
    public function __construct(
        private readonly string $entityClass,
        private readonly Admin $adminAttribute,
        private readonly EntityManagerInterface $em,
        private readonly EntityListQueryService $queryService,
        private readonly FilterMetadataProvider $filterMetadataProvider,
        private readonly DoctrineCustomColumnProvider $customColumnProvider,
        private readonly DoctrineColumnAttributeProvider $columnAttributeProvider,
        private readonly DoctrineColumnTypeMapper $columnTypeMapper,
    ) {}

    public function getIdentifier(): string
    {
        return $this->getShortName();
    }

    public function getLabel(): string
    {
        return $this->adminAttribute->getLabel()
            ?? $this->getShortName();
    }

    public function getIcon(): ?string
    {
        return $this->adminAttribute->getIcon();
    }

    public function getColumns(): array
    {
        if ($this->columnsCache !== null) {
            return $this->columnsCache;
        }

        $metadata = $this->em->getClassMetadata($this->entityClass);
        $customColumns = $this->customColumnProvider->getCustomColumns($this->entityClass);
        $columnAttrs = $this->columnAttributeProvider->getColumnAttributes($this->entityClass);

        $this->columnsCache = [];

        // Get the ordered list of column names to include
        $columnNames = $this->getColumnNames($metadata, $customColumns);

        foreach ($columnNames as $columnName) {
            // Custom columns take priority — they carry their own ColumnMetadata
            if (isset($customColumns[$columnName])) {
                $this->columnsCache[$columnName] = $customColumns[$columnName];
                continue;
            }

            $type = $this->columnTypeMapper->getColumnType($metadata, $columnName);
            $sortable = $this->isColumnSortable($metadata, $columnName);
            $group = isset($columnAttrs[$columnName]) ? $columnAttrs[$columnName]->group : null;

            $this->columnsCache[$columnName] = ColumnMetadata::create(
                name: $columnName,
                type: $type,
                sortable: $sortable,
                group: $group,
            );
        }

        return $this->columnsCache;
    }

    public function getColumnGroups(): array
    {
        if ($this->columnGroupsCache !== null) {
            return $this->columnGroupsCache;
        }

        $columns = $this->getColumns();
        $groupAttrs = $this->columnAttributeProvider->getGroupAttributes($this->entityClass);

        /** @var list<string|ColumnGroup> $slots */
        $slots = [];
        /** @var array<string, int> $groupSlotIndex group id => index in $slots */
        $groupSlotIndex = [];

        foreach ($columns as $name => $metadata) {
            if ($metadata->group !== null) {
                $groupId = $metadata->group;
                $groupAttr = $groupAttrs[$groupId] ?? null;

                if (!isset($groupSlotIndex[$groupId])) {
                    // First member of this group — create a new ColumnGroup slot
                    $groupSlotIndex[$groupId] = count($slots);
                    $slots[] = new ColumnGroup(
                        id: $groupId,
                        label: $this->humanize($groupId),
                        columns: [$name => $metadata],
                        subLabels: $groupAttr->subLabels ?? ColumnGroup::SUB_LABELS_SHOW,
                        header: $groupAttr->header ?? ColumnGroup::HEADER_TEXT,
                    );
                } else {
                    // Subsequent member — append to the existing slot
                    $idx = $groupSlotIndex[$groupId];
                    /** @var ColumnGroup $existing */
                    $existing = $slots[$idx];
                    $slots[$idx] = new ColumnGroup(
                        id: $existing->id,
                        label: $existing->label,
                        columns: array_merge($existing->columns, [$name => $metadata]),
                        subLabels: $existing->subLabels,
                        header: $existing->header,
                    );
                }
            } else {
                $slots[] = $name;
            }
        }

        $this->columnGroupsCache = array_values($slots);

        return $this->columnGroupsCache;
    }

    /**
     * Returns human-readable labels for the entity fields that are included
     * in global search (all string/text Doctrine fields that are also visible
     * as columns in this data source).
     *
     * Fields that exist at DB level but are not part of the configured column
     * list are excluded — showing them in the tooltip would be confusing because
     * users cannot see those values in the list.
     *
     * @return array<string>
     */
    public function getGlobalSearchColumnLabels(): array
    {
        $searchableFields = $this->queryService->getSearchableFieldNames($this->entityClass);

        if ($searchableFields === []) {
            return [];
        }

        $columns = $this->getColumns();
        $labels = [];

        foreach ($searchableFields as $field) {
            if (isset($columns[$field])) {
                $labels[] = $columns[$field]->label;
            }
        }

        return $labels;
    }

    public function getFilters(): array
    {
        if ($this->filtersCache !== null) {
            return $this->filtersCache;
        }

        // Use existing FilterMetadataProvider for compatibility
        $legacyFilters = $this->filterMetadataProvider->getFilters($this->entityClass);
        $this->filtersCache = [];

        // If filterableColumns is set, only include those filters
        $filterableColumns = $this->adminAttribute->getFilterableColumns();

        foreach ($legacyFilters as $name => $config) {
            // Skip filters not in filterableColumns whitelist (when configured)
            if ($filterableColumns !== null && !in_array($name, $filterableColumns, true)) {
                continue;
            }

            $this->filtersCache[$name] = $this->buildFilter($name, $config);
        }

        return $this->filtersCache;
    }

    public function getDefaultSortBy(): string
    {
        return $this->adminAttribute->getSortBy() ?? 'id';
    }

    public function getDefaultSortDirection(): string
    {
        return $this->adminAttribute->getSortDirection() ?? 'DESC';
    }

    public function getDefaultItemsPerPage(): int
    {
        return $this->adminAttribute->getItemsPerPage() ?? 20;
    }

    public function query(
        string $search,
        array $filters,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage,
    ): PaginatedResult {
        // Convert FilterMetadata array to legacy format for EntityListQueryService
        $filterMetadata = [];
        foreach ($this->getFilters() as $name => $filter) {
            $filterMetadata[$name] = $filter->toArray();
        }

        $result = $this->queryService->getEntities(
            entityClass: $this->entityClass,
            repositoryMethod: null,
            search: $search,
            columnFilters: $filters,
            filterMetadata: $filterMetadata,
            sortBy: $sortBy,
            sortDirection: $sortDirection,
            page: $page,
            itemsPerPage: $itemsPerPage,
        );

        return PaginatedResult::fromQueryResult($result, $itemsPerPage);
    }

    public function find(string|int $id): ?object
    {
        return $this->em->getRepository($this->entityClass)->find($id);
    }

    public function supportsAction(string $action): bool
    {
        return match ($action) {
            'index', 'show', 'new', 'edit', 'delete' => true,
            'batch_delete'        => $this->adminAttribute->isEnableBatchActions(),
            'column_visibility'   => $this->adminAttribute->isEnableColumnVisibility(),
            default               => false,
        };
    }

    public function getIdField(): string
    {
        $metadata = $this->em->getClassMetadata($this->entityClass);

        return $metadata->getSingleIdentifierFieldName();
    }

    public function getItemId(object $item): string|int
    {
        $metadata = $this->em->getClassMetadata($this->entityClass);
        $idField = $metadata->getSingleIdentifierFieldName();

        return $metadata->getFieldValue($item, $idField);
    }

    public function getItemValue(object $item, string $field): mixed
    {
        // Custom columns have no Doctrine field — templates read `entity` directly
        $customColumns = $this->customColumnProvider->getCustomColumns($this->entityClass);
        if (isset($customColumns[$field])) {
            return null;
        }

        $metadata = $this->em->getClassMetadata($this->entityClass);

        // For regular fields, use field value
        if ($metadata->hasField($field)) {
            return $metadata->getFieldValue($item, $field);
        }

        // For associations, get the related entity
        if ($metadata->hasAssociation($field)) {
            return $metadata->getFieldValue($item, $field);
        }

        // Fallback to getter method
        $getter = 'get' . ucfirst($field);
        if (method_exists($item, $getter)) {
            return $item->$getter();
        }

        // Try 'is' prefix for booleans
        $isGetter = 'is' . ucfirst($field);
        if (method_exists($item, $isGetter)) {
            return $item->$isGetter();
        }

        return null;
    }

    /**
     * Get the entity class.
     *
     * @return class-string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * Get the Admin attribute.
     */
    public function getAdminAttribute(): Admin
    {
        return $this->adminAttribute;
    }

    /**
     * Get the short class name.
     */
    public function getShortName(): string
    {
        if ($this->shortName === null) {
            $parts = explode('\\', $this->entityClass);
            $this->shortName = end($parts);
        }

        return $this->shortName;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Get column names in display order.
     *
     * Rules:
     *  1. If Admin::columns is set explicitly, use exactly that list (which may include
     *     custom column names at any position).
     *  2. Otherwise, use all auto-detected Doctrine columns, then append custom columns
     *     that are not already in the list.
     *
     * @param ClassMetadata<object>         $metadata
     * @param array<string, ColumnMetadata> $customColumns
     * @return array<string>
     */
    private function getColumnNames(ClassMetadata $metadata, array $customColumns): array
    {
        // Explicit list: developer controls order entirely
        if ($this->adminAttribute->getColumns() !== null) {
            return $this->adminAttribute->getColumns();
        }

        // Auto-detect Doctrine columns
        $allDoctrineColumns = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

        // Apply excludeColumns filter
        if ($this->adminAttribute->getExcludeColumns() !== null) {
            $excludeColumns = $this->adminAttribute->getExcludeColumns();
            $allDoctrineColumns = array_values(
                array_filter($allDoctrineColumns, fn ($col) => !in_array($col, $excludeColumns))
            );
        }

        // Append custom columns that are not already listed
        $customNames = array_keys($customColumns);
        $extraCustom = array_values(
            array_filter($customNames, fn ($name) => !in_array($name, $allDoctrineColumns))
        );

        return array_merge($allDoctrineColumns, $extraCustom);
    }

    /**
     * Check if a column is sortable.
     *
     * Only regular Doctrine fields support direct ORDER BY in DQL.
     * Associations — whether single-valued (ManyToOne/OneToOne) or collection-valued
     * (OneToMany/ManyToMany) — cannot be sorted without an explicit JOIN, which this
     * data source does not perform. Custom columns have no backing Doctrine field at all.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function isColumnSortable(ClassMetadata $metadata, string $column): bool
    {
        return $metadata->hasField($column);
    }

    /**
     * Build a FilterMetadata from a legacy config array entry.
     *
     * Extracted to reduce cyclomatic complexity of getFilters().
     *
     * @param array<string, mixed> $config
     */
    private function buildFilter(string $name, array $config): FilterMetadata
    {
        $enumOptions = $this->buildEnumOptions($config);

        return new FilterMetadata(
            name: $name,
            type: $config['type'] ?? 'text',
            label: $config['label'] ?? null,
            placeholder: $config['placeholder'] ?? null,
            operator: $config['operator'] ?? '=',
            enumOptions: $enumOptions,
            searchFields: $config['searchFields'] ?? null,
            priority: $config['priority'] ?? 999,
            enabled: $config['enabled'] ?? true,
            excludeFromGlobalSearch: $config['excludeFromGlobalSearch'] ?? false,
            targetClass: $config['targetClass'] ?? null,
        );
    }

    /**
     * Build FilterEnumOptions from a legacy config array, or return null when no enum keys present.
     *
     * @param array<string, mixed> $config
     */
    private function buildEnumOptions(array $config): ?FilterEnumOptions
    {
        $keysToCheck = ['options', 'enumClass', 'showAllOption', 'multiple'];

        if (!array_any($keysToCheck, fn ($key) => isset($config[$key]))) {
            return null;
        }

        return new FilterEnumOptions(
            values: $config['options'] ?? null,
            enumClass: $config['enumClass'] ?? null,
            showAllOption: $config['showAllOption'] ?? true,
            multiple: $config['multiple'] ?? false,
        );
    }

    /**
     * Humanise a snake_case or camelCase identifier into a readable label.
     */
    private function humanize(string $text): string
    {
        // Handle snake_case: replace underscores with spaces
        $text = str_replace('_', ' ', $text);

        return ucfirst(trim(strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $text))));
    }
}
