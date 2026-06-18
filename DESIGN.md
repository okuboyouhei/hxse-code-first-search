# HXSE — Design System & Customization Guide

HXSE uses CSS custom properties (variables) for all visual styling.
Override any variable in your theme CSS to customize the appearance.

---

## CSS Variables (Design Tokens)

All tokens are scoped to `.hxse-wrap`. Override them in your theme:

```css
.hxse-wrap {
    --hxse-color-primary:      #2563eb;  /* Main color — buttons, active tabs, accents */
    --hxse-color-primary-dark: #1d4ed8;  /* Hover state of primary color */
    --hxse-color-border:       #e2e8f0;  /* Input borders, card borders, dividers */
    --hxse-color-border-focus: #2563eb;  /* Input focus border */
    --hxse-color-bg:           #ffffff;  /* Card / input backgrounds */
    --hxse-color-bg-subtle:    #f8fafc;  /* Filter area, table header, hover backgrounds */
    --hxse-color-text:         #0f172a;  /* Primary text */
    --hxse-color-text-muted:   #64748b;  /* Secondary text (date, excerpt, count) */
    --hxse-color-text-label:   #334155;  /* Filter labels */
    --hxse-radius-sm:          4px;      /* Small radius */
    --hxse-radius-md:          8px;      /* Medium radius (inputs, buttons, pager) */
    --hxse-radius-lg:          12px;     /* Large radius (cards, filter box, list) */
    --hxse-font-size-sm:       0.8125rem; /* Small text (labels, dates, count) */
    --hxse-font-size-base:     0.9375rem; /* Base text size */
    --hxse-shadow-sm:          0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --hxse-shadow-md:          0 4px 12px rgba(0,0,0,.08), 0 1px 3px rgba(0,0,0,.04);
    --hxse-columns:            3;        /* Grid column count (override via schema 'columns' key) */
}
```

---

## Customization Examples

### Brand color change

```css
.hxse-wrap {
    --hxse-color-primary:      #0ea5e9;  /* Sky blue */
    --hxse-color-primary-dark: #0284c7;
    --hxse-color-border-focus: #0ea5e9;
}
```

### Softer, rounded style

```css
.hxse-wrap {
    --hxse-radius-md: 12px;
    --hxse-radius-lg: 20px;
    --hxse-color-border: #f1f5f9;
    --hxse-shadow-sm: 0 2px 8px rgba(0,0,0,.08);
    --hxse-shadow-md: 0 8px 24px rgba(0,0,0,.1);
}
```

### Sharp / flat style

```css
.hxse-wrap {
    --hxse-radius-sm: 0;
    --hxse-radius-md: 2px;
    --hxse-radius-lg: 4px;
    --hxse-shadow-sm: none;
    --hxse-shadow-md: none;
}
```

### Dark mode

```css
@media (prefers-color-scheme: dark) {
    .hxse-wrap {
        --hxse-color-bg:           #1e293b;
        --hxse-color-bg-subtle:    #0f172a;
        --hxse-color-border:       #334155;
        --hxse-color-text:         #f1f5f9;
        --hxse-color-text-muted:   #94a3b8;
        --hxse-color-text-label:   #cbd5e1;
        --hxse-color-border-focus: #60a5fa;
    }
}
```

### Grid column count

Via schema (recommended):
```php
$schemas['news'] = [
    'columns' => 2,  // 2-column grid
    ...
];
```

Via CSS (applies globally):
```css
.hxse-wrap {
    --hxse-columns: 2;
}
```

### Per-instance customization

```css
/* Only apply to a specific search instance */
#hxse-wrap-news {
    --hxse-color-primary: #16a34a;
    --hxse-columns: 2;
}
```

---

## Disabling Default Styles

To disable HXSE's default stylesheet entirely:

```php
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'hxse' );
}, 20 );
```

---

## Class Reference

| Class | Description |
|---|---|
| `.hxse-wrap` | Root wrapper — scope for all CSS variables |
| `.hxse-filters` | Filter form wrapper |
| `.hxse-filter` | Individual filter item |
| `.hxse-filter-label` | Filter label |
| `.hxse-input` | Text input (search) |
| `.hxse-select` | Select dropdown |
| `.hxse-submit` | Submit / search button |
| `.hxse-reset` | Reset button |
| `.hxse-results` | Results grid/list container |
| `.hxse-results--grid` | Grid display modifier |
| `.hxse-results--list` | List display modifier |
| `.hxse-results--table` | Table display modifier |
| `.hxse-post` | Individual result item |
| `.hxse-post-title` | Post title |
| `.hxse-post-date` | Post date |
| `.hxse-post-excerpt` | Post excerpt |
| `.hxse-post-thumbnail` | Thumbnail wrapper |
| `.hxse-pager` | Pagination wrapper |
| `.hxse-pager-btn` | Page number button |
| `.hxse-pager-current` | Current page indicator |
| `.hxse-loadmore-btn` | Load more button |
| `.hxse-tabs` | Tab navigation wrapper |
| `.hxse-tab-btn` | Individual tab button |
| `.hxse-tab-btn.is-active` | Active tab |
| `.hxse-display-switcher` | Display mode switcher |
| `.hxse-display-btn` | Display mode button |
| `.hxse-display-btn.is-active` | Active display mode |
| `.hxse-count` | Result count display |
| `.hxse-table` | Table element |
| `.hxse-table-link` | Title link in table |

---

## Result Template Override

To override the default card template, create a file in your theme:

```
your-theme/
└── hxse/
    ├── default.php     ← grid card template
    ├── list.php        ← list item template
    └── table-row.php   ← table row template
```

Or specify a custom template in your schema:

```php
$schemas['news'] = [
    'template' => 'my-news-card',  // loads your-theme/hxse/my-news-card.php
    ...
];
```

---

## Design Philosophy

HXSE's default styles follow a "subtraction design" approach:

- Minimal dependencies — no external CSS frameworks
- Theme-friendly — styles are scoped and don't bleed into the rest of the page
- Token-first — every visual decision is a CSS variable, not a hardcoded value
- Container query based — responsive without relying on viewport width
- AI-readable — this file exists so AI agents can generate accurate customizations

