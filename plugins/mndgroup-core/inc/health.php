<?php
/**
 * Widget „Zdraví webu“ na Nástěnce: verze a aktualizace, databáze, obsah, přihlášení, poslední údržba.
 * Dražší čísla (velikost databáze, autoload a uploads) se drží 12 h v transientu, tlačítko Obnovit je přepočítá.
 * Verze a aktualizace se berou z mezipaměti WordPressu – nic se tu nestahuje.
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

const MND_CORE_HEALTH_TTL = 12 * HOUR_IN_SECONDS;

/**
 * Český tvar podle počtu (1 / 2–4 / 5+).
 *
 * @param int    $n    Počet.
 * @param string $one  Tvar pro 1.
 * @param string $few  Tvar pro 2–4.
 * @param string $many Tvar pro 0 a 5+.
 * @return string
 */
function mnd_core_plural( $n, $one, $few, $many ) {
	if ( 1 === $n ) {
		return $one;
	}
	return ( $n >= 2 && $n <= 4 ) ? $few : $many;
}

/**
 * Údržba webu zaznamená čas posledního spuštění akce.
 */
function mnd_core_health_mark_maintenance() {
	update_option( 'mnd_core_last_maintenance', time(), false );
	delete_transient( 'mnd_core_health' );
}

/**
 * Velikost databáze, autoload a uploads (cache 12 h).
 *
 * @param bool $refresh Přepočítat.
 * @return array [db, autoload, uploads, at]
 */
function mnd_core_health_heavy( $refresh = false ) {
	$data = $refresh ? false : get_transient( 'mnd_core_health' );
	if ( is_array( $data ) ) {
		return $data;
	}
	$autoload = mnd_core_db_autoload();
	$uploads  = wp_get_upload_dir();
	@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$data = array(
		'db'       => mnd_core_db_size(),
		'autoload' => (int) $autoload['total'],
		'uploads'  => ! empty( $uploads['basedir'] ) ? mnd_core_path_size( $uploads['basedir'] ) : 0,
		'at'       => time(),
	);
	set_transient( 'mnd_core_health', $data, MND_CORE_HEALTH_TTL );
	return $data;
}

/**
 * Registrace widgetu (jen administrátorům).
 */
function mnd_core_dashboard_widget_register() {
	if ( current_user_can( 'manage_options' ) && mnd_core_on( 'health_widget' ) ) {
		wp_add_dashboard_widget( 'mnd_core_status', __( 'Zdraví webu', 'mndgroup-core' ), 'mnd_core_dashboard_widget', null, null, 'normal', 'high' );
	}
}
add_action( 'wp_dashboard_setup', 'mnd_core_dashboard_widget_register' );

/**
 * Obsah widgetu.
 */
function mnd_core_dashboard_widget() {
	$refresh = isset( $_GET['mnd_core_health_refresh'] ) && check_admin_referer( 'mnd_core_health_refresh' );
	$heavy   = mnd_core_health_heavy( $refresh );

	// Verze a dostupné aktualizace (z mezipaměti WordPressu).
	$theme       = wp_get_theme( get_template() );
	$upd_themes  = get_site_transient( 'update_themes' );
	$upd_plugins = get_site_transient( 'update_plugins' );
	$theme_new   = is_object( $upd_themes ) && isset( $upd_themes->response[ get_template() ]['new_version'] ) ? (string) $upd_themes->response[ get_template() ]['new_version'] : '';
	$plugin_obj  = is_object( $upd_plugins ) && isset( $upd_plugins->response[ MND_CORE_BASENAME ] ) ? $upd_plugins->response[ MND_CORE_BASENAME ] : null;
	$plugin_new  = is_object( $plugin_obj ) && isset( $plugin_obj->new_version ) ? (string) $plugin_obj->new_version : '';
	if ( ! function_exists( 'get_core_updates' ) ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}
	$wp_upd  = get_core_updates();
	$wp_new  = ( is_array( $wp_upd ) && isset( $wp_upd[0]->response ) && 'upgrade' === $wp_upd[0]->response ) ? (string) $wp_upd[0]->current : '';
	$updates = wp_get_update_data();

	// Obsah a přihlášení.
	$drafts   = (int) wp_count_posts( 'page' )->draft + (int) wp_count_posts( 'post' )->draft;
	$comments = wp_count_comments();
	$pending  = (int) $comments->moderated;
	$log      = mnd_core_login_log( 200 );
	$day_ago  = time() - DAY_IN_SECONDS;
	$failed   = 0;
	$last     = null;
	foreach ( $log as $row ) {
		if ( 'failed' === $row['e'] && $row['t'] >= $day_ago ) {
			$failed++;
		}
		if ( ! $last && 'success' === $row['e'] ) {
			$last = $row;
		}
	}
	$maint = (int) get_option( 'mnd_core_last_maintenance', 0 );

	$ok   = '<span style="color:#1a7f37">●</span>';
	$warn = '<span style="color:#bf8700">●</span>';
	$new  = function ( $version ) use ( $ok, $warn ) {
		/* translators: %s: version. */
		return $version ? ' ' . $warn . ' <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">' . esc_html( sprintf( __( 'k dispozici %s', 'mndgroup-core' ), $version ) ) . '</a>' : ' ' . $ok;
	};

	$others = max( 0, (int) $updates['counts']['total'] - ( $wp_new ? 1 : 0 ) - ( $theme_new ? 1 : 0 ) - ( $plugin_new ? 1 : 0 ) );
	$rows   = array(
		__( 'Verze', 'mndgroup-core' )      => array(
			$theme->get( 'Name' )                      => esc_html( $theme->get( 'Version' ) ) . $new( $theme_new ),
			'MND Group Core'                           => esc_html( MND_CORE_VERSION ) . $new( $plugin_new ),
			'WordPress'                                => esc_html( get_bloginfo( 'version' ) ) . $new( $wp_new ),
			'PHP'                                      => esc_html( PHP_VERSION ),
			__( 'Další aktualizace', 'mndgroup-core' ) => $others ? $warn . ' <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">' . esc_html( sprintf( mnd_core_plural( $others, __( 'čeká %d', 'mndgroup-core' ), __( 'čekají %d', 'mndgroup-core' ), __( 'čeká %d', 'mndgroup-core' ) ), $others ) ) . '</a>' : '0 ' . $ok,
		),
		__( 'Data', 'mndgroup-core' )       => array(
			__( 'Databáze', 'mndgroup-core' ) => esc_html( size_format( $heavy['db'], 1 ) ),
			__( 'Autoload', 'mndgroup-core' ) => esc_html( size_format( $heavy['autoload'], 1 ) ) . ( $heavy['autoload'] > MB_IN_BYTES ? ' ' . $warn . ' ' . esc_html__( 'víc než 1 MB', 'mndgroup-core' ) : ' ' . $ok ),
			__( 'Uploads', 'mndgroup-core' )  => esc_html( size_format( $heavy['uploads'], 1 ) ),
		),
		__( 'Obsah', 'mndgroup-core' )      => array(
			__( 'Koncepty', 'mndgroup-core' ) => (string) $drafts,
		),
		__( 'Přihlášení', 'mndgroup-core' ) => array(
			__( 'Neúspěšné za 24 h', 'mndgroup-core' )   => (string) $failed . ( $failed > 20 ? ' ' . $warn : '' ),
			__( 'Zablokované IP', 'mndgroup-core' )      => (string) mnd_core_active_lockouts(),
			__( 'Poslední přihlášení', 'mndgroup-core' ) => $last ? esc_html( mnd_core_datetime( $last['t'] ) . ' · ' . $last['u'] ) : '—',
		),
		__( 'Údržba', 'mndgroup-core' )     => array(
			__( 'Naposledy', 'mndgroup-core' ) => $maint ? esc_html( mnd_core_datetime( $maint ) . ' (' . human_time_diff( $maint ) . ')' ) : esc_html__( 'zatím nezaznamenáno', 'mndgroup-core' ),
		),
	);
	if ( ! mnd_core_on( 'comments_off' ) ) {
		$rows[ __( 'Obsah', 'mndgroup-core' ) ][ __( 'Komentáře ke schválení', 'mndgroup-core' ) ] = $pending ? $warn . ' <a href="' . esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) ) . '">' . (int) $pending . '</a>' : '0 ' . $ok;
	}

	echo '<table class="widefat striped" style="border:0;box-shadow:none"><tbody>';
	foreach ( $rows as $group => $items ) {
		printf( '<tr><th colspan="2" style="padding-top:10px;font-weight:600">%s</th></tr>', esc_html( $group ) );
		foreach ( $items as $label => $value ) {
			printf( '<tr><td style="width:55%%">%s</td><td>%s</td></tr>', esc_html( $label ), $value ); // phpcs:ignore WordPress.Security.EscapeOutput -- hodnoty escapované výše
		}
	}
	echo '</tbody></table>';
	printf(
		'<p style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin:12px 0 0"><span><a class="button" href="%1$s">%2$s</a> <a href="%3$s">%4$s</a></span><span style="color:#646970">%5$s · <a href="%6$s">%7$s</a></span></p>',
		esc_url( mnd_core_maintenance_url() ),
		esc_html__( 'Údržba webu', 'mndgroup-core' ),
		esc_url( mnd_core_settings_url() ),
		esc_html__( 'Nastavení', 'mndgroup-core' ),
		/* translators: %s: date and time. */
		esc_html( sprintf( __( 'Velikosti k %s', 'mndgroup-core' ), wp_date( 'j. n. H:i', $heavy['at'] ) ) ),
		esc_url( wp_nonce_url( admin_url( 'index.php?mnd_core_health_refresh=1' ), 'mnd_core_health_refresh' ) ),
		esc_html__( 'Obnovit', 'mndgroup-core' )
	);
}
