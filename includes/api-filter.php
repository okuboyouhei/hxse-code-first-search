<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 外部ソース（api / rss / xml）のフィルタリング・ソート・ページネーション（v1.8.0+）
 *
 * すべてキャッシュ済みデータ（配列）に対するメモリ内処理。
 * 絞り込み操作でリモートへの再フェッチは発生しない。
 *
 * スキーマに filters / sort / pagination のいずれかがある場合のみ有効（opt-in）。
 * いずれもない場合、従来どおり「取得→テンプレートに全件渡す」挙動を維持する。
 */

/**
 * スキーマが対話モード（フィルタ/ソート/ページネーションあり）かどうか
 *
 * @param array $schema
 * @return bool
 */
function hxse_api_is_interactive( $schema ) {
	return ! empty( $schema['filters'] ) || ! empty( $schema['sort'] ) || ! empty( $schema['pagination'] );
}

/**
 * APIレスポンスからアイテムのリストを取り出す
 *
 * items_key（ドット記法対応）でラップされた配列に対応する。
 * 例: connpass APIは { "events": [...] } なので 'items_key' => 'events'
 *
 * @param array|WP_Error $data
 * @param array          $schema
 * @return array 数値添字のアイテム配列（取り出せない場合は空配列）
 */
function hxse_api_extract_items( $data, $schema ) {
	if ( is_wp_error( $data ) || ! is_array( $data ) ) {
		return array();
	}

	if ( ! empty( $schema['items_key'] ) ) {
		$data = hxse_api_item_value_raw( $data, (string) $schema['items_key'] );
		if ( ! is_array( $data ) ) {
			return array();
		}
	}

	// 数値添字のリストであることを確認（PHP 7.4互換のarray_is_list相当）
	$i = 0;
	foreach ( $data as $key => $unused ) {
		if ( $key !== $i ) {
			return array();
		}
		$i++;
	}

	return $data;
}

/**
 * アイテムからフィールド値を取り出す（ドット記法対応、生値）
 *
 * @param mixed  $item
 * @param string $field 例: 'title'、'series.title'
 * @return mixed
 */
function hxse_api_item_value_raw( $item, $field ) {
	$keys = explode( '.', $field );
	foreach ( $keys as $key ) {
		if ( is_array( $item ) && array_key_exists( $key, $item ) ) {
			$item = $item[ $key ];
		} else {
			return null;
		}
	}
	return $item;
}

/**
 * アイテムからフィールド値を文字列として取り出す
 *
 * @param mixed  $item
 * @param string $field
 * @return string
 */
function hxse_api_item_value( $item, $field ) {
	$value = hxse_api_item_value_raw( $item, $field );
	if ( null === $value || is_array( $value ) ) {
		return '';
	}
	return (string) $value;
}

/**
 * 大文字小文字を区別しない部分一致検索（mbstring非搭載環境へのフォールバック付き）
 *
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
function hxse_api_str_contains( $haystack, $needle ) {
	if ( function_exists( 'mb_stripos' ) ) {
		return false !== mb_stripos( $haystack, $needle );
	}
	return false !== stripos( $haystack, $needle );
}

/**
 * アイテム配列にフィルタを適用する
 *
 * 対応フィルタタイプ:
 * - search: search_fields（省略時はアイテム直下の全文字列フィールド）を横断して部分一致
 * - select: field の値と完全一致
 *
 * @param array $items
 * @param array $schema
 * @param array $params サニタイズ済みリクエストパラメータ
 * @return array
 */
function hxse_filter_api_items( $items, $schema, $params ) {
	if ( empty( $schema['filters'] ) || ! is_array( $schema['filters'] ) ) {
		return $items;
	}

	$prefix = isset( $schema['url_params']['prefix'] ) ? sanitize_key( $schema['url_params']['prefix'] ) : '';

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

		if ( 'search' === $type ) {
			$keyword = is_array( $value ) ? '' : trim( (string) $value );
			if ( '' === $keyword ) {
				continue;
			}
			$search_fields = isset( $filter['search_fields'] ) && is_array( $filter['search_fields'] )
				? $filter['search_fields']
				: array();

			$items = array_values( array_filter(
				$items,
				function ( $item ) use ( $keyword, $search_fields ) {
					$targets = $search_fields;
					if ( empty( $targets ) && is_array( $item ) ) {
						// 省略時: アイテム直下の文字列フィールドすべてを対象
						foreach ( $item as $field => $field_value ) {
							if ( is_string( $field_value ) ) {
								$targets[] = $field;
							}
						}
					}
					foreach ( $targets as $field ) {
						$haystack = hxse_api_item_value( $item, (string) $field );
						if ( '' !== $haystack && hxse_api_str_contains( $haystack, $keyword ) ) {
							return true;
						}
					}
					return false;
				}
			) );

		} elseif ( 'select' === $type ) {
			$field = isset( $filter['field'] ) ? (string) $filter['field'] : $key;
			$value = is_array( $value ) ? '' : (string) $value;
			if ( '' === $value ) {
				continue;
			}

			$items = array_values( array_filter(
				$items,
				function ( $item ) use ( $field, $value ) {
					return hxse_api_item_value( $item, $field ) === $value;
				}
			) );
		}
	}

	return $items;
}

/**
 * アイテム配列をソートする
 *
 * sort定義（API/RSS/XMLモード用の拡張キー）:
 * [ 'key' => 'date_desc', 'label' => '新しい順',
 *   'field' => 'pubDate', 'order' => 'desc', 'compare' => 'date' ]
 *
 * compare: 'string'（デフォルト）| 'numeric' | 'date'
 * sortパラメータ未指定時は先頭の定義を使う。
 *
 * @param array $items
 * @param array $schema
 * @param array $params
 * @return array
 */
function hxse_sort_api_items( $items, $schema, $params ) {
	if ( empty( $schema['sort'] ) || ! is_array( $schema['sort'] ) ) {
		return $items;
	}

	$prefix   = isset( $schema['url_params']['prefix'] ) ? sanitize_key( $schema['url_params']['prefix'] ) : '';
	$sort_key = sanitize_key( (string) hxse_get_param( $params, 'sort', $prefix ) );

	$selected = null;
	foreach ( $schema['sort'] as $option ) {
		if ( ! isset( $option['key'] ) ) {
			continue;
		}
		if ( null === $selected ) {
			$selected = $option; // フォールバック: 先頭の定義
		}
		if ( sanitize_key( $option['key'] ) === $sort_key ) {
			$selected = $option;
			break;
		}
	}

	if ( null === $selected || empty( $selected['field'] ) ) {
		return $items;
	}

	$field   = (string) $selected['field'];
	$order   = ( isset( $selected['order'] ) && 'asc' === strtolower( (string) $selected['order'] ) ) ? 1 : -1;
	$compare = isset( $selected['compare'] ) ? sanitize_key( $selected['compare'] ) : 'string';

	usort(
		$items,
		function ( $a, $b ) use ( $field, $order, $compare ) {
			$va = hxse_api_item_value( $a, $field );
			$vb = hxse_api_item_value( $b, $field );

			if ( 'numeric' === $compare ) {
				$result = (float) $va <=> (float) $vb;
			} elseif ( 'date' === $compare ) {
				$result = (int) strtotime( $va ) <=> (int) strtotime( $vb );
			} else {
				$result = strnatcasecmp( $va, $vb );
			}

			return $result * $order;
		}
	);

	return $items;
}

/**
 * select フィルタの選択肢をデータから自動生成する（'options' => 'auto'）
 *
 * @param array  $items
 * @param string $field
 * @return array [ [ 'value' => ..., 'label' => ... ], ... ]（値の自然順）
 */
function hxse_api_auto_options( $items, $field ) {
	$values = array();
	foreach ( $items as $item ) {
		$value = hxse_api_item_value( $item, $field );
		if ( '' !== $value ) {
			$values[ $value ] = true;
		}
	}
	$values = array_keys( $values );
	natcasesort( $values );

	$options = array();
	foreach ( $values as $value ) {
		$options[] = array(
			'value' => $value,
			'label' => $value,
		);
	}
	return $options;
}

/**
 * select フィルタUIを描画する（外部ソース用フィルタタイプ）
 *
 * filters.php の hxse_render_filters から呼ばれる。
 * 'options' には [ ['value'=>'','label'=>''] ] の配列、または 'auto' を指定できる。
 * 'auto' の場合、スキーマの _api_items（描画前にセット済み）から選択肢を自動生成する。
 *
 * @param array  $filter
 * @param string $name
 * @param mixed  $value
 * @param string $ui
 * @param array  $schema
 */
function hxse_render_api_select_filter( $filter, $name, $value, $ui, $schema ) {
	$field   = isset( $filter['field'] ) ? (string) $filter['field'] : sanitize_key( $filter['key'] );
	$options = isset( $filter['options'] ) ? $filter['options'] : 'auto';

	if ( 'auto' === $options ) {
		$items   = isset( $schema['_api_items'] ) && is_array( $schema['_api_items'] ) ? $schema['_api_items'] : array();
		$options = hxse_api_auto_options( $items, $field );
	}

	if ( ! is_array( $options ) || empty( $options ) ) {
		return;
	}

	if ( 'radio' === $ui ) {
		echo '<div class="hxse-radio-group">';
		printf(
			'<label class="hxse-radio-label"><input type="radio" name="%s" value=""%s> %s</label>',
			esc_attr( $name ),
			checked( '', (string) $value, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress checked() output
			esc_html__( 'すべて', 'hxse-code-first-search' )
		);
		foreach ( $options as $option ) {
			if ( ! isset( $option['value'] ) ) {
				continue;
			}
			printf(
				'<label class="hxse-radio-label"><input type="radio" name="%s" value="%s"%s> %s</label>',
				esc_attr( $name ),
				esc_attr( (string) $option['value'] ),
				checked( (string) $option['value'], (string) $value, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress checked() output
				esc_html( isset( $option['label'] ) ? (string) $option['label'] : (string) $option['value'] )
			);
		}
		echo '</div>';
		return;
	}

	// デフォルト: select
	echo '<select name="' . esc_attr( $name ) . '" class="hxse-select">';
	echo '<option value="">' . esc_html__( 'すべて', 'hxse-code-first-search' ) . '</option>';
	foreach ( $options as $option ) {
		if ( ! isset( $option['value'] ) ) {
			continue;
		}
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( (string) $option['value'] ),
			selected( (string) $option['value'], (string) $value, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress selected() output
			esc_html( isset( $option['label'] ) ? (string) $option['label'] : (string) $option['value'] )
		);
	}
	echo '</select>';
}

/**
 * フィルタ・ソート・ページネーション適用済みの結果ページを描画する
 *
 * 件数表示 → 結果（テンプレート） → ページャーの順で出力する。
 * ショートコード初期描画とRESTエンドポイントの両方から呼ばれる。
 *
 * @param array  $schema
 * @param string $hxse_id
 * @param array  $items  フィルタ・ソート適用済みの全アイテム
 * @param int    $page
 */
function hxse_render_api_page( $schema, $hxse_id, $items, $page ) {
	$total       = count( $items );
	$pagination  = isset( $schema['pagination'] ) && is_array( $schema['pagination'] ) ? $schema['pagination'] : array();
	$per_page    = isset( $pagination['per_page'] ) ? absint( $pagination['per_page'] ) : 0;
	$total_pages = ( $per_page > 0 ) ? (int) ceil( $total / $per_page ) : 1;
	$page        = max( 1, min( $page, max( 1, $total_pages ) ) );

	if ( 0 === $total ) {
		echo '<p class="hxse-no-results">' . esc_html__( '該当するデータが見つかりませんでした。', 'hxse-code-first-search' ) . '</p>';
		return;
	}

	// 件数表示
	if ( ! empty( $pagination['show_count'] ) && $per_page > 0 ) {
		hxse_render_count( $pagination, $total, $per_page, $page );
	}

	// 該当ページのアイテムを切り出し
	$page_items = ( $per_page > 0 )
		? array_slice( $items, ( $page - 1 ) * $per_page, $per_page )
		: $items;

	// マージモード（sources）は正規化済みデータ用のmergedテンプレートで描画（v1.9.0+）
	if ( ! empty( $schema['sources'] ) && is_array( $schema['sources'] ) ) {
		hxse_render_merged_results( $schema, $hxse_id, $page_items );
	} else {
		hxse_render_api_results( $schema, $hxse_id, $page_items );
	}

	// ページャー
	if ( $per_page > 0 && ! empty( $pagination['show_pages'] ) && $total_pages > 1 ) {
		hxse_render_pager( $schema, $hxse_id, $pagination, $page, $total_pages );
	}
}
