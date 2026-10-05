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
function mnd_asset_version( $path ) {
	$file = MND_DIR . '/' . $path;
	return file_exists( $file ) ? (string) filemtime( $file ) : MND_VERSION;
}

/**
 * Načtení stylů a skriptů na webu.
 */
function mnd_enqueue_assets() {
	wp_enqueue_style( 'mnd-main', MND_URI . '/assets/css/main.css', array(), mnd_asset_version( 'assets/css/main.css' ) );
	wp_enqueue_style( 'mnd-print', MND_URI . '/assets/css/print.css', array( 'mnd-main' ), mnd_asset_version( 'assets/css/print.css' ), 'print' );

	wp_enqueue_script(
		'mnd-theme',
		MND_URI . '/assets/js/theme.js',
		array(),
		mnd_asset_version( 'assets/js/theme.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_localize_script(
		'mnd-theme',
		'mndL10n',
		array(
			/* translators: %s: slide number. */
			'goTo'  => __( 'Show slide %s', 'mndgroup' ),
			'pause' => __( 'Pause slideshow', 'mndgroup' ),
			'play'  => __( 'Play slideshow', 'mndgroup' ),
		)
	);

	// Nepotřebné pro návštěvníky.
	wp_dequeue_style( 'classic-theme-styles' );
	if ( ! is_user_logged_in() ) {
		wp_dequeue_style( 'dashicons' );
	}
}
add_action( 'wp_enqueue_scripts', 'mnd_enqueue_assets', 20 );

/**
 * Přednačtení písem (@font-face generuje WordPress z theme.json) – text se vykreslí hned správným fontem.
 */
function mnd_preload_fonts() {
	foreach ( array( 'montserrat-latin-wght-normal', 'montserrat-latin-ext-wght-normal' ) as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( MND_URI . '/assets/fonts/' . $font . '.woff2' )
		);
	}
}
add_action( 'wp_head', 'mnd_preload_fonts', 1 );

/**
 * Barva lišty prohlížeče na mobilu a záložní favicon (pokud není nastavena ikona webu).
 */
function mnd_head_meta() {
	echo '<meta name="theme-color" content="#ffffff">' . "\n";
	if ( ! has_site_icon() ) {
		printf( '<link rel="icon" href="%s" sizes="any">' . "\n", esc_url( MND_URI . '/favicon.ico' ) );
	}
}
add_action( 'wp_head', 'mnd_head_meta', 2 );

/**
 * Google Analytics 4 – jen pokud je v Přizpůsobení vyplněno ID měření.
 * Náhrada pluginu MonsterInsights (vypisoval totéž, jen s mnohem větší režií).
 */
function mnd_google_analytics() {
	$id = get_theme_mod( 'mnd_ga_id', '' );
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
add_action( 'wp_head', 'mnd_google_analytics', 20 );
