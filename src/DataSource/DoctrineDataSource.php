<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Utils\Text;
use Kachnitel\DataSourceContracts\ColumnGroup;
use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\DataSourceContracts\FilterEnumOptions;
use Kachnitel\DataSourceContracts\FilterMetadata;
use Kachnitel\DataSourceContracts\PaginatedResult;
use Kachnitel\DataSourceContracts\SearchAwareDataSourceInterface;

/**
 * Data source for Doctrine entities.
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
     * Optional pre-built DQL WHERE fragment injected by EntityList when archive
     * filtering is active (e.g. 'e.deletedAt IS NULL' or 'e.archived = false').
     *
     * Set via setArchiveDqlCondition() before calling query().
     * Cleared automatically after each query() call so stale conditions
     * do not leak into subsequent renders.
     */
    private ?string $archiveDqlCondition = null;

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

    // ── Archive condition ──────────────────────────────────────────────────────

    /**
     * Set a pre-built DQL WHERE fragment for archive filtering.
     *
     * Called by EntityList when it determines the archive condition from
     * ArchiveService.  The condition is applied as an additional andWhere()
     * in the next query() call.
     *
     * Only DoctrineDataSource supports this; custom DataSources are unaffected.
     */
    public function setArchiveDqlCondition(?string $condition): void
    {
        $this->archiveDqlCondition = $condition;
    }

    // ── DataSourceInterface ────────────────────────────────────────────────────

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

        $columnNames = $this->getColumnNames($metadata, $customColumns);

        foreach ($columnNames as $columnName) {
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
        /** @var array<string, int> $groupSlotIndex */
        $groupSlotIndex = [];

        foreach ($columns as $name => $metadata) {
            if ($metadata->group !== null) {
                $groupId = $metadata->group;
                $groupAttr = $groupAttrs[$groupId] ?? null;

                if (!isset($groupSlotIndex[$groupId])) {
                    $groupSlotIndex[$groupId] = count($slots);
                    $slots[] = new ColumnGroup(
                        id: $groupId,
                        label: Text::humanize($groupId),
                        columns: [$name => $metadata],
                        subLabels: $groupAttr->subLabels ?? ColumnGroup::SUB_LABELS_SHOW,
                        header: $groupAttr->header ?? ColumnGroup::HEADER_TEXT,
                    );
                } else {
                    /** @var ColumnGroup $existingGroup */
                    $existingGroup = $slots[$groupSlotIndex[$groupId]];
                    $slots[$groupSlotIndex[$groupId]] = new ColumnGroup(
                        id: $existingGroup->id,
                        label: $existingGroup->label,
                        columns: array_merge($existingGroup->columns, [$name => $metadata]),
                        subLabels: $existingGroup->subLabels,
                        header: $existingGroup->header,
                    );
                }
            } else {
                $slots[] = $name;
            }
        }

        $this->columnGroupsCache = $slots;

        return $this->columnGroupsCache;
    }

    public function getFilters(): array
    {
        if ($this->filtersCache !== null) {
            return $this->filtersCache;
        }

        $legacyFilters = $this->filterMetadataProvider->getFilters($this->entityClass);
        $this->filtersCache = [];

        $filterableColumns = $this->adminAttribute->getFilterableColumns();

        foreach ($legacyFilters as $name => $config) {
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
        // Consume and clear the archive condition so it doesn't leak into
        // subsequent renders if the DataSource instance is reused.
        $archiveDqlCondition = $this->archiveDqlCondition;
        $this->archiveDqlCondition = null;

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
            archiveDqlCondition: $archiveDqlCondition,
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
        $customColumns = $this->customColumnProvider->getCustomColumns($this->entityClass);
        if (isset($customColumns[$field])) {
            return null;
        }

        $metadata = $this->em->getClassMetadata($this->entityClass);

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

    /**
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

    /**
     * @return class-string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getAdminAttribute(): Admin
    {
        return $this->adminAttribute;
    }

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
     * @param ClassMetadata<object>         $metadata
     * @param array<string, ColumnMetadata> $customColumns
     * @return array<string>
     */
    private function getColumnNames(ClassMetadata $metadata, array $customColumns): array
    {
        if ($this->adminAttribute->getColumns() !== null) {
            return $this->adminAttribute->getColumns();
        }

        $allDoctrineColumns = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

        if ($this->adminAttribute->getExcludeColumns() !== null) {
            $excludeColumns = $this->adminAttribute->getExcludeColumns();
            $allDoctrineColumns = array_values(
                array_filter($allDoctrineColumns, fn ($col) => !in_array($col, $excludeColumns))
            );
        }

        $customNames = array_keys($customColumns);
        $extraCustom = array_values(
            array_filter($customNames, fn ($name) => !in_array($name, $allDoctrineColumns))
        );

        return array_merge($allDoctrineColumns, $extraCustom);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function isColumnSortable(ClassMetadata $metadata, string $column): bool
    {
        return $metadata->hasField($column);
    }

    /**
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
}
