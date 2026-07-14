# Live Forms

The admin bundle uses Symfony UX LiveComponents for edit and new entity forms, providing real-time validation feedback as the user types — no full-page reloads.

The zero-config generation engine itself — `DynamicEntityFormType`, the Doctrine → Symfony type mapping, association handling — lives in [`kachnitel/dynamic-form-bundle`](https://github.com/kachnitel/dynamic-form-bundle), a standalone dependency with no knowledge of this bundle. This page covers how forms work in the admin UI and how this bundle plugs into that engine; see dynamic-form-bundle's own docs for the generation rules themselves.

> **License note:** `kachnitel/dynamic-form-bundle` is **MPL-2.0** — a file-level copyleft, not the same as this bundle's MIT license, but compatible with closed-source use. See its [README](https://github.com/kachnitel/dynamic-form-bundle#license) for what it actually requires.

## How it works

1. The controller renders `edit.html.twig` / `new.html.twig`, passing `entityClass`, `entityId`, `formTypeClass`, and `formComponentName` as template variables.
2. The template mounts the `K:Admin:EntityForm` LiveComponent.
3. As the user changes fields, LiveComponent sends Ajax requests, re-submits values into the Symfony form, and re-renders — showing validation errors inline.
4. The **Save** button in the page header calls a `save` `#[LiveAction]` on `document.querySelector('[data-admin-form]')`
5. On success, a `toast.show` browser event is dispatched. On failure the form re-renders with inline validation errors.

### Why the event is dispatched on the element, not `window`

LiveComponent's `#[LiveAction]` listeners are scoped to the component's root element. The header Save button is outside the component, so it can't dispatch to `this` (the component instance) directly. Instead it targets the component root via `[data-admin-form]` — a data attribute on the component root — and dispatches there directly.

Custom form components that extend `AdminEntityForm` must include this attribute on their root element too.

---

## Form type resolution

`GenericAdminController::getFormType()` follows this priority chain:

| Priority | Condition | Form type used |
|---|---|---|
| 1 | `#[Admin(formType: '...')]` on the entity | Custom form type class |
| 2 | Symfony FormType registered (conventional name `{Entity}FormType`) | Hand-written FormType |
| 3 | Everything else (any Doctrine entity) | `Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType` |

The form component is always `K:Admin:EntityForm` unless `#[Admin(formComponent: '...')]` overrides it.

---

## Enabling forms for an entity

### Option A — Symfony FormType (recommended when you need full control)

Add a form type following the `{EntityShortName}FormType` convention:

```php
// src/Form/ProductFormType.php
/** @extends AbstractType<Product> */
class ProductFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['empty_data' => '']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class]);
    }
}
```

> **`empty_data: ''`** — Always set this on `TextType` fields mapped to non-nullable string
> properties. Symfony normalises empty submissions to `null` before validation runs,
> which causes a `TypeError` when the value is written to the entity. `empty_data: ''`
> keeps it as an empty string so `NotBlank` can report the error properly.
>
> The auto-generated form handles this for you already — see dynamic-form-bundle's
> [Field Mapping](https://github.com/kachnitel/dynamic-form-bundle/blob/master/docs/FIELD_MAPPING.md#why-empty_data-is-always-)
> guide if you want the full reasoning (it applies more broadly than just strings).

The admin auto-discovers the form type. New and Edit links appear automatically.

Pin the form type explicitly if you use a non-conventional name:

```php
#[Admin(formType: ProductSpecialFormType::class)]
class Product {}
```

### Option B — DynamicEntityFormType (zero-config)

When no hand-written FormType exists, [`kachnitel/dynamic-form-bundle`](https://github.com/kachnitel/dynamic-form-bundle)
automatically generates one from Doctrine metadata. **No configuration required** —
the New and Edit buttons appear for every `#[Admin]` entity without any extra code.

#### Field inclusion rules

| Condition | Included |
|---|---|
| Regular Doctrine fields (string, int, float, bool, date, datetime, time, enum) | ✅ Yes |
| `#[AdminColumn(editable: false)]` on the property | ❌ Excluded |
| The primary identifier field (`id`) | ❌ Always excluded |
| JSON, array, blob, binary fields | ❌ Skipped (no sensible widget) |
| Single-valued associations (ManyToOne, OneToOne) | ✅ As `EntityType` select |
| `ManyToMany` associations | ✅ As multi-select `EntityType` |
| `OneToMany` associations | ✅ As `LiveCollectionType` (add/remove rows) |

Collection handling — `cascade`/`orphanRemoval` requirements, adder/remover methods,
recursion prevention — is covered in dynamic-form-bundle's own
[Associations](https://github.com/kachnitel/dynamic-form-bundle/blob/master/docs/ASSOCIATIONS.md)
guide, and in this bundle's [inline-add integration notes](DYNAMIC_FORM_COLLECTIONS.md).

#### Excluding a field

```php
#[Admin(label: 'Users', enableInlineEdit: true)]
class User
{
    #[ORM\Column]
    private string $email = '';          // included

    #[ORM\Column]
    #[AdminColumn(editable: false)]
    private string $passwordHash = '';   // excluded from the auto-form
}
```

`#[AdminColumn(editable: ...)]` is read by `AdminColumnEditabilityResolver` — this
bundle's implementation of dynamic-form-bundle's
[`FieldEditabilityResolverInterface`](https://github.com/kachnitel/dynamic-form-bundle/blob/master/docs/EDITABILITY.md),
the one extension point `DynamicEntityFormType` uses to decide field inclusion. It's
bound via a compiler pass (`OverrideEditabilityResolversPass`) rather than a plain
service alias, because dynamic-form-bundle ships its own permissive default alias for
that same interface — the compiler pass guarantees this bundle's resolver wins no
matter what order bundles are registered in.

**This resolver deliberately never reads `#[Admin(enableInlineEdit: ...)]`.** That flag
gates the separate list-view inline-edit feature (see [Inline Editing](INLINE_EDIT.md))
and has no bearing on the New/Edit form — a property with no `#[AdminColumn]` attribute
at all is included on the form regardless of whether `enableInlineEdit` is set anywhere.
To keep a field off the form specifically, use `#[AdminColumn(editable: false)]`;
`enableInlineEdit: false` (or omitting it) won't remove it there.

#### Doctrine → Symfony form type mapping

| Doctrine type | Symfony form type |
|---|---|
| `string`, `text` | `TextType` |
| `integer`, `smallint`, `bigint` | `IntegerType` |
| `decimal`, `float` | `NumberType` |
| `boolean` | `CheckboxType` |
| `date`, `date_immutable` | `DateType` (single_text widget) |
| `datetime`, `datetime_immutable`, `datetimetz`, `datetimetz_immutable` | `DateTimeType` (single_text widget) |
| `time`, `time_immutable` | `TimeType` (single_text widget) |
| Backed PHP enum (via `enumType` mapping) | `EnumType` |
| ManyToOne, OneToOne | `EntityType` with `autocomplete: true` |
| ManyToMany | `EntityType` with `multiple: true` |
| OneToMany | `LiveCollectionType` |

Nullability handling, `empty_data` rules, and required-field validation quirks are
dynamic-form-bundle's territory now — see its
[Field Mapping](https://github.com/kachnitel/dynamic-form-bundle/blob/master/docs/FIELD_MAPPING.md)
guide for the full detail, including why `empty_data` is always `''` and never `null`.

---

## Custom form components

For entities that need extra LiveActions (collection management, dependent fields, computed totals), create a component that **extends `AdminEntityForm`**.

### Step 1 — Create the component

```php
// src/Twig/Components/Form/PurchaseOrderForm.php
use Kachnitel\AdminBundle\Twig\Components\AdminEntityForm;

#[AsLiveComponent(name: 'App:Form:PurchaseOrder')]
final class PurchaseOrderForm extends AdminEntityForm
{
    // Override instantiateForm() only if you need custom form options.
    protected function instantiateForm(): FormInterface
    {
        $entity = $this->entityId !== null
            ? $this->em->find(PurchaseOrderEntity::class, $this->entityId)
            : new PurchaseOrderEntity();

        return $this->createForm(PurchaseOrderFormType::class, $entity);
    }

    #[LiveAction]
    public function addLineItem(): void
    {
        $this->formValues['lineItems'][] = [];
    }
}
```

The component's root template **must** include `data-admin-form` so the header Save button can reach it:

```twig
{# templates/components/Form/PurchaseOrderForm.html.twig #}
<div data-admin-form {{ attributes }}>
  {{ form_start(form) }}
    {{ form_widget(form) }}
  {{ form_end(form) }}
</div>
```

### Step 2 — Register on the entity

```php
use Kachnitel\AdminBundle\Attribute\Admin;

#[Admin(
    formType: PurchaseOrderFormType::class,
    formComponent: 'App:Form:PurchaseOrder',
)]
class PurchaseOrder {}
```

The admin will now mount your component instead of the default one for this entity's edit and new pages.

### What you inherit for free

| Feature | Inherited from `AdminEntityForm` |
|---------|----------------------------------|
| Real-time validation | ✓ |
| Save on header button click | ✓ (`data-admin-form` + `#[LiveListener]`) |
| Toast on success | ✓ |
| New → edit transition (entityId set after first save) | ✓ |
| CSRF handled by LiveComponent | ✓ |

### What you can override

| Method/prop | Purpose |
|-------------|---------|
| `instantiateForm()` | Custom form options, e.g. `action` URL or `DynamicEntityFormType` |
| `save()` | Full save lifecycle (call `parent::save()` to keep the standard flow, or replace entirely) |
| Component template | Custom field layout, computed values |

---

## Form themes

The component renders each field individually using `form_label`, `form_widget`, and
`form_errors`. Standard Symfony form themes apply — see the
[Symfony form themes documentation](https://symfony.com/doc/current/form/form_themes.html).

Global theme via `twig.yaml`:

```yaml
twig:
    form_themes: ['bootstrap_5_layout.html.twig']
```

Per-component theme in your template override:

```twig
{% form_theme form 'my_custom_form_theme.html.twig' %}
<div data-admin-form {{ attributes }}>
  {{ form_start(form) }}
    {# ... #}
  {{ form_end(form) }}
</div>
```

---

## After-save behaviour

On a successful save the component dispatches `toast.show` with `message: 'Saved successfully!'`. Wire this to your toast library in your base layout:

```javascript
window.addEventListener('toast.show', (event) => {
    console.log(event.detail.message);
});
```

The page stays open. For new entities the component records the new ID internally so subsequent saves update rather than insert.

---

## Running feature tests

```bash
# LiveComponent integration: AdminEntityForm, InlineEntityForm, AdminColumnEditabilityResolver
php vendor/bin/phpunit --group admin-entity-form

# This bundle's own regression coverage of what it expects from dynamic-form-bundle
# (field mapping, collections, the inline-add attribute contract)
php vendor/bin/phpunit --group auto-form,dynamic-form,collections,inline-add
```

The generation engine's primary test suite lives in
[`kachnitel/dynamic-form-bundle`](https://github.com/kachnitel/dynamic-form-bundle) itself.
