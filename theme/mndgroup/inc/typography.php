<?php
/**
 * Česká typografie – pevné mezery, aby se řádek nezalomil na nevhodném místě (nahrazuje Zalomení).
 *
 * - za jednopísmennými předložkami k, s, v, z („v České republice“)
 * - mezi číslem a jednotkou („500 mil.“, „10 %“)
 * - v číslech s mezerou („1 000“), v měřítku („1 : 1000“) a za řadovou číslovkou („5. května“)
 *
 * Jen na webu a jen v češtině (podle Polylangu), nikdy uvnitř značek, kódu ani skriptů.
 * Dokud je aktivní plugin Zalomení, modul nic nedělá.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Upravovat text na této stránce?
 *
 * @return bool
 */
function mnd_typography_enabled() {
	if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || class_exists( 'Zalomeni' ) ) {
		return false;
	}
	$locale = function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'locale' ) : '';
	if ( '' === $locale ) {
		$locale = get_locale();
	}
	return (bool) apply_filters( 'mnd_typography_enabled', 0 === strpos( $locale, 'cs' ) );
}

/**
 * Vzory a náhrady (regulární výrazy sestavené jednou).
 *
 * @return array [vzor => náhrada]
 */
function mnd_typography_rules() {
	static $rules = null;
	if ( null !== $rules ) {
		return $rules;
	}

	$words = (array) apply_filters( 'mnd_typography_words', array( 'k', 's', 'v', 'z' ) );
	$units = (array) apply_filters( 'mnd_typography_units', array( 'm', 'm²', 'm³', 'm3', 'km', 'l', 'kg', 't', 'h', '°C', '%', 'Kč', '€', 'tis.', 'mil.', 'mld.', 'MW', 'MWh', 'GWh', 'TWh', 'let', 'lidí', 'dní' ) );
	$quote = function ( $item ) {
		return preg_quote( (string) $item, '/' );
	};

	// Před slovem: začátek textu, mezera, závorka, uvozovka nebo pevná mezera (i jako entita).
	// Ne za apostrofem (MND&#8217;s) – to není předložka.
	$before = '(?<=^|[\s(\[„“"\x{00A0}]|&nbsp;|&#160;|&#8222;|&#8220;|&bdquo;|&ldquo;)';
	$rules  = array(
		'/' . $before . '(' . implode( '|', array_map( $quote, $words ) ) . ') +/iu' => '$1&nbsp;',
		// Delší jednotky napřed (m² před m), za jednotkou konec slova.
		'/(\d) +(' . implode( '|', array_map( $quote, mnd_typography_sort( $units ) ) ) . ')(?=$|[\s.,;:!?)<&\x{00A0}])/u' => '$1&nbsp;$2',
		'/(\d) : (?=\d)/u'                     => '$1&nbsp;:&nbsp;',
		'/(\d) +(?=\d)/u'                      => '$1&nbsp;',
		'/(\d\.) +(?=[0-9a-záčďéěíňóřšťúůýž])/u' => '$1&nbsp;',
	);
	return $rules;
}

/**
 * Seřadí výrazy od nejdelšího (aby alternativa v regulárním výrazu vzala celou jednotku).
 *
 * @param array $items Výrazy.
 * @return array
 */
function mnd_typography_sort( $items ) {
	usort(
		$items,
		function ( $a, $b ) {
			return mb_strlen( (string) $b ) - mb_strlen( (string) $a );
		}
	);
	return $items;
}

/**
 * Doplní pevné mezery do textu mimo HTML značky a mimo pre/code/kbd/script/style/textarea.
 *
 * @param string $text HTML nebo prostý text.
 * @return string
 */
function mnd_typography( $text ) {
	if ( ! is_string( $text ) || false === strpos( $text, ' ' ) || ! mnd_typography_enabled() ) {
		return $text;
	}
	$rules = mnd_typography_rules();
	$parts = wp_html_split( $text );
	$skip  = 0;
	foreach ( $parts as $i => $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( '<' === $part[0] ) {
			if ( preg_match( '#^<(/?)(pre|code|kbd|samp|script|style|textarea)\b#i', $part, $tag ) ) {
				$skip = max( 0, $skip + ( $tag[1] ? -1 : 1 ) );
			}
			continue;
		}
		if ( ! $skip ) {
			$fixed       = preg_replace( array_keys( $rules ), array_values( $rules ), $part );
			$parts[ $i ] = null === $fixed ? $part : $fixed; // chyba regulárního výrazu (neplatné UTF-8) = text beze změny
		}
	}
	return implode( '', $parts );
}

/**
 * Napojení na výstup obsahu. Priorita 20 = až po blocích, odstavcích a shortcodech.
 */
function mnd_typography_filters() {
	$filters = (array) apply_filters( 'mnd_typography_filters', array( 'the_content', 'the_excerpt', 'the_title', 'widget_title', 'widget_text_content', 'widget_block_content', 'term_description' ) );
	foreach ( $filters as $filter ) {
		add_filter( $filter, 'mnd_typography', 20 );
	}
}
add_action( 'init', 'mnd_typography_filters' );
