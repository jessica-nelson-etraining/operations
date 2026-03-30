<?php
/**
 * Plugin Name: Course Landing Blocks v2
 * Plugin URI:  https://etraintoday.com
 * Description: Visual block editor for course landing pages. Add, remove, and reorder sections without writing any code.
 * Version:     2.0.0
 * Author:      eTrain Inc.
 * Text Domain: course-landing-blocks
 *
 * HOW THIS PLUGIN WORKS:
 * ----------------------
 * 1. It adds a "Course Landing Page Builder" panel to every WordPress page editor.
 * 2. Non-developers use that panel to add, configure, and reorder content blocks.
 * 3. The block configuration is saved as data on the page (as JSON in post meta).
 * 4. When a visitor views the page, this plugin reads that configuration and renders the blocks.
 * 5. Blocks with no content are automatically hidden — nothing breaks if a field is left empty.
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Plugin constants ──────────────────────────────────────────────────────────
define( 'CLB_DIR',     plugin_dir_path( __FILE__ ) );
define( 'CLB_URL',     plugin_dir_url( __FILE__ ) );
define( 'CLB_VERSION', '2.0.0' );

// ── Load supporting files ─────────────────────────────────────────────────────
require_once CLB_DIR . 'includes/helpers.php';       // Block definitions & utility functions
require_once CLB_DIR . 'includes/block-renderer.php'; // Frontend HTML output for each block

if ( is_admin() ) {
    require_once CLB_DIR . 'includes/admin-interface.php'; // WordPress admin editor panel
}

/**
 * Register the FAQ custom post type and FAQ Group taxonomy.
 *
 * This matches the exact post type ('faq') and taxonomy ('faq-group') used
 * on the staging site, so FAQs created here will work identically when the
 * plugin is deployed to the real site.
 *
 * If the staging theme already registers these, WordPress will simply skip
 * this registration — no conflict, no errors.
 */
function clb_register_faq_post_type() {

    // Register the FAQ post type
    if ( ! post_type_exists( 'faq' ) ) {
        register_post_type( 'faq', array(
            'labels' => array(
                'name'               => 'FAQs',
                'singular_name'      => 'FAQ',
                'add_new'            => 'Add New FAQ',
                'add_new_item'       => 'Add New FAQ',
                'edit_item'          => 'Edit FAQ',
                'new_item'           => 'New FAQ',
                'view_item'          => 'View FAQ',
                'search_items'       => 'Search FAQs',
                'not_found'          => 'No FAQs found',
                'not_found_in_trash' => 'No FAQs found in trash',
            ),
            'public'            => false,   // Not publicly accessible as its own page
            'show_ui'           => true,    // Show in WordPress admin
            'show_in_menu'      => true,    // Appear in left sidebar
            'show_in_nav_menus' => false,
            'supports'          => array( 'title', 'editor' ),
            'menu_icon'         => 'dashicons-editor-help',
            'rewrite'           => false,
        ) );
    }

    // Register the FAQ Group taxonomy — used to tag FAQs to specific pages
    if ( ! taxonomy_exists( 'faq-group' ) ) {
        register_taxonomy( 'faq-group', 'faq', array(
            'labels' => array(
                'name'              => 'FAQ Groups',
                'singular_name'     => 'FAQ Group',
                'search_items'      => 'Search FAQ Groups',
                'all_items'         => 'All FAQ Groups',
                'edit_item'         => 'Edit FAQ Group',
                'update_item'       => 'Update FAQ Group',
                'add_new_item'      => 'Add New FAQ Group',
                'new_item_name'     => 'New FAQ Group Name',
                'menu_name'         => 'FAQ Groups',
            ),
            'hierarchical'      => false,   // Works like tags, not categories
            'public'            => false,
            'show_ui'           => true,
            'show_in_nav_menus' => false,
            // The slug of each group must match the page slug exactly
            // e.g. a page at /course-test-101/ needs a group with slug 'course-test-101'
            'rewrite'           => false,
        ) );
    }
}
add_action( 'init', 'clb_register_faq_post_type' );

/**
 * Register the post meta fields used to store block data on each page.
 * 'clb_blocks_config' stores the full block configuration as a JSON string.
 */
function clb_init() {
    register_post_meta( 'page', 'clb_blocks_config', array(
        'type'              => 'string',
        'sanitize_callback' => 'wp_kses_post',
        'show_in_rest'      => false,
        'single'            => true,
    ) );
}
add_action( 'init', 'clb_init' );

/**
 * Load frontend styles on course landing pages.
 * Bootstrap, Font Awesome, and Google Fonts are loaded directly in
 * templates/header.php so they are available immediately on page load.
 * This function loads only the theme-specific CSS files via WordPress.
 */
function clb_enqueue_frontend_assets() {
    if ( ! is_page() ) return;

    $post_id = get_queried_object_id();
    $blocks  = get_post_meta( $post_id, 'clb_blocks_config', true );
    if ( empty( $blocks ) ) return;

    // Main theme CSS — header, footer, navigation, and global styles
    wp_enqueue_style(
        'clb-theme-main',
        CLB_URL . 'assets/theme-main.css',
        array(),
        CLB_VERSION
    );

    // Landing page CSS — hero, trust bar, enrollment card, stats strip etc.
    wp_enqueue_style(
        'clb-landing-page',
        CLB_URL . 'assets/landing-page.css',
        array( 'clb-theme-main' ),
        CLB_VERSION
    );

    // Frontend JS — handles modal open/close for the Course Versions popup
    wp_enqueue_script(
        'clb-frontend',
        CLB_URL . 'assets/frontend.js',
        array(),
        CLB_VERSION,
        true // Load in footer
    );
}
add_action( 'wp_enqueue_scripts', 'clb_enqueue_frontend_assets' );

/**
 * Load the admin CSS and JavaScript only on page edit screens.
 * We don't load these everywhere — only where the block editor is shown.
 */
function clb_enqueue_admin_assets( $hook ) {
    if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
        return;
    }
    global $post;
    if ( ! $post || 'page' !== $post->post_type ) {
        return;
    }

    wp_enqueue_style(
        'clb-admin',
        CLB_URL . 'assets/admin.css',
        array(),
        CLB_VERSION
    );

    // jQuery UI Sortable is bundled with WordPress — we just need to request it
    wp_enqueue_script( 'jquery-ui-sortable' );

    wp_enqueue_script(
        'clb-admin',
        CLB_URL . 'assets/admin.js',
        array( 'jquery', 'jquery-ui-sortable' ),
        CLB_VERSION,
        true // Load in footer
    );

    // Pass useful data from PHP to our JavaScript
    wp_localize_script( 'clb-admin', 'clbData', array(
        'nonce'      => wp_create_nonce( 'clb_ajax_nonce' ),
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'pluginUrl'  => CLB_URL,
    ) );
}
add_action( 'admin_enqueue_scripts', 'clb_enqueue_admin_assets' );

/**
 * AJAX handler: returns the rendered HTML for a newly added block's editor fields.
 * When a user clicks "Add a Section", JavaScript asks this endpoint for the fields HTML.
 * This keeps the field rendering consistent — always generated by PHP, never guessed by JS.
 */
function clb_ajax_get_block_fields() {
    // Security check
    check_ajax_referer( 'clb_ajax_nonce', 'nonce' );

    $type  = sanitize_key( $_POST['block_type'] ?? '' );
    $index = absint( $_POST['block_index'] ?? 0 );
    $definitions = clb_get_block_definitions();

    if ( ! isset( $definitions[ $type ] ) ) {
        wp_send_json_error( 'Unknown block type.' );
    }

    ob_start();
    clb_render_block_editor_row( $index, array( 'type' => $type, 'fields' => array() ), $definitions );
    $html = ob_get_clean();

    wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_clb_get_block_fields', 'clb_ajax_get_block_fields' );

/**
 * Use our custom page template for pages that have block configuration saved.
 * This intercepts WordPress's normal template loading and uses our template instead.
 */
function clb_maybe_use_template( $template ) {
    if ( ! is_page() ) {
        return $template;
    }
    $post_id = get_queried_object_id();
    $blocks  = get_post_meta( $post_id, 'clb_blocks_config', true );

    if ( ! empty( $blocks ) ) {
        $custom_template = CLB_DIR . 'templates/course-landing.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }
    return $template;
}
add_filter( 'template_include', 'clb_maybe_use_template' );
