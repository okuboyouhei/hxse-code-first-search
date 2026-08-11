<?php
/**
 * HXSE — 静的サイト（Distan）向けレイヤー
 *
 * HXSE の通常の検索は「htmx → REST hxse/v1/search → サーバー側 WP_Query」で動く。
 * Distan などの静的サイトジェネレーターで焼き出すと本番に WordPress がいなくなるため、
 * この REST 依存の絞り込みは死ぬ（初回一覧だけが凍結表示され、検索窓は無反応になる）。
 *
 * このファイルは「静的モード」を提供する:
 *   - Distan の生成リクエスト（X-Distan-Render ヘッダ）を検知して自動起動
 *   - htmx / REST を一切使わず、全件を焼き込む
 *   - 各アイテムに data-* 属性（検索用テキスト / タクソノミー / メタ値）を付与
 *   - 同梱の軽量な素の JS が「描画済み要素を表示・非表示」する（クライアント内絞り込み）
 *
 * 対象は wp_query ソースの有界（件数が限られた）リスト。件数が上限
 * （hxse_static_max_items、既定 500）を超える場合はクライアント絞り込みを諦め、
 * 通常のページ送り一覧を焼くだけの安全側に縮退する（HTMLコメントで警告）。
 *
 * 外部ソース（api / rss / xml / sources）は生成時点のスナップショットとして
 * フィルターUIなしの一覧を焼く（対話機能は落とす）。
 *
 * @package HXSE
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * いま Distan（静的サイトジェネレーター）に焼かれている最中か判定する。
 *
 * Distan は生成中、ループバックの各リクエストに X-Distan-Render ヘッダを載せる。
 * ここではヘッダの存在だけを見る（シークレットの検証はしない）。静的モードは
 * read-only で機能が減る方向にしか働かないため、ヘッダを詐称されても実害はない。
 *
 * @return bool
 */
function hxse_is_distan_render() {
	if ( ! empty( $_SERVER['HTTP_X_DISTAN_RENDER'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- presence check only, value never used
		return true;
	}
	return false;
}

/**
 * このスキーマ／このリクエストで静的モードを使うべきか判定する。
 *
 * スキーマに 'static' が明示されていればそれを優先（true / false）。
 * 未指定なら Distan 生成中のときだけ自動で有効化する。
 * hxse_static_active フィルターで最終上書きも可能（プレビュー確認などに便利）。
 *
 * @param array  $schema
 * @param string $hxse_id
 * @return bool
 */
function hxse_static_active( $schema, $hxse_id = '' ) {
	if ( isset( $schema['static'] ) ) {
		$active = (bool) $schema['static'];
	} else {
		$active = hxse_is_distan_render();
	}

	/**
	 * 静的モードの有効・無効を最終決定するフィルター。
	 *
	 * @param bool   $active
	 * @param array  $schema
	 * @param string $hxse_id
	 */
	return (bool) apply_filters( 'hxse_static_active', $active, $schema, $hxse_id );
}

/**
 * クライアント内絞り込みが破綻しない最大アイテム数。
 * これを超えると静的モードでも通常のページ送り一覧に縮退する。
 *
 * @return int
 */
function hxse_static_max_items() {
	return (int) apply_filters( 'hxse_static_max_items', 500 );
}

/**
 * 静的モードのショートコード出力を組み立てる。
 *
 * @param array  $schema         正規化済みスキーマ
 * @param string $hxse_id
 * @param array  $current_params サニタイズ済みGETパラメータ
 * @return string HTML
 */
function hxse_render_static( $schema, $hxse_id, $current_params = array() ) {
	$source = isset( $schema['source'] ) ? sanitize_key( $schema['source'] ) : 'wp_query';
	$prefix = isset( $schema['url_params']['prefix'] ) ? sanitize_key( $schema['url_params']['prefix'] ) : '';

	$columns = isset( $schema['columns'] ) ? absint( $schema['columns'] ) : 0;
	$style   = $columns ? ' style="--hxse-columns:' . $columns . '"' : '';

	ob_start();
	echo '<div class="hxse-wrap hxse-static" id="hxse-wrap-' . esc_attr( $hxse_id ) . '"'
		. $style // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled inline style with absint value
		. ' data-hxse-id="' . esc_attr( $hxse_id ) . '"'
		. ' data-hxse-static="1"'
		. ' data-prefix="' . esc_attr( $prefix ) . '">';

	// --- 外部ソース（api / rss / xml / sources）: スナップショットを焼くだけ ---
	if ( ! empty( $schema['sources'] ) && is_array( $schema['sources'] ) ) {
		echo '<div id="hxse-results-' . esc_attr( $hxse_id ) . '" class="hxse-results-wrap">';
		$merged = hxse_fetch_merged_data( $schema );
		hxse_render_merged_results( $schema, $hxse_id, $merged );
		echo '</div>';
		echo '<!-- HXSE static: external merged source baked as snapshot; interactive filtering is not available on a static site. -->';
		echo '</div>';
		return ob_get_clean();
	}

	if ( in_array( $source, array( 'api', 'rss', 'xml' ), true ) ) {
		echo '<div id="hxse-results-' . esc_attr( $hxse_id ) . '" class="hxse-results-wrap">';
		$api_data = hxse_fetch_api_data( $schema );
		hxse_render_api_results( $schema, $hxse_id, $api_data );
		echo '</div>';
		echo '<!-- HXSE static: external ' . esc_html( $source ) . ' source baked as snapshot; interactive filtering is not available on a static site. -->';
		echo '</div>';
		return ob_get_clean();
	}

	// --- wp_query ソース: 全件を焼いてクライアント内絞り込み（A1） ---

	// 全件取得（ページ送りなし）。上限を超えたら縮退。
	$count_args                   = hxse_build_query_args( $schema, array(), 1 );
	$count_args['posts_per_page'] = -1;
	$count_args['fields']         = 'ids';
	$count_args['no_found_rows']  = true;
	$id_query                     = new WP_Query( $count_args );
	$total                        = is_array( $id_query->posts ) ? count( $id_query->posts ) : 0;
	wp_reset_postdata();

	if ( $total > hxse_static_max_items() ) {
		// 縮退: 通常のページ送り一覧を焼くだけ（クライアント絞り込みなし）。
		echo '<!-- HXSE static: ' . (int) $total . ' items exceed hxse_static_max_items (' . (int) hxse_static_max_items()
			. '). Client-side filtering disabled; falling back to a plain paged list. '
			. 'Narrow the dataset with schema conditions, or raise the cap via the hxse_static_max_items filter. -->';
		$query_args = hxse_build_query_args( $schema, $current_params, 1 );
		$query      = new WP_Query( $query_args );
		echo '<div id="hxse-results-' . esc_attr( $hxse_id ) . '" class="hxse-results-wrap">';
		hxse_render_results( $schema, $hxse_id, $query, 1 );
		echo '</div>';
		wp_reset_postdata();
		echo '</div>';
		return ob_get_clean();
	}

	// 絞り込みフォーム（htmxなし・静的）
	$has_static_filters = hxse_render_static_filters( $schema, $hxse_id );

	// 件数表示（JSが更新する）
	echo '<p class="hxse-count hxse-static-count" data-hxse-total="' . (int) $total . '">'
		. esc_html( sprintf( /* translators: %d: total item count */ __( '%d件', 'hxse-code-first-search' ), $total ) )
		. '</p>';

	// 全件描画
	$all_args               = hxse_build_query_args( $schema, array(), 1 );
	$all_args['posts_per_page'] = -1;
	$all_args['no_found_rows'] = true;
	$query                  = new WP_Query( $all_args );

	echo '<div id="hxse-results-' . esc_attr( $hxse_id ) . '" class="hxse-results-wrap">';
	hxse_render_static_items( $schema, $hxse_id, $query );
	echo '<p class="hxse-no-results hxse-static-empty" hidden>'
		. esc_html__( '該当する結果が見つかりませんでした。', 'hxse-code-first-search' ) . '</p>';
	echo '</div>';
	wp_reset_postdata();

	echo '</div>'; // .hxse-wrap

	// 共有スクリプト（1ページに1回だけインライン）
	hxse_render_static_script_once();

	return ob_get_clean();
}

/**
 * 静的モードの絞り込みフォームを描画する（htmxなし）。
 * 既存のコントロール描画関数を再利用するので見た目は通常と同じ。
 * 静的モードで意味のあるフィルター（search / taxonomy / meta[select系]）だけ出す。
 *
 * @param array  $schema
 * @param string $hxse_id
 * @return bool 出力したフィルターが1つでもあれば true
 */
function hxse_render_static_filters( $schema, $hxse_id ) {
	if ( empty( $schema['filters'] ) || ! is_array( $schema['filters'] ) ) {
		return false;
	}

	$prefix   = isset( $schema['url_params']['prefix'] ) ? sanitize_key( $schema['url_params']['prefix'] ) : '';
	$rendered = 0;

	ob_start();
	echo '<div class="hxse-filters hxse-filters--static" id="hxse-form-' . esc_attr( $hxse_id ) . '" role="search">';

	// モバイル（コンテナ幅768px以下）用の折りたたみトグル。
	// 通常モードと同じ仕組みで、狭いコンテナでは本体が畳まれ、このボタンで開く。
	// テーマの本文カラムは 768px 以下になりがちなので、これが無いと常時畳まれてしまう。
	echo '<button type="button" class="hxse-filter-toggle" aria-expanded="false" aria-controls="hxse-filter-body-' . esc_attr( $hxse_id ) . '">';
	echo '<span class="hxse-filter-toggle-label">' . esc_html__( '絞り込み', 'hxse-code-first-search' ) . '</span>';
	echo '<span class="hxse-filter-toggle-icon" aria-hidden="true">▼</span>';
	echo '</button>';

	echo '<div class="hxse-filter-body" id="hxse-filter-body-' . esc_attr( $hxse_id ) . '">';
	echo '<div class="hxse-filters-row">';

	foreach ( $schema['filters'] as $filter ) {
		if ( empty( $filter['key'] ) || empty( $filter['type'] ) ) {
			continue;
		}

		$key        = sanitize_key( $filter['key'] );
		$type       = sanitize_key( $filter['type'] );
		$ui         = isset( $filter['ui'] ) ? sanitize_key( $filter['ui'] ) : 'select';
		$label      = isset( $filter['label'] ) ? sanitize_text_field( $filter['label'] ) : $key;
		$input_name = $prefix ? $prefix . '_' . $key : $key;

		// 静的モードで扱えるフィルターのみ。range / date / relation / sort は
		// クライアントだけでは正確に再現しにくいので、混乱を避けて出さない。
		$role = '';
		if ( 'search' === $type ) {
			$role = 'search';
		} elseif ( 'taxonomy' === $type ) {
			$role = 'facet';
		} elseif ( 'meta' === $type && 'range' !== $ui ) {
			$role = 'facet';
		} else {
			continue; // 未対応タイプはスキップ
		}

		echo '<div class="hxse-filter hxse-filter-' . esc_attr( $type ) . ' hxse-ui-' . esc_attr( $ui ) . '"'
			. ' data-hxse-role="' . esc_attr( $role ) . '"'
			. ' data-hxse-key="' . esc_attr( $key ) . '">';
		echo '<label class="hxse-filter-label">' . esc_html( $label ) . '</label>';

		switch ( $type ) {
			case 'search':
				hxse_render_search_filter( $input_name, '' );
				break;
			case 'taxonomy':
				hxse_render_taxonomy_filter( $filter, $input_name, '', $ui );
				break;
			case 'meta':
				hxse_render_meta_filter( $filter, $input_name, '', $ui, array(), $prefix );
				break;
		}

		echo '</div>';
		$rendered++;
	}

	echo '</div>'; // .hxse-filters-row
	echo '</div>'; // .hxse-filter-body

	// アクション行は本体の外（兄弟）に置く。狭いコンテナでは
	// `.hxse-filter-body.is-open ~ .hxse-filters-actions` で開いたときだけ表示される。
	echo '<div class="hxse-filters-actions">';
	echo '<button type="button" class="hxse-reset hxse-static-reset">' . esc_html__( 'リセット', 'hxse-code-first-search' ) . '</button>';
	echo '</div>';

	echo '</div>'; // .hxse-filters
	$html = ob_get_clean();

	if ( $rendered > 0 ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- composed from escaped parts and reused render functions
		return true;
	}
	return false;
}

/**
 * 全件をアイテムラッパー（data-*付き）で描画する。
 * ラッパー以外の中身は通常のテンプレートを使うので見た目は変わらない。
 *
 * @param array    $schema
 * @param string   $hxse_id
 * @param WP_Query $query
 */
function hxse_render_static_items( $schema, $hxse_id, $query ) {
	$display = isset( $schema['display'] ) ? sanitize_key( $schema['display'] ) : 'grid';

	// display に応じたコンテナ・テンプレート（table は行構造のため個別対応が要るので
	// 静的モードでは grid/list/custom を主対象とし、table は grid として扱う）。
	switch ( $display ) {
		case 'list':
			$container_class = 'hxse-results hxse-results--list';
			$template        = 'list';
			break;
		case 'custom':
			$container_class = 'hxse-results';
			$template        = 'custom';
			break;
		default:
			$container_class = 'hxse-results hxse-results--grid';
			$template        = 'grid';
			break;
	}

	if ( ! $query->have_posts() ) {
		echo '<p class="hxse-no-results">' . esc_html__( '該当する結果が見つかりませんでした。', 'hxse-code-first-search' ) . '</p>';
		return;
	}

	echo '<div class="' . esc_attr( $container_class ) . '" id="hxse-grid-' . esc_attr( $hxse_id ) . '">';
	while ( $query->have_posts() ) {
		$query->the_post();
		echo '<div class="hxse-item"' . hxse_static_item_attrs( $schema ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs escaped in builder
		hxse_load_template( $schema, $hxse_id, $template );
		echo '</div>';
	}
	echo '</div>';
}

/**
 * 現在の投稿（グローバル $post）から、クライアント絞り込み用の data-* 属性を作る。
 *
 * - data-hxse-hay : 検索用のテキスト（タイトル＋抜粋、search_fieldsのメタも含む）
 * - data-hxse-f-{key} : タクソノミーは term_id、メタはメタ値をスペース区切りで
 *
 * 値はコントロールの option value（タクソノミー=term_id、メタ=メタ値）と一致するよう
 * 揃える。突き合わせは JS 側で行う。
 *
 * @param array $schema
 * @return string 先頭スペース付きの属性文字列
 */
function hxse_static_item_attrs( $schema ) {
	$post_id = get_the_ID();
	$attrs   = '';

	// 検索用テキストの土台: タイトル＋抜粋
	$haystack_parts = array( get_the_title(), get_the_excerpt() );

	if ( ! empty( $schema['filters'] ) && is_array( $schema['filters'] ) ) {
		foreach ( $schema['filters'] as $filter ) {
			if ( empty( $filter['key'] ) || empty( $filter['type'] ) ) {
				continue;
			}
			$key  = sanitize_key( $filter['key'] );
			$type = sanitize_key( $filter['type'] );
			$ui   = isset( $filter['ui'] ) ? sanitize_key( $filter['ui'] ) : 'select';

			if ( 'search' === $type ) {
				// search_fields にメタが含まれるなら、そのメタ値も検索対象に足す
				$search_fields = isset( $filter['search_fields'] ) && is_array( $filter['search_fields'] )
					? $filter['search_fields'] : array();
				foreach ( $search_fields as $field ) {
					$field = sanitize_key( $field );
					if ( in_array( $field, array( 'post_title', 'post_content', 'post_excerpt' ), true ) ) {
						if ( 'post_content' === $field ) {
							$haystack_parts[] = wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) );
						}
						continue;
					}
					$meta_vals = get_post_meta( $post_id, $field, false );
					if ( ! empty( $meta_vals ) ) {
						$haystack_parts[] = implode( ' ', array_map( 'strval', $meta_vals ) );
					}
				}
			} elseif ( 'taxonomy' === $type ) {
				$taxonomy = isset( $filter['taxonomy'] ) ? sanitize_key( $filter['taxonomy'] ) : '';
				if ( ! $taxonomy ) {
					continue;
				}
				$terms = get_the_terms( $post_id, $taxonomy );
				$ids   = array();
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$ids[] = (int) $term->term_id;
					}
				}
				$attrs .= ' data-hxse-f-' . esc_attr( $key ) . '="' . esc_attr( implode( ' ', $ids ) ) . '"';
			} elseif ( 'meta' === $type && 'range' !== $ui ) {
				$meta_key = isset( $filter['meta_key'] ) ? sanitize_key( $filter['meta_key'] ) : '';
				if ( ! $meta_key ) {
					continue;
				}
				$meta_vals = get_post_meta( $post_id, $meta_key, false );
				$tokens    = array();
				foreach ( (array) $meta_vals as $mv ) {
					if ( is_scalar( $mv ) ) {
						$tokens[] = (string) $mv;
					}
				}
				$attrs .= ' data-hxse-f-' . esc_attr( $key ) . '="' . esc_attr( implode( "\n", $tokens ) ) . '"';
			}
		}
	}

	$haystack = wp_strip_all_tags( implode( ' ', array_filter( $haystack_parts ) ) );
	$attrs    = ' data-hxse-hay="' . esc_attr( $haystack ) . '"' . $attrs;

	return $attrs;
}

/**
 * 静的モードのクライアント絞り込みスクリプトを1回だけインライン出力する。
 * 別ファイルの enqueue にしないのは、Distan がコピー・書き換えする資産を増やさず、
 * file:// のダブルクリック運用でも確実に動くようにするため。
 */
function hxse_render_static_script_once() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
<script>
(function(){
	'use strict';
	function norm(s){
		if(!s) return '';
		if(String.prototype.normalize){ s = s.normalize('NFKC'); }
		s = s.toLowerCase();
		// カタカナ → ひらがな
		s = s.replace(/[\u30a1-\u30f6]/g, function(c){ return String.fromCharCode(c.charCodeAt(0) - 0x60); });
		// 各種ダッシュ・ハイフンを長音符に統一
		s = s.replace(/[\u30fc\u2014\u2010\u2212\uff0d\-]/g, '\u30fc');
		s = s.replace(/\s+/g, ' ').trim();
		return s;
	}
	function tokens(v){
		if(!v) return [];
		return v.split(/[\s\n]+/).filter(Boolean);
	}
	function collect(form){
		var searches = [];
		var facets = {}; // key -> [selected values]
		form.querySelectorAll('.hxse-filter[data-hxse-role]').forEach(function(group){
			var role = group.getAttribute('data-hxse-role');
			var key  = group.getAttribute('data-hxse-key');
			if(role === 'search'){
				group.querySelectorAll('input[type=search], input[type=text]').forEach(function(inp){
					var q = norm(inp.value);
					if(q) searches.push(q);
				});
			} else if(role === 'facet'){
				var vals = [];
				group.querySelectorAll('select').forEach(function(sel){
					if(sel.multiple){
						Array.prototype.forEach.call(sel.selectedOptions, function(o){ if(o.value) vals.push(o.value); });
					} else if(sel.value){ vals.push(sel.value); }
				});
				group.querySelectorAll('input[type=checkbox]:checked, input[type=radio]:checked').forEach(function(inp){
					if(inp.value) vals.push(inp.value);
				});
				if(vals.length) facets[key] = vals;
			}
		});
		return { searches: searches, facets: facets };
	}
	function apply(wrap){
		var form = wrap.querySelector('.hxse-filters--static');
		if(!form) return;
		var c = collect(form);
		var items = wrap.querySelectorAll('.hxse-item');
		var shown = 0;
		items.forEach(function(item){
			var ok = true;
			// テキスト検索（各語が haystack に部分一致・AND）
			if(ok && c.searches.length){
				var hay = norm(item.getAttribute('data-hxse-hay') || '');
				for(var i=0;i<c.searches.length;i++){
					if(hay.indexOf(c.searches[i]) === -1){ ok = false; break; }
				}
			}
			// ファセット（キーごとに OR、キー同士は AND）
			if(ok){
				for(var key in c.facets){
					if(!Object.prototype.hasOwnProperty.call(c.facets, key)) continue;
					var have = tokens(item.getAttribute('data-hxse-f-' + key) || '');
					var want = c.facets[key];
					var hit = false;
					for(var j=0;j<want.length;j++){ if(have.indexOf(want[j]) !== -1){ hit = true; break; } }
					if(!hit){ ok = false; break; }
				}
			}
			item.hidden = !ok;
			if(ok) shown++;
		});
		// 件数・no-results 更新
		var countEl = wrap.querySelector('.hxse-static-count');
		if(countEl){
			var total = countEl.getAttribute('data-hxse-total') || String(items.length);
			var active = c.searches.length || Object.keys(c.facets).length;
			countEl.textContent = active ? (shown + ' / ' + total + '\u4ef6') : (total + '\u4ef6');
		}
		var empty = wrap.querySelector('.hxse-static-empty');
		if(empty){ empty.hidden = shown !== 0; }
	}
	function bind(wrap){
		var form = wrap.querySelector('.hxse-filters--static');
		if(!form) return;
		form.addEventListener('input', function(){ apply(wrap); });
		form.addEventListener('change', function(){ apply(wrap); });
		var reset = form.querySelector('.hxse-static-reset');
		if(reset){
			reset.addEventListener('click', function(){
				form.querySelectorAll('input, select').forEach(function(el){
					if(el.type === 'checkbox' || el.type === 'radio'){ el.checked = false; }
					else if(el.tagName === 'SELECT'){ el.selectedIndex = 0; }
					else { el.value = ''; }
				});
				apply(wrap);
			});
		}
	}
	// モバイル折りたたみトグル。実際の静的出力では htmx/hxse.js が読み込まれないので、
	// その環境でだけ登録する（ローカルの 'static'=>true 検証時は hxse.js 側が処理するため二重発火を避ける）。
	function bindToggle(){
		if ( typeof window.htmx !== 'undefined' ) return; // hxse.js が有効 → そちらに任せる
		document.addEventListener('click', function(e){
			var btn = e.target.closest('.hxse-filter-toggle');
			if(!btn) return;
			var body = document.getElementById(btn.getAttribute('aria-controls'));
			if(!body) return;
			var open = btn.getAttribute('aria-expanded') === 'true';
			btn.setAttribute('aria-expanded', open ? 'false' : 'true');
			body.classList.toggle('is-open', !open);
		});
	}
	function init(){
		document.querySelectorAll('.hxse-wrap.hxse-static').forEach(bind);
		bindToggle();
	}
	if(document.readyState === 'loading'){
		document.addEventListener('DOMContentLoaded', init);
	} else { init(); }
})();
</script>
	<?php
}
