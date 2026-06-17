<?php
/**
 * HXSE listテンプレート
 * display: 'list' のときに使用。
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="hxse-post hxse-post--list">
	<a href="<?php the_permalink(); ?>" class="hxse-post-link">

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="hxse-post-thumbnail">
				<?php the_post_thumbnail( 'thumbnail' ); ?>
			</div>
		<?php endif; ?>

		<div class="hxse-post-body">
			<h2 class="hxse-post-title"><?php echo esc_html( get_the_title() ); ?></h2>
			<p class="hxse-post-date"><?php echo esc_html( get_the_date() ); ?></p>
			<?php
			$hxse_excerpt = get_the_excerpt();
			if ( $hxse_excerpt ) :
			?>
				<p class="hxse-post-excerpt"><?php echo esc_html( wp_trim_words( $hxse_excerpt, 60 ) ); ?></p>
			<?php endif; ?>
		</div>

	</a>
</article>
