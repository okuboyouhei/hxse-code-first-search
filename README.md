# HXSE — Code-First Search

**Define WordPress search filters as PHP arrays. Pull in external APIs, RSS, and XML. AI-ready, Git-managed, no page reloads.**

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org) [![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net) [![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

[WordPress.org](https://wordpress.org/plugins/hxse-code-first-search/) · [Documentation](https://github.com/okuboyouhei/hxse-code-first-search/blob/main/HXSE-manual.md) · [Report Issue](https://github.com/okuboyouhei/hxse-code-first-search/issues)

---

## What is HXSE?

HXSE is a code-first WordPress search & filter plugin powered by [htmx](https://htmx.org/). Instead of configuring filters in a GUI, you define them as PHP arrays and place a shortcode anywhere.

**HXSE** stands for **htmx Search Engine**.

What started as a search & filter plugin has grown into a small **data integration tool**: HXSE can pull data from WordPress posts, external JSON APIs, RSS/Atom feeds, and arbitrary XML — and even **merge multiple sources into a single chronological list**.

Because filters are PHP arrays, AI coding tools (Claude, Cursor, GitHub Copilot) can read and edit them directly — no screenshots, no GUI walkthroughs, no copy-paste. Ask your AI assistant to "add a price range filter" and get back a diff-ready code change instantly.

HXSE ships with `llms.txt`, `ai-reference.md`, and `DESIGN.md` so AI agents understand the schema format out of the box.

```php
add_filter( 'hxse_schemas', function( $schemas ) {
    $schemas['news_search'] = [
        'post_type' => 'post',
        'filters'   => [
            ['key' => 'keyword',  'type' => 'search',   'label' => 'Keyword'],
            ['key' => 'category', 'type' => 'taxonomy',  'label' => 'Category',
                'taxonomy' => 'category',
                'ui'       => 'checkbox',
            ],
        ],
        'pagination' => ['mode' => 'pager', 'per_page' => 12],
    ];
    return $schemas;
} );
```

```
[hxse id="news_search"]
```

That's it. A fully functional, no-reload search filter is ready.

---

## Why code-first?

| Problem with GUI builders      | HXSE solution                                              |
| ------------------------------ | ---------------------------------------------------------- |
| AI can't edit filters directly | Filters are PHP arrays — AI reads and writes them natively |
| Config stored in database      | Filters live in your codebase                              |
| Can't track changes in Git     | Every change shows in `git diff`                           |
| Config disappears after deploy | Filters deploy with your theme                             |
| Hard to reuse across projects  | Copy one PHP array to the next project                     |

---

## Key Features

- **5 filter types** — search, taxonomy, meta, date, relation
- **4 display modes** — grid, list, table, custom
- **2 pagination modes** — pager, loadmore
- **External data sources** — fetch from JSON APIs, RSS/Atom feeds, and XML
- **Merge mode** — combine WordPress posts, RSS, and APIs into one chronological list
- **Static JSON caching** — cache external data to file with a built-in management UI
- **Japanese normalization** — katakana → hiragana, full-width → half-width, uppercase → lowercase
- **Facet counts** — show result counts next to each option
- **URL sync** — filters reflected in browser URL, shareable links, browser back support
- **Mobile-first** — collapsible filter panel on 768px and below
- **AI-friendly** — ships with `llms.txt`, `ai-reference.md`, and `DESIGN.md`

---

## External Data Sources

Set the `source` key to pull data from outside WordPress.

| source | Data origin |
| --- | --- |
| (default) | WordPress posts (`WP_Query`) |
| `'api'` | External JSON API |
| `'rss'` | RSS 2.0 / Atom feed |
| `'xml'` | Arbitrary XML (xpath) |

### API mode (v1.1.0+)

```php
$schemas['survey_results'] = [
    'source'   => 'api',
    'endpoint' => 'https://script.google.com/macros/s/xxx/exec',
    'token'    => 'your-secret-token',   // appended as _token GET param
    'display'  => 'custom',
    'template' => 'chart',               // your-theme/hxse/chart.php
    'cache'    => 60,
];
```

The fetched array is passed to your template as `$hxse_api_data`. Render it with Chart.js or any library you like.

### RSS mode (v1.4.0+)

```php
$schemas['zenn_feed'] = [
    'source'   => 'rss',
    'endpoint' => 'https://zenn.dev/youheiokubo/feed',
    'cache'    => 600,
    'display'  => 'custom',
    'template' => 'feed',
];
```

RSS 2.0 and Atom are auto-detected. Each entry is normalized to `title` / `link` / `description` / `pubDate` / `guid`.

### XML mode (v1.4.0+)

```php
$schemas['products'] = [
    'source'   => 'xml',
    'endpoint' => 'https://example.com/data.xml',
    'xpath'    => '//product',
    'cache'    => 3600,
    'display'  => 'custom',
    'template' => 'product-list',
];
```

Attributes become `@key`, child elements keep their tag names.

---

## Filtering External Sources (v1.8.0+)

External-source schemas (`api` / `rss` / `xml`) now support the same filter, sort, and pagination UI as WordPress queries. Everything runs in memory against cached data — filter interactions never re-fetch the remote endpoint.

```php
$schemas['connpass_nagoya'] = [
    'source'     => 'api',
    'endpoint'   => 'https://connpass.com/api/v1/event/?prefecture=aichi&count=100',
    'items_key'  => 'events',        // extract the list from wrapped JSON (dot notation OK)
    'cache_mode' => 'static',
    'cache'      => 3600,
    'filters'    => [
        [ 'key' => 'keyword', 'type' => 'search', 'label' => 'Keyword',
          'search_fields' => [ 'title', 'catch' ] ],
        [ 'key' => 'area', 'type' => 'select', 'label' => 'Area',
          'field' => 'address', 'options' => 'auto' ],   // choices auto-generated from data
    ],
    'sort' => [
        [ 'key' => 'date_asc',  'label' => 'By date',   'field' => 'started_at', 'order' => 'asc',  'compare' => 'date' ],
        [ 'key' => 'date_desc', 'label' => 'Newest',    'field' => 'started_at', 'order' => 'desc', 'compare' => 'date' ],
    ],
    'pagination' => [ 'per_page' => 10, 'show_count' => true, 'show_pages' => true ],
];
```

- `search` matches across `search_fields` (or every top-level string field when omitted)
- `select` filters by exact match on any item field; `'options' => 'auto'` builds the choices from unique values in the data
- Sort definitions take `field` (dot notation supported), `order` (`asc` / `desc`), and `compare` (`string` / `numeric` / `date`)
- Opt-in and fully backward compatible: schemas without `filters` / `sort` / `pagination` behave exactly as before
- A bundled default template (`templates/api.php`) renders external data cleanly when no custom template is provided

## Merge Mode (v1.5.0+)

Combine multiple data sources into a single chronological list. Perfect for showing your site's announcements (WordPress) alongside your Zenn/note articles (RSS) on one page.

```php
$schemas['mixed_news'] = [
    'sources' => [
        [
            'type'      => 'wp_query',
            'post_type' => 'post',
            'label'     => 'お知らせ',
        ],
        [
            'type'     => 'rss',
            'endpoint' => 'https://zenn.dev/youheiokubo/feed',
            'label'    => 'Zenn',
        ],
    ],
    'orderby' => 'date',   // date | title
    'order'   => 'desc',
    'limit'   => 20,
    'cache'   => 600,
];
```

Each source is normalized to a common format (`title` / `link` / `date` / `excerpt` / `source` / `raw`) and merged by date. The `label` appears as a badge so readers can tell each item's origin. API/XML sources can map their keys via the `map` key.

---

## Static JSON Caching (v1.2.0+)

External data can be cached two ways:

| cache_mode | Storage | Notes |
| --- | --- | --- |
| `'transient'` (default) | DB transient | Simple, auto-expires |
| `'static'` | JSON file | Fast, saved to `wp-content/hxse-cache/` |

```php
$schemas['survey_results'] = [
    'source'     => 'api',
    'endpoint'   => 'https://script.google.com/macros/s/xxx/exec',
    'cache_mode' => 'static',
    'cache_file' => 'survey.json',
    'cache'      => 3600,
    'display'    => 'custom',
    'template'   => 'chart',
];
```

Static JSON is stored in `wp-content/hxse-cache/`, blocked from web access via `.htaccess`.

### Cache management UI (v1.3.0+)

From **Settings → HXSE** you can:

- **Refresh now** — re-fetch the API and regenerate the JSON
- **Delete / Delete all** — remove cache files
- **Orphan detection** — find and remove JSON files left behind after a schema or `cache_file` change

HXSE tracks the schema-to-file mapping, so stale files never pile up.

---

## Why HXSE over paid alternatives?

|                    | HXSE           | FacetWP   | Search & Filter |
| ------------------ | -------------- | --------- | --------------- |
| Price              | **Free**       | $99+/year | Free / Pro      |
| Configuration      | **PHP arrays** | GUI       | GUI             |
| Git-manageable     | **✅**          | ❌         | ❌               |
| htmx (no reload)   | **✅**          | ❌         | ❌               |
| External API/RSS   | **✅**          | ❌         | ❌               |
| Japanese normalize | **✅**          | ❌         | ❌               |
| AI-friendly        | **✅**          | ❌         | ❌               |

---

## Japanese Support

HXSE includes built-in Japanese text normalization — something no English-first plugin provides:

```php
['key' => 'keyword', 'type' => 'search', 'label' => 'キーワード',
    'normalize' => true,
]
```

| Input       | Normalized                                 |
| ----------- | ------------------------------------------ |
| `ワードプレス`    | `わーどぷれす` (katakana → hiragana)             |
| `ＰＨＰ`       | `php` (full-width → half-width, lowercase) |
| `WordPress` | `wordpress` (uppercase → lowercase)        |

---

## Display Modes

Change the output with one key:

```php
'display' => 'grid',    // Card grid (default)
'display' => 'list',    // Compact horizontal list
'display' => 'table',   // Table with date, title, category
'display'  => 'custom', // Your own PHP template
'template' => 'hxse-staff.php',
```

Add `display_switcher` to let users toggle:

```php
'display_switcher' => ['grid', 'list', 'table'],
```

---

## Quick Reference

### Schema keys

| Key          | Type   | Description                                        |
| ------------ | ------ | -------------------------------------------------- |
| `post_type`  | string | Target post type (default: `'post'`)               |
| `source`     | string | `'api'` / `'rss'` / `'xml'` for external data       |
| `sources`    | array  | Merge mode: multiple sources combined              |
| `display`    | string | `'grid'` / `'list'` / `'table'` / `'custom'`       |
| `columns`    | int    | Grid column count                                  |
| `filters`    | array  | Filter definitions                                 |
| `tabs`       | array  | Tab-based filtering                                |
| `sort`       | array  | Sort options                                       |
| `pagination` | array  | Pagination settings                                |
| `cache`      | int    | Cache seconds (for external sources)               |
| `cache_mode` | string | `'transient'` / `'static'`                         |
| `template`   | string | Custom template filename                           |

### Filter keys

| Key                    | Type   | Description                                                        |
| ---------------------- | ------ | ------------------------------------------------------------------ |
| `key`                  | string | **Required.** Unique filter identifier                             |
| `type`                 | string | **Required.** `search` / `taxonomy` / `meta` / `date` / `relation` |
| `label`                | string | Filter label                                                       |
| `ui`                   | string | `select` / `radio` / `checkbox` / `range`                          |
| `taxonomy`             | string | Taxonomy slug (for `taxonomy` type)                                |
| `meta_key`             | string | Meta key (for `meta` type)                                         |
| `options`              | array  | Options for select/radio/checkbox                                  |
| `show_count`           | bool   | Show facet counts next to options                                  |
| `normalize`            | bool   | Japanese text normalization (for `search` type)                    |
| `search_fields`        | array  | Custom fields to include in keyword search                         |
| `min` / `max` / `step` | float  | Range slider settings                                              |

---

## Template Customization

Place a custom template in your theme:

```
your-theme/
└── hxse/
    ├── hxse-staff.php   ← display: 'custom'
    ├── feed.php         ← RSS source template
    ├── chart.php        ← API source template
    └── merged.php       ← merge mode template
```

```php
// your-theme/hxse/hxse-staff.php
$position = get_post_meta( $post->ID, 'my_position', true );
?>
<article>
    <a href="<?php the_permalink(); ?>">
        <?php the_post_thumbnail( 'medium' ); ?>
        <h2><?php the_title(); ?></h2>
        <p><?php echo esc_html( $position ); ?></p>
    </a>
</article>
```

---

## Related Plugins

**[HXFE — Code-First Forms](https://github.com/okuboyouhei/hxfe-code-first-forms)** — Define contact forms with PHP arrays. Same code-first philosophy. Both plugins share the `hx-htmx` handle so htmx loads only once when used together on the same page.

HXFE + HXSE integration patterns:
- **Search → Form**: pass a selected item from HXSE results into an HXFE form via URL params
- **Diagnosis → Search**: HXFE chatbot collects preferences, then links to HXSE results with filters pre-applied
- **Form → Visualize**: HXFE webhook stores data in a spreadsheet, HXSE pulls it via `source: 'api'` and charts it

---

## Requirements

- WordPress 6.0+
- PHP 7.4+
- No build tools required

---

## FAQ

**Q: Does HXSE require FacetWP or any other plugin?** No. HXSE is fully standalone. htmx is bundled.

**Q: What is htmx and why does HXSE use it?** [htmx](https://htmx.org/) is a lightweight JS library that adds AJAX behavior via HTML attributes — no build step, no npm, no React. HXSE uses it to update results without page reloads.

**Q: Can I display my Zenn/note articles on my WordPress site?** Yes. Use `source: 'rss'` with the feed URL, or merge mode to combine them with your WordPress posts.

**Q: Can I use HXSE with AI coding tools?** Yes — filters defined as PHP arrays can be read and edited directly by AI tools. HXSE ships with `ai-reference.md`.

**Q: Can I use HXSE together with HXFE on the same page?** Yes. Both plugins share the `hx-htmx` handle — htmx loads only once.

---

## Security

- All input sanitized before use in `WP_Query`
- REST/AJAX endpoint is read-only (`post_status = 'publish'` enforced)
- External fetch via `wp_remote_get()` with optional token authentication
- Static cache directory protected by `.htaccess`
- Passed WordPress.org Plugin Check (ERROR: 0)

See [SECURITY.md](https://github.com/okuboyouhei/hxse-code-first-search/blob/main/SECURITY.md) for the full policy.

---

## Installation

1. Install from [WordPress.org](https://wordpress.org/plugins/hxse-code-first-search/)
2. Activate the plugin
3. Add schema to `functions.php`
4. Place `[hxse id="your-id"]` shortcode on any page

---

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

---

## Author

**Youhei Okubo** — [WordPress.org](https://profiles.wordpress.org/youheiokubo/) · [Zenn](https://zenn.dev/youheiokubo) · [GitHub](https://github.com/okuboyouhei)
