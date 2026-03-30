<?php
/**
 * block-renderer.php
 *
 * This file contains the PHP functions that generate the actual HTML shown to visitors.
 * Each block type has its own render function below.
 *
 * KEY PRINCIPLE — AUTO HIDE:
 * Every render function checks if it has the content it needs before outputting anything.
 * If a block has no content, the function simply returns without printing anything.
 * This means no blank sections, no broken layouts — the page just skips that section cleanly.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main entry point: render all configured blocks for a page.
 * Called from the page template file.
 *
 * @param int $post_id  The WordPress page ID
 */
function clb_render_blocks( $post_id ) {
    $blocks  = clb_get_page_blocks( $post_id );
    $product = clb_get_product( $post_id );

    if ( empty( $blocks ) ) {
        return;
    }

    foreach ( $blocks as $block ) {
        $type   = $block['type']   ?? '';
        $fields = $block['fields'] ?? array();

        // Skip these blocks in the main loop — they render inside other blocks:
        // enrollment_card renders inside the hero's right column.
        // course_versions renders inside the enrollment card.
        // These blocks render inside the enrollment card, not as standalone sections:
        if ( $type === 'enrollment_card' || $type === 'course_versions' || $type === 'course_outline' ) {
            continue;
        }

        clb_render_single_block( $type, $fields, $post_id, $product );
    }
}

/**
 * Route a block to its specific render function.
 */
function clb_render_single_block( $type, $fields, $post_id, $product ) {
    switch ( $type ) {
        case 'trust_bar':       clb_render_trust_bar( $fields );                    break;
        case 'hero':            clb_render_hero( $fields, $post_id, $product );     break;
        case 'enrollment_card': clb_render_enrollment_card( $fields, $post_id, $product ); break;
        case 'stats_strip':     clb_render_stats_strip( $fields );                  break;
        case 'faq':             clb_render_faq( $fields, $post_id );                break;
        case 'course_outline':    clb_render_course_outline( $fields, $post_id );                break;
        case 'why_etraining':     clb_render_why_etraining( $fields, $post_id );               break;
        case 'student_reviews':   clb_render_student_reviews( $fields, $post_id );            break;
        case 'course_info_tabs': clb_render_course_info_tabs( $fields, $post_id, $product ); break;
        case 'course_versions': clb_render_course_versions( $fields, $post_id, $product ); break;
        // Future block types go here
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// TRUST BAR
// The compliance/trust indicator banner at the top of the page
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_trust_bar( $fields ) {
    $items = $fields['items'] ?? array();

    // Filter out items that have no text
    $items = array_filter( $items, function( $item ) {
        return ! empty( trim( $item['text'] ?? '' ) );
    });

    // Auto-hide: don't render if there are no items
    if ( empty( $items ) ) return;
    ?>
    <div class="trust-bar">
        <div class="container">
            <div class="d-flex align-items-center justify-content-center flex-wrap gap-4">
                <?php foreach ( $items as $item ) :
                    $icon = $item['icon'] ?? '';
                    $text = $item['text'] ?? '';
                    ?>
                    <div class="trust-item">
                        <?php if ( $icon ) : ?>
                            <i class="<?php echo esc_attr( $icon ); ?>"></i>
                        <?php endif; ?>
                        <?php echo esc_html( $text ); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// HERO SECTION
// Main headline area — title comes from the WordPress page title automatically
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_hero( $fields, $post_id, $product ) {
    $eyebrow          = $fields['eyebrow']          ?? '';
    $eyebrow_icon     = $fields['eyebrow_icon']     ?? '';
    $subtitle         = $fields['subtitle']         ?? '';
    $badges           = $fields['badges']           ?? array();
    $show_description = isset( $fields['show_description'] ) ? (bool) $fields['show_description'] : true;

    // Filter badges that have no text
    $badges = array_filter( $badges, function( $b ) {
        return ! empty( trim( $b['text'] ?? '' ) );
    });
    ?>

    <!-- SVG gradient referenced by fill:url(#brand-gradient) throughout the page -->
    <svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">
        <defs>
            <linearGradient id="brand-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#3fd0c9"/>
                <stop offset="100%" stop-color="#70f07f"/>
            </linearGradient>
        </defs>
    </svg>

    <section class="hero">
        <div class="container">
            <div class="row align-items-center g-5">

                <!-- Left column: headline, subtitle, badges, description -->
                <div class="col-lg-7">

                    <?php if ( $eyebrow ) : ?>
                        <div class="hero-eyebrow">
                            <?php if ( $eyebrow_icon ) : ?>
                                <i class="<?php echo esc_attr( $eyebrow_icon ); ?>"></i>
                            <?php endif; ?>
                            <?php echo esc_html( $eyebrow ); ?>
                        </div>
                    <?php endif; ?>

                    <h1><?php echo get_the_title( $post_id ); ?></h1>

                    <?php if ( $subtitle ) : ?>
                        <p class="hero-sub"><?php echo esc_html( $subtitle ); ?></p>
                    <?php endif; ?>

                    <?php if ( ! empty( $badges ) ) : ?>
                        <div class="badge-row">
                            <?php foreach ( $badges as $badge ) :
                                $badge_icon = $badge['icon'] ?? '';
                                $badge_text = $badge['text'] ?? '';
                                ?>
                                <div class="badge-pill">
                                    <?php if ( $badge_icon ) : ?>
                                        <i class="<?php echo esc_attr( $badge_icon ); ?>"></i>
                                    <?php endif; ?>
                                    <?php echo esc_html( $badge_text ); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $show_description ) : ?>
                        <div class="hero-description">
                            <?php
                            // Output the WordPress page content (what's in the main editor)
                            $content = get_post_field( 'post_content', $post_id );
                            echo apply_filters( 'the_content', $content );
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Course Outline button — rendered here in the Hero below the description.
                    // Styled as a ghost secondary button, distinct from the enrollment CTA.
                    $all_blocks = clb_get_page_blocks( $post_id );
                    foreach ( $all_blocks as $outline_block ) {
                        if ( ( $outline_block['type'] ?? '' ) === 'course_outline' ) {
                            clb_render_course_outline( $outline_block['fields'] ?? array(), $post_id );
                            break;
                        }
                    }
                    ?>

                </div><!-- /.col-lg-7 -->

                <!-- Right column: enrollment card renders here when added as a block -->
                <div class="col-lg-5" id="clb-enroll-col">
                    <?php
                    // Check if an enrollment_card block exists in the config
                    // and render it inline within the hero's right column
                    $all_blocks = clb_get_page_blocks( $post_id );
                    foreach ( $all_blocks as $block ) {
                        if ( ( $block['type'] ?? '' ) === 'enrollment_card' ) {
                            clb_render_enrollment_card( $block['fields'] ?? array(), $post_id, clb_get_product( $post_id ) );
                            break; // Only render one enrollment card here
                        }
                    }
                    ?>
                </div><!-- /.col-lg-5 -->

            </div><!-- /.row -->
        </div><!-- /.container -->
    </section>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// ENROLLMENT CARD
// The purchase box — rendered inside the hero's right column
// Price and enroll URL connect to WooCommerce automatically
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_enrollment_card( $fields, $post_id, $product ) {
    $price_source      = $fields['price_source']      ?? 'woocommerce';
    $custom_price      = $fields['custom_price']      ?? '';
    $price_subtitle    = $fields['price_subtitle']    ?? 'Per person &bull; No subscription required';
    $button_text       = $fields['button_text']       ?? 'Enroll Now';
    $button_url_source = $fields['button_url_source'] ?? 'product_slug';
    $custom_button_url = $fields['custom_button_url'] ?? '';
    $topics_label      = $fields['topics_label']      ?? 'Topics Covered';
    $topics            = $fields['topics']            ?? array();
    $guarantee_text    = $fields['guarantee_text']    ?? '';

    // ── Determine price ──
    $price_html = '';
    if ( $price_source === 'woocommerce' && $product && function_exists( 'wc_price' ) ) {
        $price_html = wc_price( $product->get_price() );
    } elseif ( ! empty( $custom_price ) ) {
        $price_html = '$' . esc_html( $custom_price );
    }

    // ── Determine enroll button URL ──
    $button_url = '#';
    if ( $button_url_source === 'product_slug' ) {
        $slug = get_post_meta( $post_id, 'Product SLUG', true );
        if ( $slug ) {
            $button_url = '/shop/' . sanitize_title( $slug ) . '/';
        }
    } elseif ( ! empty( $custom_button_url ) ) {
        $button_url = $custom_button_url;
    }

    // ── Filter topics ──
    $topics = array_filter( $topics, function( $t ) {
        return ! empty( trim( $t['topic'] ?? '' ) );
    });

    // Auto-hide: don't render if there's nothing to show
    if ( ! $price_html && $button_url === '#' && empty( $topics ) ) return;
    ?>
    <div class="enroll-card">

        <?php if ( $price_html ) : ?>
            <div class="enroll-price-block">
                <div class="enroll-price"><?php echo $price_html; ?></div>
                <div class="enroll-price-sub"><?php echo wp_kses_post( $price_subtitle ); ?></div>
            </div>
        <?php endif; ?>

        <a href="<?php echo esc_url( $button_url ); ?>"
           class="btn-enroll btn-enroll-teal d-block text-center">
            <i class="fas fa-shopping-cart me-2"></i>
            <?php echo esc_html( $button_text ); ?>
        </a>

        <?php if ( ! empty( $topics ) ) : ?>
            <div class="enroll-topics">
                <div class="enroll-topics-label"><?php echo esc_html( $topics_label ); ?></div>
                <ul>
                    <?php foreach ( $topics as $topic_item ) :
                        $topic = trim( $topic_item['topic'] ?? '' );
                        if ( $topic ) : ?>
                            <li><?php echo esc_html( $topic ); ?></li>
                        <?php endif;
                    endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( $guarantee_text ) : ?>
            <div class="enroll-guarantee">
                <i class="fas fa-shield-alt"></i>
                <?php echo wp_kses_post( $guarantee_text ); ?>
            </div>
        <?php endif; ?>

        <?php
        // Render Course Versions trigger button and Course Outline button inside the
        // enrollment card where they belong — not as standalone page sections.
        $all_blocks = clb_get_page_blocks( $post_id );
        // Only render the Course Versions trigger here — Course Outline button
        // now renders in the Hero section below the badge pills.
        foreach ( $all_blocks as $inner_block ) {
            $inner_type = $inner_block['type'] ?? '';
            if ( $inner_type === 'course_versions' ) {
                clb_render_course_versions_button( $inner_block['fields'] ?? array(), $post_id );
            }
        }
        ?>

    </div><!-- /.enroll-card -->
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// STATS STRIP
// Horizontal band of highlighted numbers/statistics
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_stats_strip( $fields ) {
    $items = $fields['items'] ?? array();

    // Filter items with no number value
    $items = array_filter( $items, function( $item ) {
        return ! empty( trim( $item['number'] ?? '' ) );
    });

    // Auto-hide: don't render if there are no stats
    if ( empty( $items ) ) return;
    ?>
    <div class="stats-strip">
        <div class="container">
            <div class="row text-center g-4">
                <?php foreach ( $items as $item ) :
                    $number = $item['number'] ?? '';
                    $label  = $item['label']  ?? '';
                    ?>
                    <div class="col-6 col-md-3">
                        <div class="stat-num"><?php echo esc_html( $number ); ?></div>
                        <div class="stat-lbl"><?php echo esc_html( $label ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// FAQ SECTION
// Auto-pulls FAQs from WordPress — no manual entry needed here
// Hidden automatically if no FAQs exist for this page slug
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_faq( $fields, $post_id ) {
    $title  = $fields['title']  ?? 'Frequently Asked Questions';
    $layout = $fields['layout'] ?? 'two_col';

    $slug = get_post_field( 'post_name', $post_id );

    // Pull FAQs tagged to this page's slug
    $faqs = get_posts( array(
        'posts_per_page' => -1,
        'post_type'      => 'faq',
        'post_status'    => 'publish',
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'tax_query'      => array(
            array(
                'taxonomy' => 'faq-group',
                'field'    => 'slug',
                'terms'    => $slug,
            ),
        ),
    ) );

    // Auto-hide: don't render if there are no FAQs for this page
    if ( empty( $faqs ) ) return;

    $num_cols = ( $layout === 'two_col' ) ? 2 : 1;
    $col_class = ( $layout === 'two_col' ) ? 'col-12 col-lg-6' : 'col-12';
    $chunks   = array_chunk( $faqs, (int) ceil( count( $faqs ) / $num_cols ) );
    ?>
    <section class="hz-faq">
        <div class="container">
            <div class="hz-faq-inner">

                <!-- FAQ heading -->
                <div class="hz-faq-header">
                    <div class="sec-tag mb-3">
                        <i class="fas fa-question-circle"></i>
                        Common Questions
                    </div>
                    <h2 class="hz-faq-title"><?php echo esc_html( $title ); ?></h2>
                    <p class="hz-faq-subtitle">Everything you need to know before you enroll.</p>
                </div>

                <!-- FAQ accordion columns -->
                <div class="row">
                    <?php foreach ( $chunks as $col_index => $column_faqs ) : ?>
                        <div class="<?php echo esc_attr( $col_class ); ?>">
                            <div class="accordion clb-faq-accordion" id="faqAccordion_<?php echo (int) $col_index; ?>">
                                <?php
                                $faq_count = 1;
                                foreach ( $column_faqs as $faq ) :
                                    $is_open = ( $faq_count === 1 && $col_index === 0 );
                                    $faq_count++;
                                    ?>
                                    <div class="hz-faq-item <?php echo $is_open ? 'open' : ''; ?>">
                                        <button class="hz-faq-question"
                                                type="button"
                                                aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
                                            <?php echo esc_html( $faq->post_title ); ?>
                                            <svg class="hz-faq-chevron" viewBox="0 0 24 24">
                                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                        <div class="hz-faq-answer" <?php echo $is_open ? 'style="display:block;"' : ''; ?>>
                                            <?php echo wpautop( $faq->post_content ); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div><!-- /.hz-faq-inner -->
        </div><!-- /.container -->
    </section>

    <script>
    // Lightweight FAQ accordion — no Bootstrap dependency needed
    (function() {
        document.querySelectorAll('.clb-faq-accordion .hz-faq-question').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var item   = btn.closest('.hz-faq-item');
                var answer = item.querySelector('.hz-faq-answer');
                var isOpen = item.classList.contains('open');

                // Close all in same accordion
                var accordion = btn.closest('.clb-faq-accordion');
                accordion.querySelectorAll('.hz-faq-item').forEach(function(i) {
                    i.classList.remove('open');
                    i.querySelector('.hz-faq-answer').style.display = 'none';
                    i.querySelector('.hz-faq-question').setAttribute('aria-expanded', 'false');
                });

                // Open clicked if it was closed
                if ( ! isOpen ) {
                    item.classList.add('open');
                    answer.style.display = 'block';
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });
    })();
    </script>
    <?php

    wp_reset_postdata();
}

// ═══════════════════════════════════════════════════════════════════════════════
// COURSE OUTLINE
// A button that opens a popup — content pulled from the "Course Outline" custom field
// Hidden automatically if the custom field is empty
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_course_outline( $fields, $post_id ) {
    $button_text = $fields['button_text'] ?? 'View Full Course Outline';

    // Migration fix: if saved with old default text, correct it silently
    if ( in_array( $button_text, array(
        'View All Versions &amp; Topics',
        'View All Versions & Topics',
    ) ) ) {
        $button_text = 'View Full Course Outline';
    }

    $outline = get_post_meta( $post_id, 'Course Outline', true );

    // Auto-hide: don't render if there is no outline content
    if ( empty( trim( $outline ) ) ) return;

    // Unique modal ID
    $modal_id = 'clb-outline-modal-' . $post_id;
    ?>

    <!--
        Course Outline Button — renders in the Hero section below badge pills.
        Styled as a ghost/secondary button to distinguish from the enrollment CTA.
        Modal uses the same modal-overlay pattern as Course Versions.
    -->
    <button class="clb-outline-btn clb-versions-trigger"
            data-modal="<?php echo esc_attr( $modal_id ); ?>"
            aria-haspopup="dialog"
            aria-controls="<?php echo esc_attr( $modal_id ); ?>">
        <i class="fas fa-file-alt me-2"></i>
        <?php echo esc_html( $button_text ); ?> &rarr;
    </button>

    <!-- Modal overlay — rendered here but positioned fixed so it overlays the full page -->
    <div class="modal-overlay" id="<?php echo esc_attr( $modal_id ); ?>"
         role="dialog" aria-modal="true"
         aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title">
        <div class="modal-box">

            <div class="modal-hd">
                <h2 id="<?php echo esc_attr( $modal_id ); ?>-title">
                    <?php echo esc_html( get_the_title( $post_id ) ); ?> — Course Outline
                </h2>
                <p>Full topic breakdown and course structure</p>
                <button class="modal-close clb-modal-close" aria-label="Close">&times;</button>
            </div>

            <div class="modal-bd">
                <?php echo wpautop( $outline ); ?>
            </div>

        </div><!-- /.modal-box -->
    </div><!-- /.modal-overlay -->

    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// COURSE VERSIONS POPUP
// Optional block — hidden if no versions configured.
// Renders a button on the enrollment card + a modal popup listing all versions.
// Each version connects to its own WooCommerce product for automatic pricing.
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Render ONLY the trigger button for the course versions popup.
 * Called from inside the enrollment card so the button appears in the right place.
 */
function clb_render_course_versions_button( $fields, $post_id ) {
    $button_text = $fields['button_text'] ?? 'View All Versions & Topics';
    $versions    = $fields['versions']    ?? array();

    $versions = array_filter( $versions, function( $v ) {
        return ! empty( trim( $v['name'] ?? '' ) );
    });

    if ( empty( $versions ) ) return;

    $modal_id = 'clb-versions-modal-' . $post_id;
    ?>
    <button class="pc-more clb-versions-trigger"
            data-modal="<?php echo esc_attr( $modal_id ); ?>"
            aria-haspopup="dialog">
        <i class="fas fa-layer-group me-2"></i>
        <?php echo esc_html( $button_text ); ?> &rarr;
    </button>
    <?php
}

/**
 * Render the full course versions popup (button + modal).
 * The button renders inline; the modal overlay renders at the bottom of the page
 * via the course-landing.php template so it sits outside all other containers.
 */
function clb_render_course_versions( $fields, $post_id, $product ) {
    $button_text    = $fields['button_text']    ?? 'View All Versions & Topics';
    $modal_title    = $fields['modal_title']    ?? 'Choose Your Course Version';
    $modal_subtitle = $fields['modal_subtitle'] ?? 'All versions include instant certificate delivery';
    $versions       = $fields['versions']       ?? array();

    $versions = array_filter( $versions, function( $v ) {
        return ! empty( trim( $v['name'] ?? '' ) );
    });

    if ( empty( $versions ) ) return;

    $modal_id = 'clb-versions-modal-' . $post_id;
    ?>
    <!-- This function is called from the page template footer area — modal only -->

    <!-- ── Modal overlay ── -->
    <div class="modal-overlay" id="<?php echo esc_attr( $modal_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title">
        <div class="modal-box">

            <!-- Modal header -->
            <div class="modal-hd">
                <h2 id="<?php echo esc_attr( $modal_id ); ?>-title">
                    <?php echo esc_html( $modal_title ); ?>
                </h2>
                <p><?php echo esc_html( $modal_subtitle ); ?></p>
                <button class="modal-close clb-modal-close" aria-label="Close">
                    &times;
                </button>
            </div>

            <!-- Modal body -->
            <div class="modal-bd">

                <div class="modal-row-label">Available Versions</div>

                <div class="modal-variants-grid">
                    <?php foreach ( $versions as $version ) :
                        $name        = trim( $version['name']        ?? '' );
                        $product_id  = intval( $version['product_id']  ?? 0 );
                        $enroll_url  = trim( $version['enroll_url']  ?? '' );
                        $description = trim( $version['description'] ?? '' );
                        $is_featured = strtolower( trim( $version['is_featured'] ?? '' ) ) === 'yes';

                        // Pull price from WooCommerce automatically if product ID is set
                        $price_html = '';
                        if ( $product_id && function_exists( 'wc_get_product' ) ) {
                            $ver_product = wc_get_product( $product_id );
                            if ( $ver_product ) {
                                $price_html = wc_price( $ver_product->get_price() );
                            }
                        }
                        ?>
                        <div class="var-card <?php echo $is_featured ? 'featured' : ''; ?>">
                            <?php if ( $is_featured ) : ?>
                                <div class="var-badge unlocked">⭐ Most Popular</div>
                            <?php endif; ?>

                            <div class="var-lang"><?php echo esc_html( $name ); ?></div>

                            <?php if ( $description ) : ?>
                                <p class="var-desc"><?php echo esc_html( $description ); ?></p>
                            <?php endif; ?>

                            <?php if ( $price_html ) : ?>
                                <div class="var-price"><?php echo $price_html; ?></div>
                            <?php endif; ?>

                            <?php if ( $enroll_url ) : ?>
                                <a href="<?php echo esc_url( $enroll_url ); ?>"
                                   class="var-btn">
                                    <i class="fas fa-shopping-cart me-1"></i>
                                    Enroll Now
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div><!-- /.modal-bd -->
        </div><!-- /.modal-box -->
    </div><!-- /.modal-overlay -->

    <?php
}


// ═══════════════════════════════════════════════════════════════════════════════
// COURSE INFORMATION TABS
// Vertical tab section matching the hz-tabs design exactly.
// Left rail: icon box + heading + subtitle for each tab.
// Right panel: content area switches on tab click.
// Each tab auto-hides if its content is empty.
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_course_info_tabs( $fields, $post_id, $product ) {

    $section_tag   = $fields['section_tag']   ?? 'Course Details';
    $section_title = $fields['section_title'] ?? 'Everything You Need to Know';

    $overview_url = trim( $fields['overview_video_url'] ?? '' );
    $preview_url  = trim( $fields['preview_video_url']  ?? '' );

    $whats_included = array_filter( $fields['whats_included'] ?? array(), fn($i) => ! empty( trim( $i['title'] ?? '' ) ) );
    $course_details = array_filter( $fields['course_details'] ?? array(), fn($i) => ! empty( trim( $i['label'] ?? '' ) ) );
    $who_should     = array_filter( $fields['who_should']     ?? array(), fn($i) => ! empty( trim( $i['title'] ?? '' ) ) );
    $industries     = array_filter( $fields['industries']     ?? array(), fn($i) => ! empty( trim( $i['title'] ?? '' ) ) );

    // Build tabs array — only include tabs that have content
    // Each tab: label, subtitle, icon SVG path (Font Awesome-style path data)
    $tabs = array();

    if ( $overview_url ) {
        $tabs['overview'] = array(
            'label'    => 'Course Overview',
            'subtitle' => 'Full walkthrough video',
            'icon'     => 'video',
        );
    }
    if ( $preview_url ) {
        $tabs['preview'] = array(
            'label'    => '5-Min Free Preview',
            'subtitle' => 'Try before you buy',
            'icon'     => 'play',
        );
    }
    if ( ! empty( $whats_included ) ) {
        $tabs['whats_included'] = array(
            'label'    => "What's Included",
            'subtitle' => 'Everything in your enrollment',
            'icon'     => 'checklist',
        );
    }
    if ( ! empty( $course_details ) ) {
        $tabs['course_details'] = array(
            'label'    => 'Course Details',
            'subtitle' => 'Format, duration & compliance',
            'icon'     => 'clipboard',
        );
    }
    if ( ! empty( $who_should ) ) {
        $tabs['who_should'] = array(
            'label'    => 'Who Should Enroll',
            'subtitle' => 'Roles & job types',
            'icon'     => 'people',
        );
    }
    if ( ! empty( $industries ) ) {
        $tabs['industries'] = array(
            'label'    => 'Industries',
            'subtitle' => 'Where this training is required',
            'icon'     => 'building',
        );
    }

    if ( empty( $tabs ) ) return;

    $first_tab = array_key_first( $tabs );

    // SVG icon paths for each tab type
    $icons = array(
        'video'     => '<path d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'play'      => '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M10 8l6 4-6 4V8z" fill="currentColor"/>',
        'checklist' => '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'clipboard' => '<path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'people'    => '<path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'building'  => '<path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    );
    ?>

    <!-- SVG gradient needed for icon fills -->
    <svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">
        <defs>
            <linearGradient id="brand-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#3fd0c9"/>
                <stop offset="100%" stop-color="#70f07f"/>
            </linearGradient>
        </defs>
    </svg>

    <section class="hz-tabs-section">
        <div class="container">

            <!-- Section heading -->
            <div class="text-center mb-5">
                <?php if ( $section_tag ) : ?>
                    <div class="sec-tag mx-auto" style="width:fit-content;">
                        <i class="fas fa-graduation-cap"></i>
                        <?php echo esc_html( $section_tag ); ?>
                    </div>
                <?php endif; ?>
                <h2 class="sec-title mt-3"><?php echo esc_html( $section_title ); ?></h2>
            </div>

            <div class="hz-tabs-wrap">

                <!-- ── Left rail: tab buttons ── -->
                <nav class="hz-business-features" role="tablist">
                    <?php foreach ( $tabs as $tab_key => $tab ) :
                        $is_active  = ( $tab_key === $first_tab );
                        $icon_svg   = $icons[ $tab['icon'] ] ?? $icons['checklist'];
                        ?>
                        <div class="hz-feature <?php echo $is_active ? 'active' : ''; ?>"
                             role="tab"
                             tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
                             aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                             aria-controls="hz-panel-<?php echo esc_attr( $tab_key ); ?>"
                             data-tab="<?php echo esc_attr( $tab_key ); ?>">

                            <!-- Icon box -->
                            <div class="hz-feature-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <?php echo $icon_svg; ?>
                                </svg>
                            </div>

                            <!-- Label + subtitle -->
                            <div>
                                <h4><?php echo esc_html( $tab['label'] ); ?></h4>
                                <p><?php echo esc_html( $tab['subtitle'] ); ?></p>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </nav>

                <!-- ── Right area: tab panels ── -->
                <div class="hz-panel-wrap">

                    <?php // ── Course Overview Video ── ?>
                    <?php if ( $overview_url ) :
                        $is_active = ( $first_tab === 'overview' ); ?>
                        <div class="hz-panel <?php echo $is_active ? 'active' : ''; ?>"
                             id="hz-panel-overview" role="tabpanel">
                            <div class="hz-panel-header">
                                <h3>Course Overview</h3>
                                <p>Watch a full walkthrough of this course before you enroll.</p>
                            </div>
                            <div class="hz-iframe-wrap">
                                <iframe src="<?php echo esc_url( $overview_url ); ?>"
                                        allow="autoplay; fullscreen; picture-in-picture"
                                        allowfullscreen title="Course Overview" loading="lazy"></iframe>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php // ── 5-Minute Preview Video ── ?>
                    <?php if ( $preview_url ) :
                        $is_active = ( $first_tab === 'preview' ); ?>
                        <div class="hz-panel <?php echo $is_active ? 'active' : ''; ?>"
                             id="hz-panel-preview" role="tabpanel">
                            <div class="hz-panel-header">
                                <h3>Free 5-Minute Preview</h3>
                                <p>Try before you buy — watch the first 5 minutes free.</p>
                            </div>
                            <div class="hz-iframe-wrap">
                                <iframe src="<?php echo esc_url( $preview_url ); ?>"
                                        allow="autoplay; fullscreen; picture-in-picture"
                                        allowfullscreen title="Course Preview" loading="lazy"></iframe>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php // ── Whats Included ── ?>
                    <?php if ( ! empty( $whats_included ) ) :
                        $is_active = ( $first_tab === 'whats_included' ); ?>
                        <div class="hz-panel <?php echo $is_active ? 'active' : ''; ?>"
                             id="hz-panel-whats_included" role="tabpanel">
                            <div class="hz-panel-header">
                                <h3>What's Included</h3>
                                <p>Everything you get when you enroll in this course.</p>
                            </div>
                            <div class="hz-panel-body">
                                <ul>
                                    <?php foreach ( $whats_included as $item ) :
                                        $title    = trim( $item['title']    ?? '' );
                                        $subtitle = trim( $item['subtitle'] ?? '' );
                                        if ( ! $title ) continue; ?>
                                        <li>
                                            <strong><?php echo esc_html( $title ); ?></strong>
                                            <?php if ( $subtitle ) : ?>
                                                <br><span style="font-weight:400;"><?php echo esc_html( $subtitle ); ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <a href="#enroll-col" class="hz-panel-btn">Enroll Now &rarr;</a>
                        </div>
                    <?php endif; ?>

                    <?php // ── Course Details at a Glance ── ?>
                    <?php if ( ! empty( $course_details ) ) :
                        $is_active = ( $first_tab === 'course_details' ); ?>
                        <div class="hz-panel <?php echo $is_active ? 'active' : ''; ?>"
                             id="hz-panel-course_details" role="tabpanel">
                            <div class="hz-panel-header">
                                <h3>Course Details at a Glance</h3>
                                <p>Key facts about this course.</p>
                            </div>
                            <div class="hz-panel-body">
                                <div class="hz-tbl-wrap">
                                    <table class="hz-tbl">
                                        <thead>
                                            <tr>
                                                <th>Detail</th>
                                                <th>Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $course_details as $row ) :
                                                $label = trim( $row['label'] ?? '' );
                                                $value = trim( $row['value'] ?? '' );
                                                if ( ! $label ) continue; ?>
                                                <tr>
                                                    <td><?php echo esc_html( $label ); ?></td>
                                                    <td><?php echo esc_html( $value ); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <a href="#enroll-col" class="hz-panel-btn">Enroll Now &rarr;</a>
                        </div>
                    <?php endif; ?>

                    <?php // ── Who Should Enroll ── ?>
                    <?php if ( ! empty( $who_should ) ) :
                        $is_active = ( $first_tab === 'who_should' ); ?>
                        <div class="hz-panel <?php echo $is_active ? 'active' : ''; ?>"
                             id="hz-panel-who_should" role="tabpanel">
                            <div class="hz-panel-header">
                                <h3>Who Should Take This Course</h3>
                                <p>This course is designed for the following roles.</p>
                            </div>
                            <div class="hz-panel-body">
                                <ul>
                                    <?php foreach ( $who_should as $item ) :
                                        $title    = trim( $item['title']    ?? '' );
                                        $subtitle = trim( $item['subtitle'] ?? '' );
                                        if ( ! $title ) continue; ?>
                                        <li>
                                            <strong><?php echo esc_html( $title ); ?></strong>
                                            <?php if ( $subtitle ) : ?>
                                                <br><span style="font-weight:400;"><?php echo esc_html( $subtitle ); ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <a href="#enroll-col" class="hz-panel-btn">Enroll Now &rarr;</a>
                        </div>
                    <?php endif; ?>

                    <?php // ── Industries ── ?>
                    <?php if ( ! empty( $industries ) ) :
                        $is_active = ( $first_tab === 'industries' ); ?>
                        <div class="hz-panel <?php echo $is_active ? 'active' : ''; ?>"
                             id="hz-panel-industries" role="tabpanel">
                            <div class="hz-panel-header">
                                <h3>Industries That Require This Training</h3>
                                <p>This certification is required or recommended in the following industries.</p>
                            </div>
                            <div class="hz-panel-body">
                                <ul>
                                    <?php foreach ( $industries as $item ) :
                                        $title    = trim( $item['title']    ?? '' );
                                        $subtitle = trim( $item['subtitle'] ?? '' );
                                        if ( ! $title ) continue; ?>
                                        <li>
                                            <strong><?php echo esc_html( $title ); ?></strong>
                                            <?php if ( $subtitle ) : ?>
                                                <br><span style="font-weight:400;"><?php echo esc_html( $subtitle ); ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <a href="#enroll-col" class="hz-panel-btn">Enroll Now &rarr;</a>
                        </div>
                    <?php endif; ?>

                </div><!-- /.hz-panel-wrap -->
            </div><!-- /.hz-tabs-wrap -->
        </div><!-- /.container -->
    </section>

    <script>
    (function() {
        var features = document.querySelectorAll('.hz-feature[data-tab]');
        var panels   = document.querySelectorAll('.hz-panel[id^="hz-panel-"]');

        features.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var target = btn.getAttribute('data-tab');

                features.forEach(function(f) {
                    f.classList.remove('active');
                    f.setAttribute('aria-selected', 'false');
                    f.setAttribute('tabindex', '-1');
                });
                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');
                btn.setAttribute('tabindex', '0');

                panels.forEach(function(p) { p.classList.remove('active'); });
                var active = document.getElementById('hz-panel-' + target);
                if ( active ) active.classList.add('active');
            });

            btn.addEventListener('keydown', function(e) {
                var all   = Array.from(features);
                var index = all.indexOf(btn);
                if ( e.key === 'ArrowDown' && index < all.length - 1 ) {
                    e.preventDefault();
                    all[index + 1].click();
                    all[index + 1].focus();
                }
                if ( e.key === 'ArrowUp' && index > 0 ) {
                    e.preventDefault();
                    all[index - 1].click();
                    all[index - 1].focus();
                }
            });
        });
    })();
    </script>

    <?php
}


/**
 * Render all modal overlays for a page.
 * Called once at the bottom of course-landing.php — after all blocks — so modals
 * sit outside every container and overlay the full page correctly.
 */
function clb_render_page_modals( $post_id ) {
    $blocks  = clb_get_page_blocks( $post_id );
    $product = clb_get_product( $post_id );

    foreach ( $blocks as $block ) {
        $type   = $block['type']   ?? '';
        $fields = $block['fields'] ?? array();

        if ( $type === 'course_versions' ) {
            clb_render_course_versions( $fields, $post_id, $product );
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// WHY ETRAINING SECTION
// Static section — content is fixed and identical across all courses.
// No configurable fields. Just add the block and it renders automatically.
// To update this content in the future, a developer edits this function only.
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_why_etraining( $fields, $post_id ) {

    // Static features — same across every course
    $features = array(
        array(
            'title'       => 'Accepted at Job Sites Nationwide',
            'description' => 'Our certificates are recognized by employers and regulatory agencies in all 50 states.',
        ),
        array(
            'title'       => 'Engaging, Modern Content',
            'description' => 'No boring slideshows or robotic narration — real-world scenarios and interactive media that actually sticks.',
        ),
        array(
            'title'       => 'Resume Where You Left Off',
            'description' => 'Your progress is saved automatically. Come back on any device at any time — no time pressure.',
        ),
        array(
            'title'       => 'Group Enrollment & Volume Discounts',
            'description' => 'Training a team? Business accounts offer 20%+ off for groups of 20+ with a full admin dashboard.',
        ),
    );

    // Static stats — same across every course
    $stats = array(
        array( 'number' => '10K+', 'label' => 'Workers Certified' ),
        array( 'number' => '4.9★', 'label' => 'Average Rating'    ),
        array( 'number' => '100%', 'label' => 'OSHA Compliant'    ),
        array( 'number' => '50',   'label' => 'States Accepted'   ),
    );
    ?>

    <section class="why-section">
        <div class="container">
            <div class="row align-items-start g-5">

                <!-- Left column: heading, intro, stats bar, feature rows -->
                <div class="col-lg-6">

                    <div class="sec-tag mb-3">
                        <i class="fas fa-award"></i>
                        Trusted Training Provider
                    </div>

                    <h2 class="sec-title mb-3">Why Choose eTraining?</h2>

                    <p class="sec-sub mb-4">
                        We've helped over 10,000 workers and organizations stay OSHA compliant.
                        Here's what makes our courses different from the rest.
                    </p>

                    <!-- Stats bar -->
                    <div class="why-stats">
                        <?php foreach ( $stats as $i => $stat ) : ?>
                            <?php if ( $i > 0 ) : ?>
                                <div class="why-stat-div"></div>
                            <?php endif; ?>
                            <div class="why-stat">
                                <div class="why-stat-num"><?php echo esc_html( $stat['number'] ); ?></div>
                                <div class="why-stat-label"><?php echo esc_html( $stat['label'] ); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Feature rows -->
                    <div class="why-feature-list">
                        <?php foreach ( $features as $feature ) : ?>
                            <div class="why-feature-row">
                                <div class="why-feature-check">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div>
                                    <div class="why-feature-title"><?php echo esc_html( $feature['title'] ); ?></div>
                                    <p class="why-feature-desc"><?php echo esc_html( $feature['description'] ); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div><!-- /.col-lg-6 left -->

                <!-- Right column: branded certificate preview -->
                <div class="col-lg-6">
                    <div class="cert-box">
                        <div class="cert-doc-outer">
                            <div class="cert-doc-inner">

                                <div class="cert-doc-hd">
                                    <div class="cert-doc-logo">
                                        <span class="cert-doc-logo-e">e</span><span class="cert-doc-logo-t">Training</span>
                                    </div>
                                    <div class="cert-doc-tagline">Inc.</div>
                                </div>

                                <div class="cert-doc-seal-row">
                                    <svg class="cert-doc-seal" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="40" cy="40" r="36" fill="none" stroke="#d4a017" stroke-width="3"/>
                                        <circle cx="40" cy="40" r="28" fill="none" stroke="#d4a017" stroke-width="1.5"/>
                                        <text x="40" y="35" text-anchor="middle" font-size="7" font-weight="bold" fill="#d4a017" font-family="serif">ACHIEVEMENT</text>
                                        <text x="40" y="47" text-anchor="middle" font-size="16" fill="#d4a017">&#9733;</text>
                                    </svg>
                                </div>

                                <div class="cert-doc-title-block">
                                    <div class="cert-doc-of">Certificate of Completion</div>
                                    <div class="cert-doc-course" style="font-style:italic;font-size:1.1rem;margin-top:4px;">This certifies that</div>
                                    <div class="cert-doc-course" style="font-size:1.4rem;margin-top:4px;">Your Name Here</div>
                                    <div class="cert-doc-osha" style="margin-top:6px;font-size:0.78rem;color:#495057;">has successfully completed the</div>
                                    <div class="cert-doc-course" style="margin-top:6px;color:#3fd0c9;">HAZWOPER 8-Hour Annual Refresher</div>
                                    <div class="cert-doc-osha">OSHA 29 CFR 1910.120 / 1926.65 &mdash; 8 Hours</div>
                                </div>

                                <div class="cert-doc-meta" style="margin-top:12px;">
                                    <div class="cert-doc-meta-item">
                                        <div class="cert-doc-meta-label">Date</div>
                                        <div class="cert-doc-meta-val"><?php echo date( 'F j, Y' ); ?></div>
                                    </div>
                                    <div class="cert-doc-meta-div"></div>
                                    <div class="cert-doc-meta-item">
                                        <div class="cert-doc-meta-label">Certificate No.</div>
                                        <div class="cert-doc-meta-val" style="color:#3fd0c9;">284394</div>
                                    </div>
                                    <div class="cert-doc-meta-div"></div>
                                    <div class="cert-doc-meta-item">
                                        <div class="cert-doc-meta-label">Website</div>
                                        <div class="cert-doc-meta-val">etraintoday.com</div>
                                    </div>
                                </div>

                                <div class="cert-doc-sigs">
                                    <div class="cert-doc-sig">
                                        <div class="cert-doc-sig-line"></div>
                                        <div class="cert-doc-sig-name">Niall O'Malley</div>
                                        <div class="cert-doc-sig-org">President</div>
                                    </div>
                                    <div class="cert-doc-sig">
                                        <div class="cert-doc-sig-line"></div>
                                        <div class="cert-doc-sig-name">Larry A. Baylor</div>
                                        <div class="cert-doc-sig-org">VP Content Development</div>
                                    </div>
                                </div>

                                <div class="cert-mockup-footer" style="justify-content:center;gap:6px;flex-wrap:wrap;">
                                    <span class="cert-compliance-badge"><i class="fas fa-shield-alt"></i> OSHA Compliant</span>
                                    <span class="cert-compliance-badge"><i class="fas fa-check-circle"></i> Instant Delivery</span>
                                    <span class="cert-compliance-badge"><i class="fas fa-certificate"></i> Nationwide Accepted</span>
                                </div>

                            </div><!-- /.cert-doc-inner -->
                        </div><!-- /.cert-doc-outer -->

                        <div class="cert-info-row mt-3">
                            <div class="cert-info-pill"><i class="fas fa-download"></i> PDF Download</div>
                            <div class="cert-info-pill"><i class="fas fa-bolt"></i> Instant Access</div>
                            <div class="cert-info-pill"><i class="fas fa-globe"></i> 50 States</div>
                        </div>

                    </div><!-- /.cert-box -->
                </div><!-- /.col-lg-6 right -->

            </div><!-- /.row -->
        </div><!-- /.container -->
    </section>

    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// STUDENT REVIEWS
// Auto-scrolling carousel using the .testimonials-section styles already in
// your landing-page.css. Cards duplicate automatically to create a seamless
// infinite loop. Hidden if no reviews are configured.
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_student_reviews( $fields, $post_id ) {

    $section_tag   = $fields['section_tag']   ?? 'Student Feedback';
    $section_title = $fields['section_title'] ?? 'What Our Students Say';
    $reviews       = $fields['reviews']       ?? array();

    // Filter reviews that have at least a name and review text
    $reviews = array_filter( $reviews, function( $r ) {
        return ! empty( trim( $r['name'] ?? '' ) ) && ! empty( trim( $r['review'] ?? '' ) );
    });

    // Auto-hide: don't render if no reviews configured
    if ( empty( $reviews ) ) return;

    // Re-index after filter
    $reviews = array_values( $reviews );

    // Helper: generate initials avatar from name
    // e.g. "Jessica Nelson" -> "JN"
    function clb_get_initials( $name ) {
        $parts = explode( ' ', trim( $name ) );
        $initials = '';
        foreach ( $parts as $part ) {
            $initials .= strtoupper( substr( $part, 0, 1 ) );
            if ( strlen( $initials ) >= 2 ) break;
        }
        return $initials ?: '?';
    }

    // Helper: render star rating HTML
    function clb_render_stars( $rating ) {
        $rating = max( 1, min( 5, (int) $rating ?: 5 ) );
        $stars = '';
        for ( $i = 0; $i < $rating; $i++ ) {
            $stars .= '★';
        }
        return $stars;
    }
    ?>

    <section class="testimonials-section">
        <div class="container">

            <!-- Section heading -->
            <div class="text-center mb-5">
                <div class="sec-tag mx-auto" style="width:fit-content;">
                    <i class="fas fa-star"></i>
                    <?php echo esc_html( $section_tag ); ?>
                </div>
                <h2 class="sec-title mt-3"><?php echo esc_html( $section_title ); ?></h2>
            </div>

        </div>

        <!-- Carousel — constrained to show ~3 cards at a time -->
        <div class="container">
        <div class="testi-carousel clb-reviews-carousel">
            <div class="testi-track">

                <?php
                // Render reviews twice to create seamless infinite loop
                // The CSS animation shifts by -50% which lands back at the start
                for ( $pass = 0; $pass < 2; $pass++ ) :
                    foreach ( $reviews as $review ) :
                        $name    = trim( $review['name']   ?? '' );
                        $role    = trim( $review['role']   ?? '' );
                        $rating  = trim( $review['rating'] ?? '5' );
                        $text    = trim( $review['review'] ?? '' );
                        $initials = clb_get_initials( $name );
                        ?>
                        <div class="testi-card">
                            <div class="testi-quote">&ldquo;</div>
                            <div class="stars"><?php echo clb_render_stars( $rating ); ?></div>
                            <p><?php echo esc_html( $text ); ?></p>
                            <div class="d-flex align-items-center gap-3">
                                <div class="author-av"><?php echo esc_html( $initials ); ?></div>
                                <div>
                                    <div class="author-name"><?php echo esc_html( $name ); ?></div>
                                    <?php if ( $role ) : ?>
                                        <div class="author-role"><?php echo esc_html( $role ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach;
                endfor; ?>

            </div><!-- /.testi-track -->
        </div><!-- /.testi-carousel -->
        </div><!-- /.container -->

    </section>

    <?php
}
