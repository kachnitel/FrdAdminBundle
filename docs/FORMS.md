# Live Forms

The admin bundle uses Symfony UX LiveComponents for edit and new entity forms, providing real-time validation feedback as the user types — no full-page reloads.

## How it works

1. The controller renders `edit.html.twig` / `new.html.twig`, passing `entityClass`, `entityId`, `formTypeClass`, and `formComponentName` as template variables.
2. The template mounts the LiveComponent via `{{ component(formComponentName, ...) }}`.
3. As the user changes fields, LiveComponent sends Ajax requests, re-submits values into the Symfony form, and re-renders — showing validation errors inline.
4. The **Save** button in the page header calls a `save` `#[LiveAction]` on `document.querySelector('[data-admin-form]')`
5. On success, a `toast.show` browser event is dispatched. On failure the form re-renders with inline validation errors.

### Why the event is dispatched on the element, not `window`

LiveComponent's `#[LiveAction]` listeners are scoped to the component's root element. The header Save button is outside the component, so it can't dispatch to `this` (the component instance) directly. Instead it targets the component root via `[data-admin-form]` — a data attribute on the component root — and dispatches there directly.

Custom form components that extend `AdminEntityForm` must include this attribute on their root element too.

---

## Form component resolution

`GenericAdminController::getFormComponentName()` follows this priority chain:

| Priority | Condition | Component used |
|---|---|---|
| 1 | `#[Admin(formComponent: '...')]` on the entity | Custom component |
| 2 | Symfony FormType registered (conventional name or `#[Admin(formType:)]`) | `K:Admin:EntityForm` |
| 3 | Entity has `enableInlineEdit: true` OR any `#[AdminColumn(editable: true)]` | `K:Admin:AutoEntityForm` |
| 4 | Fallback | `K:Admin:EntityForm` (may render empty) |

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

The admin auto-discovers the form type. New and Edit links appear when a matching form type exists.

Pin the form type explicitly if you use a non-conventional name:

```php
#[Admin(formType: ProductSpecialFormType::class)]
class Product {}
```

### Option B — Auto-form (zero-config)

When no FormType exists, the bundle generates a form automatically using the same
field components as inline editing. Enable it by opting your entity into inline editing:

```php
// All writable properties become form fields
#[Admin(label: 'Products', enableInlineEdit: true)]
class Product
{
    #[ORM\Column]
    private string $name = '';

    #[ORM\Column]
    private float $price = 0.0;
}
```

Or opt in per-column without enabling inline editing globally:

```php
#[Admin(label: 'Products')]
class Product
{
    #[ORM\Column]
    #[AdminColumn(editable: true)]
    private string $name = '';

    #[ORM\Column]
    #[AdminColumn(editable: false)]   // never editable, even in the form
    private string $internalCode = '';
}
```

#### How the auto-form works

**Edit mode** (`entityId` provided) — renders each editable property as its
`K:Entity:Field:*` LiveComponent in `formMode=true`. The header Save button
emits `form:save` down to all field children, each of which validates and flushes
independently. The parent tracks responses and shows a success banner when all
complete.

**New mode** (`entityId` is null) — cannot use Field LiveComponents because they
require an existing entity ID. Instead renders plain HTML inputs synced to a
`formValues` LiveProp. The parent's `save()` coerces all raw values to proper PHP
types via `DoctrineValueCoercer`, validates the whole entity, persists once, then
switches to edit mode by setting `entityId` — consistent with `AdminEntityForm`
behaviour.

#### Auto-form field type mapping

| Doctrine type | New-mode input | Edit-mode component |
|---|---|---|
| `string`, `text` | `<input type="text">` | `K:Entity:Field:String` |
| `integer`, `smallint`, `bigint` | `<input type="number" step="1">` | `K:Entity:Field:Int` |
| `decimal`, `float` | `<input type="number" step="any">` | `K:Entity:Field:Float` |
| `boolean` | `<input type="checkbox">` | `K:Entity:Field:Bool` |
| `date`, `date_immutable` | `<input type="date">` | `K:Entity:Field:Date` |
| `datetime`, `datetime_immutable`, `datetimetz`, `datetimetz_immutable` | `<input type="datetime-local">` | `K:Entity:Field:Date` |
| `time`, `time_immutable` | `<input type="time">` | `K:Entity:Field:Date` |
| Backed enum | — (requires FormType) | `K:Entity:Field:Enum` |
| ManyToOne / OneToOne | `<input type="number">` (ID) | `K:Entity:Field:Relationship` |

> **Relations in new mode** render as a plain integer input for the related entity ID.
> For a typeahead search widget on new, add a manual `FormType` with `EntityType`.

#### Limitations

- JSON, array, and other complex Doctrine types are excluded — no field component exists for them.
- Entity-level cross-field validation is not performed in new mode; add a FormType for that.
- Relations in new mode accept raw integer IDs only (no search widget).

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
| `instantiateForm()` | Custom form options, e.g. `action` URL |
| `save()` | Full save lifecycle (call `parent::save()` to keep the standard flow, or replace entirely) |
| Component template | Custom field layout, computed values |

---

## Form themes

The component uses `form_widget(form)` by default. Standard Symfony form themes apply — see the [Symfony form themes documentation](https://symfony.com/doc/current/form/form_themes.html).

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
    {{ form_widget(form) }}
  {{ form_end(form) }}
</div>
```

---

## After-save behaviour

On a successful save the component dispatches `toast.show` with `message: 'Saved successfully!'` (or `'Created successfully!'` for new entities). Wire this to your toast library in your base layout:

```javascript
window.addEventListener('toast.show', (event) => {
    // your toast implementation
    console.log(event.detail.message);
});
```

The page stays open. For new entities the component records the new ID internally so subsequent saves update rather than insert.

---

## Running feature tests

```bash
php vendor/bin/phpunit --group auto-form,admin-entity-form
```
