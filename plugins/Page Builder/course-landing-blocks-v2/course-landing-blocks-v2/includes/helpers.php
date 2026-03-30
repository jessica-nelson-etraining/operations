<?php
/**
 * helpers.php
 *
 * Contains two things:
 *  1. The master list of available block types (what types of sections can be added)
 *  2. Small utility functions used by other parts of the plugin
 *
 * To add a new block type in the future, a developer adds an entry to clb_get_block_definitions()
 * and a corresponding render function in block-renderer.php. No other files need to change.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Returns the complete list of block types available in the editor.
 *
 * Each block definition contains:
 *  - label:       Human-readable name shown in the editor
 *  - description: Short explanation of what the block does
 *  - icon:        Emoji icon shown in the editor for easy identification
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
function clb_get_block_definitions() {
    return array(

        // ── TRUST BAR ─────────────────────────────────────────────────────────
        // The thin banner at the very top of the page with compliance/trust indicators
        'trust_bar' => array(
            'label'       => 'Trust Bar',
            'description' => 'A top banner showing trust indicators like compliance badges or statistics.',
            'icon'        => '✅',
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
        // The main headline area — takes up the top portion of the page
        'hero' => array(
            'label'       => 'Hero Section',
            'description' => 'The main headline area at the top of the page. Course title comes from the page title automatically.',
            'icon'        => '🦸',
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
        // The purchase box — shown next to the hero on desktop, below it on mobile
        'enrollment_card' => array(
            'label'       => 'Enrollment Card',
            'description' => 'The purchase/enrollment box. Price and enroll button link connect to WooCommerce automatically.',
            'icon'        => '🛒',
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
        // A horizontal band showing key numbers/statistics
        'stats_strip' => array(
            'label'       => 'Stats Strip',
            'description' => 'A band of highlighted numbers. Example: "50K+ Workers Certified", "4.9★ Rating".',
            'icon'        => '📊',
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
        // Automatically pulls FAQ content from WordPress — no manual entry needed
        'faq' => array(
            'label'       => 'FAQ Section',
            'description' => 'Pulls FAQs automatically from your FAQ content in WordPress. Hidden automatically if no FAQs exist for this page.',
            'icon'        => '❓',
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
        // A button that opens a popup showing the full course outline
        'course_outline' => array(
            'label'       => 'Course Outline Button',
            'description' => 'A button that opens a popup with the full course outline. Pulls outline content from the "Course Outline" custom field. Hidden automatically if that field is empty.',
            'icon'        => '📋',
            'fields'      => array(
                'button_text' => array(
                    'type'    => 'text',
                    'label'   => 'Button Text',
                    'default' => 'View Full Course Outline',
                ),
            ),
        ),

        // ── WHY ETRAINING ────────────────────────────────────────────────────────
        // Static section — content is fixed and consistent across all courses.
        // No configuration needed. Just add the block and it renders automatically.
        'why_etraining' => array(
            'label'       => 'Why eTraining Section',
            'description' => 'Static section showcasing why learners choose eTraining. Content is consistent across all courses — just add it and it renders automatically. No configuration needed.',
            'icon'        => '🏆',
            'fields'      => array(),
        ),

        // ── COURSE INFO TABS ─────────────────────────────────────────────────────
        // The vertical tabs section showing course details, videos, and audiences.
        // Each tab auto-hides if its content is left empty.
        // Video tabs only appear if a Vimeo URL is provided.
        'course_info_tabs' => array(
            'label'       => 'Course Information Tabs',
            'description' => 'A tabbed section showing course overview video, course details, audience info, and industries. Each tab hides automatically if left empty.',
            'icon'        => '📑',
            'fields'      => array(

                // ── Section heading ──
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

                // ── Video tabs ──
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

                // ── What's Included tab ──
                'whats_included' => array(
                    'type'        => 'repeater',
                    'label'       => 'Whats Included — List Items',
                    'description' => 'Each item appears as a checkmarked row. Example: "Instant Digital Certificate", "OSHA-Compliant Content". Leave empty to hide this tab.',
                    'sub_fields'  => array(
                        'title'    => array( 'type' => 'text', 'label' => 'Item Title' ),
                        'subtitle' => array( 'type' => 'text', 'label' => 'Item Subtitle (optional)' ),
                    ),
                ),

                // ── Course Details at a Glance tab ──
                'course_details' => array(
                    'type'        => 'repeater',
                    'label'       => 'Course Details at a Glance — Rows',
                    'description' => 'Each row has a label and a value. Example: Label = "Duration", Value = "8 Hours". Leave empty to hide this tab.',
                    'sub_fields'  => array(
                        'label' => array( 'type' => 'text', 'label' => 'Label (e.g. Duration, Format, Language)' ),
                        'value' => array( 'type' => 'text', 'label' => 'Value (e.g. 8 Hours, Online, English)' ),
                    ),
                ),

                // ── Who Should Take This Course tab ──
                'who_should' => array(
                    'type'        => 'repeater',
                    'label'       => 'Who Should Take This Course — List Items',
                    'description' => 'Each item appears as a checkmarked row. Example: "Hazardous waste site workers", "Emergency responders". Leave empty to hide this tab.',
                    'sub_fields'  => array(
                        'title'    => array( 'type' => 'text', 'label' => 'Job Title or Role' ),
                        'subtitle' => array( 'type' => 'text', 'label' => 'Description (optional)' ),
                    ),
                ),

                // ── Industries tab ──
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

        // ── STUDENT REVIEWS ──────────────────────────────────────────────────────
        // Auto-scrolling carousel of student reviews. Each review has a name,
        // role, star rating, and review text. Hidden if no reviews are added.
        'student_reviews' => array(
            'label'       => 'Student Reviews',
            'description' => 'An auto-scrolling carousel of student reviews. Add as many as you like — they loop continuously. Hidden automatically if no reviews are added.',
            'icon'        => '⭐',
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

        // ── COURSE VERSIONS ──────────────────────────────────────────────────────
        // Optional modal popup showing all available versions of this course.
        // Each version connects to its own WooCommerce product for automatic pricing.
        // Hidden automatically if no versions are configured.
        'course_versions' => array(
            'label'       => 'Course Versions Popup',
            'description' => 'A "View All Versions" button that opens a popup listing all available course versions with prices and enroll buttons. Hidden if no versions are added.',
            'icon'        => '🗂️',
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

    ); // end return
}

/**
 * Get the saved block configuration for a specific page.
 * Returns an array of blocks, or an empty array if nothing is configured yet.
 */
function clb_get_page_blocks( $post_id ) {
    $config  = get_post_meta( $post_id, 'clb_blocks_config', true );
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
function clb_get_product( $post_id ) {
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
 * Returns the list of icons available for selection in the editor.
 * Format: 'font-awesome-class' => 'Human readable label'
 *
 * To add more icons, a developer just adds another line here.
 * Icons are Font Awesome 5 classes — the theme must already load Font Awesome.
 */
function clb_get_available_icons() {
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
    );
}
