<?php
/**
 * WPAI_File_Manager — File discovery, path validation, and file I/O.
 *
 * FILE KEY FORMAT:
 *   theme:{relative}        e.g. "theme:functions.php", "theme:includes/nav.php"
 *   child:{relative}        e.g. "child:style.css"
 *   plugin:{folder}:{rel}   e.g. "plugin:core-blocks:core-blocks.php"
 *
 * This format encodes the root and relative path so resolve_path() can
 * validate access without any ambiguity. The key is safe to send to the
 * browser (no absolute server paths exposed).
 *
 * SECURITY:
 *   resolve_path() uses realpath() to resolve symlinks and then verifies the
 *   result starts with the allowed root. This blocks all path traversal
 *   attempts including "../", "%2e%2e/", symlink escapes, etc.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPAI_File_Manager {

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Returns a flat list of all files the editor is allowed to access,
     * grouped by source (Theme, Child Theme, Plugin name).
     *
     * @return array[] Each entry: { key, label, type, group }
     */
    public function get_file_list(): array {
        $files = array();

        // Active theme
        $theme_dir  = get_template_directory();
        $theme_name = wp_get_theme()->get( 'Name' );
        $files = array_merge(
            $files,
            $this->scan_directory( $theme_dir, array( 'php', 'css' ), 'Theme: ' . $theme_name, 'theme', $theme_dir )
        );

        // Child theme (if different from parent)
        $child_dir = get_stylesheet_directory();
        if ( realpath( $child_dir ) !== realpath( $theme_dir ) ) {
            $child_name = wp_get_theme()->get( 'Name' ) . ' (Child)';
            $files = array_merge(
                $files,
                $this->scan_directory( $child_dir, array( 'php', 'css' ), 'Child: ' . $child_name, 'child', $child_dir )
            );
        }

        // Allowed plugin folders
        foreach ( WPAI_Settings::get_allowed_plugins() as $folder ) {
            $plugin_dir = WP_PLUGIN_DIR . '/' . sanitize_file_name( $folder );
            if ( is_dir( $plugin_dir ) ) {
                $files = array_merge(
                    $files,
                    $this->scan_directory(
                        $plugin_dir,
                        array( 'php', 'css' ),
                        'Plugin: ' . $folder,
                        'plugin:' . $folder,
                        $plugin_dir
                    )
                );
            }
        }

        return $files;
    }

    /**
     * Resolves a file key to an absolute server path.
     * Returns WP_Error if the key is invalid, the file doesn't exist,
     * the path escapes its allowed root, or the extension isn't PHP/CSS.
     *
     * @param string $file_key
     * @return string|WP_Error Absolute path on success.
     */
    public function resolve_path( string $file_key ) {
        // Parse key prefix to determine root
        if ( strpos( $file_key, 'theme:' ) === 0 ) {
            $root     = get_template_directory();
            $relative = substr( $file_key, 6 );

        } elseif ( strpos( $file_key, 'child:' ) === 0 ) {
            $root     = get_stylesheet_directory();
            $relative = substr( $file_key, 6 );

        } elseif ( strpos( $file_key, 'plugin:' ) === 0 ) {
            $rest  = substr( $file_key, 7 );
            $colon = strpos( $rest, ':' );
            if ( $colon === false ) {
                return new WP_Error( 'invalid_key', 'Invalid plugin key format.', array( 'status' => 400 ) );
            }
            $folder   = sanitize_file_name( substr( $rest, 0, $colon ) );
            $relative = substr( $rest, $colon + 1 );

            // Must be in the admin-approved list
            if ( ! in_array( $folder, WPAI_Settings::get_allowed_plugins(), true ) ) {
                return new WP_Error( 'access_denied', 'Plugin folder not in allowed list.', array( 'status' => 403 ) );
            }
            $root = WP_PLUGIN_DIR . '/' . $folder;

        } else {
            return new WP_Error( 'invalid_key', 'Unrecognised file key format.', array( 'status' => 400 ) );
        }

        // Strip any traversal sequences from the relative segment
        $relative = ltrim( $relative, '/\\' );
        $relative = str_replace( array( '../', '..\\', '..' ), '', $relative );

        $full_path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative );
        $candidate = realpath( $full_path );

        if ( $candidate === false ) {
            return new WP_Error( 'not_found', 'File not found.', array( 'status' => 404 ) );
        }

        // Security: candidate must be inside the resolved root
        $resolved_root = realpath( $root );
        if ( $resolved_root === false || strpos( $candidate, $resolved_root . DIRECTORY_SEPARATOR ) !== 0
             && $candidate !== $resolved_root ) {
            return new WP_Error( 'access_denied', 'Path traversal attempt detected.', array( 'status' => 403 ) );
        }

        // Only PHP and CSS files
        $ext = strtolower( pathinfo( $candidate, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'php', 'css' ), true ) ) {
            return new WP_Error( 'invalid_type', 'Only PHP and CSS files can be edited.', array( 'status' => 400 ) );
        }

        return $candidate;
    }

    /**
     * Reads a file's content.
     *
     * @param string $file_key
     * @return string|WP_Error File content on success.
     */
    public function read_file( string $file_key ) {
        $path = $this->resolve_path( $file_key );
        if ( is_wp_error( $path ) ) return $path;

        $this->init_filesystem();
        global $wp_filesystem;

        if ( $wp_filesystem ) {
            $content = $wp_filesystem->get_contents( $path );
        } else {
            $content = @file_get_contents( $path );
        }

        if ( $content === false ) {
            return new WP_Error( 'read_error', 'Could not read file.', array( 'status' => 500 ) );
        }

        return $content;
    }

    /**
     * Writes content to a file.
     * Does NOT back up — the caller (REST handler) must create a backup first.
     *
     * @param string $file_key
     * @param string $content
     * @return true|WP_Error
     */
    public function write_file( string $file_key, string $content ) {
        $path = $this->resolve_path( $file_key );
        if ( is_wp_error( $path ) ) return $path;

        $this->init_filesystem();
        global $wp_filesystem;

        if ( $wp_filesystem ) {
            $result = $wp_filesystem->put_contents( $path, $content, FS_CHMOD_FILE );
        } else {
            $result = @file_put_contents( $path, $content );
        }

        if ( $result === false || $result === null ) {
            return new WP_Error( 'write_error', 'Could not write file. Check file permissions.', array( 'status' => 500 ) );
        }

        return true;
    }

    // ── Create new files ──────────────────────────────────────────────────────

    /**
     * Creates a brand-new file in the active theme or child theme directory.
     * Will not overwrite an existing file.
     *
     * @param string $destination 'theme' or 'child'
     * @param string $filename    Relative path e.g. "page-about.php" or "template-parts/hero.php"
     * @param string $content     File content to write
     * @return string|WP_Error    File key on success
     */
    public function create_file( string $destination, string $filename, string $content ) {
        if ( $destination === 'theme' ) {
            $root   = get_template_directory();
            $prefix = 'theme';
        } elseif ( $destination === 'child' ) {
            $root   = get_stylesheet_directory();
            $prefix = 'child';
            // If no child theme is active, child == parent — refuse
            if ( realpath( $root ) === realpath( get_template_directory() ) ) {
                return new WP_Error( 'no_child_theme', 'No child theme is currently active. Create a child theme first.', array( 'status' => 400 ) );
            }
        } else {
            return new WP_Error( 'invalid_destination', 'Destination must be "theme" or "child".', array( 'status' => 400 ) );
        }

        // Sanitize filename — allow subdirectories but block traversal and unsafe chars
        $filename = ltrim( $filename, '/\\' );
        $filename = str_replace( array( '../', '.\\', '..\\', '..' ), '', $filename );
        $filename = preg_replace( '/[^a-zA-Z0-9\/\-_\.]/', '', $filename );

        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'php', 'css' ), true ) ) {
            return new WP_Error( 'invalid_extension', 'Only .php and .css files can be created.', array( 'status' => 400 ) );
        }

        $full_path = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $filename );

        if ( file_exists( $full_path ) ) {
            return new WP_Error( 'file_exists', 'A file with that name already exists in this theme.', array( 'status' => 409 ) );
        }

        // Create any needed subdirectories
        $dir = dirname( $full_path );
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        $this->init_filesystem();
        global $wp_filesystem;

        if ( $wp_filesystem ) {
            $ok = $wp_filesystem->put_contents( $full_path, $content, FS_CHMOD_FILE );
        } else {
            $ok = @file_put_contents( $full_path, $content );
        }

        if ( ! $ok ) {
            return new WP_Error( 'write_error', 'Could not write the new file. Check directory permissions.', array( 'status' => 500 ) );
        }

        $relative = str_replace( '\\', '/', ltrim( str_replace( $root, '', $full_path ), DIRECTORY_SEPARATOR ) );
        return $prefix . ':' . $relative;
    }

    /**
     * Scaffolds a new child theme directory with the provided files.
     *
     * @param string $theme_name   Human-readable name e.g. "My Custom Theme"
     * @param array  $files        Map of filename => content: { style.css, functions.php, index.php }
     * @return array|WP_Error      { theme_slug, activate_url }
     */
    public function create_child_theme( string $theme_name, array $files ) {
        $theme_slug = sanitize_title( $theme_name );
        if ( empty( $theme_slug ) ) {
            return new WP_Error( 'invalid_name', 'Theme name is invalid.', array( 'status' => 400 ) );
        }

        $theme_dir = get_theme_root() . DIRECTORY_SEPARATOR . $theme_slug;
        if ( is_dir( $theme_dir ) ) {
            return new WP_Error( 'theme_exists', 'A theme folder with that name already exists.', array( 'status' => 409 ) );
        }

        if ( ! wp_mkdir_p( $theme_dir ) ) {
            return new WP_Error( 'mkdir_failed', 'Could not create the theme directory. Check permissions.', array( 'status' => 500 ) );
        }

        $this->init_filesystem();
        global $wp_filesystem;

        foreach ( $files as $filename => $content ) {
            $filepath = $theme_dir . DIRECTORY_SEPARATOR . sanitize_file_name( $filename );
            if ( $wp_filesystem ) {
                $wp_filesystem->put_contents( $filepath, $content, FS_CHMOD_FILE );
            } else {
                @file_put_contents( $filepath, $content );
            }
        }

        return array(
            'theme_slug'   => $theme_slug,
            'activate_url' => admin_url( 'themes.php' ),
        );
    }

    /**
     * Returns a list of all installed themes for the parent theme picker.
     *
     * @return array[] Each entry: { name, slug, is_child }
     */
    public function list_installed_themes(): array {
        $themes = wp_get_themes();
        $result = array();
        foreach ( $themes as $slug => $theme ) {
            $result[] = array(
                'name'     => $theme->get( 'Name' ),
                'slug'     => $slug,
                'is_child' => ! empty( $theme->get( 'Template' ) ),
            );
        }
        // Sort alphabetically
        usort( $result, function ( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );
        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Recursively scans a directory for PHP and CSS files.
     *
     * @param string   $dir        Current directory to scan.
     * @param string[] $extensions e.g. ['php', 'css']
     * @param string   $group      Label for the dropdown optgroup.
     * @param string   $key_prefix e.g. 'theme', 'child', 'plugin:core-blocks'
     * @param string   $root       Absolute path of the scan root (for key generation).
     * @param int      $depth      Current recursion depth (max 4).
     * @return array[]
     */
    private function scan_directory(
        string $dir,
        array $extensions,
        string $group,
        string $key_prefix,
        string $root,
        int $depth = 0
    ): array {
        $files = array();
        if ( $depth > 4 ) return $files;
        if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) return $files;

        $skip = array( 'node_modules', '.git', 'vendor', '.svn', 'ai-editor-backups', 'ai-editor-previews', '.cache' );

        $items = @scandir( $dir );
        if ( ! $items ) return $files;

        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) continue;
            if ( in_array( $item, $skip, true ) ) continue;

            $full_path = $dir . DIRECTORY_SEPARATOR . $item;

            if ( is_dir( $full_path ) ) {
                $sub = $this->scan_directory( $full_path, $extensions, $group, $key_prefix, $root, $depth + 1 );
                $files = array_merge( $files, $sub );
            } elseif ( is_file( $full_path ) ) {
                $ext = strtolower( pathinfo( $item, PATHINFO_EXTENSION ) );
                if ( in_array( $ext, $extensions, true ) ) {
                    // Build relative path from root, always using forward slashes
                    $relative = ltrim( str_replace( '\\', '/', str_replace( realpath( $root ), '', realpath( $full_path ) ) ), '/' );
                    $key      = $key_prefix . ':' . $relative;

                    $files[] = array(
                        'key'   => $key,
                        'label' => $relative,
                        'type'  => $ext,
                        'group' => $group,
                    );
                }
            }
        }

        // Sort files alphabetically within each directory level
        usort( $files, function( $a, $b ) {
            return strcmp( $a['label'], $b['label'] );
        } );

        return $files;
    }

    private function init_filesystem(): void {
        global $wp_filesystem;
        if ( $wp_filesystem ) return;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
    }
}
