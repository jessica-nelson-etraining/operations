<?php
/**
 * WPAI_Stream_Handler — Handles the streaming edit endpoint.
 *
 * Uses WordPress admin-ajax (wp_ajax_wpai_stream) rather than the REST API
 * because REST responses are buffered by WordPress before being sent to the
 * browser. Admin-ajax lets us flush chunks directly as they arrive from
 * the Anthropic streaming API, keeping the connection alive and eliminating
 * PHP/HTTP timeouts for large files.
 *
 * Security: nonce verified via check_ajax_referer(), manage_options capability
 * checked explicitly — same as all other plugin endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPAI_Stream_Handler {

    public function __construct() {
        add_action( 'wp_ajax_wpai_stream', array( $this, 'handle' ) );
    }

    public function handle(): void {
        // Verify nonce + capability before touching anything else
        if ( ! check_ajax_referer( 'wpai_stream', 'nonce', false ) ) {
            $this->send_error( 'Security check failed. Please refresh the page and try again.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            $this->send_error( 'You do not have permission to perform this action.' );
        }

        @set_time_limit( 180 );

        $file_key = sanitize_text_field( $_POST['file_key'] ?? '' );
        $message  = sanitize_textarea_field( $_POST['message'] ?? '' );

        if ( empty( $file_key ) || empty( $message ) ) {
            $this->send_error( 'file_key and message are required.' );
        }

        $api_key = WPAI_Settings::get_api_key();
        if ( empty( $api_key ) ) {
            $this->send_error( 'Anthropic API key not configured. Please visit Settings.' );
        }

        // Resolve and read the file
        $file_manager = new WPAI_File_Manager();
        $path         = $file_manager->resolve_path( $file_key );
        if ( is_wp_error( $path ) ) $this->send_error( $path->get_error_message() );

        $content = $file_manager->read_file( $file_key );
        if ( is_wp_error( $content ) ) $this->send_error( $content->get_error_message() );

        $filename = basename( $path );
        $claude   = new WPAI_Claude_API( $api_key );
        $prepared = $claude->prepare_file_content( $content );

        // ── Set SSE headers — flush all buffers so chunks reach the browser ──────

        while ( ob_get_level() ) ob_end_clean();
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'X-Accel-Buffering: no' );  // Disables Nginx proxy buffering
        header( 'Connection: keep-alive' );

        // Confirm the connection is open (browser starts listening)
        $this->sse( array( 'type' => 'start' ) );

        // ── Stream the edit ───────────────────────────────────────────────────────

        $original_content = $content; // save full original for safety check + response

        $result = $claude->stream_edit(
            $prepared['content'],
            $filename,
            $message,
            function ( string $chunk ) {
                $this->sse( array( 'type' => 'delta', 'text' => $chunk ) );
            }
        );

        if ( is_wp_error( $result ) ) {
            $this->sse( array( 'type' => 'error', 'message' => $result->get_error_message() ) );
            exit;
        }

        // Safety check: reject if Claude returned a significantly shorter file
        $original_len = strlen( $original_content );
        $modified_len = strlen( $result['modified_content'] );
        if ( $original_len > 500 && $modified_len < $original_len * 0.8 ) {
            $this->sse( array(
                'type'    => 'error',
                'message' => 'The AI returned an incomplete file (possibly truncated by the model). Please try a simpler or more specific instruction.',
            ) );
            exit;
        }

        // Send the fully parsed result so JS can display the diff
        $this->sse( array(
            'type'             => 'done',
            'modified_content' => $result['modified_content'],
            'summary'          => $result['summary'],
            'changed_lines'    => $result['changed_lines'],
            'original_content' => $original_content,
            'was_truncated'    => $prepared['was_truncated'],
        ) );

        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function sse( array $data ): void {
        echo 'data: ' . wp_json_encode( $data ) . "\n\n";
        flush();
    }

    /**
     * Sends an error SSE event and exits.
     * Called before headers are set — switches to SSE mode first.
     */
    private function send_error( string $message ): void {
        while ( ob_get_level() ) ob_end_clean();
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        $this->sse( array( 'type' => 'error', 'message' => $message ) );
        exit;
    }
}
