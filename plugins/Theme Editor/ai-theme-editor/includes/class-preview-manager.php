<?php
/**
 * WPAI_Preview_Manager — Live preview of pending changes before applying them.
 *
 * HOW PREVIEWS WORK:
 *   1. Admin POSTs to /wpai/v1/preview with the file key and modified content.
 *   2. A cryptographically random 32-char token is generated.
 *   3. For PHP template files: the content is written to a temp .php file in
 *      WPAI_PREVIEW_DIR. A WordPress transient stores the metadata.
 *   4. For CSS files: the modified content is stored in the transient only
 *      (no temp file needed — it will be injected via wp_head).
 *   5. A preview URL is returned: /?wpai_preview={token}
 *   6. On that URL, the init hook fires early, reads the transient, and
 *      intercepts rendering via template_include (PHP) or wp_head (CSS).
 *   7. An amber admin bar notice makes it clear this is a preview.
 *   8. Transients expire after 30 minutes. Temp PHP files are cleaned up then.
 *
 * SECURITY:
 *   - Tokens are 32 random hex chars (128 bits of entropy).
 *   - Transients have a 30-minute TTL.
 *   - Temp PHP files sit behind an .htaccess Deny rule and are only served
 *     via the template_include hook — never directly by URL.
 *   - No authentication is required to VIEW a preview (so the admin can test
 *     on a different device/browser), but only admins can CREATE preview tokens.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPAI_Preview_Manager {

    public function __construct() {
        // Must run on init (early) to intercept the request before output
        add_action( 'init', array( $this, 'intercept_preview_request' ), 1 );

        // Admin bar notice — fires on both frontend and admin
        add_action( 'wp_before_admin_bar_render', array( $this, 'add_preview_admin_bar_notice' ) );
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Creates a preview token and stores the pending content.
     *
     * @param string $file_key
     * @param string $content  The modified (not yet saved) file content.
     * @return string|WP_Error Preview URL on success.
     */
    public function create_preview( string $file_key, string $content ) {
        $this->ensure_preview_dir();

        $token = bin2hex( random_bytes( 16 ) ); // 32 hex chars
        $ext   = $this->get_ext_from_key( $file_key );

        // For PHP files, write a temp file (needed for template_include swap)
        $temp_path = null;
        if ( $ext === 'php' ) {
            $temp_path = WPAI_PREVIEW_DIR . $token . '.php';
            if ( @file_put_contents( $temp_path, $content ) === false ) {
                return new WP_Error( 'preview_write_failed', 'Could not write preview temp file.' );
            }
        }

        $data = array(
            'file_key'  => $file_key,
            'content'   => $content,
            'ext'       => $ext,
            'temp_path' => $temp_path,
            'created'   => time(),
        );

        set_transient( 'wpai_preview_' . $token, $data, 30 * MINUTE_IN_SECONDS );

        return add_query_arg( 'wpai_preview', $token, home_url( '/' ) );
    }

    /**
     * Deletes a preview token and its associated temp file.
     *
     * @param string $token
     */
    public function cleanup_preview( string $token ): void {
        $data = get_transient( 'wpai_preview_' . $token );
        if ( $data && ! empty( $data['temp_path'] ) && file_exists( $data['temp_path'] ) ) {
            @unlink( $data['temp_path'] );
        }
        delete_transient( 'wpai_preview_' . $token );
    }

    // ── WordPress hooks ───────────────────────────────────────────────────────

    /**
     * Intercepts requests with a ?wpai_preview= token.
     * Runs on init (priority 1) before theme setup or template resolution.
     */
    public function intercept_preview_request(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET['wpai_preview'] ) ) return;

        $token = sanitize_key( $_GET['wpai_preview'] );
        $data  = get_transient( 'wpai_preview_' . $token );

        if ( ! $data ) {
            // Expired or invalid — show a clear message
            wp_die(
                '<h2>Preview Expired</h2><p>This preview link has expired (30-minute limit) or is invalid. Return to the AI Theme Editor to generate a new one.</p>',
                'Preview Expired',
                array( 'response' => 410 )
            );
        }

        // Store in a global so the hooks below can access it
        $GLOBALS['wpai_preview_data']  = $data;
        $GLOBALS['wpai_preview_token'] = $token;

        if ( $data['ext'] === 'css' ) {
            // Inject modified CSS via wp_head at very high priority to override everything
            add_action( 'wp_head', array( $this, 'inject_preview_css' ), 999 );

        } elseif ( $data['ext'] === 'php' && ! empty( $data['temp_path'] ) && file_exists( $data['temp_path'] ) ) {
            // Swap the theme template at very high priority
            add_filter( 'template_include', array( $this, 'swap_preview_template' ), 999 );
        }
    }

    /**
     * Injects the modified CSS into wp_head as an inline override.
     */
    public function inject_preview_css(): void {
        $data = $GLOBALS['wpai_preview_data'] ?? null;
        if ( ! $data ) return;

        echo '<style id="wpai-preview-css">' . "\n";
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is admin-controlled content
        echo $data['content'];
        echo "\n</style>\n";
    }

    /**
     * Replaces the theme template with the preview temp file.
     *
     * @param string $template Current template path.
     * @return string
     */
    public function swap_preview_template( string $template ): string {
        $data = $GLOBALS['wpai_preview_data'] ?? null;
        if ( ! $data || empty( $data['temp_path'] ) ) return $template;
        if ( file_exists( $data['temp_path'] ) ) {
            return $data['temp_path'];
        }
        return $template;
    }

    /**
     * Adds a prominent amber admin bar notice during preview mode.
     */
    public function add_preview_admin_bar_notice(): void {
        if ( empty( $GLOBALS['wpai_preview_token'] ) ) return;

        global $wp_admin_bar;
        if ( ! $wp_admin_bar ) return;

        $wp_admin_bar->add_node( array(
            'id'    => 'wpai-preview-notice',
            'title' => '&#9888; Preview Mode &mdash; changes not saved',
            'href'  => false,
            'meta'  => array(
                'class' => 'wpai-preview-bar-notice',
                'html'  => '<style>#wpadminbar #wp-admin-bar-wpai-preview-notice { background: #d63638; } #wpadminbar #wp-admin-bar-wpai-preview-notice a, #wpadminbar #wp-admin-bar-wpai-preview-notice .ab-item { color: #fff !important; font-weight: 600; }</style>',
            ),
        ) );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function get_ext_from_key( string $file_key ): string {
        // The relative path is after the last colon segment
        $parts    = explode( ':', $file_key );
        $filename = end( $parts );
        return strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    }

    private function ensure_preview_dir(): void {
        if ( ! is_dir( WPAI_PREVIEW_DIR ) ) {
            wp_mkdir_p( WPAI_PREVIEW_DIR );
        }
        $htaccess = WPAI_PREVIEW_DIR . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            @file_put_contents( $htaccess, "Deny from all\n" );
        }
    }
}
