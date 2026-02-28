# Inline Editing

The bundle includes per-field inline editing for Doctrine entity list views. Clicking the ✏️ button on a row opens it for editing; each cell then reveals a hover-triggered edit pencil. Saving or cancelling a field is immediate — no row-level save button is needed.

## How It Works

1. A ✏️ button appears in each row's action column (replacing the page-navigation Edit link).
2. Clicking ✏️ fires the `editRow` LiveAction on `EntityList`, setting `editingRowId`.
3. The row re-renders with a Field LiveComponent per cell instead of the read-only display.
4. Hovering a cell reveals the ✎ trigger. Clicking it switches that field into its edit input.
5. **Save** writes to the database immediately and returns to display mode.
6. **Cancel** discards unsaved changes (the entity is refreshed from the database).
7. The ✕ button in the action column clears `editingRowId`, closing the row.

Only one row can be open for editing at a time. Opening a second row closes the first (any unsaved input in open field editors is discarded).

## Requirements

- Entity must have an `getId(): int` method.
- User must be granted `ADMIN_EDIT` for the entity type (same voter check as the page-navigation Edit route).

## Field Type Detection

The field component is chosen automatically from the Doctrine column type:

| Doctrine type | Component |
|---|---|
| `string`, `text`, `uuid` | `K:Admin:Field:String` |
| `integer`, `bigint`, `smallint` | `K:Admin:Field:Int` |
| `float`, `decimal` | `K:Admin:Field:Float` |
| `boolean` | `K:Admin:Field:Bool` |
| `date`, `date_immutable` | `K:Admin:Field:Date` |
| `datetime`, `datetime_immutable`, `datetimetz`, `datetimetz_immutable` | `K:Admin:Field:Date` |
| `time`, `time_immutable` | `K:Admin:Field:Date` |
| Backed PHP enum | `K:Admin:Field:Enum` |
| ManyToOne / OneToOne | `K:Admin:Field:Relationship` |
| OneToMany / ManyToMany | `K:Admin:Field:Collection` |

Association fields include a live search input to find and select related entities.

## Controlling Per-Property Editability

Three mechanisms control whether a specific property's inline editor activates, checked in order:

### 1. `#[AdminColumn(editable: ...)]` — static flag or expression

Apply `#[AdminColumn]` to any entity property to override the default edit eligibility for that column.

**Always read-only** (computed / derived fields):

```php
use Kachnitel\AdminBundle\Attribute\AdminColumn;

class Invoice
{
    // Derived from line items — no setter, and explicitly marked non-editable
    #[AdminColumn(editable: false)]
    private float $totalAmount;
}
```

The ✎ trigger is hidden entirely. The voter and setter-writable checks are skipped.

**State-dependent editability** using an expression:

```php
use Kachnitel\AdminBundle\Attribute\AdminColumn;

class Order
{
    // Only editable while the order hasn't been shipped
    #[AdminColumn(editable: 'entity.status != "shipped"')]
    private string $shippingAddress;

    // Only HR staff can change salary, and only for active employees
    #[AdminColumn(editable: 'entity.active && is_granted("ROLE_HR")')]
    private float $salary;
}
```

Expressions use the same Symfony ExpressionLanguage syntax as `#[AdminAction(condition: ...)]`:

| Syntax | Description |
|---|---|
| `entity.status == "pending"` | Property comparison (calls `getStatus()`) |
| `entity.active` | Boolean property (calls `isActive()`) |
| `is_granted("ROLE_EDITOR")` | Role check for current user |
| `is_granted("ADMIN_EDIT", entity)` | Voter check with entity subject |
| `entity.isDraft() && is_granted("ROLE_EDITOR")` | Combined condition |

If an expression fails to evaluate (syntax error, missing property), the field is treated as **not editable** — the safe default.

### 2. ADMIN_EDIT voter

Even when the `#[AdminColumn]` expression passes, the user must still be granted `ADMIN_EDIT` for the entity type. This is the same voter that gates the entire inline-edit row trigger.

### 3. Setter writable

If the property has no public setter (or is declared `readonly`), `PropertyAccessor::isWritable()` returns `false` and the field is silently read-only. This works without any attribute and is the zero-config option for truly immutable properties:

```php
class Product
{
    // No setCreatedAt() → automatically not editable
    private \DateTimeImmutable $createdAt;

    // PHP 8.1 readonly → also automatically not editable
    public readonly string $sku;
}
```

## Role-Based Column Permissions

`#[ColumnPermission]` controls column *visibility* and can additionally restrict who can activate the inline editor for a column:

```php
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

class Employee
{
    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',           // hidden in list for non-HR
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_MANAGER',   // editable only by managers
    ])]
    private float $salary;
}
```

- `ADMIN_SHOW` — column is hidden from the list entirely for users without this role.
- `ADMIN_EDIT` — ✎ trigger is hidden for users without this role; they see the value but cannot edit it.

Use `#[ColumnPermission]` when access is purely role-based. Use `#[AdminColumn(editable: '...')]` when the condition depends on the entity's own state (status, workflow step, flags), or combines both role and state.

See [Column Visibility](COLUMN_VISIBILITY.md) for the full `#[ColumnPermission]` reference.

## CSS Theming

Both Bootstrap and Tailwind themes ship with macros for the inline-edit UI.

### Bootstrap

```twig
{# In your custom theme override: #}
{% macro field_editable_cell() %}d-inline-flex align-items-center gap-1 field-editable-cell{% endmacro %}
{% macro field_edit_trigger() %}btn btn-link p-0 text-body-tertiary lh-1 field-edit-trigger{% endmacro %}
```

The ✎ trigger is always visible by default. To reveal it only on hover, add two CSS rules to your project stylesheet:

```css
.field-edit-trigger { opacity: 0; transition: opacity .15s; }
.field-editable-cell:hover .field-edit-trigger,
.field-editable-cell:focus-within .field-edit-trigger { opacity: 1; }
```

### Tailwind

Tailwind's `group` / `group-hover` utilities handle hover-reveal without extra CSS. The default macros already apply `opacity-0 group-hover:opacity-100`.

## Disabling Inline Edit

### Per property

Mark any property as permanently non-editable with `#[AdminColumn(editable: false)]` — see [Controlling Per-Property Editability](#controlling-per-property-editability) above.

### Per entity

Inline edit is enabled for all Doctrine entities that grant `ADMIN_EDIT`. To restore the page-navigation Edit link for a specific entity entirely, use `#[AdminAction]` with `override: true`:

```php
use Kachnitel\AdminBundle\Attribute\AdminAction;

#[Admin(label: 'Orders')]
#[AdminAction(
    name: 'edit',
    label: 'Edit',
    icon: '🖊',
    route: 'app_order_edit',
    voterAttribute: 'ADMIN_EDIT',
    priority: 20,
    override: true,
)]
class Order { }
```

Setting `override: true` replaces the `InlineEditRowActionProvider`'s component action with a plain link action for this entity.

## Extending: Custom Field Components

Implement a custom field component for types not covered by the built-in set (e.g., a rich-text editor, a colour picker):

```php
use Kachnitel\AdminBundle\Twig\Components\Field\AbstractEditableField;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent('K:Admin:Field:Wysiwyg', template: '@App/admin/field/wysiwyg.html.twig')]
class WysiwygField extends AbstractEditableField
{
    use \Symfony\UX\LiveComponent\DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $currentValue = null;

    public function mount(object $entity, string $property): void
    {
        parent::mount($entity, $property);
        $raw = $this->readValue();
        $this->currentValue = $raw !== null ? (string) $raw : null;
    }

    #[LiveAction]
    public function cancelEdit(): void
    {
        $this->currentValue = null;
        parent::cancelEdit();
    }

    #[LiveAction]
    public function save(): void
    {
        $this->writeValue($this->currentValue);
        parent::save();
    }
}
```

Then register a `RowActionProviderInterface` (or use `AdminEntityDataRuntime::getFieldComponentName` override) to return `'K:Admin:Field:Wysiwyg'` for the relevant column.

Alternatively, for a single entity you can override the entire `EntityList.html.twig` template and call `{{ component('K:Admin:Field:Wysiwyg', { entity: entity, property: 'body' }) }}` directly in the editing row branch.

## Architecture Notes

- `EntityList::$editingRowId` (`#[LiveProp(writable: true)]`) is the single source of truth.
- Field components store entity identity as `$entityClass` (FQCN string) + `$entityId` (int) LiveProps — not the entity object — to survive LiveComponent JSON serialization.
- Each field re-fetches the entity from Doctrine on every re-render via `PostHydrate`.
- `cancelEdit()` calls `EntityManager::refresh()` to discard any pending changes before returning to display mode.
- Edit eligibility (`canEdit()`) checks `#[AdminColumn]` first, then the `ADMIN_EDIT` voter, then `PropertyAccessor::isWritable()`. Failing any layer hides the ✎ trigger and blocks the `save()` action.
- Association fields (Relationship, Collection) delegate label resolution to `AssociationFilterConfigTrait`, the same trait used by column filters.
