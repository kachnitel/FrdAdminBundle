# DataSource Abstraction Guide

Display data from any source (APIs, audit logs, external databases) in the admin interface.

## Table of Contents

- [Quick Start](#quick-start)
- [When to Use DataSources](#when-to-use-datasources)
- [Creating a Custom Data Source](#creating-a-custom-data-source)
- [Value Objects](#value-objects)
- [Using in Templates](#using-in-templates)
- [DataSource Providers](#datasource-providers)
- [Debugging](#debugging)
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
use Kachnitel\DataSourceContracts\DataSourceInterface;

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

Data sources are **auto-discovered** — just implement the interface and register as a service.

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
use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\DataSourceContracts\FlatColumnGroupsTrait;
use Kachnitel\DataSourceContracts\PaginatedResult;

class MyDataSource implements DataSourceInterface
{
    use FlatColumnGroupsTrait;

    // Required: unique identifier
    public function getIdentifier(): string { return 'my-data'; }

    // Required: display name
    public function getLabel(): string { return 'My Data'; }

    // Required: column definitions
    public function getColumns(): array
    {
        return [
            'id'   => ColumnMetadata::create('id', 'ID', 'integer'),
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

use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\DataSourceContracts\FilterMetadata;
use Kachnitel\DataSourceContracts\FlatColumnGroupsTrait;
use Kachnitel\DataSourceContracts\PaginatedResult;

class ExternalApiDataSource implements DataSourceInterface
{
    use FlatColumnGroupsTrait;

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
            'id'        => ColumnMetadata::create('id', 'ID', 'integer'),
            'name'      => ColumnMetadata::create('name', 'Name', 'string'),
            'status'    => ColumnMetadata::create('status', 'Status', 'string'),
            'createdAt' => ColumnMetadata::create('createdAt', 'Created', 'datetime'),
        ];
    }

    public function getFilters(): array
    {
        return [
            'name'      => FilterMetadata::text('name', 'Name', 'Search by name...'),
            'status'    => FilterMetadata::enum('status', ['active', 'inactive', 'pending']),
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
        $response = $this->apiClient->list([
            'search'    => $search,
            'filters'   => $filters,
            'sort'      => $sortBy,
            'direction' => $sortDirection,
            'page'      => $page,
            'limit'     => $itemsPerPage,
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

use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\DataSourceContracts\DataSourceProviderInterface;

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

Providers are also auto-discovered via `#[AutowireIterator]` — no manual tagging needed.

## Value Objects

### ColumnMetadata

Describes how a column should be displayed:

```php
use Kachnitel\DataSourceContracts\ColumnMetadata;

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
use Kachnitel\DataSourceContracts\FilterMetadata;

// Text search filter
$filter = FilterMetadata::text('name', 'Name', 'Search...');

// Number filter
$filter = FilterMetadata::number('quantity', 'Quantity', '>=');

// Date filters
$filter = FilterMetadata::date('createdAt', 'Created After', '>=');
$filter = FilterMetadata::dateRange('updatedAt', 'Updated');

// Enum/select filter
$filter = FilterMetadata::enum('status', ['active', 'inactive', 'pending']);

// Enum from PHP backed enum class
$filter = FilterMetadata::enumClass('status', OrderStatus::class);

// Boolean filter
$filter = FilterMetadata::boolean('active', 'Active');

// Collection filter (ManyToMany / OneToMany)
$filter = FilterMetadata::collection('tags', searchFields: ['name', 'display']);
```

## DataSource Providers

### Global Search Awareness

Implement `SearchAwareDataSourceInterface` to advertise which columns are included
in the global search, displayed as a tooltip next to the search input:

```php
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\DataSourceContracts\SearchAwareDataSourceInterface;

class MyDataSource implements DataSourceInterface, SearchAwareDataSourceInterface
{
    // ...

    public function getGlobalSearchColumnLabels(): array
    {
        return ['Name', 'Description', 'Email'];
    }
}
```

`DoctrineDataSource` implements this automatically. Custom data sources may
implement it to provide the same UX improvement.

## Debugging

### Interactive Mode

```bash
bin/console debug:datasource
```

Lists all registered data sources and prompts you to select one to view details:

```
Registered Data Sources
=======================

Found 3 data source(s)

 -------------- --------------- --------- ----------------------------------------
  Identifier     Label           Type      Class
 -------------- --------------- --------- ----------------------------------------
  Product        Products        Doctrine  Kachnitel\AdminBundle\DataSource\DoctrineDataSource
  Category       Categories      Doctrine  Kachnitel\AdminBundle\DataSource\DoctrineDataSource
  api-products   API Products    Custom    App\DataSource\ApiProductDataSource
 -------------- --------------- --------- ----------------------------------------

 Select a data source to see details:
  [0] Product
  [1] Category
  [2] api-products
 >
```

### Direct Access

```bash
bin/console debug:datasource --identifier=Product
# or
bin/console debug:datasource -i Product
```

### Data Source Details

The detail view displays comprehensive information:

- **Basic Information**: Identifier, label, icon, type, class, ID field
- **Pagination Defaults**: Default sort field, direction, items per page
- **Supported Actions**: Which CRUD actions are available (`index`, `show`, `new`, `edit`, `delete`, `batch_delete`)
- **Columns**: Column definitions with name, label, type, sortable status, and custom templates
- **Filters**: Filter definitions with name, label, type, operator, and enum options

## API Reference

**Key Interfaces & Classes:**

| Class | Purpose |
|-------|---------|
| [`DataSourceInterface`](../src/DataSource/DataSourceInterface.php) | Contract for all data sources — implement this to create custom data providers |
| [`DataSourceProviderInterface`](../src/DataSource/DataSourceProviderInterface.php) | Registry-like service that returns all available data sources |
| [`ColumnMetadata`](../src/ValueObject/ColumnMetadata.php) | Describes a column: name, label, type, sortability, template, grouping |
| [`FilterMetadata`](../src/ValueObject/FilterMetadata.php) | Describes a filter: type, operator, options, placeholders, etc. Factory methods for common patterns |
| [`DoctrineDataSource`](../src/DataSource/DoctrineDataSource.php) | Built-in data source for Doctrine ORM entities |
| [`DoctrineDataSourceFactory`](../src/DataSource/DoctrineDataSourceFactory.php) | Automatically creates `DoctrineDataSource` instances for `#[Admin]` entities |

**ColumnMetadata Factory Methods:**
```php
ColumnMetadata::create(string $name, ?string $label = null, string $type = 'string', ...)
```

**FilterMetadata Factory Methods:**
```php
FilterMetadata::text($name, $label, $placeholder)
FilterMetadata::number($name, $label, $operator)
FilterMetadata::date($name, $label, $operator)
FilterMetadata::dateRange($name, $label)
FilterMetadata::enum($name, $options, $label, $showAllOption, $multiple)
FilterMetadata::enumClass($name, $enumClass, $label, ...)
FilterMetadata::boolean($name, $label, $showAllOption)
FilterMetadata::collection($name, $searchFields, $label, ...)
```

See the source files above for complete method signatures and constructor parameters.
