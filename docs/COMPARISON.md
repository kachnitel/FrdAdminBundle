# Comparison with Other Admin Bundles

The three most common ways to add an admin backend to a Symfony app — Kachnitel Admin, [EasyAdmin](https://github.com/EasyCorp/EasyAdminBundle), and [SonataAdminBundle](https://github.com/sonata-project/SonataAdminBundle) — differ less in *what* they can do and more in *where configuration lives* and *how much you write per entity*. This page covers philosophy, features, and when to reach for each.

> **Note:** this bundle is pre-1.0, unstable, actively-developed software maintained by single developer — see [Requirements & Maturity](#requirements--maturity). EasyAdmin and SonataAdmin are both mature, widely-deployed projects with large contributor bases. Weigh that alongside the feature comparisons below.

## Table of Contents

- [Philosophy: Where Configuration Lives](#philosophy-where-configuration-lives)
- [Zero-Boilerplate Comparison](#zero-boilerplate-comparison)
- [Auto-Generated Forms](#auto-generated-forms)
- [Quick Comparison](#quick-comparison)
- [What This Means in Practice](#what-this-means-in-practice)
- [Feature Matrix](#feature-matrix)
- [Requirements & Maturity](#requirements--maturity)
- [When to Choose Each](#when-to-choose-each)

## Philosophy: Where Configuration Lives

### EasyAdmin: a PHP DSL, one controller per entity

Every entity gets a `CrudController`. Layout, fields, and template overrides are all configured with fluent PHP methods:

```php
class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->renderContentMaximized()
            ->overrideTemplate('crud/field/id', 'admin/fields/my_id.html.twig')
            ->setFormThemes(['my_theme.html.twig']);
    }
}
```

### SonataAdmin: a PHP DSL, one Admin class per entity

Every entity gets an `Admin` class registered as a service. List columns, filters, and form fields are each configured through their own `Mapper`:

```php
final class ProductAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form->add('name')->add('price');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list->add('name')->add('price');
    }
}
```
```yaml
# config/services.yaml
App\Admin\ProductAdmin:
    tags:
        - { name: sonata.admin, manager_type: orm, label: Product }
```

### Kachnitel Admin: Entity attribute

```php
#[ORM\Entity]
#[Admin(label: 'Products')]
class Product
{
    // ...
}
```

Visit `/admin/product` — list, filters, sorting, and a fully generated create/edit form already exist, discovered at runtime by `GenericAdminController`. Customization uses standard Symfony extension points instead of a config DSL — Twig template inheritance for views, security voters for permissions:

```twig
{# templates/admin/products.html.twig — embed the component anywhere #}
{% extends 'base.html.twig' %}

{% block content %}
    <h1>Product Management</h1>
    <twig:K:Admin:EntityList entityShortClass="Product" />
{% endblock %}
```

```
# Override any template using standard Symfony bundle overrides
templates/bundles/KachnitelAdminBundle/types/datetime/_preview.html.twig
```

## Zero-Boilerplate Comparison

| | Kachnitel Admin | EasyAdmin | SonataAdmin |
|---|---|---|---|
| Minimum to get list + CRUD for one entity | `#[Admin]` attribute | `CrudController` class (`extends AbstractCrudController`) | `Admin` class (`extends AbstractAdmin`) + service tag |
| New PHP class required per entity | 0 | 1 | 1 |
| Scaffolding command | — (the attribute is enough) | `make:admin:crud` | none in the core bundle |
| Create/edit forms | Generated automatically, incl. relations & collections | Auto-detected scalar fields; relations usually explicit | Always explicit |

## Auto-Generated Forms

This is the largest gap between the three. Kachnitel Admin generates a full create/edit form for **any** Doctrine entity with zero form code, via the standalone [`kachnitel/dynamic-form-bundle`](https://github.com/kachnitel/dynamic-form-bundle) package:

```php
#[Admin(label: 'Orders')]
class Order
{
    #[ORM\Column]
    private string $reference = '';

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    private Collection $tags;   // → multi-select autocomplete

    #[ORM\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    private Collection $lines;  // → add/remove rows (LiveCollectionType)
}
```

Doctrine `string`/`int`/`date`/`enum`/… fields get the matching Symfony form type automatically; `ManyToOne`/`OneToOne` become autocomplete selects; `ManyToMany` becomes a multi-select; `OneToMany` becomes an add/remove row collection, recursively. Drop in a hand-written `FormType` for any entity at any time — it always takes priority over the generated one. See [Forms](FORMS.md) and [dynamic-form-bundle's own docs](https://github.com/kachnitel/dynamic-form-bundle/tree/master/docs) for the full mapping rules and escape hatches.

EasyAdmin auto-detects scalar properties the same way when `configureFields()` is left unimplemented, but each entity still needs its own `CrudController`, and anything beyond a simple `ManyToOne` typically needs an explicit `AssociationField`/`CollectionField`. SonataAdmin has no zero-config path here at all — `configureFormFields()` is required, associations included.

## Quick Comparison

| Aspect | Kachnitel Admin | EasyAdmin | SonataAdmin |
|---|---|---|---|
| Config location | Entity attributes | `CrudController` (PHP DSL) | `Admin` class (PHP DSL) |
| Boilerplate per entity | None | One controller class | One Admin class + service tag |
| View customization | Twig template inheritance | PHP `overrideTemplate()` / field `setTemplatePath()` | PHP Mappers + Twig |
| Layout control | Embed the component anywhere | PHP `renderContentMaximized()` etc. | Admin class + block/dashboard config |
| List interactions (search/filter/sort/page) | AJAX via LiveComponent, no reload | Full page navigation | Full page navigation |
| Learning curve | Know Symfony = know this | Learn EasyAdmin's Field/Crud DSL | Learn Sonata's Mapper/Admin patterns |

## What This Means in Practice

### Custom Layout

**EasyAdmin:** configured via PHP, limited to the options `Crud` exposes:
```php
$crud->renderContentMaximized()->renderSidebarMinimized();
```

**Kachnitel Admin:** full Twig control — put the component wherever you want, styled however you want:
```twig
<div class="my-custom-layout">
    <aside>{% include 'sidebar.html.twig' %}</aside>
    <main>{{ component('K:Admin:EntityList', { entityClass: 'App\\Entity\\Product', entityShortClass: 'Product' }) }}</main>
</div>
```

### Custom Field Rendering

**EasyAdmin:** override via a PHP method call:
```php
$crud->overrideTemplate('crud/field/id', 'admin/fields/my_id.html.twig');
```

**Kachnitel Admin:** a standard Symfony bundle template override, no PHP touched:
```
templates/bundles/KachnitelAdminBundle/types/integer/_preview.html.twig
```

### Permissions

**EasyAdmin:** implemented manually per controller:
```php
public function configureActions(Actions $actions): Actions
{
    return $actions->setPermission(Action::DELETE, 'ROLE_ADMIN');
}
```

**Kachnitel Admin:** attributes on the entity, backed by a Symfony voter — including per column:
```php
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

#[Admin(permissions: ['delete' => 'ROLE_ADMIN'])]
class User
{
    private string $email;

    #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR'])]
    private float $salary;
}
```

### Editing In-Place

Neither EasyAdmin nor SonataAdmin ships in-list editing — both need a hand-rolled Ajax action. Kachnitel Admin turns it on per entity, then narrows it per column:
```php
#[Admin(label: 'Products', enableInlineEdit: true)]
class Product
{
    #[AdminColumn(editable: false)]
    private string $sku = '';   // opt this one column back out
}
```
See [Inline Editing](INLINE_EDIT.md).

### Archive / Soft-Delete Toggle

Neither bundle has a built-in equivalent — the usual approach is a hand-written Doctrine filter, or a package like Gedmo's `SoftDeleteable`. Kachnitel Admin adds a show/hide toggle from one attribute value, with no query code:
```php
#[Admin(label: 'Orders', archiveExpression: 'item.deletedAt')]
class Order
{
    private ?\DateTimeImmutable $deletedAt = null;
}
```
See [Archive](ARCHIVE.md).

## Feature Matrix

| Feature | Kachnitel Admin | EasyAdmin | SonataAdmin |
|---|---|---|---|
| Zero-config CRUD (no controller class) | ✅ | ❌ | ❌ |
| Auto-generated forms, incl. associations/collections | ✅ | Scalars only | ❌ |
| Column-level permissions | ✅ `#[ColumnPermission]` | Manual | Manual |
| Non-Doctrine data sources | ✅ see `DataSourceInterface` | ❌ Doctrine ORM only | Partial — separate Model Manager bundle per store |
| Real-time list/form updates (no reload) | ✅ LiveComponents | ❌ | ❌ (Stimulus for UI polish only) |
| In-list (inline) editing | ✅ | ❌ | ❌ |
| Archive / soft-delete toggle | ✅ | ❌ | ❌ |
| Inline "+ Add" for related entities | ✅ modal dialog | ❌ | Partial, via embedded admin forms |
| Composite / grouped columns | ✅ `#[AdminColumn(group:)]` | ❌ | ❌ |
| User-toggleable column visibility | ✅ | ❌ | ❌ |
| Batch actions | Built-in delete/archive + attribute- or provider-based custom actions | Built-in delete + custom via `Actions` config | Rich, long-standing built-in system |
| Object-level (per-row) ACL | ❌ | ❌ | ✅ optional ACL handler (VIEW/EDIT/OPERATOR/MASTER/OWNER) |
| Built-in field/widget types | ~12 auto-detected + constraint- or naming-based type guessing | 30+ explicit `Field` classes | Symfony Form types directly |
| Themes | Bootstrap / Tailwind (Twig macros) | Built-in design system, light/dark | AdminLTE-based skins |

## Requirements & Maturity

| | Kachnitel Admin | EasyAdmin (5.x) | SonataAdmin (4.x) |
|---|---|---|---|
| PHP | 8.4+ | 8.2+ | 8.2+ |
| Symfony | 6.4 / 7.x / 8.x | 6.4 / 7.x / 8.x | 6.4 / 7.x / 8.x |
| Doctrine ORM | 3.5+ only | 2.x and higher | via a separate `doctrine-orm-admin-bundle` |
| License | MIT | MIT | MIT |
| Stability | **Pre-1.0** — no semver releases yet, breaking changes expected | Stable | Stable |
| Maintainers | Single maintainer | EasyCorp + 500+ contributors | `sonata-project` org + community |

*EasyAdmin 4.x is still maintained with bug fixes and supports PHP 8.1+ / Symfony 5.4+ if you need that older floor; new features land in 5.x only.*

*Kachnitel Admin's forms engine, [`kachnitel/dynamic-form-bundle`](https://github.com/kachnitel/dynamic-form-bundle), is MPL-2.0 rather than this bundle's own MIT — a file-level copyleft, compatible with closed-source use. See [Forms → license note](FORMS.md) for what that actually requires.*

If your project needs Doctrine ORM 2.x, or a PHP version below 8.4, Kachnitel Admin isn't an option yet — EasyAdmin or SonataAdmin are.

## When to Choose Each

**Kachnitel Admin** if you:
- Want the least boilerplate possible — one attribute, no controller, per entity
- Want create/edit forms — including relations and collections — generated for free
- Prefer Twig template inheritance over a PHP fluent configuration DSL
- Need list/form interactions to update without full page reloads
- Have non-Doctrine data (APIs, audit logs, Redis) you want in the same UI as your entities
- Want column-level permissions and in-place list editing without building them yourself
- Are comfortable with PHP 8.4+, Doctrine ORM 3.5+, and actively-changing, pre-1.0 software

**EasyAdmin** if you:
- Prefer PHP-based configuration (`configureFields()`, `configureCrud()`, `configureActions()`)
- Want the largest built-in field-type library and the biggest plugin ecosystem
- Need Doctrine ORM 2.x compatibility, or a PHP floor below 8.4
- Want a mature, extremely widely-deployed bundle with a large contributor base

**SonataAdmin** if you:
- Need object-level (per-row, per-user) permissions — an ACL system with OWNER/MASTER/OPERATOR-style granularity
- Are already building on the wider Sonata ecosystem (media, user, block, page bundles)
- Want the most mature, feature-complete batch-action system available for a Symfony admin
- Can swap the Model Manager bundle if your data layer isn't Doctrine ORM

---

**See also:** [Configuration](CONFIGURATION.md) · [Forms](FORMS.md) · [Filters](FILTERS.md) · [Inline Editing](INLINE_EDIT.md) · [Archive](ARCHIVE.md) · [DataSource](DATASOURCE.md)
