<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

// Handle non-JS fallback form actions
if (isset($_POST['phpinfowp_sec_scan']) && check_admin_referer('phpinfowp_sec_nonce')) {
    $audit = Phpinfo_WP_Security_Headers::scan(true);
} elseif (isset($_POST['phpinfowp_sec_clear']) && check_admin_referer('phpinfowp_sec_nonce')) {
    Phpinfo_WP_Security_Headers::bust_cache();
    $audit = null;
} else {
    $audit = Phpinfo_WP_Security_Headers::get_result();
    if ($audit === null && $is_pro) {
        $audit = Phpinfo_WP_Security_Headers::scan();
    }
}
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
        <div>
            <h1 style="display:flex; align-items:center; gap:8px; margin:0; font-size:22px; font-weight:700; color:#0f172a;">
                <?php _e('Security Headers', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('HTTP response header audit — graded against OWASP recommendations with 1-click automated hardening.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
        <?php if ($is_pro): 
            $is_active = Phpinfo_WP_Security_Headers::is_active();
            $results   = $audit['results'] ?? [];
            $missing_count = count(array_filter($results, function($r) { return empty($r['present']); }));
        ?>
            <div class="phpinfowp-compat-controls" style="margin:0; gap:8px; display:flex; align-items:center;">
                <?php if ($audit && $missing_count > 0): ?>
                    <button type="button" id="phpinfowp-sec-autofix-btn" class="button button-primary" style="background:#6366f1; border-color:#6366f1; height:36px; line-height:34px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px; padding:0 16px;">
                        <span class="dashicons dashicons-shield" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                        <?php _e('Auto-Fix Missing Headers', 'phpinfo-wp'); ?>
                    </button>
                <?php endif; ?>
                <?php if ($is_active): ?>
                    <button type="button" id="phpinfowp-sec-revert-btn" class="button button-secondary" style="height:36px; line-height:34px; border-radius:6px; font-weight:600; font-size:13px;">
                        <?php _e('Revert Rules', 'phpinfo-wp'); ?>
                    </button>
                <?php endif; ?>
                <button type="button" id="phpinfowp-sec-recheck-btn" class="button <?php echo !$audit ? 'button-primary' : 'button-secondary'; ?>" style="<?php echo !$audit ? 'background:#6366f1; border-color:#6366f1; color:#fff;' : ''; ?> height:36px; line-height:34px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-update" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                    <span><?php echo $audit ? __('Re-check Headers', 'phpinfo-wp') : __('Run Header Audit', 'phpinfo-wp'); ?></span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div id="phpinfowp-sec-result-area">
        <?php if (isset($audit['error'])): ?>
            <div class="notice notice-error inline" style="margin-bottom:20px;">
                <p><?php printf(__('Could not fetch headers: %s', 'phpinfo-wp'), '<strong>' . esc_html($audit['error']) . '</strong>'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($is_free_preview): ?>
            <!-- Free preview: contextual preview cards + centered upgrade card -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Background contextual preview cards -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                    
                    <?php
                    $sh_host = wp_parse_url($audit['url'] ?? home_url(), PHP_URL_HOST);
                    $h_results = $audit['results'] ?? [];
                    $hsts = $h_results['strict-transport-security'] ?? ['present' => false, 'value' => ''];
                    $csp  = $h_results['content-security-policy'] ?? ['present' => false, 'value' => ''];
                    $xfo  = $h_results['x-frame-options'] ?? ['present' => false, 'value' => ''];
                    ?>
                    <!-- Preview Card 1: HSTS -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid <?php echo !empty($hsts['present']) ? '#00a32a' : '#d63638'; ?>; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a;">Strict-Transport-Security (HSTS)</strong>
                                <span style="background:<?php echo !empty($hsts['present']) ? '#dcfce7' : '#fee2e2'; ?>; color:<?php echo !empty($hsts['present']) ? '#15803d' : '#dc2626'; ?>; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo !empty($hsts['present']) ? 'ACTIVE' : 'MISSING'; ?></span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php printf(__('Audit for %s · Forces HTTPS and prevents SSL downgrade attacks.', 'phpinfo-wp'), esc_html($sh_host)); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px; color:<?php echo !empty($hsts['present']) ? '#15803d' : '#dc2626'; ?>; font-weight:600;"><?php echo !empty($hsts['present']) ? '✓ Protected' : 'Action Required'; ?></span>
                        </div>
                    </div>

                    <!-- Preview Card 2: CSP -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid <?php echo !empty($csp['present']) ? '#00a32a' : '#dba617'; ?>; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a;">Content-Security-Policy (CSP)</strong>
                                <span style="background:<?php echo !empty($csp['present']) ? '#dcfce7' : '#fef3c7'; ?>; color:<?php echo !empty($csp['present']) ? '#15803d' : '#b45309'; ?>; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo !empty($csp['present']) ? 'ACTIVE' : 'RECOMMENDED'; ?></span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php _e('Restricts unauthorized scripts and frame injection to prevent Cross-Site Scripting (XSS).', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px; color:<?php echo !empty($csp['present']) ? '#15803d' : '#b45309'; ?>; font-weight:600;"><?php echo !empty($csp['present']) ? '✓ Enforced' : 'Fix Available'; ?></span>
                        </div>
                    </div>

                    <!-- Preview Card 3: X-Frame-Options -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid <?php echo !empty($xfo['present']) ? '#00a32a' : '#d63638'; ?>; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a;">X-Frame-Options</strong>
                                <span style="background:<?php echo !empty($xfo['present']) ? '#dcfce7' : '#fee2e2'; ?>; color:<?php echo !empty($xfo['present']) ? '#15803d' : '#dc2626'; ?>; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo !empty($xfo['present']) ? esc_html(strtoupper($xfo['value'] ?: 'SAMEORIGIN')) : 'MISSING'; ?></span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php _e('Configures frame embedding policies — prevents UI redressing and Clickjacking attacks.', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px; color:<?php echo !empty($xfo['present']) ? '#15803d' : '#dc2626'; ?>; font-weight:600;"><?php echo !empty($xfo['present']) ? '✓ Protected' : 'Action Required'; ?></span>
                        </div>
                    </div>

                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

                <!-- Floating Upgrade Card -->
                <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                    <span class="dashicons dashicons-shield" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                    <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Security Header Hardening & Auto-Fix Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                        <?php _e('Unlock 1-click automated security header fixes with safety verification, automated rollback protection, and full OWASP compliance.', 'phpinfo-wp'); ?>
                    </p>
                    <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🛡️</span>
                            <span><strong><?php _e('OWASP Top 10 Header Hardening:', 'phpinfo-wp'); ?></strong> <?php _e('Audits HSTS, CSP, X-Frame-Options, Permissions-Policy, and Referrer-Policy against modern standards.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">⚡</span>
                            <span><strong><?php _e('1-Click Automated Auto-Fix:', 'phpinfo-wp'); ?></strong> <?php _e('Injects optimized web server directives into .htaccess or Nginx configuration without breaking frontend scripts.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🔄</span>
                            <span><strong><?php _e('Zero-Risk Loopback Verification:', 'phpinfo-wp'); ?></strong> <?php _e('Verifies header response integrity with 1-click automated rollback protection if changes fail.', 'phpinfo-wp'); ?></span>
                        </div>
                    </div>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                        <?php _e('Unlock Security Headers with Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php elseif (!$audit): ?>

            <!-- Ready to Audit Empty State -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <span class="dashicons dashicons-shield" style="font-size:30px; width:30px; height:30px;"></span>
                </div>
                <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php _e('Ready to Audit Security Headers', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 20px; color:#64748b; font-size:14px; max-width:520px; margin-left:auto; margin-right:auto; line-height:1.5;">
                    <?php _e('Click "Run Header Audit" above to asynchronously inspect your live HTTP response headers against OWASP security standards.', 'phpinfo-wp'); ?>
                </p>
                <div style="display:inline-flex; align-items:center; gap:8px; font-size:12px; color:#94a3b8;">
                    <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:16px; width:16px; height:16px;"></span>
                    <?php _e('Zero Page Lag — Runs via non-blocking asynchronous AJAX loopback.', 'phpinfo-wp'); ?>
                </div>
            </div>

        <?php else: ?>

            <!-- Header rows -->
            <div class="phpinfowp-sec-rows" style="margin-top:20px">
                <?php 
                $results = $audit['results'] ?? [];
                usort($results, function ($a, $b) {
                    $score_a = !$a['present'] ? 0 : (!empty($a['warning']) ? 1 : 2);
                    $score_b = !$b['present'] ? 0 : (!empty($b['warning']) ? 1 : 2);
                    if ($score_a !== $score_b) {
                        return $score_a <=> $score_b;
                    }
                    return strcmp($a['label'], $b['label']);
                });

                foreach ($results as $row):
                    $present = $row['present'];
                    $border  = $present ? ($row['warning'] ? '#f59e0b' : '#10b981') : '#ef4444';
                    $bg      = $present ? ($row['warning'] ? '#fffbeb' : '#f0fdf4') : '#fef2f2';
                ?>
                <div class="phpinfowp-sec-row" style="border-left-color:<?php echo $border; ?>;background:<?php echo $bg; ?>; border-radius:8px; margin-bottom:12px; padding:16px;">
                    <div class="phpinfowp-sec-row-icon">
                        <?php if ($present): ?>
                            <span class="dashicons <?php echo $row['warning'] ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>"
                                  style="color:<?php echo $row['warning'] ? '#f59e0b' : '#10b981'; ?>;"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color:#ef4444"></span>
                        <?php endif; ?>
                    </div>
                    <div class="phpinfowp-sec-row-body">
                        <div class="phpinfowp-sec-row-title" style="display:flex; justify-content:space-between; align-items:center;">
                            <strong style="color:#0f172a; font-size:14px;"><?php echo esc_html($row['label']); ?></strong>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <?php if ($present): ?>
                                    <span class="phpinfowp-compat-sev" style="background:#10b981; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; color:#fff; text-transform:uppercase;">
                                        <?php _e('Active', 'phpinfo-wp'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="phpinfowp-compat-sev" style="background:#ef4444; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; color:#fff; text-transform:uppercase;">
                                        <?php _e('Missing', 'phpinfo-wp'); ?>
                                    </span>
                                    <button type="button" class="button button-small button-secondary phpinfowp-sec-fix-single" data-key="<?php echo esc_attr($row['key']); ?>" style="height:26px; line-height:24px; font-size:11px; font-weight:600; border-radius:4px;">
                                        <?php _e('Fix', 'phpinfo-wp'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="phpinfowp-sec-row-desc" style="color:#64748b; font-size:13px; margin:4px 0 6px;"><?php echo esc_html($row['desc']); ?></div>
                        <?php 
                        $val_display = $row['value'];
                        if (is_array($val_display)) {
                            $val_display = implode(', ', array_filter(array_map('trim', $val_display)));
                        }
                        if (!empty($val_display) && $val_display !== 'Array'): ?>
                            <code class="phpinfowp-sec-row-value" style="background:rgba(0,0,0,0.04); padding:2px 6px; border-radius:4px; font-size:12px; word-break:break-word;"><?php echo esc_html($val_display); ?></code>
                        <?php else: ?>
                            <span class="phpinfowp-sec-row-missing" style="color:#94a3b8; font-size:12px;"><?php _e('not set', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                        <?php if ($row['warning']): ?>
                            <div class="phpinfowp-sec-row-warn" style="color:#b45309; font-size:12.5px; margin-top:6px;">⚠️ <?php echo esc_html($row['warning']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var nonce = '<?php echo wp_create_nonce("phpinfowp_sec_nonce"); ?>';

    function setSideLoading() {
        var sideCard = document.querySelector('.phpinfowp-side-score-card');
        if (sideCard) {
            sideCard.style.transition = 'opacity 0.2s ease';
            sideCard.style.opacity = '0.6';
            var gradeCircle = sideCard.querySelector('.phpinfowp-grade-circle');
            if (gradeCircle) {
                gradeCircle.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:22px; width:22px; height:22px; line-height:1; display:inline-block;"></span>';
            }
        }
    }

    function runAutofix(key, btn) {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Applying & verifying...', 'phpinfo-wp')); ?>';
        }
        setSideLoading();

        if (window.phpinfowpShowScanBanner) {
            window.phpinfowpShowScanBanner('<?php echo esc_js(__('Applying & Verifying Headers...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Updating server rules and verifying response headers (2–4s)', 'phpinfo-wp')); ?>');
        }

        var data = new FormData();
        data.append('action', 'phpinfowp_sec_autofix');
        data.append('nonce', nonce);
        if (key) {
            data.append('header_key', key);
        }

        fetch(ajaxurl, { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                    alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Auto-fix failed.', 'phpinfo-wp')); ?>');
                    window.location.reload();
                }
            })
            .catch(function() {
                if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                window.location.reload();
            });
    }

    var autofixBtn = document.getElementById('phpinfowp-sec-autofix-btn');
    if (autofixBtn) {
        autofixBtn.addEventListener('click', function(e) {
            e.preventDefault();
            runAutofix(null, autofixBtn);
        });
    }

    var singleFixBtns = document.querySelectorAll('.phpinfowp-sec-fix-single');
    singleFixBtns.forEach(function(b) {
        b.addEventListener('click', function(e) {
            e.preventDefault();
            var key = b.getAttribute('data-key');
            runAutofix(key, b);
        });
    });

    var revertBtn = document.getElementById('phpinfowp-sec-revert-btn');
    if (revertBtn) {
        revertBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.phpinfowpConfirm({
                title: '<?php echo esc_js(__('Revert Applied Security Headers', 'phpinfo-wp')); ?>',
                message: '<?php echo esc_js(__('Are you sure you want to revert applied security headers? Any headers added by this plugin will be removed from your server configuration.', 'phpinfo-wp')); ?>',
                confirmText: '<?php echo esc_js(__('Revert Headers', 'phpinfo-wp')); ?>',
                isDanger: true
            }).then(function(confirmed) {
                if (!confirmed) return;
                revertBtn.disabled = true;
                revertBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Reverting...', 'phpinfo-wp')); ?>';
                setSideLoading();

                if (window.phpinfowpShowScanBanner) {
                    window.phpinfowpShowScanBanner('<?php echo esc_js(__('Reverting Headers...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Removing custom security headers and re-verifying (2–4s)', 'phpinfo-wp')); ?>');
                }

                var data = new FormData();
                data.append('action', 'phpinfowp_sec_revert');
                data.append('nonce', nonce);
                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function() { window.location.reload(); })
                    .catch(function() { window.location.reload(); });
            });
        });
    }

    var recheckBtn = document.getElementById('phpinfowp-sec-recheck-btn');
    if (recheckBtn) {
        recheckBtn.addEventListener('click', function(e) {
            e.preventDefault();
            recheckBtn.disabled = true;
            recheckBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span> <?php echo esc_js(__('Auditing Headers...', 'phpinfo-wp')); ?>';
            setSideLoading();

            if (window.phpinfowpShowScanBanner) {
                window.phpinfowpShowScanBanner('<?php echo esc_js(__('Auditing Security Headers...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Querying HTTP response headers via loopback (2–4s)', 'phpinfo-wp')); ?>');
            }

            var resultArea = document.getElementById('phpinfowp-sec-result-area');
            if (resultArea) {
                resultArea.innerHTML = '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">' +
                    '<div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">' +
                    '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>' +
                    '</div>' +
                    '<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php echo esc_js(__('Auditing HTTP Response Headers...', 'phpinfo-wp')); ?></h3>' +
                    '<p style="margin:0; color:#64748b; font-size:14px;"><?php echo esc_js(__('Executing non-blocking background loopback request...', 'phpinfo-wp')); ?></p>' +
                    '</div>';
            }

            var data = new FormData();
            data.append('action', 'phpinfowp_sec_scan');
            data.append('nonce', nonce);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function() { window.location.reload(); })
                .catch(function() { window.location.reload(); });
        });
    }
});
</script>
