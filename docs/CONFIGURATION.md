# Attribute-Based Configuration Guide

This guide covers how to configure entities for the admin bundle using PHP attributes.

## Table of Contents

- [Quick Start](#quick-start)
- [Bundle Configuration](#bundle-configuration)
- [The Admin Attribute](#the-admin-attribute)
- [Configuration Options](#configuration-options)
- [Row Actions](#row-actions)
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
    entity_namespace: 'App\Entity\'     # Default namespace for entities
    form_namespace: 'App\Form\'         # Default namespace for form types
    form_suffix: 'FormType'             # Suffix for form type classes

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

    # Archive / soft-delete filtering
    archive:
        expression: 'item.getDeletedAt()'    # field expression applied to every entity
        role: ~                         # role required to toggle; ~ = everyone
```

### Key Configuration Options

#### base_layout
**Type:** `?string` **Default:** `null`

Specify your application's base layout template. If not set, admin templates use the bundle's minimal default layout.

Admin templates will extend this layout and provide blocks like `title`, `headerTitle`, and `content`.

#### entity_namespace
**Type:** `string` **Default:** `'App\\Entity\\'`

Base namespace for your Doctrine entities. Used when resolving entity short
names — including by `EntityList` itself, so
`<twig:K:Admin:EntityList entityShortClass="Product" />` resolves to the
right FQCN without passing `entityClass` explicitly.

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

Enable multi-row selection (checkboxes, Shift+Click range select, master checkbox) with built-in batch delete and confirmation dialog. Requires `ADMIN_DELETE` permission to appear. Disabled by default.

```php
// Enable batch actions for this entity
#[Admin(enableBatchActions: true)]

// Enable with custom delete permission
#[Admin(
    enableBatchActions: true,
    permissions: ['delete' => 'ROLE_SUPER_ADMIN']
)]
```

Also requires registering a Stimulus controller for the selection UI — see the [Batch Actions Guide](BATCH_ACTIONS.md) for that setup step, selection behavior, and adding custom batch actions.

#### enableInlineEdit
**Type:** `bool` **Default:** `false`

Enable per-field inline editing directly in the list view. When enabled, an ✏️
button appears on each row, and writable columns show a ✎ trigger on hover.

**Disabled by default** to prevent accidental data modification. You must
explicitly opt each entity in.

```php
// Disabled (the default — no attribute needed)
#[Admin(label: 'Audit Logs')]
class AuditLog { }

// Enabled
#[Admin(label: 'Products', enableInlineEdit: true)]
class Product { }
```

Individual columns can override this setting via `#[AdminColumn(editable: ...)]`.
See the full [Inline Editing Guide](INLINE_EDIT.md) for details, including
per-column opt-in/opt-out, expression-based editability, and supported field types.

#### enableObjectAuth
**Type:** `bool` **Default:** `false`

Runs an additional authorization check against the actual entity **instance**
(not just its class) on show/edit/new/delete/archive/unarchive and inline
field edits, on top of the existing class-level `#[Admin(permissions: ...)]` /
`AdminEntityVoter` check.

```php
#[Admin(label: 'Contacts', enableObjectAuth: true)]
class Contact { }
```

**Requires a supporting Symfony voter.** Turning this on for an entity with no
registered voter whose `supports()` accepts that entity as subject results in
every show/edit/new/delete/archive, and every inline field edit, for it being denied — not a silent
allow. See the full [Object-Level Authorization Guide](OBJECT_AUTHORIZATION.md)
for the mechanism, a worked voter example, and this gotcha in detail before
enabling it.

#### archiveExpression
**Type:** `?string` **Default:** `null`

Expression that evaluates to `true` when a row is archived or soft-deleted.
Must be a simple `item.getFieldName()` or `item.isFieldName()` call (the
`entity.` prefix is also supported) for list-level DQL filtering to work.
Supported backing field types: `boolean` and all
Doctrine datetime variants (`datetime`, `datetime_immutable`, `date`,
`date_immutable`, `datetimetz`, `datetimetz_immutable`).

```php
#[Admin(archiveExpression: 'item.isArchived()')]   // boolean flag
#[Admin(archiveExpression: 'item.getDeletedAt()')]  // nullable datetime (soft-delete)
```

When set, a **Show archived** toggle appears in the entity list next to the
search bar. Archived rows are hidden by default; the toggle reveals them.
The `showArchived` state is URL-synchronised so it survives page refreshes.

Can also be set globally for all entities via `kachnitel_admin.archive.expression`
in the bundle configuration. A per-entity value takes priority over the global one.

See the full [Archive Guide](ARCHIVE.md).

#### archiveRole
**Type:** `?string` **Default:** `null`

Required role to use the **Show archived** toggle. When `null` (the default),
all users who can view the list may toggle it. The underlying DQL restriction
still applies for users who lack the role — they always see the non-archived view.

```php
#[Admin(archiveExpression: 'item.isArchived()', archiveRole: 'ROLE_ADMIN')]
```

Can also be set globally via `kachnitel_admin.archive.role`.

#### archiveDisabled
**Type:** `bool` **Default:** `false`

Opt this entity out of archive filtering even when a global
`kachnitel_admin.archive.expression` is configured.

```php
#[Admin(label: 'Categories', archiveDisabled: true)]
```

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

## Row Actions

Row action buttons appear in each entity row alongside the default Show and Edit buttons.

See the [Row Actions Guide](ROW_ACTIONS.md) for conditions, overrides, and programmatic providers.

### Quick Example

```php
use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;

#[Admin(label: 'Orders')]
#[AdminActionsConfig(exclude: ['edit'])]   // Remove default Edit
#[AdminAction(
    name: 'approve',
    label: 'Approve',
    icon: '✅',
    route: 'app_order_approve', // passes array of IDs to configured route
    method: 'POST',
    condition: 'entity.status == "pending"',
    priority: 30,
)]
#[AdminAction(
    name: 'cancel',
    label: 'Cancel',
    icon: '❌',
    liveComponent: OrderCancelButton::class, // Custom LiveComponent for complex interactions
    condition: 'entity.status in ["pending", "approved"]',
    priority: 40,
)]
class Order { }
```

Actions render in `priority` order — lower numbers appear first. Default Show is 10, Edit is 20.

See the [Row Actions Guide](ROW_ACTIONS.md) for every `#[AdminAction]` parameter, visibility conditions, overriding defaults, and programmatic providers.

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

Filter types are auto-detected from Doctrine metadata, or set explicitly via `type:` — text, number, date, date range, boolean, enum, relation, and collection. See [Filters](./FILTERS.md) for what each one does, how auto-detection works, nested search fields, and the full list of `#[ColumnFilter]` options.

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

// With collection display
#[Admin(label: 'Orders')]
class Order {
    #[AdminColumn(collectionDisplay: true, collectionLimit: 3)]
    private Collection $lineItems;
}

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

// Soft-delete with role-gated toggle
#[Admin(
    label: 'Orders',
    archiveExpression: 'item.getDeletedAt()',
    archiveRole: 'ROLE_ADMIN',
)]
class Order { }
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
- Exclude sensitive fields with `excludeColumns: [...]` or selectively with `#[ColumnPermission('ROLE_ADMIN')]`
- Set appropriate `itemsPerPage` for entities with many records
- Use `filterableColumns` to limit filtering to useful fields
- Enable `enableBatchActions: true` only for entities where bulk operations make sense
- Use strict delete permissions when batch actions are enabled

### ❌ DON'T:

- Include sensitive data (passwords, tokens) in `columns`
- Set `itemsPerPage` too high (causes performance issues)
- Forget to set permissions for sensitive entities
- Enable batch actions on critical entities (users, financial records) without careful consideration

<details>
<summary><strong>API Reference</strong></summary>

**Key Attributes:**

| Attribute | Target | Purpose | Source |
|-----------|--------|---------|--------|
| `Admin` | Class | Configure entity for admin | [src/Attribute/Admin.php](../src/Attribute/Admin.php) |
| `AdminRoutes` | Class | Custom CRUD routes | [src/Attribute/AdminRoutes.php](../src/Attribute/AdminRoutes.php) |
| `ColumnFilter` | Property | Fine-tune column filtering | [src/Attribute/ColumnFilter.php](../src/Attribute/ColumnFilter.php) |
| `AdminColumn` | Property | Column display & inline edit config | [src/Attribute/AdminColumn.php](../src/Attribute/AdminColumn.php) |
| `AdminAction` | Class | Custom row action button | [src/Attribute/AdminAction.php](../src/Attribute/AdminAction.php) |
| `AdminActionsConfig` | Class | Show/hide/reorder default actions | [src/Attribute/AdminActionsConfig.php](../src/Attribute/AdminActionsConfig.php) |
| `ColumnPermission` | Property | Role-based column visibility | [src/Attribute/ColumnPermission.php](../src/Attribute/ColumnPermission.php) |

**Quick Parameter Reference:**

**#[Admin]** — Common parameters:
- `label: ?string` — Display name
- `icon: ?string` — Material icon name
- `columns: ?array<string>` — Visible columns (auto-detect if null)
- `excludeColumns: ?array<string>` — Exclude from auto-detect
- `permissions: ?array<string, string>` — Role map (index, show, new, edit, delete)
- `enableFilters: bool = true`
- `enableBatchActions: bool = false`
- `enableColumnVisibility: bool = false` — user-toggleable column show/hide (see [Column Visibility](COLUMN_VISIBILITY.md))
- `enableInlineEdit: bool = false`
- `enableObjectAuth: bool = false` — instance-level authorization on top of class-level permissions; requires a supporting voter (see [Object-Level Authorization](OBJECT_AUTHORIZATION.md))
- `formComponent: ?string = null` — override the LiveComponent used for new/edit forms (see [Forms](FORMS.md#custom-form-components))
- `itemsPerPage: ?int = null`
- `sortBy: ?string = null`
- `sortDirection: ?string = null` — 'ASC' or 'DESC'
- `archiveExpression: ?string = null` — DQL path for soft-delete
- `archiveRole: ?string = null`

**#[AdminColumn]** — Collection display parameters:
- `collectionDisplay: bool = false` — Show items inline instead of count+link
- `collectionCollapsible: bool = true` — Wrap in `<details>` accordion
- `collectionLimit: ?int = 5` — Items before "+ N more…" link; null/0 = show all
- `collectionLabelMethod: ?string = null` — Custom method to get item label; null = auto-detect
- `editable: string|bool|null` — Inline edit config (see [Inline Editing Guide](INLINE_EDIT.md))
- `group: ?string` — Group columns together - See [Composite Columns](COMPOSITE_COLUMNS.md)

**#[ColumnFilter]** — Constants:
- `TYPE_TEXT`, `TYPE_NUMBER`, `TYPE_DATE`, `TYPE_DATERANGE`, `TYPE_BOOLEAN`, `TYPE_ENUM`, `TYPE_RELATION`, `TYPE_COLLECTION`

See the source files linked above for complete method signatures and constructor details.

#### AdminColumn Collection Display Usage

When you have collection-valued associations (e.g., `OneToMany`), use the parameters above:

```php
// Default: "5 items" with a link to filtered list
#[AdminColumn]
private Collection $items;

// Inline accordion (default limit of 5)
#[AdminColumn(collectionDisplay: true)]
private Collection $participants;

// Always-visible list, all items
#[AdminColumn(collectionDisplay: true, collectionCollapsible: false, collectionLimit: null)]
private Collection $tags;

// Custom label method for each item
#[AdminColumn(collectionDisplay: true, collectionLabelMethod: 'getDisplayName')]
private Collection $lineItems;
```

**Label Auto-Detection** (when `collectionLabelMethod: null`):
1. `getLabel()`
2. `getName()`
3. `getTitle()`
4. `__toString()`
5. `#id` (fallback)

**Inline Editing:**
See [Inline Editing Guide](INLINE_EDIT.md) for information about the `editable` parameter and its precedence rules.

</details>

## Need Help?

- See [TEMPLATE_OVERRIDES.md](TEMPLATE_OVERRIDES.md) for customizing appearance
- Check `vendor/kachnitel/admin-bundle/src/Attribute/` for attribute source code
- Review example entities in your application
