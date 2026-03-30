<?php
/**
 * block-renderer.php — eTraining Page Builder
 *
 * This file contains PHP functions that generate the actual HTML shown to visitors.
 * Each block type has its own render function below.
 *
 * KEY PRINCIPLE — AUTO HIDE:
 * Every render function checks if it has the content it needs before outputting anything.
 * If a block has no content, the function simply returns without printing anything.
 * This means no blank sections, no broken layouts — the page just skips that section cleanly.
 *
 * SOURCE ATTRIBUTION:
 * - clb_render_* functions originate from course-landing-blocks-v2 plugin
 * - cb_render_* functions originate from core-blocks plugin
 * Function names are intentionally preserved to avoid breaking any CSS class
 * dependencies or internal cross-references.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main entry point: render all configured blocks for a page.
 * Called from the page template file.
 *
 * @param int $post_id  The WordPress page ID
 */
function etpb_render_blocks( $post_id ) {
    $blocks  = etpb_get_page_blocks( $post_id );
    $product = etpb_get_product( $post_id );

    if ( empty( $blocks ) ) {
        if ( current_user_can( 'manage_options' ) ) {
            echo '<div style="padding:40px;text-align:center;color:#757575;font-family:sans-serif;">';
            echo '<p><strong>No sections added yet.</strong></p>';
            echo '<p>Go to <a href="' . get_edit_post_link( $post_id ) . '">Edit Page</a> and add sections in the 🧱 eTraining Page Builder panel.</p>';
            echo '</div>';
        }
        return;
    }

    foreach ( $blocks as $block ) {
        $type   = $block['type']   ?? '';
        $fields = $block['fields'] ?? array();

        // Skip these blocks in the main loop — they render inside other blocks
        // or are settings-only blocks with no frontend output:
        // enrollment_card renders inside the hero's right column.
        // course_versions renders inside the enrollment card.
        // course_outline renders inside the hero below the description.
        // woo_product is a settings-only block with no frontend output.
        if ( $type === 'enrollment_card' || $type === 'course_versions' || $type === 'course_outline' || $type === 'woo_product' ) {
            continue;
        }

        etpb_render_single_block( $type, $fields, $post_id, $product );
    }
}

/**
 * Route a block to its specific render function.
 */
function etpb_render_single_block( $type, $fields, $post_id, $product ) {
    switch ( $type ) {
        // ── Course blocks ──
        case 'trust_bar':        clb_render_trust_bar( $fields );                              break;
        case 'hero':             clb_render_hero( $fields, $post_id, $product );              break;
        case 'enrollment_card':  clb_render_enrollment_card( $fields, $post_id, $product );  break;
        case 'stats_strip':      clb_render_stats_strip( $fields );                           break;
        case 'faq':              clb_render_faq( $fields, $post_id );                         break;
        case 'course_outline':   clb_render_course_outline( $fields, $post_id );              break;
        case 'why_etraining':    clb_render_why_etraining( $fields, $post_id );               break;
        case 'student_reviews':  clb_render_student_reviews( $fields, $post_id );             break;
        case 'course_info_tabs': clb_render_course_info_tabs( $fields, $post_id, $product );  break;
        case 'course_versions':  clb_render_course_versions( $fields, $post_id, $product );   break;

        // ── General blocks ──
        case 'rich_text':        cb_render_rich_text( $fields );        break;
        case 'cta_banner':       cb_render_cta_banner( $fields );       break;
        case 'two_column':       cb_render_two_column( $fields );       break;
        case 'comparison_table': cb_render_comparison_table( $fields ); break;

        // ── Social Proof blocks ──
        case 'testimonials':     cb_render_testimonials( $fields );     break;
        case 'our_guarantee':    cb_render_our_guarantee( $fields );    break;

        // ── New blocks ──
        case 'woo_product':    etpb_render_woo_product( $fields, $post_id );                    break;
        case 'image_block':    etpb_render_image_block( $fields );                              break;
        case 'image_gallery':  etpb_render_image_gallery( $fields );                            break;
        case 'video_embed':    etpb_render_video_embed( $fields );                              break;
        case 'raw_iframe':     etpb_render_raw_iframe( $fields );                               break;
        case 'layout_section': etpb_render_layout_section( $fields, $post_id, $product );       break;
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
// COURSE LANDING BLOCKS — CLB render functions (from course-landing-blocks-v2)
// Prefixes intentionally kept as clb_ to avoid frontend CSS class breakage.
// ═══════════════════════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════════════════════
// TRUST BAR
// The compliance/trust indicator banner at the top of the page
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_trust_bar( $fields ) {
    $items = $fields['items'] ?? array();

    $items = array_filter( $items, function( $item ) {
        return ! empty( trim( $item['text'] ?? '' ) );
    });

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
                            $content = get_post_field( 'post_content', $post_id );
                            echo apply_filters( 'the_content', $content );
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Course Outline button — rendered here in the Hero below the description.
                    $all_blocks = etpb_get_page_blocks( $post_id );
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
                    $all_blocks = etpb_get_page_blocks( $post_id );
                    foreach ( $all_blocks as $block ) {
                        if ( ( $block['type'] ?? '' ) === 'enrollment_card' ) {
                            clb_render_enrollment_card( $block['fields'] ?? array(), $post_id, etpb_get_product( $post_id ) );
                            break;
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
        // Render Course Versions trigger button inside the enrollment card.
        $all_blocks = etpb_get_page_blocks( $post_id );
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
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_stats_strip( $fields ) {
    $items = $fields['items'] ?? array();

    $items = array_filter( $items, function( $item ) {
        return ! empty( trim( $item['number'] ?? '' ) );
    });

    if ( empty( $items ) ) return;

    $bg = etpb_bg( $fields );
    $section_style = $bg['style'] ? ' style="' . esc_attr( $bg['style'] ) . '"' : '';
    $section_class = 'stats-strip' . ( $bg['class'] ? ' ' . esc_attr( $bg['class'] ) : '' );
    ?>
    <div class="<?php echo $section_class; ?>"<?php echo $section_style; ?>>
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">
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
// Auto-pulls FAQs from WordPress — hidden automatically if no FAQs exist
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_faq( $fields, $post_id ) {
    $title  = $fields['title']  ?? 'Frequently Asked Questions';
    $layout = $fields['layout'] ?? 'two_col';

    $slug = get_post_field( 'post_name', $post_id );

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

    if ( empty( $faqs ) ) return;

    $num_cols  = ( $layout === 'two_col' ) ? 2 : 1;
    $col_class = ( $layout === 'two_col' ) ? 'col-12 col-lg-6' : 'col-12';
    $chunks    = array_chunk( $faqs, (int) ceil( count( $faqs ) / $num_cols ) );
    $bg = etpb_bg( $fields );
    ?>
    <section class="hz-faq <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">
            <div class="hz-faq-inner">

                <div class="hz-faq-header">
                    <div class="sec-tag mb-3">
                        <i class="fas fa-question-circle"></i>
                        Common Questions
                    </div>
                    <h2 class="hz-faq-title"><?php echo esc_html( $title ); ?></h2>
                    <p class="hz-faq-subtitle">Everything you need to know before you enroll.</p>
                </div>

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
    (function() {
        document.querySelectorAll('.clb-faq-accordion .hz-faq-question').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var item   = btn.closest('.hz-faq-item');
                var answer = item.querySelector('.hz-faq-answer');
                var isOpen = item.classList.contains('open');

                var accordion = btn.closest('.clb-faq-accordion');
                accordion.querySelectorAll('.hz-faq-item').forEach(function(i) {
                    i.classList.remove('open');
                    i.querySelector('.hz-faq-answer').style.display = 'none';
                    i.querySelector('.hz-faq-question').setAttribute('aria-expanded', 'false');
                });

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
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_course_outline( $fields, $post_id ) {
    $button_text = $fields['button_text'] ?? 'View Full Course Outline';

    // Migration fix
    if ( in_array( $button_text, array(
        'View All Versions &amp; Topics',
        'View All Versions & Topics',
    ) ) ) {
        $button_text = 'View Full Course Outline';
    }

    $outline = get_post_meta( $post_id, 'Course Outline', true );

    if ( empty( trim( $outline ) ) ) return;

    $modal_id = 'clb-outline-modal-' . $post_id;
    ?>

    <button class="clb-outline-btn clb-versions-trigger"
            data-modal="<?php echo esc_attr( $modal_id ); ?>"
            aria-haspopup="dialog"
            aria-controls="<?php echo esc_attr( $modal_id ); ?>">
        <i class="fas fa-file-alt me-2"></i>
        <?php echo esc_html( $button_text ); ?> &rarr;
    </button>

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
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Render ONLY the trigger button for the course versions popup.
 * Called from inside the enrollment card.
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
 * Render the full course versions popup (modal only).
 * Called from the page template footer area so it sits outside all containers.
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

    <div class="modal-overlay" id="<?php echo esc_attr( $modal_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title">
        <div class="modal-box">

            <div class="modal-hd">
                <h2 id="<?php echo esc_attr( $modal_id ); ?>-title">
                    <?php echo esc_html( $modal_title ); ?>
                </h2>
                <p><?php echo esc_html( $modal_subtitle ); ?></p>
                <button class="modal-close clb-modal-close" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="modal-bd">

                <div class="modal-row-label">Available Versions</div>

                <div class="modal-variants-grid">
                    <?php foreach ( $versions as $version ) :
                        $name        = trim( $version['name']        ?? '' );
                        $product_id  = intval( $version['product_id']  ?? 0 );
                        $enroll_url  = trim( $version['enroll_url']  ?? '' );
                        $description = trim( $version['description'] ?? '' );
                        $is_featured = strtolower( trim( $version['is_featured'] ?? '' ) ) === 'yes';

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

    $tabs = array();

    if ( $overview_url ) {
        $tabs['overview'] = array( 'label' => 'Course Overview',      'subtitle' => 'Full walkthrough video',              'icon' => 'video' );
    }
    if ( $preview_url ) {
        $tabs['preview'] = array( 'label' => '5-Min Free Preview',   'subtitle' => 'Try before you buy',                   'icon' => 'play' );
    }
    if ( ! empty( $whats_included ) ) {
        $tabs['whats_included'] = array( 'label' => "What's Included", 'subtitle' => 'Everything in your enrollment',      'icon' => 'checklist' );
    }
    if ( ! empty( $course_details ) ) {
        $tabs['course_details'] = array( 'label' => 'Course Details',  'subtitle' => 'Format, duration & compliance',      'icon' => 'clipboard' );
    }
    if ( ! empty( $who_should ) ) {
        $tabs['who_should'] = array( 'label' => 'Who Should Enroll',   'subtitle' => 'Roles & job types',                  'icon' => 'people' );
    }
    if ( ! empty( $industries ) ) {
        $tabs['industries'] = array( 'label' => 'Industries',          'subtitle' => 'Where this training is required',    'icon' => 'building' );
    }

    if ( empty( $tabs ) ) return;

    $first_tab = array_key_first( $tabs );

    $icons = array(
        'video'     => '<path d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'play'      => '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M10 8l6 4-6 4V8z" fill="currentColor"/>',
        'checklist' => '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'clipboard' => '<path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'people'    => '<path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'building'  => '<path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    );
    $bg = etpb_bg( $fields );
    ?>

    <svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">
        <defs>
            <linearGradient id="brand-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#3fd0c9"/>
                <stop offset="100%" stop-color="#70f07f"/>
            </linearGradient>
        </defs>
    </svg>

    <section class="hz-tabs-section <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">

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

                <nav class="hz-business-features" role="tablist">
                    <?php foreach ( $tabs as $tab_key => $tab ) :
                        $is_active = ( $tab_key === $first_tab );
                        $icon_svg  = $icons[ $tab['icon'] ] ?? $icons['checklist'];
                        ?>
                        <div class="hz-feature <?php echo $is_active ? 'active' : ''; ?>"
                             role="tab"
                             tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
                             aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                             aria-controls="hz-panel-<?php echo esc_attr( $tab_key ); ?>"
                             data-tab="<?php echo esc_attr( $tab_key ); ?>">
                            <div class="hz-feature-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <?php echo $icon_svg; ?>
                                </svg>
                            </div>
                            <div>
                                <h4><?php echo esc_html( $tab['label'] ); ?></h4>
                                <p><?php echo esc_html( $tab['subtitle'] ); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </nav>

                <div class="hz-panel-wrap">

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
 * Called once at the bottom of templates/page.php — after all blocks — so modals
 * sit outside every container and overlay the full page correctly.
 */
function clb_render_page_modals( $post_id ) {
    $blocks  = etpb_get_page_blocks( $post_id );
    $product = etpb_get_product( $post_id );

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
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_why_etraining( $fields, $post_id ) {

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

    $stats = array(
        array( 'number' => '10K+', 'label' => 'Workers Certified' ),
        array( 'number' => '4.9★', 'label' => 'Average Rating'    ),
        array( 'number' => '100%', 'label' => 'OSHA Compliant'    ),
        array( 'number' => '50',   'label' => 'States Accepted'   ),
    );

    $bg = etpb_bg( $fields );
    ?>
    <section class="why-section <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">
            <div class="row align-items-start g-5">

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
// Auto-scrolling carousel using the .testimonials-section styles in landing-page.css
// ═══════════════════════════════════════════════════════════════════════════════

function clb_render_student_reviews( $fields, $post_id ) {

    $section_tag   = $fields['section_tag']   ?? 'Student Feedback';
    $section_title = $fields['section_title'] ?? 'What Our Students Say';
    $reviews       = $fields['reviews']       ?? array();

    $reviews = array_filter( $reviews, function( $r ) {
        return ! empty( trim( $r['name'] ?? '' ) ) && ! empty( trim( $r['review'] ?? '' ) );
    });

    if ( empty( $reviews ) ) return;

    $reviews = array_values( $reviews );
    $bg = etpb_bg( $fields );

    function clb_get_initials( $name ) {
        $parts    = explode( ' ', trim( $name ) );
        $initials = '';
        foreach ( $parts as $part ) {
            $initials .= strtoupper( substr( $part, 0, 1 ) );
            if ( strlen( $initials ) >= 2 ) break;
        }
        return $initials ?: '?';
    }

    function clb_render_stars( $rating ) {
        $rating = max( 1, min( 5, (int) $rating ?: 5 ) );
        $stars  = '';
        for ( $i = 0; $i < $rating; $i++ ) {
            $stars .= '★';
        }
        return $stars;
    }
    ?>

    <section class="testimonials-section <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">

            <div class="text-center mb-5">
                <div class="sec-tag mx-auto" style="width:fit-content;">
                    <i class="fas fa-star"></i>
                    <?php echo esc_html( $section_tag ); ?>
                </div>
                <h2 class="sec-title mt-3"><?php echo esc_html( $section_title ); ?></h2>
            </div>

        </div>

        <div class="container">
        <div class="testi-carousel clb-reviews-carousel">
            <div class="testi-track">

                <?php
                for ( $pass = 0; $pass < 2; $pass++ ) :
                    foreach ( $reviews as $review ) :
                        $name     = trim( $review['name']   ?? '' );
                        $role     = trim( $review['role']   ?? '' );
                        $rating   = trim( $review['rating'] ?? '5' );
                        $text     = trim( $review['review'] ?? '' );
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


// ═══════════════════════════════════════════════════════════════════════════════
// CORE BLOCKS — cb_render_* functions (from core-blocks plugin)
// Prefixes intentionally kept as cb_ to avoid frontend CSS class breakage.
// ═══════════════════════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════════════════════
// RICH TEXT BLOCK
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_rich_text( $fields ) {
    $heading    = trim( $fields['heading']    ?? '' );
    $subheading = trim( $fields['subheading'] ?? '' );
    $body       = trim( $fields['body']       ?? '' );
    $alignment  = $fields['alignment']  ?? 'left';

    if ( ! $heading && ! $body ) return;

    $bg = etpb_bg( $fields );
    $is_dark = ( ( $fields['bg_type'] ?? '' ) === 'preset' && in_array( $fields['bg_preset'] ?? '', array( 'dark' ) ) )
            || ( ( $fields['bg_type'] ?? null ) === null && in_array( $fields['background'] ?? '', array( 'dark' ) ) );
    $text_align = ( $alignment === 'center' ) ? 'text-center' : '';
    ?>
    <section class="cb-section cb-rich-text <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">
            <div class="row justify-content-<?php echo $alignment === 'center' ? 'center' : 'start'; ?>">
                <div class="col-lg-<?php echo $alignment === 'center' ? '8' : '10'; ?> <?php echo esc_attr( $text_align ); ?>">

                    <?php if ( $subheading ) : ?>
                        <div class="cb-subheading <?php echo $is_dark ? 'cb-subheading--dark' : ''; ?>">
                            <?php echo esc_html( $subheading ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $heading ) : ?>
                        <h2 class="cb-heading <?php echo $is_dark ? 'cb-heading--dark' : ''; ?>">
                            <?php echo esc_html( $heading ); ?>
                        </h2>
                    <?php endif; ?>

                    <?php if ( $body ) : ?>
                        <div class="cb-body <?php echo $is_dark ? 'cb-body--dark' : ''; ?>">
                            <?php echo wpautop( esc_html( $body ) ); ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </section>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// CTA BANNER
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_cta_banner( $fields ) {
    $heading      = trim( $fields['heading']      ?? '' );
    $subtext      = trim( $fields['subtext']      ?? '' );
    $button_text  = trim( $fields['button_text']  ?? 'Get Started' );
    $button_url   = trim( $fields['button_url']   ?? '' );
    $button_style = $fields['button_style'] ?? 'teal';
    $trust_items  = $fields['trust_items']  ?? array();

    if ( ! $heading ) return;

    $trust_items = array_filter( $trust_items, fn($t) => ! empty( trim( $t['text'] ?? '' ) ) );
    $bg = etpb_bg( $fields );
    $btn_class  = $button_style === 'dark' ? 'cb-btn cb-btn--dark' : 'cb-btn cb-btn--teal';
    $is_dark_bg = ( ( $fields['bg_type'] ?? '' ) === 'preset' && in_array( $fields['bg_preset'] ?? '', array( 'dark' ) ) )
               || ( ( $fields['bg_type'] ?? null ) === null && in_array( $fields['background'] ?? '', array( 'dark' ) ) );
    ?>
    <section class="cb-section cb-cta-banner <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container text-center etpb-bg-content">

            <h2 class="cb-cta-heading <?php echo $is_dark_bg ? 'cb-heading--dark' : ''; ?>">
                <?php echo esc_html( $heading ); ?>
            </h2>

            <?php if ( $subtext ) : ?>
                <p class="cb-cta-subtext <?php echo $is_dark_bg ? 'cb-body--dark' : ''; ?>">
                    <?php echo esc_html( $subtext ); ?>
                </p>
            <?php endif; ?>

            <?php if ( $button_url ) : ?>
                <a href="<?php echo esc_url( $button_url ); ?>"
                   class="<?php echo esc_attr( $btn_class ); ?>">
                    <?php echo esc_html( $button_text ); ?>
                </a>
            <?php endif; ?>

            <?php if ( ! empty( $trust_items ) ) : ?>
                <div class="cb-trust-row">
                    <?php foreach ( $trust_items as $item ) :
                        $icon = $item['icon'] ?? '';
                        $text = trim( $item['text'] ?? '' );
                        if ( ! $text ) continue;
                        ?>
                        <div class="cb-trust-pill <?php echo $is_dark_bg ? 'cb-trust-pill--dark' : ''; ?>">
                            <?php if ( $icon ) : ?>
                                <i class="<?php echo esc_attr( $icon ); ?>"></i>
                            <?php endif; ?>
                            <?php echo esc_html( $text ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </section>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// TWO COLUMN LAYOUT
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_two_column( $fields ) {
    $column_split = $fields['column_split']  ?? '6_6';
    $image_side   = $fields['image_side']    ?? 'none';
    $image_url    = trim( $fields['image_url'] ?? '' );

    $left_subheading  = trim( $fields['left_subheading']   ?? '' );
    $left_heading     = trim( $fields['left_heading']      ?? '' );
    $left_body        = trim( $fields['left_body']         ?? '' );
    $left_button_text = trim( $fields['left_button_text']  ?? '' );
    $left_button_url  = trim( $fields['left_button_url']   ?? '' );

    $right_subheading  = trim( $fields['right_subheading']  ?? '' );
    $right_heading     = trim( $fields['right_heading']     ?? '' );
    $right_body        = trim( $fields['right_body']        ?? '' );
    $right_button_text = trim( $fields['right_button_text'] ?? '' );
    $right_button_url  = trim( $fields['right_button_url']  ?? '' );

    $left_has_content  = ( $left_heading  || $left_body  );
    $right_has_content = ( $right_heading || $right_body );
    if ( ! $left_has_content && ! $right_has_content ) return;

    $splits = array(
        '6_6' => array( 'col-lg-6', 'col-lg-6' ),
        '7_5' => array( 'col-lg-7', 'col-lg-5' ),
        '5_7' => array( 'col-lg-5', 'col-lg-7' ),
    );
    list( $left_col, $right_col ) = $splits[ $column_split ] ?? array( 'col-lg-6', 'col-lg-6' );

    $bg = etpb_bg( $fields );
    $is_dark = ( ( $fields['bg_type'] ?? '' ) === 'preset' && in_array( $fields['bg_preset'] ?? '', array( 'dark' ) ) )
            || ( ( $fields['bg_type'] ?? null ) === null && in_array( $fields['background'] ?? '', array( 'dark' ) ) );
    ?>
    <section class="cb-section cb-two-column <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">
            <div class="row align-items-center g-5">

                <div class="<?php echo esc_attr( $left_col ); ?>">
                    <?php if ( $image_side === 'left' && $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>"
                             alt=""
                             class="cb-two-col-image img-fluid" />
                    <?php else : ?>
                        <?php cb_render_text_column( $left_subheading, $left_heading, $left_body, $left_button_text, $left_button_url, $is_dark ); ?>
                    <?php endif; ?>
                </div>

                <div class="<?php echo esc_attr( $right_col ); ?>">
                    <?php if ( $image_side === 'right' && $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>"
                             alt=""
                             class="cb-two-col-image img-fluid" />
                    <?php else : ?>
                        <?php cb_render_text_column( $right_subheading, $right_heading, $right_body, $right_button_text, $right_button_url, $is_dark ); ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </section>
    <?php
}

/**
 * Helper: render a text column inside the two-column layout.
 */
function cb_render_text_column( $subheading, $heading, $body, $btn_text, $btn_url, $is_dark ) {
    if ( ! $subheading && ! $heading && ! $body ) return;
    ?>
    <div class="cb-text-column">

        <?php if ( $subheading ) : ?>
            <div class="cb-subheading <?php echo $is_dark ? 'cb-subheading--dark' : ''; ?>">
                <?php echo esc_html( $subheading ); ?>
            </div>
        <?php endif; ?>

        <?php if ( $heading ) : ?>
            <h2 class="cb-heading <?php echo $is_dark ? 'cb-heading--dark' : ''; ?>">
                <?php echo esc_html( $heading ); ?>
            </h2>
        <?php endif; ?>

        <?php if ( $body ) : ?>
            <div class="cb-body <?php echo $is_dark ? 'cb-body--dark' : ''; ?>">
                <?php echo wpautop( esc_html( $body ) ); ?>
            </div>
        <?php endif; ?>

        <?php if ( $btn_text && $btn_url ) : ?>
            <a href="<?php echo esc_url( $btn_url ); ?>"
               class="cb-btn cb-btn--teal mt-3">
                <?php echo esc_html( $btn_text ); ?>
            </a>
        <?php endif; ?>

    </div>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// COMPARISON TABLE
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_comparison_table( $fields ) {

    $section_tag = trim( $fields['section_tag'] ?? 'Comparison' );
    $heading     = trim( $fields['heading']     ?? 'eTraining vs The Alternatives' );
    $subtext     = trim( $fields['subtext']     ?? 'See how we stack up against in-person training and generic e-learning platforms.' );
    $col1_label  = trim( $fields['col1_label']  ?? 'eTraining' );
    $col2_label  = trim( $fields['col2_label']  ?? 'Generic eLearning' );
    $col3_label  = trim( $fields['col3_label']  ?? 'In-Person Training' );
    $rows        = $fields['rows'] ?? array();

    $rows = array_filter( $rows, fn($r) => ! empty( trim( $r['feature'] ?? '' ) ) );

    if ( empty( $rows ) ) {
        $rows = array(
            array( 'feature' => 'OSHA-Compliant Content',    'col1' => 'check', 'col2' => 'check',  'col3' => 'check'  ),
            array( 'feature' => 'Instant Certificate',       'col1' => 'check', 'col2' => 'x',      'col3' => 'x'      ),
            array( 'feature' => 'Mobile Friendly',           'col1' => 'check', 'col2' => 'Varies', 'col3' => 'x'      ),
            array( 'feature' => 'US-Based Support < 2hrs',   'col1' => 'check', 'col2' => 'x',      'col3' => 'Varies' ),
            array( 'feature' => 'Courses from $20',          'col1' => 'check', 'col2' => 'x',      'col3' => 'x'      ),
            array( 'feature' => 'Annual Content Updates',    'col1' => 'check', 'col2' => 'x',      'col3' => 'Varies' ),
            array( 'feature' => 'Team Dashboard',            'col1' => 'check', 'col2' => 'Varies', 'col3' => 'x'      ),
            array( 'feature' => '30-Day Guarantee',          'col1' => 'check', 'col2' => 'x',      'col3' => 'x'      ),
            array( 'feature' => 'Spanish Language Courses',  'col1' => 'check', 'col2' => 'x',      'col3' => 'Varies' ),
        );
    }

    function cb_comparison_cell( $value ) {
        $v = strtolower( trim( $value ) );
        if ( $v === 'check' || $v === 'yes' || $v === '✓' || $v === '✅' ) {
            return '<i class="fas fa-check-circle" style="color:#3fd0c9;font-size:1.1rem;"></i>';
        }
        if ( $v === 'x' || $v === 'no' || $v === '✗' || $v === '❌' ) {
            return '<i class="fas fa-times-circle" style="color:#e53e3e;font-size:1.1rem;"></i>';
        }
        return '<span style="font-size:0.85rem;color:#6c757d;">' . esc_html( $value ) . '</span>';
    }
    ?>

    <?php $bg = etpb_bg( $fields ); ?>
    <section class="cb-section cb-comparison-table <?php echo esc_attr( $bg['class'] ?: 'cb-bg-off-white' ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">

            <div class="text-center mb-5">
                <?php if ( $section_tag ) : ?>
                    <div class="cb-subheading mx-auto" style="width:fit-content;">
                        <i class="fas fa-table"></i>
                        <?php echo esc_html( $section_tag ); ?>
                    </div>
                <?php endif; ?>
                <h2 class="cb-heading mt-3 mb-3">
                    <?php
                    $heading_parts = explode( ' vs ', $heading, 2 );
                    if ( count( $heading_parts ) === 2 ) {
                        echo esc_html( $heading_parts[0] );
                        echo ' <span style="background:linear-gradient(90deg,#3fd0c9 0%,#70f07f 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">vs ' . esc_html( $heading_parts[1] ) . '</span>';
                    } else {
                        echo esc_html( $heading );
                    }
                    ?>
                </h2>
                <?php if ( $subtext ) : ?>
                    <p class="cb-body" style="max-width:560px;margin:0 auto;">
                        <?php echo esc_html( $subtext ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="cb-comparison-wrap">
                <table class="cb-comp-table">
                    <thead>
                        <tr>
                            <th class="cb-comp-th cb-comp-th--feature">FEATURE</th>
                            <th class="cb-comp-th cb-comp-th--brand">
                                <?php echo esc_html( strtoupper( $col1_label ) ); ?>
                            </th>
                            <th class="cb-comp-th cb-comp-th--alt">
                                <?php echo esc_html( strtoupper( $col2_label ) ); ?>
                            </th>
                            <th class="cb-comp-th cb-comp-th--alt">
                                <?php echo esc_html( strtoupper( $col3_label ) ); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) :
                            $feature = trim( $row['feature'] ?? '' );
                            $col1    = trim( $row['col1']    ?? 'check' );
                            $col2    = trim( $row['col2']    ?? 'x' );
                            $col3    = trim( $row['col3']    ?? 'x' );
                            if ( ! $feature ) continue;
                            ?>
                            <tr class="cb-comp-row">
                                <td class="cb-comp-td cb-comp-td--feature">
                                    <?php echo esc_html( $feature ); ?>
                                </td>
                                <td class="cb-comp-td cb-comp-td--brand">
                                    <?php echo cb_comparison_cell( $col1 ); ?>
                                </td>
                                <td class="cb-comp-td">
                                    <?php echo cb_comparison_cell( $col2 ); ?>
                                </td>
                                <td class="cb-comp-td">
                                    <?php echo cb_comparison_cell( $col3 ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </section>

    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// TESTIMONIALS
// 3-column card grid
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_testimonials( $fields ) {
    $section_tag = trim( $fields['section_tag'] ?? 'Student Reviews' );
    $heading     = trim( $fields['heading']     ?? 'What Our Students Say' );
    $items       = $fields['items']      ?? array();

    $items = array_values( array_filter( $items, fn($i) => ! empty( trim( $i['quote'] ?? '' ) ) ) );

    if ( empty( $items ) ) return;

    $bg = etpb_bg( $fields );
    $is_dark = ( ( $fields['bg_type'] ?? '' ) === 'preset' && in_array( $fields['bg_preset'] ?? '', array( 'dark' ) ) )
            || ( ( $fields['bg_type'] ?? null ) === null && in_array( $fields['background'] ?? '', array( 'dark' ) ) );
    ?>
    <section class="cb-section cb-testimonials <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">

            <div class="text-center mb-5">
                <?php if ( $section_tag ) : ?>
                    <div class="cb-subheading mx-auto <?php echo $is_dark ? 'cb-subheading--dark' : ''; ?>"
                         style="width:fit-content;">
                        <i class="fas fa-star"></i>
                        <?php echo esc_html( $section_tag ); ?>
                    </div>
                <?php endif; ?>
                <?php if ( $heading ) : ?>
                    <h2 class="cb-heading mt-3 <?php echo $is_dark ? 'cb-heading--dark' : ''; ?>">
                        <?php echo esc_html( $heading ); ?>
                    </h2>
                <?php endif; ?>
            </div>

            <div class="row g-4">
                <?php foreach ( $items as $item ) :
                    $quote     = trim( $item['quote']     ?? '' );
                    $name      = trim( $item['name']      ?? '' );
                    $role      = trim( $item['role']      ?? '' );
                    $photo_url = trim( $item['photo_url'] ?? '' );
                    $stars     = intval( $item['stars']   ?? 5 );
                    if ( ! $quote ) continue;

                    $initials = '';
                    if ( $name ) {
                        $parts    = explode( ' ', $name );
                        $initials = strtoupper( substr( $parts[0], 0, 1 ) . ( isset( $parts[1] ) ? substr( $parts[1], 0, 1 ) : '' ) );
                    }
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="cb-testimonial-card <?php echo $is_dark ? 'cb-testimonial-card--dark' : ''; ?>">

                            <div class="cb-stars">
                                <?php for ( $s = 1; $s <= 5; $s++ ) : ?>
                                    <i class="fas fa-star <?php echo $s <= $stars ? 'cb-star--filled' : 'cb-star--empty'; ?>"></i>
                                <?php endfor; ?>
                            </div>

                            <blockquote class="cb-testimonial-quote <?php echo $is_dark ? 'cb-testimonial-quote--dark' : ''; ?>">
                                &ldquo;<?php echo esc_html( $quote ); ?>&rdquo;
                            </blockquote>

                            <div class="cb-testimonial-author">
                                <?php if ( $photo_url ) : ?>
                                    <img src="<?php echo esc_url( $photo_url ); ?>"
                                         alt="<?php echo esc_attr( $name ); ?>"
                                         class="cb-testimonial-photo" />
                                <?php elseif ( $initials ) : ?>
                                    <div class="cb-testimonial-initials" aria-hidden="true">
                                        <?php echo esc_html( $initials ); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="cb-testimonial-info">
                                    <?php if ( $name ) : ?>
                                        <div class="cb-testimonial-name <?php echo $is_dark ? 'cb-testimonial-name--dark' : ''; ?>">
                                            <?php echo esc_html( $name ); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ( $role ) : ?>
                                        <div class="cb-testimonial-role <?php echo $is_dark ? 'cb-testimonial-role--dark' : ''; ?>">
                                            <?php echo esc_html( $role ); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════════
// OUR GUARANTEE
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_our_guarantee( $fields ) {
    $section_tag  = trim( $fields['section_tag']  ?? 'Our Promise' );
    $badge_number = trim( $fields['badge_number'] ?? '30' );
    $badge_label  = trim( $fields['badge_label']  ?? 'Day' );
    $heading      = trim( $fields['heading']      ?? '30-Day Money-Back Guarantee' );
    $body         = trim( $fields['body']         ?? '' );
    $points       = $fields['points']             ?? array();
    $button_text  = trim( $fields['button_text']  ?? '' );
    $button_url   = trim( $fields['button_url']   ?? '' );

    if ( ! $heading ) return;

    $points  = array_values( array_filter( $points, fn($p) => ! empty( trim( $p['text'] ?? '' ) ) ) );
    $bg = etpb_bg( $fields );
    $is_dark = ( ( $fields['bg_type'] ?? '' ) === 'preset' && in_array( $fields['bg_preset'] ?? '', array( 'dark' ) ) )
            || ( ( $fields['bg_type'] ?? null ) === null && in_array( $fields['background'] ?? '', array( 'dark' ) ) );
    ?>
    <section class="cb-section cb-guarantee <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">
            <div class="row align-items-center g-5">

                <div class="col-lg-3 text-center">
                    <div class="cb-guarantee-badge <?php echo $is_dark ? 'cb-guarantee-badge--dark' : ''; ?>">
                        <i class="fas fa-shield-alt cb-guarantee-icon"></i>
                        <?php if ( $badge_number ) : ?>
                            <div class="cb-guarantee-number"><?php echo esc_html( $badge_number ); ?></div>
                        <?php endif; ?>
                        <?php if ( $badge_label ) : ?>
                            <div class="cb-guarantee-label"><?php echo esc_html( $badge_label ); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-9">

                    <?php if ( $section_tag ) : ?>
                        <div class="cb-subheading <?php echo $is_dark ? 'cb-subheading--dark' : ''; ?>">
                            <?php echo esc_html( $section_tag ); ?>
                        </div>
                    <?php endif; ?>

                    <h2 class="cb-heading <?php echo $is_dark ? 'cb-heading--dark' : ''; ?>">
                        <?php echo esc_html( $heading ); ?>
                    </h2>

                    <?php if ( $body ) : ?>
                        <div class="cb-body <?php echo $is_dark ? 'cb-body--dark' : ''; ?>">
                            <?php echo wpautop( esc_html( $body ) ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $points ) ) : ?>
                        <ul class="cb-guarantee-list">
                            <?php foreach ( $points as $point ) :
                                $text = trim( $point['text'] ?? '' );
                                if ( ! $text ) continue;
                                ?>
                                <li class="cb-guarantee-point <?php echo $is_dark ? 'cb-guarantee-point--dark' : ''; ?>">
                                    <i class="fas fa-check-circle cb-guarantee-check"></i>
                                    <?php echo esc_html( $text ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ( $button_text && $button_url ) : ?>
                        <a href="<?php echo esc_url( $button_url ); ?>"
                           class="cb-btn cb-btn--teal mt-4">
                            <?php echo esc_html( $button_text ); ?>
                        </a>
                    <?php endif; ?>

                </div>

            </div>
        </div>
    </section>
    <?php
}


// ═══════════════════════════════════════════════════════════════════════════════
// ETPB NEW BLOCKS — etpb_render_* functions
// ═══════════════════════════════════════════════════════════════════════════════

// ── WOO PRODUCT (settings-only — no frontend output) ─────────────────────────

function etpb_render_woo_product( $fields, $post_id ) {
    // This block stores settings only — no frontend HTML output.
    // The Product ID and SLUG are saved to post meta by the save handler.
    return;
}

// ── SINGLE IMAGE ──────────────────────────────────────────────────────────────

function etpb_render_image_block( $fields ) {
    $image_url  = trim( $fields['image_url']  ?? '' );
    $alt_text   = trim( $fields['alt_text']   ?? '' );
    $caption    = trim( $fields['caption']    ?? '' );
    $link_url   = trim( $fields['link_url']   ?? '' );
    $alignment  = $fields['alignment']  ?? 'center';
    $max_width  = trim( $fields['max_width']  ?? '' );

    if ( ! $image_url ) return;

    $bg = etpb_bg( $fields );
    $align_map = array(
        'center' => 'margin-left:auto;margin-right:auto;',
        'left'   => 'margin-right:auto;',
        'right'  => 'margin-left:auto;',
        'full'   => 'width:100%;',
    );
    $style = $max_width ? 'max-width:' . esc_attr( $max_width ) . ';' : '';
    $style .= $align_map[ $alignment ] ?? '';
    ?>
    <section class="cb-section etpb-image-block <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">
            <figure class="etpb-image-figure" style="<?php echo $style; ?>">
                <?php if ( $link_url ) : ?>
                    <a href="<?php echo esc_url( $link_url ); ?>">
                <?php endif; ?>
                    <img src="<?php echo esc_url( $image_url ); ?>"
                         alt="<?php echo esc_attr( $alt_text ); ?>"
                         class="etpb-single-image img-fluid" />
                <?php if ( $link_url ) : ?></a><?php endif; ?>
                <?php if ( $caption ) : ?>
                    <figcaption class="etpb-image-caption"><?php echo esc_html( $caption ); ?></figcaption>
                <?php endif; ?>
            </figure>
        </div>
    </section>
    <?php
}

// ── IMAGE GALLERY ─────────────────────────────────────────────────────────────

function etpb_render_image_gallery( $fields ) {
    $heading  = trim( $fields['section_heading'] ?? '' );
    $columns  = intval( $fields['columns'] ?? 3 );
    $images   = $fields['images'] ?? array();
    $images   = array_values( array_filter( $images, fn($i) => ! empty( trim( $i['url'] ?? '' ) ) ) );

    if ( empty( $images ) ) return;

    $bg        = etpb_bg( $fields );
    $col_class = 'col-lg-' . round( 12 / $columns ) . ' col-md-6';
    ?>
    <section class="cb-section etpb-image-gallery <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">
            <?php if ( $heading ) : ?>
                <h2 class="cb-heading text-center mb-5"><?php echo esc_html( $heading ); ?></h2>
            <?php endif; ?>
            <div class="row g-3">
                <?php foreach ( $images as $img ) :
                    $url  = trim( $img['url']     ?? '' );
                    $alt  = trim( $img['alt']     ?? '' );
                    $cap  = trim( $img['caption'] ?? '' );
                    $link = trim( $img['link']    ?? '' );
                    if ( ! $url ) continue;
                    ?>
                    <div class="<?php echo esc_attr( $col_class ); ?>">
                        <figure class="etpb-gallery-item">
                            <?php if ( $link ) : ?><a href="<?php echo esc_url( $link ); ?>"><?php endif; ?>
                            <img src="<?php echo esc_url( $url ); ?>"
                                 alt="<?php echo esc_attr( $alt ); ?>"
                                 class="etpb-gallery-img img-fluid" />
                            <?php if ( $link ) : ?></a><?php endif; ?>
                            <?php if ( $cap ) : ?>
                                <figcaption class="etpb-image-caption"><?php echo esc_html( $cap ); ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

// ── VIDEO EMBED ───────────────────────────────────────────────────────────────

function etpb_render_video_embed( $fields ) {
    $video_url = trim( $fields['video_url']        ?? '' );
    $heading   = trim( $fields['section_heading']  ?? '' );
    $caption   = trim( $fields['caption']          ?? '' );
    $max_width = trim( $fields['max_width']         ?? '' );

    if ( ! $video_url ) return;

    // Build embed URL from plain video URL
    $embed_url = '';
    if ( strpos( $video_url, 'vimeo.com' ) !== false ) {
        preg_match( '/vimeo\.com\/(\d+)/', $video_url, $m );
        if ( ! empty( $m[1] ) ) {
            $embed_url = 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
        } elseif ( strpos( $video_url, 'player.vimeo.com' ) !== false ) {
            $embed_url = $video_url; // Already an embed URL
        }
    } elseif ( strpos( $video_url, 'youtube.com' ) !== false || strpos( $video_url, 'youtu.be' ) !== false ) {
        preg_match( '/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $video_url, $m );
        if ( ! empty( $m[1] ) ) {
            $embed_url = 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
    }

    if ( ! $embed_url ) return;

    $bg_resolved = etpb_bg( $fields );
    $width_style = $max_width ? 'max-width:' . esc_attr( $max_width ) . ';margin:0 auto;' : '';
    ?>
    <section class="cb-section etpb-video-embed <?php echo esc_attr( $bg_resolved['class'] ); ?>" style="<?php echo esc_attr( $bg_resolved['style'] ); ?>">
        <?php echo $bg_resolved['overlay']; ?>
        <div class="container etpb-bg-content">
            <?php if ( $heading ) : ?>
                <h2 class="cb-heading text-center mb-4"><?php echo esc_html( $heading ); ?></h2>
            <?php endif; ?>
            <div class="etpb-video-wrap" style="<?php echo $width_style; ?>">
                <div class="etpb-video-responsive">
                    <iframe src="<?php echo esc_url( $embed_url ); ?>"
                            frameborder="0"
                            allow="autoplay; fullscreen; picture-in-picture"
                            allowfullscreen
                            loading="lazy"
                            title="<?php echo esc_attr( $heading ?: 'Video' ); ?>"></iframe>
                </div>
            </div>
            <?php if ( $caption ) : ?>
                <p class="etpb-video-caption text-center mt-3"><?php echo esc_html( $caption ); ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

// ── RAW IFRAME / EMBED ────────────────────────────────────────────────────────

function etpb_render_raw_iframe( $fields ) {
    $embed_code = trim( $fields['embed_code']      ?? '' );
    $height     = trim( $fields['height']          ?? '500px' );
    $heading    = trim( $fields['section_heading'] ?? '' );

    if ( ! $embed_code ) return;

    $bg_resolved = etpb_bg( $fields );

    // If it's a raw URL (not an <iframe> tag), wrap it
    $is_iframe_tag = ( strpos( $embed_code, '<iframe' ) !== false );
    if ( ! $is_iframe_tag && filter_var( $embed_code, FILTER_VALIDATE_URL ) ) {
        $output = '<iframe src="' . esc_url( $embed_code ) . '" width="100%" height="' . esc_attr( $height ) . '" frameborder="0" loading="lazy"></iframe>';
    } else {
        // Sanitise the iframe tag — allow only safe iframe attributes
        $allowed = array( 'iframe' => array(
            'src' => true, 'width' => true, 'height' => true,
            'frameborder' => true, 'allowfullscreen' => true,
            'loading' => true, 'title' => true, 'allow' => true,
            'style' => true, 'class' => true,
        ));
        $output = wp_kses( $embed_code, $allowed );
    }
    ?>
    <section class="cb-section etpb-raw-iframe <?php echo esc_attr( $bg_resolved['class'] ); ?>" style="<?php echo esc_attr( $bg_resolved['style'] ); ?>">
        <?php echo $bg_resolved['overlay']; ?>
        <div class="container etpb-bg-content">
            <?php if ( $heading ) : ?>
                <h2 class="cb-heading text-center mb-4"><?php echo esc_html( $heading ); ?></h2>
            <?php endif; ?>
            <div class="etpb-iframe-wrap" style="width:100%;height:<?php echo esc_attr( $height ); ?>;">
                <?php echo $output; ?>
            </div>
        </div>
    </section>
    <?php
}

// ── LAYOUT SECTION ────────────────────────────────────────────────────────────

function etpb_render_layout_section( $fields, $post_id, $product ) {
    $column_layout = $fields['column_layout'] ?? '6_6';
    $gap           = $fields['gap']           ?? 'g-4';
    $columns_data  = $fields['columns']       ?? array();

    if ( empty( $columns_data ) ) return;

    // Map column_layout to Bootstrap column classes
    $col_maps = array(
        '6_6'     => array( 'col-lg-6', 'col-lg-6' ),
        '7_5'     => array( 'col-lg-7', 'col-lg-5' ),
        '5_7'     => array( 'col-lg-5', 'col-lg-7' ),
        '8_4'     => array( 'col-lg-8', 'col-lg-4' ),
        '4_8'     => array( 'col-lg-4', 'col-lg-8' ),
        '4_4_4'   => array( 'col-lg-4', 'col-lg-4', 'col-lg-4' ),
        '3_3_3_3' => array( 'col-lg-3', 'col-lg-3', 'col-lg-3', 'col-lg-3' ),
    );
    $col_classes = $col_maps[ $column_layout ] ?? array( 'col-lg-6', 'col-lg-6' );
    $bg = etpb_bg( $fields );
    ?>
    <section class="cb-section etpb-layout-section <?php echo esc_attr( $bg['class'] ); ?>" style="<?php echo esc_attr( $bg['style'] ); ?>">
        <?php echo $bg['overlay']; ?>
        <div class="container etpb-bg-content">
            <div class="row <?php echo esc_attr( $gap ); ?> align-items-start">
                <?php foreach ( $columns_data as $col_index => $column ) :
                    $col_class   = $col_classes[ $col_index ] ?? 'col-lg-6';
                    $col_blocks  = $column['blocks'] ?? array();
                    ?>
                    <div class="<?php echo esc_attr( $col_class ); ?> etpb-layout-col">
                        <?php foreach ( $col_blocks as $nested_block ) :
                            $n_type   = $nested_block['type']   ?? '';
                            $n_fields = $nested_block['fields'] ?? array();
                            if ( $n_type ) {
                                etpb_render_single_block( $n_type, $n_fields, $post_id, $product );
                            }
                        endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}
