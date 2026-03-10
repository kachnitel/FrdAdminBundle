# Live Forms

The admin bundle uses Symfony UX LiveComponents for edit and new entity forms, providing real-time validation feedback as the user types — no full-page reloads.

## How it works

1. The controller renders `edit.html.twig` / `new.html.twig`, passing `entityClass`, `entityId`, `formTypeClass`, and `formComponentName` as template variables.
2. The template mounts the LiveComponent via `{{ component(formComponentName, ...) }}`.
3. As the user changes fields, LiveComponent sends Ajax requests, re-submits values into the Symfony form, and re-renders — showing validation errors inline.
4. The **Save** button in the page header dispatches a `CustomEvent('admin:save')` directly on the component's root element (`[data-admin-form]`). The component listens via `#[LiveListener('admin:save')]`.
5. On success, a `toast.show` browser event is dispatched. On failure the form re-renders with inline errors.

### Why the event is dispatched on the element, not `window`

LiveComponent's `#[LiveListener]` receives events dispatched on the component's root DOM element or that bubble **up** to it. DOM events bubble upward (child → parent → document → window), never downward. A `window.dispatchEvent(...)` call cannot reach a component element, no matter how many `bubbles: true` flags you add. The button therefore queries `[data-admin-form]` — a data attribute on the component root — and dispatches there directly.

Custom form components must include `data-admin-form` on their root element to receive the save event.

---

## Enabling forms for an entity

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

The admin auto-discovers the form type. Edit/New links only appear when a matching form type exists.

Pin the form type explicitly if you use a non-conventional name:

```php
#[Admin(formType: ProductSpecialFormType::class)]
class Product {}
```

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
    // Omit it entirely to use the default (formTypeClass LiveProp) behaviour.
    protected function instantiateForm(): FormInterface
    {
        $entity = $this->entityId !== null
            ? $this->em->find(PurchaseOrderEntity::class, $this->entityId)
            : new PurchaseOrderEntity();

        return $this->createForm(PurchaseOrderFormType::class, $entity);
    }

    // Add any extra LiveActions you need.
    #[LiveAction]
    public function addLineItem(): void
    {
        $this->formValues['lineItems'][] = [];
    }

    // save() and the admin:save listener are inherited — no need to redeclare.
}
```

The component's root template **must** include `data-admin-form` so the header Save button can reach it:

```twig
{# templates/components/Form/PurchaseOrderForm.html.twig #}
<div data-admin-form {{ attributes }}>
  {{ form_start(form) }}
    {# custom layout here #}
    <p>Total: {{ this.getTotalCost() }}</p>
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

That's it. The admin will mount your component instead of the default one for this entity's edit and new pages.

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

On a successful save the component dispatches `toast.show` with `message: 'Saved successfully!'`. Wire this to your toast library in your base layout:

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
php bin/phpunit --group admin-entity-form
```
