<?php
/**
 * License Management Class
 * 
 * Handles license validation, activation, deactivation, and status checks
 * using the 3AG License API.
 * 
 * Client Status Values (stored locally):
 * - 'active'   : License is valid and activated for this domain
 * - 'expired'  : License has expired
 * - 'paused'   : User paused subscription
 * - 'suspended': Payment issue or manual suspension
 * - 'cancelled': Subscription cancelled
 * - 'invalid'  : License key doesn't exist (401 from API)
 * - 'domain_limit': Activation limit reached (403 with domain limit message)
 * - ''         : No license entered yet
 * 
 * API Status Values (from server):
 * - 'active', 'paused', 'suspended', 'expired', 'cancelled'
 * 
 * Grace Period:
 * - 7 days for network errors to prevent temporary outages from breaking sites
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
     * Grace period in days for network errors
     */
    const GRACE_PERIOD_DAYS = 7;
    
    /**
     * License statuses - matches API status values
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_PAUSED = 'paused';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Client-only statuses (not from API)
     */
    const STATUS_INVALID = 'invalid';        // 401 - key doesn't exist
    const STATUS_DOMAIN_LIMIT = 'domain_limit'; // 403 - activation limit reached
    
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
     * Validate license key (without activating)
     */
    public function validate($license_key) {
        return $this->api_request('/licenses/validate', [
            'license_key' => $license_key,
            'product_slug' => self::PRODUCT_SLUG,
        ]);
    }
    
    /**
     * Activate license for this domain
     * 
     * @param string $license_key The license key to activate
     * @return array Result with success status and message
     */
    public function activate($license_key) {
        $result = $this->api_request('/licenses/activate', [
            'license_key' => $license_key,
            'product_slug' => self::PRODUCT_SLUG,
            'domain' => $this->get_domain(),
        ]);
        
        if ($result['success']) {
            // Success - store all license data
            // API returns status in data (active, expired, etc.)
            $api_status = isset($result['data']['status']) ? $result['data']['status'] : self::STATUS_ACTIVE;
            
            update_option('wssc_license_key', $license_key);
            update_option('wssc_license_status', $api_status);
            update_option('wssc_license_data', $result['data']);
            update_option('wssc_license_last_check', time());
            delete_option('wssc_license_grace_start'); // Clear any grace period
            
            return $result;
        }
        
        // Handle specific error codes
        $http_code = isset($result['http_code']) ? $result['http_code'] : 0;
        
        // Always store the key for reference
        update_option('wssc_license_key', $license_key);
        delete_option('wssc_license_data');
        
        if ($http_code === 401) {
            // Invalid license key - doesn't exist
            update_option('wssc_license_status', self::STATUS_INVALID);
        } elseif ($http_code === 403) {
            // 403 can be: not active, domain limit, or other restrictions
            $message = isset($result['message']) ? strtolower($result['message']) : '';
            
            if (strpos($message, 'domain limit') !== false) {
                update_option('wssc_license_status', self::STATUS_DOMAIN_LIMIT);
            } else {
                // License exists but not active - validate to get actual status
                $validate_result = $this->validate($license_key);
                if ($validate_result['success'] && isset($validate_result['data']['status'])) {
                    // Use the actual status from API (expired, suspended, paused, cancelled)
                    update_option('wssc_license_status', $validate_result['data']['status']);
                    update_option('wssc_license_data', $validate_result['data']);
                } else {
                    // Fallback: parse message for common patterns
                    update_option('wssc_license_status', $this->parse_status_from_message($message));
                }
            }
        }
        // For network errors during activation, don't save status - let user retry
        
        return $result;
    }
    
    /**
     * Parse status from error message (fallback)
     * 
     * @param string $message Lowercase error message
     * @return string Status constant
     */
    private function parse_status_from_message($message) {
        if (strpos($message, 'expired') !== false) {
            return self::STATUS_EXPIRED;
        } elseif (strpos($message, 'suspended') !== false) {
            return self::STATUS_SUSPENDED;
        } elseif (strpos($message, 'cancelled') !== false || strpos($message, 'canceled') !== false) {
            return self::STATUS_CANCELLED;
        } elseif (strpos($message, 'paused') !== false) {
            return self::STATUS_PAUSED;
        }
        return self::STATUS_SUSPENDED; // Default for unknown 403
    }
    
    /**
     * Deactivate license for this domain
     * 
     * Clears local license data to allow entering a new license.
     * 
     * @param string|null $license_key Optional license key
     * @return array Result
     */
    public function deactivate($license_key = null) {
        if (!$license_key) {
            $license_key = get_option('wssc_license_key');
        }
        
        // Attempt to deactivate on server first (to free up activation slot)
        if ($license_key) {
            $this->api_request('/licenses/deactivate', [
                'license_key' => $license_key,
                'product_slug' => self::PRODUCT_SLUG,
                'domain' => $this->get_domain(),
            ]);
        }
        
        // Always clear local license data
        delete_option('wssc_license_key');
        delete_option('wssc_license_status');
        delete_option('wssc_license_data');
        delete_option('wssc_license_last_check');
        delete_option('wssc_license_grace_start');
        
        return [
            'success' => true,
            'message' => __('License deactivated.', 'woo-stock-sync'),
        ];
    }
    
    /**
     * Check license status with server
     * 
     * Updates local status based on server response.
     * Implements grace period for network errors.
     * 
     * @param string|null $license_key Optional license key
     * @return array Result
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
        
        // Handle successful API response
        if ($result['success'] && isset($result['data']['activated'])) {
            delete_option('wssc_license_grace_start'); // Clear grace period
            
            if ($result['data']['activated']) {
                // License is valid and activated - use status from API
                $license_data = isset($result['data']['license']) ? $result['data']['license'] : [];
                $api_status = isset($license_data['status']) ? $license_data['status'] : self::STATUS_ACTIVE;
                
                update_option('wssc_license_status', $api_status);
                update_option('wssc_license_data', $license_data);
            } else {
                // activated=false means either:
                // 1. Domain not activated for this license, OR
                // 2. License is not active (paused/suspended/expired/cancelled)
                // Try to auto-activate to determine which
                $current_status = get_option('wssc_license_status');
                $non_active_statuses = [
                    self::STATUS_INVALID, 
                    self::STATUS_EXPIRED, 
                    self::STATUS_PAUSED,
                    self::STATUS_SUSPENDED,
                    self::STATUS_CANCELLED,
                    self::STATUS_DOMAIN_LIMIT,
                ];
                
                if (in_array($current_status, $non_active_statuses, true) || $current_status === '') {
                    // Attempt re-activation
                    $activate_result = $this->activate($license_key);
                    if ($activate_result['success']) {
                        return $activate_result;
                    }
                    // activate() already set the correct status
                    return $activate_result;
                }
                
                // Was active before, now not - validate to get real status
                $validate_result = $this->validate($license_key);
                if ($validate_result['success'] && isset($validate_result['data']['status'])) {
                    update_option('wssc_license_status', $validate_result['data']['status']);
                    update_option('wssc_license_data', $validate_result['data']);
                } else {
                    update_option('wssc_license_status', self::STATUS_SUSPENDED);
                }
            }
            
            return $result;
        }
        
        // Handle API errors
        $is_network_error = !empty($result['is_network_error']);
        $http_code = isset($result['http_code']) ? $result['http_code'] : 0;
        
        if ($is_network_error) {
            // Network error - apply grace period
            return $this->handle_network_error($result);
        }
        
        // Definitive API errors
        if ($http_code === 401) {
            // License key doesn't exist
            update_option('wssc_license_status', self::STATUS_INVALID);
        }
        // Note: /check endpoint doesn't return 403, only 401 or 200
        
        return $result;
    }
    
    /**
     * Handle network error with grace period
     * 
     * @param array $result The failed result
     * @return array Modified result
     */
    private function handle_network_error($result) {
        $grace_start = get_option('wssc_license_grace_start');
        $current_status = get_option('wssc_license_status');
        
        // Only apply grace period if license was previously active
        if ($current_status !== self::STATUS_ACTIVE) {
            return $result;
        }
        
        if (!$grace_start) {
            // Start grace period
            update_option('wssc_license_grace_start', time());
            $result['message'] = __('Network error. License will remain active for 7 days.', 'woo-stock-sync');
        } else {
            // Check if grace period expired
            $grace_end = $grace_start + (self::GRACE_PERIOD_DAYS * DAY_IN_SECONDS);
            
            if (time() > $grace_end) {
                // Grace period expired - mark as suspended (network issue)
                update_option('wssc_license_status', self::STATUS_SUSPENDED);
                delete_option('wssc_license_grace_start');
                $result['message'] = __('License verification failed. Grace period expired.', 'woo-stock-sync');
            } else {
                // Still in grace period
                $days_left = ceil(($grace_end - time()) / DAY_IN_SECONDS);
                $result['message'] = sprintf(
                    __('Network error. License active for %d more days.', 'woo-stock-sync'),
                    $days_left
                );
                // Keep status as active during grace period
                $result['in_grace_period'] = true;
            }
        }
        
        return $result;
    }
    
    /**
     * Daily license verification (cron job)
     */
    public function daily_check() {
        $license_key = get_option('wssc_license_key');
        if (!$license_key) {
            return;
        }
        
        // check() updates wssc_license_status internally
        $this->check($license_key);
        
        // If license is no longer valid, disable sync
        if (!$this->is_valid()) {
            update_option('wssc_enabled', false);
            wp_clear_scheduled_hook('wssc_sync_event');
            
            WSSC()->logs->add([
                'type' => 'license',
                'status' => 'error',
                'message' => sprintf(
                    __('License status: %s. Sync has been disabled.', 'woo-stock-sync'),
                    $this->get_status_label()
                ),
            ]);
        }
    }
    
    /**
     * Check if license is valid for sync operations
     * 
     * @return bool True if license allows sync
     */
    public function is_valid() {
        $status = get_option('wssc_license_status');
        $license_key = get_option('wssc_license_key');
        
        return $license_key && $status === self::STATUS_ACTIVE;
    }
    
    /**
     * Get current license status
     * 
     * @return string Status constant or empty string
     */
    public function get_status() {
        return get_option('wssc_license_status', '');
    }
    
    /**
     * Get human-readable status label
     * 
     * @return string Translated status label
     */
    public function get_status_label() {
        $status = $this->get_status();
        
        $labels = [
            self::STATUS_ACTIVE => __('Active', 'woo-stock-sync'),
            self::STATUS_EXPIRED => __('Expired', 'woo-stock-sync'),
            self::STATUS_PAUSED => __('Paused', 'woo-stock-sync'),
            self::STATUS_SUSPENDED => __('Suspended', 'woo-stock-sync'),
            self::STATUS_CANCELLED => __('Cancelled', 'woo-stock-sync'),
            self::STATUS_INVALID => __('Invalid Key', 'woo-stock-sync'),
            self::STATUS_DOMAIN_LIMIT => __('Domain Limit Reached', 'woo-stock-sync'),
        ];
        
        return isset($labels[$status]) ? $labels[$status] : __('Not Activated', 'woo-stock-sync');
    }
    
    /**
     * Check if status allows the license to be re-activated
     * 
     * @return bool True if license can potentially be reactivated
     */
    public function can_reactivate() {
        $status = $this->get_status();
        
        // These statuses might become active again
        return in_array($status, [
            self::STATUS_PAUSED,      // User can unpause
            self::STATUS_SUSPENDED,   // Payment can be fixed
            self::STATUS_DOMAIN_LIMIT, // User can deactivate other domains
            self::STATUS_INVALID,     // Key might be corrected on server
        ], true);
    }
    
    /**
     * Check if license needs renewal (expired or cancelled)
     * 
     * @return bool True if license needs renewal/repurchase
     */
    public function needs_renewal() {
        $status = $this->get_status();
        
        return in_array($status, [
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ], true);
    }
    
    /**
     * Get license key
     * 
     * @return string License key or empty string
     */
    public function get_key() {
        return get_option('wssc_license_key', '');
    }
    
    /**
     * Get masked license key for display
     * 
     * @return string Masked key like "XXXX-••••-••••-XXXX"
     */
    public function get_masked_key() {
        $key = $this->get_key();
        $key_length = strlen($key);
        
        if ($key_length > 8) {
            return substr($key, 0, 4) . str_repeat('•', $key_length - 8) . substr($key, -4);
        } elseif ($key_length > 4) {
            return substr($key, 0, 2) . str_repeat('•', $key_length - 2);
        }
        
        return str_repeat('•', $key_length);
    }
    
    /**
     * Get license data
     * 
     * @return array License data
     */
    public function get_data() {
        return get_option('wssc_license_data', []);
    }
    
    /**
     * Get license expiry date
     * 
     * @return string|null ISO date string or null for lifetime
     */
    public function get_expiry() {
        $data = $this->get_data();
        return isset($data['expires_at']) ? $data['expires_at'] : null;
    }
    
    /**
     * Check if license is expired
     * 
     * @return bool True if expired
     */
    public function is_expired() {
        $expires_at = $this->get_expiry();
        
        if (!$expires_at) {
            return false; // Lifetime license
        }
        
        return strtotime($expires_at) < time();
    }
    
    /**
     * Get remaining days until expiry
     * 
     * @return int|null Days remaining, 0 if expired, null for lifetime
     */
    public function get_remaining_days() {
        $expires_at = $this->get_expiry();
        
        if (!$expires_at) {
            return null; // Lifetime
        }
        
        $diff = strtotime($expires_at) - time();
        return max(0, floor($diff / DAY_IN_SECONDS));
    }
    
    /**
     * Get activations info
     * 
     * @return array With 'limit' and 'used' keys
     */
    public function get_activations() {
        $data = $this->get_data();
        return isset($data['activations']) ? $data['activations'] : ['limit' => 0, 'used' => 0];
    }
    
    /**
     * Get last check timestamp
     * 
     * @return int|null Unix timestamp or null
     */
    public function get_last_check() {
        return get_option('wssc_license_last_check');
    }
    
    /**
     * Check if currently in grace period
     * 
     * @return bool True if in grace period
     */
    public function is_in_grace_period() {
        $grace_start = get_option('wssc_license_grace_start');
        
        if (!$grace_start) {
            return false;
        }
        
        $grace_end = $grace_start + (self::GRACE_PERIOD_DAYS * DAY_IN_SECONDS);
        return time() <= $grace_end;
    }
    
    /**
     * Get grace period days remaining
     * 
     * @return int|null Days remaining or null if not in grace period
     */
    public function get_grace_days_remaining() {
        $grace_start = get_option('wssc_license_grace_start');
        
        if (!$grace_start) {
            return null;
        }
        
        $grace_end = $grace_start + (self::GRACE_PERIOD_DAYS * DAY_IN_SECONDS);
        $remaining = $grace_end - time();
        
        return max(0, ceil($remaining / DAY_IN_SECONDS));
    }
    
    /**
     * Get renewal URL
     * 
     * @return string URL to renewal page
     */
    public function get_renewal_url() {
        $base_url = 'https://3ag.app/products/woo-stock-sync-from-csv';
        $license_key = $this->get_key();
        
        if ($license_key) {
            return add_query_arg('renew', urlencode($license_key), $base_url);
        }
        
        return $base_url;
    }
}
