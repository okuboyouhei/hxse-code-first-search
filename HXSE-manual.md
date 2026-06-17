# HXSE — Code-First Search マニュアル

バージョン: 1.0.0

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
