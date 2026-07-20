<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) {
    phpinfowp_render_feature_lock([
        'feature'  => 'Email Alerts & Webhooks',
        'icon'     => 'dashicons-bell',
        'tagline'  => 'Get notified on PHP EOL, config drift, OPcache drops, and SSL expiry via email, Slack, or Discord.',
        'previews' => [
            'Email alerts: <strong>Disabled</strong>',
            'Slack webhook: <strong>—</strong>',
            'Last alert sent: <strong>—</strong>',
        ],
    ]);
    return;
}

$message  = '';
$msg_type = 'success';

if (isset($_POST['phpinfowp_save_alerts']) && check_admin_referer('phpinfowp_alerts_nonce')) {
    Phpinfo_WP_Alerts::save_settings($_POST);
    $message = 'Alert settings saved.';
}

if (isset($_POST['phpinfowp_test_alert']) && check_admin_referer('phpinfowp_alerts_nonce')) {
    $to   = [get_option('admin_email')];
    $site = get_bloginfo('name') . ' (' . get_site_url() . ')';
    $sent = wp_mail($to, '[phpinfo() WP] Test alert', "This is a test alert from phpinfo() WP Pro.\nSite: {$site}");
    $message  = $sent ? 'Test email sent to ' . get_option('admin_email') : 'wp_mail() failed — check your mail configuration.';
    $msg_type = $sent ? 'success' : 'error';
}

if (isset($_POST['phpinfowp_test_webhook']) && check_admin_referer('phpinfowp_alerts_nonce')) {
    $ok = Phpinfo_WP_Alerts::send_webhook('Webhook test', 'This is a webhook test from phpinfo() WP Pro.');
    $message  = $ok ? 'Webhook payload delivered successfully.' : 'Webhook delivery failed — check the URL and provider settings.';
    $msg_type = $ok ? 'success' : 'error';
}

$s = Phpinfo_WP_Alerts::get_settings();
$next_cron = wp_next_scheduled('phpinfowp_weekly_maintenance');
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Alerts <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Email + Slack/Discord notifications for EOL, config changes, OPcache, and SSL events', 'phpinfo-wp'); ?></p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible" style="margin:0 0 20px"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <form method="post" style="max-width:720px">
        <?php wp_nonce_field('phpinfowp_alerts_nonce'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php _e('Enable Alerts', 'phpinfo-wp'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked($s['enabled']); ?>>
                        Send alerts when issues are detected
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="alert_emails"><?php _e('Email recipients', 'phpinfo-wp'); ?></label></th>
                <td>
                    <textarea name="emails" id="alert_emails" rows="3" class="large-text"><?php echo esc_textarea($s['emails']); ?></textarea>
                    <p class="description"><?php _e('One email address per line (or comma-separated). Leave blank to disable email and use webhook only.', 'phpinfo-wp'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="webhook_url"><?php _e('Webhook URL', 'phpinfo-wp'); ?></label></th>
                <td>
                    <?php if (!Phpinfo_WP_License::is_unlimited()): ?>
                        <input type="url" disabled class="large-text" placeholder="<?php echo esc_attr__('https://hooks.slack.com/services/... (Requires Unlimited/Lifetime plan)', 'phpinfo-wp'); ?>">
                        <p class="description" style="color:#ba1a1a;font-weight:600;margin-top:6px">
                            Slack / Discord / Webhook integration requires the Unlimited or Lifetime plan. <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" style="color:#7c3aed;text-decoration:none"><?php _e('Upgrade your plan →', 'phpinfo-wp'); ?></a>
                        </p>
                    <?php else: ?>
                        <input type="url" name="webhook_url" id="webhook_url" value="<?php echo esc_attr($s['webhook_url']); ?>"
                               class="large-text" placeholder="<?php echo esc_attr__('https://hooks.slack.com/services/... or https://discord.com/api/webhooks/...', 'phpinfo-wp'); ?>">
                        <p class="description" style="margin-top:6px">
                            <label style="margin-right:14px">
                                <input type="radio" name="webhook_type" value="slack" <?php checked($s['webhook_type'], 'slack'); ?>>
                                Slack
                            </label>
                            <label style="margin-right:14px">
                                <input type="radio" name="webhook_type" value="discord" <?php checked($s['webhook_type'], 'discord'); ?>>
                                Discord
                            </label>
                            <label>
                                <input type="radio" name="webhook_type" value="generic" <?php checked($s['webhook_type'], 'generic'); ?>>
                                Generic JSON (for Zapier, Make, etc.)
                            </label>
                        </p>
                        <p class="description"><?php _e('HTTPS only. Same alerts as email — delivered to your chat or automation.', 'phpinfo-wp'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Alert Conditions', 'phpinfo-wp'); ?></th>
                <td>
                    <label style="display:block;margin-bottom:8px">
                        <input type="checkbox" name="eol_warning" value="1" <?php checked($s['eol_warning']); ?>>
                        <strong><?php _e('PHP EOL approaching', 'phpinfo-wp'); ?></strong> — alert when PHP is &lt; 90 days from EOL, or already past it
                    </label>
                    <label style="display:block;margin-bottom:8px">
                        <input type="checkbox" name="config_change" value="1" <?php checked($s['config_change']); ?>>
                        <strong><?php _e('Config changes detected', 'phpinfo-wp'); ?></strong> — alert when the weekly auto-snapshot detects php.ini changes
                    </label>
                    <label style="display:block;margin-bottom:8px">
                        <input type="checkbox" name="opcache_low" value="1" <?php checked($s['opcache_low']); ?>>
                        <strong><?php _e('OPcache hit rate below', 'phpinfo-wp'); ?></strong>
                        <input type="number" name="opcache_thresh" value="<?php echo esc_attr($s['opcache_thresh']); ?>"
                               min="0" max="100" style="width:60px;margin:0 4px"> %
                    </label>
                    <label style="display:block;margin-bottom:8px">
                        <input type="checkbox" name="ssl_expiry" value="1" <?php checked($s['ssl_expiry']); ?>>
                        <strong><?php _e('SSL certificate expiring within', 'phpinfo-wp'); ?></strong>
                        <input type="number" name="ssl_thresh" value="<?php echo esc_attr($s['ssl_thresh']); ?>"
                               min="1" max="365" style="width:65px;margin:0 4px"> days
                        — <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-ssl')); ?>">Manage monitored domains</a>
                    </label>
                    <label style="display:block;margin-bottom:8px">
                        <?php if (!Phpinfo_WP_License::is_unlimited()): ?>
                            <input type="checkbox" disabled>
                            <span style="color:#646970"><strong><?php _e('Weekly digest', 'phpinfo-wp'); ?></strong> — summary every week: PHP, memory, config grade, OPcache, SSL, config changes <span style="color:#7c3aed;font-weight:600">(Requires Unlimited/Lifetime plan)</span></span>
                        <?php else: ?>
                            <input type="checkbox" name="weekly_digest" value="1" <?php checked($s['weekly_digest']); ?>>
                            <strong><?php _e('Weekly digest', 'phpinfo-wp'); ?></strong> — summary every week: PHP, memory, config grade, OPcache, SSL, config changes
                        <?php endif; ?>
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="submit" name="phpinfowp_save_alerts" class="button button-primary" value="<?php echo esc_attr__('Save Settings', 'phpinfo-wp'); ?>">
            <input type="submit" name="phpinfowp_test_alert"   class="button button-secondary" value="<?php echo esc_attr__('Send Test Email', 'phpinfo-wp'); ?>">
            <?php if ($s['webhook_url']): ?>
                <input type="submit" name="phpinfowp_test_webhook" class="button button-secondary" value="<?php echo esc_attr__('Test Webhook', 'phpinfo-wp'); ?>">
            <?php endif; ?>
        </p>
    </form>

    <div style="margin-top:24px;padding:16px 20px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;max-width:720px">
        <strong><?php _e('Alert schedule:', 'phpinfo-wp'); ?></strong>
        Alerts are checked weekly via WP Cron (alongside the auto-snapshot).
        <?php if ($next_cron): ?>
            Next run: <strong><?php echo esc_html(gmdate('Y-m-d H:i', $next_cron)); ?> UTC</strong>
            (<?php echo esc_html(human_time_diff($next_cron)); ?> from now).
        <?php else: ?>
            <span style="color:#d63638"><?php _e('WP Cron event not scheduled — deactivate and reactivate the plugin to fix this.', 'phpinfo-wp'); ?></span>
        <?php endif; ?>
    </div>
</div>
