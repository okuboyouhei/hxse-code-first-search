<?php
/**
 * HXSE — iframe埋め込みビュー（段階1：一覧のみ）
 *
 * `?hxse_embed={schema_id}` でアクセスすると、フィルターUIなしの
 * 一覧だけを自己完結HTMLで出力する。WordPress外のLPなどにiframeで埋め込める。
 *
 * @package HXSE
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 埋め込みリクエストを検知して処理する。
 * template_redirect で早期にフックし、通常のテーマ出力を行わず専用HTMLを返す。
 */
function hxse_maybe_render_embed() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public embed view, no state change
	if ( empty( $_GET['hxse_embed'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$schema_id = sanitize_key( wp_unslash( $_GET['hxse_embed'] ) );
	$schema    = hxse_get_schema( $schema_id );

	if ( ! $schema ) {
		status_header( 404 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
		echo esc_html__( '指定された埋め込みが見つかりません。', 'hxse-code-first-search' );
		echo '</body></html>';
		exit;
	}

	$schema = hxse_normalize_schema( $schema );

	// embedが有効でなければ拒否
	if ( empty( $schema['embed'] ) || empty( $schema['embed']['enabled'] ) ) {
		status_header( 403 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
		echo esc_html__( 'この検索は埋め込みが許可されていません。', 'hxse-code-first-search' );
		echo '</body></html>';
		exit;
	}

	// クリックジャッキング対策：許可ドメインのみiframe埋め込みを許可する
	hxse_send_embed_frame_headers( $schema['embed'] );

	// 埋め込みHTMLを出力
	hxse_render_embed_page( $schema, $schema_id );
	exit;
}

/**
 * iframe埋め込み用のセキュリティヘッダーを送信する。
 * allowed_origins が指定されていれば frame-ancestors で制限。
 * 空の場合は同一オリジンのみ許可（SAMEORIGIN）。
 *
 * @param array $embed 正規化済みのembed設定
 */
function hxse_send_embed_frame_headers( array $embed ) {
	$origins = isset( $embed['allowed_origins'] ) ? (array) $embed['allowed_origins'] : array();

	// 不正な値を除去し、スキーム付きのオリジンだけ許可
	$valid = array();
	foreach ( $origins as $origin ) {
		$origin = esc_url_raw( trim( $origin ) );
		if ( $origin ) {
			// パス等を除去してスキーム+ホスト+ポートだけにする
			$parts = wp_parse_url( $origin );
			if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
				$clean = $parts['scheme'] . '://' . $parts['host'];
				if ( ! empty( $parts['port'] ) ) {
					$clean .= ':' . $parts['port'];
				}
				$valid[] = $clean;
			}
		}
	}

	if ( ! empty( $valid ) ) {
		// 許可ドメインを frame-ancestors に列挙（self も含める）
		$ancestors = "frame-ancestors 'self' " . implode( ' ', $valid ) . ';';
		header( 'Content-Security-Policy: ' . $ancestors );
		// X-Frame-Optionsは単一オリジンしか書けず複数許可と矛盾するので送らない
		// （CSP frame-ancestors が優先される）
	} else {
		// 許可ドメイン未指定：同一オリジンのみ
		header( "Content-Security-Policy: frame-ancestors 'self';" );
		header( 'X-Frame-Options: SAMEORIGIN' );
	}
}

/**
 * 埋め込みページの完全なHTMLを出力する（自己完結型）。
 *
 * @param array  $schema    正規化済みスキーマ
 * @param string $schema_id スキーマID
 */
function hxse_render_embed_page( array $schema, string $schema_id ) {
	$embed     = $schema['embed'];
	$embed_ttl = isset( $embed['title'] ) ? $embed['title'] : '';

	// 表示件数：embed.per_page があれば優先
	$page = 1;
	if ( ! empty( $embed['per_page'] ) ) {
		$schema['pagination']             = isset( $schema['pagination'] ) ? $schema['pagination'] : array();
		$schema['pagination']['per_page'] = absint( $embed['per_page'] );
		$schema['pagination']['mode']     = 'none'; // 埋め込みはページャーなし
	}

	header( 'Content-Type: text/html; charset=utf-8' );

	// CSSをインラインで読み込む（埋め込みページは独立HTMLのためenqueue不可）
	$css_inline = '';
	$css_path   = HXSE_PLUGIN_DIR . 'assets/hxse.css';
	if ( file_exists( $css_path ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css_inline = (string) file_get_contents( $css_path );
	}

	echo '<!DOCTYPE html>' . "\n";
	echo '<html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '">' . "\n";
	echo '<head>' . "\n";
	echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">' . "\n";
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
	echo '<meta name="robots" content="noindex">' . "\n"; // 埋め込みページは検索避け
	echo '<title>' . esc_html( $embed_ttl ? $embed_ttl : get_bloginfo( 'name' ) ) . '</title>' . "\n";
	echo '<style>' . "\n";
	echo 'body{margin:0;padding:12px;background:transparent;font-family:system-ui,sans-serif;}' . "\n";
	// プラグイン同梱CSSをインライン展開（自前の<head>のためwp_enqueue_styleは使えない）。
	// 自己管理の静的CSSファイルだが、念のため </style> の混入だけ無害化する。
	echo str_replace( '</style', '<\\/style', $css_inline ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled static CSS, only closing-tag injection neutralized
	echo "\n" . '</style>' . "\n";
	echo '</head>' . "\n";
	echo '<body class="hxse-embed-body">' . "\n";

	if ( $embed_ttl ) {
		echo '<h2 class="hxse-embed-title">' . esc_html( $embed_ttl ) . '</h2>' . "\n";
	}

	// 一覧だけを描画（フィルターUIなし）
	echo '<div class="hxse-wrap hxse-embed" id="hxse-wrap-' . esc_attr( $schema_id ) . '">';
	echo '<div id="hxse-results-' . esc_attr( $schema_id ) . '" class="hxse-results-wrap">';

	// マージモード/外部ソース/通常を分岐して一覧を取得
	if ( ! empty( $schema['sources'] ) && is_array( $schema['sources'] ) ) {
		$merged = hxse_fetch_merged_data( $schema );
		hxse_render_merged_results( $schema, $schema_id, $merged );
	} elseif ( in_array( ( isset( $schema['source'] ) ? $schema['source'] : '' ), array( 'api', 'rss', 'xml' ), true ) ) {
		$api_data = hxse_fetch_api_data( $schema );
		hxse_render_api_results( $schema, $schema_id, $api_data );
	} else {
		$query_args = hxse_build_query_args( $schema, array(), $page );
		$query      = new WP_Query( $query_args );
		hxse_render_results( $schema, $schema_id, $query, $page );
		wp_reset_postdata();
	}

	echo '</div>';
	echo '</div>';

	echo '</body>' . "\n";
	echo '</html>';
}
