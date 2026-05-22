<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) { require __DIR__ . '/upgrade.php'; return; }

if (isset($_POST['phpinfowp_cron_run']) && check_admin_referer('phpinfowp_cron_nonce')) {
    $hook = sanitize_text_field($_POST['hook'] ?? '');
    if (Phpinfo_WP_Cron_Monitor::run_now($hook)) {
        $msg = "Hook '{$hook}' triggered to run immediately.";
    }
}
if (isset($_POST['phpinfowp_cron_delete']) && check_admin_referer('phpinfowp_cron_nonce')) {
    $hook = sanitize_text_field($_POST['hook'] ?? '');
    $ts   = (int) ($_POST['ts'] ?? 0);
    if (Phpinfo_WP_Cron_Monitor::delete($hook, $ts)) {
        $msg = "Event removed from schedule.";
    }
}
if (isset($_POST['phpinfowp_cron_purge']) && check_admin_referer('phpinfowp_cron_nonce')) {
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
            <h1>WP Cron Monitor <span class="phpinfowp-pro-badge">PRO</span></h1>
            <p class="phpinfowp-page-subtitle">Inspect scheduled events, spot missed crons, and find orphans</p>
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

    <!-- Summary card -->
    <div class="phpinfowp-cron-summary">
        <div class="phpinfowp-cron-stat">
            <div class="phpinfowp-cron-stat-num"><?php echo (int)$summary['total']; ?></div>
            <div class="phpinfowp-cron-stat-label">Scheduled events</div>
        </div>
        <div class="phpinfowp-cron-stat" style="border-top-color:<?php echo $summary['overdue'] ? '#d63638' : '#00a32a'; ?>">
            <div class="phpinfowp-cron-stat-num" style="color:<?php echo $summary['overdue'] ? '#d63638' : '#00a32a'; ?>"><?php echo (int)$summary['overdue']; ?></div>
            <div class="phpinfowp-cron-stat-label">Overdue</div>
        </div>
        <div class="phpinfowp-cron-stat" style="border-top-color:<?php echo $summary['orphan'] ? '#dba617' : '#00a32a'; ?>">
            <div class="phpinfowp-cron-stat-num" style="color:<?php echo $summary['orphan'] ? '#dba617' : '#00a32a'; ?>"><?php echo (int)$summary['orphan']; ?></div>
            <div class="phpinfowp-cron-stat-label">Orphans (no callback)</div>
        </div>
        <div class="phpinfowp-cron-stat">
            <div class="phpinfowp-cron-stat-num"><?php echo (int)$summary['imminent']; ?></div>
            <div class="phpinfowp-cron-stat-label">Due within 60s</div>
        </div>
    </div>

    <?php if ($summary['orphan']): ?>
        <div class="notice notice-info inline" style="margin:20px 0 0">
            <p style="margin:8px 0">
                <strong>Why orphan events come back after you delete them:</strong>
                "Delete" removes a single instance at one timestamp, but recurring events reschedule the next instance when WP processes cron — even with no callback. Use <strong>Purge hook</strong> on orphan rows to remove every instance of that hook in one shot (<code>wp_unschedule_hook()</code>). If it still reappears, an active plugin is re-registering it on every page load — check that plugin or fully uninstall it.
            </p>
        </div>
    <?php endif; ?>

    <?php if (!$events): ?>
        <p style="margin-top:24px">No scheduled events found.</p>
    <?php else: ?>

    <table class="wp-list-table widefat fixed striped" style="margin-top:24px">
        <thead>
            <tr>
                <th>Hook</th>
                <th style="width:140px">Next Run</th>
                <th style="width:120px">Schedule</th>
                <th style="width:80px">Status</th>
                <th style="width:140px">Actions</th>
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
                            <span class="phpinfowp-cron-orphan-badge" title="No PHP callback registered for this hook — it will run with no effect">orphan</span>
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
                            <span style="color:#d63638;font-weight:600">Overdue</span>
                        <?php elseif ($e['imminent']): ?>
                            <span style="color:#dba617;font-weight:600">Soon</span>
                        <?php else: ?>
                            <span style="color:#00a32a">OK</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field('phpinfowp_cron_nonce'); ?>
                            <input type="hidden" name="hook" value="<?php echo esc_attr($e['hook']); ?>">
                            <?php if ($e['has_callback']): ?>
                                <button type="submit" name="phpinfowp_cron_run" value="1" class="button button-small">Run now</button>
                            <?php endif; ?>
                        </form>
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field('phpinfowp_cron_nonce'); ?>
                            <input type="hidden" name="hook" value="<?php echo esc_attr($e['hook']); ?>">
                            <input type="hidden" name="ts"   value="<?php echo esc_attr($e['timestamp']); ?>">
                            <button type="submit" name="phpinfowp_cron_delete" value="1" class="button button-small"
                                    onclick="return confirm('Remove this scheduled event?')">Delete</button>
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
</div>
