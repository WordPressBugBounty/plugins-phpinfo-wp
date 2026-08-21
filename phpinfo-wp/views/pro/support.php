<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

$payload = Phpinfo_WP_License::payload();
$email   = $payload['email'] ?? ($is_pro ? 'Unknown' : get_option('admin_email'));

$sysinfo = [
    'Site URL'   => get_site_url(),
    'WP Version' => get_bloginfo('version'),
    'PHP Version'=> PHP_VERSION,
    'Plugin Ver' => PHPINFOWP_VERSION,
    'License'    => $is_pro ? (Phpinfo_WP_License::is_unlimited() ? 'Unlimited' : 'Single Site') : 'Free Tier',
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

        <!-- System Info Card (Visible to all users) -->
        <div style="flex:1; min-width:300px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:24px;">
            <h3 style="margin-top:0; font-size:18px; color:#1d2327;"><span class="dashicons dashicons-desktop" style="vertical-align:middle; color:#777BB3;"></span> <?php _e('System Information', 'phpinfo-wp'); ?></h3>
            <p style="color:#475569; font-size:14px; line-height:1.6;">
                <?php _e('Real-time server environment specs. Copy and attach this when requesting technical support.', 'phpinfo-wp'); ?>
            </p>
            <textarea readonly style="width:100%; height:140px; font-family:monospace; font-size:13px; background:#f8fafc; color:#334155; border:1px solid #cbd5e1; border-radius:4px; padding:12px; margin-bottom:12px;" onclick="this.select();"><?php echo esc_textarea($sysinfo_str); ?></textarea>
            <button type="button" class="button" onclick="var t=this.previousElementSibling; t.select(); document.execCommand('copy'); this.innerText='Copied!'; setTimeout(()=>this.innerText='<?php _e('Copy System Info', 'phpinfo-wp'); ?>', 2000);">
                <?php _e('Copy System Info', 'phpinfo-wp'); ?>
            </button>
        </div>

        <?php if ($is_free_preview): ?>
            <!-- Free preview: compact skeleton + centered upgrade card -->
            <div style="flex:1; min-width:300px; position:relative; overflow:hidden; border-radius:8px; display:flex; align-items:center; justify-content:center; min-height:260px;">
                
                <!-- Skeleton content -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; opacity:0.4;">
                    <div style="height:14px; width:45%; background:#cbd5e1; border-radius:4px; margin-bottom:10px;"></div>
                    <div style="height:10px; width:90%; background:#f1f5f9; border-radius:4px; margin-bottom:6px;"></div>
                    <div style="height:10px; width:75%; background:#f1f5f9; border-radius:4px; margin-bottom:14px;"></div>
                    <div style="height:32px; width:140px; background:#e2e8f0; border-radius:4px;"></div>
                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(246,249,252,0.4) 0%, rgba(246,249,252,0.95) 40%, #f6f9fc 100%); pointer-events:none;"></div>

                <!-- Upgrade card floating over skeleton -->
                <div style="position:relative; z-index:2; width:90%; max-width:440px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:22px 20px; box-shadow:0 6px 20px -4px rgba(0,0,0,0.07); text-align:center;">
                    <span class="dashicons dashicons-lock" style="font-size:30px; width:30px; height:30px; color:#777BB3; display:inline-block; margin-bottom:6px;"></span>
                    <h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#1d2327;"><?php _e('Priority Ticket Portal Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 14px; font-size:13px; color:#475569; line-height:1.5;">
                        <?php _e('Unlock private 1-on-1 priority email support, <24-hour response SLA, and direct bug triage from our core engineers.', 'phpinfo-wp'); ?>
                    </p>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3; height:38px; line-height:36px; font-size:13.5px; font-weight:600; padding:0 22px; border-radius:6px; text-decoration:none; display:inline-block;">
                        <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php else: ?>

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

        <?php endif; ?>

    </div>
</div>
