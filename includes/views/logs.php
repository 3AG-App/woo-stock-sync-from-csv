<?php
/**
 * Logs View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wssc-wrap">
    <div class="wssc-header">
        <div class="wssc-header-left">
            <h1><?php esc_html_e('Sync Logs', 'woo-stock-sync'); ?></h1>
            <p class="wssc-subtitle"><?php esc_html_e('View history of all sync operations', 'woo-stock-sync'); ?></p>
        </div>
        <div class="wssc-header-right">
            <button type="button" id="wssc-clear-logs" class="wssc-btn wssc-btn-danger">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Clear Logs', 'woo-stock-sync'); ?>
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="wssc-section">
        <div class="wssc-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="woo-stock-sync-logs">
                
                <div class="wssc-filter-group">
                    <label><?php esc_html_e('Type', 'woo-stock-sync'); ?></label>
                    <select name="type" class="wssc-select">
                        <option value=""><?php esc_html_e('All Types', 'woo-stock-sync'); ?></option>
                        <option value="sync" <?php selected($type_filter, 'sync'); ?>><?php esc_html_e('Sync', 'woo-stock-sync'); ?></option>
                        <option value="watchdog" <?php selected($type_filter, 'watchdog'); ?>><?php esc_html_e('Watchdog', 'woo-stock-sync'); ?></option>
                        <option value="license" <?php selected($type_filter, 'license'); ?>><?php esc_html_e('License', 'woo-stock-sync'); ?></option>
                    </select>
                </div>
                
                <div class="wssc-filter-group">
                    <label><?php esc_html_e('Status', 'woo-stock-sync'); ?></label>
                    <select name="status" class="wssc-select">
                        <option value=""><?php esc_html_e('All Statuses', 'woo-stock-sync'); ?></option>
                        <option value="success" <?php selected($status_filter, 'success'); ?>><?php esc_html_e('Success', 'woo-stock-sync'); ?></option>
                        <option value="error" <?php selected($status_filter, 'error'); ?>><?php esc_html_e('Error', 'woo-stock-sync'); ?></option>
                        <option value="warning" <?php selected($status_filter, 'warning'); ?>><?php esc_html_e('Warning', 'woo-stock-sync'); ?></option>
                    </select>
                </div>
                
                <button type="submit" class="wssc-btn wssc-btn-secondary">
                    <span class="dashicons dashicons-filter"></span>
                    <?php esc_html_e('Filter', 'woo-stock-sync'); ?>
                </button>
                
                <?php if ($type_filter || $status_filter): ?>
                    <a href="<?php echo esc_url(WSSC_Admin::get_page_url('logs')); ?>" class="wssc-btn wssc-btn-ghost">
                        <?php esc_html_e('Reset', 'woo-stock-sync'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="wssc-section">
        <?php if (empty($logs)): ?>
            <div class="wssc-empty-state">
                <span class="dashicons dashicons-format-aside"></span>
                <p><?php esc_html_e('No logs found.', 'woo-stock-sync'); ?></p>
            </div>
        <?php else: ?>
            <div class="wssc-table-wrap">
                <table class="wssc-table">
                    <thead>
                        <tr>
                            <th class="wssc-col-status"><?php esc_html_e('Status', 'woo-stock-sync'); ?></th>
                            <th class="wssc-col-type"><?php esc_html_e('Type', 'woo-stock-sync'); ?></th>
                            <th class="wssc-col-trigger"><?php esc_html_e('Trigger', 'woo-stock-sync'); ?></th>
                            <th class="wssc-col-message"><?php esc_html_e('Message', 'woo-stock-sync'); ?></th>
                            <th class="wssc-col-stats"><?php esc_html_e('Stats', 'woo-stock-sync'); ?></th>
                            <th class="wssc-col-duration"><?php esc_html_e('Duration', 'woo-stock-sync'); ?></th>
                            <th class="wssc-col-date"><?php esc_html_e('Date', 'woo-stock-sync'); ?></th>
                            <th class="wssc-col-actions"><?php esc_html_e('Actions', 'woo-stock-sync'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr class="wssc-log-row wssc-log-<?php echo esc_attr($log->status); ?>">
                                <td class="wssc-col-status">
                                    <span class="wssc-status-badge wssc-status-<?php echo esc_attr($log->status); ?>">
                                        <?php 
                                        switch ($log->status) {
                                            case 'success':
                                                echo '<span class="dashicons dashicons-yes-alt"></span>';
                                                break;
                                            case 'error':
                                                echo '<span class="dashicons dashicons-dismiss"></span>';
                                                break;
                                            case 'warning':
                                                echo '<span class="dashicons dashicons-warning"></span>';
                                                break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="wssc-col-type">
                                    <span class="wssc-type-badge wssc-type-<?php echo esc_attr($log->type); ?>">
                                        <?php echo esc_html(ucfirst($log->type)); ?>
                                    </span>
                                </td>
                                <td class="wssc-col-trigger">
                                    <?php if ($log->trigger_type): ?>
                                        <span class="wssc-trigger-badge wssc-trigger-<?php echo esc_attr($log->trigger_type); ?>">
                                            <?php 
                                            echo $log->trigger_type === 'scheduled' 
                                                ? '<span class="dashicons dashicons-clock"></span>' 
                                                : '<span class="dashicons dashicons-admin-users"></span>'; 
                                            ?>
                                            <?php echo esc_html(ucfirst($log->trigger_type)); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="wssc-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="wssc-col-message">
                                    <span class="wssc-message-text"><?php echo esc_html($log->message); ?></span>
                                </td>
                                <td class="wssc-col-stats">
                                    <?php if (!empty($log->stats) && is_array($log->stats)): ?>
                                        <div class="wssc-mini-stats">
                                            <?php if (isset($log->stats['updated'])): ?>
                                                <span class="wssc-mini-stat" title="<?php esc_attr_e('Updated', 'woo-stock-sync'); ?>">
                                                    <span class="dashicons dashicons-yes"></span>
                                                    <?php echo esc_html($log->stats['updated']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (isset($log->stats['skipped'])): ?>
                                                <span class="wssc-mini-stat" title="<?php esc_attr_e('Skipped', 'woo-stock-sync'); ?>">
                                                    <span class="dashicons dashicons-minus"></span>
                                                    <?php echo esc_html($log->stats['skipped']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (isset($log->stats['not_found']) && $log->stats['not_found'] > 0): ?>
                                                <span class="wssc-mini-stat wssc-mini-stat-warning" title="<?php esc_attr_e('Not Found', 'woo-stock-sync'); ?>">
                                                    <span class="dashicons dashicons-warning"></span>
                                                    <?php echo esc_html($log->stats['not_found']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (isset($log->stats['errors']) && $log->stats['errors'] > 0): ?>
                                                <span class="wssc-mini-stat wssc-mini-stat-error" title="<?php esc_attr_e('Errors', 'woo-stock-sync'); ?>">
                                                    <span class="dashicons dashicons-dismiss"></span>
                                                    <?php echo esc_html($log->stats['errors']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="wssc-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="wssc-col-duration">
                                    <?php if ($log->duration > 0): ?>
                                        <?php echo esc_html(number_format($log->duration, 2)); ?>s
                                    <?php else: ?>
                                        <span class="wssc-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="wssc-col-date">
                                    <span class="wssc-date" title="<?php echo esc_attr(wp_date('Y-m-d H:i:s', strtotime($log->created_at))); ?>">
                                        <?php echo esc_html(human_time_diff(strtotime($log->created_at))); ?> <?php esc_html_e('ago', 'woo-stock-sync'); ?>
                                    </span>
                                </td>
                                <td class="wssc-col-actions">
                                    <button type="button" class="wssc-btn wssc-btn-icon wssc-view-log" data-log-id="<?php echo esc_attr($log->id); ?>" title="<?php esc_attr_e('View Details', 'woo-stock-sync'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="wssc-pagination">
                    <div class="wssc-pagination-info">
                        <?php 
                        printf(
                            esc_html__('Showing %1$d-%2$d of %3$d logs', 'woo-stock-sync'),
                            $offset + 1,
                            min($offset + $per_page, $total),
                            $total
                        ); 
                        ?>
                    </div>
                    <div class="wssc-pagination-links">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $page - 1)); ?>" class="wssc-btn wssc-btn-sm wssc-btn-secondary">
                                &larr; <?php esc_html_e('Previous', 'woo-stock-sync'); ?>
                            </a>
                        <?php endif; ?>
                        
                        <span class="wssc-page-num">
                            <?php 
                            printf(
                                esc_html__('Page %1$d of %2$d', 'woo-stock-sync'),
                                $page,
                                $total_pages
                            ); 
                            ?>
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $page + 1)); ?>" class="wssc-btn wssc-btn-sm wssc-btn-secondary">
                                <?php esc_html_e('Next', 'woo-stock-sync'); ?> &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Log Details Modal -->
<div id="wssc-log-modal" class="wssc-modal">
    <div class="wssc-modal-content wssc-modal-lg">
        <div class="wssc-modal-header">
            <h3><?php esc_html_e('Log Details', 'woo-stock-sync'); ?></h3>
            <button type="button" class="wssc-modal-close">&times;</button>
        </div>
        <div class="wssc-modal-body" id="wssc-log-modal-body">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>
