# Auto-Generated Forms: Collection Support

`DynamicEntityFormType` automatically generates form fields for all Doctrine associations,
including collection-valued ones. No hand-written `FormType` is required.

See [Inline Add](INLINE_ADD.md) for details on the inline-add button and dialog.

## Association Types

| Doctrine type | Form field | UI |
|---|---|---|
| `ManyToOne` / `OneToOne` (owning) | `EntityType` | Autocomplete dropdown |
| `ManyToMany` (owning side) | `EntityType` with `multiple: true` | Autocomplete multi-select |
| `OneToMany` | `LiveCollectionType` with recursive `DynamicEntityFormType` | Add / remove rows |

## Quick Example

```php
#[Admin(label: 'Orders')]
class Order
{
    #[ORM\Column]
    private string $reference = '';

    // ManyToMany → multi-select autocomplete (no extra code needed)
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;

    // OneToMany → add/remove rows (requires cascade + adder/remover methods — see below)
    #[ORM\OneToMany(
        targetEntity: OrderLine::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $lines;

    public function addLine(OrderLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setOrder($this);
        }
        return $this;
    }

    public function removeLine(OrderLine $line): self
    {
        if ($this->lines->removeElement($line)) {
            if ($line->getOrder() === $this) {
                $line->setOrder(null);
            }
        }
        return $this;
    }
}
```

Visit `/admin/order/new` — you get a full form with a tag multi-select and
an "Add line" / "Remove" interface, with no `FormType` written.

---

## Requirements

### cascade: ['persist', 'remove'] on OneToMany

`DynamicEntityFormType` calls `$em->persist($order)` and `$em->flush()` once.
Without cascade, newly added child entities are not tracked by Doctrine and
will throw an error on flush.

```php
// ✅ Required
#[ORM\OneToMany(
    targetEntity: OrderLine::class,
    mappedBy: 'order',
    cascade: ['persist', 'remove'],  // ← required
    orphanRemoval: true,             // ← required for remove to work
)]
private Collection $lines;

// ❌ Will fail when adding new children
#[ORM\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order')]
private Collection $lines;
```

### orphanRemoval: true for deletable children

When a user removes a row from the `LiveCollectionType` UI, the item is removed
from the PHP collection. Without `orphanRemoval: true`, Doctrine will leave the child
row in the database. With it, the row is deleted automatically on flush.

### Adder and Remover Methods (required for OneToMany)

`DynamicEntityFormType` passes `by_reference: false` to `LiveCollectionType`. This tells
Symfony Form to call individual **adder** and **remover** methods on the entity instead
of replacing the entire collection.

Symfony derives method names from the field name by singularising it:

| Field name | Expected adder | Expected remover |
|---|---|---|
| `lines` | `addLine()` | `removeLine()` |
| `lineItems` | `addLineItem()` | `removeLineItem()` |
| `tags` | `addTag()` | `removeTag()` |
| `orderLines` | `addOrderLine()` | `removeOrderLine()` |

**If these methods do not exist, Symfony Form will throw a `LogicException` at submit time.**

The adder must also set the child's back-reference (the `mappedBy` field on the child),
and the remover must clear it. Without this, Doctrine persists the child without the FK
value and the relationship is silently lost:

```php
public function addLine(OrderLine $line): self
{
    if (!$this->lines->contains($line)) {
        $this->lines[] = $line;
        $line->setOrder($this);  // ← sync back-reference
    }
    return $this;
}

public function removeLine(OrderLine $line): self
{
    if ($this->lines->removeElement($line)) {
        if ($line->getOrder() === $this) {
            $line->setOrder(null);  // ← break back-reference for orphanRemoval
        }
    }
    return $this;
}
```

---

## Preventing Infinite Recursion

`DynamicEntityFormType` uses an `is_root` option (default `true`) to prevent
infinite recursion in bidirectional relationships.

- **Root form** (`is_root: true`): includes all field types including collections
- **Child form** (`is_root: false`): skips collection associations entirely

`LiveCollectionType` passes `is_root: false` in `entry_options` automatically
when `DynamicEntityFormType` is the `entry_type`. You do not need to set this manually.

```
Order form (is_root: true)
├── reference          ← scalar
├── tags               ← ManyToMany → EntityType(multiple, autocomplete)
└── lines              ← OneToMany  → LiveCollectionType
    └── OrderLine child form (is_root: false)
        ├── description  ← scalar
        ├── quantity     ← scalar
        └── order        ← skipped automatically (back-reference — see below)
```

---

## Association Auto-Skip Rules

`DynamicEntityFormType` applies two independent rules to avoid exposing redundant
or confusing controls in generated forms.

### Rule 1 — Skip inverse-side associations (`mappedBy` set)

| Association | Has `mappedBy`? | Skipped in form? | Rationale |
|---|---|---|---|
| **OneToMany** | ✅ always | ❌ **Kept** | Managing child rows IS the purpose of the parent form |
| **OneToOne** inverse | ✅ yes | ✅ Skipped | Managed by the owning side |
| **ManyToMany** inverse | ✅ yes | ✅ Skipped | Managed by the owning side |

**OneToMany is kept despite having `mappedBy`** because the parent form is precisely the
place to add and remove child rows.

### Rule 2 — Skip parent back-references (`inversedBy` set on single-valued association)

A single-valued association with `inversedBy` declares this entity as the FK owner of a
relationship whose other end is a collection. Showing a "parent" selector inside a child
form creates confusing UX and may conflict with how the parent form manages the relationship:

| Association | Has `inversedBy`? | Skipped in form? |
|---|---|---|
| **ManyToOne** without `inversedBy` | ❌ no | ❌ Kept — standalone relationship |
| **ManyToOne** with `inversedBy` | ✅ yes | ✅ Skipped — back-reference to parent's OneToMany |
| **OneToOne** owning with `inversedBy` | ✅ yes | ✅ Skipped — back-reference to parent |

```php
class OrderLine
{
    // ✅ Skipped automatically — inversedBy signals this is a parent back-reference.
    // The parent Order form owns and manages this relationship; showing an Order
    // dropdown inside the OrderLine child form would be redundant and confusing.
    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'lines')]
    private ?Order $order = null;
}
```

### Opting a Skipped Association Back In

Use `#[AdminColumn(editable: true)]` to include a field that would otherwise be skipped:

```php
class UserProfile
{
    // Explicit opt-in — shows a User autocomplete even though
    // the inversedBy would cause it to be skipped automatically.
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'profile')]
    #[AdminColumn(editable: true)]
    private ?User $user = null;
}
```

---

## Opting Out of a Collection

Collections are included by default. Use `#[AdminColumn(editable: false)]` to
exclude a specific collection from the form:

```php
class Product
{
    // Included — ManyToMany appears as a multi-select autocomplete
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;

    // Excluded — too large / internal use only
    #[ORM\ManyToMany(targetEntity: AuditEntry::class)]
    #[AdminColumn(editable: false)]
    private Collection $auditLog;
}
```

---

## Using a Hand-Written FormType for the Child

For full control over a child entity's form — including showing back-references or
applying custom validation — create a hand-written `FormType` for the child entity.
`GenericAdminController` will use it automatically via the form resolution priority:

1. `#[Admin(formType: ...)]` explicit override on the child entity
2. `App\Form\{ChildClassName}FormType` if registered as a service
3. `DynamicEntityFormType` (fallback)

So creating `App\Form\OrderLineFormType` is enough — it will be picked up as the
`entry_type` of the `LiveCollectionType` automatically. No extra wiring needed.

```php
class OrderLineFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description')
            ->add('quantity')
            // Explicit — DynamicEntityFormType would skip this automatically:
            ->add('order', EntityType::class, ['class' => Order::class]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OrderLine::class]);
    }
}
```

> **Note:** Child `FormType` classes must be registered as Symfony services
> (`autowire: true` in `config/services.yaml`), which happens automatically
> with standard Symfony configuration.

---

## Troubleshooting

### Items appear doubled / duplicated after saving or adding

**Cause 1 (most common): adder method does not sync the back-reference.**

If `addLine()` adds `$line` to the collection but does not call `$line->setOrder($this)`,
Doctrine persists the child row without the FK value. On the next page load, that child
cannot be fetched as part of `$order->getLines()`, but a new copy may be persisted on each
save — producing an ever-growing list of orphaned rows.

**Fix:** Ensure the adder calls `$child->setParent($this)` as shown in the requirements above.

**Cause 2: missing `cascade: ['persist']`.**

If cascade does not include `persist`, newly created child objects exist in the PHP
collection but are not managed by Doctrine. On flush, they are ignored. If your code
then persists them separately (e.g., from a previous `persist` call still cached in the
identity map), you may see doubles.

**Cause 3: missing `orphanRemoval: true`.**

If a user removes a row and `orphanRemoval` is not set, Doctrine detaches the child
from the collection but leaves the database row. The row reappears on the next load,
making it look as though the removal had no effect.

**Cause 4: LiveComponent DOM morphing without stable IDs.**

When LiveComponent re-renders after `addCollectionItem`, it morphs the existing DOM
into the new HTML using element `id` attributes as anchors. The compact form theme adds
`id="{{ form.vars.id }}"` to each compound entry, which provides these anchors.

If you override the form theme and lose these `id` attributes, morphing may create
new elements instead of updating existing ones — resulting in visual doubling that
disappears on a hard page refresh. Inspect the rendered HTML: each collection entry
`<div>` must have a unique `id` like `order_lines_0`, `order_lines_1`, etc.

### Remove button has no effect

1. Check `orphanRemoval: true` on the `OneToMany` mapping.
2. Check that the remover method calls `$child->setParent(null)` to break the back-reference.
   Without this, the child's FK column retains the parent ID and Doctrine won't treat it as
   an orphan even with `orphanRemoval: true`.

### "LogicException: Unable to set value of the collection" at submit

Symfony Form cannot find the adder/remover methods. Double-check the method names match
Symfony's singularisation of the field name:

- Field `lines` → `addLine()` / `removeLine()`
- Field `lineItems` → `addLineItem()` / `removeLineItem()`

Symfony's singulariser handles common English patterns. For unusual field names (e.g.
`children` → `addChild` not `addChildren`), check Symfony's `StringUtil::singularify()`
or use a hand-written `FormType` where you can configure collection handling explicitly.

### New children are lost after flush

Check that `cascade: ['persist']` is set on the `OneToMany`. Without it, calling
`$em->persist($parent); $em->flush()` ignores unmanaged child objects.

---

## Testing

```bash
# Unit tests (fast, mocked Doctrine)
composer phpunit -- --group=collections

# Functional tests (real kernel, real Doctrine, real FormFactory)
composer phpunit -- --group=functional

# All dynamic-form tests
composer phpunit -- --group=dynamic-form
```

The functional tests cover:
- Form creation without errors for entities with `OneToMany` and `ManyToMany`
- Child form skips collections (`is_root: false`)
- Correct form types resolved (`LiveCollectionType`, `EntityType`)
- `#[AdminColumn(editable: false)]` respected for collections
- Inverse `ManyToOne` side hidden via `inversedBy` detection
- ManyToMany persistence round-trip
- OneToMany new items persisted via cascade
- OneToMany removed items deleted via orphanRemoval
- Mixed update + add on existing OneToMany
- ManyToMany tags cleared on resubmit
