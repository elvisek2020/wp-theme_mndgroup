<?php
/**
 * Stránka nenalezena.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="content" class="mnd-main" tabindex="-1">
	<div class="mnd-container mnd-container--narrow mnd-404">
		<p class="mnd-404__code" aria-hidden="true">404</p>
		<h1 class="mnd-entry__title"><?php esc_html_e( 'Page not found', 'mndgroup' ); ?></h1>
		<p><?php esc_html_e( 'The page you are looking for does not exist or has been moved.', 'mndgroup' ); ?></p>
		<p><a class="mnd-button" href="<?php echo esc_url( function_exists( 'pll_home_url' ) ? pll_home_url() : home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the homepage', 'mndgroup' ); ?></a></p>
	</div>
</main>

<?php
get_footer();
