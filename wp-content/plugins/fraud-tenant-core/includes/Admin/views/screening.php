<?php
/**
 * Screening Activity — every owner search (purpose, property, result level). No applicant names
 * or SSN fragments are shown here.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;
use FraudTenant\Services\ScreeningService;
use FraudTenant\Services\TenantScreeningService;

$page = max( 1, absint( C::get( 'pg', '1' ) ) );
$data = TenantScreeningService::get_admin_activity( $page, 25 );
C::head( __( 'Screening Activity', 'fraud-tenant-core' ), __( 'Authorized owner searches with their stated permissible purpose. Applicant identity details are not shown.', 'fraud-tenant-core' ) );
?>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th><?php esc_html_e( 'Search', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Owner', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Purpose', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Property', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Result', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Plan level', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'When', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $data['items'] as $s ) : ?>
		<tr>
			<td>SCR-<?php echo esc_html( sprintf( '%06d', (int) $s->id ) ); ?></td>
			<td><a href="<?php echo esc_url( C::url( 'users', array( 'user_id' => (int) $s->owner_id ) ) ); ?>"><?php echo esc_html( (string) $s->owner_name ); ?></a></td>
			<td><?php echo esc_html( ScreeningService::ALLOWED_PURPOSES[ $s->purpose_reason ] ?? $s->purpose_reason ); ?></td>
			<td><a href="<?php echo esc_url( C::url( 'properties', array( 'property_id' => (int) $s->property_id ) ) ); ?>">#<?php echo (int) $s->property_id; ?></a></td>
			<td><?php echo $s->match_found ? C::badge( 'visible', sprintf( /* translators: %d: count */ _n( '%d record', '%d records', (int) $s->records_count, 'fraud-tenant-core' ), (int) $s->records_count ) ) : C::badge( 'no', __( 'No records', 'fraud-tenant-core' ) ); // phpcs:ignore ?></td>
			<td><?php echo C::badge( 'full' === $s->disclosure_level ? 'active' : 'no', 'full' === $s->disclosure_level ? __( 'Standard (full)', 'fraud-tenant-core' ) : ( 'basic' === $s->disclosure_level ? __( 'Free (limited)', 'fraud-tenant-core' ) : __( 'Legacy request', 'fraud-tenant-core' ) ) ); // phpcs:ignore ?></td>
			<td><?php echo C::date( $s->search_date, true ); // phpcs:ignore ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $data['items'] ) : ?><tr><td colspan="7"><?php C::empty_state( __( 'No screening searches yet.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'screening', $page, (int) $data['total'], 25 ); ?>
