<?php
/**
 * Komentáře a pingbacky úplně vypnuté – web je nepoužívá (v nastavení byly zavřené
 * a v administraci skryté pluginem Admin Menu Editor).
 *
 * @package MNDGroupCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Žádný typ obsahu nepodporuje komentáře ani trackbacky.
 */
function mndgroup_core_remove_comment_support() {
	foreach ( get_post_types() as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
		}
		if ( post_type_supports( $post_type, 'trackbacks' ) ) {
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}
add_action( 'init', 'mndgroup_core_remove_comment_support', 100 );

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
add_filter( 'get_comments_number', '__return_zero', 20 );
add_filter( 'feed_links_show_comments_feed', '__return_false' );

/**
 * Kanál komentářů vrací 404.
 */
function mndgroup_core_comment_feed_404() {
	if ( is_comment_feed() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
	}
}
add_action( 'template_redirect', 'mndgroup_core_comment_feed_404', 1 );

/**
 * Administrace: bez menu Komentáře a Nastavení → Diskuze.
 */
function mndgroup_core_comments_admin_menu() {
	remove_menu_page( 'edit-comments.php' );
	remove_submenu_page( 'options-general.php', 'options-discussion.php' );
}
add_action( 'admin_menu', 'mndgroup_core_comments_admin_menu', 999 );

/**
 * Přímý přístup na stránky komentářů přesměrovat na Nástěnku.
 */
function mndgroup_core_comments_admin_redirect() {
	global $pagenow;
	if ( in_array( $pagenow, array( 'edit-comments.php', 'comment.php', 'options-discussion.php' ), true ) ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
add_action( 'admin_init', 'mndgroup_core_comments_admin_redirect' );

/**
 * Bez komentářů v liště administrace.
 *
 * @param WP_Admin_Bar $bar Lišta.
 */
function mndgroup_core_comments_admin_bar( $bar ) {
	$bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'mndgroup_core_comments_admin_bar', 999 );

/**
 * Bez widgetu Nejnovější komentáře.
 */
function mndgroup_core_comments_widgets() {
	unregister_widget( 'WP_Widget_Recent_Comments' );
}
add_action( 'widgets_init', 'mndgroup_core_comments_widgets', 20 );
