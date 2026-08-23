<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

// Update Guard — Unified Pre-Update & Post-Update Health Suite
// 1. Plugins & Themes Update Guard: Pre-update compatibility checks (PHP/WP floor, changelog risk, abandonment)
// 2. Core Readiness Audit: Code scan against target WordPress core
// 3. Update History & Health: Automated post-update diagnostics (loopback, error log, cron) + stability scoring

$is_pro  = Phpinfo_WP_License::is_valid();
$ai_on   = Phpinfo_WP_AI_Explain::available();
$current = Phpinfo_WP_Update_Audit::current_wp();
$avail   = Phpinfo_WP_Update_Audit::available_core_update();
$pending_count = Phpinfo_WP_Update_Guard::pending_count();

// Active tab handling
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['plugins', 'core', 'history'], true)
    ? sanitize_key($_GET['tab'])
    : ($pending_count > 0 ? 'plugins' : 'core');

// Target WP version for core audit
$target = isset($_GET['target']) && preg_match('/^\d+\.\d+(\.\d+)?$/', (string) $_GET['target'])
    ? sanitize_text_field($_GET['target'])
    : Phpinfo_WP_Update_Audit::default_target();

// --- Handle Form Actions ---

// Core scan / clear
if (isset($_POST['phpinfowp_ua_scan']) && check_admin_referer('phpinfowp_ua_nonce')) {
    $tab = 'core';
    $target = preg_match('/^\d+\.\d+(\.\d+)?$/', (string) ($_POST['target'] ?? ''))
        ? sanitize_text_field($_POST['target']) : $target;
    Phpinfo_WP_Update_Audit::scan($target);
}
if (isset($_POST['phpinfowp_ua_clear']) && check_admin_referer('phpinfowp_ua_nonce')) {
    $tab = 'core';
    Phpinfo_WP_Update_Audit::clear();
}

// Plugin / Theme Guard scan / clear
if (isset($_POST['phpinfowp_ug_scan']) && check_admin_referer('phpinfowp_ug_nonce')) {
    $tab = 'plugins';
    Phpinfo_WP_Update_Guard::scan();
}
if (isset($_POST['phpinfowp_ug_clear']) && check_admin_referer('phpinfowp_ug_nonce')) {
    $tab = 'plugins';
    Phpinfo_WP_Update_Guard::clear();
}

// Update History clear / health check now
$live_health = null;
if (isset($_POST['phpinfowp_uh_health_check']) && check_admin_referer('phpinfowp_uh_nonce')) {
    $tab = 'history';
    $live_health = Phpinfo_WP_Update_History::run_live_health_check();
}
if (isset($_POST['phpinfowp_uh_clear']) && check_admin_referer('phpinfowp_uh_nonce')) {
    $tab = 'history';
    Phpinfo_WP_Update_History::clear();
}

$core_result   = Phpinfo_WP_Update_Audit::get_result();
$guard_result  = Phpinfo_WP_Update_Guard::get_result();
$history_items = Phpinfo_WP_Update_History::recent(50);

$verdict_meta = [
    'safe'    => ['label' => __('Safe to update', 'phpinfo-wp'),       'grade' => 'grade-a', 'icon' => '✓', 'color' => '#00a32a'],
    'caution' => ['label' => __('Update with caution', 'phpinfo-wp'),   'grade' => 'grade-c', 'icon' => '!', 'color' => '#dba617'],
    'risky'   => ['label' => __('Risky — review first', 'phpinfo-wp'), 'grade' => 'grade-f', 'icon' => '✕', 'color' => '#d63638'],
];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Update Guard <span style="font-size:13px;font-weight:500;color:#888;vertical-align:middle"><?php _e('Pre & Post Update Security Suite', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle">
                <?php _e('Eliminate staging site complexity. Audit pending plugin/theme updates, test WordPress core jumps for breakages, and auto-verify site health after every update.', 'phpinfo-wp'); ?>
                Currently running WordPress <strong><?php echo esc_html($current); ?></strong><?php echo $avail ? ' · <span style="color:#2271b1"><strong>' . esc_html($avail) . ' available</strong></span>' : ' · up to date'; ?>.
            </p>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="phpinfowp-ug-tab-nav">
        <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-update-audit', 'tab' => 'plugins'], admin_url('admin.php'))); ?>"
           class="phpinfowp-ug-tab-link <?php echo $tab === 'plugins' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php _e('Plugins & Themes Guard', 'phpinfo-wp'); ?>
            <?php if ($pending_count > 0): ?>
                <span class="phpinfowp-ug-tab-count"><?php echo (int) $pending_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-update-audit', 'tab' => 'core'], admin_url('admin.php'))); ?>"
           class="phpinfowp-ug-tab-link <?php echo $tab === 'core' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-shield"></span>
            <?php _e('WordPress Core Audit', 'phpinfo-wp'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-update-audit', 'tab' => 'history'], admin_url('admin.php'))); ?>"
           class="phpinfowp-ug-tab-link <?php echo $tab === 'history' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-backup"></span>
            <?php _e('Update History & Health', 'phpinfo-wp'); ?>
        </a>
    </div>

    <?php if (!$is_pro): ?>
        <div style="background:linear-gradient(135deg,#f3f7ff,#eaf4ff);border:1px solid #c8d8f5;border-radius:8px;padding:12px 16px;margin:0 0 18px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
            <span style="font-size:13px;color:#1a3a72">
                <strong><?php _e('Free:', 'phpinfo-wp'); ?></strong> PHP &amp; WP floor checks, major version jump warnings, core deprecation scan, and 30-entry update log.
                <strong><?php _e('Pro', 'phpinfo-wp'); ?></strong> adds changelog risk keyword analysis, WP.org abandonment scoring, cross-dependency validation, 200-entry history with stability tracking, and AI fix explanations.
            </span>
            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="button button-primary" style="margin-left:auto"><?php _e('Get Pro →', 'phpinfo-wp'); ?></a>
        </div>
    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- TAB 1: PLUGINS & THEMES UPDATE GUARD -->
    <!-- ===================================================================== -->
    <?php if ($tab === 'plugins'): ?>

        <div class="phpinfowp-ug-section-header">
            <div>
                <h2 style="margin:0 0 4px;font-size:16px;font-weight:600"><?php _e('Pending Updates Compatibility Scan', 'phpinfo-wp'); ?></h2>
                <p style="margin:0;font-size:13px;color:#666">
                    <?php printf(__('Scans %d pending plugin and theme update(s) before you apply them.', 'phpinfo-wp'), (int) $pending_count); ?>
                </p>
            </div>
            <form method="post" class="phpinfowp-compat-controls" style="margin:0;gap:8px">
                <?php wp_nonce_field('phpinfowp_ug_nonce'); ?>
                <button type="submit" name="phpinfowp_ug_scan" value="1" class="button button-primary">
                    <span class="dashicons dashicons-search" style="vertical-align:middle;margin-top:-2px"></span>
                    <?php _e('Scan Pending Updates', 'phpinfo-wp'); ?>
                </button>
                <?php if ($guard_result): ?>
                    <button type="submit" name="phpinfowp_ug_clear" value="1" class="button button-secondary"><?php _e('Clear', 'phpinfo-wp'); ?></button>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!$guard_result && $pending_count === 0): ?>
            <div class="phpinfowp-compat-empty" style="margin-top:20px">
                <span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span>
                <h3><?php _e('All plugins and themes are up to date!', 'phpinfo-wp'); ?></h3>
                <p><?php _e('No pending updates detected. When new updates arrive, return here to scan them for breaking changes before updating.', 'phpinfo-wp'); ?></p>
            </div>

        <?php elseif (!$guard_result): ?>
            <div class="phpinfowp-compat-empty" style="margin-top:20px">
                <span class="dashicons dashicons-shield-alt"></span>
                <h3><?php _e('Ready to scan pending updates', 'phpinfo-wp'); ?></h3>
                <p><?php printf(__('You have %d pending update(s). Click "Scan Pending Updates" to evaluate PHP/WP compatibility, changelog risks, and stability scores.', 'phpinfo-wp'), (int) $pending_count); ?></p>
            </div>

        <?php else:
            $gv = $guard_result['verdict'] ?? 'safe';
            $gvm = $verdict_meta[$gv] ?? $verdict_meta['safe'];
            $items = $guard_result['items'] ?? [];
        ?>
            <!-- Guard Verdict Card -->
            <div class="phpinfowp-score-card" style="margin:20px 0">
                <div class="phpinfowp-grade-circle <?php echo esc_attr($gvm['grade']); ?>"><?php echo esc_html($gvm['icon']); ?></div>
                <div class="phpinfowp-score-card-body">
                    <div class="phpinfowp-score-card-value">
                        <?php echo esc_html($gvm['label']); ?>
                        <span><?php printf(__('%d pending update(s) analyzed', 'phpinfo-wp'), (int) $guard_result['total']); ?></span>
                    </div>
                    <div class="phpinfowp-score-card-meta">
                        <strong style="color:#d63638"><?php echo (int) $guard_result['risky_count']; ?></strong> <?php _e('risky', 'phpinfo-wp'); ?>
                        &middot; <strong style="color:#dba617"><?php echo (int) $guard_result['caution_count']; ?></strong> <?php _e('caution', 'phpinfo-wp'); ?>
                        &middot; <strong style="color:#00a32a"><?php echo (int) $guard_result['safe_count']; ?></strong> <?php _e('safe', 'phpinfo-wp'); ?>
                        &middot; <?php echo esc_html($guard_result['duration']); ?>s
                        &middot; <?php echo esc_html(human_time_diff($guard_result['scanned_at']) . ' ago'); ?>
                    </div>
                </div>
            </div>

            <?php if (empty($items)): ?>
                <div class="notice notice-success inline"><p><?php _e('No pending plugin or theme updates found.', 'phpinfo-wp'); ?></p></div>
            <?php else: ?>
                <div class="phpinfowp-ug-item-list">
                    <?php foreach ($items as $item):
                        $iv = $item['verdict'];
                        $ivc = $verdict_meta[$iv]['color'] ?? '#00a32a';
                        $stability = class_exists('Phpinfo_WP_Update_History') ? Phpinfo_WP_Update_History::stability_score($item['file'] ?? $item['slug']) : 100;
                    ?>
                        <div class="phpinfowp-ug-item-card" style="border-left-color:<?php echo esc_attr($ivc); ?>">
                            <div class="phpinfowp-ug-item-header">
                                <div class="phpinfowp-ug-item-title-row">
                                    <span class="phpinfowp-compat-owner-type" style="background:<?php echo $item['type'] === 'plugin' ? '#777BB3' : '#2271b1'; ?>">
                                        <?php echo esc_html(ucfirst($item['type'])); ?>
                                    </span>
                                    <strong class="phpinfowp-ug-item-name"><?php echo esc_html($item['name']); ?></strong>
                                    <span class="phpinfowp-ug-ver-pill">
                                        <?php echo esc_html($item['from'] ?: '?'); ?> &rarr; <strong><?php echo esc_html($item['to']); ?></strong>
                                    </span>
                                </div>
                                <div class="phpinfowp-ug-item-badges">
                                    <?php if ($stability !== 50): ?>
                                        <span class="phpinfowp-ug-stability-pill <?php echo $stability >= 80 ? 'is-good' : 'is-warn'; ?>" title="<?php _e('Calculated from past update health checks on this site', 'phpinfo-wp'); ?>">
                                            <?php printf(__('Stability: %d%%', 'phpinfo-wp'), (int) $stability); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="phpinfowp-compat-sev" style="background:<?php echo esc_attr($ivc); ?>">
                                        <?php echo esc_html(strtoupper($iv)); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="phpinfowp-ug-signals">
                                <?php if (empty($item['signals'])): ?>
                                    <div class="phpinfowp-ug-signal is-safe">
                                        <span class="dashicons dashicons-yes" style="font-size:15px;color:#00a32a;vertical-align:text-bottom"></span>
                                        <?php _e('No breaking changes or requirement conflicts detected.', 'phpinfo-wp'); ?>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($item['signals'] as $sig):
                                        $sc = ($sig['severity'] === 'risky') ? '#d63638' : (($sig['severity'] === 'caution') ? '#dba617' : '#2271b1');
                                        $sicon = ($sig['severity'] === 'risky') ? 'dashicons-dismiss' : 'dashicons-warning';
                                    ?>
                                        <div class="phpinfowp-ug-signal" style="border-left:3px solid <?php echo esc_attr($sc); ?>">
                                            <span class="dashicons <?php echo esc_attr($sicon); ?>" style="color:<?php echo esc_attr($sc); ?>;font-size:15px;vertical-align:text-bottom"></span>
                                            <span><?php echo esc_html($sig['label']); ?></span>
                                            <?php if ($is_pro && $ai_on): ?>
                                                <button type="button" class="button-link phpinfowp-ai-explain" style="margin-left:auto;font-size:11px"
                                                        data-ai-topic="update_break"
                                                        data-ai-context="<?php echo esc_attr($item['name'] . ': ' . $sig['label']); ?>"
                                                        data-ai-value="<?php echo esc_attr($item['to']); ?>">
                                                    <?php _e('Explain with AI', 'phpinfo-wp'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- TAB 2: WORDPRESS CORE AUDIT -->
    <!-- ===================================================================== -->
    <?php elseif ($tab === 'core'): ?>

        <div class="phpinfowp-ug-section-header">
            <div>
                <h2 style="margin:0 0 4px;font-size:16px;font-weight:600"><?php _e('WordPress Core Readiness Audit', 'phpinfo-wp'); ?></h2>
                <p style="margin:0;font-size:13px;color:#666">
                    <?php _e('Static code scan for deprecated WP core APIs, removed jQuery methods, and plugin compatibility gaps.', 'phpinfo-wp'); ?>
                    <?php $rs = Phpinfo_WP_Update_Audit::ruleset_source(); ?>
                    &middot; Ruleset: <strong><?php echo esc_html($rs['source']); ?></strong><?php echo $rs['version'] ? ' (v' . esc_html($rs['version']) . ')' : ''; ?>
                </p>
            </div>
            <form method="post" class="phpinfowp-compat-controls" style="margin:0">
                <?php wp_nonce_field('phpinfowp_ua_nonce'); ?>
                <label style="display:flex;align-items:center;gap:6px">
                    <span style="font-size:13px;font-weight:600"><?php _e('Target Core:', 'phpinfo-wp'); ?></span>
                    <input type="text" name="target" value="<?php echo esc_attr($target); ?>" size="6"
                           pattern="\d+\.\d+(\.\d+)?" style="width:70px;text-align:center" />
                </label>
                <button type="submit" name="phpinfowp_ua_scan" value="1" class="button button-primary">
                    <span class="dashicons dashicons-shield" style="vertical-align:middle;margin-top:-2px"></span>
                    <?php _e('Run Core Audit', 'phpinfo-wp'); ?>
                </button>
                <?php if ($core_result): ?>
                    <button type="submit" name="phpinfowp_ua_clear" value="1" class="button button-secondary"><?php _e('Clear', 'phpinfo-wp'); ?></button>
                <?php endif; ?>
            </form>
        </div>

        <?php if (isset($core_result['error'])): ?>
            <div class="notice notice-error inline"><p><?php echo esc_html($core_result['error']); ?></p></div>

        <?php elseif (!$core_result): ?>
            <div class="phpinfowp-compat-empty" style="margin-top:20px">
                <span class="dashicons dashicons-shield"></span>
                <h3><?php _e('No core audit yet', 'phpinfo-wp'); ?></h3>
                <p><?php _e('Set the target WordPress version and click "Run Core Audit".', 'phpinfo-wp'); ?></p>
                <p style="font-size:12px;color:#888"><?php _e('Scans plugin & theme code on your server. Takes 10–60 seconds.', 'phpinfo-wp'); ?></p>
            </div>

        <?php else:
            $cv      = $core_result['verdict'];
            $cvm     = $verdict_meta[$cv] ?? $verdict_meta['caution'];
            $owners  = $core_result['owners'] ?? [];
            $rank    = ['risky' => 0, 'caution' => 1, 'safe' => 2];
            uasort($owners, function ($a, $b) use ($rank) {
                $r = ($rank[$a['verdict']] ?? 3) <=> ($rank[$b['verdict']] ?? 3);
                return $r !== 0 ? $r : ($b['breaks'] + $b['depr']) <=> ($a['breaks'] + $a['depr']);
            });
        ?>
            <!-- Verdict card -->
            <div class="phpinfowp-score-card" style="margin:20px 0">
                <div class="phpinfowp-grade-circle <?php echo esc_attr($cvm['grade']); ?>"><?php echo esc_html($cvm['icon']); ?></div>
                <div class="phpinfowp-score-card-body">
                    <div class="phpinfowp-score-card-value">
                        <?php echo esc_html($cvm['label']); ?>
                        <span><?php echo version_compare($core_result['target'], $current, '>') ? 'updating to WordPress ' . esc_html($core_result['target']) : 'for WordPress ' . esc_html($core_result['target']); ?></span>
                    </div>
                    <div class="phpinfowp-score-card-meta">
                        <strong style="color:#d63638"><?php echo (int) $core_result['total_breaks']; ?></strong> <?php _e('hard break(s)', 'phpinfo-wp'); ?>
                        &middot; <strong style="color:#dba617"><?php echo (int) $core_result['total_depr']; ?></strong> <?php _e('deprecation(s)', 'phpinfo-wp'); ?>
                        &middot; <strong><?php echo (int) $core_result['with_issues']; ?></strong> of <?php echo (int) $core_result['owner_count']; ?> <?php _e('flagged', 'phpinfo-wp'); ?>
                        &middot; <?php echo number_format($core_result['files']); ?> <?php _e('files in', 'phpinfo-wp'); ?> <?php echo esc_html($core_result['duration']); ?>s
                        &middot; <?php echo esc_html(human_time_diff($core_result['scanned_at']) . ' ago'); ?>
                    </div>
                </div>
            </div>

            <?php if ($core_result['truncated']): ?>
                <div class="notice notice-warning inline" style="margin:0 0 16px"><p>
                    <?php printf(__('Scan hit the %d-file limit. Some files were skipped.', 'phpinfo-wp'), (int) $core_result['max_files']); ?>
                    <?php if (!$is_pro): ?><a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank"><?php _e('Pro raises cap to 8,000 files.', 'phpinfo-wp'); ?></a><?php endif; ?>
                </p></div>
            <?php endif; ?>

            <?php
            $jqm = $core_result['jquery_migrate'] ?? 'unknown';
            if ($jqm === 'present'): ?>
                <div class="notice notice-info inline" style="margin:0 0 16px"><p>
                    <strong><?php _e('jQuery Migrate is loaded on this site', 'phpinfo-wp'); ?></strong> — <?php _e('Removed jQuery APIs are downgraded to deprecation warnings rather than breaking.', 'phpinfo-wp'); ?>
                </p></div>
            <?php elseif ($jqm === 'absent'): ?>
                <div class="notice notice-error inline" style="margin:0 0 16px"><p>
                    <strong><?php _e('jQuery Migrate is not loaded', 'phpinfo-wp'); ?></strong> — <?php _e('Calls to removed jQuery APIs will fail on WordPress 5.7+.', 'phpinfo-wp'); ?>
                </p></div>
            <?php endif; ?>

            <?php if (empty($owners)): ?>
                <div class="notice notice-success inline" style="margin:0"><p><?php _e('No plugins or themes detected in the scan roots.', 'phpinfo-wp'); ?></p></div>
            <?php else: ?>
                <?php foreach ($owners as $owner => $o):
                    [$type, $name] = array_pad(explode('/', $owner, 2), 2, $owner);
                    $type_label = ['plugin' => 'Plugin', 'theme' => 'Theme', 'mu-plugin' => 'Must-Use'][$type] ?? $type;
                    $type_color = ['plugin' => '#777BB3', 'theme' => '#2271b1', 'mu-plugin' => '#996800'][$type] ?? '#666';
                    $ov  = $o['verdict'];
                    $ovc = ['risky' => '#d63638', 'caution' => '#dba617', 'safe' => '#00a32a'][$ov];
                    $meta = $o['meta'] ?? null;
                ?>
                    <details class="phpinfowp-compat-owner" <?php echo $ov === 'risky' ? 'open' : ''; ?>>
                        <summary>
                            <span class="phpinfowp-compat-owner-type" style="background:<?php echo esc_attr($type_color); ?>"><?php echo esc_html($type_label); ?></span>
                            <strong class="phpinfowp-compat-owner-name"><?php echo esc_html($name); ?></strong>
                            <span class="phpinfowp-ua-verdict-dot" style="background:<?php echo esc_attr($ovc); ?>"></span>
                            <span class="phpinfowp-compat-owner-counts">
                                <?php if ($o['breaks']): ?><span style="color:#d63638"><strong><?php echo (int) $o['breaks']; ?></strong> breaks</span><?php endif; ?>
                                <?php if ($o['depr']): ?><?php if ($o['breaks']) echo '&middot;'; ?><span style="color:#dba617"><strong><?php echo (int) $o['depr']; ?></strong> deprecated</span><?php endif; ?>
                                <?php if (!$o['breaks'] && !$o['depr'] && $ov !== 'safe'): ?><span style="color:#dba617"><?php _e('metadata risk', 'phpinfo-wp'); ?></span><?php endif; ?>
                            </span>
                        </summary>
                        <div class="phpinfowp-compat-issues">
                            <?php if ($meta): ?>
                                <div class="phpinfowp-ua-meta">
                                    <?php if (!empty($meta['in_directory'])): ?>
                                        <span class="phpinfowp-ua-meta-pill">Tested up to <strong><?php echo esc_html($meta['tested'] ?: '—'); ?></strong></span>
                                        <?php if ($meta['stale_days'] !== null): ?>
                                            <span class="phpinfowp-ua-meta-pill <?php echo $meta['stale_days'] > 730 ? 'is-bad' : ''; ?>">
                                                Last updated <strong><?php echo (int) round($meta['stale_days'] / 30); ?> mo</strong> ago
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($meta['abandoned'])): ?><span class="phpinfowp-ua-meta-pill is-bad">⚠ Looks abandoned</span><?php endif; ?>
                                    <?php else: ?>
                                        <span class="phpinfowp-ua-meta-pill"><?php _e('Not on WordPress.org (premium/custom)', 'phpinfo-wp'); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($meta['php_blocks'])): ?>
                                        <span class="phpinfowp-ua-meta-pill is-bad">Requires PHP <?php echo esc_html($meta['requires_php']); ?> (you run <?php echo esc_html(PHP_VERSION); ?>)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php
                            $issues = $o['issues'] ?? [];
                            $shown = (!$is_pro && count($issues) > 0) ? array_slice($issues, 0, 3) : $issues;
                            foreach ($shown as $issue):
                                $sc = $issue['severity'] === 'breaks' ? '#d63638' : '#dba617';
                                $sl = $issue['severity'] === 'breaks' ? 'BREAKS' : 'DEPRECATED';
                            ?>
                                <div class="phpinfowp-compat-issue" style="border-left-color:<?php echo $sc; ?>">
                                    <div class="phpinfowp-compat-issue-head">
                                        <span class="phpinfowp-compat-sev" style="background:<?php echo $sc; ?>"><?php echo $sl; ?></span>
                                        <code class="phpinfowp-compat-name"><?php echo esc_html($issue['name']); ?></code>
                                        <span class="phpinfowp-ua-kind"><?php echo $issue['kind'] === 'js' ? 'JS' : 'PHP'; ?></span>
                                        <?php if ($is_pro): ?>
                                            <span class="phpinfowp-compat-where"><?php echo esc_html($issue['file']); ?>:<?php echo (int) $issue['line']; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($issue['new_now'])): ?><span class="phpinfowp-ua-new"><?php _e('new on this update', 'phpinfo-wp'); ?></span><?php endif; ?>
                                    </div>
                                    <div class="phpinfowp-compat-issue-meta">
                                        <?php echo $issue['severity'] === 'breaks' ? 'Removed from jQuery as of WordPress' : 'Deprecated since WordPress'; ?>
                                        <?php echo esc_html($issue['since']); ?> &middot;
                                        <span class="phpinfowp-compat-fix"><?php echo esc_html($issue['fix']); ?></span>
                                        <?php if ($is_pro && $ai_on): ?>
                                            <button type="button" class="button-link phpinfowp-ai-explain"
                                                    data-ai-topic="update_break"
                                                    data-ai-context="<?php echo esc_attr($issue['name']); ?>"
                                                    data-ai-value="<?php echo esc_attr($core_result['target']); ?>">Explain with AI</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (!$is_pro && count($issues) > 3): ?>
                                <div class="phpinfowp-compat-issue" style="border-left-color:#c8d8f5;background:#f7faff">
                                    <div class="phpinfowp-compat-issue-meta">
                                        + <?php echo count($issues) - 3; ?> more finding<?php echo (count($issues) - 3) !== 1 ? 's' : ''; ?> —
                                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank"><?php _e('unlock full drill-down with Pro →', 'phpinfo-wp'); ?></a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- TAB 3: UPDATE HISTORY & HEALTH -->
    <!-- ===================================================================== -->
    <?php elseif ($tab === 'history'): ?>

        <div class="phpinfowp-ug-section-header">
            <div>
                <h2 style="margin:0 0 4px;font-size:16px;font-weight:600"><?php _e('Update History & Post-Update Health Checks', 'phpinfo-wp'); ?></h2>
                <p style="margin:0;font-size:13px;color:#666">
                    <?php _e('Automatic health checks execute 60 seconds after every plugin, theme, and core update to verify site stability.', 'phpinfo-wp'); ?>
                </p>
            </div>
            <form method="post" class="phpinfowp-compat-controls" style="margin:0;gap:8px">
                <?php wp_nonce_field('phpinfowp_uh_nonce'); ?>
                <button type="submit" name="phpinfowp_uh_health_check" value="1" class="button button-primary">
                    <span class="dashicons dashicons-heart" style="vertical-align:middle;margin-top:-2px"></span>
                    <?php _e('Run Live Health Check', 'phpinfo-wp'); ?>
                </button>
                <?php if (!empty($history_items)): ?>
                    <button type="submit" name="phpinfowp_uh_clear" value="1" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Clear all update history records?', 'phpinfo-wp'); ?>');">
                        <?php _e('Clear History', 'phpinfo-wp'); ?>
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($live_health): ?>
            <div class="notice notice-info inline" style="margin:16px 0;padding:12px 16px">
                <h3 style="margin:0 0 8px;font-size:14px"><?php _e('Live Diagnostic Health Results:', 'phpinfo-wp'); ?></h3>
                <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:13px">
                    <span>
                        HTTP Loopback:
                        <strong style="color:<?php echo $live_health['loopback'] ? '#00a32a' : '#d63638'; ?>">
                            <?php echo $live_health['loopback'] ? '✓ HTTP 200' : '✕ Failed'; ?>
                        </strong>
                    </span>
                    <span>
                        Admin Dashboard:
                        <strong style="color:<?php echo $live_health['admin_ok'] ? '#00a32a' : '#d63638'; ?>">
                            <?php echo $live_health['admin_ok'] ? '✓ Reachable' : '✕ Unreachable'; ?>
                        </strong>
                    </span>
                    <span>
                        WP-Cron Integrity:
                        <strong style="color:<?php echo $live_health['cron_ok'] ? '#00a32a' : '#dba617'; ?>">
                            <?php echo $live_health['cron_ok'] ? '✓ Scheduled Events Active' : '⚠ No Events'; ?>
                        </strong>
                    </span>
                    <span>
                        PHP Error Log:
                        <strong style="color:#2271b1">
                            <?php echo $live_health['error_log'] ? esc_html(number_format($live_health['error_lines']) . ' lines') : 'Not found'; ?>
                        </strong>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($history_items)): ?>
            <div class="phpinfowp-compat-empty" style="margin-top:20px">
                <span class="dashicons dashicons-backup"></span>
                <h3><?php _e('No update history recorded yet', 'phpinfo-wp'); ?></h3>
                <p><?php _e('Update Guard automatically captures updates when you upgrade plugins, themes, or WordPress core and tests site health 60s later.', 'phpinfo-wp'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:16px;border-radius:6px;overflow:hidden">
                <thead>
                    <tr>
                        <th style="width:140px"><?php _e('Date & Time', 'phpinfo-wp'); ?></th>
                        <th style="width:100px"><?php _e('Type', 'phpinfo-wp'); ?></th>
                        <th><?php _e('Component / Upgrade', 'phpinfo-wp'); ?></th>
                        <th style="width:140px"><?php _e('Post-Health', 'phpinfo-wp'); ?></th>
                        <th style="width:120px"><?php _e('Stability Rating', 'phpinfo-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history_items as $h):
                        $health = $h['health'] ?? null;
                        $h_status = $health ? ($health['status'] ?? 'ok') : 'pending';
                        $h_color  = ['ok' => '#00a32a', 'issues' => '#d63638', 'pending' => '#dba617'][$h_status];
                        $h_label  = ['ok' => 'HEALTHY', 'issues' => 'ISSUES', 'pending' => 'PENDING'][$h_status];
                        $stability = Phpinfo_WP_Update_History::stability_score($h['slug'] ?? '');
                    ?>
                        <tr>
                            <td style="font-size:12px;color:#666">
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $h['at'] ?? time())); ?>
                            </td>
                            <td>
                                <span class="phpinfowp-compat-owner-type" style="background:<?php echo ($h['type'] ?? '') === 'plugin' ? '#777BB3' : (($h['type'] ?? '') === 'theme' ? '#2271b1' : '#1d2327'); ?>">
                                    <?php echo esc_html(ucfirst($h['type'] ?? '')); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo esc_html($h['name'] ?? $h['slug'] ?? 'Unknown'); ?></strong>
                                <?php if (!empty($h['to'])): ?>
                                    <span class="phpinfowp-ug-ver-pill" style="margin-left:6px">
                                        &rarr; <?php echo esc_html($h['to']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($health && !empty($health['problems'])): ?>
                                    <div style="font-size:11px;color:#d63638;margin-top:4px">
                                        ⚠ <?php echo esc_html(implode(', ', $health['problems'])); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="phpinfowp-compat-sev" style="background:<?php echo esc_attr($h_color); ?>">
                                    <?php echo esc_html($h_label); ?>
                                </span>
                                <?php if ($health && isset($health['new_errors']) && $health['new_errors'] > 0): ?>
                                    <span style="font-size:11px;color:#d63638;display:block;margin-top:2px">
                                        +<?php echo (int) $health['new_errors']; ?> err
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="phpinfowp-ug-stability-pill <?php echo $stability >= 80 ? 'is-good' : 'is-warn'; ?>">
                                    <?php echo (int) $stability; ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php endif; ?>

</div>
