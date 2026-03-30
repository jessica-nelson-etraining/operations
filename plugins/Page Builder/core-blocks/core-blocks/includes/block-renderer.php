<?php
/**
 * block-renderer.php — Core Blocks
 *
 * Frontend HTML output for each block type.
 * Every render function checks for content before outputting anything —
 * empty blocks render nothing.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Render all configured blocks for a page.
 */
function cb_render_blocks( $post_id ) {
    $blocks = cb_get_page_blocks( $post_id );

    // If no blocks configured, show a helpful message for admins only.
    // Visitors see nothing — the page appears blank until blocks are added.
    if ( empty( $blocks ) ) {
        if ( current_user_can( 'manage_options' ) ) {
            echo '<div style="padding:40px;text-align:center;color:#757575;font-family:sans-serif;">';
            echo '<p><strong>No sections added yet.</strong></p>';
            echo '<p>Go to <a href="' . get_edit_post_link( $post_id ) . '">Edit Page</a> and add sections in the 🧩 Page Builder panel.</p>';
            echo '</div>';
        }
        return;
    }

    foreach ( $blocks as $block ) {
        $type   = $block['type']   ?? '';
        $fields = $block['fields'] ?? array();
        cb_render_single_block( $type, $fields );
    }
}

/**
 * Route a block to its render function.
 */
function cb_render_single_block( $type, $fields ) {
    switch ( $type ) {
        case 'rich_text':   cb_render_rich_text( $fields );   break;
        case 'cta_banner':  cb_render_cta_banner( $fields );  break;
        case 'two_column':        cb_render_two_column( $fields );        break;
        case 'comparison_table':  cb_render_comparison_table( $fields ); break;
        case 'testimonials':      cb_render_testimonials( $fields );     break;
        case 'our_guarantee':     cb_render_our_guarantee( $fields );    break;
        // Future blocks go here
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER: background class map
// ═══════════════════════════════════════════════════════════════════════════════

function cb_bg_class( $bg ) {
    $map = array(
        'white'         => 'cb-bg-white',
        'off_white'     => 'cb-bg-off-white',
        'dark'          => 'cb-bg-dark',
        'teal'          => 'cb-bg-teal',
        'teal_gradient' => 'cb-bg-teal-gradient',
    );
    return $map[ $bg ] ?? 'cb-bg-white';
}

// ═══════════════════════════════════════════════════════════════════════════════
// RICH TEXT BLOCK
// Heading + optional subheading + body text.
// Supports light and dark backgrounds with appropriate text colour switching.
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_rich_text( $fields ) {
    $heading    = trim( $fields['heading']    ?? '' );
    $subheading = trim( $fields['subheading'] ?? '' );
    $body       = trim( $fields['body']       ?? '' );
    $alignment  = $fields['alignment']  ?? 'left';
    $background = $fields['background'] ?? 'white';

    // Auto-hide: must have at least a heading or body
    if ( ! $heading && ! $body ) return;

    $is_dark      = in_array( $background, array( 'dark' ) );
    $text_align   = ( $alignment === 'center' ) ? 'text-center' : '';
    $bg_class     = cb_bg_class( $background );
    ?>
    <section class="cb-section cb-rich-text <?php echo esc_attr( $bg_class ); ?>">
        <div class="container">
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
// Full-width conversion section. Headline, supporting text, button, trust pills.
// Multiple background options. Auto-hides if no headline is provided.
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_cta_banner( $fields ) {
    $heading      = trim( $fields['heading']      ?? '' );
    $subtext      = trim( $fields['subtext']      ?? '' );
    $button_text  = trim( $fields['button_text']  ?? 'Get Started' );
    $button_url   = trim( $fields['button_url']   ?? '' );
    $button_style = $fields['button_style'] ?? 'teal';
    $background   = $fields['background']   ?? 'teal_gradient';
    $trust_items  = $fields['trust_items']  ?? array();

    // Auto-hide: must have a heading
    if ( ! $heading ) return;

    $trust_items = array_filter( $trust_items, fn($t) => ! empty( trim( $t['text'] ?? '' ) ) );
    $bg_class    = cb_bg_class( $background );
    $btn_class   = $button_style === 'dark' ? 'cb-btn cb-btn--dark' : 'cb-btn cb-btn--teal';
    $is_dark_bg  = in_array( $background, array( 'dark' ) );
    ?>
    <section class="cb-section cb-cta-banner <?php echo esc_attr( $bg_class ); ?>">
        <div class="container text-center">

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
// Flexible two-column section supporting text + text or text + image.
// Columns stack on mobile. Auto-hides if neither column has a heading or body.
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_two_column( $fields ) {
    $background    = $fields['background']    ?? 'white';
    $column_split  = $fields['column_split']  ?? '6_6';
    $image_side    = $fields['image_side']    ?? 'none';
    $image_url     = trim( $fields['image_url'] ?? '' );

    // Left column
    $left_subheading   = trim( $fields['left_subheading']   ?? '' );
    $left_heading      = trim( $fields['left_heading']      ?? '' );
    $left_body         = trim( $fields['left_body']         ?? '' );
    $left_button_text  = trim( $fields['left_button_text']  ?? '' );
    $left_button_url   = trim( $fields['left_button_url']   ?? '' );

    // Right column
    $right_subheading  = trim( $fields['right_subheading']  ?? '' );
    $right_heading     = trim( $fields['right_heading']     ?? '' );
    $right_body        = trim( $fields['right_body']        ?? '' );
    $right_button_text = trim( $fields['right_button_text'] ?? '' );
    $right_button_url  = trim( $fields['right_button_url']  ?? '' );

    // Auto-hide: need at least one column with content
    $left_has_content  = ( $left_heading  || $left_body  );
    $right_has_content = ( $right_heading || $right_body );
    if ( ! $left_has_content && ! $right_has_content ) return;

    // Column widths
    $splits = array(
        '6_6' => array( 'col-lg-6', 'col-lg-6' ),
        '7_5' => array( 'col-lg-7', 'col-lg-5' ),
        '5_7' => array( 'col-lg-5', 'col-lg-7' ),
    );
    list( $left_col, $right_col ) = $splits[ $column_split ] ?? array( 'col-lg-6', 'col-lg-6' );

    $bg_class   = cb_bg_class( $background );
    $is_dark    = in_array( $background, array( 'dark' ) );
    ?>
    <section class="cb-section cb-two-column <?php echo esc_attr( $bg_class ); ?>">
        <div class="container">
            <div class="row align-items-center g-5">

                <!-- Left column -->
                <div class="<?php echo esc_attr( $left_col ); ?>">
                    <?php if ( $image_side === 'left' && $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>"
                             alt=""
                             class="cb-two-col-image img-fluid" />
                    <?php else : ?>
                        <?php cb_render_text_column( $left_subheading, $left_heading, $left_body, $left_button_text, $left_button_url, $is_dark ); ?>
                    <?php endif; ?>
                </div>

                <!-- Right column -->
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
// Three-column comparison: eTraining vs Generic eLearning vs In-Person Training.
// Pre-filled with standard content. Cell values: "check" = ✅, "x" = ❌,
// or any custom text like "Varies". Column 1 (eTraining) is highlighted in teal.
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_comparison_table( $fields ) {

    $section_tag = trim( $fields['section_tag'] ?? 'Comparison' );
    $heading     = trim( $fields['heading']     ?? 'eTraining vs The Alternatives' );
    $subtext     = trim( $fields['subtext']     ?? 'See how we stack up against in-person training and generic e-learning platforms.' );
    $col1_label  = trim( $fields['col1_label']  ?? 'eTraining' );
    $col2_label  = trim( $fields['col2_label']  ?? 'Generic eLearning' );
    $col3_label  = trim( $fields['col3_label']  ?? 'In-Person Training' );
    $rows        = $fields['rows'] ?? array();

    // Filter rows with no feature name
    $rows = array_filter( $rows, fn($r) => ! empty( trim( $r['feature'] ?? '' ) ) );

    // Use pre-filled defaults if no rows have been configured yet
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

    // Helper: render a cell value
    // "check" → teal checkmark icon, "x" → red x icon, anything else → plain text
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

    <section class="cb-section cb-comparison-table cb-bg-off-white">
        <div class="container">

            <!-- Section heading -->
            <div class="text-center mb-5">
                <?php if ( $section_tag ) : ?>
                    <div class="cb-subheading mx-auto" style="width:fit-content;">
                        <i class="fas fa-table"></i>
                        <?php echo esc_html( $section_tag ); ?>
                    </div>
                <?php endif; ?>
                <h2 class="cb-heading mt-3 mb-3">
                    <?php
                    // Bold the brand name, gradient the "vs The Alternatives" part
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

            <!-- Comparison table -->
            <div class="cb-comparison-wrap">
                <table class="cb-comp-table">
                    <thead>
                        <tr>
                            <!-- Feature column header -->
                            <th class="cb-comp-th cb-comp-th--feature">FEATURE</th>
                            <!-- Column 1 — highlighted as the brand column -->
                            <th class="cb-comp-th cb-comp-th--brand">
                                <?php echo esc_html( strtoupper( $col1_label ) ); ?>
                            </th>
                            <!-- Column 2 -->
                            <th class="cb-comp-th cb-comp-th--alt">
                                <?php echo esc_html( strtoupper( $col2_label ) ); ?>
                            </th>
                            <!-- Column 3 -->
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
// 3-column card grid. Each card has stars, quote, and author info.
// Auto-hides when no testimonial items are configured.
// ═══════════════════════════════════════════════════════════════════════════════

function cb_render_testimonials( $fields ) {
    $section_tag = trim( $fields['section_tag'] ?? 'Student Reviews' );
    $heading     = trim( $fields['heading']     ?? 'What Our Students Say' );
    $background  = $fields['background'] ?? 'off_white';
    $items       = $fields['items']      ?? array();

    // Filter out empty items
    $items = array_values( array_filter( $items, fn($i) => ! empty( trim( $i['quote'] ?? '' ) ) ) );

    if ( empty( $items ) ) return;

    $bg_class = cb_bg_class( $background );
    $is_dark  = ( $background === 'dark' );
    ?>
    <section class="cb-section cb-testimonials <?php echo esc_attr( $bg_class ); ?>">
        <div class="container">

            <!-- Section heading -->
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

            <!-- Cards -->
            <div class="row g-4">
                <?php foreach ( $items as $item ) :
                    $quote     = trim( $item['quote']     ?? '' );
                    $name      = trim( $item['name']      ?? '' );
                    $role      = trim( $item['role']      ?? '' );
                    $photo_url = trim( $item['photo_url'] ?? '' );
                    $stars     = intval( $item['stars']   ?? 5 );
                    if ( ! $quote ) continue;

                    // Build initials for the avatar fallback
                    $initials = '';
                    if ( $name ) {
                        $parts    = explode( ' ', $name );
                        $initials = strtoupper( substr( $parts[0], 0, 1 ) . ( isset( $parts[1] ) ? substr( $parts[1], 0, 1 ) : '' ) );
                    }
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="cb-testimonial-card <?php echo $is_dark ? 'cb-testimonial-card--dark' : ''; ?>">

                            <!-- Stars -->
                            <div class="cb-stars">
                                <?php for ( $s = 1; $s <= 5; $s++ ) : ?>
                                    <i class="fas fa-star <?php echo $s <= $stars ? 'cb-star--filled' : 'cb-star--empty'; ?>"></i>
                                <?php endfor; ?>
                            </div>

                            <!-- Quote -->
                            <blockquote class="cb-testimonial-quote <?php echo $is_dark ? 'cb-testimonial-quote--dark' : ''; ?>">
                                &ldquo;<?php echo esc_html( $quote ); ?>&rdquo;
                            </blockquote>

                            <!-- Author -->
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
// Trust section with a shield badge, heading, body, and checklist.
// Dark navy background recommended for maximum visual impact.
// Auto-hides if no heading is provided.
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
    $background   = $fields['background']         ?? 'dark';

    if ( ! $heading ) return;

    $points   = array_values( array_filter( $points, fn($p) => ! empty( trim( $p['text'] ?? '' ) ) ) );
    $bg_class = cb_bg_class( $background );
    $is_dark  = ( $background === 'dark' );
    ?>
    <section class="cb-section cb-guarantee <?php echo esc_attr( $bg_class ); ?>">
        <div class="container">
            <div class="row align-items-center g-5">

                <!-- Badge column -->
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

                <!-- Content column -->
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
