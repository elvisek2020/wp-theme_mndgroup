<?php
/**
 * Aktualizace šablony z GitHubu.
 *
 * Hlavička „Update URI“ ve style.css říká WordPressu, že šablona není
 * z WordPress.org. Při kontrole aktualizací (2× denně nebo tlačítkem
 * „Zkontrolovat znovu“) se proto zeptá tohoto filtru a ten přečte soubor
 * update.json z posledního vydání v repozitáři. Vydání vytváří GitHub
 * Actions po pushnutí tagu vX.Y.Z (viz .github/workflows/release.yml).
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

define( 'MNDGROUP_REPO', 'elvisek2020/wp-theme_mndgroup' );
define( 'MNDGROUP_UPDATE_JSON', 'https://github.com/' . MNDGROUP_REPO . '/releases/latest/download/update.json' );

/**
 * Na lokálním vývoji se aktualizace nehledají – šablona je tam připojená přímo z repozitáře
 * a aktualizace by ji přepsala. Pro test updateru lze v wp-config.php nastavit
 * define( 'MNDGROUP_UPDATER_ON_LOCAL', true ).
 *
 * @return bool
 */
function mndgroup_updater_enabled() {
	return 'local' !== wp_get_environment_type() || ( defined( 'MNDGROUP_UPDATER_ON_LOCAL' ) && MNDGROUP_UPDATER_ON_LOCAL );
}

/**
 * Informace o posledním vydání (s cache, aby se GitHub nevolal při každém načtení).
 *
 * @param bool $force Načíst znovu bez cache.
 * @return array|null [version, package, url, requires, requires_php] nebo null.
 */
function mndgroup_latest_release( $force = false ) {
	$cached = $force ? false : get_site_transient( 'mndgroup_release' );
	if ( false !== $cached ) {
		return is_array( $cached ) ? $cached : null;
	}

	$release  = null;
	$response = wp_remote_get(
		MNDGROUP_UPDATE_JSON,
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$package = isset( $data['package'] ) ? (string) $data['package'] : '';

		// Balíček se smí stahovat jen z vydání tohoto repozitáře.
		if (
			isset( $data['version'] )
			&& preg_match( '/^\d+(\.\d+){0,3}$/', (string) $data['version'] )
			&& 0 === strpos( $package, 'https://github.com/' . MNDGROUP_REPO . '/releases/download/' )
		) {
			$release = array(
				'version'      => (string) $data['version'],
				'package'      => $package,
				'url'          => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : 'https://github.com/' . MNDGROUP_REPO . '/releases',
				'requires'     => isset( $data['requires'] ) ? (string) $data['requires'] : '',
				'requires_php' => isset( $data['requires_php'] ) ? (string) $data['requires_php'] : '',
			);
		}
	}

	// Úspěch si pamatujeme 6 hodin, chybu (výpadek, limit GitHubu) jen hodinu.
	set_site_transient( 'mndgroup_release', $release ? $release : 0, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );

	return $release;
}

/**
 * Odpověď pro kontrolu aktualizací WordPressu (filtr update_themes_{hostname}, WP 6.1+).
 *
 * @param array|false $update           Data aktualizace.
 * @param array       $theme_data       Hlavičky šablony.
 * @param string      $theme_stylesheet Složka šablony.
 * @return array|false
 */
function mndgroup_check_update( $update, $theme_data, $theme_stylesheet ) {
	if ( basename( MNDGROUP_DIR ) !== $theme_stylesheet || ! mndgroup_updater_enabled() ) {
		return $update;
	}

	// „Zkontrolovat znovu“ v Nástěnka → Aktualizace obejde cache.
	$force   = ! empty( $_GET['force-check'] ) && current_user_can( 'update_themes' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$release = mndgroup_latest_release( $force );
	if ( ! $release ) {
		return $update;
	}

	return array(
		'theme'        => $theme_stylesheet,
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => $release['requires'],
		'requires_php' => $release['requires_php'],
	);
}
add_filter( 'update_themes_github.com', 'mndgroup_check_update', 10, 3 );

/**
 * Po aktualizaci zapomenout uložené vydání.
 */
function mndgroup_clear_release_cache() {
	delete_site_transient( 'mndgroup_release' );
}
add_action( 'upgrader_process_complete', 'mndgroup_clear_release_cache' );

/**
 * Po aktivaci zapnout automatické aktualizace šablony.
 *
 * Jde o běžné nastavení WordPressu – vypnout se dá ve Vzhled → Šablony →
 * detail šablony → „Zakázat automatické aktualizace“.
 */
function mndgroup_enable_auto_updates() {
	$auto = (array) get_site_option( 'auto_update_themes', array() );
	$slug = get_stylesheet();
	if ( ! in_array( $slug, $auto, true ) ) {
		$auto[] = $slug;
		update_site_option( 'auto_update_themes', $auto );
	}
}
add_action( 'after_switch_theme', 'mndgroup_enable_auto_updates' );
