<?php
/**
 * Plugin Name: eTraining Page Builder
 * Plugin URI:  https://etraintoday.com
 * Description: Unified page builder for eTraining. Build course landing pages and general pages using drag-and-drop content blocks — no coding required.
 * Version:     1.2.0
 * Author:      eTrain Inc.
 * Text Domain: et-page-builder
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ETPB_DIR',     plugin_dir_path( __FILE__ ) );
define( 'ETPB_URL',     plugin_dir_url( __FILE__ ) );
define( 'ETPB_VERSION', '1.2.0' );

require_once ETPB_DIR . 'includes/helpers.php';
require_once ETPB_DIR . 'includes/block-renderer.php';

if ( is_admin() ) {
    require_once ETPB_DIR . 'includes/admin-interface.php';
}

/**
 * Register the FAQ custom post type and FAQ Group taxonomy.
 *
 * Matches the exact post type ('faq') and taxonomy ('faq-group') used
 * on the staging site. If the staging theme already registers these,
 * WordPress will skip this registration — no conflict, no errors.
 */
function etpb_register_faq_post_type() {

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
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => true,
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
            'hierarchical'      => false,
            'public'            => false,
            'show_ui'           => true,
            'show_in_nav_menus' => false,
            'rewrite'           => false,
        ) );
    }
}
add_action( 'init', 'etpb_register_faq_post_type' );

/**
 * Register the post meta field used to store block data on each page.
 * 'etpb_blocks_config' stores the full block configuration as a JSON string.
 */
function etpb_init() {
    register_post_meta( 'page', 'etpb_blocks_config', array(
        'type'              => 'string',
        'sanitize_callback' => 'wp_kses_post',
        'show_in_rest'      => false,
        'single'            => true,
    ) );
}
add_action( 'init', 'etpb_init' );

/**
 * Load frontend styles and scripts on pages that use the page builder.
 * Bootstrap, Font Awesome, and Google Fonts are loaded in templates/header.php
 * so they are available immediately on page load.
 */
function etpb_enqueue_frontend_assets() {
    if ( ! is_page() ) return;

    $post_id = get_queried_object_id();
    $blocks  = get_post_meta( $post_id, 'etpb_blocks_config', true );
    if ( empty( $blocks ) ) return;

    // Main theme CSS — header, footer, navigation, and global styles
    wp_enqueue_style(
        'etpb-theme-main',
        ETPB_URL . 'assets/theme-main.css',
        array(),
        ETPB_VERSION
    );

    // Landing page CSS — hero, trust bar, enrollment card, stats strip etc.
    wp_enqueue_style(
        'etpb-landing-page',
        ETPB_URL . 'assets/landing-page.css',
        array( 'etpb-theme-main' ),
        ETPB_VERSION
    );

    // Frontend CSS — Core Blocks styles (cb-* classes for General and Social Proof blocks)
    wp_enqueue_style(
        'etpb-frontend',
        ETPB_URL . 'assets/frontend.css',
        array( 'etpb-theme-main' ),
        ETPB_VERSION
    );

    // Frontend JS — handles modal open/close for the Course Versions popup
    wp_enqueue_script(
        'etpb-frontend',
        ETPB_URL . 'assets/frontend.js',
        array(),
        ETPB_VERSION,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'etpb_enqueue_frontend_assets' );

/**
 * Load admin CSS and JavaScript only on page edit screens.
 */
function etpb_enqueue_admin_assets( $hook ) {
    if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
        return;
    }
    global $post;
    if ( ! $post || 'page' !== $post->post_type ) {
        return;
    }

    wp_enqueue_media(); // Required for the Media Library picker

    wp_enqueue_style(
        'etpb-admin',
        ETPB_URL . 'assets/admin.css',
        array(),
        ETPB_VERSION
    );

    // jQuery UI Sortable is bundled with WordPress
    wp_enqueue_script( 'jquery-ui-sortable' );

    wp_enqueue_script(
        'etpb-admin',
        ETPB_URL . 'assets/admin.js',
        array( 'jquery', 'jquery-ui-sortable' ),
        ETPB_VERSION,
        true
    );

    // Pass useful data from PHP to our JavaScript
    wp_localize_script( 'etpb-admin', 'etpbData', array(
        'nonce'            => wp_create_nonce( 'etpb_ajax_nonce' ),
        'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
        'pluginUrl'        => ETPB_URL,
        'blockDefinitions' => etpb_get_block_definitions_for_js(),
    ) );
}
add_action( 'admin_enqueue_scripts', 'etpb_enqueue_admin_assets' );

/**
 * AJAX handler: returns the rendered HTML for a newly added block's editor fields.
 * When a user clicks "Add a Section", JavaScript asks this endpoint for the fields HTML.
 */
function etpb_ajax_get_block_fields() {
    check_ajax_referer( 'etpb_ajax_nonce', 'nonce' );

    $type        = sanitize_key( $_POST['block_type'] ?? '' );
    $index       = absint( $_POST['block_index'] ?? 0 );
    $definitions = etpb_get_block_definitions();

    if ( ! isset( $definitions[ $type ] ) ) {
        wp_send_json_error( 'Unknown block type.' );
    }

    ob_start();
    etpb_render_block_editor_row( $index, array( 'type' => $type, 'fields' => array() ), $definitions );
    $html = ob_get_clean();

    wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_etpb_get_block_fields', 'etpb_ajax_get_block_fields' );

/**
 * Use our custom page template for pages that have block configuration saved.
 * Intercepts WordPress's normal template loading and uses our template instead.
 */
function etpb_maybe_use_template( $template ) {
    if ( ! is_page() ) {
        return $template;
    }
    $post_id = get_queried_object_id();
    $blocks  = get_post_meta( $post_id, 'etpb_blocks_config', true );

    if ( ! empty( $blocks ) ) {
        $custom_template = ETPB_DIR . 'templates/page.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }
    return $template;
}
add_filter( 'template_include', 'etpb_maybe_use_template' );
