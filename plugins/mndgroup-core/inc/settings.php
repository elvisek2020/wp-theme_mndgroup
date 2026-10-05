<?php
/**
 * Nastavení → MND Group Core: zapnutí a vypnutí jednotlivých funkcí pluginu.
 *
 * Výchozí hodnoty odpovídají doporučenému nastavení (vše zapnuté), takže po instalaci
 * plugin funguje bez konfigurace. Uloženo v jedné volbě mnd_core_settings.
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

const MND_CORE_SETTINGS      = 'mnd_core_settings';
const MND_CORE_SETTINGS_PAGE = 'mndgroup-core';

/**
 * Výchozí nastavení.
 *
 * @return array
 */
function mnd_core_settings_defaults() {
	return array(
		'login_protection' => 1,
		'login_attempts'   => 5,
		'login_lockout'    => 30,
		'login_log'        => 1,
		'xmlrpc_off'       => 1,
		'file_edit_off'    => 1,
		'hide_users'       => 1,
		'hide_version'     => 1,
		'security_headers' => 1,
		'hsts'             => 1,
		'sitemap_no_users' => 1,
		'sitemap_lastmod'  => 1,
		'llms_txt'         => 1,
		'webp'             => 1,
		'comments_off'     => 1,
		'core_updates'     => 'all',
		'plugin_updates'   => 'all',
		'admin_footer'     => 1,
		'health_widget'    => 1,
	);
}

/**
 * Aktuální nastavení (uložené hodnoty doplněné o výchozí).
 *
 * @return array
 */
function mnd_core_settings() {
	static $settings = null;
	if ( null === $settings ) {
		$saved    = get_option( MND_CORE_SETTINGS, array() );
		$settings = array_merge( mnd_core_settings_defaults(), is_array( $saved ) ? $saved : array() );
	}
	return $settings;
}

/**
 * Je funkce zapnutá?
 *
 * @param string $key Klíč nastavení.
 * @return bool
 */
function mnd_core_on( $key ) {
	$settings = mnd_core_settings();
	return ! empty( $settings[ $key ] );
}

/**
 * Hodnota nastavení.
 *
 * @param string $key Klíč nastavení.
 * @return mixed
 */
function mnd_core_get( $key ) {
	$settings = mnd_core_settings();
	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

/**
 * Popis voleb: klíč => [typ, popisek, nápověda, (volby | [min, max, jednotka])].
 *
 * @return array Sekce => volby.
 */
function mnd_core_settings_fields() {
	return array(
		__( 'Přihlášení', 'mndgroup-core' )    => array(
			'login_protection' => array( 'checkbox', __( 'Omezení pokusů o přihlášení', 'mndgroup-core' ), __( 'Po opakovaných chybných pokusech z jedné IP adresy se přihlášení dočasně zablokuje. Chybové hlášky neprozradí, jestli uživatel existuje.', 'mndgroup-core' ) ),
			'login_attempts'   => array( 'number', __( 'Počet pokusů', 'mndgroup-core' ), __( 'Kolik chybných pokusů během 15 minut je povoleno.', 'mndgroup-core' ), array( 3, 20, __( 'pokusů', 'mndgroup-core' ) ) ),
			'login_lockout'    => array( 'number', __( 'Délka blokace', 'mndgroup-core' ), __( 'Každá další blokace během 24 hodin je dvakrát delší (nejvýš 24 h). Zrušit ji jde v Nástroje → Údržba webu.', 'mndgroup-core' ), array( 5, 1440, __( 'minut', 'mndgroup-core' ) ) ),
			'login_log'        => array( 'checkbox', __( 'Log přihlášení', 'mndgroup-core' ), __( 'Záznam přihlášení, neúspěšných pokusů a blokací (posledních 200 událostí, nejvýš 90 dní) v Nástroje → Log přihlášení a sloupec Poslední přihlášení v přehledu uživatelů.', 'mndgroup-core' ) ),
		),
		__( 'Zabezpečení', 'mndgroup-core' )   => array(
			'xmlrpc_off'       => array( 'checkbox', __( 'Vypnout XML-RPC a pingbacky', 'mndgroup-core' ), __( 'Staré rozhraní pro vzdálené publikování, častý cíl útoků. ManageWP ho nepotřebuje.', 'mndgroup-core' ) ),
			'file_edit_off'    => array( 'checkbox', __( 'Zakázat editor souborů', 'mndgroup-core' ), __( 'Skryje úpravy kódu šablon a pluginů v administraci (Vzhled → Editor souborů).', 'mndgroup-core' ) ),
			'hide_users'       => array( 'checkbox', __( 'Skrýt uživatelská jména', 'mndgroup-core' ), __( 'Adresy ?author=… a archivy autorů přesměrují na úvodní stránku, seznam uživatelů v REST API jen pro přihlášené, oEmbed bez autora.', 'mndgroup-core' ) ),
			'hide_version'     => array( 'checkbox', __( 'Skrýt verzi WordPressu', 'mndgroup-core' ), __( 'Bez meta značky generator.', 'mndgroup-core' ) ),
			'security_headers' => array( 'checkbox', __( 'Bezpečnostní HTTP hlavičky', 'mndgroup-core' ), __( 'X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy.', 'mndgroup-core' ) ),
			'hsts'             => array( 'checkbox', __( 'HSTS (vynutit HTTPS)', 'mndgroup-core' ), __( 'Prohlížeč bude web rok otevírat jen přes HTTPS (bez subdomén, nikdy na lokálním vývoji). Zapínejte jen, pokud HTTPS funguje spolehlivě.', 'mndgroup-core' ) ),
		),
		__( 'SEO', 'mndgroup-core' )           => array(
			'sitemap_no_users' => array( 'checkbox', __( 'Sitemap bez uživatelů', 'mndgroup-core' ), __( 'Z /wp-sitemap.xml zmizí seznam autorů (prozrazuje přihlašovací jména).', 'mndgroup-core' ) ),
			'sitemap_lastmod'  => array( 'checkbox', __( 'Datum změny v sitemapě', 'mndgroup-core' ), __( 'Vyhledávače poznají, které stránky se změnily.', 'mndgroup-core' ) ),
			'llms_txt'         => array( 'checkbox', __( '/llms.txt', 'mndgroup-core' ), __( 'Stručný textový přehled webu pro AI vyhledávače (llmstxt.org) na adrese /llms.txt.', 'mndgroup-core' ) ),
		),
		__( 'Média', 'mndgroup-core' )         => array(
			'webp'             => array( 'checkbox', __( 'Obrázky jako WebP', 'mndgroup-core' ), __( 'Nahrané JPG a PNG se uloží rovnou jako WebP (fotky se otočí podle EXIF, originál se neukládá) a zmenšeniny se tvoří ve WebP. GIF a SVG beze změny. Starší obrázky převede Nástroje → Údržba webu.', 'mndgroup-core' ) ),
		),
		__( 'Komentáře', 'mndgroup-core' )     => array(
			'comments_off'     => array( 'checkbox', __( 'Vypnout komentáře', 'mndgroup-core' ), __( 'Komentáře a pingbacky úplně vypnuté včetně administrace, kanálů a widgetu.', 'mndgroup-core' ) ),
		),
		__( 'Aktualizace', 'mndgroup-core' )   => array(
			'core_updates'     => array(
				'select',
				__( 'WordPress', 'mndgroup-core' ),
				__( 'Automatické aktualizace jádra WordPressu.', 'mndgroup-core' ),
				array(
					'all'   => __( 'Všechny verze automaticky', 'mndgroup-core' ),
					'minor' => __( 'Jen opravné verze (výchozí WordPress)', 'mndgroup-core' ),
					'off'   => __( 'Vypnuto', 'mndgroup-core' ),
				),
			),
			'plugin_updates'   => array(
				'select',
				__( 'Pluginy a šablony', 'mndgroup-core' ),
				__( 'Šablona a plugin MND se automaticky neaktualizují nikdy – nabídnou se v Nástěnka → Aktualizace a instalují se kliknutím.', 'mndgroup-core' ),
				array(
					'all'    => __( 'Všechny automaticky', 'mndgroup-core' ),
					'manual' => __( 'Podle nastavení u jednotlivých pluginů a šablon', 'mndgroup-core' ),
				),
			),
		),
		__( 'Administrace', 'mndgroup-core' )  => array(
			'admin_footer'     => array( 'checkbox', __( 'Info o serveru v patičce', 'mndgroup-core' ), __( 'Verze PHP a databáze, IP serveru a využití paměti v patičce administrace (jen pro administrátory).', 'mndgroup-core' ) ),
			'health_widget'    => array( 'checkbox', __( 'Widget Zdraví webu', 'mndgroup-core' ), __( 'Přehled verzí, aktualizací, databáze a přihlášení na Nástěnce.', 'mndgroup-core' ) ),
		),
	);
}

/**
 * Očištění hodnot z formuláře.
 *
 * @param mixed $input Odeslané hodnoty.
 * @return array
 */
function mnd_core_sanitize_settings( $input ) {
	$input  = is_array( $input ) ? $input : array();
	$clean  = array();
	foreach ( mnd_core_settings_fields() as $fields ) {
		foreach ( $fields as $key => $field ) {
			$value = isset( $input[ $key ] ) ? $input[ $key ] : null;
			switch ( $field[0] ) {
				case 'checkbox':
					$clean[ $key ] = empty( $value ) ? 0 : 1;
					break;
				case 'number':
					$default       = mnd_core_settings_defaults()[ $key ];
					$clean[ $key ] = null === $value || '' === $value ? $default : max( $field[3][0], min( $field[3][1], absint( $value ) ) );
					break;
				case 'select':
					$clean[ $key ] = array_key_exists( (string) $value, $field[3] ) ? (string) $value : mnd_core_settings_defaults()[ $key ];
					break;
			}
		}
	}
	return $clean;
}

/**
 * Registrace volby a stránky.
 */
function mnd_core_settings_init() {
	register_setting(
		'mnd_core',
		MND_CORE_SETTINGS,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'mnd_core_sanitize_settings',
			'default'           => mnd_core_settings_defaults(),
		)
	);
}
add_action( 'admin_init', 'mnd_core_settings_init' );

/**
 * Stránka v menu Nastavení.
 */
function mnd_core_settings_menu() {
	add_options_page( 'MND Group Core', 'MND Group Core', 'manage_options', MND_CORE_SETTINGS_PAGE, 'mnd_core_settings_page' );
}
add_action( 'admin_menu', 'mnd_core_settings_menu' );

/**
 * Odkaz „Nastavení“ v přehledu pluginů.
 *
 * @param array $links Odkazy.
 * @return array
 */
function mnd_core_settings_link( $links ) {
	array_unshift( $links, '<a href="' . esc_url( mnd_core_settings_url() ) . '">' . esc_html__( 'Nastavení', 'mndgroup-core' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . MND_CORE_BASENAME, 'mnd_core_settings_link' );

/**
 * URL stránky nastavení.
 *
 * @return string
 */
function mnd_core_settings_url() {
	return admin_url( 'options-general.php?page=' . MND_CORE_SETTINGS_PAGE );
}

/**
 * Vykreslení jednoho pole.
 *
 * @param string $key   Klíč.
 * @param array  $field Popis pole.
 */
function mnd_core_settings_field( $key, $field ) {
	$name  = MND_CORE_SETTINGS . '[' . $key . ']';
	$id    = 'mnd-core-' . str_replace( '_', '-', $key );
	$value = mnd_core_get( $key );

	switch ( $field[0] ) {
		case 'checkbox':
			printf(
				'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( ! empty( $value ), true, false ),
				esc_html__( 'Zapnuto', 'mndgroup-core' )
			);
			break;
		case 'number':
			printf(
				'<input type="number" id="%1$s" name="%2$s" value="%3$d" min="%4$d" max="%5$d" step="1" class="small-text"> %6$s',
				esc_attr( $id ),
				esc_attr( $name ),
				(int) $value,
				(int) $field[3][0],
				(int) $field[3][1],
				esc_html( $field[3][2] )
			);
			break;
		case 'select':
			printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( $field[3] as $option => $label ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $option ), selected( $value, $option, false ), esc_html( $label ) );
			}
			echo '</select>';
			break;
	}

	if ( 'file_edit_off' === $key && defined( 'DISALLOW_FILE_EDIT' ) && ! mnd_core_on( 'file_edit_off' ) ) {
		echo '<p class="description"><strong>' . esc_html__( 'Editor je zakázaný konstantou DISALLOW_FILE_EDIT ve wp-config.php – tam má přednost.', 'mndgroup-core' ) . '</strong></p>';
	}
	echo '<p class="description">' . esc_html( $field[2] ) . '</p>';
}

/**
 * Stránka Nastavení → MND Group Core.
 */
function mnd_core_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'MND Group Core', 'mndgroup-core' ); ?></h1>
		<p>
			<?php esc_html_e( 'Funkce webu nezávislé na šabloně. Výchozí stav je doporučený – vypínejte jen, pokud něco konkrétního potřebujete jinak.', 'mndgroup-core' ); ?>
			<a href="<?php echo esc_url( mnd_core_maintenance_url() ); ?>"><?php esc_html_e( 'Údržba webu →', 'mndgroup-core' ); ?></a>
		</p>
		<?php if ( 'local' === wp_get_environment_type() ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'Lokální vývoj: automatické aktualizace jsou vypnuté bez ohledu na nastavení.', 'mndgroup-core' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'mnd_core' ); ?>
			<?php foreach ( mnd_core_settings_fields() as $section => $fields ) : ?>
				<h2 class="title"><?php echo esc_html( $section ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $key => $field ) : ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( 'mnd-core-' . str_replace( '_', '-', $key ) ); ?>"><?php echo esc_html( $field[1] ); ?></label></th>
							<td><?php mnd_core_settings_field( $key, $field ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
