<?php
/**
 * Tenant Identity Management — central identity records (masked in lists, full name on the
 * audited detail view), claimed account, linked reports, claim history, claim revocation.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\AuditLogger;
use FraudTenant\Services\ComplianceService;
use FraudTenant\Services\IdentityService;
use FraudTenant\Services\IncidentService;

if ( ! ComplianceService::identity_ready() ) {
	C::head( __( 'Tenant Identities', 'fraud-tenant-core' ) );
	echo '<div class="ft-alert is-warning">' . esc_html__( 'Identity data is locked: configure FT_MATCH_SECRET_KEY and FT_DATA_ENCRYPTION_KEY (see Security & Compliance).', 'fraud-tenant-core' ) . '</div>';
	return;
}

global $wpdb;
$iid = absint( C::get( 'identity_id' ) );

if ( $iid ) {
	$idn = IdentityService::get( $iid );
	if ( ! $idn ) {
		C::empty_state( __( 'Identity not found.', 'fraud-tenant-core' ) );
		return;
	}
	AuditLogger::log( 'identity.viewed', 'tenant_identity', $iid, array() );
	$name    = IdentityService::name( $idn );
	$account = $idn->user_id ? get_userdata( (int) $idn->user_id ) : null;
	$reports = IdentityService::get_reports( $iid );
	$claims  = $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id, match_result, status, created_at, decided_at FROM {$wpdb->prefix}ft_identity_claims WHERE identity_id = %d ORDER BY id DESC", $iid ) ) ?: array();
	C::head( sprintf( /* translators: %d: id */ __( 'Identity #%d', 'fraud-tenant-core' ), $iid ), __( 'Central tenant identity record', 'fraud-tenant-core' ) );
	echo '<p><a href="' . esc_url( C::url( 'identities' ) ) . '">← ' . esc_html__( 'All identities', 'fraud-tenant-core' ) . '</a></p>';
	?>
	<div class="ft-grid ft-grid-2">
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Identity', 'fraud-tenant-core' ); ?></h2><?php echo IdentityService::is_placeholder( $idn ) ? C::badge( 'no', __( 'Legacy placeholder', 'fraud-tenant-core' ) ) : C::badge( 'active', __( 'SSN token', 'fraud-tenant-core' ) ); // phpcs:ignore ?></div>
			<dl class="ft-dl">
				<dt><?php esc_html_e( 'Legal name', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( trim( $name['first'] . ' ' . $name['last'] ) ?: '—' ); ?></dd>
				<dt><?php esc_html_e( 'SSN', 'fraud-tenant-core' ); ?></dt><dd><?php echo IdentityService::is_placeholder( $idn ) ? esc_html__( 'No SSN on record (cannot be matched)', 'fraud-tenant-core' ) : '••• •• ' . esc_html( $idn->ssn_last_four ); ?></dd>
				<dt><?php esc_html_e( 'Claimed by', 'fraud-tenant-core' ); ?></dt><dd><?php echo $account ? '<a href="' . esc_url( C::url( 'users', array( 'user_id' => (int) $account->ID ) ) ) . '">' . esc_html( $account->display_name ) . '</a> · ' . C::date( $idn->claimed_at ) : esc_html__( 'Unclaimed', 'fraud-tenant-core' ); // phpcs:ignore ?></dd>
				<dt><?php esc_html_e( 'Created', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::date( $idn->created_at ); // phpcs:ignore ?></dd>
			</dl>
			<?php if ( $account ) : ?>
				<?php C::form( 'claim_revoke', array( 'identity_id' => $iid ) ); ?>
					<label class="ft-label" for="ft-rv"><?php esc_html_e( 'Revoke this account\'s claim (reason)', 'fraud-tenant-core' ); ?></label>
					<textarea class="ft-textarea" id="ft-rv" name="reason" required minlength="5"></textarea>
					<button class="ft-btn ft-btn-danger" type="submit"><?php esc_html_e( 'Revoke claim', 'fraud-tenant-core' ); ?></button>
					<p class="ft-help"><?php esc_html_e( 'The tenant immediately loses access to every record linked to this identity.', 'fraud-tenant-core' ); ?></p>
				</form>
			<?php endif; ?>
		</section>
		<section class="ft-card">
			<div class="ft-card-head"><h2><?php esc_html_e( 'Claim history', 'fraud-tenant-core' ); ?></h2></div>
			<?php if ( ! $claims ) : C::empty_state( __( 'No claims for this identity.', 'fraud-tenant-core' ) ); else : ?>
				<ul class="ft-list"><?php foreach ( $claims as $c ) : ?>
					<li><a href="<?php echo esc_url( C::url( 'claims', array( 'claim_id' => (int) $c->id ) ) ); ?>">IDC-<?php echo esc_html( sprintf( '%06d', (int) $c->id ) ); ?></a> <?php echo C::badge( (string) $c->status ); // phpcs:ignore ?> <?php echo C::badge( (string) $c->match_result ); // phpcs:ignore ?> <span class="ft-muted"><?php echo C::date( $c->created_at ); // phpcs:ignore ?></span></li>
				<?php endforeach; ?></ul>
			<?php endif; ?>
		</section>
	</div>
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Linked incident reports', 'fraud-tenant-core' ); ?></h2></div>
		<div class="ft-table-wrap"><table class="ft-table">
			<thead><tr><th><?php esc_html_e( 'Report', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Category', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Status', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Link', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Tenant', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Screening', 'fraud-tenant-core' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $reports as $rp ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( C::url( 'incidents', array( 'report_id' => (int) $rp->id ) ) ); ?>">INC-<?php echo esc_html( sprintf( '%06d', (int) $rp->id ) ); ?></a></td>
					<td><?php echo esc_html( IncidentService::ALLOWED_CATEGORIES[ $rp->category ] ?? $rp->category ); ?></td>
					<td><?php echo C::badge( (string) $rp->status ); // phpcs:ignore ?></td>
					<td><?php echo C::badge( (string) $rp->identity_match ); // phpcs:ignore ?></td>
					<td><?php echo C::badge( $rp->disclosable ? 'visible' : 'hidden' ); // phpcs:ignore ?></td>
					<td><?php echo C::badge( $rp->screening_disclosure ? 'visible' : 'hidden' ); // phpcs:ignore ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $reports ) : ?><tr><td colspan="6"><?php C::empty_state( __( 'No reports linked.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
			</tbody>
		</table></div>
	</section>
	<?php
	return;
}

$search  = C::get( 'q' );
$claimed = C::get( 'claimed', 'all' );
$page    = max( 1, absint( C::get( 'pg', '1' ) ) );
$list    = IdentityService::list_identities( array( 'search' => $search, 'claimed' => $claimed, 'page' => $page, 'per_page' => 25 ) );

C::head( __( 'Tenant Identities', 'fraud-tenant-core' ), __( 'One central, account-independent record per tenant (keyed SSN token + encrypted legal name). Names are masked in this list.', 'fraud-tenant-core' ) );
C::pills( 'identities', 'claimed', array( 'all' => __( 'All', 'fraud-tenant-core' ), 'yes' => __( 'Claimed by an account', 'fraud-tenant-core' ), 'no' => __( 'Unclaimed', 'fraud-tenant-core' ) ), $claimed, array( 'q' => $search ) );
?>
<form method="get" class="ft-filters" action="<?php echo esc_url( C::in_app() ? home_url( '/admin-dashboard/' ) : admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="<?php echo C::in_app() ? 'section' : 'page'; ?>" value="<?php echo esc_attr( C::in_app() ? 'identities' : 'fraud-tenant-identities' ); ?>">
	<input type="hidden" name="claimed" value="<?php echo esc_attr( $claimed ); ?>">
	<input class="ft-input" type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'SSN last 4, #ID, or account email', 'fraud-tenant-core' ); ?>" aria-label="<?php esc_attr_e( 'Search identities', 'fraud-tenant-core' ); ?>">
	<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Search', 'fraud-tenant-core' ); ?></button>
</form>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Identity', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Name', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'SSN', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Account', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Reports', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Created', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $list['items'] as $i ) : ?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'identities', array( 'identity_id' => (int) $i->id ) ) ); ?>">#<?php echo (int) $i->id; ?></a></td>
			<td><?php echo esc_html( IdentityService::masked_name( $i ) ); ?></td>
			<td><?php echo IdentityService::is_placeholder( $i ) ? C::badge( 'no', __( 'Legacy placeholder', 'fraud-tenant-core' ) ) : '••• ' . esc_html( $i->ssn_last_four ); // phpcs:ignore ?></td>
			<td><?php echo $i->user_id ? esc_html( (string) $i->account_name ) : '<span class="ft-muted">' . esc_html__( 'Unclaimed', 'fraud-tenant-core' ) . '</span>'; ?></td>
			<td><?php echo (int) $i->reports_total; ?> <span class="ft-muted">(<?php echo (int) $i->reports_approved; ?> <?php esc_html_e( 'approved', 'fraud-tenant-core' ); ?>)</span></td>
			<td><?php echo C::date( $i->created_at ); // phpcs:ignore ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $list['items'] ) : ?><tr><td colspan="6"><?php C::empty_state( __( 'No identities found.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'identities', $page, (int) $list['total'], 25, array( 'q' => $search, 'claimed' => $claimed ) ); ?>
