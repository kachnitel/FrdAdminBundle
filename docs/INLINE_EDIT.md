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

## Column-Level Edit Permissions

Use `#[ColumnPermission]` to make specific fields read-only for certain users even when inline editing is active:

```php
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

class Product
{
    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_USER',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_PRODUCT_PRICE_EDIT',
    ])]
    private float $price;
}
```

- `ADMIN_SHOW` controls whether the column is visible in the list at all.
- `ADMIN_EDIT` controls whether the inline field editor activates. Users without this role see the value but the ✎ trigger is hidden.

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

Inline edit is enabled for all Doctrine entities that grant `ADMIN_EDIT`. To restore the page-navigation Edit link for a specific entity, register a custom `RowActionProviderInterface` or use the `#[AdminAction]` attribute with `override: true`:

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
        parent::cancelEdit();
        $raw = $this->readValue();
        $this->currentValue = $raw !== null ? (string) $raw : null;
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
- Association fields (Relationship, Collection) delegate label resolution to `AssociationFilterConfigTrait`, the same trait used by column filters.
