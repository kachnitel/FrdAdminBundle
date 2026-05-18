# Column Filtering

The bundle provides automatic per-column filtering for entity list views.

## Quick Start

Filters are **automatically generated** from Doctrine metadata - no configuration required!

```php
#[Route('/products', name: 'app_admin_products')]
public function index(): Response
{
    return $this->render('@KachnitelAdmin/admin/index_live.html.twig', [
        'entityClass' => Product::class,
    ]);
}
```

## Filter Types

The bundle automatically creates appropriate filters based on property types:

| Doctrine Type | Filter UI | Operator | Example |
|--------------|-----------|----------|---------|
| string, text | Text input | LIKE | Name, description |
| integer, decimal | Number input | = | Price, quantity |
| date, datetime | Date picker | BETWEEN (exact day) | Created at |
| PHP enum | Dropdown | = | Status, type |
| PHP enum (multi) | Multi-select | IN | Tags, categories |
| boolean | Yes/No/All | = | Active, published |
| ManyToOne, OneToOne | Text search | LIKE | User, category |
| ManyToMany, OneToMany | Text search (EXISTS) | LIKE | Tags, attributes |

### Date Filters

**Single date** (`date` type) - matches the exact selected day:
```php
#[ColumnFilter(type: 'date')]
private \DateTimeInterface $createdAt;
```

**Date range** (`daterange` type) - allows selecting from/to dates:
```php
#[ColumnFilter(type: 'daterange')]
private \DateTimeInterface $createdAt;
```

The date range filter displays two date inputs. You can fill in just one (from or to) for open-ended ranges.

### Multi-Select Enum Filters

For enum fields where users need to filter by multiple values simultaneously, enable multi-select mode:

```php
#[ColumnFilter(multiple: true)]
private OrderStatus $status;
```

This renders a multi-select dropdown and uses the `IN` operator to match any of the selected values. Use Ctrl+click (Cmd+click on Mac) to select multiple options.

**Programmatic configuration:**

```php
use Kachnitel\AdminBundle\DataSource\FilterMetadata;

FilterMetadata::enumClass(
    name: 'status',
    enumClass: OrderStatus::class,
    multiple: true,
);
```

When `multiple: true`, the "All" option is hidden as selecting no values achieves the same effect.

### Collection Filters (ManyToMany / OneToMany)

Collection associations can be filtered by adding the `#[ColumnFilter]` attribute. Unlike single-valued relations, collection filters are **opt-in only** - you must explicitly add the attribute to enable filtering.

```php
// Minimal - auto-detects TYPE_COLLECTION, auto-detects searchFields
#[ORM\ManyToMany(targetEntity: Tag::class)]
#[ColumnFilter]
private Collection $tags;

// With explicit search fields
#[ORM\ManyToMany(targetEntity: OrderAttribute::class)]
#[ColumnFilter(searchFields: ['display', 'attr'])]
private Collection $attributes;

// Include in global search (not recommended for large collections)
#[ORM\ManyToMany(targetEntity: Tag::class)]
#[ColumnFilter(excludeFromGlobalSearch: false)]
private Collection $tags;
```

Collection filters use an **EXISTS subquery** for optimal performance:
- No row multiplication (pagination works correctly)
- Efficient index usage on join tables
- Returns entities that have **at least one** matching related item

**Performance Considerations:**
- Recommended for small-to-medium collections (up to ~1000 items per entity)
- By default, collection filters are excluded from global search (`excludeFromGlobalSearch: true`)
- Add database indexes on `searchFields` columns for better performance

**Programmatic configuration:**

```php
use Kachnitel\AdminBundle\DataSource\FilterMetadata;

FilterMetadata::collection(
    name: 'tags',
    searchFields: ['name'],
    excludeFromGlobalSearch: true,  // default
);
```

## Nested Search Fields (Dot Notation)

Both relation and collection filters support **dot-notation paths** in `searchFields`, allowing you to search through chained associations rather than just direct fields on the related entity.

```php
// Relation: search Order by customer name OR customer's company name
#[ColumnFilter(searchFields: ['name', 'company.name'])]
private Customer $customer;

// Collection: search Order by item name OR item's category label
#[ORM\ManyToMany(targetEntity: OrderItem::class)]
#[ColumnFilter(searchFields: ['name', 'category.label'])]
private Collection $items;
```

Typing `"acme"` in the filter above matches orders that have at least one item whose `name` or whose related `category.label` contains `"acme"`.

Paths can be arbitrarily deep (`a.b.c`) — intermediate associations are resolved automatically:

```php
// Three levels deep: order → items → product → brand name
#[ORM\ManyToMany(targetEntity: OrderItem::class)]
#[ColumnFilter(searchFields: ['sku', 'product.brand.name'])]
private Collection $items;
```

<details>
<summary><strong>How It Works</strong></summary>

**Relation filters** emit LEFT JOINs directly in the outer query, so intermediate aliases are available to the WHERE clause:

```sql
-- searchFields: ['title', 'deep.label']
LEFT JOIN e.middle rel_middle
LEFT JOIN rel_middle.deep rel_middle_deep
WHERE rel_middle.title LIKE :p
   OR rel_middle_deep.label LIKE :p
```

**Collection filters** embed the LEFT JOINs *inside* the EXISTS subquery. This is intentional — keeping them scoped to the subquery avoids row multiplication and keeps pagination correct:

```sql
-- searchFields: ['name', 'category.label']
EXISTS (
    SELECT 1 FROM App\Entity\OrderItem sub_items
    LEFT JOIN sub_items.category sub_items_category
    WHERE sub_items MEMBER OF e.items
      AND (sub_items.name LIKE :p OR sub_items_category.label LIKE :p)
)
```

Shared intermediate associations are never joined twice. If two search fields reference the same path segment (e.g. `category.name` and `category.code`), only one JOIN to `category` is emitted.

</details>

## Custom Configuration

Use the `#[ColumnFilter]` attribute to customize filters:

```php
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

class Product
{
    #[ColumnFilter(placeholder: 'Search products...')]
    private string $name;

    #[ColumnFilter(enabled: false)]  // Disable filter
    private string $internalCode;

    #[ColumnFilter(priority: 1)]  // Show first
    private \DateTimeInterface $createdAt;

    #[ColumnFilter(
        searchFields: ['name', 'email', 'phone'],  // Fields to search
        placeholder: 'Find customer...'
    )]
    private Customer $customer;
}
```

## Available Options

```php
#[ColumnFilter(
    type: 'text',                    // Override auto-detected type
    enabled: true,                   // Enable/disable
    label: 'Custom Label',           // Override label
    searchFields: ['field'],         // For relations/collections: properties to search (dot-notation supported for nested properties)
    operator: 'LIKE',                // SQL operator
    placeholder: 'Search...',        // Input placeholder
    priority: 10,                    // Display order
    multiple: false,                 // For enums: allow multi-select (uses IN operator)
    showAllOption: true,             // For enums: show "All" option
    excludeFromGlobalSearch: true    // For collections: exclude from global search (default: true)
)]
```

## Relation Filter Search Fields

For relation filters (ManyToOne, OneToOne), search fields are auto-detected based on a display **priority** list (top to bottom, first match is used):

1. `name` - most common identifier field
2. `label` - alternative naming field
3. `title` - another common naming field
4. `id` - fallback if none of the above exist

This means a relation like `#[ColumnFilter] private Customer $customer;` will automatically search the `name` field on Customer if it exists, or fall back to `id`.

**To override auto-detection**, explicitly set `searchFields`:

```php
#[ColumnFilter(searchFields: ['email', 'phone'])]
private Customer $customer;
```

Dot-notation paths also work here — see [Nested Search Fields](#nested-search-fields-dot-notation) above.

This auto-detection matches the display logic used in preview templates, ensuring consistent behavior between filtering and displaying related entities.

## Debugging Filters

Use the `admin:debug:filters` command to inspect filter configuration for any entity:

```bash
# List all admin entities and select one interactively
bin/console admin:debug:filters

# Inspect a specific entity (by short name or full class)
bin/console admin:debug:filters Product
bin/console admin:debug:filters App\\Entity\\Product

# Include non-Admin entities
bin/console admin:debug:filters --all

# Verbose mode: explains type detection and search field auto-detection
bin/console admin:debug:filters Product -v
```

The command shows:
- Filter type and operator for each property
- Enum class and multi-select status
- Relation target entity and search fields
- Properties that were skipped and why (in verbose mode)
- How types were detected (auto vs explicit attribute)

## Architecture

Filters are rendered using the `ColumnFilter` LiveComponent, which provides:
- Consistent filter UI across all columns
- Event-based communication with the parent `EntityList`
- Support for all filter types including multi-select enums

The component receives filter metadata and emits `filter:updated` events when values change:

```twig
{# EntityList automatically renders this for each filterable column #}
<twig:K:Admin:ColumnFilter
    column="status"
    value="{{ currentValue }}"
    :filterMetadata="{ type: 'enum', enumClass: 'App\\Enum\\Status', multiple: true }"
/>
```

Filter type templates are located in `templates/components/ColumnFilter/`:
- `_text.html.twig` - Text input with debounce
- `_number.html.twig` - Number input
- `_date.html.twig` - Date picker
- `_daterange.html.twig` - Date range (from/to)
- `_boolean.html.twig` - Yes/No/All dropdown
- `_enum.html.twig` - Enum dropdown (routes to `_enum_multiple.html.twig` when `multiple: true`)
- `_relation.html.twig` - Text search for related entities (also used for collection filters)

## Template Customization

Override filter templates by copying from the bundle:

```
templates/
  bundles/KachnitelAdminBundle/
    components/ColumnFilter/
      _text.html.twig      # Override text filter
      _enum.html.twig      # Override enum filter
```

## Performance Tips

- **Index filtered columns** in your database for better performance
- **Limit searchFields** on relationships to indexed columns only
- **Disable unused filters** with `enabled: false`

For more details on template customization, see the [Template Overrides Guide](TEMPLATE_OVERRIDES.md).
