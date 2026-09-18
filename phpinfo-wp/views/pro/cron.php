<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

if ($is_pro && isset($_POST['phpinfowp_cron_run']) && check_admin_referer('phpinfowp_cron_nonce')) {
    $hook = sanitize_text_field($_POST['hook'] ?? '');
    if (Phpinfo_WP_Cron_Monitor::run_now($hook)) {
        $msg = "Hook '{$hook}' triggered to run immediately.";
    }
}
if ($is_pro && isset($_POST['phpinfowp_cron_delete']) && check_admin_referer('phpinfowp_cron_nonce')) {
    $hook = sanitize_text_field($_POST['hook'] ?? '');
    $ts   = (int) ($_POST['ts'] ?? 0);
    if (Phpinfo_WP_Cron_Monitor::delete($hook, $ts)) {
        $msg = "Event removed from schedule.";
    }
}
if ($is_pro && isset($_POST['phpinfowp_cron_purge']) && check_admin_referer('phpinfowp_cron_nonce')) {
    $hook = sanitize_text_field($_POST['hook'] ?? '');
    $removed = Phpinfo_WP_Cron_Monitor::purge_hook($hook);
    $msg = $removed > 0
        ? sprintf('Purged %d scheduled instance%s of "%s".', $removed, $removed === 1 ? '' : 's', $hook)
        : "Removed all scheduled instances of \"{$hook}\".";
}

$events  = Phpinfo_WP_Cron_Monitor::events();
$summary = Phpinfo_WP_Cron_Monitor::summary();

$per_page     = 15;
$total        = count($events);
$total_pages  = max(1, (int) ceil($total / $per_page));
$paged        = max(1, min($total_pages, (int) ($_GET['paged'] ?? 1)));
$paged_events = array_slice($events, ($paged - 1) * $per_page, $per_page);
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>
                <?php _e('WP Cron Monitor', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Inspect scheduled cron events, trigger background tasks on demand, and clean orphan hooks left by deleted plugins.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>



    <?php if (isset($msg)): ?>
        <div class="notice notice-success inline" style="margin:0 0 20px"><p><?php echo esc_html($msg); ?></p></div>
    <?php endif; ?>

    <?php if ($summary['disabled']): ?>
        <div class="notice notice-warning inline" style="margin:0 0 20px">
            <p><strong>WP_CRON is disabled</strong> via the <code>DISABLE_WP_CRON</code> constant. Make sure a real system cron is calling <code>wp-cron.php</code>, or scheduled tasks will never run.</p>
        </div>
    <?php endif; ?>

    <?php if ($is_free_preview): ?>
        <!-- Free preview: contextual preview cards + centered upgrade card -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
            
            <!-- Background contextual preview cards -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                
                <?php 
                $preview_events = !empty($events) ? array_slice($events, 0, 3) : [
                    [
                        'hook'           => 'wp_version_check',
                        'timestamp'      => time() + 15120,
                        'schedule'       => 'twicedaily',
                        'schedule_label' => 'Twice Daily',
                        'diff'           => 15120,
                        'overdue'        => false,
                        'imminent'       => false,
                        'has_callback'   => true,
                    ]
                ];
                foreach ($preview_events as $pe): 
                    $is_overdue  = !empty($pe['overdue']);
                    $is_orphan   = empty($pe['has_callback']);
                    $is_imminent = !empty($pe['imminent']);
                    
                    if ($is_overdue) {
                        $border       = '#d63638';
                        $badge_bg     = '#fee2e2';
                        $badge_fg     = '#dc2626';
                        $badge_text   = __('OVERDUE', 'phpinfo-wp');
                        $status_text  = '⚠️ ' . __('Overdue', 'phpinfo-wp');
                        $status_color = '#dc2626';
                    } elseif ($is_orphan) {
                        $border       = '#dba617';
                        $badge_bg     = '#fef3c7';
                        $badge_fg     = '#b45309';
                        $badge_text   = __('ORPHAN HOOK', 'phpinfo-wp');
                        $status_text  = '🧟 ' . __('No Callback', 'phpinfo-wp');
                        $status_color = '#b45309';
                    } elseif ($is_imminent) {
                        $border       = '#6366f1';
                        $badge_bg     = '#e0e7ff';
                        $badge_fg     = '#4338ca';
                        $badge_text   = __('IMMINENT', 'phpinfo-wp');
                        $status_text  = '⚡ ' . __('Running Soon', 'phpinfo-wp');
                        $status_color = '#6366f1';
                    } else {
                        $border       = '#00a32a';
                        $badge_bg     = '#dcfce7';
                        $badge_fg     = '#15803d';
                        $badge_text   = __('SCHEDULED', 'phpinfo-wp');
                        $status_text  = '✓ ' . __('Healthy', 'phpinfo-wp');
                        $status_color = '#15803d';
                    }
                    
                    $diff_seconds = $pe['timestamp'] - time();
                    $time_str = $diff_seconds >= 0
                        ? sprintf(__('Next Run: in %s', 'phpinfo-wp'), human_time_diff(time(), $pe['timestamp']))
                        : sprintf(__('Overdue by %s', 'phpinfo-wp'), human_time_diff($pe['timestamp'], time()));
                ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid <?php echo $border; ?>; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;"><?php echo esc_html($pe['hook']); ?></strong>
                            <span style="background:<?php echo $badge_bg; ?>; color:<?php echo $badge_fg; ?>; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo esc_html($badge_text); ?></span>
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            <?php echo esc_html($time_str); ?> | <?php printf(__('Schedule: %s', 'phpinfo-wp'), esc_html($pe['schedule_label'])); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:12px; color:<?php echo $status_color; ?>; font-weight:600;"><?php echo esc_html($status_text); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

            <!-- Floating Upgrade Card -->
            <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                <span class="dashicons dashicons-clock" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Cron Job Manager & Orphan Cleaner Locked', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                    <?php _e('Inspect scheduled WordPress cron queues, trigger background jobs on demand, and purge zombie hooks left behind by deleted plugins.', 'phpinfo-wp'); ?>
                </p>
                <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">⏱️</span>
                        <span><strong><?php _e('Manual 1-Click Cron Runner:', 'phpinfo-wp'); ?></strong> <?php _e('Instantly execute scheduled tasks (like WooCommerce sync or backup jobs) without waiting for visitors.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">🧟</span>
                        <span><strong><?php _e('Orphan Hook Detection &amp; Purge:', 'phpinfo-wp'); ?></strong> <?php _e('Delete phantom cron hooks from uninstalled plugins that clutter your database and cause PHP fatals.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">🚨</span>
                        <span><strong><?php _e('Missed &amp; Overdue Schedule Alerts:', 'phpinfo-wp'); ?></strong> <?php _e('Detect blocked cron loops and receive notifications if mission-critical background jobs stall.', 'phpinfo-wp'); ?></span>
                    </div>
                </div>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                    <?php _e('Unlock Cron Manager with Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>

    <?php else: ?>

        <?php if ($summary['orphan']): ?>
            <div class="notice notice-info inline" style="margin:20px 0 0">
                <p style="margin:8px 0">
                    <strong><?php _e('Why orphan events come back after you delete them:', 'phpinfo-wp'); ?></strong>
                    "Delete" removes a single instance at one timestamp, but recurring events reschedule the next instance when WP processes cron — even with no callback. Use <strong><?php _e('Purge hook', 'phpinfo-wp'); ?></strong> on orphan rows to remove every instance of that hook in one shot (<code>wp_unschedule_hook()</code>). If it still reappears, an active plugin is re-registering it on every page load — check that plugin or fully uninstall it.
                </p>
            </div>
        <?php endif; ?>

        <?php if (!$events): ?>
            <p style="margin-top:24px"><?php _e('No scheduled events found.', 'phpinfo-wp'); ?></p>
        <?php else: ?>

        <table class="wp-list-table widefat fixed striped" style="margin-top:24px">
            <thead>
                <tr>
                    <th><?php _e('Hook', 'phpinfo-wp'); ?></th>
                    <th style="width:140px"><?php _e('Next Run', 'phpinfo-wp'); ?></th>
                    <th style="width:120px"><?php _e('Schedule', 'phpinfo-wp'); ?></th>
                    <th style="width:80px"><?php _e('Status', 'phpinfo-wp'); ?></th>
                    <th style="width:140px"><?php _e('Actions', 'phpinfo-wp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paged_events as $e):
                    $row_class = '';
                    if ($e['overdue'])      $row_class = 'phpinfowp-cron-overdue';
                    elseif ($e['imminent']) $row_class = 'phpinfowp-cron-imminent';
                ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td>
                            <code><?php echo esc_html($e['hook']); ?></code>
                            <?php if (!$e['has_callback']): ?>
                                <span class="phpinfowp-cron-orphan-badge" title="No PHP callback registered for this hook — it will run with no effect"><?php _e('orphan', 'phpinfo-wp'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html(wp_date('Y-m-d H:i:s', $e['timestamp'])); ?>
                            <div style="font-size:11px;color:#888">
                                <?php if ($e['diff'] < 0): ?>
                                    <span style="color:#d63638"><?php echo human_time_diff(time(), $e['timestamp']); ?> ago</span>
                                <?php else: ?>
                                    in <?php echo human_time_diff(time(), $e['timestamp']); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo esc_html($e['schedule_label']); ?></td>
                        <td>
                            <?php if ($e['overdue']): ?>
                                <span style="color:#d63638;font-weight:600"><?php _e('Overdue', 'phpinfo-wp'); ?></span>
                            <?php elseif ($e['imminent']): ?>
                                <span style="color:#dba617;font-weight:600"><?php _e('Soon', 'phpinfo-wp'); ?></span>
                            <?php else: ?>
                                <span style="color:#00a32a"><?php _e('OK', 'phpinfo-wp'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('phpinfowp_cron_nonce'); ?>
                                <input type="hidden" name="hook" value="<?php echo esc_attr($e['hook']); ?>">
                                <?php if ($e['has_callback']): ?>
                                    <button type="submit" name="phpinfowp_cron_run" value="1" class="button button-small"><?php _e('Run now', 'phpinfo-wp'); ?></button>
                                <?php endif; ?>
                            </form>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('phpinfowp_cron_nonce'); ?>
                                <input type="hidden" name="hook" value="<?php echo esc_attr($e['hook']); ?>">
                                <input type="hidden" name="ts"   value="<?php echo esc_attr($e['timestamp']); ?>">
                                <button type="submit" name="phpinfowp_cron_delete" value="1" class="button button-small"
                                        data-confirm="<?php esc_attr_e('Remove this scheduled event?', 'phpinfo-wp'); ?>"
                                        data-confirm-title="<?php esc_attr_e('Delete Cron Event', 'phpinfo-wp'); ?>"
                                        data-confirm-btn="<?php esc_attr_e('Delete Event', 'phpinfo-wp'); ?>"><?php _e('Delete', 'phpinfo-wp'); ?></button>
                            </form>
                            <?php if (!$e['has_callback']): ?>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field('phpinfowp_cron_nonce'); ?>
                                    <input type="hidden" name="hook" value="<?php echo esc_attr($e['hook']); ?>">
                                    <button type="submit" name="phpinfowp_cron_purge" value="1" class="button button-small"
                                            data-confirm="<?php echo esc_attr(sprintf(__('Purge every scheduled instance of \'%s\'? This wipes all timestamps and arg variants of this hook.', 'phpinfo-wp'), $e['hook'])); ?>"
                                            data-confirm-title="<?php esc_attr_e('Purge Scheduled Hook', 'phpinfo-wp'); ?>"
                                            data-confirm-btn="<?php esc_attr_e('Purge All Instances', 'phpinfo-wp'); ?>"
                                            title="<?php esc_attr_e('Removes every scheduled instance of this hook (wp_unschedule_hook). Use this if Delete keeps coming back.', 'phpinfo-wp'); ?>"><?php _e('Purge hook', 'phpinfo-wp'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px;">
                <div style="font-size:13px; color:#64748b;">
                    <?php printf(
                        __('Showing %1$d &ndash; %2$d of %3$s scheduled events', 'phpinfo-wp'),
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

    <?php endif; ?>
</div>
