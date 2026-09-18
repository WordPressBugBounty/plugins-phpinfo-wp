<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

Phpinfo_WP_Activity_Log::maybe_create_table();

$paged       = max(1, (int) ($_GET['paged'] ?? 1));
$category    = sanitize_text_field($_GET['log_cat'] ?? 'all');
$severity    = sanitize_text_field($_GET['log_sev'] ?? 'all');
$search      = sanitize_text_field($_GET['log_search'] ?? '');
$user_filter = sanitize_text_field($_GET['log_user'] ?? '');
$date_from   = sanitize_text_field($_GET['date_from'] ?? '');
$date_to     = sanitize_text_field($_GET['date_to'] ?? '');

$per_page    = 15;

// Filter for plugin operations & server actions
$cat_filter = $category;
if ($category === 'all') {
    $cat_filter = ['server', 'settings'];
}

$log_data = Phpinfo_WP_Activity_Log::get_logs([
    'category'  => $cat_filter,
    'severity'  => $severity,
    'search'    => $search,
    'user'      => $user_filter,
    'date_from' => $date_from,
    'date_to'   => $date_to,
    'paged'     => $paged,
    'per_page'  => $per_page,
]);

$items       = $log_data['items'];
$total       = $log_data['total'];
$total_pages = $log_data['total_pages'];
$cat_counts  = Phpinfo_WP_Activity_Log::get_category_counts();

$activity_categories = [
    'all'      => ['label' => __('All Plugin Operations', 'phpinfo-wp'), 'icon' => 'dashicons-list-view'],
    'server'   => ['label' => __('Server & Config', 'phpinfo-wp'),       'icon' => 'dashicons-database'],
    'settings' => ['label' => __('Settings Changes', 'phpinfo-wp'),      'icon' => 'dashicons-admin-settings'],
];

$sev_colors = [
    'info'     => ['bg' => '#f0fdf4', 'text' => '#15803d', 'border' => '#bbf7d0', 'label' => 'INFO'],
    'warning'  => ['bg' => '#fefce8', 'text' => '#a16207', 'border' => '#fef08a', 'label' => 'WARNING'],
    'critical' => ['bg' => '#fef2f2', 'text' => '#b91c1c', 'border' => '#fecaca', 'label' => 'CRITICAL'],
];

$nonce = wp_create_nonce('phpinfowp_activity_log_nonce');
?>

<div class="phpinfowp-pro-page phpinfowp-log-page">

    <div class="phpinfowp-page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:nowrap; gap:16px; margin-bottom:20px;">
        <div>
            <h1 style="margin:0; font-size:22px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <?php _e('Plugin Activity & Operations Log', 'phpinfo-wp'); ?>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Audit trail of phpinfo() WP server actions, configuration changes, 1-click auto-fixes, snapshots, and troubleshooting sessions.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-shrink:0;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-admin-log')); ?>" class="button button-primary" style="display:inline-flex; align-items:center; gap:6px; background:#6366f1; border-color:#6366f1; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                <span class="dashicons dashicons-shield" style="font-size:16px; width:16px; height:16px;"></span>
                <?php _e('View WordPress Admin Log &rarr;', 'phpinfo-wp'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg([
                'action'   => 'phpinfowp_export_activity_log',
                'nonce'    => $nonce,
                'category' => $category,
                'severity' => $severity,
                'search'   => $search,
            ], admin_url('admin-ajax.php'))); ?>" class="button button-secondary" style="display:inline-flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-download" style="font-size:16px; width:16px; height:16px;"></span>
                <?php _e('Export CSV', 'phpinfo-wp'); ?>
            </a>
        </div>
    </div>

    <!-- Category Filter Tabs -->
    <div class="phpinfowp-log-tabs" style="display:flex; gap:8px; flex-wrap:wrap; padding-bottom:12px; margin-bottom:16px; border-bottom:1px solid #e2e8f0;">
        <?php foreach ($activity_categories as $cat_key => $cat_meta): 
            $is_active = ($category === $cat_key);
            $count = ($cat_key === 'all') 
                ? (($cat_counts['server'] ?? 0) + ($cat_counts['settings'] ?? 0))
                : ($cat_counts[$cat_key] ?? 0);
            $tab_url = add_query_arg([
                'page'       => 'piwp-log',
                'log_cat'    => $cat_key,
                'log_sev'    => $severity,
                'log_search' => $search,
                'paged'      => 1,
            ], admin_url('admin.php'));
        ?>
            <a href="<?php echo esc_url($tab_url); ?>" 
               style="display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:6px; font-size:13px; font-weight:600; text-decoration:none; white-space:nowrap; transition:all 0.15s ease; <?php echo $is_active ? 'background:#777BB3; color:#fff;' : 'background:#fff; color:#475569; border:1px solid #e2e8f0;'; ?>">
                <span class="dashicons <?php echo esc_attr($cat_meta['icon']); ?>" style="font-size:15px; width:15px; height:15px; line-height:15px; <?php echo $is_active ? 'color:#fff;' : 'color:#64748b;'; ?>"></span>
                <?php echo esc_html($cat_meta['label']); ?>
                <span style="font-size:11px; padding:1px 6px; border-radius:10px; <?php echo $is_active ? 'background:rgba(255,255,255,0.25); color:#fff;' : 'background:#f1f5f9; color:#475569;'; ?>">
                    <?php echo number_format($count); ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Filters & Search Bar -->
    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:14px 18px; margin-bottom:20px; display:flex; gap:14px; align-items:center; flex-wrap:wrap; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
        <input type="hidden" name="page" value="piwp-log">
        <input type="hidden" name="log_cat" value="<?php echo esc_attr($category); ?>">

        <div style="flex:1; min-width:200px; display:flex; align-items:center; position:relative;">
            <input type="search" name="log_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by keyword, directive, or operation...', 'phpinfo-wp'); ?>" style="width:100%; height:34px; padding-left:10px; border-radius:6px; border:1px solid #d8dae5;">
        </div>

        <div>
            <select name="log_sev" style="height:34px; padding:0 30px 0 12px; min-width:145px; border-radius:6px; border:1px solid #d8dae5; font-size:13px; color:#1e293b; background-color:#fff;">
                <option value="all" <?php selected($severity, 'all'); ?>><?php _e('All Severities', 'phpinfo-wp'); ?></option>
                <option value="info" <?php selected($severity, 'info'); ?>><?php _e('Info Only', 'phpinfo-wp'); ?></option>
                <option value="warning" <?php selected($severity, 'warning'); ?>><?php _e('Warnings Only', 'phpinfo-wp'); ?></option>
                <option value="critical" <?php selected($severity, 'critical'); ?>><?php _e('Critical Only', 'phpinfo-wp'); ?></option>
            </select>
        </div>

        <div style="display:flex; gap:6px; align-items:center;">
            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" style="height:34px; padding:0 10px; border-radius:6px; border:1px solid #d8dae5; color-scheme:light; font-size:13px; color:#1e293b; background:#fff;" title="<?php esc_attr_e('From Date', 'phpinfo-wp'); ?>">
            <span style="color:#94a3b8;">&rarr;</span>
            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="height:34px; padding:0 10px; border-radius:6px; border:1px solid #d8dae5; color-scheme:light; font-size:13px; color:#1e293b; background:#fff;" title="<?php esc_attr_e('To Date', 'phpinfo-wp'); ?>">
        </div>

        <button type="submit" class="button button-primary" style="height:34px; background:#777BB3; border-color:#777BB3; color:#fff; font-weight:600; padding:0 16px; border-radius:6px;">
            <?php _e('Filter', 'phpinfo-wp'); ?>
        </button>

        <?php if ($search || $severity !== 'all' || $date_from || $date_to || $category !== 'all'): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-log')); ?>" class="button button-secondary" style="height:34px; line-height:32px; border-radius:6px;">
                <?php _e('Reset', 'phpinfo-wp'); ?>
            </a>
        <?php endif; ?>
    </form>

    <!-- Activity Log Feed -->
    <?php if (empty($items)): ?>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            <span class="dashicons dashicons-database" style="font-size:42px; width:42px; height:42px; color:#94a3b8; display:inline-block; margin-bottom:12px;"></span>
            <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('No plugin activity records found', 'phpinfo-wp'); ?></h3>
            <p style="margin:0; font-size:13px; color:#64748b; max-width:480px; margin-left:auto; margin-right:auto;">
                <?php if ($search || $severity !== 'all' || $category !== 'all'): ?>
                    <?php _e('No records matched your current filter criteria.', 'phpinfo-wp'); ?>
                <?php else: ?>
                    <?php _e('Server operations, 1-click auto-fixes, snapshots, and troubleshooting sessions will appear here automatically.', 'phpinfo-wp'); ?>
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>

        <table class="wp-list-table widefat striped phpinfowp-activity-table" style="margin:0 0 20px;">
            <thead>
                <tr>
                    <th style="width:130px;"><?php _e('Time (UTC)', 'phpinfo-wp'); ?></th>
                    <th style="width:90px;"><?php _e('Severity', 'phpinfo-wp'); ?></th>
                    <th style="width:180px;"><?php _e('User / IP', 'phpinfo-wp'); ?></th>
                    <th style="width:140px;"><?php _e('Category', 'phpinfo-wp'); ?></th>
                    <th><?php _e('Operation Description', 'phpinfo-wp'); ?></th>
                    <th style="width:70px; text-align:center;"><?php _e('Details', 'phpinfo-wp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $row): 
                    $sev_meta = $sev_colors[$row['severity']] ?? $sev_colors['info'];
                    $has_details = !empty($row['details']);
                    $dt_utc = strtotime($row['created_at'] . ' UTC');
                    $time_diff = human_time_diff($dt_utc, time()) . ' ' . __('ago', 'phpinfo-wp');
                    $row_id = 'activity-log-row-' . $row['id'];
                ?>
                    <tr>
                        <td style="font-size:12px; color:#64748b;">
                            <strong style="color:#0f172a; display:block;"><?php echo esc_html($time_diff); ?></strong>
                            <span style="font-size:11px;" title="<?php echo esc_attr($row['created_at']); ?>">
                                <?php echo esc_html(wp_date('M j, H:i:s', $dt_utc)); ?>
                            </span>
                        </td>
                        <td>
                            <span style="display:inline-block; font-size:10px; font-weight:800; padding:2px 7px; border-radius:4px; background:<?php echo esc_attr($sev_meta['bg']); ?>; color:<?php echo esc_attr($sev_meta['text']); ?>; border:1px solid <?php echo esc_attr($sev_meta['border']); ?>; text-transform:uppercase; letter-spacing:0.4px;">
                                <?php echo esc_html($sev_meta['label']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($row['user_login'])): ?>
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <span class="dashicons dashicons-admin-users" style="font-size:15px; width:15px; height:15px; color:#777BB3;"></span>
                                    <strong style="color:#0f172a; font-size:13px;"><?php echo esc_html($row['user_login']); ?></strong>
                                </div>
                            <?php else: ?>
                                <span style="color:#64748b; font-style:italic; font-size:12px;"><?php _e('System', 'phpinfo-wp'); ?></span>
                            <?php endif; ?>
                            <div style="font-size:11.5px; color:#94a3b8; font-family:ui-monospace, monospace; margin-top:2px;">
                                <?php echo esc_html($row['ip_address']); ?>
                            </div>
                        </td>
                        <td>
                            <span style="display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:600; color:#334155;">
                                <span class="dashicons <?php echo esc_attr($activity_categories[$row['category']]['icon'] ?? 'dashicons-marker'); ?>" style="font-size:14px; width:14px; height:14px; color:#64748b;"></span>
                                <?php echo esc_html($activity_categories[$row['category']]['label'] ?? ucfirst($row['category'])); ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color:#0f172a; font-size:13.5px; display:block; line-height:1.4;">
                                <?php echo esc_html($row['object_name']); ?>
                            </strong>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($has_details): ?>
                                <button type="button" class="button button-small phpinfowp-toggle-details-btn" data-target="<?php echo esc_attr($row_id); ?>" style="padding:0 8px; font-size:11.5px; height:26px; line-height:24px; border-radius:4px;">
                                    <?php _e('View', 'phpinfo-wp'); ?>
                                </button>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($has_details): 
                        $decoded = json_decode($row['details'], true);
                        if (!is_array($decoded)) $decoded = [];
                    ?>
                        <tr id="<?php echo esc_attr($row_id); ?>" class="phpinfowp-details-row" style="display:none; background:#f8fafc;">
                            <td colspan="6" style="padding:14px 20px; border-bottom:1px solid #e2e8f0;">
                                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:14px 18px; box-shadow:0 1px 2px rgba(0,0,0,0.03);">
                                    <div style="font-size:12px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:8px; border-bottom:1px solid #f1f5f9; padding-bottom:6px;">
                                        <?php _e('Operation Details', 'phpinfo-wp'); ?>
                                    </div>
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; font-size:12.5px;">
                                        <?php if (isset($decoded['old_value']) && isset($decoded['new_value'])): ?>
                                            <div style="grid-column:1 / -1;">
                                                <span style="color:#64748b; font-weight:600; display:block; font-size:11px; text-transform:uppercase; margin-bottom:4px;"><?php _e('Value Change', 'phpinfo-wp'); ?>:</span>
                                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                                    <span style="background:#fee2e2; color:#991b1b; padding:3px 8px; border-radius:4px; font-family:monospace;"><?php echo esc_html($decoded['old_value'] ?: '(empty)'); ?></span>
                                                    <span style="color:#64748b; font-weight:bold;">&rarr;</span>
                                                    <span style="background:#dcfce7; color:#166534; padding:3px 8px; border-radius:4px; font-family:monospace; font-weight:bold;"><?php echo esc_html($decoded['new_value'] ?: '(empty)'); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($decoded['applied'])): ?>
                                            <div style="grid-column:1 / -1;">
                                                <span style="color:#64748b; font-weight:600; display:block; font-size:11px; text-transform:uppercase; margin-bottom:4px;"><?php _e('Directives Applied', 'phpinfo-wp'); ?>:</span>
                                                <code style="background:#f1f5f9; padding:4px 8px; border-radius:4px;"><?php echo esc_html(implode(', ', (array)$decoded['applied'])); ?></code>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px;">
                <div style="font-size:13px; color:#64748b;">
                    <?php printf(
                        __('Showing %1$d &ndash; %2$d of %3$s records', 'phpinfo-wp'),
                        ($paged - 1) * $per_page + 1,
                        min($total, $paged * $per_page),
                        number_format($total)
                    ); ?>
                </div>
                <div style="display:flex; gap:6px;">
                    <?php if ($paged > 2): ?>
                        <a href="<?php echo esc_url(add_query_arg(['paged' => 1])); ?>" class="button button-secondary" title="<?php esc_attr_e('First page', 'phpinfo-wp'); ?>">&laquo;&laquo;</a>
                    <?php endif; ?>
                    <?php if ($paged > 1): ?>
                        <a href="<?php echo esc_url(add_query_arg(['paged' => $paged - 1])); ?>" class="button button-secondary">&laquo; <?php _e('Previous', 'phpinfo-wp'); ?></a>
                    <?php endif; ?>
                    <span style="font-size:13px; line-height:30px; padding:0 8px; color:#334155; font-weight:600;">
                        <?php printf(__('Page %d of %d', 'phpinfo-wp'), $paged, $total_pages); ?>
                    </span>
                    <?php if ($paged < $total_pages): ?>
                        <a href="<?php echo esc_url(add_query_arg(['paged' => $paged + 1])); ?>" class="button button-secondary"><?php _e('Next', 'phpinfo-wp'); ?> &raquo;</a>
                    <?php endif; ?>
                    <?php if ($paged < $total_pages - 1): ?>
                        <a href="<?php echo esc_url(add_query_arg(['paged' => $total_pages])); ?>" class="button button-secondary" title="<?php esc_attr_e('Last page', 'phpinfo-wp'); ?>">&raquo;&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.phpinfowp-toggle-details-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.getAttribute('data-target');
            var row = document.getElementById(targetId);
            if (!row) return;

            if (row.style.display === 'none' || !row.style.display) {
                row.style.display = 'table-row';
                this.textContent = '<?php echo esc_js(__('Hide', 'phpinfo-wp')); ?>';
            } else {
                row.style.display = 'none';
                this.textContent = '<?php echo esc_js(__('View', 'phpinfo-wp')); ?>';
            }
        });
    });
});
</script>