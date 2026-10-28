<?php
/**
 * Main Fallback Template.
 *
 * @package FraudTenantTheme
 */

get_header();
?>

<div class="container section-spacing">
	<div class="page-content-card">
		<?php
		if ( have_posts() ) :
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="entry-content">
						<?php the_excerpt(); ?>
					</div>
				</article>
				<?php
			endwhile;
		else :
			?>
			<h2><?php esc_html_e( 'Nothing Found', 'fraud-tenant-theme' ); ?></h2>
			<p><?php esc_html_e( 'It seems we can’t find what you’re looking for. Please return to the homepage or contact our team.', 'fraud-tenant-theme' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Return Home', 'fraud-tenant-theme' ); ?></a>
			<?php
		endif;
		?>
	</div>
</div>

<?php
get_footer();

