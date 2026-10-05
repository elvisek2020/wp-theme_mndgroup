<?php
/**
 * Google Analytics 4 s cookie lištou (nahrazuje MonsterInsights).
 *
 * Bez vyplněného ID měření (Vzhled → Přizpůsobit → MND Group) se nenačte nic – žádný skript ani lišta.
 * Consent Mode v2: výchozí stav „denied“, skript Googlu se stáhne až po souhlasu návštěvníka.
 * Přihlášení uživatelé se neměří a lištu nevidí. Volbu jde změnit odkazem „Nastavení cookies“ v patičce.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * ID měření GA4 (G-XXXXXXXXXX), jinak prázdný řetězec.
 *
 * @return string
 */
function mnd_ga_id() {
	$id = strtoupper( trim( (string) get_theme_mod( 'mnd_ga_id', '' ) ) );
	$id = (string) apply_filters( 'mnd_ga_id', $id );
	return preg_match( '/^G-[A-Z0-9]+$/', $id ) ? $id : '';
}

/**
 * Měří se na této stránce? Ne bez ID, pro přihlášené, v náhledu Přizpůsobení
 * a když GA vkládá ještě MonsterInsights (měřilo by se dvakrát).
 *
 * @return bool
 */
function mnd_analytics_active() {
	$active = '' !== mnd_ga_id() && ! is_user_logged_in() && ! is_customize_preview() && ! defined( 'MONSTERINSIGHTS_VERSION' );
	return (bool) apply_filters( 'mnd_analytics_active', $active );
}

/**
 * Výchozí stav souhlasu – co nejdřív v <head>, před jakýmkoli dalším skriptem.
 * Konfigurace GA a samotný gtag.js přijdou až po souhlasu (assets/js/consent.js).
 */
function mnd_analytics_head() {
	if ( ! mnd_analytics_active() ) {
		return;
	}
	wp_print_inline_script_tag(
		"window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}\n" .
		"gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'});",
		array( 'id' => 'mnd-consent-default' )
	);
}
add_action( 'wp_head', 'mnd_analytics_head', 2 );

/**
 * Skript lišty.
 */
function mnd_analytics_enqueue() {
	if ( ! mnd_analytics_active() ) {
		return;
	}
	wp_enqueue_script(
		'mnd-consent',
		MND_URI . '/assets/js/consent.js',
		array(),
		mnd_asset_version( 'assets/js/consent.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'mnd_analytics_enqueue' );

/**
 * Cookie lišta (skrytá, zobrazí ji skript, dokud návštěvník nerozhodne).
 */
function mnd_analytics_bar() {
	if ( ! mnd_analytics_active() ) {
		return;
	}
	$privacy = get_privacy_policy_url();
	?>
	<div id="mnd-consent" class="mnd-consent" role="region" aria-label="<?php esc_attr_e( 'Cookie consent', 'mndgroup' ); ?>" data-ga="<?php echo esc_attr( mnd_ga_id() ); ?>" hidden>
		<p class="mnd-consent__text">
			<?php esc_html_e( 'We use Google Analytics to measure how visitors use this website. Analytics cookies are stored only with your consent.', 'mndgroup' ); ?>
			<?php if ( $privacy ) : ?>
				<a href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'Privacy policy', 'mndgroup' ); ?></a>
			<?php endif; ?>
		</p>
		<div class="mnd-consent__actions">
			<button type="button" class="mnd-consent__button" data-mnd-consent="denied"><?php esc_html_e( 'Decline', 'mndgroup' ); ?></button>
			<button type="button" class="mnd-consent__button mnd-consent__button--accept" data-mnd-consent="granted"><?php esc_html_e( 'Accept', 'mndgroup' ); ?></button>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'mnd_analytics_bar', 5 );

/**
 * Odkaz „Nastavení cookies“ do patičky – znovu otevře lištu.
 */
function mnd_consent_link() {
	if ( mnd_analytics_active() ) {
		printf( '<span class="mnd-footer__consent"> <span aria-hidden="true">·</span> <button type="button" data-mnd-consent-open>%s</button></span>', esc_html__( 'Cookie settings', 'mndgroup' ) );
	}
}
add_action( 'mnd_footer_info', 'mnd_consent_link' );

/**
 * Jednorázové převzetí ID měření z MonsterInsights – po jeho vypnutí se měří dál bez dalšího nastavování.
 * Když ID později někdo smaže, znovu se nedoplní.
 */
function mnd_analytics_migrate() {
	if ( get_theme_mod( 'mnd_ga_migrated' ) || '' !== mnd_ga_id() ) {
		return;
	}
	$profile = get_option( 'monsterinsights_site_profile' );
	$id      = is_array( $profile ) && ! empty( $profile['v4'] ) ? strtoupper( (string) $profile['v4'] ) : '';
	if ( preg_match( '/^G-[A-Z0-9]+$/', $id ) ) {
		set_theme_mod( 'mnd_ga_id', $id );
		set_theme_mod( 'mnd_ga_migrated', 1 );
	}
}
add_action( 'admin_init', 'mnd_analytics_migrate' );
