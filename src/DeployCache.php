<?php

namespace WP2Static;

class DeployCache {

    public static function createTable() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_deploy_cache';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            path_hash CHAR(32) NOT NULL,
            path VARCHAR(2083) NOT NULL,
            file_hash CHAR(32) NOT NULL,
            PRIMARY KEY  (path_hash)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function addFile(
        string $local_path
    ) : void {
        global $wpdb;

        $deploy_cache_table = $wpdb->prefix . 'wp2static_deploy_cache';

        $post_processed_dir = ProcessedSite::getPath();

        $deployed_file = $post_processed_dir . $local_path;

        $path_hash = md5( $deployed_file );
        $file_contents = file_get_contents( $deployed_file );

        if ( ! $file_contents ) {
            return;
        }

        $file_hash = md5( $file_contents );

        $sql = "INSERT INTO {$deploy_cache_table} (path_hash,path,file_hash)" .
            ' VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE file_hash = %s';

        $sql = $wpdb->prepare( $sql, $path_hash, $local_path, $file_hash, $file_hash );

        $wpdb->query( $sql );
    }

    /**
     * Checks if file can skip deployment
     *  - uses hash of file and path's hash
     */
    public static function fileisCached( string $local_path ) : bool {
        global $wpdb;

        $post_processed_dir = ProcessedSite::getPath();

        $deployed_file = $post_processed_dir . $local_path;

        $path_hash = md5( $deployed_file );
        $file_contents = file_get_contents( $deployed_file );

        if ( ! $file_contents ) {
            return false;
        }

        $file_hash = md5( $file_contents );

        $table_name = $wpdb->prefix . 'wp2static_deploy_cache';

        $sql = $wpdb->prepare(
            "SELECT path_hash FROM $table_name WHERE" .
            ' path_hash = %s AND file_hash = %s LIMIT 1',
            $path_hash,
            $file_hash
        );

        $hash = $wpdb->get_var( $sql );

        return (bool) $hash;
    }

    public static function truncate() : void {
        WsLog::l( 'Deleting DeployCache' );

        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_deploy_cache';

        $wpdb->query( "TRUNCATE TABLE $table_name" );
    }

    /**
     *  Count Paths in Deploy Cache
     */
    public static function getTotal() : int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_deploy_cache';

        $total = $wpdb->get_var( "SELECT count(*) FROM $table_name" );

        return $total;
    }

    /**
     *  Get all cached paths
     *
     *  @return string[] All cached paths
     */
    public static function getPaths() : array {
        global $wpdb;
        $urls = [];

        $table_name = $wpdb->prefix . 'wp2static_deploy_cache';

        $rows = $wpdb->get_results( "SELECT path FROM $table_name" );

        foreach ( $rows as $row ) {
            $urls[] = $row->path;
        }

        sort( $urls );

        return $urls;
    }
}
