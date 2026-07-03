# HXSE — Code-First Search マニュアル

バージョン: 1.7.0

---

## HXSEとは

PHPの配列でフィルター条件を定義し、ショートコード1行で検索UIを出力するWordPressプラグインです。htmxを使うためページリロードなしで絞り込みが動作します。

**特徴**

- コードファースト — 設定はすべてPHPの配列で管理。Git管理できる。
- DBに設定を保存しない — テーマやプラグインのコードに定義を書くだけ。
- htmx同梱 — 追加のJSライブラリ不要。
- AI-friendly — スキーマ構造がシンプルなのでAIがコードを生成しやすい。

---

## インストール

1. `hxse-code-first-search` フォルダを `/wp-content/plugins/` に配置
2. WordPress管理画面 → プラグイン → 有効化
3. テーマの `functions.php` またはカスタムプラグインにスキーマを定義

---

## 基本的な使い方

### ① スキーマを定義する

```php
// functions.php に追加
add_filter( 'hxse_schemas', function( $schemas ) {

    $schemas['news_search'] = [
        'post_type' => 'post',
        'filters'   => [
            ['key' => 'keyword',  'type' => 'search',  'label' => 'キーワード'],
            ['key' => 'category', 'type' => 'taxonomy', 'label' => 'カテゴリー',
                'taxonomy' => 'category',
                'ui'       => 'checkbox',
            ],
        ],
        'pagination' => [
            'mode'     => 'pager',
            'per_page' => 12,
        ],
    ];

    return $schemas;
} );
```

### ② ショートコードを貼る

```
[hxse id="news_search"]
```

固定ページや投稿の本文に貼るだけで動作します。

---

## フィルタータイプ一覧

### search — キーワード検索

タイトル・本文・抜粋を検索します。

```php
['key' => 'keyword', 'type' => 'search', 'label' => 'キーワード']
```

**`normalize`で日本語の表記ゆれに対応**

```php
['key' => 'keyword', 'type' => 'search', 'label' => 'キーワード',
    'normalize' => true,
]
```

以下を自動で正規化して検索します：

| 入力 | 正規化後 |
|---|---|
| `ワードプレス` | `わーどぷれす` |
| `ＰＨＰ` | `php` |
| `WordPress` | `wordpress` |
| `引越し` / `引っ越し` | 同一視 |
| `コーヒー` / `コーヒー` | 同一視 |

**`search_fields`でカスタムフィールドも検索対象に追加**

```php
['key' => 'keyword', 'type' => 'search', 'label' => 'キーワード',
    'search_fields' => [
        'post_title',    // タイトル
        'post_content',  // 本文
        'post_excerpt',  // 抜粋
        'my_position',   // カスタムフィールドのメタキー
        'my_profile',    // 複数指定可能
    ],
]
```

`search_fields`を省略するとWordPressのデフォルト全文検索（タイトル・本文・抜粋）になります。

### taxonomy — タクソノミー絞り込み

カテゴリー・タグ・カスタムタクソノミーで絞り込みます。

```php
// チェックボックス（複数選択）
['key' => 'category', 'type' => 'taxonomy', 'label' => 'カテゴリー',
    'taxonomy' => 'category',
    'ui'       => 'checkbox',
]

// セレクトボックス
['key' => 'category', 'type' => 'taxonomy', 'label' => 'カテゴリー',
    'taxonomy' => 'category',
    'ui'       => 'select',
]

// ラジオボタン
['key' => 'category', 'type' => 'taxonomy', 'label' => 'カテゴリー',
    'taxonomy' => 'category',
    'ui'       => 'radio',
]
```

`taxonomy` にはタクソノミースラッグを指定します（`category` / `post_tag` / カスタムタクソノミースラッグ）。

### meta — カスタムフィールド絞り込み

`get_post_meta()` で取得できる値で絞り込みます。

```php
// セレクトボックス
['key' => 'level', 'type' => 'meta', 'label' => 'レベル',
    'meta_key' => 'my_level',   // post_metaのキー名を指定
    'ui'       => 'select',
    'options'  => ['junior' => 'ジュニア', 'senior' => 'シニア', 'lead' => 'リード'],
]

// 数値範囲スライダー
['key' => 'price', 'type' => 'meta', 'label' => '価格',
    'meta_key' => 'my_price',   // post_metaのキー名を指定
    'ui'       => 'range',
    'min'      => 0,
    'max'      => 100000,
    'step'     => 1000,
]
```

### date — 投稿年絞り込み

投稿の公開年で絞り込みます。

```php
['key' => 'year', 'type' => 'date', 'label' => '年',
    'ui'         => 'select',
    'start_year' => 2020,   // 省略時は現在年 - 10
]
```

### relation — 関連投稿絞り込み

他のCPTとの関連（post_metaに保存されたpost_id）で絞り込みます。

```php
['key' => 'project', 'type' => 'relation', 'label' => 'プロジェクト',
    'related_post_type' => 'project',
    'meta_key'          => 'my_project_id',
    'ui'                => 'select',
]
```

---

## ソートの設定

```php
'sort' => [
    ['key' => 'date_desc',  'label' => '新着順'],
    ['key' => 'date_asc',   'label' => '古い順'],
    ['key' => 'title_asc',  'label' => '名前順'],
    ['key' => 'title_desc', 'label' => '名前逆順'],
    ['key' => 'menu_order', 'label' => '並び順'],
],
```

最初の項目がデフォルトのソート順になります。

---

## ページネーションの設定

### pager（ページ番号）

```php
'pagination' => [
    'mode'         => 'pager',
    'per_page'     => 12,
    'show_pages'   => true,
    'show_count'   => true,
    'count_format' => '{total}件中 {from}〜{to}件を表示',
    'range'        => 2,          // 現在ページの前後何ページ表示するか
    'label_prev'   => '前へ',
    'label_next'   => '次へ',
    'label_first'  => '最初へ',  // 省略すると非表示
    'label_last'   => '最後へ',  // 省略すると非表示
],
```

`count_format` で使えるプレースホルダー:
- `{total}` — 総件数
- `{from}` — 現在ページの開始番号
- `{to}` — 現在ページの終了番号
- `{current_page}` — 現在のページ番号

### loadmore（もっと見るボタン）

```php
'pagination' => [
    'mode'         => 'loadmore',
    'per_page'     => 12,
    'show_count'   => true,
    'label_more'   => 'もっと見る',
    'loading_text' => '読み込み中...',
],
```


```php
'pagination' => [
    'per_page'     => 12,
    'loading_text' => '読み込み中...',
],
```

---

## URLパラメータの設定

絞り込み条件をURLに反映します。ブラウザバックで条件が戻り、URLを共有できます。

```php
'url_params' => [
    'enable' => true,   // デフォルト: true
    'prefix' => '',     // 同一ページに複数設置する場合は識別用プレフィックスを指定
],
```

`enable: false` にするとURLに検索条件が反映されなくなります。機密性の高い検索や、URLに条件を残したくない場合に使います。

```php
// URLに条件を残さない
'url_params' => ['enable' => false],
```

**同一ページに複数設置する場合**

```php
// 1つ目
'url_params' => ['enable' => true, 'prefix' => 'staff'],

// 2つ目
'url_params' => ['enable' => true, 'prefix' => 'news'],
```

---

## 表示形式の切り替え

`display`キーで結果の表示形式を切り替えられます。

```php
// grid: カードグリッド（デフォルト）
'display' => 'grid',

// list: 横並びコンパクト一覧
'display' => 'list',

// table: テーブル形式
'display' => 'table',

// custom: 独自テンプレートファイルを使う
'display'  => 'custom',
'template' => 'hxse-staff.php',
```

**テーマ側での上書き**

`grid` / `list` / `table`のテンプレートもテーマ側で上書きできます：

```
テーマ/hxse/grid.php       ← gridを上書き
テーマ/hxse/list.php       ← listを上書き
テーマ/hxse/table-row.php  ← tableの行部分を上書き
```

---

**`display_switcher`の挙動について**

- 表示形式を切り替えると常に1ページ目から表示し直されます
- `loadmore`モードと組み合わせた場合、追記されたコンテンツはリセットされます
- これはWebの一般的な慣例です（Amazonや楽天の検索結果も表示切り替え時は1ページ目に戻ります）

---

## テンプレートのカスタマイズ

結果の表示テンプレートをテーマ側に配置してカスタマイズできます。

**探索順序:**
1. `テーマ/hxse/{template}`
2. `テーマ/{template}`
3. プラグイン同梱のデフォルトテンプレート

```php
'template' => 'hxse-staff.php',
```

**テンプレートの例（テーマ/hxse/hxse-staff.php）:**

```php
<?php
// $post がグローバルに利用可能
$position = get_post_meta( $post->ID, 'my_position', true );
$photo_id = get_post_meta( $post->ID, 'my_photo', true );
?>
<article class="staff-card">
    <a href="<?php the_permalink(); ?>">
        <?php if ( $photo_id ) : ?>
            <?php echo wp_get_attachment_image( $photo_id, 'medium' ); ?>
        <?php endif; ?>
        <h2><?php the_title(); ?></h2>
        <?php if ( $position ) : ?>
            <p><?php echo esc_html( $position ); ?></p>
        <?php endif; ?>
    </a>
</article>
```

---

## ラッパーHTMLのカスタマイズ

結果全体・各アイテムのラッパーHTMLを変更できます。

```php
'wrapper' => [
    'container' => '<ul class="my-list">',
    'item'      => '<li class="my-list-item">',
],
```

閉じタグは自動で `</div>` または `</li>` ではなく常に `</div>` が使われます。リスト要素を使う場合はCSSで対応してください。

---

## CSSカスタマイズ

CSS変数を上書きするだけでデザインを変更できます。

```css
/* テーマのCSSに追加 */
.hxse-wrap {
    --hxse-color-primary:      #e11d48;  /* ブランドカラー */
    --hxse-color-primary-dark: #be123c;
    --hxse-color-border:       #f1f5f9;
    --hxse-color-bg-subtle:    #f8f8f8;
    --hxse-radius-md:          2px;      /* 角丸を小さく */
    --hxse-radius-lg:          4px;
}
```

**利用可能なCSS変数:**

| 変数名 | デフォルト | 説明 |
|--------|-----------|------|
| `--hxse-color-primary` | `#2563eb` | メインカラー（ボタン・フォーカス） |
| `--hxse-color-primary-dark` | `#1d4ed8` | ホバー時のメインカラー |
| `--hxse-color-border` | `#e2e8f0` | ボーダー色 |
| `--hxse-color-border-focus` | `#2563eb` | フォーカス時のボーダー色 |
| `--hxse-color-bg` | `#ffffff` | 背景色 |
| `--hxse-color-bg-subtle` | `#f8fafc` | 薄い背景色（フィルターエリア等） |
| `--hxse-color-text` | `#0f172a` | テキスト色 |
| `--hxse-color-text-muted` | `#64748b` | 薄いテキスト色 |
| `--hxse-color-text-label` | `#334155` | ラベル色 |
| `--hxse-radius-sm` | `4px` | 小さい角丸 |
| `--hxse-radius-md` | `6px` | 中くらいの角丸 |
| `--hxse-radius-lg` | `8px` | 大きい角丸 |
| `--hxse-font-size-sm` | `0.8125rem` | 小さいフォントサイズ |
| `--hxse-font-size-base` | `0.9375rem` | 基本フォントサイズ |

---

## 外部APIからデータを取得する（v1.1.0+）

`source: 'api'` を指定すると、WordPressの投稿ではなく外部APIからデータを取得してテンプレートに渡せます。HXFEのwebhook → GASスプレッドシートに蓄積したデータをHXSEで表示する用途に最適です。

```php
// transientキャッシュモード（デフォルト）
$schemas['survey_results'] = [
    'source'   => 'api',
    'endpoint' => 'https://script.google.com/macros/s/xxx/exec',
    'token'    => 'your-secret-token',
    'display'  => 'custom',
    'template' => 'chart',   // your-theme/hxse/chart.php
    'cache'    => 60,        // transientキャッシュ秒数
];

// 静的JSONキャッシュモード（v1.2.0+）
$schemas['survey_results'] = [
    'source'     => 'api',
    'endpoint'   => 'https://script.google.com/macros/s/xxx/exec',
    'token'      => 'your-secret-token',
    'cache_mode' => 'static',        // JSONファイルとして保存
    'cache_file' => 'survey.json',   // ファイル名（省略時はスキーマIDから自動生成）
    'cache'      => 3600,            // 再生成間隔（秒）
    'display'    => 'custom',
    'template'   => 'chart',
];
```

| キー | 説明 |
|---|---|
| `source` | `'api'`（JSON）/ `'rss'`（RSS/Atom）/ `'xml'`（汎用XML） |
| `endpoint` | フェッチするURL |
| `token` | `_token` GETパラメータとして付与（GAS側でトークン検証を推奨） |
| `xpath` | 汎用XMLモード時の繰り返し要素のxpath（例: `'//item'`） |
| `cache_mode` | `'transient'`（デフォルト）/ `'static'`（JSONファイル保存） |
| `cache_file` | 静的キャッシュのファイル名 |
| `cache` | キャッシュ有効秒数 |
| `template` | `your-theme/hxse/{template}.php` を使用 |

**RSSモードの使用例：**

```php
$schemas['zenn_feed'] = [
    'source'   => 'rss',
    'endpoint' => 'https://zenn.dev/youheiokubo/feed',
    'cache'    => 3600,
    'display'  => 'custom',
    'template' => 'feed',   // your-theme/hxse/feed.php
];
```

RSSモードではテンプレートの `$hxse_api_data` に以下のキーを持つ配列が渡されます：
- `title` — 記事タイトル
- `link` — 記事URL
- `description` — 概要
- `pubDate` — 公開日
- `guid` — 記事ID

**汎用XMLモードの使用例：**

```php
$schemas['custom_xml'] = [
    'source'   => 'xml',
    'endpoint' => 'https://example.com/data.xml',
    'xpath'    => '//item',   // 繰り返し要素のxpath
    'cache'    => 3600,
    'display'  => 'custom',
    'template' => 'xml-list',
];
```

テンプレートで使える変数：
- `$hxse_api_data` — APIから取得した配列データ
- `$hxse_schema` — スキーマ定義

静的JSONキャッシュは `wp-content/hxse-cache/` に保存されます。`.htaccess` でWebからのアクセスをブロック済みです。管理画面（設定 → HXSE）からキャッシュの一覧確認・個別削除が可能です。

**管理画面でできること（v1.3.0+）**

| 操作 | 説明 |
|---|---|
| 今すぐ更新 | APIを即時フェッチしてJSONを再生成する（APIスキーマのみ） |
| 削除 | 該当のJSONファイルを削除する |
| 全て削除 | 全キャッシュを一括削除する |
| 孤立ファイルを削除 | スキーマから削除されたか `cache_file` が変更されたファイルを検出・削除する |

スキーマを削除したり `cache_file` を変更した場合、古いJSONが孤立ファイルとして残ります。管理画面に警告が表示されるので、「孤立ファイルを削除」ボタンで一括削除してください。

---

## 外部ソースを検索・絞り込みする（v1.8.0+）

`source: 'api' / 'rss' / 'xml'` のスキーマに `filters`・`sort`・`pagination` を書くと、取得したデータに対して検索・絞り込み・並び替え・ページ送りのUIが使えます。WordPress投稿の検索と同じ操作感で、外部データを扱えます。

```php
add_filter( 'hxse_schemas', function( $schemas ) {
    $schemas['connpass_nagoya'] = [
        'source'     => 'api',
        'endpoint'   => 'https://connpass.com/api/v1/event/?prefecture=aichi&count=100',
        'items_key'  => 'events',        // JSONの中のリストの場所（ドット記法対応）
        'cache_mode' => 'static',
        'cache'      => 3600,
        'filters'    => [
            [ 'key' => 'keyword', 'type' => 'search', 'label' => 'キーワード',
              'search_fields' => [ 'title', 'catch' ] ],
            [ 'key' => 'area', 'type' => 'select', 'label' => 'エリア',
              'field' => 'address', 'options' => 'auto' ],
        ],
        'sort' => [
            [ 'key' => 'date_asc',  'label' => '開催日順', 'field' => 'started_at', 'order' => 'asc',  'compare' => 'date' ],
            [ 'key' => 'date_desc', 'label' => '新しい順', 'field' => 'started_at', 'order' => 'desc', 'compare' => 'date' ],
        ],
        'pagination' => [ 'per_page' => 10, 'show_count' => true, 'show_pages' => true ],
    ];
    return $schemas;
} );
```

### 使えるフィルタータイプ

| type | 説明 |
| --- | --- |
| `search` | `search_fields` で指定したフィールドを横断してキーワード部分一致。省略時はアイテム直下の全文字列フィールドが対象 |
| `select` | `field` の値と完全一致で絞り込み。`ui: 'radio'` も可 |

`select` の選択肢は `'options' => 'auto'` にすると、取得したデータのユニーク値から自動生成されます。手動で指定する場合は `[ [ 'value' => 'a', 'label' => 'A' ], ... ]` の形式です。

### ソート

`field`（対象フィールド、ドット記法対応）・`order`（`asc` / `desc`）・`compare`（`string` / `numeric` / `date`）で並び替えを定義します。sortパラメータ未指定時は先頭の定義が使われます。

### 覚えておくこと

- **絞り込みでAPIは叩かれません。** フィルタ・ソート・ページネーションはすべてキャッシュ済みデータへのメモリ内処理です。相手サーバーへの負荷は増えません。
- **大量データはAPI側で先に絞ってください。** HXSEの絞り込みは取得済みデータへの後処理なので、endpointのクエリパラメータ（connpassの `count` や `prefecture` など）で総量を抑えるのが基本です。
- **ページネーションは `pager` モードのみ。** 外部ソースでは `loadmore`（もっと見るボタン）は使えません。
- **後方互換。** `filters` / `sort` / `pagination` を書かないスキーマは、これまでどおり全件がテンプレートに渡されます。
- **デフォルトテンプレート。** `template` 指定がなくテーマにもテンプレートがない場合、同梱の `templates/api.php` がリスト表示します（title / link / description / pubDate があるデータはそのまま綺麗に出ます）。

## 複数ソースを統合する：マージモード（v1.5.0+）

`sources` キーを使うと、WordPress投稿・RSS・APIなど複数のデータソースを1つの時系列リストに統合できます。自社サイトのお知らせ（WordPress）とZennの技術記事（RSS）を混ぜてトップページに表示する、といった使い方ができます。

```php
$schemas['mixed_news'] = [
    'sources' => [
        [
            'type'      => 'wp_query',
            'post_type' => 'post',
            'label'     => 'お知らせ',
            'limit'     => 20,
        ],
        [
            'type'     => 'rss',
            'endpoint' => 'https://zenn.dev/youheiokubo/feed',
            'label'    => 'Zenn',
        ],
    ],
    'orderby'  => 'date',
    'order'    => 'desc',
    'limit'    => 20,
    'cache'    => 600,
];
```

各ソースは共通フォーマット（title / link / date / excerpt / source / raw）に正規化され、日付順でマージされます。`label` はテンプレートでバッジ表示に使えます。

テンプレートを省略した場合は同梱のデフォルトテンプレート（日付・バッジ・タイトルのリスト）が使われます。テーマで上書きする場合は `your-theme/hxse/merged.php` を作成し、`$hxse_merged_data` をループしてください。

---

## iframe埋め込み（v1.6.0+）

WordPress外のLPや別ドメインのサイトに、新着記事の一覧をiframeで埋め込めます。`embed` キーで有効化します。

```php
$schemas['lp_news'] = [
    'post_type'  => 'post',
    'conditions' => [
        [ 'type' => 'taxonomy', 'taxonomy' => 'category', 'terms' => ['news'] ],
    ],
    'embed' => [
        'enabled'         => true,
        'allowed_origins' => [ 'https://lp.example.com' ],
        'title'           => '新着情報',
        'per_page'        => 5,
    ],
];
```

埋め込みURLは `https://あなたのサイト.com/?hxse_embed=lp_news` です。LP側には次のように記述します。

```html
<iframe src="https://あなたのサイト.com/?hxse_embed=lp_news"
        width="100%" height="600" frameborder="0"></iframe>
```

**セキュリティ：** `allowed_origins` に指定したドメインからのみ埋め込みを許可します（CSPの frame-ancestors で制御）。指定しない場合は同一オリジンのみです。クリックジャッキングを防ぐため、埋め込み先は必ず明示的に指定してください。

### 埋め込み内のフィルターUI（v1.7.0+）

`embed.show_filters` を `true` にすると、埋め込みビューの中に絞り込みUIを表示できます。訪問者がiframe内で検索・絞り込みできるようになります（WordPress投稿ソースのみ。外部API/RSS/XMLソースは非対応）。

```php
'embed' => [
    'enabled'         => true,
    'allowed_origins' => [ 'https://lp.example.com' ],
    'show_filters'    => true,   // 埋め込み内に絞り込みUIを表示
    'per_page'        => 10,
],
```

絞り込みは埋め込みページ内のhtmxで動き、結果エリアだけが差し替わります。

### iframe高さの自動調整（v1.7.0+）

埋め込みページは、自分の高さを親ウィンドウに `postMessage` で通知します（ページ読み込み時・リサイズ時・絞り込みで結果が変わった後）。これを使うと、フィルターで件数が変わってもiframeの高さが自動で追従し、内部スクロールバーや余白が出ません。

**親ページ（LP）側に、次の受信スニペットを貼ってください：**

```html
<iframe id="hxse-frame"
        src="https://あなたのサイト.com/?hxse_embed=lp_news"
        width="100%" height="600" frameborder="0"
        style="border:0;width:100%;"></iframe>

<script>
window.addEventListener('message', function (e) {
    // 送信元を必ず検証（あなたのWordPressサイトのオリジンに変更）
    if (e.origin !== 'https://あなたのサイト.com') return;
    if (e.data && e.data.hxseEmbedHeight) {
        var frame = document.getElementById('hxse-frame');
        if (frame) frame.style.height = e.data.hxseEmbedHeight + 'px';
    }
});
</script>
```

**ポイント：**
- 高さ通知は `embed` が有効なら常に送信されます（`show_filters` のオン・オフに関係なく）。親が受信スニペットを使うかは任意です。
- 高さの送信先オリジンは `allowed_origins` に限定されます。`allowed_origins` を指定していない場合は同一オリジンにのみ送信されるため、別ドメインのLPで高さ調整を使うには `allowed_origins` の指定が必須です。
- 親側の `e.origin` 検証は必ず行ってください（なりすましメッセージを無視するため）。

---

## HXFEとの併用

HXFEと同一ページに設置しても問題ありません。htmxは `hx-htmx` という共通ハンドル名で管理されているため、重複して読み込まれることはありません。

---

## よくある質問

**Q. 複数の絞り込みはAND条件になりますか？**
A. はい。複数のフィルターを設定した場合はすべてAND条件で適用されます。同一フィルター内のcheckbox複数選択はOR条件（いずれかに一致）です。

**Q. カスタムフィールドはACFのフィールドも使えますか？**
A. はい。ACFはpost_metaに値を保存しているため、`type: 'meta'` と `meta_key` に該当のメタキーを指定すれば使えます。

**Q. テンプレートを指定しない場合は？**
A. プラグイン同梱のデフォルトテンプレート（タイトル・日付・抜粋の一覧）が使われます。

**Q. ブロックテーマでも使えますか？**
A. ショートコードはブロックテーマでも使えます。結果の表示テンプレートはPHPファイルで定義するため、フロントエンドの表示はテーマ設計に依存します。

---

## 変更履歴

### v1.7.0
- 埋め込みビュー内にフィルターUIを表示できる `embed.show_filters` を追加（WordPress投稿ソースのみ）。
- iframe高さの自動調整を追加。埋め込みページが `postMessage` で高さを親に通知します（読み込み時・リサイズ時・絞り込み後）。送信先オリジンは `allowed_origins` に限定。
- 親ページ側に受信スニペットを貼ることで、iframeの高さが内容に追従するようになります。

### v1.6.0
- iframe埋め込みビューを追加。`?hxse_embed={スキーマID}` でフィルターUIなしの一覧を自己完結HTMLで出力し、WordPress外のLPなどにiframeで埋め込めるようになりました。
- `embed` キーを追加（`enabled`・`allowed_origins`・`title`・`per_page`）。
- クリックジャッキング対策として、`allowed_origins` で指定したドメインのみ埋め込みを許可します。

### v1.5.0
- マージモード（`sources` キー）を追加。WordPress投稿・RSS・APIなど複数のデータソースを1つの時系列リストに統合できるようになりました。
- `orderby`・`order`・`limit` キーでマージ後のソート・件数制限ができます。
- API/XMLソースは `map` キーで応答のキーを共通フォーマットにマッピングできます。

### v1.4.0
- `source: 'rss'` モードを追加。RSS 2.0・AtomフィードのURLを直接指定してPHP配列に自動変換できるようになりました。
- `source: 'xml'` モードを追加。任意のXMLをxpathで要素を指定してPHP配列に変換できるようになりました。
- `xpath` キーを追加。汎用XMLモード時の繰り返し要素のパスを指定します。

### v1.3.0
- キャッシュマッピング（`hxse_cache_map`）を追加。スキーマID→ファイル名の対応をwp_optionsで管理するようになりました。
- 孤立ファイル検出を追加。スキーマから削除されたか `cache_file` が変更されたJSONファイルを管理画面で警告・一括削除できるようになりました。
- 管理画面に「今すぐ更新」ボタンを追加。APIを即時フェッチしてJSONを再生成できます（APIスキーマのみ）。
- 管理画面に「全て削除」ボタンと合計ファイルサイズ表示を追加しました。
- プラグイン削除時に `hxse_cache_map` オプションも削除するようになりました。

### v1.2.0
- `cache_mode: 'static'` を追加。外部APIのレスポンスをJSONファイルとして `wp-content/hxse-cache/` に保存できるようになりました。`.htaccess` でWebアクセスをブロック済みです。
- `cache_file` キーを追加。静的キャッシュのファイル名を指定できます。
- 管理画面（設定 → HXSE）に静的JSONキャッシュの一覧と個別削除ボタンを追加しました。
- プラグイン削除時（アンインストール）に `wp-content/hxse-cache/` を自動削除するようになりました。

### v1.1.0
- `source: 'api'` モードを追加。WordPressの投稿ではなく外部API（GAS・REST APIなど）からデータを取得してテンプレートに渡せるようになりました。
- `token` キーを追加。`_token` GETパラメータとしてAPIに付与します。
- `cache` キーを追加。transientベースのAPIレスポンスキャッシュが設定できます。

### v1.0.2
- `SECURITY.md`・`MAINTENANCE.md` を追加しました。
- `ai-reference.md` に設計思想・メンテナンス性・フォーク手順のセクションを追加しました。

### v1.0.1
- `columns` キーを追加。グリッド列数を指定できるようになりました。
- `table_columns` キーを追加。テーブル表示のヘッダーをカスタマイズできるようになりました。
- `display_switcher` キーを追加。ユーザーが表示モードを切り替えられるようになりました。
- `tabs` キーを追加。タブUI付きの絞り込みが作れるようになりました。
- `conditions` キーを追加。フィルターUIなしの固定条件を定義できるようになりました。
- アセットをショートコードがあるページのみ読み込むように改善しました。

### v1.0.0
- 初回リリース。
