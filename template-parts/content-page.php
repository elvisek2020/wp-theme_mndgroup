<?php
/**
 * Obsah stránky.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
	<?php if ( ! is_page_template( 'page-homepage.php' ) ) : ?>
		<header class="entry__header">
			<?php the_title( '<h1 class="entry__title">', '</h1>' ); ?>
		</header>
	<?php endif; ?>

	<div class="entry__content">
		<?php
		the_content();
		wp_link_pages(
			array(
				'before' => '<nav class="page-links">',
				'after'  => '</nav>',
			)
		);
		?>
	</div>

	<?php
	edit_post_link(
		/* translators: %s: page title (visible to screen readers only). */
		sprintf( __( 'Edit<span class="screen-reader-text"> “%s”</span>', 'mndgroup' ), get_the_title() ),
		'<p class="entry__edit">',
		'</p>'
	);
	?>
</article>
