<?php
/**
 * Standard Page Template.
 *
 * @package FraudTenantTheme
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-header' );
	?>

	<div class="container section-spacing" style="padding-top: 40px; padding-bottom: 60px;">
		<div class="page-content-card">
			<?php
			the_content();

			wp_link_pages(
				array(
					'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'fraud-tenant-theme' ),
					'after'  => '</div>',
				)
			);
			?>
		</div>
	</div>

	<?php
endwhile;

get_footer();

