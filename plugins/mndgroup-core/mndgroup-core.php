<?php
/**
 * Plugin Name: MND Group Core
 * Plugin URI: https://github.com/elvisek2020/wp-theme_mndgroup
 * Description: Funkce webu nezávislé na šabloně — ochrana a log přihlášení, hardening a bezpečnostní hlavičky, WebP, sitemap a /llms.txt, vypnuté komentáře, automatické aktualizace, Údržba webu a Zdraví webu. Nahrazuje 3 pluginy. Vše jde zapnout a vypnout v Nastavení → MND Group Core.
 * Version: 2.4.0
 * Requires at least: 6.5
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * Author: Zdeněk Král (ElvisEK)
 * Update URI: https://github.com/elvisek2020/wp-theme_mndgroup
 * Text Domain: mndgroup-core
 */

defined( 'ABSPATH' ) || exit;

define( 'MND_CORE_FILE', __FILE__ );
define( 'MND_CORE_BASENAME', plugin_basename( __FILE__ ) );
define( 'MND_CORE_REPO', 'elvisek2020/wp-theme_mndgroup' );
$mnd_core_meta = get_file_data( __FILE__, array( 'v' => 'Version', 't' => 'Tested up to' ) );
define( 'MND_CORE_VERSION', $mnd_core_meta['v'] );
define( 'MND_CORE_TESTED_WP', $mnd_core_meta['t'] ? $mnd_core_meta['t'] : '7.1' ); // při ověření nové verze WP zvednout v hlavičce
unset( $mnd_core_meta );

require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/seo-extra.php';
require __DIR__ . '/inc/health.php';
