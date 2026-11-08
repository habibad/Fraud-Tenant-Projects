<?php
/**
 * Audit Logs — append-only application audit trail (filters, pagination). Payloads are already
 * data-minimized at write time (no SSNs, names, narratives); only their keys are shown here.
 *
 * @package FraudTenant\Admin
 */

defined( 'ABSPATH' ) || exit;

use FraudTenant\Admin\AdminConsole as C;

global $wpdb;
$t      = $wpdb->prefix . 'ft_audit_log';
$action = sanitize_key( C::get( 'act' ) );
$otype  = sanitize_key( C::get( 'object_type' ) );
$actor  = absint( C::get( 'actor' ) );
$page   = max( 1, absint( C::get( 'pg', '1' ) ) );
$where  = array( '1=1' );
$params = array();
if ( $action ) {
	$where[]  = 'a.action LIKE %s';
	$params[] = '%' . $wpdb->esc_like( $action ) . '%';
}
if ( $otype ) {
	$where[]  = 'a.object_type = %s';
	$params[] = $otype;
}
if ( $actor ) {
	$where[]  = 'a.user_id = %d';
	$params[] = $actor;
}
$w     = implode( ' AND ', $where );
$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} a WHERE {$w}", $params ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$t} a WHERE {$w}" ) );
$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT a.id, a.user_id, a.action, a.object_type, a.object_id, a.ip_address, a.details_json, a.created_at, u.display_name FROM {$t} a LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id WHERE {$w} ORDER BY a.id DESC LIMIT %d OFFSET %d", array_merge( $params, array( 50, ( $page - 1 ) * 50 ) ) ) ) ?: array();
$types = $wpdb->get_col( "SELECT DISTINCT object_type FROM {$t} WHERE object_type IS NOT NULL ORDER BY object_type" );

C::head( __( 'Audit Logs', 'fraud-tenant-core' ), __( 'Append-only application history for accountability. Sensitive values are never written to it.', 'fraud-tenant-core' ) );
?>
<form method="get" class="ft-filters" action="<?php echo esc_url( C::in_app() ? home_url( '/admin-dashboard/' ) : admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="<?php echo C::in_app() ? 'section' : 'page'; ?>" value="<?php echo esc_attr( C::in_app() ? 'audit' : 'fraud-tenant-audit' ); ?>">
	<input class="ft-input" name="act" value="<?php echo esc_attr( $action ); ?>" placeholder="<?php esc_attr_e( 'Action contains…', 'fraud-tenant-core' ); ?>" aria-label="<?php esc_attr_e( 'Action', 'fraud-tenant-core' ); ?>">
	<select class="ft-select" name="object_type" aria-label="<?php esc_attr_e( 'Object type', 'fraud-tenant-core' ); ?>"><option value=""><?php esc_html_e( 'All object types', 'fraud-tenant-core' ); ?></option><?php foreach ( $types as $ty ) : ?><option value="<?php echo esc_attr( $ty ); ?>" <?php selected( $otype, $ty ); ?>><?php echo esc_html( $ty ); ?></option><?php endforeach; ?></select>
	<input class="ft-input" name="actor" type="number" min="0" value="<?php echo $actor ? (int) $actor : ''; ?>" placeholder="<?php esc_attr_e( 'Actor user ID', 'fraud-tenant-core' ); ?>" aria-label="<?php esc_attr_e( 'Actor user ID', 'fraud-tenant-core' ); ?>">
	<button class="ft-btn ft-btn-secondary" type="submit"><?php esc_html_e( 'Filter', 'fraud-tenant-core' ); ?></button>
</form>
<div class="ft-card"><div class="ft-table-wrap"><table class="ft-table">
	<thead><tr><th>#</th><th><?php esc_html_e( 'When', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Actor', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Action', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Object', 'fraud-tenant-core' ); ?></th><th><?php esc_html_e( 'Details (keys)', 'fraud-tenant-core' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $rows as $r ) : $keys = array_keys( (array) json_decode( (string) $r->details_json, true ) ); ?>
		<tr>
			<td><?php echo (int) $r->id; ?></td>
			<td><?php echo C::date( $r->created_at, true ); // phpcs:ignore ?></td>
			<td><?php echo $r->user_id ? esc_html( (string) ( $r->display_name ?: '#' . $r->user_id ) ) : '<span class="ft-muted">' . esc_html__( 'System', 'fraud-tenant-core' ) . '</span>'; ?></td>
			<td class="ft-mono"><?php echo esc_html( (string) $r->action ); ?></td>
			<td><?php echo esc_html( trim( (string) $r->object_type . ( $r->object_id ? ' #' . (int) $r->object_id : '' ) ) ); ?></td>
			<td class="ft-muted"><?php echo esc_html( implode( ', ', array_slice( $keys, 0, 6 ) ) ); ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $rows ) : ?><tr><td colspan="6"><?php C::empty_state( __( 'No audit entries match.', 'fraud-tenant-core' ) ); ?></td></tr><?php endif; ?>
	</tbody>
</table></div></div>
<?php C::pagination( 'audit', $page, $total, 50, array( 'act' => $action, 'object_type' => $otype, 'actor' => $actor ?: '' ) ); ?>
