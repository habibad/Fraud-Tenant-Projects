<?php
/**
 * Tenant — Identity Verification (secure claim; reviewed by an administrator with a document).
 * The response is identical whether or not a record exists, so nothing is revealed before review.
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Services\IdentityService;

$d     = $args['data'];
$uid   = $args['tenant_id'];
$claim = $d['claim'];
$state = $claim['state'];
?>
<header class="ft-page-head"><div><h1><?php esc_html_e( 'Identity verification', 'fraud-tenant-theme' ); ?></h1><p><?php esc_html_e( 'Link your account to your identity record so you can see and dispute reviewed rental records about you.', 'fraud-tenant-theme' ); ?></p></div><?php echo ft_badge( 'verified' === $state ? 'verified' : ( 'pending' === $state ? 'pending' : 'neutral' ), 'verified' === $state ? __( 'Verified', 'fraud-tenant-theme' ) : ( 'pending' === $state ? __( 'Under review', 'fraud-tenant-theme' ) : __( 'Not verified', 'fraud-tenant-theme' ) ) ); // phpcs:ignore ?></header>

<?php if ( 'unavailable' === $state ) : ?>
	<div class="ft-alert is-info"><?php esc_html_e( 'Identity verification is not available on this platform yet.', 'fraud-tenant-theme' ); ?></div>
<?php elseif ( 'verified' === $state ) : $n = IdentityService::name( $claim['identity'] ); ?>
	<section class="ft-card">
		<div class="ft-alert is-success"><?php esc_html_e( 'Your identity is verified. Reviewed records associated with you appear under My Rental Records.', 'fraud-tenant-theme' ); ?></div>
		<dl class="ft-dl">
			<dt><?php esc_html_e( 'Legal name', 'fraud-tenant-theme' ); ?></dt><dd><?php echo esc_html( trim( $n['first'] . ' ' . $n['last'] ) ); ?></dd>
			<dt><?php esc_html_e( 'SSN', 'fraud-tenant-theme' ); ?></dt><dd>••• •• <?php echo esc_html( (string) $claim['identity']->ssn_last_four ); ?></dd>
			<dt><?php esc_html_e( 'Verified on', 'fraud-tenant-theme' ); ?></dt><dd><?php echo ft_date( $claim['identity']->claimed_at ); // phpcs:ignore ?></dd>
		</dl>
	</section>
<?php elseif ( 'pending' === $state ) : ?>
	<section class="ft-card">
		<div class="ft-alert is-info"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Request submitted on %s. Our team reviews requests in the order received and will notify you of the decision.', 'fraud-tenant-theme' ), wp_strip_all_tags( ft_date( $claim['claim']->created_at ) ) ) ); ?></div>
		<p class="ft-muted"><?php esc_html_e( 'SSN on the request ends in', 'fraud-tenant-theme' ); ?> <?php echo esc_html( (string) $claim['claim']->ssn_last4 ); ?>.</p>
	</section>
<?php else : ?>
	<?php if ( 'rejected' === $state && ! empty( $claim['claim']->decision_note ) ) : ?>
		<div class="ft-alert is-warning"><strong><?php esc_html_e( 'Your previous request was not approved:', 'fraud-tenant-theme' ); ?></strong> <?php echo esc_html( (string) $claim['claim']->decision_note ); ?></div>
	<?php endif; ?>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Verify your identity', 'fraud-tenant-theme' ); ?></h2></div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ft-form" autocomplete="off">
				<input type="hidden" name="action" value="ft_identity_claim">
				<?php wp_nonce_field( 'ft_identity_claim_' . $uid ); ?>
				<div class="ft-row">
					<div><label class="ft-label" for="ft-cf"><?php esc_html_e( 'Legal first name', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-cf" name="first_name" required maxlength="100" value="<?php echo esc_attr( $args['user']->first_name ); ?>"></div>
					<div><label class="ft-label" for="ft-cl"><?php esc_html_e( 'Legal last name', 'fraud-tenant-theme' ); ?></label><input class="ft-input" id="ft-cl" name="last_name" required maxlength="100" value="<?php echo esc_attr( $args['user']->last_name ); ?>"></div>
				</div>
				<label class="ft-label" for="ftClaimSsn"><?php esc_html_e( 'Your Social Security Number', 'fraud-tenant-theme' ); ?></label>
				<div class="ft-filters" style="margin:0"><input class="ft-input" type="password" id="ftClaimSsn" name="ssn" data-ft-ssn required inputmode="numeric" pattern="\d{3}-?\d{2}-?\d{4}" placeholder="123-45-6789"><button type="button" class="ft-btn ft-btn-ghost ft-btn-sm" data-ft-ssn-toggle="ftClaimSsn" data-show="<?php esc_attr_e( 'Show', 'fraud-tenant-theme' ); ?>" data-hide="<?php esc_attr_e( 'Hide', 'fraud-tenant-theme' ); ?>" aria-pressed="false"><?php esc_html_e( 'Show', 'fraud-tenant-theme' ); ?></button></div>
				<label class="ft-label" for="ft-cd"><?php esc_html_e( 'Proof of identity (government ID or a lease in your name — PDF, JPG or PNG, max 10 MB)', 'fraud-tenant-theme' ); ?></label>
				<input class="ft-input" type="file" id="ft-cd" name="claim_document" accept=".pdf,.png,.jpg,.jpeg" required>
				<label class="ft-check"><input type="checkbox" name="claim_attestation" value="1" required> <span><?php esc_html_e( 'I confirm this is my own legal name and SSN. Claiming someone else\'s identity is prohibited.', 'fraud-tenant-theme' ); ?></span></label>
				<button class="ft-btn ft-btn-primary" type="submit"><?php esc_html_e( 'Submit for verification', 'fraud-tenant-theme' ); ?></button>
			</form>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'How your data is protected', 'fraud-tenant-theme' ); ?></h2></div>
			<ul class="ft-list">
				<li><span><?php esc_html_e( 'Your SSN is turned into a keyed token on our server. The full number is never stored, logged or shown.', 'fraud-tenant-theme' ); ?></span></li>
				<li><span><?php esc_html_e( 'Your document is stored privately and is only visible to the verification team.', 'fraud-tenant-theme' ); ?></span></li>
				<li><span><?php esc_html_e( 'A person on our team reviews every request. Records are only shown to you after approval.', 'fraud-tenant-theme' ); ?></span></li>
			</ul>
		</section>
	</div>
<?php endif; ?>
