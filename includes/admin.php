<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu',            'hxse_register_admin_page' );
add_action( 'admin_enqueue_scripts', 'hxse_enqueue_admin_assets' );
add_action( 'admin_post_hxse_delete_cache',    'hxse_handle_delete_cache' );
add_action( 'admin_post_hxse_refresh_cache',   'hxse_handle_refresh_cache' );
add_action( 'admin_post_hxse_delete_orphans',  'hxse_handle_delete_orphans' );
add_action( 'admin_post_hxse_delete_all_cache','hxse_handle_delete_all_cache' );

/**
 * 静的キャッシュ削除アクションを処理する
 */
function hxse_handle_delete_cache(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'hxse-code-first-search' ) );
	}
	check_admin_referer( 'hxse_delete_cache' );

	$schema_id = isset( $_POST['schema_id'] ) ? sanitize_key( wp_unslash( $_POST['schema_id'] ) ) : '';
	$filename  = isset( $_POST['cache_file'] ) ? sanitize_file_name( wp_unslash( $_POST['cache_file'] ) ) : '';

	if ( $schema_id ) {
		hxse_delete_static_cache( $schema_id, $filename );
	}

	wp_safe_redirect( wp_nonce_url( admin_url( 'options-general.php?page=hxse&cache_deleted=1' ), 'hxse_delete_cache' ) );
	exit;
}

/**
 * 静的キャッシュ今すぐ更新アクションを処理する
 */
function hxse_handle_refresh_cache(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'hxse-code-first-search' ) );
	}
	check_admin_referer( 'hxse_refresh_cache' );

	$schema_id = isset( $_POST['schema_id'] ) ? sanitize_key( wp_unslash( $_POST['schema_id'] ) ) : '';

	if ( $schema_id ) {
		$schemas = hxse_get_schemas();
		if ( isset( $schemas[ $schema_id ] ) ) {
			$schema   = $schemas[ $schema_id ];
			$source   = isset( $schema['source'] ) ? sanitize_key( $schema['source'] ) : 'api';
			$endpoint = isset( $schema['endpoint'] ) ? esc_url_raw( $schema['endpoint'] ) : '';
			$filename = isset( $schema['cache_file'] ) ? sanitize_file_name( $schema['cache_file'] ) : sanitize_file_name( $schema_id ) . '.json';

			if ( $endpoint ) {
				$data = hxse_do_remote_fetch( $schema, $endpoint, $source );
				if ( ! is_wp_error( $data ) ) {
					hxse_save_static_cache( $schema_id, $filename, $data );
				}
			}
		}
	}

	wp_safe_redirect( wp_nonce_url( admin_url( 'options-general.php?page=hxse&cache_refreshed=1' ), 'hxse_delete_cache' ) );
	exit;
}

/**
 * 孤立ファイル一括削除アクションを処理する
 */
function hxse_handle_delete_orphans(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'hxse-code-first-search' ) );
	}
	check_admin_referer( 'hxse_delete_orphans' );
	hxse_cache_delete_orphans();
	wp_safe_redirect( wp_nonce_url( admin_url( 'options-general.php?page=hxse&orphans_deleted=1' ), 'hxse_delete_cache' ) );
	exit;
}

/**
 * 全キャッシュ一括削除アクションを処理する
 */
function hxse_handle_delete_all_cache(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'hxse-code-first-search' ) );
	}
	check_admin_referer( 'hxse_delete_all_cache' );

	$dir   = WP_CONTENT_DIR . '/hxse-cache';
	$files = is_dir( $dir ) ? glob( $dir . '/*.json' ) : array();
	if ( $files ) {
		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $file );
		}
	}
	update_option( 'hxse_cache_map', array(), false );

	wp_safe_redirect( wp_nonce_url( admin_url( 'options-general.php?page=hxse&all_deleted=1' ), 'hxse_delete_cache' ) );
	exit;
}

/**
 * 管理画面ページを登録する
 */
function hxse_register_admin_page() {
	add_options_page(
		__( 'HXSE — Code-First Search', 'hxse-code-first-search' ),
		__( 'HXSE', 'hxse-code-first-search' ),
		'manage_options',
		'hxse',
		'hxse_render_admin_page'
	);
}

/**
 * 管理画面のアセットを読み込む
 *
 * @param string $hook
 */
function hxse_enqueue_admin_assets( $hook ) {
	if ( 'settings_page_hxse' !== $hook ) {
		return;
	}

	wp_register_script( 'hxse-admin', false, array(), HXSE_VERSION, true );
	wp_enqueue_script( 'hxse-admin' );
	wp_add_inline_script(
		'hxse-admin',
		'function hxseCopyShortcode( text, btn ) {
			var orig = btn.textContent;
			function onSuccess() {
				btn.textContent = "\u2713 Copied!";
				btn.style.background = "#00a32a";
				btn.style.borderColor = "#00a32a";
				btn.style.color = "#fff";
				setTimeout( function() {
					btn.textContent = orig;
					btn.style.background = "";
					btn.style.borderColor = "";
					btn.style.color = "";
				}, 1500 );
			}
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( onSuccess );
			} else {
				var ta = document.createElement( "textarea" );
				ta.value = text;
				ta.style.cssText = "position:fixed;top:0;left:0;opacity:0";
				document.body.appendChild( ta );
				ta.focus(); ta.select();
				try { document.execCommand( "copy" ); onSuccess(); } catch(e) {}
				document.body.removeChild( ta );
			}
		}'
	);

	wp_register_style( 'hxse-admin', false, array(), HXSE_VERSION );
	wp_enqueue_style( 'hxse-admin' );
	wp_add_inline_style(
		'hxse-admin',
		'.hxse-admin-desc{color:#646970;margin-bottom:1.25rem}
.hxse-schema-table{border-collapse:collapse;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-top:0}
.hxse-schema-table thead th{background:#f8fafc;font-size:11px;font-weight:500;color:#64748b;text-transform:uppercase;letter-spacing:.06em;padding:10px 16px;border-bottom:1px solid #e2e8f0;text-align:left;white-space:nowrap}
.hxse-schema-table tbody td{padding:14px 16px;border-bottom:1px solid #e2e8f0;vertical-align:middle;font-size:13px;color:#0f172a}
.hxse-schema-table tbody tr:last-child td{border-bottom:none}
.hxse-schema-table tbody tr:hover td{background:#f8fafc}
.hxse-schema-id{font-family:ui-monospace,monospace;font-weight:600;color:#2563eb;font-size:13px}
.hxse-badge{display:inline-flex;align-items:center;font-size:11px;font-weight:500;padding:3px 8px;border-radius:20px}
.hxse-badge--pager{background:#e8f0fe;color:#1a56db}
.hxse-badge--loadmore{background:#fef3c7;color:#92400e}
.hxse-badge--infinite{background:#d1fae5;color:#065f46}
.hxse-filter-tags{display:flex;flex-wrap:wrap;gap:4px}
.hxse-filter-tag{display:inline-block;font-size:11px;padding:2px 7px;border-radius:3px;background:#f1f5f9;border:1px solid #e2e8f0;color:#475569}
.hxse-sc-wrap{display:flex;align-items:center;gap:6px}
.hxse-sc-code{font-family:ui-monospace,monospace;font-size:12px;color:#2563eb;background:#f1f5f9;padding:4px 8px;border-radius:4px;border:1px solid #e2e8f0;white-space:nowrap}
.hxse-copy-btn{font-size:11px!important;padding:2px 10px!important;height:auto!important;line-height:1.6!important;white-space:nowrap}
.hxse-empty{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:1.5rem}
.hxse-empty pre{margin-top:1rem;padding:1rem;background:#1e293b;color:#e2e8f0;border-radius:6px;font-size:12px;line-height:1.6;overflow-x:auto;white-space:pre}'
	);
}

/**
 * 管理画面ページを描画する
 */
function hxse_render_admin_page() {
	$schemas = hxse_get_schemas();

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'HXSE — Code-First Search', 'hxse-code-first-search' ) . '</h1>';
	echo '<p class="hxse-admin-desc">' . esc_html__( '登録済みのスキーマ一覧です。ショートコードをコピーして投稿や固定ページに貼り付けてください。', 'hxse-code-first-search' ) . '</p>';

	if ( empty( $schemas ) ) {
		echo '<div class="hxse-empty">';
		echo '<p>' . esc_html__( 'スキーマが登録されていません。テーマの functions.php に hxse_schemas フィルターでスキーマを定義してください。', 'hxse-code-first-search' ) . '</p>';
		echo '<pre>add_filter( \'hxse_schemas\', function( $schemas ) {
    $schemas[\'news_search\'] = [
        \'post_type\' => \'post\',
        \'filters\'   => [
            [\'key\' => \'keyword\', \'type\' => \'search\', \'label\' => \'キーワード\'],
        ],
        \'pagination\' => [\'mode\' => \'pager\', \'per_page\' => 12],
    ];
    return $schemas;
} );</pre>';
		echo '</div>';
		echo '</div>';
		return;
	}

	echo '<table class="hxse-schema-table">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__( 'スキーマID', 'hxse-code-first-search' ) . '</th>';
	echo '<th>' . esc_html__( '対象', 'hxse-code-first-search' ) . '</th>';
	echo '<th>' . esc_html__( 'フィルター', 'hxse-code-first-search' ) . '</th>';
	echo '<th>' . esc_html__( 'ページネーション', 'hxse-code-first-search' ) . '</th>';
	echo '<th>' . esc_html__( 'ショートコード', 'hxse-code-first-search' ) . '</th>';
	echo '</tr></thead>';
	echo '<tbody>';

	foreach ( $schemas as $schema_id => $schema ) {
		$schema     = hxse_normalize_schema( $schema );
		$shortcode  = '[hxse id="' . esc_attr( $schema_id ) . '"]';
		$post_type  = sanitize_key( $schema['post_type'] );
		$pt_object  = get_post_type_object( $post_type );
		$pt_label   = $pt_object ? $pt_object->label : $post_type;
		$filters    = isset( $schema['filters'] ) ? $schema['filters'] : array();
		$mode       = isset( $schema['pagination']['mode'] ) ? sanitize_key( $schema['pagination']['mode'] ) : 'pager';
		$per_page   = isset( $schema['pagination']['per_page'] ) ? absint( $schema['pagination']['per_page'] ) : 12;

		echo '<tr>';

		// スキーマID
		echo '<td><span class="hxse-schema-id">' . esc_html( $schema_id ) . '</span></td>';

		// 対象投稿タイプ
		echo '<td>' . esc_html( $pt_label ) . ' <code style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:3px;color:#475569">' . esc_html( $post_type ) . '</code></td>';

		// フィルタータグ
		echo '<td>';
		if ( ! empty( $filters ) ) {
			echo '<div class="hxse-filter-tags">';
			foreach ( $filters as $filter ) {
				if ( ! empty( $filter['label'] ) ) {
					echo '<span class="hxse-filter-tag">' . esc_html( $filter['label'] ) . '</span>';
				}
			}
			echo '</div>';
		} else {
			echo '<span style="color:#94a3b8">' . esc_html__( 'なし', 'hxse-code-first-search' ) . '</span>';
		}
		echo '</td>';

		// ページネーション
		echo '<td>';
		echo '<span class="hxse-badge hxse-badge--' . esc_attr( $mode ) . '">' . esc_html( $mode ) . '</span>';
		echo ' <span style="font-size:12px;color:#64748b">' . absint( $per_page ) . esc_html__( '件', 'hxse-code-first-search' ) . '</span>';
		echo '</td>';

		// ショートコード
		echo '<td>';
		echo '<div class="hxse-sc-wrap">';
		echo '<code class="hxse-sc-code">' . esc_html( $shortcode ) . '</code>';
		echo '<button type="button" class="button hxse-copy-btn"';
		echo ' onclick="hxseCopyShortcode(' . esc_attr( wp_json_encode( $shortcode ) ) . ', this)">';
		echo '📋 ' . esc_html__( 'Copy', 'hxse-code-first-search' );
		echo '</button>';
		echo '</div>';
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody></table>';

	// --- キャッシュ管理セクション ---
	$cache_dir   = WP_CONTENT_DIR . '/hxse-cache';
	$cache_files = is_dir( $cache_dir ) ? glob( $cache_dir . '/*.json' ) : array();
	$cache_files = $cache_files ? $cache_files : array();
	$cache_map   = hxse_cache_map_get();
	$orphans     = hxse_cache_orphan_files();

	echo '<h2 style="margin-top:2rem">' . esc_html__( '静的JSONキャッシュ', 'hxse-code-first-search' ) . '</h2>';

	// フラッシュメッセージ（nonceで検証）
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$has_flash = isset( $_GET['cache_deleted'] ) || isset( $_GET['cache_refreshed'] ) || isset( $_GET['orphans_deleted'] ) || isset( $_GET['all_deleted'] );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( $has_flash && check_admin_referer( 'hxse_delete_cache', '_wpnonce', false ) ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['cache_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'キャッシュを削除しました。', 'hxse-code-first-search' ) . '</p></div>';
		} elseif ( isset( $_GET['cache_refreshed'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'キャッシュを更新しました。', 'hxse-code-first-search' ) . '</p></div>';
		} elseif ( isset( $_GET['orphans_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '孤立ファイルを削除しました。', 'hxse-code-first-search' ) . '</p></div>';
		} elseif ( isset( $_GET['all_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '全キャッシュを削除しました。', 'hxse-code-first-search' ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	// 孤立ファイル警告
	if ( ! empty( $orphans ) ) {
		echo '<div class="notice notice-warning">';
		echo '<p><strong>' . esc_html__( '孤立したキャッシュファイルがあります。', 'hxse-code-first-search' ) . '</strong> ';
		echo esc_html__( 'スキーマから削除されたか、cache_fileが変更されたファイルです。', 'hxse-code-first-search' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		echo '<input type="hidden" name="action" value="hxse_delete_orphans">';
		wp_nonce_field( 'hxse_delete_orphans' );
		echo '<button type="submit" class="button button-small" style="color:#b32d2e;border-color:#b32d2e">';
		// translators: %d: number of orphaned cache files
		echo esc_html( sprintf( __( '孤立ファイルを削除（%d件）', 'hxse-code-first-search' ), count( $orphans ) ) );
		echo '</button></form>';
		echo '</div>';
	}

	if ( empty( $cache_files ) ) {
		echo '<p style="color:#646970">' . esc_html__( '静的JSONキャッシュはありません。', 'hxse-code-first-search' ) . '</p>';
	} else {

		// 合計サイズ表示 + 一括削除
		$total_size = array_sum( array_map( 'filesize', $cache_files ) );
		echo '<div style="display:flex;align-items:center;gap:1rem;margin-bottom:.75rem">';
		echo '<span style="color:#646970;font-size:13px">';
		// translators: %1$d: number of cache files, %2$s: total file size
		echo esc_html( sprintf( __( '%1$d件・合計 %2$s', 'hxse-code-first-search' ), count( $cache_files ), size_format( $total_size ) ) );
		echo '</span>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="hxse_delete_all_cache">';
		wp_nonce_field( 'hxse_delete_all_cache' );
		echo '<button type="submit" class="button button-small" style="color:#b32d2e;border-color:#b32d2e" onclick="return confirm(\'' . esc_js( __( '全てのキャッシュを削除しますか？', 'hxse-code-first-search' ) ) . '\')">';
		echo esc_html__( '全て削除', 'hxse-code-first-search' );
		echo '</button></form>';
		echo '</div>';

		echo '<table class="hxse-schema-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'スキーマID', 'hxse-code-first-search' ) . '</th>';
		echo '<th>' . esc_html__( 'ファイル名', 'hxse-code-first-search' ) . '</th>';
		echo '<th>' . esc_html__( 'サイズ', 'hxse-code-first-search' ) . '</th>';
		echo '<th>' . esc_html__( '最終更新', 'hxse-code-first-search' ) . '</th>';
		echo '<th>' . esc_html__( '操作', 'hxse-code-first-search' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		// マッピングから登録済みキャッシュを表示
		foreach ( $cache_map as $schema_id => $filename ) {
			$path = $cache_dir . '/' . $filename;
			if ( ! file_exists( $path ) ) {
				continue;
			}
			$size     = size_format( filesize( $path ) );
			$modified = wp_date( 'Y-m-d H:i:s', filemtime( $path ) );
			$schemas  = hxse_get_schemas();
			$has_api  = isset( $schemas[ $schema_id ]['source'] ) && in_array( $schemas[ $schema_id ]['source'], array( 'api', 'rss', 'xml' ), true );

			echo '<tr>';
			echo '<td><span class="hxse-schema-id">' . esc_html( $schema_id ) . '</span></td>';
			echo '<td><code style="font-size:12px">' . esc_html( $filename ) . '</code></td>';
			echo '<td>' . esc_html( $size ) . '</td>';
			echo '<td>' . esc_html( $modified ) . '</td>';
			echo '<td style="display:flex;gap:6px;flex-wrap:wrap">';

			// 今すぐ更新ボタン（APIスキーマのみ）
			if ( $has_api ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="hxse_refresh_cache">';
				echo '<input type="hidden" name="schema_id" value="' . esc_attr( $schema_id ) . '">';
				wp_nonce_field( 'hxse_refresh_cache' );
				echo '<button type="submit" class="button button-small">🔄 ' . esc_html__( '今すぐ更新', 'hxse-code-first-search' ) . '</button>';
				echo '</form>';
			}

			// 削除ボタン
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="hxse_delete_cache">';
			echo '<input type="hidden" name="schema_id" value="' . esc_attr( $schema_id ) . '">';
			echo '<input type="hidden" name="cache_file" value="' . esc_attr( $filename ) . '">';
			wp_nonce_field( 'hxse_delete_cache' );
			echo '<button type="submit" class="button button-small" style="color:#b32d2e;border-color:#b32d2e" onclick="return confirm(\'' . esc_js( __( 'このキャッシュを削除しますか？', 'hxse-code-first-search' ) ) . '\')">';
			echo esc_html__( '削除', 'hxse-code-first-search' );
			echo '</button></form>';

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	echo '</div>';
}
