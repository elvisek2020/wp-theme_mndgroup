<?php
/**
 * Aktualizace pluginu z GitHub Releases a automatické aktualizace.
 *
 * Plugin i šablona se vydávají společně (stejná verze, jeden tag). WordPress se
 * díky hlavičce „Update URI“ neptá WordPress.org, ale tohoto filtru, a ten přečte
 * update.json z posledního vydání (vytváří ho .github/workflows/release.yml).
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

define( 'MNDGROUP_CORE_UPDATE_JSON', 'https://github.com/' . MNDGROUP_CORE_REPO . '/releases/latest/download/update.json' );

/**
 * Na lokálním vývoji se aktualizace nehledají (plugin je připojený z repozitáře).
 * Pro test lze v wp-config.php nastavit define( 'MNDGROUP_UPDATER_ON_LOCAL', true ).
 *
 * @return bool
 */
function mndgroup_core_updater_enabled() {
	return 'local' !== wp_get_environment_type() || ( defined( 'MNDGROUP_UPDATER_ON_LOCAL' ) && MNDGROUP_UPDATER_ON_LOCAL );
}

/**
 * Poslední vydání pluginu (cache: úspěch 6 h, chyba 1 h).
 *
 * @param bool $force Načíst bez cache.
 * @return array|null [version, package, url, requires, requires_php]
 */
function mndgroup_core_latest_release( $force = false ) {
	$cached = $force ? false : get_site_transient( 'mndgroup_core_release' );
	if ( false !== $cached ) {
		return is_array( $cached ) ? $cached : null;
	}

	$release  = null;
	$response = wp_remote_get(
		MNDGROUP_CORE_UPDATE_JSON,
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$package = isset( $data['plugin']['package'] ) ? (string) $data['plugin']['package'] : '';

		if (
			isset( $data['version'] )
			&& preg_match( '/^\d+(\.\d+){0,3}$/', (string) $data['version'] )
			&& 0 === strpos( $package, 'https://github.com/' . MNDGROUP_CORE_REPO . '/releases/download/' )
		) {
			$release = array(
				'version'      => (string) $data['version'],
				'package'      => $package,
				'url'          => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : 'https://github.com/' . MNDGROUP_CORE_REPO . '/releases',
				'requires'     => isset( $data['requires'] ) ? (string) $data['requires'] : '',
				'requires_php' => isset( $data['requires_php'] ) ? (string) $data['requires_php'] : '',
			);
		}
	}

	set_site_transient( 'mndgroup_core_release', $release ? $release : 0, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );

	return $release;
}

/**
 * Odpověď pro kontrolu aktualizací pluginů (filtr update_plugins_{hostname}).
 *
 * @param array|false $update      Data aktualizace.
 * @param array       $plugin_data Hlavičky pluginu.
 * @param string      $plugin_file Soubor pluginu (složka/soubor.php).
 * @return array|false
 */
function mndgroup_core_check_update( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( MNDGROUP_CORE_FILE ) !== $plugin_file || ! mndgroup_core_updater_enabled() ) {
		return $update;
	}

	$force   = ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$release = mndgroup_core_latest_release( $force );
	if ( ! $release ) {
		return $update;
	}

	return array(
		'slug'         => dirname( $plugin_file ),
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => $release['requires'],
		'requires_php' => $release['requires_php'],
	);
}
add_filter( 'update_plugins_github.com', 'mndgroup_core_check_update', 10, 3 );

/**
 * Okno „Zobrazit podrobnosti“ u aktualizace pluginu.
 *
 * @param false|object|array $result Výsledek.
 * @param string             $action Typ dotazu.
 * @param object             $args   Parametry.
 * @return false|object|array
 */
function mndgroup_core_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( plugin_basename( MNDGROUP_CORE_FILE ) ) !== $args->slug ) {
		return $result;
	}

	$release = mndgroup_core_latest_release();
	$repo    = 'https://github.com/' . MNDGROUP_CORE_REPO;

	return (object) array(
		'name'          => 'MND Group – jádro webu',
		'slug'          => $args->slug,
		'version'       => $release ? $release['version'] : MNDGROUP_CORE_VERSION,
		'author'        => 'elvisek.cz',
		'homepage'      => $repo,
		'requires'      => $release ? $release['requires'] : '',
		'requires_php'  => $release ? $release['requires_php'] : '',
		'download_link' => $release ? $release['package'] : '',
		'sections'      => array(
			'description' => esc_html__( 'Doprovodný plugin šablony MND Group: ochrana přihlášení, zabezpečení, vypnuté komentáře, zjednodušená administrace, údržba webu a aktualizace z GitHubu.', 'mndgroup-core' ),
			'changelog'   => sprintf(
				'<p><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>',
				esc_url( $release ? $release['url'] : $repo . '/releases' ),
				esc_html__( 'Seznam změn na GitHubu', 'mndgroup-core' )
			),
		),
	);
}
add_filter( 'plugins_api', 'mndgroup_core_plugins_api', 10, 3 );

/**
 * Po aktualizaci zapomenout uložené vydání.
 */
function mndgroup_core_clear_release_cache() {
	delete_site_transient( 'mndgroup_core_release' );
}
add_action( 'upgrader_process_complete', 'mndgroup_core_clear_release_cache' );

/**
 * Aktivace pluginu: zapnout automatické aktualizace pluginu i šablony MND Group.
 * Vypnout je jde standardně v přehledu pluginů a šablon.
 */
function mndgroup_core_activate() {
	$plugins = (array) get_site_option( 'auto_update_plugins', array() );
	$plugin  = plugin_basename( MNDGROUP_CORE_FILE );
	if ( ! in_array( $plugin, $plugins, true ) ) {
		$plugins[] = $plugin;
		update_site_option( 'auto_update_plugins', $plugins );
	}

	$themes = (array) get_site_option( 'auto_update_themes', array() );
	if ( wp_get_theme( 'mndgroup' )->exists() && ! in_array( 'mndgroup', $themes, true ) ) {
		$themes[] = 'mndgroup';
		update_site_option( 'auto_update_themes', $themes );
	}
}
