<?php
/**
 * Hlavička webu.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'mndgroup' ); ?></a>

<header class="site-header">
	<div class="site-header__inner">
		<?php mndgroup_site_logo(); ?>
		<?php mndgroup_language_switcher(); ?>
	</div>
</header>
