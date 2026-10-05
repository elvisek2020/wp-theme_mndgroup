<?php
/**
 * Odstranění zbytečností z hlavičky a výstupu WordPressu.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Emoji skript a styly – moderní prohlížeče emoji zobrazí samy.
 */
function mnd_disable_emoji() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'mnd_disable_emoji' );

// Odkazy v hlavičce, které web nepotřebuje (Windows Live Writer, RSD, verze WP, krátký odkaz).
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );

// Styly bloků se načtou jen pro bloky, které jsou na stránce opravdu použité.
add_filter( 'should_load_separate_core_block_assets', '__return_true' );
