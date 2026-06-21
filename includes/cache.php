<?php
/**
 * HXSE — 静的JSONキャッシュ管理
 *
 * cache_mode: 'static' のとき、外部APIのレスポンスをJSONファイルとして保存する。
 * 保存場所: wp-content/hxse-cache/
 * Webアクセス: .htaccess でブロック
 *
 * @package HXSE
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * キャッシュディレクトリのパスを返す。
 *
 * @return string
 */
function hxse_cache_dir(): string {
	return WP_CONTENT_DIR . '/hxse-cache';
}

/**
 * キャッシュディレクトリを初期化する（初回のみ）。
 * .htaccess と index.php を作成してWebアクセスをブロックする。
 */
function hxse_init_cache_dir(): void {
	$dir = hxse_cache_dir();

	if ( ! is_dir( $dir ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		mkdir( $dir, 0755, true );
	}

	// .htaccess でWebアクセスをブロック
	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $htaccess, "Deny from all\n" );
	}

	// index.php でディレクトリ一覧を非表示
	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $index, "<?php // Silence is golden.\n" );
	}
}

/**
 * 静的JSONキャッシュファイルのパスを返す。
 *
 * @param string $schema_id スキーマID
 * @param string $filename  ファイル名（省略時はスキーマIDから自動生成）
 * @return string
 */
function hxse_cache_file_path( string $schema_id, string $filename = '' ): string {
	if ( ! $filename ) {
		$filename = sanitize_file_name( $schema_id ) . '.json';
	}
	return hxse_cache_dir() . '/' . sanitize_file_name( $filename );
}

/**
 * 静的JSONキャッシュを読み込む。
 * cache_ttl を超えていた場合は null を返す（再生成が必要）。
 *
 * @param string $schema_id スキーマID
 * @param string $filename  ファイル名
 * @param int    $ttl       キャッシュ有効秒数
 * @return array|null キャッシュデータ、または null（期限切れ・未生成）
 */
function hxse_load_static_cache( string $schema_id, string $filename, int $ttl ): ?array {
	$path = hxse_cache_file_path( $schema_id, $filename );

	if ( ! file_exists( $path ) ) {
		return null;
	}

	// TTLチェック
	$mtime = filemtime( $path );
	if ( $ttl > 0 && ( time() - $mtime ) > $ttl ) {
		return null; // 期限切れ
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$json = file_get_contents( $path );
	if ( false === $json ) {
		return null;
	}

	$data = json_decode( $json, true );
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		return null;
	}

	return $data;
}

/**
 * 静的JSONキャッシュを保存する。
 *
 * @param string $schema_id スキーマID
 * @param string $filename  ファイル名
 * @param array  $data      保存するデータ
 * @return bool 成功/失敗
 */
function hxse_save_static_cache( string $schema_id, string $filename, array $data ): bool {
	hxse_init_cache_dir();

	$path = hxse_cache_file_path( $schema_id, $filename );
	$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

	if ( false === $json ) {
		return false;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$result = false !== file_put_contents( $path, $json );

	if ( $result ) {
		hxse_cache_map_add( $schema_id, $filename );
	}

	return $result;
}

/**
 * 静的JSONキャッシュを削除する。
 *
 * @param string $schema_id スキーマID
 * @param string $filename  ファイル名
 */
function hxse_delete_static_cache( string $schema_id, string $filename = '' ): void {
	$path = hxse_cache_file_path( $schema_id, $filename );
	if ( file_exists( $path ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $path );
	}
	hxse_cache_map_remove( $schema_id );
}

/**
 * キャッシュマッピング（スキーマID → ファイル名）を取得する。
 *
 * @return array
 */
function hxse_cache_map_get(): array {
	return (array) get_option( 'hxse_cache_map', array() );
}

/**
 * キャッシュマッピングにエントリを追加する。
 *
 * @param string $schema_id
 * @param string $filename
 */
function hxse_cache_map_add( string $schema_id, string $filename ): void {
	$map               = hxse_cache_map_get();
	$map[ $schema_id ] = $filename;
	update_option( 'hxse_cache_map', $map, false );
}

/**
 * キャッシュマッピングからエントリを削除する。
 *
 * @param string $schema_id
 */
function hxse_cache_map_remove( string $schema_id ): void {
	$map = hxse_cache_map_get();
	unset( $map[ $schema_id ] );
	update_option( 'hxse_cache_map', $map, false );
}

/**
 * マッピングに存在しない孤立JSONファイルの一覧を返す。
 *
 * @return array ファイルパスの配列
 */
function hxse_cache_orphan_files(): array {
	$dir   = hxse_cache_dir();
	$files = is_dir( $dir ) ? glob( $dir . '/*.json' ) : array();
	$files = $files ? $files : array();
	$map   = hxse_cache_map_get();

	$mapped_files = array_values( $map );

	return array_filter( $files, function( $path ) use ( $mapped_files ) {
		return ! in_array( basename( $path ), $mapped_files, true );
	} );
}

/**
 * 孤立JSONファイルを全て削除する。
 */
function hxse_cache_delete_orphans(): void {
	foreach ( hxse_cache_orphan_files() as $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $path );
	}
}
