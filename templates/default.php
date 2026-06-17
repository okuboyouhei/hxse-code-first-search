<?php
/**
 * HXSE デフォルトテンプレート
 * $post がグローバルに利用可能。
 * テーマ側でカスタマイズする場合は テーマ/hxse/ にコピーしてください。
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="hxse-post">
	<a href="<?php the_permalink(); ?>" class="hxse-post-link">

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="hxse-post-thumbnail">
				<?php the_post_thumbnail( 'medium' ); ?>
			</div>
		<?php else : ?>
			<div class="hxse-post-thumbnail hxse-post-thumbnail--empty" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
			</div>
		<?php endif; ?>

		<div class="hxse-post-body">
			<h2 class="hxse-post-title"><?php echo esc_html( get_the_title() ); ?></h2>
			<p class="hxse-post-date"><?php echo esc_html( get_the_date() ); ?></p>
			<?php
			$hxse_excerpt = get_the_excerpt();
			if ( $hxse_excerpt ) :
			?>
				<p class="hxse-post-excerpt"><?php echo esc_html( wp_trim_words( $hxse_excerpt, 40 ) ); ?></p>
			<?php endif; ?>
		</div>

	</a>
</article>
