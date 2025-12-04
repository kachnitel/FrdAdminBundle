# Template Overrides Guide

This guide explains how to customize the visual appearance of the admin bundle by overriding templates.

## Table of Contents

- [How Template Overrides Work](#how-template-overrides-work)
- [Template Hierarchy](#template-hierarchy)
- [Override Locations](#override-locations)
- [Common Override Scenarios](#common-override-scenarios)
- [Important Limitations](#important-limitations)
- [Testing Your Overrides](#testing-your-overrides)

## How Template Overrides Work

FrdAdminBundle uses Symfony's standard template override mechanism. When you place a template in your application's `templates/bundles/FrdAdminBundle/` directory, it takes precedence over the bundle's default template.

**Priority Order:**
1. **Application override** (Highest) - `templates/bundles/FrdAdminBundle/`
2. **Bundle template** (Lowest) - `vendor/frd/admin-bundle/templates/`

## Template Hierarchy

### Property Rendering Hierarchy

When rendering entity properties, the bundle uses a **three-level fallback system**:

```
1. Entity-Specific Property
   └─ types/App/Entity/User/email.html.twig

2. Type-Specific Template
   └─ types/datetime/_preview.html.twig

3. Default Fallback
   └─ types/_preview.html.twig
```

**Example:** Rendering `User.email` (string type)

1. Check: `types/App/Entity/User/email.html.twig` ← Most specific
2. Check: `types/string/_preview.html.twig` ← Type-specific
3. Use: `types/_preview.html.twig` ← Default fallback

## Override Locations

### Directory Structure

```
templates/bundles/FrdAdminBundle/
├── admin/                      # Admin page templates
│   ├── index_live.html.twig    # Entity list page (wraps LiveComponent)
│   ├── show.html.twig          # Entity detail page
│   ├── edit.html.twig          # Entity edit page
│   ├── new.html.twig           # Entity create page
│   └── dashboard.html.twig     # Admin dashboard
│
├── components/                 # LiveComponent templates
│   ├── EntityList.html.twig    # Main entity list component
│   └── ColumnFilter.html.twig  # Column filter component
│
└── types/                      # Property rendering templates
    ├── _preview.html.twig      # Default property rendering
    ├── _collection.html.twig   # Default collection rendering
    │
    ├── boolean/                # Type-specific templates
    │   └── _preview.html.twig
    ├── datetime/
    │   └── _preview.html.twig
    ├── datetime_immutable/
    │   └── _preview.html.twig
    │
    └── App/Entity/             # Entity-specific templates
        ├── User/
        │   ├── _preview.html.twig  # All User properties
        │   ├── email.html.twig     # User.email specifically
        │   └── avatar.html.twig    # User.avatar specifically
        └── Product/
            └── price.html.twig
```

## Common Override Scenarios

### 1. Override Property Rendering Globally

Change how ALL properties of a specific type are rendered:

```twig
{# templates/bundles/FrdAdminBundle/types/datetime/_preview.html.twig #}
{% if value %}
    <time datetime="{{ value|date('c') }}">
        {{ value|date('Y-m-d H:i') }}
    </time>
{% else %}
    <span class="text-muted">—</span>
{% endif %}
```

**Available Variables:**
- `value` - The property value
- `entity` - The root entity object
- `property` - Property name as string
- `cell` - Boolean indicating if rendering in table cell

### 2. Override Specific Entity Property

Customize rendering for ONE specific property:

```twig
{# templates/bundles/FrdAdminBundle/types/App/Entity/User/email.html.twig #}
<a href="mailto:{{ value }}" class="text-decoration-none">
    <span class="material-icons">email</span>
    {{ value }}
</a>
```

### 3. Override All Properties of an Entity

Apply custom rendering to ALL properties of an entity:

```twig
{# templates/bundles/FrdAdminBundle/types/App/Entity/User/_preview.html.twig #}
{% if value is null %}
    <span class="text-muted">N/A</span>
{% else %}
    {# Custom User property rendering #}
    <strong>{{ value }}</strong>
{% endif %}
```

### 4. Override the Entity List Page

Customize the page that wraps the LiveComponent:

```twig
{# templates/bundles/FrdAdminBundle/admin/index_live.html.twig #}
{% extends 'admin_layout.html.twig' %}

{% block title %}{{ entityShortClass }} Management{% endblock %}

{% block content %}
    <div class="custom-header">
        <h1>{{ entityShortClass }} List</h1>
        <p class="text-muted">Manage your {{ entityShortClass|lower }} records</p>
    </div>

    {% component 'FRD:Admin:EntityList' with {
        entityClass: entityClass,
        entityShortClass: entityShortClass
    } %}{% endcomponent %}
{% endblock %}
```

### 5. Override the LiveComponent Itself

**⚠️ Advanced:** Override the entire entity list component:

```twig
{# templates/bundles/FrdAdminBundle/components/EntityList.html.twig #}
{# IMPORTANT: Copy the ENTIRE template from the bundle, then modify #}

<div {{ attributes }}>
    {# Custom search UI #}
    <div class="custom-search-bar">
        <input type="text"
               data-model="search"
               value="{{ this.search }}"
               placeholder="Search...">
    </div>

    {# Custom table #}
    <table class="custom-table">
        {# ... your custom table structure ... #}
    </table>
</div>
```

**Note:** You MUST preserve:
- `{{ attributes }}` on the root element
- `data-model` attributes for LiveComponent reactivity
- `data-action` attributes for actions

### 6. Override Collection Display

Change how collections (OneToMany, ManyToMany) are rendered:

```twig
{# templates/bundles/FrdAdminBundle/types/_collection.html.twig #}
{% if value is iterable and value is not empty %}
    <div class="badge bg-primary">
        {{ value|length }} items
    </div>
    <ul class="list-inline">
        {% for item in value|slice(0, 3) %}
            <li class="list-inline-item">{{ item.name ?? item.id }}</li>
        {% endfor %}
        {% if value|length > 3 %}
            <li class="list-inline-item text-muted">+{{ value|length - 3 }} more</li>
        {% endif %}
    </ul>
{% else %}
    <span class="text-muted">Empty</span>
{% endif %}
```

### 7. Override Boolean Display

Custom boolean rendering with icons:

```twig
{# templates/bundles/FrdAdminBundle/types/boolean/_preview.html.twig #}
{% if value is same as(true) %}
    <span class="badge bg-success">
        <span class="material-icons">check_circle</span>
        Yes
    </span>
{% elseif value is same as(false) %}
    <span class="badge bg-danger">
        <span class="material-icons">cancel</span>
        No
    </span>
{% else %}
    <span class="badge bg-secondary">Unknown</span>
{% endif %}
```

## Important Limitations

### ❌ Cannot Use `{% extends %}` in Overrides

When you override a template, Symfony resolves `@FrdAdmin/template.html.twig` to YOUR override. This creates a circular reference:

**This WILL NOT work:**
```twig
{# templates/bundles/FrdAdminBundle/admin/index_live.html.twig #}
{% extends '@FrdAdmin/admin/index_live.html.twig' %}  {# ❌ CIRCULAR REFERENCE! #}
```

**Instead, do this:**
```twig
{# templates/bundles/FrdAdminBundle/admin/index_live.html.twig #}
{% extends 'layout.html.twig' %}  {# ✅ Extend your app layout #}

{% block content %}
    {# Copy and customize the content you need #}
{% endblock %}
```

### ❌ Entity-Specific Index Pages Not Supported

The GenericAdminController hardcodes the template path, so these DON'T work:

```
❌ templates/bundles/FrdAdminBundle/admin/Product/index_live.html.twig
❌ templates/bundles/FrdAdminBundle/admin/User/index.html.twig
```

**Alternative:** Create a custom controller for entity-specific index pages:

```php
use Frd\AdminBundle\Controller\AbstractAdminController;

class ProductController extends AbstractAdminController
{
    #[Route('/admin/products', name: 'app_product_index')]
    public function index(): Response
    {
        return $this->render('admin/product/index.html.twig', [
            'products' => $this->entityManager->getRepository(Product::class)->findAll()
        ]);
    }
}
```

## Testing Your Overrides

### 1. Check Template Resolution

Use Symfony's debug command to verify which template is being used:

```bash
php bin/console debug:twig @FrdAdmin/types/datetime/_preview.html.twig
```

**Expected output when override exists:**
```
Matched File
------------
templates/bundles/FrdAdminBundle/types/datetime/_preview.html.twig

Overridden Files
----------------
vendor/frd/admin-bundle/templates/types/datetime/_preview.html.twig
```

### 2. Clear Cache

After creating or modifying override templates:

```bash
php bin/console cache:clear
```

### 3. Check for Syntax Errors

Validate your Twig syntax:

```bash
php bin/console lint:twig templates/bundles/FrdAdminBundle/
```

### 4. Verify Available Variables

Add debugging output to see what variables are available:

```twig
{# Temporary debugging #}
{{ dump(entity) }}
{{ dump(value) }}
{{ dump(property) }}
```

## Template Variables Reference

### Property Templates (`types/**/_preview.html.twig`)

| Variable | Type | Description |
|----------|------|-------------|
| `value` | mixed | The property value to display |
| `entity` | object | The root entity object |
| `property` | string | The property name (e.g., 'email') |
| `cell` | bool\|null | True if rendering in table cell |

### Admin Page Templates (`admin/*.html.twig`)

| Variable | Type | Description |
|----------|------|-------------|
| `entity` | object | The entity being displayed/edited |
| `entityClass` | string | Fully-qualified class name |
| `entityShortClass` | string | Short class name (e.g., 'Product') |

### Component Templates (`components/*.html.twig`)

| Variable | Type | Description |
|----------|------|-------------|
| `this.entities` | array | Array of entities to display |
| `this.columns` | array | Column names to render |
| `this.search` | string | Current search query |
| `this.sortBy` | string | Current sort column |
| `this.sortDirection` | string | 'ASC' or 'DESC' |
| `this.page` | int | Current page number |
| `this.totalPages` | int | Total number of pages |

## Best Practices

### ✅ DO:
- Copy the entire original template when overriding complex templates
- Use the template hierarchy for targeted customizations
- Test your overrides with various data types and edge cases
- Keep overrides minimal - only change what you need
- Document why you created the override (comment in template)

### ❌ DON'T:
- Use `{% extends %}` to extend bundle templates you're overriding
- Create entity-specific admin page templates (not supported)
- Override templates without understanding the available variables
- Forget to preserve LiveComponent data attributes
- Remove the `{{ attributes }}` from LiveComponent templates

## Need Help?

- Check the [CONFIGURATION.md](CONFIGURATION.md) for attribute-based configuration
- Review bundle templates in `vendor/frd/admin-bundle/templates/`
- See [Symfony's template documentation](https://symfony.com/doc/current/templates.html)
