<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro = Phpinfo_WP_License::is_valid();
$current_minor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$targets = Phpinfo_WP_Compat::targets();

// Determine smart default target: next higher version, or 8.4
$smart_default = '8.4';
foreach ($targets as $t) {
    if (version_compare($t, $current_minor, '>')) {
        $smart_default = $t;
        break;
    }
}

$target = isset($_GET['target']) && in_array($_GET['target'], $targets, true)
    ? $_GET['target']
    : (isset($_POST['target']) && in_array($_POST['target'], $targets, true) ? $_POST['target'] : $smart_default);

if (isset($_POST['phpinfowp_scan']) && check_admin_referer('phpinfowp_compat_nonce')) {
    $target = sanitize_text_field($_POST['target'] ?? $smart_default);
    Phpinfo_WP_Compat::scan($target);
}
if (isset($_POST['phpinfowp_clear']) && check_admin_referer('phpinfowp_compat_nonce')) {
    Phpinfo_WP_Compat::clear();
}

$result = Phpinfo_WP_Compat::get_result();
$is_upgrade_scan = version_compare($target, $current_minor, '>');
$bg_scan = class_exists('Phpinfo_WP_Background_Scan') ? Phpinfo_WP_Background_Scan::status('compat') : ['status' => 'idle'];
$is_bg_running = ($bg_scan['status'] === 'running');
$bg_nonce = wp_create_nonce('phpinfowp_bg_scan_nonce');
if (class_exists('Phpinfo_WP_Background_Scan')) {
    Phpinfo_WP_Background_Scan::acknowledge('compat');
}
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>
                <?php _e('PHP Compatibility Scanner', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Scan your installed plugins and themes for deprecated or removed PHP functions before upgrading server PHP versions.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>

    <?php if (!$is_pro): ?>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #6366f1; border-radius:10px; padding:12px 18px; margin:0 0 20px; display:flex; justify-content:space-between; align-items:center; gap:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
            <div style="flex:1; min-width:0;">
                <div style="font-size:13.5px; color:#0f172a; font-weight:500; line-height:1.4; margin-bottom:3px;">
                    <strong><?php _e('Free scanner:', 'phpinfo-wp'); ?></strong> <?php _e('Scans all active plugins &amp; themes for PHP compatibility issues.', 'phpinfo-wp'); ?>
                </div>
                <div style="font-size:12.5px; color:#64748b; line-height:1.4;">
                    <?php _e('Upgrade to Pro for automated post-update crash detection, instant email alerts, and 1-click rollback if an update breaks your site.', 'phpinfo-wp'); ?>
                </div>
            </div>
            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="background:#6366f1; border-color:#6366f1; height:36px; line-height:34px; font-weight:600; font-size:13px; padding:0 18px; border-radius:6px; white-space:nowrap; flex-shrink:0; text-decoration:none; box-shadow:0 2px 6px rgba(99,102,241,0.25); display:inline-flex; align-items:center; justify-content:center; align-self:center; margin:0;">
                <?php _e('Get Pro &rarr;', 'phpinfo-wp'); ?>
            </a>
        </div>
    <?php endif; ?>

    <form id="phpinfowp-compat-form" method="post" class="phpinfowp-compat-controls">
        <?php wp_nonce_field('phpinfowp_compat_nonce'); ?>
        <label>
            <strong><?php _e('Target PHP version:', 'phpinfo-wp'); ?></strong>
            <select id="phpinfowp-compat-target" name="target" <?php echo $is_bg_running ? 'disabled' : ''; ?>>
                <?php foreach ($targets as $t): 
                    $is_upgrade = version_compare($t, $current_minor, '>');
                    $is_current = version_compare($t, $current_minor, '=');
                    $badge = $is_upgrade ? __(' — Upgrade Target', 'phpinfo-wp') : ($is_current ? __(' — Current Active', 'phpinfo-wp') : __(' — Legacy Check', 'phpinfo-wp'));
                ?>
                    <option value="<?php echo esc_attr($t); ?>" <?php selected($target, $t); ?>>PHP <?php echo esc_html($t . $badge); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" id="phpinfowp-compat-scan-btn" name="phpinfowp_scan" value="1" class="button button-primary" <?php echo $is_bg_running ? 'disabled' : ''; ?>>
            <?php if ($is_bg_running): ?>
                <span class="dashicons dashicons-update phpinfowp-spin"></span> <?php _e('Scanning in Background...', 'phpinfo-wp'); ?>
            <?php else: ?>
                <span class="dashicons dashicons-search"></span> <?php _e('Run Scan', 'phpinfo-wp'); ?>
            <?php endif; ?>
        </button>
        <?php if ($result && !$is_bg_running): ?>
            <button type="button" id="phpinfowp-compat-clear-btn" name="phpinfowp_clear" value="1" class="button button-secondary"><?php _e('Clear results', 'phpinfo-wp'); ?></button>
        <?php endif; ?>
    </form>

    <div id="phpinfowp-compat-result-area">
        <?php if ($is_bg_running): ?>
            <div class="phpinfowp-bg-scan-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:36px 28px; text-align:center; margin-top:20px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                <div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>
                </div>
                <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php _e('PHP Compatibility Scan in Progress...', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 16px; font-size:13.5px; color:#64748b; max-width:540px; margin-left:auto; margin-right:auto; line-height:1.5;">
                    <?php printf(esc_html__('Delta analysis is running across all active plugins and themes against PHP %s.', 'phpinfo-wp'), esc_html($target)); ?>
                </p>
                <div style="display:inline-flex; align-items:center; gap:8px; background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:8px 18px; border-radius:999px; font-size:12.5px; font-weight:600;">
                    <span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>
                    <span><?php _e('You can safely leave this page or close your browser anytime', 'phpinfo-wp'); ?></span>
                </div>
                <p style="margin:12px 0 0; font-size:12px; color:#94a3b8;">
                    <?php _e('The scan executes continuously in the background on your server. Results will load here once complete.', 'phpinfo-wp'); ?>
                </p>
            </div>
        <?php elseif (isset($result['error'])): ?>
            <div class="notice notice-error inline"><p><?php echo esc_html($result['error']); ?></p></div>
        <?php elseif (!$result): ?>
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:36px 24px; text-align:center; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                <span class="dashicons dashicons-search" style="font-size:36px; width:36px; height:36px; color:#64748b; display:inline-block; margin-bottom:14px;"></span>
                <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('No scan yet', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 6px; font-size:13px; color:#64748b;"><?php _e('Pick a target PHP version above and click "Run Scan" to analyze your installed plugins and themes. Runs in the background — you can safely leave anytime.', 'phpinfo-wp'); ?></p>
                <p style="margin:0; font-size:12px; color:#94a3b8;"><?php _e('Scans all PHP files across your plugins & themes without any file limits. Takes 10–60 seconds.', 'phpinfo-wp'); ?></p>
            </div>
        <?php else:
            $sev_color = ['removed' => '#d63638', 'deprecated' => '#dba617'];
            $sev_label = ['removed' => 'REMOVED', 'deprecated' => 'DEPRECATED'];
            $issues = $result['issues'] ?? [];
            uksort($issues, function ($a, $b) use ($issues) {
                return count($issues[$b]) - count($issues[$a]);
            });

            $current_php = phpversion();
            $is_already_on_target = version_compare($current_php, $result['target'], '>=');

            $total_removed = 0;
            $total_deprecated = 0;
            foreach ($issues as $list) {
                foreach ($list as $issue) {
                    if (!empty($issue['ignored'])) continue;
                    if ($issue['severity'] === 'removed') $total_removed++;
                    else $total_deprecated++;
                }
            }

            $files_scanned = $result['files'] ?? 0;
            $was_truncated = !empty($result['truncated']);
        ?>

            <?php if ($files_scanned > 0): ?>
                <p style="margin:0 0 14px; font-size:12px; color:#15803d;">
                    <span style="font-weight:700;">✓ <?php printf(__('Full uncapped coverage: %s PHP files scanned across all active plugins &amp; themes.', 'phpinfo-wp'), number_format($files_scanned)); ?></span>
                    <span style="color:#64748b; margin-left:8px;"><?php printf(__('Scan completed in %ss.', 'phpinfo-wp'), esc_html($result['duration'] ?? '—')); ?></span>
                </p>
            <?php endif; ?>

            <?php if ($total_removed === 0 && $total_deprecated === 0): ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:36px 24px; text-align:center; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                    <span class="dashicons dashicons-yes-alt" style="font-size:36px; width:36px; height:36px; color:#15803d; display:inline-block; margin-bottom:14px;"></span>
                    <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;">
                        <?php if ($is_already_on_target): ?>
                            <?php printf(__('All plugins & themes compatible with PHP %s!', 'phpinfo-wp'), esc_html($result['target'])); ?>
                        <?php else: ?>
                            <?php printf(__('Safe to upgrade to PHP %s!', 'phpinfo-wp'), esc_html($result['target'])); ?>
                        <?php endif; ?>
                    </h3>
                    <p style="margin:0; font-size:13px; color:#64748b; max-width:620px; margin-left:auto; margin-right:auto; line-height:1.5;">
                        <?php if ($is_already_on_target): ?>
                            <?php printf(__('Zero breaking changes or unguarded compatibility issues detected against PHP %s. All multi-version fallbacks and shims are operating cleanly.', 'phpinfo-wp'), esc_html($result['target'])); ?>
                        <?php else: ?>
                            <?php printf(__('No deprecated or removed function calls were detected across your installed plugins and themes for PHP %s. Your site should continue working normally after upgrading your server.', 'phpinfo-wp'), esc_html($result['target'])); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php if ($is_already_on_target): ?>
                    <div class="notice notice-info inline" style="margin:0 0 24px; border-left-color: #2271b1; padding: 12px 16px;">
                        <p style="font-size: 14.5px; margin: 0 0 6px;"><strong>ℹ️ <?php printf(__('Active Server PHP: %s (Legacy / Historical Check)', 'phpinfo-wp'), esc_html(explode('-', $current_php)[0])); ?></strong></p>
                        <p style="margin: 0; color: #475569; font-size: 13px; line-height: 1.45;">
                            <?php printf(__('You scanned against PHP %s, which is lower than or equal to your current server PHP. Any flagged items below are legacy compatibility shims or dormant diagnostic branches. Because your site is running normally right now on PHP %s, these do not cause active runtime crashes.', 'phpinfo-wp'), esc_html($result['target']), esc_html(explode('-', $current_php)[0])); ?>
                        </p>
                    </div>
                <?php elseif ($total_removed > 0): ?>
                    <div class="notice notice-error inline" style="margin:0 0 24px; border-left-color: #d63638; padding: 12px 16px;">
                        <p style="font-size: 14.5px; margin: 0 0 6px;"><strong>🚨 <?php printf(__('Upgrade Delta Alert: %d Breaking Issue(s) on PHP %s', 'phpinfo-wp'), $total_removed, esc_html($result['target'])); ?></strong></p>
                        <p style="margin: 0; color: #7f1d1d; font-size: 13px; line-height: 1.45;">
                            <?php printf(__('Your plugins contain %d function call(s) that are completely removed in PHP %s. Upgrading to PHP %s before updating these plugins will trigger fatal errors.', 'phpinfo-wp'), $total_removed, esc_html($result['target']), esc_html($result['target'])); ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning inline" style="margin:0 0 24px; border-left-color: #dba617; padding: 12px 16px;">
                        <p style="font-size: 14.5px; margin: 0 0 6px;"><strong>⚠️ <?php printf(__('Upgrade Safe (Deprecation Notices): %d Notice(s) on PHP %s', 'phpinfo-wp'), $total_deprecated, esc_html($result['target'])); ?></strong></p>
                        <p style="margin: 0; color: #78350f; font-size: 13px; line-height: 1.45;">
                            <?php printf(__('Zero breaking removals detected. Your plugins will continue to run normally after upgrading to PHP %1$s. We detected %2$d function call(s) deprecated upstream in PHP %1$s. While they do not crash your site, they may output non-fatal notices into your debug log.', 'phpinfo-wp'), esc_html($result['target']), $total_deprecated); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php foreach ($issues as $owner => $list):
                    [$type, $name] = explode('/', $owner, 2);
                    $type_label = ['plugin' => 'Plugin', 'theme' => 'Theme', 'mu-plugin' => 'Must-Use'][$type] ?? $type;
                    $type_color = ['plugin' => '#777BB3', 'theme' => '#2271b1', 'mu-plugin' => '#996800'][$type] ?? '#666';
                    
                    $active_items = array_filter($list, function ($i) { return empty($i['ignored']); });
                    if (empty($active_items)) continue;

                    $removed_count = count(array_filter($active_items, function ($i) {
                        return $i['severity'] === 'removed';
                    }));
                    $deprecated_count = count($active_items) - $removed_count;
                ?>
                    <details class="phpinfowp-compat-owner">
                        <summary>
                            <span class="phpinfowp-compat-owner-type" style="background:<?php echo $type_color; ?>"><?php echo esc_html($type_label); ?></span>
                            <strong class="phpinfowp-compat-owner-name"><?php echo esc_html($name); ?></strong>
                            <span class="phpinfowp-compat-owner-counts">
                                <?php if ($removed_count): ?>
                                    <span style="color:#d63638"><strong><?php echo $removed_count; ?></strong> <?php _e('removed', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                                <?php if ($deprecated_count): ?>
                                    <?php if ($removed_count) echo '&middot;'; ?>
                                    <span style="color:#dba617"><strong><?php echo $deprecated_count; ?></strong> <?php _e('deprecated', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                            </span>
                        </summary>
                        <div class="phpinfowp-compat-issues">
                            <?php foreach ($list as $issue): 
                                if (!empty($issue['ignored'])) continue;
                                $hash = $issue['hash'] ?? md5($issue['file'] . ':' . $issue['line'] . ':' . $issue['name']);
                            ?>
                                <div class="phpinfowp-compat-issue" id="issue-<?php echo esc_attr($hash); ?>" style="border-left-color:<?php echo $sev_color[$issue['severity']]; ?>">
                                    <div class="phpinfowp-compat-issue-head" style="display:flex; justify-content:space-between; align-items:center;">
                                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                            <span class="phpinfowp-compat-sev" style="background:<?php echo $sev_color[$issue['severity']]; ?>">
                                                <?php echo $sev_label[$issue['severity']]; ?>
                                            </span>
                                            <code class="phpinfowp-compat-name"><?php echo esc_html($issue['name']); ?></code>
                                            <span class="phpinfowp-compat-where"><?php echo esc_html($issue['file']); ?>:<?php echo (int)$issue['line']; ?></span>
                                        </div>
                                        <button type="button" class="button button-small phpinfowp-dismiss-issue-btn" data-hash="<?php echo esc_attr($hash); ?>" style="font-size:11px; height:24px; line-height:22px; color:#64748b;">
                                            <span class="dashicons dashicons-yes" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span> <?php _e('Mark as Safe', 'phpinfo-wp'); ?>
                                        </button>
                                    </div>
                                    <div class="phpinfowp-compat-issue-meta">
                                        <?php if ($issue['severity'] === 'removed'): ?>
                                            <?php printf(__('Removed in PHP %s', 'phpinfo-wp'), esc_html($issue['out'])); ?> &middot;
                                        <?php else: ?>
                                            <?php printf(__('Deprecated in PHP %s', 'phpinfo-wp'), esc_html($issue['in'])); ?> &middot;
                                        <?php endif; ?>
                                        <span class="phpinfowp-compat-fix"><?php echo esc_html($issue['fix']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>

            <?php endif; ?>

        <?php endif; ?>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('phpinfowp-compat-form');
    var scanBtn = document.getElementById('phpinfowp-compat-scan-btn');
    var clearBtn = document.getElementById('phpinfowp-compat-clear-btn');
    var targetSelect = document.getElementById('phpinfowp-compat-target');
    var resultArea = document.getElementById('phpinfowp-compat-result-area');
    var nonce = '<?php echo wp_create_nonce("phpinfowp_compat_nonce"); ?>';
    var bgNonce = '<?php echo esc_js($bg_nonce); ?>';
    var isRunning = <?php echo $is_bg_running ? 'true' : 'false'; ?>;
    var pollTimer = null;

    function ackBgNotice() {
        if (!bgNonce) return;
        var fd = new FormData();
        fd.append('action', 'phpinfowp_bg_scan_dismiss_notice');
        fd.append('module', 'compat');
        fd.append('nonce', bgNonce);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(ajaxurl, fd);
        } else {
            fetch(ajaxurl, { method: 'POST', body: fd }).catch(function(){});
        }
    }

    function pollStatus(targetVal) {
        if (pollTimer) return;
        pollTimer = setInterval(function() {
            var fd = new FormData();
            fd.append('action', 'phpinfowp_bg_scan_status');
            fd.append('module', 'compat');
            fd.append('nonce', bgNonce);

            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res && res.success && res.data) {
                        if (res.data.status === 'complete' || res.data.status === 'idle') {
                            clearInterval(pollTimer);
                            ackBgNotice();
                            var url = new URL(window.location.href);
                            if (targetVal) url.searchParams.set('target', targetVal);
                            window.location.href = url.toString();
                        }
                    }
                })
                .catch(function() {});
        }, 2500);
    }

    if (isRunning) {
        if (window.phpinfowpShowScanBanner) {
            window.phpinfowpShowScanBanner('<?php echo esc_js(__('Analyzing Codebase against PHP', 'phpinfo-wp')); ?> <?php echo esc_js($target); ?>...', '<?php echo esc_js(__('You can leave anytime — scan continues on server', 'phpinfo-wp')); ?>');
        }
        pollStatus('<?php echo esc_js($target); ?>');
    }

    if (scanBtn && form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var targetVal = targetSelect ? targetSelect.value : '8.4';

            scanBtn.disabled = true;
            if (clearBtn) clearBtn.disabled = true;
            if (targetSelect) targetSelect.disabled = true;
            scanBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Scanning in Background...', 'phpinfo-wp')); ?>';

            if (resultArea) {
                resultArea.innerHTML = '<div class="phpinfowp-bg-scan-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:36px 28px; text-align:center; margin-top:20px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">' +
                    '<div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">' +
                    '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>' +
                    '</div>' +
                    '<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php echo esc_js(__('Analyzing Codebase against PHP', 'phpinfo-wp')); ?> ' + targetVal + '...</h3>' +
                    '<p style="margin:0 0 16px; font-size:13.5px; color:#64748b; max-width:540px; margin-left:auto; margin-right:auto; line-height:1.5;"><?php echo esc_js(__('Running Delta analysis across all active plugins and themes.', 'phpinfo-wp')); ?></p>' +
                    '<div style="display:inline-flex; align-items:center; gap:8px; background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:8px 18px; border-radius:999px; font-size:12.5px; font-weight:600;">' +
                    '<span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>' +
                    '<span><?php echo esc_js(__('You can safely leave this page or close your browser anytime', 'phpinfo-wp')); ?></span>' +
                    '</div>' +
                    '<p style="margin:12px 0 0; font-size:12px; color:#94a3b8;"><?php echo esc_js(__('The scan executes continuously in the background on your server. Results will load here once complete.', 'phpinfo-wp')); ?></p>' +
                    '</div>';
            }

            if (window.phpinfowpShowScanBanner) {
                window.phpinfowpShowScanBanner('<?php echo esc_js(__('Analyzing Codebase against PHP', 'phpinfo-wp')); ?> ' + targetVal + '...', '<?php echo esc_js(__('You can leave anytime — scan continues on server', 'phpinfo-wp')); ?>');
            }

            pollStatus(targetVal);

            var data = new FormData();
            data.append('action', 'phpinfowp_compat_scan');
            data.append('nonce', nonce);
            data.append('target', targetVal);

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            }).then(function(res) {
                return res.json();
            }).then(function(res) {
                if (pollTimer) clearInterval(pollTimer);
                ackBgNotice();
                if (res.success) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('target', targetVal);
                    window.location.href = url.toString();
                } else {
                    if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                    alert(res.data && res.data.message ? res.data.message : 'Scan failed.');
                    window.location.reload();
                }
            }).catch(function(err) {
                // Network error or aborted socket: Keep poller running because server scan continues via ignore_user_abort(true)!
            });
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.phpinfowpConfirm({
                title: '<?php echo esc_js(__('Clear Compatibility Results', 'phpinfo-wp')); ?>',
                message: '<?php echo esc_js(__('Are you sure you want to clear the PHP compatibility scan results? You can run a new scan at any time.', 'phpinfo-wp')); ?>',
                confirmText: '<?php echo esc_js(__('Clear Results', 'phpinfo-wp')); ?>',
                isDanger: true
            }).then(function(confirmed) {
                if (!confirmed) return;
                clearBtn.disabled = true;
                if (scanBtn) scanBtn.disabled = true;
                clearBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Clearing...', 'phpinfo-wp')); ?>';

                var data = new FormData();
                data.append('action', 'phpinfowp_compat_clear');
                data.append('nonce', nonce);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                }).then(function(res) {
                    return res.json();
                }).then(function(res) {
                    window.location.reload();
                }).catch(function() {
                    window.location.reload();
                });
            });
        });
    }

    // Dismiss / Mark as Safe handler
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.phpinfowp-dismiss-issue-btn');
        if (!btn) return;

        e.preventDefault();
        var hash = btn.getAttribute('data-hash');
        if (!hash) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin"></span>';

        var data = new FormData();
        data.append('action', 'phpinfowp_compat_ignore');
        data.append('nonce', nonce);
        data.append('hash', hash);
        data.append('status', 'ignore');

        fetch(ajaxurl, {
            method: 'POST',
            body: data
        }).then(function(res) { return res.json(); })
        .then(function(res) {
            if (res.success) {
                var el = document.getElementById('issue-' + hash);
                if (el) {
                    el.style.transition = 'all 0.3s ease';
                    el.style.opacity = '0';
                    el.style.transform = 'scaleY(0)';
                    setTimeout(function() { el.remove(); }, 300);
                }
            } else {
                btn.disabled = false;
                btn.textContent = 'Retry';
            }
        }).catch(function() {
            btn.disabled = false;
            btn.textContent = 'Retry';
        });
    });
});
</script>
