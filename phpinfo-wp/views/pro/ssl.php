<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

// Non-blocking cached lookup
$primary = Phpinfo_WP_SSL::get_result();
$health  = Phpinfo_WP_SSL::get_health_cached();
$has_data = ($primary !== null || $health !== null);

// Extra monitored domains
$can_monitor_extra = Phpinfo_WP_SSL::can_monitor_extra();
$extra_domains     = $can_monitor_extra ? Phpinfo_WP_SSL::get_extra_domains() : [];
$extra_results     = [];
if ($can_monitor_extra && !empty($extra_domains)) {
    // If not cached and <= 5 domains, auto-fetch synchronously so user immediately sees live SSL results
    $should_auto_probe = (count($extra_domains) <= 5);
    foreach ($extra_domains as $d) {
        $res = Phpinfo_WP_SSL::get_result_for($d, 443, $should_auto_probe);
        if ($res) {
            $extra_results[$d] = $res;
        } else {
            $extra_results[$d] = [
                'host'       => $d,
                'status'     => 'pending',
                'days'       => null,
                'cn'         => $d,
                'issuer'     => '—',
                'issued'     => '—',
                'expiry'     => '—',
                'error'      => null,
                'is_pending' => true,
            ];
        }
    }
}

$ssl_paged = isset($_GET['ssl_paged']) ? max(1, (int)$_GET['ssl_paged']) : 1;
$ssl_per_page = 15;
$ssl_total = count($extra_domains);
$ssl_pages = max(1, (int) ceil($ssl_total / $ssl_per_page));
$paged_extra_domains = array_slice($extra_domains, ($ssl_paged - 1) * $ssl_per_page, $ssl_per_page);
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
        <div>
            <h1 style="display:flex; align-items:center; gap:8px; margin:0; font-size:22px; font-weight:700; color:#0f172a;">
                <?php _e('SSL Certificate & HTTPS Health', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Cryptographic audit, certificate expiration tracking, HTTPS redirection verification, and mixed content scanner.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
        <?php if ($is_pro): ?>
            <div class="phpinfowp-compat-controls" style="margin:0; gap:8px; display:flex; align-items:center;">
                <button type="button" id="phpinfowp-ssl-recheck-btn" class="button <?php echo !$has_data ? 'button-primary' : 'button-secondary'; ?>" style="<?php echo !$has_data ? 'background:#6366f1; border-color:#6366f1; color:#fff;' : ''; ?> height:36px; line-height:34px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-update" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                    <span><?php echo $has_data ? __('Re-check SSL & HTTPS', 'phpinfo-wp') : __('Run SSL & HTTPS Audit', 'phpinfo-wp'); ?></span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div id="phpinfowp-ssl-result-area">
        <?php if ($is_free_preview): ?>
            <!-- Free preview: contextual preview cards + centered upgrade card -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Background contextual preview cards -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                    
                    <?php
                    $ssl_host = $primary['host'] ?? (string) wp_parse_url(home_url(), PHP_URL_HOST);
                    $ssl_issuer = $primary['issuer'] ?? (is_ssl() ? 'Active TLS Certificate' : 'No TLS Certificate');
                    $ssl_days = isset($primary['days']) ? (int) $primary['days'] : (is_ssl() ? 90 : 0);
                    $ssl_valid = ($primary['status'] ?? (is_ssl() ? 'ok' : 'eol')) === 'ok';
                    ?>
                    <!-- Preview Row 1 -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid <?php echo $ssl_valid ? '#00a32a' : '#d63638'; ?>; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a;"><?php echo esc_html($ssl_host); ?></strong>
                                <span style="background:<?php echo $ssl_valid ? '#dcfce7' : '#fee2e2'; ?>; color:<?php echo $ssl_valid ? '#15803d' : '#dc2626'; ?>; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo $ssl_valid ? sprintf(__('VALID (%d DAYS)', 'phpinfo-wp'), max(1, $ssl_days)) : __('UNENCRYPTED', 'phpinfo-wp'); ?></span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php printf(__('Issuer: %s · Cryptographic &amp; Expiry Tracking Active', 'phpinfo-wp'), esc_html($ssl_issuer)); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px; color:<?php echo $ssl_valid ? '#15803d' : '#dc2626'; ?>; font-weight:600;"><?php echo $ssl_valid ? '✓ HTTPS Active' : '⚠️ No HTTPS'; ?></span>
                        </div>
                    </div>

                    <!-- Preview Row 2 -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid <?php echo is_ssl() ? '#00a32a' : '#dba617'; ?>; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a;"><?php _e('HTTPS Auto-Redirection &amp; HSTS', 'phpinfo-wp'); ?></strong>
                                <span style="background:<?php echo is_ssl() ? '#dcfce7' : '#fef3c7'; ?>; color:<?php echo is_ssl() ? '#15803d' : '#b45309'; ?>; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo is_ssl() ? 'HTTP 301 / HTTPS' : 'HTTP ONLY'; ?></span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php printf(__('Canonical URL: %s · Verified via cURL Loopback', 'phpinfo-wp'), esc_html(home_url())); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px; color:<?php echo is_ssl() ? '#15803d' : '#b45309'; ?>; font-weight:600;"><?php echo is_ssl() ? '✓ Force SSL OK' : '⚠️ Unenforced'; ?></span>
                        </div>
                    </div>

                    <!-- Preview Row 3 -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #00a32a; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a;"><?php _e('Mixed Content Asset Scanner', 'phpinfo-wp'); ?></strong>
                                <span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">ACTIVE</span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php printf(__('Auditing scripts, stylesheets, and media asset URLs on %s', 'phpinfo-wp'), esc_html($ssl_host)); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px; color:#15803d; font-weight:600;">✓ Padlock Secure</span>
                        </div>
                    </div>

                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

                <!-- Floating Upgrade Card -->
                <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                    <span class="dashicons dashicons-lock" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                    <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('HTTPS Security & Protocol Audit Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                        <?php _e('Prevent browser security warnings and broken padlock icons by validating TLS protocols, auto-redirections, and insecure mixed content.', 'phpinfo-wp'); ?>
                    </p>
                    <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🔒</span>
                            <span><strong><?php _e('Mixed Content Deep Asset Scanner:', 'phpinfo-wp'); ?></strong> <?php _e('Live scan DOM stylesheets, scripts, and media files for insecure http:// links that break the padlock.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">⏳</span>
                            <span><strong><?php _e('Automated SSL Expiration Alerts:', 'phpinfo-wp'); ?></strong> <?php _e('Receive proactive warnings 30, 14, and 3 days before TLS certificates expire.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🛡️</span>
                            <span><strong><?php _e('TLS 1.3 Handshake &amp; Protocol Auditor:', 'phpinfo-wp'); ?></strong> <?php _e('Verify cipher suites, ALPN HTTP/2 support, and strict HSTS preloading compliance.', 'phpinfo-wp'); ?></span>
                        </div>
                    </div>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                        <?php _e('Unlock SSL Audit with Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php else: // PRO user ?>

            <?php if (!$has_data): ?>

            <!-- Ready to Audit Empty State -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <span class="dashicons dashicons-admin-network" style="font-size:30px; width:30px; height:30px;"></span>
                </div>
                <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php _e('Ready to Audit SSL Certificate & HTTPS Health', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 20px; color:#64748b; font-size:14px; max-width:520px; margin-left:auto; margin-right:auto; line-height:1.5;">
                    <?php _e('Click "Run SSL & HTTPS Audit" above to asynchronously verify your SSL certificate expiration, cryptographic ciphers, SANs, and HTTP redirection rules.', 'phpinfo-wp'); ?>
                </p>
                <div style="margin-bottom:16px;">
                    <button type="button" class="button button-primary button-hero" onclick="var b = document.getElementById('phpinfowp-ssl-recheck-btn'); if (b) b.click();" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; padding:0 22px; font-size:14px; font-weight:600; border-radius:6px; display:inline-flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-update" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                        <span><?php _e('Run SSL & HTTPS Audit', 'phpinfo-wp'); ?></span>
                    </button>
                </div>
                <div style="display:inline-flex; align-items:center; gap:8px; font-size:12px; color:#94a3b8;">
                    <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:16px; width:16px; height:16px;"></span>
                    <?php _e('Zero Page Lag — Runs via non-blocking asynchronous AJAX probe.', 'phpinfo-wp'); ?>
                </div>
            </div>

        <?php else: ?>

            <?php if ($health): ?>
                <!-- SECTION 1: HTTPS & PROTOCOL HEALTH MATRIX -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-top:20px;">
                    <!-- HTTP Auto-Redirect -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                            <?php _e('HTTPS Auto-Redirect', 'phpinfo-wp'); ?>
                        </div>
                        <div style="font-size:15px; font-weight:700; color:<?php echo $health['redirect_ok'] ? '#15803d' : '#b91c1c'; ?>; margin-bottom:4px;">
                            <?php echo $health['redirect_ok'] ? '✓ ' . sprintf(__('Active (HTTP %d)', 'phpinfo-wp'), $health['redirect_code']) : '✕ ' . __('Not Redirecting', 'phpinfo-wp'); ?>
                        </div>
                        <div style="font-size:12px; color:#64748b; line-height:1.4;">
                            <?php _e('Unencrypted HTTP traffic is automatically forced to secure HTTPS.', 'phpinfo-wp'); ?>
                        </div>
                    </div>

                    <!-- WordPress URLs Scheme -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                            <?php _e('WordPress Site URLs', 'phpinfo-wp'); ?>
                        </div>
                        <div style="font-size:15px; font-weight:700; color:<?php echo $health['wp_urls_ok'] ? '#15803d' : '#b91c1c'; ?>; margin-bottom:4px;">
                            <?php echo $health['wp_urls_ok'] ? '✓ ' . __('HTTPS Configured', 'phpinfo-wp') : '✕ ' . __('HTTP Configured', 'phpinfo-wp'); ?>
                        </div>
                        <div style="font-size:12px; color:#64748b; line-height:1.4;">
                            <?php _e('Both Home URL and Site URL use the https:// scheme.', 'phpinfo-wp'); ?>
                        </div>
                    </div>

                    <!-- Admin SSL Enforcement -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                            <?php _e('Admin SSL Enforcement', 'phpinfo-wp'); ?>
                        </div>
                        <div style="font-size:15px; font-weight:700; color:<?php echo $health['force_ssl_admin'] ? '#15803d' : '#b45309'; ?>; margin-bottom:4px;">
                            <?php echo $health['force_ssl_admin'] ? '✓ ' . __('FORCE_SSL_ADMIN', 'phpinfo-wp') : 'ℹ ' . __('Default SSL', 'phpinfo-wp'); ?>
                        </div>
                        <div style="font-size:12px; color:#64748b; line-height:1.4;">
                            <?php _e('Strictly locks login and admin sessions to secure SSL encryption.', 'phpinfo-wp'); ?>
                        </div>
                    </div>

                    <!-- HSTS Header -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                            <?php _e('HSTS Security Header', 'phpinfo-wp'); ?>
                        </div>
                        <div style="font-size:15px; font-weight:700; color:<?php echo $health['hsts_ok'] ? '#15803d' : '#64748b'; ?>; margin-bottom:4px;">
                            <?php echo $health['hsts_ok'] ? '✓ ' . __('Active (Enforced)', 'phpinfo-wp') : '— ' . __('Not Enforced', 'phpinfo-wp'); ?>
                        </div>
                        <div style="font-size:12px; color:#64748b; line-height:1.4;">
                            <?php _e('Enforces browser-level SSL protection against downgrade attacks.', 'phpinfo-wp'); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- SECTION 2: CERTIFICATE CRYPTOGRAPHY & TRUST SPECS -->
            <?php if ($primary && empty($primary['error'])): ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px 24px; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
                        <div>
                            <h2 style="margin:0 0 2px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('Primary Certificate Cryptography & Coverage', 'phpinfo-wp'); ?></h2>
                            <p style="margin:0; font-size:12.5px; color:#64748b;"><?php printf(__('Issued for %s by %s', 'phpinfo-wp'), '<code>' . esc_html($primary['cn'] ?? '') . '</code>', '<strong>' . esc_html($primary['issuer'] ?? '—') . '</strong>'); ?></p>
                        </div>
                        <span class="eol-badge eol-badge-<?php echo ($primary['status'] ?? 'ok') === 'ok' ? 'ok' : (($primary['status'] ?? '') === 'warning' ? 'warning' : 'eol'); ?>">
                            <?php echo esc_html(Phpinfo_WP_SSL::status_label($primary['status'] ?? 'ok')); ?> (<?php echo (int) ($primary['days'] ?? 0); ?> <?php _e('days left', 'phpinfo-wp'); ?>)
                        </span>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
                        <div>
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('Public Key & Bits', 'phpinfo-wp'); ?></div>
                            <div style="font-size:13.5px; font-weight:700; color:#0f172a; margin-top:3px;"><?php echo esc_html($primary['key_size'] ?? '—'); ?></div>
                        </div>
                        <div>
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('Signature Algorithm', 'phpinfo-wp'); ?></div>
                            <div style="font-size:13.5px; font-weight:700; color:#0f172a; margin-top:3px;"><?php echo esc_html($primary['sig_alg'] ?? 'SHA-256'); ?></div>
                        </div>
                        <div>
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('Valid Period', 'phpinfo-wp'); ?></div>
                            <div style="font-size:13px; color:#0f172a; margin-top:3px;"><?php echo esc_html($primary['issued'] ?? '—'); ?> &rarr; <?php echo esc_html($primary['expiry'] ?? '—'); ?></div>
                        </div>
                        <div>
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('Certificate Serial', 'phpinfo-wp'); ?></div>
                            <div style="font-size:12px; color:#0f172a; margin-top:3px; font-family:monospace; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($primary['serial'] ?? ''); ?>">
                                <?php echo esc_html($primary['serial'] ?? '—'); ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($primary['sans'])): ?>
                        <div style="margin-top:16px; border-top:1px dashed #e2e8f0; padding-top:12px;">
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                                <?php _e('Subject Alternative Names (SANs Coverage)', 'phpinfo-wp'); ?>
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                <?php foreach ($primary['sans'] as $san): ?>
                                    <span style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; font-size:11.5px; font-family:monospace; padding:2px 8px; color:#334155;">
                                        <?php echo esc_html($san); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- SECTION 3: LIVE MIXED CONTENT SCANNER -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px 24px; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h2 style="margin:0 0 2px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('Mixed Content & Insecure Asset Scanner', 'phpinfo-wp'); ?></h2>
                        <p style="margin:0; font-size:12.5px; color:#64748b;">
                            <?php _e('Tests whether homepage images, scripts, stylesheets, or iframes use insecure http:// URLs.', 'phpinfo-wp'); ?>
                        </p>
                    </div>
                    <button type="button" id="phpinfowp-ssl-scan-mixed-btn" class="button button-primary" style="background:#6366f1; border-color:#6366f1;">
                        <span class="dashicons dashicons-search" style="vertical-align:middle; margin-top:-2px;"></span>
                        <?php _e('Scan for Insecure Assets', 'phpinfo-wp'); ?>
                    </button>
                </div>

                <div id="phpinfowp-ssl-mixed-results" style="margin-top:12px;">
                    <?php if (!empty($health['mixed'])): ?>
                        <?php if ($health['mixed']['count'] === 0): ?>
                            <div style="background:#f0faf2; border:1px solid #bbf7d0; border-radius:6px; padding:10px 14px; color:#15803d; font-size:13px; font-weight:600;">
                                ✓ <?php _e('Zero mixed content issues detected — all homepage assets are loaded securely over HTTPS!', 'phpinfo-wp'); ?>
                            </div>
                        <?php else: ?>
                            <div style="background:#fff4f4; border:1px solid #fecaca; border-radius:6px; padding:12px 14px; color:#b91c1c; font-size:13px;">
                                <strong><?php printf(__('Found %d insecure HTTP assets:', 'phpinfo-wp'), (int) $health['mixed']['count']); ?></strong>
                                <ul style="margin:6px 0 0 16px; font-family:monospace; font-size:11.5px;">
                                    <?php foreach ($health['mixed']['items'] as $item): ?>
                                        <li>[<?php echo esc_html($item['tag']); ?>] <?php echo esc_html($item['url']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; // End primary $has_data check ?>

        <?php if ($can_monitor_extra): ?>
        <!-- SECTION 4: ADDITIONAL MONITORED DOMAINS TABLE -->
        <div id="phpinfowp-ssl-extra-domains-container" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px 24px; margin-top:24px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:14px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <h2 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">
                        <?php _e('Additional Monitored Domains', 'phpinfo-wp'); ?>
                    </h2>
                    <span id="phpinfowp-ssl-extra-badge" style="background:#eef2ff; color:#6366f1; font-size:12px; font-weight:700; padding:2px 8px; border-radius:12px;">
                        <?php echo count($extra_domains); ?>
                    </span>
                </div>
                <?php if (!empty($extra_domains)): ?>
                    <button type="button" id="phpinfowp-ssl-scan-all-extra-btn" class="button button-secondary" style="height:32px; line-height:30px; font-size:12px; display:inline-flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-update" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                        <span><?php _e('Scan All Monitored Domains', 'phpinfo-wp'); ?></span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($extra_domains)): ?>
                <div id="phpinfowp-ssl-extra-empty" style="text-align:center; padding:32px 16px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:6px;">
                    <span class="dashicons dashicons-admin-site-alt3" style="font-size:36px; width:36px; height:36px; color:#94a3b8; margin-bottom:8px;"></span>
                    <p style="margin:0 0 4px; font-size:14px; font-weight:600; color:#334155;"><?php _e('No additional domains are being monitored yet', 'phpinfo-wp'); ?></p>
                    <p style="margin:0; font-size:12.5px; color:#64748b;"><?php _e('Add hostnames in the form below to track client sites, staging servers, and remote APIs.', 'phpinfo-wp'); ?></p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="widefat striped" style="border:none; box-shadow:none; font-size:13px;" id="phpinfowp-ssl-extra-table">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0; color:#475569; font-size:11px; text-transform:uppercase; font-weight:700; text-align:left;">
                                <th style="padding:10px 12px;"><?php _e('Domain / Hostname', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 12px;"><?php _e('SSL Status', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 12px;"><?php _e('Days Left', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 12px;"><?php _e('Expiration Date', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 12px;"><?php _e('Issuer / CA', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 12px;"><?php _e('Public Key & Cipher', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 12px; text-align:right;"><?php _e('Actions', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paged_extra_domains as $d):
                                $res = $extra_results[$d] ?? null;
                                $has_err = !empty($res['error']);
                                $is_pending = !empty($res['is_pending']);
                                $status = $res['status'] ?? 'pending';
                                $days = isset($res['days']) ? (int) $res['days'] : null;
                            ?>
                                <tr id="phpinfowp-ssl-row-<?php echo esc_attr(sanitize_key($d)); ?>" style="border-bottom:1px solid #f1f5f9; vertical-align:middle;">
                                    <td style="padding:12px;">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <a href="https://<?php echo esc_attr($d); ?>" target="_blank" rel="noopener noreferrer" style="font-weight:700; color:#0f172a; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                                                <?php echo esc_html($d); ?>
                                                <span class="dashicons dashicons-external" style="font-size:13px; width:13px; height:13px; color:#94a3b8;"></span>
                                            </a>
                                        </div>
                                        <?php if (!empty($res['cn']) && $res['cn'] !== $d): ?>
                                            <div style="font-size:11px; color:#64748b; font-family:monospace; margin-top:2px;">CN: <?php echo esc_html($res['cn']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px;" class="cell-status">
                                        <?php if ($has_err): ?>
                                            <span class="eol-badge eol-badge-eol" style="background:#fee2e2; color:#dc2626; font-size:11px; font-weight:700; padding:2px 7px; border-radius:4px;" title="<?php echo esc_attr($res['error']); ?>">
                                                <?php _e('UNREACHABLE', 'phpinfo-wp'); ?>
                                            </span>
                                        <?php elseif ($is_pending): ?>
                                            <span style="background:#f1f5f9; color:#64748b; font-size:11px; font-weight:700; padding:2px 7px; border-radius:4px;">
                                                <?php _e('PENDING SCAN', 'phpinfo-wp'); ?>
                                            </span>
                                        <?php else:
                                            $s_label = Phpinfo_WP_SSL::status_label($status);
                                            $s_bg = $status === 'ok' ? '#dcfce7' : ($status === 'warning' ? '#fef3c7' : '#fee2e2');
                                            $s_fg = $status === 'ok' ? '#15803d' : ($status === 'warning' ? '#b45309' : '#dc2626');
                                        ?>
                                            <span style="background:<?php echo $s_bg; ?>; color:<?php echo $s_fg; ?>; font-size:11px; font-weight:700; padding:2px 7px; border-radius:4px;">
                                                <?php echo esc_html($s_label); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px;" class="cell-days">
                                        <?php if ($has_err): ?>
                                            <span style="color:#dc2626; font-size:11.5px;"><?php echo esc_html($res['error']); ?></span>
                                        <?php elseif ($is_pending || $days === null): ?>
                                            <span style="color:#94a3b8; font-size:12px;">—</span>
                                        <?php elseif ($days < 0): ?>
                                            <strong style="color:#dc2626; font-size:12.5px;"><?php printf(__('Expired %d days ago', 'phpinfo-wp'), abs($days)); ?></strong>
                                        <?php else:
                                            $d_color = $days < 7 ? '#dc2626' : ($days < 30 ? '#d97706' : '#15803d');
                                        ?>
                                            <strong style="color:<?php echo $d_color; ?>; font-size:12.5px;"><?php printf(__('%d days left', 'phpinfo-wp'), $days); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px; font-size:12.5px; color:#334155;" class="cell-expiry">
                                        <?php echo esc_html($res['expiry'] ?? '—'); ?>
                                    </td>
                                    <td style="padding:12px; font-size:12px; color:#334155; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" class="cell-issuer" title="<?php echo esc_attr($res['issuer'] ?? ''); ?>">
                                        <strong><?php echo esc_html($res['issuer'] ?? '—'); ?></strong>
                                    </td>
                                    <td style="padding:12px; font-size:11.5px; color:#64748b;" class="cell-crypto">
                                        <?php
                                        $crypto_parts = array_filter([$res['key_size'] ?? '', $res['sig_alg'] ?? '']);
                                        echo esc_html(!empty($crypto_parts) ? implode(' · ', $crypto_parts) : '—');
                                        ?>
                                    </td>
                                    <td style="padding:12px; text-align:right; white-space:nowrap;">
                                        <button type="button" class="button button-small phpinfowp-ssl-recheck-single-btn" data-domain="<?php echo esc_attr($d); ?>" style="display:inline-flex; align-items:center; gap:4px; height:28px; line-height:26px;">
                                            <span class="dashicons dashicons-update" style="font-size:13px; width:13px; height:13px; margin-top:2px;"></span>
                                            <span><?php _e('Re-check', 'phpinfo-wp'); ?></span>
                                        </button>
                                        <button type="button" class="button button-small button-link-delete phpinfowp-ssl-remove-domain-btn" data-domain="<?php echo esc_attr($d); ?>" style="height:28px; line-height:26px; margin-left:4px; color:#dc2626;">
                                            <?php _e('Remove', 'phpinfo-wp'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($ssl_pages > 1): ?>
                    <div class="tablenav" style="margin-top:12px; margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
                        <div class="tablenav-pages">
                            <span class="displaying-num" style="font-size:12px; color:#64748b;"><?php printf(__('%d domains (15 per page)', 'phpinfo-wp'), $ssl_total); ?></span>
                            <span class="pagination-links" style="margin-left:12px;">
                                <?php if ($ssl_paged > 1): ?>
                                    <a class="prev-page button button-small" href="<?php echo esc_url(add_query_arg('ssl_paged', $ssl_paged - 1)); ?>">&lsaquo; <?php _e('Prev', 'phpinfo-wp'); ?></a>
                                <?php endif; ?>
                                <span class="paging-input" style="font-size:12px; margin:0 6px;">
                                    <span class="current-page"><?php echo $ssl_paged; ?></span> <?php _e('of', 'phpinfo-wp'); ?> <span class="total-pages"><?php echo $ssl_pages; ?></span>
                                </span>
                                <?php if ($ssl_paged < $ssl_pages): ?>
                                    <a class="next-page button button-small" href="<?php echo esc_url(add_query_arg('ssl_paged', $ssl_paged + 1)); ?>"><?php _e('Next', 'phpinfo-wp'); ?> &rsaquo;</a>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- SECTION 5: MONITOR ADDITIONAL DOMAINS BLOCK -->
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.03); max-width:640px; margin-top:24px;">
            <h2 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('Monitor Additional Domains', 'phpinfo-wp'); ?></h2>
            <p style="font-size:13px; color:#64748b; margin:0 0 14px; line-height:1.5;">
                <?php _e('Add hostnames you want to monitor (one per line). Ideal for agencies tracking client sites or staging environments.', 'phpinfo-wp'); ?><br>
                <?php _e('Enter the domain only (e.g. <code>client.com</code>, <code>staging.site.com</code>).', 'phpinfo-wp'); ?>
            </p>
            <form id="phpinfowp-ssl-domains-form" method="post">
                <?php wp_nonce_field('phpinfowp_ssl_nonce'); ?>
                <textarea id="phpinfowp-ssl-domains-input" name="ssl_domains" rows="4" class="large-text" style="font-family:monospace; font-size:13px; border-radius:6px; padding:10px 12px; border-color:#cbd5e1;"
                          placeholder="<?php echo esc_attr__('client-a.com&#10;client-b.com&#10;staging.mysite.com', 'phpinfo-wp'); ?>"><?php
                    echo esc_textarea(implode("\n", Phpinfo_WP_SSL::get_extra_domains()));
                ?></textarea>
                <p class="submit" style="margin:12px 0 0; padding:0;">
                    <button type="submit" id="phpinfowp-ssl-save-btn" class="button button-primary" style="background:#6366f1; border-color:#6366f1; height:34px; line-height:32px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-saved" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span>
                        <span><?php _e('Save Domains', 'phpinfo-wp'); ?></span>
                    </button>
                </p>
            </form>
        </div>
        <?php else: ?>
        <!-- LOCKED: ADDITIONAL DOMAIN MONITORING (UNLIMITED & LIFETIME EXCLUSIVE) -->
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:28px 32px; margin-top:24px; box-shadow:0 1px 3px rgba(0,0,0,0.03); position:relative; overflow:hidden;">
            <div style="position:absolute; top:0; left:0; width:4px; height:100%; background:linear-gradient(180deg, #6366f1 0%, #a855f7 100%);"></div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:24px;">
                <div style="max-width:720px;">
                    <div style="display:inline-flex; align-items:center; gap:6px; background:#eef2ff; color:#6366f1; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; padding:4px 10px; border-radius:12px; margin-bottom:12px;">
                        <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:1px;"></span>
                        <?php _e('Unlimited & Lifetime Exclusive', 'phpinfo-wp'); ?>
                    </div>
                    <h2 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;">
                        <?php _e('Monitor Additional Domains & External Sites', 'phpinfo-wp'); ?>
                    </h2>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#64748b; line-height:1.6;">
                        <?php printf(
                            __('Your Single-Site license actively monitors SSL certificates, HTTPS protocols, and mixed content for your primary domain (<strong>%s</strong>). To monitor remote client websites, staging servers, and external hostnames from this dashboard, upgrade to an Unlimited or Lifetime license.', 'phpinfo-wp'),
                            esc_html(wp_parse_url(get_site_url(), PHP_URL_HOST) ?: 'this site')
                        ); ?>
                    </p>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px; margin-bottom:22px;">
                        <div style="display:flex; align-items:flex-start; gap:8px; font-size:13px; color:#334155;">
                            <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:18px; width:18px; height:18px; flex-shrink:0; margin-top:1px;"></span>
                            <span><?php _e('Monitor unlimited external domains & subdomains', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px; font-size:13px; color:#334155;">
                            <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:18px; width:18px; height:18px; flex-shrink:0; margin-top:1px;"></span>
                            <span><?php _e('Automatic certificate expiration tracking & alerts', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px; font-size:13px; color:#334155;">
                            <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:18px; width:18px; height:18px; flex-shrink:0; margin-top:1px;"></span>
                            <span><?php _e('One-click bulk cryptographic & cipher audits', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px; font-size:13px; color:#334155;">
                            <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:18px; width:18px; height:18px; flex-shrink:0; margin-top:1px;"></span>
                            <span><?php _e('White-labeled client PDF reports & webhook alerts', 'phpinfo-wp'); ?></span>
                        </div>
                    </div>
                    <div>
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background:#6366f1; border-color:#6366f1; height:38px; line-height:36px; padding:0 22px; font-size:13.5px; font-weight:600; border-radius:6px; display:inline-flex; align-items:center; gap:8px; text-decoration:none;">
                            <span><?php _e('Upgrade to Unlimited / Lifetime', 'phpinfo-wp'); ?></span>
                            <span class="dashicons dashicons-arrow-right-alt" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; // End $is_free_preview check ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var nonce = '<?php echo wp_create_nonce("phpinfowp_ssl_nonce"); ?>';

    function setSideLoading() {
        var sideCards = document.querySelectorAll('.phpinfowp-side-score-card');
        sideCards.forEach(function(card) {
            card.style.transition = 'opacity 0.2s ease';
            card.style.opacity = '0.6';
        });
    }

    var recheckBtn = document.getElementById('phpinfowp-ssl-recheck-btn');
    if (recheckBtn) {
        recheckBtn.addEventListener('click', function(e) {
            e.preventDefault();
            recheckBtn.disabled = true;
            recheckBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span> <?php echo esc_js(__('Probing SSL & HTTPS...', 'phpinfo-wp')); ?>';
            setSideLoading();

            if (window.phpinfowpShowScanBanner) {
                window.phpinfowpShowScanBanner('<?php echo esc_js(__('Scanning SSL & HTTPS...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Probing certificate validity & TLS handshake (2–5s)', 'phpinfo-wp')); ?>');
            }

            var resultArea = document.getElementById('phpinfowp-ssl-result-area');
            if (resultArea) {
                resultArea.innerHTML = '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">' +
                    '<div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">' +
                    '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>' +
                    '</div>' +
                    '<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php echo esc_js(__('Probing SSL Certificate & HTTPS Health...', 'phpinfo-wp')); ?></h3>' +
                    '<p style="margin:0; color:#64748b; font-size:14px;"><?php echo esc_js(__('Opening cryptographic socket and verifying HTTPS rules asynchronously...', 'phpinfo-wp')); ?></p>' +
                    '</div>';
            }

            var data = new FormData();
            data.append('action', 'phpinfowp_ssl_scan');
            data.append('nonce', nonce);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function() { window.location.reload(); })
                .catch(function() { window.location.reload(); });
        });
    }

    function checkAutoRunSSL() {
        var url = new URL(window.location.href);
        var hasParam = url.searchParams.get('ssl_run') === '1';
        var hasStorage = sessionStorage.getItem('phpinfowp_auto_run_ssl') === '1';

        if (hasParam || hasStorage) {
            sessionStorage.removeItem('phpinfowp_auto_run_ssl');
            if (hasParam) {
                url.searchParams.delete('ssl_run');
                window.history.replaceState({}, document.title, url.toString());
            }
            if (recheckBtn) {
                setTimeout(function() {
                    recheckBtn.click();
                }, 150);
            }
        }
    }
    checkAutoRunSSL();

    var mixedBtn = document.getElementById('phpinfowp-ssl-scan-mixed-btn');
    var mixedArea = document.getElementById('phpinfowp-ssl-mixed-results');

    if (mixedBtn && mixedArea) {
        mixedBtn.addEventListener('click', function(e) {
            e.preventDefault();
            mixedBtn.disabled = true;
            mixedBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Scanning homepage assets...', 'phpinfo-wp')); ?>';

            if (window.phpinfowpShowScanBanner) {
                window.phpinfowpShowScanBanner('<?php echo esc_js(__('Scanning Assets for Mixed Content...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Checking homepage resources for HTTP URLs (2–6s)', 'phpinfo-wp')); ?>');
            }

            var data = new FormData();
            data.append('action', 'phpinfowp_ssl_scan_mixed');
            data.append('nonce', nonce);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                    mixedBtn.disabled = false;
                    mixedBtn.innerHTML = '<span class="dashicons dashicons-search" style="vertical-align:middle; margin-top:-2px;"></span> <?php echo esc_js(__('Scan for Insecure Assets', 'phpinfo-wp')); ?>';
                    if (res.success) {
                        var d = res.data;
                        if (d.count === 0) {
                            mixedArea.innerHTML = '<div style="background:#f0faf2; border:1px solid #bbf7d0; border-radius:6px; padding:10px 14px; color:#15803d; font-size:13px; font-weight:600;">✓ <?php echo esc_js(__('Zero mixed content issues detected — all homepage assets are loaded securely over HTTPS!', 'phpinfo-wp')); ?></div>';
                        } else {
                            var itemsHtml = '';
                            if (d.items && d.items.length) {
                                for (var i = 0; i < d.items.length; i++) {
                                    itemsHtml += '<li>[' + d.items[i].tag + '] ' + d.items[i].url + '</li>';
                                }
                            }
                            mixedArea.innerHTML = '<div style="background:#fff4f4; border:1px solid #fecaca; border-radius:6px; padding:12px 14px; color:#b91c1c; font-size:13px;">' +
                                '<strong><?php echo esc_js(__('Found insecure HTTP assets:', 'phpinfo-wp')); ?> (' + d.count + ')</strong>' +
                                '<ul style="margin:6px 0 0 16px; font-family:monospace; font-size:11.5px;">' + itemsHtml + '</ul>' +
                                '</div>';
                        }
                    }
                })
                .catch(function() {
                    if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                    mixedBtn.disabled = false;
                    mixedBtn.innerHTML = '<span class="dashicons dashicons-search" style="vertical-align:middle; margin-top:-2px;"></span> <?php echo esc_js(__('Scan for Insecure Assets', 'phpinfo-wp')); ?>';
                });
        });
    }

    var domainsForm = document.getElementById('phpinfowp-ssl-domains-form');
    var domainsInput = document.getElementById('phpinfowp-ssl-domains-input');
    var saveBtn = document.getElementById('phpinfowp-ssl-save-btn');

    if (domainsForm && saveBtn) {
        domainsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span> <?php echo esc_js(__('Saving & Preparing Audit...', 'phpinfo-wp')); ?>';

            var data = new FormData();
            data.append('action', 'phpinfowp_ssl_save_domains');
            data.append('nonce', nonce);
            data.append('ssl_domains', domainsInput ? domainsInput.value : '');

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function() {
                    sessionStorage.setItem('phpinfowp_auto_run_ssl', '1');
                    var url = new URL(window.location.href);
                    url.searchParams.set('ssl_run', '1');
                    window.location.href = url.toString();
                })
                .catch(function() {
                    sessionStorage.setItem('phpinfowp_auto_run_ssl', '1');
                    var url = new URL(window.location.href);
                    url.searchParams.set('ssl_run', '1');
                    window.location.href = url.toString();
                });
        });
    }

    var scanAllExtraBtn = document.getElementById('phpinfowp-ssl-scan-all-extra-btn');
    if (scanAllExtraBtn) {
        scanAllExtraBtn.addEventListener('click', function(e) {
            e.preventDefault();
            scanAllExtraBtn.disabled = true;
            scanAllExtraBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span> <?php echo esc_js(__('Scanning Domains...', 'phpinfo-wp')); ?>';

            var data = new FormData();
            data.append('action', 'phpinfowp_ssl_scan_all_extra');
            data.append('nonce', nonce);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function() { window.location.reload(); })
                .catch(function() { window.location.reload(); });
        });
    }

    document.addEventListener('click', function(e) {
        var recheckSingle = e.target.closest('.phpinfowp-ssl-recheck-single-btn');
        if (recheckSingle) {
            e.preventDefault();
            var domain = recheckSingle.getAttribute('data-domain');
            if (!domain) return;

            var origHtml = recheckSingle.innerHTML;
            recheckSingle.disabled = true;
            recheckSingle.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:13px; width:13px; height:13px; margin-top:2px;"></span>';

            var row = recheckSingle.closest('tr');

            var data = new FormData();
            data.append('action', 'phpinfowp_ssl_check_single');
            data.append('nonce', nonce);
            data.append('domain', domain);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    recheckSingle.disabled = false;
                    recheckSingle.innerHTML = origHtml;
                    if (res.success && res.data && row) {
                        var d = res.data;
                        var cellStatus = row.querySelector('.cell-status');
                        var cellDays = row.querySelector('.cell-days');
                        var cellExpiry = row.querySelector('.cell-expiry');
                        var cellIssuer = row.querySelector('.cell-issuer');
                        var cellCrypto = row.querySelector('.cell-crypto');

                        if (d.error) {
                            if (cellStatus) cellStatus.innerHTML = '<span class="eol-badge eol-badge-eol" style="background:#fee2e2; color:#dc2626; font-size:11px; font-weight:700; padding:2px 7px; border-radius:4px;">UNREACHABLE</span>';
                            if (cellDays) cellDays.innerHTML = '<span style="color:#dc2626; font-size:11.5px;">' + (d.error || '') + '</span>';
                        } else {
                            var sLabel = d.status === 'ok' ? 'VALID' : (d.status === 'warning' ? 'EXPIRING SOON' : 'EXPIRED');
                            var sBg = d.status === 'ok' ? '#dcfce7' : (d.status === 'warning' ? '#fef3c7' : '#fee2e2');
                            var sFg = d.status === 'ok' ? '#15803d' : (d.status === 'warning' ? '#b45309' : '#dc2626');
                            if (cellStatus) cellStatus.innerHTML = '<span style="background:' + sBg + '; color:' + sFg + '; font-size:11px; font-weight:700; padding:2px 7px; border-radius:4px;">' + sLabel + '</span>';

                            var dDays = parseInt(d.days, 10);
                            var dColor = dDays < 7 ? '#dc2626' : (dDays < 30 ? '#d97706' : '#15803d');
                            var daysStr = dDays < 0 ? 'Expired ' + Math.abs(dDays) + ' days ago' : dDays + ' days left';
                            if (cellDays) cellDays.innerHTML = '<strong style="color:' + dColor + '; font-size:12.5px;">' + daysStr + '</strong>';
                            if (cellExpiry) cellExpiry.textContent = d.expiry || '—';
                            if (cellIssuer) cellIssuer.innerHTML = '<strong>' + (d.issuer || '—') + '</strong>';
                            var cryptoArr = [];
                            if (d.key_size) cryptoArr.push(d.key_size);
                            if (d.sig_alg) cryptoArr.push(d.sig_alg);
                            if (cellCrypto) cellCrypto.textContent = cryptoArr.join(' · ') || '—';
                        }
                    }
                })
                .catch(function() {
                    recheckSingle.disabled = false;
                    recheckSingle.innerHTML = origHtml;
                });
        }

        var removeBtn = e.target.closest('.phpinfowp-ssl-remove-domain-btn');
        if (removeBtn) {
            e.preventDefault();
            var domainToRemove = removeBtn.getAttribute('data-domain');
            if (!domainToRemove) return;
            if (!confirm('<?php echo esc_js(__('Remove this domain from SSL monitoring?', 'phpinfo-wp')); ?>')) return;

            removeBtn.disabled = true;
            var data = new FormData();
            data.append('action', 'phpinfowp_ssl_remove_domain');
            data.append('nonce', nonce);
            data.append('domain', domainToRemove);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        var row = removeBtn.closest('tr');
                        if (row) {
                            row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(10px)';
                            setTimeout(function() {
                                row.remove();
                                var badge = document.getElementById('phpinfowp-ssl-extra-badge');
                                if (badge && res.data && typeof res.data.remaining !== 'undefined') {
                                    badge.textContent = res.data.remaining;
                                }
                                var input = document.getElementById('phpinfowp-ssl-domains-input');
                                if (input && res.data && typeof res.data.domains_text !== 'undefined') {
                                    input.value = res.data.domains_text;
                                }
                                if (res.data && res.data.remaining === 0) {
                                    window.location.reload();
                                }
                            }, 300);
                        }
                    } else {
                        removeBtn.disabled = false;
                        alert(res.data && res.data.message ? res.data.message : 'Failed to remove domain.');
                    }
                })
                .catch(function() {
                    removeBtn.disabled = false;
                });
        }
    });
});
</script>
