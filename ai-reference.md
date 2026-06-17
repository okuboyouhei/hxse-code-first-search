# HXSE — ai-reference.md

AI agents: Read this file before generating HXSE schemas.

---

## Basic Structure

```php
add_filter( 'hxse_schemas', function( $schemas ) {
    $schemas['schema_key'] = [
        'post_type'  => 'post',       // 対象投稿タイプ（必須）
        'filters'    => [ ... ],       // フィルター定義（必須）
        'sort'       => [ ... ],       // ソート定義（省略可）
        'pagination' => [ ... ],       // ページネーション設定（省略可）
        'url_params' => [ ... ],       // URLパラメータ設定（省略可）
        'template'   => 'file.php',   // テンプレートファイル名（省略可）
        'wrapper'    => [ ... ],       // ラッパーHTML（省略可）
    ];
    return $schemas;
} );
```

**ショートコード**

```
[hxse id="schema_key"]
```

---

## filters Keys (Common)

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `key` | string | ✅ | フィルターキー（英数字・アンダースコア） |
| `type` | string | ✅ | フィルタータイプ（下記参照） |
| `label` | string | — | フォームの表示ラベル |
| `ui` | string | — | UIタイプ: `select` / `radio` / `checkbox` / `range`（デフォルト: `select`） |

---

## Filter Types

### search
```php
['key' => 'keyword', 'type' => 'search', 'label' => 'キーワード']
```
UIは常に`text`入力。`ui`指定不要。

### taxonomy
```php
['key' => 'department', 'type' => 'taxonomy', 'label' => '部署',
 'taxonomy' => 'department',   // タクソノミースラッグ（必須）
 'ui'       => 'checkbox',     // 'select' / 'radio' / 'checkbox'
]
```

### meta
```php
// select / radio / checkbox
['key' => 'level', 'type' => 'meta', 'label' => 'レベル',
 'meta_key' => 'my_level',
 'ui'       => 'radio',
 'options'  => ['junior' => 'ジュニア', 'senior' => 'シニア'],
]

// range（数値範囲スライダー）
['key' => 'price', 'type' => 'meta', 'label' => '価格',
 'meta_key' => 'my_price',
 'ui'       => 'range',
 'min'      => 0,
 'max'      => 100000,
 'step'     => 1000,
]
```
rangeの場合、GETパラメータは `{key}_min` / `{key}_max` になる。

### date
```php
['key' => 'year', 'type' => 'date', 'label' => '年',
 'ui'         => 'select',   // 'select' / 'text'
 'start_year' => 2020,       // 省略時: 現在年 - 10
]
```
投稿の公開年で絞り込む。

### relation
```php
['key' => 'project', 'type' => 'relation', 'label' => 'プロジェクト',
 'related_post_type' => 'project',   // 参照するCPTスラッグ（必須）
 'meta_key'          => 'my_project_id',  // 保存先のmeta_key（必須）
 'ui'                => 'select',    // 'select' / 'checkbox'
]
```

---

## sort Keys

```php
'sort' => [
    ['key' => 'date_desc',  'label' => '新着順'],
    ['key' => 'date_asc',   'label' => '古い順'],
    ['key' => 'title_asc',  'label' => '名前順'],
    ['key' => 'title_desc', 'label' => '名前逆順'],
    ['key' => 'menu_order', 'label' => '並び順'],
],
```

---

## pagination Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `per_page` | int | `12` | 1ページあたりの件数 |
| `show_pages` | bool | `true` | ページ番号を表示するか（pagerのみ） |
| `show_count` | bool | `true` | 件数を表示するか |
| `count_format` | string | `'{total}件中 {from}〜{to}件を表示'` | 件数フォーマット |
| `range` | int | `2` | 現在ページ前後のページ数（pagerのみ） |
| `label_prev` | string | `'前へ'` | 前へボタンラベル |
| `label_next` | string | `'次へ'` | 次へボタンラベル |
| `label_first` | string | `'最初へ'` | 最初へボタンラベル（省略で非表示） |
| `label_last` | string | `'最後へ'` | 最後へボタンラベル（省略で非表示） |
| `label_more` | string | `'もっと見る'` | もっと見るボタンラベル（loadmoreのみ） |
| `loading_text` | string | `'読み込み中...'` | ローディングテキスト |

**count_formatのプレースホルダー**

| プレースホルダー | 内容 |
|---|---|
| `{total}` | 総件数 |
| `{from}` | 現在ページの開始番号 |
| `{to}` | 現在ページの終了番号 |
| `{current_page}` | 現在のページ番号 |

---

## url_params Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enable` | bool | `true` | URLパラメータを使うか |
| `prefix` | string | `''` | パラメータのプレフィックス（複数設置時の衝突回避） |

同一ページに複数設置する場合：
```php
// 1つ目
'url_params' => ['enable' => true, 'prefix' => 'staff'],
// 2つ目
'url_params' => ['enable' => true, 'prefix' => 'news'],
```

---

## template

テンプレートの探索順序：

1. `テーマ/hxse/{template}`
2. `テーマ/{template}`
3. `プラグイン/templates/default.php`（デフォルト）

テンプレート内では `$post` がグローバルに利用可能：

```php
// テーマ/hxse/hxse-staff.php
$position = get_post_meta( $post->ID, 'my_position', true );
?>
<article>
    <h2><?php the_title(); ?></h2>
    <p><?php echo esc_html( $position ); ?></p>
</article>
```

---

## wrapper Keys

```php
'wrapper' => [
    'container' => '<div class="my-results">',   // 結果全体のラッパー開始タグ
    'item'      => '<div class="my-item">',       // 各アイテムのラッパー開始タグ
],
```
閉じタグは自動で`</div>`が使われる。

---

## Complete Example

```php
add_filter( 'hxse_schemas', function( $schemas ) {
    $schemas['staff_search'] = [
        'post_type' => 'staff',
        'filters'   => [
            ['key' => 'keyword',    'type' => 'search',   'label' => 'キーワード'],
            ['key' => 'department', 'type' => 'taxonomy',  'label' => '部署',
                'taxonomy' => 'department',
                'ui'       => 'checkbox',
            ],
            ['key' => 'level',      'type' => 'meta',     'label' => 'レベル',
                'meta_key' => 'my_level',
                'ui'       => 'radio',
                'options'  => ['junior' => 'ジュニア', 'senior' => 'シニア', 'lead' => 'リード'],
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
        'url_params' => ['enable' => true, 'prefix' => ''],
        'template'   => 'hxse-staff.php',
    ];
    return $schemas;
} );
```

---

## Important Notes

- フィルターはAND条件で適用される
- `taxonomy`フィルターの複数選択（checkbox）はOR条件（IN）
- `meta`フィルターの複数選択（checkbox）はOR条件（IN）
- `range`フィルターのGETパラメータは `{key}_min` / `{key}_max`
- URLパラメータの`action`・`hxse_id`・`hxse_nonce`は内部パラメータ（URLに反映されない）
- フィルター名: `hxse_schemas`
