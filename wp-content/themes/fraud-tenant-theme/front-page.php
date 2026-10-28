<?php
/**
 * Front Page Template for Fraud Tenant.
 *
 * @package FraudTenantTheme
 */

get_header();
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
				<?php esc_html_e( 'Make Smarter Rental Decisions with', 'fraud-tenant-theme' ); ?>
				<span class="text-teal"><?php esc_html_e( 'Confidence', 'fraud-tenant-theme' ); ?></span>
			</h1>
			<p class="hero-subtitle">
				<?php esc_html_e( 'Access verified rental history reports, document past issues, and connect with a trusted community. Designed for individual property owners and tenants across the United States.', 'fraud-tenant-theme' ); ?>
			</p>
			<div class="hero-cta-group">
				<a href="<?php echo esc_url( home_url( '/how-it-works/' ) ); ?>" class="btn btn-primary btn-lg">
					<?php esc_html_e( 'Get Started', 'fraud-tenant-theme' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/for-property-owners/' ) ); ?>" class="btn btn-outline btn-lg">
					<?php esc_html_e( 'Learn How It Works', 'fraud-tenant-theme' ); ?>
				</a>
			</div>
			<div class="hero-trust-bar">
				<div class="trust-avatars" aria-hidden="true">
					<span class="trust-avatar">JS</span>
					<span class="trust-avatar" style="background:#CCFBF1; color:#0F766E;">EC</span>
					<span class="trust-avatar" style="background:#FEF3C7; color:#B45309;">MR</span>
					<span class="trust-avatar" style="background:#E0E7FF; color:#4338CA;">DL</span>
				</div>
				<div class="trust-text">
					<strong><?php esc_html_e( 'Trusted by property owners and tenants', 'fraud-tenant-theme' ); ?></strong>
					<br><?php esc_html_e( 'Building transparent, responsible housing standards nationwide.', 'fraud-tenant-theme' ); ?>
				</div>
			</div>
		</div>

		<!-- Showcase Visual Composition -->
		<div class="hero-visual" aria-hidden="true">
			<div class="hero-visual-card">
				<div class="visual-top-badge">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
					</svg>
					Make Smarter Rental Decisions
				</div>

				<div class="visual-main-box">
					<div class="visual-row">
						<span class="visual-label">Rental Screening Status</span>
						<span class="visual-val" style="color: #0E9488;">● Verified Registry</span>
					</div>
					<div class="visual-row">
						<span class="visual-label">Dispute Mechanism</span>
						<span class="visual-val">FCRA-Aligned Rights</span>
					</div>
					<div class="visual-row">
						<span class="visual-label">Identity Match Security</span>
						<span class="visual-val">Keyed HMAC-SHA256</span>
					</div>
				</div>

				<!-- Floating Sample Badges -->
				<div class="floating-pill floating-pill-1">
					<div class="floating-icon-wrap">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
							<circle cx="8.5" cy="7" r="4"></circle>
							<polyline points="17 11 19 13 23 9"></polyline>
						</svg>
					</div>
					<div>
						<div style="font-size: 0.85rem; font-weight: 700; color: #14263E;">Applicant Record</div>
						<div style="font-size: 0.75rem; color: #64748B;">Masked Match: ••••-1234</div>
					</div>
				</div>

				<div class="floating-pill floating-pill-2">
					<div class="floating-icon-wrap" style="background:#FEF3C7; color:#D97706;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
						</svg>
					</div>
					<div>
						<div style="font-size: 0.85rem; font-weight: 700; color: #14263E;">Safer Tenancies</div>
						<div style="font-size: 0.75rem; color: #64748B;">Stronger Communities</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- 2. HOW IT WORKS -->
<section id="how-it-works" class="section-spacing" style="background-color: var(--ft-white);">
	<div class="container">
		<div class="section-header">
			<span class="badge badge-teal"><?php esc_html_e( 'Simple. Secure. Effective.', 'fraud-tenant-theme' ); ?></span>
			<h2><?php esc_html_e( 'How It Works', 'fraud-tenant-theme' ); ?></h2>
			<p class="lead-text"><?php esc_html_e( 'A straightforward, transparent process designed for property owners and tenants.', 'fraud-tenant-theme' ); ?></p>
		</div>

		<div class="process-grid">
			<div class="process-card">
				<div class="process-icon-wrap">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
						<circle cx="8.5" cy="7" r="4"></circle>
						<line x1="20" y1="8" x2="20" y2="14"></line>
						<line x1="23" y1="11" x2="17" y2="11"></line>
					</svg>
				</div>
				<div class="process-step-num"><?php esc_html_e( 'Step 01', 'fraud-tenant-theme' ); ?></div>
				<h3><?php esc_html_e( '1. Register', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Create your account as an independent property owner or tenant with necessary business or identity details.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="process-card">
				<div class="process-icon-wrap">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
						<polyline points="22,6 12,13 2,6"></polyline>
					</svg>
				</div>
				<div class="process-step-num"><?php esc_html_e( 'Step 02', 'fraud-tenant-theme' ); ?></div>
				<h3><?php esc_html_e( '2. Get Verified', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Verify your email with a 6-digit OTP and complete administrative account review to prevent impersonation.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="process-card">
				<div class="process-icon-wrap">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<circle cx="11" cy="11" r="8"></circle>
						<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
					</svg>
				</div>
				<div class="process-step-num"><?php esc_html_e( 'Step 03', 'fraud-tenant-theme' ); ?></div>
				<h3><?php esc_html_e( '3. Access Features', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Initiate permissible tenant screening, submit factual incident reports, or inspect your own records.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="process-card">
				<div class="process-icon-wrap">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
						<polyline points="9 22 9 12 15 12 15 22"></polyline>
					</svg>
				</div>
				<div class="process-step-num"><?php esc_html_e( 'Step 04', 'fraud-tenant-theme' ); ?></div>
				<h3><?php esc_html_e( '4. Build Safer Rentals', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Make informed rental decisions, protect tenant reputations, and foster transparency in the community.', 'fraud-tenant-theme' ); ?></p>
			</div>
		</div>
	</div>
</section>

<!-- 3. DUAL AUDIENCE VALUE PROPOSITIONS -->
<section class="section-spacing">
	<div class="container">
		<div class="section-header">
			<span class="badge badge-navy"><?php esc_html_e( 'Tailored Experiences', 'fraud-tenant-theme' ); ?></span>
			<h2><?php esc_html_e( 'Designed for Owners & Tenants', 'fraud-tenant-theme' ); ?></h2>
			<p class="lead-text"><?php esc_html_e( 'Balancing thorough risk reduction for landlords with accuracy and fairness protections for renters.', 'fraud-tenant-theme' ); ?></p>
		</div>

		<div class="audience-grid">
			<!-- Owner Card -->
			<div class="audience-card">
				<div class="audience-header">
					<h3>
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
						</svg>
						<?php esc_html_e( 'For Property Owners', 'fraud-tenant-theme' ); ?>
					</h3>
					<p><?php esc_html_e( 'Protect your rental investments with verified records and responsible screening.', 'fraud-tenant-theme' ); ?></p>
				</div>
				<div class="audience-body">
					<ul class="feature-list">
						<li>
							<svg class="check-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
								<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
							</svg>
							<span><strong><?php esc_html_e( 'Authorized Tenant Searches:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Initiate SSN-based matching backed by permissible-purpose certification.', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<svg class="check-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
								<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
							</svg>
							<span><strong><?php esc_html_e( 'Human-Reviewed Incident Reports:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Access documented rental disputes reviewed before disclosure.', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<svg class="check-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
								<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
							</svg>
							<span><strong><?php esc_html_e( 'Factual Documentation:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Submit incident records with objective evidence and owner attestation.', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<svg class="check-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
								<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
							</svg>
							<span><strong><?php esc_html_e( 'Property Organization:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Manage rental units and lease records from a consolidated dashboard.', 'fraud-tenant-theme' ); ?></span>
						</li>
					</ul>
					<a href="<?php echo esc_url( home_url( '/for-property-owners/' ) ); ?>" class="btn btn-primary" style="width: 100%;">
						<?php esc_html_e( 'Learn More for Property Owners →', 'fraud-tenant-theme' ); ?>
					</a>
				</div>
			</div>

			<!-- Tenant Card -->
			<div class="audience-card">
				<div class="audience-header">
					<h3>
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0E9488" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
							<circle cx="12" cy="7" r="4"></circle>
						</svg>
						<?php esc_html_e( 'For Tenants', 'fraud-tenant-theme' ); ?>
					</h3>
					<p><?php esc_html_e( 'Know what is on your record and preserve your positive housing reputation.', 'fraud-tenant-theme' ); ?></p>
				</div>
				<div class="audience-body">
					<ul class="feature-list">
						<li>
							<svg class="check-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
								<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
							</svg>
							<span><strong><?php esc_html_e( 'Complete Transparency:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Access and inspect rental records associated with your verified profile.', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<svg class="check-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
								<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
							</svg>
							<span><strong><?php esc_html_e( 'Dispute & Correction Rights:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Submit formal disputes, provide counter-evidence, and correct errors.', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<svg class="check-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
								<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
							</svg>
							<span><strong><?php esc_html_e( 'No Automated Defamation:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Allegations are not presented as proven guilt without administrative review.', 'fraud-tenant-theme' ); ?></span>
						</li>
						<li>
							<svg class="check-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
								<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
							</svg>
							<span><strong><?php esc_html_e( 'Privacy First:', 'fraud-tenant-theme' ); ?></strong> <?php esc_html_e( 'Your SSN is never stored in plaintext and records require authorized permissible purpose.', 'fraud-tenant-theme' ); ?></span>
						</li>
					</ul>
					<a href="<?php echo esc_url( home_url( '/for-tenants/' ) ); ?>" class="btn btn-outline" style="width: 100%;">
						<?php esc_html_e( 'Learn More for Tenants →', 'fraud-tenant-theme' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- 4. WHY CHOOSE FRAUD TENANT -->
<section class="section-spacing" style="background-color: var(--ft-white);">
	<div class="container">
		<div class="section-header">
			<span class="badge badge-teal"><?php esc_html_e( 'Core Values', 'fraud-tenant-theme' ); ?></span>
			<h2><?php esc_html_e( 'Why Choose Fraud Tenant?', 'fraud-tenant-theme' ); ?></h2>
			<p class="lead-text"><?php esc_html_e( 'A modern platform built for a safer, more transparent, and trustworthy rental ecosystem.', 'fraud-tenant-theme' ); ?></p>
		</div>

		<div class="why-grid">
			<div class="why-card">
				<div class="why-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
						<circle cx="9" cy="7" r="4"></circle>
						<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
						<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
					</svg>
				</div>
				<h3><?php esc_html_e( 'Trusted Community', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Verified users and documented rental history reports maintain accountability across all parties.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="why-card">
				<div class="why-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
						<polyline points="14 2 14 8 20 8"></polyline>
						<line x1="16" y1="13" x2="8" y2="13"></line>
						<line x1="16" y1="17" x2="8" y2="17"></line>
						<polyline points="10 9 9 9 8 9"></polyline>
					</svg>
				</div>
				<h3><?php esc_html_e( 'Transparency', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Clear, fair information backed by objective evidence and formal tenant dispute resolution.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="why-card">
				<div class="why-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
					</svg>
				</div>
				<h3><?php esc_html_e( 'Security & Privacy', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Keyed HMAC SSN matching, zero plaintext retention, and protected private document storage.', 'fraud-tenant-theme' ); ?></p>
			</div>

			<div class="why-card">
				<div class="why-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
					</svg>
				</div>
				<h3><?php esc_html_e( 'Peace of Mind', 'fraud-tenant-theme' ); ?></h3>
				<p><?php esc_html_e( 'Make informed housing decisions with confidence while upholding regulatory compliance.', 'fraud-tenant-theme' ); ?></p>
			</div>
		</div>
	</div>
</section>

<!-- 5. RESPONSIBLE REPORTING CALLOUT -->
<section class="section-spacing legal-callout-section">
	<div class="container">
		<div class="legal-callout-box">
			<h4><?php esc_html_e( 'Commitment to Responsible Reporting & FCRA Principles', 'fraud-tenant-theme' ); ?></h4>
			<p><?php esc_html_e( 'Fraud Tenant requires legitimate permissible purpose before any tenant screening is initiated. Unreviewed incident reports are treated as pending factual allegations and are never labeled as definitive proof of wrongdoing. Tenants hold the fundamental right to inspect their records and initiate formal dispute proceedings.', 'fraud-tenant-theme' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/tenant-rights/' ) ); ?>" style="font-weight: 600; font-size: 0.95rem;">
				<?php esc_html_e( 'Read our Tenant Rights & Screening Information Guide →', 'fraud-tenant-theme' ); ?>
			</a>
		</div>
	</div>
</section>

<!-- 6. CONTACT & ENQUIRY SECTION -->
<section id="contact" class="section-spacing">
	<div class="container">
		<div class="section-header">
			<span class="badge badge-teal"><?php esc_html_e( 'Have Questions?', 'fraud-tenant-theme' ); ?></span>
			<h2><?php esc_html_e( "We're Here to Help", 'fraud-tenant-theme' ); ?></h2>
			<p class="lead-text"><?php esc_html_e( 'Not sure how it works or need more information? Send us an enquiry and our team will get back to you promptly.', 'fraud-tenant-theme' ); ?></p>
		</div>

		<div class="contact-grid">
			<!-- Contact Form Card -->
			<div class="contact-form-card">
				<h3><?php esc_html_e( 'Send Us an Enquiry', 'fraud-tenant-theme' ); ?></h3>
				<p style="margin-bottom: 24px; font-size: 0.95rem;"><?php esc_html_e( 'Fill out the form below. We respond to all inquiries within 1 business day.', 'fraud-tenant-theme' ); ?></p>
				<?php get_template_part( 'template-parts/contact-form' ); ?>
			</div>

			<!-- Contact Info Card -->
			<div class="contact-info-card">
				<div>
					<h3><?php esc_html_e( 'Other Ways to Reach Us', 'fraud-tenant-theme' ); ?></h3>
					<ul class="contact-info-list">
						<li class="contact-info-item">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
								<polyline points="22,6 12,13 2,6"></polyline>
							</svg>
							<div>
								<strong><?php esc_html_e( 'Email Us', 'fraud-tenant-theme' ); ?></strong><br>
								<a href="mailto:support@fraudtenant.com">support@fraudtenant.com</a>
							</div>
						</li>
						<li class="contact-info-item">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
							</svg>
							<div>
								<strong><?php esc_html_e( 'Call Us', 'fraud-tenant-theme' ); ?></strong><br>
								<a href="tel:+18331234567">+1 (833) 123-4567</a>
							</div>
						</li>
						<li class="contact-info-item">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
								<circle cx="12" cy="10" r="3"></circle>
							</svg>
							<div>
								<strong><?php esc_html_e( 'Our Location', 'fraud-tenant-theme' ); ?></strong><br>
								125 Business Ave, New York, NY
							</div>
						</li>
						<li class="contact-info-item">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<circle cx="12" cy="12" r="10"></circle>
								<polyline points="12 6 12 12 16 14"></polyline>
							</svg>
							<div>
								<strong><?php esc_html_e( 'Support Hours', 'fraud-tenant-theme' ); ?></strong><br>
								Mon - Fri, 6:00 AM - 6:00 PM (EST)
							</div>
						</li>
					</ul>
				</div>
				<div style="font-size: 0.825rem; color: var(--ft-slate-400); border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
					<?php esc_html_e( 'For urgent screening or dispute questions, please include your applicant or property reference in your message.', 'fraud-tenant-theme' ); ?>
				</div>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();

