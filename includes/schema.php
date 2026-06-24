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
		'columns'          => 0,         // グリッド列数（0=CSSデフォルト3列）
		'table_columns'    => array(),   // tableヘッダー定義: [['key'=>'date','label'=>'日付'],...]
		'display_switcher' => array(),   // 表示切り替えボタン: ['grid','list','table'] or false
		'filters'          => array(),
		'conditions'       => array(),   // 固定絞り込み条件（UIなし）
		'tabs'             => array(),   // タブ切り替え
		'sort'             => array(),
		'pagination'       => array(),
		'url_params'       => array(),
		'template'         => '',
		'wrapper'          => array(),
		'embed'            => array(),   // iframe埋め込み設定
	);

	$schema = wp_parse_args( $schema, $defaults );

	// embedのデフォルト（enabledが指定された場合のみ正規化）
	if ( ! empty( $schema['embed'] ) && ! empty( $schema['embed']['enabled'] ) ) {
		$embed_defaults = array(
			'enabled'         => false,
			'allowed_origins' => array(),  // 埋め込みを許可するドメイン（空=同一オリジンのみ）
			'title'           => '',       // 埋め込みページの見出し（省略可）
			'per_page'        => 0,        // 埋め込み時の表示件数（0=スキーマのpagination設定に従う）
			'show_filters'    => false,    // 埋め込み内にフィルターUIを表示するか（v1.7.0+）
		);
		$schema['embed'] = wp_parse_args( $schema['embed'], $embed_defaults );
	}

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
