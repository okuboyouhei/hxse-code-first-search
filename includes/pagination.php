<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ページネーションを描画する（件数表示＋ページャー）
 *
 * @param array    $schema
 * @param string   $hxse_id
 * @param WP_Query $query
 * @param int      $current_page
 */
function hxse_render_pagination( $schema, $hxse_id, $query, $current_page ) {
	$pagination  = $schema['pagination'];
	$mode        = isset( $pagination['mode'] ) ? sanitize_key( $pagination['mode'] ) : 'pager';
	$total       = (int) $query->found_posts;
	$per_page    = (int) $query->query_vars['posts_per_page'];
	$total_pages = (int) $query->max_num_pages;

	if ( $total <= 0 ) return;

	// 件数表示
	if ( ! empty( $pagination['show_count'] ) ) {
		hxse_render_count( $pagination, $total, $per_page, $current_page );
	}

	if ( 'pager' === $mode && ! empty( $pagination['show_pages'] ) && $total_pages > 1 ) {
		hxse_render_pager( $schema, $hxse_id, $pagination, $current_page, $total_pages );
	} elseif ( 'loadmore' === $mode && $current_page < $total_pages ) {
		hxse_render_loadmore( $schema, $hxse_id, $pagination, $current_page );
	}
}

/**
 * 件数表示のみ出力する（結果グリッドの上に表示するため）
 *
 * @param array    $schema
 * @param WP_Query $query
 * @param int      $current_page
 */
function hxse_render_count_only( $schema, $query, $current_page ) {
	$pagination = $schema['pagination'];
	$total      = (int) $query->found_posts;
	$per_page   = (int) $query->query_vars['posts_per_page'];

	if ( $total <= 0 ) return;
	if ( empty( $pagination['show_count'] ) ) return;

	hxse_render_count( $pagination, $total, $per_page, $current_page );
}

/**
 * ページャーのみ出力する（件数表示を除く）
 *
 * @param array    $schema
 * @param string   $hxse_id
 * @param WP_Query $query
 * @param int      $current_page
 */
function hxse_render_pagination_only( $schema, $hxse_id, $query, $current_page ) {
	$pagination  = $schema['pagination'];
	$mode        = isset( $pagination['mode'] ) ? sanitize_key( $pagination['mode'] ) : 'pager';
	$total       = (int) $query->found_posts;
	$total_pages = (int) $query->max_num_pages;

	if ( $total <= 0 ) return;

	if ( 'pager' === $mode && ! empty( $pagination['show_pages'] ) && $total_pages > 1 ) {
		hxse_render_pager( $schema, $hxse_id, $pagination, $current_page, $total_pages );
	} elseif ( 'loadmore' === $mode && $current_page < $total_pages ) {
		hxse_render_loadmore( $schema, $hxse_id, $pagination, $current_page );
	}
}

/**
 * 件数表示
 */
function hxse_render_count( $pagination, $total, $per_page, $current_page ) {
	$format = isset( $pagination['count_format'] )
		? $pagination['count_format']
		: '{total}件中 {from}〜{to}件を表示';

	$from = ( ( $current_page - 1 ) * $per_page ) + 1;
	$to   = min( $current_page * $per_page, $total );

	$output = str_replace(
		array( '{total}', '{from}', '{to}', '{current_page}' ),
		array( $total, $from, $to, $current_page ),
		$format
	);

	echo '<p class="hxse-count">' . esc_html( $output ) . '</p>';
}

/**
 * ページャー描画
 */
function hxse_render_pager( $schema, $hxse_id, $pagination, $current_page, $total_pages ) {
	$range    = isset( $pagination['range'] ) ? absint( $pagination['range'] ) : 2;
	$endpoint = rest_url( 'hxse/v1/search' );
	$target   = '#hxse-results-' . esc_attr( $hxse_id );
	$form_id  = '#hxse-form-' . esc_attr( $hxse_id );

	echo '<nav class="hxse-pager" aria-label="' . esc_attr__( 'ページナビゲーション', 'hxse-code-first-search' ) . '">';
	echo '<ul class="hxse-pager-list">';

	// 最初へ
	if ( ! empty( $pagination['label_first'] ) && $current_page > 1 ) {
		hxse_render_pager_item( $endpoint, $target, $form_id, $hxse_id, 1, $pagination['label_first'], false );
	}

	// 前へ
	if ( $current_page > 1 ) {
		hxse_render_pager_item( $endpoint, $target, $form_id, $hxse_id, $current_page - 1, $pagination['label_prev'], false );
	}

	// ページ番号
	$start = max( 1, $current_page - $range );
	$end   = min( $total_pages, $current_page + $range );

	if ( $start > 1 ) {
		hxse_render_pager_item( $endpoint, $target, $form_id, $hxse_id, 1, '1', false );
		if ( $start > 2 ) {
			echo '<li class="hxse-pager-ellipsis"><span>...</span></li>';
		}
	}

	for ( $i = $start; $i <= $end; $i++ ) {
		hxse_render_pager_item( $endpoint, $target, $form_id, $hxse_id, $i, (string) $i, $i === $current_page );
	}

	if ( $end < $total_pages ) {
		if ( $end < $total_pages - 1 ) {
			echo '<li class="hxse-pager-ellipsis"><span>...</span></li>';
		}
		hxse_render_pager_item( $endpoint, $target, $form_id, $hxse_id, $total_pages, (string) $total_pages, false );
	}

	// 次へ
	if ( $current_page < $total_pages ) {
		hxse_render_pager_item( $endpoint, $target, $form_id, $hxse_id, $current_page + 1, $pagination['label_next'], false );
	}

	// 最後へ
	if ( ! empty( $pagination['label_last'] ) && $current_page < $total_pages ) {
		hxse_render_pager_item( $endpoint, $target, $form_id, $hxse_id, $total_pages, $pagination['label_last'], false );
	}

	echo '</ul>';
	echo '</nav>';
}

/**
 * ページャーアイテム描画
 */
function hxse_render_pager_item( $endpoint, $target, $form_id, $hxse_id, $page, $label, $is_current ) {
	$class = 'hxse-pager-item';
	if ( $is_current ) $class .= ' is-current';

	echo '<li class="' . esc_attr( $class ) . '">';

	if ( $is_current ) {
		echo '<span class="hxse-pager-current">' . esc_html( $label ) . '</span>';
	} else {
		printf(
			'<button type="button" class="hxse-pager-btn" data-page="%d" data-hxse-id="%s"
				hx-get="%s"
				hx-target="%s"
				hx-include="%s"
				hx-vals=\'{"page": "%d", "id": "%s"}\'
			>%s</button>',
			absint( $page ),
			esc_attr( $hxse_id ),
			esc_url( $endpoint ),
			esc_attr( $target ),
			esc_attr( $form_id ),
			absint( $page ),
			esc_attr( $hxse_id ),
			esc_html( $label )
		);
	}

	echo '</li>';
}

/**
 * もっと見るボタン描画
 */
function hxse_render_loadmore( $schema, $hxse_id, $pagination, $current_page ) {
	$endpoint = rest_url( 'hxse/v1/search' );
	$next     = $current_page + 1;
	$label    = isset( $pagination['label_more'] ) ? $pagination['label_more'] : 'もっと見る';
	$form_id  = '#hxse-form-' . esc_attr( $hxse_id );
	$results  = '#hxse-results-' . esc_attr( $hxse_id );

	// ボタン自体のIDをターゲットにして差し替える
	// 次のページがなければボタンは返さない → 自然に消える
	$btn_id = 'hxse-loadmore-btn-' . esc_attr( $hxse_id );

	printf(
		'<div class="hxse-loadmore-wrap" id="hxse-loadmore-wrap-%s">
			<button type="button" class="hxse-loadmore-btn" id="%s"
				hx-get="%s"
				hx-target="%s"
				hx-swap="beforeend"
				hx-include="%s"
				hx-vals=\'{"page": "%d", "hxse_append": "1", "hxse_loadmore_id": "%s"}\'
				hx-indicator=".hxse-loading-%s"
			><span class="hxse-loadmore-icon">+</span> %s</button>
		</div>',
		esc_attr( $hxse_id ),
		esc_attr( $btn_id ),
		esc_url( $endpoint ),
		esc_attr( $results ),
		esc_attr( $form_id ),
		absint( $next ),
		esc_attr( $hxse_id ),
		esc_attr( $hxse_id ),
		esc_html( $label )
	);
}
