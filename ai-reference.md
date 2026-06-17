# HXSE — ai-reference.md

AI agents: Read this file before generating HXSE schemas.

---

## Basic Structure

```php
add_filter( 'hxse_schemas', function( $schemas ) {
    $schemas['schema_key'] = [
        'post_type'  => 'post',       // Target post type (required)
        'display'    => 'grid',       // Display mode: 'grid' / 'list' / 'table' / 'custom'
        'filters'    => [ ... ],       // Filter definitions (required)
        'sort'       => [ ... ],       // Sort options (optional)
        'pagination' => [ ... ],       // Pagination settings (optional)
        'url_params' => [ ... ],       // URL sync settings (optional)
        'template'   => 'file.php',   // Custom template filename (for display: 'custom')
    ];
    return $schemas;
} );
```

**Shortcode**

```
[hxse id="schema_key"]
```

---

## filters Keys (Common)

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `key` | string | ✅ | Filter identifier (alphanumeric + underscore) |
| `type` | string | ✅ | Filter type (see below) |
| `label` | string | — | Display label for the filter |
| `ui` | string | — | UI type: `select` / `radio` / `checkbox` / `range` (default: `select`) |

---

## Filter Types

### search
```php
['key' => 'keyword', 'type' => 'search', 'label' => 'Keyword']
```
Always renders as a text input. No `ui` key needed.

**Extended options:**
```php
['key' => 'keyword', 'type' => 'search', 'label' => 'Keyword',
    'normalize'     => true,               // Japanese text normalization
    'search_fields' => [                   // Additional fields to search
        'post_title',
        'post_content',
        'my_meta_key',                     // Any post meta key
    ],
]
```

### taxonomy
```php
['key' => 'category', 'type' => 'taxonomy', 'label' => 'Category',
    'taxonomy'   => 'category',            // Taxonomy slug (required)
    'ui'         => 'checkbox',            // 'select' / 'radio' / 'checkbox'
    'show_count' => true,                  // Show facet counts
]
```

### meta
```php
// Select / radio / checkbox
['key' => 'level', 'type' => 'meta', 'label' => 'Level',
    'meta_key'   => 'my_level',            // Post meta key (required)
    'ui'         => 'select',              // 'select' / 'radio' / 'checkbox'
    'options'    => ['junior' => 'Junior', 'senior' => 'Senior'],
    'show_count' => true,
]

// Numeric range slider
['key' => 'price', 'type' => 'meta', 'label' => 'Price',
    'meta_key' => 'my_price',
    'ui'       => 'range',
    'min'      => 0,
    'max'      => 100000,
    'step'     => 1000,
]
```

### date
```php
['key' => 'year', 'type' => 'date', 'label' => 'Year',
    'ui'         => 'select',
    'start_year' => 2020,                  // Default: current year - 10
]
```

### relation
```php
['key' => 'project', 'type' => 'relation', 'label' => 'Project',
    'related_post_type' => 'project',      // Related CPT slug (required)
    'meta_key'          => 'my_project_id', // Meta key storing the related post ID
    'ui'                => 'select',
]
```

---

## sort

```php
'sort' => [
    ['key' => 'date_desc',  'label' => 'Newest'],
    ['key' => 'date_asc',   'label' => 'Oldest'],
    ['key' => 'title_asc',  'label' => 'A–Z'],
    ['key' => 'title_desc', 'label' => 'Z–A'],
    ['key' => 'menu_order', 'label' => 'Order'],
],
```

First item is the default sort order.

---

## pagination

```php
// Pager (numbered links)
'pagination' => [
    'mode'         => 'pager',             // Default
    'per_page'     => 12,
    'show_pages'   => true,
    'show_count'   => true,
    'count_format' => '{total} results ({from}–{to})',
    'range'        => 2,                   // Pages shown around current page
    'label_prev'   => 'Prev',
    'label_next'   => 'Next',
    'label_first'  => 'First',             // Omit to hide
    'label_last'   => 'Last',              // Omit to hide
],

// Load more button
'pagination' => [
    'mode'       => 'loadmore',
    'per_page'   => 12,
    'show_count' => true,
    'label_more' => 'Load more',
],
```

`count_format` placeholders: `{total}` `{from}` `{to}` `{current_page}`

---

## url_params

```php
'url_params' => [
    'enable' => true,                      // Sync filters to URL (default: true)
    'prefix' => '',                        // Prefix for multiple instances on one page
    'mode'   => 'always',                  // 'always' / 'submit_only'
],
```

---

## display

```php
'display' => 'grid',    // Card grid (default)
'display' => 'list',    // Compact horizontal list
'display' => 'table',   // Table with date, title, category
'display' => 'custom',  // Use custom template (set 'template' key)
```

### Custom template

Override in theme:

```
your-theme/hxse/grid.php       ← override grid template
your-theme/hxse/list.php       ← override list template
your-theme/hxse/table-row.php  ← override table row template
your-theme/hxse/my-template.php ← custom template for 'custom' mode
```

---

## wrapper (for display: 'custom')

```php
'wrapper' => [
    'container' => '<ul class="my-list">',
    'item'      => '<li class="my-item">',
],
```

---

## Complete Example

```php
add_filter( 'hxse_schemas', function( $schemas ) {

    $schemas['staff_search'] = [
        'post_type' => 'staff',
        'display'   => 'grid',
        'filters'   => [
            ['key' => 'keyword',    'type' => 'search',   'label' => 'Keyword',
                'normalize' => true,
            ],
            ['key' => 'department', 'type' => 'taxonomy',  'label' => 'Department',
                'taxonomy'   => 'department',
                'ui'         => 'checkbox',
                'show_count' => true,
            ],
            ['key' => 'level',      'type' => 'meta',     'label' => 'Level',
                'meta_key' => 'my_level',
                'ui'       => 'radio',
                'options'  => ['junior' => 'Junior', 'senior' => 'Senior', 'lead' => 'Lead'],
            ],
        ],
        'sort' => [
            ['key' => 'date_desc', 'label' => 'Newest'],
            ['key' => 'title_asc', 'label' => 'A–Z'],
        ],
        'pagination' => [
            'mode'       => 'pager',
            'per_page'   => 12,
            'show_count' => true,
        ],
        'url_params' => ['enable' => true],
        'template'   => 'hxse-staff.php',
    ];

    return $schemas;
} );
```

---

## What HXSE Does NOT Support

- Infinite scroll (use `loadmore` instead)
- Search result caching (delegate to server-side cache plugins)
- AI semantic/vector search (implement as a recipe using OpenAI API)
- Saving search logs to DB (use Google Analytics / Search Console)

---

## display_switcher

```php
'display'          => 'grid',                     // Initial display mode
'display_switcher' => ['grid', 'list'],            // Show grid + list switcher
'display_switcher' => ['grid', 'list', 'table'],   // Show all three
// Omit or false → no switcher shown
```

**Behavior:**
- Switching display mode always resets to page 1
- With `loadmore`, appended items are cleared on display switch
- This is standard web behavior (consistent with major e-commerce sites)

---

## tabs

```php
'tabs' => [
    ['label' => 'All',    'conditions' => []],
    ['label' => 'News',   'conditions' => [
        ['type' => 'taxonomy', 'taxonomy' => 'category', 'terms' => [3]],
    ]],
    ['label' => 'Events', 'conditions' => [
        ['type' => 'taxonomy', 'taxonomy' => 'category', 'terms' => [5]],
    ]],
],
```

First tab (index 0) is active by default.

---

## conditions

Fixed filter conditions applied without any UI. Useful for "latest N items" lists.

```php
'filters'    => [],    // No filter UI
'conditions' => [
    ['type' => 'taxonomy', 'taxonomy' => 'category', 'terms' => [3, 5]],
    ['type' => 'meta',     'meta_key' => 'my_level', 'value' => 'senior', 'compare' => '='],
],
```

| Key | Type | Description |
|---|---|---|
| `type` | string | `'taxonomy'` or `'meta'` |
| `taxonomy` | string | Taxonomy slug (for `taxonomy` type) |
| `terms` | array | Term IDs (for `taxonomy` type) |
| `meta_key` | string | Meta key (for `meta` type) |
| `value` | string | Meta value (for `meta` type) |
| `compare` | string | Comparison operator (default: `'='`) |
