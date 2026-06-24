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
	$embed        = $schema['embed'];
	$embed_ttl    = isset( $embed['title'] ) ? $embed['title'] : '';
	$show_filters = ! empty( $embed['show_filters'] );

	// 表示件数：embed.per_page があれば優先
	$page = 1;
	if ( ! empty( $embed['per_page'] ) ) {
		$schema['pagination']             = isset( $schema['pagination'] ) ? $schema['pagination'] : array();
		$schema['pagination']['per_page'] = absint( $embed['per_page'] );
		$schema['pagination']['mode']     = 'none'; // 埋め込みはページャーなし
	}

	$is_external = ! empty( $schema['sources'] )
		|| in_array( ( isset( $schema['source'] ) ? $schema['source'] : '' ), array( 'api', 'rss', 'xml' ), true );

	// 現在のフィルター値（フィルターUI使用時、htmxからのGETで渡る）
	$current_params = array();
	if ( $show_filters && ! $is_external && function_exists( 'hxse_sanitize_get_params' ) ) {
		$current_params = hxse_sanitize_get_params();
	}

	header( 'Content-Type: text/html; charset=utf-8' );

	// CSSをインラインで読み込む（埋め込みページは独立HTMLのためenqueue不可）
	$css_inline = '';
	$css_path   = HXSE_PLUGIN_DIR . 'assets/hxse.css';
	if ( file_exists( $css_path ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css_inline = (string) file_get_contents( $css_path );
	}

	// htmx本体とhxse.js（フィルター使用時のみインライン）
	$htmx_inline = '';
	$hxse_js     = '';
	if ( $show_filters && ! $is_external ) {
		$htmx_path = HXSE_PLUGIN_DIR . 'assets/htmx.min.js';
		if ( file_exists( $htmx_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$htmx_inline = (string) file_get_contents( $htmx_path );
		}
		$hxse_js_path = HXSE_PLUGIN_DIR . 'assets/hxse.js';
		if ( file_exists( $hxse_js_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$hxse_js = (string) file_get_contents( $hxse_js_path );
		}
	}

	echo '<!DOCTYPE html>' . "\n";
	echo '<html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '">' . "\n";
	echo '<head>' . "\n";
	echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">' . "\n";
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
	echo '<meta name="robots" content="noindex">' . "\n";
	echo '<title>' . esc_html( $embed_ttl ? $embed_ttl : get_bloginfo( 'name' ) ) . '</title>' . "\n";
	echo '<style>' . "\n";
	echo 'body{margin:0;padding:12px;background:transparent;font-family:system-ui,sans-serif;}' . "\n";
	// 同梱CSSをインライン展開（自前の<head>のためenqueue不可）。</style 混入のみ無害化。
	echo str_replace( '</style', '<\/style', $css_inline ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled static CSS, closing-tag injection neutralized
	echo "\n" . '</style>' . "\n";

	if ( $htmx_inline ) {
		echo '<script>' . "\n";
		echo str_replace( '</script', '<\/script', $htmx_inline ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled static JS, closing-tag injection neutralized
		echo "\n" . '</script>' . "\n";
	}

	if ( $hxse_js ) {
		echo '<script>' . "\n";
		echo str_replace( '</script', '<\/script', $hxse_js ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled static JS, closing-tag injection neutralized
		echo "\n" . '</script>' . "\n";
	}

	echo '</head>' . "\n";
	echo '<body class="hxse-embed-body">' . "\n";

	if ( $embed_ttl ) {
		echo '<h2 class="hxse-embed-title">' . esc_html( $embed_ttl ) . '</h2>' . "\n";
	}

	echo '<div class="hxse-wrap hxse-embed" id="hxse-wrap-' . esc_attr( $schema_id ) . '">';

	// フィルターUI（show_filters時・WordPressソースのみ）
	if ( $show_filters && ! $is_external && ! empty( $schema['filters'] ) ) {
		// エンドポイントはデフォルト（REST API hxse/v1/search）を使う。
		// 結果エリアだけが差し替わり、高さ通知スクリプトのhtmx:afterSwapが発火する。
		hxse_render_filters( $schema, $schema_id, $current_params );
	}

	echo '<div id="hxse-results-' . esc_attr( $schema_id ) . '" class="hxse-results-wrap">';

	if ( ! empty( $schema['sources'] ) && is_array( $schema['sources'] ) ) {
		$merged = hxse_fetch_merged_data( $schema );
		hxse_render_merged_results( $schema, $schema_id, $merged );
	} elseif ( $is_external ) {
		$api_data = hxse_fetch_api_data( $schema );
		hxse_render_api_results( $schema, $schema_id, $api_data );
	} else {
		$query_args = hxse_build_query_args( $schema, $current_params, $page );
		$query      = new WP_Query( $query_args );
		hxse_render_results( $schema, $schema_id, $query, $page );
		wp_reset_postdata();
	}

	echo '</div>';
	echo '</div>';

	// iframe高さ自動通知スクリプト（常に出力。親が使うかは任意）
	hxse_render_embed_height_script( $embed );

	echo '</body>' . "\n";
	echo '</html>';
}

/**
 * iframe高さを親ウィンドウに通知するスクリプトを出力する。
 * 初回ロード・リサイズ・htmxによる結果差し替え後・DOM変化時に高さを送る。
 * 送信先originは allowed_origins に限定（未指定なら自オリジン）。
 *
 * @param array $embed 正規化済みのembed設定
 */
function hxse_render_embed_height_script( array $embed ) {
	$origins = isset( $embed['allowed_origins'] ) ? (array) $embed['allowed_origins'] : array();
	$targets = array();
	foreach ( $origins as $origin ) {
		$parts = wp_parse_url( esc_url_raw( trim( $origin ) ) );
		if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
			$clean = $parts['scheme'] . '://' . $parts['host'];
			if ( ! empty( $parts['port'] ) ) {
				$clean .= ':' . $parts['port'];
			}
			$targets[] = $clean;
		}
	}
	if ( empty( $targets ) ) {
		$targets[] = home_url();
	}
	$targets_json = wp_json_encode( array_values( array_unique( $targets ) ) );

	echo '<script>' . "\n";
	echo '(function(){' . "\n";
	echo 'var targets=' . $targets_json . ';' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output, safe in JS context
	echo 'function sendHeight(){var h=document.documentElement.scrollHeight;for(var i=0;i<targets.length;i++){try{window.parent.postMessage({hxseEmbedHeight:h},targets[i]);}catch(e){}}}' . "\n";
	echo 'window.addEventListener("load",sendHeight);' . "\n";
	echo 'window.addEventListener("resize",sendHeight);' . "\n";
	echo 'document.body.addEventListener("htmx:afterSwap",function(){setTimeout(sendHeight,50);});' . "\n";
	echo 'if(window.MutationObserver){var mo=new MutationObserver(function(){sendHeight();});mo.observe(document.body,{childList:true,subtree:true});}' . "\n";
	echo '})();' . "\n";
	echo '</script>' . "\n";
}
