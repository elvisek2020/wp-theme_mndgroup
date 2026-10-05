<?php
/**
 * Základní nastavení šablony: podpora funkcí WordPressu, menu, widgety.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nastavení šablony po jejím načtení.
 */
function mndgroup_setup() {
	load_theme_textdomain( 'mndgroup', MNDGROUP_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 140,
			'width'       => 230,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// Editor (klasický i blokový) používá stejné písmo, proměnné a barvy jako web.
	// Paleta a velikosti písma pro blokový editor jsou v theme.json.
	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'assets/css/fonts.css', 'assets/css/tokens.css', 'assets/css/editor.css' ) );

	// Umístění menu ponechává původní identifikátor, aby se přiřazení po přepnutí šablony zachovalo.
	register_nav_menus(
		array(
			'primary' => __( 'Business areas (tiles)', 'mndgroup' ),
		)
	);
}
add_action( 'after_setup_theme', 'mndgroup_setup' );

/**
 * Šířka obsahu pro vložená média.
 */
function mndgroup_content_width() {
	$GLOBALS['content_width'] = 1170;
}
add_action( 'after_setup_theme', 'mndgroup_content_width', 0 );

/**
 * Oblast widgetů v patičce (stejné ID jako v původní šabloně – widget s adresou zůstane).
 */
function mndgroup_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Footer – contact', 'mndgroup' ),
			'id'            => 'sidebar-footer',
			'description'   => __( 'Address shown next to the map in the footer. With Polylang, add one widget per language.', 'mndgroup' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'mndgroup_widgets_init' );

/**
 * Úklid widgetů po přepnutí šablony.
 *
 * Původní šablona měla nepoužívanou postranní lištu s výchozími widgety
 * (Hledat, Archivy, Rubriky…). WordPress je při přepnutí přesune do patičky –
 * tady je vrátíme mezi neaktivní widgety, v patičce zůstane jen adresa.
 */
function mndgroup_cleanup_footer_widgets() {
	$sidebars = wp_get_sidebars_widgets();
	if ( empty( $sidebars['sidebar-footer'] ) ) {
		return;
	}

	$defaults = array( 'search', 'recent-posts', 'recent-comments', 'archives', 'categories', 'meta', 'calendar', 'pages', 'tag_cloud', 'rss' );
	$keep     = array();
	foreach ( $sidebars['sidebar-footer'] as $widget_id ) {
		if ( in_array( preg_replace( '/-\d+$/', '', $widget_id ), $defaults, true ) ) {
			$sidebars['wp_inactive_widgets'][] = $widget_id;
		} else {
			$keep[] = $widget_id;
		}
	}

	if ( count( $keep ) !== count( $sidebars['sidebar-footer'] ) ) {
		$sidebars['sidebar-footer'] = $keep;
		wp_set_sidebars_widgets( $sidebars );
	}
}
// Priorita 20 = až po mapování widgetů, které při přepnutí šablony dělá WordPress.
add_action( 'after_switch_theme', 'mndgroup_cleanup_footer_widgets', 20 );
