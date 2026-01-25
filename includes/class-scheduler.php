<?php
/**
 * Scheduler Class
 * 
 * Manages cron jobs for stock sync and watchdog monitoring.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSSC_Scheduler {
    
    /**
     * Available schedule intervals
     */
    private $intervals = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add custom cron intervals
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);
        
        // Watchdog check
        add_action('wssc_watchdog_check', [$this, 'watchdog_check']);
        
        // Initialize intervals
        $this->intervals = [
            'wssc_5min' => [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display' => __('Every 5 Minutes', 'woo-stock-sync'),
            ],
            'wssc_15min' => [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display' => __('Every 15 Minutes', 'woo-stock-sync'),
            ],
            'wssc_30min' => [
                'interval' => 30 * MINUTE_IN_SECONDS,
                'display' => __('Every 30 Minutes', 'woo-stock-sync'),
            ],
            'hourly' => [
                'interval' => HOUR_IN_SECONDS,
                'display' => __('Hourly', 'woo-stock-sync'),
            ],
            'wssc_2hours' => [
                'interval' => 2 * HOUR_IN_SECONDS,
                'display' => __('Every 2 Hours', 'woo-stock-sync'),
            ],
            'wssc_4hours' => [
                'interval' => 4 * HOUR_IN_SECONDS,
                'display' => __('Every 4 Hours', 'woo-stock-sync'),
            ],
            'wssc_6hours' => [
                'interval' => 6 * HOUR_IN_SECONDS,
                'display' => __('Every 6 Hours', 'woo-stock-sync'),
            ],
            'wssc_12hours' => [
                'interval' => 12 * HOUR_IN_SECONDS,
                'display' => __('Every 12 Hours', 'woo-stock-sync'),
            ],
            'daily' => [
                'interval' => DAY_IN_SECONDS,
                'display' => __('Daily', 'woo-stock-sync'),
            ],
            'wssc_2days' => [
                'interval' => 2 * DAY_IN_SECONDS,
                'display' => __('Every 2 Days', 'woo-stock-sync'),
            ],
            'weekly' => [
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Weekly', 'woo-stock-sync'),
            ],
        ];
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        // Custom intervals for sync
        foreach ($this->intervals as $key => $data) {
            if (!isset($schedules[$key])) {
                $schedules[$key] = $data;
            }
        }
        
        // Watchdog interval (4 hours)
        if (!isset($schedules['wssc_four_hours'])) {
            $schedules['wssc_four_hours'] = [
                'interval' => 4 * HOUR_IN_SECONDS,
                'display' => __('Every 4 Hours (Watchdog)', 'woo-stock-sync'),
            ];
        }
        
        return $schedules;
    }
    
    /**
     * Get available intervals
     */
    public function get_intervals() {
        return $this->intervals;
    }
    
    /**
     * Schedule sync
     */
    public function schedule($interval = null) {
        if (!$interval) {
            $interval = get_option('wssc_schedule_interval', 'hourly');
        }
        
        // Clear existing schedule
        $this->unschedule();
        
        // Schedule new
        if (!wp_next_scheduled('wssc_sync_event')) {
            wp_schedule_event(time(), $interval, 'wssc_sync_event');
        }
        
        // Schedule watchdog if not exists
        if (!wp_next_scheduled('wssc_watchdog_check')) {
            wp_schedule_event(time(), 'wssc_four_hours', 'wssc_watchdog_check');
        }
        
        update_option('wssc_schedule_interval', $interval);
        update_option('wssc_last_scheduled', current_time('timestamp'));
        
        return true;
    }
    
    /**
     * Unschedule sync
     */
    public function unschedule() {
        wp_clear_scheduled_hook('wssc_sync_event');
        return true;
    }
    
    /**
     * Reschedule sync (called after each sync)
     */
    public function reschedule() {
        $enabled = get_option('wssc_enabled', false);
        
        if (!$enabled) {
            return;
        }
        
        $interval = get_option('wssc_schedule_interval', 'hourly');
        
        // Clear and reschedule
        wp_clear_scheduled_hook('wssc_sync_event');
        wp_schedule_event(time() + $this->get_interval_seconds($interval), $interval, 'wssc_sync_event');
    }
    
    /**
     * Get interval in seconds
     */
    public function get_interval_seconds($interval_key) {
        $schedules = wp_get_schedules();
        
        if (isset($schedules[$interval_key])) {
            return $schedules[$interval_key]['interval'];
        }
        
        return HOUR_IN_SECONDS; // Default to hourly
    }
    
    /**
     * Get next scheduled run
     */
    public function get_next_run() {
        $timestamp = wp_next_scheduled('wssc_sync_event');
        return $timestamp ? $timestamp : null;
    }
    
    /**
     * Get time until next run
     */
    public function get_time_until_next_run() {
        $next = $this->get_next_run();
        
        if (!$next) {
            return null;
        }
        
        $diff = $next - current_time('timestamp');
        
        if ($diff < 0) {
            return __('Overdue', 'woo-stock-sync');
        }
        
        return human_time_diff(current_time('timestamp'), $next);
    }
    
    /**
     * Watchdog check
     * 
     * This runs every 4 hours to ensure the sync cron is properly scheduled.
     * If sync is enabled but no cron is scheduled, it will reschedule it.
     */
    public function watchdog_check() {
        $enabled = get_option('wssc_enabled', false);
        
        if (!$enabled) {
            return;
        }
        
        // Check if license is valid
        if (!WSSC()->license->is_valid()) {
            // Log and disable
            WSSC()->logs->add([
                'type' => 'watchdog',
                'status' => 'warning',
                'message' => __('Watchdog: License invalid. Sync disabled.', 'woo-stock-sync'),
            ]);
            
            update_option('wssc_enabled', false);
            $this->unschedule();
            return;
        }
        
        // Check if sync cron is scheduled
        $next_run = wp_next_scheduled('wssc_sync_event');
        
        if (!$next_run) {
            // Cron is missing, reschedule it
            $interval = get_option('wssc_schedule_interval', 'hourly');
            wp_schedule_event(time(), $interval, 'wssc_sync_event');
            
            WSSC()->logs->add([
                'type' => 'watchdog',
                'status' => 'warning',
                'message' => __('Watchdog: Sync cron was missing. Rescheduled successfully.', 'woo-stock-sync'),
            ]);
            
            return;
        }
        
        // Check if cron is overdue by more than double the interval
        $interval = get_option('wssc_schedule_interval', 'hourly');
        $interval_seconds = $this->get_interval_seconds($interval);
        $overdue_threshold = $interval_seconds * 2;
        
        $time_diff = $next_run - current_time('timestamp');
        
        // If it's been too long since last scheduled run
        if ($time_diff < -$overdue_threshold) {
            // Cron seems stuck, reschedule
            wp_clear_scheduled_hook('wssc_sync_event');
            wp_schedule_event(time(), $interval, 'wssc_sync_event');
            
            WSSC()->logs->add([
                'type' => 'watchdog',
                'status' => 'warning',
                'message' => __('Watchdog: Sync cron was stuck/overdue. Rescheduled successfully.', 'woo-stock-sync'),
            ]);
        }
        
        // Log successful watchdog check
        update_option('wssc_watchdog_last_check', current_time('timestamp'));
    }
    
    /**
     * Get sync status info
     */
    public function get_status() {
        $enabled = get_option('wssc_enabled', false);
        $interval = get_option('wssc_schedule_interval', 'hourly');
        $next_run = $this->get_next_run();
        $last_sync = get_option('wssc_last_sync_time');
        $watchdog_last = get_option('wssc_watchdog_last_check');
        
        $schedules = wp_get_schedules();
        $interval_display = isset($schedules[$interval]) ? $schedules[$interval]['display'] : $interval;
        
        return [
            'enabled' => $enabled,
            'interval' => $interval,
            'interval_display' => $interval_display,
            'next_run' => $next_run,
            'next_run_human' => $next_run ? human_time_diff(current_time('timestamp'), $next_run) : null,
            'next_run_formatted' => $next_run ? wp_date('Y-m-d H:i:s', $next_run) : null,
            'last_sync' => $last_sync,
            'last_sync_human' => $last_sync ? human_time_diff($last_sync) . ' ' . __('ago', 'woo-stock-sync') : null,
            'watchdog_last' => $watchdog_last,
            'watchdog_last_human' => $watchdog_last ? human_time_diff($watchdog_last) . ' ' . __('ago', 'woo-stock-sync') : null,
        ];
    }
    
    /**
     * Check if sync is currently running
     */
    public function is_running() {
        return get_transient('wssc_sync_running') ? true : false;
    }
    
    /**
     * Set running status
     */
    public function set_running($running = true) {
        if ($running) {
            set_transient('wssc_sync_running', true, 30 * MINUTE_IN_SECONDS);
        } else {
            delete_transient('wssc_sync_running');
        }
    }
}
