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
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>WP Cron Monitor <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Inspect scheduled events, spot missed crons, and find orphans', 'phpinfo-wp'); ?></p>
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

    <!-- Summary card (Visible to all users) -->
    <div class="phpinfowp-cron-summary">
        <div class="phpinfowp-cron-stat">
            <div class="phpinfowp-cron-stat-num"><?php echo (int)($summary['total'] ?? 0); ?></div>
            <div class="phpinfowp-cron-stat-label"><?php _e('Scheduled events', 'phpinfo-wp'); ?></div>
        </div>
        <div class="phpinfowp-cron-stat" style="border-top-color:<?php echo !empty($summary['overdue']) ? '#d63638' : '#00a32a'; ?>">
            <div class="phpinfowp-cron-stat-num" style="color:<?php echo !empty($summary['overdue']) ? '#d63638' : '#00a32a'; ?>"><?php echo (int)($summary['overdue'] ?? 0); ?></div>
            <div class="phpinfowp-cron-stat-label"><?php _e('Overdue', 'phpinfo-wp'); ?></div>
        </div>
        <div class="phpinfowp-cron-stat" style="border-top-color:<?php echo !empty($summary['orphan']) ? '#dba617' : '#00a32a'; ?>">
            <div class="phpinfowp-cron-stat-num" style="color:<?php echo !empty($summary['orphan']) ? '#dba617' : '#00a32a'; ?>"><?php echo (int)($summary['orphan'] ?? 0); ?></div>
            <div class="phpinfowp-cron-stat-label"><?php _e('Orphans (no callback)', 'phpinfo-wp'); ?></div>
        </div>
        <div class="phpinfowp-cron-stat">
            <div class="phpinfowp-cron-stat-num"><?php echo (int)($summary['imminent'] ?? 0); ?></div>
            <div class="phpinfowp-cron-stat-label"><?php _e('Due within 60s', 'phpinfo-wp'); ?></div>
        </div>
    </div>

    <?php if ($is_free_preview): ?>
        <!-- Free preview: compact skeleton rows + centered upgrade card (fits in single viewpoint) -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:10px; min-height:260px; display:flex; align-items:center; justify-content:center;">
            
            <!-- Skeleton content -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.5;">
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">
                        <div style="height:11px; width:30%; background:#cbd5e1; border-radius:4px;"></div>
                        <div style="height:11px; width:20%; background:#cbd5e1; border-radius:4px;"></div>
                        <div style="height:11px; width:15%; background:#cbd5e1; border-radius:4px;"></div>
                    </div>
                    <?php for ($sk = 0; $sk < 3; $sk++): ?>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;">
                        <div style="height:10px; width:<?php echo [45, 55, 38][$sk]; ?>%; background:#e2e8f0; border-radius:4px;"></div>
                        <div style="height:10px; width:18%; background:#e2e8f0; border-radius:4px;"></div>
                        <div style="height:10px; width:12%; background:#e2e8f0; border-radius:4px;"></div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(246,249,252,0.4) 0%, rgba(246,249,252,0.95) 40%, #f6f9fc 100%); pointer-events:none;"></div>

            <!-- Upgrade card floating over skeleton -->
            <div style="position:relative; z-index:2; width:100%; max-width:480px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px 28px; box-shadow:0 6px 20px -4px rgba(0,0,0,0.07); text-align:center; margin:12px auto;">
                <span class="dashicons dashicons-lock" style="font-size:30px; width:30px; height:30px; color:#777BB3; display:inline-block; margin-bottom:6px;"></span>
                <h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#1d2327;"><?php _e('Cron Job Manager & Orphan Cleaner Locked', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 4px; font-size:13px; color:#475569; line-height:1.5; max-width:400px; margin-left:auto; margin-right:auto;">
                    <?php _e('Unlock real-time cron event inspection, 1-click "Run Now" triggers, orphan hook purging, and overdue cron alerts.', 'phpinfo-wp'); ?>
                </p>
                <p style="margin:0 0 16px; font-size:12px; color:#94a3b8;">
                    <?php _e('Your scheduled event counts and overdue stats above are real — upgrade to manage schedules.', 'phpinfo-wp'); ?>
                </p>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3; height:38px; line-height:36px; font-size:13.5px; font-weight:600; padding:0 22px; border-radius:6px; text-decoration:none; display:inline-block;">
                    <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
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
                <?php foreach ($events as $e):
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
                                        onclick="return confirm('Remove this scheduled event?')"><?php _e('Delete', 'phpinfo-wp'); ?></button>
                            </form>
                            <?php if (!$e['has_callback']): ?>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field('phpinfowp_cron_nonce'); ?>
                                    <input type="hidden" name="hook" value="<?php echo esc_attr($e['hook']); ?>">
                                    <button type="submit" name="phpinfowp_cron_purge" value="1" class="button button-small"
                                            onclick="return confirm('Purge every scheduled instance of \'<?php echo esc_js($e['hook']); ?>\'? This wipes all timestamps and arg variants of this hook.')"
                                            title="Removes every scheduled instance of this hook (wp_unschedule_hook). Use this if Delete keeps coming back.">Purge hook</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>

    <?php endif; ?>
</div>
