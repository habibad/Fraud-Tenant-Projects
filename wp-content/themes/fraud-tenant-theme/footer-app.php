<?php
/**
 * Authenticated app shell — footer.
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;
?>
			<footer class="ft-main-foot">
				<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Fraud Tenant</span>
				<a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>"><?php esc_html_e( 'Terms', 'fraud-tenant-theme' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'fraud-tenant-theme' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/tenant-rights/' ) ); ?>"><?php esc_html_e( 'Tenant rights', 'fraud-tenant-theme' ); ?></a>
			</footer>
		</main>
	</div>
</div>
<?php wp_footer(); ?>
</body>
</html>
