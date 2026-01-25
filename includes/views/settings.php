<?php
/**
 * Settings View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wssc-wrap">
    <div class="wssc-header">
        <div class="wssc-header-left">
            <h1><?php esc_html_e('Settings', 'woo-stock-sync'); ?></h1>
            <p class="wssc-subtitle"><?php esc_html_e('Configure your stock synchronization settings', 'woo-stock-sync'); ?></p>
        </div>
    </div>

    <?php if (!$license_valid): ?>
        <div class="wssc-notice wssc-notice-warning">
            <span class="dashicons dashicons-lock"></span>
            <div>
                <strong><?php esc_html_e('License Required', 'woo-stock-sync'); ?></strong>
                <p><?php esc_html_e('Please activate your license before configuring settings.', 'woo-stock-sync'); ?></p>
                <a href="<?php echo esc_url(WSSC_Admin::get_page_url('license')); ?>" class="wssc-btn wssc-btn-primary wssc-btn-sm">
                    <?php esc_html_e('Activate License', 'woo-stock-sync'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <form id="wssc-settings-form" class="wssc-form <?php echo !$license_valid ? 'wssc-form-disabled' : ''; ?>">
        <!-- CSV Configuration -->
        <div class="wssc-section wssc-card">
            <div class="wssc-card-header">
                <h2>
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php esc_html_e('CSV Configuration', 'woo-stock-sync'); ?>
                </h2>
            </div>
            <div class="wssc-card-body">
                <div class="wssc-form-row">
                    <label for="wssc-csv-url" class="wssc-label">
                        <?php esc_html_e('CSV URL', 'woo-stock-sync'); ?>
                        <span class="wssc-required">*</span>
                    </label>
                    <div class="wssc-input-group">
                        <input type="url" 
                               id="wssc-csv-url" 
                               name="csv_url" 
                               value="<?php echo esc_attr($csv_url); ?>" 
                               class="wssc-input wssc-input-lg"
                               placeholder="https://example.com/stock.csv"
                               <?php disabled(!$license_valid); ?>>
                        <button type="button" id="wssc-test-connection" class="wssc-btn wssc-btn-secondary" <?php disabled(!$license_valid); ?>>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Test Connection', 'woo-stock-sync'); ?>
                        </button>
                        <button type="button" id="wssc-preview-csv" class="wssc-btn wssc-btn-ghost" <?php disabled(!$license_valid); ?>>
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e('Preview', 'woo-stock-sync'); ?>
                        </button>
                    </div>
                    <p class="wssc-help-text">
                        <?php esc_html_e('Enter the full URL to your CSV file containing stock data.', 'woo-stock-sync'); ?>
                    </p>
                </div>

                <!-- CSV Preview -->
                <div id="wssc-csv-preview" class="wssc-csv-preview" style="display: none;">
                    <h4><?php esc_html_e('CSV Preview', 'woo-stock-sync'); ?></h4>
                    <div class="wssc-preview-content"></div>
                </div>

                <div class="wssc-form-row">
                    <label class="wssc-label">
                        <?php esc_html_e('SSL Verification', 'woo-stock-sync'); ?>
                    </label>
                    <div class="wssc-toggle-row">
                        <label class="wssc-switch">
                            <input type="checkbox" id="wssc-disable-ssl" name="disable_ssl" value="1" <?php checked($disable_ssl); ?> <?php disabled(!$license_valid); ?>>
                            <span class="wssc-slider"></span>
                        </label>
                        <span class="wssc-toggle-label">
                            <?php esc_html_e('Disable SSL certificate verification', 'woo-stock-sync'); ?>
                        </span>
                    </div>
                    <p class="wssc-help-text wssc-help-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Only disable this for development or if your CSV source uses a self-signed certificate. Not recommended for production.', 'woo-stock-sync'); ?>
                    </p>
                </div>

                <div class="wssc-form-row wssc-form-row-2col">
                    <div class="wssc-form-col">
                        <label for="wssc-sku-column" class="wssc-label">
                            <?php esc_html_e('SKU Column Name', 'woo-stock-sync'); ?>
                        </label>
                        <input type="text" 
                               id="wssc-sku-column" 
                               name="sku_column" 
                               value="<?php echo esc_attr($sku_column); ?>" 
                               class="wssc-input"
                               placeholder="sku"
                               <?php disabled(!$license_valid); ?>>
                        <p class="wssc-help-text">
                            <?php esc_html_e('The column header name for SKU in your CSV file.', 'woo-stock-sync'); ?>
                        </p>
                    </div>
                    <div class="wssc-form-col">
                        <label for="wssc-qty-column" class="wssc-label">
                            <?php esc_html_e('Quantity Column Name', 'woo-stock-sync'); ?>
                        </label>
                        <input type="text" 
                               id="wssc-qty-column" 
                               name="quantity_column" 
                               value="<?php echo esc_attr($qty_column); ?>" 
                               class="wssc-input"
                               placeholder="quantity"
                               <?php disabled(!$license_valid); ?>>
                        <p class="wssc-help-text">
                            <?php esc_html_e('The column header name for stock quantity in your CSV file.', 'woo-stock-sync'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Configuration -->
        <div class="wssc-section wssc-card">
            <div class="wssc-card-header">
                <h2>
                    <span class="dashicons dashicons-clock"></span>
                    <?php esc_html_e('Schedule Configuration', 'woo-stock-sync'); ?>
                </h2>
            </div>
            <div class="wssc-card-body">
                <div class="wssc-form-row">
                    <label for="wssc-schedule-interval" class="wssc-label">
                        <?php esc_html_e('Sync Interval', 'woo-stock-sync'); ?>
                    </label>
                    <select id="wssc-schedule-interval" name="schedule_interval" class="wssc-select" <?php disabled(!$license_valid); ?>>
                        <?php foreach ($intervals as $key => $interval): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($current_interval, $key); ?>>
                                <?php echo esc_html($interval['display']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="wssc-help-text">
                        <?php esc_html_e('How often the stock sync should run automatically.', 'woo-stock-sync'); ?>
                    </p>
                </div>

                <div class="wssc-form-row">
                    <label class="wssc-label">
                        <?php esc_html_e('Enable Automatic Sync', 'woo-stock-sync'); ?>
                    </label>
                    <div class="wssc-toggle-row">
                        <label class="wssc-switch">
                            <input type="checkbox" id="wssc-enabled" name="enabled" value="1" <?php checked($enabled); ?> <?php disabled(!$license_valid); ?>>
                            <span class="wssc-slider"></span>
                        </label>
                        <span class="wssc-toggle-label">
                            <?php esc_html_e('Enable scheduled stock synchronization', 'woo-stock-sync'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Box -->
        <div class="wssc-section wssc-card wssc-card-info">
            <div class="wssc-card-body">
                <div class="wssc-info-grid">
                    <div class="wssc-info-item">
                        <span class="dashicons dashicons-shield"></span>
                        <div>
                            <strong><?php esc_html_e('Watchdog Protection', 'woo-stock-sync'); ?></strong>
                            <p><?php esc_html_e('A watchdog cron runs every 4 hours to ensure the sync schedule is working properly.', 'woo-stock-sync'); ?></p>
                        </div>
                    </div>
                    <div class="wssc-info-item">
                        <span class="dashicons dashicons-performance"></span>
                        <div>
                            <strong><?php esc_html_e('Optimized for Large Catalogs', 'woo-stock-sync'); ?></strong>
                            <p><?php esc_html_e('Supports 4000+ products with batch processing for optimal performance.', 'woo-stock-sync'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="wssc-form-actions">
            <button type="submit" class="wssc-btn wssc-btn-primary wssc-btn-lg" <?php disabled(!$license_valid); ?>>
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e('Save Settings', 'woo-stock-sync'); ?>
            </button>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div id="wssc-preview-modal" class="wssc-modal">
    <div class="wssc-modal-content wssc-modal-lg">
        <div class="wssc-modal-header">
            <h3><?php esc_html_e('CSV Preview', 'woo-stock-sync'); ?></h3>
            <button type="button" class="wssc-modal-close">&times;</button>
        </div>
        <div class="wssc-modal-body" id="wssc-preview-modal-body">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>
