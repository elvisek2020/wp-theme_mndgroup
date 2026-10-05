<?php
/**
 * Odinstalace pluginu: smazat nastavení, záznam přihlášení, blokace, cache a metadata pluginu.
 * Karanténa (složka mndgroup-karantena a tabulky mndq_*) zůstává – smazat ji jde v Údržbě webu.
 *
 * @package MNDGroupCore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'mnd_core_settings' );
delete_option( 'mnd_core_login_log' );
delete_option( 'mnd_core_last_maintenance' );
delete_transient( 'mnd_core_health_db' );
delete_transient( 'mnd_core_health' );
delete_metadata( 'user', 0, 'mnd_core_last_login', '', true );
delete_metadata( 'post', 0, '_mnd_core_webp_fail', '', true );
delete_site_transient( 'mnd_core_release' );
delete_metadata( 'user', 0, 'mnd_core_full_menu', '', true ); // přepínač menu z verze 2.1

$mnd_core_like = $wpdb->esc_like( '_transient_mnd_core_' ) . '%';
$mnd_core_like_timeout = $wpdb->esc_like( '_transient_timeout_mnd_core_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $mnd_core_like, $mnd_core_like_timeout ) );
