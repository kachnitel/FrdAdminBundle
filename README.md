# Kachnitel Admin Bundle

<!-- BADGES -->
![Tests](<https://img.shields.io/badge/tests-533%20passed-brightgreen>)
![Coverage](<https://img.shields.io/badge/coverage-68%25-yellow>)
![Assertions](<https://img.shields.io/badge/assertions-1206-blue>)
![PHPStan](<https://img.shields.io/badge/PHPStan-6-brightgreen>)
![PHP](<https://img.shields.io/badge/PHP-&gt;=8.2-777BB4?logo=php&logoColor=white>)
![Symfony](<https://img.shields.io/badge/Symfony-^6.4|^7.0|^8.0-000000?logo=symfony&logoColor=white>)
<!-- BADGES -->

### Modern Symfony admin bundle for managing entities

- Powered by [Symfony's LiveComponents](https://symfony.com/bundles/ux-live-component/current/index.html)
- Minimal config
- Extensive customization capabilities
- Requires no front end libraries beyond Live Components

## Documentation

- **[Configuration Guide](docs/CONFIGURATION.md)** - Configure entities with the `#[Admin]` attribute
- **[DataSource Abstraction](docs/DATASOURCE.md)** - Display non-Doctrine data sources in the admin
- **[Column Filtering](docs/FILTERS.md)** - Automatic per-column filters and customization
- **[Batch Actions Setup](docs/BATCH_ACTIONS.md)** - Enable multi-select and bulk operations
- **[Asset Management](docs/ASSETS.md)** - AssetMapper & Webpack Encore setup for Stimulus controllers
- **[Template Overrides Guide](docs/TEMPLATE_OVERRIDES.md)** - Customize the admin interface appearance
- **[Development Guide](docs/DEVELOPMENT.md)** - Running tests, code quality, and contributing

## Features

- 🚀 **LiveComponent-Ready**: Built for Symfony UX LiveComponents
- 🎨 **Template Override System**: Hierarchical template resolution for easy customization
- 🔧 **Type-Based Rendering**: Smart property rendering based on Doctrine types
- 📝 **Attribute-Driven**: Modern PHP 8+ attribute configuration
- 🔍 **Filters & Search**: Built-in filtering and search capabilities
- ⚡ **Batch Operations**: Multi-select with Shift/Ctrl+Click and bulk delete
- 📊 **Dashboard & Menu**: Configurable admin dashboard and navigation
- 🔌 **DataSource Abstraction**: Display data from external APIs, audit logs, or any source

## 🏗️ Installation & Quick Start

### 1. Installation

```bash
composer require kachnitel/admin-bundle
```

The bundle is registered automatically by **Symfony Flex**. If not using Flex, enable the bundle manually in `config/bundles.php`:

```php
return [
    // ...
    Kachnitel\AdminBundle\KachnitelAdminBundle::class => ['all' => true],
];
```

### 2. Configure the Bundle

Create the bundle configuration file:

```yaml
# config/packages/kachnitel_admin.yaml
kachnitel_admin:
    base_layout: 'base.html.twig'  # Your app's base template
    required_role: 'ROLE_ADMIN'    # Role required to access admin (null for no restriction)
```

> **Note:** The `kachnitel_admin:` key **must** be present for the bundle to load. Use `kachnitel_admin: ~` for all defaults.

See the [Configuration Guide](docs/CONFIGURATION.md) for all available options.

### 3. Add Routes

Import the bundle's routes:

```yaml
# config/routes/kachnitel_admin.yaml
kachnitel_admin:
    resource: '@KachnitelAdminBundle/config/routes.yaml'
    prefix: /admin
```

### 4. Mark Entities with #[Admin] Attribute

Add the `#[Admin]` attribute to any Doctrine entity:

```php
use Kachnitel\AdminBundle\Attribute\Admin;

#[Admin(label: 'Products', icon: 'inventory')]
class Product
{
    // ...
}
```

That's it! The entity will now appear in the admin dashboard at `/admin`.

### 5. Enable Batch Actions (Optional)

For multi-select and batch delete functionality, you'll need to:

1. Enable batch actions on your entity:
   ```php
   #[Admin(label: 'Products', enableBatchActions: true)]
   class Product { /* ... */ }
   ```

2. Register the Stimulus controller in your asset configuration

**→ See the [Batch Actions Setup Guide](docs/BATCH_ACTIONS.md) for complete setup instructions.**

---

## 📋 Installation Summary

| Step | Automated by Flex | Manual Setup Required |
|------|-------------------|----------------------|
| Bundle registration (`bundles.php`) | ✅ Yes | Only if not using Flex |
| Config file (`kachnitel_admin.yaml`) | ✅ Creates template | Customize `base_layout`, `required_role` |
| Routes import | ✅ Creates file | Adjust prefix if needed |
| Entity `#[Admin]` attribute | ❌ No | Add to each entity |
| Security/access control | ❌ No | Configure in `security.yaml` |
| Batch actions Stimulus controller | ❌ No | Manual registration in `controllers.json` |
| Template overrides | ❌ No | Create in `templates/bundles/KachnitelAdminBundle/` |
| Form types for edit/new | ❌ No | Create form classes |

For template customization, see the [Template Overrides Guide](docs/TEMPLATE_OVERRIDES.md).

## 🛠️ Core Technology Stack

Kachnitel Admin is built purely on the established **Symfony stack**.

It relies only on **Symfony**, **Doctrine ORM**, and **Live Components** (part of the Symfony UX initiative) for all functionality and interactivity. This approach ensures the administration interface is built using server-side PHP without requiring any external JavaScript frameworks or frontend libraries.

> **DataSource Abstraction:** While Doctrine entities work out-of-the-box with the `#[Admin]` attribute, the bundle also supports custom data sources via the `DataSourceInterface`. This allows displaying data from external APIs, audit logs, or other non-Doctrine sources using the same admin UI. See the [DataSource Guide](docs/DATASOURCE.md) for details.

## Requirements

- PHP 8.2 or higher
- Symfony 6.4 or 7.0+
- Doctrine ORM 2.0 or 3.0+

## License

MIT License - see [LICENSE](LICENSE) file for details.
