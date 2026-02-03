<?php
/**
 * Admin Class
 * 
 * Handles admin menu, pages, and assets.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSSC_Admin {
    
    /**
     * Menu slug
     */
    const MENU_SLUG = 'woo-stock-sync';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'license_inactive_notice']);
    }
    
    /**
     * Show admin notice when license is not active
     */
    public function license_inactive_notice() {
        // Only show on our plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, self::MENU_SLUG) === false) {
            return;
        }
        
        // Skip on license page itself
        if (strpos($screen->id, 'license') !== false) {
            return;
        }
        
        $license = WSSC()->license;
        $status = $license->get_status();
        
        if (!$license->get_key() || $status === WSSC_License::STATUS_ACTIVE) {
            return;
        }
        
        // Determine message and severity based on status
        $notice_class = 'notice-warning';
        switch ($status) {
            case WSSC_License::STATUS_EXPIRED:
                $message = __('Your license has expired. Renew to continue using sync features.', 'woo-stock-sync');
                break;
            case WSSC_License::STATUS_CANCELLED:
                $message = __('Your subscription was cancelled. Purchase a new license to restore sync.', 'woo-stock-sync');
                break;
            case WSSC_License::STATUS_PAUSED:
                $message = __('Your subscription is paused. Resume it in your account to restore sync.', 'woo-stock-sync');
                break;
            case WSSC_License::STATUS_SUSPENDED:
                $message = __('Your license is suspended. Please update your payment method.', 'woo-stock-sync');
                break;
            case WSSC_License::STATUS_DOMAIN_LIMIT:
                $message = __('Domain activation limit reached. Deactivate another site or upgrade your license.', 'woo-stock-sync');
                break;
            case WSSC_License::STATUS_INVALID:
                $message = __('Your license key is invalid. Please check your key.', 'woo-stock-sync');
                $notice_class = 'notice-error';
                break;
            default:
                $message = __('License issue detected. Sync is disabled.', 'woo-stock-sync');
        }
        
        ?>
        <div class="notice <?php echo esc_attr($notice_class); ?>">
            <p>
                <strong><?php esc_html_e('Stock Sync:', 'woo-stock-sync'); ?></strong>
                <?php echo esc_html($message); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::MENU_SLUG . '-license')); ?>">
                    <?php esc_html_e('Manage License', 'woo-stock-sync'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Add admin menu
     */
    public function add_menu() {
        // Main menu
        add_menu_page(
            __('Stock Sync', 'woo-stock-sync'),
            __('Stock Sync', 'woo-stock-sync'),
            'manage_woocommerce',
            self::MENU_SLUG,
            [$this, 'render_dashboard_page'],
            'dashicons-update',
            56
        );
        
        // Dashboard submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'woo-stock-sync'),
            __('Dashboard', 'woo-stock-sync'),
            'manage_woocommerce',
            self::MENU_SLUG,
            [$this, 'render_dashboard_page']
        );
        
        // Logs submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Sync Logs', 'woo-stock-sync'),
            __('Logs', 'woo-stock-sync'),
            'manage_woocommerce',
            self::MENU_SLUG . '-logs',
            [$this, 'render_logs_page']
        );
        
        // Settings submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'woo-stock-sync'),
            __('Settings', 'woo-stock-sync'),
            'manage_woocommerce',
            self::MENU_SLUG . '-settings',
            [$this, 'render_settings_page']
        );
        
        // License submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('License', 'woo-stock-sync'),
            __('License', 'woo-stock-sync'),
            'manage_woocommerce',
            self::MENU_SLUG . '-license',
            [$this, 'render_license_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        // Only load on our pages
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'wssc-admin',
            WSSC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WSSC_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'wssc-admin',
            WSSC_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WSSC_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wssc-admin', 'wssc_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wssc_admin_nonce'),
            'strings' => [
                'sync_running' => __('Sync in progress...', 'woo-stock-sync'),
                'sync_complete' => __('Sync completed!', 'woo-stock-sync'),
                'sync_error' => __('Sync failed!', 'woo-stock-sync'),
                'confirm_sync' => __('Are you sure you want to run a manual sync now?', 'woo-stock-sync'),
                'confirm_clear_logs' => __('Are you sure you want to clear all logs?', 'woo-stock-sync'),
                'testing' => __('Testing connection...', 'woo-stock-sync'),
                'saving' => __('Saving...', 'woo-stock-sync'),
                'activating' => __('Activating license...', 'woo-stock-sync'),
                'deactivating' => __('Deactivating license...', 'woo-stock-sync'),
            ],
        ]);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // CSV Settings
        register_setting('wssc_settings', 'wssc_csv_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        
        register_setting('wssc_settings', 'wssc_sku_column', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'sku',
        ]);
        
        register_setting('wssc_settings', 'wssc_quantity_column', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'quantity',
        ]);
        
        // Schedule Settings
        register_setting('wssc_settings', 'wssc_schedule_interval', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'hourly',
        ]);
        
        register_setting('wssc_settings', 'wssc_enabled', [
            'type' => 'boolean',
            'default' => false,
        ]);
        
        register_setting('wssc_settings', 'wssc_disable_ssl', [
            'type' => 'boolean',
            'default' => false,
        ]);
        
        register_setting('wssc_settings', 'wssc_missing_sku_action', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'ignore',
        ]);
    }
    
    /**
     * Render Dashboard Page
     */
    public function render_dashboard_page() {
        $license_valid = WSSC()->license->is_valid();
        $stats = WSSC()->logs->get_stats(30);
        $scheduler_status = WSSC()->scheduler->get_status();
        $recent_logs = WSSC()->logs->get(['limit' => 5, 'type' => 'sync']);
        $chart_data = WSSC()->logs->get_chart_data(14);
        
        include WSSC_PLUGIN_DIR . 'includes/views/dashboard.php';
    }
    
    /**
     * Render Logs Page
     */
    public function render_logs_page() {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : null;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
        
        $logs = WSSC()->logs->get([
            'type' => $type_filter,
            'status' => $status_filter,
            'limit' => $per_page,
            'offset' => $offset,
        ]);
        
        $total = WSSC()->logs->get_count([
            'type' => $type_filter,
            'status' => $status_filter,
        ]);
        
        $total_pages = ceil($total / $per_page);
        
        include WSSC_PLUGIN_DIR . 'includes/views/logs.php';
    }
    
    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        $license_valid = WSSC()->license->is_valid();
        $intervals = WSSC()->scheduler->get_intervals();
        $current_interval = get_option('wssc_schedule_interval', 'hourly');
        $csv_url = get_option('wssc_csv_url', '');
        $sku_column = get_option('wssc_sku_column', 'sku');
        $qty_column = get_option('wssc_quantity_column', 'quantity');
        $enabled = get_option('wssc_enabled', false);
        $disable_ssl = get_option('wssc_disable_ssl', false);
        $missing_sku_action = get_option('wssc_missing_sku_action', 'ignore');
        
        include WSSC_PLUGIN_DIR . 'includes/views/settings.php';
    }
    
    /**
     * Render License Page
     */
    public function render_license_page() {
        // View gets data directly from WSSC()->license
        include WSSC_PLUGIN_DIR . 'includes/views/license.php';
    }
    
    /**
     * Get admin page URL
     */
    public static function get_page_url($page = '') {
        $slug = self::MENU_SLUG;
        if ($page) {
            $slug .= '-' . $page;
        }
        return admin_url('admin.php?page=' . $slug);
    }
}
