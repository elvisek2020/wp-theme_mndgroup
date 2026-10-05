<?php
/**
 * Styly, skripty a písma.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verze souboru podle data změny – prohlížeč po nasazení nové verze nepoužije starou cache.
 *
 * @param string $path Cesta relativní ke složce šablony.
 * @return string
 */
function mndgroup_asset_version( $path ) {
	$file = MNDGROUP_DIR . '/' . $path;
	return file_exists( $file ) ? (string) filemtime( $file ) : MNDGROUP_VERSION;
}

/**
 * Načtení stylů a skriptů na webu.
 */
function mndgroup_enqueue_assets() {
	wp_enqueue_style( 'mndgroup-fonts', MNDGROUP_URI . '/assets/css/fonts.css', array(), mndgroup_asset_version( 'assets/css/fonts.css' ) );
	wp_enqueue_style( 'mndgroup-tokens', MNDGROUP_URI . '/assets/css/tokens.css', array(), mndgroup_asset_version( 'assets/css/tokens.css' ) );
	wp_enqueue_style( 'mndgroup', MNDGROUP_URI . '/assets/css/theme.css', array( 'mndgroup-fonts', 'mndgroup-tokens' ), mndgroup_asset_version( 'assets/css/theme.css' ) );
	wp_enqueue_style( 'mndgroup-print', MNDGROUP_URI . '/assets/css/print.css', array( 'mndgroup' ), mndgroup_asset_version( 'assets/css/print.css' ), 'print' );

	wp_enqueue_script(
		'mndgroup',
		MNDGROUP_URI . '/assets/js/theme.js',
		array(),
		mndgroup_asset_version( 'assets/js/theme.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_localize_script(
		'mndgroup',
		'mndgroupL10n',
		array(
			/* translators: %s: slide number. */
			'goTo'  => __( 'Show slide %s', 'mndgroup' ),
			'pause' => __( 'Pause slideshow', 'mndgroup' ),
			'play'  => __( 'Play slideshow', 'mndgroup' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'mndgroup_enqueue_assets' );

/**
 * Přednačtení písem – text se vykreslí hned správným fontem.
 */
function mndgroup_preload_fonts() {
	foreach ( array( 'montserrat-latin.woff2', 'montserrat-latin-ext.woff2' ) as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( MNDGROUP_URI . '/assets/fonts/' . $font )
		);
	}
}
add_action( 'wp_head', 'mndgroup_preload_fonts', 1 );

/**
 * Barva lišty prohlížeče na mobilu a záložní favicon (pokud není nastavena ikona webu).
 */
function mndgroup_head_meta() {
	echo '<meta name="theme-color" content="#ffffff">' . "\n";
	if ( ! has_site_icon() ) {
		printf( '<link rel="icon" href="%s" sizes="any">' . "\n", esc_url( MNDGROUP_URI . '/favicon.ico' ) );
	}
}
add_action( 'wp_head', 'mndgroup_head_meta', 2 );

/**
 * Google Analytics 4 – jen pokud je v Přizpůsobení vyplněno ID měření.
 * Náhrada pluginu MonsterInsights (vypisoval totéž, jen s mnohem větší režií).
 */
function mndgroup_google_analytics() {
	$id = get_theme_mod( 'mndgroup_ga_id', '' );
	if ( ! $id || ! preg_match( '/^G-[A-Z0-9]+$/', $id ) || is_user_logged_in() ) {
		return;
	}
	?>
	<script async src="<?php echo esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . $id ); ?>"></script>
	<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		gtag('config', <?php echo wp_json_encode( $id ); ?>);
	</script>
	<?php
}
add_action( 'wp_head', 'mndgroup_google_analytics', 20 );
