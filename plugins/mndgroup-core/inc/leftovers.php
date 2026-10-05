<?php
/**
 * Pozůstatky odebraných pluginů a staré šablony – sekce v Nástroje → Údržba webu.
 *
 * Ukáže jen to, co na webu opravdu zůstalo a patří pluginu, který není aktivní
 * (tabulky, volby, složky). Akce „Do karantény“ nic nemaže:
 *  - složky se přesunou do karantény (mimo webový kořen, pokud to hosting dovolí),
 *  - volby se uloží do JSON souboru v karanténě a pak odeberou,
 *  - tabulky se přejmenují na {prefix}mndq_… (data zůstanou v databázi).
 * Trvalé smazání karantény je samostatné tlačítko – rozhoduje správce webu.
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

const MND_CORE_QUARANTINE_TABLE = 'mndq_';

/**
 * Známé pozůstatky: slug => [název, plugin nebo šablona, tabulky, volby, cesty].
 * Vzory: % = cokoli. Cesty relativně k wp-content (glob).
 *
 * @return array
 */
function mnd_core_leftover_defs() {
	return array(
		'aios'          => array(
			'name'    => 'All-In-One Security',
			'plugin'  => 'all-in-one-wp-security-and-firewall/wp-security.php',
			'tables'  => array( 'aiowps_%' ),
			'options' => array( 'aio_wp_security_configs', 'aiowpsec_%', 'aios_%', 'updraft_lock_aios_%' ),
			'paths'   => array( 'aiowps_backups', 'uploads/aios' ),
		),
		'simple-login-log' => array(
			'name'    => 'Simple Login Log',
			'plugin'  => 'simple-login-log/simple-login-log.php',
			'tables'  => array( 'simple_login_log' ),
			'options' => array( 'sll_%', 'simple_login_log%' ),
			'paths'   => array(),
		),
		'backupwordpress' => array(
			'name'    => 'BackUpWordPress',
			'plugin'  => 'backupwordpress/backupwordpress.php',
			'tables'  => array(),
			'options' => array( 'hmbkp_%', '_transient_hmbkp_%', '_transient_timeout_hmbkp_%' ),
			'paths'   => array( 'backupwordpress-*-backups' ),
		),
		'monsterinsights' => array(
			'name'    => 'MonsterInsights',
			'plugin'  => 'google-analytics-for-wordpress/googleanalytics.php',
			'tables'  => array( 'monsterinsights_%' ),
			'options' => array( 'monsterinsights_%', '_transient_monsterinsights_%', '_transient_timeout_monsterinsights_%', '_transient__monsterinsights_%', '_transient_timeout__monsterinsights_%', '_site_transient_monsterinsights_%', '_site_transient_timeout_monsterinsights_%' ),
			'paths'   => array(),
		),
		'lightbox'      => array(
			'name'    => 'Huge IT Lightbox',
			'plugin'  => 'lightbox/lightbox.php',
			'tables'  => array(),
			'options' => array( 'hugeit_lightbox_%', 'lightbox_open_close_effect' ),
			'paths'   => array(),
		),
		'admin-menu-editor' => array(
			'name'    => 'Admin Menu Editor',
			'plugin'  => 'admin-menu-editor/menu-editor.php',
			'tables'  => array(),
			'options' => array( 'ws_menu_editor', 'ws_menu_editor_%', 'ws_ame_%' ),
			'paths'   => array(),
		),
		'admin-columns' => array(
			'name'    => 'Admin Columns',
			'plugin'  => 'codepress-admin-columns/codepress-admin-columns.php',
			'tables'  => array( 'admin_columns' ),
			'options' => array( 'cpac_%', 'ac_version', 'ac_capabilities_set' ),
			'paths'   => array(),
		),
		'scpo'          => array(
			'name'    => 'Simple Custom Post Order',
			'plugin'  => 'simple-custom-post-order/simple-custom-post-order.php',
			'tables'  => array(),
			'options' => array( 'scporder_%' ),
			'paths'   => array(),
		),
		'zalomeni'      => array(
			'name'    => 'Zalomení',
			'plugin'  => 'zalomeni/zalomeni.php',
			'tables'  => array(),
			'options' => array( 'zalomeni_%' ),
			'paths'   => array(),
		),
		'w3tc'          => array(
			'name'    => 'W3 Total Cache',
			'plugin'  => 'w3-total-cache/w3-total-cache.php',
			'tables'  => array(),
			'options' => array( 'w3tc_%' ),
			'paths'   => array( 'w3tc-config', 'cache' ),
		),
		'old-theme'     => array(
			'name'    => __( 'Původní šablona DP Enigmatic for MND Group', 'mndgroup-core' ),
			'theme'   => 'enigmatic-for-mndgroup',
			'tables'  => array(),
			'options' => array( 'theme_mods_enigmatic-for-mndgroup', 'dp-enigmatic-options' ),
			'paths'   => array( 'themes/enigmatic-for-mndgroup' ),
		),
	);
}

/**
 * Je plugin nebo šablona pozůstatku ještě v provozu?
 *
 * @param array $def Definice.
 * @return bool
 */
function mnd_core_leftover_in_use( $def ) {
	if ( ! empty( $def['plugin'] ) ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $def['plugin'] );
	}
	if ( ! empty( $def['theme'] ) ) {
		return in_array( $def['theme'], array( get_template(), get_stylesheet() ), true );
	}
	return false;
}

/**
 * Co z pozůstatku na webu skutečně je.
 *
 * @param array $def Definice.
 * @return array [tables => [název => bajty], options => [název => bajty], paths => [cesta => bajty]]
 */
function mnd_core_leftover_scan( $def ) {
	global $wpdb;
	$found = array(
		'tables'  => array(),
		'options' => array(),
		'paths'   => array(),
	);

	foreach ( $def['tables'] as $pattern ) {
		$like = $wpdb->esc_like( $wpdb->prefix ) . str_replace( '\%', '%', $wpdb->esc_like( $pattern ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT table_name AS t, data_length + index_length AS b FROM information_schema.tables WHERE table_schema = %s AND table_name LIKE %s',
				DB_NAME,
				$like
			)
		);
		foreach ( (array) $rows as $row ) {
			if ( 0 !== strpos( $row->t, $wpdb->prefix . MND_CORE_QUARANTINE_TABLE ) ) {
				$found['tables'][ $row->t ] = (int) $row->b;
			}
		}
	}

	foreach ( $def['options'] as $pattern ) {
		$like = str_replace( '\%', '%', $wpdb->esc_like( $pattern ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name AS n, LENGTH(option_value) AS b FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		foreach ( (array) $rows as $row ) {
			$found['options'][ $row->n ] = (int) $row->b;
		}
	}

	foreach ( $def['paths'] as $pattern ) {
		foreach ( (array) glob( WP_CONTENT_DIR . '/' . $pattern, GLOB_ONLYDIR ) as $path ) {
			if ( $path && is_dir( $path ) ) {
				$found['paths'][ $path ] = mnd_core_path_size( $path );
			}
		}
	}
	return $found;
}

/**
 * Přesun pozůstatku do karantény.
 *
 * @param string $slug Klíč definice.
 * @return string Zpráva.
 */
function mnd_core_leftover_quarantine( $slug ) {
	global $wpdb;
	$defs = mnd_core_leftover_defs();
	if ( ! isset( $defs[ $slug ] ) ) {
		return __( 'Neznámá položka.', 'mndgroup-core' );
	}
	$def = $defs[ $slug ];
	if ( mnd_core_leftover_in_use( $def ) ) {
		return __( 'Plugin nebo šablona je pořád aktivní – nejdřív ji vypněte.', 'mndgroup-core' );
	}

	$found = mnd_core_leftover_scan( $def );
	$dir   = mnd_core_quarantine_dir( wp_date( 'Y-m-d' ) . '/' . $slug );
	if ( is_wp_error( $dir ) ) {
		return $dir->get_error_message();
	}
	$done = array();

	// Volby: nejdřív záloha do JSON, pak odebrat.
	if ( $found['options'] ) {
		$backup = array();
		foreach ( array_keys( $found['options'] ) as $name ) {
			$backup[ $name ] = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		}
		$file = $dir . 'options-' . time() . '.json';
		if ( false === file_put_contents( $file, wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return __( 'Zálohu voleb se nepodařilo uložit – nic se nezměnilo.', 'mndgroup-core' );
		}
		foreach ( array_keys( $backup ) as $name ) {
			delete_option( $name );
		}
		/* translators: %d: number of options. */
		$done[] = sprintf( __( 'voleb: %d', 'mndgroup-core' ), count( $backup ) );
	}

	// Tabulky: přejmenovat do karantény.
	$renamed = 0;
	foreach ( array_keys( $found['tables'] ) as $table ) {
		$target = $wpdb->prefix . MND_CORE_QUARANTINE_TABLE . substr( $table, strlen( $wpdb->prefix ) );
		if ( false !== $wpdb->query( 'RENAME TABLE `' . esc_sql( $table ) . '` TO `' . esc_sql( substr( $target, 0, 64 ) ) . '`' ) ) {
			$renamed++;
		}
	}
	if ( $renamed ) {
		/* translators: %d: number of tables. */
		$done[] = sprintf( __( 'tabulek: %d', 'mndgroup-core' ), $renamed );
	}

	// Složky: přesunout.
	$moved  = 0;
	$failed = array();
	foreach ( array_keys( $found['paths'] ) as $path ) {
		if ( mnd_core_quarantine_move( $path, $slug ) ) {
			$moved++;
		} else {
			$failed[] = str_replace( WP_CONTENT_DIR, 'wp-content', $path );
		}
	}
	if ( $moved ) {
		/* translators: %d: number of folders. */
		$done[] = sprintf( __( 'složek: %d', 'mndgroup-core' ), $moved );
	}

	/* translators: 1: item name, 2: what was moved. */
	$msg = sprintf( __( '%1$s – do karantény přesunuto: %2$s.', 'mndgroup-core' ), $def['name'], $done ? implode( ', ', $done ) : __( 'nic', 'mndgroup-core' ) );
	if ( $failed ) {
		$msg .= ' ' . __( 'Nepodařilo se přesunout:', 'mndgroup-core' ) . ' ' . implode( ', ', $failed ) . '.';
	}
	return $msg;
}

/**
 * Obsah karantény: složky (soubory) a přejmenované tabulky.
 *
 * @return array [dir => cesta|null, bytes => int, tables => [název => bajty]]
 */
function mnd_core_quarantine_contents() {
	global $wpdb;
	$outside = dirname( untrailingslashit( ABSPATH ) ) . '/mndgroup-karantena';
	$inside  = WP_CONTENT_DIR . '/mndgroup-karantena';
	$dir     = is_dir( $outside ) ? $outside : ( is_dir( $inside ) ? $inside : null );
	$bytes   = $dir ? mnd_core_path_size( $dir ) : 0;
	$tables  = array();
	$rows    = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT table_name AS t, data_length + index_length AS b FROM information_schema.tables WHERE table_schema = %s AND table_name LIKE %s',
			DB_NAME,
			$wpdb->esc_like( $wpdb->prefix . MND_CORE_QUARANTINE_TABLE ) . '%'
		)
	);
	foreach ( (array) $rows as $row ) {
		$tables[ $row->t ] = (int) $row->b;
	}
	return array(
		'dir'    => $dir,
		'bytes'  => $bytes,
		'tables' => $tables,
	);
}

/**
 * Trvalé smazání karantény (soubory i tabulky).
 *
 * @return string Zpráva.
 */
function mnd_core_quarantine_purge() {
	global $wpdb;
	$q = mnd_core_quarantine_contents();
	foreach ( array_keys( $q['tables'] ) as $table ) {
		if ( 0 === strpos( $table, $wpdb->prefix . MND_CORE_QUARANTINE_TABLE ) ) {
			$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
		}
	}
	if ( $q['dir'] && 'mndgroup-karantena' === basename( $q['dir'] ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $q['dir'], FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $file ) {
			$file->isDir() ? @rmdir( $file->getPathname() ) : @unlink( $file->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		@rmdir( $q['dir'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	/* translators: 1: size, 2: number of tables. */
	return sprintf( __( 'Karanténa smazána: %1$s souborů a %2$d tabulek.', 'mndgroup-core' ), size_format( $q['bytes'], 1 ), count( $q['tables'] ) );
}

/**
 * Akce z formuláře (volá mnd_core_maintenance_action po ověření nonce a oprávnění).
 *
 * @param string $task Úloha.
 * @param string $item Slug pozůstatku.
 * @return string Zpráva.
 */
function mnd_core_leftovers_action( $task, $item ) {
	return 'quarantine_purge' === $task ? mnd_core_quarantine_purge() : mnd_core_leftover_quarantine( $item );
}

/**
 * Sekce v Údržbě webu.
 */
function mnd_core_leftovers_section() {
	$rows = array();
	foreach ( mnd_core_leftover_defs() as $slug => $def ) {
		if ( mnd_core_leftover_in_use( $def ) ) {
			continue;
		}
		$found = mnd_core_leftover_scan( $def );
		if ( $found['tables'] || $found['options'] || $found['paths'] ) {
			$rows[ $slug ] = array( $def, $found );
		}
	}
	$q = mnd_core_quarantine_contents();
	?>
	<h2><?php esc_html_e( 'Pozůstatky odebraných pluginů', 'mndgroup-core' ); ?></h2>
	<?php if ( $rows ) : ?>
		<p><?php esc_html_e( 'Tabulky, volby a složky pluginů (a staré šablony), které na webu nejsou aktivní. „Do karantény“ nic nemaže: složky přesune do karantény, volby uloží do JSON souboru v karanténě a tabulky přejmenuje. Plugin samotný smažte v Pluginy.', 'mndgroup-core' ); ?></p>
		<table class="widefat striped" style="max-width:860px">
			<tbody>
				<?php foreach ( $rows as $slug => $row ) : ?>
					<?php
					list( $def, $found ) = $row;
					$parts               = array();
					if ( $found['tables'] ) {
						/* translators: 1: number of tables, 2: size. */
						$parts[] = sprintf( __( 'tabulky: %1$d (%2$s)', 'mndgroup-core' ), count( $found['tables'] ), size_format( array_sum( $found['tables'] ), 1 ) );
					}
					if ( $found['options'] ) {
						/* translators: 1: number of options, 2: size. */
						$parts[] = sprintf( __( 'volby: %1$d (%2$s)', 'mndgroup-core' ), count( $found['options'] ), size_format( array_sum( $found['options'] ), 1 ) );
					}
					if ( $found['paths'] ) {
						$names = array_map(
							function ( $path ) {
								return str_replace( WP_CONTENT_DIR, 'wp-content', $path );
							},
							array_keys( $found['paths'] )
						);
						/* translators: 1: folders, 2: size. */
						$parts[] = sprintf( __( 'složky: %1$s (%2$s)', 'mndgroup-core' ), implode( ', ', $names ), size_format( array_sum( $found['paths'] ), 1 ) );
					}
					?>
					<tr>
						<td><strong><?php echo esc_html( $def['name'] ); ?></strong><br><span class="description"><?php echo esc_html( implode( ' · ', $parts ) ); ?></span></td>
						<td style="width:150px">
							<?php
							/* translators: %s: item name. */
							mnd_core_action_button( 'leftovers', __( 'Do karantény', 'mndgroup-core' ), sprintf( __( 'Přesunout pozůstatky „%s“ do karantény?', 'mndgroup-core' ), $def['name'] ), false, $slug );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'Po odebraných pluginech nic nezůstalo.', 'mndgroup-core' ); ?></p>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Karanténa', 'mndgroup-core' ); ?></h3>
	<?php if ( $q['dir'] || $q['tables'] ) : ?>
		<p>
			<?php
			/* translators: 1: size, 2: number of tables. */
			echo esc_html( sprintf( __( 'V karanténě: soubory %1$s, tabulky %2$d.', 'mndgroup-core' ), size_format( $q['bytes'], 1 ), count( $q['tables'] ) ) );
			if ( $q['dir'] ) {
				echo ' ' . esc_html__( 'Složka:', 'mndgroup-core' ) . ' <code>' . esc_html( $q['dir'] ) . '</code>';
				echo mnd_core_quarantine_outside() ? '' : ' ' . esc_html__( '(ve wp-content, přístup z webu zakázaný)', 'mndgroup-core' );
			}
			?>
		</p>
		<p class="description"><?php esc_html_e( 'Až ověříte, že web funguje a nic nechybí, můžete karanténu smazat natrvalo. Pro jistotu mějte čerstvou zálohu.', 'mndgroup-core' ); ?></p>
		<?php mnd_core_action_button( 'quarantine_purge', __( 'Smazat karanténu natrvalo', 'mndgroup-core' ), __( 'Opravdu natrvalo smazat celou karanténu (soubory i tabulky)? Akce je nevratná.', 'mndgroup-core' ) ); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Karanténa je prázdná.', 'mndgroup-core' ); ?></p>
	<?php endif; ?>
	<?php
}
