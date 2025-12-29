<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;

/**
 * Test data source with custom column templates.
 *
 * Used to test the column-level template override feature in EntityList.
 */
class CustomTemplateDataSource implements DataSourceInterface
{
    /** @var list<object> */
    private array $items = [];

    public function getIdentifier(): string
    {
        return 'custom-template-test';
    }

    public function getLabel(): string
    {
        return 'Custom Template Test';
    }

    public function getIcon(): ?string
    {
        return 'code';
    }

    public function getColumns(): array
    {
        return [
            'id' => ColumnMetadata::create('id', 'ID', 'integer'),
            'name' => ColumnMetadata::create('name', 'Name', 'string'),
            // This column uses a custom template
            'changes' => new ColumnMetadata(
                name: 'changes',
                label: 'Changes',
                type: 'json',
                sortable: false,
                template: 'test/column_changes.html.twig',
            ),
            // Another custom template column
            'status' => new ColumnMetadata(
                name: 'status',
                label: 'Status',
                type: 'string',
                sortable: true,
                template: 'test/column_status.html.twig',
            ),
        ];
    }

    public function getFilters(): array
    {
        return [
            'name' => FilterMetadata::text('name', 'Name', 'Search by name...'),
        ];
    }

    public function getDefaultSortBy(): string
    {
        return 'id';
    }

    public function getDefaultSortDirection(): string
    {
        return 'DESC';
    }

    public function getDefaultItemsPerPage(): int
    {
        return 20;
    }

    /**
     * Set test items for the data source.
     *
     * @param list<object> $items
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function query(
        string $search,
        array $filters,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage
    ): PaginatedResult {
        $items = $this->items;

        // Apply search filter
        if ($search !== '') {
            $items = array_filter($items, fn (object $item) => str_contains(
                strtolower((string) ($item->name ?? '')),
                strtolower($search)
            ));
        }

        // Apply name filter
        if (!empty($filters['name'])) {
            $items = array_filter($items, fn (object $item) => str_contains(
                strtolower((string) ($item->name ?? '')),
                strtolower((string) $filters['name'])
            ));
        }

        $items = array_values($items);
        $total = count($items);

        // Apply sorting
        usort($items, function (object $a, object $b) use ($sortBy, $sortDirection) {
            $aVal = $a->{$sortBy} ?? null;
            $bVal = $b->{$sortBy} ?? null;
            $cmp = $aVal <=> $bVal;
            return $sortDirection === 'DESC' ? -$cmp : $cmp;
        });

        // Apply pagination
        $offset = ($page - 1) * $itemsPerPage;
        $items = array_slice($items, $offset, $itemsPerPage);

        return new PaginatedResult(
            items: $items,
            totalItems: $total,
            currentPage: $page,
            itemsPerPage: $itemsPerPage,
        );
    }

    public function find(string|int $id): ?object
    {
        foreach ($this->items as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }
        return null;
    }

    public function supportsAction(string $action): bool
    {
        return in_array($action, ['index', 'show'], true);
    }

    public function getIdField(): string
    {
        return 'id';
    }

    public function getItemId(object $item): string|int
    {
        return $item->id;
    }

    public function getItemValue(object $item, string $field): mixed
    {
        return $item->{$field} ?? null;
    }
}
