<?php
/**
 * Bannery v úvodu stránky (typ obsahu „slide“).
 *
 * Režim „carousel“ zobrazuje jeden banner a střídá je, režim „stack“ je
 * zobrazí všechny pod sebou. Bez JavaScriptu se v režimu carousel ukáže první banner.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;

$mndgroup_slides = mndgroup_get_slides();
if ( ! $mndgroup_slides ) {
	return;
}

$mndgroup_count    = count( $mndgroup_slides );
$mndgroup_carousel = 'carousel' === get_theme_mod( 'mndgroup_hero_layout', 'carousel' ) && $mndgroup_count > 1;
$mndgroup_interval = (int) get_theme_mod( 'mndgroup_hero_interval', 6 ) * 1000;
?>
<section
	class="hero <?php echo $mndgroup_carousel ? 'hero--carousel' : 'hero--stack'; ?>"
	aria-label="<?php esc_attr_e( 'Business areas', 'mndgroup' ); ?>"
	<?php if ( $mndgroup_carousel ) : ?>
		data-carousel
		data-interval="<?php echo esc_attr( (string) $mndgroup_interval ); ?>"
		aria-roledescription="<?php esc_attr_e( 'carousel', 'mndgroup' ); ?>"
	<?php endif; ?>
>
	<div class="hero__slides"<?php echo $mndgroup_carousel ? ' aria-live="off"' : ''; ?>>
		<?php foreach ( $mndgroup_slides as $mndgroup_i => $mndgroup_slide ) : ?>
			<div
				class="hero__slide<?php echo 0 === $mndgroup_i ? ' is-active' : ''; ?>"
				<?php if ( $mndgroup_carousel ) : ?>
					role="group"
					aria-roledescription="<?php esc_attr_e( 'slide', 'mndgroup' ); ?>"
					<?php /* translators: 1: slide number, 2: number of slides. */ ?>
					aria-label="<?php echo esc_attr( sprintf( __( '%1$s of %2$s', 'mndgroup' ), $mndgroup_i + 1, $mndgroup_count ) ); ?>"
					<?php echo 0 === $mndgroup_i ? '' : 'aria-hidden="true"'; ?>
				<?php endif; ?>
			>
				<?php
				echo wp_get_attachment_image(
					get_post_thumbnail_id( $mndgroup_slide ),
					'full',
					false,
					array(
						'class'         => 'hero__image',
						'alt'           => '',
						'sizes'         => '100vw',
						'loading'       => 0 === $mndgroup_i ? 'eager' : 'lazy',
						'fetchpriority' => 0 === $mndgroup_i ? 'high' : 'low',
					)
				);
				?>
				<div class="hero__overlay" aria-hidden="true"></div>
				<p class="hero__caption"><?php echo esc_html( get_the_title( $mndgroup_slide ) ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $mndgroup_carousel ) : ?>
		<div class="hero__nav">
			<button type="button" class="hero__btn" data-carousel-prev aria-label="<?php esc_attr_e( 'Previous slide', 'mndgroup' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<div class="hero__dots" data-carousel-dots></div>
			<button type="button" class="hero__btn" data-carousel-next aria-label="<?php esc_attr_e( 'Next slide', 'mndgroup' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<button type="button" class="hero__btn hero__btn--pause" data-carousel-pause aria-label="<?php esc_attr_e( 'Pause slideshow', 'mndgroup' ); ?>">
				<svg class="hero__icon-pause" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M8 5v14M16 5v14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
				<svg class="hero__icon-play" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M8 5l11 7-11 7z" fill="currentColor"/></svg>
			</button>
		</div>
	<?php endif; ?>
</section>
