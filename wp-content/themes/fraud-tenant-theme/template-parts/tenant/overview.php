<?php
/**
 * Tenant — Overview.
 *
 * @package FraudTenantTheme
 */

defined( 'ABSPATH' ) || exit;

$d     = $args['data'];
$base  = $args['base'];
$state = $d['claim']['state'];
$first = $args['user']->first_name ?: $args['user']->display_name;
?>
<section class="ft-hero">
	<div>
		<?php /* translators: %s: first name */ ?>
		<h1><?php echo esc_html( sprintf( __( 'Hello, %s', 'fraud-tenant-theme' ), $first ) ); ?></h1>
		<p><?php esc_html_e( 'See the reviewed rental records associated with your identity and dispute anything that is inaccurate.', 'fraud-tenant-theme' ); ?></p>
	</div>
	<?php if ( 'verified' !== $state ) : ?><a class="ft-btn ft-btn-primary" href="<?php echo esc_url( ft_app_url( $base, 'identity' ) ); ?>"><?php esc_html_e( 'Verify your identity', 'fraud-tenant-theme' ); ?></a><?php endif; ?>
</section>

<?php if ( 'verified' !== $state ) : ?>
	<div class="ft-alert is-info">
		<?php
		$msg = array(
			'none'        => __( 'To see records associated with you, verify your identity. Records are only ever shown after our team confirms the identity belongs to you.', 'fraud-tenant-theme' ),
			'pending'     => __( 'Your identity verification request is under review. We will notify you when it is decided.', 'fraud-tenant-theme' ),
			'rejected'    => __( 'Your last identity verification request was not approved. You can submit a new request.', 'fraud-tenant-theme' ),
			'unavailable' => __( 'Identity verification is not available on this platform yet.', 'fraud-tenant-theme' ),
		);
		echo esc_html( $msg[ $state ] ?? $msg['none'] );
		?>
	</div>
<?php endif; ?>

<div class="ft-grid ft-grid-3">
	<a class="ft-kpi" href="<?php echo esc_url( ft_app_url( $base, 'identity' ) ); ?>">
		<span class="ft-kpi-label"><?php esc_html_e( 'Identity', 'fraud-tenant-theme' ); ?></span>
		<strong class="ft-kpi-value" style="font-size:20px"><?php echo 'verified' === $state ? esc_html__( 'Verified', 'fraud-tenant-theme' ) : ( 'pending' === $state ? esc_html__( 'Under review', 'fraud-tenant-theme' ) : esc_html__( 'Not verified', 'fraud-tenant-theme' ) ); ?></strong>
	</a>
	<a class="ft-kpi" href="<?php echo esc_url( ft_app_url( $base, 'records' ) ); ?>">
		<span class="ft-kpi-label"><?php esc_html_e( 'Rental records', 'fraud-tenant-theme' ); ?></span>
		<strong class="ft-kpi-value"><?php echo (int) count( $d['records'] ); ?></strong>
		<span class="ft-kpi-sub"><?php esc_html_e( 'Reviewed records visible to you', 'fraud-tenant-theme' ); ?></span>
	</a>
	<a class="ft-kpi" href="<?php echo esc_url( ft_app_url( $base, 'disputes' ) ); ?>">
		<span class="ft-kpi-label"><?php esc_html_e( 'Open disputes', 'fraud-tenant-theme' ); ?></span>
		<strong class="ft-kpi-value"><?php echo (int) count( $d['active_disputes'] ); ?></strong>
		<?php /* translators: %d: count */ ?>
		<span class="ft-kpi-sub"><?php echo esc_html( sprintf( __( '%d decided', 'fraud-tenant-theme' ), count( $d['dispute_history'] ) ) ); ?></span>
	</a>
</div>

<section class="ft-card">
	<div class="ft-card-head"><h2><?php esc_html_e( 'Your rights', 'fraud-tenant-theme' ); ?></h2></div>
	<ul class="ft-list">
		<li><span><?php esc_html_e( 'Reports are submitted by property owners and are only shown after our team reviews them.', 'fraud-tenant-theme' ); ?></span></li>
		<li><span><?php esc_html_e( 'You can dispute any record that is inaccurate, wrongly attributed to you, or already resolved.', 'fraud-tenant-theme' ); ?></span></li>
		<li><span><?php esc_html_e( 'Owners who screen you see whether you disputed a record and the outcome. Your dispute statements and evidence stay private.', 'fraud-tenant-theme' ); ?></span></li>
		<li><span><?php esc_html_e( 'Fraud Tenant never scores, ranks or blacklists anyone. Owners make their own decisions.', 'fraud-tenant-theme' ); ?></span></li>
	</ul>
	<a href="<?php echo esc_url( home_url( '/tenant-rights/' ) ); ?>"><?php esc_html_e( 'Read more about your rights', 'fraud-tenant-theme' ); ?> →</a>
</section>
