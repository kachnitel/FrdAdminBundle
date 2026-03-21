<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\DataSourceContracts\ColumnGroup;
use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\DataSourceContracts\FilterMetadata;
use Kachnitel\DataSourceContracts\PaginatedResult;
use Kachnitel\DataSourceContracts\SearchAwareDataSourceInterface;

/**
 * Data source for Doctrine entities.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
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
        private readonly DoctrineFilterConverter $filterConverter,
        private readonly DoctrineItemValueResolver $itemValueResolver,
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

        $metadata      = $this->em->getClassMetadata($this->entityClass);
        $customColumns = $this->customColumnProvider->getCustomColumns($this->entityClass);
        $columnAttrs   = $this->columnAttributeProvider->getColumnAttributes($this->entityClass);

        $this->columnsCache = [];

        foreach ($this->getColumnNames($metadata, $customColumns) as $columnName) {
            if (isset($customColumns[$columnName])) {
                $this->columnsCache[$columnName] = $customColumns[$columnName];
                continue;
            }

            $this->columnsCache[$columnName] = ColumnMetadata::create(
                name: $columnName,
                type: $this->columnTypeMapper->getColumnType($metadata, $columnName),
                sortable: $metadata->hasField($columnName),
                group: $columnAttrs[$columnName]->group ?? null,
            );
        }

        return $this->columnsCache;
    }

    public function getColumnGroups(): array
    {
        return $this->columnGroupsCache ??= $this->columnAttributeProvider->build(
            $this->getColumns(),
            $this->columnAttributeProvider->getGroupAttributes($this->entityClass),
        );
    }

    public function getFilters(): array
    {
        if ($this->filtersCache !== null) {
            return $this->filtersCache;
        }

        $legacyFilters      = $this->filterMetadataProvider->getFilters($this->entityClass);
        $filterableColumns  = $this->adminAttribute->getFilterableColumns();
        $this->filtersCache = [];

        foreach ($legacyFilters as $name => $config) {
            if ($filterableColumns !== null && !in_array($name, $filterableColumns, true)) {
                continue;
            }

            $this->filtersCache[$name] = $this->filterConverter->convert($name, $config);
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
        $archiveDqlCondition       = $this->archiveDqlCondition;
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
            'batch_delete'      => $this->adminAttribute->isEnableBatchActions(),
            'column_visibility' => $this->adminAttribute->isEnableColumnVisibility(),
            default             => false,
        };
    }

    public function getIdField(): string
    {
        return $this->em->getClassMetadata($this->entityClass)->getSingleIdentifierFieldName();
    }

    public function getItemId(object $item): string|int
    {
        $metadata = $this->em->getClassMetadata($this->entityClass);
        $idField  = $metadata->getSingleIdentifierFieldName();

        return $metadata->getFieldValue($item, $idField);
    }

    public function getItemValue(object $item, string $field): mixed
    {
        $customColumns = $this->customColumnProvider->getCustomColumns($this->entityClass);
        if (isset($customColumns[$field])) {
            return null;
        }

        return $this->itemValueResolver->resolve(
            $item,
            $field,
            $this->em->getClassMetadata($this->entityClass),
        );
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
        $labels  = [];

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
            $parts            = explode('\\', $this->entityClass);
            $this->shortName  = end($parts);
        }

        return $this->shortName;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Determine the ordered list of column names to expose.
     *
     * Priority: explicit #[Admin(columns:)] → all Doctrine columns minus excludeColumns
     * → append any custom columns not already in the list.
     *
     * @param ClassMetadata<object>         $metadata
     * @param array<string, ColumnMetadata> $customColumns
     * @return array<string>
     */
    private function getColumnNames(ClassMetadata $metadata, array $customColumns): array
    {
        if ($this->adminAttribute->getColumns() !== null) {
            return $this->adminAttribute->getColumns();
        }

        $allDoctrineColumns = array_merge(
            $metadata->getFieldNames(),
            $metadata->getAssociationNames(),
        );

        if ($this->adminAttribute->getExcludeColumns() !== null) {
            $excludeColumns     = $this->adminAttribute->getExcludeColumns();
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
}
