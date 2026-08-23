<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_unlimited    = Phpinfo_WP_License::is_unlimited();
$is_free_preview = !$is_pro;

$payload = Phpinfo_WP_License::payload();
$email   = $payload['email'] ?? ($is_pro ? 'Unknown' : get_option('admin_email'));

$grader    = class_exists('Phpinfo_WP_Config_Grader') ? Phpinfo_WP_Config_Grader::summary() : null;
$err_count = class_exists('Phpinfo_WP_Error_Log') ? Phpinfo_WP_Error_Log::today_count() : 0;
$mem_limit = ini_get('memory_limit') ?: '128M';
$max_time  = ini_get('max_execution_time') ?: '30';

$sysinfo = [
    'Site URL'    => get_site_url(),
    'WP Version'  => get_bloginfo('version'),
    'PHP Version' => PHP_VERSION,
    'Plugin Ver'  => PHPINFOWP_VERSION,
    'Memory Limit'=> $mem_limit,
    'Max Exec Time'=> $max_time . 's',
    'Config Grade'=> $grader ? ($grader['grade'] . ' (' . ($grader['fails'] ?? 0) . ' failing checks)') : 'N/A',
    'Errors Today'=> $err_count . ' logged',
    'License'     => $is_pro ? ($is_unlimited ? 'Unlimited / Agency' : 'Single Site') : 'Free Tier',
    'Reg. Email'  => $email,
];

$sysinfo_str = "";
foreach ($sysinfo as $k => $v) {
    $sysinfo_str .= str_pad($k, 14) . ": " . $v . "\n";
}
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1><?php _e('Technical Support & Diagnostics', 'phpinfo-wp'); ?> <?php if ($is_pro): ?><span class="phpinfowp-pro-badge"><?php echo $is_unlimited ? __('VIP PRO', 'phpinfo-wp') : __('PRO', 'phpinfo-wp'); ?></span><?php endif; ?></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Need help or looking to fix server bottlenecks immediately? Our technical team and one-click tools are here for you.', 'phpinfo-wp'); ?></p>
        </div>
    </div>

    <!-- Real-time Live Diagnostic Badges -->
    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:20px; padding:12px 16px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; align-items:center;">
        <span style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b;"><?php _e('Live Environment Signals:', 'phpinfo-wp'); ?></span>
        <span style="font-size:12px; padding:3px 8px; border-radius:4px; background:#f1f5f9; color:#334155; font-weight:600;">PHP <?php echo esc_html(PHP_VERSION); ?></span>
        <span style="font-size:12px; padding:3px 8px; border-radius:4px; background:<?php echo ((int)$mem_limit < 256) ? '#fef3c7; color:#92400e;' : '#f1f5f9; color:#334155; font-weight:600;'; ?>">Memory: <?php echo esc_html($mem_limit); ?></span>
        <?php if ($grader && isset($grader['grade'])): ?>
            <span style="font-size:12px; padding:3px 8px; border-radius:4px; background:<?php echo in_array($grader['grade'], ['A', 'B']) ? '#dcfce7; color:#166534;' : '#fee2e2; color:#991b1b;'; ?> font-weight:600;">
                Config Score: Grade <?php echo esc_html($grader['grade']); ?> (<?php echo (int)($grader['fails'] ?? 0); ?> failing)
            </span>
        <?php endif; ?>
        <?php if ($err_count > 0): ?>
            <span style="font-size:12px; padding:3px 8px; border-radius:4px; background:#fee2e2; color:#991b1b; font-weight:600;">
                <?php echo (int)$err_count; ?> <?php _e('PHP errors logged today', 'phpinfo-wp'); ?>
            </span>
        <?php else: ?>
            <span style="font-size:12px; padding:3px 8px; border-radius:4px; background:#dcfce7; color:#166534; font-weight:600;">
                ✓ <?php _e('0 PHP errors today', 'phpinfo-wp'); ?>
            </span>
        <?php endif; ?>
    </div>

    <div style="display:flex; gap:24px; flex-wrap:wrap; margin-top:20px; align-items:flex-start;">

        <!-- System Info Card (Visible to all users) -->
        <div style="flex:1; min-width:320px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:24px; display:flex; flex-direction:column;">
            <h3 style="margin-top:0; font-size:18px; color:#1d2327;"><span class="dashicons dashicons-desktop" style="vertical-align:middle; color:#777BB3;"></span> <?php _e('System Information', 'phpinfo-wp'); ?></h3>
            <p style="color:#475569; font-size:14px; line-height:1.6; margin-bottom:14px;">
                <?php _e('Real-time server environment specs. Copy and attach this when requesting technical support or debugging server issues.', 'phpinfo-wp'); ?>
            </p>
            <textarea readonly style="width:100%; height:230px; min-height:230px; font-family:monospace; font-size:12.5px; background:#f8fafc; color:#334155; border:1px solid #cbd5e1; border-radius:6px; padding:14px; margin-bottom:16px; line-height:1.55; resize:none; box-sizing:border-box;" onclick="this.select();"><?php echo esc_textarea($sysinfo_str); ?></textarea>
            <div>
                <button type="button" class="button button-secondary" onclick="var t=this.parentNode.previousElementSibling; t.select(); document.execCommand('copy'); this.innerText='Copied!'; setTimeout(()=>this.innerText='<?php _e('Copy System Info', 'phpinfo-wp'); ?>', 2000);">
                    <?php _e('Copy System Info', 'phpinfo-wp'); ?>
                </button>
            </div>
        </div>

        <?php if ($is_free_preview): ?>
            <!-- Free preview: Instant 1-Click Fixes + Priority Ticket Portal Locked -->
            <div style="flex:1.2; min-width:340px; display:flex; flex-direction:column; gap:20px;">

                <!-- Instant Self-Fixes vs Waiting -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:22px;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                        <span class="dashicons dashicons-superhero" style="color:#777BB3; font-size:22px; width:22px; height:22px;"></span>
                        <h3 style="margin:0; font-size:16px; color:#1d2327;"><?php _e('Instant 1-Click Solutions (No Waiting)', 'phpinfo-wp'); ?></h3>
                    </div>
                    <p style="font-size:13px; color:#475569; margin:0 0 14px; line-height:1.5;">
                        <?php _e('Over 85% of support requests can be resolved immediately without waiting for a reply:', 'phpinfo-wp'); ?>
                    </p>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px;">
                            <div style="font-weight:600; font-size:12.5px; color:#1d2327; margin-bottom:2px;">⚡ <?php _e('Memory Exhausted?', 'phpinfo-wp'); ?></div>
                            <div style="font-size:11.5px; color:#64748b;"><?php _e('1-Click raise in PHP Config Editor', 'phpinfo-wp'); ?></div>
                        </div>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px;">
                            <div style="font-weight:600; font-size:12.5px; color:#1d2327; margin-bottom:2px;">🛡️ <?php _e('Security Headers F?', 'phpinfo-wp'); ?></div>
                            <div style="font-size:11.5px; color:#64748b;"><?php _e('Auto-inject 6 OWASP headers', 'phpinfo-wp'); ?></div>
                        </div>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px;">
                            <div style="font-weight:600; font-size:12.5px; color:#1d2327; margin-bottom:2px;">⚠️ <?php _e('500 Fatal Error?', 'phpinfo-wp'); ?></div>
                            <div style="font-size:11.5px; color:#64748b;"><?php _e('Live error log with stack traces', 'phpinfo-wp'); ?></div>
                        </div>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px;">
                            <div style="font-weight:600; font-size:12.5px; color:#1d2327; margin-bottom:2px;">⏱️ <?php _e('Script Timeouts?', 'phpinfo-wp'); ?></div>
                            <div style="font-size:11.5px; color:#64748b;"><?php _e('Extend max_execution_time', 'phpinfo-wp'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Priority Ticket Portal Upgrade Box -->
                <div style="background:#fff; border:1px solid #777BB3; border-top:4px solid #777BB3; border-radius:8px; padding:22px; text-align:center; box-shadow:0 4px 14px rgba(119,123,179,0.12);">
                    <span class="dashicons dashicons-email-alt" style="font-size:32px; width:32px; height:32px; color:#777BB3; margin-bottom:8px; display:inline-block;"></span>
                    <h3 style="margin:0 0 6px; font-size:17px; font-weight:700; color:#1d2327;"><?php _e('Unlock Priority 1-on-1 Engineer Support', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 14px; font-size:13px; color:#475569; line-height:1.5; max-width:440px; margin-left:auto; margin-right:auto;">
                        <?php _e('Get direct private email ticket support, <24-hour response SLA (including weekends), and expert server troubleshooting from our core engineers.', 'phpinfo-wp'); ?>
                    </p>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="min-height:38px; padding:0 22px; font-size:13.5px; font-weight:600;">
                        <?php _e('Unlock Pro Support ($29/yr) &rarr;', 'phpinfo-wp'); ?>
                    </a>
                    <div style="margin-top:14px; font-size:11.5px; color:#64748b; display:flex; justify-content:center; gap:16px; flex-wrap:wrap;">
                        <span>⭐ <strong>4.9/5</strong> Rating</span>
                        <span>🛡️ <strong>14-Day</strong> Money-Back Guarantee</span>
                        <span>⚡ <strong>Instant</strong> Key Activation</span>
                    </div>
                </div>

            </div>

        <?php else: ?>

            <!-- Pro User Support Options Card -->
            <div style="flex:1.2; min-width:340px; display:flex; flex-direction:column; gap:20px;">
                
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:24px;">
                    <h3 style="margin-top:0; font-size:18px; color:#1d2327;"><span class="dashicons dashicons-email-alt" style="vertical-align:middle; color:#777BB3;"></span> <?php _e('Contact Priority Support', 'phpinfo-wp'); ?></h3>
                    <p style="color:#475569; font-size:14px; line-height:1.6; margin-bottom:20px;">
                        <?php _e('As a Pro user, you get priority email support. If you are experiencing a bug, need help configuring the plugin, or have a feature request, please open a ticket. We usually reply within an hour on weekdays and weekends.', 'phpinfo-wp'); ?>
                    </p>
                    <a href="https://exeebit.com/support" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3;">
                        <?php _e('Open a Support Ticket &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>

                <?php if (!$is_unlimited): ?>
                <!-- Single Site -> Unlimited / Agency Upsell -->
                <div style="background:linear-gradient(135deg, #f8f8fc 0%, #eff0f9 100%); border:1px solid #d8daeb; border-radius:8px; padding:22px;">
                    <span style="background:#777BB3; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:3px; text-transform:uppercase; letter-spacing:.4px;"><?php _e('Agency & Client Sites', 'phpinfo-wp'); ?></span>
                    <h3 style="margin:8px 0 6px; font-size:16px; color:#1d2327;"><?php _e('Upgrade to Unlimited & White-Label Reports', 'phpinfo-wp'); ?></h3>
                    <p style="color:#475569; font-size:13px; line-height:1.5; margin-bottom:14px;">
                        <?php _e('Managing client websites? Upgrade to Unlimited or Lifetime to unlock White-Label PDF Reports (your own agency logo & colors), multi-site license keys, and VIP 1-hour weekend response.', 'phpinfo-wp'); ?>
                    </p>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-secondary">
                        <?php _e('Upgrade to Unlimited / Lifetime &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
                <?php endif; ?>

                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:20px;">
                    <h4 style="margin-top:0; margin-bottom:8px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#64748b;">More from Exeebit</h4>
                    <h3 style="margin-top:0; font-size:18px; color:#1d2327;">Meet Mimonous 💸</h3>
                    <p style="color:#475569; font-size:13.5px; line-height:1.5; margin-bottom:16px;">
                        Are you a freelancer or small agency? Check out <strong>Mimonous</strong>, our peer-to-peer invoicing platform that turns the invoices you send directly into payable bills for your clients.
                    </p>
                    <a href="https://mimonous.com" target="_blank" rel="noopener" class="button">
                        Learn about Mimonous &rarr;
                    </a>
                </div>

            </div>

        <?php endif; ?>

    </div>
</div>
