<?php
/**
 * Pomocné funkce pro šablony.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logo webu – vlastní logo z Přizpůsobení, jinak logo MND Group ze šablony.
 */
function mndgroup_site_logo() {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	printf(
		'<a href="%1$s" class="mnd-logo" rel="home"><img src="%2$s" width="115" height="70" alt="%3$s"></a>',
		esc_url( function_exists( 'pll_home_url' ) ? pll_home_url() : home_url( '/' ) ),
		esc_url( MNDGROUP_URI . '/assets/img/logo.svg' ),
		esc_attr( get_bloginfo( 'name', 'display' ) )
	);
}

/**
 * Přepínač jazyků (Polylang).
 */
function mndgroup_language_switcher() {
	$languages = mndgroup_languages();
	if ( ! $languages ) {
		return;
	}
	?>
	<nav class="mnd-lang" aria-label="<?php esc_attr_e( 'Language', 'mndgroup' ); ?>">
		<ul>
			<?php foreach ( $languages as $language ) : ?>
				<li>
					<?php if ( ! empty( $language['current_lang'] ) ) : ?>
						<span class="mnd-lang__current" aria-current="true" title="<?php echo esc_attr( $language['name'] ); ?>"><?php echo esc_html( $language['slug'] ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( $language['url'] ); ?>" hreflang="<?php echo esc_attr( $language['locale'] ? str_replace( '_', '-', $language['locale'] ) : $language['slug'] ); ?>" lang="<?php echo esc_attr( $language['slug'] ); ?>" title="<?php echo esc_attr( $language['name'] ); ?>"><?php echo esc_html( $language['slug'] ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}

/**
 * Text s rokem do patičky.
 */
function mndgroup_copyright() {
	/* translators: %s: current year. */
	$text = sprintf( __( 'Copyright &copy; %s MND', 'mndgroup' ), wp_date( 'Y' ) );
	echo wp_kses_post( apply_filters( 'mndgroup_copyright', $text ) );
}
