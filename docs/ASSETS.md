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

**Usage:** Automatically used by `EntityList` component when `enableBatchActions: true`.

**Features:**
- Click to toggle individual selection
- Shift+Click for range selection
- Ctrl/Cmd+Click for multi-toggle
- Real-time selection counter
- Syncs with LiveComponent state

**Targets:**
- `checkbox`: Checkboxes to track
- `selectedIds`: Hidden input for LiveComponent sync
- `count`: Element to display selection count

**Example:**
```twig
<div data-controller="kachnitel--admin-bundle--batch-select">
    <input
        type="checkbox"
        value="1"
        data-kachnitel--admin-bundle--batch-select-target="checkbox"
        data-action="change->kachnitel--admin-bundle--batch-select#toggle"
    >
    <!-- More checkboxes... -->

    <input
        type="hidden"
        data-kachnitel--admin-bundle--batch-select-target="selectedIds"
    >

    <span data-kachnitel--admin-bundle--batch-select-target="count">0</span>
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
