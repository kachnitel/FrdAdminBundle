# Column Visibility

Allow users to show/hide columns in entity list views. Hidden columns are remembered per-entity using session storage by default.

## Setup

Enable column visibility on any entity:

```php
use Kachnitel\AdminBundle\Attribute\Admin;

#[Admin(
    label: 'Products',
    enableColumnVisibility: true,
    columns: ['id', 'name', 'price', 'stock']
)]
class Product { }
```

A "Columns" dropdown will appear in the list view, allowing users to toggle column visibility. Preferences are stored in the session by default and reset when the session expires.

## Custom Storage

To persist preferences beyond the session (e.g., in a database), implement `AdminPreferencesStorageInterface` and add `#[AsAlias]` to register it as the active storage:

```php
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AdminPreferencesStorageInterface::class)]
class DbAdminPreferencesStorage implements AdminPreferencesStorageInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        // Retrieve stored value for $key, or return $default
    }

    public function set(string $key, mixed $value): void
    {
        // Persist $key => $value
    }
}
```

### Storage Keys

Keys follow the pattern `{preference_type}.{EntityShortName}`, e.g. `column_visibility.Product`. Values are arrays of hidden column names (e.g. `['price', 'stock']`). An empty array (the default) means all columns are visible.

See `PreferenceKeys` for available preference type constants.
