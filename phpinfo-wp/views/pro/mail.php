<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

$force_refresh = false;
if (isset($_POST['phpinfowp_mail_refresh']) && check_admin_referer('phpinfowp_mail_refresh_nonce')) {
    $force_refresh = true;
}

$test_result = null;
if ($is_pro && isset($_POST['phpinfowp_mail_test']) && check_admin_referer('phpinfowp_mail_nonce')) {
    $to = sanitize_email($_POST['test_to'] ?? '');
    $test_result = Phpinfo_WP_Mail_Check::send_test($to);
}

$audit = Phpinfo_WP_Mail_Check::audit($force_refresh);

$row = function($title, $ok, $value, $warning = null) {
    $color = $ok ? '#00a32a' : '#d63638';
    $dicon = $ok ? 'dashicons-yes-alt' : 'dashicons-dismiss';
    ?>
    <div class="phpinfowp-sec-row phpinfowp-mail-row" style="border-left-color:<?php echo $color; ?>">
        <div class="phpinfowp-sec-row-icon"><span class="dashicons <?php echo $dicon; ?>" style="color:<?php echo $color; ?>"></span></div>
        <div class="phpinfowp-sec-row-body">
            <div class="phpinfowp-sec-row-title"><strong><?php echo esc_html($title); ?></strong></div>
            <?php if ($value): ?>
                <code class="phpinfowp-sec-row-value"><?php echo esc_html($value); ?></code>
            <?php else: ?>
                <span class="phpinfowp-sec-row-missing"><?php _e('not found', 'phpinfo-wp'); ?></span>
            <?php endif; ?>
            <?php if ($warning): ?>
                <div class="phpinfowp-sec-row-note" style="color:#996800"><?php echo esc_html($warning); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Mail Deliverability <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('SPF, DKIM, DMARC, and SMTP configuration audit', 'phpinfo-wp'); ?></p>
        </div>
        <div>
            <form method="post" style="display:inline-block; margin:0;">
                <?php wp_nonce_field('phpinfowp_mail_refresh_nonce'); ?>
                <button type="submit" name="phpinfowp_mail_refresh" value="1" class="button button-secondary">
                    <span class="dashicons dashicons-update" style="vertical-align:middle; font-size:16px; width:16px; height:16px; margin-right:2px;"></span>
                    <?php _e('Re-check DNS', 'phpinfo-wp'); ?>
                </button>
            </form>
        </div>
    </div>



    <?php if (!$audit): ?>
        <div class="notice notice-error inline"><p><?php _e('Could not audit mail configuration.', 'phpinfo-wp'); ?></p></div>
    <?php else: ?>

        <!-- Summary card (Visible to all users) -->
        <div class="phpinfowp-mail-summary">
            <div>
                <div class="phpinfowp-mail-summary-label"><?php _e('Sending from', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-mail-summary-value"><?php echo esc_html($audit['from_email']); ?></div>
                <div class="phpinfowp-mail-summary-sub">Domain: <code><?php echo esc_html($audit['domain']); ?></code></div>
            </div>
            <div>
                <div class="phpinfowp-mail-summary-label"><?php _e('Mail method', 'phpinfo-wp'); ?></div>
                <?php if ($audit['smtp_plugin']): ?>
                    <div class="phpinfowp-mail-summary-value" style="color:#00a32a"><?php echo esc_html($audit['smtp_plugin']); ?></div>
                    <div class="phpinfowp-mail-summary-sub"><?php _e('SMTP plugin active', 'phpinfo-wp'); ?></div>
                <?php else: ?>
                    <div class="phpinfowp-mail-summary-value" style="color:#dba617"><?php _e('PHP mail()', 'phpinfo-wp'); ?></div>
                    <div class="phpinfowp-mail-summary-sub"><?php _e('Consider an SMTP plugin for deliverability', 'phpinfo-wp'); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_free_preview): ?>
            <!-- Free preview: compact skeleton rows + centered upgrade card (fits in single viewpoint) -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:10px; min-height:260px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Skeleton content -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.5;">
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <?php for ($sk = 0; $sk < 3; $sk++): ?>
                        <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #cbd5e1; border-radius:6px; padding:10px 14px; display:flex; gap:12px; align-items:center;">
                            <div style="width:16px; height:16px; border-radius:50%; background:#e2e8f0;"></div>
                            <div style="flex:1;">
                                <div style="height:11px; width:<?php echo [30, 20, 25][$sk]; ?>%; background:#cbd5e1; border-radius:4px; margin-bottom:4px;"></div>
                                <div style="height:9px; width:60%; background:#f1f5f9; border-radius:4px;"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(246,249,252,0.4) 0%, rgba(246,249,252,0.95) 40%, #f6f9fc 100%); pointer-events:none;"></div>

                <!-- Upgrade card floating over skeleton -->
                <div style="position:relative; z-index:2; width:100%; max-width:480px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px 28px; box-shadow:0 6px 20px -4px rgba(0,0,0,0.07); text-align:center; margin:12px auto;">
                    <span class="dashicons dashicons-lock" style="font-size:30px; width:30px; height:30px; color:#777BB3; display:inline-block; margin-bottom:6px;"></span>
                    <h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#1d2327;"><?php _e('Mail Deliverability & DNS Diagnostics Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 4px; font-size:13px; color:#475569; line-height:1.5; max-width:400px; margin-left:auto; margin-right:auto;">
                        <?php _e('Unlock deep SPF, DKIM, and DMARC DNS record validation, live email dispatch test runner, and spam deliverability diagnostics.', 'phpinfo-wp'); ?>
                    </p>
                    <p style="margin:0 0 16px; font-size:12px; color:#94a3b8;">
                        <?php _e('Your sender domain and sending method above are real — upgrade to ensure customer emails do not hit spam.', 'phpinfo-wp'); ?>
                    </p>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3; height:38px; line-height:36px; font-size:13.5px; font-weight:600; padding:0 22px; border-radius:6px; text-decoration:none; display:inline-block;">
                        <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php else: ?>

            <!-- Routing Audit -->
            <?php $routing_emails = Phpinfo_WP_Mail_Check::discover_routing_emails($force_refresh); ?>
            <?php if (!empty($routing_emails)): ?>
            <h2 class="phpinfowp-section-heading" style="margin-top:24px"><?php _e('Email Routing Audit', 'phpinfo-wp'); ?></h2>
            <p class="phpinfowp-page-subtitle" style="margin-bottom:16px"><?php _e('Discovered email addresses used for administration and contact forms.', 'phpinfo-wp'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Source', 'phpinfo-wp'); ?></th>
                        <th><?php _e('Email Address', 'phpinfo-wp'); ?></th>
                        <th><?php _e('Domain MX', 'phpinfo-wp'); ?></th>
                        <th style="width:100px"><?php _e('Test', 'phpinfo-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routing_emails as $re): ?>
                    <tr>
                        <td style="vertical-align:middle;font-weight:600;color:#1d2327"><?php echo esc_html($re['source']); ?></td>
                        <td style="vertical-align:middle"><code><?php echo esc_html($re['email']); ?></code></td>
                        <td style="vertical-align:middle">
                            <?php if ($re['mx_ok']): ?>
                                <span style="color:#00a32a"><span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:2px"></span> Valid</span>
                            <?php else: ?>
                                <span style="color:#d63638;font-weight:600"><span class="dashicons dashicons-warning" style="vertical-align:middle;margin-right:2px"></span> No MX Records</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align:middle">
                            <form method="post" style="margin:0">
                                <?php wp_nonce_field('phpinfowp_mail_nonce'); ?>
                                <input type="hidden" name="test_to" value="<?php echo esc_attr($re['email']); ?>">
                                <button type="submit" name="phpinfowp_mail_test" value="1" class="button button-small"><?php _e('Send test', 'phpinfo-wp'); ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- DNS audit -->
            <h2 class="phpinfowp-section-heading" style="margin-top:32px"><?php _e('DNS Records', 'phpinfo-wp'); ?></h2>
            <div class="phpinfowp-sec-rows">
                <?php $row('SPF (Sender Policy Framework)',  $audit['spf']['present'],  $audit['spf']['value'] ?? null,  $audit['spf']['warning'] ?? null); ?>
                <?php $row('DMARC',                          $audit['dmarc']['present'],$audit['dmarc']['value'] ?? null, $audit['dmarc']['warning'] ?? null); ?>

                <?php if ($audit['mx']): ?>
                    <div class="phpinfowp-sec-row phpinfowp-mail-row" style="border-left-color:#00a32a">
                        <div class="phpinfowp-sec-row-icon"><span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span></div>
                        <div class="phpinfowp-sec-row-body">
                            <div class="phpinfowp-sec-row-title"><strong><?php _e('MX records', 'phpinfo-wp'); ?></strong></div>
                            <?php foreach ($audit['mx'] as $mx): ?>
                                <code class="phpinfowp-sec-row-value"><?php echo (int)$mx['priority']; ?> &nbsp; <?php echo esc_html($mx['host']); ?></code><br>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="phpinfowp-sec-row phpinfowp-mail-row" style="border-left-color:#d63638">
                        <div class="phpinfowp-sec-row-icon"><span class="dashicons dashicons-dismiss" style="color:#d63638"></span></div>
                        <div class="phpinfowp-sec-row-body">
                            <div class="phpinfowp-sec-row-title"><strong><?php _e('MX records', 'phpinfo-wp'); ?></strong></div>
                            <span class="phpinfowp-sec-row-missing">no MX records found for <?php echo esc_html($audit['domain']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="phpinfowp-sec-row phpinfowp-mail-row" style="border-left-color:#888">
                    <div class="phpinfowp-sec-row-icon"><span class="dashicons dashicons-info" style="color:#888"></span></div>
                    <div class="phpinfowp-sec-row-body">
                        <div class="phpinfowp-sec-row-title"><strong><?php _e('DKIM', 'phpinfo-wp'); ?></strong></div>
                        <div class="phpinfowp-sec-row-note">DKIM uses a selector-specific subdomain (e.g. <code>default._domainkey.<?php echo esc_html($audit['domain']); ?></code>). Verify with your SMTP provider — selectors are not auto-detectable.</div>
                    </div>
                </div>
            </div>

            <!-- Test email -->
            <h2 class="phpinfowp-section-heading" style="margin-top:32px"><?php _e('Send Test Email', 'phpinfo-wp'); ?></h2>
            <form method="post" class="phpinfowp-mail-test-form">
                <?php wp_nonce_field('phpinfowp_mail_nonce'); ?>
                <input type="email" name="test_to" placeholder="<?php echo esc_attr__('recipient@example.com', 'phpinfo-wp'); ?>" required class="regular-text">
                <button type="submit" name="phpinfowp_mail_test" value="1" class="button button-primary">
                    <span class="dashicons dashicons-email" style="vertical-align:middle"></span> Send test
                </button>
            </form>

            <?php if ($test_result): ?>
                <div class="notice notice-<?php echo $test_result['ok'] ? 'success' : 'error'; ?> inline" style="margin-top:14px">
                    <?php if ($test_result['ok']): ?>
                        <p>Test email sent to <strong><?php echo esc_html($test_result['sent_to']); ?></strong> via <?php echo esc_html($test_result['method']); ?>. Check the inbox (and spam folder) within 60 seconds.</p>
                    <?php else: ?>
                        <p>Could not send: <strong><?php echo esc_html($test_result['error'] ?? implode('; ', $test_result['errors'])); ?></strong></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    <?php endif; ?>
</div>
