# HXSE — Code-First Search

**Define WordPress search filters as PHP arrays. AI-ready, Git-managed, no page reloads.**

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

[WordPress.org](#) · [Documentation](./HXSE-manual.md) · [Report Issue](https://github.com/okuboyouhei/hxse-code-first-search/issues)

---

## What is HXSE?

HXSE is a code-first WordPress search & filter plugin powered by [htmx](https://htmx.org/). Instead of configuring filters in a GUI, you define them as PHP arrays and place a shortcode anywhere.

**HXSE** stands for **htmx Search Engine**.

Because filters are PHP arrays, AI coding tools (Claude, Cursor, GitHub Copilot) can read and edit them directly — no screenshots, no GUI walkthroughs, no copy-paste. Ask your AI assistant to "add a price range filter" and get back a diff-ready code change instantly.

HXSE ships with `llms.txt` and `ai-reference.md` so AI agents understand the schema format out of the box — the lowest-cost way to build and maintain WordPress search filters with AI.

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

| Problem with GUI builders | HXSE solution |
|---|---|
| AI can't edit filters directly | Filters are PHP arrays — AI reads and writes them natively |
| Config stored in database | Filters live in your codebase |
| Can't track changes in Git | Every change shows in `git diff` |
| Config disappears after deploy | Filters deploy with your theme |
| Hard to reuse across projects | Copy one PHP array to the next project |

---

## Why HXSE over paid alternatives?

| | HXSE | FacetWP | Search & Filter |
|---|---|---|---|
| Price | **Free** | $99+/year | Free / Pro |
| Configuration | **PHP arrays** | GUI | GUI |
| Git-manageable | **✅** | ❌ | ❌ |
| htmx (no reload) | **✅** | ❌ | ❌ |
| Japanese normalize | **✅** | ❌ | ❌ |
| AI-friendly | **✅** | ❌ | ❌ |

---

## Key Features

- **5 filter types** — search, taxonomy, meta, date, relation
- **4 display modes** — grid, list, table, custom
- **2 pagination modes** — pager, loadmore
- **Japanese normalization** — katakana → hiragana, full-width → half-width, uppercase → lowercase
- **Facet counts** — show result counts next to each option
- **Custom field search** — extend keyword search to any meta field
- **URL sync** — filters reflected in browser URL, shareable links, browser back support
- **Mobile-first** — collapsible filter panel on 768px and below
- **AI-friendly** — ships with `llms.txt` and `ai-reference.md`

---

## Japanese Support

HXSE includes built-in Japanese text normalization — something no English-first plugin provides:

```php
['key' => 'keyword', 'type' => 'search', 'label' => 'キーワード',
    'normalize' => true,
]
```

| Input | Normalized |
|---|---|
| `ワードプレス` | `わーどぷれす` (katakana → hiragana) |
| `ＰＨＰ` | `php` (full-width → half-width, lowercase) |
| `WordPress` | `wordpress` (uppercase → lowercase) |

---

## Display Modes

Change the output with one key:

```php
// Card grid (default)
'display' => 'grid',

// Compact horizontal list
'display' => 'list',

// Table with date, title, category
'display' => 'table',

// Your own PHP template
'display'  => 'custom',
'template' => 'hxse-staff.php',
```

---

## AI-Friendly by Design

HXSE ships with AI-facing documentation:

```
hxse-code-first-search/
├── llms.txt          ← API summary for AI agents
├── ai-reference.md   ← Schema key reference with examples
└── HXSE-manual.md    ← Human-readable usage guide
```

Ask your AI assistant to add a filter, change display mode, or scaffold a full staff directory schema — it reads the schema directly from your code.

---

## Quick Reference

### Schema keys

| Key | Type | Description |
|---|---|---|
| `post_type` | string | Target post type (default: `'post'`) |
| `display` | string | `'grid'` / `'list'` / `'table'` / `'custom'` |
| `filters` | array | **Required.** Filter definitions |
| `sort` | array | Sort options |
| `pagination` | array | Pagination settings |
| `url_params` | array | URL sync settings |
| `template` | string | Custom template filename (for `display: 'custom'`) |

### Filter keys

| Key | Type | Description |
|---|---|---|
| `key` | string | **Required.** Unique filter identifier |
| `type` | string | **Required.** `search` / `taxonomy` / `meta` / `date` / `relation` |
| `label` | string | Filter label |
| `ui` | string | `select` / `radio` / `checkbox` / `range` |
| `taxonomy` | string | Taxonomy slug (for `taxonomy` type) |
| `meta_key` | string | Meta key (for `meta` type) |
| `options` | array | Options for select/radio/checkbox: `['value' => 'Label']` |
| `show_count` | bool | Show facet counts next to options |
| `normalize` | bool | Japanese text normalization (for `search` type) |
| `search_fields` | array | Custom fields to include in keyword search |
| `min` / `max` / `step` | float | Range slider settings |

### Pagination keys

| Key | Type | Default | Description |
|---|---|---|---|
| `mode` | string | `'pager'` | `'pager'` / `'loadmore'` |
| `per_page` | int | `12` | Results per page |
| `show_count` | bool | `true` | Show result count |
| `count_format` | string | `'{total}件中 {from}〜{to}件を表示'` | Count format |
| `range` | int | `2` | Pages to show around current page |
| `label_prev` | string | `'前へ'` | Previous button label |
| `label_next` | string | `'次へ'` | Next button label |
| `label_more` | string | `'もっと見る'` | Load more button label |

---

### Full example

```php
add_filter( 'hxse_schemas', function( $schemas ) {

    $schemas['staff_search'] = [
        'post_type' => 'staff',
        'display'   => 'grid',
        'filters'   => [
            ['key' => 'keyword',    'type' => 'search',   'label' => 'キーワード',
                'normalize' => true,
            ],
            ['key' => 'department', 'type' => 'taxonomy',  'label' => '部署',
                'taxonomy'   => 'department',
                'ui'         => 'checkbox',
                'show_count' => true,
            ],
            ['key' => 'level',      'type' => 'meta',     'label' => 'レベル',
                'meta_key' => 'my_level',
                'ui'       => 'radio',
                'options'  => ['junior' => 'ジュニア', 'senior' => 'シニア'],
            ],
        ],
        'sort' => [
            ['key' => 'date_desc', 'label' => '新着順'],
            ['key' => 'title_asc', 'label' => '名前順'],
        ],
        'pagination' => [
            'mode'         => 'pager',
            'per_page'     => 12,
            'show_count'   => true,
            'count_format' => '{total}名中 {from}〜{to}名を表示',
        ],
        'url_params' => ['enable' => true],
        'template'   => 'hxse-staff.php',
    ];

    return $schemas;
} );
```

```
[hxse id="staff_search"]
```

---

## Template Customization

Place a custom template in your theme:

```
your-theme/
└── hxse/
    └── hxse-staff.php
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

## What HXSE Does NOT Support

- Saving search logs to the database (use Google Analytics / Search Console instead)
- Infinite scroll (removed for stability — use `loadmore` instead)
- AI semantic search (available as a recipe)
- Geolocation search (available as a recipe)
- Block editor widget (shortcode only in v1.0)

---

## Related Plugins

**[HXFE — Code-First Forms](https://github.com/okuboyouhei/hxfe-code-first-forms)** — Define contact forms with PHP arrays. Same code-first philosophy. Both plugins share the `hx-htmx` handle so htmx loads only once when used together on the same page.

---

## Requirements

- WordPress 6.0+
- PHP 7.4+
- No build tools required

---

## FAQ

**Q: Does HXSE require FacetWP or any other plugin?**
No. HXSE is fully standalone. htmx is bundled.

**Q: What is htmx and why does HXSE use it?**
[htmx](https://htmx.org/) is a lightweight JS library that adds AJAX behavior via HTML attributes — no build step, no npm, no React. HXSE uses it to update search results without page reloads. It fits naturally with WordPress's server-rendered PHP.

**Q: Can I use HXSE with AI coding tools?**
Yes — this is one of HXSE's strengths. Filters defined as PHP arrays can be read and edited directly by AI tools. HXSE ships with `ai-reference.md` for AI agents to reference.

**Q: Can I use HXSE with custom post types?**
Yes. Set `post_type` to any registered CPT slug.

**Q: Can I use HXSE together with HXFE on the same page?**
Yes. Both plugins share the `hx-htmx` handle — htmx loads only once.

---

## Security

- All input sanitized via `hxse_sanitize_request_params()`
- REST API endpoint is read-only (`post_status = 'publish'` enforced)
- `__return_true` permission callback is intentional — endpoint returns only published post HTML, no private data exposed
- Passed WordPress.org Plugin Check (ERROR: 0)

---

## Installation

1. Install from [WordPress.org](#) *(coming soon)*
2. Activate the plugin
3. Add schema to `functions.php`
4. Place `[hxse id="your-id"]` shortcode on any page

---

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

---

## Author

**Youhei Okubo** — [WordPress.org](https://profiles.wordpress.org/youheiokubo/) · [Zenn](https://zenn.dev/youheiokubo) · [GitHub](https://github.com/okuboyouhei)
