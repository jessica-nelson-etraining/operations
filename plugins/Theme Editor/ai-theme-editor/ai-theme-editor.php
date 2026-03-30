<?php
/**
 * Plugin Name: AI Theme Editor
 * Plugin URI:  https://etraintoday.com
 * Description: Browse, understand, and edit your active theme and plugin files using plain-English
 *              instructions powered by Claude AI. No coding knowledge required.
 *              Ask questions about your code, request specific edits, preview changes live,
 *              and apply them with one click — all with automatic backups.
 * Version:     1.1.0
 * Author:      eTrain Inc.
 * Text Domain: ai-theme-editor
 *
 * HOW THIS PLUGIN WORKS:
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. Adds an "AI Theme Editor" page to your WordPress admin dashboard.
 * 2. Select any theme PHP/CSS file or allowed plugin file from a dropdown.
 * 3. Ask questions about the file in plain English — the AI explains it and
 *    highlights the relevant lines in the code viewer.
 * 4. Switch to Edit mode, describe a change — the AI streams the response live
 *    so you see progress in real time, then shows a diff to review.
 * 5. Preview changes live in a new tab before applying anything.
 * 6. Click Apply — a backup is created automatically, then the file is saved.
 * 7. Restore any backup at any time from the Backups dropdown.
 * 8. Create new theme files or child themes directly from the editor.
 *
 * SECURITY:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Requires manage_options capability (admins only).
 * - All REST endpoints verified via WordPress REST nonce.
 * - Streaming endpoint verified via separate wpai_stream nonce.
 * - File access restricted to the active theme and explicitly allowed plugins.
 * - Path traversal prevention via realpath() validation.
 * - API key encrypted at rest using AUTH_KEY-derived encryption.
 * - Backup and preview directories blocked from web access via .htaccess.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Plugin constants ──────────────────────────────────────────────────────────
define( 'WPAI_DIR',         plugin_dir_path( __FILE__ ) );
define( 'WPAI_URL',         plugin_dir_url( __FILE__ ) );
define( 'WPAI_VERSION',     '1.1.0' );
define( 'WPAI_BACKUP_DIR',  WP_CONTENT_DIR . '/ai-editor-backups/' );
define( 'WPAI_PREVIEW_DIR', WP_CONTENT_DIR . '/ai-editor-previews/' );

// ── Load supporting files ─────────────────────────────────────────────────────
require_once WPAI_DIR . 'includes/class-settings.php';
require_once WPAI_DIR . 'includes/class-file-manager.php';
require_once WPAI_DIR . 'includes/class-backup-manager.php';
require_once WPAI_DIR . 'includes/class-claude-api.php';
require_once WPAI_DIR . 'includes/class-preview-manager.php';
require_once WPAI_DIR . 'includes/class-rest-api.php';
require_once WPAI_DIR . 'includes/class-stream-handler.php';

if ( is_admin() ) {
    require_once WPAI_DIR . 'includes/class-admin-page.php';
}

// ── Activation / Deactivation ─────────────────────────────────────────────────
register_activation_hook( __FILE__, 'wpai_activate' );
function wpai_activate() {
    wp_mkdir_p( WPAI_BACKUP_DIR );
    if ( ! file_exists( WPAI_BACKUP_DIR . '.htaccess' ) ) {
        file_put_contents( WPAI_BACKUP_DIR . '.htaccess', "Deny from all\n" );
    }

    wp_mkdir_p( WPAI_PREVIEW_DIR );
    if ( ! file_exists( WPAI_PREVIEW_DIR . '.htaccess' ) ) {
        file_put_contents( WPAI_PREVIEW_DIR . '.htaccess', "Deny from all\n" );
    }

    if ( ! wp_next_scheduled( 'wpai_prune_backups' ) ) {
        wp_schedule_event( time(), 'daily', 'wpai_prune_backups' );
    }
}

register_deactivation_hook( __FILE__, 'wpai_deactivate' );
function wpai_deactivate() {
    wp_clear_scheduled_hook( 'wpai_prune_backups' );
}

// ── WP-Cron: prune old backups ────────────────────────────────────────────────
add_action( 'wpai_prune_backups', 'wpai_run_backup_prune' );
function wpai_run_backup_prune() {
    $retention      = WPAI_Settings::get_backup_retention();
    $backup_manager = new WPAI_Backup_Manager();
    $backup_manager->prune_old_backups( $retention );
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'wpai_init' );
function wpai_init() {
    new WPAI_Settings();
    new WPAI_Rest_API();
    new WPAI_Stream_Handler(); // Streaming edit endpoint (wp_ajax_wpai_stream)
    new WPAI_Preview_Manager(); // Must run on all requests — handles frontend preview intercept

    if ( is_admin() ) {
        new WPAI_Admin_Page();
    }
}
