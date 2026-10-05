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

$mnd_slides = mnd_get_slides();
if ( ! $mnd_slides ) {
	return;
}

$mnd_count    = count( $mnd_slides );
$mnd_carousel = 'carousel' === get_theme_mod( 'mnd_hero_layout', 'carousel' ) && $mnd_count > 1;
$mnd_interval = (int) get_theme_mod( 'mnd_hero_interval', 6 ) * 1000;
?>
<section
	class="mnd-hero <?php echo $mnd_carousel ? 'mnd-hero--carousel' : 'mnd-hero--stack'; ?>"
	aria-label="<?php esc_attr_e( 'Business areas', 'mndgroup' ); ?>"
	<?php if ( $mnd_carousel ) : ?>
		data-mnd-carousel
		data-interval="<?php echo esc_attr( (string) $mnd_interval ); ?>"
		aria-roledescription="<?php esc_attr_e( 'carousel', 'mndgroup' ); ?>"
	<?php endif; ?>
>
	<div class="mnd-hero__slides"<?php echo $mnd_carousel ? ' aria-live="off"' : ''; ?>>
		<?php foreach ( $mnd_slides as $mnd_i => $mnd_slide ) : ?>
			<div
				class="mnd-hero__slide<?php echo 0 === $mnd_i ? ' is-active' : ''; ?>"
				<?php if ( $mnd_carousel ) : ?>
					role="group"
					aria-roledescription="<?php esc_attr_e( 'slide', 'mndgroup' ); ?>"
					<?php /* translators: 1: slide number, 2: number of slides. */ ?>
					aria-label="<?php echo esc_attr( sprintf( __( '%1$s of %2$s', 'mndgroup' ), $mnd_i + 1, $mnd_count ) ); ?>"
					<?php echo 0 === $mnd_i ? '' : 'aria-hidden="true"'; ?>
				<?php endif; ?>
			>
				<?php
				echo wp_get_attachment_image(
					get_post_thumbnail_id( $mnd_slide ),
					'full',
					false,
					array(
						'class'         => 'mnd-hero__image',
						'alt'           => '',
						'sizes'         => '100vw',
						'loading'       => 0 === $mnd_i ? 'eager' : 'lazy',
						'fetchpriority' => 0 === $mnd_i ? 'high' : 'low',
					)
				);
				?>
				<div class="mnd-hero__overlay" aria-hidden="true"></div>
				<p class="mnd-hero__caption"><?php echo esc_html( get_the_title( $mnd_slide ) ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $mnd_carousel ) : ?>
		<div class="mnd-hero__nav">
			<button type="button" class="mnd-hero__btn" data-mnd-carousel-prev aria-label="<?php esc_attr_e( 'Previous slide', 'mndgroup' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<div class="mnd-hero__dots" data-mnd-carousel-dots></div>
			<button type="button" class="mnd-hero__btn" data-mnd-carousel-next aria-label="<?php esc_attr_e( 'Next slide', 'mndgroup' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<button type="button" class="mnd-hero__btn mnd-hero__btn--pause" data-mnd-carousel-pause aria-label="<?php esc_attr_e( 'Pause slideshow', 'mndgroup' ); ?>">
				<svg class="mnd-hero__icon-pause" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M8 5v14M16 5v14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
				<svg class="mnd-hero__icon-play" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M8 5l11 7-11 7z" fill="currentColor"/></svg>
			</button>
		</div>
	<?php endif; ?>
</section>
