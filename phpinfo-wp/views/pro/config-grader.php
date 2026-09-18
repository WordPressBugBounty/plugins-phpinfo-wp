<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

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
                <h1>
                    <?php _e('Config Grader', 'phpinfo-wp'); ?>
                    <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                    <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('PHP configuration scored against WordPress, WooCommerce, and security best practices.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
                </h1>
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

        <?php
        $grader_run = Phpinfo_WP_Config_Grader::run();
        $preview_checks = [];

        // Pick up to 2 failing or warning checks from real runtime
        foreach ($grader_run['checks'] as $c) {
            if ($c['status'] === 'fail' || $c['status'] === 'warn') {
                $is_fail = $c['status'] === 'fail';
                $preview_checks[] = [
                    'label'    => $c['label'],
                    'key'      => $c['key'],
                    'desc'     => !empty($c['explanation']) ? $c['explanation'] : sprintf(__('Current: %s • Recommended: %s', 'phpinfo-wp'), $c['value'], $c['good']),
                    'status'   => $is_fail ? 'FAIL' : 'WARNING',
                    'border'   => $is_fail ? '#d63638' : '#dba617',
                    'bg'       => $is_fail ? '#fee2e2' : '#fefce8',
                    'fg'       => $is_fail ? '#dc2626' : '#a16207',
                    'curr'     => $c['value'],
                    'target'   => $c['good'],
                ];
                if (count($preview_checks) >= 2) break;
            }
        }

        // If fewer than 2 issues found, add prominent real directive checks
        if (count($preview_checks) < 2) {
            foreach ($grader_run['checks'] as $c) {
                if (count($preview_checks) >= 2) break;
                $already = false;
                foreach ($preview_checks as $p) {
                    if ($p['key'] === $c['key']) { $already = true; break; }
                }
                if ($already) continue;

                $is_pass = $c['status'] === 'pass';
                $preview_checks[] = [
                    'label'    => $c['label'],
                    'key'      => $c['key'],
                    'desc'     => !empty($c['explanation']) ? $c['explanation'] : sprintf(__('Current: %s • Recommended: %s', 'phpinfo-wp'), $c['value'], $c['good']),
                    'status'   => $is_pass ? 'OPTIMAL' : 'NOTICE',
                    'border'   => $is_pass ? '#00a32a' : '#6366f1',
                    'bg'       => $is_pass ? '#dcfce7' : '#e0e7ff',
                    'fg'       => $is_pass ? '#15803d' : '#4338ca',
                    'curr'     => $c['value'],
                    'target'   => $c['good'],
                ];
            }
        }
        ?>

        <!-- Free preview: contextual preview cards + centered upgrade card -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">

            <!-- Background contextual preview cards -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($preview_checks as $pc): ?>
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid <?php echo esc_attr($pc['border']); ?>; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;"><?php echo esc_html($pc['key']); ?></strong>
                                <span style="background:<?php echo esc_attr($pc['bg']); ?>; color:<?php echo esc_attr($pc['fg']); ?>; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo esc_html($pc['status']); ?></span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php echo esc_html($pc['label']); ?> &bull; <?php echo esc_html($pc['desc']); ?>
                            </div>
                        </div>
                        <div style="text-align:right; font-size:12.5px; font-family:monospace;">
                            <span style="color:#64748b; text-decoration:line-through;"><?php echo esc_html($pc['curr']); ?></span>
                            <span style="color:#0f172a; font-weight:700; margin-left:6px;">&rarr; <?php echo esc_html($pc['target']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

            <!-- Floating Upgrade Card -->
            <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                <span class="dashicons dashicons-admin-settings" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('See Every Failing Directive & How to Fix It', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                    <?php _e('Pro unlocks the full breakdown across Performance, Security, OPcache, and Session categories — with exact recommended values, benchmark explanations, and 1-click auto-fixes.', 'phpinfo-wp'); ?>
                </p>
                <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">⚡</span>
                        <span><strong><?php _e('1-Click Auto-Fix Engine:', 'phpinfo-wp'); ?></strong> <?php _e('Safely write recommended values directly into .user.ini or php.ini with instant rollback support.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">🎯</span>
                        <span><strong><?php _e('Context-Aware Benchmarking:', 'phpinfo-wp'); ?></strong> <?php _e('Tailored thresholds adapted for WooCommerce, Elementor, high concurrency, and managed hosts.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">🔍</span>
                        <span><strong><?php _e('Host Override Detection:', 'phpinfo-wp'); ?></strong> <?php _e('Detect immutable host overrides and cross-directive conflicts like post_max_size mismatches.', 'phpinfo-wp'); ?></span>
                    </div>
                </div>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                    <?php _e('Unlock Full Details with Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
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
    } elseif (isset($_POST['phpinfowp_revert_keys']) && check_admin_referer('phpinfowp_revert_keys_nonce')) {
        $keys = isset($_POST['revert_keys']) ? (array) $_POST['revert_keys'] : [];
        $keys = array_map('sanitize_text_field', $keys);
        $res  = Phpinfo_WP_Config_Grader_Fixer::revert_keys($keys);
        if ($res['ok']) {
            $fix_notice = sprintf(__('Reverted %d directive(s) from config file: %s', 'phpinfo-wp'), count($keys), esc_html(implode(', ', $keys)));
        } else {
            $fix_notice = $res['error'];
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
    } elseif (isset($_POST['phpinfowp_recheck_overrides']) && check_admin_referer('phpinfowp_recheck_overrides_nonce')) {
        $settle = Phpinfo_WP_Config_Grader_Fixer::schedule_recheck();
        $mins   = $settle > 60 ? ceil($settle / 60) . ' minute' . (ceil($settle / 60) === 1.0 ? '' : 's') : $settle . ' seconds';
        $fix_notice = sprintf(
            __('Scheduled server re-check. PHP-FPM reloads configuration in about <strong>%s</strong>. We\'ll automatically evaluate runtime values once the cache clears.', 'phpinfo-wp'),
            esc_html($mins)
        );
        $fix_notice_type = 'success';
    } elseif (isset($_POST['phpinfowp_dismiss_overrides']) && check_admin_referer('phpinfowp_dismiss_overrides_nonce')) {
        update_user_meta(get_current_user_id(), 'phpinfowp_dismissed_override_notice', 1);
        $fix_notice = __('Override advisory dismissed.', 'phpinfo-wp');
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

$status_icon  = ['pass' => '✓', 'warn' => '⚠', 'fail' => '✗', 'host_managed' => '⚙'];
$status_color = ['pass' => '#00a32a', 'warn' => '#996800', 'fail' => '#d63638', 'host_managed' => '#64748b'];

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

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>
                <?php _e('Config Grader', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('PHP configuration scored against WordPress, WooCommerce, and security best practices.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>

    <?php if ($fix_notice): ?>
        <div class="notice notice-<?php echo esc_attr($fix_notice_type); ?> is-dismissible inline piwp-notice" style="margin:0 0 18px;padding:12px 16px"><p><?php echo $fix_notice; ?></p></div>
    <?php endif; ?>

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

    // Determine fixable failing/warning checks and check what is already on disk
    $fixable_keys    = [];
    foreach ($checks as $c) {
        if ($c['status'] === 'pass') continue;
        if (Phpinfo_WP_Config_Grader_Fixer::can_fix($c['key'])) $fixable_keys[] = $c['key'];
    }
    $managed_on_disk = Phpinfo_WP_Config_Grader_Fixer::get_managed_directives();
    $unwritten_keys  = Phpinfo_WP_Config_Grader_Fixer::get_unwritten_keys($fixable_keys);
    $overrides       = Phpinfo_WP_Config_Grader_Fixer::detect_overrides();
    ?>

    <?php if (!empty($unwritten_keys) && $target_writable): ?>
        <?php $profile = Phpinfo_WP_Config_Grader::dominant_profile($ctx); ?>
        <?php if ($profile): ?>
            <div class="phpinfowp-action-banner phpinfowp-profile-banner" style="background:linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%) !important; border:1px solid #4338ca !important; border-radius:10px; padding:20px 24px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; gap:24px; flex-wrap:wrap; box-shadow:0 4px 20px rgba(49,46,129,0.25);">
                <div style="flex:1; min-width:300px;">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                        <span style="font-size:24px; line-height:1;"><?php echo esc_html($profile['icon']); ?></span>
                        <strong style="font-size:16px; font-weight:800; color:#ffffff !important; letter-spacing:0.2px;"><?php echo esc_html($profile['name']); ?> Detected</strong>
                    </div>
                    <p style="margin:0 0 8px; color:#e0e7ff !important; font-size:13px; line-height:1.5;">
                        <?php echo esc_html($profile['desc']); ?>
                    </p>
                    <div style="color:#c7d2fe !important; font-size:12px; display:inline-flex; align-items:center; gap:6px;">
                        <span><?php _e("We'll automatically apply the optimal configuration to", 'phpinfo-wp'); ?></span>
                        <code style="background:rgba(255,255,255,0.15) !important; color:#ffffff !important; border:1px solid rgba(255,255,255,0.25) !important; padding:2px 6px; border-radius:4px; font-size:12px; font-weight:700;"><?php echo esc_html(basename($target_info['file'])); ?></code>
                    </div>
                </div>
                <form method="post" style="margin:0">
                    <?php wp_nonce_field('phpinfowp_autofix_nonce'); ?>
                    <input type="hidden" name="phpinfowp_autofix" value="1">
                    <?php foreach ($unwritten_keys as $k): ?>
                        <input type="hidden" name="fix_keys[]" value="<?php echo esc_attr($k); ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="button button-primary" style="font-size:13.5px; padding:8px 20px; height:auto; background:#fbbf24 !important; border-color:#fbbf24 !important; color:#1e1b4b !important; font-weight:800 !important; border-radius:6px; box-shadow:0 2px 8px rgba(251,191,36,0.3);"><?php _e('1-Click Optimize Server →', 'phpinfo-wp'); ?></button>
                </form>
            </div>
        <?php else: ?>
            <div class="phpinfowp-action-banner" style="background:linear-gradient(135deg, #f8f8fc 0%, #eff0f9 100%); border:1px solid #d8daeb; border-radius:8px; padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; box-shadow:0 1px 3px rgba(119,123,179,0.06);">
                <div>
                    <strong style="font-size:14px; color:#2c2f48; display:flex; align-items:center; gap:6px;">⚡ Optimize Server Profile (<?php echo count($unwritten_keys); ?> missing directives)</strong>
                    <p style="margin:4px 0 0; color:#555870; font-size:12.5px; line-height:1.5;">
                        Writes our recommended WordPress defaults to <code><?php echo esc_html(basename($target_info['file'])); ?></code> with automatic rollback on errors.
                    </p>
                </div>
                <form method="post" style="margin:0">
                    <?php wp_nonce_field('phpinfowp_autofix_nonce'); ?>
                    <input type="hidden" name="phpinfowp_autofix" value="1">
                    <?php foreach ($unwritten_keys as $k): ?>
                        <input type="hidden" name="fix_keys[]" value="<?php echo esc_attr($k); ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="button button-primary" style="background:#777BB3; border-color:#777BB3; color:#fff; font-weight:600; padding:6px 16px; border-radius:6px;"><?php _e('Apply Server Profile →', 'phpinfo-wp'); ?></button>
                </form>
            </div>
        <?php endif; ?>
    <?php elseif (!empty($unwritten_keys) && !$target_writable): ?>
        <div class="notice notice-warning inline piwp-notice" style="margin:0 0 18px"><p>
            <strong><?php _e('Auto-fix unavailable:', 'phpinfo-wp'); ?></strong> Your site root or <code><?php echo esc_html(basename($target_info['file'])); ?></code> is not writable by PHP. Fix permissions, or apply the recommended values manually via the <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-htaccess')); ?>">PHP Config editor</a>.
        </p></div>
    <?php endif; ?>




    <?php
    // Sorter function: Strictly sort Red (fail) -> Yellow (warn) -> Green (pass)
    $sort_checks = function (array $items): array {
        usort($items, function ($a, $b) {
            $order = ['fail' => 1, 'warn' => 2, 'pass' => 3];
            $oa = $order[$a['status']] ?? 99;
            $ob = $order[$b['status']] ?? 99;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }
            $sev_order = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
            $sa = $sev_order[$a['severity'] ?? 'low'] ?? 99;
            $sb = $sev_order[$b['severity'] ?? 'low'] ?? 99;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            return strcmp($a['key'], $b['key']);
        });
        return $items;
    };

    $failing = array_filter($checks, function ($c) {
        $ctl = $c['controllability'] ?? ($c['status'] === 'host_locked' ? 'host_locked' : 'actionable');
        return $ctl === 'actionable' && $c['status'] === 'fail';
    });
    $warning = array_filter($checks, function ($c) {
        $ctl = $c['controllability'] ?? ($c['status'] === 'host_locked' ? 'host_locked' : 'actionable');
        return $ctl === 'actionable' && $c['status'] === 'warn';
    });
    $passing = array_filter($checks, function ($c) {
        return ($c['health_status'] ?? $c['status']) === 'pass';
    });
    $host_arch_checks = array_filter($checks, function ($c) {
        $ctl = $c['controllability'] ?? ($c['status'] === 'host_locked' ? 'host_locked' : 'actionable');
        return $ctl === 'host_locked';
    });
    // Sort locked checks so critical/failing items appear first
    usort($host_arch_checks, function ($a, $b) {
        $a_has_issue = (!empty($a['live_evidence']) || ($a['health_status'] ?? '') === 'fail');
        $b_has_issue = (!empty($b['live_evidence']) || ($b['health_status'] ?? '') === 'fail');
        if ($a_has_issue !== $b_has_issue) {
            return $a_has_issue ? -1 : 1;
        }
        $sev_order = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
        $sa = $sev_order[$a['severity'] ?? 'low'] ?? 99;
        $sb = $sev_order[$b['severity'] ?? 'low'] ?? 99;
        return $sa <=> $sb;
    });

    $override_keys     = array_column($overrides, 'key');
    // For legacy fallback, also gather any 'host_managed' that might exist
    $legacy_locked     = array_filter($checks, function ($c) { return $c['status'] === 'host_managed'; });
    $all_locked_keys   = array_unique(array_merge(array_column($host_arch_checks, 'key'), array_column($overrides, 'key'), array_column($legacy_locked, 'key')));
    $locked_total      = count($all_locked_keys);

    $issue_total = count($failing) + count($warning);
    $active_tab  = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'issues';
    
    // Map legacy or direct category tab URLs
    $legacy_cats = ['performance', 'security', 'opcache', 'configuration', 'all'];
    $active_cat  = isset($_GET['cat']) ? sanitize_key($_GET['cat']) : 'all';
    if (in_array($active_tab, $legacy_cats, true)) {
        $active_cat = $active_tab;
        $active_tab = 'directives';
    }
    if (!in_array($active_tab, ['issues', 'directives', 'consistency', 'locked'], true)) {
        $active_tab = 'issues';
    }

    // Pre-calculate categorized and sorted data
    $cat_data = [];
    foreach ($categories as $cat) {
        $cat_checks = array_filter($checks, function ($c) use ($cat) { return $c['category'] === $cat; });
        $cat_fails  = count(array_filter($cat_checks, function ($c) { return $c['status'] === 'fail'; }));
        $cat_warns  = count(array_filter($cat_checks, function ($c) { return $c['status'] === 'warn'; }));
        $cat_data[$cat] = [
            'checks' => $sort_checks($cat_checks),
            'fails'  => $cat_fails,
            'warns'  => $cat_warns,
            'issues' => $cat_fails + $cat_warns,
            'total'  => count($cat_checks),
        ];
    }

    // Reusable single check card renderer
    $render_check_card = function ($c) use ($status_color, $sev_pill, $managed_on_disk, $settle_left) {
        $is_host = ($c['status'] === 'host_managed');
        $is_propagating = ($c['status'] !== 'pass' && isset($managed_on_disk[$c['key']]) && $settle_left > 0);
        
        // Base styling off Severity, not just a binary Pass/Fail
        if ($is_propagating) {
            $color = '#d97706';
            $bg    = '#fffdf5';
            $dicon = 'dashicons-clock';
        } elseif ($c['status'] === 'pass') {
            $color = '#00a32a';
            $bg    = '#f0faf2';
            $dicon = 'dashicons-yes-alt';
        } elseif ($is_host) {
            $color = '#64748b';
            $bg    = '#f8fafc';
            $dicon = 'dashicons-admin-generic';
        } elseif ($is_host || $c['status'] === 'host_locked' || ($c['controllability'] ?? '') === 'host_locked') {
            $is_failing_locked = ($c['health_status'] ?? '') === 'fail' || !empty($c['live_evidence']);
            if ($is_failing_locked) {
                $sev   = $c['severity'] ?? 'high';
                $color = ($sev === 'critical') ? '#d63638' : '#d97706';
                $bg    = ($sev === 'critical') ? '#fff4f4' : '#fffbeb';
                $dicon = 'dashicons-warning';
            } else {
                $color = '#64748b';
                $bg    = '#f8fafc';
                $dicon = 'dashicons-lock';
            }
        } else {
            // It's a fail or warn. Style based on severity.
            $sev = $c['severity'] ?? 'low';
            if ($sev === 'critical') {
                $color = '#d63638';
                $bg    = '#fff4f4';
                $dicon = 'dashicons-dismiss';
            } elseif ($sev === 'high') {
                $color = '#d97706';
                $bg    = '#fffbeb';
                $dicon = 'dashicons-warning';
            } elseif ($sev === 'medium') {
                $color = '#6366f1';
                $bg    = '#e0e7ff';
                $dicon = 'dashicons-info-outline';
            } else { // low
                $color = '#64748b';
                $bg    = '#f8fafc';
                $dicon = 'dashicons-info';
            }
        }
        ?>
        <div class="phpinfowp-grade-check" style="border-left-color:<?php echo $color; ?>;background:<?php echo $bg; ?>">
            <div class="phpinfowp-grade-check-icon">
                <span class="dashicons <?php echo $dicon; ?>" style="color:<?php echo $color; ?>"></span>
            </div>
            <div class="phpinfowp-grade-check-body">
                <div class="phpinfowp-grade-check-key">
                    <code><?php echo esc_html($c['key']); ?></code>
                    <span class="phpinfowp-grade-check-cat" style="font-size:11px;color:#888;margin-left:6px;font-weight:500">[<?php echo esc_html($c['category'] ?? ''); ?>]</span>
                    <?php if ($is_propagating): ?>
                        <span class="phpinfowp-sev" style="color:#d97706;border-color:#fde68a;background:#fefce8;font-weight:600"><?php _e('Propagating', 'phpinfo-wp'); ?></span>
                    <?php elseif ($is_host): ?>
                        <span class="phpinfowp-sev" style="color:#475569;border-color:#cbd5e1;background:#f1f5f9;font-weight:600"><?php _e('Host / php.ini', 'phpinfo-wp'); ?></span>
                    <?php elseif ($c['status'] === 'host_locked' || ($c['controllability'] ?? '') === 'host_locked'): ?>
                        <span class="phpinfowp-sev" style="color:#475569;border-color:#cbd5e1;background:#f1f5f9;font-weight:600"><?php _e('Host Locked', 'phpinfo-wp'); ?></span>
                        <?php if (($c['health_status'] ?? '') !== 'pass' && !empty($c['severity'])): ?>
                            <?php echo $sev_pill($c['severity'], $c['severity_label']); ?>
                        <?php endif; ?>
                    <?php elseif ($c['status'] !== 'pass'): ?>
                        <?php echo $sev_pill($c['severity'], $c['severity_label']); ?>
                    <?php endif; ?>
                    <?php if (!$is_host && $c['status'] !== 'pass' && ($c['controllability'] ?? '') !== 'host_locked' && Phpinfo_WP_Config_Grader_Fixer::can_fix($c['key'])): ?>
                        <?php if ($is_propagating): ?>
                            <span class="phpinfowp-sev" style="margin-left:8px;vertical-align:middle;color:#15803d;border-color:#bbf7d0;background:#f0fdf4;font-weight:600;display:inline-flex;align-items:center;gap:4px">
                                <span class="dashicons dashicons-yes-alt" style="font-size:14px;width:14px;height:14px;line-height:14px"></span>
                                <?php _e('Applied (Awaiting PHP Reload)', 'phpinfo-wp'); ?>
                            </span>
                        <?php else: ?>
                            <button type="button" class="button button-small phpinfowp-trigger-autofix"
                                     style="margin-left:8px;vertical-align:middle"
                                     data-key="<?php echo esc_attr($c['key']); ?>"
                                     data-current="<?php echo esc_attr($c['value']); ?>"
                                     data-recommended="<?php echo esc_attr($c['target_label'] ?? $c['good']); ?>">
                                <?php _e('Fix this →', 'phpinfo-wp'); ?>
                            </button>
                        <?php endif; ?>
                    <?php elseif ($is_host || ($c['controllability'] ?? '') === 'host_locked' || $c['status'] === 'host_locked'): ?>
                        <span style="margin-left:8px;font-size:11px;color:#64748b;font-style:italic"><?php _e('server-level setting · requires host / php.ini update', 'phpinfo-wp'); ?></span>
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
        <?php
    };
    ?>

    <!-- Top Alert for Host-Locked Bottlenecks -->
    <?php
    $critical_locked_count = 0;
    foreach ($host_arch_checks as $c) {
        if (!empty($c['live_evidence']) || ($c['health_status'] ?? '') === 'fail') {
            $critical_locked_count++;
        }
    }
    $is_bottleneck_dismissed = (bool) get_user_meta(get_current_user_id(), 'phpinfowp_dismiss_server_bottleneck', true);
    if ($critical_locked_count > 0 && $active_tab !== 'locked' && !$is_bottleneck_dismissed):
    ?>
        <div id="phpinfowp-server-bottleneck-banner" style="background:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #f59e0b; border-radius:8px; padding:12px 18px; margin:0 0 20px; display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; box-shadow:0 1px 3px rgba(0,0,0,0.02); transition:opacity 0.2s ease, transform 0.2s ease;">
            <div style="display:flex; align-items:center; gap:10px; min-width:280px; flex:1;">
                <span class="dashicons dashicons-warning" style="color:#d97706; font-size:20px; width:20px; height:20px; flex-shrink:0;"></span>
                <span style="font-size:13px; color:#92400e; line-height:1.4;">
                    <strong><?php _e('Server Bottleneck Detected:', 'phpinfo-wp'); ?></strong>
                    <?php printf(
                        _n(
                            '%d host-locked setting requires adjustment in your server\'s php.ini or by your web host.',
                            '%d host-locked settings require adjustment in your server\'s php.ini or by your web host.',
                            $critical_locked_count,
                            'phpinfo-wp'
                        ),
                        $critical_locked_count
                    ); ?>
                </span>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-config-grader', 'tab' => 'locked'], admin_url('admin.php'))); ?>" class="button button-secondary" style="font-size:12px; height:28px; line-height:26px; white-space:nowrap;">
                    <?php _e('View Host-Locked Directives &rarr;', 'phpinfo-wp'); ?>
                </a>
                <button type="button" id="phpinfowp-bottleneck-dismiss-btn" title="<?php esc_attr_e('Dismiss notice', 'phpinfo-wp'); ?>" aria-label="<?php esc_attr_e('Dismiss notice', 'phpinfo-wp'); ?>" style="background:transparent; border:none; color:#b45309; cursor:pointer; padding:4px; display:inline-flex; align-items:center; justify-content:center; border-radius:4px; line-height:1; transition:all 0.15s ease;" onmouseover="this.style.background='rgba(245,158,11,0.2)'; this.style.color='#78350f';" onmouseout="this.style.background='transparent'; this.style.color='#b45309';">
                    <span class="dashicons dashicons-no-alt" style="font-size:18px; width:18px; height:18px;"></span>
                </button>
            </div>
        </div>
        <script>
        (function(){
            var banner = document.getElementById('phpinfowp-server-bottleneck-banner');
            if (localStorage.getItem('piwp_dismiss_server_bottleneck') === '1' && banner) {
                banner.style.display = 'none';
            }
            var btn = document.getElementById('phpinfowp-bottleneck-dismiss-btn');
            if (btn && banner) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    banner.style.opacity = '0';
                    banner.style.transform = 'translateY(-4px)';
                    setTimeout(function(){ banner.style.display = 'none'; }, 200);
                    localStorage.setItem('piwp_dismiss_server_bottleneck', '1');
                    if (window.fetch && typeof ajaxurl !== 'undefined') {
                        var formData = new FormData();
                        formData.append('action', 'phpinfowp_dismiss_server_bottleneck');
                        formData.append('nonce', '<?php echo esc_js(wp_create_nonce("phpinfowp_dismiss_bottleneck")); ?>');
                        fetch(ajaxurl, { method: 'POST', body: formData, credentials: 'same-origin' });
                    }
                });
            }
        })();
        </script>
    <?php endif; ?>

    <!-- Main Tab Navigation (Clean 3-Tab Architecture) -->
    <div class="phpinfowp-ug-tab-nav" style="margin:24px 0 20px;">
        <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-config-grader', 'tab' => 'issues'], admin_url('admin.php'))); ?>"
           class="phpinfowp-ug-tab-link <?php echo $active_tab === 'issues' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-warning" style="font-size:16px;width:16px;height:16px;"></span>
            <?php _e('Actionable Issues', 'phpinfo-wp'); ?>
            <?php if ($issue_total > 0): ?>
                <span class="phpinfowp-ug-tab-count" style="background:<?php echo count($failing) > 0 ? '#d63638' : '#dba617'; ?>">
                    <?php echo $issue_total; ?>
                </span>
            <?php else: ?>
                <span style="color:#00a32a;font-size:13px;font-weight:700;">✓</span>
            <?php endif; ?>
        </a>

        <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-config-grader', 'tab' => 'directives', 'cat' => 'all'], admin_url('admin.php'))); ?>"
           class="phpinfowp-ug-tab-link <?php echo $active_tab === 'directives' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-list-view" style="font-size:16px;width:16px;height:16px;"></span>
            <?php _e('All Directives & Categories', 'phpinfo-wp'); ?>
        </a>

        <?php if (!empty($cross)): ?>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-config-grader', 'tab' => 'consistency'], admin_url('admin.php'))); ?>"
               class="phpinfowp-ug-tab-link <?php echo $active_tab === 'consistency' ? 'is-active' : ''; ?>">
                <span class="dashicons dashicons-randomize" style="font-size:16px;width:16px;height:16px;"></span>
                <?php _e('Consistency Checks', 'phpinfo-wp'); ?>
                <span class="phpinfowp-ug-tab-count" style="background:#dba617;"><?php echo count($cross); ?></span>
            </a>
        <?php endif; ?>

        <?php if ($locked_total > 0): ?>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-config-grader', 'tab' => 'locked'], admin_url('admin.php'))); ?>"
               class="phpinfowp-ug-tab-link <?php echo $active_tab === 'locked' ? 'is-active' : ''; ?>">
                <span class="dashicons dashicons-lock" style="font-size:16px;width:16px;height:16px;"></span>
                <?php _e('Host & Server Locked', 'phpinfo-wp'); ?>
                <span class="phpinfowp-ug-tab-count" style="background:#64748b;"><?php echo $locked_total; ?></span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Active Tab Content -->
    <?php if ($active_tab === 'issues'): ?>
        <?php
        $issue_checks = $sort_checks(array_filter($checks, function ($c) {
            $ctl = $c['controllability'] ?? ($c['status'] === 'host_locked' ? 'host_locked' : 'actionable');
            return $ctl === 'actionable' && in_array($c['status'], ['fail', 'warn'], true);
        }));
        ?>
        <?php if (empty($issue_checks)): ?>
            <div class="phpinfowp-compat-empty" style="margin-top:16px;background:#f0faf2;border:1px solid #c3e6cb;border-radius:8px;padding:36px 20px;text-align:center">
                <span class="dashicons dashicons-yes-alt" style="font-size:48px;width:48px;height:48px;color:#00a32a"></span>
                <h3 style="color:#008a20;margin:12px 0 6px"><?php _e('All Actionable Directives Optimized!', 'phpinfo-wp'); ?></h3>
                <p style="color:#555;margin:0"><?php _e('Your PHP configuration is fully optimized. Server-level settings are managed directly by your web host.', 'phpinfo-wp'); ?></p>
            </div>
        <?php else: ?>
            <div class="phpinfowp-grade-checks">
                <?php foreach ($issue_checks as $c): ?>
                    <?php $render_check_card($c); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'consistency'): ?>
        <?php if (!empty($cross)): ?>
            <div class="phpinfowp-cg-cross" style="margin-top:10px">
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
        <?php else: ?>
            <div class="phpinfowp-compat-empty" style="margin-top:16px">
                <span class="dashicons dashicons-yes-alt" style="color:#00a32a;font-size:36px;width:36px;height:36px"></span>
                <h3><?php _e('No consistency contradictions', 'phpinfo-wp'); ?></h3>
                <p><?php _e('All related directives (e.g. memory_limit vs post_max_size vs upload_max_filesize) are properly aligned.', 'phpinfo-wp'); ?></p>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'directives'): ?>
        <!-- Secondary Category Sub-Filter Pills -->
        <div class="phpinfowp-cg-filter-bar">
            <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-config-grader', 'tab' => 'directives', 'cat' => 'all'], admin_url('admin.php'))); ?>"
               class="phpinfowp-cg-filter-pill <?php echo $active_cat === 'all' ? 'is-active' : ''; ?>">
                <?php _e('All Checks', 'phpinfo-wp'); ?>
                <span>(<?php echo count($checks); ?>)</span>
            </a>
            <?php
            $cat_icons = [
                'Performance'   => '⚡',
                'Security'      => '🔒',
                'OPcache'       => '⚡',
                'Configuration' => '⚙️',
            ];
            foreach ($categories as $cat):
                $c_slug = strtolower($cat);
                $c_info = $cat_data[$cat] ?? null;
                $c_icon = $cat_icons[$cat] ?? '📁';
            ?>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-config-grader', 'tab' => 'directives', 'cat' => $c_slug], admin_url('admin.php'))); ?>"
                   class="phpinfowp-cg-filter-pill <?php echo $active_cat === $c_slug ? 'is-active' : ''; ?>">
                    <span><?php echo $c_icon; ?> <?php echo esc_html($cat); ?></span>
                    <?php if ($c_info && $c_info['issues'] > 0): ?>
                        <span class="phpinfowp-ug-tab-count" style="background:<?php echo $c_info['fails'] > 0 ? '#d63638' : '#dba617'; ?>">
                            <?php echo $c_info['issues']; ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php
        if ($active_cat === 'all') {
            $filtered_checks = $sort_checks($checks);
        } else {
            $matching_cat = null;
            foreach ($categories as $cat) {
                if (strtolower($cat) === $active_cat) {
                    $matching_cat = $cat;
                    break;
                }
            }
            $filtered_checks = $matching_cat ? ($cat_data[$matching_cat]['checks'] ?? []) : [];
        }
        ?>

        <?php if (!empty($filtered_checks)): ?>
            <div class="phpinfowp-grade-checks">
                <?php foreach ($filtered_checks as $c): ?>
                    <?php $render_check_card($c); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="phpinfowp-compat-empty" style="margin-top:16px">
                <span class="dashicons dashicons-info"></span>
                <h3><?php _e('No checks found', 'phpinfo-wp'); ?></h3>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'locked'): ?>
        <?php if (!empty($overrides) || !empty($host_arch_checks)): ?>
            <?php if (!empty($overrides)): ?>
                <div class="phpinfowp-cg-locked-panel" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.04);margin-top:16px;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;border-bottom:1px solid #f1f5f9;padding-bottom:16px;">
                        <div style="width:40px;height:40px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#475569;font-size:20px;">
                            <span class="dashicons dashicons-lock" style="font-size:20px;width:20px;height:20px;"></span>
                        </div>
                        <div>
                            <h3 style="margin:0 0 4px;font-size:16px;color:#1e293b;"><?php _e('Server-Enforced Settings', 'phpinfo-wp'); ?></h3>
                            <p style="margin:0;color:#64748b;font-size:13px;"><?php _e('These settings were saved to your configuration file, but your web hosting provider enforces fixed limits on this server.', 'phpinfo-wp'); ?></p>
                        </div>
                    </div>

                    <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:20px;">
                        <thead>
                            <tr style="text-align:left;color:#64748b;border-bottom:2px solid #f1f5f9;background:#f8fafc;">
                                <th style="padding:10px 12px;font-weight:600;"><?php _e('Directive', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 12px;font-weight:600;"><?php _e('Value We Wrote', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 12px;font-weight:600;"><?php _e('Host Enforces', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 12px;font-weight:600;"><?php _e('Proof', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overrides as $o): ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px 12px;font-family:monospace;font-weight:600;color:#0f172a;"><?php echo esc_html($o['key']); ?></td>
                                <td style="padding:10px 12px;font-family:monospace;color:#15803d;font-weight:600;"><?php echo esc_html($o['expected']); ?></td>
                                <td style="padding:10px 12px;font-family:monospace;color:#475569;"><?php echo esc_html($o['actual'] === '' ? '(empty)' : $o['actual']); ?></td>
                                <td style="padding:10px 12px;">
                                    <?php
                                    $diag_method = $target_info['mode'] === 'htaccess' ? '.htaccess (php_value)' : '.user.ini';
                                    $diag_file   = $target_info['file'] ?? '';
                                    $diag_text   = sprintf(
                                        "Directive: %s\nValue attempted: %s\nValue observed after write: %s\nMethod: phpinfo() WP wrote via %s\nFile: %s\nTimestamp: %s UTC\n\nEmprical conclusion: phpinfo() WP attempted to write this value and the server explicitly rejected it. This is not a plugin limitation — your hosting environment is enforcing a ceiling on this directive. Please update your server-level php.ini or pool configuration to allow this value.",
                                        $o['key'],
                                        $o['expected'],
                                        $o['actual'] ?: '(empty/default)',
                                        $diag_method,
                                        $diag_file,
                                        gmdate('Y-m-d H:i:s')
                                    );
                                    ?>
                                    <button type="button"
                                            class="button button-small piwp-copy-diagnostic"
                                            data-report="<?php echo esc_attr($diag_text); ?>"
                                            style="font-size:11px;color:#475569;">
                                        <span class="dashicons dashicons-clipboard" style="font-size:13px;width:13px;height:13px;vertical-align:middle;margin-right:3px;"></span>
                                        <?php _e('Copy Diagnostic', 'phpinfo-wp'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;margin-bottom:20px;">
                        <strong style="color:#334155;font-size:13px;display:flex;align-items:center;gap:6px;">
                            <span class="dashicons dashicons-info" style="color:#64748b;font-size:16px;width:16px;height:16px;"></span>
                            <?php _e('Why does this happen?', 'phpinfo-wp'); ?>
                        </strong>
                        <p style="margin:6px 0 0;color:#64748b;font-size:12.5px;line-height:1.5;">
                            <?php _e('Web hosts set fixed limits on their servers to ensure stability across accounts. WordPress plugins cannot override these server-wide limits directly. Your site is already fully optimized — if you ever need higher limits, your hosting provider or server administrator can adjust them.', 'phpinfo-wp'); ?>
                        </p>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                        <form method="post" style="margin:0">
                            <?php wp_nonce_field('phpinfowp_revert_keys_nonce'); ?>
                            <input type="hidden" name="phpinfowp_revert_keys" value="1">
                            <?php foreach ($overrides as $o): ?>
                                <input type="hidden" name="revert_keys[]" value="<?php echo esc_attr($o['key']); ?>">
                            <?php endforeach; ?>
                            <button type="submit" class="button button-secondary" style="color:#475569;"
                                    data-confirm="<?php echo esc_attr(sprintf(__('Revert the %d locked directive(s) from your config file?', 'phpinfo-wp'), count($overrides))); ?>"
                                    data-confirm-title="<?php esc_attr_e('Revert Locked Directives', 'phpinfo-wp'); ?>"
                                    data-confirm-btn="<?php esc_attr_e('Revert Directives', 'phpinfo-wp'); ?>">
                                <?php printf(__('↺ Revert %s from Config File', 'phpinfo-wp'), esc_html(implode(', ', array_column($overrides, 'key')))); ?>
                            </button>
                        </form>
                        <form method="post" style="margin:0">
                            <?php wp_nonce_field('phpinfowp_recheck_overrides_nonce'); ?>
                            <input type="hidden" name="phpinfowp_recheck_overrides" value="1">
                            <button type="submit" class="button button-secondary">
                                <span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;"></span>
                                <?php _e('Re-check Status', 'phpinfo-wp'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($host_arch_checks)): ?>
                <div style="margin-top:24px;">
                    <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:20px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                        <div>
                            <h4 style="margin:0 0 4px; font-size:15px; color:#0f172a; font-weight:700; display:flex; align-items:center; gap:6px;">
                                <span class="dashicons dashicons-email-alt" style="color:#64748b;"></span>
                                <?php _e('Need these fixed? Ask your Host.', 'phpinfo-wp'); ?>
                            </h4>
                            <p style="margin:0; font-size:13px; color:#475569;"><?php _e('Generate a pre-written support request to copy-paste to your hosting provider.', 'phpinfo-wp'); ?></p>
                        </div>
                        <button type="button" class="button button-primary" id="piwp-generate-ticket-btn" style="background:#0f172a; border-color:#0f172a; color:#fff; font-weight:600; padding:4px 16px;">
                            <?php _e('Generate Host Ticket &rarr;', 'phpinfo-wp'); ?>
                        </button>
                    </div>

                    <h3 style="font-size:15px;color:#1e293b;margin:0 0 12px;display:flex;align-items:center;gap:8px;">
                        <span class="dashicons dashicons-admin-generic" style="color:#64748b;font-size:18px;width:18px;height:18px;"></span>
                        <?php _e('Server-Level PHP Settings', 'phpinfo-wp'); ?>
                        <span style="font-size:12px;font-weight:normal;color:#64748b;">(<?php _e('Managed directly by your web host', 'phpinfo-wp'); ?>)</span>
                    </h3>
                    <div class="phpinfowp-grade-checks">
                        <?php foreach ($host_arch_checks as $c): ?>
                            <?php $render_check_card($c); ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var btn = document.getElementById('piwp-generate-ticket-btn');
                    if (!btn) return;
                    btn.addEventListener('click', function() {
                        var checks = <?php echo json_encode(array_values($host_arch_checks)); ?>;
                        var overrides = <?php echo json_encode(array_values($overrides)); ?>;
                        var lines = [];
                        checks.forEach(function(c) {
                            if (c.target) lines.push("- " + c.key + " = " + c.target);
                        });
                        overrides.forEach(function(o) {
                            if (o.expected) lines.push("- " + o.key + " = " + o.expected);
                        });
                        
                        var text = "Hi Support Team,\n\nI am auditing my WordPress site's performance and security requirements. Could you please update my server's core php.ini to apply the following missing directives?\n\n";
                        text += lines.join("\n");
                        text += "\n\nPlease let me know when this is applied. Thank you!";
                        
                        // Simple modal creation
                        var overlay = document.createElement('div');
                        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:99999;display:flex;align-items:center;justify-content:center;';
                        var modal = document.createElement('div');
                        modal.style.cssText = 'background:#fff;padding:24px;border-radius:12px;width:90%;max-width:500px;box-shadow:0 10px 25px rgba(0,0,0,0.2);';
                        modal.innerHTML = '<h3 style="margin:0 0 12px;font-size:18px;">Support Ticket Template</h3>' +
                            '<p style="margin:0 0 16px;color:#475569;font-size:13px;">Copy the text below and send it to your hosting provider\'s support team.</p>' +
                            '<textarea readonly style="width:100%;height:200px;font-family:monospace;font-size:13px;padding:12px;margin-bottom:16px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;" id="piwp-ticket-text">' + text + '</textarea>' +
                            '<div style="display:flex;gap:12px;justify-content:flex-end;">' +
                                '<button type="button" class="button" id="piwp-ticket-close">Close</button>' +
                                '<button type="button" class="button button-primary" id="piwp-ticket-copy">Copy to Clipboard</button>' +
                            '</div>';
                        overlay.appendChild(modal);
                        document.body.appendChild(overlay);
                        
                        document.getElementById('piwp-ticket-close').addEventListener('click', function() { overlay.remove(); });
                        document.getElementById('piwp-ticket-copy').addEventListener('click', function() {
                            var ta = document.getElementById('piwp-ticket-text');
                            ta.select();
                            document.execCommand('copy');
                            this.innerHTML = 'Copied! ✓';
                            this.style.background = '#00a32a';
                            this.style.borderColor = '#00a32a';
                            setTimeout(() => { overlay.remove(); }, 1500);
                        });
                    });
                });
                </script>
            <?php endif; ?>
        <?php else: ?>
            <div class="phpinfowp-compat-empty" style="margin-top:16px;background:#f0faf2;border:1px solid #c3e6cb;border-radius:8px;padding:36px 20px;text-align:center">
                <span class="dashicons dashicons-yes-alt" style="font-size:48px;width:48px;height:48px;color:#00a32a"></span>
                <h3 style="color:#008a20;margin:12px 0 6px"><?php _e('No Server Locks Detected', 'phpinfo-wp'); ?></h3>
                <p style="color:#555;margin:0"><?php _e('All configuration directives written to your runtime files are taking full effect.', 'phpinfo-wp'); ?></p>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <p class="description" style="margin-top:20px">
        Some directives differ between production and development. Review context before changing values.
        Use the <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-htaccess')); ?>">PHP Config editor</a> to apply changes manually.
    </p>
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

    // Copy Diagnostic Report button — for host-blocked directives in the Locked tab
    $(document).on('click', '.piwp-copy-diagnostic', function() {
        var btn  = $(this);
        var text = btn.data('report');
        var original = btn.html();

        var doCopy = function(t) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(t).then(function() {
                    btn.html('<span class="dashicons dashicons-yes" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span> Copied!');
                    setTimeout(function() { btn.html(original); }, 2000);
                });
            } else {
                // Fallback for non-HTTPS or older browsers
                var ta = document.createElement('textarea');
                ta.value = t;
                ta.style.cssText = 'position:fixed;top:-999px;left:-999px;opacity:0;';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                try { document.execCommand('copy'); } catch(e) {}
                document.body.removeChild(ta);
                btn.html('<span class="dashicons dashicons-yes" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span> Copied!');
                setTimeout(function() { btn.html(original); }, 2000);
            }
        };

        doCopy(text);
    });
});
</script>
