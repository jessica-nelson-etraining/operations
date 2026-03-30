<?php
/**
 * WPAI_Claude_API — All communication with the Anthropic Messages API.
 *
 * Modes:
 *   ask_question()   — Returns a plain-English explanation and line highlights.
 *   request_edit()   — Returns the complete modified file content plus a summary.
 *   stream_edit()    — Streams the edit response chunk by chunk (no timeout).
 *   generate_file_content()  — AI-generates content for a new theme file.
 *   generate_child_theme()   — AI-generates starter files for a new child theme.
 *
 * The API key is retrieved from settings and used only in this class.
 * It is never passed to JavaScript or included in any REST response.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPAI_Claude_API {

    const API_URL   = 'https://api.anthropic.com/v1/messages';
    const API_VER   = '2023-06-01';

    /**
     * Max characters of file content to send.
     * ~28,000 chars ≈ 7,000 tokens, leaving ~3,000 tokens of headroom
     * for the system prompt + instruction within a 10,000 token/min rate limit.
     * Users on higher-tier plans can effectively raise this by loading larger
     * files — the truncation is only a safety cap.
     */
    const MAX_CHARS = 28000;

    private string $api_key;

    public function __construct( string $api_key ) {
        $this->api_key = $api_key;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Ask a question about a file. Returns explanation + line numbers to highlight.
     *
     * @param string $file_content   (potentially truncated) file content
     * @param string $filename       e.g. "functions.php"
     * @param string $question       The user's plain-English question
     * @return array|WP_Error        { answer: string, highlighted_lines: int[] }
     */
    public function ask_question( string $file_content, string $filename, string $question ) {
        $system           = $this->build_system_prompt( 'question', $filename );
        $numbered_content = $this->add_line_numbers( $file_content );
        $messages         = array( array(
            'role'    => 'user',
            'content' => "FILE: {$filename}\n---\n{$numbered_content}\n---\nQUESTION: {$question}",
        ) );

        $raw = $this->call_api( $messages, $system );
        if ( is_wp_error( $raw ) ) return $raw;

        $text        = $raw['content'][0]['text'] ?? '';
        $highlighted = array();

        preg_match_all( '/\[Lines?\s+(\d+)(?:\s*[-–]\s*(\d+))?\]/i', $text, $matches, PREG_SET_ORDER );
        foreach ( $matches as $m ) {
            $start = (int) $m[1];
            $end   = isset( $m[2] ) && $m[2] !== '' ? (int) $m[2] : $start;
            for ( $l = $start; $l <= min( $end, $start + 50 ); $l++ ) {
                $highlighted[] = $l;
            }
        }

        return array(
            'answer'            => $text,
            'highlighted_lines' => array_values( array_unique( $highlighted ) ),
        );
    }

    /**
     * Request a code edit. Returns the complete modified file content.
     * Use stream_edit() instead when called from the streaming endpoint.
     *
     * @param string $file_content
     * @param string $filename
     * @param string $instruction
     * @return array|WP_Error  { modified_content, summary, changed_lines[] }
     */
    public function request_edit( string $file_content, string $filename, string $instruction ) {
        $system   = $this->build_system_prompt( 'edit', $filename );
        $messages = array( array(
            'role'    => 'user',
            'content' => "FILE: {$filename}\n---\n{$file_content}\n---\nINSTRUCTION: {$instruction}",
        ) );

        $raw = $this->call_api( $messages, $system );
        if ( is_wp_error( $raw ) ) return $raw;

        $text = $raw['content'][0]['text'] ?? '';
        $ext  = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

        return $this->parse_edit_response( $text, $ext );
    }

    /**
     * Streams a code edit request to Claude, calling $on_chunk() for each text
     * delta as it arrives. Eliminates timeouts because the connection stays alive
     * with data flowing continuously — no waiting for a complete response.
     *
     * Falls back to the blocking request_edit() if curl is unavailable.
     *
     * @param string   $file_content
     * @param string   $filename
     * @param string   $instruction
     * @param callable $on_chunk     Called with each string chunk as it arrives
     * @return array|WP_Error        { modified_content, summary, changed_lines }
     */
    public function stream_edit( string $file_content, string $filename, string $instruction, callable $on_chunk ) {
        if ( ! function_exists( 'curl_init' ) ) {
            return $this->request_edit( $file_content, $filename, $instruction );
        }

        $system    = $this->build_system_prompt( 'edit', $filename );
        $ext       = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        $full_text = '';
        $raw_body  = ''; // captures non-SSE responses (e.g. HTTP 429 JSON error)
        $error_msg = '';

        $body = wp_json_encode( array(
            'model'      => WPAI_Settings::get_model(),
            'max_tokens' => WPAI_Settings::get_max_tokens(),
            'system'     => $system,
            'stream'     => true,
            'messages'   => array( array(
                'role'    => 'user',
                'content' => "FILE: {$filename}\n---\n{$file_content}\n---\nINSTRUCTION: {$instruction}",
            ) ),
        ) );

        $ch = curl_init( self::API_URL );
        curl_setopt_array( $ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'x-api-key: ' . $this->api_key,
                'anthropic-version: ' . self::API_VER,
            ),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => WPAI_Settings::get_timeout(),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_WRITEFUNCTION  => function ( $ch, $data ) use ( $on_chunk, &$full_text, &$raw_body, &$error_msg ) {
                $raw_body .= $data;
                foreach ( explode( "\n", $data ) as $line ) {
                    $line = trim( $line );
                    if ( strpos( $line, 'data: ' ) !== 0 ) continue;

                    $parsed = json_decode( substr( $line, 6 ), true );
                    if ( ! is_array( $parsed ) ) continue;

                    $type = $parsed['type'] ?? '';

                    if ( $type === 'content_block_delta' && isset( $parsed['delta']['text'] ) ) {
                        $chunk      = $parsed['delta']['text'];
                        $full_text .= $chunk;
                        call_user_func( $on_chunk, $chunk );
                    }

                    if ( $type === 'error' ) {
                        $error_msg = $parsed['error']['message'] ?? 'Streaming API error';
                    }
                }
                return strlen( $data );
            },
        ) );

        curl_exec( $ch );
        $curl_error = curl_error( $ch );
        $http_code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( $curl_error ) {
            return new WP_Error( 'curl_error', 'Could not reach the Claude API: ' . $curl_error, array( 'status' => 502 ) );
        }

        // Handle non-200 HTTP responses (e.g. 429 rate limit, 401 auth error).
        // These come back as plain JSON, not SSE, so $full_text will be empty.
        if ( $http_code !== 200 && $http_code !== 0 ) {
            $json = json_decode( $raw_body, true );
            $msg  = $json['error']['message'] ?? "API error (HTTP {$http_code})";

            if ( $http_code === 429 ) {
                return new WP_Error(
                    'rate_limit',
                    'Rate limit reached: ' . $msg . ' — Please wait 60 seconds and try again. If this happens often, upgrade your Anthropic plan at console.anthropic.com.',
                    array( 'status' => 429 )
                );
            }

            return new WP_Error( 'api_error', "Claude API error (HTTP {$http_code}): {$msg}", array( 'status' => 502 ) );
        }

        if ( $error_msg ) {
            return new WP_Error( 'api_error', 'Claude API error: ' . $error_msg, array( 'status' => 502 ) );
        }
        if ( empty( $full_text ) ) {
            return new WP_Error( 'empty_response', 'Claude returned an empty response. Please try again.', array( 'status' => 502 ) );
        }

        return $this->parse_edit_response( $full_text, $ext );
    }

    /**
     * Generates content for a brand-new theme file.
     *
     * @param string $filename     e.g. "page-about.php"
     * @param string $description  What the user wants the file to do
     * @param string $theme_name   Active theme name (for context)
     * @return string|WP_Error     File content on success
     */
    public function generate_file_content( string $filename, string $description, string $theme_name ) {
        $ext    = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        $type   = $ext === 'css' ? 'CSS stylesheet' : 'PHP WordPress template file';
        $system = <<<PROMPT
You are a WordPress theme developer. Generate a clean, well-commented {$type} for a WordPress theme called "{$theme_name}".

RULES:
- Write production-ready code appropriate for the file type and name.
- For PHP files: include the standard WordPress header comment, use get_header()/get_footer() where appropriate, and follow WordPress coding standards.
- For CSS files: include an organized structure with clear section comments.
- Do NOT include any explanation, markdown, or fences — return ONLY the raw file content.
- Keep it practical: generate real, usable code based on the description, not placeholder Lorem ipsum.
PROMPT;

        $raw = $this->call_api(
            array( array(
                'role'    => 'user',
                'content' => "FILE: {$filename}\nDESCRIPTION: {$description}\n\nGenerate the complete file content.",
            ) ),
            $system
        );

        if ( is_wp_error( $raw ) ) return $raw;

        $text = $raw['content'][0]['text'] ?? '';
        $text = preg_replace( '/^```(?:php|css|)\s*\n/i', '', $text );
        $text = preg_replace( '/\n```\s*$/', '', $text );

        return $text;
    }

    /**
     * Generates the three starter files for a new child theme.
     *
     * @param string $child_theme_name
     * @param string $parent_theme_name
     * @param string $parent_theme_slug
     * @param string $description
     * @return array|WP_Error  { 'style.css' => content, 'functions.php' => content, 'index.php' => content }
     */
    public function generate_child_theme( string $child_theme_name, string $parent_theme_name, string $parent_theme_slug, string $description ) {
        $system = <<<PROMPT
You are a WordPress theme developer. Generate starter files for a new WordPress child theme.

RULES:
- Return ONLY valid JSON with exactly three keys: "style.css", "functions.php", "index.php".
- Each value is the complete raw file content as a string.
- style.css must include the required WordPress theme header with "Template: {$parent_theme_slug}".
- functions.php must enqueue the parent theme stylesheet using wp_enqueue_scripts.
- index.php should be a minimal fallback.
- Do not include any markdown, explanation, or wrapper — just the JSON object.
PROMPT;

        $raw = $this->call_api(
            array( array(
                'role'    => 'user',
                'content' => "Child theme name: {$child_theme_name}\nParent theme: {$parent_theme_name} (slug: {$parent_theme_slug})\nDescription: {$description}\n\nGenerate the three starter files.",
            ) ),
            $system
        );

        if ( is_wp_error( $raw ) ) return $raw;

        $text  = $raw['content'][0]['text'] ?? '';
        $text  = preg_replace( '/^```(?:json)?\s*\n/i', '', trim( $text ) );
        $text  = preg_replace( '/\n```\s*$/', '', $text );
        $files = json_decode( $text, true );

        if ( ! is_array( $files ) || empty( $files['style.css'] ) ) {
            return new WP_Error( 'parse_error', 'Could not parse the AI-generated child theme files. Please try again.', array( 'status' => 500 ) );
        }

        return $files;
    }

    /**
     * Truncates file content to fit within Claude's context window and
     * within the user's API rate limit (input tokens per minute).
     *
     * Files larger than MAX_CHARS are truncated: top 40% + bottom 20% are kept;
     * the middle is replaced with a comment explaining what was omitted.
     *
     * @param string $content
     * @return array { content, was_truncated, original_lines, truncated_range? }
     */
    public function prepare_file_content( string $content ): array {
        if ( strlen( $content ) <= self::MAX_CHARS ) {
            return array(
                'content'        => $content,
                'was_truncated'  => false,
                'original_lines' => substr_count( $content, "\n" ) + 1,
            );
        }

        $lines      = explode( "\n", $content );
        $total      = count( $lines );
        $head_count = (int) ( $total * 0.4 );
        $tail_count = (int) ( $total * 0.2 );
        $mid_start  = $head_count + 1;
        $mid_end    = $total - $tail_count;

        $truncated = array_merge(
            array_slice( $lines, 0, $head_count ),
            array( "/* [TRUNCATED: lines {$mid_start}–{$mid_end} omitted to fit AI context window. These lines were not sent and will not be modified.] */" ),
            array_slice( $lines, $total - $tail_count )
        );

        return array(
            'content'         => implode( "\n", $truncated ),
            'was_truncated'   => true,
            'original_lines'  => $total,
            'truncated_range' => array( $mid_start, $mid_end ),
        );
    }

    /**
     * Quick connectivity test — sends a minimal message to verify the API key.
     *
     * @return bool
     */
    public function test_connection(): bool {
        $raw = $this->call_api(
            array( array( 'role' => 'user', 'content' => 'Reply with only the word "ok".' ) ),
            'You are a test responder. Reply with only the word "ok".'
        );
        return ! is_wp_error( $raw );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function call_api( array $messages, string $system ) {
        $body = wp_json_encode( array(
            'model'      => WPAI_Settings::get_model(),
            'max_tokens' => WPAI_Settings::get_max_tokens(),
            'system'     => $system,
            'messages'   => $messages,
        ) );

        $response = wp_remote_post( self::API_URL, array(
            'headers' => array(
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => self::API_VER,
            ),
            'body'    => $body,
            'timeout' => WPAI_Settings::get_timeout(),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_request_failed', 'Could not reach the Claude API: ' . $response->get_error_message(), array( 'status' => 502 ) );
        }

        $status       = (int) wp_remote_retrieve_response_code( $response );
        $decoded_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status === 429 ) {
            $api_msg = $decoded_body['error']['message'] ?? 'Rate limit exceeded.';
            return new WP_Error(
                'rate_limit',
                'Rate limit reached: ' . $api_msg . ' — Please wait 60 seconds and try again. If this happens often, upgrade your Anthropic plan at console.anthropic.com.',
                array( 'status' => 429 )
            );
        }

        if ( $status !== 200 ) {
            $api_msg = $decoded_body['error']['message'] ?? 'Unknown API error';
            return new WP_Error( 'api_error', "Claude API error (HTTP {$status}): {$api_msg}", array( 'status' => 502 ) );
        }

        return $decoded_body;
    }

    private function build_system_prompt( string $mode, string $filename ): string {
        if ( $mode === 'question' ) {
            return <<<PROMPT
You are a WordPress code assistant helping a non-technical site owner understand their theme and plugin files.

You are currently reviewing the file: {$filename}

RULES:
- Explain in plain English. Avoid jargon. If a technical term is necessary, define it briefly in the same sentence.
- When referencing specific parts of the code, always include line numbers using the format [Line 42] or [Lines 42-47]. This is important — the UI will highlight those lines.
- Do NOT suggest changes unless explicitly asked. This is an explanation-only request.
- If the question cannot be answered from this file alone, say so clearly and name the file the user should look at next.
- Keep answers concise — no more than 3–4 paragraphs unless the question is genuinely complex.

Return plain text. Do not wrap your answer in code blocks or markdown headers.
PROMPT;
        }

        $fence = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) === 'css' ? 'css' : 'php';

        return <<<PROMPT
You are a WordPress code editor. The user will give you a file and an instruction describing a change to make.

You are currently editing the file: {$filename}

RULES:
- Return the COMPLETE modified file content — every single line. Do not truncate, summarize, or omit any part.
- Make ONLY the changes described in the instruction. Do not refactor, reformat, rename variables, add comments, or improve anything else.
- Preserve all existing comments, blank lines, indentation, and code style exactly as found.
- If the instruction is ambiguous, make the most conservative interpretation and explain it in SUMMARY.
- If the requested change cannot be found in this file, say so in SUMMARY and return the original file unchanged.
- If the change would break functionality or remove a critical WordPress hook, refuse it, explain why in SUMMARY, and return the file unchanged.

You MUST respond with EXACTLY this format and nothing else:

SUMMARY: [one or two sentences: what was changed, or why nothing was changed]
CHANGED_LINES: [comma-separated line numbers that were modified, e.g. "42,43,107" — or "none" if unchanged]
```{$fence}
[complete file content here — every line]
```
PROMPT;
    }

    private function parse_edit_response( string $raw_response, string $ext ) {
        $summary = 'Changes applied.';
        if ( preg_match( '/^SUMMARY:\s*(.+)/m', $raw_response, $m ) ) {
            $summary = trim( $m[1] );
        }

        $changed_lines = array();
        if ( preg_match( '/^CHANGED_LINES:\s*(.+)/m', $raw_response, $m ) ) {
            $line_str = trim( $m[1] );
            if ( $line_str !== 'none' ) {
                foreach ( explode( ',', $line_str ) as $l ) {
                    $l = trim( $l );
                    if ( is_numeric( $l ) ) {
                        $changed_lines[] = (int) $l;
                    }
                }
            }
        }

        // Find the opening code fence and get its position.
        if ( ! preg_match( '/```(?:php|css|)\s*\n/i', $raw_response, $fence_match, PREG_OFFSET_CAPTURE ) ) {
            return new WP_Error(
                'parse_error',
                'Claude did not return a code block. Try a more specific instruction, or increase Max Output Tokens in Settings.',
                array( 'status' => 500 )
            );
        }

        $code_start = $fence_match[0][1] + strlen( $fence_match[0][0] );

        // Use strrpos to find the LAST closing ``` — greedy, avoids false matches
        // when the file content itself contains ``` sequences (e.g. CSS comments).
        $last_fence = strrpos( $raw_response, '```', $code_start );

        if ( $last_fence !== false && $last_fence > $code_start ) {
            $modified_content = substr( $raw_response, $code_start, $last_fence - $code_start );
        } else {
            // No closing fence — response was likely cut off by max_tokens.
            // Use whatever was returned and warn the user via the summary.
            $modified_content = substr( $raw_response, $code_start );
            $summary         .= ' Note: the response may have been truncated — check the diff carefully and increase Max Output Tokens in Settings if the file looks incomplete.';
        }

        return array(
            'modified_content' => rtrim( $modified_content ),
            'summary'          => $summary,
            'changed_lines'    => $changed_lines,
        );
    }

    private function add_line_numbers( string $content ): string {
        $lines    = explode( "\n", $content );
        $total    = count( $lines );
        $pad      = strlen( (string) $total );
        $numbered = array();
        foreach ( $lines as $i => $line ) {
            $numbered[] = str_pad( (string) ( $i + 1 ), $pad, ' ', STR_PAD_LEFT ) . ': ' . $line;
        }
        return implode( "\n", $numbered );
    }
}
