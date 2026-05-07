# Batch Actions

Batch actions allow users to select multiple entities and perform operations on them (e.g., bulk delete, bulk publish, bulk archive). The feature combines UI components (master checkbox, range selection) with pluggable action handlers.

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

## Built-in Features

Once enabled, batch actions provide:

- **Master Checkbox**: Select/deselect all visible entities
- **Shift+Click**: Select a range of checkboxes
- **Ctrl+Click** (Cmd+Click on Mac): Toggle individual selections
- **Bulk Delete**: Delete all selected entities at once (default action)

## Custom Batch Actions

### Using AdminAction Attributes with Type Parameter

Define custom batch actions directly on your entity class using `#[AdminAction]` with `type: AdminAction::TYPE_BATCH` or `TYPE_BOTH`:

```php
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

#[Admin(label: 'Products', enableBatchActions: true)]
// Batch-only action with live action handler
#[AdminAction(
    name: 'bulk-publish',
    label: 'Publish Selected',
    icon: '🚀',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'bulkPublish',
    voterAttribute: AdminEntityVoter::ADMIN_EDIT,
    confirmMessage: 'Publish %count% items?',
    priority: 10
)]
// Batch-only action with route handler
#[AdminAction(
    name: 'bulk-archive',
    label: 'Archive',
    icon: '📦',
    type: AdminAction::TYPE_BATCH,
    route: 'app_product_batch_archive',
    voterAttribute: AdminEntityVoter::ADMIN_DELETE,
    confirmMessage: 'Archive %count% items?',
    priority: 20
)]
class Product
{
    // ...
}
```

### LiveComponent Actions (Real-time)

For fast, client-side batch operations without page reload:

```php
// In EntityList.php component
use Symfony\UX\LiveComponent\Attribute\LiveAction;

#[LiveAction]
public function bulkPublish(BatchActionDto $dto): void
{
    // $dto->getEntityIds() - array of selected IDs
    // $dto->getEntityClass() - full class name
    // $dto->getEntityShortClass() - short class name
    // $dto->getCount() - number of selected items

    $this->publishService->bulkPublish($dto->getEntityIds());

    // Component re-renders automatically
    $this->selectedIds = []; // Clear selection
}
```

The `liveAction` must be a public method on the `EntityList` component that accepts a `BatchActionDto` parameter.

### Route-based Actions (Server POST)

For complex operations requiring a dedicated controller:

```php
// Route handler
#[Route('/admin/product/batch-archive', methods: ['POST'], name: 'app_product_batch_archive')]
public function batchArchive(BatchActionDto $dto): Response
{
    $this->archiveService->archiveBatch($dto->getEntityIds());

    $this->addFlash('success', sprintf('%d products archived', $dto->getCount()));
    return $this->redirectToRoute('admin_product_index');
}
```

The `ArgumentResolver` automatically converts POST data to a typed `BatchActionDto` object.

### Custom Provider

For complex scenarios requiring dynamic action registration:

```php
use Kachnitel\AdminBundle\BatchAction\BatchActionProviderInterface;
use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('kachnitel_admin.batch_action_provider')]
class ProductBatchActionProvider implements BatchActionProviderInterface
{
    public function supports(string $entityClass): bool
    {
        return $entityClass === Product::class;
    }

    public function getActions(string $entityClass): array
    {
        return [
            new BatchAction(
                name: 'bulk-pricing-update',
                label: 'Update Prices',
                icon: '💰',
                liveAction: 'updatePrices',
                voterAttribute: AdminEntityVoter::ADMIN_EDIT,
                confirmMessage: 'Update pricing for %count% products?',
            ),
        ];
    }

    public function getPriority(): int
    {
        return 10; // Higher priority than default (0)
    }
}
```

## BatchActionDto Reference

Actions receive a `BatchActionDto` with these methods:

```php
$dto->getName(): string                    // Action name
$dto->getEntityIds(): array<int|string>   // Selected entity IDs
$dto->getEntityClass(): string             // Full class name (e.g. App\Entity\Product)
$dto->getEntityShortClass(): string        // Short name (e.g. Product)
$dto->getCount(): int                      // Number of selected items
$dto->isAllSelected(): bool                // Whether "select all" was used
```

## Permissions

Every batch action requires permission checking via:

- **voterAttribute** (recommended): Checked via `AdminEntityVoter`
  ```php
  voterAttribute: AdminEntityVoter::ADMIN_EDIT  // or ADMIN_DELETE, etc.
  ```

- **permission**: Checked as a role
  ```php
  permission: 'ROLE_ADMIN'
  ```

If neither is specified, the action is **denied by default** for security. At least one must be provided.

## Message Placeholders

Confirmation messages support substitution:

```php
confirmMessage: 'Archive %count% items with %action_name% for %entity_name%?'
```

- `%count%` — Number of selected items
- `%action_name%` — Batch action label
- `%entity_name%` — Short entity class name

## Common Patterns

### Confirmation with Custom Message

```php
#[AdminAction(
    name: 'bulk-delete-permanent',
    label: 'Permanent Delete',
    icon: '⚠️',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'permanentDelete',
    voterAttribute: AdminEntityVoter::ADMIN_DELETE,
    confirmMessage: 'Permanently delete %count% items? This cannot be undone.',
)]
```

### External Action (URL)

```php
#[AdminAction(
    name: 'export',
    label: 'Export to CSV',
    icon: '📥',
    type: AdminAction::TYPE_BATCH,
    url: '/export/products',
    permission: 'ROLE_ADMIN',
)]
```

### Visual Distinction with CSS

```php
#[AdminAction(
    name: 'send-notification',
    label: 'Send Notification',
    icon: '📧',
    type: AdminAction::TYPE_BATCH,
    batchLiveAction: 'sendNotification',
    voterAttribute: AdminEntityVoter::ADMIN_EDIT,
    cssClass: 'btn-info',
)]
```

## Troubleshooting

### Action not appearing

1. **Check `enableBatchActions: true`** on the `#[Admin]` attribute
2. **Verify permissions**: User must have the required `voterAttribute` or `permission`
3. **Check provider registration**: Custom providers must implement `BatchActionProviderInterface`
4. **Clear cache**: `php bin/console cache:clear`

### Action renders but doesn't work

1. **LiveAction**: Verify method exists on EntityList component and accepts `BatchActionDto`
2. **Route**: Verify route exists and handler accepts `BatchActionDto` parameter
3. **Browser console**: Check for JavaScript errors
4. **Check CSRF**: POST-based actions must include CSRF token (handled automatically)

### Configuration Errors

The attribute provider validates configuration and provides helpful messages:

```
Batch action "bulk-publish" must specify one of: batchLiveAction, route, or url.
Batch action "bulk-publish" specifies both batchLiveAction and route; route will be ignored when batchLiveAction is present.
```

If you see these errors, review your `#[AdminAction]` configuration with type set to `TYPE_BATCH` or `TYPE_BOTH`.

## See Also

- [Configuration Guide](CONFIGURATION.md) — Entity setup and permissions
- [Asset Management](ASSETS.md) — Stimulus controller registration
- [Row Actions](ROW_ACTIONS.md) — Single-entity actions
