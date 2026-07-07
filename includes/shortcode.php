<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'hxse', 'hxse_shortcode' );

/**
 * [hxse id="schema_id"] ショートコード
 *
 * @param array $atts
 * @return string
 */
function hxse_shortcode( $atts ) {
	$atts = shortcode_atts(
		array( 'id' => '' ),
		$atts,
		'hxse'
	);

	$hxse_id = sanitize_key( $atts['id'] );
	if ( ! $hxse_id ) {
		return '<p class="hxse-error">' . esc_html__( '[hxse] id属性が指定されていません。', 'hxse-code-first-search' ) . '</p>';
	}

	$schema = hxse_get_schema( $hxse_id );
	if ( ! $schema ) {
		return '<p class="hxse-error">' . esc_html__( 'スキーマが見つかりません: ', 'hxse-code-first-search' ) . esc_html( $hxse_id ) . '</p>';
	}

	$schema         = hxse_normalize_schema( $schema );

	// 同一ページへの重複設置チェック
	static $rendered = array();
	if ( in_array( $hxse_id, $rendered, true ) ) {
		return '<p class="hxse-error">'
			. esc_html__( '同じIDのショートコードが既に配置されています: ', 'hxse-code-first-search' )
			. esc_html( $hxse_id )
			. '</p>';
	}
	$rendered[] = $hxse_id;
	$current_params = hxse_sanitize_get_params();
	$prefix         = isset( $schema['url_params']['prefix'] ) ? sanitize_key( $schema['url_params']['prefix'] ) : '';
	$page           = isset( $current_params['page'] ) ? max( 1, absint( $current_params['page'] ) ) : 1;

	// URLのdisplayパラメータをスキーマに反映（display_switcher使用時）
	if ( ! empty( $schema['display_switcher'] ) && isset( $current_params['display'] ) ) {
		$allowed_modes = array( 'grid', 'list', 'table', 'custom' );
		$url_display   = sanitize_key( $current_params['display'] );
		if ( in_array( $url_display, $allowed_modes, true ) ) {
			$schema['display'] = $url_display;
		}
	}

	$source = isset( $schema['source'] ) ? sanitize_key( $schema['source'] ) : 'wp_query';

	// --- マージモード（複数ソース） ---
	if ( ! empty( $schema['sources'] ) && is_array( $schema['sources'] ) ) {
		ob_start();
		echo '<div class="hxse-wrap" id="hxse-wrap-' . esc_attr( $hxse_id ) . '"'
			. ' data-hxse-id="' . esc_attr( $hxse_id ) . '"'
			. ' data-prefix="' . esc_attr( $prefix ) . '">';

		$merged_data = hxse_fetch_merged_data( $schema );

		if ( hxse_api_is_interactive( $schema ) ) {
			// v1.9.0+: マージモードでもフィルタ・ソート・ページネーション対応
			// マージ済みデータは title / link / date / excerpt / source に正規化済み
			$schema['_api_items'] = $merged_data; // 'options' => 'auto' の選択肢生成用（絞り込み前）

			$endpoint = rest_url( 'hxse/v1/search' );
			hxse_render_filters( $schema, $hxse_id, $current_params, $endpoint );

			$merged_data = hxse_filter_api_items( $merged_data, $schema, $current_params );
			$merged_data = hxse_sort_api_items( $merged_data, $schema, $current_params );

			echo '<div id="hxse-results-' . esc_attr( $hxse_id ) . '" class="hxse-results-wrap">';
			hxse_render_api_page( $schema, $hxse_id, $merged_data, $page );
			echo '</div>';
		} else {
			// 従来どおり: 取得→テンプレートに全件渡す
			echo '<div id="hxse-results-' . esc_attr( $hxse_id ) . '" class="hxse-results-wrap">';
			hxse_render_merged_results( $schema, $hxse_id, $merged_data );
			echo '</div>';
		}

		echo '</div>';
		return ob_get_clean();
	}

	// --- API/RSS/XMLモード ---
	if ( in_array( $source, array( 'api', 'rss', 'xml' ), true ) ) {
		ob_start();
		$columns = isset( $schema['columns'] ) ? absint( $schema['columns'] ) : 0;
		$style   = $columns ? ' style="--hxse-columns:' . $columns . '"' : '';

		echo '<div class="hxse-wrap" id="hxse-wrap-' . esc_attr( $hxse_id ) . '"'
			. $style // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled inline style with absint value
			. ' data-hxse-id="' . esc_attr( $hxse_id ) . '"'
			. ' data-prefix="' . esc_attr( $prefix ) . '">';

		$api_data = hxse_fetch_api_data( $schema );

		if ( hxse_api_is_interactive( $schema ) && ! is_wp_error( $api_data ) ) {
			// v1.8.0+: フィルタUI付き対話モード
			$items = hxse_api_extract_items( $api_data, $schema );

			// 'options' => 'auto' の選択肢生成用に全アイテムを渡す（絞り込み前）
			$schema['_api_items'] = $items;

			$endpoint = rest_url( 'hxse/v1/search' );
			hxse_render_filters( $schema, $hxse_id, $current_params, $endpoint );

			$items = hxse_filter_api_items( $items, $schema, $current_params );
			$items = hxse_sort_api_items( $items, $schema, $current_params );

			echo '<div id="hxse-results-' . esc_attr( $hxse_id ) . '" class="hxse-results-wrap">';
			hxse_render_api_page( $schema, $hxse_id, $items, $page );
			echo '</div>';
		} else {
			echo '<div id="hxse-results-' . esc_attr( $hxse_id ) . '" class="hxse-results-wrap">';
			hxse_render_api_results( $schema, $hxse_id, $api_data );
			echo '</div>';
		}

		echo '</div>';
		return ob_get_clean();
	}

	$query_args = hxse_build_query_args( $schema, $current_params, $page );
	$query      = new WP_Query( $query_args );

	// REST APIエンドポイントURL
	$endpoint = rest_url( 'hxse/v1/search' );

	ob_start();

	$columns = isset( $schema['columns'] ) ? absint( $schema['columns'] ) : 0;
	$style   = $columns ? ' style="--hxse-columns:' . $columns . '"' : '';

	echo '<div class="hxse-wrap" id="hxse-wrap-' . esc_attr( $hxse_id ) . '"'
		. $style // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled inline style with absint value
		. ' data-hxse-id="' . esc_attr( $hxse_id ) . '"'
		. ' data-prefix="' . esc_attr( $prefix ) . '">';

	hxse_render_filters( $schema, $hxse_id, $current_params, $endpoint );

	// タブ切り替え
	if ( ! empty( $schema['tabs'] ) ) {
		hxse_render_tabs( $schema, $hxse_id, $current_params, $endpoint );
	}

	// 表示切り替えアイコン
	if ( ! empty( $schema['display_switcher'] ) ) {
		hxse_render_display_switcher( $schema, $hxse_id, $current_params, $endpoint );
	}

	echo '<div id="hxse-results-' . esc_attr( $hxse_id ) . '" class="hxse-results-wrap">';
	hxse_render_results( $schema, $hxse_id, $query, $page );
	echo '</div>';

	echo '</div>';

	wp_reset_postdata();

	return ob_get_clean();
}
