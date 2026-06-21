<?php
/**
 * HXSE マージモード デフォルトテンプレート
 *
 * 利用可能な変数:
 * $hxse_merged_data … 正規化・マージ済みのアイテム配列
 *   各アイテム: title / link / date / excerpt / source / raw
 * $hxse_schema      … スキーマ定義
 *
 * テーマで上書きする場合: your-theme/hxse/merged.php
 *
 * @package HXSE
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $hxse_merged_data ) || ! is_array( $hxse_merged_data ) ) {
	echo '<p class="hxse-no-results">' . esc_html__( '表示できる項目がありません。', 'hxse-code-first-search' ) . '</p>';
	return;
}
?>
<ul class="hxse-merged-list">
	<?php foreach ( $hxse_merged_data as $hxse_item ) : ?>
		<?php
		$hxse_title  = isset( $hxse_item['title'] ) ? $hxse_item['title'] : '';
		$hxse_link   = isset( $hxse_item['link'] ) ? $hxse_item['link'] : '';
		$hxse_source = isset( $hxse_item['source'] ) ? $hxse_item['source'] : '';
		$hxse_date   = isset( $hxse_item['date'] ) ? $hxse_item['date'] : '';

		$hxse_date_formatted = '';
		if ( $hxse_date ) {
			$hxse_ts = strtotime( $hxse_date );
			if ( $hxse_ts ) {
				$hxse_date_formatted = wp_date( 'Y.m.d', $hxse_ts );
			}
		}
		?>
		<li class="hxse-merged-item">
			<a href="<?php echo esc_url( $hxse_link ); ?>" class="hxse-merged-link">
				<div class="hxse-merged-meta">
					<?php if ( $hxse_date_formatted ) : ?>
						<time class="hxse-merged-date"><?php echo esc_html( $hxse_date_formatted ); ?></time>
					<?php endif; ?>
					<?php if ( $hxse_source ) : ?>
						<span class="hxse-merged-badge"><?php echo esc_html( $hxse_source ); ?></span>
					<?php endif; ?>
				</div>
				<span class="hxse-merged-title"><?php echo esc_html( $hxse_title ); ?></span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
