# Inline Editing

Inline editing lets users edit individual field values directly in the list view,
without navigating to a separate edit page. It is **disabled by default** — you
must explicitly opt each entity (or column) in.

## Table of Contents

- [Quick Start](#quick-start)
- [Entity-Level Opt-In](#entity-level-opt-in)
- [Column-Level Control](#column-level-control)
- [Precedence Rules](#precedence-rules)
- [Expression-Based Editability](#expression-based-editability)
- [Permissions](#permissions)
- [Supported Field Types](#supported-field-types)
- [Examples](#examples)

---

## Quick Start

Add `enableInlineEdit: true` to the `#[Admin]` attribute on your entity:

```php
use Kachnitel\AdminBundle\Attribute\Admin;

#[ORM\Entity]
#[Admin(label: 'Products', enableInlineEdit: true)]
class Product
{
    // All writable properties become inline-editable
}
```

That's it. An ✏️ button appears on each row, and clicking it activates
per-field edit triggers (✎) on every eligible column.

---

## Entity-Level Opt-In

### `#[Admin(enableInlineEdit: bool)]`

| Value | Behaviour |
|-------|-----------|
| `false` *(default)* | Inline editing is completely disabled. The ✏️ row button is not shown. |
| `true` | Inline editing is active for this entity. All writable columns are editable unless individually disabled. |

```php
// Disabled (the default — no attribute needed)
#[Admin(label: 'Audit Logs')]
class AuditLog { }

// Enabled
#[Admin(label: 'Products', enableInlineEdit: true)]
class Product { }
```

---

## Column-Level Control

Individual columns can override the entity default using `#[AdminColumn(editable: ...)]`.

### `#[AdminColumn(editable: null|true|false|'expression')]`

| Value | Behaviour |
|-------|-----------|
| `null` *(default)* | Inherit the entity's `enableInlineEdit` setting. |
| `true` | Always editable — overrides the entity default. Useful to opt a single column **in** even when the entity default is `false`. |
| `false` | Never editable — overrides the entity default. Hides the ✎ trigger entirely. |
| `'expression'` | ExpressionLanguage string evaluated at runtime. Completely bypasses the entity default. |

```php
#[Admin(label: 'Orders', enableInlineEdit: true)]
class Order
{
    // Inherits entity default → editable
    #[ORM\Column]
    private string $notes = '';

    // Opt out even though entity has inline editing enabled
    #[ORM\Column]
    #[AdminColumn(editable: false)]
    private string $orderNumber = '';

    // Expression-based: editable only when the order is a draft
    #[ORM\Column]
    #[AdminColumn(editable: 'entity.status == "draft"')]
    private string $description = '';
}
```

### Partial opt-in (entity disabled, columns opted in)

You can enable inline editing on specific columns **without** enabling it at
the entity level. In this case `editable: true` on the column acts as an
explicit override:

```php
// Entity has inline editing disabled (default)
#[Admin(label: 'Customers')]
class Customer
{
    // This column is always editable even though the entity default is false
    #[ORM\Column]
    #[AdminColumn(editable: true)]
    private string $notes = '';

    // All other columns remain read-only
}
```

> **Note:** The ✏️ row button appears whenever at least one column is eligible
> for inline editing (either via entity-level opt-in or a column-level
> `editable: true`).

---

## Precedence Rules

Column-level `editable` is resolved in this order:

1. **`false`** → always read-only, regardless of entity setting or permissions.
2. **String expression** → evaluate; if falsy, read-only. Entity default is bypassed entirely.
3. **`true`** → always editable. Entity default bypassed; ADMIN_EDIT voter + property-writable checks still apply.
4. **`null`** (or no `#[AdminColumn]`) → defer to the entity's `#[Admin(enableInlineEdit: ...)]`.

---

## Expression-Based Editability

The expression string is evaluated using Symfony's ExpressionLanguage. The
following variables are available:

| Variable | Type | Description |
|----------|------|-------------|
| `entity` | `object` | The current row entity |
| `auth` | `AuthorizationCheckerInterface` | Symfony's authorization checker |

Common patterns:

```php
// Editable only when status is "draft"
#[AdminColumn(editable: 'entity.status == "draft"')]

// Editable only for editors
#[AdminColumn(editable: 'is_granted("ROLE_EDITOR")')]

// Combine entity state and role check
#[AdminColumn(editable: 'entity.isDraft() && is_granted("ROLE_EDITOR")')]
```

> **Note:** When a string expression is provided, the entity-level
> `enableInlineEdit` setting is **ignored** — the expression has full
> control. The ADMIN_EDIT voter and property-writable checks still apply
> when the expression returns `true`.

---

## Permissions

Inline editing always enforces the `ADMIN_EDIT` voter check on top of the
`editable` resolution. A user must:

1. Pass the column's `editable` check (see [Precedence Rules](#precedence-rules)).
2. Be granted `ADMIN_EDIT` for the entity type.
3. Have a writable setter for the property (Symfony's `PropertyAccess`).

To restrict edit access to a specific role:

```php
#[Admin(
    label: 'Products',
    enableInlineEdit: true,
    permissions: ['edit' => 'ROLE_PRODUCT_MANAGER'],
)]
class Product { }
```

---

## Supported Field Types

The following field types support inline editing out of the box:

| Doctrine type | Component |
|---------------|-----------|
| `string` | `TextField` |
| `integer`, `smallint`, `bigint` | `IntegerField` |
| `float`, `decimal` | `FloatField` |
| `boolean` | `BooleanField` |
| `date`, `datetime`, `datetime_immutable` | `DateField` / `DateTimeField` |

Relation fields (`ManyToOne`, etc.) are **not** currently editable inline.

---

## Examples

### Fully enabled entity

```php
#[ORM\Entity]
#[Admin(label: 'Articles', enableInlineEdit: true)]
class Article
{
    #[ORM\Column]
    private string $title = '';     // editable

    #[ORM\Column]
    private bool $published = false; // editable

    // Read-only: computed value, no setter
    #[ORM\Column]
    #[AdminColumn(editable: false)]
    private string $slug = '';
}
```

### Selective columns only

```php
#[ORM\Entity]
#[Admin(label: 'Invoices')]  // enableInlineEdit defaults to false
class Invoice
{
    #[ORM\Column]
    private string $invoiceNumber = '';  // read-only

    // Only this column allows inline editing
    #[ORM\Column]
    #[AdminColumn(editable: true)]
    private string $notes = '';

    #[ORM\Column]
    private float $total = 0.0;  // read-only
}
```

### State-gated editing

```php
#[ORM\Entity]
#[Admin(label: 'Orders', enableInlineEdit: true)]
class Order
{
    #[ORM\Column]
    private string $status = 'pending';  // editable

    // Only editable while pending
    #[ORM\Column]
    #[AdminColumn(editable: 'entity.status == "pending"')]
    private string $deliveryAddress = '';

    // Never editable — immutable after creation
    #[ORM\Column]
    #[AdminColumn(editable: false)]
    private string $orderReference = '';
}
```
