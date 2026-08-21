<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;
$message         = '';
$msg_type        = 'success';

if ($is_pro && isset($_POST['phpinfowp_save_alerts']) && check_admin_referer('phpinfowp_alerts_nonce')) {
    Phpinfo_WP_Alerts::save_settings($_POST);
    $message = 'Alert settings saved.';
}

if ($is_pro && isset($_POST['phpinfowp_test_alert']) && check_admin_referer('phpinfowp_alerts_nonce')) {
    $to   = [get_option('admin_email')];
    $site = get_bloginfo('name') . ' (' . get_site_url() . ')';
    $sent = wp_mail($to, '[phpinfo() WP] Test alert', "This is a test alert from phpinfo() WP Pro.\nSite: {$site}");
    $message  = $sent ? 'Test email sent to ' . get_option('admin_email') : 'wp_mail() failed — check your mail configuration.';
    $msg_type = $sent ? 'success' : 'error';
}

if ($is_pro && isset($_POST['phpinfowp_test_webhook']) && check_admin_referer('phpinfowp_alerts_nonce')) {
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

    <!-- Summary Channels Card (Visible to all users) -->
    <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:20px 24px; display:flex; gap:32px; box-shadow:0 1px 1px rgba(0,0,0,0.04); margin-bottom:28px; flex-wrap:wrap;">
        <div>
            <div style="font-size:12px; color:#666; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;"><?php _e('Email Destination', 'phpinfo-wp'); ?></div>
            <div style="font-size:18px; font-weight:600; color:#1d2327; margin-top:4px;"><code><?php echo esc_html(get_option('admin_email')); ?></code></div>
        </div>
        <div style="border-left:1px solid #eee; padding-left:32px;">
            <div style="font-size:12px; color:#666; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;"><?php _e('Webhooks Supported', 'phpinfo-wp'); ?></div>
            <div style="font-size:18px; font-weight:600; color:#1d2327; margin-top:4px;">Slack &middot; Discord &middot; Custom</div>
        </div>
        <div style="border-left:1px solid #eee; padding-left:32px;">
            <div style="font-size:12px; color:#666; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;"><?php _e('Weekly Scan Schedule', 'phpinfo-wp'); ?></div>
            <div style="font-size:18px; font-weight:600; color:#00a32a; margin-top:4px;">
                <?php echo $next_cron ? esc_html(wp_date('M j, Y H:i', $next_cron)) . ' UTC' : __('Automatic', 'phpinfo-wp'); ?>
            </div>
        </div>
    </div>

    <?php if ($is_free_preview): ?>
        <!-- Free preview: compact skeleton rows + centered upgrade card (fits in single viewpoint) -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:10px; min-height:260px; display:flex; align-items:center; justify-content:center;">
            
            <!-- Skeleton content -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; opacity:0.5;">
                <?php for ($sk = 0; $sk < 3; $sk++): ?>
                <div style="display:flex; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid #f8fafc; padding-bottom:8px;">
                    <div style="width:35%;">
                        <div style="height:11px; width:70%; background:#cbd5e1; border-radius:4px; margin-bottom:4px;"></div>
                        <div style="height:9px; width:90%; background:#f1f5f9; border-radius:4px;"></div>
                    </div>
                    <div style="width:55%;">
                        <div style="height:24px; width:100%; background:#f1f5f9; border-radius:4px;"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(246,249,252,0.4) 0%, rgba(246,249,252,0.95) 40%, #f6f9fc 100%); pointer-events:none;"></div>

            <!-- Upgrade card floating over skeleton -->
            <div style="position:relative; z-index:2; width:100%; max-width:480px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px 28px; box-shadow:0 6px 20px -4px rgba(0,0,0,0.07); text-align:center; margin:12px auto;">
                <span class="dashicons dashicons-lock" style="font-size:30px; width:30px; height:30px; color:#777BB3; display:inline-block; margin-bottom:6px;"></span>
                <h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#1d2327;"><?php _e('Email & Webhook Alerts Locked', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 4px; font-size:13px; color:#475569; line-height:1.5; max-width:400px; margin-left:auto; margin-right:auto;">
                    <?php _e('Unlock automatic instant email, Slack, and Discord alerts for upcoming PHP EOL, silent config drift, OPcache hit drops, and SSL certificate expiration.', 'phpinfo-wp'); ?>
                </p>
                <p style="margin:0 0 16px; font-size:12px; color:#94a3b8;">
                    <?php _e('Catch server and configuration problems before your clients or visitors notice.', 'phpinfo-wp'); ?>
                </p>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3; height:38px; line-height:36px; font-size:13.5px; font-weight:600; padding:0 22px; border-radius:6px; text-decoration:none; display:inline-block;">
                    <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>

    <?php else: ?>

        <form method="post" style="max-width:720px">
            <?php wp_nonce_field('phpinfowp_alerts_nonce'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php _e('Enable alerts', 'phpinfo-wp'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked($s['enabled']); ?>>
                            <?php _e('Send email and/or webhook notifications when events occur', 'phpinfo-wp'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Notification email(s)', 'phpinfo-wp'); ?></th>
                    <td>
                        <textarea name="emails" rows="3" class="large-text" placeholder="admin@example.com&#10;devops@example.com"><?php echo esc_textarea($s['emails']); ?></textarea>
                        <p class="description"><?php _e('One email per line (or comma-separated). Defaults to site admin email.', 'phpinfo-wp'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Alert triggers', 'phpinfo-wp'); ?></th>
                    <td>
                        <fieldset>
                            <label style="display:block;margin-bottom:8px">
                                <input type="checkbox" name="eol_warning" value="1" <?php checked($s['eol_warning']); ?>>
                                <strong><?php _e('PHP EOL warning', 'phpinfo-wp'); ?></strong> — <?php _e('Warn within 90 days of active/security support end', 'phpinfo-wp'); ?>
                            </label>
                            <label style="display:block;margin-bottom:8px">
                                <input type="checkbox" name="config_change" value="1" <?php checked($s['config_change']); ?>>
                                <strong><?php _e('Config drift', 'phpinfo-wp'); ?></strong> — <?php _e('Alert when critical php.ini directives change unexpectedly', 'phpinfo-wp'); ?>
                            </label>
                            <label style="display:block;margin-bottom:8px">
                                <input type="checkbox" name="opcache_low" value="1" <?php checked($s['opcache_low']); ?>>
                                <strong><?php _e('OPcache hit rate drops below', 'phpinfo-wp'); ?></strong>
                                <input type="number" name="opcache_thresh" value="<?php echo esc_attr($s['opcache_thresh']); ?>" min="1" max="99" style="width:65px"> %
                            </label>
                            <label style="display:block;margin-bottom:8px">
                                <input type="checkbox" name="ssl_expiry" value="1" <?php checked($s['ssl_expiry']); ?>>
                                <strong><?php _e('SSL certificate expiring within', 'phpinfo-wp'); ?></strong>
                                <input type="number" name="ssl_thresh" value="<?php echo esc_attr($s['ssl_thresh']); ?>" min="1" max="90" style="width:65px"> <?php _e('days', 'phpinfo-wp'); ?>
                            </label>
                            <label style="display:block;margin-bottom:8px">
                                <?php $can_digest = Phpinfo_WP_License::is_unlimited(); ?>
                                <input type="checkbox" name="weekly_digest" value="1" <?php checked($s['weekly_digest'] && $can_digest); ?> <?php disabled(!$can_digest); ?>>
                                <strong><?php _e('Weekly health digest', 'phpinfo-wp'); ?></strong> — <?php _e('Send a weekly summary email with score, EOL status, and log summary', 'phpinfo-wp'); ?>
                                <?php if (!$can_digest): ?>
                                    <span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:3px;background:#777BB3;color:#fff;font-size:10px;font-weight:700;letter-spacing:.5px"><?php _e('UNLIMITED', 'phpinfo-wp'); ?></span>
                                    <span style="font-size:12px;color:#666"> — <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank"><?php _e('Upgrade to Unlimited', 'phpinfo-wp'); ?></a></span>
                                <?php endif; ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <!-- Webhooks -->
                <tr>
                    <th scope="row">
                        <?php _e('Webhook integration', 'phpinfo-wp'); ?>
                        <?php if (!Phpinfo_WP_License::is_unlimited()): ?>
                            <br><span style="display:inline-block;margin-top:4px;padding:1px 6px;border-radius:3px;background:#777BB3;color:#fff;font-size:10px;font-weight:700;letter-spacing:.5px"><?php _e('UNLIMITED', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </th>
                    <td>
                        <?php if (!Phpinfo_WP_License::is_unlimited()): ?>
                            <p class="description" style="color:#666">
                                <?php _e('Slack, Discord, and custom webhook notifications are available on the <strong>Unlimited plan</strong>.', 'phpinfo-wp'); ?>
                                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" style="font-weight:600;color:#777BB3;text-decoration:none"><?php _e('Upgrade to Unlimited &rarr;', 'phpinfo-wp'); ?></a>
                            </p>
                        <?php else: ?>
                            <input type="url" name="webhook_url" value="<?php echo esc_attr($s['webhook_url']); ?>" class="large-text" placeholder="https://hooks.slack.com/services/... or Discord webhook URL">
                            <p class="description"><?php _e('POST notifications to a Slack Incoming Webhook, Discord channel, or custom HTTP endpoint.', 'phpinfo-wp'); ?></p>
                            <div style="margin-top:8px">
                                <label style="margin-right:16px"><input type="radio" name="webhook_type" value="slack" <?php checked($s['webhook_type'], 'slack'); ?>> Slack</label>
                                <label style="margin-right:16px"><input type="radio" name="webhook_type" value="discord" <?php checked($s['webhook_type'], 'discord'); ?>> Discord</label>
                                <label><input type="radio" name="webhook_type" value="generic" <?php checked($s['webhook_type'], 'generic'); ?>> <?php _e('Generic JSON POST', 'phpinfo-wp'); ?></label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p class="submit" style="display:flex;gap:10px;align-items:center">
                <input type="submit" name="phpinfowp_save_alerts" class="button button-primary" value="<?php echo esc_attr__('Save Alert Settings', 'phpinfo-wp'); ?>">
                <button type="submit" name="phpinfowp_test_alert" value="1" class="button button-secondary"><?php _e('Send Test Email', 'phpinfo-wp'); ?></button>
                <?php if (Phpinfo_WP_License::is_unlimited() && !empty($s['webhook_url'])): ?>
                    <button type="submit" name="phpinfowp_test_webhook" value="1" class="button button-secondary"><?php _e('Test Webhook', 'phpinfo-wp'); ?></button>
                <?php endif; ?>
            </p>
        </form>

    <?php endif; ?>
</div>
