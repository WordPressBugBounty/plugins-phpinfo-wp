<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

// Handle non-JS fallback form actions
if (isset($_POST['phpinfowp_perms_scan']) && check_admin_referer('phpinfowp_perms_nonce')) {
    $scan_results = Phpinfo_WP_Permissions::scan(true);
} elseif (isset($_POST['phpinfowp_perms_clear']) && check_admin_referer('phpinfowp_perms_nonce')) {
    Phpinfo_WP_Permissions::clear();
    $scan_results = null;
} elseif (isset($_POST['phpinfowp_perms_fix']) && check_admin_referer('phpinfowp_perms_nonce')) {
    if ($is_pro) {
        Phpinfo_WP_Permissions::auto_fix();
        $scan_results = Phpinfo_WP_Permissions::get_result();
    }
} else {
    $scan_results = Phpinfo_WP_Permissions::get_result();
}

$wp_config      = $scan_results['wp_config'] ?? null;
$dangerous      = $scan_results['dangerous'] ?? [];
$mismatch       = $scan_results['mismatch'] ?? [];
$mismatch_count = $scan_results['mismatch_count'] ?? count($mismatch);
$scanned        = $scan_results['scanned'] ?? 0;
$scanned_dirs   = $scan_results['scanned_dirs'] ?? 0;
$scanned_files  = $scan_results['scanned_files'] ?? 0;
$php_user       = $scan_results['php_user'] ?? Phpinfo_WP_Permissions::get_php_user();
$php_uid        = $scan_results['php_uid'] ?? (function_exists('posix_geteuid') ? posix_geteuid() : (function_exists('getmyuid') ? getmyuid() : ''));
$duration       = $scan_results['duration'] ?? 0;
$truncated      = $scan_results['truncated'] ?? false;

$can_auto_fix = $is_pro && $scan_results && (!empty($dangerous) || ($wp_config && ($wp_config['perms'] ?? '') !== '0600'));
$has_issues   = $scan_results && (!empty($dangerous) || $mismatch_count > 0 || ($wp_config && !empty($wp_config['is_dangerous'])));
$bg_scan = class_exists('Phpinfo_WP_Background_Scan') ? Phpinfo_WP_Background_Scan::status('perms') : ['status' => 'idle'];
$is_bg_running = ($bg_scan['status'] === 'running');
$bg_nonce = wp_create_nonce('phpinfowp_bg_scan_nonce');
if (class_exists('Phpinfo_WP_Background_Scan')) {
    Phpinfo_WP_Background_Scan::acknowledge('perms');
}
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
        <div>
            <h1 style="display:flex; align-items:center; gap:8px; margin:0; font-size:22px; font-weight:700; color:#0f172a;">
                <?php _e('Permissions & Ownership Auditor', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Deep filesystem audit of WordPress core, plugins, themes, and configuration files to prevent security breaches and auto-update failures.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>

        <?php if ($is_pro): ?>
            <form id="phpinfowp-perms-form" method="post" style="display:flex; gap:8px; align-items:center; margin:0;">
                <?php wp_nonce_field('phpinfowp_perms_nonce'); ?>
                <?php if ($can_auto_fix && !$is_bg_running): ?>
                    <button type="button" id="phpinfowp-perms-fix-btn" class="button piwp-trigger-auto-fix" style="background:#059669; border-color:#059669; color:#fff; height:36px; line-height:34px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px; padding:0 16px; box-shadow:0 1px 2px rgba(0,0,0,0.05); cursor:pointer;">
                        <span class="dashicons dashicons-shield" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                        <span><?php _e('Auto-Fix Permissions', 'phpinfo-wp'); ?></span>
                    </button>
                <?php endif; ?>
                <button type="submit" id="phpinfowp-perms-scan-btn" name="phpinfowp_perms_scan" value="1" class="button button-primary" style="background:#6366f1; border-color:#6366f1; height:36px; line-height:34px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px; padding:0 16px;" <?php echo $is_bg_running ? 'disabled' : ''; ?>>
                    <span class="dashicons dashicons-update <?php echo $is_bg_running ? 'phpinfowp-spin' : ''; ?>" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                    <span><?php echo $is_bg_running ? __('Scanning in Background...', 'phpinfo-wp') : ($scan_results ? __('Re-run Deep Scan', 'phpinfo-wp') : __('Run Deep Scan', 'phpinfo-wp')); ?></span>
                </button>
                <?php if ($scan_results && !$is_bg_running): ?>
                    <button type="button" id="phpinfowp-perms-clear-btn" class="button button-secondary" style="height:36px; line-height:34px; border-radius:6px; font-weight:600; font-size:13px; padding:0 14px;">
                        <?php _e('Clear Results', 'phpinfo-wp'); ?>
                    </button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($truncated): ?>
        <div style="background:#fffbeb; color:#92400e; padding:14px 18px; border:1px solid #fde68a; border-left:4px solid #f59e0b; margin-bottom:20px; border-radius:8px; font-size:13.5px; display:flex; align-items:center; gap:10px;">
            <span class="dashicons dashicons-warning" style="color:#f59e0b; font-size:20px; width:20px; height:20px;"></span>
            <div>
                <strong><?php _e('Scan Timeout Safeguard:', 'phpinfo-wp'); ?></strong>
                <?php printf(esc_html__('Audited %s files and directories before reaching the server safety deadline. All critical root and code files were checked.', 'phpinfo-wp'), number_format($scanned)); ?>
            </div>
        </div>
    <?php endif; ?>

    <div id="phpinfowp-perms-result-area">
        <?php if ($is_bg_running): ?>
            <div class="phpinfowp-bg-scan-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>
                </div>
                <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php _e('Auditing Filesystem Permissions...', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 16px; color:#64748b; font-size:14px; max-width:540px; margin-left:auto; margin-right:auto; line-height:1.5;">
                    <?php _e('Scanning core, plugins, themes, and configuration files asynchronously for permission anomalies and ownership mismatches.', 'phpinfo-wp'); ?>
                </p>
                <div style="display:inline-flex; align-items:center; gap:8px; background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:8px 18px; border-radius:999px; font-size:12.5px; font-weight:600;">
                    <span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>
                    <span><?php _e('You can safely leave this page or close your browser anytime', 'phpinfo-wp'); ?></span>
                </div>
                <p style="margin:12px 0 0; font-size:12px; color:#94a3b8;">
                    <?php _e('The audit executes continuously in the background on your server. Results will load here once complete.', 'phpinfo-wp'); ?>
                </p>
            </div>
        <?php elseif ($is_free_preview): ?>
            <!-- Free preview: contextual preview cards + centered upgrade card -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Background contextual preview cards -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                    
                    <!-- Preview Row 1 -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #d63638; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;">wp-config.php</strong>
                                <span style="background:#fee2e2; color:#dc2626; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">0666 WORLD-WRITABLE</span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php printf(__('Ownership Scanned: Process User %s · Critical security audit across root directory.', 'phpinfo-wp'), esc_html($php_user ?: 'web server')); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-family:monospace; font-size:12px; background:#f1f5f9; padding:3px 8px; border-radius:4px; color:#0f172a;">chmod 600 wp-config.php</span>
                        </div>
                    </div>

                    <!-- Preview Row 2 -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #d63638; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;">.htaccess</strong>
                                <span style="background:#fee2e2; color:#dc2626; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">0777 INSECURE</span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php _e('Web server rewrite rules can be modified by unauthorized processes.', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-family:monospace; font-size:12px; background:#f1f5f9; padding:3px 8px; border-radius:4px; color:#0f172a;">chmod 644 .htaccess</span>
                        </div>
                    </div>

                    <!-- Preview Row 3 -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #00a32a; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;">wp-content/uploads/</strong>
                                <span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">0755 SECURE</span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php _e('Correct directory permissions and matching web server write access.', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px; color:#15803d; font-weight:600;">✓ Healthy</span>
                        </div>
                    </div>

                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

                <!-- Floating Upgrade Card -->
                <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                    <span class="dashicons dashicons-shield" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                    <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Permissions & Ownership Details Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                        <?php _e('Pinpoint dangerous world-writable files and fix ownership mismatches that cause failed plugin updates.', 'phpinfo-wp'); ?>
                    </p>
                    <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🔒</span>
                            <span><strong><?php _e('World-Writable Security Pinpointer:', 'phpinfo-wp'); ?></strong> <?php _e('Catches dangerous 0777/0666 files and directories vulnerable to malicious code injection.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">👤</span>
                            <span><strong><?php _e('Web Server UID/GID Ownership Audit:', 'phpinfo-wp'); ?></strong> <?php _e('Identifies user/group mismatches (e.g. root vs www-data) causing silent auto-update failures.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🛡️</span>
                            <span><strong><?php _e('1-Click Auto-Fix Permissions (Pro):', 'phpinfo-wp'); ?></strong> <?php _e('Instantly harden insecure files and directories to recommended 644/755/600 standards directly from WordPress.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">⚡</span>
                            <span><strong><?php _e('Automated SSH Remediation:', 'phpinfo-wp'); ?></strong> <?php _e('Generates exact chmod and chown terminal commands tailored to your server\'s active user for root ownership fixes.', 'phpinfo-wp'); ?></span>
                        </div>
                    </div>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                        <?php _e('Unlock Permissions Auditor with Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php elseif (!$scan_results): ?>

            <!-- Ready to scan empty state -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <span class="dashicons dashicons-shield" style="font-size:30px; width:30px; height:30px;"></span>
                </div>
                <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php _e('Ready to Audit Filesystem', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 20px; color:#64748b; font-size:14px; max-width:520px; margin-left:auto; margin-right:auto; line-height:1.5;">
                    <?php _e('Click "Run Deep Scan" above to asynchronously scan core WordPress files, plugins, themes, and configuration files for insecure world-writable permissions and update ownership mismatches.', 'phpinfo-wp'); ?>
                </p>
                <div style="display:inline-flex; align-items:center; gap:8px; font-size:12px; color:#94a3b8;">
                    <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:16px; width:16px; height:16px;"></span>
                    <?php _e('100% Uncapped Coverage — Executes via non-blocking background AJAX in milliseconds.', 'phpinfo-wp'); ?>
                </div>
            </div>

        <?php else: ?>

            <!-- Quick Stats Telemetry Strip -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:24px;">
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                    <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;"><?php _e('Files & Dirs Audited', 'phpinfo-wp'); ?></div>
                    <div style="font-size:20px; font-weight:800; color:#0f172a;">
                        <?php echo number_format($scanned); ?>
                        <span style="font-size:12px; font-weight:500; color:#64748b;">(<?php echo esc_html($duration); ?>s)</span>
                    </div>
                </div>

                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                    <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;"><?php _e('PHP Execution User', 'phpinfo-wp'); ?></div>
                    <div style="font-size:18px; font-weight:800; color:#0f172a; font-family:monospace;">
                        <?php echo esc_html($php_user); ?><?php echo $php_uid !== '' ? ' <span style="font-size:12px; font-weight:600; color:#64748b;">(' . esc_html($php_uid) . ')</span>' : ''; ?>
                    </div>
                </div>

                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                    <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;"><?php _e('Dangerous Permissions', 'phpinfo-wp'); ?></div>
                    <div style="font-size:20px; font-weight:800; color:<?php echo count($dangerous) > 0 ? '#ef4444' : '#10b981'; ?>;">
                        <?php echo count($dangerous) > 0 ? sprintf(__('%d Insecure', 'phpinfo-wp'), count($dangerous)) : __('0 (None)', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                    <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;"><?php _e('Owner Mismatches', 'phpinfo-wp'); ?></div>
                    <div style="font-size:20px; font-weight:800; color:<?php echo $mismatch_count > 0 ? '#f59e0b' : '#10b981'; ?>;">
                        <?php echo $mismatch_count > 0 ? sprintf(__('%d Mismatched', 'phpinfo-wp'), $mismatch_count) : __('0 (Clean)', 'phpinfo-wp'); ?>
                    </div>
                </div>
            </div>

            <?php if (!$has_issues): ?>
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:24px; border-radius:12px; display:flex; align-items:center; gap:16px; margin-bottom:24px;">
                    <span class="dashicons dashicons-shield-alt" style="color:#16a34a; font-size:42px; width:42px; height:42px; flex-shrink:0;"></span>
                    <div>
                        <strong style="color:#15803d; font-size:16px;"><?php _e('Filesystem is Fully Secure', 'phpinfo-wp'); ?></strong>
                        <p style="margin:4px 0 0; color:#166534; font-size:13.5px; line-height:1.5;">
                            <?php printf(esc_html__('All %s files and directories were scanned. No dangerous 0777/0666 permissions were found, and file ownership matches the PHP runtime process (%s). WordPress core, plugin, and theme updates will function smoothly without requiring FTP credentials.', 'phpinfo-wp'), number_format($scanned), esc_html($php_user)); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Core Configuration Check -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <h2 style="display:flex; align-items:center; gap:8px; margin:0 0 14px; font-size:16px; font-weight:700; color:#0f172a;">
                    <span class="dashicons dashicons-admin-generic" style="color:#6366f1;"></span>
                    <?php _e('Core Configuration Security', 'phpinfo-wp'); ?>
                </h2>
                <?php if ($wp_config): ?>
                    <table class="wp-list-table widefat striped" style="border:1px solid #f1f5f9; border-radius:8px; overflow:hidden;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="font-weight:700; color:#475569;"><?php _e('Target File', 'phpinfo-wp'); ?></th>
                                <th style="font-weight:700; color:#475569;"><?php _e('Permissions', 'phpinfo-wp'); ?></th>
                                <th style="font-weight:700; color:#475569;"><?php _e('File Owner', 'phpinfo-wp'); ?></th>
                                <th style="font-weight:700; color:#475569;"><?php _e('Security Status', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; color:#0f172a; font-weight:600;"><?php echo esc_html($wp_config['path']); ?></code></td>
                                <td><code style="font-weight:700; color:<?php echo $wp_config['is_dangerous'] ? '#ef4444' : '#10b981'; ?>;"><?php echo esc_html($wp_config['perms']); ?></code></td>
                                <td><?php echo esc_html($wp_config['owner']); ?></td>
                                <td>
                                    <?php if ($wp_config['is_dangerous']): ?>
                                        <span style="display:inline-flex; align-items:center; gap:4px; background:#fee2e2; color:#b91c1c; font-size:11.5px; font-weight:700; padding:2px 8px; border-radius:9999px;">
                                            <span class="dashicons dashicons-warning" style="font-size:14px; width:14px; height:14px; margin-top:1px;"></span> CRITICAL RISK (World-Writable)
                                        </span>
                                    <?php else: ?>
                                        <span style="display:inline-flex; align-items:center; gap:4px; background:#dcfce7; color:#15803d; font-size:11.5px; font-weight:700; padding:2px 8px; border-radius:9999px;">
                                            <span class="dashicons dashicons-yes" style="font-size:14px; width:14px; height:14px; margin-top:1px;"></span> SECURE
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:#64748b; font-size:13.5px; margin:0;"><code>wp-config.php</code> could not be located in standard directory paths.</p>
                <?php endif; ?>
            </div>

            <!-- Dangerous Permissions List -->
            <?php if (!empty($dangerous)): 
                $dang_per_page    = 15;
                $dang_total       = count($dangerous);
                $dang_total_pages = max(1, (int) ceil($dang_total / $dang_per_page));
                $dang_paged       = max(1, min($dang_total_pages, (int) ($_GET['dang_paged'] ?? 1)));
                $paged_dangerous  = array_slice($dangerous, ($dang_paged - 1) * $dang_per_page, $dang_per_page);
            ?>
                <div style="background:#fff; border:1px solid #fecaca; border-radius:12px; padding:20px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                        <div>
                            <h2 style="display:flex; align-items:center; gap:8px; margin:0 0 6px; font-size:16px; font-weight:700; color:#991b1b;">
                                <span class="dashicons dashicons-unlock" style="color:#ef4444;"></span>
                                <?php _e('Dangerous Insecure Permissions Found', 'phpinfo-wp'); ?>
                                <span style="background:#fee2e2; color:#991b1b; font-size:11.5px; font-weight:700; padding:2px 8px; border-radius:9999px;"><?php echo count($dangerous); ?></span>
                            </h2>
                            <p style="margin:0; color:#64748b; font-size:13px;"><?php _e('These files or folders are world-writable (0777 or 0666) or contain executable scripts in upload directories. Any unprivileged user or process can modify or execute them.', 'phpinfo-wp'); ?></p>
                        </div>
                        <?php if ($can_auto_fix && !$is_bg_running): ?>
                            <button type="button" class="button piwp-trigger-auto-fix" style="background:#059669; border-color:#059669; color:#fff; height:34px; line-height:32px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px; padding:0 14px; cursor:pointer;">
                                <span class="dashicons dashicons-shield" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span>
                                <span><?php _e('Auto-Fix Insecure Files', 'phpinfo-wp'); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <table class="wp-list-table widefat striped" style="border:1px solid #f1f5f9; border-radius:8px; overflow:hidden;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="font-weight:700; color:#475569;"><?php _e('Relative Path', 'phpinfo-wp'); ?></th>
                                <th style="font-weight:700; color:#475569; width:110px;"><?php _e('Type', 'phpinfo-wp'); ?></th>
                                <th style="font-weight:700; color:#475569; width:110px;"><?php _e('Permissions', 'phpinfo-wp'); ?></th>
                                <th style="font-weight:700; color:#475569; width:180px;"><?php _e('Risk Category', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paged_dangerous as $item): ?>
                                <tr>
                                    <td><code style="background:#f8fafc; padding:2px 6px; border-radius:4px;"><?php echo esc_html(str_replace(ABSPATH, '', $item['path'])); ?></code></td>
                                    <td><?php echo $item['type'] === 'dir' ? __('Directory', 'phpinfo-wp') : __('File', 'phpinfo-wp'); ?></td>
                                    <td><code style="color:#ef4444; font-weight:700;"><?php echo esc_html($item['perms']); ?></code></td>
                                    <td>
                                        <span style="background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700; padding:2px 8px; border-radius:9999px;">
                                            <?php echo esc_html($item['reason'] ?? __('World-Writable', 'phpinfo-wp')); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination Controls -->
                    <?php if ($dang_total > 0): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px;">
                            <div style="font-size:13px; color:#64748b;">
                                <?php printf(
                                    __('Showing %1$d &ndash; %2$d of %3$s insecure files', 'phpinfo-wp'),
                                    ($dang_paged - 1) * $dang_per_page + 1,
                                    min($dang_total, $dang_paged * $dang_per_page),
                                    number_format($dang_total)
                                ); ?>
                            </div>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <?php if ($dang_paged > 2): ?>
                                    <a href="<?php echo esc_url(add_query_arg(['dang_paged' => 1])); ?>" class="button button-secondary" title="<?php esc_attr_e('First page', 'phpinfo-wp'); ?>">&laquo;&laquo;</a>
                                <?php endif; ?>
                                <?php if ($dang_paged > 1): ?>
                                    <a href="<?php echo esc_url(add_query_arg(['dang_paged' => $dang_paged - 1])); ?>" class="button button-secondary">&laquo; <?php _e('Previous', 'phpinfo-wp'); ?></a>
                                <?php else: ?>
                                    <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;">&laquo; <?php _e('Previous', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                                <span style="font-size:13px; line-height:30px; padding:0 8px; color:#334155; font-weight:600;">
                                    <?php printf(__('Page %d of %d', 'phpinfo-wp'), $dang_paged, max(1, $dang_total_pages)); ?>
                                </span>
                                <?php if ($dang_paged < $dang_total_pages): ?>
                                    <a href="<?php echo esc_url(add_query_arg(['dang_paged' => $dang_paged + 1])); ?>" class="button button-secondary"><?php _e('Next', 'phpinfo-wp'); ?> &raquo;</a>
                                <?php else: ?>
                                    <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;"><?php _e('Next', 'phpinfo-wp'); ?> &raquo;</span>
                                <?php endif; ?>
                                <?php if ($dang_paged < $dang_total_pages - 1): ?>
                                    <a href="<?php echo esc_url(add_query_arg(['dang_paged' => $dang_total_pages])); ?>" class="button button-secondary" title="<?php esc_attr_e('Last page', 'phpinfo-wp'); ?>">&raquo;&raquo;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Ownership Mismatches List -->
            <?php if (!empty($mismatch)): 
                $mis_per_page    = 15;
                $mis_total       = count($mismatch);
                $mis_total_pages = max(1, (int) ceil($mis_total / $mis_per_page));
                $mis_paged       = max(1, min($mis_total_pages, (int) ($_GET['mis_paged'] ?? 1)));
                $paged_mismatch  = array_slice($mismatch, ($mis_paged - 1) * $mis_per_page, $mis_per_page);
            ?>
                <div style="background:#fff; border:1px solid #fed7aa; border-radius:12px; padding:20px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <h2 style="display:flex; align-items:center; gap:8px; margin:0 0 6px; font-size:16px; font-weight:700; color:#9a3412;">
                        <span class="dashicons dashicons-admin-users" style="color:#f97316;"></span>
                        <?php _e('Ownership Mismatches', 'phpinfo-wp'); ?>
                        <span style="background:#ffedd5; color:#9a3412; font-size:11.5px; font-weight:700; padding:2px 8px; border-radius:9999px;"><?php echo (int)$mismatch_count; ?></span>
                    </h2>
                    <p style="margin:0 0 14px; color:#64748b; font-size:13px;">
                        <?php printf(esc_html__('These files are owned by a different system user than the PHP runtime process (%s). This typically causes WordPress to fail silent background updates and prompt for FTP credentials.', 'phpinfo-wp'), '<code>' . esc_html($php_user) . '</code>'); ?>
                    </p>
                    
                    <table class="wp-list-table widefat striped" style="border:1px solid #f1f5f9; border-radius:8px; overflow:hidden;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="font-weight:700; color:#475569;"><?php _e('Relative Path', 'phpinfo-wp'); ?></th>
                                <th style="font-weight:700; color:#475569; width:150px;"><?php _e('Current Owner', 'phpinfo-wp'); ?></th>
                                <th style="font-weight:700; color:#475569; width:150px;"><?php _e('Required Runtime Owner', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paged_mismatch as $item): ?>
                                <tr>
                                    <td><code style="background:#f8fafc; padding:2px 6px; border-radius:4px;"><?php echo esc_html(str_replace(ABSPATH, '', $item['path'])); ?></code></td>
                                    <td><code style="color:#ea580c;"><?php echo esc_html($item['owner']); ?></code></td>
                                    <td><code style="color:#16a34a; font-weight:600;"><?php echo esc_html($php_user); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination Controls -->
                    <?php if ($mis_total > 0): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px;">
                            <div style="font-size:13px; color:#64748b;">
                                <?php printf(
                                    __('Showing %1$d &ndash; %2$d of %3$s mismatches', 'phpinfo-wp'),
                                    ($mis_paged - 1) * $mis_per_page + 1,
                                    min($mis_total, $mis_paged * $mis_per_page),
                                    number_format($mis_total)
                                ); ?>
                            </div>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <?php if ($mis_paged > 2): ?>
                                    <a href="<?php echo esc_url(add_query_arg(['mis_paged' => 1])); ?>" class="button button-secondary" title="<?php esc_attr_e('First page', 'phpinfo-wp'); ?>">&laquo;&laquo;</a>
                                <?php endif; ?>
                                <?php if ($mis_paged > 1): ?>
                                    <a href="<?php echo esc_url(add_query_arg(['mis_paged' => $mis_paged - 1])); ?>" class="button button-secondary">&laquo; <?php _e('Previous', 'phpinfo-wp'); ?></a>
                                <?php else: ?>
                                    <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;">&laquo; <?php _e('Previous', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                                <span style="font-size:13px; line-height:30px; padding:0 8px; color:#334155; font-weight:600;">
                                    <?php printf(__('Page %d of %d', 'phpinfo-wp'), $mis_paged, max(1, $mis_total_pages)); ?>
                                </span>
                                <?php if ($mis_paged < $mis_total_pages): ?>
                                    <a href="<?php echo esc_url(add_query_arg(['mis_paged' => $mis_paged + 1])); ?>" class="button button-secondary"><?php _e('Next', 'phpinfo-wp'); ?> &raquo;</a>
                                <?php else: ?>
                                    <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;"><?php _e('Next', 'phpinfo-wp'); ?> &raquo;</span>
                                <?php endif; ?>
                                <?php if ($mis_paged < $mis_total_pages - 1): ?>
                                    <a href="<?php echo esc_url(add_query_arg(['mis_paged' => $mis_total_pages])); ?>" class="button button-secondary" title="<?php esc_attr_e('Last page', 'phpinfo-wp'); ?>">&raquo;&raquo;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Manual SSH Remediation (Collapsible Fallback for chown Ownership) -->
            <details class="phpinfowp-ssh-details" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px 20px; box-shadow:0 1px 3px rgba(0,0,0,0.02); margin-top:20px;" <?php echo ($mismatch_count > 0 && empty($dangerous)) ? 'open' : ''; ?>>
                <summary style="display:flex; justify-content:space-between; align-items:center; cursor:pointer; font-weight:700; font-size:15px; color:#0f172a; list-style:none; outline:none; user-select:none;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-editor-code" style="color:#6366f1; font-size:18px; width:18px; height:18px;"></span>
                        <span><?php _e('Manual SSH Remediation Commands', 'phpinfo-wp'); ?></span>
                        <span style="font-size:11.5px; font-weight:600; color:#64748b; background:#f1f5f9; padding:2px 8px; border-radius:4px;">
                            <?php _e('For Root / chown Ownership Fixes', 'phpinfo-wp'); ?>
                        </span>
                    </div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <button type="button" id="piwp-copy-ssh-fix" class="button button-secondary" style="border-radius:6px; height:30px; line-height:28px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:6px;" onclick="event.stopPropagation();">
                            <span class="dashicons dashicons-clipboard" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <span id="piwp-copy-fix-text"><?php _e('Copy Commands', 'phpinfo-wp'); ?></span>
                        </button>
                        <span class="dashicons dashicons-arrow-down-alt2 piwp-details-arrow" style="color:#94a3b8; font-size:16px; width:16px; height:16px; transition:transform 0.2s ease;"></span>
                    </div>
                </summary>

                <div style="margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9;">
                    <p style="margin:0 0 14px; color:#64748b; font-size:13px; max-width:850px; line-height:1.5;">
                        <?php _e('File permissions (644/755/600) can be auto-fixed with 1 click above. However, if files are owned by <code>root</code> or another system account, the Linux kernel restricts PHP from changing owners. Run these commands via SSH terminal with superuser privileges (<code>sudo</code>) to reassign ownership to PHP.', 'phpinfo-wp'); ?>
                    </p>

                    <?php
                    $fix_script = "# 1. Fix Ownership (Enables native WP core & plugin auto-updates)\n"
                        . "chown -R " . $php_user . ":" . $php_user . " " . ABSPATH . "\n\n"
                        . "# 2. Fix Directory Permissions (755 standard)\n"
                        . "find " . ABSPATH . " -type d -exec chmod 755 {} \\;\n\n"
                        . "# 3. Fix File Permissions (644 standard)\n"
                        . "find " . ABSPATH . " -type f -exec chmod 644 {} \\;\n";
                    if ($wp_config) {
                        $fix_script .= "\n# 4. Lock down wp-config.php (600 strict)\n"
                            . "chmod 600 " . $wp_config['path'] . "\n";
                    }
                    ?>

                    <textarea id="piwp-ssh-script-raw" style="display:none;"><?php echo esc_textarea($fix_script); ?></textarea>

                    <div style="background:#0f172a; padding:18px 20px; border-radius:8px; font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:12.5px; line-height:1.7; color:#f8fafc; overflow-x:auto;">
                        <div style="color:#94a3b8; margin-bottom:4px;"># 1. Fix Ownership (Enables native WP core & plugin auto-updates)</div>
                        <div style="color:#38bdf8;">chown <span style="color:#f8fafc;">-R</span> <?php echo esc_html($php_user . ':' . $php_user); ?> <?php echo esc_html(ABSPATH); ?></div>
                        
                        <div style="color:#94a3b8; margin-top:14px; margin-bottom:4px;"># 2. Fix Directory Permissions (755 standard)</div>
                        <div style="color:#38bdf8;">find <span style="color:#f8fafc;"><?php echo esc_html(ABSPATH); ?></span> -type d -exec chmod 755 {} \;</div>
                        
                        <div style="color:#94a3b8; margin-top:14px; margin-bottom:4px;"># 3. Fix File Permissions (644 standard)</div>
                        <div style="color:#38bdf8;">find <span style="color:#f8fafc;"><?php echo esc_html(ABSPATH); ?></span> -type f -exec chmod 644 {} \;</div>
                        
                        <?php if ($wp_config): ?>
                            <div style="color:#94a3b8; margin-top:14px; margin-bottom:4px;"># 4. Lock down wp-config.php (600 strict)</div>
                            <div style="color:#38bdf8;">chmod <span style="color:#f8fafc;">600</span> <?php echo esc_html($wp_config['path']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </details>

        <?php endif; ?>
    </div>
</div>

<style>
.phpinfowp-ssh-details summary::-webkit-details-marker { display: none; }
.phpinfowp-ssh-details[open] .piwp-details-arrow { transform: rotate(180deg); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form     = document.getElementById('phpinfowp-perms-form');
    var scanBtn  = document.getElementById('phpinfowp-perms-scan-btn');
    var clearBtn = document.getElementById('phpinfowp-perms-clear-btn');
    var copyBtn  = document.getElementById('piwp-copy-ssh-fix');
    var copyTxt  = document.getElementById('piwp-copy-fix-text');
    var copyRaw  = document.getElementById('piwp-ssh-script-raw');
    var nonce    = '<?php echo wp_create_nonce("phpinfowp_perms_nonce"); ?>';
    var bgNonce  = '<?php echo esc_js($bg_nonce); ?>';
    var isRunning = <?php echo $is_bg_running ? 'true' : 'false'; ?>;
    var pollTimer = null;

    function setSideScoreCardLoading() {
        var sideCircle = document.querySelector('.phpinfowp-side-score-card .phpinfowp-grade-circle');
        if (sideCircle) {
            sideCircle.className = 'phpinfowp-grade-circle grade-b';
            sideCircle.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span>';
        }
    }

    // Auto-Fix Permissions (Pro Only)
    var fixButtons = document.querySelectorAll('.piwp-trigger-auto-fix');
    if (fixButtons && fixButtons.length > 0) {
        fixButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                var performFix = function() {
                    fixButtons.forEach(function(b) {
                        b.disabled = true;
                        b.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span> <?php echo esc_js(__('Hardening...', 'phpinfo-wp')); ?>';
                    });
                    if (scanBtn) scanBtn.disabled = true;
                    if (clearBtn) clearBtn.disabled = true;

                    setSideScoreCardLoading();

                    if (window.phpinfowpShowScanBanner) {
                        window.phpinfowpShowScanBanner('<?php echo esc_js(__('Hardening Permissions...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Applying 644/755/600 security standards directly via PHP', 'phpinfo-wp')); ?>');
                    }

                    var data = new FormData();
                    data.append('action', 'phpinfowp_perms_fix');
                    data.append('nonce', nonce);

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: data
                    }).then(function(res) {
                        return res.json();
                    }).then(function(res) {
                        if (res && res.success) {
                            window.location.reload();
                        } else {
                            alert((res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js(__('Failed to auto-fix permissions.', 'phpinfo-wp')); ?>');
                            window.location.reload();
                        }
                    }).catch(function() {
                        window.location.reload();
                    });
                };

                if (window.phpinfowpConfirm) {
                    window.phpinfowpConfirm({
                        title: '<?php echo esc_js(__('Auto-Fix Filesystem Permissions?', 'phpinfo-wp')); ?>',
                        message: '<?php echo esc_js(__('This will automatically harden wp-config.php to 0600 (or 0640), core entry files (.htaccess, index.php) to 0644, and remediate insecure world-writable (0777/0666) files and folders. Continue?', 'phpinfo-wp')); ?>',
                        confirmText: '<?php echo esc_js(__('Harden Permissions Now', 'phpinfo-wp')); ?>',
                        isDanger: false
                    }).then(function(confirmed) {
                        if (confirmed) {
                            performFix();
                        }
                    });
                } else {
                    if (confirm('<?php echo esc_js(__('Harden insecure file permissions to 644/755/600 now?', 'phpinfo-wp')); ?>')) {
                        performFix();
                    }
                }
            });
        });
    }

    function ackBgNotice() {
        if (!bgNonce) return;
        var fd = new FormData();
        fd.append('action', 'phpinfowp_bg_scan_dismiss_notice');
        fd.append('module', 'perms');
        fd.append('nonce', bgNonce);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(ajaxurl, fd);
        } else {
            fetch(ajaxurl, { method: 'POST', body: fd }).catch(function(){});
        }
    }

    function pollStatus() {
        if (pollTimer) return;
        pollTimer = setInterval(function() {
            var fd = new FormData();
            fd.append('action', 'phpinfowp_bg_scan_status');
            fd.append('module', 'perms');
            fd.append('nonce', bgNonce);

            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res && res.success && res.data) {
                        if (res.data.status === 'complete' || res.data.status === 'idle') {
                            clearInterval(pollTimer);
                            ackBgNotice();
                            window.location.reload();
                        }
                    }
                })
                .catch(function() {});
        }, 2500);
    }

    if (isRunning) {
        if (window.phpinfowpShowScanBanner) {
            window.phpinfowpShowScanBanner('<?php echo esc_js(__('Auditing Filesystem Permissions...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('You can leave anytime — audit continues on server', 'phpinfo-wp')); ?>');
        }
        pollStatus();
    }

    if (form && scanBtn) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            scanBtn.disabled = true;
            if (clearBtn) clearBtn.disabled = true;
            scanBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span> <?php echo esc_js(__('Scanning in Background...', 'phpinfo-wp')); ?>';

            setSideScoreCardLoading();

            var resultArea = document.getElementById('phpinfowp-perms-result-area');
            if (resultArea) {
                resultArea.innerHTML = '<div class="phpinfowp-bg-scan-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">' +
                    '<div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">' +
                    '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>' +
                    '</div>' +
                    '<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php echo esc_js(__('Auditing Filesystem Permissions...', 'phpinfo-wp')); ?></h3>' +
                    '<p style="margin:0 0 16px; color:#64748b; font-size:14px; max-width:540px; margin-left:auto; margin-right:auto; line-height:1.5;"><?php echo esc_js(__('Scanning core, plugins, themes, and configuration files asynchronously for permission anomalies and ownership mismatches.', 'phpinfo-wp')); ?></p>' +
                    '<div style="display:inline-flex; align-items:center; gap:8px; background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:8px 18px; border-radius:999px; font-size:12.5px; font-weight:600;">' +
                    '<span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>' +
                    '<span><?php echo esc_js(__('You can safely leave this page or close your browser anytime', 'phpinfo-wp')); ?></span>' +
                    '</div>' +
                    '<p style="margin:12px 0 0; font-size:12px; color:#94a3b8;"><?php echo esc_js(__('The audit executes continuously in the background on your server. Results will load here once complete.', 'phpinfo-wp')); ?></p>' +
                    '</div>';
            }

            if (window.phpinfowpShowScanBanner) {
                window.phpinfowpShowScanBanner('<?php echo esc_js(__('Auditing Filesystem Permissions...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('You can leave anytime — audit continues on server', 'phpinfo-wp')); ?>');
            }

            pollStatus();

            var data = new FormData();
            data.append('action', 'phpinfowp_perms_scan');
            data.append('nonce', nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            }).then(function(res) {
                return res.json();
            }).then(function(res) {
                if (pollTimer) clearInterval(pollTimer);
                ackBgNotice();
                window.location.reload();
            }).catch(function() {
                // Connection dropped or abort: keep poller running
            });
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.phpinfowpConfirm({
                title: '<?php echo esc_js(__('Clear Permission Audit Results', 'phpinfo-wp')); ?>',
                message: '<?php echo esc_js(__('Are you sure you want to clear the permission audit results? You can run a new deep scan at any time.', 'phpinfo-wp')); ?>',
                confirmText: '<?php echo esc_js(__('Clear Results', 'phpinfo-wp')); ?>',
                isDanger: true
            }).then(function(confirmed) {
                if (!confirmed) return;
                clearBtn.disabled = true;
                if (scanBtn) scanBtn.disabled = true;
                clearBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Clearing...', 'phpinfo-wp')); ?>';

                setSideScoreCardLoading();

                var data = new FormData();
                data.append('action', 'phpinfowp_perms_clear');
                data.append('nonce', nonce);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                }).then(function(res) {
                    return res.json();
                }).then(function() {
                    window.location.reload();
                }).catch(function() {
                    window.location.reload();
                });
            });
        });
    }

    if (copyBtn && copyRaw && copyTxt) {
        copyBtn.addEventListener('click', function() {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(copyRaw.value).then(function() {
                    copyTxt.textContent = 'Copied!';
                    copyBtn.style.color = '#16a34a';
                    setTimeout(function() {
                        copyTxt.textContent = 'Copy Fix Commands';
                        copyBtn.style.color = '';
                    }, 2500);
                });
            }
        });
    }
});
</script>
