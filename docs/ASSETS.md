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
