<?php
/**
 * Dlaždice oblastí podnikání s rozbalovacím seznamem společností (menu „primary“).
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

if ( ! has_nav_menu( 'primary' ) ) {
	return;
}

wp_nav_menu(
	array(
		'theme_location'       => 'primary',
		'container'            => 'nav',
		'container_class'      => 'mnd-segments',
		'container_aria_label' => __( 'MND Group companies', 'mndgroup' ),
		'menu_class'           => 'mnd-segments__list',
		'menu_id'              => 'mnd-segments-menu',
		'depth'                => 2,
		'fallback_cb'          => false,
	)
);
