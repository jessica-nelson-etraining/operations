<?php
/**
 * helpers.php — eTraining Page Builder
 *
 * Contains:
 *  1. The master list of all available block types (Course, General, and Social Proof)
 *  2. Small utility functions used throughout the plugin
 *
 * To add a new block type, add an entry to etpb_get_block_definitions()
 * and a corresponding render function in block-renderer.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Returns the complete list of block types available in the editor.
 *
 * Each block definition contains:
 *  - label:       Human-readable name shown in the editor
 *  - description: Short explanation of what the block does
 *  - icon:        Emoji icon shown in the editor for easy identification
 *  - category:    One of: Course | General | Social Proof
 *  - fields:      Array of configurable fields for this block type
 *
 * Field types supported:
 *  - text:        Single-line text input
 *  - textarea:    Multi-line text input
 *  - select:      Dropdown with predefined options
 *  - toggle:      On/off checkbox
 *  - icon_picker: Dropdown of available Font Awesome icons
 *  - repeater:    A list of items, each with their own sub-fields
 */
function etpb_get_block_definitions() {
    $definitions = array(

        // ════════════════════════════════════════════════════════════════
        // COURSE BLOCKS — specific to course landing pages
        // ════════════════════════════════════════════════════════════════

        // ── WOO PRODUCT ───────────────────────────────────────────────────────
        'woo_product' => array(
            'label'       => 'WooCommerce Product',
            'description' => 'Connect this page to a WooCommerce product. Required for the Enrollment Card to show live pricing and the correct enroll link. Only needed on course pages.',
            'icon'        => '🔗',
            'category'    => 'Course',
            'fields'      => array(
                'product_id' => array(
                    'type'        => 'text',
                    'label'       => 'Product ID',
                    'description' => 'The WooCommerce product number. Find it by editing the product — it appears in the browser URL as post=123.',
                    'default'     => '',
                ),
                'product_slug' => array(
                    'type'        => 'text',
                    'label'       => 'Product SLUG',
                    'description' => 'The URL-friendly product name. Find it in WooCommerce → Product data → Advanced → Slug. Example: hazwoper-9-hour-refresher',
                    'default'     => '',
                ),
            ),
        ),

        // ── TRUST BAR ─────────────────────────────────────────────────────────
        'trust_bar' => array(
            'label'       => 'Trust Bar',
            'description' => 'A top banner showing trust indicators like compliance badges or statistics.',
            'icon'        => '✅',
            'category'    => 'Course',
            'fields'      => array(
                'items' => array(
                    'type'        => 'repeater',
                    'label'       => 'Trust Items',
                    'description' => 'Each item appears as a small badge. Example: "OSHA 29 CFR Compliant" or "50,000+ Certified".',
                    'sub_fields'  => array(
                        'icon' => array( 'type' => 'icon_picker', 'label' => 'Icon' ),
                        'text' => array( 'type' => 'text',        'label' => 'Text' ),
                    ),
                ),
            ),
        ),

        // ── HERO SECTION ──────────────────────────────────────────────────────
        'hero' => array(
            'label'       => 'Hero Section',
            'description' => 'The main headline area at the top of the page. Course title comes from the page title automatically.',
            'icon'        => '🦸',
            'category'    => 'Course',
            'fields'      => array(
                'eyebrow' => array(
                    'type'        => 'text',
                    'label'       => 'Eyebrow Text',
                    'description' => 'Small label shown above the main headline. Example: "OSHA Annual Requirement".',
                    'default'     => '',
                ),
                'eyebrow_icon' => array(
                    'type'    => 'icon_picker',
                    'label'   => 'Eyebrow Icon',
                    'default' => '',
                ),
                'subtitle' => array(
                    'type'        => 'textarea',
                    'label'       => 'Subtitle',
                    'description' => 'Supporting text shown below the headline. Keep it to 1–2 sentences.',
                    'default'     => '',
                ),
                'badges' => array(
                    'type'        => 'repeater',
                    'label'       => 'Feature Badge Pills',
                    'description' => 'Short highlights shown as pills. Examples: "100% Online", "Self-Paced", "Certificate Included".',
                    'sub_fields'  => array(
                        'icon' => array( 'type' => 'icon_picker', 'label' => 'Icon' ),
                        'text' => array( 'type' => 'text',        'label' => 'Text' ),
                    ),
                ),
                'show_description' => array(
                    'type'        => 'toggle',
                    'label'       => 'Show Course Description',
                    'description' => 'When enabled, displays the course description from the WordPress page editor.',
                    'default'     => true,
                ),
            ),
        ),

        // ── ENROLLMENT CARD ───────────────────────────────────────────────────
        'enrollment_card' => array(
            'label'       => 'Enrollment Card',
            'description' => 'The purchase/enrollment box. Price and enroll button link connect to WooCommerce automatically.',
            'icon'        => '🛒',
            'category'    => 'Course',
            'fields'      => array(
                'price_source' => array(
                    'type'        => 'select',
                    'label'       => 'Where should the price come from?',
                    'description' => 'WooCommerce (automatic) is recommended — it always stays current.',
                    'options'     => array(
                        'woocommerce' => 'WooCommerce — pull automatically',
                        'custom'      => 'Custom — I will type it in',
                    ),
                    'default'     => 'woocommerce',
                ),
                'custom_price' => array(
                    'type'        => 'text',
                    'label'       => 'Custom Price',
                    'description' => 'Only used if you selected "Custom" above. Example: 39.95',
                    'default'     => '',
                ),
                'price_subtitle' => array(
                    'type'        => 'text',
                    'label'       => 'Price Subtitle',
                    'description' => 'Small text below the price. Example: "Per person • No subscription required".',
                    'default'     => 'Per person &bull; No subscription required',
                ),
                'button_text' => array(
                    'type'    => 'text',
                    'label'   => 'Enroll Button Text',
                    'default' => 'Enroll Now',
                ),
                'button_url_source' => array(
                    'type'        => 'select',
                    'label'       => 'Where should the enroll button link go?',
                    'description' => '"Product SLUG" is recommended — it connects to the right WooCommerce product automatically.',
                    'options'     => array(
                        'product_slug' => 'Product SLUG field — connect automatically',
                        'custom'       => 'Custom URL — I will type it in',
                    ),
                    'default'     => 'product_slug',
                ),
                'custom_button_url' => array(
                    'type'        => 'text',
                    'label'       => 'Custom Button URL',
                    'description' => 'Only used if you selected "Custom URL" above.',
                    'default'     => '',
                ),
                'topics_label' => array(
                    'type'    => 'text',
                    'label'   => 'Topics Section Heading',
                    'default' => 'Topics Covered',
                ),
                'topics' => array(
                    'type'        => 'repeater',
                    'label'       => 'Topics List',
                    'description' => 'Each topic appears as a bullet point in the enrollment card.',
                    'sub_fields'  => array(
                        'topic' => array( 'type' => 'text', 'label' => 'Topic' ),
                    ),
                ),
                'guarantee_text' => array(
                    'type'        => 'text',
                    'label'       => 'Guarantee Text',
                    'description' => 'Small reassurance line at the bottom of the card. Leave blank to hide.',
                    'default'     => '30-Day Satisfaction Guarantee &bull; No Hidden Fees',
                ),
            ),
        ),

        // ── STATS STRIP ───────────────────────────────────────────────────────
        'stats_strip' => array(
            'label'       => 'Stats Strip',
            'description' => 'A band of highlighted numbers. Example: "50K+ Workers Certified", "4.9★ Rating".',
            'icon'        => '📊',
            'category'    => 'Course',
            'fields'      => array(
                'items' => array(
                    'type'        => 'repeater',
                    'label'       => 'Stats',
                    'description' => 'Add up to 4 stats for best results. Each needs a number/value and a label.',
                    'sub_fields'  => array(
                        'number' => array( 'type' => 'text', 'label' => 'Number or Value (e.g. 50K+ or 4.9★)' ),
                        'label'  => array( 'type' => 'text', 'label' => 'Label (e.g. Workers Certified)' ),
                    ),
                ),
            ),
        ),

        // ── FAQ SECTION ───────────────────────────────────────────────────────
        'faq' => array(
            'label'       => 'FAQ Section',
            'description' => 'Pulls FAQs automatically from your FAQ content in WordPress. Hidden automatically if no FAQs exist for this page.',
            'icon'        => '❓',
            'category'    => 'Course',
            'fields'      => array(
                'title' => array(
                    'type'    => 'text',
                    'label'   => 'Section Heading',
                    'default' => 'Frequently Asked Questions',
                ),
                'layout' => array(
                    'type'    => 'select',
                    'label'   => 'Layout',
                    'options' => array(
                        'two_col' => 'Two columns (side by side)',
                        'one_col' => 'Single column (stacked)',
                    ),
                    'default' => 'two_col',
                ),
            ),
        ),

        // ── COURSE OUTLINE ────────────────────────────────────────────────────
        'course_outline' => array(
            'label'       => 'Course Outline Button',
            'description' => 'A button that opens a popup with the full course outline. Hidden automatically if the outline content field is empty.',
            'icon'        => '📋',
            'category'    => 'Course',
            'fields'      => array(
                'button_text' => array(
                    'type'    => 'text',
                    'label'   => 'Button Text',
                    'default' => 'View Full Course Outline',
                ),
                'outline_content' => array(
                    'type'        => 'textarea',
                    'label'       => 'Course Outline Content',
                    'description' => 'This content appears in the popup when a visitor clicks the Course Outline button. Supports basic HTML: <ul><li>...</li></ul> for bullet lists, <h3> for headings. Leave blank to hide the button automatically.',
                    'default'     => '',
                ),
            ),
        ),

        // ── COURSE VERSIONS ──────────────────────────────────────────────────────
        'course_versions' => array(
            'label'       => 'Course Versions Popup',
            'description' => 'A "View All Versions" button that opens a popup listing all available course versions with prices and enroll buttons. Hidden if no versions are added.',
            'icon'        => '🗂️',
            'category'    => 'Course',
            'fields'      => array(
                'button_text' => array(
                    'type'        => 'text',
                    'label'       => 'Button Text',
                    'description' => 'Text shown on the button that opens the popup.',
                    'default'     => 'View All Versions & Topics',
                ),
                'modal_title' => array(
                    'type'    => 'text',
                    'label'   => 'Popup Heading',
                    'default' => 'Choose Your Course Version',
                ),
                'modal_subtitle' => array(
                    'type'    => 'text',
                    'label'   => 'Popup Subheading',
                    'default' => 'All versions include instant certificate delivery',
                ),
                'versions' => array(
                    'type'        => 'repeater',
                    'label'       => 'Course Versions',
                    'description' => 'Add one entry per version of this course. Each version needs its own WooCommerce Product ID for automatic pricing.',
                    'sub_fields'  => array(
                        'name'        => array( 'type' => 'text', 'label' => 'Version Name (e.g. English, Spanish, With Proctor)' ),
                        'product_id'  => array( 'type' => 'text', 'label' => 'WooCommerce Product ID (for automatic price)' ),
                        'enroll_url'  => array( 'type' => 'text', 'label' => 'Enroll Button URL' ),
                        'description' => array( 'type' => 'text', 'label' => 'Short Description (1 sentence)' ),
                        'is_featured' => array( 'type' => 'text', 'label' => 'Show "Most Popular" badge? Type yes or leave blank' ),
                    ),
                ),
            ),
        ),

        // ── COURSE INFO TABS ─────────────────────────────────────────────────────
        'course_info_tabs' => array(
            'label'       => 'Course Information Tabs',
            'description' => 'A tabbed section showing course overview video, course details, audience info, and industries. Each tab hides automatically if left empty.',
            'icon'        => '📑',
            'category'    => 'Course',
            'fields'      => array(
                'section_tag' => array(
                    'type'    => 'text',
                    'label'   => 'Section Tag (small label above heading)',
                    'default' => 'Course Details',
                ),
                'section_title' => array(
                    'type'    => 'text',
                    'label'   => 'Section Heading',
                    'default' => 'Everything You Need to Know',
                ),
                'overview_video_url' => array(
                    'type'        => 'text',
                    'label'       => 'Course Overview Video URL (Vimeo)',
                    'description' => 'Paste the full Vimeo embed URL. Example: https://player.vimeo.com/video/123456789 — Leave blank to hide this tab.',
                    'default'     => '',
                ),
                'preview_video_url' => array(
                    'type'        => 'text',
                    'label'       => '5-Minute Preview Video URL (Vimeo)',
                    'description' => 'Paste the full Vimeo embed URL for the free preview. Leave blank to hide this tab.',
                    'default'     => '',
                ),
                'whats_included' => array(
                    'type'        => 'repeater',
                    'label'       => 'Whats Included — List Items',
                    'description' => 'Each item appears as a checkmarked row. Example: "Instant Digital Certificate", "OSHA-Compliant Content". Leave empty to hide this tab.',
                    'sub_fields'  => array(
                        'title'    => array( 'type' => 'text', 'label' => 'Item Title' ),
                        'subtitle' => array( 'type' => 'text', 'label' => 'Item Subtitle (optional)' ),
                    ),
                ),
                'course_details' => array(
                    'type'        => 'repeater',
                    'label'       => 'Course Details at a Glance — Rows',
                    'description' => 'Each row has a label and a value. Example: Label = "Duration", Value = "8 Hours". Leave empty to hide this tab.',
                    'sub_fields'  => array(
                        'label' => array( 'type' => 'text', 'label' => 'Label (e.g. Duration, Format, Language)' ),
                        'value' => array( 'type' => 'text', 'label' => 'Value (e.g. 8 Hours, Online, English)' ),
                    ),
                ),
                'who_should' => array(
                    'type'        => 'repeater',
                    'label'       => 'Who Should Take This Course — List Items',
                    'description' => 'Each item appears as a checkmarked row. Example: "Hazardous waste site workers", "Emergency responders". Leave empty to hide this tab.',
                    'sub_fields'  => array(
                        'title'    => array( 'type' => 'text', 'label' => 'Job Title or Role' ),
                        'subtitle' => array( 'type' => 'text', 'label' => 'Description (optional)' ),
                    ),
                ),
                'industries' => array(
                    'type'        => 'repeater',
                    'label'       => 'Industries That Require This Training — List Items',
                    'description' => 'Each item appears as a checkmarked row. Example: "Construction", "Oil & Gas". Leave empty to hide this tab.',
                    'sub_fields'  => array(
                        'title'    => array( 'type' => 'text', 'label' => 'Industry Name' ),
                        'subtitle' => array( 'type' => 'text', 'label' => 'Description (optional)' ),
                    ),
                ),
            ),
        ),

        // ── WHY ETRAINING ────────────────────────────────────────────────────────
        'why_etraining' => array(
            'label'       => 'Why eTraining Section',
            'description' => 'Static section showcasing why learners choose eTraining. Content is consistent across all courses — just add it and it renders automatically. No configuration needed.',
            'icon'        => '🏆',
            'category'    => 'Course',
            'fields'      => array(),
        ),

        // ── STUDENT REVIEWS (Course — auto-scrolling carousel) ────────────────────────────────────────────────
        'student_reviews' => array(
            'label'       => 'Student Reviews Carousel',
            'description' => 'An auto-scrolling carousel of student reviews. Add as many as you like — they loop continuously. Hidden automatically if no reviews are added.',
            'icon'        => '⭐',
            'category'    => 'Course',
            'fields'      => array(
                'section_tag' => array(
                    'type'    => 'text',
                    'label'   => 'Section Tag',
                    'default' => 'Student Feedback',
                ),
                'section_title' => array(
                    'type'    => 'text',
                    'label'   => 'Section Heading',
                    'default' => 'What Our Students Say',
                ),
                'reviews' => array(
                    'type'        => 'repeater',
                    'label'       => 'Reviews',
                    'description' => 'Add one entry per review. Name and review text are required. Role is optional.',
                    'sub_fields'  => array(
                        'name'   => array( 'type' => 'text', 'label' => 'Reviewer Name' ),
                        'role'   => array( 'type' => 'text', 'label' => 'Job Title or Role (optional)' ),
                        'rating' => array( 'type' => 'text', 'label' => 'Star Rating (1-5, default 5)' ),
                        'review' => array( 'type' => 'text', 'label' => 'Review Text' ),
                    ),
                ),
            ),
        ),

        // ════════════════════════════════════════════════════════════════
        // GENERAL BLOCKS — general purpose content blocks
        // ════════════════════════════════════════════════════════════════

        // ── RICH TEXT ─────────────────────────────────────────────────────────
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

        // ── LAYOUT SECTION ────────────────────────────────────────────────────
        'layout_section' => array(
            'label'       => 'Layout Section',
            'description' => 'A flexible multi-column layout. Choose your column configuration then add any block inside each column — text, images, video, or any other block type.',
            'icon'        => '⊞',
            'category'    => 'General',
            'fields'      => array(
                'background' => array(
                    'type'    => 'select',
                    'label'   => 'Section Background',
                    'options' => array(
                        'white'     => 'White',
                        'off_white' => 'Off White / Light Grey',
                        'dark'      => 'Dark Navy',
                    ),
                    'default' => 'white',
                ),
                'column_layout' => array(
                    'type'        => 'select',
                    'label'       => 'Column Layout',
                    'description' => 'Choose how many columns and their relative widths. Columns stack on mobile.',
                    'options'     => array(
                        '6_6'     => '2 columns — 50% / 50%',
                        '7_5'     => '2 columns — 60% / 40%',
                        '5_7'     => '2 columns — 40% / 60%',
                        '8_4'     => '2 columns — 67% / 33%',
                        '4_8'     => '2 columns — 33% / 67%',
                        '4_4_4'   => '3 columns — equal thirds',
                        '3_3_3_3' => '4 columns — equal quarters',
                    ),
                    'default' => '6_6',
                ),
                'gap' => array(
                    'type'    => 'select',
                    'label'   => 'Column Gap',
                    'options' => array(
                        'g-3' => 'Small',
                        'g-4' => 'Medium',
                        'g-5' => 'Large',
                    ),
                    'default' => 'g-4',
                ),
                'columns' => array(
                    'type'        => 'column_builder',
                    'label'       => 'Column Content',
                    'description' => 'Add blocks inside each column. Click "＋ Add block" inside a column to choose a block type.',
                ),
            ),
        ),

        // ════════════════════════════════════════════════════════════════
        // MEDIA BLOCKS — images, video, embeds
        // ════════════════════════════════════════════════════════════════

        // ── SINGLE IMAGE ──────────────────────────────────────────────────────────────
        'image_block' => array(
            'label'       => 'Single Image',
            'description' => 'Display a single image with optional caption, link, and alignment. Paste an image URL from your Media Library.',
            'icon'        => '🖼️',
            'category'    => 'Media',
            'fields'      => array(
                'image_url' => array(
                    'type'        => 'text',
                    'label'       => 'Image URL',
                    'description' => 'Paste the full URL of an image from your WordPress Media Library.',
                    'default'     => '',
                ),
                'alt_text' => array(
                    'type'        => 'text',
                    'label'       => 'Alt Text',
                    'description' => 'Describe the image for accessibility and SEO.',
                    'default'     => '',
                ),
                'caption' => array(
                    'type'    => 'text',
                    'label'   => 'Caption (optional)',
                    'default' => '',
                ),
                'link_url' => array(
                    'type'        => 'text',
                    'label'       => 'Link URL (optional)',
                    'description' => 'If set, the image becomes a clickable link.',
                    'default'     => '',
                ),
                'alignment' => array(
                    'type'    => 'select',
                    'label'   => 'Alignment',
                    'options' => array(
                        'center' => 'Center',
                        'left'   => 'Left',
                        'right'  => 'Right',
                        'full'   => 'Full Width',
                    ),
                    'default' => 'center',
                ),
                'max_width' => array(
                    'type'        => 'text',
                    'label'       => 'Max Width (optional)',
                    'description' => 'Limit the image width. Example: 600px or 80%. Leave blank for full width.',
                    'default'     => '',
                ),
                'background' => array(
                    'type'    => 'select',
                    'label'   => 'Section Background',
                    'options' => array(
                        'white'     => 'White',
                        'off_white' => 'Off White',
                        'dark'      => 'Dark Navy',
                    ),
                    'default' => 'white',
                ),
            ),
        ),

        // ── IMAGE GALLERY ─────────────────────────────────────────────────────────────
        'image_gallery' => array(
            'label'       => 'Image Gallery',
            'description' => 'A grid of images. Paste image URLs from your Media Library. Configurable number of columns.',
            'icon'        => '🗃️',
            'category'    => 'Media',
            'fields'      => array(
                'section_heading' => array(
                    'type'    => 'text',
                    'label'   => 'Section Heading (optional)',
                    'default' => '',
                ),
                'columns' => array(
                    'type'    => 'select',
                    'label'   => 'Columns',
                    'options' => array(
                        '2' => '2 columns',
                        '3' => '3 columns',
                        '4' => '4 columns',
                    ),
                    'default' => '3',
                ),
                'background' => array(
                    'type'    => 'select',
                    'label'   => 'Background',
                    'options' => array(
                        'white'     => 'White',
                        'off_white' => 'Off White',
                        'dark'      => 'Dark Navy',
                    ),
                    'default' => 'white',
                ),
                'images' => array(
                    'type'        => 'repeater',
                    'label'       => 'Images',
                    'description' => 'Add one entry per image.',
                    'sub_fields'  => array(
                        'url'     => array( 'type' => 'text', 'label' => 'Image URL' ),
                        'alt'     => array( 'type' => 'text', 'label' => 'Alt Text' ),
                        'caption' => array( 'type' => 'text', 'label' => 'Caption (optional)' ),
                        'link'    => array( 'type' => 'text', 'label' => 'Link URL (optional)' ),
                    ),
                ),
            ),
        ),

        // ── VIDEO EMBED ───────────────────────────────────────────────────────────────
        'video_embed' => array(
            'label'       => 'Video Embed',
            'description' => 'Embed a video from Vimeo or YouTube. Paste the full video URL and it renders as a responsive player.',
            'icon'        => '▶️',
            'category'    => 'Media',
            'fields'      => array(
                'video_url' => array(
                    'type'        => 'text',
                    'label'       => 'Video URL',
                    'description' => 'Paste the full Vimeo or YouTube URL. Examples: https://vimeo.com/123456789 or https://www.youtube.com/watch?v=xxxxx',
                    'default'     => '',
                ),
                'section_heading' => array(
                    'type'    => 'text',
                    'label'   => 'Heading Above Video (optional)',
                    'default' => '',
                ),
                'caption' => array(
                    'type'    => 'text',
                    'label'   => 'Caption Below Video (optional)',
                    'default' => '',
                ),
                'max_width' => array(
                    'type'        => 'text',
                    'label'       => 'Max Width (optional)',
                    'description' => 'Limit the player width. Example: 800px. Leave blank for full width.',
                    'default'     => '',
                ),
                'background' => array(
                    'type'    => 'select',
                    'label'   => 'Section Background',
                    'options' => array(
                        'white'     => 'White',
                        'off_white' => 'Off White',
                        'dark'      => 'Dark Navy',
                    ),
                    'default' => 'white',
                ),
            ),
        ),

        // ── RAW IFRAME / EMBED ────────────────────────────────────────────────────────
        'raw_iframe' => array(
            'label'       => 'Iframe / Embed',
            'description' => 'Paste any iframe embed code or a direct URL to embed. Good for maps, forms, booking tools, or other third-party widgets.',
            'icon'        => '🔲',
            'category'    => 'Media',
            'fields'      => array(
                'embed_code' => array(
                    'type'        => 'textarea',
                    'label'       => 'Embed Code or URL',
                    'description' => 'Paste a full <iframe> tag, or just a plain URL (e.g. https://maps.google.com/...) — it will be wrapped in an iframe automatically.',
                    'default'     => '',
                ),
                'height' => array(
                    'type'        => 'text',
                    'label'       => 'Height',
                    'description' => 'Height of the embed. Example: 500px or 60vh.',
                    'default'     => '500px',
                ),
                'section_heading' => array(
                    'type'    => 'text',
                    'label'   => 'Heading Above Embed (optional)',
                    'default' => '',
                ),
                'background' => array(
                    'type'    => 'select',
                    'label'   => 'Section Background',
                    'options' => array(
                        'white'     => 'White',
                        'off_white' => 'Off White',
                        'dark'      => 'Dark Navy',
                    ),
                    'default' => 'white',
                ),
            ),
        ),

        // ════════════════════════════════════════════════════════════════
        // SOCIAL PROOF BLOCKS — trust and conversion blocks
        // ════════════════════════════════════════════════════════════════

        // ── TESTIMONIALS (Social Proof — 3-column card grid) ──────────────────────────────────────────────
        'testimonials' => array(
            'label'       => 'Testimonials Grid',
            'description' => 'A grid of student testimonials. Displays 3 per row on desktop, stacks on mobile. Add as many as you like.',
            'icon'        => '💬',
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

    ); // end $definitions

    // Blocks that receive background settings (replace 'background' field with full system)
    $blocks_with_bg = array(
        'rich_text', 'cta_banner', 'two_column', 'comparison_table',
        'testimonials', 'our_guarantee', 'image_block', 'image_gallery',
        'video_embed', 'raw_iframe', 'layout_section', 'faq',
        'why_etraining', 'student_reviews', 'stats_strip', 'course_info_tabs',
    );

    $bg_fields = etpb_get_background_fields();

    foreach ( $blocks_with_bg as $btype ) {
        if ( ! isset( $definitions[ $btype ] ) ) continue;
        // Remove legacy 'background' field
        unset( $definitions[ $btype ]['fields']['background'] );
        // Append new background fields
        $definitions[ $btype ]['fields'] = array_merge(
            $definitions[ $btype ]['fields'],
            $bg_fields
        );
    }

    return $definitions;
}

/**
 * Get the saved block configuration for a specific page.
 * Returns an array of blocks, or an empty array if nothing is configured yet.
 * Reads from 'etpb_blocks_config' meta key.
 */
function etpb_get_page_blocks( $post_id ) {
    $config  = get_post_meta( $post_id, 'etpb_blocks_config', true );
    if ( empty( $config ) ) {
        return array();
    }
    $decoded = json_decode( $config, true );
    return is_array( $decoded ) ? $decoded : array();
}

/**
 * Get the WooCommerce product connected to a given page.
 * Returns the product object, or null if no product is connected or WooCommerce isn't active.
 */
function etpb_get_product( $post_id ) {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return null;
    }
    $product_id = get_post_meta( $post_id, 'Product ID', true );
    if ( ! $product_id ) {
        return null;
    }
    return wc_get_product( (int) $product_id );
}

/**
 * Returns the standard background fields injected into every applicable block.
 * These fields replace the simple 'background' select on applicable blocks.
 */
function etpb_get_background_fields() {
    return array(

        'bg_type' => array(
            'type'    => 'select',
            'label'   => 'Background Type',
            'options' => array(
                'preset' => 'Preset Color',
                'custom' => 'Custom Color',
                'image'  => 'Background Image',
            ),
            'default' => 'preset',
        ),

        'bg_preset' => array(
            'type'    => 'select',
            'label'   => 'Preset Color',
            'options' => array(
                'white'     => 'White',
                'off_white' => 'Off White / Light Grey',
                'dark'      => 'Dark Navy',
                'teal'      => 'Teal Gradient',
            ),
            'default' => 'white',
        ),

        'bg_custom_color' => array(
            'type'        => 'color_picker',
            'label'       => 'Custom Background Color',
            'description' => 'Click the swatch to open the colour picker, or type a hex value directly.',
            'default'     => '#ffffff',
        ),

        'bg_image_url' => array(
            'type'        => 'media_picker',
            'label'       => 'Background Image',
            'description' => 'Choose an image from your Media Library.',
            'default'     => '',
        ),

        'bg_image_position' => array(
            'type'    => 'select',
            'label'   => 'Image Position',
            'options' => array(
                'center center' => 'Center (default)',
                'top center'    => 'Top',
                'bottom center' => 'Bottom',
                'center left'   => 'Left',
                'center right'  => 'Right',
            ),
            'default' => 'center center',
        ),

        'bg_image_size' => array(
            'type'    => 'select',
            'label'   => 'Image Size',
            'options' => array(
                'cover'   => 'Cover — fills the section (recommended)',
                'contain' => 'Contain — shows the full image',
                'auto'    => 'Auto — natural size',
            ),
            'default' => 'cover',
        ),

        'bg_overlay_color' => array(
            'type'        => 'color_picker',
            'label'       => 'Overlay Colour',
            'description' => 'Colour layered over the image to improve text readability.',
            'default'     => '#0b1b2c',
        ),

        'bg_overlay_opacity' => array(
            'type'    => 'select',
            'label'   => 'Overlay Opacity',
            'options' => array(
                '0'   => 'None (0%)',
                '0.2' => '20%',
                '0.4' => '40%',
                '0.6' => '60% (recommended)',
                '0.8' => '80%',
                '1'   => 'Solid (100%)',
            ),
            'default' => '0.6',
        ),

    );
}

/**
 * Resolves background settings from a block's fields array.
 *
 * Returns an array with:
 *   'class'   => string  CSS class(es) to add to the <section> element
 *   'style'   => string  Inline style string for the <section> element
 *   'overlay' => string  HTML for the overlay <div> (image mode only, else empty)
 *
 * Supports two systems:
 *   - New: reads bg_type / bg_preset / bg_custom_color / bg_image_* fields
 *   - Legacy fallback: reads old 'background' select field
 */
function etpb_bg( $fields ) {
    $bg_type = $fields['bg_type'] ?? null;

    // ── Legacy fallback (blocks saved before the new system) ──────────────────
    if ( $bg_type === null ) {
        return array(
            'class'   => cb_bg_class( $fields['background'] ?? 'white' ),
            'style'   => '',
            'overlay' => '',
        );
    }

    // ── Preset colour ──────────────────────────────────────────────────────────
    if ( $bg_type === 'preset' ) {
        return array(
            'class'   => cb_bg_class( $fields['bg_preset'] ?? 'white' ),
            'style'   => '',
            'overlay' => '',
        );
    }

    // ── Custom colour ──────────────────────────────────────────────────────────
    if ( $bg_type === 'custom' ) {
        $color = sanitize_hex_color( $fields['bg_custom_color'] ?? '' ) ?: '#ffffff';
        return array(
            'class'   => '',
            'style'   => 'background:' . $color . ';',
            'overlay' => '',
        );
    }

    // ── Background image ───────────────────────────────────────────────────────
    if ( $bg_type === 'image' ) {
        $img_url = esc_url( $fields['bg_image_url']      ?? '' );
        $pos     = esc_attr( $fields['bg_image_position'] ?? 'center center' );
        $size    = esc_attr( $fields['bg_image_size']     ?? 'cover' );
        $ov_hex  = sanitize_hex_color( $fields['bg_overlay_color']   ?? '' ) ?: '#0b1b2c';
        $opacity = floatval( $fields['bg_overlay_opacity'] ?? 0.6 );

        if ( ! $img_url ) {
            // No image set — fall back to dark preset
            return array(
                'class'   => 'cb-bg-dark',
                'style'   => '',
                'overlay' => '',
            );
        }

        $style = 'background-image:url(\'' . $img_url . '\');'
               . 'background-size:' . $size . ';'
               . 'background-position:' . $pos . ';'
               . 'background-repeat:no-repeat;';

        $overlay = '';
        if ( $opacity > 0 ) {
            // Convert hex to r,g,b components
            list( $r, $g, $b ) = sscanf( $ov_hex, '#%02x%02x%02x' );
            $overlay = '<div class="etpb-bg-overlay" style="background:rgba('
                . (int) $r . ',' . (int) $g . ',' . (int) $b . ',' . $opacity
                . ');"></div>';
        }

        return array(
            'class'   => 'etpb-has-bg-image',
            'style'   => $style,
            'overlay' => $overlay,
        );
    }

    // Default fallback
    return array( 'class' => 'cb-bg-white', 'style' => '', 'overlay' => '' );
}

/**
 * Returns the merged list of icons available for selection in the editor.
 * Union of CLB and Core Blocks icon lists.
 * Format: 'font-awesome-class' => 'Human readable label'
 */
function etpb_get_available_icons() {
    return array(
        ''                       => '— No icon —',
        'fas fa-check-circle'    => '✓  Check Circle',
        'fas fa-shield-alt'      => '🛡  Shield / Protected',
        'fas fa-users'           => '👥  Users / People',
        'fas fa-bolt'            => '⚡  Bolt / Fast',
        'fas fa-certificate'     => '🏆  Certificate',
        'fas fa-laptop'          => '💻  Laptop / Online',
        'fas fa-clock'           => '🕐  Clock / Time',
        'fas fa-mobile-alt'      => '📱  Mobile Friendly',
        'fas fa-hard-hat'        => '👷  Hard Hat / Safety',
        'fas fa-shopping-cart'   => '🛒  Shopping Cart',
        'fas fa-star'            => '⭐  Star',
        'fas fa-graduation-cap'  => '🎓  Graduation Cap',
        'fas fa-file-alt'        => '📄  Document',
        'fas fa-layer-group'     => '📚  Layers / Topics',
        'fas fa-lock'            => '🔒  Lock / Secure',
        'fas fa-award'           => '🥇  Award',
        'fas fa-tools'           => '🔧  Tools',
        'fas fa-map-marker-alt'  => '📍  Location',
        'fas fa-phone'           => '📞  Phone',
        'fas fa-envelope'        => '✉️  Email',
        'fas fa-dollar-sign'     => '💲  Dollar / Price',
        'fas fa-percentage'      => '%  Percentage / Discount',
        'fas fa-arrow-right'     => '→  Arrow Right',
    );
}

/**
 * Returns a simplified version of block definitions for use in JavaScript.
 * Only includes label, icon, and category — not the full field definitions.
 */
function etpb_get_block_definitions_for_js() {
    $defs = etpb_get_block_definitions();
    $out  = array();
    foreach ( $defs as $type => $def ) {
        $out[ $type ] = array(
            'label'    => $def['label'],
            'icon'     => $def['icon'],
            'category' => $def['category'],
        );
    }
    return $out;
}

/**
 * Background class helper used by Core Blocks render functions.
 * DO NOT RENAME — cb_bg_class is called by cb_render_* functions.
 */
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
