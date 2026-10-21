# Entity URL Functions

Generate admin panel URLs for related entities directly in Twig templates.

## Quick Start

Link to a related entity's admin page:

```twig
{% set customer = order.customer %}
{% set url = admin_entity_url(customer) %}

{% if url %}
    <a href="{{ url }}">{{ customer.name }}</a>
{% else %}
    {{ customer.name }}
{% endif %}
```

Link to a collection's admin list (e.g., view all orders for a customer):

```twig
{% set url = admin_collection_url(customer, 'orders') %}

{% if url %}
    <a href="{{ url }}">View Orders ({{ customer.orders|length }})</a>
{% endif %}
```

## Functions

### `admin_entity_url(entity, checkAccess = true)`

Generates a URL to the admin detail page for a single related entity.

**Parameters:**
- `entity` - The related entity object
- `checkAccess` (optional, default: `true`) - Whether to check if current user can access the route

**Returns:** `string|null` - The URL, or `null` if:
- The entity's class has no `#[Admin]` attribute
- The user lacks permission to access the route (when `checkAccess` is `true`)
- No suitable route exists

**Behavior:**
1. Tries the "show" route first (direct link to entity detail page)
2. Falls back to "index" route with an `id` filter if no show route exists

```twig
{# Direct link to product detail page #}
{% set url = admin_entity_url(orderItem.product) %}

{# Skip access check (e.g., for admin-only templates) #}
{% set url = admin_entity_url(orderItem.product, false) %}
```

### `admin_collection_url(entity, property, checkAccess = true)`

Generates a URL to the target entity's admin list, scoped to the source entity's collection.

**Parameters:**
- `entity` - The source entity that owns the collection
- `property` - The property name of the collection (e.g., `'orders'`, `'products'`)
- `checkAccess` (optional, default: `true`) - Whether to check if current user can access the route

**Returns:** `string|null` - The URL, or `null` if:
- The target or the source entity class has no `#[Admin]` attribute
- The user lacks permission to access the target's index route (when `checkAccess` is `true`)
- The property is not a collection-valued association
- The source entity has no id yet

**Behavior:**

The URL carries a compact scope in the `collection` query parameter, `Owner:id:property`. The index page turns it into the same exact `IN (subquery)` restriction that the [collection dialog](COLLECTION_DIALOG.md) uses, so the new-tab link and the dialog always list identical rows. This holds for **OneToMany**, bidirectional and unidirectional **ManyToMany** alike; it does not depend on `mappedBy`/`inversedBy` or on any column filter.

The target page shows a "Orders of Customer #42" banner with a ✕ to clear the scope. Authorization is re-checked by `CollectionScopeResolver` on every query (owner is `#[Admin]`, class and column `ADMIN_SHOW`, object-level `ADMIN_SHOW` on the owner), so editing the URL by hand cannot widen access; a malformed or unknown value is a 404.

```twig
{# Link to orders of this customer #}
{% set url = admin_collection_url(customer, 'orders') %}
{# Result: /admin/order?collection=Customer%3A42%3Aorders #}

{# Same for ManyToMany, including unidirectional #}
{% set url = admin_collection_url(product, 'tags') %}
{# Result: /admin/tag?collection=Product%3A7%3Atags #}
```

> **Changed:** earlier versions emitted `columnFilters[<inverse field>]=<id>` (and no filter at all for unidirectional ManyToMany). Existing bookmarked `columnFilters` URLs keep working; they are just no longer generated.

## Access Control

Both functions respect the bundle's permission system:

- When `checkAccess` is `true` (default), the function returns `null` if the current user doesn't have permission to view the target admin page
- Uses `AdminEntityVoter` for permission checks
- Supports role hierarchy automatically

```twig
{# Safe to use in templates - won't show links user can't access #}
{% set url = admin_entity_url(sensitiveEntity) %}
{% if url %}
    <a href="{{ url }}">View Details</a>
{% endif %}
```

## Use Cases

### Preview Templates

Make related entities clickable in entity list previews:

```twig
{# templates/bundles/KachnitelAdminBundle/preview/relation.html.twig #}
{% set url = admin_entity_url(value) %}
{% if url %}
    <a href="{{ url }}" class="text-blue-600 hover:underline">
        {{ value.name ?? value.id }}
    </a>
{% else %}
    {{ value.name ?? value.id }}
{% endif %}
```

### Collection Counts with Links

Show collection counts that link to scoped admin lists:

```twig
{% set orderCount = customer.orders|length %}
{% set url = admin_collection_url(customer, 'orders') %}

{% if url and orderCount > 0 %}
    <a href="{{ url }}">{{ orderCount }} orders</a>
{% else %}
    {{ orderCount }} orders
{% endif %}
```

### Conditional Navigation

Build navigation based on available admin routes:

```twig
<div class="related-entities">
    {% if admin_entity_url(entity.category) %}
        <a href="{{ admin_entity_url(entity.category) }}">Category</a>
    {% endif %}

    {% if admin_collection_url(entity, 'comments') %}
        <a href="{{ admin_collection_url(entity, 'comments') }}">Comments</a>
    {% endif %}
</div>
```

## Notes

- Both functions handle Doctrine proxies correctly (resolves to real class)
- Entity slugs are auto-generated from class names (e.g., `ProductCategory` becomes `product-category`)
- Requires `getId()` method on entities for URL generation
