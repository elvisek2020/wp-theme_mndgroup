<?php
/**
 * Odinstalace pluginu: smazat záznam přihlášení, blokace, cache vydání a nastavení uživatelů.
 *
 * @package MNDGroupCore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'mndgroup_core_login_log' );
delete_site_transient( 'mndgroup_core_release' );
delete_metadata( 'user', 0, 'mndgroup_core_full_menu', '', true );

$mndgroup_core_like = $wpdb->esc_like( '_transient_mndgroup_core_' ) . '%';
$mndgroup_core_like_timeout = $wpdb->esc_like( '_transient_timeout_mndgroup_core_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $mndgroup_core_like, $mndgroup_core_like_timeout ) );
