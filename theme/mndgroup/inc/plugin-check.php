<?php
/**
 * Upozornění v administraci, když chybí doprovodný plugin mndgroup-core.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Výpis upozornění pro administrátory.
 */
function mndgroup_plugin_check_notice() {
	if ( defined( 'MNDGROUP_CORE_VERSION' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$installed = file_exists( WP_PLUGIN_DIR . '/mndgroup-core/mndgroup-core.php' );
	$link      = $installed
		? admin_url( 'plugins.php' )
		: 'https://github.com/' . ( defined( 'MNDGROUP_REPO' ) ? MNDGROUP_REPO : 'elvisek2020/wp-theme_mndgroup' ) . '/releases/latest';

	printf(
		'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s"%4$s>%5$s</a></p></div>',
		esc_html__( 'MND Group:', 'mndgroup' ),
		$installed
			? esc_html__( 'the companion plugin “MND Group – core” is installed but not active. It provides login protection, security hardening and site maintenance.', 'mndgroup' )
			: esc_html__( 'the companion plugin “MND Group – core” is missing. It provides login protection, security hardening and site maintenance.', 'mndgroup' ),
		esc_url( $link ),
		$installed ? '' : ' target="_blank" rel="noopener"',
		$installed ? esc_html__( 'Activate it in Plugins', 'mndgroup' ) : esc_html__( 'Download mndgroup-core.zip', 'mndgroup' )
	);
}
add_action( 'admin_notices', 'mndgroup_plugin_check_notice' );
