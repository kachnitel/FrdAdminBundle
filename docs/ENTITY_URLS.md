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

Generates a URL to the target entity's admin list, pre-filtered to show items related to the source entity.

**Parameters:**
- `entity` - The source entity that owns the collection
- `property` - The property name of the collection (e.g., `'orders'`, `'products'`)
- `checkAccess` (optional, default: `true`) - Whether to check if current user can access the route

**Returns:** `string|null` - The URL, or `null` if:
- The target entity class has no `#[Admin]` attribute
- The user lacks permission to access the route (when `checkAccess` is `true`)
- The property is not a collection-valued association

**Behavior:**
- For **OneToMany** associations: The URL includes a `columnFilter` on the inverse ManyToOne field, so the list shows only items belonging to the source entity
- For **ManyToMany** associations: Links to the target admin list without a pre-applied filter

```twig
{# Link to orders filtered by this customer #}
{% set url = admin_collection_url(customer, 'orders') %}
{# Result: /admin/order?columnFilters[customer]=42 #}

{# Link to tags for this product (ManyToMany) #}
{% set url = admin_collection_url(product, 'tags') %}
{# Result: /admin/tag #}
```

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

Show collection counts that link to filtered admin lists:

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
