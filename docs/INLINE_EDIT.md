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
- [Validation](#validation)
- [Save Feedback](#save-feedback)
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
the entity level.

---

## Precedence Rules

The editability of a column is resolved in this order:

1. `#[AdminColumn(editable: false)]` — **never editable**, short-circuits all other checks
2. `#[AdminColumn(editable: 'expression')]` — expression evaluated; entity default bypassed
3. `#[AdminColumn(editable: true)]` — **always eligible**; entity default bypassed
4. `#[AdminColumn(editable: null)]` or **no** `#[AdminColumn]` — read entity-level `enableInlineEdit`

After the above resolves to eligible, two additional gates apply:

5. **Voter** — `ADMIN_EDIT` must be granted for the entity type
6. **PropertyAccessor** — the property must have a setter

---

## Expression-Based Editability

The `editable` string is evaluated by the same `ExpressionLanguage` engine used
by `#[AdminAction(condition: ...)]`, so the syntax is identical.

Available variables:

| Variable | Type | Description |
|----------|------|-------------|
| `entity` | proxy | The entity row being evaluated. Property access via `entity.fieldName`. |
| `item` | proxy | Alias for `entity`. |
| `auth` | `AuthorizationCheckerInterface\|null` | For `is_granted()` inside expressions. |

```php
// Editable only while unlocked
#[AdminColumn(editable: 'entity.status != "locked"')]
private string $title = '';

// Editable only by editors — combines state + permission
#[AdminColumn(editable: 'entity.active && is_granted("ROLE_EDITOR")')]
private string $internalNotes = '';
```

---

## Permissions

Inline editing participates in the same voter-based security system as the rest
of the bundle. `ADMIN_EDIT` must be granted for the entity's short class name.

### Security Guarantee

The permission check **always runs before** any entity mutation. The save
lifecycle is enforced by the base `AbstractEditableField::save()` method in
this order:

1. `canEdit()` guard — throws `RuntimeException` on denial (**no entity write yet**)
2. `persistEdit()` — subclass writes the new value to the entity
3. Validation — `ValidatorInterface::validateProperty()` runs on the modified entity
4. If invalid → entity refreshed from DB (write discarded), error shown; **no flush**
5. If valid → `flush()` — database persisted

This template-method pattern means it is **impossible** for a subclass to skip
the permission check, regardless of how it overrides value-writing logic.

---

## Validation

Inline edits are validated using the standard **Symfony Validator** component.
Any `#[Assert\*]` constraint declared on the entity property is enforced
automatically before the value is flushed.

### How it works

After the user submits a field, the field component:

1. Writes the new value to the entity (in memory only)
2. Calls `ValidatorInterface::validateProperty($entity, $property)`
3. If violations exist:
   - Sets `errorMessage` to the first violation message
   - Refreshes the entity from the database (discards the in-memory write)
   - Returns early — **no flush, no data loss**
   - Keeps the component in edit mode so the user can correct the input
4. If clean: flushes and exits edit mode

### Adding constraints

Add standard Symfony validator attributes to your entity properties:

```php
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[Admin(label: 'Articles', enableInlineEdit: true)]
class Article
{
    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Title must not be blank.')]
    #[Assert\Length(max: 200, maxMessage: 'Title cannot exceed {{ limit }} characters.')]
    private string $title = '';

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private float $score = 0.0;
}
```

No bundle-specific configuration is required — constraints are read directly
from the entity class at runtime.

### What is validated

Only the **single property being saved** is validated
(`validateProperty($entity, $property)`), not the entire entity. This avoids
false positives from other required fields that the user has not touched.

### Null / nullable columns

`BoolField` coerces `null` to `false`. Nullable boolean columns (`?bool`) are
not separately supported — if you need three states, use an enum.

---

## Save Feedback

After a save attempt the field component exposes two LiveProps that templates
use to inform the user of the result:

| LiveProp | Type | Description |
|----------|------|-------------|
| `errorMessage` | `string` | Non-empty when the last save failed validation. Cleared on `activateEditing()` and `cancelEdit()`. |
| `saveSuccess` | `bool` | `true` after a successful flush. Reset to `false` on the next `activateEditing()`. |

### Template rendering

In **edit mode**, when `errorMessage` is non-empty:

- The input receives the `is-invalid` CSS class (Bootstrap error styling)
- The message appears in an `invalid-feedback` block beneath the input

In **display mode**, when `saveSuccess` is `true`:

- A `✓` indicator with class `inline-edit-saved` appears next to the displayed value
- The indicator disappears as soon as the user clicks ✎ to enter edit mode again

Both behaviours are built into the default field templates. No additional
configuration is required.

---

## Supported Field Types

| PHP / Doctrine type | Field component | Edit widget |
|---------------------|-----------------|-------------|
| `string` | `StringField` | `<input type="text">` |
| `integer` | `IntField` | `<input type="number" step="1">` |
| `float` / `decimal` | `FloatField` | `<input type="number" step="any">` |
| `boolean` | `BoolField` | `<input type="checkbox">` |
| `date` / `datetime` / `time` (mutable & immutable) | `DateField` | `<input type="date|datetime-local|time">` |
| Backed enum (`string\|int`) | `EnumField` | `<select>` with all cases |
| ManyToOne / OneToOne | `RelationshipField` | Search-as-you-type + select |
| OneToMany / ManyToMany | `CollectionField` | Multi-select with add/remove |

Fields **not** currently editable inline:

- Computed / virtual columns
- Columns without a setter
- JSON / array columns

---

## Examples

### Fully enabled entity

```php
#[ORM\Entity]
#[Admin(label: 'Articles', enableInlineEdit: true)]
class Article
{
    #[ORM\Column]
    #[Assert\NotBlank]
    private string $title = '';     // editable, validated

    #[ORM\Column]
    private bool $published = false; // editable

    // Read-only: immutable after creation
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
    #[Assert\Length(max: 500)]
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
    #[Assert\NotBlank]
    private string $deliveryAddress = '';

    // Never editable — immutable after creation
    #[ORM\Column]
    #[AdminColumn(editable: false)]
    private string $orderReference = '';
}
```

---

## `#[AdminColumn]` vs `#[ColumnPermission]`

Both attributes affect field access, but serve different purposes:

| | `#[AdminColumn(editable: ...)]` | `#[ColumnPermission]` |
|---|---|---|
| **Purpose** | UX opt-in/out for inline editing | Security ACL for column visibility |
| **Effect** | Shows or hides the ✎ edit trigger | Shows or hides the entire column |
| **Evaluated by** | Field component at render time | `EntityList` column building |
| **Expressions** | Yes — `entity.*`, `is_granted()` | No |
| **When to use** | Control which fields are editable | Control which columns are visible per role |
