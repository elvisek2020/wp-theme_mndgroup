<?php
/**
 * Integrace s Polylangem (čeština / angličtina).
 *
 * Šablona funguje i bez Polylangu – přepínač jazyků se pak jen nezobrazí.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slidy jsou překládaný typ obsahu i přesto, že nemají veřejné stránky.
 *
 * @param string[] $post_types  Překládané typy obsahu.
 * @param bool     $is_settings Volání z nastavení Polylangu.
 * @return string[]
 */
function mnd_pll_post_types( $post_types, $is_settings ) {
	if ( ! $is_settings ) {
		$post_types['slide'] = 'slide';
	}
	return $post_types;
}
add_filter( 'pll_get_post_types', 'mnd_pll_post_types', 10, 2 );

/**
 * Po přepnutí šablony převezme přiřazení menu pro jednotlivé jazyky (doplní chybějící).
 *
 * Polylang si přiřazení menu ukládá zvlášť pro každou šablonu, takže by po
 * aktivaci nové šablony menu „zmizelo“ a muselo se ručně přiřadit znovu.
 *
 * @param string   $old_name  Název předchozí šablony.
 * @param WP_Theme $old_theme Předchozí šablona.
 */
function mnd_pll_migrate_menus( $old_name, $old_theme = null ) {
	if ( ! function_exists( 'PLL' ) || ! $old_theme instanceof WP_Theme ) {
		return;
	}

	$old_slug = $old_theme->get_stylesheet();
	$new_slug = get_stylesheet();
	$options  = PLL()->options;
	$menus    = isset( $options['nav_menus'] ) ? (array) $options['nav_menus'] : array();

	if ( empty( $menus[ $old_slug ] ) || ! is_array( $menus[ $old_slug ] ) ) {
		return;
	}

	// Doplnit chybějící nebo prázdná přiřazení po jazycích. WordPress při přepnutí šablony
	// (i přes administraci) někdy stihne uložit jen menu aktuálního jazyka – ostatní by zůstaly prázdné.
	$new     = isset( $menus[ $new_slug ] ) && is_array( $menus[ $new_slug ] ) ? $menus[ $new_slug ] : array();
	$changed = false;
	foreach ( $menus[ $old_slug ] as $location => $languages ) {
		foreach ( (array) $languages as $lang => $menu_id ) {
			if ( $menu_id && empty( $new[ $location ][ $lang ] ) ) {
				$new[ $location ][ $lang ] = (int) $menu_id;
				$changed                   = true;
			}
		}
	}
	if ( ! $changed ) {
		return;
	}
	$menus[ $new_slug ] = $new;

	if ( is_object( $options ) && method_exists( $options, 'set' ) ) {
		// Polylang 3.7+.
		$options->set( 'nav_menus', $menus );
	} else {
		$raw              = get_option( 'polylang', array() );
		$raw['nav_menus'] = $menus;
		update_option( 'polylang', $raw );
	}
}
// Priorita 20 = až po mapování menu, které při přepnutí šablony dělá WordPress.
add_action( 'after_switch_theme', 'mnd_pll_migrate_menus', 20, 2 );

/**
 * Jazyky pro přepínač: [ ['slug','name','url','current','lang'], ... ].
 *
 * @return array
 */
function mnd_languages() {
	if ( ! function_exists( 'pll_the_languages' ) ) {
		return array();
	}

	$languages = pll_the_languages(
		array(
			'raw'           => 1,
			'hide_if_empty' => 0,
		)
	);

	return is_array( $languages ) && count( $languages ) > 1 ? $languages : array();
}
