# Upgrade Guide

## Upgrading to the Datasource-Contracts Release

This release extracts core data-source value objects and interfaces into two
standalone packages:

- **[`kachnitel/datasource-contracts`](https://github.com/kachnitel/datasource-contracts)**
  — shared DTOs, interfaces, and traits
- **[`kachnitel/entity-expression-language`](https://github.com/kachnitel/entity-expression-language)**
  — expression-language evaluation for entity rows

All previously bundled classes have moved to these packages. Update your imports
as described below.

---

### 1. Update `composer.json`

The two new packages are pulled automatically as dependencies of
`kachnitel/admin-bundle`. Add explicit entries only if you typehint against the
contracts package directly in your own code:

```json
"require": {
    "kachnitel/admin-bundle": "^<next-version>",

    // optional — only if you reference contracts classes in your own code:
    "kachnitel/datasource-contracts": "^1.0"
}
```

> **Note on stability:** Both packages are currently released from their default
> branch. If your project sets `"minimum-stability": "stable"` you may need to
> wait for a tagged release or add a stability flag.

---

### 2. Replace all data-source imports

Ten classes and interfaces were removed from the admin bundle and now live in
`kachnitel/datasource-contracts`. The following applies to **all** files —
source and tests alike.

| Old FQCN | New FQCN |
|---|---|
| `Kachnitel\AdminBundle\DataSource\ColumnGroup` | `Kachnitel\DataSourceContracts\ColumnGroup` |
| `Kachnitel\AdminBundle\DataSource\ColumnMetadata` | `Kachnitel\DataSourceContracts\ColumnMetadata` |
| `Kachnitel\AdminBundle\DataSource\DataSourceInterface` | `Kachnitel\DataSourceContracts\DataSourceInterface` |
| `Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface` | `Kachnitel\DataSourceContracts\DataSourceProviderInterface` |
| `Kachnitel\AdminBundle\DataSource\FilterEnumOptions` | `Kachnitel\DataSourceContracts\FilterEnumOptions` |
| `Kachnitel\AdminBundle\DataSource\FilterMetadata` | `Kachnitel\DataSourceContracts\FilterMetadata` |
| `Kachnitel\AdminBundle\DataSource\FlatColumnGroupsTrait` | `Kachnitel\DataSourceContracts\FlatColumnGroupsTrait` |
| `Kachnitel\AdminBundle\DataSource\PaginatedResult` | `Kachnitel\DataSourceContracts\PaginatedResult` |
| `Kachnitel\AdminBundle\DataSource\SearchAwareDataSourceInterface` | `Kachnitel\DataSourceContracts\SearchAwareDataSourceInterface` |
| `Kachnitel\AdminBundle\ValueObject\PaginationInfo` | `Kachnitel\DataSourceContracts\PaginationInfo` |

**Bash one-liner (run from your project root):**

```bash
find src tests -name '*.php' | xargs sed -i \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\ColumnGroup|Kachnitel\\DataSourceContracts\\ColumnGroup|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\ColumnMetadata|Kachnitel\\DataSourceContracts\\ColumnMetadata|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\DataSourceInterface|Kachnitel\\DataSourceContracts\\DataSourceInterface|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\DataSourceProviderInterface|Kachnitel\\DataSourceContracts\\DataSourceProviderInterface|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\FilterEnumOptions|Kachnitel\\DataSourceContracts\\FilterEnumOptions|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\FilterMetadata|Kachnitel\\DataSourceContracts\\FilterMetadata|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\FlatColumnGroupsTrait|Kachnitel\\DataSourceContracts\\FlatColumnGroupsTrait|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\PaginatedResult|Kachnitel\\DataSourceContracts\\PaginatedResult|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\SearchAwareDataSourceInterface|Kachnitel\\DataSourceContracts\\SearchAwareDataSourceInterface|g' \
  -e 's|Kachnitel\\AdminBundle\\ValueObject\\PaginationInfo|Kachnitel\\DataSourceContracts\\PaginationInfo|g'
```

---

### 3. Manual service tags (uncommon)

If you manually tag a data-source service in `services.yaml`, update the tag to
the contracts FQCN:

```yaml
# Before
App\DataSource\MyDataSource:
    tags:
        - { name: 'Kachnitel\AdminBundle\DataSource\DataSourceInterface' }

# After
App\DataSource\MyDataSource:
    tags:
        - { name: 'Kachnitel\DataSourceContracts\DataSourceInterface' }
```

Services using **autowiring / autoconfiguration** (the default) are unaffected.

---

### 4. `PropertyAccessProxy` (internal — rare)

`Kachnitel\AdminBundle\RowAction\PropertyAccessProxy` has moved to
`Kachnitel\EntityExpressionLanguage\PropertyAccessProxy`. The class is
`@internal` and should not appear in application code, but if you have a
**voter** that type-checks its subject, update it:

```php
// Before
if ($subject instanceof \Kachnitel\AdminBundle\RowAction\PropertyAccessProxy) {
    $subject = $subject->getEntity();
}

// After
if ($subject instanceof \Kachnitel\EntityExpressionLanguage\PropertyAccessProxy) {
    $subject = $subject->getEntity();
}
```

In practice the expression-language runtime unwraps the proxy before passing the
subject to voters, so most voter code is unaffected.

---

### Summary checklist

- [ ] `composer update kachnitel/admin-bundle` pulls the new packages
- [ ] Run the find-replace for all 10 moved FQCNs
- [ ] Update any manually-tagged services in `services.yaml`
- [ ] Update voter `instanceof` checks for `PropertyAccessProxy` if present
- [ ] Run `composer dump-autoload` and your test suite
