# Column Visibility

## Per-Column Permissions

Restrict column visibility and editability using the `#[ColumnPermission]` attribute. The attribute accepts a map of admin actions to required roles:

```php
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

#[Admin(label: 'Employees')]
class Employee
{
    private string $name;

    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
    ])]
    private float $salary;

    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => ['ROLE_HR', 'ROLE_MANAGER'],
    ])]
    private string $internalNotes;
}
```

**Available actions:** `ADMIN_SHOW`, `ADMIN_EDIT`, `ADMIN_DELETE` (use `AdminEntityVoter` constants).

**Role arrays:** Pass an array of roles for OR logic — user needs at least one.

Columns restricted for `ADMIN_SHOW` are excluded from list views, filters, and the column visibility toggle picker. Uses Symfony's `AuthorizationCheckerInterface`, so role hierarchy is supported automatically.

## User Column Visibility Toggle

Allow users to show/hide columns in entity list views. Hidden columns are remembered per-entity using session storage by default.

### Setup

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

When combined with `#[ColumnPermission]`, users can only toggle columns they have permission to see.

### Custom Storage

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
