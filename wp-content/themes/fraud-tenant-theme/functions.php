<?php
/**
 * Fraud Tenant Theme functions and definitions.
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FRAUD_TENANT_THEME_VERSION', '1.0.6' );

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function fraud_tenant_theme_setup(): void {
	load_theme_textdomain( 'fraud-tenant-theme', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 40,
			'width'       => 200,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary Navigation', 'fraud-tenant-theme' ),
		)
	);
}
add_action( 'after_setup_theme', 'fraud_tenant_theme_setup' );

/**
 * Enqueue scripts and styles.
 */
function fraud_tenant_enqueue_assets(): void {
	wp_enqueue_style( 'fraud-tenant-style', get_stylesheet_uri(), array(), FRAUD_TENANT_THEME_VERSION );
	wp_enqueue_style( 'fraud-tenant-main', get_template_directory_uri() . '/assets/css/main.css', array( 'fraud-tenant-style' ), FRAUD_TENANT_THEME_VERSION );

	wp_enqueue_script( 'fraud-tenant-main', get_template_directory_uri() . '/assets/js/main.js', array(), FRAUD_TENANT_THEME_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'fraud_tenant_enqueue_assets' );

/**
 * Fallback menu when no custom WordPress menu is assigned.
 */
function fraud_tenant_fallback_menu(): void {
	$current_url = home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );
	$pages       = array(
		array(
			'title' => __( 'Home', 'fraud-tenant-theme' ),
			'url'   => home_url( '/' ),
			'slug'  => '',
		),
		array(
			'title' => __( 'How It Works', 'fraud-tenant-theme' ),
			'url'   => home_url( '/how-it-works/' ),
			'slug'  => 'how-it-works',
		),
		array(
			'title' => __( 'For Property Owners', 'fraud-tenant-theme' ),
			'url'   => home_url( '/for-property-owners/' ),
			'slug'  => 'for-property-owners',
		),
		array(
			'title' => __( 'For Tenants', 'fraud-tenant-theme' ),
			'url'   => home_url( '/for-tenants/' ),
			'slug'  => 'for-tenants',
		),
		array(
			'title' => __( 'FAQ', 'fraud-tenant-theme' ),
			'url'   => home_url( '/faq/' ),
			'slug'  => 'faq',
		),
		array(
			'title' => __( 'Contact', 'fraud-tenant-theme' ),
			'url'   => home_url( '/contact/' ),
			'slug'  => 'contact',
		),
	);

	echo '<ul class="nav-menu">';
	foreach ( $pages as $p ) {
		$is_current = false;
		if ( empty( $p['slug'] ) ) {
			$is_current = is_front_page();
		} else {
			$is_current = is_page( $p['slug'] );
		}
		$current_class = $is_current ? ' current-menu-item' : '';
		echo '<li class="' . esc_attr( $current_class ) . '"><a href="' . esc_url( $p['url'] ) . '">' . esc_html( $p['title'] ) . '</a></li>';
	}
	echo '</ul>';
}

/**
 * Note: Contact enquiry handling logic has been migrated into the custom plugin
 * `fraud-tenant-core` (\FraudTenant\Services\ContactEnquiryHandler) so that it remains
 * permanently active, secure, and resilient across theme changes.
 */

/**
 * Idempotent Public Pages Generator.
 * Creates the 9 required platform pages if they do not exist yet.
 */
function fraud_tenant_ensure_public_pages(): void {
	$pages_to_create = array(
		'home'              => array(
			'title'     => 'Home',
			'content'   => '',
			'is_front'  => true,
		),
		'how-it-works'      => array(
			'title'     => 'How It Works',
			'subtitle'  => 'A transparent, step-by-step overview of our tenant screening and incident reporting platform.',
			'content'   => '<h2>A Modern Approach to Rental Risk & Transparency</h2><p>Fraud Tenant was designed to address the unique challenges faced by independent property owners and residential renters across the United States. Traditional screening can be impersonal, rigid, or incomplete, while informal word-of-mouth reports risk inaccuracy and unfairness.</p><h3>The Four-Step Workflow</h3><ol><li><strong>Account Registration & Role Selection:</strong> Users create either a Property Owner account or a Tenant account. Each account collects only the minimal required profile and contact information.</li><li><strong>Email Verification & Administrative Review:</strong> To maintain community integrity, each account verifies its email with a secure 6-digit OTP and completes administrative review before accessing dashboard functionality.</li><li><strong>Authorized Tenant Screening:</strong> Eligible owners who have certified a permissible purpose under applicable housing and privacy laws may submit a tenant-matching request using SSN-based keyed tokens.</li><li><strong>Factual Incident Reporting & Dispute Management:</strong> Owners can record factual, objective lease violations or unpaid rent incidents with supporting documentation. Unreviewed allegations are never disclosed publicly, and tenants hold the right to inspect records and file disputes.</li></ol><div class="legal-callout-box"><h4>FCRA & Regulatory Alignment</h4><p>Fraud Tenant is committed to fair housing principles and the Fair Credit Reporting Act (FCRA). We do not label individuals as fraudulent based solely on an allegation, nor do we operate an open public blacklist.</p></div>',
		),
		'for-property-owners' => array(
			'title'     => 'For Property Owners',
			'subtitle'  => 'Protect your rental investments, document factual lease disputes, and screen applicants responsibly.',
			'content'   => '<h2>Responsible Tenant Screening for Independent Landlords</h2><p>As a property owner or residential landlord, selecting trustworthy tenants is critical to preserving your rental income and property value. Fraud Tenant is designed to equip property owners with structured, objective screening and incident reporting tools.</p><h3>Planned Landlord Features (Subject to Account Approval)</h3><ul><li><strong>Authorized SSN Tenant Matching:</strong> Verify applicant rental history securely without ever exposing or retaining plaintext Social Security Numbers.</li><li><strong>Documented Incident Records:</strong> Record past tenancy incidents such as unpaid rent, property damage beyond normal wear, or broken leases. All submissions require owner attestation and supporting evidence.</li><li><strong>Property & Lease Portfolio:</strong> Organize all your rental addresses and active tenancies in one unified dashboard shell.</li><li><strong>Membership Tiers:</strong> Access free neutral status inquiries or upgrade to paid monthly/annual memberships for full report disclosures and advanced portfolio management.</li></ul><div class="legal-callout-box"><h4>Permissible Purpose Requirement</h4><p>Under US federal and state regulations, tenant screening information may only be requested for legitimate housing decisions where the applicant has authorized screening. Landlords must certify permissible purpose before initiating an inquiry. Fraud Tenant does not guarantee tenant payment performance or legal immunity.</p></div>',
		),
		'for-tenants'        => array(
			'title'     => 'For Tenants',
			'subtitle'  => 'Inspect your rental records, dispute inaccuracies, and protect your housing reputation.',
			'content'   => '<h2>Transparency and Due Process for Renters</h2><p>Fraud Tenant is designed around the core principle that renters have the fundamental right to know what information is being reported about them, to review the accuracy of rental incident records, and to challenge false or inaccurate claims.</p><h3>Planned Tenant Features (Subject to Account Verification)</h3><ul><li><strong>Review Your Own Records:</strong> Approved tenant users can view rental history entries and incident reports associated with their verified profile.</li><li><strong>File Formal Disputes:</strong> If a report contains incorrect dates, amounts, or untrue claims, submit a dispute directly through the platform with counter-evidence.</li><li><strong>Human Administrative Review:</strong> Disputes are investigated by platform administrators. Incorrect records are updated, corrected, or dismissed.</li><li><strong>Bank-Grade Privacy:</strong> Your full Social Security Number is never stored in plain text or sold to third parties. Matching is conducted using cryptographic tokenization.</li></ul><div class="legal-callout-box"><h4>No Automated Fraud Labels</h4><p>An incident report submitted by a landlord is treated as a pending allegation until reviewed and verified. Allegations are never published as proven facts. The platform does not operate a public blacklist.</p></div>',
		),
		'faq'               => array(
			'title'     => 'Frequently Asked Questions',
			'subtitle'  => 'Common questions about accounts, screening, privacy, incident reports, and dispute resolution.',
			'content'   => '<div class="faq-list">
<div class="faq-item active">
	<button class="faq-question" aria-expanded="true">What is Fraud Tenant? <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></button>
	<div class="faq-answer">Fraud Tenant is an online platform for independent property owners and residential tenants in the United States. It facilitates responsible tenant screening, structured incident reporting, and transparent dispute resolution.</div>
</div>
<div class="faq-item">
	<button class="faq-question" aria-expanded="false">How is my Social Security Number protected? <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></button>
	<div class="faq-answer">Full SSNs are never stored in plain text in our database, never written to server logs, and never returned in API responses. Tenant matching uses a server-side keyed HMAC-SHA256 token, and only the last four digits (e.g. ••••-1234) are displayed in search history.</div>
</div>
<div class="faq-item">
	<button class="faq-question" aria-expanded="false">Can a landlord automatically label a tenant as fraudulent? <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></button>
	<div class="faq-answer">No. Unverified allegations are never published as facts. Incident reports require supporting documentation (such as lease agreements, ledger balances, or photos) and undergo human review. Tenants have the right to file disputes and submit counter-evidence.</div>
</div>
<div class="faq-item">
	<button class="faq-question" aria-expanded="false">What happens when a tenant disputes a report? <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></button>
	<div class="faq-answer">Disputed reports are flagged as pending investigation. An administrator reviews the statements and evidence provided by both parties. If the report is inaccurate, it is modified or dismissed. Complete audit trails of all corrections are maintained.</div>
</div>
<div class="faq-item">
	<button class="faq-question" aria-expanded="false">How does the 6-digit email OTP verification work? <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></button>
	<div class="faq-answer">During registration, a 6-digit verification code is sent to your email address. It expires in 10 minutes and allows up to 5 attempts. Codes are stored securely using HMAC-SHA256 hashing and are never logged in plain text.</div>
</div>
</div>',
		),
		'contact'           => array(
			'title'     => 'Contact & Support',
			'subtitle'  => 'Have questions or need assistance? Reach out to our customer support team.',
			'content'   => '<h2>We’re Here to Help</h2><p>Whether you are an independent property owner inquiring about screening services or a tenant seeking assistance with record review, our team is ready to assist you.</p><div class="showcase-contact-card"><div class="contact-card-main"><h3>Send an Inquiry</h3><p>Fill out the form below. Our support team responds to all platform inquiries within one business day.</p>[ft_contact_form]</div><div class="contact-card-sidebar"><h3>Other Ways to Reach Us</h3><ul class="contact-info-list-light"><li class="contact-info-item-light"><div><span class="info-label">Email Us</span><a href="mailto:support@fraudtenant.com" class="info-val">support@fraudtenant.com</a></div></li><li class="contact-info-item-light"><div><span class="info-label">Helpdesk Support</span><span class="info-val">Digital Inquiries via Form</span></div></li><li class="contact-info-item-light"><div><span class="info-label">Service Area</span><span class="info-val">United States (National)</span></div></li><li class="contact-info-item-light"><div><span class="info-label">Support Hours</span><span class="info-val">Mon - Fri, 8:00 AM - 6:00 PM (EST)</span></div></li></ul></div></div>',
		),
		'privacy-policy'    => array(
			'title'     => 'Privacy Policy (Draft)',
			'subtitle'  => 'How Fraud Tenant collects, protects, tokenizes, and handles user and tenant information.',
			'content'   => '<div class="legal-notice-box"><strong>Regulatory Draft Notice:</strong> This privacy policy is a development draft prepared for the Fraud Tenant platform. Production deployment requires review and approval by qualified US legal counsel.</div><h2>1. Information We Collect</h2><p>Fraud Tenant collects personal profile information (name, business name, address, verified email address, and phone number) necessary to create and authenticate user accounts. For tenant matching, Social Security Numbers are processed server-side into keyed HMAC tokens and never stored in plain text.</p><h2>2. Use of Information</h2><p>Information is used exclusively to facilitate permissible tenant screening, record factual tenancy reports, and authenticate legitimate users. We do not sell personal data to advertisers or third-party data brokers.</p><h2>3. Data Minimization & Retention</h2><p>We apply data minimization across all tables. Audit trails and payment webhook events retain only processing identifiers and status codes, discarding extraneous personal telemetry.</p><h2>4. User Rights & Corrections</h2><p>Users may request access to their profile records, update outdated business information, and file disputes regarding any reported tenancy incidents.</p>',
		),
		'terms-of-service'  => array(
			'title'     => 'Terms of Service (Draft)',
			'subtitle'  => 'Terms and conditions governing the use of the Fraud Tenant platform.',
			'content'   => '<div class="legal-notice-box"><strong>Regulatory Draft Notice:</strong> These terms of service are a development draft prepared for the Fraud Tenant platform. Production deployment requires review and approval by qualified US legal counsel.</div><h2>1. Permissible Purpose Requirement</h2><p>Property owners utilizing Fraud Tenant agree to initiate screening inquiries strictly for legitimate rental housing transactions where applicant consent has been obtained. Unauthorized searches are strictly prohibited.</p><h2>2. Factual Reporting Standards</h2><p>Users submitting incident reports represent and warrant that all submitted details (dates, financial balances, damage descriptions) are truthful, objective, and backed by verifiable evidence. Intentional submission of false claims will result in immediate account termination.</p><h2>3. Disclaimers & Dispute Resolution</h2><p>Fraud Tenant provides screening coordination and record management tools. Incident records reflect owner allegations pending review. We do not guarantee tenant performance or rental outcomes.</p>',
		),
		'tenant-rights'     => array(
			'title'     => 'Tenant Rights & Screening Information',
			'subtitle'  => 'Educational guidance on tenant rights, FCRA principles, and dispute mechanisms.',
			'content'   => '<div class="legal-notice-box"><strong>Educational Notice:</strong> This guide provides general educational information regarding rental records and due process. It does not constitute formal legal advice. Compliance with the Fair Credit Reporting Act (15 U.S.C. § 1681 et seq.) requires permissible purpose verification and qualified legal counsel review.</div><h2>Understanding Your Rental Records</h2><p>Under US fair housing principles and credit reporting regulations, renters are protected against inaccurate, unauthorized, or unsubstantiated negative reports.</p><h3>Core Tenant Protections on Fraud Tenant</h3><ol><li><strong>Notice of Screening:</strong> Property owners may only conduct screening inquiries when they have a permissible purpose, such as an active rental application with applicant consent.</li><li><strong>Right to Review:</strong> Verified tenants can log into their account to review any disclosable incident reports associated with their profile.</li><li><strong>Right to Dispute:</strong> If a report contains errors, disputed balances, or inaccuracies, tenants can submit a formal dispute with supporting evidence directly to our review team.</li><li><strong>Mandatory Correction:</strong> Reports found to be unsubstantiated or inaccurate are removed or amended, and complete correction history is documented in the audit log.</li></ol><div class="legal-callout-box"><h4>Legal Disclaimer</h4><p>Fraud Tenant is an independent rental reporting platform. Nothing on this website constitutes legal counsel or an official guarantee of outcome. Consult a qualified attorney for specific legal guidance on tenant-landlord regulations in your jurisdiction.</p></div>',
		),
		'login'             => array(
			'title'     => 'Sign In',
			'subtitle'  => 'Access your Fraud Tenant account.',
			'content'   => '',
		),
		'register'          => array(
			'title'     => 'Create Your Account',
			'subtitle'  => 'Join as a Property Owner or Tenant.',
			'content'   => '',
		),
		'verify-otp'        => array(
			'title'     => 'Verify Your Email',
			'subtitle'  => 'Enter the 6-digit verification code sent to your email.',
			'content'   => '',
		),
		'forgot-password'   => array(
			'title'     => 'Reset Password',
			'subtitle'  => 'Request a password reset link.',
			'content'   => '',
		),
		'reset-password'    => array(
			'title'     => 'Set New Password',
			'subtitle'  => 'Enter and confirm your new password.',
			'content'   => '',
		),
		'account-status'    => array(
			'title'     => 'Account Status',
			'subtitle'  => 'Check your application and membership review status.',
			'content'   => '',
		),
	);

	foreach ( $pages_to_create as $slug => $data ) {
		$existing = get_page_by_path( $slug );
		if ( ! $existing ) {
			$page_id = wp_insert_post(
				array(
					'post_title'   => $data['title'],
					'post_name'    => $slug,
					'post_content' => $data['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);

			if ( ! empty( $data['subtitle'] ) && ! is_wp_error( $page_id ) ) {
				update_post_meta( $page_id, '_ft_subtitle', $data['subtitle'] );
			}

			if ( ! empty( $data['is_front'] ) && ! is_wp_error( $page_id ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $page_id );
			}
		}
	}
}
add_action( 'after_switch_theme', 'fraud_tenant_ensure_public_pages' );

/**
 * Shortcode to render contact form inside standard content pages.
 */
function fraud_tenant_contact_form_shortcode(): string {
	ob_start();
	get_template_part( 'template-parts/contact-form' );
	return ob_get_clean();
}
add_shortcode( 'ft_contact_form', 'fraud_tenant_contact_form_shortcode' );
