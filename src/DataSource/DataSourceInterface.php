<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Interface for data sources that can be displayed in the admin list view.
 *
 * This abstraction allows non-Doctrine data sources (e.g., audit logs, API data)
 * to be displayed and filtered using the same admin UI as Doctrine entities.
 */
interface DataSourceInterface
{
    /**
     * Get unique identifier for this data source.
     *
     * Used in URLs and route parameters.
     * Example: 'product', 'audit-App-Entity-User'
     */
    public function getIdentifier(): string;

    /**
     * Get human-readable label for this data source.
     *
     * Displayed in dashboard and navigation.
     */
    public function getLabel(): string;

    /**
     * Get icon identifier for this data source.
     *
     * Used in dashboard cards.
     */
    public function getIcon(): ?string;

    /**
     * Get column definitions for list display.
     *
     * @return array<string, ColumnMetadata> Map of column name => metadata
     */
    public function getColumns(): array;

    /**
     * Get filter definitions for this data source.
     *
     * @return array<string, FilterMetadata> Map of filter name => metadata
     */
    public function getFilters(): array;

    /**
     * Get default sort field.
     */
    public function getDefaultSortBy(): string;

    /**
     * Get default sort direction.
     */
    public function getDefaultSortDirection(): string;

    /**
     * Get default items per page.
     */
    public function getDefaultItemsPerPage(): int;

    /**
     * Query the data source with filters, sorting, and pagination.
     *
     * @param string $search Global search term
     * @param array<string, mixed> $filters Column-specific filter values
     * @param string $sortBy Column to sort by
     * @param string $sortDirection 'ASC' or 'DESC'
     * @param int $page Current page (1-indexed)
     * @param int $itemsPerPage Items per page
     */
    public function query(
        string $search,
        array $filters,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage
    ): PaginatedResult;

    /**
     * Find a single item by ID.
     *
     * @return object|null The item, or null if not found
     */
    public function find(string|int $id): ?object;

    /**
     * Check if this data source supports a specific action.
     *
     * @param string $action One of: 'index', 'show', 'new', 'edit', 'delete', 'batch_delete'
     */
    public function supportsAction(string $action): bool;

    /**
     * Get the ID field name for items.
     */
    public function getIdField(): string;

    /**
     * Get the value of the ID field for an item.
     */
    public function getItemId(object $item): string|int;

    /**
     * Get the value of a specific field for an item.
     */
    public function getItemValue(object $item, string $field): mixed;
}
