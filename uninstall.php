<?php
/**
 * Woo Stock Sync from CSV Uninstall
 *
 * Fires when the plugin is deleted.
 * Cleans up all plugin data from the database.
 *
 * @package Woo_Stock_Sync_CSV
 * @since 1.1.4
 */

// Exit if not called from WordPress uninstaller
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verify this is a valid uninstall request
if (!current_user_can('activate_plugins')) {
    return;
}

/**
 * Clean up all plugin data
 */
function wssc_uninstall_cleanup() {
    global $wpdb;
    
    // Delete all plugin options
    $options_to_delete = [
        // Core settings
        'wssc_csv_url',
        'wssc_sku_column',
        'wssc_quantity_column',
        'wssc_schedule_interval',
        'wssc_enabled',
        'wssc_disable_ssl',
        'wssc_missing_sku_action',
        // License
        'wssc_license_key',
        'wssc_license_status',
        'wssc_license_data',
        'wssc_license_last_check',
        // Sync tracking
        'wssc_last_sync_time',
        'wssc_last_sync_stats',
        'wssc_last_scheduled',
        'wssc_privatized_products',
        'wssc_watchdog_last_check',
        // Database
        'wssc_db_version',
    ];
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Delete transients
    $transients_to_delete = [
        'wssc_update_data',
        'wssc_sync_running',
        'wssc_last_manual_sync',
    ];
    
    foreach ($transients_to_delete as $transient) {
        delete_transient($transient);
    }
    
    // Unschedule all cron events
    $cron_hooks = [
        'wssc_sync_event',
        'wssc_watchdog_check',
        'wssc_license_check',
        'wssc_update_check',
    ];
    
    foreach ($cron_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
        // Clear all events for this hook
        wp_clear_scheduled_hook($hook);
    }
    
    // Drop the logs table
    $table_name = $wpdb->prefix . 'wssc_logs';
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    
    // Clean up any remaining meta data (if we ever store product meta)
    // $wpdb->delete($wpdb->postmeta, ['meta_key' => '_wssc_original_visibility']);
    
    // Clear any cached data
    wp_cache_flush();
}

// Run cleanup
wssc_uninstall_cleanup();
