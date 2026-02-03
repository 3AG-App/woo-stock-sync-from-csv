<?php
/**
 * License View
 * 
 * Displays different UI based on license status:
 * - active:       Green card with license details and verify/deactivate buttons
 * - expired:      Yellow card with renewal link
 * - cancelled:    Yellow card with renewal link  
 * - paused:       Yellow card with verify button (user can unpause)
 * - suspended:    Yellow card with verify button (payment issue)
 * - invalid:      Red card with enter new key form
 * - domain_limit: Yellow card with info about limit reached
 * - (none):       Standard activation form
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get license info from class methods
$license = WSSC()->license;
$status = $license->get_status();
$status_label = $license->get_status_label();
$license_key = $license->get_key();
$masked_key = $license->get_masked_key();
$license_data = $license->get_data();
$last_check = $license->get_last_check();
$renewal_url = $license->get_renewal_url();

// Status checks
$is_active = ($status === WSSC_License::STATUS_ACTIVE);
$is_expired = ($status === WSSC_License::STATUS_EXPIRED);
$is_cancelled = ($status === WSSC_License::STATUS_CANCELLED);
$is_paused = ($status === WSSC_License::STATUS_PAUSED);
$is_suspended = ($status === WSSC_License::STATUS_SUSPENDED);
$is_invalid = ($status === WSSC_License::STATUS_INVALID);
$is_domain_limit = ($status === WSSC_License::STATUS_DOMAIN_LIMIT);
$has_license = !empty($license_key);

// Group statuses for UI logic
$needs_renewal = $license->needs_renewal(); // expired, cancelled
$can_reactivate = $license->can_reactivate(); // paused, suspended, domain_limit, invalid

// Get license details
$expires_at = isset($license_data['expires_at']) ? $license_data['expires_at'] : null;
$activations = isset($license_data['activations']) ? $license_data['activations'] : null;
$product_name = isset($license_data['product']) ? $license_data['product'] : '';
$package = isset($license_data['package']) ? $license_data['package'] : '';
$remaining_days = $license->get_remaining_days();
$in_grace_period = $license->is_in_grace_period();
$grace_days = $license->get_grace_days_remaining();

// Determine card class and icon
$card_class = 'wssc-license-inactive';
$icon_class = 'dashicons-lock';
if ($is_active) {
    $card_class = 'wssc-license-active';
    $icon_class = 'dashicons-yes-alt';
} elseif ($needs_renewal) {
    $card_class = 'wssc-license-expired';
    $icon_class = 'dashicons-clock';
} elseif ($is_invalid) {
    $card_class = 'wssc-license-invalid';
    $icon_class = 'dashicons-dismiss';
} elseif ($is_domain_limit) {
    $card_class = 'wssc-license-warning';
    $icon_class = 'dashicons-admin-multisite';
} elseif ($is_paused || $is_suspended) {
    $card_class = 'wssc-license-warning';
    $icon_class = 'dashicons-warning';
}
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
        <div class="wssc-section wssc-card wssc-license-card <?php echo esc_attr($card_class); ?>">
            <div class="wssc-card-body">
                <div class="wssc-license-status-display">
                    <div class="wssc-license-icon">
                        <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                    </div>
                    <div class="wssc-license-info">
                        <h2>
                            <?php 
                            if ($is_active) {
                                esc_html_e('License Active', 'woo-stock-sync');
                            } elseif (!$has_license) {
                                esc_html_e('No License', 'woo-stock-sync');
                            } else {
                                // Show actual status label for all other states
                                printf(esc_html__('License %s', 'woo-stock-sync'), esc_html($status_label));
                            }
                            ?>
                        </h2>
                        
                        <?php if ($is_active && $product_name): ?>
                            <p class="wssc-license-product">
                                <?php echo esc_html($product_name); ?>
                                <?php if ($package): ?>
                                    <span class="wssc-license-package"><?php echo esc_html($package); ?></span>
                                <?php endif; ?>
                            </p>
                        <?php elseif ($is_expired): ?>
                            <p><?php esc_html_e('Your license has expired. Renew to continue using sync features.', 'woo-stock-sync'); ?></p>
                        <?php elseif ($is_cancelled): ?>
                            <p><?php esc_html_e('Your subscription has been cancelled. Purchase a new license to continue.', 'woo-stock-sync'); ?></p>
                        <?php elseif ($is_paused): ?>
                            <p><?php esc_html_e('Your subscription is paused. Resume your subscription to restore access.', 'woo-stock-sync'); ?></p>
                        <?php elseif ($is_suspended): ?>
                            <p><?php esc_html_e('Your license is suspended due to a payment issue. Please update your payment method.', 'woo-stock-sync'); ?></p>
                        <?php elseif ($is_domain_limit): ?>
                            <p><?php esc_html_e('You have reached the maximum number of domain activations. Deactivate another domain or upgrade your license.', 'woo-stock-sync'); ?></p>
                        <?php elseif ($is_invalid): ?>
                            <p><?php esc_html_e('This license key was not found. Please check your key or enter a new one.', 'woo-stock-sync'); ?></p>
                        <?php else: ?>
                            <p><?php esc_html_e('Enter your license key to activate sync features.', 'woo-stock-sync'); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($in_grace_period && $grace_days): ?>
                            <p class="wssc-grace-notice">
                                <span class="dashicons dashicons-info"></span>
                                <?php printf(
                                    esc_html__('Network issue detected. License will remain active for %d more days.', 'woo-stock-sync'),
                                    $grace_days
                                ); ?>
                            </p>
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
                                        echo esc_html(wp_date('F j, Y', $expiry_date));
                                        if ($remaining_days !== null) {
                                            if ($remaining_days > 0) {
                                                echo ' <span class="wssc-days-remaining">(' . sprintf(esc_html__('%d days left', 'woo-stock-sync'), $remaining_days) . ')</span>';
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

        <!-- License Management Card -->
        <div class="wssc-section wssc-card">
            <div class="wssc-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php 
                    if ($is_active) {
                        esc_html_e('License Management', 'woo-stock-sync');
                    } elseif ($needs_renewal) {
                        esc_html_e('Renew License', 'woo-stock-sync');
                    } elseif (!$has_license) {
                        esc_html_e('Activate License', 'woo-stock-sync');
                    } else {
                        esc_html_e('Restore License', 'woo-stock-sync');
                    }
                    ?>
                </h2>
            </div>
            <div class="wssc-card-body">
            
                <?php if ($is_active): ?>
                    <!-- ===== ACTIVE LICENSE ===== -->
                    <div class="wssc-license-key-display">
                        <label class="wssc-label"><?php esc_html_e('License Key', 'woo-stock-sync'); ?></label>
                        <div class="wssc-license-key-masked">
                            <span class="wssc-key-value"><?php echo esc_html($masked_key); ?></span>
                        </div>
                    </div>

                    <div class="wssc-license-actions">
                        <button type="button" id="wssc-check-license" class="wssc-btn wssc-btn-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Verify License', 'woo-stock-sync'); ?>
                        </button>
                        <button type="button" id="wssc-deactivate-license" class="wssc-btn wssc-btn-outline-danger">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e('Deactivate', 'woo-stock-sync'); ?>
                        </button>
                    </div>
                    
                <?php elseif ($needs_renewal): ?>
                    <!-- ===== EXPIRED OR CANCELLED - NEEDS RENEWAL ===== -->
                    <div class="wssc-license-key-display">
                        <label class="wssc-label"><?php esc_html_e('License Key', 'woo-stock-sync'); ?></label>
                        <div class="wssc-license-key-masked">
                            <span class="wssc-key-value"><?php echo esc_html($masked_key); ?></span>
                        </div>
                    </div>
                    
                    <div class="wssc-status-notice wssc-notice-warning">
                        <p>
                            <?php if ($is_cancelled): ?>
                                <?php esc_html_e('Your subscription was cancelled. Purchase a new license or renew to restore access.', 'woo-stock-sync'); ?>
                            <?php else: ?>
                                <?php esc_html_e('Renew your license to continue using sync features.', 'woo-stock-sync'); ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="wssc-license-actions">
                        <a href="<?php echo esc_url($renewal_url); ?>" target="_blank" class="wssc-btn wssc-btn-primary">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e('Renew License', 'woo-stock-sync'); ?>
                        </a>
                        <button type="button" id="wssc-check-license" class="wssc-btn wssc-btn-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Verify Status', 'woo-stock-sync'); ?>
                        </button>
                    </div>
                    
                    <div class="wssc-different-license">
                        <p><a href="#" id="wssc-use-different-license"><?php esc_html_e('Use a different license key', 'woo-stock-sync'); ?></a></p>
                    </div>
                    
                    <!-- Hidden form for different license -->
                    <form id="wssc-license-form" class="wssc-form" style="display: none;">
                        <div class="wssc-form-row">
                            <label for="wssc-license-key" class="wssc-label"><?php esc_html_e('New License Key', 'woo-stock-sync'); ?></label>
                            <div class="wssc-input-group">
                                <input type="text" id="wssc-license-key" name="license_key" value="" class="wssc-input wssc-input-lg wssc-input-mono" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
                                <button type="submit" class="wssc-btn wssc-btn-primary">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e('Activate', 'woo-stock-sync'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                    
                <?php elseif ($is_paused || $is_suspended): ?>
                    <!-- ===== PAUSED OR SUSPENDED - CAN BE RESTORED ===== -->
                    <div class="wssc-license-key-display">
                        <label class="wssc-label"><?php esc_html_e('License Key', 'woo-stock-sync'); ?></label>
                        <div class="wssc-license-key-masked">
                            <span class="wssc-key-value"><?php echo esc_html($masked_key); ?></span>
                        </div>
                    </div>
                    
                    <div class="wssc-status-notice wssc-notice-warning">
                        <p>
                            <?php if ($is_paused): ?>
                                <?php esc_html_e('Resume your subscription in your account to restore access. Then click "Verify License".', 'woo-stock-sync'); ?>
                            <?php else: ?>
                                <?php esc_html_e('Please update your payment method to restore access. Then click "Verify License".', 'woo-stock-sync'); ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="wssc-license-actions">
                        <a href="https://3ag.app/account" target="_blank" class="wssc-btn wssc-btn-primary">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e('Manage Account', 'woo-stock-sync'); ?>
                        </a>
                        <button type="button" id="wssc-check-license" class="wssc-btn wssc-btn-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Verify License', 'woo-stock-sync'); ?>
                        </button>
                    </div>
                    
                    <div class="wssc-different-license">
                        <p><a href="#" id="wssc-use-different-license"><?php esc_html_e('Use a different license key', 'woo-stock-sync'); ?></a></p>
                    </div>
                    
                    <!-- Hidden form for different license -->
                    <form id="wssc-license-form" class="wssc-form" style="display: none;">
                        <div class="wssc-form-row">
                            <label for="wssc-license-key" class="wssc-label"><?php esc_html_e('New License Key', 'woo-stock-sync'); ?></label>
                            <div class="wssc-input-group">
                                <input type="text" id="wssc-license-key" name="license_key" value="" class="wssc-input wssc-input-lg wssc-input-mono" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
                                <button type="submit" class="wssc-btn wssc-btn-primary">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e('Activate', 'woo-stock-sync'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                    
                <?php elseif ($is_domain_limit): ?>
                    <!-- ===== DOMAIN LIMIT REACHED ===== -->
                    <div class="wssc-license-key-display">
                        <label class="wssc-label"><?php esc_html_e('License Key', 'woo-stock-sync'); ?></label>
                        <div class="wssc-license-key-masked">
                            <span class="wssc-key-value"><?php echo esc_html($masked_key); ?></span>
                        </div>
                    </div>
                    
                    <div class="wssc-status-notice wssc-notice-warning">
                        <p><?php esc_html_e('Deactivate the license on another site, or upgrade to a higher plan. Then click "Verify License".', 'woo-stock-sync'); ?></p>
                    </div>

                    <div class="wssc-license-actions">
                        <a href="https://3ag.app/account" target="_blank" class="wssc-btn wssc-btn-primary">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e('Manage Activations', 'woo-stock-sync'); ?>
                        </a>
                        <button type="button" id="wssc-check-license" class="wssc-btn wssc-btn-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Verify License', 'woo-stock-sync'); ?>
                        </button>
                    </div>
                    
                    <div class="wssc-different-license">
                        <p><a href="#" id="wssc-use-different-license"><?php esc_html_e('Use a different license key', 'woo-stock-sync'); ?></a></p>
                    </div>
                    
                    <!-- Hidden form for different license -->
                    <form id="wssc-license-form" class="wssc-form" style="display: none;">
                        <div class="wssc-form-row">
                            <label for="wssc-license-key" class="wssc-label"><?php esc_html_e('New License Key', 'woo-stock-sync'); ?></label>
                            <div class="wssc-input-group">
                                <input type="text" id="wssc-license-key" name="license_key" value="" class="wssc-input wssc-input-lg wssc-input-mono" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
                                <button type="submit" class="wssc-btn wssc-btn-primary">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e('Activate', 'woo-stock-sync'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                    
                <?php elseif ($is_invalid): ?>
                    <!-- ===== INVALID LICENSE ===== -->
                    <?php if ($has_license): ?>
                        <div class="wssc-license-key-display wssc-invalid-key">
                            <label class="wssc-label"><?php esc_html_e('Invalid License Key', 'woo-stock-sync'); ?></label>
                            <div class="wssc-license-key-masked">
                                <span class="wssc-key-value"><?php echo esc_html($masked_key); ?></span>
                            </div>
                        </div>
                        
                        <div class="wssc-invalid-notice">
                            <p><?php esc_html_e('If this license has been corrected on our server, click "Verify License" to check again.', 'woo-stock-sync'); ?></p>
                        </div>

                        <div class="wssc-license-actions">
                            <button type="button" id="wssc-check-license" class="wssc-btn wssc-btn-primary">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Verify License', 'woo-stock-sync'); ?>
                            </button>
                            <button type="button" id="wssc-deactivate-license" class="wssc-btn wssc-btn-outline-danger">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php esc_html_e('Remove License', 'woo-stock-sync'); ?>
                            </button>
                        </div>
                        
                        <div class="wssc-different-license">
                            <p><a href="#" id="wssc-use-different-license"><?php esc_html_e('Use a different license key', 'woo-stock-sync'); ?></a></p>
                        </div>
                        
                        <!-- Hidden form for different license -->
                        <form id="wssc-license-form" class="wssc-form" style="display: none;">
                            <div class="wssc-form-row">
                                <label for="wssc-license-key" class="wssc-label"><?php esc_html_e('New License Key', 'woo-stock-sync'); ?></label>
                                <div class="wssc-input-group">
                                    <input type="text" id="wssc-license-key" name="license_key" value="" class="wssc-input wssc-input-lg wssc-input-mono" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
                                    <button type="submit" class="wssc-btn wssc-btn-primary">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php esc_html_e('Activate', 'woo-stock-sync'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- No key stored, show activation form -->
                        <form id="wssc-license-form" class="wssc-form">
                            <div class="wssc-form-row">
                                <label for="wssc-license-key" class="wssc-label">
                                    <?php esc_html_e('Enter Valid License Key', 'woo-stock-sync'); ?>
                                    <span class="wssc-required">*</span>
                                </label>
                                <div class="wssc-input-group">
                                    <input type="text" id="wssc-license-key" name="license_key" value="" class="wssc-input wssc-input-lg wssc-input-mono" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
                                    <button type="submit" class="wssc-btn wssc-btn-primary">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php esc_html_e('Activate', 'woo-stock-sync'); ?>
                                    </button>
                                </div>
                                <p class="wssc-help-text">
                                    <?php esc_html_e('Please enter a valid license key. Check your purchase confirmation email.', 'woo-stock-sync'); ?>
                                </p>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- ===== NO LICENSE ===== -->
                    <form id="wssc-license-form" class="wssc-form">
                        <div class="wssc-form-row">
                            <label for="wssc-license-key" class="wssc-label">
                                <?php esc_html_e('License Key', 'woo-stock-sync'); ?>
                                <span class="wssc-required">*</span>
                            </label>
                            <div class="wssc-input-group">
                                <input type="text" id="wssc-license-key" name="license_key" value="" class="wssc-input wssc-input-lg wssc-input-mono" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
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
                                '<a href="https://3ag.app/products/woo-stock-sync-from-csv" target="_blank">' . esc_html__('Purchase here', 'woo-stock-sync') . '</a>'
                            );
                        } else {
                            printf(
                                /* translators: %s: link to purchase page */
                                esc_html__("Don't have a license? %s", 'woo-stock-sync'),
                                '<a href="https://3ag.app/products/woo-stock-sync-from-csv" target="_blank">' . esc_html__('Purchase here', 'woo-stock-sync') . '</a>'
                            );
                        }
                        ?>
                    </p>
                    <p>
                        <span class="dashicons dashicons-email"></span>
                        <?php 
                        printf(
                            esc_html__('Need help? %s', 'woo-stock-sync'),
                            '<a href="mailto:support@3ag.app">' . esc_html__('Contact support', 'woo-stock-sync') . '</a>'
                        );
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Updates Info Card -->
        <div class="wssc-section wssc-card">
            <div class="wssc-card-header">
                <h2>
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Plugin Updates', 'woo-stock-sync'); ?>
                </h2>
            </div>
            <div class="wssc-card-body">
                <p class="wssc-update-version">
                    <strong><?php esc_html_e('Current Version:', 'woo-stock-sync'); ?></strong> 
                    <?php echo esc_html(WSSC_VERSION); ?>
                </p>
                
                <div class="wssc-update-actions">
                    <button type="button" id="wssc-check-update" class="wssc-btn wssc-btn-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Check for Updates', 'woo-stock-sync'); ?>
                    </button>
                </div>
                
                <div id="wssc-update-result" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>
