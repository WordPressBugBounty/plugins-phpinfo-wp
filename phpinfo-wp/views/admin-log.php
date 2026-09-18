<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

Phpinfo_WP_Activity_Log::maybe_create_table();

$paged       = max(1, (int) ($_GET['paged'] ?? 1));
$category    = sanitize_text_field($_GET['log_cat'] ?? 'all');
$severity    = sanitize_text_field($_GET['log_sev'] ?? 'all');
$search      = sanitize_text_field($_GET['log_search'] ?? '');
$user_filter = sanitize_text_field($_GET['log_user'] ?? '');
$date_from   = sanitize_text_field($_GET['date_from'] ?? '');
$date_to     = sanitize_text_field($_GET['date_to'] ?? '');

$per_page    = 15;

// Exclude server-only internal operations from Admin Log unless specifically selected
$cat_filter = $category;
if ($category === 'all') {
    $cat_filter = ['auth', 'plugins', 'themes', 'settings', 'users', 'posts', 'media'];
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

$admin_categories = [
    'all'      => ['label' => __('All Admin Events', 'phpinfo-wp'), 'icon' => 'dashicons-list-view'],
    'auth'     => ['label' => __('Authentication', 'phpinfo-wp'),   'icon' => 'dashicons-lock'],
    'plugins'  => ['label' => __('Plugins', 'phpinfo-wp'),          'icon' => 'dashicons-admin-plugins'],
    'themes'   => ['label' => __('Themes', 'phpinfo-wp'),           'icon' => 'dashicons-admin-appearance'],
    'posts'    => ['label' => __('Content', 'phpinfo-wp'),          'icon' => 'dashicons-edit'],
    'media'    => ['label' => __('Media', 'phpinfo-wp'),            'icon' => 'dashicons-admin-media'],
    'users'    => ['label' => __('Users', 'phpinfo-wp'),            'icon' => 'dashicons-admin-users'],
    'settings' => ['label' => __('Settings', 'phpinfo-wp'),         'icon' => 'dashicons-admin-settings'],
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
                <?php _e('WordPress Admin Log', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Full audit trail tracking user logins, plugin/theme changes, media uploads, content publishing, and user updates.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-shrink:0;">
            <?php if ($is_free_preview): ?>
                <button type="button" class="button button-secondary" disabled style="opacity:0.6; cursor:not-allowed; display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-lock" style="font-size:16px; width:16px; height:16px;"></span>
                    <?php _e('Export CSV', 'phpinfo-wp'); ?>
                </button>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$is_free_preview): ?>
    <!-- Category Filter Tabs -->
    <div class="phpinfowp-log-tabs" style="display:flex; gap:8px; flex-wrap:wrap; padding-bottom:12px; margin-bottom:16px; border-bottom:1px solid #e2e8f0;">
        <?php foreach ($admin_categories as $cat_key => $cat_meta): 
            $is_active = ($category === $cat_key);
            $count = ($cat_key === 'all') 
                ? array_sum(array_intersect_key($cat_counts, array_flip(['auth', 'plugins', 'themes', 'posts', 'media', 'users', 'settings']))) 
                : ($cat_counts[$cat_key] ?? 0);
            $tab_url = add_query_arg([
                'page'       => 'piwp-admin-log',
                'log_cat'    => $cat_key,
                'log_sev'    => $severity,
                'log_search' => $search,
                'paged'      => 1,
            ], admin_url('admin.php'));
        ?>
            <a href="<?php echo esc_url($tab_url); ?>" 
               style="display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:6px; font-size:13px; font-weight:600; text-decoration:none; white-space:nowrap; transition:all 0.15s ease; <?php echo $is_active ? 'background:#6366f1; color:#fff;' : 'background:#fff; color:#475569; border:1px solid #e2e8f0;'; ?>">
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
        <input type="hidden" name="page" value="piwp-admin-log">
        <input type="hidden" name="log_cat" value="<?php echo esc_attr($category); ?>">

        <div style="flex:1; min-width:200px; display:flex; align-items:center; position:relative;">
            <input type="search" name="log_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by keyword, user, IP, or event...', 'phpinfo-wp'); ?>" style="width:100%; height:34px; padding-left:10px; border-radius:6px; border:1px solid #d8dae5;">
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

        <button type="submit" class="button button-primary" style="height:34px; background:#6366f1; border-color:#6366f1; color:#fff; font-weight:600; padding:0 16px; border-radius:6px;">
            <?php _e('Filter', 'phpinfo-wp'); ?>
        </button>

        <?php if ($search || $severity !== 'all' || $date_from || $date_to || $category !== 'all'): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-admin-log')); ?>" class="button button-secondary" style="height:34px; line-height:32px; border-radius:6px;">
                <?php _e('Reset', 'phpinfo-wp'); ?>
            </a>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <!-- Activity Log Feed or Pro Lock -->
    <?php if ($is_free_preview): ?>
        <!-- Free preview: contextual preview cards + centered upgrade card -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
            
            <!-- Background contextual preview cards -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                
                <!-- Preview Row 1 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #00a32a; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; gap:14px; align-items:center;">
                        <span style="font-size:10.5px; font-weight:800; background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; padding:2px 7px; border-radius:4px;">INFO</span>
                        <div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <strong style="color:#0f172a; font-size:13px;"><?php _e('User "admin" logged in', 'phpinfo-wp'); ?></strong>
                                <span style="font-size:11px; color:#64748b; background:#f1f5f9; padding:1px 5px; border-radius:3px;">administrator</span>
                            </div>
                            <div style="font-size:11.5px; color:#94a3b8; font-family:monospace; margin-top:2px;">
                                127.0.0.1 &bull; 8 <?php _e('minutes ago', 'phpinfo-wp'); ?> &bull; <?php _e('Authentication', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                    </div>
                    <span style="font-size:11.5px; color:#64748b; background:#f8fafc; border:1px solid #e2e8f0; padding:3px 10px; border-radius:4px;"><?php _e('View', 'phpinfo-wp'); ?></span>
                </div>

                <!-- Preview Row 2 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #6366f1; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; gap:14px; align-items:center;">
                        <span style="font-size:10.5px; font-weight:800; background:#eef2ff; color:#4338ca; border:1px solid #e0e7ff; padding:2px 7px; border-radius:4px;">INFO</span>
                        <div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <strong style="color:#0f172a; font-size:13px;"><?php _e('Activated plugin "WooCommerce"', 'phpinfo-wp'); ?></strong>
                                <span style="font-size:11px; color:#64748b; background:#f1f5f9; padding:1px 5px; border-radius:3px;">administrator</span>
                            </div>
                            <div style="font-size:11.5px; color:#94a3b8; font-family:monospace; margin-top:2px;">
                                127.0.0.1 &bull; 2 <?php _e('hours ago', 'phpinfo-wp'); ?> &bull; <?php _e('Plugins', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                    </div>
                    <span style="font-size:11.5px; color:#64748b; background:#f8fafc; border:1px solid #e2e8f0; padding:3px 10px; border-radius:4px;"><?php _e('View', 'phpinfo-wp'); ?></span>
                </div>

                <!-- Preview Row 3 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #d63638; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; gap:14px; align-items:center;">
                        <span style="font-size:10.5px; font-weight:800; background:#fefce8; color:#a16207; border:1px solid #fef08a; padding:2px 7px; border-radius:4px;">WARNING</span>
                        <div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <strong style="color:#0f172a; font-size:13px;"><?php _e('Trashed Page "(Untitled ID #30)"', 'phpinfo-wp'); ?></strong>
                                <span style="font-size:11px; color:#64748b; background:#f1f5f9; padding:1px 5px; border-radius:3px;">administrator</span>
                            </div>
                            <div style="font-size:11.5px; color:#94a3b8; font-family:monospace; margin-top:2px;">
                                127.0.0.1 &bull; 1 <?php _e('day ago', 'phpinfo-wp'); ?> &bull; <?php _e('Content', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                    </div>
                    <span style="font-size:11.5px; color:#64748b; background:#f8fafc; border:1px solid #e2e8f0; padding:3px 10px; border-radius:4px;"><?php _e('View', 'phpinfo-wp'); ?></span>
                </div>

            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

            <!-- Floating Upgrade Card -->
            <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                <span class="dashicons dashicons-shield" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('WordPress Admin Audit Log Locked', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                    <?php _e('Track user authentication, unauthorized role escalations, plugin installations, and content modifications in real time.', 'phpinfo-wp'); ?>
                </p>
                <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">🔐</span>
                        <span><strong><?php _e('User Authentication &amp; Login Tracker:', 'phpinfo-wp'); ?></strong> <?php _e('Audit successful and failed administrator logins with user IPs and browser metadata.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">📦</span>
                        <span><strong><?php _e('Plugin &amp; Theme Change History:', 'phpinfo-wp'); ?></strong> <?php _e('Record who installed, updated, activated, or deleted plugins and themes.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">📄</span>
                        <span><strong><?php _e('Content &amp; Settings Audit Trail:', 'phpinfo-wp'); ?></strong> <?php _e('Log deleted posts, modified site options, and user role updates for full accountability.', 'phpinfo-wp'); ?></span>
                    </div>
                </div>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                    <?php _e('Unlock Admin Log with Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>
    <?php elseif (empty($items)): ?>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            <span class="dashicons dashicons-shield" style="font-size:42px; width:42px; height:42px; color:#94a3b8; display:inline-block; margin-bottom:12px;"></span>
            <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('No admin activity records found', 'phpinfo-wp'); ?></h3>
            <p style="margin:0; font-size:13px; color:#64748b; max-width:480px; margin-left:auto; margin-right:auto;">
                <?php if ($search || $severity !== 'all' || $category !== 'all'): ?>
                    <?php _e('No events matched your current filter criteria. Try broadening your search or resetting filters.', 'phpinfo-wp'); ?>
                <?php else: ?>
                    <?php _e('Admin actions will be recorded here automatically as users log in, update settings, upload media, or modify plugins.', 'phpinfo-wp'); ?>
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
                    <th><?php _e('Event Description', 'phpinfo-wp'); ?></th>
                    <th style="width:70px; text-align:center;"><?php _e('Details', 'phpinfo-wp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $row): 
                    $sev_meta = $sev_colors[$row['severity']] ?? $sev_colors['info'];
                    $has_details = !empty($row['details']);
                    $dt_utc = strtotime($row['created_at'] . ' UTC');
                    $time_diff = human_time_diff($dt_utc, time()) . ' ' . __('ago', 'phpinfo-wp');
                    $row_id = 'admin-log-row-' . $row['id'];
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
                                    <?php if (!empty($row['user_role'])): ?>
                                        <span style="font-size:10px; padding:1px 5px; background:#f1f5f9; color:#475569; border-radius:3px;">
                                            <?php echo esc_html($row['user_role']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#64748b; font-style:italic; font-size:12px;"><?php _e('System / Guest', 'phpinfo-wp'); ?></span>
                            <?php endif; ?>
                            <div style="font-size:11.5px; color:#94a3b8; font-family:ui-monospace, monospace; margin-top:2px;">
                                <?php echo esc_html($row['ip_address']); ?>
                            </div>
                        </td>
                        <td>
                            <span style="display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:600; color:#334155;">
                                <span class="dashicons <?php echo esc_attr($admin_categories[$row['category']]['icon'] ?? 'dashicons-marker'); ?>" style="font-size:14px; width:14px; height:14px; color:#64748b;"></span>
                                <?php echo esc_html($admin_categories[$row['category']]['label'] ?? ucfirst($row['category'])); ?>
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
                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:10px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">
                                        <div style="font-size:12px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.4px;">
                                            <?php _e('Activity Information & Direct Actions', 'phpinfo-wp'); ?>
                                        </div>
                                        <div>
                                            <?php if (!empty($decoded['post_id'])): 
                                                $post_id = (int)$decoded['post_id'];
                                                $post_obj = get_post($post_id);
                                            ?>
                                                <?php if ($post_obj): ?>
                                                    <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" class="button button-small button-primary" style="margin-right:6px; background:#777BB3; border-color:#777BB3;">
                                                        <?php printf(__('Edit %s &rarr;', 'phpinfo-wp'), ucfirst($decoded['type'] ?? 'Item')); ?>
                                                    </a>
                                                    <?php if ($post_obj->post_status === 'publish'): ?>
                                                        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank" rel="noopener" class="button button-small">
                                                            <?php _e('View Live &rarr;', 'phpinfo-wp'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php elseif (!empty($decoded['user_id'])): ?>
                                                <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . (int)$decoded['user_id'])); ?>" class="button button-small button-primary" style="background:#777BB3; border-color:#777BB3;">
                                                    <?php _e('View User Profile &rarr;', 'phpinfo-wp'); ?>
                                                </a>
                                            <?php elseif ($row['category'] === 'media' && !empty($decoded['post_id'])): ?>
                                                <a href="<?php echo esc_url(admin_url('upload.php?item=' . (int)$decoded['post_id'])); ?>" class="button button-small button-primary" style="background:#777BB3; border-color:#777BB3;">
                                                    <?php _e('View in Media Library &rarr;', 'phpinfo-wp'); ?>
                                                </a>
                                            <?php elseif (!empty($decoded['plugin_file']) || $row['category'] === 'plugins'): ?>
                                                <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-small">
                                                    <?php _e('Manage Plugins &rarr;', 'phpinfo-wp'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Key/Value Fields -->
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; font-size:12.5px;">
                                        <?php if (!empty($decoded['post_id'])): ?>
                                            <div>
                                                <span style="color:#64748b; font-weight:600; display:block; font-size:11px; text-transform:uppercase;"><?php _e('Content Type', 'phpinfo-wp'); ?>:</span>
                                                <strong style="color:#0f172a;"><?php echo esc_html(ucfirst($decoded['type'] ?? 'post')); ?></strong> (ID #<?php echo esc_html($decoded['post_id']); ?>)
                                            </div>
                                        <?php endif; ?>

                                        <?php if (isset($decoded['old_value']) && isset($decoded['new_value'])): ?>
                                            <div style="grid-column:1 / -1;">
                                                <span style="color:#64748b; font-weight:600; display:block; font-size:11px; text-transform:uppercase; margin-bottom:4px;"><?php _e('Setting Modification', 'phpinfo-wp'); ?>:</span>
                                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                                    <span style="background:#fee2e2; color:#991b1b; padding:3px 8px; border-radius:4px; font-family:monospace;"><?php echo esc_html($decoded['old_value'] ?: '(empty)'); ?></span>
                                                    <span style="color:#64748b; font-weight:bold;">&rarr;</span>
                                                    <span style="background:#dcfce7; color:#166534; padding:3px 8px; border-radius:4px; font-family:monospace; font-weight:bold;"><?php echo esc_html($decoded['new_value'] ?: '(empty)'); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($decoded['version'])): ?>
                                            <div>
                                                <span style="color:#64748b; font-weight:600; display:block; font-size:11px; text-transform:uppercase;"><?php _e('Version', 'phpinfo-wp'); ?>:</span>
                                                <span style="color:#0f172a; font-family:monospace;">v<?php echo esc_html($decoded['version']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($row['ip_address'])): ?>
                                            <div>
                                                <span style="color:#64748b; font-weight:600; display:block; font-size:11px; text-transform:uppercase;"><?php _e('Client IP', 'phpinfo-wp'); ?>:</span>
                                                <span style="color:#0f172a; font-family:monospace;"><?php echo esc_html($row['ip_address']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($row['user_agent'])): ?>
                                            <div style="grid-column:1 / -1;">
                                                <span style="color:#64748b; font-weight:600; display:block; font-size:11px; text-transform:uppercase;"><?php _e('Browser / Device', 'phpinfo-wp'); ?>:</span>
                                                <span style="color:#64748b; font-size:12px;"><?php echo esc_html($row['user_agent']); ?></span>
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
                        __('Showing %1$d &ndash; %2$d of %3$s activity events', 'phpinfo-wp'),
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
    // Toggle Details Row
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
