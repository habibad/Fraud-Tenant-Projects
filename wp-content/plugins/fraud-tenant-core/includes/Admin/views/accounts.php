<?php
/**
 * Account & Email Verification Management — unverified, suspended and legacy-rejected accounts.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Utils\Constants;

global $wpdb;
$p      = $wpdb->prefix;
$filter = C::get( 'state', 'email_unverified' );
if ( ! in_array( $filter, array( 'email_unverified', 'suspended', 'rejected' ), true ) ) {
	$filter = 'email_unverified';
}
$counts = array();
foreach ( array( 'email_unverified', 'suspended', 'rejected' ) as $st ) {
	$counts[ $st ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ft_user_profiles WHERE approval_status = %s", $st ) );
}
$rows = $wpdb->get_results( $wpdb->prepare( "SELECT pr.user_id, pr.account_type, pr.approval_status, pr.rejection_reason, pr.created_at, u.display_name, u.user_email FROM {$p}ft_user_profiles pr INNER JOIN {$wpdb->users} u ON u.ID = pr.user_id WHERE pr.approval_status = %s ORDER BY pr.id DESC LIMIT 200", $filter ) ) ?: array();

C::head( __( 'Account & Email Verification', 'fraud-tenant-core' ), __( 'Email verification activates an account. Help users who are stuck, and suspend or reactivate accounts.', 'fraud-tenant-core' ) );
C::pills(
	'accounts',
	'state',
	array(
		/* translators: %d: count */
		'email_unverified' => sprintf( __( 'Awaiting email verification (%d)', 'fraud-tenant-core' ), $counts['email_unverified'] ),
		/* translators: %d: count */
		'suspended'        => sprintf( __( 'Suspended (%d)', 'fraud-tenant-core' ), $counts['suspended'] ),
		/* translators: %d: count */
		'rejected'         => sprintf( __( 'Rejected — legacy (%d)', 'fraud-tenant-core' ), $counts['rejected'] ),
	),
	$filter
);
?>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Account', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Type', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Since', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Actions', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $rows as $r ) : ?>
		<tr>
			<td><a href="<?php echo esc_url( C::url( 'users', array( 'user_id' => (int) $r->user_id ) ) ); ?>"><?php echo esc_html( $r->display_name ); ?></a><br><span class="ft-muted"><?php echo esc_html( $r->user_email ); ?></span></td>
			<td><?php echo esc_html( false !== strpos( (string) $r->account_type, 'tenant' ) ? __( 'Tenant', 'fraud-tenant-core' ) : __( 'Property owner', 'fraud-tenant-core' ) ); ?></td>
			<td><?php echo C::date( $r->created_at ); // phpcs:ignore ?></td>
			<td class="ft-actions">
				<?php if ( Constants::STATUS_EMAIL_UNVERIFIED === $r->approval_status ) : ?>
					<?php C::form( 'user_resend_otp', array( 'user_id' => (int) $r->user_id ), 'style="display:inline"' ); ?><button class="ft-btn ft-btn-secondary ft-btn-sm" type="submit"><?php esc_html_e( 'Resend code', 'fraud-tenant-core' ); ?></button></form>
					<details class="ft-details"><summary class="ft-btn ft-btn-ghost ft-btn-sm"><?php esc_html_e( 'Mark verified…', 'fraud-tenant-core' ); ?></summary>
						<?php C::form( 'user_verify_email', array( 'user_id' => (int) $r->user_id ) ); ?>
							<label class="ft-label" for="ft-ver-<?php echo (int) $r->user_id; ?>"><?php esc_html_e( 'How was the address confirmed?', 'fraud-tenant-core' ); ?></label>
							<textarea class="ft-textarea" id="ft-ver-<?php echo (int) $r->user_id; ?>" name="reason" required minlength="10"></textarea>
							<button class="ft-btn ft-btn-primary ft-btn-sm" type="submit"><?php esc_html_e( 'Mark email verified', 'fraud-tenant-core' ); ?></button>
						</form>
					</details>
				<?php elseif ( Constants::STATUS_SUSPENDED === $r->approval_status ) : ?>
					<span class="ft-muted"><?php echo esc_html( wp_trim_words( (string) $r->rejection_reason, 12 ) ); ?></span>
					<?php C::form( 'user_reactivate', array( 'user_id' => (int) $r->user_id, 'reason' => 'Reactivated from account management.' ), 'style="display:inline"' ); ?><button class="ft-btn ft-btn-primary ft-btn-sm" type="submit"><?php esc_html_e( 'Reactivate', 'fraud-tenant-core' ); ?></button></form>
				<?php else : ?>
					<?php C::form( 'user_reactivate', array( 'user_id' => (int) $r->user_id, 'reason' => 'Legacy rejection lifted: email verification now activates accounts.' ), 'style="display:inline"' ); ?><button class="ft-btn ft-btn-secondary ft-btn-sm" type="submit"><?php esc_html_e( 'Activate', 'fraud-tenant-core' ); ?></button></form>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $rows ) : ?><tr><td colspan="4"><?php C::empty_state( __( 'Nothing in this list.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
