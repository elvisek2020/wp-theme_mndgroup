<?php
/**
 * Údržba webu — Nástroje → Údržba webu (nahrazuje Server IP & Memory Usage a nástroje na optimalizaci DB).
 *
 *  1) Přehled systému
 *  2) Obrázky na WebP: starší JPG/PNG natrvalo převede na .webp (soubory i odkazy v obsahu),
 *     staré adresy přesměruje 301, původní soubory přesune do karantény. Vidět jen dokud nějaké zbývají.
 *  3) Databáze: velikost tabulek, autoload, optimalizace
 *  4) Úklid databáze: revize, koncepty, koš, prošlé transienty, osiřelá metadata
 *  5) Média a odkazy: největší soubory, nepoužité obrázky (jen ke kontrole), rozbité interní odkazy
 *  6) Pozůstatky odebraných pluginů a karanténa (inc/leftovers.php)
 *
 * Hromadné akce: potvrzovací dialog, check_admin_referer a nové ověření na serveru.
 * Načítá inc/core.php.
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

const MND_CORE_MAINTENANCE_PAGE = 'mndgroup-core-maintenance';

/* =========================================================================
 * Karanténa – co se má smazat, přesune se napřed sem; smazání je na správci webu
 * ====================================================================== */

/**
 * Složka karantény: mimo webový kořen, pokud to hosting dovolí, jinak ve wp-content (zamčená).
 *
 * @param string $sub Podsložka.
 * @return string|WP_Error Cesta (se lomítkem na konci).
 */
function mnd_core_quarantine_dir( $sub = '' ) {
	$outside = dirname( untrailingslashit( ABSPATH ) ) . '/mndgroup-karantena';
	$inside  = WP_CONTENT_DIR . '/mndgroup-karantena';
	$base    = ( is_dir( $outside ) || wp_is_writable( dirname( $outside ) ) ) ? $outside : $inside;

	$dir = trailingslashit( $base ) . ( $sub ? trim( $sub, '/' ) . '/' : '' );
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'quarantine', __( 'Nepodařilo se vytvořit složku karantény.', 'mndgroup-core' ) );
	}
	if ( $base === $inside && ! is_file( $inside . '/.htaccess' ) ) {
		file_put_contents( $inside . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $inside . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $dir;
}

/**
 * Je karanténa mimo webový kořen?
 *
 * @return bool
 */
function mnd_core_quarantine_outside() {
	$dir = mnd_core_quarantine_dir();
	return ! is_wp_error( $dir ) && 0 !== strpos( $dir, untrailingslashit( ABSPATH ) . '/' );
}

/**
 * Přesune soubor nebo složku do karantény (zachová cestu relativní k webu).
 *
 * @param string $path  Absolutní cesta.
 * @param string $group Podsložka karantény (např. „webp“ nebo slug pluginu).
 * @return bool
 */
function mnd_core_quarantine_move( $path, $group ) {
	if ( ! file_exists( $path ) ) {
		return false;
	}
	$rel = ltrim( str_replace( untrailingslashit( ABSPATH ), '', $path ), '/' );
	$dir = mnd_core_quarantine_dir( wp_date( 'Y-m-d' ) . '/' . sanitize_key( $group ) . '/' . dirname( $rel ) );
	if ( is_wp_error( $dir ) ) {
		return false;
	}
	$target = $dir . basename( $path );
	if ( file_exists( $target ) ) {
		$target .= '-' . time();
	}
	return @rename( $path, $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

/**
 * Velikost souboru nebo složky.
 *
 * @param string $path Cesta.
 * @return int Bajty.
 */
function mnd_core_path_size( $path ) {
	if ( is_file( $path ) ) {
		return (int) filesize( $path );
	}
	$bytes = 0;
	if ( is_dir( $path ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( $file->isFile() ) {
				$bytes += $file->getSize();
			}
		}
	}
	return $bytes;
}

/* =========================================================================
 * Soubory přílohy a převod na WebP
 * ====================================================================== */

/**
 * Všechny soubory přílohy na disku (originál, případný nezmenšený originál, zmenšeniny).
 *
 * @param int $id ID přílohy.
 * @return string[]
 */
function mnd_core_attachment_files( $id ) {
	$main = get_attached_file( $id );
	if ( ! $main ) {
		return array();
	}
	$dir   = dirname( $main );
	$meta  = wp_get_attachment_metadata( $id );
	$meta  = is_array( $meta ) ? $meta : array();
	$files = array( $main );
	if ( ! empty( $meta['original_image'] ) ) {
		$files[] = $dir . '/' . $meta['original_image'];
	}
	foreach ( isset( $meta['sizes'] ) ? (array) $meta['sizes'] : array() as $size ) {
		if ( ! empty( $size['file'] ) ) {
			$files[] = $dir . '/' . $size['file'];
		}
	}
	return array_values( array_unique( array_filter( $files, 'is_file' ) ) );
}

/**
 * ID příloh, které jsou ještě JPG/PNG (bez těch, jejichž převod selhal).
 *
 * @return int[]
 */
function mnd_core_webp_pending() {
	global $wpdb;
	return array_map(
		'intval',
		$wpdb->get_col(
			"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_mnd_core_webp_fail'
			 WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/png','image/jpeg') AND m.meta_id IS NULL ORDER BY p.ID DESC"
		)
	);
}

/**
 * Převede jednu přílohu natrvalo na WebP: všechny velikosti, metadata, odkazy v obsahu.
 * Původní soubory přesune do karantény. Při chybě nechá přílohu beze změny.
 *
 * @param int $id ID přílohy.
 * @return int|WP_Error Ušetřené bajty.
 */
function mnd_core_webp_unify( $id ) {
	global $wpdb;
	$main = get_attached_file( $id );
	$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
	if ( ! $main || ! is_file( $main ) || '' === $rel ) {
		return new WP_Error( 'missing', __( 'Soubor chybí', 'mndgroup-core' ) );
	}
	$dir    = dirname( $main );
	$subdir = trim( dirname( $rel ), './' );
	$meta   = wp_get_attachment_metadata( $id );
	$meta   = is_array( $meta ) ? $meta : array();
	$names  = array( basename( $main ) );
	if ( ! empty( $meta['original_image'] ) ) {
		$names[] = $meta['original_image'];
	}
	foreach ( isset( $meta['sizes'] ) ? (array) $meta['sizes'] : array() as $size ) {
		if ( ! empty( $size['file'] ) ) {
			$names[] = $size['file'];
		}
	}

	$map     = array(); // starý název => nový název
	$created = array();
	$before  = 0;
	$after   = 0;
	foreach ( array_unique( $names ) as $name ) {
		if ( ! preg_match( '/\.(png|jpe?g)$/i', $name ) ) {
			continue; // zmenšenina už je WebP
		}
		$old = $dir . '/' . $name;
		if ( ! is_file( $old ) ) {
			continue;
		}
		$new_name = preg_replace( '/\.(png|jpe?g)$/i', '.webp', $name );
		if ( is_file( $dir . '/' . $new_name ) ) {
			$new_name = wp_unique_filename( $dir, $new_name );
		}
		$new    = $dir . '/' . $new_name;
		$editor = wp_get_image_editor( $old );
		$saved  = is_wp_error( $editor ) ? $editor : null;
		if ( ! $saved ) {
			$editor->set_quality( 82 );
			$editor->maybe_exif_rotate(); // WebP nenese EXIF – otočit podle něj už teď
			$saved = $editor->save( $new, 'image/webp' );
		}
		if ( is_wp_error( $saved ) || ! is_file( $new ) ) {
			array_map( 'wp_delete_file', $created );
			/* translators: %s: file name. */
			return new WP_Error( 'convert', sprintf( __( 'Převod se nepovedl: %s', 'mndgroup-core' ), $name ) );
		}
		$created[]    = $new;
		$map[ $name ] = $new_name;
		$before      += (int) filesize( $old );
		$after       += (int) filesize( $new );
	}
	if ( ! $map ) {
		$wpdb->update( $wpdb->posts, array( 'post_mime_type' => 'image/webp' ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		return 0;
	}

	// Metadata přílohy.
	$main_new = $dir . '/' . ( isset( $map[ basename( $main ) ] ) ? $map[ basename( $main ) ] : basename( $main ) );
	if ( ! empty( $meta['file'] ) ) {
		$meta['file'] = ( $subdir ? $subdir . '/' : '' ) . basename( $main_new );
	}
	if ( ! empty( $meta['original_image'] ) && isset( $map[ $meta['original_image'] ] ) ) {
		$meta['original_image'] = $map[ $meta['original_image'] ];
	}
	foreach ( isset( $meta['sizes'] ) ? (array) $meta['sizes'] : array() as $key => $size ) {
		if ( isset( $map[ $size['file'] ] ) ) {
			$meta['sizes'][ $key ]['file']      = $map[ $size['file'] ];
			$meta['sizes'][ $key ]['mime-type'] = 'image/webp';
			$meta['sizes'][ $key ]['filesize']  = (int) filesize( $dir . '/' . $map[ $size['file'] ] );
		}
	}
	$meta['filesize'] = (int) filesize( $main_new );
	update_attached_file( $id, $main_new );
	wp_update_attachment_metadata( $id, $meta );
	$wpdb->update(
		$wpdb->posts,
		array(
			'post_mime_type' => 'image/webp',
			'guid'           => str_replace( array_keys( $map ), array_values( $map ), (string) get_post_field( 'guid', $id ) ),
		),
		array( 'ID' => $id )
	);

	// Odkazy v obsahu (stránky, příspěvky, bloky; revize ne – staré adresy stejně přesměrujeme).
	$prefix = '/' . ( $subdir ? $subdir . '/' : '' );
	foreach ( $map as $from => $to ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_type NOT IN ('revision','attachment') AND post_content LIKE %s",
				$prefix . $from,
				$prefix . $to,
				'%' . $wpdb->esc_like( $prefix . $from ) . '%'
			)
		);
	}

	// Původní soubory do karantény (smazání je na správci).
	foreach ( array_keys( $map ) as $from ) {
		mnd_core_quarantine_move( $dir . '/' . $from, 'webp' );
	}
	clean_post_cache( $id );
	return max( 0, $before - $after );
}

/**
 * Dávka převodů (časově omezená, aby nespadl PHP limit).
 *
 * @return string Zpráva.
 */
function mnd_core_webp_unify_batch() {
	@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$start = time();
	$done  = 0;
	$saved = 0;
	$fail  = array();
	foreach ( mnd_core_webp_pending() as $id ) {
		$result = mnd_core_webp_unify( $id );
		if ( is_wp_error( $result ) ) {
			$fail[] = $id;
			update_post_meta( $id, '_mnd_core_webp_fail', $result->get_error_message() ); // další dávka ji přeskočí
		} else {
			$done++;
			$saved += $result;
		}
		if ( time() - $start > 40 ) {
			break;
		}
	}
	$left = count( mnd_core_webp_pending() );
	/* translators: 1: number of images, 2: saved size. */
	$msg = sprintf( __( 'Převedeno obrázků: %1$d, ušetřeno %2$s.', 'mndgroup-core' ), $done, size_format( $saved, 1 ) );
	/* translators: %d: remaining images. */
	$msg .= ' ' . ( $left ? sprintf( __( 'Zbývá %d – klikněte znovu.', 'mndgroup-core' ), $left ) : __( 'Hotovo, všechny obrázky jsou WebP. Původní soubory jsou v karanténě.', 'mndgroup-core' ) );
	if ( $fail ) {
		$msg .= ' ' . __( 'Nepovedlo se: ID', 'mndgroup-core' ) . ' ' . implode( ', ', $fail ) . '.';
	}
	return $msg;
}

/**
 * Staré adresy JPG/PNG (odkazy zvenku, vyhledávače obrázků) → 301 na WebP verzi.
 */
function mnd_core_webp_redirect() {
	if ( ! is_404() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	$up   = wp_get_upload_dir();
	$base = (string) wp_parse_url( $up['baseurl'], PHP_URL_PATH );
	if ( 0 !== strpos( $path, $base . '/' ) || ! preg_match( '/^(.+)\.(png|jpe?g)$/i', rawurldecode( substr( $path, strlen( $base ) ) ), $m ) || false !== strpos( $m[1], '..' ) ) {
		return;
	}
	if ( is_file( $up['basedir'] . $m[1] . '.webp' ) ) {
		wp_redirect( $up['baseurl'] . $m[1] . '.webp', 301 ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}
}
add_action( 'template_redirect', 'mnd_core_webp_redirect' );

/* =========================================================================
 * Databáze
 * ====================================================================== */

/**
 * Tabulky webu (velikost, řádky, nevyužité místo).
 *
 * @return object[]
 */
function mnd_core_db_tables() {
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT table_name AS t, table_rows AS r, data_length AS d, index_length AS i, data_free AS f
			 FROM information_schema.tables WHERE table_schema = %s AND table_name LIKE %s ORDER BY (data_length + index_length) DESC',
			DB_NAME,
			$wpdb->esc_like( $wpdb->prefix ) . '%'
		)
	);
	return $rows ? $rows : array();
}

/**
 * Velikost tabulek webu v databázi (bajty).
 *
 * @return int
 */
function mnd_core_db_size() {
	$bytes = 0;
	foreach ( mnd_core_db_tables() as $table ) {
		$bytes += (int) $table->d + (int) $table->i;
	}
	return $bytes;
}

/**
 * Volby načítané na každé stránce (autoload).
 *
 * @return array [total, count, top]
 */
function mnd_core_db_autoload() {
	global $wpdb;
	$where = "autoload IN ('yes','on','auto','auto-on')";
	return array(
		'total' => (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE $where" ),
		'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE $where" ),
		'top'   => $wpdb->get_results( "SELECT option_name AS n, LENGTH(option_value) AS b FROM {$wpdb->options} WHERE $where ORDER BY b DESC LIMIT 10" ),
	);
}

/* =========================================================================
 * Úklid databáze
 * ====================================================================== */

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

/* =========================================================================
 * Média a odkazy
 * ====================================================================== */

/**
 * Největší přílohy (všechny jejich soubory na disku).
 *
 * @param int $limit Počet.
 * @return array [[id, bajty, název], …]
 */
function mnd_core_media_largest( $limit = 15 ) {
	global $wpdb;
	$rows = array();
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'" ) as $id ) {
		$bytes = 0;
		foreach ( mnd_core_attachment_files( (int) $id ) as $file ) {
			$bytes += (int) filesize( $file );
		}
		$file   = get_attached_file( (int) $id );
		$rows[] = array( (int) $id, $bytes, $file ? basename( $file ) : '?' );
	}
	usort(
		$rows,
		function ( $a, $b ) {
			return $b[1] - $a[1];
		}
	);
	return array_slice( $rows, 0, $limit );
}

/**
 * Média, která nejsou nikde použitá (náhled, logo, ikona webu, nastavení šablony, obsah).
 *
 * @return WP_Post[]
 */
function mnd_core_unused_media() {
	global $wpdb;

	$used = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'" ) );
	foreach ( (array) get_theme_mods() as $value ) {
		if ( is_numeric( $value ) ) {
			$used[] = (int) $value;
		}
	}
	$used[] = (int) get_option( 'site_icon' );
	$used   = array_flip( array_filter( $used ) );

	$content = implode( "\n", $wpdb->get_col( "SELECT post_content FROM {$wpdb->posts} WHERE post_type NOT IN ('attachment','revision') AND post_status NOT IN ('trash','auto-draft')" ) );
	$unused  = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 500,
			'no_found_rows'  => true,
		)
	) as $attachment ) {
		if ( isset( $used[ $attachment->ID ] ) ) {
			continue;
		}
		$rel  = (string) get_post_meta( $attachment->ID, '_wp_attached_file', true );
		$stem = preg_replace( '#(-scaled)?\.[a-z0-9]+$#i', '', $rel );
		if ( $stem && ( false !== strpos( $content, $stem ) || false !== strpos( $content, 'wp-image-' . $attachment->ID . '"' ) ) ) {
			continue;
		}
		$unused[] = $attachment;
	}
	return $unused;
}

/**
 * Interní odkazy a obrázky v obsahu, které nikam nevedou.
 *
 * @return array [[id, titulek, url], …]
 */
function mnd_core_links_broken() {
	global $wpdb;
	$host = preg_replace( '#^www\.#', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$out  = array();
	$seen = array();
	$rows = $wpdb->get_results( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type IN ('post','page') AND post_status = 'publish'" );
	foreach ( $rows as $row ) {
		if ( ! preg_match_all( '#(?:href|src)=["\']([^"\']+)["\']#i', $row->post_content, $m ) ) {
			continue;
		}
		foreach ( array_unique( $m[1] ) as $url ) {
			$parts = wp_parse_url( html_entity_decode( $url ) );
			$link  = preg_replace( '#^www\.#', '', (string) ( isset( $parts['host'] ) ? $parts['host'] : '' ) );
			$path  = isset( $parts['path'] ) ? (string) $parts['path'] : '';
			if ( '' === $link && 0 === strpos( $path, '/' ) ) {
				$link = $host; // relativní odkaz
			}
			if ( $link !== $host || '' === $path || '/' === $path ) {
				continue;
			}
			$path = untrailingslashit( $path );
			$key  = $row->ID . $path;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			if ( false !== strpos( $path, '/wp-content/' ) || preg_match( '#\.(?!html?$|php$)[a-z0-9]{2,5}$#i', $path ) ) {
				$ok = is_file( untrailingslashit( ABSPATH ) . $path );
			} elseif ( preg_match( '#/(feed|page/\d+|wp-admin|wp-login\.php|wp-json)#', $path ) ) {
				$ok = true;
			} else {
				$ok = url_to_postid( home_url( $path . '/' ) ) > 0 || get_page_by_path( ltrim( $path, '/' ) ) || url_to_postid( home_url( $path ) ) > 0;
			}
			if ( ! $ok ) {
				$out[] = array( (int) $row->ID, $row->post_title, $url );
			}
		}
	}
	return $out;
}

/* =========================================================================
 * Stránka a akce
 * ====================================================================== */

/**
 * Stránka v menu Nástroje.
 */
function mnd_core_maintenance_menu() {
	add_management_page( __( 'Údržba webu', 'mndgroup-core' ), __( 'Údržba webu', 'mndgroup-core' ), 'manage_options', MND_CORE_MAINTENANCE_PAGE, 'mnd_core_maintenance_page' );
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
 * Zpracování formulářů (admin-post.php). Výsledek se zobrazí po přesměrování zpět.
 */
function mnd_core_maintenance_action() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce se ověřuje hned pod tím podle úlohy.
	$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$item = isset( $_POST['item'] ) ? sanitize_key( wp_unslash( $_POST['item'] ) ) : '';
	check_admin_referer( 'mnd_core_maintenance_' . $task . $item );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Na tuto akci nemáte oprávnění.', 'mndgroup-core' ), 403 );
	}

	$tasks = mnd_core_cleanup_tasks();
	if ( 'unlock' === $task ) {
		mnd_core_clear_lockouts();
		$msg = __( 'Všechny blokace přihlášení byly zrušeny.', 'mndgroup-core' );
	} elseif ( 'webp' === $task ) {
		$msg = mnd_core_webp_unify_batch();
	} elseif ( 'optimize' === $task ) {
		global $wpdb;
		$count = 0;
		foreach ( mnd_core_db_tables() as $table ) {
			$wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $table->t ) . '`' );
			$count++;
		}
		/* translators: %d: number of tables. */
		$msg = sprintf( __( 'Optimalizováno tabulek: %d.', 'mndgroup-core' ), $count );
	} elseif ( isset( $tasks[ $task ] ) ) {
		// Počet se ověří znovu na serveru – formulář mohl být otevřený dlouho.
		$count = mnd_core_cleanup_count( $task ) ? mnd_core_cleanup_run( $task ) : 0;
		/* translators: 1: task name, 2: number of items. */
		$msg = sprintf( __( '%1$s: odstraněno položek: %2$d.', 'mndgroup-core' ), $tasks[ $task ][0], $count );
	} elseif ( function_exists( 'mnd_core_leftovers_action' ) && ( 'leftovers' === $task || 'quarantine_purge' === $task ) ) {
		$msg = mnd_core_leftovers_action( $task, $item );
	} else {
		wp_die( esc_html__( 'Neznámá úloha.', 'mndgroup-core' ), 400 );
	}

	if ( function_exists( 'mnd_core_health_mark_maintenance' ) ) {
		mnd_core_health_mark_maintenance();
	}
	set_transient( 'mnd_core_maint_msg_' . get_current_user_id(), $msg, 5 * MINUTE_IN_SECONDS );

	$back = wp_get_referer();
	wp_safe_redirect( add_query_arg( 'done', 1, $back ? remove_query_arg( 'done', $back ) : mnd_core_maintenance_url() ) );
	exit;
}
add_action( 'admin_post_mnd_core_maintenance', 'mnd_core_maintenance_action' );

/**
 * Zpráva po akci (jednorázová).
 */
function mnd_core_maintenance_notice() {
	$key = 'mnd_core_maint_msg_' . get_current_user_id();
	$msg = get_transient( $key );
	if ( $msg ) {
		delete_transient( $key );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
}

/**
 * Formulář s jedním tlačítkem a potvrzením.
 *
 * @param string $task    Úloha.
 * @param string $label   Text tlačítka.
 * @param string $confirm Potvrzovací otázka.
 * @param bool   $primary Zvýrazněné tlačítko.
 * @param string $item    Upřesnění úlohy (např. slug pluginu).
 */
function mnd_core_action_button( $task, $label, $confirm, $primary = false, $item = '' ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm(<?php echo esc_attr( wp_json_encode( $confirm ) ); ?>);" style="margin:0;display:inline-block">
		<input type="hidden" name="action" value="mnd_core_maintenance">
		<input type="hidden" name="task" value="<?php echo esc_attr( $task ); ?>">
		<input type="hidden" name="item" value="<?php echo esc_attr( $item ); ?>">
		<?php wp_nonce_field( 'mnd_core_maintenance_' . $task . $item ); ?>
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
 * Stránka Nástroje → Údržba webu.
 */
function mnd_core_maintenance_page() {
	global $wpdb;

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tasks    = mnd_core_cleanup_tasks();
	$theme    = wp_get_theme( get_template() );
	$tables   = mnd_core_db_tables();
	$autoload = mnd_core_db_autoload();
	$pending  = mnd_core_webp_pending();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Údržba webu', 'mndgroup-core' ); ?></h1>
		<p>
			<a href="<?php echo esc_url( mnd_core_settings_url() ); ?>"><?php esc_html_e( 'Nastavení MND Group Core', 'mndgroup-core' ); ?></a> ·
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=mndgroup-core-login-log' ) ); ?>"><?php esc_html_e( 'Log přihlášení', 'mndgroup-core' ); ?></a>
		</p>
		<?php mnd_core_maintenance_notice(); ?>
		<p><?php esc_html_e( 'Před větším úklidem je dobré mít čerstvou zálohu (WEDOS administrace → zálohy).', 'mndgroup-core' ); ?></p>

		<h2><?php esc_html_e( 'Přehled', 'mndgroup-core' ); ?></h2>
		<table class="widefat striped" style="max-width:860px">
			<tbody>
				<tr><th><?php esc_html_e( 'WordPress', 'mndgroup-core' ); ?></th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Šablona', 'mndgroup-core' ); ?></th><td><?php echo esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Plugin MND Group Core', 'mndgroup-core' ); ?></th><td><?php echo esc_html( MND_CORE_VERSION ); ?></td></tr>
				<tr><th><?php esc_html_e( 'PHP', 'mndgroup-core' ); ?></th><td><?php echo esc_html( PHP_VERSION . ' · memory_limit ' . ini_get( 'memory_limit' ) . ' · upload ' . size_format( wp_max_upload_size() ) . ' · WebP ' . ( mnd_core_webp_supported() ? __( 'ano', 'mndgroup-core' ) : __( 'ne', 'mndgroup-core' ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Databáze', 'mndgroup-core' ); ?></th><td><?php echo esc_html( $wpdb->db_server_info() . ' · ' . size_format( mnd_core_db_size(), 1 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Prostředí', 'mndgroup-core' ); ?></th><td><?php echo esc_html( wp_get_environment_type() ); ?></td></tr>
			</tbody>
		</table>

		<?php if ( $pending ) : ?>
			<h2><?php esc_html_e( 'Obrázky na WebP', 'mndgroup-core' ); ?></h2>
			<p>
				<?php
				/* translators: %d: number of images. */
				echo esc_html( sprintf( __( 'Obrázků ve formátu JPG/PNG: %d. Převod je natrvalo: vznikne WebP verze všech velikostí, odkazy v obsahu se přepíšou a staré adresy přesměrují (301). Původní soubory se přesunou do karantény.', 'mndgroup-core' ), count( $pending ) ) );
				?>
			</p>
			<?php
			if ( mnd_core_webp_supported() ) {
				/* translators: %d: number of images. */
				mnd_core_action_button( 'webp', __( 'Převést na WebP', 'mndgroup-core' ), sprintf( __( 'Převést %d obrázků natrvalo na WebP?', 'mndgroup-core' ), count( $pending ) ), true );
			} else {
				echo '<p><strong>' . esc_html__( 'Server neumí ukládat WebP (chybí podpora v GD/Imagick).', 'mndgroup-core' ) . '</strong></p>';
			}
			?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Databáze', 'mndgroup-core' ); ?></h2>
		<table class="widefat striped" style="max-width:860px">
			<thead><tr><th><?php esc_html_e( 'Tabulka', 'mndgroup-core' ); ?></th><th style="text-align:right"><?php esc_html_e( 'Řádků', 'mndgroup-core' ); ?></th><th style="text-align:right"><?php esc_html_e( 'Velikost', 'mndgroup-core' ); ?></th><th style="text-align:right"><?php esc_html_e( 'Nevyužito', 'mndgroup-core' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $tables as $table ) : ?>
					<tr>
						<td><code><?php echo esc_html( $table->t ); ?></code></td>
						<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $table->r ) ); ?></td>
						<td style="text-align:right"><?php echo esc_html( size_format( (int) $table->d + (int) $table->i, 1 ) ); ?></td>
						<td style="text-align:right"><?php echo esc_html( (int) $table->f ? size_format( (int) $table->f, 1 ) : '–' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<?php
			/* translators: 1: number of options, 2: size. */
			echo esc_html( sprintf( __( 'Autoload (volby načítané na každé stránce): %1$d voleb, %2$s.', 'mndgroup-core' ), $autoload['count'], size_format( $autoload['total'], 1 ) ) );
			echo $autoload['total'] > MB_IN_BYTES ? ' <strong>' . esc_html__( 'Víc než 1 MB zpomaluje web.', 'mndgroup-core' ) . '</strong>' : '';
			?>
		</p>
		<details style="max-width:860px;margin-bottom:1em">
			<summary><?php esc_html_e( 'Největší volby v autoloadu', 'mndgroup-core' ); ?></summary>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( (array) $autoload['top'] as $row ) : ?>
						<tr><td><code><?php echo esc_html( $row->n ); ?></code></td><td style="text-align:right"><?php echo esc_html( size_format( (int) $row->b, 1 ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</details>
		<?php mnd_core_action_button( 'optimize', __( 'Optimalizovat tabulky', 'mndgroup-core' ), __( 'Spustit optimalizaci všech tabulek webu? Může to chvíli trvat.', 'mndgroup-core' ) ); ?>

		<h2><?php esc_html_e( 'Úklid databáze', 'mndgroup-core' ); ?></h2>
		<table class="widefat striped" style="max-width:860px">
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

		<h2><?php esc_html_e( 'Média a odkazy', 'mndgroup-core' ); ?></h2>
		<h3><?php esc_html_e( 'Největší soubory', 'mndgroup-core' ); ?></h3>
		<?php $largest = mnd_core_media_largest(); ?>
		<?php if ( $largest ) : ?>
			<table class="widefat striped" style="max-width:860px">
				<tbody>
					<?php foreach ( $largest as $row ) : ?>
						<tr><td><a href="<?php echo esc_url( (string) get_edit_post_link( $row[0] ) ); ?>"><?php echo esc_html( $row[2] ); ?></a></td><td style="text-align:right"><?php echo esc_html( size_format( $row[1], 1 ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'Knihovna médií je prázdná.', 'mndgroup-core' ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Nepoužité obrázky', 'mndgroup-core' ); ?></h3>
		<?php $unused = mnd_core_unused_media(); ?>
		<?php if ( $unused ) : ?>
			<p><?php esc_html_e( 'Nejsou použité v obsahu, jako náhledový obrázek, logo, ikona webu ani v nastavení šablony. Nic se nemaže automaticky – před smazáním v Knihovně médií je zkontrolujte (mohou být odkazované zvenku).', 'mndgroup-core' ); ?></p>
			<ul style="list-style:disc;padding-left:1.5em">
				<?php foreach ( array_slice( $unused, 0, 50 ) as $attachment ) : ?>
					<li><a href="<?php echo esc_url( (string) get_edit_post_link( $attachment->ID ) ); ?>"><?php echo esc_html( basename( (string) get_attached_file( $attachment->ID ) ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'Všechna média jsou použitá.', 'mndgroup-core' ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Rozbité interní odkazy', 'mndgroup-core' ); ?></h3>
		<?php $broken = mnd_core_links_broken(); ?>
		<?php if ( $broken ) : ?>
			<ul style="list-style:disc;padding-left:1.5em">
				<?php foreach ( $broken as $row ) : ?>
					<li><a href="<?php echo esc_url( (string) get_edit_post_link( $row[0] ) ); ?>"><?php echo esc_html( $row[1] ); ?></a>: <code><?php echo esc_html( $row[2] ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'V publikovaných stránkách a příspěvcích nejsou žádné rozbité interní odkazy ani obrázky.', 'mndgroup-core' ); ?></p>
		<?php endif; ?>

		<?php
		if ( function_exists( 'mnd_core_leftovers_section' ) ) {
			mnd_core_leftovers_section();
		}
		?>
	</div>
	<?php
}
