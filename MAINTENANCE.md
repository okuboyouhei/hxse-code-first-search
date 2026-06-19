# Maintenance Guide — HXSE — Code-First Search

This file is for developers who want to maintain, modify, or fork this plugin independently.

HXSE is intentionally simple. There are no compiled assets, no build pipeline, and no external services required. A developer comfortable with PHP and WordPress can maintain it without help from the original author.

---

## Plugin Architecture

```
hxse-code-first-search/
├── hxse-code-first-search.php  # Entry point. Defines constants, loads includes, registers assets.
├── includes/
│   ├── schema.php              # Schema registration (hxse_schemas filter)
│   ├── shortcode.php           # [hxse] shortcode handler
│   ├── endpoint.php            # AJAX/direct HTML endpoint (header()+echo+exit)
│   ├── query.php               # WP_Query builder from schema + user input
│   ├── filters.php             # Filter UI HTML generation
│   ├── pagination.php          # Pager and loadmore HTML
│   └── admin.php               # Admin settings page
├── templates/
│   ├── default.php             # Grid display template
│   ├── list.php                # List display template
│   └── table-row.php           # Table row template
├── assets/
│   ├── htmx.min.js             # Bundled htmx (see "Updating htmx" below)
│   ├── htmx-LICENSE.txt        # htmx license file (keep alongside htmx.min.js)
│   ├── hxse.js                 # Filter behavior (URL sync, tab switching, display switcher)
│   └── hxse.css                # All styles (CSS custom properties, see DESIGN.md)
├── ai-reference.md             # AI agent schema reference
├── llms.txt                    # LLM entry point
├── DESIGN.md                   # CSS custom property reference
├── MAINTENANCE.md              # This file
├── SECURITY.md                 # Security policy
├── readme.txt                  # WordPress.org readme
└── uninstall.php               # Cleanup on uninstall
```

---

## How a Search/Filter Request Works

1. User changes a filter → htmx sends a GET request to the AJAX endpoint
2. `endpoint.php` receives the request, identifies the schema by `hxse_id`
3. `schema.php` loads the registered schema for that ID
4. URL parameters are sanitized and matched against schema-defined filter keys
5. `query.php` builds a `WP_Query` from the sanitized parameters
6. The matching template (`default.php`, `list.php`, `table-row.php`, or custom) renders the results
7. `pagination.php` appends pager or loadmore HTML
8. `endpoint.php` outputs the HTML directly (`header()+echo+exit`) — no JSON, no REST API
9. htmx swaps the result into the page

**Key design decision:** The endpoint outputs raw HTML, not JSON. This keeps the response simple and avoids a client-side rendering step.

---

## Updating htmx

htmx is bundled at `assets/htmx.min.js`. The script handle is `hx-htmx` (shared with HXFE). If both plugins are active, only one copy of htmx loads.

**Steps to update:**

1. Download the new `htmx.min.js` from https://unpkg.com/htmx.org/dist/htmx.min.js
2. Replace `assets/htmx.min.js`
3. Update the version note in `readme.txt` changelog
4. Test: change a filter, verify the AJAX request succeeds and results update

HXSE uses only core htmx attributes (`hx-get`, `hx-target`, `hx-swap`, `hx-trigger`, `hx-indicator`). No htmx extensions are used, so major version upgrades are unlikely to break anything — but always test after updating.

---

## Adding a Filter Type

Each filter type is handled in `includes/filters.php` (UI generation) and `includes/query.php` (WP_Query conversion).

To add a new filter type (e.g. `author`):

1. In `includes/filters.php`, add a case for `'author'` that returns the filter UI HTML
2. In `includes/query.php`, add logic to translate the `author` parameter into a `WP_Query` argument
3. Add the type to `ai-reference.md` under `## Filter Types`

---

## Adding a Display Mode

Display modes are handled in `endpoint.php` (template selection) and `templates/` (rendering).

To add a new display mode (e.g. `masonry`):

1. Create `templates/masonry.php`
2. In `endpoint.php`, add a case for `'masonry'` that loads the new template
3. Add the mode to `ai-reference.md` under `## display`

Users can also override templates from their theme without forking:

```
your-theme/hxse/grid.php        ← override grid template
your-theme/hxse/list.php        ← override list template
your-theme/hxse/table-row.php   ← override table row template
your-theme/hxse/my-template.php ← custom template for display: 'custom'
```

---

## Modifying Styles

All styles use CSS custom properties. See `DESIGN.md` for the full variable reference.

To change default styles without forking:

```css
/* In your theme's style.css or a custom CSS block */
:root {
  --hxse-color-primary: #your-color;
  --hxse-radius: 4px;
}
```

---

## Forking This Plugin

GPLv2 allows you to fork and redistribute freely. Notes for a successful fork:

1. **Rename the plugin slug** — Change the directory name and the `Plugin Name:` header in the entry point to avoid conflicts with the original.
2. **Update the filter hook name** — `hxse_schemas` is the main integration point. If you rename it, existing user code will break — so consider keeping it.
3. **Update the shortcode** — `[hxse]` is registered in `includes/shortcode.php`.
4. **No build step needed** — There is no webpack, no npm, no Sass. Edit PHP and CSS directly.
5. **No database schema** — There are no custom tables. The plugin uses only WordPress options (prefixed `hxse_`).
6. **htmx is shared with HXFE** — Both plugins register htmx under the same handle `hx-htmx`. If you fork HXSE standalone, rename the handle to avoid conflicts.

---

## Release Checklist

Files that must be updated on every release:

- `hxse-code-first-search.php` — `Version:` header and `HXSE_VERSION` constant
- `readme.txt` — `Stable tag:` and `== Changelog ==`
- `llms.txt` — `Current version:` and changelog entry
- `ai-reference.md` — version note if applicable
- `HXSE-manual.md` — Version, last updated date, changelog

---

## Common Modification Patterns

### Change the grid card layout
Override `your-theme/hxse/grid.php`. Copy the original from `templates/default.php` as a starting point.

### Add custom WP_Query arguments
```php
add_filter( 'hxse_query_args', function( $args, $schema, $params ) {
    // $args   = current WP_Query arguments
    // $schema = full schema definition
    // $params = sanitized URL parameters
    if ( ( $schema['post_type'] ?? '' ) === 'staff' ) {
        $args['meta_key']  = 'display_order';
        $args['orderby']   = 'meta_value_num';
    }
    return $args;
}, 10, 3 );
```

### Change results per page dynamically
Use the `hxse_query_args` filter to override `posts_per_page` based on any condition.

---

*Last updated: 2026-06-19*
