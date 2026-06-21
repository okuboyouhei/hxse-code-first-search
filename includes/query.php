<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * キーワードの表記ゆれを正規化する
 *
 * - カタカナ → ひらがな
 * - 全角英数字・記号 → 半角
 * - 全角スペース → 半角スペース
 * - 大文字 → 小文字
 * - 長音符の正規化
 *
 * @param string $keyword
 * @return string
 */
function hxse_normalize_keyword( $keyword ) {
	if ( ! function_exists( 'mb_convert_kana' ) ) {
		return $keyword;
	}

	// 全角英数字・記号 → 半角、カタカナ → ひらがな、全角スペース → 半角
	$keyword = mb_convert_kana( $keyword, 'KVas', 'UTF-8' );

	// 大文字 → 小文字
	$keyword = mb_strtolower( $keyword, 'UTF-8' );

	// 長音符の正規化（様々なダッシュ・ハイフンをひらがなの長音符「ー」に統一）
	$keyword = str_replace(
		array( '－', '—', '‐', '−', '-' ),
		'ー',
		$keyword
	);

	// 連続スペースを1つに
	$keyword = preg_replace( '/\s+/u', ' ', $keyword );

	return trim( $keyword );
}

/**
 * $_GETパラメータをサニタイズして返す（ショートコードの初期表示用）
 *
 * @return array
 */
function hxse_sanitize_get_params() {
	$sanitized = array();

	foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display/query params; REST API handles auth separately
		$key = sanitize_key( $key );

		if ( is_array( $value ) ) {
			$sanitized[ $key ] = array_map( 'sanitize_text_field', wp_unslash( $value ) );
		} else {
			$sanitized[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
		}
	}

	return $sanitized;
}

/**
 * フィルターパラメータからWP_Queryの引数を組み立てる
 *
 * @param array  $schema
 * @param array  $params  サニタイズ済みのパラメータ
 * @param int    $page    現在のページ番号
 * @return array WP_Query引数
 */
function hxse_build_query_args( $schema, $params, $page = 1 ) {
	$pagination = $schema['pagination'];
	$per_page   = isset( $pagination['per_page'] ) ? absint( $pagination['per_page'] ) : 12;
	$prefix     = isset( $schema['url_params']['prefix'] ) ? sanitize_key( $schema['url_params']['prefix'] ) : '';

	$args = array(
		'post_type'      => sanitize_key( $schema['post_type'] ),
		'posts_per_page' => $per_page,
		'paged'          => max( 1, absint( $page ) ),
		'post_status'    => 'publish',
	);

	// ソート
	$sort_key = hxse_get_param( $params, 'sort', $prefix );
	if ( $sort_key ) {
		$args = array_merge( $args, hxse_build_orderby( sanitize_key( $sort_key ) ) );
	} elseif ( ! empty( $schema['sort'] ) ) {
		$first_sort = reset( $schema['sort'] );
		$args       = array_merge( $args, hxse_build_orderby( sanitize_key( $first_sort['key'] ) ) );
	}

	$tax_query  = array();
	$meta_query = array();

	// conditionsの処理（固定絞り込み条件）
	$conditions = isset( $schema['conditions'] ) ? $schema['conditions'] : array();

	// tabsの処理: active_tabパラメータに応じてconditionsを切り替え
	if ( ! empty( $schema['tabs'] ) ) {
		$active_tab = hxse_get_param( $params, 'tab', $prefix );
		$active_tab = $active_tab !== '' ? absint( $active_tab ) : 0;
		if ( isset( $schema['tabs'][ $active_tab ]['conditions'] ) ) {
			$conditions = $schema['tabs'][ $active_tab ]['conditions'];
		}
	}

	// conditionsをtax_query/meta_queryに適用
	foreach ( $conditions as $condition ) {
		$ctype = isset( $condition['type'] ) ? sanitize_key( $condition['type'] ) : '';

		if ( 'taxonomy' === $ctype ) {
			$taxonomy = isset( $condition['taxonomy'] ) ? sanitize_key( $condition['taxonomy'] ) : '';
			$terms    = isset( $condition['terms'] ) ? (array) $condition['terms'] : array();
			if ( $taxonomy && ! empty( $terms ) ) {
				// termsの値が数値のみならterm_id、文字列が含まれるならslugで検索
				$has_string = false;
				foreach ( $terms as $t ) {
					if ( ! is_numeric( $t ) ) {
						$has_string = true;
						break;
					}
				}
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => $has_string ? 'slug' : 'term_id',
					'terms'    => $has_string
						? array_map( 'sanitize_key', $terms )
						: array_map( 'absint', $terms ),
					'operator' => 'IN',
				);
			}
		} elseif ( 'meta' === $ctype ) {
			$meta_key = isset( $condition['meta_key'] ) ? sanitize_key( $condition['meta_key'] ) : '';
			$value    = isset( $condition['value'] ) ? sanitize_text_field( (string) $condition['value'] ) : '';
			$compare  = isset( $condition['compare'] ) ? sanitize_text_field( $condition['compare'] ) : '=';
			if ( $meta_key && $value !== '' ) {
				$meta_query[] = array(
					'key'     => $meta_key,
					'value'   => $value,
					'compare' => $compare,
				);
			}
		}
	}

	// filtersの処理
	foreach ( $schema['filters'] as $filter ) {
		if ( empty( $filter['key'] ) || empty( $filter['type'] ) ) {
			continue;
		}

		$key   = sanitize_key( $filter['key'] );
		$type  = sanitize_key( $filter['type'] );
		$value = hxse_get_param( $params, $key, $prefix );

		if ( '' === $value || array() === $value ) {
			continue;
		}

		switch ( $type ) {
			case 'search':
				$keyword = is_array( $value ) ? '' : (string) $value;
				if ( empty( $keyword ) ) break;

				// 表記ゆれ正規化
				if ( ! empty( $filter['normalize'] ) ) {
					$keyword = hxse_normalize_keyword( $keyword );
				}

				$search_fields = isset( $filter['search_fields'] ) && is_array( $filter['search_fields'] )
					? $filter['search_fields']
					: array();

				if ( empty( $search_fields ) ) {
					// デフォルト: WordPressの標準全文検索
					$args['s'] = $keyword;
				} else {
					// search_fieldsが指定された場合: タイトル・本文・メタをOR検索
					$or_query = array( 'relation' => 'OR' );

					foreach ( $search_fields as $field ) {
						$field = sanitize_key( $field );

						if ( 'post_title' === $field || 'post_content' === $field || 'post_excerpt' === $field ) {
							$args['_hxse_content_search'] = $keyword;
							continue;
						}

						// カスタムフィールド（メタキー）
						$or_query[] = array(
							'key'     => $field,
							'value'   => $keyword,
							'compare' => 'LIKE',
						);
					}

					// タイトル・本文・抜粋の検索が含まれる場合はsパラメータも使う
					if ( isset( $args['_hxse_content_search'] ) ) {
						$args['s'] = $keyword;
						unset( $args['_hxse_content_search'] );
					}

					// カスタムフィールド検索がある場合
					if ( count( $or_query ) > 1 ) {
						if ( isset( $args['s'] ) ) {
							$args['_hxse_meta_search'] = $or_query;
						} else {
							$meta_query = array_merge( $meta_query, array( $or_query ) );
						}
					}
				}
				break;

			case 'taxonomy':
				$taxonomy = isset( $filter['taxonomy'] ) ? sanitize_key( $filter['taxonomy'] ) : '';
				if ( ! $taxonomy ) break;

				$terms = is_array( $value )
					? array_map( 'absint', $value )
					: array( absint( $value ) );

				if ( ! empty( $terms ) ) {
					$tax_query[] = array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $terms,
						'operator' => 'IN',
					);
				}
				break;

			case 'meta':
				$meta_key = isset( $filter['meta_key'] ) ? sanitize_key( $filter['meta_key'] ) : '';
				if ( ! $meta_key ) break;

				$ui = isset( $filter['ui'] ) ? sanitize_key( $filter['ui'] ) : 'select';

				if ( 'range' === $ui ) {
					$min = hxse_get_param( $params, $key . '_min', $prefix );
					$max = hxse_get_param( $params, $key . '_max', $prefix );

					if ( '' !== $min && '' !== $max ) {
						$meta_query[] = array(
							'key'     => $meta_key,
							'value'   => array( (float) $min, (float) $max ),
							'type'    => 'NUMERIC',
							'compare' => 'BETWEEN',
						);
					}
				} else {
					$values = is_array( $value ) ? $value : (string) $value;

					$meta_query[] = array(
						'key'     => $meta_key,
						'value'   => $values,
						'compare' => is_array( $values ) ? 'IN' : '=',
					);
				}
				break;

			case 'date':
				$year = absint( $value );
				if ( $year ) {
					$args['date_query'] = array(
						array( 'year' => $year ),
					);
				}
				break;

			case 'relation':
				$relation_meta_key = isset( $filter['meta_key'] ) ? sanitize_key( $filter['meta_key'] ) : '';
				if ( ! $relation_meta_key ) break;

				$relation_values = is_array( $value )
					? array_map( 'absint', $value )
					: array( absint( $value ) );

				if ( ! empty( $relation_values ) ) {
					$meta_query[] = array(
						'key'     => $relation_meta_key,
						'value'   => $relation_values,
						'compare' => 'IN',
					);
				}
				break;
		}
	}

	if ( ! empty( $tax_query ) ) {
		$tax_query['relation'] = 'AND';
		$args['tax_query']     = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	if ( ! empty( $meta_query ) ) {
		$meta_query['relation'] = 'AND';
		$args['meta_query']     = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	return $args;
}

/**
 * ソートキーからorderby引数を返す
 *
 * @param string $sort_key
 * @return array
 */
function hxse_build_orderby( $sort_key ) {
	$map = array(
		'date_desc'  => array( 'orderby' => 'date',       'order' => 'DESC' ),
		'date_asc'   => array( 'orderby' => 'date',       'order' => 'ASC' ),
		'title_asc'  => array( 'orderby' => 'title',      'order' => 'ASC' ),
		'title_desc' => array( 'orderby' => 'title',      'order' => 'DESC' ),
		'menu_order' => array( 'orderby' => 'menu_order', 'order' => 'ASC' ),
	);

	return isset( $map[ $sort_key ] ) ? $map[ $sort_key ] : array( 'orderby' => 'date', 'order' => 'DESC' );
}

/**
 * プレフィックス付きでパラメータを取得する
 *
 * @param array  $params
 * @param string $key
 * @param string $prefix
 * @return mixed
 */
function hxse_get_param( $params, $key, $prefix = '' ) {
	$full_key = $prefix ? $prefix . '_' . $key : $key;
	return isset( $params[ $full_key ] ) ? $params[ $full_key ] : '';
}

/**
 * sパラメータとmeta_queryを組み合わせてOR検索を実現する
 * _hxse_meta_searchが設定されている場合に動作する
 */
add_filter( 'posts_search', 'hxse_extend_search_query', 10, 2 );

/**
 * @param string   $search
 * @param WP_Query $query
 * @return string
 */
function hxse_extend_search_query( $search, $query ) {
	if ( empty( $query->query_vars['_hxse_meta_search'] ) ) {
		return $search;
	}

	global $wpdb;

	$meta_query_obj = new WP_Meta_Query( $query->query_vars['_hxse_meta_search'] );
	$meta_sql       = $meta_query_obj->get_sql( 'post', $wpdb->posts, 'ID', $query );

	if ( empty( $meta_sql['where'] ) ) {
		return $search;
	}

	if ( ! empty( $search ) ) {
		$search = preg_replace( '/^\s*AND\s*/i', '', $search );
		$search = " AND ( {$search} OR 1=1{$meta_sql['where']} )";
	}

	return $search;
}

/**
 * _hxse_meta_search用のJOINを追加する
 */
add_filter( 'posts_join', 'hxse_extend_search_join', 10, 2 );

/**
 * @param string   $join
 * @param WP_Query $query
 * @return string
 */
function hxse_extend_search_join( $join, $query ) {
	if ( empty( $query->query_vars['_hxse_meta_search'] ) ) {
		return $join;
	}

	global $wpdb;

	$meta_query_obj = new WP_Meta_Query( $query->query_vars['_hxse_meta_search'] );
	$meta_sql       = $meta_query_obj->get_sql( 'post', $wpdb->posts, 'ID', $query );

	if ( ! empty( $meta_sql['join'] ) ) {
		$join .= $meta_sql['join'];
	}

	return $join;
}

/**
 * 外部APIからデータをフェッチする（source: 'api' モード）
 *
 * @param array $schema
 * @return array|WP_Error
 */
function hxse_fetch_api_data( $schema ) {
	$endpoint   = isset( $schema['endpoint'] ) ? esc_url_raw( $schema['endpoint'] ) : '';
	$schema_id  = isset( $schema['id'] ) ? $schema['id'] : '';
	$source     = isset( $schema['source'] ) ? sanitize_key( $schema['source'] ) : 'api';
	$cache_mode = isset( $schema['cache_mode'] ) ? sanitize_key( $schema['cache_mode'] ) : 'transient';
	$cache_ttl  = isset( $schema['cache'] ) ? absint( $schema['cache'] ) : 60;
	$cache_file = isset( $schema['cache_file'] ) ? $schema['cache_file'] : '';

	if ( ! $endpoint ) {
		return new WP_Error( 'hxse_api_no_endpoint', 'endpoint が指定されていません。' );
	}

	// --- 静的JSONキャッシュモード ---
	if ( 'static' === $cache_mode ) {
		$filename = $cache_file ? $cache_file : sanitize_file_name( $schema_id ) . '.json';
		$cached   = hxse_load_static_cache( $schema_id, $filename, $cache_ttl );
		if ( null !== $cached ) {
			return $cached;
		}

		$data = hxse_do_remote_fetch( $schema, $endpoint, $source );
		if ( ! is_wp_error( $data ) ) {
			hxse_save_static_cache( $schema_id, $filename, $data );
		}
		return $data;
	}

	// --- transientキャッシュモード（デフォルト） ---
	$cache_key = 'hxse_api_' . md5( $endpoint . $source );

	if ( $cache_ttl > 0 ) {
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
	}

	$data = hxse_do_remote_fetch( $schema, $endpoint, $source );

	if ( ! is_wp_error( $data ) && $cache_ttl > 0 ) {
		set_transient( $cache_key, $data, $cache_ttl );
	}

	return $data;
}

/**
 * エンドポイントをフェッチしてsourceに応じてパースする（内部処理）。
 *
 * @param array  $schema
 * @param string $endpoint
 * @param string $source  'api' | 'rss' | 'xml'
 * @return array|WP_Error
 */
function hxse_do_remote_fetch( array $schema, string $endpoint, string $source = 'api' ) {
	// トークンをGETパラメータに付与
	if ( ! empty( $schema['token'] ) ) {
		$endpoint = add_query_arg( '_token', sanitize_text_field( $schema['token'] ), $endpoint );
	}

	$response = wp_remote_get( $endpoint, array( 'timeout' => 10 ) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== (int) $code ) {
		return new WP_Error( 'hxse_api_error', 'レスポンスエラー: ' . $code );
	}

	$body = wp_remote_retrieve_body( $response );

	// RSSモード
	if ( 'rss' === $source ) {
		return hxse_parse_rss( $body );
	}

	// 汎用XMLモード
	if ( 'xml' === $source ) {
		$xpath = isset( $schema['xpath'] ) ? $schema['xpath'] : '//item';
		return hxse_parse_xml( $body, $xpath );
	}

	// JSONモード（デフォルト）
	$data = json_decode( $body, true );
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		return new WP_Error( 'hxse_api_json_error', 'JSONのパースに失敗しました。' );
	}

	return $data;
}

/**
 * RSS/AtomフィードをパースしてPHP配列に変換する。
 *
 * @param string $body XMLボディ
 * @return array|WP_Error
 */
function hxse_parse_rss( string $body ) {
	libxml_use_internal_errors( true );
	$xml = simplexml_load_string( $body );

	if ( false === $xml ) {
		return new WP_Error( 'hxse_rss_parse_error', 'RSSのパースに失敗しました。' );
	}

	$items = array();

	// RSS 2.0
	if ( isset( $xml->channel->item ) ) {
		foreach ( $xml->channel->item as $item ) {
			$items[] = array(
				'title'       => (string) $item->title,
				'link'        => (string) $item->link,
				'description' => (string) $item->description,
				'pubDate'     => (string) $item->pubDate,
				'guid'        => (string) $item->guid,
			);
		}
		return $items;
	}

	// Atom
	$ns = $xml->getNamespaces( true );
	if ( isset( $ns[''] ) && strpos( $ns[''], 'atom' ) !== false || $xml->getName() === 'feed' ) {
		foreach ( $xml->entry as $entry ) {
			$link = '';
			foreach ( $entry->link as $l ) {
				$attrs = $l->attributes();
				if ( ! isset( $attrs['rel'] ) || 'alternate' === (string) $attrs['rel'] ) {
					$link = (string) $attrs['href'];
					break;
				}
			}
			$items[] = array(
				'title'       => (string) $entry->title,
				'link'        => $link,
				'description' => (string) $entry->summary,
				'pubDate'     => (string) $entry->updated,
				'guid'        => (string) $entry->id,
			);
		}
		return $items;
	}

	return new WP_Error( 'hxse_rss_unknown_format', '未知のフィード形式です。' );
}

/**
 * 汎用XMLをxpathでパースしてPHP配列に変換する。
 *
 * @param string $body  XMLボディ
 * @param string $xpath 繰り返し要素のxpath（例: '//item'）
 * @return array|WP_Error
 */
function hxse_parse_xml( string $body, string $xpath ) {
	libxml_use_internal_errors( true );
	$xml = simplexml_load_string( $body );

	if ( false === $xml ) {
		return new WP_Error( 'hxse_xml_parse_error', 'XMLのパースに失敗しました。' );
	}

	$nodes = $xml->xpath( $xpath );
	if ( false === $nodes || empty( $nodes ) ) {
		return array();
	}

	$items = array();
	foreach ( $nodes as $node ) {
		// SimpleXMLElementをネストした配列に変換
		$items[] = hxse_simplexml_to_array( $node );
	}

	return $items;
}

/**
 * SimpleXMLElementを再帰的にPHP配列に変換する。
 *
 * @param \SimpleXMLElement $xml
 * @return array
 */
function hxse_simplexml_to_array( \SimpleXMLElement $xml ): array {
	$result = array();

	// 属性
	foreach ( $xml->attributes() as $key => $value ) {
		$result[ '@' . $key ] = (string) $value;
	}

	// 子要素
	foreach ( $xml->children() as $key => $child ) {
		$child_array = hxse_simplexml_to_array( $child );
		if ( isset( $result[ $key ] ) ) {
			if ( ! is_array( $result[ $key ] ) || ! isset( $result[ $key ][0] ) ) {
				$result[ $key ] = array( $result[ $key ] );
			}
			$result[ $key ][] = $child_array;
		} else {
			$result[ $key ] = $child_array;
		}
	}

	// テキストノード
	$text = trim( (string) $xml );
	if ( $text !== '' && empty( $result ) ) {
		return $text;
	}
	if ( $text !== '' ) {
		$result['_text'] = $text;
	}

	return $result;
}

/**
 * 後方互換：hxse_do_api_requestの旧名称エイリアス
 *
 * @param array  $schema
 * @param string $endpoint
 * @return array|WP_Error
 */
function hxse_do_api_request( array $schema, string $endpoint ) {
	return hxse_do_remote_fetch( $schema, $endpoint, 'api' );
}
