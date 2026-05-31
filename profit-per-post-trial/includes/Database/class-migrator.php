<?php
/**
 * Database Migrator - handles table creation and upgrades.
 *
 * @package ProfitPerPost\Database
 */

namespace ProfitPerPost\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Migrator
 *
 * Handles database table creation and schema upgrades across plugin versions.
 */
class Migrator {

    /**
     * Option name for storing the current DB version.
     *
     * @var string
     */
    const DB_VERSION_OPTION = 'ppp_db_version';

    /**
     * Current database schema version.
     *
     * @var string
     */
    const CURRENT_DB_VERSION = '1.0.0';

    /**
     * Run migrations - create or update tables.
     *
     * @return void
     */
    public static function run() {
        $installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

        if ( version_compare( $installed_version, self::CURRENT_DB_VERSION, '<' ) ) {
            self::create_tables();
            update_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION );
        }
    }

    /**
     * Create all database tables using dbDelta.
     *
     * @return void
     */
    public static function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $schemas = Schema::get_all_schemas();

        foreach ( $schemas as $sql ) {
            dbDelta( $sql );
        }
    }

    /**
     * Drop all plugin tables.
     *
     * Used during uninstallation.
     *
     * @return void
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = Schema::get_all_tables();

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }

        delete_option( self::DB_VERSION_OPTION );
    }

    /**
     * Check if all required tables exist.
     *
     * @return bool
     */
    public static function tables_exist() {
        global $wpdb;

        $tables = Schema::get_all_tables();

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $result = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
            if ( $result !== $table ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Repair tables if they're missing.
     *
     * @return bool True if repair was needed and successful.
     */
    public static function repair() {
        if ( ! self::tables_exist() ) {
            self::create_tables();
            return true;
        }
        return false;
    }
}
