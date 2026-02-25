# Row Actions

Row actions add custom buttons to each row in the entity list view. The bundle ships with default **Show** and **Edit** actions; this guide explains how to add, modify, and remove them.

## Table of Contents

- [Quick Start](#quick-start)
- [The `#[AdminAction]` Attribute](#the-adminaction-attribute)
- [Action Parameters](#action-parameters)
- [Conditions (Visibility)](#conditions-visibility)
- [Controlling Default Actions](#controlling-default-actions)
- [Programmatic Providers](#programmatic-providers)
- [Twig Functions](#twig-functions)
- [Examples](#examples)

## Quick Start

Add `#[AdminAction]` to your entity class — it's repeatable, so add one per action:

```php
use Kachnitel\AdminBundle\Attribute\AdminAction;

#[ORM\Entity]
#[Admin(label: 'Orders')]
#[AdminAction(
    name: 'approve',
    label: 'Approve',
    icon: '✅',
    route: 'app_order_approve',
    condition: 'entity.status == "pending"',
)]
class Order
{
    // ...
}
```

That's it. The bundle auto-discovers the attribute, evaluates the condition for each row, and renders the button alongside the default Show/Edit actions.

## The `#[AdminAction]` Attribute

**Namespace:** `Kachnitel\AdminBundle\Attribute\AdminAction`

The attribute is placed on the entity class and can be repeated for multiple actions:

```php
#[ORM\Entity]
#[Admin(label: 'Products')]
#[AdminAction(name: 'duplicate', label: 'Duplicate', icon: '📋', route: 'app_product_duplicate', priority: 30)]
#[AdminAction(name: 'archive',   label: 'Archive',   icon: '📦', route: 'app_product_archive',   priority: 40,
    confirmMessage: 'Archive this product?',
    condition: 'entity.status != "archived"',
)]
class Product
{
    // ...
}
```

## Action Parameters

| Parameter        | Type                                     | Default      | Description |
|------------------|------------------------------------------|--------------|-------------|
| `name`           | `string`                                 | **required** | Unique action identifier (e.g., `'approve'`, `'duplicate'`) |
| `label`          | `string`                                 | **required** | Button label text |
| `icon`           | `?string`                                | `null`       | Emoji or icon identifier displayed in the button |
| `route`          | `?string`                                | `null`       | Named Symfony route; entity `id` is appended automatically |
| `routeParams`    | `array`                                  | `[]`         | Additional parameters merged into the route (alongside `id`) |
| `url`            | `?string`                                | `null`       | Static URL — use `route` for dynamic entity-based links |
| `permission`     | `?string`                                | `null`       | Required role, e.g. `'ROLE_EDITOR'` |
| `voterAttribute` | `?string`                                | `null`       | Admin voter constant, e.g. `'ADMIN_EDIT'` |
| `condition`      | `string\|array\|null`                    | `null`       | Visibility condition — see [Conditions](#conditions-visibility) |
| `cssClass`       | `?string`                                | `null`       | CSS classes for the button (overrides theme default) |
| `confirmMessage` | `?string`                                | `null`       | If set, a browser confirm dialog is shown before the action |
| `openInNewTab`   | `bool`                                   | `false`      | Open link in a new tab |
| `priority`       | `int`                                    | `100`        | Sort order — lower renders first (default Show=10, Edit=20) |
| `method`         | `?string`                                | `null`       | HTTP method (`'POST'`, `'DELETE'`) — renders a `<form>` with CSRF instead of a link |
| `template`       | `?string`                                | `null`       | Custom Twig template to render this button — receives `action`, `entity`, `entityShortClass` |
| `override`       | `bool`                                   | `false`      | Completely replace an existing action with the same name (see [Overriding Defaults](#overriding-defaults)) |

### URL Resolution Order

For link-based actions, the URL is resolved in this order:

1. `route` — `path(route, routeParams + ['id' => entity.id])`
2. `url` — used verbatim
3. Auto-resolution — `admin_object_path(entity, name)` (matches built-in admin routes by action name)

## Conditions (Visibility)

The `condition` parameter controls whether the button is shown for a specific row. A button is hidden when the condition evaluates to `false`; it is not disabled.

Two styles are supported:

### String Expressions (Simple)

Use for straightforward property checks — no extra class needed:

```php
// Equality check
#[AdminAction(name: 'approve', label: 'Approve', condition: 'entity.status == "pending"')]

// Inequality
#[AdminAction(name: 'reactivate', label: 'Reactivate', condition: 'entity.status != "active"')]

// Boolean check
#[AdminAction(name: 'publish', label: 'Publish', condition: 'entity.isDraft')]

// Negation
#[AdminAction(name: 'edit', label: 'Edit', condition: '!entity.isLocked')]

// Numeric comparison
#[AdminAction(name: 'discount', label: 'Discount', condition: 'entity.stock > 0')]

// Strict equality
#[AdminAction(name: 'verify', label: 'Verify', condition: 'entity.verifiedAt === null')]
```

Both `entity.` and `item.` prefixes work. The expression uses Symfony's `PropertyAccess` component to read values, so `entity.status` calls `getStatus()`, `isStatus()`, or accesses the public property.

If the expression fails to evaluate (e.g., property doesn't exist), the action is **hidden** as a safe default.

### DI Tuple Conditions (Complex)

Use when the condition requires injected services, database queries, or multi-entity logic:

```php
#[AdminAction(
    name: 'approve',
    label: 'Approve',
    condition: [ApprovalService::class, 'canApprove'],
)]
```

The method receives the **entity object** and must return `bool`:

```php
class ApprovalService
{
    public function __construct(
        private readonly WorkflowInterface $workflow,
    ) {}

    public function canApprove(object $entity): bool
    {
        return $this->workflow->can($entity, 'approve');
    }
}
```

The service is resolved from the DI container at render time. Any class under `src/` with `autoconfigure: true` (the Symfony default) is available without extra registration.

If the service is not found or the method throws, the action is **hidden** as a safe default.

## Controlling Default Actions

The bundle shows **Show** and **Edit** by default. Use `#[AdminActionsConfig]` to control them.

### Remove Specific Defaults

```php
#[ORM\Entity]
#[Admin(label: 'Audit Logs')]
#[AdminActionsConfig(exclude: ['edit'])]   // Show "Show", hide "Edit"
class AuditLog { }
```

### Disable All Defaults

```php
#[ORM\Entity]
#[Admin(label: 'Read-Only Reports')]
#[AdminActionsConfig(disableDefaults: true)]
#[AdminAction(name: 'export', label: 'Export', icon: '📥', route: 'app_report_export')]
class Report { }
```

### Whitelist Specific Actions

Only show the listed action names (custom + built-in):

```php
#[ORM\Entity]
#[Admin(label: 'Products')]
#[AdminActionsConfig(include: ['show', 'duplicate'])]  // Only show and duplicate
#[AdminAction(name: 'duplicate', label: 'Duplicate', route: 'app_product_duplicate')]
class Product { }
```

### Overriding Defaults

To completely replace a default action (e.g., change the Show button), use `override: true`:

```php
// Merge (default) — add a condition to the existing Show action, keep its voter check
#[AdminAction(name: 'show', label: 'Preview', condition: 'entity.isPublished')]

// Full override — replace Show entirely; no voter attribute unless you specify one
#[AdminAction(name: 'show', label: 'Preview', icon: '🔍', route: 'app_preview', override: true)]
```

Without `override: true`, properties from your attribute are **merged** into the default action — only non-null values replace existing ones. This lets you, for example, add a condition to the default Edit button without losing its permission check:

```php
// Adds condition while keeping ADMIN_EDIT voter check
#[AdminAction(name: 'edit', label: 'Edit', condition: '!entity.isLocked')]
```

## Programmatic Providers

For reusable action sets or actions computed at runtime, implement `RowActionProviderInterface`:

```php
use Kachnitel\AdminBundle\RowAction\RowActionProviderInterface;
use Kachnitel\AdminBundle\ValueObject\RowAction;

class WorkflowRowActionProvider implements RowActionProviderInterface
{
    public function __construct(
        private readonly WorkflowRegistry $workflows,
    ) {}

    public function supports(string $entityClass): bool
    {
        return is_a($entityClass, HasWorkflowInterface::class, true);
    }

    public function getActions(string $entityClass): array
    {
        $workflow = $this->workflows->get(new $entityClass());
        $actions = [];

        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $actions[] = new RowAction(
                name: 'workflow_' . $transition->getName(),
                label: ucfirst($transition->getName()),
                route: 'app_workflow_transition',
                routeParams: ['transition' => $transition->getName()],
                condition: [WorkflowConditionService::class, 'canTransition'],
                priority: 50,
            );
        }

        return $actions;
    }

    public function getPriority(): int
    {
        return 50; // Higher than DefaultRowActionProvider (0)
    }
}
```

Providers are **auto-discovered** — implement the interface and they're registered automatically via `#[AutoconfigureTag]`. No manual service configuration needed.

**Priority rules:**

| Provider | Priority | Notes |
|----------|----------|-------|
| `DefaultRowActionProvider` | 0 | Ships with the bundle — Show and Edit |
| `AttributeRowActionProvider` | 50 | Reads `#[AdminAction]` attributes |
| Your provider | Your choice | Higher wins on merge conflicts |

## Twig Functions

Three functions are available in templates:

```twig
{# All actions registered for an entity class (unfiltered) #}
{% set all = admin_row_actions('App\\Entity\\Product') %}

{# Actions visible for a specific row (filtered by permissions + conditions) #}
{% set visible = admin_visible_row_actions(entityClass, entity, entityShortClass) %}

{# Check a single action #}
{% if admin_is_action_visible(action, entity, entityShortClass) %}
    ...
{% endif %}
```

`admin_visible_row_actions` is what the default `_RowActions.html.twig` partial uses.

## Examples

### POST Action with Confirmation

```php
#[AdminAction(
    name: 'archive',
    label: 'Archive',
    icon: '📦',
    route: 'app_product_archive',
    method: 'POST',                           // renders a <form> with CSRF token
    confirmMessage: 'Archive this product?',  // browser confirm() before submit
    condition: 'entity.status != "archived"',
    priority: 50,
)]
```

### Role-Gated Action

```php
#[AdminAction(
    name: 'impersonate',
    label: 'Login as',
    icon: '👤',
    route: 'app_impersonate',
    permission: 'ROLE_SUPER_ADMIN',
)]
```

### External Link

```php
#[AdminAction(
    name: 'stripe',
    label: 'Stripe',
    icon: '💳',
    url: 'https://dashboard.stripe.com/customers',  // static URL
    openInNewTab: true,
    condition: [StripeService::class, 'hasCustomer'],
)]
```

### Custom Button Template

```php
#[AdminAction(
    name: 'status_badge',
    label: 'Status',
    template: 'admin/row_actions/status_badge.html.twig',
    priority: 5,
)]
```

```twig
{# admin/row_actions/status_badge.html.twig #}
{# Variables: action, entity, entityShortClass #}
<span class="badge bg-{{ entity.status == 'active' ? 'success' : 'secondary' }}">
    {{ entity.status }}
</span>
```

### Combining Multiple Actions

```php
#[ORM\Entity]
#[Admin(label: 'Orders')]
#[AdminActionsConfig(exclude: ['edit'])]   // No edit — orders are immutable
#[AdminAction(
    name: 'approve',
    label: 'Approve',
    icon: '✅',
    route: 'app_order_approve',
    method: 'POST',
    confirmMessage: 'Approve this order?',
    condition: 'entity.status == "pending"',
    priority: 30,
)]
#[AdminAction(
    name: 'reject',
    label: 'Reject',
    icon: '❌',
    route: 'app_order_reject',
    method: 'POST',
    confirmMessage: 'Reject this order?',
    condition: 'entity.status == "pending"',
    cssClass: 'btn btn-sm btn-outline-danger',
    priority: 31,
)]
#[AdminAction(
    name: 'refund',
    label: 'Refund',
    icon: '↩️',
    route: 'app_order_refund',
    method: 'POST',
    condition: [OrderRefundService::class, 'canRefund'],  // DI tuple for complex check
    priority: 40,
)]
class Order { }
```
