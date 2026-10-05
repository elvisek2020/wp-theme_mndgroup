<?php
/**
 * MND Group – funkce šablony.
 *
 * Funkce jsou rozdělené do modulů v inc/. Modul se vypne zakomentováním
 * řádku v seznamu níže. Bezpečnost, administrace a údržba jsou v pluginu
 * mndgroup-core (aby přežily případnou výměnu šablony).
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

define( 'MNDGROUP_DIR', get_template_directory() );
define( 'MNDGROUP_URI', get_template_directory_uri() );
define( 'MNDGROUP_VERSION', (string) wp_get_theme( get_template() )->get( 'Version' ) ); // jediný zdroj verze je style.css

$mndgroup_modules = array(
	'setup',         // podpora funkcí WordPressu, menu, widgety, úklid widgetů po aktivaci
	'assets',        // CSS/JS, přednačtení písem, Google Analytics (volitelně)
	'cleanup',       // emoji, zbytečné odkazy v hlavičce
	'post-types',    // typ obsahu „slide“ – bannery
	'navigation',    // dlaždice: ikony a rozbalovací tlačítka
	'customizer',    // Vzhled → Přizpůsobit → MND Group
	'template-tags', // logo, přepínač jazyků, copyright
	'polylang',      // překládané slidy, převzetí menu po aktivaci
	'updater',       // aktualizace šablony z GitHub Releases
	'plugin-check',  // upozornění, když chybí plugin mndgroup-core
);

foreach ( $mndgroup_modules as $mndgroup_module ) {
	require MNDGROUP_DIR . '/inc/' . $mndgroup_module . '.php';
}
unset( $mndgroup_modules, $mndgroup_module );
