# Kachnitel Admin Bundle

<!-- BADGES -->
![Tests](<https://img.shields.io/badge/tests-84%20passed-brightgreen>)
![Coverage](<https://img.shields.io/badge/coverage-43%25-red>)
![Assertions](<https://img.shields.io/badge/assertions-302-blue>)
![PHPStan](<https://img.shields.io/badge/PHPStan-5-brightgreen>)
![PHP](<https://img.shields.io/badge/PHP-&gt;=8.2-777BB4?logo=php&logoColor=white>)
![Symfony](<https://img.shields.io/badge/Symfony-^6.4|^7.0-000000?logo=symfony&logoColor=white>)
<!-- BADGES -->

### Modern Symfony admin bundle for managing entities

- Powered by [Symfony's LiveComponents](https://symfony.com/bundles/ux-live-component/current/index.html)
- Minimal config
- Extensive customization capabilities
- Requires no front end libraries beyond Live Components

## Documentation

- **[Configuration Guide](docs/CONFIGURATION.md)** - Complete guide to configuring entities with the `#[Admin]` attribute
- **[Template Overrides Guide](docs/TEMPLATE_OVERRIDES.md)** - How to customize the admin interface appearance
- **[Development Guide](docs/DEVELOPMENT.md)** - Information on running tests, code quality, and contributing

## Features

- 🚀 **LiveComponent-Ready**: Built for Symfony UX LiveComponents
- 🎨 **Template Override System**: Hierarchical template resolution for easy customization
- 🔧 **Type-Based Rendering**: Smart property rendering based on Doctrine types
- 📝 **Attribute-Driven**: Modern PHP 8+ attribute configuration
- 🔍 **Filters & Search**: Built-in filtering and search capabilities
- ⚡ **Batch Operations**: Select and act on multiple entities
- 📊 **Dashboard & Menu**: Configurable admin dashboard and navigation

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

### 2. Configure the Bundle (Minimum Config)

This bundle is designed to be **minimum-config**. The minimal required configuration is simply the bundle key in a YAML file:

```yaml
# config/packages/kachnitel_admin.yaml
kachnitel_admin: ~
```

The entry `kachnitel_admin: ~` is the **minimum required configuration** and **must** be present in `config/packages/kachnitel_admin.yaml` (or an equivalent config file) for the bundle to load its default services and settings. The configuration file itself cannot be empty or missing for the bundle to function correctly.

See the [Configuration Guide](docs/CONFIGURATION.md) for further details.

### 3. Add Routes

In `config/routes.yaml`:

```yaml
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

That's it! The entity will now appear in the admin dashboard.

For advanced configuration (columns, permissions, pagination, etc.), see the [Configuration Guide](docs/CONFIGURATION.md).

For template customization, see the [Template Overrides Guide](docs/TEMPLATE_OVERRIDES.md).

## 🛠️ Core Technology Stack

Kachnitel Admin is built purely on the established **Symfony stack**.

It relies only on **Symfony**, **Doctrine ORM**, and **Live Components** (part of the Symfony UX initiative) for all functionality and interactivity. This approach ensures the administration interface is built using server-side PHP without requiring any external JavaScript frameworks or frontend libraries.

> **Note on Doctrine:** While currently dependent on Doctrine ORM, the goal is to decouple this dependency in a future release to allow for integration with other persistence layers/ORMs.

## Requirements

- PHP 8.2 or higher
- Symfony 6.4 or 7.0+
- Doctrine ORM 2.0 or 3.0+

## License

MIT License - see [LICENSE](LICENSE) file for details.
