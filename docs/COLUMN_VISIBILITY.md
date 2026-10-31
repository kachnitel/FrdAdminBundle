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

Uses Symfony's `AuthorizationCheckerInterface`, so role hierarchy is supported
automatically. For the built-in admin UI, the permission behaviour is:

| Permission state | Forms | Inline edit | Show/index data |
|---|---|---|---|
| No `ColumnPermission` restriction | Unchanged | Unchanged | Visible |
| `ADMIN_SHOW` denied | Field removed | No trigger; direct saves are rejected | Hidden |
| `ADMIN_SHOW` allowed, `ADMIN_EDIT` denied | Field disabled; submitted values ignored | No edit trigger; save is rejected | Visible |
| Both allowed | Unchanged | Unchanged | Visible |

> **Scope:** form enforcement covers the auto-generated form
> (`DynamicEntityFormType`) only. Hand-written form types — including a custom
> `#[Admin(formType: ...)]` or an `App\Form\{Class}FormType` — are out of scope
> and do not apply `#[ColumnPermission]` automatically. Inject
> `ColumnPermissionService` in such a type and use `canPerformAction()` to omit or
> disable fields yourself. Inline edit and show/index data are enforced regardless
> of the form type.

`ADMIN_SHOW`-denied columns are excluded from list views, filters, the column
visibility toggle picker, and entity show-page data. They are also kept out of
the list **query**, so their values can't be inferred from results the user can't
see: sorting by a denied column (e.g. `?sortBy=salary`) falls back to the default
sort, column filters on it are ignored, and global search doesn't match it (nor
list it in the search help text). The entity's title or breadcrumb label may
still expose a denied property if it is used by `admin_entity_label()` or a
controller label getter.

### Required columns and creating entities

These rules apply to generated New and Edit forms. `ADMIN_EDIT` governs whether
a field can be set on either form; there is no separate `ADMIN_NEW` column
permission. A column the user may not see is removed and one they may see but
not edit is disabled, so either restriction makes **creation impossible** when
the column must be filled by the user (e.g. `NotBlank`, or `NOT NULL` with no
default). Valid patterns that avoid this:

| Pattern | Example |
|---|---|
| Required in the DB, but has a default | `status = 'draft'`, `approved = false`, `creditLimit = 0` |
| Set by a constructor or lifecycle listener | `createdBy`, `tenant`, `slug`, `internalReference` |
| Only the editing role may create | restrict `new` to the same role: `#[Admin(permissions: ['new' => 'ROLE_HR'])]` |
| Computed on persist | `margin`, `fingerprint` |
| Filled by import/API only | a compliance-restricted identifier |

In `kernel.debug`, if a restricted column fails validation while a **new** entity
is submitted, a warning is logged naming the column and the denied action.
Production behaviour is unchanged. Only the root form is inspected, and "new"
means the form was built with no data (what `AdminEntityForm` and the inline-add
dialog do).

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
