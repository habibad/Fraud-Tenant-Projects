<?php
/**
 * Subpage Header Banner Template Part.
 *
 * @package FraudTenantTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title    = get_the_title();
$subtitle = get_post_meta( get_the_ID(), '_ft_subtitle', true );
?>

<div class="subpage-header">
	<div class="container">
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php if ( ! empty( $subtitle ) ) : ?>
			<p class="lead-text"><?php echo esc_html( $subtitle ); ?></p>
		<?php endif; ?>
	</div>
</div>

