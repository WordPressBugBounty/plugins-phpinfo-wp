<?php
defined('ABSPATH') or die('Unauthorized Access');

// PHP Compatibility Scanner is FREE — the WP-Engine and Eli scanners are
// abandoned or only work in dev environments, so this captures real demand.
// Pro adds scheduled scans, more files per run, and email alerts when new
// compat issues appear after a plugin/theme update.
$is_pro = Phpinfo_WP_License::is_valid();

$target = isset($_GET['target']) && in_array($_GET['target'], Phpinfo_WP_Compat::targets(), true)
    ? $_GET['target'] : '8.2';

if (isset($_POST['phpinfowp_scan']) && check_admin_referer('phpinfowp_compat_nonce')) {
    $target = sanitize_text_field($_POST['target'] ?? '8.2');
    Phpinfo_WP_Compat::scan($target);
}
if (isset($_POST['phpinfowp_clear']) && check_admin_referer('phpinfowp_compat_nonce')) {
    Phpinfo_WP_Compat::clear();
}

$result = Phpinfo_WP_Compat::get_result();
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1><?php _e('PHP Compatibility Scanner', 'phpinfo-wp'); ?></h1>
            <p class="phpinfowp-page-subtitle">Find deprecated/removed PHP functions in your plugins and themes before upgrading. Works on managed hosts (no <code>exec()</code> required).</p>
        </div>
    </div>

    <?php if (!$is_pro): ?>
        <div style="background:linear-gradient(135deg,#f3f7ff,#eaf4ff);border:1px solid #c8d8f5;border-radius:8px;padding:12px 16px;margin:0 0 18px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
            <span style="font-size:13px;color:#1a3a72">
                <strong><?php _e('Free scanner:', 'phpinfo-wp'); ?></strong> up to <?php echo Phpinfo_WP_Compat::FREE_MAX_FILES; ?> files per run. Pro raises the cap to <?php echo Phpinfo_WP_Compat::MAX_FILES_PER_RUN; ?>, adds scheduled weekly scans, and emails you when an updated plugin introduces a new compat issue.
            </span>
            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="button button-primary" style="margin-left:auto"><?php _e('Get Pro →', 'phpinfo-wp'); ?></a>
        </div>
    <?php endif; ?>

    <form method="post" class="phpinfowp-compat-controls">
        <?php wp_nonce_field('phpinfowp_compat_nonce'); ?>
        <label>
            <strong><?php _e('Target PHP version:', 'phpinfo-wp'); ?></strong>
            <select name="target">
                <?php foreach (Phpinfo_WP_Compat::targets() as $t): ?>
                    <option value="<?php echo esc_attr($t); ?>" <?php selected($target, $t); ?>>PHP <?php echo esc_html($t); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" name="phpinfowp_scan" value="1" class="button button-primary">
            <span class="dashicons dashicons-search"></span>Run Scan
        </button>
        <?php if ($result): ?>
            <button type="submit" name="phpinfowp_clear" value="1" class="button button-secondary"><?php _e('Clear results', 'phpinfo-wp'); ?></button>
        <?php endif; ?>
    </form>

    <?php if (isset($result['error'])): ?>
        <div class="notice notice-error inline"><p><?php echo esc_html($result['error']); ?></p></div>
    <?php elseif (!$result): ?>
        <div class="phpinfowp-compat-empty">
            <span class="dashicons dashicons-search"></span>
            <h3><?php _e('No scan yet', 'phpinfo-wp'); ?></h3>
            <p><?php _e('Pick a target PHP version above and click "Run Scan" to analyze your installed plugins and themes.', 'phpinfo-wp'); ?></p>
            <p style="font-size:12px;color:#888">Scans up to <?php echo $is_pro ? Phpinfo_WP_Compat::MAX_FILES_PER_RUN : Phpinfo_WP_Compat::FREE_MAX_FILES; ?> PHP files. Takes 10–60 seconds.</p>
        </div>
    <?php else:
        $sev_color = ['removed' => '#d63638', 'deprecated' => '#dba617'];
        $sev_label = ['removed' => 'REMOVED', 'deprecated' => 'DEPRECATED'];
        $issues = $result['issues'] ?? [];
        uksort($issues, function ($a, $b) use ($issues) {
            return count($issues[$b]) - count($issues[$a]);
        });
    ?>

        <!-- Score card -->
        <div class="phpinfowp-score-card" style="margin-bottom:24px">
            <div class="phpinfowp-grade-circle <?php echo $result['total'] === 0 ? 'grade-aplus' : ($result['total'] < 10 ? 'grade-b' : ($result['total'] < 50 ? 'grade-c' : 'grade-f')); ?>">
                <?php echo $result['total'] === 0 ? '✓' : (int)$result['total']; ?>
            </div>
            <div class="phpinfowp-score-card-body">
                <div class="phpinfowp-score-card-value">
                    <?php if ($result['total'] === 0): ?>
                        Compatible<span> with PHP <?php echo esc_html($result['target']); ?></span>
                    <?php else: ?>
                        <?php echo (int)$result['total']; ?> issue<?php echo $result['total'] !== 1 ? 's' : ''; ?>
                        <span>against PHP <?php echo esc_html($result['target']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="phpinfowp-score-card-meta">
                    <strong><?php echo (int)$result['with_issues']; ?></strong> of <strong><?php echo (int)$result['owners']; ?></strong> plugins/themes affected
                    &nbsp;&middot;&nbsp;
                    <?php echo number_format($result['files']); ?> files scanned in <?php echo esc_html($result['duration']); ?>s
                    &nbsp;&middot;&nbsp;
                    <?php echo esc_html(human_time_diff($result['scanned_at']) . ' ago'); ?>
                </div>
            </div>
        </div>

        <?php 
        $current_php = phpversion();
        $is_already_on_target = version_compare($current_php, $result['target'], '>=');

        $total_removed = 0;
        $total_deprecated = 0;
        foreach ($issues as $list) {
            foreach ($list as $issue) {
                if ($issue['severity'] === 'removed') $total_removed++;
                else $total_deprecated++;
            }
        }
        ?>

        <?php if ($result['total'] === 0): ?>
            <div class="notice notice-success inline" style="margin:0 0 24px">
                <p><strong><?php _e('Safe to upgrade!', 'phpinfo-wp'); ?></strong> No deprecated or removed function calls were detected for PHP <?php echo esc_html($result['target']); ?>. Your site should continue working normally after the upgrade.</p>
            </div>
        <?php else: ?>
            <?php if ($is_already_on_target): ?>
                <div class="notice notice-info inline" style="margin:0 0 24px; border-left-color: #2271b1;">
                    <p style="font-size: 15px;"><strong>ℹ️ You are already running PHP <?php echo esc_html(explode('-', $current_php)[0]); ?></strong></p>
                    <p>We found <strong><?php echo $total_removed + $total_deprecated; ?> potential issues</strong> against PHP <?php echo esc_html($result['target']); ?>. However, since your site is currently running fine, these are almost certainly <strong><?php _e('false positives', 'phpinfo-wp'); ?></strong> (e.g., legacy fallback code that never runs, or function names inside comments). No action is required unless you are experiencing actual errors in your logs.</p>
                </div>
            <?php elseif ($total_removed > 0): ?>
                <div class="notice notice-error inline" style="margin:0 0 24px; border-left-color: #d63638;">
                    <p style="font-size: 15px;"><strong>🚨 HIGH RISK: Check before upgrading</strong></p>
                    <p>Your plugins contain <strong><?php echo $total_removed; ?> removed functions</strong>. Removed functions cause <strong><?php _e('fatal errors', 'phpinfo-wp'); ?></strong> in PHP <?php echo esc_html($result['target']); ?>. Unless these are false positives, your website will likely crash if you upgrade your server right now. Please verify the red items below or update those plugins first.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning inline" style="margin:0 0 24px; border-left-color: #dba617;">
                    <p style="font-size: 15px;"><strong>⚠️ Proceed with Caution</strong></p>
                    <p>We found <strong><?php echo $total_deprecated; ?> deprecated functions</strong>. If you upgrade to PHP <?php echo esc_html($result['target']); ?>, your site should not crash, but you may see PHP warnings or minor bugs. You should look for updates for the plugins highlighted in yellow below.</p>
                </div>
            <?php endif; ?>

            <p class="description" style="margin:0 0 16px"><?php _e('Regex-based scan — some matches may be false positives (e.g., function names in comments or strings). Always verify before changing third-party code.', 'phpinfo-wp'); ?></p>

            <?php foreach ($issues as $owner => $list):
                [$type, $name] = explode('/', $owner, 2);
                $type_label = ['plugin' => 'Plugin', 'theme' => 'Theme', 'mu-plugin' => 'Must-Use'][$type] ?? $type;
                $type_color = ['plugin' => '#777BB3', 'theme' => '#2271b1', 'mu-plugin' => '#996800'][$type] ?? '#666';
                $removed_count    = count(array_filter($list, function ($i) {
                    return $i['severity'] === 'removed';
                }));
                $deprecated_count = count($list) - $removed_count;
            ?>
                <details class="phpinfowp-compat-owner">
                    <summary>
                        <span class="phpinfowp-compat-owner-type" style="background:<?php echo $type_color; ?>"><?php echo esc_html($type_label); ?></span>
                        <strong class="phpinfowp-compat-owner-name"><?php echo esc_html($name); ?></strong>
                        <span class="phpinfowp-compat-owner-counts">
                            <?php if ($removed_count): ?>
                                <span style="color:#d63638"><strong><?php echo $removed_count; ?></strong> removed</span>
                            <?php endif; ?>
                            <?php if ($deprecated_count): ?>
                                <?php if ($removed_count) echo '&middot;'; ?>
                                <span style="color:#dba617"><strong><?php echo $deprecated_count; ?></strong> deprecated</span>
                            <?php endif; ?>
                        </span>
                    </summary>
                    <div class="phpinfowp-compat-issues">
                        <?php foreach ($list as $issue): ?>
                            <div class="phpinfowp-compat-issue" style="border-left-color:<?php echo $sev_color[$issue['severity']]; ?>">
                                <div class="phpinfowp-compat-issue-head">
                                    <span class="phpinfowp-compat-sev" style="background:<?php echo $sev_color[$issue['severity']]; ?>">
                                        <?php echo $sev_label[$issue['severity']]; ?>
                                    </span>
                                    <code class="phpinfowp-compat-name"><?php echo esc_html($issue['name']); ?></code>
                                    <span class="phpinfowp-compat-where"><?php echo esc_html($issue['file']); ?>:<?php echo (int)$issue['line']; ?></span>
                                </div>
                                <div class="phpinfowp-compat-issue-meta">
                                    <?php if ($issue['severity'] === 'removed'): ?>
                                        Removed in PHP <?php echo esc_html($issue['out']); ?> &middot;
                                    <?php else: ?>
                                        Deprecated in PHP <?php echo esc_html($issue['in']); ?> &middot;
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
