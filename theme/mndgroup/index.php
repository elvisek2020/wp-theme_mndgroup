<?php
/**
 * Výchozí šablona (výpisy, archivy, vyhledávání, jednotlivé příspěvky).
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="content" class="mnd-main" tabindex="-1">
	<div class="mnd-container mnd-container--narrow">
		<?php if ( is_archive() || is_search() ) : ?>
			<header class="mnd-page-header">
				<h1 class="mnd-page-header__title">
					<?php
					if ( is_search() ) {
						/* translators: %s: search query. */
						printf( esc_html__( 'Search results for “%s”', 'mndgroup' ), esc_html( get_search_query() ) );
					} else {
						echo wp_kses_post( get_the_archive_title() );
					}
					?>
				</h1>
			</header>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', is_page() ? 'page' : '' );
			endwhile;

			the_posts_pagination();
			?>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing found.', 'mndgroup' ); ?></p>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
