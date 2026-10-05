<?php
/**
 * Widget „Zdraví webu“ na Nástěnce: verze a aktualizace, databáze, přihlášení, poslední údržba.
 * Velikost databáze se drží 12 h v transientu.
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

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
 * Velikost databáze (cache 12 h – dotaz do information_schema je na sdíleném hostingu pomalejší).
 *
 * @return int Bajty.
 */
function mnd_core_health_db_size() {
	$size = get_transient( 'mnd_core_health_db' );
	if ( false === $size ) {
		$size = mnd_core_db_size();
		set_transient( 'mnd_core_health_db', $size, 12 * HOUR_IN_SECONDS );
	}
	return (int) $size;
}

/**
 * Údržba webu zaznamená čas posledního spuštění akce.
 */
function mnd_core_health_mark_maintenance() {
	update_option( 'mnd_core_last_maintenance', time(), false );
	delete_transient( 'mnd_core_health_db' );
}

/**
 * Widget na Nástěnce.
 */
function mnd_core_dashboard_widget_register() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'mnd_core_status', __( 'Zdraví webu', 'mndgroup-core' ), 'mnd_core_dashboard_widget' );
	}
}
add_action( 'wp_dashboard_setup', 'mnd_core_dashboard_widget_register' );

/**
 * Obsah widgetu.
 */
function mnd_core_dashboard_widget() {
	$theme   = wp_get_theme( get_template() );
	$updates = wp_get_update_data();
	$log     = mnd_core_login_log( 200 );
	$day_ago = time() - DAY_IN_SECONDS;
	$failed  = count(
		array_filter(
			$log,
			function ( $row ) use ( $day_ago ) {
				return 'failed' === $row['e'] && $row['t'] >= $day_ago;
			}
		)
	);
	$last    = (int) get_option( 'mnd_core_last_maintenance', 0 );
	$logins  = array_slice(
		array_values(
			array_filter(
				$log,
				function ( $row ) {
					return 'success' === $row['e'];
				}
			)
		),
		0,
		5
	);
	?>
	<ul>
		<li><?php echo esc_html( sprintf( 'WordPress %s · PHP %s · %s %s · MND Group Core %s', get_bloginfo( 'version' ), PHP_VERSION, $theme->get( 'Name' ), $theme->get( 'Version' ), MND_CORE_VERSION ) ); ?></li>
		<li>
			<?php
			if ( $updates['counts']['total'] ) {
				$total = (int) $updates['counts']['total'];
				echo '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">' . esc_html( sprintf( mnd_core_plural( $total, __( 'Čeká %d aktualizace', 'mndgroup-core' ), __( 'Čekají %d aktualizace', 'mndgroup-core' ), __( 'Čeká %d aktualizací', 'mndgroup-core' ) ), $total ) ) . '</a>';
			} else {
				esc_html_e( 'Vše je aktuální.', 'mndgroup-core' );
			}
			?>
		</li>
		<li><?php echo esc_html( __( 'Databáze:', 'mndgroup-core' ) . ' ' . size_format( mnd_core_health_db_size(), 1 ) ); ?></li>
		<li>
			<?php
			echo esc_html(
				__( 'Poslední údržba:', 'mndgroup-core' ) . ' ' . ( $last ? mnd_core_datetime( $last ) : __( 'zatím nikdy', 'mndgroup-core' ) )
			);
			?>
		</li>
		<li>
			<?php
			/* translators: 1: failed attempts, 2: blocked IPs. */
			echo esc_html( sprintf( __( 'Neúspěšná přihlášení za 24 h: %1$d · zablokované IP: %2$d', 'mndgroup-core' ), $failed, mnd_core_active_lockouts() ) );
			?>
		</li>
	</ul>
	<?php if ( $logins ) : ?>
		<p><strong><?php esc_html_e( 'Poslední přihlášení', 'mndgroup-core' ); ?></strong></p>
		<ul>
			<?php foreach ( $logins as $row ) : ?>
				<li><?php echo esc_html( mnd_core_datetime( $row['t'] ) . ' · ' . $row['u'] ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<p><a href="<?php echo esc_url( mnd_core_maintenance_url() ); ?>"><?php esc_html_e( 'Údržba webu →', 'mndgroup-core' ); ?></a></p>
	<?php
}
