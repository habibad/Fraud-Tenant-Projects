<?php
/**
 * Footer template for Fraud Tenant Theme.
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main><!-- /#main-content -->

<footer class="site-footer">
	<div class="container footer-top">
		<div class="footer-grid">
			<!-- Col 1: Brand -->
			<div class="footer-brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand-logo" rel="home">
					<svg class="brand-icon" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M18 3L4 9V17C4 24.5 10 31.4 18 33C26 31.4 32 24.5 32 17V9L18 3Z" fill="#0E9488" fill-opacity="0.15" stroke="#0E9488" stroke-width="2.5" stroke-linejoin="round"/>
						<path d="M18 10V26" stroke="#0E9488" stroke-width="2.5" stroke-linecap="round"/>
						<path d="M12 18H24" stroke="#0E9488" stroke-width="2.5" stroke-linecap="round"/>
					</svg>
					<span style="color: #FFFFFF;">Fraud<span style="color: #0E9488;">Tenant</span></span>
				</a>
				<p><?php esc_html_e( 'Building a safer, transparent rental ecosystem for independent property owners and tenants across the United States.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<!-- Col 2: Navigation -->
			<div class="footer-col">
				<h4><?php esc_html_e( 'Platform', 'fraud-tenant-theme' ); ?></h4>
				<ul class="footer-links">
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'fraud-tenant-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/how-it-works/' ) ); ?>"><?php esc_html_e( 'How It Works', 'fraud-tenant-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/for-property-owners/' ) ); ?>"><?php esc_html_e( 'For Property Owners', 'fraud-tenant-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/for-tenants/' ) ); ?>"><?php esc_html_e( 'For Tenants', 'fraud-tenant-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php esc_html_e( 'FAQ', 'fraud-tenant-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'fraud-tenant-theme' ); ?></a></li>
				</ul>
			</div>

			<!-- Col 3: Educational -->
			<div class="footer-col">
				<h4><?php esc_html_e( 'Transparency', 'fraud-tenant-theme' ); ?></h4>
				<ul class="footer-links">
					<li><a href="<?php echo esc_url( home_url( '/tenant-rights/' ) ); ?>"><?php esc_html_e( 'Tenant Rights & Rights Notice', 'fraud-tenant-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/how-it-works/#responsible-screening' ) ); ?>"><?php esc_html_e( 'Responsible Screening', 'fraud-tenant-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/for-tenants/#dispute-process' ) ); ?>"><?php esc_html_e( 'Dispute Resolution', 'fraud-tenant-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/for-property-owners/#compliance' ) ); ?>"><?php esc_html_e( 'Permissible Purpose', 'fraud-tenant-theme' ); ?></a></li>
				</ul>
			</div>

			<!-- Col 4: Legal -->
			<div class="footer-col">
				<h4><?php esc_html_e( 'Legal', 'fraud-tenant-theme' ); ?></h4>
				<ul class="footer-links">
					<li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'fraud-tenant-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>"><?php esc_html_e( 'Terms of Service', 'fraud-tenant-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/tenant-rights/' ) ); ?>"><?php esc_html_e( 'FCRA Educational Notice', 'fraud-tenant-theme' ); ?></a></li>
				</ul>
			</div>
		</div>
	</div>

	<div class="container footer-bottom">
		<div class="footer-bottom-inner">
			<div>
				&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'fraud-tenant-theme' ); ?>
			</div>
			<div class="footer-disclaimer">
				<?php esc_html_e( 'Educational & Compliance Notice: Fraud Tenant provides structured rental incident documentation and screening verification tools. Incident records represent factual dispute allegations subject to administrative review. An allegation or record does not by itself establish legal fraud or guilt. Production deployment requires formal legal review under the Fair Credit Reporting Act (FCRA) and applicable state laws.', 'fraud-tenant-theme' ); ?>
			</div>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

