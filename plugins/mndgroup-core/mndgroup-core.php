<?php
/**
 * Plugin Name:       MND Group – jádro webu
 * Plugin URI:        https://github.com/elvisek2020/wp-theme_mndgroup
 * Description:       Doprovodný plugin šablony MND Group: ochrana přihlášení, zabezpečení, vypnuté komentáře, zjednodušená administrace, údržba webu a aktualizace z GitHubu.
 * Version:           2.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Tested up to:      7.1
 * Author:            elvisek.cz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mndgroup-core
 * Update URI:        https://github.com/elvisek2020/wp-theme_mndgroup
 *
 * Funkce, které mají přežít výměnu šablony. Moduly jsou v inc/ a vypínají se
 * zakomentováním řádku v seznamu níže.
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

define( 'MNDGROUP_CORE_FILE', __FILE__ );
define( 'MNDGROUP_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'MNDGROUP_CORE_VERSION', '2.1.0' ); // musí odpovídat hlavičce Version (kontroluje CI)
define( 'MNDGROUP_CORE_REPO', 'elvisek2020/wp-theme_mndgroup' );

$mndgroup_core_modules = array(
	'login-protection', // omezení pokusů o přihlášení, záznam přihlášení
	'hardening',        // XML-RPC, editace souborů, výčet uživatelů, sitemap, bezpečnostní hlavičky
	'comments',         // komentáře a pingbacky úplně vypnuté
	'admin-menu',       // zjednodušené menu administrace a Nástěnky
	'maintenance',      // Nástroje → Údržba webu, widget na Nástěnce
	'updates',          // aktualizace pluginu z GitHub Releases, automatické aktualizace
);

foreach ( $mndgroup_core_modules as $mndgroup_core_module ) {
	require MNDGROUP_CORE_DIR . 'inc/' . $mndgroup_core_module . '.php';
}
unset( $mndgroup_core_modules, $mndgroup_core_module );

register_activation_hook( __FILE__, 'mndgroup_core_activate' );
