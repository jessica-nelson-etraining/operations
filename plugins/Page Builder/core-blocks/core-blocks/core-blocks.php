<?php
/**
 * Plugin Name: Core Blocks
 * Plugin URI:  https://etraintoday.com
 * Description: General purpose page blocks — Rich Text, CTA Banner, and Two-Column Layout.
 *              Works on any WordPress page independently of Course Landing Blocks.
 *              Install alongside other eTraining block plugins to mix and match.
 * Version:     1.1.0
 * Author:      eTrain Inc.
 * Text Domain: core-blocks
 *
 * HOW THIS PLUGIN WORKS:
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. Adds a "Page Builder" meta box to every WordPress page editor.
 * 2. Non-developers use that panel to add, configure, and reorder content blocks.
 * 3. Block configuration is saved as JSON in post meta (cb_blocks_config).
 * 4. When a visitor views the page, this plugin reads the config and renders blocks.
 * 5. Works independently — does NOT require Course Landing Blocks to be installed.
 *
 * RELATIONSHIP TO OTHER eTRAIN PLUGINS:
 * ─────────────────────────────────────────────────────────────────────────────
 * This plugin is part of the eTraining page builder family:
 *   - core-blocks         → General purpose blocks (this plugin)
 *   - course-landing-blocks-v2 → Course landing page blocks
 *   - (future) business-blocks → Group enrollment / B2B landing pages
 *   - (future) catalog-blocks  → Course catalog and search
 *
 * All plugins can be active simultaneously — they use different meta keys and
 * separate admin panels so they never conflict with each other.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Plugin constants ──────────────────────────────────────────────────────────
define( 'CB_DIR',     plugin_dir_path( __FILE__ ) );
define( 'CB_URL',     plugin_dir_url( __FILE__ ) );
define( 'CB_VERSION', '1.1.0' );

// ── Load supporting files ─────────────────────────────────────────────────────
require_once CB_DIR . 'includes/helpers.php';
require_once CB_DIR . 'includes/block-renderer.php';

if ( is_admin() ) {
    require_once CB_DIR . 'includes/admin-interface.php';
}

/**
 * Register the post meta field used to store block configuration on each page.
 * Uses 'cb_blocks_config' (not 'clb_') to avoid any conflict with Course Landing Blocks.
 */
function cb_init() {
    register_post_meta( 'page', 'cb_blocks_config', array(
        'type'              => 'string',
        'sanitize_callback' => 'wp_kses_post',
        'show_in_rest'      => false,
        'single'            => true,
    ) );
}
add_action( 'init', 'cb_init' );

/**
 * Enqueue admin assets — only on page edit screens.
 */
function cb_enqueue_admin_assets( $hook ) {
    if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) return;
    global $post;
    if ( ! $post || 'page' !== $post->post_type ) return;

    wp_enqueue_style(
        'cb-admin',
        CB_URL . 'assets/admin.css',
        array(),
        CB_VERSION
    );

    wp_enqueue_script( 'jquery-ui-sortable' );

    wp_enqueue_script(
        'cb-admin',
        CB_URL . 'assets/admin.js',
        array( 'jquery', 'jquery-ui-sortable' ),
        CB_VERSION,
        true
    );

    wp_localize_script( 'cb-admin', 'cbData', array(
        'nonce'   => wp_create_nonce( 'cb_ajax_nonce' ),
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'cb_enqueue_admin_assets' );

/**
 * Enqueue frontend assets — only on pages that have Core Block configuration.
 */
function cb_enqueue_frontend_assets() {
    if ( ! is_page() ) return;

    $post_id = get_queried_object_id();
    $blocks  = get_post_meta( $post_id, 'cb_blocks_config', true );
    if ( empty( $blocks ) ) return;

    // Google Fonts
    wp_enqueue_style( 'cb-fonts',
        'https://fonts.googleapis.com/css2?family=Urbanist:wght@400;600;700;800;900&display=swap',
        array(), null
    );

    // Bootstrap 5 — grid and utilities
    wp_enqueue_style( 'cb-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
        array(), '5.3.0'
    );

    // Font Awesome 6 — icons
    wp_enqueue_style( 'cb-fontawesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
        array(), '6.5.0'
    );

    // Core Blocks frontend styles
    wp_enqueue_style( 'cb-frontend',
        CB_URL . 'assets/frontend.css',
        array( 'cb-bootstrap', 'cb-fonts', 'cb-fontawesome' ),
        CB_VERSION
    );

    // Bootstrap JS — needed for interactive components
    wp_enqueue_script( 'cb-bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
        array(), '5.3.0', true
    );
}
add_action( 'wp_enqueue_scripts', 'cb_enqueue_frontend_assets' );

/**
 * AJAX handler: returns rendered HTML for a newly added block's editor fields.
 */
function cb_ajax_get_block_fields() {
    check_ajax_referer( 'cb_ajax_nonce', 'nonce' );

    $type        = sanitize_key( $_POST['block_type']  ?? '' );
    $index       = absint( $_POST['block_index'] ?? 0 );
    $definitions = cb_get_block_definitions();

    if ( ! isset( $definitions[ $type ] ) ) {
        wp_send_json_error( 'Unknown block type.' );
    }

    ob_start();
    cb_render_block_editor_row( $index, array( 'type' => $type, 'fields' => array() ), $definitions );
    $html = ob_get_clean();

    wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_cb_get_block_fields', 'cb_ajax_get_block_fields' );

/**
 * Use our page template when a page has Core Block configuration saved.
 * Only intercepts if the page has cb_blocks_config AND no Course Landing config —
 * if both plugins are active on the same page, Course Landing Blocks takes priority.
 */
function cb_maybe_use_template( $template ) {
    if ( ! is_page() ) return $template;

    $post_id  = get_queried_object_id();
    $cb_blocks  = get_post_meta( $post_id, 'cb_blocks_config',  true );
    $clb_blocks = get_post_meta( $post_id, 'clb_blocks_config', true );

    // Course Landing Blocks takes priority if both are configured
    if ( ! empty( $cb_blocks ) && empty( $clb_blocks ) ) {
        $custom_template = CB_DIR . 'templates/page.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }

    return $template;
}
add_filter( 'template_include', 'cb_maybe_use_template' );
