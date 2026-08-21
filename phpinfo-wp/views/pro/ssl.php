<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;
$message         = '';
$msg_type        = 'success';

if ($is_pro && isset($_POST['phpinfowp_ssl_action']) && check_admin_referer('phpinfowp_ssl_nonce')) {
    $action = sanitize_text_field($_POST['phpinfowp_ssl_action']);
    if ($action === 'recheck') {
        Phpinfo_WP_SSL::bust_cache();
        $message = 'Cache cleared — re-checking all certificates.';
    } elseif ($action === 'save_domains') {
        Phpinfo_WP_SSL::save_extra_domains($_POST['ssl_domains'] ?? '');
        $message = 'Domain list saved.';
    }
}

$results    = $is_pro ? Phpinfo_WP_SSL::check_all() : [Phpinfo_WP_SSL::check(wp_parse_url(get_site_url(), PHP_URL_HOST))];
$any_cached = array_filter($results, function ($r) {
    return !empty($r['cached']);
});
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1>SSL Certificate Monitor <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Monitor SSL validity, expiration dates, and domain name mismatches.', 'phpinfo-wp'); ?></p>
        </div>
    </div>



    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Certificate cards (Visible to all users) -->
    <div class="phpinfowp-ssl-grid">
        <?php foreach ($results as $cert): ?>
            <?php if (!empty($cert['error'])): ?>
                <div class="phpinfowp-ssl-card phpinfowp-ssl-error">
                    <div class="phpinfowp-ssl-card-host"><?php echo esc_html($cert['host'] ?? '—'); ?></div>
                    <div class="phpinfowp-ssl-card-status" style="color:#d63638"><?php _e('ERROR', 'phpinfo-wp'); ?></div>
                    <div class="phpinfowp-ssl-card-days" style="font-size:13px;color:#d63638">
                        <?php echo esc_html($cert['error']); ?>
                    </div>
                </div>
            <?php else:
                $color = Phpinfo_WP_SSL::status_color($cert['status']);
                $label = Phpinfo_WP_SSL::status_label($cert['status']);
            ?>
                <div class="phpinfowp-ssl-card" style="border-top-color:<?php echo $color; ?>">
                    <div class="phpinfowp-ssl-card-host">
                        <?php echo esc_html($cert['host']); ?>
                        <?php if (!empty($cert['cached'])): ?>
                            <span style="font-size:10px;color:#999;font-weight:400"> (cached)</span>
                        <?php endif; ?>
                    </div>
                    <div class="phpinfowp-ssl-card-days" style="color:<?php echo $color; ?>">
                        <?php if ($cert['days'] < 0): ?>
                            Expired <?php echo esc_html(abs($cert['days'])); ?> days ago
                        <?php else: ?>
                            <?php echo esc_html($cert['days']); ?> <span style="font-size:14px;font-weight:400"><?php _e('days left', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="phpinfowp-ssl-card-status">
                        <span class="eol-badge eol-badge-<?php echo $cert['status'] === 'ok' ? 'ok' : ($cert['status'] === 'warning' ? 'warning' : 'eol'); ?>">
                            <?php echo $label; ?>
                        </span>
                    </div>
                    <table class="phpinfowp-ssl-card-meta">
                        <tr><td><?php _e('Expires', 'phpinfo-wp'); ?></td><td><?php echo esc_html($cert['expiry']); ?></td></tr>
                        <tr><td><?php _e('Issued', 'phpinfo-wp'); ?></td><td><?php echo esc_html($cert['issued']); ?></td></tr>
                        <tr><td><?php _e('Issuer', 'phpinfo-wp'); ?></td><td><?php echo esc_html($cert['issuer']); ?></td></tr>
                        <?php if (!empty($cert['cn']) && $cert['cn'] !== $cert['host']): ?>
                        <tr><td><?php _e('CN', 'phpinfo-wp'); ?></td><td><?php echo esc_html($cert['cn']); ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($cert['sans'])): ?>
                        <tr>
                            <td><?php _e('SANs', 'phpinfo-wp'); ?></td>
                            <td style="font-size:11px;color:#666">
                                <?php echo esc_html(implode(', ', array_slice($cert['sans'], 0, 6))); ?>
                                <?php if (count($cert['sans']) > 6): ?>
                                    <em>+<?php echo count($cert['sans']) - 6; ?> more</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($is_free_preview): ?>
        <!-- Free preview: compact skeleton rows + centered upgrade card (fits in single viewpoint) -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:10px; min-height:260px; display:flex; align-items:center; justify-content:center;">
            
            <!-- Skeleton content -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.5;">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px;">
                    <?php for ($sk = 0; $sk < 2; $sk++): ?>
                    <div style="background:#fff; border:1px solid #e2e8f0; border-top:3px solid #cbd5e1; border-radius:6px; padding:12px 14px;">
                        <div style="height:12px; width:45%; background:#e2e8f0; border-radius:4px; margin-bottom:8px;"></div>
                        <div style="height:20px; width:35%; background:#cbd5e1; border-radius:4px; margin-bottom:10px;"></div>
                        <div style="height:9px; width:70%; background:#f1f5f9; border-radius:4px;"></div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(246,249,252,0.4) 0%, rgba(246,249,252,0.95) 40%, #f6f9fc 100%); pointer-events:none;"></div>

            <!-- Upgrade card floating over skeleton -->
            <div style="position:relative; z-index:2; width:100%; max-width:480px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px 28px; box-shadow:0 6px 20px -4px rgba(0,0,0,0.07); text-align:center; margin:12px auto;">
                <span class="dashicons dashicons-lock" style="font-size:30px; width:30px; height:30px; color:#777BB3; display:inline-block; margin-bottom:6px;"></span>
                <h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#1d2327;"><?php _e('Multi-Domain SSL Monitoring Locked', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 4px; font-size:13px; color:#475569; line-height:1.5; max-width:400px; margin-left:auto; margin-right:auto;">
                    <?php _e('Unlock unlimited domain monitoring, automatic 14/7/1-day email & webhook expiry alerts, and SANs coverage validation.', 'phpinfo-wp'); ?>
                </p>
                <p style="margin:0 0 16px; font-size:12px; color:#94a3b8;">
                    <?php _e('Your primary certificate status above is real — upgrade to add multi-domain tracking and automated alerts.', 'phpinfo-wp'); ?>
                </p>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3; height:38px; line-height:36px; font-size:13.5px; font-weight:600; padding:0 22px; border-radius:6px; text-decoration:none; display:inline-block;">
                    <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>

    <?php else: ?>

        <div style="display:flex;gap:10px;margin-top:16px;align-items:center;flex-wrap:wrap">
            <form method="post">
                <?php wp_nonce_field('phpinfowp_ssl_nonce'); ?>
                <input type="hidden" name="phpinfowp_ssl_action" value="recheck">
                <button type="submit" class="button button-secondary"><?php _e('Re-check all certificates', 'phpinfo-wp'); ?></button>
            </form>
            <?php if ($any_cached): ?>
                <span style="font-size:12px;color:#666"><?php _e('Results cached for 6 hours.', 'phpinfo-wp'); ?></span>
            <?php endif; ?>
        </div>

        <!-- Extra domains -->
        <div style="margin-top:32px;max-width:520px">
            <h2 style="margin-bottom:8px"><?php _e('Monitor Additional Domains', 'phpinfo-wp'); ?></h2>
            <p style="font-size:13px;color:#555;margin-bottom:12px">
                Add hostnames you want to monitor (one per line). Useful for agencies managing client sites.<br>
                Enter the domain only — no <code>https://</code> or path. Example: <code>client.com</code>
            </p>
            <form method="post">
                <?php wp_nonce_field('phpinfowp_ssl_nonce'); ?>
                <input type="hidden" name="phpinfowp_ssl_action" value="save_domains">
                <textarea name="ssl_domains" rows="5" class="large-text" style="font-family:monospace;font-size:13px"
                          placeholder="<?php echo esc_attr__('client-a.com&#10;client-b.com&#10;staging.mysite.com', 'phpinfo-wp'); ?>"><?php
                    echo esc_textarea(implode("\n", Phpinfo_WP_SSL::get_extra_domains()));
                ?></textarea>
                <p class="submit"><input type="submit" class="button button-primary" value="<?php echo esc_attr__('Save Domains', 'phpinfo-wp'); ?>"></p>
            </form>
        </div>

    <?php endif; ?>
</div>
