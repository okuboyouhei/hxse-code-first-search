<?php
/**
 * HXSE API/RSS/XMLモード デフォルトテンプレート（v1.8.0+）
 *
 * 利用可能な変数:
 * $hxse_api_data … 表示対象のアイテム配列（ページネーション適用済み）
 * $hxse_schema   … スキーマ定義
 *
 * RSS/XMLの正規化キー（title / link / description / pubDate）を優先して表示し、
 * 一致しない場合はアイテム直下のスカラー値を定義リストで表示する。
 * テーマで上書きする場合: your-theme/hxse/api.php（または schema の template で任意名を指定）
 *
 * @package HXSE
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $hxse_api_data ) || ! is_array( $hxse_api_data ) ) {
	echo '<p class="hxse-no-results">' . esc_html__( '表示できる項目がありません。', 'hxse-code-first-search' ) . '</p>';
	return;
}
?>
<ul class="hxse-api-list">
	<?php foreach ( $hxse_api_data as $hxse_item ) : ?>
		<?php
		if ( ! is_array( $hxse_item ) ) {
			continue;
		}
		$hxse_title = isset( $hxse_item['title'] ) ? (string) $hxse_item['title'] : '';
		$hxse_link  = isset( $hxse_item['link'] ) ? (string) $hxse_item['link'] : '';
		$hxse_desc  = isset( $hxse_item['description'] ) ? (string) $hxse_item['description'] : '';
		$hxse_date  = isset( $hxse_item['pubDate'] ) ? (string) $hxse_item['pubDate'] : '';

		$hxse_date_formatted = '';
		if ( $hxse_date ) {
			$hxse_ts = strtotime( $hxse_date );
			if ( $hxse_ts ) {
				$hxse_date_formatted = wp_date( 'Y.m.d', $hxse_ts );
			}
		}
		?>
		<li class="hxse-api-item">
			<?php if ( $hxse_title ) : ?>
				<?php if ( $hxse_link ) : ?>
					<a class="hxse-api-item-title" href="<?php echo esc_url( $hxse_link ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $hxse_title ); ?>
					</a>
				<?php else : ?>
					<span class="hxse-api-item-title"><?php echo esc_html( $hxse_title ); ?></span>
				<?php endif; ?>
				<?php if ( $hxse_date_formatted ) : ?>
					<time class="hxse-api-item-date"><?php echo esc_html( $hxse_date_formatted ); ?></time>
				<?php endif; ?>
				<?php if ( $hxse_desc ) : ?>
					<p class="hxse-api-item-desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $hxse_desc ), 40, '…' ) ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<dl class="hxse-api-item-fields">
					<?php foreach ( $hxse_item as $hxse_field => $hxse_value ) : ?>
						<?php
						if ( is_array( $hxse_value ) || null === $hxse_value ) {
							continue;
						}
						?>
						<dt><?php echo esc_html( (string) $hxse_field ); ?></dt>
						<dd><?php echo esc_html( (string) $hxse_value ); ?></dd>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
