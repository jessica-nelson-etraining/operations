<?php
/**
 * course-landing.php
 *
 * This is the page template used for all course landing pages.
 * It reads the block configuration saved by the Course Landing Blocks editor
 * and renders each block in order.
 *
 * HOW IT WORKS:
 *  1. WordPress calls this file when a visitor views a course landing page.
 *  2. We load the block configuration from the page's saved data.
 *  3. We call clb_render_blocks() which loops through the blocks and renders each one.
 *  4. Blocks with no content are automatically skipped — nothing breaks.
 *
 * NOTE FOR DEVELOPERS:
 *  The get_header() and get_footer() calls use your theme's header and footer.
 *  If your theme uses named templates (e.g. get_header('v1')), update those calls below.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Use the plugin's self-contained header — includes all CSS, fonts, and navigation
// When deploying to staging with your real theme, change this to: get_header('v1');
include CLB_DIR . 'templates/header.php';
?>

<div class="container-fluid product-landing-page">

    <?php
    // Get the current page ID
    $post_id = get_the_ID();

    // Render all configured blocks in order.
    // Each block's render function handles its own auto-hide logic.
    clb_render_blocks( $post_id );
    ?>

</div><!-- /.container-fluid.product-landing-page -->

<?php
// ── Structured data (Schema.org) ──────────────────────────────────────────────
// This section outputs JSON-LD schema for SEO. It runs regardless of which
// blocks are active — schema should always be present on course pages.

$product = clb_get_product( $post_id );

if ( $product ) :
    $page_title         = get_the_title( $post_id );
    $course_name        = $product->get_name();
    $course_description = $product->get_short_description();
    $course_price       = $product->get_price();
    $course_currency    = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
    $enroll_url         = get_permalink( $post_id );
    $provider_name      = 'eTraining Inc.';
    $provider_url       = 'https://etraintoday.com';

    // Optional custom fields (only included in schema if they have a value)
    $target_audience        = get_post_meta( $post_id, 'Target Audience',        true );
    $learning_objectives    = get_post_meta( $post_id, 'Learning Objectives',    true );
    $applicable_standard    = get_post_meta( $post_id, 'Applicable Standard',    true );
    $competency_level       = get_post_meta( $post_id, 'Competency Level',       true );
    $industry_applicability = get_post_meta( $post_id, 'Industry Applicability', true );
    $prerequisites          = get_post_meta( $post_id, 'Prerequisites',          true );

    $schema = array(
        '@context'  => 'https://schema.org',
        '@type'     => 'Course',
        'name'      => $course_name,
        'description' => $course_description,
        'provider'  => array(
            '@type'  => 'Organization',
            'name'   => $provider_name,
            'sameAs' => $provider_url,
        ),
        'hasCourseInstance' => array(
            '@type'      => 'CourseInstance',
            'courseMode' => 'Online',
            'offers'     => array(
                '@type'         => 'Offer',
                'price'         => $course_price,
                'priceCurrency' => $course_currency,
                'availability'  => 'https://schema.org/InStock',
                'url'           => $enroll_url,
            ),
        ),
    );

    if ( ! empty( $target_audience ) ) {
        $schema['audience'] = array( '@type' => 'Audience', 'audienceType' => $target_audience );
    }
    if ( ! empty( $learning_objectives ) ) {
        $schema['educationalUse'] = $learning_objectives;
    }
    if ( ! empty( $applicable_standard ) ) {
        $schema['teaches'] = $applicable_standard;
    }
    if ( ! empty( $competency_level ) ) {
        $schema['educationalLevel'] = $competency_level;
    }
    if ( ! empty( $industry_applicability ) ) {
        $schema['about'] = strpos( $industry_applicability, ',' ) !== false
            ? array_map( 'trim', explode( ',', $industry_applicability ) )
            : trim( $industry_applicability );
    }
    if ( ! empty( $prerequisites ) ) {
        $schema['coursePrerequisites'] = strpos( $prerequisites, ',' ) !== false
            ? array_map( 'trim', explode( ',', $prerequisites ) )
            : trim( $prerequisites );
    }

    echo '<script type="application/ld+json">'
        . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        . '</script>';

    // ── Product schema with aggregate rating ──────────────────────────────────
    $placeholder = get_template_directory_uri() . '/V1/images/cd1.png';
    $image_id    = $product->get_image_id();
    $image_url   = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : $placeholder;

    // Rating data (uses your existing custom function if available)
    $has_rating   = false;
    $rating_value = 0;
    $review_count = 0;
    if ( function_exists( 'etr_calculate_aggregate_course_rating' ) ) {
        $feedback     = etr_calculate_aggregate_course_rating( $product->get_id() );
        $rating_value = $feedback['rating_value'] ?? 0;
        $review_count = $feedback['review_count']  ?? 0;
        $has_rating   = ( $rating_value > 0 && $review_count > 0 );
    }
    ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "<?php echo esc_js( $page_title ); ?>",
        "image": "<?php echo esc_url( $image_url ); ?>",
        "offers": {
            "@type": "Offer",
            "priceCurrency": "USD",
            "price": "<?php echo esc_js( $course_price ); ?>",
            "availability": "https://schema.org/InStock"
        }
        <?php if ( $has_rating ) : ?>
        ,"aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "<?php echo esc_js( $rating_value ); ?>",
            "reviewCount": "<?php echo esc_js( $review_count ); ?>"
        }
        <?php endif; ?>
    }
    </script>

<?php endif; // end if $product ?>

<?php
// ── Google Analytics / GTM data layer push ────────────────────────────────────
// Only fires for non-admin users (prevents polluting analytics with internal traffic)
if ( isset( $product ) && $product && ! current_user_can( 'manage_options' ) ) :
    $product_cat_names = array();
    $product_terms = get_the_terms( $product->get_id(), 'product_cat' );
    if ( $product_terms && ! is_wp_error( $product_terms ) ) {
        foreach ( $product_terms as $term ) {
            $product_cat_names[] = $term->name;
        }
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'view_item',
            ecommerce: {
                items: [{
                    item_name:     '<?php echo esc_js( $product->get_name() ); ?>',
                    item_id:       '<?php echo esc_js( $product->get_id() ); ?>',
                    price:         <?php echo (float) $product->get_price(); ?>,
                    item_category: '<?php echo esc_js( implode( ', ', $product_cat_names ) ); ?>'
                }]
            }
        });
    });
    </script>
<?php endif; ?>

<?php // Render modal overlays here — outside all containers so they overlay the full page correctly.
// This must come before the footer but after all page blocks.
clb_render_page_modals( $post_id );

// Use the plugin's self-contained footer — includes newsletter, links, and copyright
// When deploying to staging with your real theme, change this to: get_footer('v1');
include CLB_DIR . 'templates/footer.php'; ?>
