<?php
/**
 * HXSE tableテンプレート（行部分）
 * display: 'table' のときに使用。
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<tr class="hxse-table-row">
	<td class="hxse-table-cell hxse-table-cell--date">
		<?php echo esc_html( get_the_date() ); ?>
	</td>
	<td class="hxse-table-cell hxse-table-cell--title">
		<a href="<?php the_permalink(); ?>" class="hxse-table-link">
			<?php echo esc_html( get_the_title() ); ?>
		</a>
	</td>
	<td class="hxse-table-cell hxse-table-cell--cat">
		<?php
		$hxse_cats = get_the_category();
		if ( $hxse_cats ) {
			$hxse_labels = array_map( function( $c ) { return esc_html( $c->name ); }, $hxse_cats );
			echo implode( '・', $hxse_labels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		}
		?>
	</td>
</tr>
