<?php
/**
 * Patička webu.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;
?>

<footer class="mnd-footer">
	<div class="mnd-footer__inner">
		<div class="mnd-footer__grid">
			<?php if ( is_active_sidebar( 'sidebar-footer' ) ) : ?>
				<div class="mnd-footer__contact">
					<?php dynamic_sidebar( 'sidebar-footer' ); ?>
				</div>
			<?php endif; ?>
			<div class="mnd-footer__map">
				<img src="<?php echo esc_url( MND_URI . '/assets/img/map.png' ); ?>" width="420" height="300" loading="lazy" decoding="async" alt="<?php esc_attr_e( 'Map of Europe with the countries where MND Group AG operates', 'mndgroup' ); ?>">
			</div>
		</div>

		<p class="mnd-footer__info"><?php mnd_copyright(); ?><?php do_action( 'mnd_footer_info' ); // např. „Nastavení cookies“ ?></p>
	</div>

	<?php if ( get_theme_mod( 'mnd_show_kkcg', true ) ) : ?>
		<img class="mnd-footer__kkcg" src="<?php echo esc_url( MND_URI . '/assets/img/kkcg.png' ); ?>" width="347" height="155" loading="lazy" decoding="async" alt="KKCG">
	<?php endif; ?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
