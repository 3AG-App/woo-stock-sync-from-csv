<?php
/**
 * License View
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_active = $license_status === 'active';
$expires_at = isset($license_data['expires_at']) ? $license_data['expires_at'] : null;
$activations = isset($license_data['activations']) ? $license_data['activations'] : null;
$product_name = isset($license_data['product']) ? $license_data['product'] : '';
$package = isset($license_data['package']) ? $license_data['package'] : '';
?>

<div class="wssc-wrap">
    <div class="wssc-header">
        <div class="wssc-header-left">
            <h1><?php esc_html_e('License', 'woo-stock-sync'); ?></h1>
            <p class="wssc-subtitle"><?php esc_html_e('Manage your plugin license activation', 'woo-stock-sync'); ?></p>
        </div>
    </div>

    <div class="wssc-license-container">
        <!-- License Status Card -->
        <div class="wssc-section wssc-card wssc-license-card <?php echo $is_active ? 'wssc-license-active' : 'wssc-license-inactive'; ?>">
            <div class="wssc-card-body">
                <div class="wssc-license-status-display">
                    <div class="wssc-license-icon">
                        <?php if ($is_active): ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-lock"></span>
                        <?php endif; ?>
                    </div>
                    <div class="wssc-license-info">
                        <h2>
                            <?php if ($is_active): ?>
                                <?php esc_html_e('License Active', 'woo-stock-sync'); ?>
                            <?php else: ?>
                                <?php esc_html_e('License Not Active', 'woo-stock-sync'); ?>
                            <?php endif; ?>
                        </h2>
                        <?php if ($is_active && $product_name): ?>
                            <p class="wssc-license-product">
                                <?php echo esc_html($product_name); ?>
                                <?php if ($package): ?>
                                    <span class="wssc-license-package"><?php echo esc_html($package); ?></span>
                                <?php endif; ?>
                            </p>
                        <?php elseif (!$is_active): ?>
                            <p><?php esc_html_e('Enter your license key to activate premium features.', 'woo-stock-sync'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_active): ?>
                    <div class="wssc-license-details">
                        <div class="wssc-license-detail-grid">
                            <!-- Expiration -->
                            <div class="wssc-license-detail-item">
                                <span class="wssc-detail-label"><?php esc_html_e('Expires', 'woo-stock-sync'); ?></span>
                                <span class="wssc-detail-value">
                                    <?php if ($expires_at): ?>
                                        <?php 
                                        $expiry_date = strtotime($expires_at);
                                        $remaining = WSSC()->license->get_remaining_days();
                                        echo esc_html(wp_date('F j, Y', $expiry_date));
                                        if ($remaining !== null) {
                                            if ($remaining > 0) {
                                                echo ' <span class="wssc-days-remaining">(' . sprintf(esc_html__('%d days left', 'woo-stock-sync'), $remaining) . ')</span>';
                                            } else {
                                                echo ' <span class="wssc-expired">(' . esc_html__('Expired', 'woo-stock-sync') . ')</span>';
                                            }
                                        }
                                        ?>
                                    <?php else: ?>
                                        <span class="wssc-lifetime"><?php esc_html_e('Lifetime', 'woo-stock-sync'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <!-- Activations -->
                            <?php if ($activations): ?>
                                <div class="wssc-license-detail-item">
                                    <span class="wssc-detail-label"><?php esc_html_e('Activations', 'woo-stock-sync'); ?></span>
                                    <span class="wssc-detail-value">
                                        <?php 
                                        printf(
                                            esc_html__('%1$d of %2$d used', 'woo-stock-sync'),
                                            intval($activations['used']),
                                            intval($activations['limit'])
                                        ); 
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- Last Verified -->
                            <?php if ($last_check): ?>
                                <div class="wssc-license-detail-item">
                                    <span class="wssc-detail-label"><?php esc_html_e('Last Verified', 'woo-stock-sync'); ?></span>
                                    <span class="wssc-detail-value wssc-local-time" data-timestamp="<?php echo esc_attr($last_check); ?>">
                                        <?php echo esc_html(human_time_diff($last_check, time())); ?> <?php esc_html_e('ago', 'woo-stock-sync'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- License Form Card -->
        <div class="wssc-section wssc-card">
            <div class="wssc-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php echo $is_active ? esc_html__('License Management', 'woo-stock-sync') : esc_html__('Activate License', 'woo-stock-sync'); ?>
                </h2>
            </div>
            <div class="wssc-card-body">
                <?php if ($is_active): ?>
                    <!-- Active License Actions -->
                    <div class="wssc-license-key-display">
                        <label class="wssc-label"><?php esc_html_e('Current License Key', 'woo-stock-sync'); ?></label>
                        <div class="wssc-license-key-masked">
                            <span class="wssc-key-value">
                                <?php 
                                $key_length = strlen($license_key);
                                if ($key_length > 8) {
                                    $masked_key = substr($license_key, 0, 4) . str_repeat('•', $key_length - 8) . substr($license_key, -4);
                                } elseif ($key_length > 4) {
                                    $masked_key = substr($license_key, 0, 2) . str_repeat('•', $key_length - 2);
                                } else {
                                    $masked_key = str_repeat('•', $key_length);
                                }
                                echo esc_html($masked_key);
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="wssc-license-actions">
                        <button type="button" id="wssc-check-license" class="wssc-btn wssc-btn-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Verify License', 'woo-stock-sync'); ?>
                        </button>
                        <button type="button" id="wssc-deactivate-license" class="wssc-btn wssc-btn-danger">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e('Deactivate License', 'woo-stock-sync'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <!-- License Activation Form -->
                    <form id="wssc-license-form" class="wssc-form">
                        <div class="wssc-form-row">
                            <label for="wssc-license-key" class="wssc-label">
                                <?php esc_html_e('License Key', 'woo-stock-sync'); ?>
                                <span class="wssc-required">*</span>
                            </label>
                            <div class="wssc-input-group">
                                <input type="text" 
                                       id="wssc-license-key" 
                                       name="license_key" 
                                       value="" 
                                       class="wssc-input wssc-input-lg wssc-input-mono"
                                       placeholder="XXXX-XXXX-XXXX-XXXX"
                                       autocomplete="off">
                                <button type="submit" class="wssc-btn wssc-btn-primary">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e('Activate', 'woo-stock-sync'); ?>
                                </button>
                            </div>
                            <p class="wssc-help-text">
                                <?php esc_html_e('Enter the license key you received after purchase.', 'woo-stock-sync'); ?>
                            </p>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="wssc-license-help">
                    <p>
                        <span class="dashicons dashicons-info-outline"></span>
                        <?php 
                        if ($is_active) {
                            printf(
                                esc_html__('Need more licenses? %s', 'woo-stock-sync'),
                                '<a href="https://3ag.app/products/woo-stock-sync-from-csv" target="_blank">' . esc_html__('Purchase one here', 'woo-stock-sync') . '</a>'
                            );
                        } else {
                            printf(
                                esc_html__('Don\'t have a license? %s', 'woo-stock-sync'),
                                '<a href="https://3ag.app/products/woo-stock-sync-from-csv" target="_blank">' . esc_html__('Purchase one here', 'woo-stock-sync') . '</a>'
                            );
                        }
                        ?>
                    </p>
                    <p>
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php 
                        printf(
                            esc_html__('Manage your licenses and domain activations: %s', 'woo-stock-sync'),
                            '<a href="https://3ag.app/dashboard/licenses" target="_blank">' . esc_html__('License Dashboard', 'woo-stock-sync') . '</a>'
                        ); 
                        ?>
                    </p>
                    <p>
                        <span class="dashicons dashicons-email"></span>
                        <?php 
                        printf(
                            esc_html__('Need help? Contact support: %s', 'woo-stock-sync'),
                            '<a href="mailto:info@3ag.app">info@3ag.app</a>'
                        ); 
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Plugin Updates Card -->
        <?php 
        $update_data = get_transient('wssc_update_data');
        $current_version = WSSC_VERSION;
        $has_update = $update_data && !empty($update_data['version']) && version_compare($current_version, $update_data['version'], '<');
        ?>
        <div class="wssc-section wssc-card">
            <div class="wssc-card-header">
                <h2>
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Plugin Updates', 'woo-stock-sync'); ?>
                </h2>
            </div>
            <div class="wssc-card-body">
                <div class="wssc-update-status">
                    <div class="wssc-version-info">
                        <div class="wssc-version-row">
                            <span class="wssc-version-label"><?php esc_html_e('Installed Version:', 'woo-stock-sync'); ?></span>
                            <span class="wssc-version-value"><?php echo esc_html($current_version); ?></span>
                        </div>
                        <?php if ($update_data && !empty($update_data['version'])): ?>
                        <div class="wssc-version-row">
                            <span class="wssc-version-label"><?php esc_html_e('Latest Version:', 'woo-stock-sync'); ?></span>
                            <span class="wssc-version-value <?php echo $has_update ? 'wssc-version-new' : ''; ?>">
                                <?php echo esc_html($update_data['version']); ?>
                                <?php if ($has_update): ?>
                                    <span class="wssc-update-badge"><?php esc_html_e('Update Available', 'woo-stock-sync'); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if (!empty($update_data['checked'])): ?>
                        <div class="wssc-version-row">
                            <span class="wssc-version-label"><?php esc_html_e('Last Checked:', 'woo-stock-sync'); ?></span>
                            <span class="wssc-version-value wssc-muted wssc-local-time" data-timestamp="<?php echo esc_attr($update_data['checked']); ?>">
                                <?php echo esc_html(human_time_diff($update_data['checked'], time())); ?> <?php esc_html_e('ago', 'woo-stock-sync'); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="wssc-update-actions">
                        <button type="button" id="wssc-check-update" class="wssc-btn wssc-btn-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Check for Updates', 'woo-stock-sync'); ?>
                        </button>
                        <?php if ($has_update): ?>
                        <button type="button" id="wssc-install-update" class="wssc-btn wssc-btn-primary" data-version="<?php echo esc_attr($update_data['version']); ?>">
                            <span class="dashicons dashicons-download"></span>
                            <?php printf(esc_html__('Update to %s', 'woo-stock-sync'), esc_html($update_data['version'])); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <p class="wssc-help-text wssc-muted" style="margin-top: 15px;">
                        <span class="dashicons dashicons-external"></span>
                        <?php 
                        printf(
                            esc_html__('Updates are fetched from %s', 'woo-stock-sync'),
                            '<a href="https://github.com/SourovCodes/woo-stock-sync-from-csv/releases" target="_blank">GitHub Releases</a>'
                        ); 
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Features Info -->
        <div class="wssc-section wssc-card wssc-card-info">
            <div class="wssc-card-header">
                <h2>
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e('Premium Features', 'woo-stock-sync'); ?>
                </h2>
            </div>
            <div class="wssc-card-body">
                <ul class="wssc-features-list">
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Automatic scheduled stock synchronization', 'woo-stock-sync'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Custom CSV column mapping', 'woo-stock-sync'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Support for 4000+ products', 'woo-stock-sync'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Detailed sync logs and history', 'woo-stock-sync'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Watchdog cron for reliability', 'woo-stock-sync'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Priority email support', 'woo-stock-sync'); ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
