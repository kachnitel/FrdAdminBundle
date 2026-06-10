# Auto-Generated Forms: Collection Support

`DynamicEntityFormType` automatically generates form fields for all Doctrine associations,
including collection-valued ones. No hand-written `FormType` is required.

## Association Types

| Doctrine type | Form field | UI |
|---|---|---|
| `ManyToOne` / `OneToOne` | `EntityType` | Dropdown |
| `ManyToMany` | `EntityType` with `multiple: true` | Multi-select |
| `OneToMany` | `LiveCollectionType` with recursive `DynamicEntityFormType` | Add/remove rows |

## Quick Example

```php
#[Admin(label: 'Orders')]
class Order
{
    #[ORM\Column]
    private string $reference = '';

    // ManyToMany → multi-select dropdown
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;

    // OneToMany → add/remove rows (requires cascade)
    #[ORM\OneToMany(
        targetEntity: OrderLine::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $lines;
}
```

Visit `/admin/order/new` — you get a full form with a tag multi-select and
an "Add line" / "Remove" interface, with no `FormType` written.

## Requirements

### cascade: ['persist', 'remove'] on OneToMany

`DynamicEntityFormType` calls `$em->persist($order)` and `$em->flush()` once.
Without cascade, newly added child entities are not tracked by Doctrine and
will throw `EntityNotFoundException` on flush.

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
from the collection. Without `orphanRemoval: true`, Doctrine will leave the child
row in the database (as an orphan). With it, the row is deleted automatically on flush.

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
├── tags               ← ManyToMany → EntityType(multiple)
└── lines              ← OneToMany  → LiveCollectionType
    └── OrderLine child form (is_root: false)
        ├── description  ← scalar
        ├── quantity     ← scalar
        └── order        ← ManyToOne → ⚠️ see below
```

## Inverse Side Hidden Automatically

`DynamicEntityFormType` detects inverse-side associations via Doctrine's `mappedBy`
metadata and skips them automatically. No `#[AdminColumn]` attribute is required.

This affects:
- **OneToMany** — always the inverse side (always has `mappedBy`)
- **OneToOne** inverse side — has `mappedBy`
- **ManyToMany** inverse side — has `mappedBy`

**ManyToOne is always the owning side** in Doctrine and is never skipped.

```php
class OrderLine
{
    // ✅ Skipped automatically — Doctrine sets mappedBy on this side
    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'lines')]
    private ?Order $order = null;
}
```

### Opting an Inverse Side Back In

If you genuinely need the inverse field in the form, use `#[AdminColumn(editable: true)]`:

```php
class UserProfile
{
    // Explicit opt-in — shows a User dropdown even though this is the inverse side
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'profile')]
    #[AdminColumn(editable: true)]
    private ?User $user = null;
}
```

### Using a Custom FormType for the Child

For full control over what the child form renders — including showing the parent
back-reference — create a hand-written `FormType` for the child entity:

```php
class OrderLineFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description')
            ->add('quantity')
            ->add('order', EntityType::class, ['class' => Order::class]); // explicit
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OrderLine::class]);
    }
}
```

`GenericAdminController` will use `OrderLineFormType` as the `entry_type` automatically
via the form resolution priority (see the [Forms guide](../docs/CONFIGURATION.md#forms)).

## Opting Out of a Collection

Collections are included by default. Use `#[AdminColumn(editable: false)]` to
exclude a specific collection from the form:

```php
class Product
{
    // This ManyToMany will appear as a multi-select
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;

    // This ManyToMany is excluded from the form
    #[ORM\ManyToMany(targetEntity: AuditEntry::class)]
    #[AdminColumn(editable: false)]
    private Collection $auditLog;
}
```

## Using a Hand-Written FormType for the Child

If the auto-generated child form is not sufficient (e.g. you need custom validation
or a different field layout), replace `DynamicEntityFormType` with your own `FormType`
by creating a hand-written `FormType` for the child entity. `GenericAdminController`
will use it automatically via the form resolution priority:

1. `#[Admin(formType: ...)]` explicit override
2. `App\Form\{ClassName}FormType` if registered
3. `DynamicEntityFormType` (fallback)

So creating `App\Form\OrderLineFormType` is enough — it will be used as the
`entry_type` of the `LiveCollectionType` automatically.

> **Note:** Hand-written form types for child entities must be registered as Symfony
> services (which happens automatically with `autowire: true` in `config/services.yaml`).

## Testing

Run the collection-specific tests:

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
- Inverse `ManyToOne` side hidden via `editable: false`
- ManyToMany persistence round-trip
- OneToMany new items persisted via cascade
- OneToMany removed items deleted via orphanRemoval
- Mixed update + add on existing OneToMany
- ManyToMany tags cleared on resubmit
