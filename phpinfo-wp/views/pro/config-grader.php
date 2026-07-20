<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro = Phpinfo_WP_License::is_valid();

if (!$is_pro) {
    // Free tier — show grade + counts only, gate the details
    $summary = Phpinfo_WP_Config_Grader::summary();
    $score   = $summary['score'];
    $grade   = $summary['grade'];
    ?>
    <div class="phpinfowp-pro-page">

        <div class="phpinfowp-page-header">
            <div>
                <h1><?php _e('Config Grader', 'phpinfo-wp'); ?></h1>
                <p class="phpinfowp-page-subtitle"><?php _e('PHP configuration scored against WordPress best practices', 'phpinfo-wp'); ?></p>
            </div>
        </div>

        <div class="phpinfowp-score-card" style="margin-bottom:28px">
            <div class="phpinfowp-grade-circle grade-<?php echo esc_attr(strtolower(str_replace('+', 'plus', $grade))); ?>">
                <?php echo esc_html($grade); ?>
            </div>
            <div class="phpinfowp-score-card-body">
                <div class="phpinfowp-score-card-value"><?php echo esc_html($score); ?><span>/100</span></div>
                <div class="phpinfowp-score-card-meta">
                    <?php if ($summary['fails']): ?>
                        <span style="color:#d63638"><strong><?php echo (int)$summary['fails']; ?></strong> failing</span> &nbsp;&middot;&nbsp;
                    <?php endif; ?>
                    <?php if ($summary['warns']): ?>
                        <span style="color:#dba617"><strong><?php echo (int)$summary['warns']; ?></strong> warnings</span> &nbsp;&middot;&nbsp;
                    <?php endif; ?>
                    <span style="color:#00a32a"><strong><?php echo (int)$summary['passes']; ?></strong> passing</span>
                    &nbsp;&middot;&nbsp; <?php echo (int)$summary['total']; ?> checks total
                </div>
            </div>
        </div>

        <div class="phpinfowp-grader-teaser">
            <div class="phpinfowp-grader-teaser-skeleton" aria-hidden="true" style="padding: 18px 22px; pointer-events: none; user-select: none;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;border-bottom:1px solid #f0f0f0;padding-bottom:12px;">
                    <div style="width:16px;height:16px;border-radius:50%;background:#fee2e2;flex-shrink:0;"></div>
                    <div style="height:11px;width:35%;background:#cbd5e1;border-radius:4px;"></div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;border-bottom:1px solid #f0f0f0;padding-bottom:12px;">
                    <div style="width:16px;height:16px;border-radius:50%;background:#fef3c7;flex-shrink:0;"></div>
                    <div style="height:11px;width:45%;background:#cbd5e1;border-radius:4px;"></div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;border-bottom:1px solid #f0f0f0;padding-bottom:12px;">
                    <div style="width:16px;height:16px;border-radius:50%;background:#fee2e2;flex-shrink:0;"></div>
                    <div style="height:11px;width:30%;background:#cbd5e1;border-radius:4px;"></div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:16px;height:16px;border-radius:50%;background:#fee2e2;flex-shrink:0;"></div>
                    <div style="height:11px;width:40%;background:#cbd5e1;border-radius:4px;"></div>
                </div>
            </div>
            <div class="phpinfowp-grader-teaser-cta">
                <h3><?php _e('See every failing directive and how to fix it', 'phpinfo-wp'); ?></h3>
                <p><?php _e('Pro unlocks the full breakdown across Performance, Security, OPcache, and Session categories — with the exact recommended values and one-line explanations.', 'phpinfo-wp'); ?></p>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="phpinfowp-upgrade-cta"><?php _e('Unlock full details — Get Pro →', 'phpinfo-wp'); ?></a>
            </div>
        </div>

    </div>
    <?php
    return;
}

// Handle auto-fix actions before re-running the grader, so the page reflects the new state.
$fix_notice = '';
$fix_notice_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['phpinfowp_autofix']) && check_admin_referer('phpinfowp_autofix_nonce')) {
        $keys = isset($_POST['fix_keys']) ? (array) $_POST['fix_keys'] : [];
        $keys = array_map('sanitize_text_field', $keys);
        $res  = Phpinfo_WP_Config_Grader_Fixer::apply($keys);
        if ($res['ok']) {
            $settle = (int) ($res['settle'] ?? 0);
            $mins   = $settle > 60 ? ceil($settle / 60) . ' minute' . (ceil($settle / 60) === 1.0 ? '' : 's') : $settle . ' seconds';
            $fix_notice = 'Wrote ' . count($res['applied']) . ' directive(s) to <code>' . esc_html(basename($res['file'])) . '</code>: ' . esc_html(implode(', ', $res['applied'])) . '. '
                . ($res['mode'] === 'userini'
                    ? '<br>New values activate once the <code>.user.ini</code> cache clears — about <strong>' . esc_html($mins) . '</strong>. We\'ll automatically re-check for host overrides after that; nothing to do until then.'
                    : '<br>New values activate on the next page load. We\'ll re-check for host overrides shortly.');
        } else {
            $fix_notice = $res['error'] ?? 'Auto-fix failed.';
            $fix_notice_type = 'error';
        }
    } elseif (isset($_POST['phpinfowp_autofix_revert']) && check_admin_referer('phpinfowp_autofix_revert_nonce')) {
        $res = Phpinfo_WP_Config_Grader_Fixer::revert_all();
        if ($res['ok']) {
            $fix_notice = $res['reverted'] ? 'Auto-fix block reverted.' : 'Nothing to revert.';
        } else {
            $fix_notice = $res['error'];
            $fix_notice_type = 'error';
        }
    }
}

$result      = Phpinfo_WP_Config_Grader::run();
$checks      = $result['checks'];
$cross       = $result['cross'];
$score       = $result['score'];
$grade       = $result['grade'];
$categories  = $result['categories'];
$ctx         = $result['context'];
$trend       = $result['trend'];
$sev_counts  = $result['severity_counts'];

$status_icon  = ['pass' => '✓', 'warn' => '⚠', 'fail' => '✗'];
$status_color = ['pass' => '#00a32a', 'warn' => '#996800', 'fail' => '#d63638'];

// Map of host slugs → display names for the context banner
$host_names = [
    'kinsta'    => 'Kinsta',     'wpengine'  => 'WP Engine',  'siteground' => 'SiteGround',
    'cloudways' => 'Cloudways',  'pantheon'  => 'Pantheon',   'flywheel'   => 'Flywheel',
    'liquidweb' => 'LiquidWeb',  'litespeed' => 'LiteSpeed',
];
$plugin_labels = [
    'woocommerce'    => 'WooCommerce',  'elementor'     => 'Elementor',
    'wpbakery'       => 'WPBakery',      'divi'          => 'Divi',
    'avada'          => 'Avada',         'oxygen'        => 'Oxygen',
    'beaver-builder' => 'Beaver Builder','buddypress'    => 'BuddyPress',
    'learndash'      => 'LearnDash',     'tutor'         => 'Tutor LMS',
    'lifterlms'      => 'LifterLMS',     'edd'           => 'Easy Digital Downloads',
    'wp-all-import'  => 'WP All Import', 'forms-heavy'   => 'forms',
    'backup'         => 'a backup plugin','wp-rocket'    => 'WP Rocket',
    'litespeed-cache'=> 'LiteSpeed Cache',
];

// Severity pill helper (closure)
$sev_pill = function (string $sev, string $label) {
    $color = Phpinfo_WP_Config_Grader::severity_color($sev);
    return sprintf(
        '<span class="phpinfowp-sev phpinfowp-sev-%s" style="color:%s;border-color:%s">%s</span>',
        esc_attr($sev), esc_attr($color), esc_attr($color), esc_html($label)
    );
};

$target_info     = Phpinfo_WP_Config_Grader_Fixer::detect_target();
$target_writable = Phpinfo_WP_Config_Grader_Fixer::target_writable();
$settle_left     = Phpinfo_WP_Config_Grader_Fixer::settle_remaining();
$overrides       = Phpinfo_WP_Config_Grader_Fixer::detect_overrides();
?>

<?php if ($settle_left > 0): ?>
    <div class="notice notice-info" style="margin:0 0 16px"><p>
        <span class="dashicons dashicons-update" style="vertical-align:middle"></span>
        <strong><?php _e('Auto-fix applied — values are still propagating.', 'phpinfo-wp'); ?></strong>
        <code><?php echo esc_html(basename($target_info['file'])); ?></code> changes don't apply to the page that wrote them and are cached briefly by PHP.
        The override check is paused for about <strong><?php echo esc_html($settle_left > 60 ? ceil($settle_left / 60) . ' more minute(s)' : $settle_left . ' more seconds'); ?></strong>, then runs automatically — reload after that to confirm.
    </p></div>
<?php endif; ?>

<?php if ($fix_notice): ?>
    <div class="notice notice-<?php echo esc_attr($fix_notice_type); ?> is-dismissible" style="margin:0 0 16px"><p><?php echo $fix_notice; ?></p></div>
<?php endif; ?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Config Grader <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('PHP configuration scored against WordPress, WooCommerce, and security best practices', 'phpinfo-wp'); ?></p>
        </div>
    </div>

    <?php
    $failing = array_filter($checks, function ($c) {
    return $c['status'] === 'fail';
});
    $warning = array_filter($checks, function ($c) {
        return $c['status'] === 'warn';
    });
    $passing = array_filter($checks, function ($c) {
        return $c['status'] === 'pass';
    });
    ?>

    <?php
        // Determine fixable failing/warning checks
        $fixable_keys = [];
        foreach ($checks as $c) {
            if ($c['status'] === 'pass') continue;
            if (Phpinfo_WP_Config_Grader_Fixer::can_fix($c['key'])) $fixable_keys[] = $c['key'];
        }
    ?>

    <?php if ($overrides): ?>
        <div style="background:#fff4f4;border:1px solid #f0c8c8;border-left:4px solid #d63638;border-radius:6px;padding:14px 18px;margin-bottom:20px">
            <strong style="color:#a00;font-size:14px">⚠️ <?php echo count($overrides); ?> directive<?php echo count($overrides) === 1 ? '' : 's'; ?> not taking effect — your host is overriding the auto-fix</strong>
            <p style="margin:6px 0 8px;color:#444;font-size:12.5px">
                We wrote these values to <code><?php echo esc_html(basename($target_info['file'])); ?></code>, but PHP is still reporting different values. This usually means a <code>.user.ini</code> in a parent directory, your hosting control panel's PHP options, or a php.ini lock is winning the override.
            </p>
            <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                <thead>
                    <tr style="text-align:left;color:#777;border-bottom:1px solid #f0c8c8">
                        <th style="padding:4px 6px;font-weight:600"><?php _e('Directive', 'phpinfo-wp'); ?></th>
                        <th style="padding:4px 6px;font-weight:600"><?php _e('We wrote', 'phpinfo-wp'); ?></th>
                        <th style="padding:4px 6px;font-weight:600"><?php _e('PHP reports', 'phpinfo-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overrides as $o): ?>
                    <tr style="border-bottom:1px solid #fbe6e6">
                        <td style="padding:6px;font-family:monospace;color:#a00"><?php echo esc_html($o['key']); ?></td>
                        <td style="padding:6px;font-family:monospace;color:#00a32a"><?php echo esc_html($o['expected']); ?></td>
                        <td style="padding:6px;font-family:monospace;color:#d63638"><?php echo esc_html($o['actual'] === '' ? '(empty)' : $o['actual']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin:10px 0 0;color:#666;font-size:12px">
                <strong><?php _e('What to do:', 'phpinfo-wp'); ?></strong> set these directives via your hosting control panel's PHP options (cPanel "MultiPHP INI Editor", Hostinger "PHP Configuration", etc.), or ask host support to raise the limit. Then revert the auto-fix block to keep your config file clean.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($fixable_keys && $target_writable): ?>
        <?php $profile = Phpinfo_WP_Config_Grader::dominant_profile($ctx); ?>
        <?php if ($profile): ?>
            <div style="background:linear-gradient(135deg, #1e1e1e, #2a2a2a); border:1px solid #333; border-radius:10px; padding:20px 24px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; gap:24px; flex-wrap:wrap; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div style="flex:1; min-width:300px;">
                    <div style="display:inline-block; font-size:24px; margin-right:12px; vertical-align:middle;"><?php echo esc_html($profile['icon']); ?></div>
                    <strong style="font-size:18px; color:#fff; vertical-align:middle; letter-spacing:0.3px;"><?php echo esc_html($profile['name']); ?> Detected</strong>
                    <p style="margin:8px 0 0; color:#aaa; font-size:13.5px; line-height:1.5;">
                        <?php echo esc_html($profile['desc']); ?><br>
                        <span style="color:#ddd; margin-top:6px; display:inline-block;">We'll automatically apply the optimal configuration to <code><?php echo esc_html(basename($target_info['file'])); ?></code>.</span>
                    </p>
                </div>
                <form method="post" style="margin:0">
                    <?php wp_nonce_field('phpinfowp_autofix_nonce'); ?>
                    <input type="hidden" name="phpinfowp_autofix" value="1">
                    <?php foreach ($fixable_keys as $k): ?>
                        <input type="hidden" name="fix_keys[]" value="<?php echo esc_attr($k); ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="button button-primary" style="font-size:14px; padding:6px 16px; height:auto; background:#007cba; border-color:#007cba;"><?php _e('1-Click Optimize Server →', 'phpinfo-wp'); ?></button>
                </form>
            </div>
        <?php else: ?>
            <div style="background:linear-gradient(135deg,#f3f7ff,#eaf4ff);border:1px solid #c8d8f5;border-radius:8px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
                <div>
                    <strong style="font-size:14px;color:#1a3a72">⚡ Optimize Server Profile (<?php echo count($fixable_keys); ?> missing directives)</strong>
                    <p style="margin:4px 0 0;color:#3a567c;font-size:12px">
                        Writes our recommended WordPress defaults to <code><?php echo esc_html(basename($target_info['file'])); ?></code> with automatic rollback on errors.
                    </p>
                </div>
                <form method="post" style="margin:0">
                    <?php wp_nonce_field('phpinfowp_autofix_nonce'); ?>
                    <input type="hidden" name="phpinfowp_autofix" value="1">
                    <?php foreach ($fixable_keys as $k): ?>
                        <input type="hidden" name="fix_keys[]" value="<?php echo esc_attr($k); ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="button button-primary"><?php _e('Apply Server Profile →', 'phpinfo-wp'); ?></button>
                </form>
            </div>
        <?php endif; ?>
    <?php elseif ($fixable_keys && !$target_writable): ?>
        <div class="notice notice-warning inline" style="margin:0 0 18px"><p>
            <strong><?php _e('Auto-fix unavailable:', 'phpinfo-wp'); ?></strong> Your site root or <code><?php echo esc_html(basename($target_info['file'])); ?></code> is not writable by PHP. Fix permissions, or apply the recommended values manually via the <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-htaccess')); ?>">PHP Config editor</a>.
        </p></div>
    <?php endif; ?>

    <!-- Detected context — what we tuned the recommendations for -->
    <div class="phpinfowp-cg-context">
        <div class="phpinfowp-cg-context-label"><?php _e('Recommendations tuned for this site', 'phpinfo-wp'); ?></div>
        <div class="phpinfowp-cg-context-row">
            <span class="phpinfowp-cg-chip phpinfowp-cg-chip-php">PHP <?php echo esc_html($ctx['php_full']); ?></span>
            <?php if ($ctx['is_https']): ?>
                <span class="phpinfowp-cg-chip"><?php _e('HTTPS', 'phpinfo-wp'); ?></span>
            <?php endif; ?>
            <?php if (!$ctx['is_production']): ?>
                <span class="phpinfowp-cg-chip phpinfowp-cg-chip-dev"><?php _e('Development mode', 'phpinfo-wp'); ?></span>
            <?php endif; ?>
            <?php if ($ctx['host'] && isset($host_names[$ctx['host']])): ?>
                <span class="phpinfowp-cg-chip phpinfowp-cg-chip-host"><?php echo esc_html($host_names[$ctx['host']]); ?> detected</span>
            <?php endif; ?>
            <?php if (!empty($ctx['server']) && $ctx['server'] !== 'unknown'): ?>
                <span class="phpinfowp-cg-chip"><?php echo esc_html(ucfirst($ctx['server'])); ?></span>
            <?php endif; ?>
            <?php
            // Only show meaningful plugin chips
            foreach ($ctx['plugins'] as $slug => $_) {
                if (!isset($plugin_labels[$slug])) continue;
                echo '<span class="phpinfowp-cg-chip phpinfowp-cg-chip-plugin">' . esc_html($plugin_labels[$slug]) . '</span>';
            }
            ?>
        </div>
    </div>

    <!-- Score card with trend -->
    <div class="phpinfowp-score-card" style="margin-bottom:28px">
        <div class="phpinfowp-grade-circle grade-<?php echo esc_attr(strtolower(str_replace('+', 'plus', $grade))); ?>">
            <?php echo esc_html($grade); ?>
        </div>
        <div class="phpinfowp-score-card-body">
            <div class="phpinfowp-score-card-value">
                <?php echo esc_html($score); ?><span>/100</span>
                <?php if (!empty($trend['available'])):
                    $delta = (int) $trend['delta'];
                    $cls = $delta > 0 ? 'phpinfowp-trend-up' : ($delta < 0 ? 'phpinfowp-trend-down' : 'phpinfowp-trend-flat');
                    $arrow = $delta > 0 ? '↑' : ($delta < 0 ? '↓' : '→');
                ?>
                <span class="phpinfowp-trend <?php echo $cls; ?>" title="Previous score: <?php echo (int) $trend['previous']; ?>">
                    <?php echo $arrow; ?> <?php echo $delta > 0 ? '+' . $delta : (string) $delta; ?>
                    <small>vs last reading</small>
                </span>
                <?php endif; ?>
            </div>
            <div class="phpinfowp-score-card-meta">
                <?php if ($failing): ?>
                    <span style="color:#d63638"><strong><?php echo count($failing); ?></strong> failing</span> &nbsp;&middot;&nbsp;
                <?php endif; ?>
                <?php if ($warning): ?>
                    <span style="color:#dba617"><strong><?php echo count($warning); ?></strong> warnings</span> &nbsp;&middot;&nbsp;
                <?php endif; ?>
                <span style="color:#00a32a"><strong><?php echo count($passing); ?></strong> passing</span>
                &nbsp;&middot;&nbsp; <?php echo count($checks); ?> checks total
                <?php if ($sev_counts[Phpinfo_WP_Config_Grader::SEV_CRITICAL] ?? 0): ?>
                    &nbsp;&middot;&nbsp; <?php echo $sev_pill(Phpinfo_WP_Config_Grader::SEV_CRITICAL, $sev_counts[Phpinfo_WP_Config_Grader::SEV_CRITICAL] . ' critical'); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($cross)): ?>
    <!-- Cross-directive consistency issues -->
    <div class="phpinfowp-cg-cross">
        <h3 class="phpinfowp-cg-cross-heading">
            <span class="dashicons dashicons-warning" style="color:#dba617"></span>
            <?php echo count($cross); ?> consistency issue<?php echo count($cross) === 1 ? '' : 's'; ?>
            <span class="phpinfowp-cg-cross-sub">— directives that contradict each other</span>
        </h3>
        <?php foreach ($cross as $x): ?>
            <div class="phpinfowp-cg-cross-row">
                <div class="phpinfowp-cg-cross-top">
                    <strong><?php echo esc_html($x['label']); ?></strong>
                    <?php echo $sev_pill($x['severity'], $x['severity_label']); ?>
                </div>
                <div class="phpinfowp-cg-cross-reason"><?php echo esc_html($x['reason']); ?></div>
                <div class="phpinfowp-cg-cross-fix"><strong><?php _e('Fix:', 'phpinfo-wp'); ?></strong> <?php echo esc_html($x['fix']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Checks by category -->
    <?php foreach ($categories as $cat):
        $cat_checks = array_filter($checks, function ($c) use ($cat) {
            return $c['category'] === $cat;
        });
        $cat_fails  = count(array_filter($cat_checks, function ($c) {
            return $c['status'] === 'fail';
        }));
        $cat_warns  = count(array_filter($cat_checks, function ($c) {
            return $c['status'] === 'warn';
        }));
    ?>
        <div class="phpinfowp-grade-section">
            <h2 class="phpinfowp-grade-section-heading">
                <?php echo esc_html($cat); ?>
                <?php if ($cat_fails): ?>
                    <span class="phpinfowp-grade-section-badge" style="background:#d63638"><?php echo $cat_fails; ?> failing</span>
                <?php elseif ($cat_warns): ?>
                    <span class="phpinfowp-grade-section-badge" style="background:#dba617"><?php echo $cat_warns; ?> warnings</span>
                <?php else: ?>
                    <span class="phpinfowp-grade-section-badge" style="background:#00a32a"><?php _e('All good', 'phpinfo-wp'); ?></span>
                <?php endif; ?>
            </h2>

            <div class="phpinfowp-grade-checks">
                <?php foreach ($cat_checks as $c):
                    $color  = $status_color[$c['status']];
                    $bg     = $c['status'] === 'fail' ? '#fff4f4' : ($c['status'] === 'warn' ? '#fffbf0' : '#f0faf2');
                    $dicon  = $c['status'] === 'fail' ? 'dashicons-dismiss' : ($c['status'] === 'warn' ? 'dashicons-warning' : 'dashicons-yes-alt');
                ?>
                <div class="phpinfowp-grade-check" style="border-left-color:<?php echo $color; ?>;background:<?php echo $bg; ?>">
                    <div class="phpinfowp-grade-check-icon">
                        <span class="dashicons <?php echo $dicon; ?>" style="color:<?php echo $color; ?>"></span>
                    </div>
                    <div class="phpinfowp-grade-check-body">
                        <div class="phpinfowp-grade-check-key">
                            <code><?php echo esc_html($c['key']); ?></code>
                            <?php if ($c['status'] !== 'pass'): ?>
                                <?php echo $sev_pill($c['severity'], $c['severity_label']); ?>
                            <?php endif; ?>
                            <?php if ($c['status'] !== 'pass' && Phpinfo_WP_Config_Grader_Fixer::can_fix($c['key'])): ?>
                                <button type="button" class="button button-small phpinfowp-trigger-autofix"
                                        style="margin-left:8px;vertical-align:middle"
                                        data-key="<?php echo esc_attr($c['key']); ?>"
                                        data-current="<?php echo esc_attr($c['value']); ?>"
                                        data-recommended="<?php echo esc_attr($c['target_label'] ?? $c['good']); ?>">
                                    <?php _e('Fix this →', 'phpinfo-wp'); ?>
                                </button>
                            <?php elseif ($c['status'] !== 'pass' && ($note = Phpinfo_WP_Config_Grader_Fixer::manual_note($c['key']))): ?>
                                <span style="margin-left:8px;font-size:11px;color:#888;font-style:italic">manual fix only · <?php echo esc_html($note); ?></span>
                            <?php endif; ?>
                            <?php if ($c['status'] !== 'pass' && Phpinfo_WP_AI_Explain::available()): ?>
                                <button type="button"
                                        class="button button-small phpinfowp-ai-explain"
                                        style="margin-left:6px;vertical-align:middle"
                                        data-ai-topic="config_issue"
                                        data-ai-context="<?php echo esc_attr($c['key']); ?>"
                                        data-ai-value="<?php echo esc_attr($c['value']); ?>">Explain with AI</button>
                            <?php endif; ?>
                        </div>
                        <div class="phpinfowp-grade-check-values">
                            <span>Current: <code style="color:<?php echo $color; ?>"><?php echo esc_html($c['value']); ?></code></span>
                            <span style="color:#888">&rarr;</span>
                            <span>Recommended: <code style="color:#00a32a"><?php echo esc_html($c['target_label'] ?? $c['good']); ?></code></span>
                        </div>
                        <div class="phpinfowp-grade-check-note"><?php echo esc_html($c['note']); ?></div>
                        <?php if (!empty($c['live_evidence'])): ?>
                            <div class="phpinfowp-grade-check-live">
                                <span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
                                <strong><?php _e('Observed:', 'phpinfo-wp'); ?></strong> <?php echo esc_html($c['live_evidence']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($c['status'] !== 'pass' && !empty($c['host_fix'])): ?>
                            <div class="phpinfowp-grade-check-host">
                                <strong>On <?php echo esc_html($c['host_fix']['panel']); ?>:</strong>
                                <?php echo esc_html($c['host_fix']['how']); ?>
                                <?php if (!empty($c['host_fix']['url'])): ?>
                                    <a href="<?php echo esc_url($c['host_fix']['url']); ?>" target="_blank" rel="noopener">Open panel ↗</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <p class="description" style="margin-top:16px">
        Some directives differ between production and development. Review context before changing values.
        Use the <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-htaccess')); ?>">PHP Config editor</a> to apply changes manually.
    </p>

    <?php
        $current_target = @file_exists($target_info['file']) ? @file_get_contents($target_info['file']) : '';
        $has_autofix_block = is_string($current_target) && strpos($current_target, 'BEGIN phpinfo-wp-autofix') !== false;
    ?>
    <?php if ($has_autofix_block): ?>
        <form method="post" style="margin-top:8px" onsubmit="return confirm('Revert the entire auto-fix block? This removes every value phpinfo() WP added — manual edits to your config file are untouched.')">
            <?php wp_nonce_field('phpinfowp_autofix_revert_nonce'); ?>
            <input type="hidden" name="phpinfowp_autofix_revert" value="1">
            <button type="submit" class="button-link" style="color:#a00;font-size:12px"><?php _e('Revert all auto-fix changes', 'phpinfo-wp'); ?></button>
        </form>
    <?php endif; ?>
</div>

<!-- Contextual Auto-Fix Modal -->
<div id="phpinfowp-autofix-modal" class="phpinfowp-modal" role="dialog" aria-modal="true" aria-labelledby="phpinfowp-modal-title-el">
    <div class="phpinfowp-modal-content">
        <div class="phpinfowp-modal-header">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(119, 123, 179, 0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <span class="dashicons dashicons-admin-tools" style="font-size:20px;width:20px;height:20px;color:#777BB3"></span>
            </div>
            <h2 id="phpinfowp-modal-title-el" class="phpinfowp-modal-title"><?php _e('Confirm Auto-Fix', 'phpinfo-wp'); ?></h2>
        </div>
        <div class="phpinfowp-modal-body">
            <p><?php _e('You are about to automatically tune the following configuration directive:', 'phpinfo-wp'); ?></p>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px 14px; margin: 14px 0; font-family:monospace; font-size:13px;">
                <div style="margin-bottom:6px;"><strong>Directive:</strong> <code id="phpinfowp-modal-key" style="color:#2c3338;"></code></div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span>Current: <code id="phpinfowp-modal-current" style="color:#d63638;"></code></span>
                    <span style="color:#94a3b8;">&rarr;</span>
                    <span>New Value: <code id="phpinfowp-modal-recommended" style="color:#00a32a;"></code></span>
                </div>
            </div>
            <p>
                <?php printf(
                    __('This action will write an override rule directly to your %s file.', 'phpinfo-wp'),
                    '<code>' . esc_html(basename($target_info['file'])) . '</code>'
                ); ?>
            </p>
            <p style="font-size:12px; color:#64748b; font-style:italic; margin-top:10px; line-height:1.4;">
                💡 <?php _e('Note: We wrap all auto-fix rules inside a custom section. You can revert this entire block of changes at any time using the revert button below the checks list.', 'phpinfo-wp'); ?>
            </p>
        </div>
        <div class="phpinfowp-modal-footer">
            <form method="post" id="phpinfowp-autofix-form">
                <?php wp_nonce_field('phpinfowp_autofix_nonce'); ?>
                <input type="hidden" name="phpinfowp_autofix" value="1">
                <input type="hidden" name="fix_keys[]" id="phpinfowp-modal-input-key" value="">
                <button type="button" class="button phpinfowp-modal-close-btn" style="margin-right:4px;"><?php _e('Cancel', 'phpinfo-wp'); ?></button>
                <button type="submit" class="button button-primary" style="background:#777BB3; border-color:#777BB3;"><?php _e('Apply Auto-Fix', 'phpinfo-wp'); ?></button>
            </form>
        </div>
    </div>
</div>

<style>
.phpinfowp-modal {
    opacity: 0;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    z-index: 99999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.phpinfowp-modal-content {
    background-color: #fff;
    padding: 30px;
    border: 1px solid #e2e8f0;
    width: 90%;
    max-width: 500px;
    border-radius: 8px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    transform: scale(0.95);
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.phpinfowp-modal.is-open {
    opacity: 1;
    pointer-events: auto;
}
.phpinfowp-modal.is-open .phpinfowp-modal-content {
    transform: scale(1);
}
.phpinfowp-modal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}
.phpinfowp-modal-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1d2327;
}
.phpinfowp-modal-body {
    font-size: 13.5px;
    color: #475569;
    line-height: 1.5;
    margin-bottom: 24px;
}
.phpinfowp-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    var $modal = $('#phpinfowp-autofix-modal');
    
    $(document).on('click', '.phpinfowp-trigger-autofix', function(e) {
        e.preventDefault();
        var key = $(this).data('key');
        var current = $(this).data('current');
        var recommended = $(this).data('recommended');
        
        $('#phpinfowp-modal-key').text(key);
        $('#phpinfowp-modal-current').text(current);
        $('#phpinfowp-modal-recommended').text(recommended);
        $('#phpinfowp-modal-input-key').val(key);
        
        $modal.addClass('is-open');
    });
    
    $(document).on('click', '.phpinfowp-modal-close-btn, .phpinfowp-modal', function(e) {
        if ($(e.target).hasClass('phpinfowp-modal') || $(e.target).hasClass('phpinfowp-modal-close-btn')) {
            $modal.removeClass('is-open');
        }
    });
});
</script>
