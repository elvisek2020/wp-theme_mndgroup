<?php
/**
 * Typ obsahu „slide“ – bannery v úvodu stránky.
 *
 * Název typu zůstává stejný jako v původní šabloně, takže stávající slidy
 * včetně překladů v Polylangu fungují bez jakékoli migrace.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registrace typu obsahu.
 */
function mndgroup_register_slide() {
	register_post_type(
		'slide',
		array(
			'labels'              => array(
				'name'               => __( 'Slides', 'mndgroup' ),
				'singular_name'      => __( 'Slide', 'mndgroup' ),
				'add_new'            => __( 'Add slide', 'mndgroup' ),
				'add_new_item'       => __( 'Add new slide', 'mndgroup' ),
				'edit_item'          => __( 'Edit slide', 'mndgroup' ),
				'new_item'           => __( 'New slide', 'mndgroup' ),
				'view_item'          => __( 'View slide', 'mndgroup' ),
				'search_items'       => __( 'Search slides', 'mndgroup' ),
				'not_found'          => __( 'No slides found.', 'mndgroup' ),
				'not_found_in_trash' => __( 'No slides found in Trash.', 'mndgroup' ),
				'all_items'          => __( 'All slides', 'mndgroup' ),
				'featured_image'     => __( 'Slide image (1920 × 484 px)', 'mndgroup' ),
				'set_featured_image' => __( 'Set slide image', 'mndgroup' ),
			),
			'description'         => __( 'Banners at the top of the homepage. The order is set by the “Order” field.', 'mndgroup' ),
			// Samostatné stránky slidů nemají smysl – jen administrace.
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-images-alt2',
			'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
		)
	);
}
add_action( 'init', 'mndgroup_register_slide' );

/**
 * V přehledu slidů zobrazit náhled obrázku a pořadí.
 *
 * @param array $columns Sloupce tabulky.
 * @return array
 */
function mndgroup_slide_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['mndgroup_thumb'] = __( 'Image', 'mndgroup' );
		}
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['menu_order'] = __( 'Order', 'mndgroup' );
		}
	}
	return $new;
}
add_filter( 'manage_slide_posts_columns', 'mndgroup_slide_columns' );

/**
 * Obsah vlastních sloupců.
 *
 * @param string $column  Sloupec.
 * @param int    $post_id ID slidu.
 */
function mndgroup_slide_column_content( $column, $post_id ) {
	if ( 'mndgroup_thumb' === $column ) {
		echo get_the_post_thumbnail( $post_id, array( 160, 40 ), array( 'style' => 'width:160px;height:40px;object-fit:cover' ) );
	} elseif ( 'menu_order' === $column ) {
		echo (int) get_post_field( 'menu_order', $post_id );
	}
}
add_action( 'manage_slide_posts_custom_column', 'mndgroup_slide_column_content', 10, 2 );

/**
 * Výchozí řazení slidů v administraci podle pořadí.
 *
 * @param WP_Query $query Dotaz.
 */
function mndgroup_slide_admin_order( $query ) {
	if ( is_admin() && $query->is_main_query() && 'slide' === $query->get( 'post_type' ) && ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', 'menu_order' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'mndgroup_slide_admin_order' );

/**
 * Slidy pro aktuální jazyk v nastaveném pořadí.
 *
 * @return WP_Post[]
 */
function mndgroup_get_slides() {
	$query = new WP_Query(
		array(
			'post_type'           => 'slide',
			'post_status'         => 'publish',
			'posts_per_page'      => 12,
			'orderby'             => array(
				'menu_order' => 'ASC',
				'date'       => 'ASC',
			),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'meta_query'          => array(
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'EXISTS',
				),
			),
		)
	);
	return $query->posts;
}
