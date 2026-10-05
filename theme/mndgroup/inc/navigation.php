<?php
/**
 * Menu „Oblasti podnikání“ – dlaždice s ikonami a rozbalovacím seznamem společností.
 *
 * Používá standardní menu WordPressu (Vzhled → Menu), jen doplňuje ikony
 * a tlačítka pro rozbalení na dotykových zařízeních.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ikony dlaždic v pořadí položek menu (stejně jako v původní šabloně).
 * Ikonu lze položce určit i ručně CSS třídou „icon-oil“, „icon-drill“ atd.
 *
 * @return string[]
 */
function mnd_segment_icons() {
	return array( 'oil', 'drill', 'gas', 'fire', 'arrows' );
}

/**
 * Jde o menu s dlaždicemi?
 *
 * @param stdClass|array $args Argumenty wp_nav_menu().
 * @return bool
 */
function mnd_is_segments_menu( $args ) {
	$args = (object) $args;
	return isset( $args->theme_location ) && 'primary' === $args->theme_location;
}

/**
 * Vrátí inline SVG ze složky assets/img.
 *
 * @param string $name Název souboru bez přípony (např. „icons/oil“).
 * @return string
 */
function mnd_svg( $name ) {
	static $cache = array();
	if ( ! isset( $cache[ $name ] ) ) {
		$file           = MND_DIR . '/assets/img/' . $name . '.svg';
		$cache[ $name ] = file_exists( $file ) ? trim( (string) file_get_contents( $file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	return $cache[ $name ];
}

/**
 * Šipka pro rozbalovací tlačítka.
 *
 * @return string
 */
function mnd_chevron() {
	return '<svg class="mnd-chevron" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/**
 * Doplní položkám pořadí mezi hlavními položkami a informaci o podpoložkách.
 *
 * @param WP_Post[] $items Položky menu.
 * @param stdClass  $args  Argumenty menu.
 * @return WP_Post[]
 */
function mnd_nav_menu_objects( $items, $args ) {
	if ( ! mnd_is_segments_menu( $args ) ) {
		return $items;
	}

	$parents = array();
	foreach ( $items as $item ) {
		if ( (int) $item->menu_item_parent ) {
			$parents[ (int) $item->menu_item_parent ] = true;
		}
	}

	$index = 0;
	foreach ( $items as $item ) {
		$item->mnd_has_children = isset( $parents[ (int) $item->ID ] );
		if ( ! (int) $item->menu_item_parent ) {
			$item->mnd_index = $index++;
		}
	}
	return $items;
}
add_filter( 'wp_nav_menu_objects', 'mnd_nav_menu_objects', 10, 2 );

/**
 * Ikona dlaždice podle CSS třídy položky, jinak podle pořadí.
 *
 * @param WP_Post $item Položka menu.
 * @return string
 */
function mnd_segment_icon( $item ) {
	$icons = mnd_segment_icons();
	foreach ( (array) $item->classes as $class ) {
		if ( 0 === strpos( $class, 'icon-' ) && in_array( substr( $class, 5 ), $icons, true ) ) {
			return mnd_svg( 'icons/' . substr( $class, 5 ) );
		}
	}
	$index = isset( $item->mnd_index ) ? (int) $item->mnd_index : 0;
	return mnd_svg( 'icons/' . $icons[ $index % count( $icons ) ] );
}

/**
 * Text hlavní položky doplní o ikonu.
 *
 * @param string   $title Text položky.
 * @param WP_Post  $item  Položka.
 * @param stdClass $args  Argumenty menu.
 * @param int      $depth Úroveň zanoření.
 * @return string
 */
function mnd_nav_item_title( $title, $item, $args, $depth ) {
	if ( ! mnd_is_segments_menu( $args ) || $depth > 0 ) {
		return $title;
	}
	return '<span class="mnd-segments__icon">' . mnd_segment_icon( $item ) . '</span><span class="mnd-segments__label">' . $title . '</span>';
}
add_filter( 'nav_menu_item_title', 'mnd_nav_item_title', 10, 4 );

/**
 * CSS třídy odkazů.
 *
 * @param array    $atts  Atributy odkazu.
 * @param WP_Post  $item  Položka.
 * @param stdClass $args  Argumenty menu.
 * @param int      $depth Úroveň zanoření.
 * @return array
 */
function mnd_nav_link_attributes( $atts, $item, $args, $depth ) {
	if ( mnd_is_segments_menu( $args ) ) {
		$atts['class'] = trim( ( isset( $atts['class'] ) ? $atts['class'] : '' ) . ' ' . ( $depth > 0 ? 'mnd-segments__sublink' : 'mnd-segments__link' ) );
	}
	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'mnd_nav_link_attributes', 10, 4 );

/**
 * CSS třídy položek.
 *
 * @param string[] $classes Třídy.
 * @param WP_Post  $item    Položka.
 * @param stdClass $args    Argumenty menu.
 * @param int      $depth   Úroveň zanoření.
 * @return string[]
 */
function mnd_nav_css_class( $classes, $item, $args, $depth ) {
	if ( mnd_is_segments_menu( $args ) ) {
		$classes[] = $depth > 0 ? 'mnd-segments__subitem' : 'mnd-segments__item';
	}
	return $classes;
}
add_filter( 'nav_menu_css_class', 'mnd_nav_css_class', 10, 4 );

/**
 * CSS třída rozbalovacího seznamu.
 *
 * @param string[] $classes Třídy.
 * @param stdClass $args    Argumenty menu.
 * @return string[]
 */
function mnd_nav_submenu_class( $classes, $args ) {
	if ( mnd_is_segments_menu( $args ) ) {
		$classes[] = 'mnd-segments__submenu';
	}
	return $classes;
}
add_filter( 'nav_menu_submenu_css_class', 'mnd_nav_submenu_class', 10, 2 );

/**
 * Položky s podmenu dostanou tlačítko pro rozbalení.
 *
 * Položka bez vlastního odkazu („#“) se celá změní na tlačítko,
 * položka s odkazem si odkaz ponechá a tlačítko se šipkou se přidá vedle.
 *
 * @param string   $output HTML položky.
 * @param WP_Post  $item   Položka.
 * @param int      $depth  Úroveň zanoření.
 * @param stdClass $args   Argumenty menu.
 * @return string
 */
function mnd_nav_start_el( $output, $item, $depth, $args ) {
	if ( ! mnd_is_segments_menu( $args ) || $depth > 0 || empty( $item->mnd_has_children ) ) {
		return $output;
	}

	if ( in_array( trim( (string) $item->url ), array( '', '#' ), true ) ) {
		$output = preg_replace( '/<a\b[^>]*>/', '<button type="button" class="mnd-segments__link mnd-segments__trigger" aria-expanded="false">', $output, 1 );
		$output = preg_replace( '/<\/a>(?!.*<\/a>)/s', '<span class="mnd-segments__chevron">' . mnd_chevron() . '</span></button>', $output, 1 );
		return $output;
	}

	/* translators: %s: business area name. */
	$label = sprintf( __( 'Show companies: %s', 'mndgroup' ), wp_strip_all_tags( $item->title ) );

	return $output . sprintf(
		'<button type="button" class="mnd-segments__toggle" aria-expanded="false"><span class="screen-reader-text">%s</span>%s</button>',
		esc_html( $label ),
		mnd_chevron()
	);
}
add_filter( 'walker_nav_menu_start_el', 'mnd_nav_start_el', 10, 4 );
