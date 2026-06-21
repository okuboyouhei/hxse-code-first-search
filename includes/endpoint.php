<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST APIエンドポイントを登録する
 */
function hxse_register_rest_route() {
	register_rest_route(
		'hxse/v1',
		'/search',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'hxse_rest_handler',
			'permission_callback' => '__return_true',
			// Public read-only endpoint.
			// Returns rendered HTML of published posts only (post_status = 'publish' is enforced in query args).
			// No authentication required as no private data is exposed.
		)
	);
}

/**
 * REST APIリクエストを処理する
 * WP_REST_ResponseではなくHTMLを直接出力する
 *
 * @param WP_REST_Request $request
 */
function hxse_rest_handler( WP_REST_Request $request ) {
	$hxse_id = sanitize_key( $request->get_param( 'id' ) );

	if ( ! $hxse_id ) {
		status_header( 400 );
		echo esc_html__( 'id パラメータが必要です。', 'hxse-code-first-search' );
		exit;
	}

	$schema = hxse_get_schema( $hxse_id );
	if ( ! $schema ) {
		status_header( 404 );
		echo esc_html__( 'スキーマが見つかりません。', 'hxse-code-first-search' );
		exit;
	}

	$schema    = hxse_normalize_schema( $schema );
	$params    = hxse_sanitize_request_params( $request );
	$page      = absint( $request->get_param( 'page' ) ) ?: 1;
	$is_append = (bool) $request->get_param( 'hxse_append' );

	// display切り替えパラメータをスキーマに反映
	$display_param = $request->get_param( 'display' );
	if ( $display_param ) {
		$allowed = array( 'grid', 'list', 'table', 'custom' );
		if ( in_array( sanitize_key( $display_param ), $allowed, true ) ) {
			$schema['display'] = sanitize_key( $display_param );
		}
	}

	$source = isset( $schema['source'] ) ? sanitize_key( $schema['source'] ) : 'wp_query';

	ob_start();
	if ( in_array( $source, array( 'api', 'rss', 'xml' ), true ) ) {
		$api_data = hxse_fetch_api_data( $schema );
		hxse_render_api_results( $schema, $hxse_id, $api_data );
	} else {
		$query_args = hxse_build_query_args( $schema, $params, $page );
		$query      = new WP_Query( $query_args );
		hxse_render_results( $schema, $hxse_id, $query, $page, $is_append );
	}
	$html = ob_get_clean();

	header( 'Content-Type: text/html; charset=utf-8' );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from controlled render functions
	exit;
}

/**
 * WP_REST_Requestからパラメータをサニタイズして返す
 *
 * @param WP_REST_Request $request
 * @return array
 */
function hxse_sanitize_request_params( WP_REST_Request $request ) {
	$sanitized = array();
	$params    = $request->get_query_params();

	foreach ( $params as $key => $value ) {
		$key = sanitize_key( $key );

		if ( is_array( $value ) ) {
			$sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
		} else {
			$sanitized[ $key ] = sanitize_text_field( (string) $value );
		}
	}

	return $sanitized;
}

/**
 * 検索結果HTMLを出力する
 *
 * @param array    $schema
 * @param string   $hxse_id
 * @param WP_Query $query
 * @param int      $page
 * @param bool     $is_append
 */
function hxse_render_results( $schema, $hxse_id, $query, $page, $is_append = false ) {
	$display = isset( $schema['display'] ) ? sanitize_key( $schema['display'] ) : 'grid';
	$wrapper = $schema['wrapper'];

	// displayに応じてコンテナ・アイテムのHTML・テンプレートを決定
	switch ( $display ) {
		case 'list':
			$container_open  = '<div class="hxse-results hxse-results--list" id="hxse-list-' . esc_attr( $hxse_id ) . '">';
			$container_close = '</div>';
			$item_open       = '<div class="hxse-item">';
			$item_close      = '</div>';
			$template        = 'list';
			break;

		case 'table':
			// table_columns が指定されていればそれを使い、なければデフォルト3列
			$table_cols = isset( $schema['table_columns'] ) && is_array( $schema['table_columns'] )
				? $schema['table_columns']
				: array(
					array( 'key' => 'date',     'label' => __( '日付',       'hxse-code-first-search' ) ),
					array( 'key' => 'title',    'label' => __( 'タイトル',   'hxse-code-first-search' ) ),
					array( 'key' => 'category', 'label' => __( 'カテゴリー', 'hxse-code-first-search' ) ),
				);
			$thead = '';
			foreach ( $table_cols as $col ) {
				$thead .= '<th>' . esc_html( $col['label'] ) . '</th>';
			}
			// table_columnsをendpoint側でも使えるようにschemaに保存
			$schema['_table_columns'] = $table_cols;
			$container_open  = '<div class="hxse-results hxse-results--table" id="hxse-table-' . esc_attr( $hxse_id ) . '"><table class="hxse-table"><thead><tr>'
				. $thead // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in loop above
				. '</tr></thead><tbody>';
			$container_close = '</tbody></table></div>';
			$item_open       = '';
			$item_close      = '';
			$template        = 'table-row';
			break;

		case 'custom':
			$container_open  = isset( $wrapper['container'] ) ? $wrapper['container'] : '<div class="hxse-results">';
			$container_close = '</div>';
			$item_open       = isset( $wrapper['item'] ) ? $wrapper['item'] : '<div class="hxse-item">';
			$item_close      = '</div>';
			$template        = 'custom';
			break;

		default: // grid
			$container_open  = '<div class="hxse-results hxse-results--grid" id="hxse-grid-' . esc_attr( $hxse_id ) . '">';
			$container_close = '</div>';
			$item_open       = '<div class="hxse-item">';
			$item_close      = '</div>';
			$template        = 'grid';
			break;
	}

	if ( ! $is_append ) {
		// 件数表示をコンテナの前（結果の上）に出力
		hxse_render_count_only( $schema, $query, $page );

		// 結果がない場合はコンテナを出力せずno-resultsのみ表示
		if ( ! $query->have_posts() ) {
			echo '<p class="hxse-no-results">' . esc_html__( '該当する結果が見つかりませんでした。', 'hxse-code-first-search' ) . '</p>';
			return;
		}

		echo $container_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled HTML string
	}

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			if ( $item_open ) {
				echo $item_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled HTML string
			}
			hxse_load_template( $schema, $hxse_id, $template );
			if ( $item_close ) {
				echo $item_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static closing tag
			}
		}
		wp_reset_postdata();
	}

	if ( ! $is_append ) {
		echo $container_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static closing tag
		hxse_render_pagination_only( $schema, $hxse_id, $query, $page );
	} else {
		// appendモード: loadmoreボタンをOOBスワップで差し替え
		$pagination  = $schema['pagination'];
		$mode        = isset( $pagination['mode'] ) ? sanitize_key( $pagination['mode'] ) : 'pager';
		$total_pages = (int) $query->max_num_pages;

		if ( 'loadmore' === $mode ) {
			if ( $page < $total_pages ) {
				ob_start();
				hxse_render_loadmore( $schema, $hxse_id, $pagination, $page );
				$new_btn     = ob_get_clean();
				$new_btn_oob = str_replace(
					'<div class="hxse-loadmore-wrap" id="hxse-loadmore-wrap-' . esc_attr( $hxse_id ) . '">',
					'<div class="hxse-loadmore-wrap" id="hxse-loadmore-wrap-' . esc_attr( $hxse_id ) . '" hx-swap-oob="outerHTML">',
					$new_btn
				);
				echo $new_btn_oob; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from controlled render function, OOB attribute added via str_replace on safe input
			} else {
				echo '<div id="hxse-loadmore-wrap-' . esc_attr( $hxse_id ) . '" hx-swap-oob="outerHTML"></div>';
			}
		}
	}
}

/**
 * テンプレートを読み込む
 *
 * @param array  $schema
 * @param string $hxse_id
 * @param string $display_template  display種別またはカスタムファイル名のヒント
 */
function hxse_load_template( $schema, $hxse_id, $display_template = 'grid' ) {
	$located = '';

	if ( 'custom' === $display_template ) {
		// customモード: schemaのtemplateキーで指定したファイルを使う
		$template_file = isset( $schema['template'] ) ? sanitize_file_name( $schema['template'] ) : '';

		if ( $template_file ) {
			$theme_path = get_stylesheet_directory() . '/hxse/' . $template_file;
			if ( file_exists( $theme_path ) ) {
				$located = $theme_path;
			}

			if ( ! $located ) {
				$theme_root = get_stylesheet_directory() . '/' . $template_file;
				if ( file_exists( $theme_root ) ) {
					$located = $theme_root;
				}
			}
		}
	} else {
		// grid / list / table-row: テーマ側での上書きを先に探す
		$template_file = $display_template . '.php';

		$theme_path = get_stylesheet_directory() . '/hxse/' . $template_file;
		if ( file_exists( $theme_path ) ) {
			$located = $theme_path;
		}

		// テーマに上書きがなければプラグイン同梱のテンプレートを使う
		if ( ! $located ) {
			$plugin_path = HXSE_PLUGIN_DIR . 'templates/' . $template_file;
			if ( file_exists( $plugin_path ) ) {
				$located = $plugin_path;
			}
		}
	}

	// 最終フォールバック: default.php
	if ( ! $located ) {
		$located = HXSE_PLUGIN_DIR . 'templates/default.php';
	}

	if ( file_exists( $located ) ) {
		include $located;
	}
}

/**
 * APIデータを表示する（source: 'api' モード）
 *
 * @param array        $schema
 * @param string       $hxse_id
 * @param array|WP_Error $api_data
 */
function hxse_render_api_results( $schema, $hxse_id, $api_data ) {
	if ( is_wp_error( $api_data ) ) {
		echo '<p class="hxse-no-results">' . esc_html( $api_data->get_error_message() ) . '</p>';
		return;
	}

	if ( empty( $api_data ) || ! is_array( $api_data ) ) {
		echo '<p class="hxse-no-results">' . esc_html__( '該当するデータが見つかりませんでした。', 'hxse-code-first-search' ) . '</p>';
		return;
	}

	// テンプレートに渡す変数
	$hxse_api_data   = $api_data;
	$hxse_schema     = $schema;
	$template_name   = isset( $schema['template'] ) ? sanitize_file_name( $schema['template'] ) : 'api';

	// テーマ側のテンプレートを探す
	$located = '';
	$theme_path = get_stylesheet_directory() . '/hxse/' . $template_name . '.php';
	if ( file_exists( $theme_path ) ) {
		$located = $theme_path;
	}

	if ( ! $located ) {
		// テンプレートが見つからない場合はJSONをそのまま出力（デバッグ用）
		echo '<pre class="hxse-api-debug">' . esc_html( wp_json_encode( $api_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';
		return;
	}

	include $located;
}

/**
 * マージデータを表示する（sources モード）。
 *
 * @param array $schema
 * @param string $hxse_id
 * @param array  $merged_data 正規化・マージ済みのアイテム配列
 */
function hxse_render_merged_results( $schema, $hxse_id, $merged_data ) {
	if ( empty( $merged_data ) || ! is_array( $merged_data ) ) {
		echo '<p class="hxse-no-results">' . esc_html__( '表示できる項目がありません。', 'hxse-code-first-search' ) . '</p>';
		return;
	}

	// テンプレートに渡す変数
	$hxse_merged_data = $merged_data;
	$hxse_schema      = $schema;
	$template_name    = isset( $schema['template'] ) ? sanitize_file_name( $schema['template'] ) : 'merged';

	// テーマ側のテンプレートを優先
	$theme_path = get_stylesheet_directory() . '/hxse/' . $template_name . '.php';
	if ( file_exists( $theme_path ) ) {
		include $theme_path;
		return;
	}

	// プラグイン同梱のデフォルトテンプレート
	$plugin_path = HXSE_PLUGIN_DIR . 'templates/merged.php';
	if ( file_exists( $plugin_path ) ) {
		include $plugin_path;
		return;
	}

	// フォールバック（デバッグ用）
	echo '<pre class="hxse-merged-debug">' . esc_html( wp_json_encode( $merged_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';
}
