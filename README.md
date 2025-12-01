# FRD Admin Bundle

Modern Symfony admin bundle powered by LiveComponents for managing Doctrine entities with extensive customization capabilities.

## Development

It's under development, more specifically extracting it from an existing, production application.

TODO:
- add "template to extend" in config (replace layout.html.twig)
- eliminate hardcoded App\Entity and App\Form
  - templates (entity)
  - review in code - used as default throughout
- make IDs link to "view"
FIXME:
- index of entities w/ relation
- detail exception
- Live test

## Features

- 🚀 **LiveComponent-Ready**: Built for Symfony UX LiveComponents
- 🎨 **Template Override System**: Hierarchical template resolution for easy customization
- 🔧 **Type-Based Rendering**: Smart property rendering based on Doctrine types
- 📝 **Attribute-Driven**: Modern PHP 8+ attribute configuration
- 🔍 **Filters & Search**: Built-in filtering and search capabilities
- ⚡ **Batch Operations**: Select and act on multiple entities
- 📊 **Dashboard & Menu**: Configurable admin dashboard and navigation

## Installation

```bash
composer require frd/admin-bundle
```

Enable the bundle in `config/bundles.php`:

```php
return [
    // ...
    Frd\AdminBundle\FrdAdminBundle::class => ['all' => true],
];
```

## Quick Start

### 1. Configure the Bundle

Create `config/packages/frd_admin.yaml`:

```yaml
frd_admin:
    entity_namespace: 'App\Entity\'
    form_namespace: 'App\Form\'
    route_prefix: 'admin'
```

### 2. Add Routes

In `config/routes.yaml`:

```yaml
frd_admin:
    resource: '@FrdAdminBundle/config/routes.yaml'
    prefix: /admin
```

### 3. Configure Your Entities

Use the `#[Admin]` attribute on your entities:

```php
use Frd\AdminBundle\Attribute\Admin;
use Frd\AdminBundle\Attribute\AdminRoutes;

#[Admin(
    label: 'Products',
    icon: 'inventory'
)]
#[AdminRoutes([
    'index' => 'app_product_index',
    'new' => 'app_product_new',
    'show' => 'app_product_show',
    'edit' => 'app_product_edit',
    'delete' => 'app_product_delete'
])]
class Product
{
    // ...
}
```

## Template Customization

### Override Entity Templates

Create templates in your app to override bundle templates:

```twig
{# templates/admin/product/index.html.twig #}
{% extends '@FrdAdmin/admin/index.html.twig' %}

{% block th %}
    <tr>
        <th>Name</th>
        <th>Price</th>
        <th>Stock</th>
        <th>Actions</th>
    </tr>
{% endblock %}

{% block tr %}
    <tr>
        <td>{{ entity.name }}</td>
        <td>{{ entity.price }}</td>
        <td>{{ entity.stock }}</td>
        <td>{{ block('actions') }}</td>
    </tr>
{% endblock %}
```

### Type-Specific Rendering

Customize how specific types are displayed:

```twig
{# templates/admin/types/datetime/_preview.html.twig #}
{{ value ? value|date('Y-m-d H:i') }}
```

Or for specific entity properties:

```twig
{# templates/admin/types/App/Entity/User/_preview.html.twig #}
<a href="{{ path('app_user_show', {id: value.id}) }}">
    <span class="material-icons">person</span>
    {{ value.name }}
</a>
```

## Requirements

- PHP 8.2 or higher
- Symfony 6.4 or 7.0+
- Doctrine ORM 2.0 or 3.0+

## License

MIT License - see [LICENSE](LICENSE) file for details.
