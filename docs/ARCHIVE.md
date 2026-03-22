# Archive / Soft-Delete Filtering

The archive feature adds a show/hide toggle to the entity list for soft-deleted or archived rows — without adding a scoped Doctrine filter or modifying your queries. It works with any boolean or nullable-datetime field that signals "this row is inactive".

## Table of Contents

- [Quick Start](#quick-start)
- [How It Works](#how-it-works)
- [Configuration](#configuration)
  - [Global Default](#global-default)
  - [Per-Entity Override](#per-entity-override)
  - [Disabling per Entity](#disabling-per-entity)
- [Archive / Unarchive Row Actions](#archive--unarchive-row-actions)
  - [Archive Permission](#archive-permission)
  - [Customising the Buttons](#customising-the-buttons)
- [Supported Field Types](#supported-field-types)
- [Role-Gating the Toggle](#role-gating-the-toggle)
- [Show/Edit Page Banner](#showedit-page-banner)
- [Template Customization](#template-customization)
- [Limitations](#limitations)

---

## Quick Start

**1. Add the expression to your entity:**

```php
use Kachnitel\AdminBundle\Attribute\Admin;

#[Admin(label: 'Products', archiveExpression: 'item.archived')]
class Product
{
    #[ORM\Column]
    private bool $archived = false;

    // ...
}
```

**2. That's it.** The bundle provides:

- A **"Show archived"** toggle in the list view next to the search bar
- An **Archive** button on each row (for active items)
- An **Unarchive** button on each row (for archived items, when `showArchived` is on)

---

## How It Works

When `archiveExpression` resolves to a simple `item.fieldName` or `entity.fieldName` expression pointing to a Doctrine-mapped boolean or nullable-datetime field, the bundle:

1. Builds a DQL `WHERE` fragment at query time to hide archived rows by default.
2. Evaluates the expression per-row for the archive/unarchive button visibility.
3. Provides Archive and Unarchive row actions that POST to `/admin/{entitySlug}/{id}/archive` and `/admin/{entitySlug}/{id}/unarchive`.

The `showArchived` state is a `#[LiveProp(url: true)]` on `EntityList`, so the toggle is bookmarkable and survives page refreshes.

---

## Configuration

### Global Default

Apply one expression to every entity in the admin without touching each class:

```yaml
# config/packages/kachnitel_admin.yaml
kachnitel_admin:
    archive:
        expression: 'item.deletedAt'   # simple item.field or entity.field
        role: 'ROLE_ADMIN'             # optional — who may toggle; null = everyone
```

Entities that have no per-entity `archiveExpression` will inherit this global setting automatically, provided the named field exists and is a supported type.

### Per-Entity Override

```php
#[Admin(
    label: 'Orders',
    archiveExpression: 'item.cancelledAt',  // overrides global
    archiveRole: 'ROLE_MANAGER',            // overrides global role
    permissions: [
        'archive' => 'ROLE_MANAGER',        // who can archive/unarchive
    ]
)]
class Order
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;
}
```

### Disabling per Entity

Opt a specific entity out of archive filtering even when a global expression is configured:

```php
#[Admin(label: 'Categories', archiveDisabled: true)]
class Category { }
```

---

## Archive / Unarchive Row Actions

When archive is configured for an entity, two action buttons appear automatically in the row actions column:

| Button | Icon | Visible when |
|--------|------|--------------|
| Archive | 🗃 | Entity is **not** currently archived |
| Unarchive | 📤 | Entity **is** currently archived (requires "Show archived" toggle) |

Both buttons send a `POST` request with CSRF protection to:
- `POST /admin/{entitySlug}/{id}/archive`
- `POST /admin/{entitySlug}/{id}/unarchive`

On success, the page redirects back to the referring page (or to the entity list if no referer is available), and a flash message confirms the action.

### What happens to the field

| Doctrine type | Archive sets | Unarchive sets |
|---|---|---|
| `boolean` | `true` | `false` |
| `datetime` | `new \DateTime()` | `null` |
| `datetime_immutable` | `new \DateTimeImmutable()` | `null` |
| `datetimetz` | `new \DateTime()` | `null` |
| `datetimetz_immutable` | `new \DateTimeImmutable()` | `null` |
| `date` | `new \DateTime('today')` | `null` |
| `date_immutable` | `new \DateTimeImmutable('today')` | `null` |

### Archive Permission

The archive and unarchive actions are guarded by the `ADMIN_ARCHIVE` voter attribute. This is a distinct permission that sits **between edit and delete**, so you can allow editors to archive without granting them delete access.

**Configure in `#[Admin]`:**

```php
#[Admin(
    label: 'Articles',
    archiveExpression: 'item.archived',
    permissions: [
        'index'   => 'ROLE_USER',
        'show'    => 'ROLE_USER',
        'new'     => 'ROLE_EDITOR',
        'edit'    => 'ROLE_EDITOR',
        'archive' => 'ROLE_EDITOR',   // ← archive permission
        'delete'  => 'ROLE_ADMIN',
    ],
)]
class Article { }
```

If no `archive` permission is set, it falls back to the global `kachnitel_admin.required_role` (default: `ROLE_ADMIN`).

**Use in custom controllers:**

```php
$this->denyAccessUnlessGranted(AdminEntityVoter::ADMIN_ARCHIVE, 'Article');
```

### Customising the Buttons

Override the defaults via `#[AdminAction]` with `override: true`:

```php
// Change the archive button label and icon
#[AdminAction(name: 'archive', label: 'Soft Delete', icon: '🗑', override: true)]

// Remove the unarchive button
#[AdminActionsConfig(exclude: ['unarchive'])]
```

---

## Supported Field Types

| Doctrine type | "Hide archived" DQL condition |
|---|---|
| `boolean` | `e.field = false` |
| `datetime` | `e.field IS NULL` |
| `datetime_immutable` | `e.field IS NULL` |
| `datetimetz` | `e.field IS NULL` |
| `datetimetz_immutable` | `e.field IS NULL` |
| `date` | `e.field IS NULL` |
| `date_immutable` | `e.field IS NULL` |

Fields of any other type (string, integer, enum, …) are not supported for DQL generation. The expression is still evaluated per-row, but no list-level filtering occurs and `resolveConfig()` returns null — the toggle and archive buttons will not appear.

---

## Role-Gating the Toggle

By default anyone with access to the admin list can toggle archive visibility. Restrict it:

```php
// Per-entity
#[Admin(archiveExpression: 'item.archived', archiveRole: 'ROLE_ADMIN')]

// Global (applies to all entities without a per-entity archiveRole)
kachnitel_admin:
    archive:
        role: 'ROLE_ADMIN'
```

When `archiveRole` is set, the toggle button is hidden for users who do not hold the required role. The DQL restriction (`WHERE e.archived = false`) still applies regardless of role — unapproved users always see the non-archived view only.

> **Note:** `archiveRole` controls the **filter toggle**. The archive/unarchive **action buttons** are controlled separately by the `archive` permission in `#[Admin(permissions: [...])]` or the global `required_role`.

---

## Show/Edit Page Banner

Import the banner partial on your show and edit templates to warn users when they are viewing an archived entity:

```twig
{# templates/bundles/KachnitelAdminBundle/admin/show.html.twig #}
{% extends '@!KachnitelAdmin/admin/show.html.twig' %}

{% block content %}
    {{ include('@KachnitelAdmin/admin/_archive_banner.html.twig', { entity: entity }) }}
    {{ parent() }}
{% endblock %}
```

The banner only renders when `admin_is_archived(entity)` returns true, so it is safe to include unconditionally.

### `admin_is_archived(entity)` Twig function

```twig
{% if admin_is_archived(product) %}
    <div class="alert alert-warning">This product is archived.</div>
{% endif %}
```

---

## Template Customization

### Override the toggle button

```
templates/bundles/KachnitelAdminBundle/components/EntityList/_ArchiveToggle.html.twig
```

Variables available:
- `showArchived` — current state (bool)
- `canToggle` — whether the current user may use the toggle (bool)

### Override the archive banner

```
templates/bundles/KachnitelAdminBundle/admin/_archive_banner.html.twig
```

Variables available:
- `entity` — the entity object

---

## Limitations

- **Simple expressions only for DQL.** The archive filter in the list view only works when the expression is exactly `item.fieldName` or `entity.fieldName`. Complex expressions (e.g. `item.status == "archived"`) are evaluated per-row via the expression language but **do not produce a DQL WHERE clause**, so the list is not pre-filtered — `resolveConfig()` returns null and the toggle/buttons do not appear. Use a dedicated boolean or nullable-datetime field for reliable list filtering.

- **No Doctrine filter.** The bundle deliberately does not register a global Doctrine filter. This keeps the feature opt-in and avoids side effects in repositories, console commands, or non-admin code that queries the same entities.

- **Single field per entity.** Only one archive expression per entity is supported.

- **Archive button requires `ArchiveConditionService` in the condition locator.** It implements `RowActionConditionInterface` and is auto-discovered. No manual registration is needed.
