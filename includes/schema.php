<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * hxse_schemas フィルター経由で全スキーマを収集して返す
 *
 * @return array
 */
function hxse_get_schemas() {
	$schemas = apply_filters( 'hxse_schemas', array() );
	return is_array( $schemas ) ? $schemas : array();
}

/**
 * IDを指定してスキーマを取得する
 *
 * @param string $id
 * @return array|null
 */
function hxse_get_schema( $id ) {
	$schemas = hxse_get_schemas();
	return isset( $schemas[ $id ] ) ? $schemas[ $id ] : null;
}

/**
 * スキーマのデフォルト値をマージして返す
 *
 * @param array $schema
 * @return array
 */
function hxse_normalize_schema( $schema ) {
	$defaults = array(
		'post_type'        => 'post',
		'display'          => 'grid',
		'display_switcher' => array(),   // 表示切り替えボタン: ['grid','list','table'] or false
		'filters'          => array(),
		'conditions'       => array(),   // 固定絞り込み条件（UIなし）
		'tabs'             => array(),   // タブ切り替え
		'sort'             => array(),
		'pagination'       => array(),
		'url_params'       => array(),
		'template'         => '',
		'wrapper'          => array(),
	);

	$schema = wp_parse_args( $schema, $defaults );

	// paginationのデフォルト
	$pagination_defaults = array(
		'mode'         => 'pager',
		'per_page'     => 12,
		'show_pages'   => true,
		'show_count'   => true,
		'count_format' => '{total}件中 {from}〜{to}件を表示',
		'range'        => 2,
		'label_prev'   => '前へ',
		'label_next'   => '次へ',
		'label_first'  => '最初へ',
		'label_last'   => '最後へ',
		'label_more'   => 'もっと見る',
		'loading_text' => '読み込み中...',
	);
	$schema['pagination'] = wp_parse_args( $schema['pagination'], $pagination_defaults );

	// url_paramsのデフォルト
	$url_defaults = array(
		'enable' => true,
		'prefix' => '',
		'mode'   => 'always', // 'always' or 'submit_only'
	);
	$schema['url_params'] = wp_parse_args( $schema['url_params'], $url_defaults );

	// wrapperのデフォルト
	$wrapper_defaults = array(
		'container' => '<div class="hxse-results">',
		'item'      => '<div class="hxse-item">',
	);
	$schema['wrapper'] = wp_parse_args( $schema['wrapper'], $wrapper_defaults );

	return $schema;
}
