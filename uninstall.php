<?php
/**
 * HXSE Uninstall
 * アンインストール時に wp-content/hxse-cache/ を削除する。
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * ディレクトリを再帰的に削除する。
 *
 * @param string $dir
 */
function hxse_uninstall_rmdir( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$files = glob( $dir . '/*' );
	if ( $files ) {
		foreach ( $files as $file ) {
			if ( is_dir( $file ) ) {
				hxse_uninstall_rmdir( $file );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $file );
			}
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	rmdir( $dir );
}

// wp-content/hxse-cache/ を削除
$hxse_cache_dir = WP_CONTENT_DIR . '/hxse-cache';
if ( is_dir( $hxse_cache_dir ) ) {
	hxse_uninstall_rmdir( $hxse_cache_dir );
}

// キャッシュマッピングのオプションを削除
delete_option( 'hxse_cache_map' );
