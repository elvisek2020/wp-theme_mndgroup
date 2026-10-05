<?php
/**
 * Zabezpečení: XML-RPC, editace souborů, výčet uživatelů, sitemap, bezpečnostní hlavičky.
 *
 * Nahrazuje zbytek pluginu All-In-One Security (na webu neměl zapnutou žádnou ochranu).
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

/*
 * XML-RPC úplně vypnuté – web ho nepoužívá (ManageWP má vlastní rozhraní) a je to
 * oblíbený cíl útoků hrubou silou. Povolit jde filtrem mndgroup_core_allow_xmlrpc.
 */
if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && ! apply_filters( 'mndgroup_core_allow_xmlrpc', false ) ) {
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( 'XML-RPC is disabled.' );
}
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
remove_action( 'wp_head', 'rsd_link' );

/**
 * Bez hlavičky X-Pingback.
 *
 * @param array $headers HTTP hlavičky.
 * @return array
 */
function mndgroup_core_remove_pingback_header( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
}
add_filter( 'wp_headers', 'mndgroup_core_remove_pingback_header' );

// Editor souborů šablon a pluginů v administraci (změny kódu jen přes Git/vydání).
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// Verze WordPressu se nevypisuje.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/**
 * Výčet uživatelů pro nepřihlášené: ?author=N a archivy autorů vrací 404
 * (web archivy autorů nepoužívá, prozrazovaly by uživatelská jména).
 */
function mndgroup_core_block_author_enumeration() {
	if ( is_user_logged_in() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( is_author() || isset( $_GET['author'] ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'mndgroup_core_block_author_enumeration', 1 );

/**
 * REST API: seznam uživatelů jen pro přihlášené (editor ho potřebuje pro výběr autora).
 *
 * @param array $endpoints Registrované cesty.
 * @return array
 */
function mndgroup_core_rest_users( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'mndgroup_core_rest_users' );

/**
 * oEmbed bez jména a odkazu na autora.
 *
 * @param array $data Data odpovědi.
 * @return array
 */
function mndgroup_core_oembed_author( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
}
add_filter( 'oembed_response_data', 'mndgroup_core_oembed_author' );

/**
 * Sitemap bez uživatelů.
 *
 * @param WP_Sitemaps_Provider|false $provider Poskytovatel.
 * @param string                     $name     Název.
 * @return WP_Sitemaps_Provider|false
 */
function mndgroup_core_sitemap_providers( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'mndgroup_core_sitemap_providers', 10, 2 );

/**
 * Bezpečnostní hlavičky na webu. Administrace a přihlášení posílají X-Frame-Options samy.
 * Úprava filtrem mndgroup_core_security_headers (např. vypnutí HSTS).
 */
function mndgroup_core_security_headers() {
	if ( headers_sent() ) {
		return;
	}

	$headers = array(
		'X-Content-Type-Options' => 'nosniff',
		'X-Frame-Options'        => 'SAMEORIGIN',
		'Referrer-Policy'        => 'strict-origin-when-cross-origin',
		'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), browsing-topics=()',
	);
	if ( is_ssl() ) {
		$headers['Strict-Transport-Security'] = 'max-age=31536000';
	}

	foreach ( (array) apply_filters( 'mndgroup_core_security_headers', $headers ) as $name => $value ) {
		if ( $value ) {
			header( $name . ': ' . $value );
		}
	}
}
add_action( 'send_headers', 'mndgroup_core_security_headers' );
