# Comparison with Other Admin Bundles

## Philosophy: Twig vs PHP Configuration

The key difference between Kachnitel Admin and bundles like EasyAdmin or SonataAdmin is **how you customize**.

### EasyAdmin: PHP Configuration DSL

EasyAdmin uses fluent PHP methods for everything - layout, templates, fields:

```php
// EasyAdmin: PHP config for layout
public function configureCrud(Crud $crud): Crud
{
    return $crud
        ->renderContentMaximized()
        ->renderSidebarMinimized()
        ->overrideTemplate('crud/field/id', 'admin/fields/my_id.html.twig')
        ->setFormThemes(['my_theme.html.twig']);
}
```

### Kachnitel Admin: Standard Symfony Patterns

Kachnitel Admin uses Symfony's native extension points - Twig template inheritance and security voters:

```twig
{# templates/admin/products.html.twig - just embed the component #}
{% extends 'base.html.twig' %}

{% block content %}
    <h1>Product Management</h1>
    <twig:K:Admin:EntityList className="App\Entity\Product" shortClassName="Product" />
{% endblock %}
```

```
# Override any template using standard Symfony bundle overrides
templates/bundles/KachnitelAdminBundle/types/datetime/_preview.html.twig
```

## Quick Comparison

| Aspect | Kachnitel Admin | EasyAdmin | SonataAdmin |
|--------|-----------------|-----------|-------------|
| **Config location** | Entity attributes | Controller classes | Admin classes |
| **View customization** | Twig inheritance | PHP `overrideTemplate()` | PHP + Twig |
| **Layout control** | Embed component anywhere | PHP `renderContentMaximized()` | Admin class config |
| **Learning curve** | Know Symfony = know this | Learn EasyAdmin DSL | Learn Sonata patterns |
| **UI updates** | LiveComponents (real-time) | Page reloads | Page reloads |

## What This Means in Practice

### Custom Layout

**EasyAdmin:** Configure via PHP, limited to predefined options
```php
$crud->renderContentMaximized()->renderSidebarMinimized();
```

**Kachnitel Admin:** Full Twig control - put the component wherever you want
```twig
<div class="my-custom-layout">
    <aside>{% include 'sidebar.html.twig' %}</aside>
    <main>{{ component('EntityList', { dataSourceId: 'product' }) }}</main>
</div>
```

### Custom Field Rendering

**EasyAdmin:** Override via PHP method
```php
$crud->overrideTemplate('crud/field/id', 'admin/fields/my_id.html.twig');
```

**Kachnitel Admin:** Standard Symfony bundle override
```
templates/bundles/KachnitelAdminBundle/types/integer/_preview.html.twig
```

### Permissions

**EasyAdmin:** Manual implementation in controllers
```php
public function configureActions(Actions $actions): Actions
{
    return $actions->setPermission(Action::DELETE, 'ROLE_ADMIN');
}
```

**Kachnitel Admin:** Attributes on entity + Symfony voters
```php
#[Admin(permissions: ['delete' => 'ROLE_ADMIN'])]
class User
{
    // ...
    private string $email;

    #[ColumnPermission('ROLE_HR')]
    private float $salary;
    // ...
```

## Feature Matrix

| Feature | Kachnitel | EasyAdmin | Sonata |
|---------|-----------|-----------|--------|
| Zero-config start | `#[Admin]` attribute | Dashboard + Controller | Admin class |
| Column permissions | Built-in attribute | Manual | Manual |
| Non-Doctrine data | DataSourceInterface | Doctrine only | Doctrine only |
| Real-time UI | LiveComponents | No | No |
| User column visibility | Built-in | No | No |
| Batch actions | Delete (extensible) | Custom operations | Rich system |
| Themes | Bootstrap / Tailwind | Custom theme | AdminLTE |
| Field types | Auto-detected | 30+ types | Many types |

## When to Choose Each

**Kachnitel Admin** if you:
- Want minimal boilerplate (one attribute to start)
- Prefer Twig for view customization
- Need real-time UI without page reloads
- Have non-Doctrine data sources (APIs, audit logs)
- Want column-level permissions

**EasyAdmin** if you:
- Prefer PHP-based configuration
- Need extensive field type library
- Want large community and ecosystem
- Are on PHP 8.1-8.3

**SonataAdmin** if you:
- Need enterprise-grade permissions (OWNER, OPERATOR levels)
- Want extensive batch action capabilities
- Have existing Sonata ecosystem investment
