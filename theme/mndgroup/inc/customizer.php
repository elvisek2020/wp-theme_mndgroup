<?php
/**
 * Nastavení šablony v Přizpůsobení (Vzhled → Přizpůsobit → MND Group).
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registrace nastavení.
 *
 * @param WP_Customize_Manager $wp_customize Správce přizpůsobení.
 */
function mnd_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'mndgroup',
		array(
			'title'    => __( 'MND Group', 'mndgroup' ),
			'priority' => 30,
		)
	);

	$wp_customize->add_setting(
		'mnd_hero_layout',
		array(
			'default'           => 'carousel',
			'sanitize_callback' => 'mnd_sanitize_hero_layout',
		)
	);
	$wp_customize->add_control(
		'mnd_hero_layout',
		array(
			'label'       => __( 'Homepage banners', 'mndgroup' ),
			'description' => __( 'Banners are managed under Slides.', 'mndgroup' ),
			'section'     => 'mndgroup',
			'type'        => 'radio',
			'choices'     => array(
				'carousel' => __( 'Slideshow (one banner at a time)', 'mndgroup' ),
				'stack'    => __( 'All banners below each other', 'mndgroup' ),
			),
		)
	);

	$wp_customize->add_setting(
		'mnd_hero_interval',
		array(
			'default'           => 6,
			'sanitize_callback' => 'mnd_sanitize_interval',
		)
	);
	$wp_customize->add_control(
		'mnd_hero_interval',
		array(
			'label'       => __( 'Slideshow interval (seconds)', 'mndgroup' ),
			'section'     => 'mndgroup',
			'type'        => 'number',
			'input_attrs' => array(
				'min'  => 3,
				'max'  => 20,
				'step' => 1,
			),
		)
	);

	$wp_customize->add_setting(
		'mnd_show_kkcg',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'mnd_show_kkcg',
		array(
			'label'   => __( 'Show the KKCG logo in the footer (large screens)', 'mndgroup' ),
			'section' => 'mndgroup',
			'type'    => 'checkbox',
		)
	);

	$wp_customize->add_setting(
		'mnd_ga_id',
		array(
			'default'           => '',
			'sanitize_callback' => 'mnd_sanitize_ga_id',
		)
	);
	$wp_customize->add_control(
		'mnd_ga_id',
		array(
			'label'       => __( 'Google Analytics 4 – measurement ID', 'mndgroup' ),
			'description' => __( 'For example G-XXXXXXXXXX. Visitors first see a cookie bar, analytics starts only after they accept. Leave empty to disable analytics.', 'mndgroup' ),
			'section'     => 'mndgroup',
			'type'        => 'text',
		)
	);
}
add_action( 'customize_register', 'mnd_customize_register' );

/**
 * @param string $value Hodnota.
 * @return string
 */
function mnd_sanitize_hero_layout( $value ) {
	return in_array( $value, array( 'carousel', 'stack' ), true ) ? $value : 'carousel';
}

/**
 * @param mixed $value Hodnota.
 * @return int
 */
function mnd_sanitize_interval( $value ) {
	return max( 3, min( 20, absint( $value ) ) );
}

/**
 * @param string $value Hodnota.
 * @return string
 */
function mnd_sanitize_ga_id( $value ) {
	$value = strtoupper( trim( (string) $value ) );
	return preg_match( '/^G-[A-Z0-9]+$/', $value ) ? $value : '';
}
