<?php
/**
 * SEO: meta description, Open Graph, Twitter karta a strukturovaná data JSON-LD (nahrazuje Yoast SEO a podobné).
 *
 * Popis stránky se bere z jejího stručného výpisu (Stránky → Upravit → Stručný výpis), jinak z textu.
 * Jazykové verze řeší Polylang (odkazy hreflang vypisuje sám), tady se doplní og:locale.
 * Když je aktivní SEO plugin, modul nic nevypisuje – meta značky by byly dvakrát.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Je aktivní SEO plugin, který vypisuje totéž?
 *
 * @return bool
 */
function mnd_seo_plugin_active() {
	$active = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
	return (bool) apply_filters( 'mnd_seo_plugin_active', $active );
}

/**
 * Stručný výpis i u stránek – slouží jako meta description.
 */
function mnd_seo_page_excerpt() {
	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'init', 'mnd_seo_page_excerpt' );

/**
 * Text bez HTML a bloků, zkrácený na celé slovo.
 *
 * @param string $text   Text.
 * @param int    $length Maximální délka ve znacích.
 * @return string
 */
function mnd_seo_clean( $text, $length = 160 ) {
	$text = wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( (string) $text ) ) );
	$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
	if ( mb_strlen( $text ) > $length ) {
		$text = preg_replace( '/\s+\S*$/u', '', mb_substr( $text, 0, $length - 1 ) ) . '…';
	}
	return $text;
}

/**
 * Locale aktuálního jazyka (cs_CZ, en_GB…).
 *
 * @return string
 */
function mnd_seo_locale() {
	$locale = function_exists( 'pll_current_language' ) ? pll_current_language( 'locale' ) : '';
	return $locale ? (string) $locale : get_locale();
}

/**
 * Data pro meta značky aktuální stránky.
 *
 * @return array [title, description, url, type, image => [url, width, height], locale, alternate]
 */
function mnd_seo_data() {
	$object = get_queried_object();
	$title  = wp_get_document_title();
	$text   = '';
	$url    = home_url( user_trailingslashit( (string) $GLOBALS['wp']->request ) );

	if ( is_front_page() ) {
		$title = get_bloginfo( 'name' );
	} elseif ( is_singular() ) {
		$title = single_post_title( '', false );
	}

	if ( is_singular() && $object instanceof WP_Post ) {
		$text = has_excerpt( $object ) ? $object->post_excerpt : $object->post_content;
		$url  = (string) wp_get_canonical_url( $object );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = term_description();
	} elseif ( is_post_type_archive() ) {
		$text = get_the_post_type_description();
	}
	if ( '' === mnd_seo_clean( $text ) ) {
		$text = get_bloginfo( 'description' );
	}
	if ( '' === trim( $text ) && get_option( 'page_on_front' ) ) {
		// Polylang vrací úvodní stránku v aktuálním jazyce.
		$text = get_post_field( 'post_content', (int) get_option( 'page_on_front' ) );
	}

	$image = array(
		'url'    => MND_URI . '/assets/img/og-image.png',
		'width'  => 1200,
		'height' => 630,
	);
	if ( is_singular() && has_post_thumbnail( $object ) ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id( $object ), 'large' );
		if ( $src ) {
			$image = array(
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
			);
		}
	}

	$locale    = mnd_seo_locale();
	$alternate = function_exists( 'pll_languages_list' ) ? array_diff( (array) pll_languages_list( array( 'fields' => 'locale' ) ), array( $locale ) ) : array();

	$data = array(
		'title'       => html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ),
		'description' => mnd_seo_clean( $text ),
		'url'         => $url,
		'type'        => is_singular( 'post' ) ? 'article' : 'website',
		'image'       => $image,
		'locale'      => $locale,
		'alternate'   => array_values( $alternate ),
	);
	return (array) apply_filters( 'mnd_seo_data', $data );
}

/**
 * Meta description, Open Graph a Twitter karta.
 */
function mnd_seo_meta() {
	if ( mnd_seo_plugin_active() || is_404() || is_search() ) {
		return;
	}
	$d    = mnd_seo_data();
	$meta = array();

	if ( $d['description'] ) {
		$meta[] = array( 'name', 'description', $d['description'] );
	}
	$meta[] = array( 'property', 'og:type', $d['type'] );
	$meta[] = array( 'property', 'og:site_name', html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) );
	$meta[] = array( 'property', 'og:title', $d['title'] );
	if ( $d['description'] ) {
		$meta[] = array( 'property', 'og:description', $d['description'] );
	}
	$meta[] = array( 'property', 'og:url', $d['url'] );
	$meta[] = array( 'property', 'og:locale', $d['locale'] );
	foreach ( $d['alternate'] as $alternate ) {
		$meta[] = array( 'property', 'og:locale:alternate', $alternate );
	}
	$meta[] = array( 'property', 'og:image', $d['image']['url'] );
	$meta[] = array( 'property', 'og:image:width', $d['image']['width'] );
	$meta[] = array( 'property', 'og:image:height', $d['image']['height'] );
	if ( 'article' === $d['type'] ) {
		$meta[] = array( 'property', 'article:published_time', get_the_date( DATE_W3C ) );
		$meta[] = array( 'property', 'article:modified_time', get_the_modified_date( DATE_W3C ) );
	}
	// Titulek, popis a obrázek si X (Twitter) vezme z Open Graph.
	$meta[] = array( 'name', 'twitter:card', 'summary_large_image' );

	foreach ( $meta as $tag ) {
		printf( '<meta %s="%s" content="%s">' . "\n", $tag[0], esc_attr( $tag[1] ), esc_attr( (string) $tag[2] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- $tag[0] je pevně name/property
	}
}
add_action( 'wp_head', 'mnd_seo_meta', 3 );

/**
 * Strukturovaná data: organizace a web na úvodní stránce, článek u příspěvků.
 */
function mnd_seo_json_ld() {
	if ( mnd_seo_plugin_active() ) {
		return;
	}
	$root     = trailingslashit( (string) get_option( 'home' ) ); // kořen webu – organizace je jedna pro všechny jazyky
	$home     = home_url( '/' ); // úvodní stránka aktuálního jazyka (Polylang)
	$language = str_replace( '_', '-', mnd_seo_locale() );
	$org      = array(
		'@type' => 'Organization',
		'@id'   => $root . '#organization',
		'name'  => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ),
		'url'   => $root,
		'logo'  => array(
			'@type'  => 'ImageObject',
			'url'    => MND_URI . '/assets/img/logo.png',
			'width'  => 460,
			'height' => 280,
		),
	);
	$graph    = array();

	if ( is_front_page() ) {
		$graph[] = $org;
		$graph[] = array(
			'@type'      => 'WebSite',
			'@id'        => $home . '#website',
			'url'        => $home,
			'name'       => $org['name'],
			'inLanguage' => $language,
			'publisher'  => array( '@id' => $org['@id'] ),
		);
	} elseif ( is_singular( 'post' ) ) {
		$d       = mnd_seo_data();
		$graph[] = array(
			'@type'            => 'BlogPosting',
			'headline'         => $d['title'],
			'description'      => $d['description'],
			'url'              => $d['url'],
			'mainEntityOfPage' => $d['url'],
			'image'            => $d['image']['url'],
			'datePublished'    => get_the_date( DATE_W3C ),
			'dateModified'     => get_the_modified_date( DATE_W3C ),
			'inLanguage'       => $language,
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', get_queried_object_id() ) ),
			),
			'publisher'        => $org,
		);
	}

	$graph = (array) apply_filters( 'mnd_seo_json_ld', $graph );
	if ( ! $graph ) {
		return;
	}
	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
	);
	echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON s JSON_HEX_TAG
}
add_action( 'wp_head', 'mnd_seo_json_ld', 4 );
