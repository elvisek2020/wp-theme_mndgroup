<?php
/**
 * MND Group Core — veškerá funkcionalita (načítá mndgroup-core.php).
 *
 * Každá funkce jde zapnout a vypnout v Nastavení → MND Group Core (inc/settings.php).
 *
 * 1) Omezení pokusů o přihlášení (nahrazuje All-In-One Security)
 * 2) Log přihlášení + poslední přihlášení (nahrazuje Simple Login Log)
 * 3) Info o serveru v patičce administrace (nahrazuje Server IP & Memory Usage)
 * 4) Komentáře vypnuté
 * 5) Automatické aktualizace (WordPress, pluginy a šablony – šablona a plugin MND vždy ručně)
 * 6) Obrázky jako WebP
 * 7) Hardening (XML-RPC, editor souborů, uživatelská jména, verze WordPressu)
 * 8) Údržba webu — Nástroje → Údržba webu (inc/maintenance.php, inc/leftovers.php)
 * 9) Aktualizace pluginu z GitHub Releases
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/settings.php';

/* =========================================================================
 * 1) Omezení pokusů o přihlášení (nahrazuje All-In-One Security)
 * Po nastaveném počtu chybných pokusů během 15 minut se IP zablokuje na nastavenou dobu,
 * každé další zablokování během 24 hodin dobu zdvojnásobí (max. 24 h). Platí pro wp-login.php
 * i všechna další místa, kde se ověřuje heslo. Odblokovat jde v Nástroje → Údržba webu.
 * ====================================================================== */

const MND_CORE_LOGIN_WINDOW  = 15 * MINUTE_IN_SECONDS;
const MND_CORE_LOCKOUT_MAX   = DAY_IN_SECONDS;
const MND_CORE_LOG_OPTION    = 'mnd_core_login_log';
const MND_CORE_LOG_MAX_ITEMS = 200;

/**
 * Povolený počet chybných pokusů (nastavení).
 *
 * @return int
 */
function mnd_core_login_attempts() {
	return max( 3, (int) mnd_core_get( 'login_attempts' ) );
}

/**
 * Délka první blokace v sekundách (nastavení).
 *
 * @return int
 */
function mnd_core_lockout_seconds() {
	return max( 5, (int) mnd_core_get( 'login_lockout' ) ) * MINUTE_IN_SECONDS;
}

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
		$left  = mnd_core_login_attempts() - $fails;
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

	if ( $fails < mnd_core_login_attempts() ) {
		set_transient( $fail_key, $fails, MND_CORE_LOGIN_WINDOW );
		return;
	}

	// Blokace – každá další během 24 h je dvakrát delší.
	$count_key = mnd_core_login_key( 'count', $ip );
	$count     = (int) get_transient( $count_key ) + 1;
	$duration  = (int) min( mnd_core_lockout_seconds() * pow( 2, $count - 1 ), MND_CORE_LOCKOUT_MAX );

	set_transient( mnd_core_login_key( 'lock', $ip ), time() + $duration, $duration );
	set_transient( $count_key, $count, DAY_IN_SECONDS );
	delete_transient( $fail_key );
	if ( mnd_core_on( 'login_log' ) ) {
		mnd_core_log_login( 'lockout', $username, $ip, $duration );
	}
}

/**
 * Úspěšné přihlášení: vynulovat počítadlo.
 */
function mnd_core_login_success() {
	delete_transient( mnd_core_login_key( 'fail', mnd_core_client_ip() ) );
}

if ( mnd_core_on( 'login_protection' ) ) {
	add_filter( 'authenticate', 'mnd_core_authenticate', 99, 2 );
	add_action( 'wp_login_failed', 'mnd_core_login_failed' );
	add_action( 'wp_login', 'mnd_core_login_success' );
}

/* =========================================================================
 * 2) Log přihlášení (nahrazuje Simple Login Log) + poslední přihlášení
 * Posledních 200 událostí (max. 90 dní): přihlášení, neúspěšné pokusy, blokace – v Nástroje →
 * Log přihlášení. Čas posledního přihlášení v user meta a ve sloupci v přehledu uživatelů.
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
			'a' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 160 ) : '',
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
 * Neúspěšný pokus do záznamu (během blokace se nezapisuje).
 *
 * @param string $username Uživatelské jméno.
 */
function mnd_core_log_failed( $username ) {
	$ip = mnd_core_client_ip();
	if ( mnd_core_on( 'login_protection' ) && mnd_core_locked_until( $ip ) ) {
		return;
	}
	mnd_core_log_login( 'failed', $username, $ip );
}

/**
 * Úspěšné přihlášení do záznamu a čas posledního přihlášení uživatele.
 *
 * @param string  $user_login Uživatelské jméno.
 * @param WP_User $user       Uživatel.
 */
function mnd_core_log_success( $user_login, $user = null ) {
	mnd_core_log_login( 'success', $user_login, mnd_core_client_ip() );
	if ( $user instanceof WP_User ) {
		update_user_meta( $user->ID, 'mnd_core_last_login', time() );
	}
}

/**
 * Sloupec „Poslední přihlášení“ v přehledu uživatelů.
 *
 * @param array $columns Sloupce.
 * @return array
 */
function mnd_core_users_column( $columns ) {
	$columns['mnd_core_last_login'] = __( 'Poslední přihlášení', 'mndgroup-core' );
	return $columns;
}

/**
 * Obsah sloupce.
 *
 * @param string $output  Výstup.
 * @param string $column  Sloupec.
 * @param int    $user_id ID uživatele.
 * @return string
 */
function mnd_core_users_column_content( $output, $column, $user_id ) {
	if ( 'mnd_core_last_login' !== $column ) {
		return $output;
	}
	$time = (int) get_user_meta( $user_id, 'mnd_core_last_login', true );
	return $time ? esc_html( wp_date( 'j. n. Y H:i', $time ) ) : '—';
}

/**
 * Stránka Nástroje → Log přihlášení.
 */
function mnd_core_login_log_menu() {
	add_management_page( __( 'Log přihlášení', 'mndgroup-core' ), __( 'Log přihlášení', 'mndgroup-core' ), 'manage_options', 'mndgroup-core-login-log', 'mnd_core_login_log_page' );
}

/**
 * Popis události v záznamu přihlášení.
 *
 * @param array $row Záznam.
 * @return string
 */
function mnd_core_login_event_label( $row ) {
	switch ( isset( $row['e'] ) ? $row['e'] : '' ) {
		case 'success':
			return '✅ ' . __( 'přihlášení', 'mndgroup-core' );
		case 'failed':
			return '❌ ' . __( 'neúspěšný pokus', 'mndgroup-core' );
		case 'lockout':
			/* translators: %d: minutes. */
			return '⛔ ' . sprintf( __( 'zablokováno na %d min', 'mndgroup-core' ), (int) round( ( isset( $row['d'] ) ? $row['d'] : 0 ) / MINUTE_IN_SECONDS ) );
	}
	return '';
}

/**
 * Obsah stránky logu (zrušení blokací přes Údržbu webu – stejná akce).
 */
function mnd_core_login_log_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$log      = mnd_core_login_log( MND_CORE_LOG_MAX_ITEMS );
	$lockouts = mnd_core_active_lockouts();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Log přihlášení', 'mndgroup-core' ); ?></h1>
		<?php
		if ( function_exists( 'mnd_core_maintenance_notice' ) ) {
			mnd_core_maintenance_notice();
		}
		?>
		<p>
			<?php
			if ( mnd_core_on( 'login_protection' ) ) {
				/* translators: 1: attempts, 2: number of blocked IP addresses. */
				echo esc_html( sprintf( __( 'Po %1$d neúspěšných pokusech se IP adresa dočasně zablokuje. Aktuálně zablokováno: %2$d.', 'mndgroup-core' ), mnd_core_login_attempts(), $lockouts ) );
			} else {
				esc_html_e( 'Omezení pokusů o přihlášení je vypnuté.', 'mndgroup-core' );
			}
			if ( ! mnd_core_on( 'login_log' ) ) {
				echo ' ' . esc_html__( 'Log přihlášení je vypnutý – níže jsou jen starší záznamy.', 'mndgroup-core' );
			}
			?>
			<a href="<?php echo esc_url( mnd_core_settings_url() ); ?>"><?php esc_html_e( 'Nastavení', 'mndgroup-core' ); ?></a>
		</p>
		<?php if ( $lockouts && function_exists( 'mnd_core_action_button' ) ) : ?>
			<?php mnd_core_action_button( 'unlock', __( 'Zrušit všechny blokace', 'mndgroup-core' ), __( 'Zrušit všechny blokace přihlášení?', 'mndgroup-core' ) ); ?>
		<?php endif; ?>
		<table class="widefat striped" style="margin-top:1em">
			<thead><tr><th><?php esc_html_e( 'Čas', 'mndgroup-core' ); ?></th><th><?php esc_html_e( 'Uživatel', 'mndgroup-core' ); ?></th><th><?php esc_html_e( 'Výsledek', 'mndgroup-core' ); ?></th><th><?php esc_html_e( 'IP adresa', 'mndgroup-core' ); ?></th><th><?php esc_html_e( 'Prohlížeč', 'mndgroup-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $log ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Zatím prázdné.', 'mndgroup-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'j. n. Y H:i:s', (int) $row['t'] ) ); ?></td>
						<td><?php echo esc_html( $row['u'] ); ?></td>
						<td><?php echo esc_html( mnd_core_login_event_label( $row ) ); ?></td>
						<td><code><?php echo esc_html( $row['i'] ); ?></code></td>
						<td><small><?php echo esc_html( isset( $row['a'] ) ? $row['a'] : '' ); ?></small></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Uchovává se posledních 200 událostí, nejvýše 90 dní.', 'mndgroup-core' ); ?></p>
	</div>
	<?php
}

if ( mnd_core_on( 'login_log' ) ) {
	add_action( 'wp_login_failed', 'mnd_core_log_failed', 5 );
	add_action( 'wp_login', 'mnd_core_log_success', 10, 2 );
	add_filter( 'manage_users_columns', 'mnd_core_users_column' );
	add_filter( 'manage_users_custom_column', 'mnd_core_users_column_content', 10, 3 );
}
add_action( 'admin_menu', 'mnd_core_login_log_menu' );

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
 * Verze PHP a databáze, IP serveru a využití paměti v patičce administrace (jen pro administrátory).
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
		'%1$s · PHP %2$s · DB %3$s · IP %7$s · %4$s %5$s / %6$s',
		$text,
		esc_html( PHP_VERSION ),
		esc_html( $wpdb->db_server_info() ),
		esc_html__( 'paměť', 'mndgroup-core' ),
		esc_html( size_format( memory_get_peak_usage( true ) ) ),
		esc_html( ini_get( 'memory_limit' ) ),
		esc_html( isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : '?' )
	);
}
if ( mnd_core_on( 'admin_footer' ) ) {
	add_filter( 'admin_footer_text', 'mnd_core_admin_footer', 20 );
}

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

/**
 * Administrace: bez menu Komentáře a Nastavení → Diskuze.
 */
function mnd_core_comments_admin_menu() {
	remove_menu_page( 'edit-comments.php' );
	remove_submenu_page( 'options-general.php', 'options-discussion.php' );
}

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

/**
 * Bez komentářů v liště administrace.
 *
 * @param WP_Admin_Bar $bar Lišta.
 */
function mnd_core_comments_admin_bar( $bar ) {
	$bar->remove_node( 'comments' );
}

/**
 * Bez widgetu Nejnovější komentáře.
 */
function mnd_core_comments_widgets() {
	unregister_widget( 'WP_Widget_Recent_Comments' );
}

if ( mnd_core_on( 'comments_off' ) ) {
	add_action( 'init', 'mnd_core_remove_comment_support', 100 );
	add_filter( 'comments_open', '__return_false', 20 );
	add_filter( 'pings_open', '__return_false', 20 );
	add_filter( 'comments_array', '__return_empty_array', 20 );
	add_filter( 'get_comments_number', '__return_zero', 20 );
	add_filter( 'feed_links_show_comments_feed', '__return_false' );
	add_action( 'template_redirect', 'mnd_core_comment_feed_404', 1 );
	add_action( 'admin_menu', 'mnd_core_comments_admin_menu', 999 );
	add_action( 'admin_init', 'mnd_core_comments_admin_redirect' );
	add_action( 'admin_bar_menu', 'mnd_core_comments_admin_bar', 999 );
	add_action( 'widgets_init', 'mnd_core_comments_widgets', 20 );
}

/* =========================================================================
 * 5) Automatické aktualizace
 * WordPress podle nastavení (všechny verze / jen opravné / vypnuto), pluginy a šablony všechny
 * nebo podle volby u jednotlivých položek. Šablona a plugin MND se automaticky neaktualizují
 * nikdy – nabídnou se v Nástěnka → Aktualizace a instalují se kliknutím.
 * ====================================================================== */

/**
 * Plugin MND Group Core nikdy automaticky; ostatní podle nastavení.
 *
 * @param bool|null $update Aktualizovat?
 * @param object    $item   Plugin.
 * @return bool|null
 */
function mnd_core_auto_update_plugin( $update, $item ) {
	if ( isset( $item->plugin ) && MND_CORE_BASENAME === $item->plugin ) {
		return false;
	}
	return 'all' === mnd_core_get( 'plugin_updates' ) ? true : $update;
}
add_filter( 'auto_update_plugin', 'mnd_core_auto_update_plugin', 10, 2 );

/**
 * Šablona MND Group nikdy automaticky; ostatní podle nastavení.
 *
 * @param bool|null $update Aktualizovat?
 * @param object    $item   Šablona.
 * @return bool|null
 */
function mnd_core_auto_update_theme( $update, $item ) {
	if ( isset( $item->theme ) && 'mndgroup' === $item->theme ) {
		return false;
	}
	return 'all' === mnd_core_get( 'plugin_updates' ) ? true : $update;
}
add_filter( 'auto_update_theme', 'mnd_core_auto_update_theme', 10, 2 );

switch ( mnd_core_get( 'core_updates' ) ) {
	case 'all':
		add_filter( 'allow_major_auto_core_updates', '__return_true' );
		add_filter( 'allow_minor_auto_core_updates', '__return_true' );
		break;
	case 'off':
		add_filter( 'auto_update_core', '__return_false' );
		break;
}

// Lokální vývoj: žádné automatické aktualizace.
if ( 'local' === wp_get_environment_type() ) {
	add_filter( 'automatic_updater_disabled', '__return_true' );
}

/* =========================================================================
 * 6) Obrázky jako WebP
 * Nahraný PNG/JPG se rovnou převede na WebP (originál se neukládá), fotky se otočí podle
 * EXIF a zmenšeniny se tvoří jako WebP. GIF a SVG beze změny. Starší obrázky převede
 * jednorázová akce v Nástroje → Údržba webu.
 * ====================================================================== */

/**
 * Umí server ukládat WebP (GD nebo Imagick)?
 *
 * @return bool
 */
function mnd_core_webp_supported() {
	return wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
}

/**
 * Převod nahraného JPG/PNG na WebP.
 *
 * @param array $upload Výsledek nahrání (file, url, type).
 * @return array
 */
function mnd_core_webp_upload( $upload ) {
	if ( ! empty( $upload['error'] ) || ! isset( $upload['type'] ) || ! in_array( $upload['type'], array( 'image/jpeg', 'image/png' ), true ) || ! mnd_core_webp_supported() ) {
		return $upload;
	}
	$file   = $upload['file'];
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return $upload;
	}
	$dir  = dirname( $file );
	$name = preg_replace( '/\.(png|jpe?g)$/i', '.webp', basename( $file ) );
	// Stejný název s jinou příponou má jen právě nahraný soubor (ten se smaže) → není potřeba „-1“.
	$same = array_diff( (array) glob( $dir . '/' . preg_replace( '/\.webp$/', '', $name ) . '.*' ), array( $file ) );
	if ( $same ) {
		$name = wp_unique_filename( $dir, $name );
	}
	$editor->set_quality( 82 );
	$editor->maybe_exif_rotate(); // fotky z mobilu: WebP nenese EXIF, otočit hned
	$saved = $editor->save( $dir . '/' . $name, 'image/webp' );
	if ( is_wp_error( $saved ) || ! is_file( $dir . '/' . $name ) ) {
		return $upload; // když převod selže, zůstane původní soubor
	}
	wp_delete_file( $file );
	return array(
		'file' => $dir . '/' . $name,
		'url'  => trailingslashit( dirname( $upload['url'] ) ) . $name,
		'type' => 'image/webp',
	);
}

/**
 * Zmenšeniny JPG/PNG ve formátu WebP.
 *
 * @param array $formats Mapování formátů.
 * @return array
 */
function mnd_core_webp_output_format( $formats ) {
	if ( mnd_core_webp_supported() ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
	}
	return $formats;
}

if ( mnd_core_on( 'webp' ) ) {
	add_filter( 'wp_handle_upload', 'mnd_core_webp_upload' );
	add_filter( 'wp_handle_sideload', 'mnd_core_webp_upload' ); // REST API (tools/wp.py), WP-CLI, stažení z URL
	add_filter( 'image_editor_output_format', 'mnd_core_webp_output_format' );
}

/* =========================================================================
 * 7) Hardening
 * XML-RPC, editor souborů, verze WordPressu, výčet uživatelů.
 * ====================================================================== */

/*
 * XML-RPC úplně vypnuté – web ho nepoužívá (ManageWP má vlastní rozhraní) a je to
 * oblíbený cíl útoků hrubou silou. Povolit jde filtrem mnd_core_allow_xmlrpc.
 */
if ( mnd_core_on( 'xmlrpc_off' ) && defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && ! apply_filters( 'mnd_core_allow_xmlrpc', false ) ) {
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( 'XML-RPC is disabled.' );
}

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
if ( mnd_core_on( 'xmlrpc_off' ) ) {
	add_filter( 'xmlrpc_enabled', '__return_false' );
	add_filter( 'xmlrpc_methods', '__return_empty_array' );
	add_filter( 'wp_headers', 'mnd_core_remove_pingback_header' );
	remove_action( 'wp_head', 'rsd_link' );
}

// Editor souborů šablon a pluginů v administraci (změny kódu jen přes Git/vydání).
if ( mnd_core_on( 'file_edit_off' ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// Verze WordPressu se nevypisuje.
if ( mnd_core_on( 'hide_version' ) ) {
	remove_action( 'wp_head', 'wp_generator' );
	add_filter( 'the_generator', '__return_empty_string' );
}

/**
 * Výčet uživatelů pro nepřihlášené: ?author=N a archivy autorů přesměrují na titulku
 * (web archivy autorů nepoužívá, prozrazovaly by uživatelská jména).
 */
function mnd_core_block_author_enumeration() {
	if ( is_user_logged_in() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( is_author() || isset( $_GET['author'] ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}

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

if ( mnd_core_on( 'hide_users' ) ) {
	add_action( 'template_redirect', 'mnd_core_block_author_enumeration', 1 );
	add_filter( 'rest_endpoints', 'mnd_core_rest_users' );
	add_filter( 'oembed_response_data', 'mnd_core_oembed_author' );
}

/* =========================================================================
 * 8) Údržba webu — Nástroje → Údržba webu (inc/maintenance.php)
 * ====================================================================== */

require __DIR__ . '/maintenance.php';
require __DIR__ . '/leftovers.php';

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
