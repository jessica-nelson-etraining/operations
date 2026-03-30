<?php
/**
 * WPAI_Rest_API — REST route registration and request handlers.
 *
 * Namespace: wpai/v1
 *
 * All routes:
 *   - Use POST to prevent caching by aggressive host-level plugins
 *   - Require the WordPress REST nonce via X-WP-Nonce header
 *   - Require manage_options capability (admin only)
 *
 * The nonce is generated via wp_create_nonce('wp_rest') and passed to
 * admin.js via wp_localize_script. WordPress REST middleware validates it
 * automatically before permission_callback fires.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPAI_Rest_API {

    const NAMESPACE = 'wpai/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // ── Route registration ────────────────────────────────────────────────────

    public function register_routes(): void {
        $routes = array(
            '/files'              => 'handle_get_files',
            '/read'               => 'handle_read_file',
            '/ask'                => 'handle_ask',
            '/apply'              => 'handle_apply',
            '/backups'            => 'handle_list_backups',
            '/restore'            => 'handle_restore_backup',
            '/preview'            => 'handle_preview',
            '/list-themes'        => 'handle_list_themes',
            '/generate-content'   => 'handle_generate_content',
            '/create-file'        => 'handle_create_file',
            '/create-child-theme' => 'handle_create_child_theme',
        );

        foreach ( $routes as $route => $method ) {
            register_rest_route( self::NAMESPACE, $route, array(
                'methods'             => 'POST',
                'callback'            => array( $this, $method ),
                'permission_callback' => array( $this, 'permission_check' ),
            ) );
        }
    }

    public function permission_check(): bool {
        return current_user_can( 'manage_options' );
    }

    // ── Route handlers ────────────────────────────────────────────────────────

    /**
     * GET /files — Returns the list of editable files for the dropdown.
     */
    public function handle_get_files( WP_REST_Request $request ): WP_REST_Response {
        $file_manager = new WPAI_File_Manager();
        $files        = $file_manager->get_file_list();

        return new WP_REST_Response( array( 'files' => $files ), 200 );
    }

    /**
     * POST /read — Returns the content of a selected file.
     */
    public function handle_read_file( WP_REST_Request $request ): WP_REST_Response {
        $file_key = sanitize_text_field( $request->get_param( 'file_key' ) ?? '' );

        if ( empty( $file_key ) ) {
            return $this->error( 'file_key is required', 400 );
        }

        $file_manager = new WPAI_File_Manager();
        $content      = $file_manager->read_file( $file_key );

        if ( is_wp_error( $content ) ) {
            return $this->from_wp_error( $content );
        }

        return new WP_REST_Response( array(
            'content'      => $content,
            'line_count'   => substr_count( $content, "\n" ) + 1,
            'size_bytes'   => strlen( $content ),
            'was_truncated' => false,
        ), 200 );
    }

    /**
     * POST /ask — Sends the file + user message to Claude and returns the response.
     *
     * Body params:
     *   file_key  string   The file to analyse
     *   message   string   The user's question or instruction
     *   mode      string   'question' or 'edit'
     */
    public function handle_ask( WP_REST_Request $request ): WP_REST_Response {
        // Give Claude extra time to respond (overrides default PHP timeout on most hosts)
        @set_time_limit( 120 );

        $file_key = sanitize_text_field( $request->get_param( 'file_key' ) ?? '' );
        $message  = sanitize_textarea_field( $request->get_param( 'message' ) ?? '' );
        $mode     = sanitize_key( $request->get_param( 'mode' ) ?? 'question' );

        if ( empty( $file_key ) || empty( $message ) ) {
            return $this->error( 'file_key and message are required', 400 );
        }

        if ( ! in_array( $mode, array( 'question', 'edit' ), true ) ) {
            return $this->error( 'mode must be "question" or "edit"', 400 );
        }

        $api_key = WPAI_Settings::get_api_key();
        if ( empty( $api_key ) ) {
            return $this->error( 'Anthropic API key not configured. Please visit Settings to add your key.', 400 );
        }

        // Resolve and read the file
        $file_manager = new WPAI_File_Manager();
        $path         = $file_manager->resolve_path( $file_key );
        if ( is_wp_error( $path ) ) return $this->from_wp_error( $path );

        $content = $file_manager->read_file( $file_key );
        if ( is_wp_error( $content ) ) return $this->from_wp_error( $content );

        $filename = basename( $path );
        $claude   = new WPAI_Claude_API( $api_key );
        $prepared = $claude->prepare_file_content( $content );

        if ( $mode === 'edit' ) {
            $result = $claude->request_edit( $prepared['content'], $filename, $message );
            if ( is_wp_error( $result ) ) return $this->from_wp_error( $result );

            // Safety: reject if Claude returned a significantly shorter file
            $original_len = strlen( $content );
            $modified_len = strlen( $result['modified_content'] );
            if ( $original_len > 500 && $modified_len < $original_len * 0.8 ) {
                return $this->error(
                    'The AI returned an incomplete file (possibly truncated by the model). Please try again, or try a simpler instruction.',
                    500
                );
            }

            return new WP_REST_Response( array(
                'type'             => 'edit',
                'modified_content' => $result['modified_content'],
                'summary'          => $result['summary'],
                'changed_lines'    => $result['changed_lines'],
                'original_content' => $content,       // full original for diffing
                'was_truncated'    => $prepared['was_truncated'],
            ), 200 );

        } else {
            $result = $claude->ask_question( $prepared['content'], $filename, $message );
            if ( is_wp_error( $result ) ) return $this->from_wp_error( $result );

            return new WP_REST_Response( array(
                'type'             => 'answer',
                'answer'           => $result['answer'],
                'highlighted_lines' => $result['highlighted_lines'],
                'was_truncated'    => $prepared['was_truncated'],
            ), 200 );
        }
    }

    /**
     * POST /apply — Backs up the current file and writes the modified content.
     *
     * Body params:
     *   file_key  string
     *   content   string  The modified content to save
     */
    public function handle_apply( WP_REST_Request $request ): WP_REST_Response {
        $file_key = sanitize_text_field( $request->get_param( 'file_key' ) ?? '' );
        $content  = $request->get_param( 'content' ) ?? '';

        if ( empty( $file_key ) ) {
            return $this->error( 'file_key is required', 400 );
        }
        if ( $content === '' ) {
            return $this->error( 'content cannot be empty', 400 );
        }

        $file_manager = new WPAI_File_Manager();

        // Read current file for backup
        $current = $file_manager->read_file( $file_key );
        if ( is_wp_error( $current ) ) {
            return $this->error( 'Could not read current file to create backup: ' . $current->get_error_message(), 500 );
        }

        // Create backup
        $backup_manager = new WPAI_Backup_Manager();
        $backup_path    = $backup_manager->create_backup( $file_key, $current );
        if ( is_wp_error( $backup_path ) ) {
            return $this->error( 'Could not create backup: ' . $backup_path->get_error_message(), 500 );
        }

        // Write new content
        $write = $file_manager->write_file( $file_key, $content );
        if ( is_wp_error( $write ) ) {
            return $this->error( 'File saved but write failed: ' . $write->get_error_message(), 500 );
        }

        return new WP_REST_Response( array(
            'success'     => true,
            'backup_path' => $backup_path,
            'timestamp'   => time(),
        ), 200 );
    }

    /**
     * POST /backups — Lists available backups for a file.
     */
    public function handle_list_backups( WP_REST_Request $request ): WP_REST_Response {
        $file_key = sanitize_text_field( $request->get_param( 'file_key' ) ?? '' );

        if ( empty( $file_key ) ) {
            return $this->error( 'file_key is required', 400 );
        }

        $backup_manager = new WPAI_Backup_Manager();
        $backups        = $backup_manager->list_backups( $file_key );

        return new WP_REST_Response( array( 'backups' => $backups ), 200 );
    }

    /**
     * POST /restore — Restores a specific backup to the live file.
     *
     * Body params:
     *   file_key     string
     *   backup_path  string  Absolute path to the .bak file
     */
    public function handle_restore_backup( WP_REST_Request $request ): WP_REST_Response {
        $file_key    = sanitize_text_field( $request->get_param( 'file_key' ) ?? '' );
        $backup_path = sanitize_text_field( $request->get_param( 'backup_path' ) ?? '' );

        if ( empty( $file_key ) || empty( $backup_path ) ) {
            return $this->error( 'file_key and backup_path are required', 400 );
        }

        $backup_manager = new WPAI_Backup_Manager();
        $result         = $backup_manager->restore_backup( $file_key, $backup_path );

        if ( is_wp_error( $result ) ) {
            return $this->from_wp_error( $result );
        }

        // Return restored content so the UI can reload the editor
        $file_manager = new WPAI_File_Manager();
        $content      = $file_manager->read_file( $file_key );

        return new WP_REST_Response( array(
            'success'          => true,
            'restored_content' => is_wp_error( $content ) ? '' : $content,
        ), 200 );
    }

    /**
     * POST /preview — Creates a live preview token for the modified content.
     *
     * Body params:
     *   file_key  string
     *   content   string  The modified content to preview
     */
    public function handle_preview( WP_REST_Request $request ): WP_REST_Response {
        $file_key = sanitize_text_field( $request->get_param( 'file_key' ) ?? '' );
        $content  = $request->get_param( 'content' ) ?? '';

        if ( empty( $file_key ) ) {
            return $this->error( 'file_key is required', 400 );
        }
        if ( $content === '' ) {
            return $this->error( 'content cannot be empty', 400 );
        }

        // Validate file_key is within allowed scope before storing the content
        $file_manager = new WPAI_File_Manager();
        $path         = $file_manager->resolve_path( $file_key );
        if ( is_wp_error( $path ) ) return $this->from_wp_error( $path );

        $preview_manager = new WPAI_Preview_Manager();
        $preview_url     = $preview_manager->create_preview( $file_key, $content );

        if ( is_wp_error( $preview_url ) ) {
            return $this->from_wp_error( $preview_url );
        }

        return new WP_REST_Response( array(
            'preview_url' => $preview_url,
            'expires_in'  => 30 * MINUTE_IN_SECONDS,
        ), 200 );
    }

    /**
     * POST /list-themes — Returns all installed themes for the parent theme picker.
     */
    public function handle_list_themes( WP_REST_Request $request ): WP_REST_Response {
        $file_manager = new WPAI_File_Manager();
        return new WP_REST_Response( array( 'themes' => $file_manager->list_installed_themes() ), 200 );
    }

    /**
     * POST /generate-content — Asks Claude to generate content for a new file.
     *
     * Body params:
     *   type         string  'file' or 'child-theme'
     *   filename     string  For type=file: e.g. "page-about.php"
     *   description  string  What the file/theme should do
     *   theme_name   string  Active theme name (for file) or parent theme name (for child theme)
     *   parent_slug  string  For type=child-theme: parent theme slug
     *   child_name   string  For type=child-theme: child theme name
     */
    public function handle_generate_content( WP_REST_Request $request ): WP_REST_Response {
        @set_time_limit( 120 );

        $type = sanitize_key( $request->get_param( 'type' ) ?? 'file' );

        $api_key = WPAI_Settings::get_api_key();
        if ( empty( $api_key ) ) {
            return $this->error( 'Anthropic API key not configured. Please visit Settings to add your key.', 400 );
        }

        $claude = new WPAI_Claude_API( $api_key );

        if ( $type === 'child-theme' ) {
            $child_name  = sanitize_text_field( $request->get_param( 'child_name' ) ?? '' );
            $parent_name = sanitize_text_field( $request->get_param( 'theme_name' ) ?? '' );
            $parent_slug = sanitize_key( $request->get_param( 'parent_slug' ) ?? '' );
            $description = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );

            if ( empty( $child_name ) || empty( $parent_slug ) ) {
                return $this->error( 'child_name and parent_slug are required', 400 );
            }

            $result = $claude->generate_child_theme( $child_name, $parent_name, $parent_slug, $description );
            if ( is_wp_error( $result ) ) return $this->from_wp_error( $result );

            return new WP_REST_Response( array( 'files' => $result ), 200 );

        } else {
            $filename    = sanitize_text_field( $request->get_param( 'filename' ) ?? '' );
            $description = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );
            $theme_name  = sanitize_text_field( $request->get_param( 'theme_name' ) ?? wp_get_theme()->get( 'Name' ) );

            if ( empty( $filename ) || empty( $description ) ) {
                return $this->error( 'filename and description are required', 400 );
            }

            $result = $claude->generate_file_content( $filename, $description, $theme_name );
            if ( is_wp_error( $result ) ) return $this->from_wp_error( $result );

            return new WP_REST_Response( array( 'content' => $result ), 200 );
        }
    }

    /**
     * POST /create-file — Creates a new file in the active or child theme.
     *
     * Body params:
     *   destination  string  'theme' or 'child'
     *   filename     string  Relative filename e.g. "page-about.php"
     *   content      string  File content
     */
    public function handle_create_file( WP_REST_Request $request ): WP_REST_Response {
        $destination = sanitize_key( $request->get_param( 'destination' ) ?? 'theme' );
        $filename    = sanitize_text_field( $request->get_param( 'filename' ) ?? '' );
        $content     = $request->get_param( 'content' ) ?? '';

        if ( empty( $filename ) ) {
            return $this->error( 'filename is required', 400 );
        }

        $file_manager = new WPAI_File_Manager();
        $result       = $file_manager->create_file( $destination, $filename, $content );

        if ( is_wp_error( $result ) ) return $this->from_wp_error( $result );

        // $result is the new file key, e.g. "theme:page-about.php"
        return new WP_REST_Response( array(
            'success'  => true,
            'file_key' => $result,
            'label'    => $filename,
        ), 200 );
    }

    /**
     * POST /create-child-theme — Scaffolds a new child theme directory.
     *
     * Body params:
     *   theme_name  string  Human-readable child theme name
     *   files       object  { 'style.css': content, 'functions.php': content, 'index.php': content }
     */
    public function handle_create_child_theme( WP_REST_Request $request ): WP_REST_Response {
        $theme_name = sanitize_text_field( $request->get_param( 'theme_name' ) ?? '' );
        $files      = $request->get_param( 'files' ) ?? array();

        if ( empty( $theme_name ) ) {
            return $this->error( 'theme_name is required', 400 );
        }
        if ( ! is_array( $files ) || empty( $files ) ) {
            return $this->error( 'files must be a non-empty object', 400 );
        }

        // Sanitize: only allow style.css, functions.php, index.php
        $allowed_files = array( 'style.css', 'functions.php', 'index.php' );
        $clean_files   = array();
        foreach ( $allowed_files as $fname ) {
            if ( isset( $files[ $fname ] ) ) {
                $clean_files[ $fname ] = $files[ $fname ];
            }
        }

        $file_manager = new WPAI_File_Manager();
        $result       = $file_manager->create_child_theme( $theme_name, $clean_files );

        if ( is_wp_error( $result ) ) return $this->from_wp_error( $result );

        return new WP_REST_Response( array(
            'success'      => true,
            'theme_slug'   => $result['theme_slug'],
            'activate_url' => $result['activate_url'],
        ), 200 );
    }

    // ── Response helpers ──────────────────────────────────────────────────────

    private function error( string $message, int $status = 400 ): WP_REST_Response {
        return new WP_REST_Response( array( 'error' => $message ), $status );
    }

    private function from_wp_error( WP_Error $error ): WP_REST_Response {
        $data   = $error->get_error_data();
        $status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
        return new WP_REST_Response( array( 'error' => $error->get_error_message() ), $status );
    }
}
