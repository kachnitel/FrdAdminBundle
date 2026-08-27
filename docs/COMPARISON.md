# Comparison with Other Admin Bundles

Kachnitel Admin, [EasyAdmin](https://github.com/EasyCorp/EasyAdminBundle), and [SonataAdminBundle](https://github.com/sonata-project/SonataAdminBundle) can all produce a working CRUD admin for a Symfony app. They differ mainly in where configuration lives and how much code each entity needs.

> Kachnitel Admin is production-tested but still an early release, maintained by one person. EasyAdmin and SonataAdmin are mature, multi-contributor projects with years of production use across many deployments — weigh that difference in track record alongside the comparisons below.

## Configuration

Kachnitel Admin marks an entity with a single attribute:

```php
#[Admin(label: 'Products')]
class Product { }
```

EasyAdmin and SonataAdmin instead require a dedicated controller/admin class per entity, configured through a fluent PHP API.

| | Kachnitel Admin | EasyAdmin | SonataAdmin |
|---|---|---|---|
| Config location | Entity attributes | `CrudController` per entity | `Admin` class per entity + service tag |
| New PHP class per entity | 0 | 1 | 1 |
| View customization | Twig template inheritance | PHP `overrideTemplate()` | PHP Mappers + Twig |
| Permissions | Attributes + Symfony voters, incl. per-column | Manual, per controller | Manual, per Admin class |

## Auto-Generated Forms

Kachnitel Admin generates a full create/edit form for any Doctrine entity — scalars, `ManyToOne`/`OneToOne` as autocomplete selects, `ManyToMany` as multi-select, `OneToMany` as add/remove row collections — via [`kachnitel/dynamic-form-bundle`](https://github.com/kachnitel/dynamic-form-bundle), with no form code required. A hand-written `FormType` takes priority whenever one exists. See [Forms](FORMS.md).

EasyAdmin auto-detects scalar fields the same way when `configureFields()` is omitted, but associations beyond a simple `ManyToOne` usually need an explicit `AssociationField`/`CollectionField`. SonataAdmin has no zero-config path — `configureFormFields()` is required for every field, associations included.

## Feature Matrix

| Feature | Kachnitel Admin | EasyAdmin | SonataAdmin |
|---|---|---|---|
| Real-time list/form updates (no reload) | ✅ LiveComponents | ❌ | ❌ |
| [In-list (inline) editing](INLINE_EDIT.md) | ✅ | ❌ | ❌ |
| [Archive / soft-delete toggle](ARCHIVE.md) | ✅ attribute-driven | ❌ | ❌ |
| [Non-Doctrine data sources](DATASOURCE.md) | ✅ `DataSourceInterface` | ❌ | ✅ separate Model Manager per store |
| [Column-level permissions](COLUMN_VISIBILITY.md#per-column-permissions) | ✅ `#[ColumnPermission]` | Manual | Manual |
| [Composite / grouped columns](COMPOSITE_COLUMNS.md) | ✅ | ❌ | ❌ |
| [User-toggleable column visibility](COLUMN_VISIBILITY.md#user-column-visibility-toggle) | ✅ | ❌ | ❌ |
| [Batch actions](BATCH_ACTIONS.md) | ✅ Built-in delete/archive + custom | ✅ Built-in delete + custom | ✅ Rich, long-standing built-in system |
| Object-level (per-row) ACL | ❌ | ❌ | ✅ OWNER/MASTER/OPERATOR-style |
| Built-in field/widget types | ~12, plus constraint/naming-based guessing | 30+ explicit `Field` classes | Symfony Form types directly |
| INTL / i18n support | ❌ | ✅ via `trans()` | ✅ via `trans()` |

A ❌ means no first-party feature — EasyAdmin and Sonata users commonly cover some of these with third-party packages (e.g. Gedmo `SoftDeleteable` for archiving) or custom code instead.

## Requirements

| | Kachnitel Admin | EasyAdmin 5.x | SonataAdmin 4.x |
|---|---|---|---|
| PHP | 8.4+ | 8.2+ | 8.2+ |
| Doctrine ORM | 3.5+ only | 2.x+ | via separate `doctrine-orm-admin-bundle` |
| License | MIT (forms engine is MPL-2.0) | MIT | MIT |
| Maintainers / stability | Single maintainer, stable (1.0) | EasyCorp + contributors, stable | `sonata-project` + community, stable |

EasyAdmin 4.x still supports PHP 8.1+ / Symfony 5.4+ for older projects; new features land in 5.x only.

## When to Choose Each

**Kachnitel Admin** if you:
- Want zero per-entity PHP classes and auto-generated forms, associations included
- Prefer Twig template inheritance over a PHP configuration DSL
- Need real-time list/form updates or non-Doctrine data sources
- Can work within PHP 8.4+ and Doctrine ORM 3.5+

**EasyAdmin** if you:
- Prefer PHP-based configuration (`configureFields()`, `configureCrud()`)
- Want the largest built-in field-type library and plugin ecosystem
- Need Doctrine ORM 2.x or a PHP floor below 8.4
- Want a mature, widely-deployed bundle with a large contributor base

**SonataAdmin** if you:
- Need object-level (per-row) permissions — OWNER/MASTER/OPERATOR-style ACL
- Are already building on the Sonata ecosystem (media/user/block/page bundles)
- Want the most mature batch-action system available
- Can swap the Model Manager bundle for a non-Doctrine data layer

---

**See also:** [Configuration](CONFIGURATION.md) · [Forms](FORMS.md) · [Filters](FILTERS.md) · [Inline Editing](INLINE_EDIT.md) · [Archive](ARCHIVE.md) · [DataSource](DATASOURCE.md)
