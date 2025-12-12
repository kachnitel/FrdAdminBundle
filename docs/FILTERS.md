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
| date, datetime | Date picker | >= | Created at |
| PHP enum | Dropdown | = | Status, type |
| boolean | Yes/No/All | = | Active, published |
| ManyToOne, OneToOne | Text search | LIKE | User, category |

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
    type: 'text',              // Override auto-detected type
    enabled: true,             // Enable/disable
    label: 'Custom Label',     // Override label
    searchFields: ['field'],   // For relations
    operator: 'LIKE',          // SQL operator
    placeholder: 'Search...',  // Input placeholder
    priority: 10               // Display order
)]
```

## Template Customization

Override filter templates for specific fields or entities:

```
templates/
  bundles/KachnitelAdminBundle/
    filters/
      _default.html.twig                    # All filters
      App/Entity/Product/name_filter.html.twig  # Specific field
```

## Performance Tips

- **Index filtered columns** in your database for better performance
- **Limit searchFields** on relationships to indexed columns only
- **Disable unused filters** with `enabled: false`

For more details on template customization, see the [Template Overrides Guide](TEMPLATE_OVERRIDES.md).
