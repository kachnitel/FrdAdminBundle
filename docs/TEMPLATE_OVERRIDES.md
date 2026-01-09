# Template Overrides Guide

Customize the admin interface appearance by overriding templates.

## Quick Start

**Most common customization** - use your app's base layout:

```yaml
# config/packages/kachnitel_admin.yaml
kachnitel_admin:
    base_layout: 'layout.html.twig'
```

**Override a specific template** - place it in your app:

```
templates/bundles/KachnitelAdminBundle/
└── types/
    └── datetime/
        └── _preview.html.twig   # Overrides datetime display
```

## Table of Contents

- [How Template Overrides Work](#how-template-overrides-work)
- [Configuring Base Layout](#configuring-base-layout)
- [CSS Theming](#css-theming)
- [Template Hierarchy](#template-hierarchy)
- [Override Locations](#override-locations)
- [Common Override Scenarios](#common-override-scenarios)
- [Important Limitations](#important-limitations)
- [Testing Your Overrides](#testing-your-overrides)

## How Template Overrides Work

Uses Symfony's standard bundle override mechanism:

1. **Application override** (priority) - `templates/bundles/KachnitelAdminBundle/`
2. **Bundle template** (fallback) - `vendor/kachnitel/admin-bundle/templates/`

## Configuring Base Layout

The easiest way to integrate the admin bundle with your application's design is to configure the `base_layout` option.

### Quick Setup

In `config/packages/kachnitel_admin.yaml`:

```yaml
kachnitel_admin:
    base_layout: 'layout.html.twig'  # Your app's base layout
```

All admin templates (`dashboard.html.twig`, `index_live.html.twig`, `edit.html.twig`, etc.) will automatically extend your specified layout.

### What Admin Templates Provide

Admin templates provide these blocks for your base layout:

- `{% block title %}` - Page title (e.g., "Admin Dashboard", "Edit Product")
- `{% block headerTitle %}` - Page header (optional, for breadcrumbs or page titles)
- `{% block headerButtons %}` - Action buttons in header (e.g., "New" button)
- `{% block content %}` - Main page content

### Example Integration

**Your app layout** (`templates/layout.html.twig`):
```twig
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}My App{% endblock %}</title>
</head>
<body>
    <header>
        <h1>{% block headerTitle %}{% endblock %}</h1>
        <div>{% block headerButtons %}{% endblock %}</div>
    </header>
    <main>
        {% block content %}{% endblock %}
    </main>
</body>
</html>
```

**Admin template** (automatically uses your layout):
```twig
{% extends kachnitel_admin_base_layout ?: '@KachnitelAdmin/admin/base.html.twig' %}

{% block title %}Product List{% endblock %}
{% block headerTitle %}Products{% endblock %}
{% block content %}
    {# Admin content here #}
{% endblock %}
```

### Default Behavior

If `base_layout` is not configured (null), admin templates use the bundle's minimal default layout at `@KachnitelAdmin/admin/base.html.twig`.

## CSS Theming

The bundle uses Twig macros for CSS classes, making it easy to switch between CSS frameworks or customize styling.

### Built-in Themes

Two themes are included:

- **Bootstrap 5** (default): `@KachnitelAdmin/theme/bootstrap.html.twig`
- **Tailwind CSS**: `@KachnitelAdmin/theme/tailwind.html.twig`

### Switching to Tailwind

```yaml
# config/packages/kachnitel_admin.yaml
kachnitel_admin:
    theme: '@KachnitelAdmin/theme/tailwind.html.twig'
```

### Creating a Custom Theme

Create a Twig file with macros for each CSS class category:

```twig
{# templates/admin/theme.html.twig #}

{# Buttons #}
{% macro btn_primary() %}my-custom-btn my-custom-btn-primary{% endmacro %}
{% macro btn_danger() %}my-custom-btn my-custom-btn-danger{% endmacro %}
{% macro btn_sm() %}my-custom-btn-sm{% endmacro %}

{# Forms #}
{% macro form_input() %}my-custom-input{% endmacro %}
{% macro form_input_sm() %}my-custom-input my-custom-input-sm{% endmacro %}
{% macro form_checkbox() %}my-custom-checkbox{% endmacro %}

{# Tables #}
{% macro table() %}my-custom-table{% endmacro %}

{# Text #}
{% macro text_muted() %}my-custom-muted{% endmacro %}

{# ... see theme files for full list of available macros #}
```

Then configure it:

```yaml
kachnitel_admin:
    theme: 'admin/theme.html.twig'
```

### Extending a Built-in Theme

You can start from an existing theme and override specific macros:

```twig
{# templates/admin/theme.html.twig #}
{# Import all macros from Bootstrap theme #}
{% import '@KachnitelAdmin/theme/bootstrap.html.twig' as bootstrap %}

{# Re-export with modifications #}
{% macro btn_primary() %}btn btn-lg btn-primary{% endmacro %}
{% macro btn_danger() %}{{ bootstrap.btn_danger() }} text-uppercase{% endmacro %}

{# Re-export unchanged macros #}
{% macro form_input() %}{{ bootstrap.form_input() }}{% endmacro %}
{% macro table() %}{{ bootstrap.table() }}{% endmacro %}
{# ... etc #}
```

### Available CSS Macros

| Macro | Bootstrap Default | Used For |
|-------|------------------|----------|
| `btn_primary()` | `btn btn-primary` | Primary action buttons |
| `btn_danger()` | `btn btn-danger` | Delete/destructive buttons |
| `btn_outline_primary()` | `btn btn-outline-primary` | Secondary action buttons |
| `btn_sm()` | `btn-sm` | Small button modifier |
| `form_input()` | `form-control` | Text inputs |
| `form_input_sm()` | `form-control form-control-sm` | Small inputs (filters) |
| `form_checkbox()` | `form-check-input` | Checkboxes |
| `table()` | `table table-hover` | Data tables |
| `text_muted()` | `text-muted` | Muted/secondary text |
| `pagination()` | `pagination pagination-sm mb-0` | Pagination wrapper |
| `page_link()` | `page-link` | Pagination links |

See `templates/theme/bootstrap.html.twig` for the complete list.

### How Templates Use Themes

Templates import the theme and call macros:

```twig
{% import kachnitel_admin_theme as css %}

<button class="{{ css.btn_primary() }}">Save</button>
<input type="text" class="{{ css.form_input() }}">
<table class="{{ css.table() }}">...</table>
```

The `kachnitel_admin_theme` global variable contains the configured theme path.

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
templates/bundles/KachnitelAdminBundle/
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
{# templates/bundles/KachnitelAdminBundle/types/datetime/_preview.html.twig #}
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
{# templates/bundles/KachnitelAdminBundle/types/App/Entity/User/email.html.twig #}
<a href="mailto:{{ value }}" class="text-decoration-none">
    <span class="material-icons">email</span>
    {{ value }}
</a>
```

### 3. Override All Properties of an Entity

Apply custom rendering to ALL properties of an entity:

```twig
{# templates/bundles/KachnitelAdminBundle/types/App/Entity/User/_preview.html.twig #}
{% if value is null %}
    <span class="text-muted">N/A</span>
{% else %}
    {# Custom User property rendering #}
    <strong>{{ value }}</strong>
{% endif %}
```

<details>
<summary><strong>4. Override the Entity List Page</strong></summary>

Customize the page that wraps the LiveComponent:

```twig
{# templates/bundles/KachnitelAdminBundle/admin/index_live.html.twig #}
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

</details>

<details>
<summary><strong>5. Override the LiveComponent Itself (Advanced)</strong></summary>

Override the entire entity list component:

```twig
{# templates/bundles/KachnitelAdminBundle/components/EntityList.html.twig #}
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

**You MUST preserve:**
- `{{ attributes }}` on the root element
- `data-model` attributes for LiveComponent reactivity
- `data-action` attributes for actions

</details>

<details>
<summary><strong>6. Override Collection Display</strong></summary>

Change how collections (OneToMany, ManyToMany) are rendered:

```twig
{# templates/bundles/KachnitelAdminBundle/types/_collection.html.twig #}
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

</details>

<details>
<summary><strong>7. Override Boolean Display</strong></summary>

Custom boolean rendering with icons:

```twig
{# templates/bundles/KachnitelAdminBundle/types/boolean/_preview.html.twig #}
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

</details>

## Important Limitations

### ❌ Cannot Use `{% extends %}` in Overrides

When you override a template, Symfony resolves `@KachnitelAdmin/template.html.twig` to YOUR override. This creates a circular reference:

**This WILL NOT work:**
```twig
{# templates/bundles/KachnitelAdminBundle/admin/index_live.html.twig #}
{% extends '@KachnitelAdmin/admin/index_live.html.twig' %}  {# ❌ CIRCULAR REFERENCE! #}
```

**Instead, do this:**
```twig
{# templates/bundles/KachnitelAdminBundle/admin/index_live.html.twig #}
{% extends 'layout.html.twig' %}  {# ✅ Extend your app layout #}

{% block content %}
    {# Copy and customize the content you need #}
{% endblock %}
```

### ❌ Entity-Specific Index Pages Not Supported

The GenericAdminController hardcodes the template path, so these DON'T work:

```
❌ templates/bundles/KachnitelAdminBundle/admin/Product/index_live.html.twig
❌ templates/bundles/KachnitelAdminBundle/admin/User/index.html.twig
```

**Alternative:** Create a custom controller that renders the `EntityList` component:

```php
// src/Controller/Admin/ProductController.php
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProductController extends AbstractController
{
    #[Route('/admin/products', name: 'app_product_index')]
    public function index(): Response
    {
        return $this->render('admin/product/index.html.twig');
    }
}
```

```twig
{# templates/admin/product/index.html.twig #}
{% extends 'layout.html.twig' %}

{% block content %}
    <h1>Products</h1>
    {# Full functionality: search, filters, pagination, sorting #}
    <twig:K:Admin:EntityList dataSourceId="Product" />
{% endblock %}
```

Use `#[AdminRoutes]` on your entity to wire up the custom routes:

```php
#[Admin(label: 'Products')]
#[AdminRoutes(['index' => 'app_product_index'])]
class Product { }
```

## Testing Your Overrides

### 1. Check Template Resolution

Use Symfony's debug command to verify which template is being used:

```bash
php bin/console debug:twig @KachnitelAdmin/types/datetime/_preview.html.twig
```

**Expected output when override exists:**
```
Matched File
------------
templates/bundles/KachnitelAdminBundle/types/datetime/_preview.html.twig

Overridden Files
----------------
vendor/kachnitel/admin-bundle/templates/types/datetime/_preview.html.twig
```

### 2. Clear Cache

After creating or modifying override templates:

```bash
php bin/console cache:clear
```

### 3. Check for Syntax Errors

Validate your Twig syntax:

```bash
php bin/console lint:twig templates/bundles/KachnitelAdminBundle/
```

### 4. Verify Available Variables

Add debugging output to see what variables are available:

```twig
{# Temporary debugging #}
{{ dump(entity) }}
{{ dump(value) }}
{{ dump(property) }}
```

<details>
<summary><strong>Template Variables Reference</strong></summary>

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

</details>

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
- Review bundle templates in `vendor/kachnitel/admin-bundle/templates/`
- See [Symfony's template documentation](https://symfony.com/doc/current/templates.html)
