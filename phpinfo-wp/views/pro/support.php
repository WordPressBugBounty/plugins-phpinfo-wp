<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) {
    phpinfowp_render_feature_lock([
        'feature'  => 'Premium Support',
        'icon'     => 'dashicons-sos',
        'tagline'  => 'Get priority email support and access to our technical experts.',
        'previews' => [
            'Ticket response time: <strong>&lt; 24 hours</strong>',
            'Support channel: <strong>Priority Email</strong>',
            'Bug fixes: <strong>Guaranteed</strong>',
        ],
    ]);
    return;
}

$payload = Phpinfo_WP_License::payload();
$email   = $payload['email'] ?? 'Unknown';

$sysinfo = [
    'Site URL'   => get_site_url(),
    'WP Version' => get_bloginfo('version'),
    'PHP Version'=> PHP_VERSION,
    'Plugin Ver' => PHPINFOWP_VERSION,
    'License'    => Phpinfo_WP_License::is_unlimited() ? 'Unlimited' : 'Single Site',
    'Reg. Email' => $email,
];

$sysinfo_str = "";
foreach ($sysinfo as $k => $v) {
    $sysinfo_str .= str_pad($k, 12) . ": " . $v . "\n";
}
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1>Premium Support <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Need help? Our technical experts are ready to assist you.', 'phpinfo-wp'); ?></p>
        </div>
    </div>

    <div style="display:flex; gap:24px; flex-wrap:wrap; margin-top:24px;">
        <!-- Support Options Card -->
        <div style="flex:1; min-width:300px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:24px;">
            <h3 style="margin-top:0; font-size:18px; color:#1d2327;"><span class="dashicons dashicons-email-alt" style="vertical-align:middle; color:#777BB3;"></span> <?php _e('Contact Support', 'phpinfo-wp'); ?></h3>
            <p style="color:#475569; font-size:14px; line-height:1.6; margin-bottom:20px;">
                <?php _e('As a Pro user, you get priority email support. If you are experiencing a bug, need help configuring the plugin, or have a feature request, please open a ticket.', 'phpinfo-wp'); ?>
            </p>
            <a href="https://exeebit.com/support" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3;">
                <?php _e('Open a Support Ticket &rarr;', 'phpinfo-wp'); ?>
            </a>
            
            <hr style="border:0; border-top:1px solid #e2e8f0; margin:24px 0;">
            
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

        <!-- System Info Card -->
        <div style="flex:1; min-width:300px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:24px;">
            <h3 style="margin-top:0; font-size:18px; color:#1d2327;"><span class="dashicons dashicons-desktop" style="vertical-align:middle; color:#777BB3;"></span> <?php _e('System Information', 'phpinfo-wp'); ?></h3>
            <p style="color:#475569; font-size:14px; line-height:1.6;">
                <?php _e('Please copy and paste this information when opening a support ticket. It helps us diagnose issues faster.', 'phpinfo-wp'); ?>
            </p>
            <textarea readonly style="width:100%; height:140px; font-family:monospace; font-size:13px; background:#f8fafc; color:#334155; border:1px solid #cbd5e1; border-radius:4px; padding:12px; margin-bottom:12px;" onclick="this.select();"><?php echo esc_textarea($sysinfo_str); ?></textarea>
            <button type="button" class="button" onclick="var t=this.previousElementSibling; t.select(); document.execCommand('copy'); this.innerText='Copied!'; setTimeout(()=>this.innerText='<?php _e('Copy System Info', 'phpinfo-wp'); ?>', 2000);">
                <?php _e('Copy System Info', 'phpinfo-wp'); ?>
            </button>
        </div>
    </div>
</div>
