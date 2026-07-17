# Asset Management

This bundle includes Stimulus controllers for enhanced interactivity. It supports both **AssetMapper** and **Webpack Encore**.

## Table of Contents

- [AssetMapper Setup](#assetmapper-setup)
- [Webpack Encore Setup](#webpack-encore-setup)
- [Available Controllers](#available-controllers)
- [Troubleshooting](#troubleshooting)

## AssetMapper Setup

### Automatic Configuration

The bundle automatically configures AssetMapper when it's available. No manual configuration needed!

### After Installation

1. **Update your importmap** (if using AssetMapper):
   ```bash
   php bin/console importmap:update
   ```

2. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

### Verify Installation

Check that the bundle's controllers are available:

```bash
php bin/console debug:asset-map | grep kachnitel
```

You should see:
```
@kachnitel/admin-bundle/batch-select_controller.js
@kachnitel/admin-bundle/admin-inline-add_controller.js
```

### Manual Configuration (Optional)

If you need to customize the asset path, you can override it in `config/packages/framework.yaml`:

```yaml
framework:
    asset_mapper:
        paths:
            # Bundle assets are auto-registered, but you can customize:
            '%kernel.project_dir%/vendor/kachnitel/admin-bundle/assets/dist': '@kachnitel/admin-bundle'
```

## Webpack Encore Setup

### Configuration

The bundle works seamlessly with Webpack Encore. The controllers are exposed via the standard UX bundle mechanism.

### After Installation

1. **Install JavaScript dependencies**:
   ```bash
   npm install
   # or
   yarn install
   ```

2. **Build assets**:
   ```bash
   npm run dev
   # or for production
   npm run build
   ```

### Encore Configuration

The bundle's `package.json` includes the necessary Symfony UX configuration. Encore will automatically detect and include the controllers.

## Available Controllers

### batch-select

**Purpose:** Multi-select functionality with keyboard modifiers for batch operations.

**Usage:** Automatically used by `EntityList` component when `enableBatchActions: true`. It's wired up for you —
`EntityList.html.twig` sets `data-controller="batch-select"` on its own root element whenever `canBatchDelete()`
is true, so you don't attach the controller by hand in normal usage.

**Features:**
- Click to toggle individual selection
- Shift+Click for range selection (selects all checkboxes between first and second click)
- Ctrl/Cmd+Click for multi-toggle
- Master checkbox to select/deselect all visible rows, with an indeterminate state when partially selected
- Syncs with LiveComponent state via `data-model="selectedIds[]"` on each row checkbox

**Targets used by the bundle's own templates:**
- `master`: the header checkbox that selects/deselects all visible rows
- `checkbox`: each row's own selection checkbox

> Selection state itself isn't tracked via a dedicated Stimulus target — it lives in `EntityList`'s `selectedIds`
> LiveProp, kept in sync through `data-model="selectedIds[]"`. The running total shown on batch action buttons
> (e.g. "Delete Selected (3)") is rendered server-side from `this.selectedIds|length`, not read from a `count`
> target. If your controller version exposes further targets, check
> `assets/controllers/batch-select_controller.js` in the bundle for the authoritative list.

**Example (mirrors the markup `EntityList.html.twig` actually renders):**
```twig
<div data-controller="batch-select">
    <input
        type="checkbox"
        data-batch-select-target="master"
        data-action="change->batch-select#toggleAll"
    >

    <!-- One per row -->
    <input
        type="checkbox"
        value="1"
        data-model="selectedIds[]"
        data-batch-select-target="checkbox"
        data-action="click->batch-select#toggle"
    >
</div>
```

### admin-inline-add

**Purpose:** Manages the "+ Add" inline-entity-creation dialog next to `EntityType` autocomplete fields in edit and new forms.

**Usage:** Automatically used by `EntityTypeAddButton` when the `admin_compact` theme renders an EntityType field the user has `ADMIN_NEW` permission for. You don't attach the controller by hand — it ships wired to the dialog markup that `EntityTypeAddButton.html.twig` renders. See [Inline Entity Creation](INLINE_ADD.md) for the full feature guide.

**Features:**
- Opens the native `<dialog>` via `showModal()` on button click
- Listens on `window` for the `admin:inline:entity:saved` event dispatched by `K:Admin:EntityType:InlineForm` after a successful save
- Closes the dialog once the matching entity is saved
- Auto-selects the newly created entity in the parent Tom Select widget (from `symfony/ux-autocomplete`), adding it as a new option first if needed

**Values:**
- `entityClass` — FQCN of the entity managed by this dialog; used to ignore `admin:inline:entity:saved` events fired by other inline-add dialogs on the same page
- `fieldName` — HTML `name` of the parent `<select>` (e.g. `order[category]`, or `order[tags]` for multi-valued fields)
- `dialogId` — id of the `<dialog>` element; used as a fallback lookup if the Stimulus target reference is lost after a LiveComponent re-render inside the dialog

**Targets used by the bundle's own templates:**
- `dialog`: the `<dialog>` element inside the component root

**Example (mirrors the markup `EntityTypeAddButton.html.twig` actually renders):**
```twig
<div data-controller="admin-inline-add"
     data-admin-inline-add-entity-class-value="App\Entity\Category"
     data-admin-inline-add-field-name-value="order[category]"
     data-admin-inline-add-dialog-id-value="category-dialog">
    <button type="button" data-action="admin-inline-add#open">+ Category</button>

    <dialog id="category-dialog" data-admin-inline-add-target="dialog">
        <twig:K:Admin:EntityType:InlineForm entityClass="App\Entity\Category" />
    </dialog>
</div>
```

**Registering it (AssetMapper):**

Add to `assets/controllers.json`:
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

Then clear the cache to regenerate the importmap: `php bin/console cache:clear`. Symfony Flex will **not** auto-add this entry for symlinked or path-repository installs, so it must be added manually.

**Registering it (Webpack Encore):**

```javascript
import AdminInlineAddController from '../vendor/kachnitel/admin-bundle/assets/controllers/admin-inline-add_controller.js';
application.register('admin-inline-add', AdminInlineAddController);
```

## Troubleshooting

### Controller Not Loading (AssetMapper)

1. **Clear cache:**
   ```bash
   php bin/console cache:clear
   ```

2. **Update importmap:**
   ```bash
   php bin/console importmap:update
   ```

3. **Check if bundle is registered:**
   ```bash
   php bin/console debug:container | grep kachnitel
   ```

4. **Verify asset mapper paths:**
   ```bash
   php bin/console debug:config framework asset_mapper
   ```

### Controller Not Loading (Webpack Encore)

1. **Rebuild assets:**
   ```bash
   npm run build
   ```

2. **Check for JavaScript errors in browser console**

3. **Verify Stimulus is installed:**
   ```bash
   npm list @hotwired/stimulus
   ```

### Symlinked Bundle (Development)

If you're developing with a symlinked bundle:

1. **AssetMapper:** The bundle path is auto-detected, but you may need to clear cache after changes
2. **Webpack Encore:** You may need to rebuild assets after controller changes

### Console Errors

**"Controller definition not found"**
- Ensure Stimulus Bundle is installed: `composer require symfony/stimulus-bundle`
- Check browser console for loading errors
- Verify the controller file exists in `vendor/kachnitel/admin-bundle/assets/dist/`

**"Module not found"**
- Run `php bin/console importmap:update` (AssetMapper)
- Run `npm install` (Webpack Encore)

## Development

When developing the bundle itself:

1. Edit controllers in `assets/controllers/`
2. Copy to `dist/` for AssetMapper compatibility
3. Test in both AssetMapper and Encore environments
4. Clear cache in test applications

### Symlinked Development

For local development with a symlinked bundle, see the official Symfony documentation:
[Linking an Already Published Bundle](https://symfony.com/doc/current/bundles.html#linking-an-already-published-bundle)

## Related Documentation

- [Symfony AssetMapper Documentation](https://symfony.com/doc/current/frontend/asset_mapper.html)
- [Symfony Webpack Encore Documentation](https://symfony.com/doc/current/frontend/encore/index.html)
- [Symfony UX Documentation](https://symfony.com/bundles/StimulusBundle/current/index.html)
- [Create a UX Bundle](https://symfony.com/doc/current/frontend/create_ux_bundle.html)
