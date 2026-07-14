# Dynamic Form Collections

Collection-association handling (`OneToMany`, `ManyToMany`) for the zero-config auto-form — the `cascade`/`orphanRemoval` requirements, adder/remover methods, recursion prevention, and the auto-skip rules for inverse-side and back-reference associations — now lives in [`kachnitel/dynamic-form-bundle`](https://github.com/kachnitel/dynamic-form-bundle), the package that provides `DynamicEntityFormType` itself.

**See [Associations](https://github.com/kachnitel/dynamic-form-bundle/blob/master/docs/ASSOCIATIONS.md)** in that repo for the full guide — it's written bundle-agnostic and applies exactly as-is here.

Two things stay specific to this bundle:

- **`#[AdminColumn(editable: ...)]`** is *how* you opt a collection in or out here — see [Forms → Excluding a field](FORMS.md#excluding-a-field) and [Inline Editing](INLINE_EDIT.md#admincolumn-vs-columnpermission). Under the hood it's `AdminColumnEditabilityResolver`, this bundle's binding of dynamic-form-bundle's [`FieldEditabilityResolverInterface`](https://github.com/kachnitel/dynamic-form-bundle/blob/master/docs/EDITABILITY.md).
- **The inline "+ Add" button** next to `EntityType` autocomplete fields (`EntityTypeAddButton`, the `admin-inline-add` Stimulus controller, the `K:Admin:EntityType:InlineForm` dialog) is entirely this bundle's own feature, consuming a `data-admin-entity-class` hook that dynamic-form-bundle adds to association fields for exactly this purpose. See [Inline Add](INLINE_ADD.md).

## Troubleshooting

Doubled items, an inert Remove button, `LogicException` at submit, lost children after flush — all covered in dynamic-form-bundle's [Associations → Troubleshooting](https://github.com/kachnitel/dynamic-form-bundle/blob/master/docs/ASSOCIATIONS.md#troubleshooting).

## Testing

```bash
# In kachnitel/dynamic-form-bundle — the generation engine itself
vendor/bin/phpunit --group collections

# In this bundle — regression coverage of what admin-bundle specifically expects
# from dynamic-form-bundle (inline-add attribute contract included)
vendor/bin/phpunit --group collections,inline-add
```
