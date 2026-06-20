<?php
/**
 * Plugin Name: HXSE — Code-First Search
 * Plugin URI:  https://github.com/okuboyouhei/hxse-code-first-search
 * Description: Code-first search & filter for WordPress. Define filters with PHP arrays, output with a shortcode. Powered by htmx — no page reloads.
 * Version:     1.1.0
 * Author:      Youhei Okubo
 * Author URI:  https://zenn.dev/youheiokubo
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hxse-code-first-search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HXSE_VERSION',    '1.1.0' );
define( 'HXSE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HXSE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once HXSE_PLUGIN_DIR . 'includes/schema.php';
require_once HXSE_PLUGIN_DIR . 'includes/query.php';
require_once HXSE_PLUGIN_DIR . 'includes/filters.php';
require_once HXSE_PLUGIN_DIR . 'includes/pagination.php';
require_once HXSE_PLUGIN_DIR . 'includes/endpoint.php';
require_once HXSE_PLUGIN_DIR . 'includes/shortcode.php';
require_once HXSE_PLUGIN_DIR . 'includes/admin.php';

add_action( 'wp_enqueue_scripts', 'hxse_enqueue_assets' );
add_action( 'rest_api_init',      'hxse_register_rest_route' );

/**
 * フロントエンドアセットの読み込み
 * ショートコード [hxse] が使われているページのみ読み込む。
 * htmxはHXシリーズ共通ハンドル名 'hx-htmx' で登録する。
 * Text Domain はWordPress 4.6以降自動ロードされるため load_plugin_textdomain() 不要。
 */
function hxse_enqueue_assets() {
	global $post;

	// ショートコードが含まれていないページはスキップ
	if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'hxse' ) ) {
		return;
	}

	if ( ! wp_script_is( 'hx-htmx', 'registered' ) ) {
		wp_register_script(
			'hx-htmx',
			HXSE_PLUGIN_URL . 'assets/htmx.min.js',
			array(),
			'2.0.10',
			true
		);
	}
	wp_enqueue_script( 'hx-htmx' );

	wp_enqueue_script(
		'hxse',
		HXSE_PLUGIN_URL . 'assets/hxse.js',
		array( 'hx-htmx' ),
		HXSE_VERSION,
		true
	);

	wp_enqueue_style(
		'hxse',
		HXSE_PLUGIN_URL . 'assets/hxse.css',
		array(),
		HXSE_VERSION
	);
}
