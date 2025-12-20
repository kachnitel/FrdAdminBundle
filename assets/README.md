# Kachnitel Admin Bundle - Assets

This directory contains Stimulus controllers for the admin bundle.

## Structure

```
assets/
├── controllers/           # Source Stimulus controllers
│   └── batch-select_controller.js
├── dist/                  # Build output for AssetMapper
│   └── batch-select_controller.js
├── package.json           # UX bundle configuration
└── README.md
```

## AssetMapper Support

The bundle automatically registers its Stimulus controllers with AssetMapper when available. The controllers are exposed under the `@kachnitel/admin-bundle` namespace.

### In Templates

Controllers are used via the standard Stimulus naming convention:

```twig
<div data-controller="kachnitel--admin-bundle--batch-select">
    <!-- ... -->
</div>
```

Or using the `stimulus_controller()` Twig function:

```twig
<div {{ stimulus_controller('kachnitel/admin-bundle/batch-select') }}>
    <!-- ... -->
</div>
```

## Webpack Encore Support

For Webpack Encore users, the bundle also works seamlessly. The controllers are exposed via the same package.json configuration.

## Development

When making changes to controllers:

1. Edit the source file in `controllers/`
2. Copy to `dist/` (for now, manual copy - automated build coming soon)
3. Clear cache in consuming applications: `php bin/console cache:clear`
4. For AssetMapper apps, run: `php bin/console importmap:update`

## Controllers

### batch-select

Multi-select controller with keyboard modifier support:
- **Click**: Toggle individual selection
- **Shift+Click**: Range selection
- **Ctrl/Cmd+Click**: Multi-toggle

Used by the EntityList component when `enableBatchActions: true`.
