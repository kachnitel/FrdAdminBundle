# Kachnitel Admin Bundle

<!-- BADGES -->
![Tests](<https://img.shields.io/badge/tests-663%20passed-brightgreen>)
![Coverage](<https://img.shields.io/badge/coverage-75%25-yellow>)
![Assertions](<https://img.shields.io/badge/assertions-1531-blue>)
![PHPStan](<https://img.shields.io/badge/PHPStan-6-brightgreen>)
![PHP](<https://img.shields.io/badge/PHP-&gt;=8.4-777BB4?logo=php&logoColor=white>)
![Symfony](<https://img.shields.io/badge/Symfony-^6.4|^7.0|^8.0-000000?logo=symfony&logoColor=white>)
<!-- BADGES -->

A modern Symfony admin bundle powered by [LiveComponents](https://symfony.com/bundles/ux-live-component/current/index.html). Add one attribute to your entity and get a full CRUD interface with search, filters, pagination, and batch actions.

<details>
<summary><strong>Why another admin bundle?</strong></summary>
#### Motivation

I have struggled with keeping my controllers DRY as my applications grew. All my attempts at solving the issue eventually timed perfectly with [Live Components](https://symfony.com/bundles/ux-live-component/current/index.html) growing up into a mature and stable UX system. This bundle is the result of my attempts at previous reusable tables and admin generators, rebuilt on top of Live Components at its core.

While there's excellent admin bundles out there, I felt like defining their configuration replaced my controller problem with a new "configuration problem". I wanted something that was easy to get started with, non-repetitive, but also flexible enough to handle complex use cases while leaning on established patterns.

By leveraging Symfony UX, I was able to create a bundle that provides an admin interface with minimal boilerplate, while still allowing for deep customization through Twig templates and your own components.
</details>

## Quick Start

### 1. Install

```bash
composer require kachnitel/admin-bundle
```

### 2. Add attribute to any entity

```php
use Kachnitel\AdminBundle\Attribute\Admin;

#[Admin]
class Product
{
    // Your existing entity...
}
```

### 3. Visit `/admin`

Your entity appears with auto-detected columns, search, filters, and CRUD.

<details>
<summary><strong>Manual setup (if not using Symfony Flex)</strong></summary>

1. Enable the bundle in `config/bundles.php`:
```php
Kachnitel\AdminBundle\KachnitelAdminBundle::class => ['all' => true],
```

2. Import routes in `config/routes/kachnitel_admin.yaml`:
```yaml
kachnitel_admin:
    resource: '@KachnitelAdminBundle/config/routes.yaml'
    prefix: /admin
```

3. Create config in `config/packages/kachnitel_admin.yaml`:
```yaml
kachnitel_admin:
    base_layout: 'base.html.twig'  # Your app's base template
```

</details>

## What's Next?

<details>
<summary><strong>Control Your Columns</strong></summary>

**Level 1:** Auto-detection (zero config) - all properties shown automatically

**Level 2:** Specify columns and order:
```php
#[Admin(columns: ['id', 'name', 'price'])]
```
Or exclude: `excludeColumns: ['costPrice']`

**Level 3:** Role-based visibility:
```php
#[ColumnPermission('ROLE_HR')]
private float $salary;
```

**Level 4:** User-toggleable:
```php
#[Admin(enableColumnVisibility: true)]
```

**Details:** [Configuration Guide](docs/CONFIGURATION.md#column-configuration) | [Column Visibility](docs/COLUMN_VISIBILITY.md)

</details>

<details>
<summary><strong>Customize the Look</strong></summary>

**Level 1:** Use your layout:
```yaml
kachnitel_admin:
    base_layout: 'base.html.twig'
```

**Level 2:** Switch theme (Bootstrap/Tailwind):
```yaml
kachnitel_admin:
    theme: '@KachnitelAdmin/theme/tailwind.html.twig'
```

**Level 3:** Override type templates:
```
templates/bundles/KachnitelAdminBundle/types/datetime/_preview.html.twig
```

**Level 4:** Entity-specific:
```
templates/bundles/KachnitelAdminBundle/types/App/Entity/Product/price.html.twig
```

**Details:** [Template Overrides Guide](docs/TEMPLATE_OVERRIDES.md)

</details>

## Features

- **Multi-Layer Permissions** - Entity, action, and column-level control
- **Easy start** - Add `#[Admin]` to entity, auto-detects columns
- **Highly Customizable** - From [cell level templates](docs/TEMPLATE_OVERRIDES.md#common-override-scenarios) to entire layout overrides using [Symfony's Twig inheritance](https://symfony.com/doc/current/bundles/override.html#templates)
- **LiveComponent-Powered** - Real-time search, filters, and updates without page reloads
- **Column Visibility** - Show/hide columns with session or database-backed preferences
- **DataSource Abstraction** - Display data from external APIs, audit logs, or any source

## Documentation

| Guide | Description |
|-------|-------------|
| [Configuration](docs/CONFIGURATION.md) | Entity attributes and bundle config |
| [Column Visibility](docs/COLUMN_VISIBILITY.md) | Permissions and user preferences |
| [Filters](docs/FILTERS.md) | Automatic filtering and customization |
| [Template Overrides](docs/TEMPLATE_OVERRIDES.md) | Customize the admin appearance |
| [Batch Actions](docs/BATCH_ACTIONS.md) | Multi-select and bulk operations |
| [DataSource](docs/DATASOURCE.md) | Non-Doctrine data sources |
| [Assets](docs/ASSETS.md) | AssetMapper and Webpack Encore setup |
| [Development](docs/DEVELOPMENT.md) | Contributing and running tests |

<details>
<summary><strong>How does this compare to EasyAdmin?</strong></summary>

EasyAdmin and SonataAdmin use PHP configuration, while this bundle leans heavily on a single Live Component with Twig templates for customization. This allows for real-time UI updates, and separates configuration (security, columns) from presentation (templates).

**[Full comparison](docs/COMPARISON.md)** - philosophy, features, and when to choose each.

</details>

## Requirements

- PHP 8.4 or higher
- Symfony 6.4 / 7.0 / 8.0
- Doctrine ORM 3.5+

## License

MIT License - see [LICENSE](LICENSE) file for details.
