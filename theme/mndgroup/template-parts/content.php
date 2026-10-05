<?php
/**
 * Příspěvek – v detailu celý obsah, ve výpisu nadpis a perex.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'mnd-entry' ); ?>>
	<header class="mnd-entry__header">
		<?php
		if ( is_singular() ) {
			the_title( '<h1 class="mnd-entry__title">', '</h1>' );
		} else {
			the_title( '<h2 class="mnd-entry__title"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' );
		}
		?>
		<p class="mnd-entry__meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
		</p>
	</header>

	<div class="mnd-entry__content">
		<?php
		if ( is_singular() ) {
			the_content();
		} else {
			the_excerpt();
		}
		?>
	</div>
</article>
