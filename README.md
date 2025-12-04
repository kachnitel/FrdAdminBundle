# FRD Admin Bundle

Modern Symfony admin bundle powered by LiveComponents for managing Doctrine entities with extensive customization capabilities.

## Documentation

- **[Configuration Guide](docs/CONFIGURATION.md)** - Complete guide to configuring entities with the `#[Admin]` attribute
- **[Template Overrides Guide](docs/TEMPLATE_OVERRIDES.md)** - How to customize the admin interface appearance

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

### 3. Mark Entities with #[Admin] Attribute

Add the `#[Admin]` attribute to any Doctrine entity:

```php
use Frd\AdminBundle\Attribute\Admin;

#[Admin(label: 'Products', icon: 'inventory')]
class Product
{
    // ...
}
```

That's it! The entity will now appear in the admin dashboard.

For advanced configuration (columns, permissions, pagination, etc.), see the [Configuration Guide](docs/CONFIGURATION.md).

For template customization, see the [Template Overrides Guide](docs/TEMPLATE_OVERRIDES.md).

## Requirements

- PHP 8.2 or higher
- Symfony 6.4 or 7.0+
- Doctrine ORM 2.0 or 3.0+

## License

MIT License - see [LICENSE](LICENSE) file for details.
