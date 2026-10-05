<?php
/**
 * Odinstalace pluginu: smazat záznam přihlášení, blokace, cache vydání a nastavení uživatelů.
 *
 * @package MNDGroupCore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'mnd_core_login_log' );
delete_option( 'mnd_core_last_maintenance' );
delete_transient( 'mnd_core_health_db' );
delete_site_transient( 'mnd_core_release' );
delete_metadata( 'user', 0, 'mnd_core_full_menu', '', true );

$mnd_core_like = $wpdb->esc_like( '_transient_mnd_core_' ) . '%';
$mnd_core_like_timeout = $wpdb->esc_like( '_transient_timeout_mnd_core_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $mnd_core_like, $mnd_core_like_timeout ) );
