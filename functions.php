<?php
/**
 * MND Group – funkce šablony.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

define( 'MNDGROUP_DIR', get_template_directory() );
define( 'MNDGROUP_VERSION', (string) wp_get_theme( get_template() )->get( 'Version' ) ); // jediný zdroj verze je style.css
define( 'MNDGROUP_URI', get_template_directory_uri() );

require MNDGROUP_DIR . '/inc/setup.php';
require MNDGROUP_DIR . '/inc/assets.php';
require MNDGROUP_DIR . '/inc/cleanup.php';
require MNDGROUP_DIR . '/inc/post-types.php';
require MNDGROUP_DIR . '/inc/navigation.php';
require MNDGROUP_DIR . '/inc/customizer.php';
require MNDGROUP_DIR . '/inc/template-tags.php';
require MNDGROUP_DIR . '/inc/polylang.php';
require MNDGROUP_DIR . '/inc/updater.php';
