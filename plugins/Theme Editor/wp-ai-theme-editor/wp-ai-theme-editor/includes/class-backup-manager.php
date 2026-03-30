<?php
/**
 * WPAI_Backup_Manager — Creates, lists, restores, and prunes file backups.
 *
 * Backup directory layout:
 *   /wp-content/ai-editor-backups/
 *     theme/
 *       functions.php/
 *         1710000000.bak
 *         1710003600.bak
 *     plugin/
 *       core-blocks/
 *         core-blocks.php/
 *           1710000000.bak
 *
 * The directory is protected with an .htaccess Deny rule on creation.
 * Backup paths returned to the frontend are validated against the backup
 * directory root before any read or restore operation.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPAI_Backup_Manager {

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Creates a timestamped backup of the given file content.
     *
     * @param string $file_key  The file key (used to build the backup subdirectory).
     * @param string $content   The file content to back up.
     * @return string|WP_Error  Absolute path to the created backup file.
     */
    public function create_backup( string $file_key, string $content ) {
        $this->ensure_backup_dir();

        $subdir = WPAI_BACKUP_DIR . $this->key_to_subdir( $file_key ) . DIRECTORY_SEPARATOR;
        if ( ! wp_mkdir_p( $subdir ) ) {
            return new WP_Error( 'backup_mkdir', 'Could not create backup subdirectory.' );
        }

        $backup_path = $subdir . time() . '.bak';
        if ( @file_put_contents( $backup_path, $content ) === false ) {
            return new WP_Error( 'backup_write', 'Could not write backup file.' );
        }

        return $backup_path;
    }

    /**
     * Returns all backups for a given file key, newest first.
     *
     * @param string $file_key
     * @return array[] Each entry: { label, path, timestamp }
     */
    public function list_backups( string $file_key ): array {
        $subdir = WPAI_BACKUP_DIR . $this->key_to_subdir( $file_key ) . DIRECTORY_SEPARATOR;
        if ( ! is_dir( $subdir ) ) return array();

        $bak_files = glob( $subdir . '*.bak' );
        if ( ! $bak_files ) return array();

        $backups = array();
        foreach ( $bak_files as $file ) {
            $timestamp = (int) basename( $file, '.bak' );
            if ( $timestamp <= 0 ) continue;
            $backups[] = array(
                'label'     => date( 'M j, Y \a\t g:i a', $timestamp ),
                'path'      => $file,
                'timestamp' => $timestamp,
            );
        }

        // Newest first
        usort( $backups, function ( $a, $b ) {
            return $b['timestamp'] - $a['timestamp'];
        } );

        return $backups;
    }

    /**
     * Reads the content of a specific backup file.
     * Validates that the path is inside the backup directory.
     *
     * @param string $backup_path Absolute path to a .bak file.
     * @return string|WP_Error
     */
    public function read_backup( string $backup_path ) {
        if ( ! $this->is_safe_backup_path( $backup_path ) ) {
            return new WP_Error( 'access_denied', 'Backup path is outside the backup directory.', array( 'status' => 403 ) );
        }

        $content = @file_get_contents( $backup_path );
        if ( $content === false ) {
            return new WP_Error( 'read_error', 'Could not read backup file.' );
        }

        return $content;
    }

    /**
     * Restores a backup to the live file.
     * Backs up the current live content first so the restore is reversible.
     *
     * @param string $file_key
     * @param string $backup_path Absolute path to the .bak file to restore.
     * @return true|WP_Error
     */
    public function restore_backup( string $file_key, string $backup_path ) {
        $backup_content = $this->read_backup( $backup_path );
        if ( is_wp_error( $backup_content ) ) return $backup_content;

        // Back up current live content before overwriting
        $file_manager = new WPAI_File_Manager();
        $current      = $file_manager->read_file( $file_key );
        if ( ! is_wp_error( $current ) ) {
            $this->create_backup( $file_key, $current );
        }

        // Write restored content
        return $file_manager->write_file( $file_key, $backup_content );
    }

    /**
     * Deletes backup files older than $days days.
     * Called daily via WP-Cron.
     *
     * @param int $days
     * @return int Number of files deleted.
     */
    public function prune_old_backups( int $days = 30 ): int {
        if ( ! is_dir( WPAI_BACKUP_DIR ) ) return 0;

        $cutoff  = time() - ( $days * DAY_IN_SECONDS );
        $deleted = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( WPAI_BACKUP_DIR, FilesystemIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $file ) {
            /** @var SplFileInfo $file */
            if ( $file->getExtension() === 'bak' && $file->getMTime() < $cutoff ) {
                if ( @unlink( $file->getPathname() ) ) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Converts a file key to a safe relative subdirectory path.
     * e.g. "theme:includes/nav.php" → "theme/includes/nav.php"
     */
    private function key_to_subdir( string $file_key ): string {
        // Replace colons and backslashes with directory separators, strip traversal
        $path = str_replace( ':', DIRECTORY_SEPARATOR, $file_key );
        $path = str_replace( '/', DIRECTORY_SEPARATOR, $path );
        $path = str_replace( '..', '', $path );
        return trim( $path, DIRECTORY_SEPARATOR );
    }

    /**
     * Verifies a backup path is inside WPAI_BACKUP_DIR.
     */
    private function is_safe_backup_path( string $path ): bool {
        $real_path = realpath( $path );
        $real_dir  = realpath( WPAI_BACKUP_DIR );

        if ( $real_path === false || $real_dir === false ) return false;

        return strpos( $real_path, $real_dir ) === 0;
    }

    /**
     * Ensures the backup directory exists and is web-access protected.
     */
    private function ensure_backup_dir(): void {
        if ( ! is_dir( WPAI_BACKUP_DIR ) ) {
            wp_mkdir_p( WPAI_BACKUP_DIR );
        }
        $htaccess = WPAI_BACKUP_DIR . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            @file_put_contents( $htaccess, "Deny from all\n" );
        }
    }
}
