=== HXSE — Code-First Search ===
Contributors: youheiokubo
Tags: search, filter, ajax, shortcode, custom post type
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Code-first search & filter for WordPress. Define filters with PHP arrays, output with a shortcode. Powered by htmx — no page reloads.

== Description ==

HXSE — Code-First Search lets you define search filters with PHP arrays and output them with a simple shortcode. No JavaScript configuration required. Powered by htmx for seamless, no-reload filtering.

**Why HXSE?**

* **Code-first** — Define everything in PHP arrays. Version-control friendly.
* **No page reloads** — htmx handles all filtering and pagination seamlessly.
* **AI-friendly** — Simple, consistent schema structure that AI agents can read and write.
* **No dependencies** — htmx is bundled. No jQuery required.
* **Fully customizable** — All styles use CSS custom properties (design tokens). Ships with `DESIGN.md` for a complete variable reference and customization examples.

**Filter Types**

* `search` — Keyword search
* `taxonomy` — Filter by taxonomy / category
* `meta` — Filter by custom field value or range
* `date` — Filter by year
* `relation` — Filter by related post
* `select` — Filter external-source data by field value (api / rss / xml, v1.8.0+)

**UI Types**

* `select` — Dropdown
* `radio` — Radio buttons
* `checkbox` — Multiple selection
* `range` — Min/max slider (for numeric meta fields)

**Pagination Modes**

* `pager` — Numbered page links with count display
* `loadmore` — "Load more" button

**Basic Usage**

Define a schema in your theme's `functions.php`:

    add_filter( 'hxse_schemas', function( $schemas ) {
        $schemas['staff_search'] = [
            'post_type' => 'staff',
            'filters'   => [
                ['key' => 'keyword',    'type' => 'search',   'label' => 'キーワード'],
                ['key' => 'department', 'type' => 'taxonomy',  'label' => '部署',
                    'taxonomy' => 'department',
                    'ui'       => 'checkbox',
                ],
            ],
            'pagination' => [
                'mode'     => 'pager',
                'per_page' => 12,
            ],
            'template' => 'hxse-staff.php',
        ];
        return $schemas;
    } );

Then place the shortcode on any page:

    [hxse id="staff_search"]

== Installation ==

1. Upload the `hxse-code-first-search` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Add `hxse_schemas` filter to your theme's `functions.php` or a custom plugin.
4. Add `[hxse id="your_schema_id"]` shortcode to any page.

== Frequently Asked Questions ==

= Do I need to write JavaScript? =

No. HXSE handles all htmx configuration automatically. You only write PHP to define your filters.

= Can I use multiple search instances on one page? =

Yes. Use the `prefix` option in `url_params` to avoid parameter conflicts.

= How do I customize the result template? =

Create `hxse/your-template.php` in your theme directory and specify it in the schema's `template` key.

= Does it work with custom fields from ACF? =

Yes. Use `type: 'meta'` with the appropriate `meta_key`.

== Changelog ==

= 1.9.0 =
* Added: Filter, sort, and pagination support for merged mode (`sources`) — the v1.8.0 external-source filter UI now works on merged multi-source data (opt-in: enabled when `filters`, `sort`, or `pagination` is present in the schema)
* Added: REST endpoint handling for merged-mode schemas — filter interactions and pager navigation now update results without page reloads, same as other modes
* Note: Merged items are normalized to a common shape (`title` / `link` / `date` / `excerpt` / `source`), so filters and sort definitions target these fields — e.g. a `select` filter on `source` lets visitors narrow results to one feed
* Note: All filtering runs in memory against merged cached data. No remote re-fetches on filter interactions. Schemas without `filters` / `sort` / `pagination` behave exactly as before.

= 1.8.0 =
* Added: Filter UI for external sources (`api` / `rss` / `xml`) — `search` and `select` filter types now work on fetched data (opt-in: enabled when `filters`, `sort`, or `pagination` is present in the schema)
* Added: `select` filter type — exact-match filtering on any item field, with `'options' => 'auto'` to generate choices from the data automatically
* Added: `sort` support for external sources — sort definitions accept `field`, `order`, and `compare` ('string' / 'numeric' / 'date')
* Added: Pagination for external sources — `per_page`, `show_count`, and `show_pages` work on fetched data via in-memory slicing (pager mode only; loadmore is not supported for external sources)
* Added: `items_key` schema key — extract the item list from wrapped JSON payloads (dot notation supported, e.g. connpass's `events`)
* Added: Bundled default template `templates/api.php` — external-source schemas without a custom template now render a clean list instead of raw JSON debug output
* Note: All filtering, sorting, and pagination run in memory against cached data. Filter interactions never trigger remote re-fetches.
* Note: Fully backward compatible — schemas without `filters` / `sort` / `pagination` behave exactly as before, and custom templates keep receiving data unchanged.

= 1.7.0 =
* Added: Filter UI inside the iframe embed view — set `embed.show_filters` to let visitors filter within the embed (WordPress sources only)
* Added: Automatic iframe height notification via postMessage — the embed reports its height to the parent on load, resize, and after htmx filtering
* Added: postMessage target origins limited to `allowed_origins` (no wildcard) for security
* Added: htmx and hxse.js are inlined into the embed page when filters are enabled
* Note: Parent pages must add a small receiver snippet to auto-resize the iframe (see manual)

= 1.6.0 =
* Added: iframe embed view — access `?hxse_embed={schema_id}` to output a list-only, self-contained HTML page for embedding on external landing pages
* Added: `embed` schema key — `enabled`, `allowed_origins`, `title`, `per_page`
* Added: Clickjacking protection — `Content-Security-Policy: frame-ancestors` restricts which domains may embed the view (same-origin only when no origins are specified)
* Added: `includes/embed.php` — embed view rendering and frame security headers

= 1.5.0 =
* Added: Merge mode (`sources` key) — combine WordPress posts, RSS feeds, and APIs into a single chronological list
* Added: `hxse_fetch_merged_data()` — fetches, normalizes, and merges multiple data sources
* Added: Source normalizers for wp_query / rss / api / xml into a common format (title, link, date, excerpt, source, raw)
* Added: `orderby` / `order` / `limit` keys for merge mode sorting
* Added: `map` key — maps API/XML response keys to the common format
* Added: `templates/merged.php` — default template with source badges
* Added: Merge list styles in hxse.css

= 1.4.0 =
* Added: `source: 'rss'` mode — fetches RSS 2.0 and Atom feeds and converts to PHP array automatically
* Added: `source: 'xml'` mode — fetches any XML and converts to PHP array using `xpath` key
* Added: `hxse_parse_rss()` — RSS 2.0 / Atom parser
* Added: `hxse_parse_xml()` — generic XML parser with xpath support
* Added: `hxse_simplexml_to_array()` — recursive SimpleXMLElement to array converter
* Improved: `hxse_do_remote_fetch()` now handles json / rss / xml based on `source` key
* Improved: backward-compatible `hxse_do_api_request()` alias retained

= 1.3.0 =
* Added: Cache mapping (`hxse_cache_map` option) — tracks schema ID → filename relationships to detect orphaned files
* Added: Orphan file detection — warns when JSON files exist without a corresponding schema
* Added: "今すぐ更新" button — manually re-fetches API and regenerates JSON from admin UI (API schemas only)
* Added: Bulk delete all cache button with total file size display
* Added: Delete orphaned files button in admin UI
* Improved: `hxse_delete_static_cache()` now also removes the mapping entry
* Improved: `uninstall.php` now also deletes the `hxse_cache_map` option

= 1.2.0 =
* Added: `cache_mode: 'static'` — saves API responses as JSON files in `wp-content/hxse-cache/` (blocked from web access via .htaccess)
* Added: `cache_file` key — custom filename for the static JSON cache
* Added: `includes/cache.php` — cache directory management (init, load, save, delete)
* Added: Admin UI — static JSON cache list with individual delete buttons (Settings → HXSE)
* Added: `uninstall.php` — removes `wp-content/hxse-cache/` directory on plugin uninstall
* Improved: refactored `hxse_fetch_api_data()` to support both transient and static cache modes

= 1.1.0 =
* Added: `source: 'api'` mode — fetch data from external APIs (GAS, REST API, etc.) via PHP and render with a custom theme template
* Added: `token` key — appended as `_token` GET parameter for simple API authentication
* Added: `cache` key — transient-based caching for API responses (seconds, 0 to disable)

= 1.0.2 =
* Added: SECURITY.md — security policy, vulnerability reporting, and disclosure timeline
* Added: MAINTENANCE.md — architecture overview, htmx update steps, and fork guide
* Docs: Updated ai-reference.md — added design philosophy and maintainability section for AI agents

= 1.0.1 =
* Grid columns now controllable via `columns` schema key (CSS variable injection).
* Table headers customizable via `table_columns` schema key.
* Assets now loaded only on pages containing the [hxse] shortcode.
* Refactored hxse.js: replaced var with const/let.
* Taxonomy conditions now auto-detect slug vs term_id.
* Fixed: escape the_title() in templates for XSS hardening.

= 1.0.0 =
* Initial release.
* Filter types: search, taxonomy, meta, date, relation.
* UI types: select, radio, checkbox, range.
* URL parameter sync with browser history support.

== External Services ==

This plugin bundles htmx (https://htmx.org/) for handling AJAX requests without page reloads. htmx is included locally within the plugin and does not make any external network requests. It is licensed under the BSD 2-Clause License.

* htmx: https://htmx.org/
* htmx License: https://github.com/bigskysoftware/htmx/blob/master/LICENSE
