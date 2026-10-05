<?php
/**
 * Bezpečnostní hlavičky a sitemap bez uživatelů.
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sitemap bez uživatelů.
 *
 * @param WP_Sitemaps_Provider|false $provider Poskytovatel.
 * @param string                     $name     Název.
 * @return WP_Sitemaps_Provider|false
 */
function mnd_core_sitemap_providers( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'mnd_core_sitemap_providers', 10, 2 );

/**
 * Bezpečnostní hlavičky na webu. Administrace a přihlášení posílají X-Frame-Options samy.
 * Úprava filtrem mnd_core_security_headers (např. vypnutí HSTS).
 */
function mnd_core_security_headers() {
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

	foreach ( (array) apply_filters( 'mnd_core_security_headers', $headers ) as $name => $value ) {
		if ( $value ) {
			header( $name . ': ' . $value );
		}
	}
}
add_action( 'send_headers', 'mnd_core_security_headers' );
