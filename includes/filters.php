<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * フィルターフォームを描画する
 *
 * @param array  $schema
 * @param string $hxse_id
 * @param array  $current_params 現在のパラメータ
 * @param string $endpoint       REST APIエンドポイントURL
 */
function hxse_render_filters( $schema, $hxse_id, $current_params = array(), $endpoint = '' ) {
	$prefix      = isset( $schema['url_params']['prefix'] ) ? sanitize_key( $schema['url_params']['prefix'] ) : '';
	$target      = '#hxse-results-' . esc_attr( $hxse_id );
	$url_enable  = ! empty( $schema['url_params']['enable'] );
	$url_mode    = isset( $schema['url_params']['mode'] ) ? sanitize_key( $schema['url_params']['mode'] ) : 'always';
	$push_url    = 'false';
	$has_filters = ! empty( $schema['filters'] );

	if ( ! $endpoint ) {
		$endpoint = rest_url( 'hxse/v1/search' );
	}

	// filtersが空の場合: 見えないフォームを出力してloadmore等のhtmx機能を維持
	if ( ! $has_filters ) {
		echo '<form class="hxse-filters hxse-filters--hidden" id="hxse-form-' . esc_attr( $hxse_id ) . '"';
		echo ' hx-get="' . esc_url( $endpoint ) . '"';
		echo ' hx-target="' . esc_attr( $target ) . '"';
		echo ' hx-include="this"';
		echo ' data-url-mode="' . esc_attr( $url_mode ) . '"';
		echo ' data-url-enable="' . esc_attr( $url_enable ? '1' : '0' ) . '"';
		echo ' style="display:none">';
		echo '<input type="hidden" name="id" value="' . esc_attr( $hxse_id ) . '">';
		echo '<input type="hidden" name="page" value="1" class="hxse-page-input">';
		echo '</form>';
		return;
	}

	echo '<form class="hxse-filters" id="hxse-form-' . esc_attr( $hxse_id ) . '"';
	echo ' hx-get="' . esc_url( $endpoint ) . '"';
	echo ' hx-target="' . esc_attr( $target ) . '"';
	echo ' hx-trigger="keyup changed delay:500ms from:[type=search], change, submit"';
	echo ' hx-include="this"';
	echo ' hx-push-url="' . esc_attr( $push_url ) . '"';
	echo ' hx-indicator=".hxse-loading-' . esc_attr( $hxse_id ) . '"';
	echo ' data-url-mode="' . esc_attr( $url_mode ) . '"';
	echo ' data-url-enable="' . esc_attr( $url_enable ? '1' : '0' ) . '"';
	echo '>';

	// モバイル用トグルボタン
	echo '<button type="button" class="hxse-filter-toggle" aria-expanded="false" aria-controls="hxse-filter-body-' . esc_attr( $hxse_id ) . '">';
	echo '<span class="hxse-filter-toggle-label">' . esc_html__( '絞り込み', 'hxse-code-first-search' ) . '</span>';
	echo '<span class="hxse-filter-toggle-icon" aria-hidden="true">▼</span>';
	echo '</button>';

	// フィルター本体（モバイルで折りたたみ対象）
	echo '<div class="hxse-filter-body" id="hxse-filter-body-' . esc_attr( $hxse_id ) . '">';

	// hidden fields（REST API用：action・nonceは不要）
	echo '<input type="hidden" name="id" value="' . esc_attr( $hxse_id ) . '">';
	echo '<input type="hidden" name="page" value="1" class="hxse-page-input">';

	// display・tab状態管理用hidden input（JS側で更新）
	$current_display = isset( $schema['display'] ) ? sanitize_key( $schema['display'] ) : 'grid';
	echo '<input type="hidden" name="display" value="' . esc_attr( $current_display ) . '" class="hxse-display-input">';
	echo '<input type="hidden" name="tab" value="0" class="hxse-tab-input">';

	// フィルター行
	echo '<div class="hxse-filters-row">';

	foreach ( $schema['filters'] as $filter ) {
		if ( empty( $filter['key'] ) || empty( $filter['type'] ) ) {
			continue;
		}

		$key         = sanitize_key( $filter['key'] );
		$type        = sanitize_key( $filter['type'] );
		$label       = isset( $filter['label'] ) ? sanitize_text_field( $filter['label'] ) : $key;
		$ui          = isset( $filter['ui'] ) ? sanitize_key( $filter['ui'] ) : 'select';
		$input_name  = $prefix ? $prefix . '_' . $key : $key;
		$current_val = hxse_get_param( $current_params, $key, $prefix );

		echo '<div class="hxse-filter hxse-filter-' . esc_attr( $type ) . ' hxse-ui-' . esc_attr( $ui ) . '">';
		echo '<label class="hxse-filter-label">' . esc_html( $label ) . '</label>';

		switch ( $type ) {
			case 'search':
				hxse_render_search_filter( $input_name, $current_val );
				break;
			case 'taxonomy':
				hxse_render_taxonomy_filter( $filter, $input_name, $current_val, $ui );
				break;
			case 'meta':
				hxse_render_meta_filter( $filter, $input_name, $current_val, $ui, $current_params, $prefix );
				break;
			case 'date':
				hxse_render_date_filter( $filter, $input_name, $current_val, $ui );
				break;
			case 'relation':
				hxse_render_relation_filter( $filter, $input_name, $current_val, $ui );
				break;
		}

		echo '</div>';
	}

	// ソート
	if ( ! empty( $schema['sort'] ) ) {
		hxse_render_sort( $schema['sort'], $prefix, $current_params );
	}

	echo '</div>'; // .hxse-filters-row

	echo '</div>'; // .hxse-filter-body

	// ボタン行
	echo '<div class="hxse-filters-actions">';
	echo '<button type="reset" class="hxse-reset">' . esc_html__( 'リセット', 'hxse-code-first-search' ) . '</button>';
	echo '<button type="submit" class="hxse-submit">' . esc_html__( '検索', 'hxse-code-first-search' ) . '</button>';
	echo '</div>'; // .hxse-filters-actions

	echo '</form>';
	echo '<div class="hxse-loading hxse-loading-' . esc_attr( $hxse_id ) . '" aria-hidden="true">' . esc_html__( '読み込み中...', 'hxse-code-first-search' ) . '</div>';
}

function hxse_render_search_filter( $name, $value ) {
	printf(
		'<input type="search" name="%s" value="%s" class="hxse-input" placeholder="%s">',
		esc_attr( $name ),
		esc_attr( (string) $value ),
		esc_attr__( 'キーワードを入力', 'hxse-code-first-search' )
	);
}

function hxse_render_taxonomy_filter( $filter, $name, $value, $ui ) {
	$taxonomy   = isset( $filter['taxonomy'] ) ? sanitize_key( $filter['taxonomy'] ) : '';
	if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) return;

	$show_count = ! empty( $filter['show_count'] );

	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
	) );

	if ( is_wp_error( $terms ) || empty( $terms ) ) return;

	$values = is_array( $value ) ? $value : ( $value ? array( $value ) : array() );

	if ( 'checkbox' === $ui ) {
		echo '<div class="hxse-checkbox-group">';
		foreach ( $terms as $term ) {
			$checked = in_array( (string) $term->term_id, $values, true ) ? ' checked' : '';
			$label   = esc_html( $term->name );
			if ( $show_count ) {
				$label .= ' <span class="hxse-count-badge">(' . absint( $term->count ) . ')</span>';
			}
			printf(
				'<label class="hxse-checkbox-label"><input type="checkbox" name="%s[]" value="%s"%s> %s</label>',
				esc_attr( $name ),
				esc_attr( (string) $term->term_id ),
				$checked, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string
				$label    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			);
		}
		echo '</div>';

	} elseif ( 'radio' === $ui ) {
		echo '<div class="hxse-radio-group">';
		printf(
			'<label class="hxse-radio-label"><input type="radio" name="%s" value=""> %s</label>',
			esc_attr( $name ),
			esc_html__( 'すべて', 'hxse-code-first-search' )
		);
		foreach ( $terms as $term ) {
			$checked = checked( (string) $term->term_id, (string) $value, false );
			$label   = esc_html( $term->name );
			if ( $show_count ) {
				$label .= ' <span class="hxse-count-badge">(' . absint( $term->count ) . ')</span>';
			}
			printf(
				'<label class="hxse-radio-label"><input type="radio" name="%s" value="%s"%s> %s</label>',
				esc_attr( $name ),
				esc_attr( (string) $term->term_id ),
				$checked, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress checked() output
				$label    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			);
		}
		echo '</div>';

	} else {
		// select
		echo '<select name="' . esc_attr( $name ) . '" class="hxse-select">';
		echo '<option value="">' . esc_html__( 'すべて', 'hxse-code-first-search' ) . '</option>';
		foreach ( $terms as $term ) {
			$selected = selected( (string) $term->term_id, (string) $value, false );
			$label    = $show_count
				? $term->name . ' (' . absint( $term->count ) . ')'
				: $term->name;
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $term->term_id ),
				$selected,            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress selected() output
				esc_html( $label )
			);
		}
		echo '</select>';
	}
}

function hxse_render_meta_filter( $filter, $name, $value, $ui, $current_params, $prefix ) {
	if ( 'range' === $ui ) {
		$min      = isset( $filter['min'] )  ? (float) $filter['min']  : 0;
		$max      = isset( $filter['max'] )  ? (float) $filter['max']  : 100;
		$step     = isset( $filter['step'] ) ? (float) $filter['step'] : 1;
		$key      = sanitize_key( $filter['key'] );
		$name_min = $prefix ? $prefix . '_' . $key . '_min' : $key . '_min';
		$name_max = $prefix ? $prefix . '_' . $key . '_max' : $key . '_max';
		$val_min  = hxse_get_param( $current_params, $key . '_min', $prefix );
		$val_max  = hxse_get_param( $current_params, $key . '_max', $prefix );
		$val_min  = '' !== $val_min ? (float) $val_min : $min;
		$val_max  = '' !== $val_max ? (float) $val_max : $max;

		echo '<div class="hxse-range-wrap" data-min="' . esc_attr( $min ) . '" data-max="' . esc_attr( $max ) . '">';
		echo '<div class="hxse-range-display">';
		echo '<span class="hxse-range-min-val">' . esc_html( $val_min ) . '</span>';
		echo ' 〜 ';
		echo '<span class="hxse-range-max-val">' . esc_html( $val_max ) . '</span>';
		echo '</div>';
		printf(
			'<input type="range" name="%s" min="%s" max="%s" step="%s" value="%s" class="hxse-range hxse-range-min">',
			esc_attr( $name_min ),
			esc_attr( $min ),
			esc_attr( $max ),
			esc_attr( $step ),
			esc_attr( $val_min )
		);
		printf(
			'<input type="range" name="%s" min="%s" max="%s" step="%s" value="%s" class="hxse-range hxse-range-max">',
			esc_attr( $name_max ),
			esc_attr( $min ),
			esc_attr( $max ),
			esc_attr( $step ),
			esc_attr( $val_max )
		);
		echo '</div>';
		return;
	}

	$options    = isset( $filter['options'] ) && is_array( $filter['options'] ) ? $filter['options'] : array();
	$show_count = ! empty( $filter['show_count'] );
	$meta_key   = isset( $filter['meta_key'] ) ? sanitize_key( $filter['meta_key'] ) : '';

	// show_count有効時: 各オプションの件数を取得
	$counts = array();
	if ( $show_count && $meta_key && ! empty( $options ) ) {
		foreach ( $options as $opt_val => $opt_label ) {
			$count_query = new WP_Query( array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => $meta_key,
						'value'   => (string) $opt_val,
						'compare' => '=',
					),
				),
			) );
			$counts[ (string) $opt_val ] = $count_query->found_posts;
			wp_reset_postdata();
		}
	}

	if ( 'checkbox' === $ui ) {
		$values = is_array( $value ) ? $value : ( $value ? array( $value ) : array() );
		echo '<div class="hxse-checkbox-group">';
		foreach ( $options as $opt_val => $opt_label ) {
			$checked = in_array( (string) $opt_val, $values, true ) ? ' checked' : '';
			$label   = esc_html( (string) $opt_label );
			if ( $show_count && isset( $counts[ (string) $opt_val ] ) ) {
				$label .= ' <span class="hxse-count-badge">(' . absint( $counts[ (string) $opt_val ] ) . ')</span>';
			}
			printf(
				'<label class="hxse-checkbox-label"><input type="checkbox" name="%s[]" value="%s"%s> %s</label>',
				esc_attr( $name ),
				esc_attr( (string) $opt_val ),
				$checked, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string
				$label    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			);
		}
		echo '</div>';

	} elseif ( 'radio' === $ui ) {
		echo '<div class="hxse-radio-group">';
		printf(
			'<label class="hxse-radio-label"><input type="radio" name="%s" value=""> %s</label>',
			esc_attr( $name ),
			esc_html__( 'すべて', 'hxse-code-first-search' )
		);
		foreach ( $options as $opt_val => $opt_label ) {
			$checked = checked( (string) $opt_val, (string) $value, false );
			$label   = esc_html( (string) $opt_label );
			if ( $show_count && isset( $counts[ (string) $opt_val ] ) ) {
				$label .= ' <span class="hxse-count-badge">(' . absint( $counts[ (string) $opt_val ] ) . ')</span>';
			}
			printf(
				'<label class="hxse-radio-label"><input type="radio" name="%s" value="%s"%s> %s</label>',
				esc_attr( $name ),
				esc_attr( (string) $opt_val ),
				$checked, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress checked() output
				$label    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			);
		}
		echo '</div>';

	} else {
		echo '<select name="' . esc_attr( $name ) . '" class="hxse-select">';
		echo '<option value="">' . esc_html__( 'すべて', 'hxse-code-first-search' ) . '</option>';
		foreach ( $options as $opt_val => $opt_label ) {
			$selected = selected( (string) $opt_val, (string) $value, false );
			$label    = (string) $opt_label;
			if ( $show_count && isset( $counts[ (string) $opt_val ] ) ) {
				$label .= ' (' . absint( $counts[ (string) $opt_val ] ) . ')';
			}
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $opt_val ),
				$selected,         // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress selected() output
				esc_html( $label )
			);
		}
		echo '</select>';
	}
}

function hxse_render_date_filter( $filter, $name, $value, $ui ) {
	$current_year = (int) gmdate( 'Y' );
	$start_year   = isset( $filter['start_year'] ) ? (int) $filter['start_year'] : $current_year - 10;

	if ( 'select' === $ui ) {
		echo '<select name="' . esc_attr( $name ) . '" class="hxse-select">';
		echo '<option value="">' . esc_html__( 'すべて', 'hxse-code-first-search' ) . '</option>';
		for ( $y = $current_year; $y >= $start_year; $y-- ) {
			$selected = selected( (string) $y, (string) $value, false );
			printf(
				'<option value="%d"%s>%d年</option>',
				absint( $y ),
				$selected, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress selected() output
				absint( $y )
			);
		}
		echo '</select>';
	} else {
		printf(
			'<input type="text" name="%s" value="%s" class="hxse-input" placeholder="2024">',
			esc_attr( $name ),
			esc_attr( (string) $value )
		);
	}
}

function hxse_render_relation_filter( $filter, $name, $value, $ui ) {
	$related_post_type = isset( $filter['related_post_type'] ) ? sanitize_key( $filter['related_post_type'] ) : 'post';

	$posts = get_posts( array(
		'post_type'      => $related_post_type,
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );

	if ( empty( $posts ) ) return;

	$values = is_array( $value ) ? $value : ( $value ? array( $value ) : array() );

	if ( 'checkbox' === $ui ) {
		echo '<div class="hxse-checkbox-group">';
		foreach ( $posts as $related_post ) {
			$checked = in_array( (string) $related_post->ID, $values, true ) ? ' checked' : '';
			printf(
				'<label class="hxse-checkbox-label"><input type="checkbox" name="%s[]" value="%s"%s> %s</label>',
				esc_attr( $name ),
				esc_attr( (string) $related_post->ID ),
				$checked, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string
				esc_html( $related_post->post_title )
			);
		}
		echo '</div>';
	} else {
		echo '<select name="' . esc_attr( $name ) . '" class="hxse-select">';
		echo '<option value="">' . esc_html__( 'すべて', 'hxse-code-first-search' ) . '</option>';
		foreach ( $posts as $related_post ) {
			$selected = selected( (string) $related_post->ID, (string) $value, false );
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $related_post->ID ),
				$selected, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress selected() output
				esc_html( $related_post->post_title )
			);
		}
		echo '</select>';
	}
}

function hxse_render_sort( $sort_options, $prefix, $current_params ) {
	$name        = $prefix ? $prefix . '_sort' : 'sort';
	$current_val = hxse_get_param( $current_params, 'sort', $prefix );

	echo '<div class="hxse-sort">';
	echo '<label class="hxse-filter-label">' . esc_html__( '並び順', 'hxse-code-first-search' ) . '</label>';
	echo '<select name="' . esc_attr( $name ) . '" class="hxse-select">';
	foreach ( $sort_options as $option ) {
		$selected = selected( sanitize_key( $option['key'] ), (string) $current_val, false );
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( sanitize_key( $option['key'] ) ),
			$selected, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress selected() output
			esc_html( $option['label'] )
		);
	}
	echo '</select>';
	echo '</div>';
}

/**
 * タブ切り替えUIを描画する
 *
 * @param array  $schema
 * @param string $hxse_id
 * @param array  $current_params
 * @param string $endpoint
 */
function hxse_render_tabs( $schema, $hxse_id, $current_params, $endpoint ) {
	$tabs       = $schema['tabs'];
	$prefix     = isset( $schema['url_params']['prefix'] ) ? sanitize_key( $schema['url_params']['prefix'] ) : '';
	$active_tab = hxse_get_param( $current_params, 'tab', $prefix );
	$active_tab = $active_tab !== '' ? absint( $active_tab ) : 0;
	$target     = '#hxse-results-' . esc_attr( $hxse_id );
	$form_id    = '#hxse-form-' . esc_attr( $hxse_id );

	echo '<div class="hxse-tabs" role="tablist">';

	foreach ( $tabs as $index => $tab ) {
		$label     = isset( $tab['label'] ) ? sanitize_text_field( $tab['label'] ) : '';
		$is_active = ( $index === $active_tab );

		printf(
			'<button type="button" class="hxse-tab-btn%s" role="tab" aria-selected="%s"
				hx-get="%s"
				hx-target="%s"
				hx-include="%s"
				hx-vals=\'{"tab": "%d", "page": "1"}\'
			>%s</button>',
			$is_active ? ' is-active' : '',
			$is_active ? 'true' : 'false',
			esc_url( $endpoint ),
			esc_attr( $target ),
			esc_attr( $form_id ),
			absint( $index ),
			esc_html( $label )
		);
	}

	echo '</div>';
}

/**
 * 表示切り替えアイコンボタンを描画する
 *
 * @param array  $schema
 * @param string $hxse_id
 * @param array  $current_params
 * @param string $endpoint
 */
function hxse_render_display_switcher( $schema, $hxse_id, $current_params, $endpoint ) {
	$switcher       = (array) $schema['display_switcher'];
	$current_display = isset( $schema['display'] ) ? sanitize_key( $schema['display'] ) : 'grid';
	$target         = '#hxse-results-' . esc_attr( $hxse_id );
	$form_id        = '#hxse-form-' . esc_attr( $hxse_id );

	$icons = array(
		'grid'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
		'list'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
		'table' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="9" x2="9" y2="21"/></svg>',
	);

	$labels = array(
		'grid'  => __( 'グリッド表示', 'hxse-code-first-search' ),
		'list'  => __( 'リスト表示', 'hxse-code-first-search' ),
		'table' => __( 'テーブル表示', 'hxse-code-first-search' ),
	);

	echo '<div class="hxse-display-switcher">';

	foreach ( $switcher as $mode ) {
		$mode      = sanitize_key( $mode );
		$is_active = ( $mode === $current_display );
		$icon      = isset( $icons[ $mode ] ) ? $icons[ $mode ] : '';
		$label     = isset( $labels[ $mode ] ) ? $labels[ $mode ] : $mode;

		printf(
			'<button type="button" class="hxse-display-btn%s" aria-label="%s" aria-pressed="%s"
				hx-get="%s"
				hx-target="%s"
				hx-include="%s"
				hx-vals=\'{"display": "%s", "page": "1"}\'
			>%s</button>',
			$is_active ? ' is-active' : '',
			esc_attr( $label ),
			$is_active ? 'true' : 'false',
			esc_url( $endpoint ),
			esc_attr( $target ),
			esc_attr( $form_id ),
			esc_attr( $mode ),
			$icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG string
		);
	}

	echo '</div>';
}
