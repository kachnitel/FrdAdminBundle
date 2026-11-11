# Preferences Storage

The bundle stores user-facing admin preferences behind the `AdminPreferencesStorageInterface` abstraction. This covers features such as column visibility and can be extended for future preference types.

By default, the bundle uses a session-backed implementation. If you want preferences to persist beyond the session (for example, in a database or a user profile table), register your own implementation as the active storage.

## Default behavior

The default storage is session-based and resets when the session expires.

## Implement a custom storage

Create a service that implements `AdminPreferencesStorageInterface` and register it with `#[AsAlias]` so it becomes the active implementation for the bundle:

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

This is the mechanism used for persistent preference storage in applications that need longer-lived values than the default PHP session.

## Storage keys

Preference keys use the pattern `{preference_type}.{EntityShortName}`.

Examples:

- `column_visibility.Product`
- `column_visibility.Order`
- future keys can be added to `PreferenceKeys`

The value type depends on the preference. For column visibility, values are arrays of hidden column names such as `['price', 'stock']`. An empty array means all columns are visible.

## Related docs

- [Column Visibility](COLUMN_VISIBILITY.md)
- `PreferenceKeys`
- `AdminPreferencesStorageInterface`
