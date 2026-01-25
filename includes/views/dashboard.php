<?php
/**
 * Dashboard View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wssc-wrap">
    <div class="wssc-header">
        <div class="wssc-header-left">
            <h1><?php esc_html_e('Stock Sync Dashboard', 'woo-stock-sync'); ?></h1>
            <p class="wssc-subtitle"><?php esc_html_e('Monitor and manage your stock synchronization', 'woo-stock-sync'); ?></p>
        </div>
        <div class="wssc-header-right">
            <?php if ($license_valid): ?>
                <span class="wssc-license-badge wssc-license-active">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('License Active', 'woo-stock-sync'); ?>
                </span>
            <?php else: ?>
                <a href="<?php echo esc_url(WSSC_Admin::get_page_url('license')); ?>" class="wssc-license-badge wssc-license-inactive">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('License Required', 'woo-stock-sync'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$license_valid): ?>
        <div class="wssc-notice wssc-notice-warning">
            <span class="dashicons dashicons-lock"></span>
            <div>
                <strong><?php esc_html_e('License Required', 'woo-stock-sync'); ?></strong>
                <p><?php esc_html_e('Please activate your license to enable stock synchronization.', 'woo-stock-sync'); ?></p>
                <a href="<?php echo esc_url(WSSC_Admin::get_page_url('license')); ?>" class="wssc-btn wssc-btn-primary wssc-btn-sm">
                    <?php esc_html_e('Activate License', 'woo-stock-sync'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="wssc-section">
        <div class="wssc-quick-actions">
            <div class="wssc-action-card wssc-sync-status-card">
                <div class="wssc-action-icon <?php echo $scheduler_status['enabled'] ? 'wssc-status-active' : 'wssc-status-inactive'; ?>">
                    <span class="dashicons dashicons-update-alt"></span>
                </div>
                <div class="wssc-action-content">
                    <h3><?php esc_html_e('Sync Status', 'woo-stock-sync'); ?></h3>
                    <p class="wssc-status-text">
                        <?php if ($scheduler_status['enabled']): ?>
                            <span class="wssc-status-dot wssc-status-dot-active"></span>
                            <?php esc_html_e('Active', 'woo-stock-sync'); ?>
                            - <?php echo esc_html($scheduler_status['interval_display']); ?>
                        <?php else: ?>
                            <span class="wssc-status-dot wssc-status-dot-inactive"></span>
                            <?php esc_html_e('Disabled', 'woo-stock-sync'); ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($scheduler_status['next_run']): ?>
                        <p class="wssc-next-run">
                            <?php 
                            printf(
                                esc_html__('Next run: %s', 'woo-stock-sync'),
                                esc_html($scheduler_status['next_run_human'])
                            ); 
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="wssc-action-toggle">
                    <label class="wssc-switch" <?php echo !$license_valid ? 'title="' . esc_attr__('License required', 'woo-stock-sync') . '"' : ''; ?>>
                        <input type="checkbox" id="wssc-toggle-sync" <?php checked($scheduler_status['enabled']); ?> <?php disabled(!$license_valid); ?>>
                        <span class="wssc-slider"></span>
                    </label>
                </div>
            </div>

            <div class="wssc-action-card">
                <div class="wssc-action-icon wssc-icon-sync">
                    <span class="dashicons dashicons-controls-repeat"></span>
                </div>
                <div class="wssc-action-content">
                    <h3><?php esc_html_e('Manual Sync', 'woo-stock-sync'); ?></h3>
                    <p><?php esc_html_e('Run sync now regardless of schedule', 'woo-stock-sync'); ?></p>
                </div>
                <button type="button" id="wssc-run-sync" class="wssc-btn wssc-btn-primary" <?php disabled(!$license_valid); ?>>
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Run Now', 'woo-stock-sync'); ?>
                </button>
            </div>

            <div class="wssc-action-card">
                <div class="wssc-action-icon wssc-icon-time">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="wssc-action-content">
                    <h3><?php esc_html_e('Last Sync', 'woo-stock-sync'); ?></h3>
                    <p>
                        <?php if ($scheduler_status['last_sync']): ?>
                            <?php echo esc_html($scheduler_status['last_sync_human']); ?>
                        <?php else: ?>
                            <?php esc_html_e('Never', 'woo-stock-sync'); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <a href="<?php echo esc_url(WSSC_Admin::get_page_url('logs')); ?>" class="wssc-btn wssc-btn-secondary">
                    <?php esc_html_e('View Logs', 'woo-stock-sync'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="wssc-section">
        <h2 class="wssc-section-title"><?php esc_html_e('Last 30 Days Statistics', 'woo-stock-sync'); ?></h2>
        <div class="wssc-stats-grid">
            <div class="wssc-stat-card">
                <div class="wssc-stat-icon wssc-stat-icon-syncs">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="wssc-stat-content">
                    <span class="wssc-stat-value"><?php echo esc_html($stats['total_syncs']); ?></span>
                    <span class="wssc-stat-label"><?php esc_html_e('Total Syncs', 'woo-stock-sync'); ?></span>
                </div>
            </div>

            <div class="wssc-stat-card">
                <div class="wssc-stat-icon wssc-stat-icon-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="wssc-stat-content">
                    <span class="wssc-stat-value"><?php echo esc_html($stats['success_rate']); ?>%</span>
                    <span class="wssc-stat-label"><?php esc_html_e('Success Rate', 'woo-stock-sync'); ?></span>
                </div>
            </div>

            <div class="wssc-stat-card">
                <div class="wssc-stat-icon wssc-stat-icon-products">
                    <span class="dashicons dashicons-products"></span>
                </div>
                <div class="wssc-stat-content">
                    <span class="wssc-stat-value"><?php echo esc_html(number_format($stats['total_updated'])); ?></span>
                    <span class="wssc-stat-label"><?php esc_html_e('Products Updated', 'woo-stock-sync'); ?></span>
                </div>
            </div>

            <div class="wssc-stat-card">
                <div class="wssc-stat-icon wssc-stat-icon-time">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="wssc-stat-content">
                    <span class="wssc-stat-value"><?php echo esc_html($stats['avg_duration']); ?>s</span>
                    <span class="wssc-stat-label"><?php esc_html_e('Avg. Duration', 'woo-stock-sync'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="wssc-section">
        <h2 class="wssc-section-title"><?php esc_html_e('Sync Activity (Last 14 Days)', 'woo-stock-sync'); ?></h2>
        <div class="wssc-chart-container">
            <canvas id="wssc-activity-chart"></canvas>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="wssc-section">
        <div class="wssc-section-header">
            <h2 class="wssc-section-title"><?php esc_html_e('Recent Activity', 'woo-stock-sync'); ?></h2>
            <a href="<?php echo esc_url(WSSC_Admin::get_page_url('logs')); ?>" class="wssc-link">
                <?php esc_html_e('View All', 'woo-stock-sync'); ?> →
            </a>
        </div>
        
        <?php if (empty($recent_logs)): ?>
            <div class="wssc-empty-state">
                <span class="dashicons dashicons-format-aside"></span>
                <p><?php esc_html_e('No sync activity yet.', 'woo-stock-sync'); ?></p>
            </div>
        <?php else: ?>
            <div class="wssc-activity-list">
                <?php foreach ($recent_logs as $log): ?>
                    <div class="wssc-activity-item">
                        <div class="wssc-activity-status wssc-activity-status-<?php echo esc_attr($log->status); ?>">
                            <span class="dashicons dashicons-<?php echo $log->status === 'success' ? 'yes-alt' : 'warning'; ?>"></span>
                        </div>
                        <div class="wssc-activity-content">
                            <p class="wssc-activity-message"><?php echo esc_html($log->message); ?></p>
                            <div class="wssc-activity-meta">
                                <span class="wssc-activity-trigger">
                                    <?php 
                                    echo $log->trigger_type === 'scheduled' 
                                        ? esc_html__('Scheduled', 'woo-stock-sync') 
                                        : esc_html__('Manual', 'woo-stock-sync'); 
                                    ?>
                                </span>
                                <span class="wssc-activity-time">
                                    <?php echo esc_html(human_time_diff(strtotime($log->created_at))); ?> <?php esc_html_e('ago', 'woo-stock-sync'); ?>
                                </span>
                                <?php if (!empty($log->stats) && isset($log->stats['updated'])): ?>
                                    <span class="wssc-activity-stats">
                                        <?php 
                                        printf(
                                            esc_html__('%d updated', 'woo-stock-sync'),
                                            intval($log->stats['updated'])
                                        ); 
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="button" class="wssc-btn wssc-btn-ghost wssc-view-log" data-log-id="<?php echo esc_attr($log->id); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Details Modal -->
<div id="wssc-log-modal" class="wssc-modal">
    <div class="wssc-modal-content">
        <div class="wssc-modal-header">
            <h3><?php esc_html_e('Sync Log Details', 'woo-stock-sync'); ?></h3>
            <button type="button" class="wssc-modal-close">&times;</button>
        </div>
        <div class="wssc-modal-body" id="wssc-log-modal-body">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<script>
// Chart data
var wsscChartData = <?php echo wp_json_encode($chart_data); ?>;
</script>
