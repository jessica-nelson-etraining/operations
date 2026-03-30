<?php
/**
 * page.php — Core Blocks page template
 *
 * Used when a page has Core Block configuration but no Course Landing config.
 * Uses the active theme's header and footer so the page looks native.
 *
 * NOTE FOR DEVELOPERS:
 * If your theme uses named templates (e.g. get_header('v1')),
 * update those calls below before deploying to production.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get the post ID reliably — get_queried_object_id() works outside the loop.
$cb_post_id = get_queried_object_id();

get_header();
?>

<main id="cb-page-content">
    <?php
    // Run the WordPress loop so template tags like the_title() work correctly,
    // then render our blocks using the queried post ID.
    if ( have_posts() ) :
        while ( have_posts() ) : the_post();
            cb_render_blocks( $cb_post_id );
        endwhile;
    else :
        // Fallback: render blocks directly without the loop
        cb_render_blocks( $cb_post_id );
    endif;
    ?>
</main>

<?php
get_footer();
?>
