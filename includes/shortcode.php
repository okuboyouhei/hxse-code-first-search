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

	$query_args = hxse_build_query_args( $schema, $current_params, $page );
	$query      = new WP_Query( $query_args );

	// REST APIエンドポイントURL
	$endpoint = rest_url( 'hxse/v1/search' );

	ob_start();

	echo '<div class="hxse-wrap" id="hxse-wrap-' . esc_attr( $hxse_id ) . '"'
		. ' data-hxse-id="' . esc_attr( $hxse_id ) . '"'
		. ' data-prefix="' . esc_attr( $prefix ) . '">';

	hxse_render_filters( $schema, $hxse_id, $current_params, $endpoint );

	echo '<div id="hxse-results-' . esc_attr( $hxse_id ) . '" class="hxse-results-wrap">';
	hxse_render_results( $schema, $hxse_id, $query, $page );
	echo '</div>';

	echo '</div>';

	wp_reset_postdata();

	return ob_get_clean();
}
