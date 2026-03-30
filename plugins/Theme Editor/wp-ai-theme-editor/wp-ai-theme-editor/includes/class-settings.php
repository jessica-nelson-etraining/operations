<?php
/**
 * WPAI_Settings — Settings registration, options page, and getter methods.
 *
 * All other classes retrieve configuration through the static getter methods
 * here — this is the single authoritative source for plugin configuration.
 * The API key is stored encrypted and never returned to the frontend or JS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPAI_Settings {

    const OPTION_GROUP = 'wpai_settings';
    const PAGE_SLUG    = 'wpai-settings';

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_menu', array( $this, 'add_settings_page' ), 20 );
    }

    // ── Register settings ─────────────────────────────────────────────────────

    public function register_settings(): void {
        register_setting( self::OPTION_GROUP, 'wpai_api_key', array(
            'sanitize_callback' => array( $this, 'sanitize_api_key' ),
        ) );
        register_setting( self::OPTION_GROUP, 'wpai_model', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'claude-opus-4-5',
        ) );
        register_setting( self::OPTION_GROUP, 'wpai_max_tokens', array(
            'sanitize_callback' => 'absint',
            'default'           => 8192,
        ) );
        register_setting( self::OPTION_GROUP, 'wpai_timeout', array(
            'sanitize_callback' => 'absint',
            'default'           => 120,
        ) );
        register_setting( self::OPTION_GROUP, 'wpai_plugin_dirs', array(
            'sanitize_callback' => array( $this, 'sanitize_plugin_dirs' ),
        ) );
        register_setting( self::OPTION_GROUP, 'wpai_backup_retention', array(
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ) );
    }

    public function add_settings_page(): void {
        add_submenu_page(
            'wpai-editor',
            'AI Editor Settings',
            'Settings',
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    // ── Settings page render ──────────────────────────────────────────────────

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized.' );
        }

        $api_key_saved   = ! empty( get_option( 'wpai_api_key', '' ) );
        $current_model   = self::get_model();
        $max_tokens      = self::get_max_tokens();
        $timeout         = self::get_timeout();
        $allowed_plugins = self::get_allowed_plugins();
        $retention       = self::get_backup_retention();

        // Build list of installed plugins for the folder checklist
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins    = get_plugins();
        $plugin_folders = array();
        foreach ( array_keys( $all_plugins ) as $plugin_file ) {
            $folder = dirname( $plugin_file );
            if ( $folder !== '.' ) {
                $plugin_folders[ $folder ] = $folder;
            }
        }
        ksort( $plugin_folders );

        // Check for settings-updated flag to show notices
        $updated = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'];
        ?>
        <div class="wrap">
            <h1>AI Theme Editor — Settings</h1>

            <?php if ( $updated ) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong></p></div>
            <?php endif; ?>

            <?php if ( ! $api_key_saved ) : ?>
                <div class="notice notice-warning">
                    <p><strong>API key required.</strong> Enter your Anthropic API key below to start using the AI editor.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( self::OPTION_GROUP ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wpai_api_key">Anthropic API Key</label></th>
                        <td>
                            <input
                                type="password"
                                id="wpai_api_key"
                                name="wpai_api_key"
                                class="regular-text"
                                value=""
                                placeholder="<?php echo $api_key_saved ? 'Key saved — enter a new one to replace it' : 'sk-ant-api03-...'; ?>"
                                autocomplete="new-password"
                            />
                            <p class="description">
                                Get your key from <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a>.
                                Leave blank to keep the current key.
                                The key is encrypted before storage and never sent to the browser.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="wpai_model">Claude Model</label></th>
                        <td>
                            <select id="wpai_model" name="wpai_model">
                                <option value="claude-opus-4-5" <?php selected( $current_model, 'claude-opus-4-5' ); ?>>
                                    Claude Opus 4.5 — Most capable, best for complex edits
                                </option>
                                <option value="claude-sonnet-4-6" <?php selected( $current_model, 'claude-sonnet-4-6' ); ?>>
                                    Claude Sonnet 4.6 — Balanced speed and quality
                                </option>
                                <option value="claude-haiku-4-5-20251001" <?php selected( $current_model, 'claude-haiku-4-5-20251001' ); ?>>
                                    Claude Haiku 4.5 — Fastest, best for simple questions
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="wpai_max_tokens">Max Output Tokens</label></th>
                        <td>
                            <input type="number" id="wpai_max_tokens" name="wpai_max_tokens"
                                value="<?php echo esc_attr( $max_tokens ); ?>"
                                min="1024" max="16000" class="small-text" />
                            <p class="description">Maximum tokens Claude can return. Higher values allow editing larger files. Default: 8192.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="wpai_timeout">Request Timeout (seconds)</label></th>
                        <td>
                            <input type="number" id="wpai_timeout" name="wpai_timeout"
                                value="<?php echo esc_attr( $timeout ); ?>"
                                min="15" max="300" class="small-text" />
                            <p class="description">How long to wait for Claude to respond. Edit mode requires returning the full file and regularly takes 60–90 seconds. Default: 120. Raise this if you see timeout errors.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Editable Plugin Folders</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Select which plugin folders the AI editor can access</legend>
                                <?php if ( empty( $plugin_folders ) ) : ?>
                                    <p>No plugins found.</p>
                                <?php else : ?>
                                    <?php foreach ( $plugin_folders as $folder ) : ?>
                                        <label style="display:block; margin-bottom:4px;">
                                            <input
                                                type="checkbox"
                                                name="wpai_plugin_dirs[]"
                                                value="<?php echo esc_attr( $folder ); ?>"
                                                <?php checked( in_array( $folder, $allowed_plugins, true ) ); ?>
                                            />
                                            <?php echo esc_html( $folder ); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </fieldset>
                            <p class="description">Only check plugins you own or manage. The editor can read and write files in these folders.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="wpai_backup_retention">Backup Retention (days)</label></th>
                        <td>
                            <input type="number" id="wpai_backup_retention" name="wpai_backup_retention"
                                value="<?php echo esc_attr( $retention ); ?>"
                                min="1" max="365" class="small-text" />
                            <p class="description">
                                Backups older than this many days are automatically deleted.
                                Backups are stored at <code><?php echo esc_html( WPAI_BACKUP_DIR ); ?></code>.
                                Default: 30 days.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }

    // ── API key encryption / decryption ───────────────────────────────────────

    /**
     * Sanitize callback for the API key option.
     * If blank, keeps the existing stored value (user left field empty = no change).
     */
    public function sanitize_api_key( string $key ): string {
        $key = sanitize_text_field( $key );

        // Blank = keep existing
        if ( empty( $key ) ) {
            return get_option( 'wpai_api_key', '' );
        }

        // Encrypt before storing
        $encrypted = openssl_encrypt(
            $key,
            'AES-256-CBC',
            self::get_encryption_key(),
            0,
            self::get_iv()
        );

        if ( $encrypted === false ) {
            // openssl unavailable — store base64 as a fallback (not ideal but functional)
            return 'wpai_b64:' . base64_encode( $key );
        }

        return 'wpai_enc:' . base64_encode( $encrypted );
    }

    /**
     * Sanitize the plugin directories checkbox array.
     */
    public function sanitize_plugin_dirs( $dirs ): array {
        if ( ! is_array( $dirs ) ) return array();
        return array_values( array_filter( array_map( 'sanitize_file_name', $dirs ) ) );
    }

    // ── Static getters (used by all other classes) ────────────────────────────

    public static function get_api_key(): string {
        $stored = get_option( 'wpai_api_key', '' );
        if ( empty( $stored ) ) return '';

        if ( strpos( $stored, 'wpai_enc:' ) === 0 ) {
            $decrypted = openssl_decrypt(
                base64_decode( substr( $stored, 9 ) ),
                'AES-256-CBC',
                self::get_encryption_key(),
                0,
                self::get_iv()
            );
            return $decrypted !== false ? $decrypted : '';
        }

        if ( strpos( $stored, 'wpai_b64:' ) === 0 ) {
            return base64_decode( substr( $stored, 9 ) ) ?: '';
        }

        return $stored; // plaintext fallback for pre-encryption values
    }

    public static function get_model(): string {
        return get_option( 'wpai_model', 'claude-opus-4-5' );
    }

    public static function get_max_tokens(): int {
        return (int) get_option( 'wpai_max_tokens', 8192 );
    }

    public static function get_timeout(): int {
        return (int) get_option( 'wpai_timeout', 120 );
    }

    public static function get_allowed_plugins(): array {
        $stored = get_option( 'wpai_plugin_dirs', array() );
        return is_array( $stored ) ? $stored : array();
    }

    public static function get_backup_retention(): int {
        return (int) get_option( 'wpai_backup_retention', 30 );
    }

    // ── Encryption helpers ────────────────────────────────────────────────────

    private static function get_encryption_key(): string {
        $key = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
        return substr( hash( 'sha256', $key, true ), 0, 32 );
    }

    private static function get_iv(): string {
        $salt = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : wp_salt( 'secure_auth' );
        return substr( hash( 'md5', $salt, true ), 0, 16 );
    }
}
