<?php
/**
 * Patička webu.
 *
 * @package MNDGroup
 */

defined( 'ABSPATH' ) || exit;
?>

<footer class="site-footer">
	<div class="site-footer__inner">
		<div class="site-footer__grid">
			<?php if ( is_active_sidebar( 'sidebar-footer' ) ) : ?>
				<div class="site-footer__contact">
					<?php dynamic_sidebar( 'sidebar-footer' ); ?>
				</div>
			<?php endif; ?>
			<div class="site-footer__map">
				<img src="<?php echo esc_url( MNDGROUP_URI . '/assets/img/map.png' ); ?>" width="420" height="300" loading="lazy" decoding="async" alt="<?php esc_attr_e( 'Map of Europe with the countries where MND Group AG operates', 'mndgroup' ); ?>">
			</div>
		</div>

		<p class="site-footer__info"><?php mndgroup_copyright(); ?></p>
	</div>

	<?php if ( get_theme_mod( 'mndgroup_show_kkcg', true ) ) : ?>
		<img class="site-footer__kkcg" src="<?php echo esc_url( MNDGROUP_URI . '/assets/img/kkcg.png' ); ?>" width="347" height="155" loading="lazy" decoding="async" alt="KKCG">
	<?php endif; ?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
