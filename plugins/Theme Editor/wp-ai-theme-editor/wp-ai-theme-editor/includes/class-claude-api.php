<?php
/**
 * WPAI_Claude_API — All communication with the Anthropic Messages API.
 *
 * Two modes:
 *   ask_question()  — Returns a plain-English explanation and line highlights.
 *   request_edit()  — Returns the complete modified file content plus a summary.
 *
 * The API key is retrieved from settings and used only in this class.
 * It is never passed to JavaScript or included in any REST response.
 *
 * Large file handling:
 *   prepare_file_content() truncates files >150k chars before sending to Claude,
 *   preserving the head (40%) and tail (20%) of the file and inserting a
 *   truncation comment in the middle. The REST handler flags this to the UI.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPAI_Claude_API {

    const API_URL    = 'https://api.anthropic.com/v1/messages';
    const API_VER    = '2023-06-01';
    const MAX_CHARS  = 150000; // ~37k tokens — leaves headroom for output

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
        $system   = $this->build_system_prompt( 'question', $filename );
        // Prepend explicit line numbers so Claude references them accurately
        $numbered_content = $this->add_line_numbers( $file_content );
        $messages = array( array(
            'role'    => 'user',
            'content' => "FILE: {$filename}\n---\n{$numbered_content}\n---\nQUESTION: {$question}",
        ) );

        $raw = $this->call_api( $messages, $system );
        if ( is_wp_error( $raw ) ) return $raw;

        $text = $raw['content'][0]['text'] ?? '';

        // Extract line numbers from patterns like [Line 42] or [Lines 42-47]
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
     *
     * @param string $file_content   (potentially truncated) file content
     * @param string $filename
     * @param string $instruction    The user's edit instruction
     * @return array|WP_Error        { modified_content, summary, changed_lines[] }
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
     * Truncates file content to fit within Claude's context window.
     * Preserves the top 40% and bottom 20% of the file; replaces the
     * middle 40% with a truncation comment.
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
     * Generates content for a brand-new theme file.
     *
     * @param string $filename     e.g. "page-about.php" or "template-parts/hero.php"
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

        // Strip any accidental fences Claude added despite instructions
        $text = preg_replace( '/^```(?:php|css|)\s*\n/i', '', $text );
        $text = preg_replace( '/\n```\s*$/', '', $text );

        return $text;
    }

    /**
     * Generates the three starter files for a new child theme.
     *
     * @param string $child_theme_name    Human-readable child theme name
     * @param string $parent_theme_name   Human-readable parent theme name
     * @param string $parent_theme_slug   Folder slug of the parent theme
     * @param string $description         Optional description of customisations planned
     * @return array|WP_Error  { 'style.css' => content, 'functions.php' => content, 'index.php' => content }
     */
    public function generate_child_theme( string $child_theme_name, string $parent_theme_name, string $parent_theme_slug, string $description ) {
        $system = <<<PROMPT
You are a WordPress theme developer. Generate starter files for a new WordPress child theme.

RULES:
- Return ONLY valid JSON with exactly three keys: "style.css", "functions.php", "index.php".
- Each value is the complete raw file content as a string (no additional encoding).
- style.css must include the required WordPress theme header with "Template: {parent_slug}" pointing to the parent theme.
- functions.php must enqueue the parent theme stylesheet using wp_enqueue_scripts and wp_enqueue_style.
- index.php should be a minimal fallback that redirects to the parent index.
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

        $text = $raw['content'][0]['text'] ?? '';

        // Strip fences if Claude wrapped in ```json
        $text = preg_replace( '/^```(?:json)?\s*\n/i', '', trim( $text ) );
        $text = preg_replace( '/\n```\s*$/', '', $text );

        $files = json_decode( $text, true );
        if ( ! is_array( $files ) || empty( $files['style.css'] ) ) {
            return new WP_Error(
                'parse_error',
                'Could not parse the AI-generated child theme files. Please try again.',
                array( 'status' => 500 )
            );
        }

        return $files;
    }

    /**
     * Quick connectivity test — sends a minimal message to verify the API key.
     * Used by the settings page save handler.
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

    /**
     * Makes the HTTP request to the Anthropic Messages API via wp_remote_post.
     *
     * @param array  $messages Claude messages array
     * @param string $system   System prompt
     * @return array|WP_Error  Decoded response body on success.
     */
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
            return new WP_Error(
                'api_request_failed',
                'Could not reach the Claude API: ' . $response->get_error_message(),
                array( 'status' => 502 )
            );
        }

        $status       = wp_remote_retrieve_response_code( $response );
        $decoded_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( (int) $status !== 200 ) {
            $api_msg = $decoded_body['error']['message'] ?? 'Unknown API error';
            return new WP_Error(
                'api_error',
                "Claude API error (HTTP {$status}): {$api_msg}",
                array( 'status' => 502 )
            );
        }

        return $decoded_body;
    }

    /**
     * Builds the system prompt for question or edit mode.
     */
    private function build_system_prompt( string $mode, string $filename ): string {
        if ( $mode === 'question' ) {
            return <<<PROMPT
You are a WordPress code assistant helping a non-technical site owner understand their theme and plugin files.

You are currently reviewing the file: {$filename}

RULES:
- Explain in plain English. Avoid jargon. If a technical term is necessary, define it briefly in the same sentence.
- When referencing specific parts of the code, always include line numbers using the format [Line 42] or [Lines 42-47]. This is important — the UI will highlight those lines.
- Do NOT suggest changes unless explicitly asked. This is an explanation-only request.
- If the question cannot be answered from this file alone (e.g. the logic is in another file), say so clearly and name the file the user should look at next.
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

    /**
     * Parses Claude's edit response to extract the modified file content,
     * summary, and changed line numbers.
     *
     * @param string $raw_response  Full text response from Claude
     * @param string $ext           'php' or 'css'
     * @return array|WP_Error
     */
    private function parse_edit_response( string $raw_response, string $ext ) {
        // Extract SUMMARY
        $summary = 'Changes applied.';
        if ( preg_match( '/^SUMMARY:\s*(.+)/m', $raw_response, $m ) ) {
            $summary = trim( $m[1] );
        }

        // Extract CHANGED_LINES
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

        // Extract code block — accept ```php, ```css, or plain ``` fences
        if ( preg_match( '/```(?:php|css|)\s*\n([\s\S]*?)```\s*$/s', $raw_response, $m ) ) {
            $modified_content = $m[1];
        } else {
            return new WP_Error(
                'parse_error',
                'Claude did not return a valid code block. This sometimes happens with large files — please try again.',
                array( 'status' => 500 )
            );
        }

        return array(
            'modified_content' => $modified_content,
            'summary'          => $summary,
            'changed_lines'    => $changed_lines,
        );
    }

    /**
     * Prepends each line with its 1-based line number so Claude references
     * the correct line numbers when answering questions.
     * e.g. "1: <?php\n2: \n3: body {"
     *
     * Only used in question mode — edit mode needs clean code returned.
     */
    private function add_line_numbers( string $content ): string {
        $lines    = explode( "\n", $content );
        $total    = count( $lines );
        $pad      = strlen( (string) $total ); // right-align numbers for readability
        $numbered = array();
        foreach ( $lines as $i => $line ) {
            $numbered[] = str_pad( (string) ( $i + 1 ), $pad, ' ', STR_PAD_LEFT ) . ': ' . $line;
        }
        return implode( "\n", $numbered );
    }
}
