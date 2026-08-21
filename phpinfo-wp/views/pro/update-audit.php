<?php
defined('ABSPATH') or die('Unauthorized Access');

// Update Guard — pre-update WordPress core readiness audit.
// FREE: the code-scan engine + an overall Safe/Caution/Risky verdict.
// PRO:  WP.org metadata risk (tested-up-to gap, abandonment), per-item AI
//       explanations, automatic interception on the core update screen, and
//       uncapped scanning with full per-file/line drill-down.
$is_pro  = Phpinfo_WP_License::is_valid();
$ai_on   = Phpinfo_WP_AI_Explain::available();
$current = Phpinfo_WP_Update_Audit::current_wp();
$avail   = Phpinfo_WP_Update_Audit::available_core_update();

$target = isset($_GET['target']) && preg_match('/^\d+\.\d+(\.\d+)?$/', (string) $_GET['target'])
    ? sanitize_text_field($_GET['target'])
    : Phpinfo_WP_Update_Audit::default_target();

if (isset($_POST['phpinfowp_ua_scan']) && check_admin_referer('phpinfowp_ua_nonce')) {
    $target = preg_match('/^\d+\.\d+(\.\d+)?$/', (string) ($_POST['target'] ?? ''))
        ? sanitize_text_field($_POST['target']) : $target;
    Phpinfo_WP_Update_Audit::scan($target);
}
if (isset($_POST['phpinfowp_ua_clear']) && check_admin_referer('phpinfowp_ua_nonce')) {
    Phpinfo_WP_Update_Audit::clear();
}

$result = Phpinfo_WP_Update_Audit::get_result();

$verdict_meta = [
    'safe'    => ['label' => 'Safe to update', 'grade' => 'grade-a',     'icon' => '✓'],
    'caution' => ['label' => 'Update with caution', 'grade' => 'grade-c', 'icon' => '!'],
    'risky'   => ['label' => 'Risky — review first', 'grade' => 'grade-f', 'icon' => '✕'],
];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Update Guard <span style="font-size:13px;font-weight:500;color:#888;vertical-align:middle"><?php _e('Pre-update core audit', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle">
                Before you click <strong><?php _e('Update WordPress', 'phpinfo-wp'); ?></strong>, scan every plugin and theme for code that breaks on the new core —
                removed jQuery APIs, deprecated WordPress functions<?php echo $is_pro ? ', and untested/abandoned listings' : ''; ?>.
                Currently running WordPress <?php echo esc_html($current); ?><?php echo $avail ? ' · <strong>' . esc_html($avail) . ' available</strong>' : ' · up to date'; ?>.
            </p>
            <?php $rs = Phpinfo_WP_Update_Audit::ruleset_source(); ?>
            <p style="margin:4px 0 0;font-size:12px;color:#888">
                <?php if ($rs['source'] === 'cloud'): ?>
                    <span class="dashicons dashicons-cloud" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom;color:#2271b1"></span>
                    Ruleset: <strong><?php _e('cloud', 'phpinfo-wp'); ?></strong> (v<?php echo esc_html($rs['version'] ?: '?'); ?>, updated <?php echo $rs['at'] ? esc_html(human_time_diff($rs['at']) . ' ago') : 'just now'; ?>)
                <?php else: ?>
                    Ruleset: built-in<?php if ($is_pro): ?> — cloud rules will load on the next license check<?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (!$is_pro): ?>
        <div style="background:linear-gradient(135deg,#f3f7ff,#eaf4ff);border:1px solid #c8d8f5;border-radius:8px;padding:12px 16px;margin:0 0 18px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
            <span style="font-size:13px;color:#1a3a72">
                <strong><?php _e('Free:', 'phpinfo-wp'); ?></strong> code scan (up to <?php echo (int) Phpinfo_WP_Update_Audit::FREE_MAX_FILES; ?> files) + an overall verdict.
                <strong><?php _e('Pro', 'phpinfo-wp'); ?></strong> adds WP.org "tested up to" &amp; abandonment scoring, AI fix explanations, uncapped scans, and an automatic warning on the WordPress Updates screen before every core update.
            </span>
            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="button button-primary" style="margin-left:auto"><?php _e('Get Pro →', 'phpinfo-wp'); ?></a>
        </div>
    <?php endif; ?>

    <form method="post" class="phpinfowp-compat-controls">
        <?php wp_nonce_field('phpinfowp_ua_nonce'); ?>
        <label>
            <strong><?php _e('Audit against:', 'phpinfo-wp'); ?></strong>
            <input type="text" name="target" value="<?php echo esc_attr($target); ?>" size="8"
                   pattern="\d+\.\d+(\.\d+)?" style="width:80px" />
            <span class="description" style="font-weight:400"><?php _e('WordPress version', 'phpinfo-wp'); ?></span>
        </label>
        <button type="submit" name="phpinfowp_ua_scan" value="1" class="button button-primary">
            <span class="dashicons dashicons-shield"></span>Run pre-update audit
        </button>
        <?php if ($result): ?>
            <button type="submit" name="phpinfowp_ua_clear" value="1" class="button button-secondary"><?php _e('Clear', 'phpinfo-wp'); ?></button>
        <?php endif; ?>
    </form>

    <?php if (isset($result['error'])): ?>
        <div class="notice notice-error inline"><p><?php echo esc_html($result['error']); ?></p></div>

    <?php elseif (!$result): ?>
        <div class="phpinfowp-compat-empty">
            <span class="dashicons dashicons-shield"></span>
            <h3><?php _e('No audit yet', 'phpinfo-wp'); ?></h3>
            <p><?php _e('Set the WordPress version you plan to upgrade to and click "Run pre-update audit".', 'phpinfo-wp'); ?></p>
            <p style="font-size:12px;color:#888">Scans plugin/theme PHP &amp; JavaScript on your server. Takes 10–90 seconds. Nothing is sent anywhere<?php echo $is_pro ? ' except anonymous slug lookups to WordPress.org for "tested up to" data' : ''; ?>.</p>
        </div>

    <?php else:
        $v      = $result['verdict'];
        $vm     = $verdict_meta[$v] ?? $verdict_meta['caution'];
        $owners = $result['owners'] ?? [];
        // Risky first, then caution, then safe; within a tier, most breaks first.
        $rank = ['risky' => 0, 'caution' => 1, 'safe' => 2];
        uasort($owners, function ($a, $b) use ($rank) {
            $r = ($rank[$a['verdict']] ?? 3) <=> ($rank[$b['verdict']] ?? 3);
            return $r !== 0 ? $r : ($b['breaks'] + $b['depr']) <=> ($a['breaks'] + $a['depr']);
        });
    ?>

        <!-- Verdict card -->
        <div class="phpinfowp-score-card" style="margin-bottom:20px">
            <div class="phpinfowp-grade-circle <?php echo esc_attr($vm['grade']); ?>"><?php echo esc_html($vm['icon']); ?></div>
            <div class="phpinfowp-score-card-body">
                <div class="phpinfowp-score-card-value">
                    <?php echo esc_html($vm['label']); ?>
                    <span><?php echo version_compare($result['target'], $current, '>') ? 'updating to WordPress ' . esc_html($result['target']) : 'for WordPress ' . esc_html($result['target']); ?></span>
                </div>
                <div class="phpinfowp-score-card-meta">
                    <strong><?php echo (int) $result['total_breaks']; ?></strong> hard break<?php echo $result['total_breaks'] !== 1 ? 's' : ''; ?>
                    &middot; <strong><?php echo (int) $result['total_depr']; ?></strong> deprecation<?php echo $result['total_depr'] !== 1 ? 's' : ''; ?>
                    &middot; <strong><?php echo (int) $result['with_issues']; ?></strong> of <?php echo (int) $result['owner_count']; ?> plugins/themes flagged
                    &middot; <?php echo number_format($result['files']); ?> files in <?php echo esc_html($result['duration']); ?>s
                    &middot; <?php echo esc_html(human_time_diff($result['scanned_at']) . ' ago'); ?>
                </div>
            </div>
        </div>

        <?php if ($result['truncated']): ?>
            <div class="notice notice-warning inline" style="margin:0 0 16px"><p>
                Scan hit the <?php echo (int) $result['max_files']; ?>-file limit, so some files were skipped.
                <?php if (!$is_pro): ?><a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank">Pro raises the cap to <?php echo (int) Phpinfo_WP_Update_Audit::PRO_MAX_FILES; ?> files.</a><?php endif; ?>
            </p></div>
        <?php endif; ?>

        <?php if (!$is_pro): ?>
            <div class="notice notice-info inline" style="margin:0 0 16px"><p>
                This free verdict is based on code analysis only. <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank"><?php _e('Pro', 'phpinfo-wp'); ?></a> also checks each plugin/theme's "tested up to" version and abandonment status on WordPress.org — the biggest predictor of a quiet breakage.
            </p></div>
        <?php endif; ?>

        <?php
        $jqm = $result['jquery_migrate'] ?? 'unknown';
        if (!empty($result['total_breaks']) || $jqm !== 'unknown'):
            if ($jqm === 'present'): ?>
                <div class="notice notice-info inline" style="margin:0 0 16px"><p>
                    <strong><?php _e('jQuery Migrate is currently loaded on your site', 'phpinfo-wp'); ?></strong>, so the jQuery calls below still work for now — they're flagged as <em><?php _e('deprecated', 'phpinfo-wp'); ?></em>, not broken. They become hard breaks only if Migrate is removed (it's deprecated and shouldn't be relied on long-term).
                </p></div>
            <?php elseif ($jqm === 'absent'): ?>
                <div class="notice notice-error inline" style="margin:0 0 16px"><p>
                    <strong><?php _e('jQuery Migrate is not loaded on your site.', 'phpinfo-wp'); ?></strong> The jQuery calls below (marked <strong><?php _e('BREAKS', 'phpinfo-wp'); ?></strong>) will fail silently on the front end on WordPress 5.7+.
                </p></div>
            <?php endif;
        endif; ?>

        <?php if (empty($owners)): ?>
            <div class="notice notice-success inline" style="margin:0"><p><?php _e('No plugins or themes detected in the scan roots.', 'phpinfo-wp'); ?></p></div>
        <?php else: ?>

            <p class="description" style="margin:0 0 16px"><?php _e('Regex-based static analysis — a match in a comment, string, or a plugin\'s own same-named function can be a false positive. Verify before editing third-party code.', 'phpinfo-wp'); ?></p>

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
                                    <span class="phpinfowp-ua-meta-pill"><?php _e('Not on WordPress.org (premium/custom) — metadata unavailable', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($meta['php_blocks'])): ?>
                                    <span class="phpinfowp-ua-meta-pill is-bad">Requires PHP <?php echo esc_html($meta['requires_php']); ?> (you run <?php echo esc_html(PHP_VERSION); ?>)</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php
                        // Free tier shows the findings but not the file/line drill-down.
                        $issues = $o['issues'] ?? [];
                        if (!$is_pro && count($issues) > 0):
                            $shown = array_slice($issues, 0, 3);
                        else:
                            $shown = $issues;
                        endif;
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
                                                data-ai-value="<?php echo esc_attr($result['target']); ?>">Explain with AI</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!$is_pro && count($issues) > 3): ?>
                            <div class="phpinfowp-compat-issue" style="border-left-color:#c8d8f5;background:#f7faff">
                                <div class="phpinfowp-compat-issue-meta">
                                    + <?php echo count($issues) - 3; ?> more finding<?php echo (count($issues) - 3) !== 1 ? 's' : ''; ?> with exact file &amp; line —
                                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank"><?php _e('unlock the full drill-down with Pro →', 'phpinfo-wp'); ?></a>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                </details>
            <?php endforeach; ?>

        <?php endif; ?>

    <?php endif; ?>

</div>
