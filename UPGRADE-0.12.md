# Upgrade Guide

## Upgrading to the Dynamic Form / Entity Components Release

This release makes two sibling packages hard dependencies of `kachnitel/admin-bundle`:

- **[`kachnitel/dynamic-form-bundle`](https://github.com/kachnitel/dynamic-form-bundle)**
  (MPL-2.0) — `DynamicEntityFormType`, Doctrine → Symfony form-type mapping,
  association/collection handling for auto-generated New/Edit forms
- **[`kachnitel/entity-components-bundle`](https://github.com/kachnitel/entity-components-bundle)**
  — inline-edit field component base classes

> **License note:** `kachnitel/dynamic-form-bundle` is MPL-2.0 — file-level
> copyleft, not the same as this bundle's MIT license, but compatible with
> closed-source use. See its own README for what that actually requires.

---

### 1. Register the two new bundles

In `config/bundles.php`:

```php
return [
    // ...
    Kachnitel\DynamicFormBundle\KachnitelDynamicFormBundle::class => ['all' => true],
    Kachnitel\EntityComponentsBundle\KachnitelEntityComponentsBundle::class => ['all' => true],
    Kachnitel\AdminBundle\KachnitelAdminBundle::class => ['all' => true],
];
```

If installed via Symfony Flex, `composer update kachnitel/admin-bundle` may pull
these in and register them via their own recipes — **confirm `config/bundles.php`
either way**. A missing registration fails at container-compile time with an
unresolved-service error, not a message pointing at the actual cause.

---

### 2. `GenericAdminController::dashboard()` / `dataSourceIndex()` / `dataSourceShow()` moved

These moved to a new `Kachnitel\AdminBundle\Controller\DataSourceController`.
**Routes are unchanged** (`app_admin_dashboard`, `app_admin_datasource_index`,
`app_admin_datasource_show`), so this is invisible to almost everyone.

Only relevant if you extended `GenericAdminController` and either overrode one
of those three methods, called them directly, or relied on its constructor
accepting a `DataSourceRegistry` argument. In that case, move the corresponding
logic to extend `DataSourceController` instead.

---

### 3. New UI: inline entity creation ("+" button)

`EntityTypeAddButton` now renders automatically next to every `EntityType`
autocomplete field (ManyToOne/OneToOne associations) in generated forms, for
any user with `ADMIN_NEW` permission on the target entity — no opt-in
attribute required.

For the button to actually work (open the dialog, auto-select the new entity),
the `admin-inline-add` Stimulus controller must be registered in your app, the
same way `batch-select` already needed to be. See
[docs/ASSETS.md](docs/ASSETS.md) and [docs/INLINE_ADD.md](docs/INLINE_ADD.md)
for AssetMapper/Encore setup — Flex will not auto-add this entry for
symlinked or path-repository installs.

If you'd rather not expose this yet: deny it per field with
`#[AdminColumn(editable: false)]`, or restrict the `new` permission on the
target entity.

---

### 4. `templates/filters/_default.html.twig` removed

Superseded by the `ColumnFilter` LiveComponent's own template set and no
longer referenced anywhere in the bundle. If you had a local override at
`templates/bundles/KachnitelAdminBundle/filters/_default.html.twig`, it is
now dead code and can be deleted.

---

### Summary checklist

- [ ] Register `KachnitelDynamicFormBundle` and `KachnitelEntityComponentsBundle` in `config/bundles.php` (verify even under Flex)
- [ ] If you extended `GenericAdminController` for dashboard/datasource routes, move that code to extend `DataSourceController`
- [ ] Register the `admin-inline-add` Stimulus controller (or explicitly restrict `ADMIN_NEW` where you don't want the "+" button yet)
- [ ] Remove any local override of the now-dead `filters/_default.html.twig`
- [ ] Run `composer update` and your test suite
