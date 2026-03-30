<?php
/**
 * helpers.php — Core Blocks
 *
 * Block definitions and utility functions.
 * To add a new block type: add an entry here and a render function in block-renderer.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Returns all available Core Block types and their field definitions.
 */
function cb_get_block_definitions() {
    return array(

        // ── RICH TEXT ─────────────────────────────────────────────────────────
        // A clean heading + body text block. No code needed.
        // Supports dark and light backgrounds.
        'rich_text' => array(
            'label'       => 'Rich Text',
            'description' => 'A heading with body text below it. Choose a light or dark background. Good for introductions, descriptions, and general content.',
            'icon'        => '📝',
            'category'    => 'General',
            'fields'      => array(
                'heading' => array(
                    'type'        => 'text',
                    'label'       => 'Heading',
                    'description' => 'Main heading for this section.',
                    'default'     => '',
                ),
                'subheading' => array(
                    'type'        => 'text',
                    'label'       => 'Subheading (optional)',
                    'description' => 'Smaller text shown above the heading, like a section label.',
                    'default'     => '',
                ),
                'body' => array(
                    'type'        => 'textarea',
                    'label'       => 'Body Text',
                    'description' => 'Main paragraph content. Keep it concise.',
                    'default'     => '',
                ),
                'alignment' => array(
                    'type'    => 'select',
                    'label'   => 'Text Alignment',
                    'options' => array(
                        'left'   => 'Left',
                        'center' => 'Center',
                    ),
                    'default' => 'left',
                ),
                'background' => array(
                    'type'    => 'select',
                    'label'   => 'Background',
                    'options' => array(
                        'white'     => 'White',
                        'off_white' => 'Off White / Light Grey',
                        'dark'      => 'Dark Navy',
                        'teal'      => 'Teal Gradient',
                    ),
                    'default' => 'white',
                ),
            ),
        ),

        // ── CTA BANNER ────────────────────────────────────────────────────────
        // A full-width call-to-action section with headline, supporting text,
        // and a configurable button. Great for conversion points on any page.
        'cta_banner' => array(
            'label'       => 'CTA Banner',
            'description' => 'A full-width call-to-action section with a headline, supporting text, and a button. Use it to drive sign-ups, enrollments, or any key action.',
            'icon'        => '📢',
            'category'    => 'General',
            'fields'      => array(
                'heading' => array(
                    'type'    => 'text',
                    'label'   => 'Headline',
                    'default' => '',
                ),
                'subtext' => array(
                    'type'        => 'textarea',
                    'label'       => 'Supporting Text',
                    'description' => 'One or two sentences supporting the headline.',
                    'default'     => '',
                ),
                'button_text' => array(
                    'type'    => 'text',
                    'label'   => 'Button Text',
                    'default' => 'Get Started',
                ),
                'button_url' => array(
                    'type'        => 'text',
                    'label'       => 'Button URL',
                    'description' => 'The full URL the button links to. Example: /shop/course-name/',
                    'default'     => '',
                ),
                'button_style' => array(
                    'type'    => 'select',
                    'label'   => 'Button Style',
                    'options' => array(
                        'teal'  => 'Teal Gradient (primary)',
                        'dark'  => 'Dark Gradient (secondary)',
                    ),
                    'default' => 'teal',
                ),
                'background' => array(
                    'type'    => 'select',
                    'label'   => 'Background',
                    'options' => array(
                        'teal_gradient' => 'Teal Gradient (light)',
                        'dark'          => 'Dark Navy',
                        'white'         => 'White',
                    ),
                    'default' => 'teal_gradient',
                ),
                'trust_items' => array(
                    'type'        => 'repeater',
                    'label'       => 'Trust Pills (optional)',
                    'description' => 'Small trust indicators shown below the button. Example: "No Hidden Fees", "Instant Access". Leave empty to hide.',
                    'sub_fields'  => array(
                        'icon' => array( 'type' => 'icon_picker', 'label' => 'Icon' ),
                        'text' => array( 'type' => 'text',        'label' => 'Text' ),
                    ),
                ),
            ),
        ),

        // ── TWO COLUMN LAYOUT ─────────────────────────────────────────────────
        // Flexible two-column section. Left and right columns each support
        // a heading, text, optional button, and optional image.
        'two_column' => array(
            'label'       => 'Two-Column Layout',
            'description' => 'Two side-by-side columns. Each column can have a heading, text, an optional button, and an optional image URL. Columns stack on mobile.',
            'icon'        => '⬛⬛',
            'category'    => 'General',
            'fields'      => array(

                'background' => array(
                    'type'    => 'select',
                    'label'   => 'Background',
                    'options' => array(
                        'white'     => 'White',
                        'off_white' => 'Off White / Light Grey',
                        'dark'      => 'Dark Navy',
                    ),
                    'default' => 'white',
                ),

                'column_split' => array(
                    'type'        => 'select',
                    'label'       => 'Column Split',
                    'description' => 'How wide each column is. 50/50 is equal. 60/40 gives more space to the left.',
                    'options'     => array(
                        '6_6' => '50% / 50% — Equal',
                        '7_5' => '60% / 40% — More on left',
                        '5_7' => '40% / 60% — More on right',
                    ),
                    'default' => '6_6',
                ),

                'image_side' => array(
                    'type'        => 'select',
                    'label'       => 'If using an image, which side?',
                    'description' => 'Choose which column shows an image. The other column shows text.',
                    'options'     => array(
                        'none'  => 'No image — both columns are text',
                        'left'  => 'Image on the left',
                        'right' => 'Image on the right',
                    ),
                    'default' => 'none',
                ),

                'image_url' => array(
                    'type'        => 'text',
                    'label'       => 'Image URL',
                    'description' => 'Paste the full URL of an image already uploaded to your Media Library. Leave blank if not using an image.',
                    'default'     => '',
                ),

                // Left column fields
                'left_subheading' => array(
                    'type'    => 'text',
                    'label'   => 'Left Column — Subheading (optional)',
                    'default' => '',
                ),
                'left_heading' => array(
                    'type'    => 'text',
                    'label'   => 'Left Column — Heading',
                    'default' => '',
                ),
                'left_body' => array(
                    'type'    => 'textarea',
                    'label'   => 'Left Column — Body Text',
                    'default' => '',
                ),
                'left_button_text' => array(
                    'type'    => 'text',
                    'label'   => 'Left Column — Button Text (optional)',
                    'default' => '',
                ),
                'left_button_url' => array(
                    'type'    => 'text',
                    'label'   => 'Left Column — Button URL',
                    'default' => '',
                ),

                // Right column fields
                'right_subheading' => array(
                    'type'    => 'text',
                    'label'   => 'Right Column — Subheading (optional)',
                    'default' => '',
                ),
                'right_heading' => array(
                    'type'    => 'text',
                    'label'   => 'Right Column — Heading',
                    'default' => '',
                ),
                'right_body' => array(
                    'type'    => 'textarea',
                    'label'   => 'Right Column — Body Text',
                    'default' => '',
                ),
                'right_button_text' => array(
                    'type'    => 'text',
                    'label'   => 'Right Column — Button Text (optional)',
                    'default' => '',
                ),
                'right_button_url' => array(
                    'type'    => 'text',
                    'label'   => 'Right Column — Button URL',
                    'default' => '',
                ),
            ),
        ),

        // ── COMPARISON TABLE ─────────────────────────────────────────────────────
        // Three-column comparison table: eTraining vs Generic eLearning vs In-Person.
        // Pre-filled with standard content but fully editable.
        // Each row cell can be: "check" (✅), "x" (❌), or any custom text like "Varies".
        'comparison_table' => array(
            'label'       => 'Comparison Table',
            'description' => 'A three-column comparison table showing eTraining vs the alternatives. Pre-filled with standard content — edit rows as needed.',
            'icon'        => '📊',
            'category'    => 'General',
            'fields'      => array(
                'section_tag' => array(
                    'type'    => 'text',
                    'label'   => 'Section Tag',
                    'default' => 'Comparison',
                ),
                'heading' => array(
                    'type'    => 'text',
                    'label'   => 'Heading',
                    'default' => 'eTraining vs The Alternatives',
                ),
                'subtext' => array(
                    'type'    => 'text',
                    'label'   => 'Subtext',
                    'default' => 'See how we stack up against in-person training and generic e-learning platforms.',
                ),
                'col1_label' => array(
                    'type'    => 'text',
                    'label'   => 'Column 1 Label (your brand — highlighted)',
                    'default' => 'eTraining',
                ),
                'col2_label' => array(
                    'type'    => 'text',
                    'label'   => 'Column 2 Label',
                    'default' => 'Generic eLearning',
                ),
                'col3_label' => array(
                    'type'    => 'text',
                    'label'   => 'Column 3 Label',
                    'default' => 'In-Person Training',
                ),
                'rows' => array(
                    'type'        => 'repeater',
                    'label'       => 'Comparison Rows',
                    'description' => 'Each row has a feature name and three values. For values use: "check" for ✅, "x" for ❌, or type any text like "Varies".',
                    'sub_fields'  => array(
                        'feature' => array( 'type' => 'text', 'label' => 'Feature Name' ),
                        'col1'    => array( 'type' => 'text', 'label' => 'eTraining value (check / x / text)' ),
                        'col2'    => array( 'type' => 'text', 'label' => 'Generic eLearning value (check / x / text)' ),
                        'col3'    => array( 'type' => 'text', 'label' => 'In-Person value (check / x / text)' ),
                    ),
                ),
            ),
        ),

        // ── TESTIMONIALS ──────────────────────────────────────────────────────────
        // Grid of student review cards. Each card shows a star rating, quote,
        // and the reviewer's name, role, and optional photo.
        'testimonials' => array(
            'label'       => 'Student Reviews',
            'description' => 'A grid of student testimonials. Displays 3 per row on desktop, stacks on mobile. Add as many as you like.',
            'icon'        => '⭐',
            'category'    => 'Social Proof',
            'fields'      => array(
                'section_tag' => array(
                    'type'    => 'text',
                    'label'   => 'Section Tag',
                    'default' => 'Student Reviews',
                ),
                'heading' => array(
                    'type'    => 'text',
                    'label'   => 'Heading',
                    'default' => 'What Our Students Say',
                ),
                'background' => array(
                    'type'    => 'select',
                    'label'   => 'Background',
                    'options' => array(
                        'white'     => 'White',
                        'off_white' => 'Off White / Light Grey',
                        'dark'      => 'Dark Navy',
                    ),
                    'default' => 'off_white',
                ),
                'items' => array(
                    'type'        => 'repeater',
                    'label'       => 'Testimonials',
                    'description' => 'Each card shows a quote, name, optional role/company, optional photo, and star rating.',
                    'sub_fields'  => array(
                        'quote'     => array( 'type' => 'textarea', 'label' => 'Quote' ),
                        'name'      => array( 'type' => 'text',     'label' => 'Student Name' ),
                        'role'      => array( 'type' => 'text',     'label' => 'Job Title / Company (optional)' ),
                        'photo_url' => array( 'type' => 'text',     'label' => 'Photo URL (optional — leave blank for initials)' ),
                        'stars'     => array(
                            'type'    => 'select',
                            'label'   => 'Star Rating',
                            'options' => array(
                                '5' => '★★★★★  5 Stars',
                                '4' => '★★★★☆  4 Stars',
                                '3' => '★★★☆☆  3 Stars',
                            ),
                            'default' => '5',
                        ),
                    ),
                ),
            ),
        ),

        // ── OUR GUARANTEE ─────────────────────────────────────────────────────────
        // Trust section with a prominent badge, guarantee heading, body text,
        // and a checklist of specific guarantee points.
        // Pre-filled with 30-day money-back content but fully editable.
        'our_guarantee' => array(
            'label'       => 'Our Guarantee',
            'description' => 'A trust-building section with a guarantee badge, heading, explanation, and checklist. Pre-filled with 30-day money-back content.',
            'icon'        => '🛡️',
            'category'    => 'Social Proof',
            'fields'      => array(
                'section_tag' => array(
                    'type'    => 'text',
                    'label'   => 'Section Tag',
                    'default' => 'Our Promise',
                ),
                'badge_number' => array(
                    'type'        => 'text',
                    'label'       => 'Badge Number',
                    'description' => 'Large number shown inside the shield badge (e.g. 30 for "30-Day").',
                    'default'     => '30',
                ),
                'badge_label' => array(
                    'type'    => 'text',
                    'label'   => 'Badge Label',
                    'default' => 'Day',
                ),
                'heading' => array(
                    'type'    => 'text',
                    'label'   => 'Heading',
                    'default' => '30-Day Money-Back Guarantee',
                ),
                'body' => array(
                    'type'    => 'textarea',
                    'label'   => 'Body Text',
                    'default' => "We're confident you'll love your course. If you're not completely satisfied within 30 days of purchase, contact our support team and we'll issue a full refund — no questions asked.",
                ),
                'points' => array(
                    'type'        => 'repeater',
                    'label'       => 'Guarantee Points',
                    'description' => 'Checklist items shown below the body text. Leave empty to hide.',
                    'sub_fields'  => array(
                        'text' => array( 'type' => 'text', 'label' => 'Guarantee Point' ),
                    ),
                ),
                'button_text' => array(
                    'type'    => 'text',
                    'label'   => 'Button Text (optional)',
                    'default' => '',
                ),
                'button_url' => array(
                    'type'    => 'text',
                    'label'   => 'Button URL',
                    'default' => '',
                ),
                'background' => array(
                    'type'    => 'select',
                    'label'   => 'Background',
                    'options' => array(
                        'dark'      => 'Dark Navy (recommended)',
                        'off_white' => 'Off White',
                        'white'     => 'White',
                    ),
                    'default' => 'dark',
                ),
            ),
        ),

    ); // end return
}

/**
 * Get the saved block configuration for a specific page.
 */
function cb_get_page_blocks( $post_id ) {
    $config  = get_post_meta( $post_id, 'cb_blocks_config', true );
    if ( empty( $config ) ) return array();
    $decoded = json_decode( $config, true );
    return is_array( $decoded ) ? $decoded : array();
}

/**
 * Available icons for icon picker fields.
 */
function cb_get_available_icons() {
    return array(
        ''                       => '— No icon —',
        'fas fa-check-circle'    => '✓  Check Circle',
        'fas fa-shield-alt'      => '🛡  Shield / Protected',
        'fas fa-bolt'            => '⚡  Bolt / Fast',
        'fas fa-certificate'     => '🏆  Certificate',
        'fas fa-laptop'          => '💻  Laptop / Online',
        'fas fa-clock'           => '🕐  Clock / Time',
        'fas fa-mobile-alt'      => '📱  Mobile Friendly',
        'fas fa-star'            => '⭐  Star',
        'fas fa-graduation-cap'  => '🎓  Graduation Cap',
        'fas fa-lock'            => '🔒  Lock / Secure',
        'fas fa-award'           => '🥇  Award',
        'fas fa-users'           => '👥  Users / Group',
        'fas fa-envelope'        => '✉️  Email',
        'fas fa-phone'           => '📞  Phone',
        'fas fa-map-marker-alt'  => '📍  Location',
        'fas fa-dollar-sign'     => '💲  Dollar / Price',
        'fas fa-percentage'      => '%  Percentage / Discount',
        'fas fa-file-alt'        => '📄  Document',
        'fas fa-arrow-right'     => '→  Arrow Right',
    );
}
