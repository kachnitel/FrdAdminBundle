# Upgrade Guide

## Upgrading to the Datasource-Contracts Release

This release extracts core data-source value objects and interfaces into two
standalone packages:

- **[`kachnitel/datasource-contracts`](https://github.com/kachnitel/datasource-contracts)**
  — shared DTOs, interfaces, and traits
- **[`kachnitel/entity-expression-language`](https://github.com/kachnitel/entity-expression-language)**
  — expression-language evaluation for entity rows

The admin bundle re-exports everything under its original FQCNs where possible
(thin `extends`/`use` wrappers), so many applications require **only namespace
import changes**. Six value-object classes that were deleted entirely are listed
below with the one-line find-replace needed.

---

### 1. Update `composer.json`

The two new packages are added as direct dependencies of `kachnitel/admin-bundle`,
so Composer will pull them automatically. You only need an explicit entry if you
want to typehint against the contracts package directly in your own code:

```json
"require": {
    "kachnitel/admin-bundle": "^<next-version>",

    // optional — only if you reference contracts classes in your own code:
    "kachnitel/datasource-contracts": "^1.0"
}
```

> **Note on stability:** Both packages are currently released from their default
> branch. If your project sets `"minimum-stability": "stable"` you may need to
> add a `minimum-stability` override or wait for a tagged release.

---

### 2. Replace deleted class imports

Six classes were removed from the admin bundle and now live in
`kachnitel/datasource-contracts`. Run these replacements across your codebase
(adjust the tool — sed, PHPStorm *Replace in Path*, Rector, etc.):

| Old FQCN | New FQCN |
|---|---|
| `Kachnitel\AdminBundle\DataSource\ColumnGroup` | `Kachnitel\DataSourceContracts\ColumnGroup` |
| `Kachnitel\AdminBundle\DataSource\ColumnMetadata` | `Kachnitel\DataSourceContracts\ColumnMetadata` |
| `Kachnitel\AdminBundle\DataSource\FilterMetadata` | `Kachnitel\DataSourceContracts\FilterMetadata` |
| `Kachnitel\AdminBundle\DataSource\FilterEnumOptions` | `Kachnitel\DataSourceContracts\FilterEnumOptions` |
| `Kachnitel\AdminBundle\DataSource\PaginatedResult` | `Kachnitel\DataSourceContracts\PaginatedResult` |
| `Kachnitel\AdminBundle\ValueObject\PaginationInfo` | `Kachnitel\DataSourceContracts\PaginationInfo` |

**Bash one-liner (run from your project root):**

```bash
find src tests -name '*.php' | xargs sed -i \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\ColumnGroup|Kachnitel\\DataSourceContracts\\ColumnGroup|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\ColumnMetadata|Kachnitel\\DataSourceContracts\\ColumnMetadata|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\FilterMetadata|Kachnitel\\DataSourceContracts\\FilterMetadata|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\FilterEnumOptions|Kachnitel\\DataSourceContracts\\FilterEnumOptions|g' \
  -e 's|Kachnitel\\AdminBundle\\DataSource\\PaginatedResult|Kachnitel\\DataSourceContracts\\PaginatedResult|g' \
  -e 's|Kachnitel\\AdminBundle\\ValueObject\\PaginationInfo|Kachnitel\\DataSourceContracts\\PaginationInfo|g'
```

---

### 3. Interfaces and traits — no change required

The following admin-bundle FQCNs are **kept as thin wrappers** that extend or
re-export the contracts equivalents. Existing `use` statements and `instanceof`
checks continue to work with no modifications:

| Admin-bundle FQCN | Status |
|---|---|
| `Kachnitel\AdminBundle\DataSource\DataSourceInterface` | ✅ kept — extends contracts interface |
| `Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface` | ✅ kept — extends contracts interface |
| `Kachnitel\AdminBundle\DataSource\SearchAwareDataSourceInterface` | ✅ kept — extends contracts interface |
| `Kachnitel\AdminBundle\DataSource\FlatColumnGroupsTrait` | ✅ kept — re-exports contracts trait |

New code should prefer typehinting against the contracts FQCNS directly when
admin-bundle-specific behaviour is not required:

```php
// preferred for new code
use Kachnitel\DataSourceContracts\DataSourceInterface;

// still works — no change needed for existing code
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
```

---

### 4. `ColumnFilter::TYPE_*` constants — no change required

All eight `TYPE_*` constants on `ColumnFilter` now delegate to their counterparts
on `Kachnitel\DataSourceContracts\FilterMetadata`. The string values are
identical, so existing attribute usage compiles and behaves the same:

```php
// both of these remain valid and equivalent:
#[ColumnFilter(type: ColumnFilter::TYPE_TEXT)]
#[ColumnFilter(type: 'text')]
```

---

### 5. Manual service tags (uncommon)

If you manually tag a data-source service in `services.yaml` with the
admin-bundle interface FQCN, update the tag to the contracts FQCN so that
`DataSourceRegistry`'s `AutowireIterator` picks it up:

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

Services that rely on **autowiring / autoconfiguration** (the default) are
unaffected — Symfony tags implementing services with all parent interface FQCNs
automatically.

---

### 6. `PropertyAccessProxy` (internal — rare)

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

In practice, the expression-language runtime unwraps the proxy before passing the
subject to `is_granted()` voters, so most voter code is unaffected.

---

### 7. `RowActionExpressionLanguage` constructor

`RowActionExpressionLanguage` now **extends** `EntityExpressionLanguage` instead
of composing `ExpressionLanguage` internally. If you instantiate it manually
(outside of the Symfony service container), verify that the parent constructor
signature is satisfied:

```php
// autowired — no change needed
public function __construct(private RowActionExpressionLanguage $el) {}

// manual instantiation — check EntityExpressionLanguage constructor
$el = new RowActionExpressionLanguage();
```

---

### Summary checklist

- [ ] `composer update kachnitel/admin-bundle` pulls the new packages
- [ ] Run the find-replace for the 6 deleted value-object FQCNs
- [ ] Optionally update interface/trait imports to the contracts FQCN
- [ ] Update any manually-tagged services in `services.yaml`
- [ ] Update voter `instanceof` checks for `PropertyAccessProxy` if present
- [ ] Run `composer dump-autoload` and your test suite
