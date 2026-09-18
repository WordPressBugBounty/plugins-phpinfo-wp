<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;
$message         = '';
$msg_type        = 'success';

if ($is_pro && isset($_POST['phpinfowp_save_alerts']) && check_admin_referer('phpinfowp_alerts_nonce')) {
    Phpinfo_WP_Alerts::save_settings($_POST);
    $message = 'Alert settings saved.';
}

if ($is_pro && isset($_POST['phpinfowp_test_alert']) && check_admin_referer('phpinfowp_alerts_nonce')) {
    $raw_emails = $_POST['emails'] ?? get_option('admin_email');
    $list = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $raw_emails)));
    $to = array_values(array_filter($list, 'is_email'));
    if (empty($to)) {
        $to = [get_option('admin_email')];
    }

    $site = get_bloginfo('name') . ' (' . get_site_url() . ')';
    $sent = wp_mail($to, '[phpinfo() WP] Test alert', "This is a test alert from phpinfo() WP Pro.\nSite: {$site}");
    
    $to_list = implode(', ', $to);
    $message  = $sent ? 'Test email sent to ' . esc_html($to_list) : 'wp_mail() failed — check your mail configuration.';
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
            <h1>
                <?php _e('Alerts', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Instant Email, Slack, and Discord webhook notifications for PHP EOL, directive changes, OPcache saturation, and SSL expiration events.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>



    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible" style="margin:0 0 20px"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if ($is_free_preview): ?>
        <!-- Free preview: contextual preview cards + centered upgrade card -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
            
            <!-- Background contextual preview cards -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                
                <!-- Preview Row 1 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #6366f1; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a;"><?php _e('Emergency Email &amp; Webhook Dispatch', 'phpinfo-wp'); ?></strong>
                            <span style="background:#e0e7ff; color:#4338ca; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">DISPATCH ACTIVE</span>
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            <?php printf(__('Primary Recipient: %s · Instant Slack, Discord &amp; Custom Webhooks', 'phpinfo-wp'), esc_html(get_option('admin_email'))); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:12px; color:#6366f1; font-weight:600;">⚡ Instant Ping</span>
                    </div>
                </div>

                <!-- Preview Row 2 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #d63638; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a;"><?php printf(__('PHP %s Fatal Crash &amp; WSoD Watcher', 'phpinfo-wp'), esc_html(PHP_VERSION)); ?></strong>
                            <span style="background:#fee2e2; color:#dc2626; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">ARMED</span>
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            <?php printf(__('Automated shutdown hook logs fatal crashes and dispatches trace summaries for %s', 'phpinfo-wp'), esc_html(wp_parse_url(home_url(), PHP_URL_HOST))); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:12px; color:#dc2626; font-weight:600;">🚨 Priority 1</span>
                    </div>
                </div>

                <!-- Preview Row 3 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #00a32a; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a;">SSL Expiration &amp; PHP EOL Countdown</strong>
                            <span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">30-DAY NOTICE</span>
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            <?php _e('Automated warnings 30, 14, and 3 days before certificate expiry or runtime deprecation.', 'phpinfo-wp'); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:12px; color:#15803d; font-weight:600;">✓ Automated</span>
                    </div>
                </div>

            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

            <!-- Floating Upgrade Card -->
            <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                <span class="dashicons dashicons-bell" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Automated Email & Webhook Alerts Locked', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                    <?php _e('Get notified immediately via Email, Slack, or Discord when fatal errors occur, certificates expire, or PHP directives drift.', 'phpinfo-wp'); ?>
                </p>
                <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">⚡</span>
                        <span><strong><?php _e('Instant PHP Fatal Error Notifications:', 'phpinfo-wp'); ?></strong> <?php _e('Catch white screens and catastrophic plugin crashes the second they happen on production.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">🔔</span>
                        <span><strong><?php _e('Slack &amp; Discord Webhook Integrations:', 'phpinfo-wp'); ?></strong> <?php _e('Route server alerts directly into your dev team\'s incident response channels.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">⏳</span>
                        <span><strong><?php _e('SSL Expiration &amp; PHP EOL Warnings:', 'phpinfo-wp'); ?></strong> <?php _e('Automatic countdown warnings 30 days before security certificates expire or PHP versions hit end-of-life.', 'phpinfo-wp'); ?></span>
                    </div>
                </div>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                    <?php _e('Unlock Automated Alerts with Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>

    <?php else: ?>

        <form method="post" style="max-width:720px">
            <?php wp_nonce_field('phpinfowp_alerts_nonce'); ?>

            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; margin-bottom:24px;">
                <!-- Row: Enable alerts -->
                <div style="display:flex; border-bottom:1px solid #e2e8f0; padding:20px 24px;">
                    <div style="width:240px; flex-shrink:0;">
                        <strong style="font-size:14px; color:#0f172a;"><?php _e('Enable alerts', 'phpinfo-wp'); ?></strong>
                    </div>
                    <div style="flex-grow:1;">
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked($s['enabled']); ?>>
                            <?php _e('Send email and/or webhook notifications when events occur', 'phpinfo-wp'); ?>
                        </label>
                    </div>
                </div>

                <!-- Row: Notification email(s) -->
                <div style="display:flex; border-bottom:1px solid #e2e8f0; padding:20px 24px;">
                    <div style="width:240px; flex-shrink:0;">
                        <strong style="font-size:14px; color:#0f172a;"><?php _e('Notification email(s)', 'phpinfo-wp'); ?></strong>
                    </div>
                    <div style="flex-grow:1;">
                        <textarea name="emails" rows="3" class="large-text" placeholder="admin@example.com&#10;devops@example.com" style="width:100%;"><?php echo esc_textarea($s['emails']); ?></textarea>
                        <p class="description" style="margin-top:8px; color:#64748b; font-size:13px;"><?php _e('One email per line (or comma-separated). Defaults to site admin email.', 'phpinfo-wp'); ?></p>
                    </div>
                </div>

                <!-- Row: Alert triggers -->
                <div style="display:flex; border-bottom:1px solid #e2e8f0; padding:20px 24px;">
                    <div style="width:240px; flex-shrink:0;">
                        <strong style="font-size:14px; color:#0f172a;"><?php _e('Alert triggers', 'phpinfo-wp'); ?></strong>
                    </div>
                    <div style="flex-grow:1;">
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
                            <label style="display:block;margin-bottom:0">
                                <?php $can_digest = Phpinfo_WP_License::is_unlimited(); ?>
                                <input type="checkbox" name="weekly_digest" value="1" <?php checked($s['weekly_digest'] && $can_digest); ?> <?php disabled(!$can_digest); ?>>
                                <strong><?php _e('Weekly health digest', 'phpinfo-wp'); ?></strong> — <?php _e('Send a weekly summary email with score, EOL status, and log summary', 'phpinfo-wp'); ?>
                                <?php if (!$can_digest): ?>
                                    <span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:3px;background:#777BB3;color:#fff;font-size:10px;font-weight:700;letter-spacing:.5px"><?php _e('UNLIMITED', 'phpinfo-wp'); ?></span>
                                    <span style="font-size:12px;color:#666"> — <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank"><?php _e('Upgrade to Unlimited', 'phpinfo-wp'); ?></a></span>
                                <?php endif; ?>
                            </label>
                        </fieldset>
                    </div>
                </div>

                <!-- Row: Webhook integration -->
                <div style="display:flex; padding:20px 24px;">
                    <div style="width:240px; flex-shrink:0;">
                        <strong style="font-size:14px; color:#0f172a;"><?php _e('Webhook integration', 'phpinfo-wp'); ?></strong>
                        <?php if (!Phpinfo_WP_License::is_unlimited()): ?>
                            <br><span style="display:inline-block;margin-top:4px;padding:1px 6px;border-radius:3px;background:#777BB3;color:#fff;font-size:10px;font-weight:700;letter-spacing:.5px"><?php _e('UNLIMITED', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="flex-grow:1;">
                        <?php if (!Phpinfo_WP_License::is_unlimited()): ?>
                            <p class="description" style="color:#64748b; margin-top:0;">
                                <?php _e('Slack, Discord, and custom webhook notifications are available on the <strong>Unlimited plan</strong>.', 'phpinfo-wp'); ?>
                                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" style="font-weight:600;color:#777BB3;text-decoration:none"><?php _e('Upgrade to Unlimited &rarr;', 'phpinfo-wp'); ?></a>
                            </p>
                        <?php else: ?>
                            <input type="url" name="webhook_url" value="<?php echo esc_attr($s['webhook_url']); ?>" class="large-text" placeholder="https://hooks.slack.com/services/... or Discord webhook URL" style="width:100%;">
                            <p class="description" style="margin-top:8px; color:#64748b; font-size:13px;"><?php _e('POST notifications to a Slack Incoming Webhook, Discord channel, or custom HTTP endpoint.', 'phpinfo-wp'); ?></p>
                            <div style="margin-top:12px; display:flex; gap:16px;">
                                <label><input type="radio" name="webhook_type" value="slack" <?php checked($s['webhook_type'], 'slack'); ?>> Slack</label>
                                <label><input type="radio" name="webhook_type" value="discord" <?php checked($s['webhook_type'], 'discord'); ?>> Discord</label>
                                <label><input type="radio" name="webhook_type" value="generic" <?php checked($s['webhook_type'], 'generic'); ?>> <?php _e('Generic JSON POST', 'phpinfo-wp'); ?></label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

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
