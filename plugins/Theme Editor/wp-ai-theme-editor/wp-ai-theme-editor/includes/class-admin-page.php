<?php
/**
 * WPAI_Admin_Page — Admin menu registration, asset enqueuing, and page rendering.
 *
 * Assets loaded only on the AI Theme Editor admin page (hook check on page slug).
 * CDN sources used for CodeMirror 5, jsDiff, and diff2html — replace with
 * self-hosted copies in /assets/ for production deployments without internet access.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPAI_Admin_Page {

    public function __construct() {
        add_action( 'admin_menu',             array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
    }

    // ── Menu registration ─────────────────────────────────────────────────────

    public function register_menu(): void {
        add_menu_page(
            'AI Theme Editor',      // page title
            'AI Theme Editor',      // menu label
            'manage_options',       // capability
            'wpai-editor',          // menu slug
            array( $this, 'render_page' ),
            'dashicons-edit-large', // icon
            65                      // position (after Appearance)
        );
    }

    // ── Asset enqueue ─────────────────────────────────────────────────────────

    public function enqueue_assets( string $hook ): void {
        // Only load on our page
        if ( $hook !== 'toplevel_page_wpai-editor' ) return;

        // ── Styles ────────────────────────────────────────────────────────────

        // CodeMirror 5 core + Monokai theme
        wp_enqueue_style(
            'wpai-codemirror',
            'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css',
            array(), '5.65.16'
        );
        wp_enqueue_style(
            'wpai-codemirror-monokai',
            'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/monokai.min.css',
            array( 'wpai-codemirror' ), '5.65.16'
        );

        // diff2html
        wp_enqueue_style(
            'wpai-diff2html',
            'https://cdn.jsdelivr.net/npm/diff2html@3.4.48/bundles/css/diff2html.min.css',
            array(), '3.4.48'
        );

        // Plugin admin styles
        wp_enqueue_style(
            'wpai-admin',
            WPAI_URL . 'assets/admin.css',
            array( 'wpai-codemirror', 'wpai-diff2html' ),
            WPAI_VERSION
        );

        // ── Scripts ───────────────────────────────────────────────────────────

        // CodeMirror 5 core
        wp_enqueue_script(
            'wpai-codemirror-js',
            'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js',
            array( 'jquery' ), '5.65.16', true
        );

        // CodeMirror modes (clike must load before php)
        wp_enqueue_script(
            'wpai-codemirror-clike',
            'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js',
            array( 'wpai-codemirror-js' ), '5.65.16', true
        );
        wp_enqueue_script(
            'wpai-codemirror-php',
            'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js',
            array( 'wpai-codemirror-clike' ), '5.65.16', true
        );
        wp_enqueue_script(
            'wpai-codemirror-css-mode',
            'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js',
            array( 'wpai-codemirror-js' ), '5.65.16', true
        );

        // jsDiff — generates unified diff strings for diff2html
        wp_enqueue_script(
            'wpai-jsdiff',
            'https://cdn.jsdelivr.net/npm/diff@7.0.0/dist/diff.min.js',
            array(), '7.0.0', true
        );

        // diff2html-ui — renders side-by-side HTML diffs
        wp_enqueue_script(
            'wpai-diff2html-js',
            'https://cdn.jsdelivr.net/npm/diff2html@3.4.48/bundles/js/diff2html-ui.min.js',
            array( 'wpai-jsdiff' ), '3.4.48', true
        );

        // Plugin admin JS (depends on all of the above)
        wp_enqueue_script(
            'wpai-admin',
            WPAI_URL . 'assets/admin.js',
            array( 'jquery', 'wpai-codemirror-php', 'wpai-codemirror-css-mode', 'wpai-diff2html-js' ),
            WPAI_VERSION,
            true
        );

        // Pass data to JavaScript
        wp_localize_script( 'wpai-admin', 'wpaiData', array(
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'restUrl' => rest_url( 'wpai/v1/' ),
            'siteUrl' => home_url( '/' ),
            'version' => WPAI_VERSION,
        ) );
    }

    // ── Page render ───────────────────────────────────────────────────────────

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to access this page.' );
        }

        $api_key_missing = empty( WPAI_Settings::get_api_key() );

        include WPAI_DIR . 'templates/admin-page.php';
    }
}
