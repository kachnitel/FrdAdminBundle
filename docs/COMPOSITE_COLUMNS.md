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

## Display Options — `#[AdminColumnGroup]`

Fine-tune how a group looks using the `#[AdminColumnGroup]` attribute on the
**entity class**. The `id` must match the group identifier used in
`#[AdminColumn(group:)]`.

```php
use Kachnitel\AdminBundle\Attribute\AdminColumnGroup;
use Kachnitel\DataSourceContracts\ColumnGroup;

#[Admin(label: 'Orders')]
#[AdminColumnGroup(
    id: 'delivery',
    subLabels: ColumnGroup::SUB_LABELS_ICON,
    header: ColumnGroup::HEADER_COLLAPSIBLE,
)]
class Order { ... }
```

### `header` — Composite column `<th>` style

Three practical modes control what the group header cell renders:

| Constant                          | Behaviour                                                                                    | Default |
|-----------------------------------|----------------------------------------------------------------------------------------------|---------|
| `ColumnGroup::HEADER_TEXT`        | Renders only the humanized group label as plain text — like a regular column header          | ✔       |
| `ColumnGroup::HEADER_COLLAPSIBLE` | Group label inside a native `<details>`/`<summary>` toggle; per-sub-column sort and filter rows are hidden by default and revealed on click. No JavaScript required. | |
| `ColumnGroup::HEADER_FULL`        | Group label strip always visible plus all per-sub-column sort and filter rows always expanded | |

**Choosing a mode:**

- `HEADER_TEXT` is the least cluttered option and the sensible default for most tables. Sort and filter access is still available per-column through the global filter panel.
- `HEADER_COLLAPSIBLE` is the sweet spot for tables where the group's filters are used occasionally — users can open them on demand without permanently widening the header.
- `HEADER_FULL` suits power-user interfaces where column-level sorting and filtering within the group needs to be immediately accessible.

### `subLabels` — Sub-column labels in body cells

Controls how each sub-column's label is displayed next to its value inside the
composite `<td>`.

| Constant                         | Behaviour                                         | Default |
|----------------------------------|---------------------------------------------------|---------|
| `ColumnGroup::SUB_LABELS_SHOW`   | Text label before each value                      | ✔       |
| `ColumnGroup::SUB_LABELS_ICON`   | Small ℹ icon with the label in a `title` tooltip  |         |
| `ColumnGroup::SUB_LABELS_HIDDEN` | No label rendered — values only                   |         |

### Full example

```php
#[Admin(label: 'Orders')]
#[AdminColumnGroup(
    id: 'delivery',
    subLabels: ColumnGroup::SUB_LABELS_ICON,   // compact labels in cells
    header: ColumnGroup::HEADER_COLLAPSIBLE,    // filters on demand
)]
#[AdminColumnGroup(
    id: 'dates',
    header: ColumnGroup::HEADER_FULL,           // date filters always visible
)]
class Order
{
    #[AdminColumn(group: 'delivery')]
    private ?FulfillmentMethod $fulfillmentMethod = null;

    #[AdminColumn(group: 'delivery')]
    private ?Region $region = null;

    #[AdminColumn(group: 'dates')]
    private ?\DateTimeImmutable $shipmentDate = null;

    #[AdminColumn(group: 'dates')]
    private ?\DateTimeImmutable $targetDate = null;
}
```

---

## How It Works

1. `#[AdminColumn(group: 'identifier')]` is placed on each property to include in the group.
2. Optional `#[AdminColumnGroup]` on the entity class configures per-group display options.
3. `DoctrineColumnAttributeProvider` reads both attribute types.
4. `DoctrineDataSource::getColumnGroups()` returns an ordered list of `string|ColumnGroup` slots.
5. `EntityList.html.twig` dispatches to `_CompositeHeader.html.twig` / `_CompositeCell.html.twig` for group slots.

### Header row alignment

The composite `<th>` is rendered with `rowspan="2"` so it spans both the label row and the
filter row in `<thead>`. The filter row correctly skips composite slots — column counts stay
aligned regardless of `header` mode.

---

## Group Label

The group label is derived by humanising the identifier string:

| Identifier    | Label          |
|---------------|----------------|
| `name_block`  | `Name block`   |
| `nameBlock`   | `Name block`   |
| `contactInfo` | `Contact info` |
| `addr`        | `Addr`         |

---

## Column Order

Members of a group appear in the composite cell in the **same order they appear in the entity
class** (or in `#[Admin(columns: [...])]` if an explicit list is set).

Groups appear in the overall table at the position of their **first member**. Non-contiguous
group members are merged into the group at the first-member position.

---

## Column Visibility, Inline Editing

See earlier in this file — these sections are unaffected by header/subLabels configuration.

## Custom Data Sources

`DataSourceInterface` includes `getColumnGroups()`. Implementations that do not
need grouping should use `FlatColumnGroupsTrait` to satisfy the contract:

```php
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\DataSourceContracts\FlatColumnGroupsTrait;

class MyDataSource implements DataSourceInterface
{
    use FlatColumnGroupsTrait;  // provides default getColumnGroups()

    // ... rest of implementation
}
```

Custom data sources can also return `ColumnGroup` instances manually, including
display options:

```php
use Kachnitel\DataSourceContracts\ColumnGroup;
use Kachnitel\DataSourceContracts\ColumnMetadata;

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
            subLabels: ColumnGroup::SUB_LABELS_ICON,
            header: ColumnGroup::HEADER_FULL,
        ),
        'email',
    ];
}
```

---

## API Reference

### `#[AdminColumnGroup]`

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AdminColumnGroup
{
    public function __construct(
        public readonly string $id,
        public readonly string $subLabels = ColumnGroup::SUB_LABELS_SHOW,
        public readonly string $header    = ColumnGroup::HEADER_TEXT,
    ) {}
}
```

### `ColumnGroup`

```php
readonly class ColumnGroup
{
    // subLabels
    public const SUB_LABELS_SHOW   = 'show';
    public const SUB_LABELS_ICON   = 'icon';
    public const SUB_LABELS_HIDDEN = 'hidden';

    // header
    public const HEADER_TEXT        = 'text';        // default
    public const HEADER_COLLAPSIBLE = 'collapsible';
    public const HEADER_FULL        = 'full';

    public function __construct(
        public string $id,
        public string $label,
        public array  $columns,    // array<string, ColumnMetadata>
        public string $subLabels = self::SUB_LABELS_SHOW,
        public string $header    = self::HEADER_TEXT,
    ) {}
}
```
