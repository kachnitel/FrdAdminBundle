# DataSource Abstraction Guide

Display data from any source (APIs, audit logs, external databases) in the admin interface.

## Table of Contents

- [Quick Start](#quick-start)
- [When to Use DataSources](#when-to-use-datasources)
- [Creating a Custom Data Source](#creating-a-custom-data-source)
- [Value Objects](#value-objects)
- [Using in Templates](#using-in-templates)
- [DataSource Providers](#datasource-providers)
- [API Reference](#api-reference)

## Quick Start

**Doctrine entities work automatically** - just add `#[Admin]`:

```php
#[Admin(label: 'Products')]
class Product { }  // Available as data source 'Product'
```

```twig
<twig:K:Admin:EntityList dataSourceId="Product" />
```

**For custom data sources**, implement `DataSourceInterface`:

```php
class ApiProductDataSource implements DataSourceInterface
{
    public function getIdentifier(): string { return 'api-products'; }
    public function getLabel(): string { return 'API Products'; }
    // ... other required methods
}
```

```twig
<twig:K:Admin:EntityList dataSourceId="api-products" />
```

Data sources are **auto-discovered** - just implement the interface and register as a service.

## When to Use DataSources

| Use Case | Example |
|----------|---------|
| External APIs | Display products from a third-party service |
| Audit logs | Show entity change history |
| Read-only views | Dashboard statistics, reports |
| Non-Doctrine data | Redis, MongoDB, file-based data |

## Creating a Custom Data Source

Implement `DataSourceInterface` with these key methods:

```php
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;

class MyDataSource implements DataSourceInterface
{
    // Required: unique identifier
    public function getIdentifier(): string { return 'my-data'; }

    // Required: display name
    public function getLabel(): string { return 'My Data'; }

    // Required: column definitions
    public function getColumns(): array
    {
        return [
            'id' => ColumnMetadata::create('id', 'ID', 'integer'),
            'name' => ColumnMetadata::create('name', 'Name', 'string'),
        ];
    }

    // Required: query data with pagination
    public function query(...): PaginatedResult
    {
        // Fetch your data here
        return new PaginatedResult($items, $total, $page, $itemsPerPage);
    }

    // Required: which actions are supported
    public function supportsAction(string $action): bool
    {
        return in_array($action, ['index', 'show'], true); // Read-only
    }

    // ... other required methods
}
```

<details>
<summary><strong>Full implementation example</strong></summary>

```php
<?php

declare(strict_types=1);

namespace App\DataSource;

use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;

class ExternalApiDataSource implements DataSourceInterface
{
    public function __construct(
        private ApiClientInterface $apiClient,
    ) {}

    public function getIdentifier(): string
    {
        return 'external-api';
    }

    public function getLabel(): string
    {
        return 'External API Data';
    }

    public function getIcon(): ?string
    {
        return 'cloud';
    }

    public function getColumns(): array
    {
        return [
            'id' => ColumnMetadata::create('id', 'ID', 'integer'),
            'name' => ColumnMetadata::create('name', 'Name', 'string'),
            'status' => ColumnMetadata::create('status', 'Status', 'string'),
            'createdAt' => ColumnMetadata::create('createdAt', 'Created', 'datetime'),
        ];
    }

    public function getFilters(): array
    {
        return [
            'name' => FilterMetadata::text('name', 'Name', 'Search by name...'),
            'status' => FilterMetadata::enum('status', ['active', 'inactive', 'pending']),
            'createdAt' => FilterMetadata::dateRange('createdAt', 'Created'),
        ];
    }

    public function getDefaultSortBy(): string
    {
        return 'createdAt';
    }

    public function getDefaultSortDirection(): string
    {
        return 'DESC';
    }

    public function getDefaultItemsPerPage(): int
    {
        return 20;
    }

    public function query(
        string $search,
        array $filters,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage
    ): PaginatedResult {
        // Call your external API
        $response = $this->apiClient->list([
            'search' => $search,
            'filters' => $filters,
            'sort' => $sortBy,
            'direction' => $sortDirection,
            'page' => $page,
            'limit' => $itemsPerPage,
        ]);

        return new PaginatedResult(
            items: $response->items,
            totalItems: $response->total,
            currentPage: $page,
            itemsPerPage: $itemsPerPage,
        );
    }

    public function find(string|int $id): ?object
    {
        return $this->apiClient->get($id);
    }

    public function supportsAction(string $action): bool
    {
        // Read-only data source
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
```

</details>

### Service Registration

Data sources are **auto-discovered** when implementing `DataSourceInterface`. The bundle uses Symfony's `#[AutowireIterator]` attribute (Symfony 6.4+) to automatically collect all implementations:

```yaml
# config/services.yaml
services:
    App\DataSource\:
        resource: '../src/DataSource'
```

That's all you need! Any class implementing `DataSourceInterface` is automatically registered with the `DataSourceRegistry`.

## DataSource Providers

For bundles providing multiple data sources, implement `DataSourceProviderInterface`:

<details>
<summary>Provider implementation example (click to expand)</summary>

```php
<?php

declare(strict_types=1);

namespace App\DataSource;

use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface;

class AuditDataSourceProvider implements DataSourceProviderInterface
{
    public function __construct(
        private EntityDiscoveryService $entityDiscovery,
        private AuditReader $auditReader,
    ) {}

    /**
     * @return iterable<DataSourceInterface>
     */
    public function getDataSources(): iterable
    {
        // Create a data source for each audited entity
        foreach ($this->entityDiscovery->getAdminEntities() as $entityClass) {
            if ($this->auditReader->isAudited($entityClass)) {
                yield new AuditLogDataSource(
                    $entityClass,
                    $this->auditReader
                );
            }
        }
    }
}
```

</details>

Providers are also auto-discovered via `#[AutowireIterator]` - no manual tagging needed. Just implement the interface and register your service.

## Value Objects

### ColumnMetadata

Describes how a column should be displayed:

```php
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;

// Full constructor
$column = new ColumnMetadata(
    name: 'createdAt',
    label: 'Created At',
    type: 'datetime',
    sortable: true,
    template: 'admin/columns/date.html.twig',
);

// Factory with defaults
$column = ColumnMetadata::create('name', 'Product Name', 'string');
$column = ColumnMetadata::create('price'); // Auto-generates label "Price"
```

**Supported types:** `string`, `integer`, `boolean`, `datetime`, `date`, `json`, `text`, `collection`

### FilterMetadata

Describes how a filter should be rendered:

```php
use Kachnitel\AdminBundle\DataSource\FilterMetadata;

// Text search filter
$filter = FilterMetadata::text('name', 'Name', 'Search...');

// Number filter
$filter = FilterMetadata::number('quantity', 'Quantity', '>=');

// Date filters
$filter = FilterMetadata::date('createdAt', 'Created After', '>=');
$filter = FilterMetadata::dateRange('updatedAt', 'Updated');

// Enum/select filter
$filter = FilterMetadata::enum('status', ['active', 'inactive', 'pending']);

// Enum from PHP enum class
$filter = FilterMetadata::enumClass('status', OrderStatus::class);

// Boolean filter
$filter = FilterMetadata::boolean('active', 'Active');
```

### PaginatedResult

Return type for `query()` method:

```php
use Kachnitel\AdminBundle\DataSource\PaginatedResult;

$result = new PaginatedResult(
    items: $entities,        // array of objects
    totalItems: 150,         // total count
    currentPage: 2,          // current page (1-indexed)
    itemsPerPage: 20,        // items per page
);

// Pagination helpers
$result->getTotalPages();    // 8
$result->hasNextPage();      // true
$result->hasPreviousPage();  // true
$result->getStartItem();     // 21
$result->getEndItem();       // 40

// Convert to PaginationInfo for templates
$paginationInfo = $result->toPaginationInfo();
```

## Using in Templates

```twig
{# Basic usage #}
<twig:K:Admin:EntityList dataSourceId="Product" />

{# With options #}
<twig:K:Admin:EntityList
    dataSourceId="api-products"
    :itemsPerPage="50"
    sortBy="createdAt"
    sortDirection="DESC"
/>
```

<details>
<summary><strong>Accessing DataSourceRegistry in PHP</strong></summary>

```php
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;

class MyController
{
    public function __construct(private DataSourceRegistry $registry) {}

    public function index(): Response
    {
        $dataSource = $this->registry->get('Product');

        if ($this->registry->has('api-products')) {
            // ...
        }

        foreach ($this->registry->all() as $id => $dataSource) {
            // ...
        }
    }
}
```

</details>

<details>
<summary><strong>Full example: Audit Log Data Source</strong></summary>

```php
<?php

declare(strict_types=1);

namespace App\DataSource;

use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;

class AuditLogDataSource implements DataSourceInterface
{
    public function __construct(
        private string $entityClass,
        private AuditReader $auditReader,
    ) {}

    public function getIdentifier(): string
    {
        // e.g., 'audit-App-Entity-User'
        return 'audit-' . str_replace('\\', '-', $this->entityClass);
    }

    public function getLabel(): string
    {
        $shortName = (new \ReflectionClass($this->entityClass))->getShortName();
        return $shortName . ' Audit Log';
    }

    public function getIcon(): ?string
    {
        return 'history';
    }

    public function getColumns(): array
    {
        return [
            'revision' => ColumnMetadata::create('revision', 'Revision', 'integer'),
            'action' => ColumnMetadata::create('action', 'Action', 'string'),
            'entityId' => ColumnMetadata::create('entityId', 'Entity ID', 'integer'),
            'username' => ColumnMetadata::create('username', 'User', 'string'),
            'timestamp' => ColumnMetadata::create('timestamp', 'Timestamp', 'datetime'),
            'changes' => new ColumnMetadata(
                name: 'changes',
                label: 'Changes',
                type: 'json',
                sortable: false,
            ),
        ];
    }

    public function getFilters(): array
    {
        return [
            'action' => FilterMetadata::enum('action', ['INSERT', 'UPDATE', 'DELETE'], 'Action'),
            'username' => FilterMetadata::text('username', 'User', 'Filter by user...'),
            'timestamp' => FilterMetadata::dateRange('timestamp', 'Time Period'),
        ];
    }

    public function getDefaultSortBy(): string
    {
        return 'timestamp';
    }

    public function getDefaultSortDirection(): string
    {
        return 'DESC';
    }

    public function getDefaultItemsPerPage(): int
    {
        return 50;
    }

    public function query(
        string $search,
        array $filters,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage
    ): PaginatedResult {
        $revisions = $this->auditReader->findRevisions(
            $this->entityClass,
            $filters,
            $search,
            $sortBy,
            $sortDirection,
            $page,
            $itemsPerPage
        );

        return new PaginatedResult(
            items: $revisions['items'],
            totalItems: $revisions['total'],
            currentPage: $page,
            itemsPerPage: $itemsPerPage,
        );
    }

    public function find(string|int $id): ?object
    {
        return $this->auditReader->findRevision($this->entityClass, $id);
    }

    public function supportsAction(string $action): bool
    {
        // Audit logs are read-only
        return in_array($action, ['index', 'show'], true);
    }

    public function getIdField(): string
    {
        return 'revision';
    }

    public function getItemId(object $item): string|int
    {
        return $item->revision;
    }

    public function getItemValue(object $item, string $field): mixed
    {
        return match ($field) {
            'revision' => $item->revision,
            'action' => $item->action,
            'entityId' => $item->entityId,
            'username' => $item->username,
            'timestamp' => $item->timestamp,
            'changes' => $item->changes,
            default => null,
        };
    }
}
```

### Using in Dashboard

```twig
{# templates/admin/dashboard.html.twig #}
<h2>Recent Audit Activity</h2>
<twig:K:Admin:EntityList
    dataSourceId="audit-App-Entity-User"
    :itemsPerPage="10"
/>
```

</details>

## API Reference

<details>
<summary>DataSourceInterface (click to expand)</summary>

```php
interface DataSourceInterface
{
    public function getIdentifier(): string;
    public function getLabel(): string;
    public function getIcon(): ?string;
    public function getColumns(): array;
    public function getFilters(): array;
    public function getDefaultSortBy(): string;
    public function getDefaultSortDirection(): string;
    public function getDefaultItemsPerPage(): int;
    public function query(
        string $search,
        array $filters,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage
    ): PaginatedResult;
    public function find(string|int $id): ?object;
    public function supportsAction(string $action): bool;
    public function getIdField(): string;
    public function getItemId(object $item): string|int;
    public function getItemValue(object $item, string $field): mixed;
}
```

</details>

<details>
<summary>DataSourceProviderInterface (click to expand)</summary>

```php
interface DataSourceProviderInterface
{
    /** @return iterable<DataSourceInterface> */
    public function getDataSources(): iterable;
}
```

</details>

<details>
<summary>ColumnMetadata (click to expand)</summary>

```php
readonly class ColumnMetadata
{
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'string',
        public bool $sortable = true,
        public ?string $template = null,
    );

    public static function create(
        string $name,
        ?string $label = null,
        string $type = 'string',
        bool $sortable = true,
        ?string $template = null,
    ): self;
}
```

</details>

<details>
<summary>FilterMetadata (click to expand)</summary>

```php
readonly class FilterMetadata
{
    public function __construct(
        public string $name,
        public string $type = 'text',
        public ?string $label = null,
        public ?string $placeholder = null,
        public string $operator = '=',
        public ?FilterEnumOptions $enumOptions = null,
        public ?array $searchFields = null,
        public int $priority = 999,
        public bool $enabled = true,
    );

    // Accessors for enum options (backward compatibility)
    public function getOptions(): ?array;
    public function getEnumClass(): ?string;
    public function getShowAllOption(): bool;

    // Factory methods (recommended)
    public static function text(string $name, ?string $label = null, ?string $placeholder = null, int $priority = 999): self;
    public static function number(string $name, ?string $label = null, string $operator = '=', int $priority = 999): self;
    public static function date(string $name, ?string $label = null, string $operator = '>=', int $priority = 999): self;
    public static function dateRange(string $name, ?string $label = null, int $priority = 999): self;
    public static function enum(string $name, array $options, ?string $label = null, bool $showAllOption = true, int $priority = 999): self;
    public static function enumClass(string $name, string $enumClass, ?string $label = null, bool $showAllOption = true, int $priority = 999): self;
    public static function boolean(string $name, ?string $label = null, bool $showAllOption = true, int $priority = 999): self;
}

readonly class FilterEnumOptions
{
    public function __construct(
        public ?array $values = null,
        public ?string $enumClass = null,
        public bool $showAllOption = true,
    );

    public static function fromValues(array $values, bool $showAllOption = true): self;
    public static function fromEnumClass(string $enumClass, bool $showAllOption = true): self;
}
```

</details>

<details>
<summary>PaginatedResult (click to expand)</summary>

```php
readonly class PaginatedResult
{
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $currentPage,
        public int $itemsPerPage,
    );

    public function getTotalPages(): int;
    public function hasNextPage(): bool;
    public function hasPreviousPage(): bool;
    public function getStartItem(): int;
    public function getEndItem(): int;
    public function toPaginationInfo(): PaginationInfo;
}
```

</details>

## Need Help?

- See [CONFIGURATION.md](CONFIGURATION.md) for entity configuration with `#[Admin]` attribute
- See [FILTERS.md](FILTERS.md) for filter customization
- Check `src/DataSource/` for implementation examples
