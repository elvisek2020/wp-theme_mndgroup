<?php
/**
 * Aktualizace šablony z GitHub Releases (bez pluginu).
 *
 * Jak to funguje: WordPress se při běžné kontrole aktualizací (2× denně / v adminu)
 * zeptá GitHubu na poslední Release repozitáře. Když je jeho tag vyšší než Version
 * ve style.css a Release obsahuje soubor mndgroup.zip, nabídne se aktualizace
 * v Nástěnka → Aktualizace jako u každé jiné šablony (instaluje se kliknutím).
 *
 * Soukromé repo: do wp-config.php přidat define( 'MND_GITHUB_TOKEN', 'github_pat_…' ); (jen čtení obsahu).
 * Na lokálním vývoji je vypnuto (pro test define( 'MND_UPDATER_ON_LOCAL', true )).
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

const MND_UPDATE_REPO  = 'elvisek2020/wp-theme_mndgroup';
const MND_UPDATE_ASSET = 'mndgroup.zip';
const MND_UPDATE_CACHE = 'mnd_theme_release';

if ( 'local' === wp_get_environment_type() && ! ( defined( 'MND_UPDATER_ON_LOCAL' ) && MND_UPDATER_ON_LOCAL ) ) {
	return;
}

/**
 * Poslední release z GitHubu (cache: úspěch 6 h, chyba 1 h).
 *
 * @return array|null [version, url, package]
 */
function mnd_update_latest_release() {
	$cached = get_site_transient( MND_UPDATE_CACHE );
	if ( is_array( $cached ) ) {
		return $cached ? $cached : null;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . MND_UPDATE_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => mnd_update_headers( 'application/vnd.github+json' ),
		)
	);

	$release = array();
	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		foreach ( (array) ( isset( $data['assets'] ) ? $data['assets'] : array() ) as $asset ) {
			if ( isset( $asset['name'] ) && MND_UPDATE_ASSET === $asset['name'] ) {
				$release = array(
					'version' => ltrim( (string) $data['tag_name'], 'vV' ),
					'url'     => (string) $data['html_url'],
					// Soukromé repo stahuje přes API URL assetu, veřejné přes přímý odkaz.
					'package' => defined( 'MND_GITHUB_TOKEN' ) ? (string) $asset['url'] : (string) $asset['browser_download_url'],
				);
				break;
			}
		}
	}

	set_site_transient( MND_UPDATE_CACHE, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release ? $release : null;
}

/**
 * Hlavičky pro GitHub API.
 *
 * @param string $accept Hlavička Accept.
 * @return array
 */
function mnd_update_headers( $accept ) {
	$headers = array(
		'Accept'     => $accept,
		'User-Agent' => 'mndgroup-theme-updater',
	);
	if ( defined( 'MND_GITHUB_TOKEN' ) && MND_GITHUB_TOKEN ) {
		$headers['Authorization'] = 'Bearer ' . MND_GITHUB_TOKEN;
	}
	return $headers;
}

/**
 * Nabídnout aktualizaci.
 *
 * @param object $transient Data aktualizací šablon.
 * @return object
 */
function mnd_update_offer( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}
	$slug    = basename( MND_DIR );
	$theme   = wp_get_theme( $slug );
	$release = mnd_update_latest_release();
	if ( ! $release || ! $theme->exists() ) {
		return $transient;
	}
	$item = array(
		'theme'        => $slug,
		'new_version'  => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => $theme->get( 'RequiresWP' ),
		'requires_php' => $theme->get( 'RequiresPHP' ),
	);
	if ( version_compare( $release['version'], $theme->get( 'Version' ), '>' ) ) {
		$transient->response[ $slug ] = $item;
	} else {
		$transient->no_update[ $slug ] = $item;
	}
	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'mnd_update_offer' );

/**
 * Soukromé repo: stažení assetu přes API potřebuje token a Accept: octet-stream.
 *
 * @param array  $args Parametry požadavku.
 * @param string $url  Adresa.
 * @return array
 */
function mnd_update_download_args( $args, $url ) {
	if ( defined( 'MND_GITHUB_TOKEN' ) && 0 === strpos( $url, 'https://api.github.com/repos/' . MND_UPDATE_REPO . '/releases/assets/' ) ) {
		$args['headers'] = array_merge( (array) ( isset( $args['headers'] ) ? $args['headers'] : array() ), mnd_update_headers( 'application/octet-stream' ) );
	}
	return $args;
}
add_filter( 'http_request_args', 'mnd_update_download_args', 10, 2 );

/**
 * Tlačítko „Zkontrolovat znovu“ v Aktualizacích smaže i naši cache.
 */
function mnd_update_force_check() {
	if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		delete_site_transient( MND_UPDATE_CACHE );
	}
}
add_action( 'load-update-core.php', 'mnd_update_force_check' );
