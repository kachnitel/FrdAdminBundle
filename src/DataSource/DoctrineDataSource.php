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
 */
class DoctrineDataSource implements DataSourceInterface
{
    private ?string $shortName = null;

    /** @var array<string, ColumnMetadata>|null */
    private ?array $columnsCache = null;

    /** @var array<string, FilterMetadata>|null */
    private ?array $filtersCache = null;

    /**
     * @param class-string $entityClass Fully-qualified entity class name
     */
    public function __construct(
        private readonly string $entityClass,
        private readonly Admin $adminAttribute,
        private readonly EntityManagerInterface $em,
        private readonly EntityListQueryService $queryService,
        private readonly FilterMetadataProvider $filterMetadataProvider,
    ) {}

    public function getIdentifier(): string
    {
        return $this->getShortName();
    }

    public function getLabel(): string
    {
        return $this->adminAttribute->getLabel() ?? $this->getShortName();
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
        $this->columnsCache = [];

        // Get column names based on Admin attribute configuration
        $columnNames = $this->getColumnNames($metadata);

        foreach ($columnNames as $columnName) {
            $type = $this->getColumnType($metadata, $columnName);
            $sortable = $this->isColumnSortable($metadata, $columnName);

            $this->columnsCache[$columnName] = ColumnMetadata::create(
                name: $columnName,
                type: $type,
                sortable: $sortable,
            );
        }

        return $this->columnsCache;
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

            $enumOptions = null;
            $keysToCheck = [
                'options',
                'enumClass',
                'showAllOption',
                'multiple'
            ];

            if (array_any($keysToCheck, fn($key) => isset($config[$key]))) {
                $enumOptions = new FilterEnumOptions(
                    values: $config['options'] ?? null,
                    enumClass: $config['enumClass'] ?? null,
                    showAllOption: $config['showAllOption'] ?? true,
                    multiple: $config['multiple'] ?? false,
                );
            }

            $this->filtersCache[$name] = new FilterMetadata(
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
        int $itemsPerPage
    ): PaginatedResult {
        // Convert FilterMetadata array to legacy format for EntityListQueryService
        $filterMetadata = [];
        foreach ($this->getFilters() as $name => $filter) {
            $filterMetadata[$name] = $filter->toArray();
        }

        $result = $this->queryService->getEntities(
            entityClass: $this->entityClass,
            repositoryMethod: null, // Repository method can be set via component prop
            search: $search,
            columnFilters: $filters,
            filterMetadata: $filterMetadata,
            sortBy: $sortBy,
            sortDirection: $sortDirection,
            page: $page,
            itemsPerPage: $itemsPerPage
        );

        return PaginatedResult::fromQueryResult($result, $itemsPerPage);
    }

    public function find(string|int $id): ?object
    {
        return $this->em->getRepository($this->entityClass)->find($id);
    }

    public function supportsAction(string $action): bool
    {
        // The actual permission check happens in the controller/voter
        // This method indicates whether the action is structurally supported
        return match ($action) {
            'index', 'show', 'new', 'edit', 'delete' => true,
            'batch_delete' => $this->adminAttribute->isEnableBatchActions(),
            'column_visibility' => $this->adminAttribute->isEnableColumnVisibility(),
            default => false,
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

    /**
     * Get column names based on Admin attribute configuration.
     *
     * @param ClassMetadata<object> $metadata
     * @return array<string>
     */
    private function getColumnNames(ClassMetadata $metadata): array
    {
        // If columns are explicitly configured, use only those
        if ($this->adminAttribute->getColumns() !== null) {
            return $this->adminAttribute->getColumns();
        }

        // Get all available columns
        $allColumns = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

        // If excludeColumns is configured, filter them out
        if ($this->adminAttribute->getExcludeColumns() !== null) {
            $excludeColumns = $this->adminAttribute->getExcludeColumns();
            return array_values(array_filter($allColumns, fn($col) => !in_array($col, $excludeColumns)));
        }

        return $allColumns;
    }

    /**
     * Get column type for rendering.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function getColumnType(ClassMetadata $metadata, string $column): string
    {
        if ($metadata->hasField($column)) {
            $type = $metadata->getTypeOfField($column);

            return match ($type) {
                'integer', 'smallint', 'bigint' => 'integer',
                'decimal', 'float' => 'decimal',
                'boolean' => 'boolean',
                'date', 'date_immutable' => 'date',
                'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable' => 'datetime',
                'time', 'time_immutable' => 'time',
                'text' => 'text',
                'json', 'json_array' => 'json',
                'array', 'simple_array' => 'array',
                default => 'string',
            };
        }

        if ($metadata->hasAssociation($column)) {
            return $metadata->isCollectionValuedAssociation($column) ? 'collection' : 'relation';
        }

        return 'string';
    }

    /**
     * Check if a column is sortable.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function isColumnSortable(ClassMetadata $metadata, string $column): bool
    {
        // Regular fields are sortable
        if ($metadata->hasField($column)) {
            return true;
        }

        // Collection associations are not sortable
        if ($metadata->hasAssociation($column) && $metadata->isCollectionValuedAssociation($column)) {
            return false;
        }

        // Single associations can be sortable (sorts by related entity's ID)
        return $metadata->hasAssociation($column);
    }
}
