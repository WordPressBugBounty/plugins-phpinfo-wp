<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

// Non-blocking cached lookups
$audit          = Phpinfo_WP_Mail_Check::get_result();
$routing_emails = Phpinfo_WP_Mail_Check::get_routing_cached();
$has_data       = ($audit !== null);

$row = function($title, $ok, $value, $warning = null) {
    $border = $ok ? ($warning ? '#f59e0b' : '#10b981') : '#ef4444';
    $bg     = $ok ? ($warning ? '#fffbeb' : '#f0fdf4') : '#fef2f2';
    $dicon  = $ok ? ($warning ? 'dashicons-warning' : 'dashicons-yes-alt') : 'dashicons-dismiss';
    $dcolor = $ok ? ($warning ? '#f59e0b' : '#10b981') : '#ef4444';
    ?>
    <div class="phpinfowp-sec-row phpinfowp-mail-row" style="border-left-color:<?php echo $border; ?>; background:<?php echo $bg; ?>; border-radius:8px; margin-bottom:12px; padding:16px;">
        <div class="phpinfowp-sec-row-icon"><span class="dashicons <?php echo $dicon; ?>" style="color:<?php echo $dcolor; ?>;"></span></div>
        <div class="phpinfowp-sec-row-body">
            <div class="phpinfowp-sec-row-title" style="display:flex; justify-content:space-between; align-items:center;">
                <strong style="color:#0f172a; font-size:14px;"><?php echo esc_html($title); ?></strong>
                <span style="background:<?php echo $border; ?>; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; color:#fff; text-transform:uppercase;">
                    <?php echo $ok ? ($warning ? __('Warning', 'phpinfo-wp') : __('Active', 'phpinfo-wp')) : __('Missing', 'phpinfo-wp'); ?>
                </span>
            </div>
            <?php if ($value): ?>
                <div style="margin-top:6px;">
                    <code class="phpinfowp-sec-row-value" style="background:rgba(0,0,0,0.04); padding:2px 6px; border-radius:4px; font-size:12px;"><?php echo esc_html($value); ?></code>
                </div>
            <?php else: ?>
                <div style="margin-top:4px;">
                    <span class="phpinfowp-sec-row-missing" style="color:#94a3b8; font-size:12px;"><?php _e('not found', 'phpinfo-wp'); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($warning): ?>
                <div class="phpinfowp-sec-row-note" style="color:#b45309; font-size:12.5px; margin-top:6px;">⚠️ <?php echo esc_html($warning); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
        <div>
            <h1 style="display:flex; align-items:center; gap:8px; margin:0; font-size:22px; font-weight:700; color:#0f172a;">
                <?php _e('Mail Deliverability & DNS', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('SPF, DKIM, DMARC, MX records, and contact form mail routing deliverability diagnostic.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
        <?php if ($is_pro): ?>
            <div class="phpinfowp-compat-controls" style="margin:0; gap:8px; display:flex; align-items:center;">
                <button type="button" id="phpinfowp-mail-recheck-btn" class="button <?php echo !$has_data ? 'button-primary' : 'button-secondary'; ?>" style="<?php echo !$has_data ? 'background:#6366f1; border-color:#6366f1; color:#fff;' : ''; ?> height:36px; line-height:34px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-update" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                    <span><?php echo $has_data ? __('Re-check DNS & Deliverability', 'phpinfo-wp') : __('Run Deliverability Audit', 'phpinfo-wp'); ?></span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div id="phpinfowp-mail-result-area">
        <?php if ($is_free_preview): ?>
            <!-- Free preview: contextual preview cards + centered upgrade card -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Background contextual preview cards -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                    
                <?php
                $admin_email = get_option('admin_email', '');
                $site_host   = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'localhost';
                $mail_domain = '';
                if ($admin_email && strpos($admin_email, '@') !== false) {
                    $parts = explode('@', $admin_email);
                    $mail_domain = strtolower(trim($parts[1] ?? ''));
                }
                if (!$mail_domain) {
                    $mail_domain = $site_host;
                }

                $spf_value   = !empty($audit['spf']['raw']) ? $audit['spf']['raw'] : 'v=spf1 +a +mx ~all';
                $dmarc_value = !empty($audit['dmarc']['raw']) ? $audit['dmarc']['raw'] : 'v=DMARC1; p=reject; rua=mailto:' . ($admin_email ?: 'admin@' . $mail_domain);
                $mx_display  = !empty($audit['mx'][0]['host']) 
                    ? $audit['mx'][0]['host'] . ' (Priority ' . ($audit['mx'][0]['priority'] ?? '10') . ')'
                    : 'mail.' . $mail_domain . ' (Direct SMTP Port 587)';
                ?>
                <!-- Preview Row 1 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #00a32a; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;">SPF Record (<?php echo esc_html($mail_domain); ?>)</strong>
                            <span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">PASS</span>
                        </div>
                        <div style="font-size:12px; color:#64748b; font-family:monospace; word-break:break-all;">
                            <?php echo esc_html($spf_value); ?>
                        </div>
                    </div>
                    <div style="text-align:right; flex-shrink:0; margin-left:12px;">
                        <span style="font-size:12px; color:#15803d; font-weight:600;">✓ Authorized</span>
                    </div>
                </div>

                <!-- Preview Row 2 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #00a32a; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;">DMARC Policy (_dmarc.<?php echo esc_html($mail_domain); ?>)</strong>
                            <span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">ENFORCED</span>
                        </div>
                        <div style="font-size:12px; color:#64748b; font-family:monospace; word-break:break-all;">
                            <?php echo esc_html($dmarc_value); ?>
                        </div>
                    </div>
                    <div style="text-align:right; flex-shrink:0; margin-left:12px;">
                        <span style="font-size:12px; color:#15803d; font-weight:600;">✓ Policy Alignment</span>
                    </div>
                </div>

                <!-- Preview Row 3 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #6366f1; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;">Mail Dispatch Host</strong>
                            <span style="background:#e0e7ff; color:#4338ca; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">ROUTING</span>
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            <?php echo esc_html($mx_display); ?> &bull; From: <code><?php echo esc_html($admin_email ?: 'admin@' . $mail_domain); ?></code>
                        </div>
                    </div>
                    <div style="text-align:right; flex-shrink:0; margin-left:12px;">
                        <span style="font-size:12px; color:#6366f1; font-weight:600;">⚡ Active Routing</span>
                    </div>
                </div>

                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

                <!-- Floating Upgrade Card -->
                <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                    <span class="dashicons dashicons-email-alt" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                    <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Mail Deliverability & DNS Diagnostics Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                        <?php _e('Validate SPF, DKIM, and DMARC DNS records and test outbound SMTP routing so customer and order emails never land in spam.', 'phpinfo-wp'); ?>
                    </p>
                    <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">📬</span>
                            <span><strong><?php _e('SPF, DKIM &amp; DMARC DNS Validator:', 'phpinfo-wp'); ?></strong> <?php _e('Audit domain authentication headers required by Gmail and Yahoo to prevent silent spam drops.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🧪</span>
                            <span><strong><?php _e('Live Outbound Email Dispatch Tester:', 'phpinfo-wp'); ?></strong> <?php _e('Send real diagnostic test emails with SMTP error tracebacks and TLS handshake inspection.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🚨</span>
                            <span><strong><?php _e('Silent Mail Failure Detection:', 'phpinfo-wp'); ?></strong> <?php _e('Get alerted immediately if your transactional SMTP provider disconnects or authentication expires.', 'phpinfo-wp'); ?></span>
                        </div>
                    </div>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                        <?php _e('Unlock Mail Diagnostics with Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php elseif (!$has_data): ?>

            <!-- Ready to Audit Empty State -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <span class="dashicons dashicons-email-alt" style="font-size:30px; width:30px; height:30px;"></span>
                </div>
                <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php _e('Ready to Audit Mail Deliverability', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 20px; color:#64748b; font-size:14px; max-width:520px; margin-left:auto; margin-right:auto; line-height:1.5;">
                    <?php _e('Click "Run Deliverability Audit" above to asynchronously inspect SPF, DMARC, MX records, and auto-discover contact form notification routes.', 'phpinfo-wp'); ?>
                </p>
                <div style="display:inline-flex; align-items:center; gap:8px; font-size:12px; color:#94a3b8;">
                    <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:16px; width:16px; height:16px;"></span>
                    <?php _e('Zero Page Lag — Runs via non-blocking asynchronous DNS probe.', 'phpinfo-wp'); ?>
                </div>
            </div>

        <?php else: ?>

            <!-- Sender Overview Card -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:18px 22px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div>
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;"><?php _e('Sender Domain & Transport', 'phpinfo-wp'); ?></div>
                    <div style="font-size:16px; font-weight:700; color:#0f172a;">
                        <code><?php echo esc_html($audit['from_email']); ?></code>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:13px; color:#64748b;"><?php _e('Sending Method:', 'phpinfo-wp'); ?></span>
                    <?php if (!empty($audit['smtp_plugin'])): ?>
                        <span style="background:#dcfce7; color:#15803d; font-size:12px; font-weight:700; padding:4px 10px; border-radius:6px;">
                            ✓ <?php echo esc_html($audit['smtp_plugin']); ?>
                        </span>
                    <?php else: ?>
                        <span style="background:#fef3c7; color:#b45309; font-size:12px; font-weight:700; padding:4px 10px; border-radius:6px;">
                            ⚠ <?php _e('PHP mail() Default', 'phpinfo-wp'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Email Routing Audit -->
            <?php if (!empty($routing_emails)): ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px 24px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <h2 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('Email Routing Audit', 'phpinfo-wp'); ?></h2>
                    <p style="margin:0 0 16px; font-size:13px; color:#64748b;"><?php _e('Discovered notification routes used by WordPress core and active form plugins.', 'phpinfo-wp'); ?></p>
                    
                    <table class="widefat striped" style="border:1px solid #e2e8f0; border-radius:6px; overflow:hidden;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px; font-weight:600; color:#475569;"><?php _e('Source', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569;"><?php _e('Email Address', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569;"><?php _e('Domain MX', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:110px; text-align:right;"><?php _e('Quick Test', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routing_emails as $re): ?>
                            <tr>
                                <td style="padding:10px 14px; vertical-align:middle; font-weight:600; color:#1e293b;"><?php echo esc_html($re['source']); ?></td>
                                <td style="padding:10px 14px; vertical-align:middle;"><code style="font-size:12.5px;"><?php echo esc_html($re['email']); ?></code></td>
                                <td style="padding:10px 14px; vertical-align:middle;">
                                    <?php if ($re['mx_ok']): ?>
                                        <span style="color:#15803d; font-weight:600; font-size:12.5px;"><span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; vertical-align:middle; margin-right:2px;"></span> <?php _e('Valid MX', 'phpinfo-wp'); ?></span>
                                    <?php else: ?>
                                        <span style="color:#b91c1c; font-weight:600; font-size:12.5px;"><span class="dashicons dashicons-warning" style="font-size:16px; width:16px; height:16px; vertical-align:middle; margin-right:2px;"></span> <?php _e('No MX Records', 'phpinfo-wp'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px 14px; vertical-align:middle; text-align:right;">
                                    <button type="button" class="button button-small button-secondary phpinfowp-mail-quick-test" data-email="<?php echo esc_attr($re['email']); ?>" style="height:28px; line-height:26px; font-size:12px; font-weight:600; border-radius:4px;">
                                        <?php _e('Send Test', 'phpinfo-wp'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- DNS records -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px 24px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <h2 style="margin:0 0 16px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('DNS Authentication Records', 'phpinfo-wp'); ?></h2>
                <div class="phpinfowp-sec-rows">
                    <?php $row(__('SPF (Sender Policy Framework)', 'phpinfo-wp'), !empty($audit['spf']['present']), $audit['spf']['value'] ?? null, $audit['spf']['warning'] ?? null); ?>
                    <?php $row(__('DMARC (Domain-based Message Authentication)', 'phpinfo-wp'), !empty($audit['dmarc']['present']), $audit['dmarc']['value'] ?? null, $audit['dmarc']['warning'] ?? null); ?>

                    <?php if (!empty($audit['mx'])): ?>
                        <div class="phpinfowp-sec-row phpinfowp-mail-row" style="border-left-color:#10b981; background:#f0fdf4; border-radius:8px; margin-bottom:12px; padding:16px;">
                            <div class="phpinfowp-sec-row-icon"><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span></div>
                            <div class="phpinfowp-sec-row-body">
                                <div class="phpinfowp-sec-row-title" style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong style="color:#0f172a; font-size:14px;"><?php _e('MX Records', 'phpinfo-wp'); ?></strong>
                                    <span style="background:#10b981; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; color:#fff; text-transform:uppercase;"><?php _e('Configured', 'phpinfo-wp'); ?></span>
                                </div>
                                <div style="margin-top:6px; display:flex; flex-direction:column; gap:4px;">
                                    <?php foreach ($audit['mx'] as $mx): ?>
                                        <div><code class="phpinfowp-sec-row-value" style="background:rgba(0,0,0,0.04); padding:2px 6px; border-radius:4px; font-size:12px;"><?php echo (int) $mx['priority']; ?> &nbsp; <?php echo esc_html($mx['host']); ?></code></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="phpinfowp-sec-row phpinfowp-mail-row" style="border-left-color:#ef4444; background:#fef2f2; border-radius:8px; margin-bottom:12px; padding:16px;">
                            <div class="phpinfowp-sec-row-icon"><span class="dashicons dashicons-dismiss" style="color:#ef4444;"></span></div>
                            <div class="phpinfowp-sec-row-body">
                                <div class="phpinfowp-sec-row-title" style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong style="color:#0f172a; font-size:14px;"><?php _e('MX Records', 'phpinfo-wp'); ?></strong>
                                    <span style="background:#ef4444; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; color:#fff; text-transform:uppercase;"><?php _e('Missing', 'phpinfo-wp'); ?></span>
                                </div>
                                <div style="margin-top:4px;">
                                    <span class="phpinfowp-sec-row-missing" style="color:#94a3b8; font-size:12px;"><?php printf(__('No MX records found for %s', 'phpinfo-wp'), esc_html($audit['domain'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="phpinfowp-sec-row phpinfowp-mail-row" style="border-left-color:#94a3b8; background:#f8fafc; border-radius:8px; margin-bottom:12px; padding:16px;">
                        <div class="phpinfowp-sec-row-icon"><span class="dashicons dashicons-info" style="color:#64748b;"></span></div>
                        <div class="phpinfowp-sec-row-body">
                            <div class="phpinfowp-sec-row-title">
                                <strong style="color:#0f172a; font-size:14px;"><?php _e('DKIM (DomainKeys Identified Mail)', 'phpinfo-wp'); ?></strong>
                            </div>
                            <div class="phpinfowp-sec-row-note" style="color:#64748b; font-size:12.5px; margin-top:4px; line-height:1.5;">
                                <?php printf(__('DKIM uses selector-specific subdomains (e.g. <code>default._domainkey.%s</code>). Verify with your SMTP provider — selectors are not auto-discoverable via public DNS without selector names.', 'phpinfo-wp'), esc_html($audit['domain'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Send Test Email Card -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px 24px; box-shadow:0 1px 3px rgba(0,0,0,0.02); max-width:600px;">
                <h2 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('Live Email Dispatch Test', 'phpinfo-wp'); ?></h2>
                <p style="margin:0 0 16px; font-size:13px; color:#64748b;"><?php _e('Sends an asynchronous probe to verify whether WordPress can successfully deliver email through your configured transport.', 'phpinfo-wp'); ?></p>
                
                <form id="phpinfowp-mail-test-form" style="display:flex; gap:10px; align-items:center;">
                    <input type="email" id="phpinfowp-test-email-input" placeholder="<?php echo esc_attr__('recipient@example.com', 'phpinfo-wp'); ?>" required class="regular-text" style="height:36px; border-radius:6px; border-color:#cbd5e1; flex:1;">
                    <button type="submit" id="phpinfowp-test-email-btn" class="button button-primary" style="background:#6366f1; border-color:#6366f1; height:36px; line-height:34px; border-radius:6px; font-weight:600; padding:0 18px; display:inline-flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-email" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                        <span><?php _e('Send Test', 'phpinfo-wp'); ?></span>
                    </button>
                </form>

                <div id="phpinfowp-mail-test-output" style="margin-top:14px;"></div>
            </div>

        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var nonce = '<?php echo wp_create_nonce("phpinfowp_mail_nonce"); ?>';

    var recheckBtn = document.getElementById('phpinfowp-mail-recheck-btn');
    if (recheckBtn) {
        recheckBtn.addEventListener('click', function(e) {
            e.preventDefault();
            recheckBtn.disabled = true;
            recheckBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span> <?php echo esc_js(__('Auditing DNS & Mail...', 'phpinfo-wp')); ?>';

            if (window.phpinfowpShowScanBanner) {
                window.phpinfowpShowScanBanner('<?php echo esc_js(__('Auditing Mail Deliverability...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Querying SPF, DKIM, DMARC & MX records (2–6s)', 'phpinfo-wp')); ?>');
            }

            var resultArea = document.getElementById('phpinfowp-mail-result-area');
            if (resultArea) {
                resultArea.innerHTML = '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">' +
                    '<div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">' +
                    '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>' +
                    '</div>' +
                    '<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php echo esc_js(__('Auditing Mail Deliverability & DNS...', 'phpinfo-wp')); ?></h3>' +
                    '<p style="margin:0; color:#64748b; font-size:14px;"><?php echo esc_js(__('Querying SPF, DMARC, MX records and scanning mail routes asynchronously...', 'phpinfo-wp')); ?></p>' +
                    '</div>';
            }

            var data = new FormData();
            data.append('action', 'phpinfowp_mail_scan');
            data.append('nonce', nonce);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function() { window.location.reload(); })
                .catch(function() { window.location.reload(); });
        });
    }

    function dispatchTestEmail(email, btn, outputArea) {
        if (!email) return;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Sending...', 'phpinfo-wp')); ?>';
        }

        if (window.phpinfowpShowScanBanner) {
            window.phpinfowpShowScanBanner('<?php echo esc_js(__('Dispatching Test Email...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Connecting to SMTP server & sending (2–5s)', 'phpinfo-wp')); ?>');
        }

        var data = new FormData();
        data.append('action', 'phpinfowp_mail_test');
        data.append('nonce', nonce);
        data.append('test_to', email);

        fetch(ajaxurl, { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="dashicons dashicons-email" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span> <?php echo esc_js(__('Send Test', 'phpinfo-wp')); ?>';
                }
                if (outputArea) {
                    if (res.success && res.data.ok) {
                        outputArea.innerHTML = '<div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:12px 14px; color:#15803d; font-size:13px; font-weight:600;">' +
                            '✓ <?php echo esc_js(__('Test email sent to', 'phpinfo-wp')); ?> <strong>' + res.data.sent_to + '</strong> <?php echo esc_js(__('via', 'phpinfo-wp')); ?> ' + res.data.method + '. <?php echo esc_js(__('Check your inbox or spam folder.', 'phpinfo-wp')); ?>' +
                            '</div>';
                    } else {
                        var err = (res.data && (res.data.error || (res.data.errors && res.data.errors.join('; ')))) || '<?php echo esc_js(__('Could not send test email.', 'phpinfo-wp')); ?>';
                        outputArea.innerHTML = '<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:12px 14px; color:#b91c1c; font-size:13px;">' +
                            '<strong>✕ <?php echo esc_js(__('Delivery Failed:', 'phpinfo-wp')); ?></strong> ' + err +
                            '</div>';
                    }
                }
            })
            .catch(function() {
                if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="dashicons dashicons-email" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span> <?php echo esc_js(__('Send Test', 'phpinfo-wp')); ?>';
                }
                if (outputArea) {
                    outputArea.innerHTML = '<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:12px 14px; color:#b91c1c; font-size:13px;">' +
                        '<strong>✕ <?php echo esc_js(__('Network error during test dispatch.', 'phpinfo-wp')); ?></strong>' +
                        '</div>';
                }
            });
    }

    var testForm = document.getElementById('phpinfowp-mail-test-form');
    var testInput = document.getElementById('phpinfowp-test-email-input');
    var testBtn = document.getElementById('phpinfowp-test-email-btn');
    var testOut = document.getElementById('phpinfowp-mail-test-output');

    if (testForm) {
        testForm.addEventListener('submit', function(e) {
            e.preventDefault();
            dispatchTestEmail(testInput ? testInput.value : '', testBtn, testOut);
        });
    }

    var quickTestBtns = document.querySelectorAll('.phpinfowp-mail-quick-test');
    quickTestBtns.forEach(function(b) {
        b.addEventListener('click', function(e) {
            e.preventDefault();
            var email = b.getAttribute('data-email');
            if (testInput) testInput.value = email;
            dispatchTestEmail(email, b, testOut);
        });
    });
});
</script>
