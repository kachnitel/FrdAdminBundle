# Batch Actions

Batch actions let users select multiple entities and perform operations on them — bulk delete, bulk publish, status changes, and more.

## Table of Contents

- [Quick Start](#quick-start)
- [Requirements](#requirements)
- [Setup Steps](#setup-steps)
  - [1. Enable on Entity](#1-enable-on-entity)
  - [2. Register Stimulus Controller](#2-register-stimulus-controller)
  - [3. Clear Cache](#3-clear-cache)
- [Built-In Batch Actions](#built-in-batch-actions)
- [Custom Batch Actions](#custom-batch-actions)
  - [Declaring with `#[AdminAction]`](#declaring-with-adminaction)
  - [Action Types](#action-types)
  - [Handler Modes](#handler-modes)
  - [Permissions](#permissions)
  - [Confirmation Dialogs](#confirmation-dialogs)
  - [Programmatic Providers](#programmatic-providers)
- [Selection Features](#selection-features)
- [Troubleshooting](#troubleshooting)

---

## Quick Start

```php
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminAction;

#[Admin(label: 'Orders', enableBatchActions: true)]
#[AdminAction(
    name: 'bulk-archive',
    label: 'Archive Selected',
    icon: '📦',
    route: 'app_order_bulk_archive',
    confirmMessage: 'Archive %count% orders?',
    actionType: AdminAction::ACTION_TYPE_BATCH,
)]
class Order { }
```

A "Archive Selected (N)" button appears in the batch actions bar. Clicking it posts the selected IDs to your route.

---

## Requirements

Batch actions rely on a Stimulus controller for interactive checkbox behaviour (Shift+Click range selection, Ctrl+Click toggle, master checkbox). You must register this controller in your application before batch actions will work.

---

## Setup Steps

### 1. Enable on Entity

Add `enableBatchActions: true` to the `#[Admin]` attribute:

```php
#[Admin(label: 'Products', enableBatchActions: true)]
class Product { }
```

### 2. Register Stimulus Controller

#### For AssetMapper Users

**`assets/controllers.json`**:
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

**`importmap.php`**:
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

```bash
php bin/console cache:clear
```

---

## Built-In Batch Actions

When `enableBatchActions: true` is set, a **Delete Selected** button appears automatically. The user must hold the `ADMIN_DELETE` voter attribute to see it.

No additional configuration is needed — this is provided by the bundle out of the box.

When the entity also has `archiveExpression` configured, an **Archive Selected**
button is automatically added to the batch actions bar. No additional code needed.

```
#[Admin(
    enableBatchActions: true,   // ← required for the bar to render at all
    archiveExpression: 'item.archived',  // ← required for ArchiveBatchActionProvider to register the action
)]
```

---

## Custom Batch Actions

### Declaring with `#[AdminAction]`

Use the `#[AdminAction]` attribute on the entity class with `actionType` set to `ACTION_TYPE_BATCH`:

```php
#[Admin(label: 'Orders', enableBatchActions: true)]
#[AdminAction(
    name: 'bulk-publish',
    label: 'Publish Selected',
    icon: '🌐',
    route: 'app_order_bulk_publish',
    confirmMessage: 'Publish %count% orders?',
    priority: 30,
    actionType: AdminAction::ACTION_TYPE_BATCH,
)]
class Order { }
```

The attribute is **repeatable** — add one per custom batch action.

### Action Types

The `actionType` parameter controls where an action button appears:

| Constant | Renders in |
|---|---|
| `ACTION_TYPE_ROW` *(default)* | Entity list rows, show page header, edit page header |
| `ACTION_TYPE_BATCH` | Batch actions bar only |
| `ACTION_TYPE_BOTH` | All row action positions **and** the batch actions bar |

```php
// Row action only (default — no actionType needed)
#[AdminAction(name: 'view', label: 'View', route: 'app_order_view')]

// Batch action only
#[AdminAction(name: 'bulk-export', label: 'Export', url: '/admin/orders/export',
    actionType: AdminAction::ACTION_TYPE_BATCH)]

// Both row and batch
#[AdminAction(name: 'archive', label: 'Archive', url: '/admin/orders/archive',
    actionType: AdminAction::ACTION_TYPE_BOTH)]
```

### Handler Modes

Batch action buttons can submit data to your app in three ways, checked in order:

**1. LiveComponent** — renders a LiveComponent, receiving selectedIds / entityClass / entityShortClass as LiveProps:

```php
#[AdminAction(
    name: 'bulk-tag',
    label: 'Tag Selected',
    liveComponent: App\LiveComponent\BatchTagger::class,
    actionType: AdminAction::ACTION_TYPE_BATCH,
)]
```

When clicked, the EntityList emits a `batch:action` browser event. Wire this in your JavaScript or a parent LiveComponent.

**2. Route** — form POST with selected IDs as `ids[]`:

```php
#[AdminAction(
    name: 'bulk-publish',
    label: 'Publish Selected',
    route: 'app_order_bulk_publish',
    actionType: AdminAction::ACTION_TYPE_BATCH,
)]
```

Your route receives a `ids[]` POST parameter containing the selected entity IDs:

```php
#[Route('/admin/orders/bulk-publish', name: 'app_order_bulk_publish', methods: ['POST'])]
public function bulkPublish(Request $request, EntityManagerInterface $em): Response
{
    $ids = $request->request->all('ids');
    foreach ($ids as $id) {
        $order = $em->find(Order::class, $id);
        // ...
    }
    $this->addFlash('success', count($ids) . ' orders published.');
    return $this->redirectToRoute('app_admin_entity_index', ['entitySlug' => 'order']);
}
```

**3. Static URL** — same as route but with a hardcoded URL:

```php
#[AdminAction(
    name: 'bulk-archive',
    label: 'Archive',
    url: '/admin/orders/archive',
    actionType: AdminAction::ACTION_TYPE_BATCH,
)]
```

### Permissions

Batch action visibility is controlled by `permission` (role check) and `voterAttribute` (Admin voter):

```php
#[AdminAction(
    name: 'bulk-delete-sensitive',
    label: 'Delete All',
    url: '/admin/orders/delete',
    permission: 'ROLE_SUPER_ADMIN',          // requires this role
    actionType: AdminAction::ACTION_TYPE_BATCH,
)]

#[AdminAction(
    name: 'bulk-edit',
    label: 'Edit Selected',
    url: '/admin/orders/bulk-edit',
    voterAttribute: AdminEntityVoter::ADMIN_EDIT,  // uses Admin voter
    actionType: AdminAction::ACTION_TYPE_BATCH,
)]
```

### Confirmation Dialogs

Use `confirmMessage` to show a native browser confirmation before the action fires. The `%count%` placeholder is replaced with the number of selected items:

```php
#[AdminAction(
    name: 'bulk-delete',
    label: 'Delete',
    url: '/admin/orders/delete',
    confirmMessage: 'Permanently delete %count% orders? This cannot be undone.',
    actionType: AdminAction::ACTION_TYPE_BATCH,
)]
```

### Programmatic Providers

For reusable batch actions across multiple entities or logic that requires injected services, implement `BatchActionProviderInterface`:

```php
use Kachnitel\AdminBundle\BatchAction\BatchActionProviderInterface;
use Kachnitel\AdminBundle\ValueObject\BatchAction;

class OrderBatchActionProvider implements BatchActionProviderInterface
{
    public function supports(string $entityClass): bool
    {
        return $entityClass === Order::class;
    }

    public function getActions(string $entityClass): array
    {
        return [
            new BatchAction(
                name: 'bulk-notify',
                label: 'Send Notification',
                icon: '📧',
                route: 'app_order_bulk_notify',
                confirmMessage: 'Send email to %count% customers?',
                priority: 40,
            ),
        ];
    }

    public function getPriority(): int
    {
        return 50;
    }
}
```

Providers are **auto-discovered** via `#[AutoconfigureTag]` — implement the interface and they register automatically. No manual service configuration is needed.

---

## Selection Features

The Stimulus `batch-select` controller provides these interaction patterns:

- **Click** — toggle individual checkbox
- **Shift+Click** — range selection between last clicked and current
- **Ctrl/Cmd+Click** — toggle individual without losing other selections
- **Master checkbox** — select or deselect all visible rows; shows indeterminate state when partially selected

Selected IDs are tracked as a `#[LiveProp]` on the EntityList component, so selection state survives LiveComponent re-renders (filtering, pagination changes).

---

## Troubleshooting

If batch actions aren't working:

1. Verify the Stimulus controller is registered in `controllers.json`
2. Check the browser console for JavaScript errors
3. Ensure the importmap entry exists (AssetMapper) or Encore is configured
4. Clear cache: `php bin/console cache:clear`
5. Confirm `enableBatchActions: true` is set on the entity's `#[Admin]` attribute
6. Verify the user holds the `ADMIN_DELETE` permission (required for built-in batch delete)

See [Asset Management](ASSETS.md) for detailed asset troubleshooting.
