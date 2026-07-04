# Inline Entity Creation

The admin bundle renders a **"+ Add"** button next to EntityType autocomplete fields in edit and new forms. This opens a modal dialog allowing users to create the related entity without leaving the current form. Upon saving, the dialog closes and the new entity is automatically selected.

---

## How It Works

1. `DoctrineFormTypeMapper` adds `data-admin-entity-class` to the field configuration.
2. The `admin_compact` theme renders the `K:Admin:EntityType:AddButton` component.
3. The button only appears if the user has `ADMIN_NEW` permission for the target entity.
4. Clicking it opens a native `<dialog>` containing the `K:Admin:EntityType:InlineForm` LiveComponent.
5. Submitting the form successfully fires the `admin:inline:entity:saved` browser event.
6. The `admin-inline-add` Stimulus controller closes the dialog and auto-selects the new entity in the parent field.

```text
Order edit form
  └── category field (EntityType autocomplete)
        └── [+ Category] button
              └── <dialog>
                    └── K:Admin:EntityType:InlineForm (for Category)

```

---

## Styling

The dialog uses the active CSS theme's macros (`dialog()`, `dialog_header()`, `dialog_title()`, `dialog_body()`). To restyle the dialog entirely, override these macros in your theme file.

To darken the browser's default `<dialog>` backdrop, add this to your CSS and apply the `admin-inline-dialog` class via a template override of `EntityTypeAddButton.html.twig`:

```css
.admin-inline-dialog::backdrop {
    background: rgba(0, 0, 0, .45);
}

```

---

## Asset Setup

The `admin-inline-add` controller is part of the `@kachnitel/admin-bundle` package.

### AssetMapper

Manually add the entry to your project's `assets/controllers.json` (Symfony Flex will not auto-update this for symlinked or path-repository installs):

```json
{
    "controllers": {
        "@kachnitel/admin-bundle": {
            "admin-inline-add": {
                "enabled": true,
                "fetch": "eager"
            }
        }
    }
}

```

Clear the cache to regenerate the importmap:

```bash
php bin/console cache:clear

```

### Webpack Encore

Import and register the controller directly in `assets/app.js`:

```javascript
import AdminInlineAddController from '../vendor/kachnitel/admin-bundle/assets/controllers/admin-inline-add_controller.js';
application.register('admin-inline-add', AdminInlineAddController);
```

---

## Permissions

The button requires `ADMIN_NEW` access for the related entity.

```php
#[Admin(permissions: ['new' => 'ROLE_EDITOR'])]
class Category {}
```

---

## Label Used for Auto-Select

The newly created option's display label is resolved in this order:

1. `getLabel()`
2. `getName()`
3. `getTitle()`
4. `__toString()`
5. `#id` fallback

---

## Form Type Resolution

The dialog determines which form type to use based on the following priority:

| Priority | Condition | Form type used |
| --- | --- | --- |
| 1 | `#[Admin(formType: Class)]` | Custom form type class |
| 2 | Default | `DynamicEntityFormType` (auto-generated) |

---

## Form ID Uniqueness

The dialog creates its form using an FQCN-derived name (e.g., `inline_app_entity_category`). This prevents HTML `id` attribute collisions with parent forms or other simultaneously open dialogs.

---

## OneToMany Fields

OneToMany associations appear in the inline dialog via `LiveCollectionType` when using the auto-generated form. If you encounter issues with add/remove row controls (as this edge-case is untested), exclude the association from the inline form:

```php
class OrderLine {
    #[ORM\OneToMany(...)]
    #[AdminColumn(editable: false)]
    private Collection $attachments;
}
```

---

## Disabling the Inline-Add Button

* **Per field:** Set `#[AdminColumn(editable: false)]` on the specific property.
* **Per entity:** Restrict the `new` permission to a role no user holds, or override `EntityTypeAddButton.html.twig` to return empty for that entity.

---

## Running Tests

```bash
# All inline-add feature tests
composer phpunit -- --group=inline-add
```
