# Entity Actions

Entity actions add buttons that operate on a single entity instance. They appear in three places:

1. **Entity list rows** — the rightmost column of the `EntityList` component
2. **Show page header** — alongside the Delete button on the entity detail page
3. **Edit page header** — alongside Save and Delete on the entity edit page

The bundle ships with default **Show** and **Edit** actions; this guide explains how to add, modify, and remove them.

> **Terminology note:** Actions are called "row actions" in the code for historical reasons.
> They are not limited to list rows — the `#[AdminAction]` attribute and `RowActionProviderInterface`
> apply equally to all three contexts above.

## Table of Contents

- [Quick Start](#quick-start)
- [The `#[AdminAction]` Attribute](#the-adminaction-attribute)
- [Action Parameters](#action-parameters)
- [Conditions (Visibility)](#conditions-visibility)
- [Controlling Default Actions](#controlling-default-actions)
- [Programmatic Providers](#programmatic-providers)
- [List-Only Actions](#list-only-actions)
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

That's it. The bundle auto-discovers the attribute, evaluates the condition for each row, and renders the button in the list, on the show page, and on the edit page — alongside the default Show/Edit actions.

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
| `cssClass`       | `?string`                                | `null`       | Override default button CSS classes |
| `confirmMessage` | `?string`                                | `null`       | Show a JS `confirm()` dialog before the action fires |
| `openInNewTab`   | `bool`                                   | `false`      | Open link in a new browser tab |
| `priority`       | `int`                                    | `100`        | Sort order — lower appears first. Default: Show=10, Edit=20 |
| `method`         | `?string`                                | `null`       | HTTP method for form-based actions (`'POST'`, `'DELETE'`) |
| `template`       | `?string`                                | `null`       | Custom Twig template to render this button |
| `liveComponent`  | `?string`                                | `null`       | TwigComponent/LiveComponent name; receives `{entity}` as prop |
| `listOnly`       | `bool`                                   | `false`      | If `true`, suppress this action on show/edit pages — see [List-Only Actions](#list-only-actions) |
| `override`       | `bool`                                   | `false`      | If `true`, fully replaces an existing action with the same name instead of merging |

## Conditions (Visibility)

An action can be shown or hidden per-entity-instance using the `condition` parameter.

### String Expressions (Simple)

Uses Symfony's ExpressionLanguage with `entity` as the root variable:

```php
// Show only when status is pending
#[AdminAction(name: 'approve', condition: 'entity.status == "pending"')]

// Show only for active items
#[AdminAction(name: 'deactivate', condition: 'entity.active == true')]

// Role-based condition
#[AdminAction(name: 'impersonate', condition: 'is_granted("ROLE_SUPER_ADMIN")')]
```

PropertyAccess syntax works for nested properties:

```php
#[AdminAction(name: 'contact', condition: 'entity.owner.isActive')]
```

### DI Tuple Conditions (Complex)

For conditions that require injected services, use a `[ServiceClass::class, 'method']` tuple:

```php
#[AdminAction(
    name: 'approve',
    label: 'Approve',
    condition: [WorkflowConditionService::class, 'canApprove'],
)]
```

The referenced service must implement `RowActionConditionInterface`:

```php
use Kachnitel\AdminBundle\RowAction\RowActionConditionInterface;

class WorkflowConditionService implements RowActionConditionInterface
{
    public function __construct(private readonly WorkflowRegistry $workflows) {}

    public function canApprove(object $entity): bool
    {
        return $this->workflows->get($entity)->can($entity, 'approve');
    }
}
```

In `debug` mode, a `RuntimeException` is thrown immediately to surface misconfiguration early.

### Choosing Between Expression and DI Tuple

| Use case | Recommended |
|----------|------------|
| Simple property check | String expression |
| Role + property combined | String expression with `is_granted()` |
| Complex logic / injected services / database queries | DI tuple |
| Workflow transitions | DI tuple |

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

Without `override: true`, properties from your attribute are **merged** into the default action — only non-null values replace existing ones.

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

> **Note:** If your provider's `RowAction` objects use DI tuple conditions (the `[ServiceClass::class, 'method']` form), the referenced service must implement `RowActionConditionInterface`. See [DI Tuple Conditions](#di-tuple-conditions-complex) above.

Providers are **auto-discovered** — implement the interface and they're registered automatically via `#[AutoconfigureTag]`. No manual service configuration needed.

**Priority rules:**

| Provider | Priority | Notes |
|----------|----------|-------|
| `DefaultRowActionProvider` | 0 | Ships with the bundle — Show and Edit |
| `InlineEditRowActionProvider` | 15 | Adds inline-edit component to the Edit action (list-only) |
| `AttributeRowActionProvider` | 50 | Reads `#[AdminAction]` attributes |
| Your provider | Your choice | Higher wins on merge conflicts |

## List-Only Actions

Some actions only make sense inside an `EntityList` — specifically, liveComponent actions that
interact with the list's own LiveComponent state (e.g. the inline-edit entry button, which fires
`editRow` on the parent `EntityList` via a Stimulus data-action attribute).

Set `listOnly: true` to restrict an action to list rows. It will be **automatically suppressed**
on show and edit page headers.

```php
// Via attribute — custom liveComponent that fires actions on parent EntityList
#[AdminAction(
    name: 'quick_edit',
    label: 'Quick Edit',
    liveComponent: 'App:Admin:RowAction:QuickEdit',
    listOnly: true,
)]
class Product { }

// Via programmatic provider
new RowAction(
    name: 'quick_edit',
    label: 'Quick Edit',
    liveComponent: 'App:Admin:RowAction:QuickEdit',
    listOnly: true,
)
```

Actions **without** `listOnly` — including liveComponent actions — render in all three contexts:
list rows, show page header, and edit page header. This allows components like status-change
modals or confirmation dialogs to appear wherever the entity is displayed.

### How the Bundle Uses `listOnly`

The `InlineEditButton` component is registered with `listOnly: true` by `InlineEditRowActionProvider`
because it fires `editRow` on the parent `EntityList` LiveComponent via Stimulus — a parent that
does not exist on show/edit pages. The plain-link Edit action from `DefaultRowActionProvider`
(with `listOnly: false`) is preserved and continues to appear on show/edit page headers.

### merge() semantics

When two providers register actions with the same name, they are merged (unless `override: true`).
`listOnly` uses OR semantics: if either action in the merge marks it as `listOnly: true`, the result
is list-only. Use `override: true` to fully replace an action and reset `listOnly` to `false`.

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

`admin_visible_row_actions` is what the default `_RowActions.html.twig` partial and the
show/edit page header loops use. The show/edit templates additionally filter `action.listOnly`.

## Examples

### Status workflow with condition

```php
#[ORM\Entity]
#[Admin(label: 'Support Tickets')]
#[AdminAction(name: 'close',    label: 'Close',    icon: '🔒', route: 'app_ticket_close',
    condition: 'entity.status != "closed"')]
#[AdminAction(name: 'escalate', label: 'Escalate', icon: '🔺', route: 'app_ticket_escalate',
    condition: 'entity.priority < 3')]
class Ticket { }
```

### POST action with confirmation

```php
#[AdminAction(
    name: 'publish',
    label: 'Publish',
    icon: '🌐',
    route: 'app_post_publish',
    method: 'POST',
    confirmMessage: 'Publish this post? It will be visible to all users.',
    condition: 'entity.status == "draft"',
)]
```

### Custom liveComponent action (renders everywhere)

A liveComponent action without `listOnly` renders in list rows **and** on show/edit page headers.
Use this for self-contained components like modal dialogs that don't interact with the list state:

```php
#[AdminAction(
    name: 'reassign',
    label: 'Reassign',
    icon: '👤',
    liveComponent: 'App:Admin:RowAction:ReassignModal',
    // listOnly not set (defaults to false) — modal appears on show/edit pages too
)]
class Task { }
```

### List-only liveComponent action

Set `listOnly: true` when the component depends on the parent `EntityList` LiveComponent:

```php
#[AdminAction(
    name: 'quick_preview',
    label: 'Preview',
    icon: '👁',
    liveComponent: 'App:Admin:RowAction:QuickPreview',
    listOnly: true,  // fires live actions on parent EntityList — suppress on show/edit
)]
class Product { }
```

### Override the default Edit to add a condition without losing the voter check

```php
// Adds condition while keeping ADMIN_EDIT voter check
#[AdminAction(name: 'edit', label: 'Edit', condition: '!entity.isLocked')]
```

### Level 4: Remove or replace default Show/Edit

```php
#[AdminActionsConfig(exclude: ['edit'])]
#[AdminAction(name: 'show', label: 'Preview', icon: '🔍', route: 'app_preview', override: true)]
```
