<?php
/**
 * Front Page Template for Fraud Tenant.
 * Adheres precisely to Fraud Tenant Product UI Showcase.
 *
 * @package FraudTenantTheme
 */

get_header();
$theme_uri = get_template_directory_uri();
?>

<!-- 1. HERO SECTION -->
<section class="hero-section">
	<div class="container hero-grid">
		<div class="hero-content">
			<div class="hero-badge">
				<span class="badge badge-teal">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
					</svg>
					<?php esc_html_e( 'A Safer Rental Community', 'fraud-tenant-theme' ); ?>
				</span>
			</div>
			<h1 class="hero-title">
				<?php esc_html_e( 'Make Smarter', 'fraud-tenant-theme' ); ?><br>
				<?php esc_html_e( 'Rental Decisions', 'fraud-tenant-theme' ); ?><br>
				<?php esc_html_e( 'with', 'fraud-tenant-theme' ); ?> <span class="text-teal"><?php esc_html_e( 'Confidence', 'fraud-tenant-theme' ); ?></span>
			</h1>
			<p class="hero-subtitle">
				<?php esc_html_e( 'Access verified rental history reports, document past issues, and connect with a trusted community. Designed for individual property owners and tenants across the United States.', 'fraud-tenant-theme' ); ?>
			</p>
			<div class="hero-cta-group">
				<a href="<?php echo esc_url( home_url( '/how-it-works/' ) ); ?>" class="btn btn-primary btn-lg">
					<?php esc_html_e( 'Get Started', 'fraud-tenant-theme' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/how-it-works/' ) ); ?>" class="btn btn-outline btn-lg">
					<?php esc_html_e( 'Learn How It Works', 'fraud-tenant-theme' ); ?>
				</a>
			</div>
			<div class="hero-trust-bar">
				<img src="<?php echo esc_url( $theme_uri . '/assets/images/hero-avatars.png' ); ?>" alt="<?php esc_attr_e( 'Community members', 'fraud-tenant-theme' ); ?>" class="trust-avatar-strip" width="78" height="30">
				<div class="trust-text">
					<strong><?php esc_html_e( 'Trusted platform architecture', 'fraud-tenant-theme' ); ?></strong>
					<br><?php esc_html_e( 'Designed for property owners and tenants across the United States.', 'fraud-tenant-theme' ); ?>
				</div>
			</div>
		</div>

		<!-- Showcase Visual Composition -->
		<div class="hero-visual">
			<div class="hero-showcase-frame">
				<img src="<?php echo esc_url( $theme_uri . '/assets/images/hero-showcase-wide.png' ); ?>" alt="<?php esc_attr_e( 'Fraud Tenant platform preview showing verified reporting and tenant lookup', 'fraud-tenant-theme' ); ?>" class="hero-showcase-img" width="335" height="340">
				<div class="sample-watermark" aria-hidden="true">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<circle cx="12" cy="12" r="10"></circle>
						<line x1="12" y1="16" x2="12" y2="12"></line>
						<line x1="12" y1="8" x2="12.01" y2="8"></line>
					</svg>
					<?php esc_html_e( 'Illustrative Sample Preview', 'fraud-tenant-theme' ); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Organic Curved Bottom Wave -->
	<div class="hero-bottom-curve" aria-hidden="true">
		<svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
			<path d="M0 20C240 50 480 60 720 40C960 20 1200 45 1440 25V60H0V20Z" fill="#FFFFFF"/>
		</svg>
	</div>
</section>

<!-- 2. HOW IT WORKS -->
<section id="how-it-works" class="section-spacing" style="background-color: var(--ft-white); padding-top: 20px;">
	<div class="container">
		<div class="section-header">
			<span class="badge badge-teal"><?php esc_html_e( 'Simple. Secure. Effective.', 'fraud-tenant-theme' ); ?></span>
			<h2><?php esc_html_e( 'How It Works', 'fraud-tenant-theme' ); ?></h2>
			<p class="lead-text"><?php esc_html_e( 'A straightforward process for property owners and tenants.', 'fraud-tenant-theme' ); ?></p>
		</div>

		<div class="process-grid">
			<div class="process-card">
				<div class="process-icon-wrap">
					<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
						<circle cx="8.5" cy="7" r="4"></circle>
						<line x1="20" y1="8" x2="20" y2="14"></line>
						<line x1="23" y1="11" x2="17" y2="11"></line>
					</svg>
				</div>
				<h3><?php esc_html_e( '1. Register', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Create your account as a property owner or tenant with necessary details.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="process-arrow" aria-hidden="true">&rarr;</div>

			<div class="process-card">
				<div class="process-icon-wrap">
					<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
						<polyline points="14 2 14 8 20 8"></polyline>
						<path d="M9 15l2 2 4-4"></path>
					</svg>
				</div>
				<h3><?php esc_html_e( '2. Get Verified', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Verify your email with OTP and wait for admin approval.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="process-arrow" aria-hidden="true">&rarr;</div>

			<div class="process-card">
				<div class="process-icon-wrap">
					<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
					</svg>
				</div>
				<h3><?php esc_html_e( '3. Access Features', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Search reports, submit incidents, or manage your information.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="process-arrow" aria-hidden="true">&rarr;</div>

			<div class="process-card">
				<div class="process-icon-wrap">
					<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
						<circle cx="9" cy="7" r="4"></circle>
						<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
						<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
					</svg>
				</div>
				<h3><?php esc_html_e( '4. Build Safer Rentals', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Make informed decisions and help create a trusted rental community.', 'fraud-tenant-theme' ); ?></p>
			</div>
		</div>
	</div>
</section>

<!-- 3. DUAL AUDIENCE SPLIT CARDS -->
<section class="section-spacing">
	<div class="container">
		<div class="audience-grid">
			<!-- For Property Owners Card -->
			<div class="audience-card audience-card-split">
				<div class="audience-card-text">
					<div class="audience-card-header">
						<div class="audience-icon-badge">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
								<polyline points="9 22 9 12 15 12 15 22"></polyline>
							</svg>
						</div>
						<h3><?php esc_html_e( 'For Property Owners', 'fraud-tenant-theme' ); ?></h3>
					</div>

					<ul class="audience-check-list">
						<li>
							<span class="teal-check" aria-hidden="true">&bull;</span>
							<span><?php esc_html_e( 'Search tenant rental history (SSN required)', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<span class="teal-check" aria-hidden="true">&bull;</span>
							<span><?php esc_html_e( 'View full reports with membership', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<span class="teal-check" aria-hidden="true">&bull;</span>
							<span><?php esc_html_e( 'Submit and document past incidents', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<span class="teal-check" aria-hidden="true">&bull;</span>
							<span><?php esc_html_e( 'Manage your properties', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<span class="teal-check" aria-hidden="true">&bull;</span>
							<span><?php esc_html_e( 'Make informed rental decisions', 'fraud-tenant-theme' ); ?></span>
						</li>
					</ul>

					<a href="<?php echo esc_url( home_url( '/for-property-owners/' ) ); ?>" class="btn btn-primary btn-sm">
						<?php esc_html_e( 'Learn More For Property Owners', 'fraud-tenant-theme' ); ?> &rarr;
					</a>
				</div>

				<div class="audience-card-visual">
					<img src="<?php echo esc_url( $theme_uri . '/assets/images/owner-card-visual.png' ); ?>" alt="<?php esc_attr_e( 'Property owner searching rental records', 'fraud-tenant-theme' ); ?>" class="audience-visual-img" width="128" height="174">
				</div>
			</div>

			<!-- For Tenants Card -->
			<div class="audience-card audience-card-split">
				<div class="audience-card-text">
					<div class="audience-card-header">
						<div class="audience-icon-badge">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
								<circle cx="12" cy="7" r="4"></circle>
							</svg>
						</div>
						<h3><?php esc_html_e( 'For Tenants', 'fraud-tenant-theme' ); ?></h3>
					</div>

					<ul class="audience-check-list">
						<li>
							<span class="teal-check" aria-hidden="true">&bull;</span>
							<span><?php esc_html_e( 'Access your own records', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<span class="teal-check" aria-hidden="true">&bull;</span>
							<span><?php esc_html_e( 'Request corrections or dispute reports', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<span class="teal-check" aria-hidden="true">&bull;</span>
							<span><?php esc_html_e( 'Keep your information transparent', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<span class="teal-check" aria-hidden="true">&bull;</span>
							<span><?php esc_html_e( 'Build a positive rental history', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<span class="teal-check" aria-hidden="true">&bull;</span>
							<span><?php esc_html_e( 'Be part of a trustworthy community', 'fraud-tenant-theme' ); ?></span>
						</li>
					</ul>

					<a href="<?php echo esc_url( home_url( '/for-tenants/' ) ); ?>" class="btn btn-primary btn-sm">
						<?php esc_html_e( 'Learn More For Tenants', 'fraud-tenant-theme' ); ?> &rarr;
					</a>
				</div>

				<div class="audience-card-visual">
					<img src="<?php echo esc_url( $theme_uri . '/assets/images/tenant-card-visual.png' ); ?>" alt="<?php esc_attr_e( 'Tenant reviewing rental record history', 'fraud-tenant-theme' ); ?>" class="audience-visual-img" width="128" height="174">
				</div>
			</div>
		</div>
	</div>
</section>

<!-- 4. WHY CHOOSE FRAUD TENANT -->
<section class="section-spacing" style="background-color: var(--ft-white);">
	<div class="container">
		<div class="section-header">
			<h2><?php esc_html_e( 'Why Choose Fraud Tenant?', 'fraud-tenant-theme' ); ?></h2>
			<p class="lead-text"><?php esc_html_e( 'A trusted platform built for a safer and more transparent rental ecosystem.', 'fraud-tenant-theme' ); ?></p>
		</div>

		<div class="why-grid">
			<div class="why-card">
				<div class="why-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
						<circle cx="9" cy="7" r="4"></circle>
						<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
						<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
					</svg>
				</div>
				<h3><?php esc_html_e( 'Trusted Community', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Verified users and documented reports', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="why-card">
				<div class="why-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
						<polyline points="14 2 14 8 20 8"></polyline>
						<line x1="16" y1="13" x2="8" y2="13"></line>
						<line x1="16" y1="17" x2="8" y2="17"></line>
						<polyline points="10 9 9 9 8 9"></polyline>
					</svg>
				</div>
				<h3><?php esc_html_e( 'Transparency', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Clear and fair information for both sides', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="why-card">
				<div class="why-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
					</svg>
				</div>
				<h3><?php esc_html_e( 'Security & Privacy', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Your data is protected and confidential', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="why-card">
				<div class="why-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
					</svg>
				</div>
				<h3><?php esc_html_e( 'Peace of Mind', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Make better decisions and reduce rental risk', 'fraud-tenant-theme' ); ?></p>
			</div>
		</div>
	</div>
</section>

<!-- 5. CONTACT / WE'RE HERE TO HELP SECTION -->
<section id="contact" class="section-spacing">
	<div class="container">
		<div class="showcase-contact-card">
			<!-- House Photo on Left -->
			<div class="contact-card-photo">
				<img src="<?php echo esc_url( $theme_uri . '/assets/images/contact-house-visual.png' ); ?>" alt="<?php esc_attr_e( 'Residential community houses', 'fraud-tenant-theme' ); ?>" class="contact-house-img" width="220" height="160">
			</div>

			<!-- Center Form Area -->
			<div class="contact-card-main">
				<div class="contact-badge-wrap">
					<span class="badge badge-teal"><?php esc_html_e( 'Have Questions?', 'fraud-tenant-theme' ); ?></span>
				</div>
				<h2><?php esc_html_e( "We're Here to Help", 'fraud-tenant-theme' ); ?></h2>
				<p class="contact-card-sub">
					<?php esc_html_e( 'Not sure how it works or need more information? Send us an enquiry and our team will get back to you soon.', 'fraud-tenant-theme' ); ?>
				</p>

				<?php get_template_part( 'template-parts/contact-form' ); ?>
			</div>

			<!-- Right Sidebar Box -->
			<div class="contact-card-sidebar">
				<h3><?php esc_html_e( 'Other Ways to Reach Us', 'fraud-tenant-theme' ); ?></h3>
				<ul class="contact-info-list-light">
					<li class="contact-info-item-light">
						<div class="contact-icon-teal">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
								<polyline points="22,6 12,13 2,6"></polyline>
							</svg>
						</div>
						<div>
							<span class="info-label"><?php esc_html_e( 'Email Us', 'fraud-tenant-theme' ); ?></span>
							<a href="mailto:support@fraudtenant.com" class="info-val">support@fraudtenant.com</a>
						</div>
					</li>

					<li class="contact-info-item-light">
						<div class="contact-icon-teal">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
							</svg>
						</div>
						<div>
							<span class="info-label"><?php esc_html_e( 'Helpdesk Support', 'fraud-tenant-theme' ); ?></span>
							<span class="info-val"><?php esc_html_e( 'Digital Inquiries via Form', 'fraud-tenant-theme' ); ?></span>
						</div>
					</li>

					<li class="contact-info-item-light">
						<div class="contact-icon-teal">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
								<circle cx="12" cy="10" r="3"></circle>
							</svg>
						</div>
						<div>
							<span class="info-label"><?php esc_html_e( 'Service Area', 'fraud-tenant-theme' ); ?></span>
							<span class="info-val"><?php esc_html_e( 'United States (National)', 'fraud-tenant-theme' ); ?></span>
						</div>
					</li>

					<li class="contact-info-item-light">
						<div class="contact-icon-teal">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<circle cx="12" cy="12" r="10"></circle>
								<polyline points="12 6 12 12 16 14"></polyline>
							</svg>
						</div>
						<div>
							<span class="info-label"><?php esc_html_e( 'Support Hours', 'fraud-tenant-theme' ); ?></span>
							<span class="info-val"><?php esc_html_e( 'Mon - Fri, 8:00 AM - 6:00 PM (EST)', 'fraud-tenant-theme' ); ?></span>
						</div>
					</li>
				</ul>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
