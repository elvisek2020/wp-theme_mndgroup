<?php
/**
 * Údržba webu — Nástroje → Údržba webu.
 *
 * Přehled systému, úklid databáze (revize, koncepty, koš, transienty, osiřelá metadata),
 * nepoužitá média (jen ke kontrole, nic se nemaže samo) a záznam přihlášení s rušením blokací.
 * Načítá inc/core.php.
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

const MND_CORE_MAINTENANCE_PAGE = 'mndgroup-core-maintenance';

/**
 * Úklidové úlohy: klíč => [název, popis].
 *
 * @return array
 */
function mnd_core_cleanup_tasks() {
	return array(
		'revisions'   => array( __( 'Revize stránek a příspěvků', 'mndgroup-core' ), __( 'Starší uložené verze obsahu. Aktuální obsah zůstane.', 'mndgroup-core' ) ),
		'autodrafts'  => array( __( 'Automatické koncepty', 'mndgroup-core' ), __( 'Nedokončené koncepty, které WordPress vytvoří při otevření editoru.', 'mndgroup-core' ) ),
		'trash'       => array( __( 'Koš', 'mndgroup-core' ), __( 'Stránky, příspěvky a slidy v koši – smažou se natrvalo.', 'mndgroup-core' ) ),
		'transients'  => array( __( 'Prošlé dočasné záznamy', 'mndgroup-core' ), __( 'Expirovaná mezipaměť (transienty) v databázi.', 'mndgroup-core' ) ),
		'orphan_meta' => array( __( 'Osiřelá metadata', 'mndgroup-core' ), __( 'Metadata obsahu, který už neexistuje.', 'mndgroup-core' ) ),
	);
}

/**
 * Počet položek pro úlohu.
 *
 * @param string $task Klíč úlohy.
 * @return int
 */
function mnd_core_cleanup_count( $task ) {
	global $wpdb;
	switch ( $task ) {
		case 'revisions':
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
		case 'autodrafts':
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
		case 'trash':
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
		case 'transients':
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->options} WHERE ( option_name LIKE %s OR option_name LIKE %s ) AND option_value < %d",
					$wpdb->esc_like( '_transient_timeout_' ) . '%',
					$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
					time()
				)
			);
		case 'orphan_meta':
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
	}
	return 0;
}

/**
 * Provedení úlohy (po dávkách, aby nevypršel čas na sdíleném hostingu).
 *
 * @param string $task Klíč úlohy.
 * @return int Počet odstraněných položek.
 */
function mnd_core_cleanup_run( $task ) {
	global $wpdb;
	$done = 0;

	switch ( $task ) {
		case 'revisions':
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT 500" );
			foreach ( $ids as $id ) {
				$done += wp_delete_post_revision( (int) $id ) ? 1 : 0;
			}
			break;
		case 'autodrafts':
		case 'trash':
			$status = 'trash' === $task ? 'trash' : 'auto-draft';
			$ids    = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = %s LIMIT 500", $status ) );
			foreach ( $ids as $id ) {
				$done += wp_delete_post( (int) $id, true ) ? 1 : 0;
			}
			break;
		case 'transients':
			$done = mnd_core_cleanup_count( 'transients' );
			delete_expired_transients( true );
			break;
		case 'orphan_meta':
			$done = (int) $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
			break;
	}

	return $done;
}

/**
 * Média, která nejsou nikde použitá (není u nich nadřazený obsah, nejsou náhledovým
 * obrázkem, logem ani ikonou webu a jejich soubor není zmíněný v žádném obsahu).
 *
 * @return WP_Post[]
 */
function mnd_core_unused_media() {
	global $wpdb;

	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 500,
			'no_found_rows'  => true,
		)
	);

	$featured = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'" ) );
	$special  = array_filter( array( (int) get_option( 'site_icon' ), (int) get_theme_mod( 'custom_logo' ) ) );
	$unused   = array();

	foreach ( $attachments as $attachment ) {
		if ( in_array( $attachment->ID, $featured, true ) || in_array( $attachment->ID, $special, true ) ) {
			continue;
		}
		if ( $attachment->post_parent && get_post_status( $attachment->post_parent ) ) {
			continue;
		}
		$file = get_post_meta( $attachment->ID, '_wp_attached_file', true );
		$name = $file ? pathinfo( $file, PATHINFO_FILENAME ) : '';
		if ( $name ) {
			$used = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type NOT IN ( 'revision', 'attachment' ) AND post_content LIKE %s",
					'%' . $wpdb->esc_like( $name ) . '%'
				)
			);
			if ( $used ) {
				continue;
			}
		}
		$unused[] = $attachment;
	}

	return $unused;
}

/**
 * Velikost tabulek webu v databázi (bajty).
 *
 * @return int
 */
function mnd_core_db_size() {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT SUM( data_length + index_length ) FROM information_schema.TABLES WHERE table_schema = %s AND table_name LIKE %s',
			DB_NAME,
			$wpdb->esc_like( $wpdb->prefix ) . '%'
		)
	);
}

/**
 * Stránka v menu Nástroje.
 */
function mnd_core_maintenance_menu() {
	add_management_page(
		__( 'Údržba webu', 'mndgroup-core' ),
		__( 'Údržba webu', 'mndgroup-core' ),
		'manage_options',
		MND_CORE_MAINTENANCE_PAGE,
		'mnd_core_maintenance_page'
	);
}
add_action( 'admin_menu', 'mnd_core_maintenance_menu' );

/**
 * URL stránky údržby.
 *
 * @param array $args Parametry.
 * @return string
 */
function mnd_core_maintenance_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => MND_CORE_MAINTENANCE_PAGE ), $args ), admin_url( 'tools.php' ) );
}

/**
 * Zpracování formulářů (admin-post.php).
 */
function mnd_core_maintenance_action() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce se ověřuje níže podle úlohy.
	$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
	check_admin_referer( 'mnd_core_maintenance_' . $task );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Na tuto akci nemáte oprávnění.', 'mndgroup-core' ), 403 );
	}

	if ( 'unlock' === $task ) {
		mnd_core_clear_lockouts();
		$done = 1;
	} elseif ( array_key_exists( $task, mnd_core_cleanup_tasks() ) ) {
		// Počet se ověří znovu na serveru – formulář mohl být otevřený dlouho.
		$done = mnd_core_cleanup_count( $task ) ? mnd_core_cleanup_run( $task ) : 0;
	} else {
		wp_die( esc_html__( 'Neznámá úloha.', 'mndgroup-core' ), 400 );
	}

	mnd_core_health_mark_maintenance();

	wp_safe_redirect(
		mnd_core_maintenance_url(
			array(
				'done'  => $task,
				'count' => $done,
			)
		)
	);
	exit;
}
add_action( 'admin_post_mnd_core_maintenance', 'mnd_core_maintenance_action' );

/**
 * Formulář s jedním tlačítkem a potvrzením.
 *
 * @param string $task    Úloha.
 * @param string $label   Text tlačítka.
 * @param string $confirm Potvrzovací otázka.
 * @param bool   $primary Zvýrazněné tlačítko.
 */
function mnd_core_action_button( $task, $label, $confirm, $primary = false ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm(<?php echo esc_attr( wp_json_encode( $confirm ) ); ?>);" style="margin:0">
		<input type="hidden" name="action" value="mnd_core_maintenance">
		<input type="hidden" name="task" value="<?php echo esc_attr( $task ); ?>">
		<?php wp_nonce_field( 'mnd_core_maintenance_' . $task ); ?>
		<button type="submit" class="button<?php echo $primary ? ' button-primary' : ''; ?>"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

/**
 * Formát data a času podle nastavení webu.
 *
 * @param int $timestamp Unix čas.
 * @return string
 */
function mnd_core_datetime( $timestamp ) {
	return wp_date( get_option( 'date_format' ) . ' H:i', (int) $timestamp );
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
			return __( 'Přihlášení', 'mndgroup-core' );
		case 'failed':
			return __( 'Neúspěšný pokus', 'mndgroup-core' );
		case 'lockout':
			/* translators: %d: minutes. */
			return sprintf( __( 'Zablokováno na %d min', 'mndgroup-core' ), (int) round( ( isset( $row['d'] ) ? $row['d'] : 0 ) / MINUTE_IN_SECONDS ) );
	}
	return '';
}

/**
 * Stránka Nástroje → Údržba webu.
 */
function mnd_core_maintenance_page() {
	global $wpdb;

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tasks = mnd_core_cleanup_tasks();
	$theme = wp_get_theme( get_template() );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- jen zobrazení výsledku.
	$done  = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
	$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
	// phpcs:enable
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Údržba webu', 'mndgroup-core' ); ?></h1>
		<p><a href="<?php echo esc_url( mnd_core_settings_url() ); ?>"><?php esc_html_e( 'Nastavení funkcí pluginu MND Group Core →', 'mndgroup-core' ); ?></a></p>

		<?php if ( 'unlock' === $done ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Všechny blokace přihlášení byly zrušeny.', 'mndgroup-core' ); ?></p></div>
		<?php elseif ( isset( $tasks[ $done ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				/* translators: 1: task name, 2: number of items. */
				echo esc_html( sprintf( __( '%1$s: odstraněno položek: %2$d.', 'mndgroup-core' ), $tasks[ $done ][0], $count ) );
				?>
			</p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Přehled', 'mndgroup-core' ); ?></h2>
		<table class="widefat striped" style="max-width:820px">
			<tbody>
				<tr><th><?php esc_html_e( 'WordPress', 'mndgroup-core' ); ?></th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Šablona', 'mndgroup-core' ); ?></th><td><?php echo esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Plugin MND Group Core', 'mndgroup-core' ); ?></th><td><?php echo esc_html( MND_CORE_VERSION ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Aktualizace', 'mndgroup-core' ); ?></th><td><?php esc_html_e( 'WordPress, pluginy a překlady automaticky; šablona a plugin MND ručně z GitHub Releases (Nástěnka → Aktualizace).', 'mndgroup-core' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'PHP', 'mndgroup-core' ); ?></th><td><?php echo esc_html( PHP_VERSION . ' · memory_limit ' . ini_get( 'memory_limit' ) . ' · upload ' . size_format( wp_max_upload_size() ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Databáze', 'mndgroup-core' ); ?></th><td><?php echo esc_html( $wpdb->db_server_info() . ' · ' . size_format( mnd_core_db_size(), 1 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Prostředí', 'mndgroup-core' ); ?></th><td><?php echo esc_html( wp_get_environment_type() ); ?></td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Úklid databáze', 'mndgroup-core' ); ?></h2>
		<p><?php esc_html_e( 'Před větším úklidem je dobré mít zálohu databáze (WEDOS administrace → zálohy).', 'mndgroup-core' ); ?></p>
		<table class="widefat striped" style="max-width:820px">
			<tbody>
				<?php foreach ( $tasks as $key => $task ) : ?>
					<?php $items = mnd_core_cleanup_count( $key ); ?>
					<tr>
						<td><strong><?php echo esc_html( $task[0] ); ?></strong><br><span class="description"><?php echo esc_html( $task[1] ); ?></span></td>
						<td style="width:90px;text-align:right"><?php echo esc_html( number_format_i18n( $items ) ); ?></td>
						<td style="width:120px">
							<?php
							if ( $items ) {
								/* translators: 1: number of items, 2: task name. */
								mnd_core_action_button( $key, __( 'Smazat', 'mndgroup-core' ), sprintf( __( 'Opravdu smazat %1$d položek (%2$s)? Akce je nevratná.', 'mndgroup-core' ), $items, $task[0] ) );
							} else {
								echo '<span class="description">' . esc_html__( 'Není co mazat', 'mndgroup-core' ) . '</span>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Nepoužitá média', 'mndgroup-core' ); ?></h2>
		<?php $unused = mnd_core_unused_media(); ?>
		<?php if ( $unused ) : ?>
			<p><?php esc_html_e( 'Soubory, které nejsou použité v žádném obsahu, jako náhledový obrázek, logo ani ikona webu. Nic se nemaže automaticky – před smazáním v Knihovně médií je zkontrolujte (mohou být odkazované zvenku).', 'mndgroup-core' ); ?></p>
			<ul style="list-style:disc;padding-left:1.5em">
				<?php foreach ( array_slice( $unused, 0, 50 ) as $attachment ) : ?>
					<li><a href="<?php echo esc_url( get_edit_post_link( $attachment->ID ) ); ?>"><?php echo esc_html( basename( (string) get_attached_file( $attachment->ID ) ) ); ?></a> <span class="description">(<?php echo esc_html( mnd_core_datetime( strtotime( $attachment->post_date_gmt . ' UTC' ) ) ); ?>)</span></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'Všechna média jsou použitá.', 'mndgroup-core' ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Přihlášení', 'mndgroup-core' ); ?></h2>
		<p>
			<?php
			$lockouts = mnd_core_active_lockouts();
			if ( mnd_core_on( 'login_protection' ) ) {
				/* translators: 1: attempts, 2: number of blocked IP addresses. */
				echo esc_html( sprintf( __( 'Po %1$d neúspěšných pokusech se IP adresa dočasně zablokuje. Aktuálně zablokováno: %2$d.', 'mndgroup-core' ), mnd_core_login_attempts(), $lockouts ) );
			} else {
				esc_html_e( 'Omezení pokusů o přihlášení je vypnuté (Nastavení → MND Group Core).', 'mndgroup-core' );
			}
			if ( ! mnd_core_on( 'login_log' ) ) {
				echo ' ' . esc_html__( 'Log přihlášení je vypnutý – níže jsou jen starší záznamy.', 'mndgroup-core' );
			}
			?>
		</p>
		<?php if ( $lockouts ) : ?>
			<?php mnd_core_action_button( 'unlock', __( 'Zrušit všechny blokace', 'mndgroup-core' ), __( 'Zrušit všechny blokace přihlášení?', 'mndgroup-core' ) ); ?>
		<?php endif; ?>
		<?php $log = mnd_core_login_log( 50 ); ?>
		<?php if ( $log ) : ?>
			<table class="widefat striped" style="max-width:820px;margin-top:1em">
				<thead><tr><th><?php esc_html_e( 'Čas', 'mndgroup-core' ); ?></th><th><?php esc_html_e( 'Událost', 'mndgroup-core' ); ?></th><th><?php esc_html_e( 'Uživatel', 'mndgroup-core' ); ?></th><th><?php esc_html_e( 'IP adresa', 'mndgroup-core' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $log as $row ) : ?>
						<tr>
							<td><?php echo esc_html( mnd_core_datetime( $row['t'] ) ); ?></td>
							<td><?php echo esc_html( mnd_core_login_event_label( $row ) ); ?></td>
							<td><?php echo esc_html( $row['u'] ); ?></td>
							<td><code><?php echo esc_html( $row['i'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Uchovává se posledních 200 událostí, nejvýše 90 dní.', 'mndgroup-core' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Zatím žádné záznamy.', 'mndgroup-core' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

