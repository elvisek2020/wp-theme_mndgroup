<?php
/**
 * MND Group — šablona pro www.mndgroup.eu
 *
 * Každý modul v inc/ jde vypnout zakomentováním řádku níže.
 * Bezpečnost, administrace a údržba jsou v pluginu MND Group Core (plugins/mndgroup-core).
 */

defined( 'ABSPATH' ) || exit;

define( 'MND_VERSION', (string) wp_get_theme( get_template() )->get( 'Version' ) );
define( 'MND_DIR', get_template_directory() );
define( 'MND_URI', get_template_directory_uri() );

$mnd_modules = array(
	'setup',         // podpora šablony, menu, widgety, úklid widgetů po aktivaci
	'customizer',    // Vzhled → Přizpůsobit → MND Group (bannery, logo KKCG, GA4 ID)
	'assets',        // CSS/JS, přednačtení písem, Google Analytics (jen s vyplněným ID)
	'cleanup',       // emoji, zbytečné odkazy v <head>
	'template-tags', // logo, přepínač jazyků, copyright
	'post-types',    // typ obsahu „slide“ – bannery
	'navigation',    // dlaždice: ikony a rozbalovací tlačítka
	'polylang',      // překládané slidy, převzetí menu po aktivaci
	'updater',       // aktualizace z GitHub Releases
	'plugin-check',  // upozornění, když chybí plugin MND Group Core
);

foreach ( $mnd_modules as $mnd_module ) {
	require MND_DIR . '/inc/' . $mnd_module . '.php';
}
