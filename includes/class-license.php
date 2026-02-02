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
     * 
     * @param string $endpoint API endpoint
     * @param array $body Request body
     * @return array Response with success, message, data, http_code, is_network_error
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
                'is_network_error' => true,
                'http_code' => 0,
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 204) {
            return [
                'success' => true,
                'message' => __('Operation successful.', 'woo-stock-sync'),
                'http_code' => $code,
                'is_network_error' => false,
            ];
        }
        
        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'data' => isset($data['data']) ? $data['data'] : $data,
                'http_code' => $code,
                'is_network_error' => false,
            ];
        }
        
        return [
            'success' => false,
            'message' => isset($data['message']) ? $data['message'] : __('Unknown error occurred.', 'woo-stock-sync'),
            'errors' => isset($data['errors']) ? $data['errors'] : [],
            'http_code' => $code,
            'is_network_error' => false,
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
     * 
     * Clears any existing license data before attempting activation
     * to ensure clean state regardless of outcome.
     */
    public function activate($license_key) {
        // Clear existing license data first to avoid stale state
        delete_option('wssc_license_key');
        delete_option('wssc_license_status');
        delete_option('wssc_license_data');
        delete_option('wssc_license_last_check');
        
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
        // On failure, all license data is cleared - user can try again
        
        return $result;
    }
    
    /**
     * Deactivate license for this domain
     * 
     * Always clears local license data, regardless of API response.
     * This ensures users can enter a new license even if the old one
     * was deleted from the server or the API is unreachable.
     */
    public function deactivate($license_key = null) {
        if (!$license_key) {
            $license_key = get_option('wssc_license_key');
        }
        
        // Always clear local license data first
        delete_option('wssc_license_key');
        delete_option('wssc_license_status');
        delete_option('wssc_license_data');
        delete_option('wssc_license_last_check');
        
        if (!$license_key) {
            return [
                'success' => true,
                'message' => __('License cleared.', 'woo-stock-sync'),
            ];
        }
        
        // Attempt to deactivate on server (to free up activation slot)
        // Result doesn't affect local deactivation - it's already done
        $result = $this->api_request('/licenses/deactivate', [
            'license_key' => $license_key,
            'product_slug' => self::PRODUCT_SLUG,
            'domain' => $this->get_domain(),
        ]);
        
        // Always return success since local data is cleared
        return [
            'success' => true,
            'message' => __('License deactivated.', 'woo-stock-sync'),
            'api_success' => $result['success'],
        ];
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
        
        update_option('wssc_license_last_check', time());
        
        if ($result['success'] && isset($result['data']['activated'])) {
            if ($result['data']['activated']) {
                update_option('wssc_license_status', 'active');
                if (isset($result['data']['license'])) {
                    update_option('wssc_license_data', $result['data']['license']);
                }
            } else {
                // License exists but not activated for this domain
                update_option('wssc_license_status', 'inactive');
            }
        } elseif (!$result['success']) {
            // Check if this is a network error or a definitive API error
            $is_network_error = !empty($result['is_network_error']);
            $http_code = isset($result['http_code']) ? $result['http_code'] : 0;
            
            // Only mark as inactive for definitive API errors (401, 403)
            // Don't invalidate on network errors (temporary issues)
            if (!$is_network_error && in_array($http_code, [401, 403], true)) {
                update_option('wssc_license_status', 'inactive');
            }
            // For network errors, keep current status unchanged
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
        
        // check() updates wssc_license_status internally
        $this->check($license_key);
        
        // Now check if license is valid after the API check
        if (!$this->is_valid()) {
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
