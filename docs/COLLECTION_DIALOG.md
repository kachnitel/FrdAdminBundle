# Collection Dialog

Show a collection column as a **count button that opens a dialog containing a full, scoped entity list** of the collection's items — search, sort, filters and paging included — instead of a bare count or a short inline list.

## Table of Contents

- [Quick Start](#quick-start)
- [How It Works](#how-it-works)
- [Requirements and Fallback](#requirements-and-fallback)
- [Security](#security)
- [Embedding a Scoped List Yourself](#embedding-a-scoped-list-yourself)
- [EntityList vs EntityListEmbedded](#entitylist-vs-entitylistembedded)
- [Asset Setup](#asset-setup)
- [Limitations](#limitations)
- [Running Tests](#running-tests)

---

## Quick Start

```php
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;

#[Admin(label: 'Orders')]
class Order
{
    #[ORM\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order')]
    #[AdminColumn(collectionDialog: true)]
    private Collection $lines;
}
```

The `Lines` cell now renders a "N lines" button. Clicking it opens a dialog listing that order's lines, using the `OrderLine` entity's own admin list (columns, filters, row actions, permissions).

`collectionDialog` takes precedence over `collectionDisplay`; `collectionLimit`, `collectionCollapsible` and `collectionLabelMethod` do not apply.

Works for `OneToMany`, bidirectional `ManyToMany` and unidirectional `ManyToMany` alike.

## How It Works

1. The cell renders `types/_collection_dialog.html.twig`: a button plus a native `<dialog>`, wired to the `admin-collection-dialog` Stimulus controller (shared dialog helpers live in `assets/lib/native-dialog.js`, which `admin-inline-add` uses too).
2. Inside the dialog, `K:Admin:EntityListEmbedded` is mounted with `loading: "lazy"`. Nothing is fetched until the dialog is opened, so a list page with many rows and several collection columns does not hydrate every collection up front.
3. The embedded list receives a signed `scope` prop — `{ownerClass, ownerId, property}`. On each query `CollectionScopeResolver` authorizes it and turns it into one DQL restriction that is AND-ed with the list's own search and filters:

```sql
e.id IN (
    SELECT scope_t.id
    FROM App\Entity\Order scope_o
    JOIN scope_o.lines scope_t
    WHERE scope_o.id = :scope_owner_id
)
```

This is exact (it is not a column filter, so it cannot be confused with the LIKE-based relation filter) and independent of whether the association has an inverse side.

The new-tab link produced by `admin_collection_url()` uses the same scope: it points at the target's index with `?collection=Owner:id:property`, which the index page passes to `EntityList` as its `scope`. The dialog and the link therefore always show the same rows. See [ENTITY_URLS.md](ENTITY_URLS.md).

## Requirements and Fallback

- The **target** entity must have `#[Admin]`, and its index must be accessible to the current user (`admin_collection_url(entity, property)` non-null; this also requires the owner to be `#[Admin]`).
- Otherwise the column falls back to the default count-only display.

## Security

Every check runs server-side in `CollectionScopeResolver::resolve()`, on **every query** — not just when the dialog is first rendered:

| Check | Fails with |
|---|---|
| Owner entity has `#[Admin]` | 404 |
| Class-level `ADMIN_SHOW` on the owner | 403 |
| `#[ColumnPermission]` `ADMIN_SHOW` on the property | 403 |
| Property is a collection association | 404 |
| Target entity has `#[Admin]` | 404 |
| Target entity is the entity the list shows (a mismatched `?collection=` on another entity's index) | 404 |
| Owner id is valid for the owner's identifier type; single-column identifier | 404 |
| Owner row exists | 404 |
| Object-level `ADMIN_SHOW` on the owner instance (`enableObjectAuth`) | 403 |

The `scope` prop is a non-writable LiveProp: the client cannot alter it. The embedded list itself applies the target entity's normal permissions (class-level index, column visibility, row-action visibility).

A scope on a non-Doctrine data source fails closed (`LogicException`) rather than listing everything.

## Embedding a Scoped List Yourself

The mechanism is not specific to `AdminColumn`. Any template can embed a scoped list:

```twig
{{ component('K:Admin:EntityListEmbedded', {
    entityShortClass: 'OrderLine',
    scope: {
        ownerClass: admin_real_class(order),
        ownerId: '' ~ order.id,
        property: 'lines',
    },
}) }}
```

`admin_real_class(entity)` unwraps Doctrine proxies. If the list is inside a closed `<dialog>`, keep `loading: 'lazy'` — see the `InlineEntityForm` docblock for why that is required rather than an optimisation.

## EntityList vs EntityListEmbedded

`EntityListEmbedded` extends `EntityList` and uses the same template and behavior. It redeclares the URL-sensitive state (`search`, `sortBy`, `sortDirection`, `columnFilters`, `page`, `itemsPerPage`, `showArchived`) without URL synchronization, so embedded lists coexist without fighting over query parameters.

Use `K:Admin:EntityList` for index pages; use `K:Admin:EntityListEmbedded` for anything nested or shown in a dialog.

For a custom embedded list, extend `EntityListEmbedded` and preserve its non-URL-bound LiveProps and scope handling.

## Asset Setup

Register the controller alongside `admin-inline-add` in `assets/controllers.json` (AssetMapper):

```json
{
    "controllers": {
        "@kachnitel/admin-bundle": {
            "admin-collection-dialog": {
                "enabled": true,
                "fetch": "eager"
            }
        }
    }
}
```

Webpack Encore:

```javascript
import AdminCollectionDialogController from '../vendor/kachnitel/admin-bundle/assets/controllers/admin-collection-dialog_controller.js';
application.register('admin-collection-dialog', AdminCollectionDialogController);
```

## Events inside the dialog

The embedded list sits inside the outer list's element, and UX's `emitUp()` reaches *every* ancestor component. Filters and batch actions therefore emit to the component name of the list that rendered them (`EntityList::getListComponentName()`, passed down as `listComponent`; see [scoping events](https://symfony.com/bundles/ux-live-component/current/index.html#scoping-events)). A filter typed in a dialog updates only the embedded list, never the outer list's filters, page or URL.

Custom filter or batch-action components get this by using `EmitsToListTrait` (or `BatchActionTrait`) and receiving `listComponent`; without it they broadcast as before.

## Limitations

- **Dialogs don't nest.** UX can target components by name but not by instance, so a dialog inside an embedded list would leak its events into the embedded list around it. Inside an `EntityListEmbedded`, a `collectionDialog` column falls back to the plain count and link.

- One `<dialog>` per row per collection column is rendered, but it is empty until opened (the list is lazy).
- Column visibility preferences are stored per entity short name, so the embedded list shares them with the entity's main list.
- Collection items are not individually object-authorized by the *owner's* rules; the embedded list applies the *target* entity's own permissions and, if it has `enableObjectAuth`, the usual per-row action visibility.

## Running Tests

```bash
composer phpunit -- --group=collection-dialog
composer phpunit -- --group=collection-scope
npm --prefix assets test
```
