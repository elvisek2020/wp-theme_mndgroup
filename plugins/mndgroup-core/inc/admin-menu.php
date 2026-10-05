<?php
/**
 * Zjednodušená administrace.
 *
 * Menu skrývá položky, které správci obsahu nepotřebují (dosud to dělal plugin
 * Admin Menu Editor). Skrytí není zákaz – stránky zůstávají dostupné, jen nejsou
 * v menu. Administrátor si úplné menu zapne v Uživatelé → Profil.
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

const MNDGROUP_CORE_FULL_MENU_META = 'mndgroup_core_full_menu';

/**
 * Položky menu skryté ve zjednodušeném režimu (slug stránky menu).
 *
 * @return string[]
 */
function mndgroup_core_hidden_menu_items() {
	return (array) apply_filters(
		'mndgroup_core_hidden_menu_items',
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
function mndgroup_core_full_menu() {
	return (bool) get_user_meta( get_current_user_id(), MNDGROUP_CORE_FULL_MENU_META, true );
}

/**
 * Skrytí položek menu.
 */
function mndgroup_core_simplify_menu() {
	if ( mndgroup_core_full_menu() ) {
		return;
	}
	foreach ( mndgroup_core_hidden_menu_items() as $slug ) {
		remove_menu_page( $slug );
	}
}
add_action( 'admin_menu', 'mndgroup_core_simplify_menu', 9999 );

/**
 * Přepínač v profilu (jen pro administrátory).
 *
 * @param WP_User $user Upravovaný uživatel.
 */
function mndgroup_core_full_menu_field( $user ) {
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
					<input type="checkbox" name="mndgroup_core_full_menu" id="mndgroup-core-full-menu" value="1" <?php checked( (bool) get_user_meta( $user->ID, MNDGROUP_CORE_FULL_MENU_META, true ) ); ?>>
					<?php esc_html_e( 'Zobrazit úplné menu (Nástroje, Nastavení, Jazyky…)', 'mndgroup-core' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Ve výchozím stavu jsou technické položky skryté, aby se v administraci snáz orientovalo.', 'mndgroup-core' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'mndgroup_core_full_menu_field' );
add_action( 'edit_user_profile', 'mndgroup_core_full_menu_field' );

/**
 * Uložení přepínače (nonce kontroluje formulář profilu WordPressu).
 *
 * @param int $user_id ID uživatele.
 */
function mndgroup_core_full_menu_save( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! current_user_can( 'manage_options' ) || ! user_can( $user_id, 'manage_options' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST['mndgroup_core_full_menu'] ) ) {
		update_user_meta( $user_id, MNDGROUP_CORE_FULL_MENU_META, 1 );
	} else {
		delete_user_meta( $user_id, MNDGROUP_CORE_FULL_MENU_META );
	}
}
add_action( 'personal_options_update', 'mndgroup_core_full_menu_save' );
add_action( 'edit_user_profile_update', 'mndgroup_core_full_menu_save' );

/**
 * Nástěnka bez novinek z WordPress.org a rychlého konceptu (web příspěvky nepoužívá).
 */
function mndgroup_core_dashboard_cleanup() {
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
}
add_action( 'wp_dashboard_setup', 'mndgroup_core_dashboard_cleanup', 20 );
