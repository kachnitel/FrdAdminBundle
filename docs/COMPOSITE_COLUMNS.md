# Composite Columns (Grouped Columns)

Group multiple entity properties into a single stacked table cell using
`#[AdminColumn(group: '...')]`.

## Overview

Composite columns let you visually merge related properties into one `<th>`/`<td>`,
reducing table width while keeping all data accessible. The grouping is purely a
rendering concern — no backend or query changes are required.

```php
#[ORM\Entity]
#[Admin(label: 'Contacts')]
class Contact
{
    #[ORM\Column]
    #[AdminColumn(group: 'name')]
    private string $firstName = '';

    #[ORM\Column]
    #[AdminColumn(group: 'name')]
    private string $lastName = '';

    #[ORM\Column]
    private string $email = '';   // ungrouped — renders as a regular column
}
```

Result: a single **Name** column header containing `firstName` and `lastName`
stacked vertically, followed by a regular **Email** column.

---

## How It Works

1. `#[AdminColumn(group: 'identifier')]` is placed on each property to include
   in the group.
2. The `DoctrineColumnAttributeProvider` service reads these attributes on the
   entity class and populates the `group` field of each `ColumnMetadata`.
3. `DoctrineDataSource::getColumnGroups()` returns an ordered list of
   `string|ColumnGroup` *slots*:
   - **`string`** — a plain column name; rendered as a normal `<th>`/`<td>`.
   - **`ColumnGroup`** — a value object carrying `id`, `label`, and an ordered
     `ColumnMetadata[]` array; rendered as a composite stacked cell.
4. The `EntityList` component exposes `getColumnSlots()`, which applies column
   visibility filtering to the raw slot list.
5. `EntityList.html.twig` iterates slots via `this.columnSlots` and dispatches
   to `_CompositeHeader.html.twig` / `_CompositeCell.html.twig` for group slots.

---

## Group Label

The group label is derived by humanising the identifier string:

| Identifier    | Label          |
|---------------|----------------|
| `name_block`  | `Name block`   |
| `nameBlock`   | `Name block`   |
| `contactInfo` | `Contact info` |
| `addr`        | `Addr`         |

There is currently no `label:` override on `#[AdminColumn(group:)]` — use a
descriptive identifier if the humanised form is not suitable.

---

## Column Order

Members of a group appear in the composite cell in the **same order they appear
in the entity class** (or in `#[Admin(columns: [...])]` if an explicit list is set).

Groups appear in the overall table at the position of their **first member**.
Non-contiguous group members (with ungrouped columns in between) are merged into
the group at the first-member position:

```php
// firstName → name group starts here
// email     → ungrouped → stays between groups
// lastName  → appended to name group (NOT at its position)

// Rendered slot order:  [ColumnGroup{firstName, lastName}]  [email]
```

---

## Column Visibility Compatibility

Individual group members participate in column visibility just like regular
columns. If all members of a group are hidden, the group slot itself disappears
from the table. If only some members are hidden, the group cell renders with the
remaining visible sub-columns.

---

## Inline Editing Compatibility

Composite cells fully support inline editing. When a row is in edit mode, each
sub-column in the group renders its `Field` LiveComponent (or the read-only
fallback) independently, using the same logic as a normal single-column cell.

---

## Custom Data Sources

`DataSourceInterface` includes `getColumnGroups()`. Implementations that do not
need grouping should include `FlatColumnGroupsTrait` to satisfy the contract:

```php
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\FlatColumnGroupsTrait;

class MyDataSource implements DataSourceInterface
{
    use FlatColumnGroupsTrait;  // provides default getColumnGroups()

    // ... rest of implementation
}
```

Custom data sources can also return `ColumnGroup` instances manually:

```php
use Kachnitel\AdminBundle\DataSource\ColumnGroup;
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;

public function getColumnGroups(): array
{
    return [
        'id',
        new ColumnGroup(
            id: 'name',
            label: 'Name',
            columns: [
                'firstName' => ColumnMetadata::create('firstName', 'First Name'),
                'lastName'  => ColumnMetadata::create('lastName', 'Last Name'),
            ],
        ),
        'email',
    ];
}
```

---

## API Reference

### `#[AdminColumn(group: '...')]`

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
class AdminColumn
{
    public function __construct(
        string|bool|null $editable = null,
        ?string $group = null,            // composite group identifier
    ) {}
}
```

### `ColumnGroup`

```php
readonly class ColumnGroup
{
    public function __construct(
        public string $id,                            // e.g. 'name_block'
        public string $label,                         // e.g. 'Name block'
        public array $columns,                        // array<string, ColumnMetadata>
    ) {}
}
```

### `DataSourceInterface::getColumnGroups()`

```php
/** @return list<string|ColumnGroup> */
public function getColumnGroups(): array;
```

### `EntityList::getColumnSlots()`

```php
/** @return list<string|ColumnGroup> */
public function getColumnSlots(): array;
```

Returns the visibility-filtered slot list used by the template.

### `EntityList::isColumnGroup(string|ColumnGroup $slot): bool`

Helper used in Twig to distinguish group slots from plain column names.
