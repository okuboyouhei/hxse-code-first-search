<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu',            'hxse_register_admin_page' );
add_action( 'admin_enqueue_scripts', 'hxse_enqueue_admin_assets' );

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
	echo '</div>';
}
