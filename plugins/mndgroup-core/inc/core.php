<?php
/**
 * MND Group Core — veškerá funkcionalita (načítá mndgroup-core.php).
 *
 * 1) Omezení pokusů o přihlášení (nahrazuje All-In-One Security)
 * 2) Log přihlášení (nahrazuje Simple Login Log)
 * 3) Info o serveru v patičce administrace (nahrazuje Server IP & Memory Usage)
 * 4) Komentáře vypnuté
 * 5) Automatické aktualizace (WordPress, pluginy, překlady – šablona a plugin MND ručně)
 * 6) Hardening (XML-RPC, editor souborů, výčet uživatelů)
 * 7) Zjednodušená administrace (nahrazuje Admin Menu Editor)
 * 8) Údržba webu — Nástroje → Údržba webu (inc/maintenance.php)
 * 9) Aktualizace pluginu z GitHub Releases
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * 1) Omezení pokusů o přihlášení (nahrazuje All-In-One Security)
 * Po MND_CORE_LOGIN_ATTEMPTS chybných pokusech během 15 minut se IP zablokuje na 30 minut,
 * každé další zablokování během 24 hodin dobu zdvojnásobí (max. 24 h). Platí pro wp-login.php
 * i všechna další místa, kde se ověřuje heslo. Odblokovat jde v Nástroje → Údržba webu.
 * ====================================================================== */

if ( ! defined( 'MND_CORE_LOGIN_ATTEMPTS' ) ) {
	define( 'MND_CORE_LOGIN_ATTEMPTS', 5 );
}

const MND_CORE_LOGIN_WINDOW  = 15 * MINUTE_IN_SECONDS;
const MND_CORE_LOCKOUT       = 30 * MINUTE_IN_SECONDS;
const MND_CORE_LOCKOUT_MAX   = DAY_IN_SECONDS;
const MND_CORE_LOG_OPTION    = 'mnd_core_login_log';
const MND_CORE_LOG_MAX_ITEMS = 200;

/**
 * IP adresa návštěvníka. Za reverzní proxy lze upravit filtrem mnd_core_client_ip.
 *
 * @return string
 */
function mnd_core_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip = (string) apply_filters( 'mnd_core_client_ip', $ip );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Klíč transientu pro IP adresu.
 *
 * @param string $type fail|lock|count.
 * @param string $ip   IP adresa.
 * @return string
 */
function mnd_core_login_key( $type, $ip ) {
	return 'mnd_core_' . $type . '_' . substr( md5( $ip ), 0, 16 );
}

/**
 * Do kdy je IP zablokovaná (unix čas), 0 = není.
 *
 * @param string $ip IP adresa.
 * @return int
 */
function mnd_core_locked_until( $ip ) {
	$until = (int) get_transient( mnd_core_login_key( 'lock', $ip ) );
	return $until > time() ? $until : 0;
}

/**
 * Zablokovaná IP se nepřihlásí ani se správným heslem; chybové hlášky neprozrazují,
 * jestli existuje uživatel.
 *
 * @param WP_User|WP_Error|null $user     Výsledek ověření.
 * @param string                $username Uživatelské jméno.
 * @return WP_User|WP_Error|null
 */
function mnd_core_authenticate( $user, $username ) {
	$ip    = mnd_core_client_ip();
	$until = mnd_core_locked_until( $ip );

	if ( $until ) {
		return new WP_Error(
			'mnd_core_locked',
			sprintf(
				/* translators: %d: minutes. */
				__( '<strong>Chyba:</strong> Příliš mnoho neúspěšných pokusů o přihlášení. Zkuste to znovu za %d min.', 'mndgroup-core' ),
				max( 1, (int) ceil( ( $until - time() ) / MINUTE_IN_SECONDS ) )
			)
		);
	}

	if ( is_wp_error( $user ) && array_intersect( $user->get_error_codes(), array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
		$fails = (int) get_transient( mnd_core_login_key( 'fail', $ip ) ) + 1;
		$left  = MND_CORE_LOGIN_ATTEMPTS - $fails;
		$text  = __( '<strong>Chyba:</strong> Nesprávné uživatelské jméno, e-mail nebo heslo.', 'mndgroup-core' );
		if ( $left <= 0 ) {
			$text = __( '<strong>Chyba:</strong> Příliš mnoho neúspěšných pokusů o přihlášení. Přihlášení je dočasně zablokované.', 'mndgroup-core' );
		} elseif ( $left <= 2 ) {
			/* translators: %d: remaining attempts. */
			$text .= ' ' . sprintf( _n( 'Zbývá %d pokus.', 'Zbývají %d pokusy.', $left, 'mndgroup-core' ), $left );
		}
		return new WP_Error( 'mnd_core_failed', $text );
	}

	return $user;
}
add_filter( 'authenticate', 'mnd_core_authenticate', 99, 2 );

/**
 * Neúspěšný pokus: započítat a případně zablokovat.
 *
 * @param string $username Uživatelské jméno.
 */
function mnd_core_login_failed( $username ) {
	$ip = mnd_core_client_ip();
	if ( mnd_core_locked_until( $ip ) ) {
		return; // během blokace se nepočítá ani neprodlužuje
	}

	$fail_key = mnd_core_login_key( 'fail', $ip );
	$fails    = (int) get_transient( $fail_key ) + 1;
	mnd_core_log_login( 'failed', $username, $ip );

	if ( $fails < MND_CORE_LOGIN_ATTEMPTS ) {
		set_transient( $fail_key, $fails, MND_CORE_LOGIN_WINDOW );
		return;
	}

	// Blokace – každá další během 24 h je dvakrát delší.
	$count_key = mnd_core_login_key( 'count', $ip );
	$count     = (int) get_transient( $count_key ) + 1;
	$duration  = (int) min( MND_CORE_LOCKOUT * pow( 2, $count - 1 ), MND_CORE_LOCKOUT_MAX );

	set_transient( mnd_core_login_key( 'lock', $ip ), time() + $duration, $duration );
	set_transient( $count_key, $count, DAY_IN_SECONDS );
	delete_transient( $fail_key );
	mnd_core_log_login( 'lockout', $username, $ip, $duration );
}
add_action( 'wp_login_failed', 'mnd_core_login_failed' );

/**
 * Úspěšné přihlášení: vynulovat počítadlo a zapsat do záznamu.
 *
 * @param string $user_login Uživatelské jméno.
 */
function mnd_core_login_success( $user_login ) {
	$ip = mnd_core_client_ip();
	delete_transient( mnd_core_login_key( 'fail', $ip ) );
	mnd_core_log_login( 'success', $user_login, $ip );
}
add_action( 'wp_login', 'mnd_core_login_success' );

/* =========================================================================
 * 2) Log přihlášení (nahrazuje Simple Login Log)
 * Posledních 200 událostí (max. 90 dní): přihlášení, neúspěšné pokusy, blokace.
 * ====================================================================== */

/**
 * Zápis do záznamu přihlášení (posledních MND_CORE_LOG_MAX_ITEMS událostí, max. 90 dní).
 *
 * @param string $type     success|failed|lockout.
 * @param string $username Uživatelské jméno (jak bylo zadáno).
 * @param string $ip       IP adresa.
 * @param int    $duration Délka blokace v sekundách.
 */
function mnd_core_log_login( $type, $username, $ip, $duration = 0 ) {
	$log   = (array) get_option( MND_CORE_LOG_OPTION, array() );
	$limit = time() - 90 * DAY_IN_SECONDS;

	array_unshift(
		$log,
		array(
			't' => time(),
			'e' => $type,
			'u' => mb_substr( sanitize_user( (string) $username, false ), 0, 60 ),
			'i' => $ip,
			'd' => (int) $duration,
		)
	);

	$log = array_filter(
		array_slice( $log, 0, MND_CORE_LOG_MAX_ITEMS ),
		function ( $row ) use ( $limit ) {
			return isset( $row['t'] ) && $row['t'] >= $limit;
		}
	);

	update_option( MND_CORE_LOG_OPTION, array_values( $log ), false );
}

/**
 * Záznam přihlášení.
 *
 * @param int $limit Počet položek.
 * @return array
 */
function mnd_core_login_log( $limit = 50 ) {
	return array_slice( (array) get_option( MND_CORE_LOG_OPTION, array() ), 0, $limit );
}

/**
 * Počet aktuálně zablokovaných IP adres.
 *
 * @return int
 */
function mnd_core_active_lockouts() {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value > %d",
			$wpdb->esc_like( '_transient_timeout_mnd_core_lock_' ) . '%',
			time()
		)
	);
}

/**
 * Zrušení všech blokací a počítadel.
 */
function mnd_core_clear_lockouts() {
	global $wpdb;
	foreach ( array( 'lock', 'count', 'fail' ) as $type ) {
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_mnd_core_' . $type . '_' ) . '%'
			)
		);
		foreach ( $names as $name ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) );
		}
	}
}

/* =========================================================================
 * 3) Info o serveru v patičce administrace (nahrazuje Server IP & Memory Usage)
 * ====================================================================== */

/**
 * Verze PHP, databáze a využití paměti v patičce administrace (jen pro administrátory).
 *
 * @param string $text Text patičky.
 * @return string
 */
function mnd_core_admin_footer( $text ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return $text;
	}
	global $wpdb;
	return sprintf(
		'%1$s · PHP %2$s · %3$s · %4$s %5$s / %6$s',
		$text,
		esc_html( PHP_VERSION ),
		esc_html( $wpdb->db_server_info() ),
		esc_html__( 'paměť', 'mndgroup-core' ),
		esc_html( size_format( memory_get_peak_usage( true ) ) ),
		esc_html( ini_get( 'memory_limit' ) )
	);
}
add_filter( 'admin_footer_text', 'mnd_core_admin_footer', 20 );

/* =========================================================================
 * 4) Komentáře vypnuté
 * Web je nepoužívá (v nastavení byly zavřené a v administraci skryté).
 * ====================================================================== */

/**
 * Žádný typ obsahu nepodporuje komentáře ani trackbacky.
 */
function mnd_core_remove_comment_support() {
	foreach ( get_post_types() as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
		}
		if ( post_type_supports( $post_type, 'trackbacks' ) ) {
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}
add_action( 'init', 'mnd_core_remove_comment_support', 100 );

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
add_filter( 'get_comments_number', '__return_zero', 20 );
add_filter( 'feed_links_show_comments_feed', '__return_false' );

/**
 * Kanál komentářů vrací 404.
 */
function mnd_core_comment_feed_404() {
	if ( is_comment_feed() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
	}
}
add_action( 'template_redirect', 'mnd_core_comment_feed_404', 1 );

/**
 * Administrace: bez menu Komentáře a Nastavení → Diskuze.
 */
function mnd_core_comments_admin_menu() {
	remove_menu_page( 'edit-comments.php' );
	remove_submenu_page( 'options-general.php', 'options-discussion.php' );
}
add_action( 'admin_menu', 'mnd_core_comments_admin_menu', 999 );

/**
 * Přímý přístup na stránky komentářů přesměrovat na Nástěnku.
 */
function mnd_core_comments_admin_redirect() {
	global $pagenow;
	if ( in_array( $pagenow, array( 'edit-comments.php', 'comment.php', 'options-discussion.php' ), true ) ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
add_action( 'admin_init', 'mnd_core_comments_admin_redirect' );

/**
 * Bez komentářů v liště administrace.
 *
 * @param WP_Admin_Bar $bar Lišta.
 */
function mnd_core_comments_admin_bar( $bar ) {
	$bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'mnd_core_comments_admin_bar', 999 );

/**
 * Bez widgetu Nejnovější komentáře.
 */
function mnd_core_comments_widgets() {
	unregister_widget( 'WP_Widget_Recent_Comments' );
}
add_action( 'widgets_init', 'mnd_core_comments_widgets', 20 );

/* =========================================================================
 * 5) Automatické aktualizace
 * WordPress (i hlavní verze), pluginy a překlady se aktualizují samy. Šablona a plugin MND
 * se jen nabídnou v Nástěnka → Aktualizace a instalují se kliknutím.
 * ====================================================================== */

add_filter( 'allow_major_auto_core_updates', '__return_true' );
add_filter( 'allow_minor_auto_core_updates', '__return_true' );
add_filter( 'auto_update_translation', '__return_true' );

/**
 * Pluginy se aktualizují samy – kromě MND Group Core, ten se instaluje ručně z GitHub Releases
 * (Nástěnka → Aktualizace), aby nové vydání nemohlo samo rozbít web.
 *
 * @param bool|null $update Aktualizovat?
 * @param object    $item   Plugin.
 * @return bool
 */
function mnd_core_auto_update_plugin( $update, $item ) {
	return ! ( isset( $item->plugin ) && MND_CORE_BASENAME === $item->plugin );
}
add_filter( 'auto_update_plugin', 'mnd_core_auto_update_plugin', 10, 2 );

/**
 * Šablony se aktualizují samy – kromě šablony MND Group (ručně z GitHub Releases).
 *
 * @param bool|null $update Aktualizovat?
 * @param object    $item   Šablona.
 * @return bool
 */
function mnd_core_auto_update_theme( $update, $item ) {
	return ! ( isset( $item->theme ) && 'mndgroup' === $item->theme );
}
add_filter( 'auto_update_theme', 'mnd_core_auto_update_theme', 10, 2 );

// Lokální vývoj: žádné automatické aktualizace.
if ( 'local' === wp_get_environment_type() ) {
	add_filter( 'automatic_updater_disabled', '__return_true' );
}

/* =========================================================================
 * 6) Hardening
 * XML-RPC, editor souborů, verze WordPressu, výčet uživatelů.
 * ====================================================================== */

/*
 * XML-RPC úplně vypnuté – web ho nepoužívá (ManageWP má vlastní rozhraní) a je to
 * oblíbený cíl útoků hrubou silou. Povolit jde filtrem mnd_core_allow_xmlrpc.
 */
if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && ! apply_filters( 'mnd_core_allow_xmlrpc', false ) ) {
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( 'XML-RPC is disabled.' );
}
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
remove_action( 'wp_head', 'rsd_link' );

/**
 * Bez hlavičky X-Pingback.
 *
 * @param array $headers HTTP hlavičky.
 * @return array
 */
function mnd_core_remove_pingback_header( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
}
add_filter( 'wp_headers', 'mnd_core_remove_pingback_header' );

// Editor souborů šablon a pluginů v administraci (změny kódu jen přes Git/vydání).
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// Verze WordPressu se nevypisuje.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/**
 * Výčet uživatelů pro nepřihlášené: ?author=N a archivy autorů vrací 404
 * (web archivy autorů nepoužívá, prozrazovaly by uživatelská jména).
 */
function mnd_core_block_author_enumeration() {
	if ( is_user_logged_in() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( is_author() || isset( $_GET['author'] ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'mnd_core_block_author_enumeration', 1 );

/**
 * REST API: seznam uživatelů jen pro přihlášené (editor ho potřebuje pro výběr autora).
 *
 * @param array $endpoints Registrované cesty.
 * @return array
 */
function mnd_core_rest_users( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'mnd_core_rest_users' );

/**
 * oEmbed bez jména a odkazu na autora.
 *
 * @param array $data Data odpovědi.
 * @return array
 */
function mnd_core_oembed_author( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
}
add_filter( 'oembed_response_data', 'mnd_core_oembed_author' );

/* =========================================================================
 * 7) Zjednodušená administrace (nahrazuje Admin Menu Editor)
 * Menu skrývá položky, které správci obsahu nepotřebují. Skrytí není zákaz – stránky zůstávají
 * dostupné. Úplné menu si administrátor zapne v Uživatelé → Profil.
 * ====================================================================== */

const MND_CORE_FULL_MENU_META = 'mnd_core_full_menu';

/**
 * Položky menu skryté ve zjednodušeném režimu (slug stránky menu).
 *
 * @return string[]
 */
function mnd_core_hidden_menu_items() {
	return (array) apply_filters(
		'mnd_core_hidden_menu_items',
		array(
			'tools.php',                // Nástroje
			'options-general.php',      // Nastavení
			'mlang',                    // Polylang → Jazyky
			'aiowpsec',                 // All-In-One Security (pokud ještě zůstal)
			'monsterinsights_settings', // MonsterInsights (pokud ještě zůstal)
			'huge_it_light_box',        // Huge IT Lightbox (pokud ještě zůstal)
		)
	);
}

/**
 * Má aktuální uživatel úplné menu?
 *
 * @return bool
 */
function mnd_core_full_menu() {
	return (bool) get_user_meta( get_current_user_id(), MND_CORE_FULL_MENU_META, true );
}

/**
 * Skrytí položek menu.
 */
function mnd_core_simplify_menu() {
	if ( mnd_core_full_menu() ) {
		return;
	}
	foreach ( mnd_core_hidden_menu_items() as $slug ) {
		remove_menu_page( $slug );
	}
}
add_action( 'admin_menu', 'mnd_core_simplify_menu', 9999 );

/**
 * Přepínač v profilu (jen pro administrátory).
 *
 * @param WP_User $user Upravovaný uživatel.
 */
function mnd_core_full_menu_field( $user ) {
	if ( ! current_user_can( 'manage_options' ) || ! user_can( $user, 'manage_options' ) ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Administrace MND Group', 'mndgroup-core' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Menu administrace', 'mndgroup-core' ); ?></th>
			<td>
				<label for="mndgroup-core-full-menu">
					<input type="checkbox" name="mnd_core_full_menu" id="mndgroup-core-full-menu" value="1" <?php checked( (bool) get_user_meta( $user->ID, MND_CORE_FULL_MENU_META, true ) ); ?>>
					<?php esc_html_e( 'Zobrazit úplné menu (Nástroje, Nastavení, Jazyky…)', 'mndgroup-core' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Ve výchozím stavu jsou technické položky skryté, aby se v administraci snáz orientovalo.', 'mndgroup-core' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'mnd_core_full_menu_field' );
add_action( 'edit_user_profile', 'mnd_core_full_menu_field' );

/**
 * Uložení přepínače (nonce kontroluje formulář profilu WordPressu).
 *
 * @param int $user_id ID uživatele.
 */
function mnd_core_full_menu_save( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! current_user_can( 'manage_options' ) || ! user_can( $user_id, 'manage_options' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST['mnd_core_full_menu'] ) ) {
		update_user_meta( $user_id, MND_CORE_FULL_MENU_META, 1 );
	} else {
		delete_user_meta( $user_id, MND_CORE_FULL_MENU_META );
	}
}
add_action( 'personal_options_update', 'mnd_core_full_menu_save' );
add_action( 'edit_user_profile_update', 'mnd_core_full_menu_save' );

/**
 * Nástěnka bez novinek z WordPress.org a rychlého konceptu (web příspěvky nepoužívá).
 */
function mnd_core_dashboard_cleanup() {
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
}
add_action( 'wp_dashboard_setup', 'mnd_core_dashboard_cleanup', 20 );

/* =========================================================================
 * 8) Údržba webu — Nástroje → Údržba webu (inc/maintenance.php)
 * ====================================================================== */

require __DIR__ . '/maintenance.php';

/* =========================================================================
 * 9) Aktualizace pluginu z GitHub Releases (Update URI → filtr update_plugins_github.com)
 * Vydání musí obsahovat soubor mndgroup-core.zip (sestavuje GitHub Action). Na lokálním vývoji
 * vypnuto, pro test define( 'MND_UPDATER_ON_LOCAL', true ) v wp-config.php.
 * ====================================================================== */

if ( 'local' !== wp_get_environment_type() || ( defined( 'MND_UPDATER_ON_LOCAL' ) && MND_UPDATER_ON_LOCAL ) ) {
	add_filter( 'update_plugins_github.com', 'mnd_core_check_update', 10, 3 );
	add_filter( 'plugins_api', 'mnd_core_plugins_api', 10, 3 );
	add_action( 'load-update-core.php', 'mnd_core_force_check' );
	add_action( 'upgrader_process_complete', 'mnd_core_clear_release_cache' );
}

/**
 * Poslední vydání z GitHubu s balíčkem mndgroup-core.zip (cache: úspěch 6 h, chyba 1 h).
 *
 * @return array|null [version, url, package, notes]
 */
function mnd_core_latest_release() {
	$cached = get_site_transient( 'mnd_core_release' );
	if ( is_array( $cached ) ) {
		return $cached ? $cached : null;
	}

	$release  = array();
	$response = wp_remote_get(
		'https://api.github.com/repos/' . MND_CORE_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'mndgroup-core-updater',
			),
		)
	);

	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		foreach ( (array) ( isset( $data['assets'] ) ? $data['assets'] : array() ) as $asset ) {
			if ( isset( $asset['name'] ) && 'mndgroup-core.zip' === $asset['name'] ) {
				$release = array(
					'version' => ltrim( (string) $data['tag_name'], 'vV' ),
					'url'     => (string) $data['html_url'],
					'package' => (string) $asset['browser_download_url'],
					'notes'   => isset( $data['body'] ) ? (string) $data['body'] : '',
				);
				break;
			}
		}
	}

	set_site_transient( 'mnd_core_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release ? $release : null;
}

/**
 * Odpověď pro kontrolu aktualizací pluginů (Update URI → filtr update_plugins_github.com).
 *
 * @param array|false $update      Data aktualizace.
 * @param array       $plugin_data Hlavičky pluginu.
 * @param string      $plugin_file Soubor pluginu.
 * @return array|false
 */
function mnd_core_check_update( $update, $plugin_data, $plugin_file ) {
	if ( MND_CORE_BASENAME !== $plugin_file ) {
		return $update;
	}
	$release = mnd_core_latest_release();
	if ( ! $release ) {
		return $update;
	}
	return array(
		'id'           => 'https://github.com/' . MND_CORE_REPO,
		'slug'         => 'mndgroup-core',
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'tested'       => MND_CORE_TESTED_WP,
		'requires'     => '6.5',
		'requires_php' => '7.4',
	);
}

/**
 * Okno „Zobrazit podrobnosti“ (jinak by WordPress hledal plugin na wordpress.org).
 *
 * @param false|object|array $result Výsledek.
 * @param string             $action Typ dotazu.
 * @param object             $args   Parametry.
 * @return false|object|array
 */
function mnd_core_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'mndgroup-core' !== $args->slug ) {
		return $result;
	}
	$release = mnd_core_latest_release();
	return (object) array(
		'name'          => 'MND Group Core',
		'slug'          => 'mndgroup-core',
		'version'       => $release ? $release['version'] : MND_CORE_VERSION,
		'author'        => 'Zdeněk Král (ElvisEK)',
		'homepage'      => 'https://github.com/' . MND_CORE_REPO,
		'tested'        => MND_CORE_TESTED_WP,
		'requires'      => '6.5',
		'requires_php'  => '7.4',
		'download_link' => $release ? $release['package'] : '',
		'sections'      => array(
			'changelog' => $release && $release['notes'] ? wpautop( esc_html( $release['notes'] ) ) : esc_html__( 'Viz CHANGELOG na GitHubu.', 'mndgroup-core' ),
		),
	);
}

/**
 * Tlačítko „Zkontrolovat znovu“ v Aktualizacích smaže i naši cache.
 */
function mnd_core_force_check() {
	if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		delete_site_transient( 'mnd_core_release' );
	}
}

/**
 * Po aktualizaci zapomenout uložené vydání.
 */
function mnd_core_clear_release_cache() {
	delete_site_transient( 'mnd_core_release' );
}
