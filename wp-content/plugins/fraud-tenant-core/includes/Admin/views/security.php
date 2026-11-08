<?php
/**
 * Security & Compliance Gate Status — technical gates (verified in code, fail closed) and external
 * release gates (documented; recording one is an audited sign-off, never a compliance claim).
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\ComplianceService;

$s  = ComplianceService::status();
$od = $s['owner_disclosure'];
$ok = static fn( bool $v, string $yes, string $no ): string => C::badge( $v ? 'active' : 'failed', $v ? $yes : $no );

C::head( __( 'Security & Compliance', 'fraud-tenant-core' ), __( 'Identity features run only when every technical gate below is satisfied. Missing configuration fails closed.', 'fraud-tenant-core' ) );
?>
<div class="ft-grid ft-grid-2">
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'Technical gates', 'fraud-tenant-core' ); ?></h2></div>
		<dl class="ft-dl">
			<dt>BLK-04 · <?php esc_html_e( 'SSN match key', 'fraud-tenant-core' ); ?></dt><dd><?php echo $ok( $s['match_key_ready'], __( 'FT_MATCH_SECRET_KEY configured', 'fraud-tenant-core' ), __( 'Missing — matching disabled', 'fraud-tenant-core' ) ); // phpcs:ignore ?></dd>
			<dt>BLK-08 · <?php esc_html_e( 'Field encryption', 'fraud-tenant-core' ); ?></dt><dd><?php echo $ok( $s['encryption']['available'], __( 'AES-256-GCM self-test passed', 'fraud-tenant-core' ), __( 'FT_DATA_ENCRYPTION_KEY missing or invalid', 'fraud-tenant-core' ) ); // phpcs:ignore ?><?php if ( $s['encryption']['current_kid'] ) : ?><br><span class="ft-muted"><?php echo esc_html( sprintf( /* translators: 1: key id, 2: count */ __( 'Key id %1$s · %2$d previous key(s) accepted for rotation', 'fraud-tenant-core' ), $s['encryption']['current_kid'], $s['encryption']['previous_keys'] ) ); ?></span><?php endif; ?></dd>
			<dt><?php esc_html_e( 'Identity data', 'fraud-tenant-core' ); ?></dt><dd><?php echo $ok( $s['identity_ready'], __( 'Capture, matching, claims and review enabled', 'fraud-tenant-core' ), __( 'Locked', 'fraud-tenant-core' ) ); // phpcs:ignore ?></dd>
			<dt><?php esc_html_e( 'HTTPS', 'fraud-tenant-core' ); ?></dt><dd><?php echo $ok( (bool) $s['https'], __( 'Site served over HTTPS', 'fraud-tenant-core' ), __( 'Not HTTPS — required in production (Stripe Live is blocked without it)', 'fraud-tenant-core' ) ); // phpcs:ignore ?></dd>
			<dt><?php esc_html_e( 'Environment', 'fraud-tenant-core' ); ?></dt><dd><?php echo esc_html( (string) $s['environment'] ); ?></dd>
		</dl>
		<?php C::form( 'reencrypt' ); ?>
			<button class="ft-btn ft-btn-secondary" type="submit" <?php disabled( ! $s['encryption']['available'] ); ?>><?php esc_html_e( 'Re-encrypt protected data with the current key', 'fraud-tenant-core' ); ?></button>
			<p class="ft-help"><?php esc_html_e( 'Run after rotating FT_DATA_ENCRYPTION_KEY (keep the old key in FT_DATA_ENCRYPTION_PREVIOUS_KEYS until this completes). Also encrypts any legacy plaintext identity names.', 'fraud-tenant-core' ); ?></p>
		</form>
	</section>
	<section class="ft-card">
		<div class="ft-card-head"><h2><?php esc_html_e( 'External release gates', 'fraud-tenant-core' ); ?></h2></div>
		<dl class="ft-dl">
			<dt>BLK-03 · <?php esc_html_e( 'Legal review before production screening disclosure', 'fraud-tenant-core' ); ?></dt>
			<dd>
				<?php if ( ! $s['legal_release_required'] ) : ?>
					<?php echo C::badge( 'info', __( 'Not required in non-production', 'fraud-tenant-core' ) ); // phpcs:ignore ?>
				<?php else : ?>
					<?php echo C::badge( ! empty( $od['enabled'] ) ? 'active' : 'pending', ! empty( $od['enabled'] ) ? __( 'Sign-off recorded', 'fraud-tenant-core' ) : __( 'Required — screening disabled until recorded', 'fraud-tenant-core' ) ); // phpcs:ignore ?>
				<?php endif; ?>
				<?php if ( ! empty( $od['by'] ) ) : $who = get_userdata( (int) $od['by'] ); ?><br><span class="ft-muted"><?php echo esc_html( ( $who ? $who->display_name : '#' . (int) $od['by'] ) . ' · ' . (string) $od['at'] ); ?></span><?php endif; ?>
			</dd>
			<dt><?php esc_html_e( 'Backups & key custody', 'fraud-tenant-core' ); ?></dt><dd><?php echo C::badge( 'pending', __( 'Infrastructure duty — verify', 'fraud-tenant-core' ) ); // phpcs:ignore ?><br><span class="ft-muted"><?php esc_html_e( 'Backups must be encrypted and must not contain the match/encryption keys (store keys in a secret manager).', 'fraud-tenant-core' ); ?></span></dd>
		</dl>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<?php C::form( 'legal_release', array( 'enable' => ! empty( $od['enabled'] ) ? '0' : '1' ) ); ?>
				<?php if ( empty( $od['enabled'] ) ) : ?>
					<label class="ft-label" for="ft-legal"><?php esc_html_e( 'Reference of qualified counsel\'s written approval', 'fraud-tenant-core' ); ?></label>
					<textarea class="ft-textarea" id="ft-legal" name="statement" minlength="20" required></textarea>
					<button class="ft-btn ft-btn-primary" type="submit" <?php disabled( ! $s['identity_ready'] ); ?>><?php esc_html_e( 'Record legal release sign-off', 'fraud-tenant-core' ); ?></button>
				<?php else : ?>
					<button class="ft-btn ft-btn-danger" type="submit"><?php esc_html_e( 'Withdraw legal release sign-off', 'fraud-tenant-core' ); ?></button>
				<?php endif; ?>
				<p class="ft-help"><?php esc_html_e( 'Recording this sign-off documents who accepted responsibility and when. It is not a statement that the platform complies with FCRA, FHA or any other law.', 'fraud-tenant-core' ); ?></p>
			</form>
		<?php endif; ?>
	</section>
</div>
