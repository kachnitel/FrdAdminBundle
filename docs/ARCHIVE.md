# Archive / Soft-Delete Filtering

The archive feature adds a show/hide toggle to the entity list for soft-deleted or archived rows — without adding a scoped Doctrine filter or modifying your queries. It works with any boolean or nullable-datetime field that signals "this row is inactive".

## Table of Contents

- [Quick Start](#quick-start)
- [How It Works](#how-it-works)
- [Configuration](#configuration)
  - [Global Default](#global-default)
  - [Per-Entity Override](#per-entity-override)
  - [Disabling per Entity](#disabling-per-entity)
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

**2. That's it.** An **"Show archived"** toggle appears in the list view next to the search bar. Archived rows are hidden by default; clicking the toggle reveals them.

---

## How It Works

When `archiveExpression` resolves to a simple `item.fieldName` or `entity.fieldName` expression pointing to a Doctrine-mapped boolean or nullable-datetime field, the bundle:

1. Builds a DQL `WHERE` fragment at query time and passes it through the filter pipeline to `EntityListQueryService`.
2. Evaluates the expression per-row via `RowActionExpressionLanguage` for the show/edit page banner (`admin_is_archived()`).

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

Fields of any other type (string, integer, enum, …) are not supported for DQL generation. The expression is still evaluated per-row by the expression language, but no list-level filtering occurs and `resolveConfig()` returns null — the toggle will not appear.

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

The function evaluates the configured `archiveExpression` via the expression language, returning a bool. It handles Doctrine proxies transparently.

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

- **Simple expressions only for DQL.** The archive filter in the list view only works when the expression is exactly `item.fieldName` or `entity.fieldName`. Complex expressions (e.g. `item.status == "archived"`) are evaluated per-row via the expression language but **do not produce a DQL WHERE clause**, so the list is not pre-filtered — `resolveConfig()` returns null and the toggle does not appear. Use a dedicated boolean or nullable-datetime field for reliable list filtering.

- **No Doctrine filter.** The bundle deliberately does not register a global Doctrine filter. This keeps the feature opt-in and avoids side effects in repositories, console commands, or non-admin code that queries the same entities.

- **Single field per entity.** Only one archive expression per entity is supported.
