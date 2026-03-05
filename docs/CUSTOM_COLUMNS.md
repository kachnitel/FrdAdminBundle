## Custom Columns

Custom columns let you add **virtual, template-driven columns** to the entity list view — columns that are not backed by a Doctrine field. Use them for computed values, enriched badges, or any cell content that pulls from the entity but doesn't map 1:1 to a property.

### Quick Start

```php
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminCustomColumn;

#[Admin(label: 'Users', columns: ['id', 'email', 'fullName', 'createdAt'])]
#[AdminCustomColumn(
    name: 'fullName',
    template: 'admin/columns/user_full_name.html.twig',
    label: 'Full Name',
)]
class User
{
    private string $firstName;
    private string $lastName;
    // ...
}
```

```twig
{# templates/admin/columns/user_full_name.html.twig #}
{# Variables available: entity, value (always null), property, cell #}
{{ entity.firstName }} {{ entity.lastName }}
```

### The `#[AdminCustomColumn]` Attribute

**Namespace:** `Kachnitel\AdminBundle\Attribute\AdminCustomColumn`

The attribute is placed on the entity **class** and can be repeated for multiple custom columns:

```php
#[Admin(label: 'Products')]
#[AdminCustomColumn(
    name: 'activityBadge',
    template: 'admin/columns/activity_badge.html.twig',
    label: 'Activity',
    sortable: false,
)]
#[AdminCustomColumn(
    name: 'priceFormatted',
    template: 'admin/columns/price_formatted.html.twig',
    label: 'Price',
)]
class Product { }
```

### Parameters

| Parameter  | Type     | Default      | Description |
|------------|----------|--------------|-------------|
| `name`     | `string` | **required** | Unique column identifier |
| `template` | `string` | **required** | Twig template path for this cell |
| `label`    | `?string`| `null`       | Column header label (humanised from `name` when null) |
| `sortable` | `bool`   | `false`      | Whether a sort link is rendered (no DB field = false by default) |

### Column Ordering

**When `columns:` is set in `#[Admin]`** — include the custom column name wherever you want it:

```php
#[Admin(columns: ['id', 'email', 'fullName', 'createdAt'])]  // fullName between email and createdAt
#[AdminCustomColumn(name: 'fullName', template: '...')]
class User { }
```

**When `columns:` is NOT set** — custom columns are **appended after all auto-detected Doctrine columns** in the order they are declared:

```php
#[Admin(label: 'Users')]               // no columns: — auto-detect
#[AdminCustomColumn(name: 'badge1', template: '...')]   // appended 1st
#[AdminCustomColumn(name: 'badge2', template: '...')]   // appended 2nd
class User { }
// Result order: [id, name, email, ..., badge1, badge2]
```

### Template Variables

Inside a custom column template, you have access to:

| Variable   | Type      | Description |
|------------|-----------|-------------|
| `entity`   | `object`  | The entity object — use this for all data access |
| `value`    | `null`    | Always `null` for custom columns (no Doctrine field) |
| `property` | `string`  | The column name string (e.g. `'fullName'`) |
| `cell`     | `bool`    | Always `true` (rendering in a table cell) |

```twig
{# templates/admin/columns/activity_badge.html.twig #}
{% set score = entity.activityScore %}
<span class="badge {% if score > 80 %}badge-success{% else %}badge-warning{% endif %}">
    {{ score }}
</span>
```

### Multiple Custom Columns

Stack as many `#[AdminCustomColumn]` attributes as needed:

```php
#[Admin(
    label: 'Orders',
    columns: ['id', 'customer', 'total', 'statusBadge', 'deliveryCountdown', 'createdAt'],
)]
#[AdminCustomColumn(name: 'statusBadge',        template: 'admin/columns/order_status.html.twig',     label: 'Status')]
#[AdminCustomColumn(name: 'deliveryCountdown',  template: 'admin/columns/delivery_countdown.html.twig', label: 'Delivery')]
class Order { }
```

### API Reference

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AdminCustomColumn
{
    public function __construct(
        public readonly string $name,
        public readonly string $template,
        public readonly ?string $label = null,
        public readonly bool $sortable = false,
    ) {}
}
```
