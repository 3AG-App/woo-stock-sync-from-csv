<?php
/**
 * AJAX Handler Class
 * 
 * Handles all AJAX requests for the plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSSC_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Admin AJAX actions
        add_action('wp_ajax_wssc_run_sync', [$this, 'run_sync']);
        add_action('wp_ajax_wssc_test_connection', [$this, 'test_connection']);
        add_action('wp_ajax_wssc_preview_csv', [$this, 'preview_csv']);
        add_action('wp_ajax_wssc_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_wssc_activate_license', [$this, 'activate_license']);
        add_action('wp_ajax_wssc_deactivate_license', [$this, 'deactivate_license']);
        add_action('wp_ajax_wssc_check_license', [$this, 'check_license']);
        add_action('wp_ajax_wssc_clear_logs', [$this, 'clear_logs']);
        add_action('wp_ajax_wssc_get_log_details', [$this, 'get_log_details']);
        add_action('wp_ajax_wssc_get_sync_status', [$this, 'get_sync_status']);
        add_action('wp_ajax_wssc_toggle_sync', [$this, 'toggle_sync']);
    }
    
    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!check_ajax_referer('wssc_admin_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed.', 'woo-stock-sync'),
            ]);
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'woo-stock-sync'),
            ]);
        }
    }
    
    /**
     * Run manual sync
     */
    public function run_sync() {
        $this->verify_nonce();
        
        // Check if already running
        if (WSSC()->scheduler->is_running()) {
            wp_send_json_error([
                'message' => __('A sync is already in progress.', 'woo-stock-sync'),
            ]);
        }
        
        // Set running status
        WSSC()->scheduler->set_running(true);
        
        try {
            $result = WSSC()->sync->run_manual_sync();
            WSSC()->scheduler->set_running(false);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            WSSC()->scheduler->set_running(false);
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Test CSV connection
     */
    public function test_connection() {
        $this->verify_nonce();
        
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($url)) {
            wp_send_json_error([
                'message' => __('Please provide a CSV URL.', 'woo-stock-sync'),
            ]);
        }
        
        $result = WSSC()->sync->test_connection($url);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Preview CSV columns
     */
    public function preview_csv() {
        $this->verify_nonce();
        
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($url)) {
            wp_send_json_error([
                'message' => __('Please provide a CSV URL.', 'woo-stock-sync'),
            ]);
        }
        
        $result = WSSC()->sync->preview_columns($url);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Save settings
     */
    public function save_settings() {
        $this->verify_nonce();
        
        // Validate license
        if (!WSSC()->license->is_valid()) {
            wp_send_json_error([
                'message' => __('Please activate a valid license first.', 'woo-stock-sync'),
            ]);
        }
        
        // Get and sanitize settings
        $csv_url = isset($_POST['csv_url']) ? esc_url_raw($_POST['csv_url']) : '';
        $sku_column = isset($_POST['sku_column']) ? sanitize_text_field($_POST['sku_column']) : 'sku';
        $qty_column = isset($_POST['quantity_column']) ? sanitize_text_field($_POST['quantity_column']) : 'quantity';
        $interval = isset($_POST['schedule_interval']) ? sanitize_text_field($_POST['schedule_interval']) : 'hourly';
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        $disable_ssl = isset($_POST['disable_ssl']) && $_POST['disable_ssl'] === 'true';
        
        // Save settings
        update_option('wssc_csv_url', $csv_url);
        update_option('wssc_sku_column', $sku_column);
        update_option('wssc_quantity_column', $qty_column);
        update_option('wssc_schedule_interval', $interval);
        update_option('wssc_enabled', $enabled);
        update_option('wssc_disable_ssl', $disable_ssl);
        
        // Handle scheduling
        if ($enabled && !empty($csv_url)) {
            WSSC()->scheduler->schedule($interval);
        } else {
            WSSC()->scheduler->unschedule();
        }
        
        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'woo-stock-sync'),
        ]);
    }
    
    /**
     * Activate license
     */
    public function activate_license() {
        $this->verify_nonce();
        
        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        
        if (empty($license_key)) {
            wp_send_json_error([
                'message' => __('Please enter a license key.', 'woo-stock-sync'),
            ]);
        }
        
        $result = WSSC()->license->activate($license_key);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => __('License activated successfully!', 'woo-stock-sync'),
                'data' => $result['data'],
            ]);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Deactivate license
     */
    public function deactivate_license() {
        $this->verify_nonce();
        
        $result = WSSC()->license->deactivate();
        
        // Disable sync
        update_option('wssc_enabled', false);
        WSSC()->scheduler->unschedule();
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => __('License deactivated successfully.', 'woo-stock-sync'),
            ]);
        } else {
            // Still consider it success if we cleared local data
            wp_send_json_success([
                'message' => __('License deactivated locally.', 'woo-stock-sync'),
            ]);
        }
    }
    
    /**
     * Check license status
     */
    public function check_license() {
        $this->verify_nonce();
        
        $result = WSSC()->license->check();
        
        if ($result['success']) {
            wp_send_json_success([
                'activated' => isset($result['data']['activated']) ? $result['data']['activated'] : false,
                'data' => isset($result['data']['license']) ? $result['data']['license'] : $result['data'],
            ]);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Clear logs
     */
    public function clear_logs() {
        $this->verify_nonce();
        
        WSSC()->logs->clear();
        
        wp_send_json_success([
            'message' => __('Logs cleared successfully.', 'woo-stock-sync'),
        ]);
    }
    
    /**
     * Get log details
     */
    public function get_log_details() {
        $this->verify_nonce();
        
        $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
        
        if (!$log_id) {
            wp_send_json_error([
                'message' => __('Invalid log ID.', 'woo-stock-sync'),
            ]);
        }
        
        $log = WSSC()->logs->get_by_id($log_id);
        
        if (!$log) {
            wp_send_json_error([
                'message' => __('Log not found.', 'woo-stock-sync'),
            ]);
        }
        
        wp_send_json_success([
            'log' => $log,
        ]);
    }
    
    /**
     * Get sync status
     */
    public function get_sync_status() {
        $this->verify_nonce();
        
        $status = WSSC()->scheduler->get_status();
        $is_running = WSSC()->scheduler->is_running();
        
        wp_send_json_success([
            'status' => $status,
            'is_running' => $is_running,
        ]);
    }
    
    /**
     * Toggle sync enabled/disabled
     */
    public function toggle_sync() {
        $this->verify_nonce();
        
        // Validate license
        if (!WSSC()->license->is_valid()) {
            wp_send_json_error([
                'message' => __('Please activate a valid license first.', 'woo-stock-sync'),
            ]);
        }
        
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        
        update_option('wssc_enabled', $enabled);
        
        if ($enabled) {
            $csv_url = get_option('wssc_csv_url');
            if (empty($csv_url)) {
                update_option('wssc_enabled', false);
                wp_send_json_error([
                    'message' => __('Please configure a CSV URL in settings first.', 'woo-stock-sync'),
                ]);
            }
            
            WSSC()->scheduler->schedule();
            $message = __('Sync enabled successfully.', 'woo-stock-sync');
        } else {
            WSSC()->scheduler->unschedule();
            $message = __('Sync disabled.', 'woo-stock-sync');
        }
        
        wp_send_json_success([
            'message' => $message,
            'enabled' => $enabled,
        ]);
    }
}
