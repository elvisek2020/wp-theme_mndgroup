<?php
/**
 * Ochrana přihlášení: omezení počtu pokusů z jedné IP adresy a záznam přihlášení.
 *
 * Po MNDGROUP_CORE_LOGIN_ATTEMPTS chybných pokusech během 15 minut se IP adresa
 * zablokuje na 30 minut; každé další zablokování během 24 hodin dobu zdvojnásobí
 * (max. 24 h). Platí pro wp-login.php i všechna další místa, kde se ověřuje heslo.
 * Odblokovat lze v Nástroje → Údržba webu.
 *
 * Nahrazuje pluginy All-In-One Security (ochrana přihlášení) a Simple Login Log.
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MNDGROUP_CORE_LOGIN_ATTEMPTS' ) ) {
	define( 'MNDGROUP_CORE_LOGIN_ATTEMPTS', 5 );
}

const MNDGROUP_CORE_LOGIN_WINDOW  = 15 * MINUTE_IN_SECONDS;
const MNDGROUP_CORE_LOCKOUT       = 30 * MINUTE_IN_SECONDS;
const MNDGROUP_CORE_LOCKOUT_MAX   = DAY_IN_SECONDS;
const MNDGROUP_CORE_LOG_OPTION    = 'mndgroup_core_login_log';
const MNDGROUP_CORE_LOG_MAX_ITEMS = 200;

/**
 * IP adresa návštěvníka. Za reverzní proxy lze upravit filtrem mndgroup_core_client_ip.
 *
 * @return string
 */
function mndgroup_core_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip = (string) apply_filters( 'mndgroup_core_client_ip', $ip );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Klíč transientu pro IP adresu.
 *
 * @param string $type fail|lock|count.
 * @param string $ip   IP adresa.
 * @return string
 */
function mndgroup_core_login_key( $type, $ip ) {
	return 'mndgroup_core_' . $type . '_' . substr( md5( $ip ), 0, 16 );
}

/**
 * Do kdy je IP zablokovaná (unix čas), 0 = není.
 *
 * @param string $ip IP adresa.
 * @return int
 */
function mndgroup_core_locked_until( $ip ) {
	$until = (int) get_transient( mndgroup_core_login_key( 'lock', $ip ) );
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
function mndgroup_core_authenticate( $user, $username ) {
	$ip    = mndgroup_core_client_ip();
	$until = mndgroup_core_locked_until( $ip );

	if ( $until ) {
		return new WP_Error(
			'mndgroup_core_locked',
			sprintf(
				/* translators: %d: minutes. */
				__( '<strong>Chyba:</strong> Příliš mnoho neúspěšných pokusů o přihlášení. Zkuste to znovu za %d min.', 'mndgroup-core' ),
				max( 1, (int) ceil( ( $until - time() ) / MINUTE_IN_SECONDS ) )
			)
		);
	}

	if ( is_wp_error( $user ) && array_intersect( $user->get_error_codes(), array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
		$fails = (int) get_transient( mndgroup_core_login_key( 'fail', $ip ) ) + 1;
		$left  = MNDGROUP_CORE_LOGIN_ATTEMPTS - $fails;
		$text  = __( '<strong>Chyba:</strong> Nesprávné uživatelské jméno, e-mail nebo heslo.', 'mndgroup-core' );
		if ( $left <= 0 ) {
			$text = __( '<strong>Chyba:</strong> Příliš mnoho neúspěšných pokusů o přihlášení. Přihlášení je dočasně zablokované.', 'mndgroup-core' );
		} elseif ( $left <= 2 ) {
			/* translators: %d: remaining attempts. */
			$text .= ' ' . sprintf( _n( 'Zbývá %d pokus.', 'Zbývají %d pokusy.', $left, 'mndgroup-core' ), $left );
		}
		return new WP_Error( 'mndgroup_core_failed', $text );
	}

	return $user;
}
add_filter( 'authenticate', 'mndgroup_core_authenticate', 99, 2 );

/**
 * Neúspěšný pokus: započítat a případně zablokovat.
 *
 * @param string $username Uživatelské jméno.
 */
function mndgroup_core_login_failed( $username ) {
	$ip = mndgroup_core_client_ip();
	if ( mndgroup_core_locked_until( $ip ) ) {
		return; // během blokace se nepočítá ani neprodlužuje
	}

	$fail_key = mndgroup_core_login_key( 'fail', $ip );
	$fails    = (int) get_transient( $fail_key ) + 1;
	mndgroup_core_log_login( 'failed', $username, $ip );

	if ( $fails < MNDGROUP_CORE_LOGIN_ATTEMPTS ) {
		set_transient( $fail_key, $fails, MNDGROUP_CORE_LOGIN_WINDOW );
		return;
	}

	// Blokace – každá další během 24 h je dvakrát delší.
	$count_key = mndgroup_core_login_key( 'count', $ip );
	$count     = (int) get_transient( $count_key ) + 1;
	$duration  = (int) min( MNDGROUP_CORE_LOCKOUT * pow( 2, $count - 1 ), MNDGROUP_CORE_LOCKOUT_MAX );

	set_transient( mndgroup_core_login_key( 'lock', $ip ), time() + $duration, $duration );
	set_transient( $count_key, $count, DAY_IN_SECONDS );
	delete_transient( $fail_key );
	mndgroup_core_log_login( 'lockout', $username, $ip, $duration );
}
add_action( 'wp_login_failed', 'mndgroup_core_login_failed' );

/**
 * Úspěšné přihlášení: vynulovat počítadlo a zapsat do záznamu.
 *
 * @param string $user_login Uživatelské jméno.
 */
function mndgroup_core_login_success( $user_login ) {
	$ip = mndgroup_core_client_ip();
	delete_transient( mndgroup_core_login_key( 'fail', $ip ) );
	mndgroup_core_log_login( 'success', $user_login, $ip );
}
add_action( 'wp_login', 'mndgroup_core_login_success' );

/**
 * Zápis do záznamu přihlášení (posledních MNDGROUP_CORE_LOG_MAX_ITEMS událostí, max. 90 dní).
 *
 * @param string $type     success|failed|lockout.
 * @param string $username Uživatelské jméno (jak bylo zadáno).
 * @param string $ip       IP adresa.
 * @param int    $duration Délka blokace v sekundách.
 */
function mndgroup_core_log_login( $type, $username, $ip, $duration = 0 ) {
	$log   = (array) get_option( MNDGROUP_CORE_LOG_OPTION, array() );
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
		array_slice( $log, 0, MNDGROUP_CORE_LOG_MAX_ITEMS ),
		function ( $row ) use ( $limit ) {
			return isset( $row['t'] ) && $row['t'] >= $limit;
		}
	);

	update_option( MNDGROUP_CORE_LOG_OPTION, array_values( $log ), false );
}

/**
 * Záznam přihlášení.
 *
 * @param int $limit Počet položek.
 * @return array
 */
function mndgroup_core_login_log( $limit = 50 ) {
	return array_slice( (array) get_option( MNDGROUP_CORE_LOG_OPTION, array() ), 0, $limit );
}

/**
 * Počet aktuálně zablokovaných IP adres.
 *
 * @return int
 */
function mndgroup_core_active_lockouts() {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value > %d",
			$wpdb->esc_like( '_transient_timeout_mndgroup_core_lock_' ) . '%',
			time()
		)
	);
}

/**
 * Zrušení všech blokací a počítadel.
 */
function mndgroup_core_clear_lockouts() {
	global $wpdb;
	foreach ( array( 'lock', 'count', 'fail' ) as $type ) {
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_mndgroup_core_' . $type . '_' ) . '%'
			)
		);
		foreach ( $names as $name ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) );
		}
	}
}
