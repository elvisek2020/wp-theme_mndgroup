<?php
/**
 * Template Name: Šablona úvodní stránky
 *
 * Název souboru i šablony je stejný jako v původní šabloně, takže úvodní
 * stránky (CZ i EN) po přepnutí šablony nic nastavovat nemusí.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<?php get_template_part( 'template-parts/hero' ); ?>
<?php get_template_part( 'template-parts/segments' ); ?>

<main id="content" class="site-main site-main--home" tabindex="-1">
	<?php
	while ( have_posts() ) :
		the_post();
		get_template_part( 'template-parts/content', 'page' );
	endwhile;
	?>
</main>

<?php
get_footer();
