<?php
/**
 * License Management Class
 * 
 * Handles license validation, activation, deactivation, and status checks
 * using the 3AG License API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSSC_License {
    
    /**
     * API Base URL
     */
    const API_URL = 'https://3ag.app/api/v3';
    
    /**
     * Product slug for API
     */
    const PRODUCT_SLUG = 'woo-stock-sync-from-csv';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wssc_license_check', [$this, 'daily_check']);
        
        // Schedule daily license check if not exists
        if (!wp_next_scheduled('wssc_license_check')) {
            wp_schedule_event(time(), 'daily', 'wssc_license_check');
        }
    }
    
    /**
     * Get current domain
     */
    private function get_domain() {
        return wssc_get_domain();
    }
    
    /**
     * Make API request
     */
    private function api_request($endpoint, $body) {
        $response = wp_remote_post(self::API_URL . $endpoint, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 204) {
            return [
                'success' => true,
                'message' => __('Operation successful.', 'woo-stock-sync'),
            ];
        }
        
        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'data' => isset($data['data']) ? $data['data'] : $data,
            ];
        }
        
        return [
            'success' => false,
            'message' => isset($data['message']) ? $data['message'] : __('Unknown error occurred.', 'woo-stock-sync'),
            'errors' => isset($data['errors']) ? $data['errors'] : [],
        ];
    }
    
    /**
     * Validate license key
     */
    public function validate($license_key) {
        $result = $this->api_request('/licenses/validate', [
            'license_key' => $license_key,
            'product_slug' => self::PRODUCT_SLUG,
        ]);
        
        return $result;
    }
    
    /**
     * Activate license for this domain
     */
    public function activate($license_key) {
        $result = $this->api_request('/licenses/activate', [
            'license_key' => $license_key,
            'product_slug' => self::PRODUCT_SLUG,
            'domain' => $this->get_domain(),
        ]);
        
        if ($result['success']) {
            update_option('wssc_license_key', $license_key);
            update_option('wssc_license_status', 'active');
            update_option('wssc_license_data', $result['data']);
            update_option('wssc_license_last_check', time());
        }
        
        return $result;
    }
    
    /**
     * Deactivate license for this domain
     */
    public function deactivate($license_key = null) {
        if (!$license_key) {
            $license_key = get_option('wssc_license_key');
        }
        
        if (!$license_key) {
            return [
                'success' => false,
                'message' => __('No license key found.', 'woo-stock-sync'),
            ];
        }
        
        $result = $this->api_request('/licenses/deactivate', [
            'license_key' => $license_key,
            'product_slug' => self::PRODUCT_SLUG,
            'domain' => $this->get_domain(),
        ]);
        
        if ($result['success']) {
            delete_option('wssc_license_key');
            delete_option('wssc_license_status');
            delete_option('wssc_license_data');
            delete_option('wssc_license_last_check');
        }
        
        return $result;
    }
    
    /**
     * Check license status
     */
    public function check($license_key = null) {
        if (!$license_key) {
            $license_key = get_option('wssc_license_key');
        }
        
        if (!$license_key) {
            return [
                'success' => false,
                'activated' => false,
                'message' => __('No license key found.', 'woo-stock-sync'),
            ];
        }
        
        $result = $this->api_request('/licenses/check', [
            'license_key' => $license_key,
            'product_slug' => self::PRODUCT_SLUG,
            'domain' => $this->get_domain(),
        ]);
        
        if ($result['success'] && isset($result['data']['activated'])) {
            update_option('wssc_license_last_check', time());
            
            if ($result['data']['activated']) {
                update_option('wssc_license_status', 'active');
                if (isset($result['data']['license'])) {
                    update_option('wssc_license_data', $result['data']['license']);
                }
            } else {
                update_option('wssc_license_status', 'inactive');
            }
        }
        
        return $result;
    }
    
    /**
     * Daily license verification
     */
    public function daily_check() {
        $license_key = get_option('wssc_license_key');
        if (!$license_key) {
            return;
        }
        
        $result = $this->check($license_key);
        
        if (!$result['success'] || (isset($result['data']['activated']) && !$result['data']['activated'])) {
            // License is no longer valid, disable sync
            update_option('wssc_enabled', false);
            
            // Clear scheduled sync
            wp_clear_scheduled_hook('wssc_sync_event');
            
            // Log the event
            WSSC()->logs->add([
                'type' => 'license',
                'status' => 'error',
                'message' => __('License validation failed. Sync has been disabled.', 'woo-stock-sync'),
            ]);
        }
    }
    
    /**
     * Check if license is valid
     */
    public function is_valid() {
        $status = get_option('wssc_license_status');
        $license_key = get_option('wssc_license_key');
        
        return $license_key && $status === 'active';
    }
    
    /**
     * Get license data
     */
    public function get_data() {
        return get_option('wssc_license_data', []);
    }
    
    /**
     * Get license expiry
     */
    public function get_expiry() {
        $data = $this->get_data();
        return isset($data['expires_at']) ? $data['expires_at'] : null;
    }
    
    /**
     * Check if license is expired
     */
    public function is_expired() {
        $expires_at = $this->get_expiry();
        
        if (!$expires_at) {
            // Lifetime license
            return false;
        }
        
        $expiry_time = strtotime($expires_at);
        return $expiry_time < time();
    }
    
    /**
     * Get remaining days
     */
    public function get_remaining_days() {
        $expires_at = $this->get_expiry();
        
        if (!$expires_at) {
            return null; // Lifetime
        }
        
        $expiry_time = strtotime($expires_at);
        $diff = $expiry_time - time();
        
        return max(0, floor($diff / DAY_IN_SECONDS));
    }
    
    /**
     * Get activations info
     */
    public function get_activations() {
        $data = $this->get_data();
        return isset($data['activations']) ? $data['activations'] : ['limit' => 0, 'used' => 0];
    }
}
