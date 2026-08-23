# Upgrade Guide

**This bundle is pre-1.0.** Breaking changes are expected between minor versions. This guide covers migration paths for significant API changes.

## Table of Contents

- [General Policy](#general-policy)
- [Upgrading](#upgrading)
- [Breaking Changes](#breaking-changes)

## General Policy

Until 1.0 is released:

- **Minor version bumps** (`0.x` → `0.y`) may include breaking changes. Read the [CHANGELOG](../CHANGELOG.md) before upgrading.
- **Patch releases** (`0.x.y` → `0.x.z`) are safe — only bug fixes and documentation.
- Doctrine ORM 3.5+, PHP 8.4+, Symfony 6.4+/7.x/8.x required.

## Upgrading

```bash
composer update kachnitel/admin-bundle
php bin/console cache:clear
```

Check the CHANGELOG for your version. If breaking changes are listed, see "Breaking Changes" below.

## Breaking Changes

### Extracting Packages (0.x → 0.y)

Several internal packages were extracted into standalone repositories with their own release cycles:

| Moved | Repository | Namespace Change |
|-------|-----------|---------|
| Form auto-generation engine | [`kachnitel/dynamic-form-bundle`](https://github.com/kachnitel/dynamic-form-bundle) | `Kachnitel\AdminBundle\Form\DynamicEntityFormType` → `Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType` |
| Data source contracts | [`kachnitel/datasource-contracts`](https://github.com/kachnitel/datasource-contracts) | `Kachnitel\AdminBundle\ValueObject\ColumnGroup` → `Kachnitel\DataSourceContracts\ColumnGroup`; `DataSourceInterface` etc. moved similarly |

**Action:** If you implemented custom `DataSourceInterface`, used `DynamicEntityFormType` directly, or referenced value objects from the bundle's `ValueObject\` namespace, update the namespace references. The admin bundle includes these packages as composer dependencies automatically.

### Native Symfony Expression Language (0.x → 0.y)

The internal expression evaluator no longer depends on
`kachnitel/entity-expression-language`. It now uses Symfony's native
`ExpressionLanguage` component. Expressions that access entity state must use
public properties or explicit method calls, for example:

```text
item.getStatus() == "pending"
item.isArchived()
```

Property-style access to private entity properties, such as `item.status`, is
not supported by Symfony's native evaluator. Update custom row-action,
editability, and archive expressions to call the entity's getter or boolean
accessor directly.

---

## No Action Needed

The following are **not** breaking changes, just clarifications:

- `#[Admin]` attributes on entities are optional — omit to exclude from admin
- `enableInlineEdit` does **not** affect the New/Edit form, only list-view row editing
- `dataSourceId`-only (no `entityClass`) silently disables archive toggling, inline editing, and `#[ColumnPermission]` — use `entityClass` for full functionality
- Forms require Symfony Form 7.0+ (included with Symfony 7.x/8.x; users on 6.4 must pin `symfony/form:^7`)

---

## Getting Help

- Check the [CHANGELOG](../CHANGELOG.md) for detailed breaking change notes
- Review relevant feature guides (e.g., [Forms](FORMS.md), [Inline Editing](INLINE_EDIT.md))
- Open an issue if you hit a migration blocker
