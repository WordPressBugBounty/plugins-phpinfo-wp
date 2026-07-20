<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) {
    phpinfowp_render_feature_lock([
        'feature'  => 'Mail Deliverability Check',
        'icon'     => 'dashicons-email-alt',
        'tagline'  => 'Send a test email, verify SPF & DKIM records, and catch delivery problems before clients do.',
        'previews' => [
            'SPF record: <strong>—</strong>',
            'DKIM: <strong>—</strong>',
            'Test email result: <strong>—</strong>',
        ],
    ]);
    return;
}

$test_result = null;
if (isset($_POST['phpinfowp_mail_test']) && check_admin_referer('phpinfowp_mail_nonce')) {
    $to = sanitize_email($_POST['test_to'] ?? '');
    $test_result = Phpinfo_WP_Mail_Check::send_test($to);
}

$audit = Phpinfo_WP_Mail_Check::audit();

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
    </div>

    <?php if (!$audit): ?>
        <div class="notice notice-error inline"><p><?php _e('Could not audit mail configuration.', 'phpinfo-wp'); ?></p></div>
    <?php else: ?>

        <!-- Summary card -->
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

        <!-- Routing Audit -->
        <?php $routing_emails = Phpinfo_WP_Mail_Check::discover_routing_emails(); ?>
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
</div>
