# Batch Actions Setup

Batch actions allow users to select multiple entities and perform operations on them (e.g., bulk delete). This feature requires additional setup beyond the basic installation.

## Requirements

Batch actions rely on a Stimulus controller for the interactive checkbox behavior (Shift+Click range selection, Ctrl+Click toggle, master checkbox).

## Setup Steps

### 1. Enable on Entity

Add `enableBatchActions: true` to the `#[Admin]` attribute:

```php
use Kachnitel\AdminBundle\Attribute\Admin;

#[Admin(label: 'Products', enableBatchActions: true)]
class Product
{
    // ...
}
```

### 2. Register Stimulus Controller

The bundle provides a `batch-select` Stimulus controller that needs to be registered in your application.

#### For AssetMapper Users

**`assets/controllers.json`** - Add the bundle's controller:
```json
{
    "controllers": {
        "@kachnitel/admin-bundle": {
            "batch-select": {
                "enabled": true,
                "fetch": "eager",
                "autoimport": {}
            }
        }
    }
}
```

**`importmap.php`** - Add the importmap entry:
```php
return [
    // ... existing entries
    '@kachnitel/admin-bundle/batch-select_controller.js' => [
        'path' => '@kachnitel/admin-bundle/batch-select_controller.js',
    ],
];
```

#### For Webpack Encore Users

See the [Asset Management Guide](ASSETS.md) for Webpack Encore configuration.

### 3. Clear Cache

After making these changes, clear your Symfony cache:

```bash
php bin/console cache:clear
```

## Features

Once enabled, batch actions provide:

- **Master Checkbox**: Select/deselect all visible entities
- **Shift+Click**: Select a range of checkboxes
- **Ctrl+Click** (Cmd+Click on Mac): Toggle individual selections
- **Bulk Delete**: Delete all selected entities at once

## Troubleshooting

If batch actions aren't working:

1. Verify the Stimulus controller is properly registered in `controllers.json`
2. Check browser console for JavaScript errors
3. Ensure the importmap entry exists (AssetMapper) or Encore is properly configured
4. Clear cache: `php bin/console cache:clear`
5. Check that `enableBatchActions: true` is set on the entity's `#[Admin]` attribute

See [Asset Management](ASSETS.md) for detailed troubleshooting steps.
