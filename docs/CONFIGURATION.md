# Attribute-Based Configuration Guide

This guide covers how to configure entities for the admin bundle using PHP attributes.

## Table of Contents

- [Quick Start](#quick-start)
- [Bundle Configuration](#bundle-configuration)
- [The Admin Attribute](#the-admin-attribute)
- [Configuration Options](#configuration-options)
- [AdminRoutes Attribute](#adminroutes-attribute)
- [Column Filtering](#column-filtering)
- [Permissions](#permissions)
- [Examples](#examples)

## Quick Start

Add the `#[Admin]` attribute to any Doctrine entity to make it available in the admin:

```php
use Kachnitel\AdminBundle\Attribute\Admin;

#[Admin]  // That's it! Uses sensible defaults
class Product
{
    // ...
}
```

The entity will now appear in the admin dashboard with auto-detected columns.

**With common options:**

```php
#[Admin(label: 'Products', icon: 'inventory')]
class Product
{
    // ...
}
```

## Bundle Configuration

**Minimal** - just enable the bundle:

```yaml
# config/packages/kachnitel_admin.yaml
kachnitel_admin: ~
```

**Typical setup:**

```yaml
# config/packages/kachnitel_admin.yaml
kachnitel_admin:
    base_layout: 'layout.html.twig'  # Your app's base template
    required_role: 'ROLE_ADMIN'      # Role required to access admin
```

<details>
<summary><strong>All configuration options</strong></summary>

```yaml
kachnitel_admin:
    # Entity and form namespaces
    entity_namespace: 'App\Entity\'      # Default namespace for entities
    form_namespace: 'App\Form\'          # Default namespace for form types
    form_suffix: 'FormType'              # Suffix for form type classes

    # Layout and routing
    base_layout: 'layout.html.twig'     # Your app's base layout (optional)
    route_prefix: 'app_admin_entity'    # Route prefix for CRUD operations
    dashboard_route: 'app_admin_dashboard'  # Dashboard route name

    # Security
    required_role: 'ROLE_ADMIN'         # Default required role

    # Features
    enable_generic_controller: true     # Enable generic admin controller

    # Pagination
    pagination:
        default_items_per_page: 20      # Default items per page
        allowed_items_per_page: [10, 20, 50, 100]  # Allowed values
```

### Key Configuration Options

#### base_layout
**Type:** `?string` **Default:** `null`

Specify your application's base layout template. If not set, admin templates use the bundle's minimal default layout.

Admin templates will extend this layout and provide blocks like `title`, `headerTitle`, and `content`.

#### entity_namespace
**Type:** `string` **Default:** `'App\\Entity\\'`

Base namespace for your Doctrine entities. Used when resolving entity short names.

#### required_role
**Type:** `string|false` **Default:** `'ROLE_ADMIN'`

Global required role for accessing the admin. Can be overridden per-entity using the `permissions` option in the `#[Admin]` attribute. False disables default controller security in `GenericAdminController`.

</details>

## The Admin Attribute

The `#[Admin]` attribute marks an entity as manageable through the admin interface and provides configuration options.

**Namespace:** `Kachnitel\AdminBundle\Attribute\Admin`

### Auto-Discovery

Entities with the `#[Admin]` attribute are **automatically discovered**!

```php
#[ORM\Entity]
#[Admin(label: 'Users')]  // ← Auto-discovered!
class User
{
    // ...
}
```

The bundle scans all Doctrine entities at runtime and finds those with the attribute.

## Configuration Options

### Basic Options

#### label
**Type:** `?string` **Default:** Entity class name

Display name for the entity in the admin interface.

```php
#[Admin(label: 'Products')]
```

#### icon
**Type:** `?string` **Default:** `null`

[Material Icons](https://fonts.google.com/icons) icon name.

```php
#[Admin(icon: 'inventory')]
```

#### formType
**Type:** `?string` **Default:** `null`

Custom form type class for create/edit forms.

```php
#[Admin(formType: ProductFormType::class)]
```

### Feature Toggles

#### enableFilters
**Type:** `bool` **Default:** `true`

Enable/disable column filtering in the list view.

```php
#[Admin(enableFilters: false)]  // Disable filtering
```

#### enableBatchActions
**Type:** `bool` **Default:** `false`

Enable/disable batch actions for selecting and performing operations on multiple entities at once.

**Features:**
- Individual row selection with checkboxes
- **Shift+Click** for range selection (click first checkbox, then shift+click another to select all rows between them)
- **Ctrl/Cmd+Click** for multi-toggle
- Master checkbox to select/deselect all (with indeterminate state when partially selected)
- Batch delete with confirmation dialog
- Real-time selection counter

**Requirements:**
- User must have delete permission (`ADMIN_DELETE`) to see batch actions
- Must explicitly enable with `enableBatchActions: true`

```php
// Enable batch actions for this entity
#[Admin(enableBatchActions: true)]

// Enable with custom delete permission
#[Admin(
    enableBatchActions: true,
    permissions: ['delete' => 'ROLE_SUPER_ADMIN']
)]
```

**Note:** Batch actions are disabled by default for safety. The batch delete UI includes a confirmation dialog before deletion to prevent accidental data loss.

### Column Configuration

#### columns
**Type:** `?array<string>` **Default:** `null` (auto-detect)

Explicitly specify which columns to display. If `null`, columns are auto-detected from entity properties.

```php
#[Admin(columns: ['id', 'name', 'email', 'createdAt'])]
```

**Order matters:** Columns appear in the order specified.

#### excludeColumns
**Type:** `?array<string>` **Default:** `null`

Columns to exclude from display (useful with auto-detection).

```php
#[Admin(excludeColumns: ['password', 'salt', 'internalNotes'])]
```

**Note:** `columns` and `excludeColumns` work together:
- If `columns` is set, `excludeColumns` is applied to that list
- If `columns` is `null`, `excludeColumns` removes from auto-detected columns

#### filterableColumns
**Type:** `?array<string>` **Default:** `null` (all visible columns)

Specify which columns can be filtered. If `null`, all visible columns are filterable if supported.

```php
#[Admin(
    columns: ['id', 'name', 'price', 'stock', 'createdAt'],
    filterableColumns: ['name', 'price']  // Only these can be filtered
)]
```

### Pagination

#### itemsPerPage
**Type:** `?int` **Default:** `null` (use global default: 20)

Number of items per page for this entity.

```php
#[Admin(itemsPerPage: 50)]
```

**Limits:** Must be one of the allowed values (default: 10, 20, 50, 100).

### Sorting

#### sortBy
**Type:** `?string` **Default:** `null` ('id')

Default column to sort by.

```php
#[Admin(sortBy: 'createdAt')]
```

#### sortDirection
**Type:** `?string` **Default:** `null` ('DESC')

Default sort direction: `'ASC'` or `'DESC'`.

```php
#[Admin(sortBy: 'name', sortDirection: 'ASC')]
```

### Permissions

#### permissions
**Type:** `?array<string, string>` **Default:** `null`

Per-action permission requirements. Map of action name to required role.

```php
#[Admin(
    permissions: [
        'index' => 'ROLE_PRODUCT_VIEW',
        'new' => 'ROLE_PRODUCT_CREATE',
        'edit' => 'ROLE_PRODUCT_EDIT',
        'delete' => 'ROLE_PRODUCT_DELETE',
    ]
)]
```

**Available Actions:**
- `index` - View entity list
- `new` - Create new entity
- `show` - View entity details
- `edit` - Edit entity
- `delete` - Delete entity

**Fallback:** If no specific permission is set, the global `kachnitel_admin.required_role` is used (default: `ROLE_ADMIN`).

## AdminRoutes Attribute

The `#[AdminRoutes]` attribute defines custom routes for CRUD operations.

**Namespace:** `Kachnitel\AdminBundle\Attribute\AdminRoutes`

### Basic Usage

```php
use Kachnitel\AdminBundle\Attribute\AdminRoutes;

#[AdminRoutes([
    'index' => 'app_product_index',
    'new' => 'app_product_new',
    'show' => 'app_product_show',
    'edit' => 'app_product_edit',
    'delete' => 'app_product_delete'
])]
class Product
{
    // ...
}
```

### When to Use

Use `#[AdminRoutes]` when you have **custom controllers** for your entities instead of using the generic admin controller.

**Example:**
```php
// Custom controller
class ProductController extends AbstractAdminController
{
    #[Route('/products', name: 'app_product_index')]
    public function index(): Response
    {
        // Custom index logic
    }
}

// Entity with custom routes
#[Admin(label: 'Products')]
#[AdminRoutes(['index' => 'app_product_index'])]
class Product {}
```

## Column Filtering

The bundle provides automatic filtering based on Doctrine property types. You can fine-tune filtering with the `#[ColumnFilter]` attribute.

### ColumnFilter Attribute

**Namespace:** `Kachnitel\AdminBundle\Attribute\ColumnFilter`

```php
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

class Product
{
    #[ColumnFilter(
        type: ColumnFilter::TYPE_TEXT,
        placeholder: 'Search by name...'
    )]
    private string $name;

    #[ColumnFilter(type: ColumnFilter::TYPE_NUMBER)]
    private int $stock;

    #[ColumnFilter(type: ColumnFilter::TYPE_DATE)]
    private \DateTimeInterface $createdAt;

    #[ColumnFilter(enabled: false)]
    private string $internalId;
}
```

### Available Filter Types

| Constant | Description |
|----------|-------------|
| `TYPE_TEXT` | Text input (default for strings) |
| `TYPE_NUMBER` | Number input |
| `TYPE_DATE` | Date picker (matches exact day) |
| `TYPE_DATERANGE` | Date range picker (from/to) |
| `TYPE_BOOLEAN` | Yes/No/All dropdown |
| `TYPE_RELATION` | Search related entities |

See [Filters](./FILTERS.md) for details

## Permissions

### Per-Entity Permissions

Grant different roles access to different operations:

```php
#[Admin(
    permissions: [
        'index' => 'ROLE_USER',        // Anyone can view
        'show' => 'ROLE_USER',         // Anyone can view details
        'new' => 'ROLE_EDITOR',        // Editors can create
        'edit' => 'ROLE_EDITOR',       // Editors can edit
        'delete' => 'ROLE_ADMIN',      // Only admins can delete
    ]
)]
class Article
{
    // ...
}
```

### Permission Hierarchy

1. **Entity-specific permission** (highest priority)
   - Defined in `#[Admin(permissions: [...])]`

2. **Global admin role** (fallback)
   - Defined in `kachnitel_admin.required_role` (default: `ROLE_ADMIN`)

### Example: Read-Only Entity

```php
#[Admin(
    label: 'Audit Logs',
    permissions: [
        'index' => 'ROLE_ADMIN',
        'show' => 'ROLE_ADMIN',
        // No 'new', 'edit', or 'delete' - those actions won't be available
    ]
)]
class AuditLog
{
    // ...
}
```

## Examples

### Minimal - Just Works

```php
#[Admin]
class Category
{
    // Auto-detects columns from Doctrine metadata
    // Uses class name as label
    // Default pagination (20 items)
    // Requires ROLE_ADMIN
}
```

### Common Configurations

```php
// With label and icon
#[Admin(label: 'Products', icon: 'inventory')]
class Product { }

// With batch actions
#[Admin(label: 'Blog Posts', enableBatchActions: true)]
class BlogPost { }

// With custom permissions
#[Admin(
    label: 'Users',
    permissions: [
        'index' => 'ROLE_USER_ADMIN',
        'delete' => 'ROLE_SUPER_ADMIN',
    ]
)]
class User { }

// Read-only (no create/edit/delete)
#[Admin(
    label: 'Audit Logs',
    permissions: ['index' => 'ROLE_ADMIN', 'show' => 'ROLE_ADMIN']
)]
class AuditLog { }
```

<details>
<summary><strong>Full example: E-Commerce Product with all options</strong></summary>

```php
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminRoutes;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

#[ORM\Entity]
#[Admin(
    label: 'Products',
    icon: 'inventory',
    columns: ['id', 'sku', 'name', 'price', 'stock', 'category', 'active', 'createdAt'],
    excludeColumns: ['internalNotes', 'costPrice'],
    filterableColumns: ['name', 'sku', 'category', 'active'],
    permissions: [
        'index' => 'ROLE_PRODUCT_VIEW',
        'new' => 'ROLE_PRODUCT_MANAGE',
        'edit' => 'ROLE_PRODUCT_MANAGE',
        'delete' => 'ROLE_ADMIN',
    ],
    itemsPerPage: 25,
    sortBy: 'createdAt',
    sortDirection: 'DESC'
)]
#[AdminRoutes([
    'index' => 'app_product_index',
    'new' => 'app_product_new',
    'show' => 'app_product_show',
    'edit' => 'app_product_edit',
    'delete' => 'app_product_delete'
])]
class Product
{
    #[ORM\Column]
    #[ColumnFilter(type: ColumnFilter::TYPE_TEXT, placeholder: 'Search SKU...')]
    private string $sku;

    #[ORM\Column]
    #[ColumnFilter(type: ColumnFilter::TYPE_TEXT, placeholder: 'Search name...')]
    private string $name;

    #[ORM\Column(type: 'decimal')]
    #[ColumnFilter(type: ColumnFilter::TYPE_NUMBER)]
    private string $price;

    #[ORM\Column]
    #[ColumnFilter(type: ColumnFilter::TYPE_NUMBER)]
    private int $stock;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ColumnFilter(
        type: ColumnFilter::TYPE_RELATION,
        searchFields: ['name'],
        placeholder: 'Filter by category...'
    )]
    private ?Category $category = null;

    #[ORM\Column]
    #[ColumnFilter(type: ColumnFilter::TYPE_BOOLEAN)]
    private bool $active = true;

    #[ORM\Column]
    #[ColumnFilter(type: ColumnFilter::TYPE_DATE)]
    private \DateTimeImmutable $createdAt;

    // Internal fields - excluded from admin
    #[ORM\Column]
    #[ColumnFilter(enabled: false)]
    private string $internalNotes = '';

    #[ORM\Column(type: 'decimal')]
    #[ColumnFilter(enabled: false)]
    private string $costPrice;
}
```

</details>

<details>
<summary><strong>Full example: User Management</strong></summary>

```php
#[ORM\Entity]
#[Admin(
    label: 'Users',
    icon: 'people',
    columns: ['id', 'email', 'name', 'roles', 'active', 'lastLogin'],
    permissions: [
        'index' => 'ROLE_USER_ADMIN',
        'show' => 'ROLE_USER_ADMIN',
        'edit' => 'ROLE_USER_ADMIN',
        'delete' => 'ROLE_SUPER_ADMIN',
    ],
    sortBy: 'lastLogin',
    sortDirection: 'DESC'
)]
class User implements UserInterface
{
    #[ORM\Column]
    #[ColumnFilter(type: ColumnFilter::TYPE_TEXT)]
    private string $email;

    #[ORM\Column]
    #[ColumnFilter(type: ColumnFilter::TYPE_TEXT)]
    private string $name;

    #[ORM\Column]
    #[ColumnFilter(type: ColumnFilter::TYPE_BOOLEAN)]
    private bool $active = true;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    #[ColumnFilter(type: ColumnFilter::TYPE_DATE)]
    private ?\DateTimeInterface $lastLogin = null;

    // Never show password in admin
    #[ORM\Column]
    #[ColumnFilter(enabled: false)]
    private string $password;
}
```

</details>

## Best Practices

### ✅ DO:

- Use `#[Admin]` for all entities you want in the admin
- Set meaningful `label` and `icon` for better UX
- Use `permissions` for fine-grained access control
- Exclude sensitive fields with `excludeColumns`
- Set appropriate `itemsPerPage` for entities with many records
- Use `filterableColumns` to limit filtering to useful fields
- Enable `enableBatchActions: true` only for entities where bulk operations make sense
- Use strict delete permissions when batch actions are enabled

### ❌ DON'T:

- Mix YAML and attribute configuration for the same entity
- Include sensitive data (passwords, tokens) in `columns`
- Set `itemsPerPage` too high (causes performance issues)
- Forget to set permissions for sensitive entities
- Use `columns` when auto-detection works fine
- Enable batch actions on critical entities (users, financial records) without careful consideration

<details>
<summary><strong>API Reference</strong></summary>

### Admin Attribute

```php
#[Attribute(Attribute::TARGET_CLASS)]
class Admin
{
    public function __construct(
        ?string $label = null,
        ?string $icon = null,
        ?string $formType = null,
        bool $enableFilters = true,
        bool $enableBatchActions = true,
        ?array $columns = null,
        ?array $excludeColumns = null,
        ?array $filterableColumns = null,
        ?array $permissions = null,
        ?int $itemsPerPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null,
    ) {}
}
```

### AdminRoutes Attribute

```php
#[Attribute(Attribute::TARGET_CLASS)]
class AdminRoutes
{
    public function __construct(
        private array $routes = []
    ) {}
}
```

### ColumnFilter Attribute

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
class ColumnFilter
{
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_DATERANGE = 'daterange';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_RELATION = 'relation';

    public function __construct(
        string $type = self::TYPE_TEXT,
        bool $enabled = true,
        ?string $placeholder = null,
        ?array $searchFields = null,
    ) {}
}
```

</details>

## Need Help?

- See [TEMPLATE_OVERRIDES.md](TEMPLATE_OVERRIDES.md) for customizing appearance
- Check `vendor/kachnitel/admin-bundle/src/Attribute/` for attribute source code
- Review example entities in your application
