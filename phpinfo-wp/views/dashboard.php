<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

global $wpdb;

$is_pro = Phpinfo_WP_License::is_valid();

// 1. Config Grader Telemetry — using run() to obtain real checks
$full_grader = class_exists('Phpinfo_WP_Config_Grader') ? Phpinfo_WP_Config_Grader::run() : [];
$grader_summary = class_exists('Phpinfo_WP_Config_Grader') ? Phpinfo_WP_Config_Grader::summary() : [];

// Primary display: controllable score (excludes host-locked items the user can't fix)
$grade  = $full_grader['grade'] ?? ($grader_summary['grade'] ?? 'A');
$score        = (int) ($full_grader['score'] ?? ($grader_summary['score'] ?? 85));
$grade_ctl    = $grader_summary['grade_controllable'] ?? $full_grader['grade_controllable'] ?? $grade;
$score_ctl    = (int) ($grader_summary['score_controllable'] ?? $full_grader['score_controllable'] ?? $score);
$is_capped    = $is_pro && (!empty($grader_summary['is_capped']) || !empty($full_grader['is_capped']));
$grade_eff    = $grader_summary['grade_effective'] ?? ($full_grader['grade_effective'] ?? $grade_ctl);
$score_eff    = (int) ($grader_summary['score_effective'] ?? ($full_grader['score_effective'] ?? $score_ctl));
$show_dual    = ($score !== $score_ctl); // true when host-locked items are pulling the real score down

$passes = (int) ($grader_summary['passes'] ?? 0);
$warns  = (int) ($grader_summary['warns'] ?? 0);
$fails  = (int) ($grader_summary['fails'] ?? 0);
$total_directives = (int) ($grader_summary['total'] ?? 30);

if ($passes === 0 && $warns === 0 && $fails === 0 && !empty($full_grader['checks'])) {
    foreach ($full_grader['checks'] as $c) {
        $ctl = $c['controllability'] ?? ($c['status'] === 'host_locked' ? 'host_locked' : 'actionable');
        if ($c['status'] === 'pass') $passes++;
        elseif ($ctl === 'actionable' && $c['status'] === 'warn') $warns++;
        elseif ($ctl === 'actionable' && $c['status'] === 'fail') $fails++;
    }
    $total_directives = count($full_grader['checks']);
}

$has_autofixable = false;
if (class_exists('Phpinfo_WP_Config_Grader_Fixer') && !empty($full_grader['checks'])) {
    foreach ($full_grader['checks'] as $c) {
        if (($c['status'] === 'fail' || $c['status'] === 'warn') && Phpinfo_WP_Config_Grader_Fixer::can_fix($c['key'])) {
            $has_autofixable = true;
            break;
        }
    }
}

// Grade Colors based on effective score & capping
if ($is_capped) {
    $grade_color = $score_eff >= 85 ? '#16a34a' : ($score_eff >= 75 ? '#d97706' : '#dc2626');
    $grade_bg    = $score_eff >= 85 ? '#f0fdf4' : ($score_eff >= 75 ? '#fffbeb' : '#fef2f2');
} else {
    $grade_color = $score_ctl < 60 ? '#dc2626' : ($score_ctl < 80 ? '#d97706' : '#16a34a');
    $grade_bg    = $score_ctl < 60 ? '#fef2f2' : ($score_ctl < 80 ? '#fffbeb' : '#f0fdf4');
}
$grade_slug  = strtolower(str_replace('+', 'plus', $grade_eff));

// Dynamic colors for Server Reality badge
$score_server_bg    = ($score >= 90) ? '#dcfce7' : (($score >= 80) ? '#fef9c3' : (($score >= 70) ? '#ffedd5' : '#fee2e2'));
$score_server_color = ($score >= 90) ? '#15803d' : (($score >= 80) ? '#dba617' : (($score >= 70) ? '#ea580c' : '#dc2626'));

// 2. Memory & Admin Bar Status
$bar        = class_exists('Phpinfo_WP_Admin_Bar') ? Phpinfo_WP_Admin_Bar::status() : [];
$mem        = $bar['memory'] ?? ['peak_bytes' => memory_get_usage(true), 'limit_label' => ini_get('memory_limit'), 'pct' => 0];
$mem_pct    = (int) ($mem['pct'] ?? 0);
$mem_peak_b = (int) ($mem['peak_bytes'] ?? memory_get_usage(true));
$mem_color  = $mem_pct > 85 ? '#dc2626' : ($mem_pct > 65 ? '#d97706' : '#16a34a');
$dasharray  = $mem_pct . ' ' . (100 - $mem_pct);

// 3. EOL Status
$eol = class_exists('Phpinfo_WP_EOL') ? Phpinfo_WP_EOL::status() : ['status' => 'ok', 'minor' => PHP_VERSION, 'days' => 365, 'eol' => ''];
$eol_status = $eol['status'] ?? 'ok';
$eol_badge_class = $eol_status === 'eol' ? 'badge-danger' : ($eol_status === 'warning' ? 'badge-warning' : 'badge-success');
$eol_badge_text  = $eol_status === 'eol' ? __('EOL (Unsupported)', 'phpinfo-wp') : ($eol_status === 'warning' ? sprintf(__('EOL in %dd', 'phpinfo-wp'), (int)$eol['days']) : __('Supported ✓', 'phpinfo-wp'));

// 4. Server Specs
$web_server = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
if (stripos($web_server, 'litespeed') !== false) {
    $server_short = 'LiteSpeed';
} elseif (stripos($web_server, 'nginx') !== false) {
    $server_short = 'Nginx';
} elseif (stripos($web_server, 'apache') !== false) {
    $server_short = 'Apache';
} elseif (stripos($web_server, 'caddy') !== false) {
    $server_short = 'Caddy';
} elseif (stripos($web_server, 'iis') !== false) {
    $server_short = 'Microsoft IIS';
} else {
    $server_short = explode('/', $web_server)[0] ?? 'Web Server';
}

$db_version = $wpdb->get_var('SELECT VERSION()') ?? 'MySQL';
$db_software = (stripos($db_version, 'mariadb') !== false) ? 'MariaDB ' . explode('-', $db_version)[0] : 'MySQL ' . explode('-', $db_version)[0];
$wp_core_version = get_bloginfo('version');
$max_exec = ini_get('max_execution_time') ? ini_get('max_execution_time') . 's' : '—';
$upload_max = ini_get('upload_max_filesize') ?: '—';
$post_max = ini_get('post_max_size') ?: '—';
$max_vars = ini_get('max_input_vars') ? number_format((int)ini_get('max_input_vars')) : '—';

// 5. Database Autoload Telemetry
$db_stats = class_exists('Phpinfo_WP_DB_Health') ? Phpinfo_WP_DB_Health::autoload_size() : ['total_kb' => 0, 'bytes' => 0];
if (!is_array($db_stats)) $db_stats = ['total_kb' => 0, 'bytes' => 0];
$db_bytes = (int) ($db_stats['bytes'] ?? 0);
$db_kb    = round($db_bytes / 1024, 1);
$db_pct   = min(100, ($db_kb / 1000) * 100);
$db_color = $db_kb > 800 ? '#dc2626' : ($db_kb > 400 ? '#d97706' : '#16a34a');
$db_status_text = $db_kb > 800 ? __('High TTFB Impact', 'phpinfo-wp') : ($db_kb > 400 ? __('Moderate Size', 'phpinfo-wp') : __('Optimal (<400 KB)', 'phpinfo-wp'));

$db_size_info = class_exists('Phpinfo_WP_DB_Health') ? Phpinfo_WP_DB_Health::db_size() : [];
$total_db_size_fmt = isset($db_size_info['total']) ? size_format($db_size_info['total']) : '—';
$table_count = isset($db_size_info['tables']) ? (int) $db_size_info['tables'] : '—';

// 6. Outbound API Telemetry
$api_stats = class_exists('Phpinfo_WP_API_Monitor') ? Phpinfo_WP_API_Monitor::get_stats() : [];
if (!is_array($api_stats)) $api_stats = [];
$total_api_calls = 0;
$total_api_time  = 0.0;
$slowest_apis    = [];

foreach ($api_stats as $host => $data) {
    $total_api_calls += (int)($data['count'] ?? 0);
    $total_api_time  += (float)($data['total_time'] ?? 0);
    $slowest_apis[$host] = (float)($data['max_time'] ?? 0);
}
arsort($slowest_apis);
$top_apis = array_slice($slowest_apis, 0, 2, true);
$avg_api_time = $total_api_calls > 0 ? round($total_api_time / $total_api_calls, 2) : 0.0;

// 7. WP-Cron Telemetry
$cron_summary = class_exists('Phpinfo_WP_Cron_Monitor') ? Phpinfo_WP_Cron_Monitor::summary() : [
    'total' => 0, 'overdue' => 0, 'orphan' => 0, 'disabled' => false
];
$cron_total   = (int)($cron_summary['total'] ?? 0);
$cron_overdue = (int)($cron_summary['overdue'] ?? 0);
$cron_disabled = !empty($cron_summary['disabled']);

// 8. Triage & Recommendations Engine (Real Issues from Live Checks)
$triage_items = [];

// Check A: Failing Directives from Config Grader
if (!empty($full_grader['checks'])) {
    foreach ($full_grader['checks'] as $c) {
        if (($c['status'] ?? '') === 'fail') {
            $key = $c['key'] ?? '';
            $can_fix = class_exists('Phpinfo_WP_Config_Grader_Fixer') && Phpinfo_WP_Config_Grader_Fixer::can_fix($key);
            $link_lbl = ($is_pro && $can_fix) ? __('Auto-Fix', 'phpinfo-wp') : __('Review', 'phpinfo-wp');
            $triage_items[] = [
                'type'     => 'danger',
                'icon'     => 'dashicons-warning',
                'title'    => sprintf(__('PHP Directive Risk: %s', 'phpinfo-wp'), esc_html($c['label'] ?? $key)),
                'desc'     => sprintf(__('Current: "%s" (Target: %s) — %s', 'phpinfo-wp'), esc_html($c['value'] ?? ''), esc_html($c['target_label'] ?? ''), esc_html($c['note'] ?? '')),
                'link'     => admin_url('admin.php?page=piwp-config-grader'),
                'link_lbl' => $link_lbl,
            ];
            if (count($triage_items) >= 2) break;
        }
    }
}

// Check B: EOL PHP
if ($eol_status === 'eol') {
    $triage_items[] = [
        'type'     => 'danger',
        'icon'     => 'dashicons-calendar-alt',
        'title'    => sprintf(__('Critical: PHP %s is End-of-Life', 'phpinfo-wp'), esc_html($eol['minor'])),
        'desc'     => sprintf(__('Your PHP version stopped receiving security patches on %s. Upgrading ensures security and unlocks modern performance.', 'phpinfo-wp'), esc_html($eol['eol'] ?? '')),
        'link'     => admin_url('admin.php?page=piwp-eol'),
        'link_lbl' => __('View EOL Roadmap', 'phpinfo-wp'),
    ];
} elseif ($eol_status === 'warning') {
    $triage_items[] = [
        'type'     => 'warning',
        'icon'     => 'dashicons-calendar-alt',
        'title'    => sprintf(__('Upcoming EOL: PHP %s expires in %d days', 'phpinfo-wp'), esc_html($eol['minor']), (int)$eol['days']),
        'desc'     => sprintf(__('PHP %s reaches official End-of-Life on %s. Test your plugins before upgrading server PHP.', 'phpinfo-wp'), esc_html($eol['minor']), esc_html($eol['eol'] ?? '')),
        'link'     => admin_url('admin.php?page=piwp-compat'),
        'link_lbl' => __('Scan Compatibility', 'phpinfo-wp'),
    ];
}

// Check C: Overdue Crons
if ($cron_overdue > 0) {
    $triage_items[] = [
        'type'     => 'warning',
        'icon'     => 'dashicons-clock',
        'title'    => sprintf(__('%d Overdue WP-Cron Task(s)', 'phpinfo-wp'), $cron_overdue),
        'desc'     => __('Scheduled background jobs have passed their execution window. This can delay publishing, backups, or email sending.', 'phpinfo-wp'),
        'link'     => admin_url('admin.php?page=piwp-cron'),
        'link_lbl' => $is_pro ? __('Inspect Crons', 'phpinfo-wp') : __('WP-Cron (Pro)', 'phpinfo-wp'),
    ];
}

// Check D: Autoload Bloat
if ($db_kb > 800) {
    $triage_items[] = [
        'type'     => 'danger',
        'icon'     => 'dashicons-database',
        'title'    => sprintf(__('High Autoload Database Bloat (%s KB)', 'phpinfo-wp'), $db_kb),
        'desc'     => __('Your wp_options autoload data exceeds 800 KB, which slows down Time to First Byte (TTFB) on every request.', 'phpinfo-wp'),
        'link'     => admin_url('admin.php?page=piwp-db-health'),
        'link_lbl' => $is_pro ? __('Clean Autoload', 'phpinfo-wp') : __('DB Health (Pro)', 'phpinfo-wp'),
    ];
}

// Fallback: Warning directives if no fails
if (count($triage_items) < 3 && !empty($full_grader['checks'])) {
    foreach ($full_grader['checks'] as $c) {
        if (($c['status'] ?? '') === 'warn') {
            $key = $c['key'] ?? '';
            $can_fix = class_exists('Phpinfo_WP_Config_Grader_Fixer') && Phpinfo_WP_Config_Grader_Fixer::can_fix($key);
            $link_lbl = ($is_pro && $can_fix) ? __('Auto-Fix', 'phpinfo-wp') : __('Review', 'phpinfo-wp');
            $triage_items[] = [
                'type'     => 'warning',
                'icon'     => 'dashicons-info',
                'title'    => sprintf(__('Optimization Tip: %s', 'phpinfo-wp'), esc_html($c['label'] ?? $key)),
                'desc'     => sprintf(__('Current: "%s" (Target: %s) — %s', 'phpinfo-wp'), esc_html($c['value'] ?? ''), esc_html($c['target_label'] ?? ''), esc_html($c['note'] ?? '')),
                'link'     => admin_url('admin.php?page=piwp-config-grader'),
                'link_lbl' => $link_lbl,
            ];
            if (count($triage_items) >= 3) break;
        }
    }
}

// Check E: Consistency Contradictions (from Config Grader)
if (count($triage_items) < 3 && !empty($full_grader['cross'])) {
    foreach ($full_grader['cross'] as $x) {
        $triage_items[] = [
            'type'     => 'warning',
            'icon'     => 'dashicons-randomize',
            'title'    => sprintf(__('Consistency Issue: %s', 'phpinfo-wp'), esc_html($x['label'] ?? '')),
            'desc'     => sprintf(__('%s — Fix: %s', 'phpinfo-wp'), esc_html($x['reason'] ?? ''), esc_html($x['fix'] ?? '')),
            'link'     => admin_url('admin.php?page=piwp-config-grader&tab=consistency'),
            'link_lbl' => __('Review', 'phpinfo-wp'),
        ];
        if (count($triage_items) >= 3) break;
    }
}

// Check F: Runtime PHP Errors Today
$today_errs = class_exists('Phpinfo_WP_Error_Log') ? Phpinfo_WP_Error_Log::today_count() : 0;
if ($today_errs > 0 && count($triage_items) < 3) {
    $triage_items[] = [
        'type'     => $today_errs >= 50 ? 'danger' : 'warning',
        'icon'     => 'dashicons-warning',
        'title'    => sprintf(__('%d PHP Errors Logged Today', 'phpinfo-wp'), $today_errs),
        'desc'     => __('Active runtime notices or errors were recorded today. Check stack traces to locate problematic code.', 'phpinfo-wp'),
        'link'     => admin_url('admin.php?page=piwp-error-log'),
        'link_lbl' => __('Inspect Log', 'phpinfo-wp'),
    ];
}

// Check G: Server Audit Cap Advisory — Pro only
if ($is_pro && empty($triage_items) && $is_capped && (int)$locked_n > 0) {
    $triage_items[] = [
        'type'     => 'warning',
        'icon'     => 'dashicons-lock',
        'title'    => sprintf(__('%d Server-Level Directives Affecting Overall Grade', 'phpinfo-wp'), (int)$locked_n),
        'desc'     => sprintf(__('Your site settings are 100%% optimal (%1$s), but %2$d server-level directives pull your server score down to %3$s (%4$d/100). Upgrade your hosting plan to resolve.', 'phpinfo-wp'), esc_html($grade_ctl), (int)$locked_n, esc_html($grade), (int)$score),
        'link'     => admin_url('admin.php?page=piwp-config-grader&tab=locked'),
        'link_lbl' => __('View Details', 'phpinfo-wp'),
    ];
}
?>

<div class="phpinfowp-pro-page phpinfowp-dashboard">

    <!-- 1. HERO HEADER -->
    <div class="phpinfowp-dash-topbar">
        <div>
            <div class="phpinfowp-dash-live-badge">
                <span class="phpinfowp-pulse-dot"></span>
                <?php _e('Live Server Telemetry', 'phpinfo-wp'); ?>
                <?php if (!$is_pro): ?>
                    <span class="phpinfowp-plan-pill"><?php _e('Free Plan', 'phpinfo-wp'); ?></span>
                <?php else: ?>
                    <span class="phpinfowp-plan-pill pro-active"><?php _e('Pro Active', 'phpinfo-wp'); ?></span>
                <?php endif; ?>
            </div>
            <h1 class="phpinfowp-dash-title"><?php _e('Server Intelligence & Health Command', 'phpinfo-wp'); ?></h1>
            <p class="phpinfowp-dash-subtitle"><?php _e('Real-time PHP environment, database efficiency, security verification, and system diagnostic overview.', 'phpinfo-wp'); ?></p>
        </div>
        <div class="phpinfowp-dash-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-viewer')); ?>" class="button button-secondary phpinfowp-btn-glow">
                <span class="dashicons dashicons-info"></span> <?php _e('phpinfo() Raw Viewer', 'phpinfo-wp'); ?>
            </a>
            <?php if (!$is_pro): ?>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary phpinfowp-btn-upgrade-top">
                    <span class="dashicons dashicons-star-filled" style="color:#fbbf24; font-size:14px; width:14px; height:14px; margin-right:4px;"></span>
                    <?php _e('Upgrade to Pro', 'phpinfo-wp'); ?>
                </a>
            <?php else: ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-config-grader')); ?>" class="button button-primary phpinfowp-btn-brand">
                    <span class="dashicons dashicons-shield"></span> <?php _e('Config Grader', 'phpinfo-wp'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. SYSTEM SPECS RIBBON (Free for all users) -->
    <div class="phpinfowp-specs-ribbon">
        <div class="phpinfowp-spec-item">
            <span class="phpinfowp-spec-label"><?php _e('PHP Version', 'phpinfo-wp'); ?></span>
            <div class="phpinfowp-spec-val-wrap">
                <strong class="phpinfowp-spec-value">PHP <?php echo esc_html(PHP_VERSION); ?></strong>
                <span class="phpinfowp-badge <?php echo esc_attr($eol_badge_class); ?>"><?php echo esc_html($eol_badge_text); ?></span>
            </div>
        </div>

        <div class="phpinfowp-spec-item">
            <span class="phpinfowp-spec-label"><?php _e('Web Server', 'phpinfo-wp'); ?></span>
            <strong class="phpinfowp-spec-value"><?php echo esc_html($server_short); ?></strong>
        </div>

        <div class="phpinfowp-spec-item">
            <span class="phpinfowp-spec-label"><?php _e('Database Engine', 'phpinfo-wp'); ?></span>
            <strong class="phpinfowp-spec-value"><?php echo esc_html($db_software); ?></strong>
        </div>

        <div class="phpinfowp-spec-item">
            <span class="phpinfowp-spec-label"><?php _e('WordPress Core', 'phpinfo-wp'); ?></span>
            <strong class="phpinfowp-spec-value">WP <?php echo esc_html($wp_core_version); ?></strong>
        </div>

        <div class="phpinfowp-spec-item">
            <span class="phpinfowp-spec-label"><?php _e('Memory Limit', 'phpinfo-wp'); ?></span>
            <strong class="phpinfowp-spec-value"><?php echo esc_html($mem['limit_label']); ?></strong>
        </div>

        <div class="phpinfowp-spec-item">
            <span class="phpinfowp-spec-label"><?php _e('Max Exec Time', 'phpinfo-wp'); ?></span>
            <strong class="phpinfowp-spec-value"><?php echo esc_html($max_exec); ?></strong>
        </div>
    </div>

    <!-- 3. COMMAND CENTER HERO GRID (Score + Triage) -->
    <div class="phpinfowp-command-grid">
        
        <!-- Hero Scorecard -->
        <div class="phpinfowp-command-card phpinfowp-hero-score">
            <div class="phpinfowp-hero-header">
                <div>
                    <h2 class="phpinfowp-card-heading"><?php _e('PHP Server Health & Configuration', 'phpinfo-wp'); ?></h2>
                    <p class="phpinfowp-card-subheading"><?php _e('Automated audit against production security & performance standards.', 'phpinfo-wp'); ?></p>
                </div>
                <div class="phpinfowp-grade-badge grade-<?php echo esc_attr($grade_slug); ?>" title="<?php echo ($show_dual && $is_pro) ? esc_attr(sprintf(__('Site Controllable: %1$s (%2$d/100). Server Reality: %3$s (%4$d/100)', 'phpinfo-wp'), $grade_ctl, $score_ctl, $grade, $score)) : ''; ?>">
                    <?php echo esc_html($grade_eff); ?><?php echo $is_capped ? '*' : ''; ?>
                </div>
            </div>

            <div class="phpinfowp-score-meter-wrap">
                <?php
                $locked_n = $grader_summary['locked_count'] ?? max(0, (int)($grader_summary['total'] ?? 0) - (int)($grader_summary['passes'] ?? 0) - (int)($grader_summary['fails'] ?? 0) - (int)($grader_summary['warns'] ?? 0));
                ?>
                <?php if ($show_dual): ?>
                    <?php if ($is_pro): ?>
                        <!-- DUAL TRACK: Site Controllable + Server Reality (Pro) -->
                        <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:12px;">
                            <!-- Track 1: Site Controllable -->
                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:5px;">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="font-size:13px; font-weight:700; color:#0f172a;"><?php _e('Site Controllable', 'phpinfo-wp'); ?></span>
                                        <span style="font-size:11px; font-weight:800; padding:1px 7px; border-radius:10px; background:#dcfce7; color:#15803d;"><?php echo esc_html($grade_ctl); ?></span>
                                    </div>
                                    <div style="display:flex; align-items:baseline; gap:4px;">
                                        <span style="font-size:18px; font-weight:800; color:#0f172a;"><?php echo (int)$score_ctl; ?></span>
                                        <span style="font-size:12px; color:#64748b;">/ 100</span>
                                        <span style="font-size:12px; font-weight:600; color:#16a34a; margin-left:6px;"><?php _e('Fully Optimized ✓', 'phpinfo-wp'); ?></span>
                                    </div>
                                </div>
                                <div class="phpinfowp-progress-track" style="height:9px; background:#e2e8f0; border-radius:5px; overflow:hidden;">
                                    <div class="phpinfowp-progress-fill" style="width:<?php echo min(100, $score_ctl); ?>%; height:100%; background:#16a34a; border-radius:5px;"></div>
                                </div>
                            </div>

                            <!-- Track 2: Server Reality -->
                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:5px;">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="font-size:13px; font-weight:700; color:#0f172a;"><?php _e('Server Reality', 'phpinfo-wp'); ?></span>
                                        <span style="font-size:11px; font-weight:800; padding:1px 7px; border-radius:10px; background:<?php echo esc_attr($score_server_bg); ?>; color:<?php echo esc_attr($score_server_color); ?>;"><?php echo esc_html($grade); ?></span>
                                    </div>
                                    <div style="display:flex; align-items:baseline; gap:4px;">
                                        <span style="font-size:18px; font-weight:800; color:#0f172a;"><?php echo (int)$score; ?></span>
                                        <span style="font-size:12px; color:#64748b;">/ 100</span>
                                        <span style="font-size:12px; font-weight:600; color:#d97706; margin-left:6px;"><?php printf(__('%d Server Setting(s) ⚠️', 'phpinfo-wp'), (int)$locked_n); ?></span>
                                    </div>
                                </div>
                                <div class="phpinfowp-progress-track" style="height:9px; background:#e2e8f0; border-radius:5px; overflow:hidden;">
                                    <div class="phpinfowp-progress-fill" style="width:<?php echo min(100, $score); ?>%; height:100%; background:#d97706; border-radius:5px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Informational Callout (Pro) -->
                        <div style="font-size:11.5px; color:#92400e; background:#fffbeb; border:1px solid #fef3c7; border-radius:6px; padding:8px 12px; margin-top:10px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                            <span>
                                <strong><?php printf(__('Overall %s* (%d/100)', 'phpinfo-wp'), esc_html($grade_eff), (int)$score_eff); ?>:</strong>
                                <?php printf(__('Site settings are fully optimal (%1$s). Some server-level settings (%2$d) require a hosting plan upgrade to resolve (Server: %3$s).', 'phpinfo-wp'), esc_html($grade_ctl), (int)$locked_n, esc_html($grade)); ?>
                            </span>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-config-grader&tab=locked')); ?>" style="font-weight:700; color:#b45309; text-decoration:none; white-space:nowrap;">
                                <?php printf(__('View %d Settings &rarr;', 'phpinfo-wp'), (int)$locked_n); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- LOCKED: Pro-only dual-track audit (Free Plan) -->
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:18px 16px; text-align:center; position:relative; overflow:hidden;">
                            <div style="position:absolute; inset:0; background:repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(99,102,241,0.03) 10px, rgba(99,102,241,0.03) 20px);"></div>
                            <div style="position:relative; z-index:1;">
                                <div style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; background:#eef2ff; margin-bottom:10px;">
                                    <span class="dashicons dashicons-lock" style="font-size:18px; width:18px; height:18px; color:#6366f1;"></span>
                                </div>
                                <p style="margin:0 0 4px; font-size:13.5px; font-weight:800; color:#0f172a;"><?php _e('Full Server Audit — Pro Only', 'phpinfo-wp'); ?></p>
                                <p style="margin:0 0 12px; font-size:12px; color:#64748b; line-height:1.5;"><?php _e('Unlock the complete dual-track audit: see your server&#39;s real configuration score alongside your site-level score, with actionable server-level recommendations.', 'phpinfo-wp'); ?></p>
                                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button" style="background:#6366f1; border-color:#4f46e5; color:#fff; font-weight:700; font-size:12px; height:32px; line-height:30px; padding:0 16px; border-radius:6px; text-decoration:none; display:inline-block;">
                                    <span class="dashicons dashicons-star-filled" style="font-size:13px; width:13px; height:13px; color:#fbbf24; margin-right:4px; vertical-align:middle;"></span>
                                    <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="phpinfowp-score-numbers">
                        <span class="phpinfowp-score-big"><?php echo (int)$score_ctl; ?></span>
                        <span class="phpinfowp-score-max">/ 100</span>
                        <span class="phpinfowp-score-status" style="color:<?php echo esc_attr($grade_color); ?>;">
                            <?php echo $score_ctl >= 80 ? __('Optimal Configuration ✓', 'phpinfo-wp') : ($score_ctl >= 60 ? __('Needs Optimization ⚠️', 'phpinfo-wp') : __('Action Required 🚨', 'phpinfo-wp')); ?>
                        </span>
                    </div>
                    <div class="phpinfowp-progress-track">
                        <div class="phpinfowp-progress-fill" style="width:<?php echo min(100, $score_ctl); ?>%; background:<?php echo esc_attr($grade_color); ?>;"></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="phpinfowp-pills-row">
                <div class="phpinfowp-pill-stat pill-success">
                    <strong><?php echo $passes; ?></strong> <?php _e('Passed', 'phpinfo-wp'); ?>
                </div>
                <div class="phpinfowp-pill-stat <?php echo $warns > 0 ? 'pill-warning' : 'pill-muted'; ?>">
                    <strong><?php echo $warns; ?></strong> <?php _e('Warnings', 'phpinfo-wp'); ?>
                </div>
                <div class="phpinfowp-pill-stat <?php echo $fails > 0 ? 'pill-danger' : 'pill-muted'; ?>">
                    <strong><?php echo $fails; ?></strong> <?php _e('Failing', 'phpinfo-wp'); ?>
                </div>
                <div class="phpinfowp-pill-stat pill-info">
                    <strong><?php echo $total_directives; ?></strong> <?php _e('Total Directives', 'phpinfo-wp'); ?>
                </div>
            </div>

            <div class="phpinfowp-hero-footer">
                <?php if ($fails > 0 || $warns > 0): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-config-grader')); ?>" class="button button-primary phpinfowp-btn-brand">
                        <?php
                        if ($is_pro && $has_autofixable) {
                            echo __('Review & Auto-Fix Directives &rarr;', 'phpinfo-wp');
                        } elseif ($is_pro) {
                            echo __('Review Actionable Issues &rarr;', 'phpinfo-wp');
                        } else {
                            echo __('Review Full Directive Breakdown &rarr;', 'phpinfo-wp');
                        }
                        ?>
                    </a>
                    <span class="phpinfowp-hero-hint">
                        <?php
                        if ($is_pro && $has_autofixable) {
                            echo __('1-Click Auto-Fixer active for fast remediation.', 'phpinfo-wp');
                        } elseif ($is_pro) {
                            echo __('Server/php.ini configuration required for remaining items.', 'phpinfo-wp');
                        } else {
                            echo __('Pro includes 1-click automatic directive fixer & security hardening.', 'phpinfo-wp');
                        }
                        ?>
                    </span>
                <?php elseif ($show_dual && (int)$locked_n > 0): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-config-grader' . ($is_pro ? '&tab=locked' : ''))); ?>" class="button button-secondary" style="font-weight:600;">
                        <?php echo $is_pro ? sprintf(__('View %d Server Directives &rarr;', 'phpinfo-wp'), (int)$locked_n) : __('View All Directives &rarr;', 'phpinfo-wp'); ?>
                    </a>
                    <?php if ($is_pro): ?>
                        <span class="phpinfowp-hero-hint" style="color:#15803d; font-weight:600;">
                            <span class="dashicons dashicons-yes" style="font-size:16px; width:16px; height:16px; vertical-align:middle; margin-right:2px;"></span>
                            <?php _e('All actionable site settings are optimized. Host-managed limits require server/cPanel access.', 'phpinfo-wp'); ?>
                        </span>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-config-grader&tab=directives')); ?>" class="button button-secondary" style="font-weight:600;">
                        <?php _e('View All Directives &rarr;', 'phpinfo-wp'); ?>
                    </a>
                    <span class="phpinfowp-hero-hint" style="color:#15803d; font-weight:600;">
                        <span class="dashicons dashicons-yes" style="font-size:16px; width:16px; height:16px; vertical-align:middle; margin-right:2px;"></span>
                        <?php _e('All PHP configuration directives meet optimal standards.', 'phpinfo-wp'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Priority Triage & Action Items (Accurate State) -->
        <div class="phpinfowp-command-card phpinfowp-triage-card">
            <div class="phpinfowp-triage-head">
                <h2 class="phpinfowp-card-heading"><?php _e('Priority Action & Security Triage', 'phpinfo-wp'); ?></h2>
                <?php if ($is_pro): ?>
                <span class="phpinfowp-triage-count <?php echo count($triage_items) > 0 ? 'triage-has-issues' : ''; ?>">
                    <?php echo count($triage_items) > 0 ? sprintf(_n('%d Priority Item', '%d Priority Items', count($triage_items), 'phpinfo-wp'), count($triage_items)) : __('Clean', 'phpinfo-wp'); ?>
                </span>
                <?php else: ?>
                <span class="phpinfowp-pro-tag" style="font-size:10px; padding:2px 8px;">PRO</span>
                <?php endif; ?>
            </div>

            <?php if ($is_pro): ?>
                <?php if (!empty($triage_items)): ?>
                    <div class="phpinfowp-triage-list">
                        <?php foreach ($triage_items as $item): 
                            $border_c = $item['type'] === 'danger' ? '#dc2626' : '#d97706';
                            $icon_c   = $item['type'] === 'danger' ? '#dc2626' : '#d97706';
                            $bg_c     = $item['type'] === 'danger' ? '#fef2f2' : '#fffbeb';
                        ?>
                            <div class="phpinfowp-triage-item" style="border-left-color:<?php echo esc_attr($border_c); ?>; background:<?php echo esc_attr($bg_c); ?>;">
                                <div class="phpinfowp-triage-icon-wrap" style="color:<?php echo esc_attr($icon_c); ?>;">
                                    <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                                </div>
                                <div class="phpinfowp-triage-body">
                                    <strong class="phpinfowp-triage-title"><?php echo esc_html($item['title']); ?></strong>
                                    <p class="phpinfowp-triage-desc"><?php echo esc_html($item['desc']); ?></p>
                                </div>
                                <a href="<?php echo esc_url($item['link']); ?>" class="button button-secondary phpinfowp-triage-btn">
                                    <?php echo esc_html($item['link_lbl']); ?> &rarr;
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="phpinfowp-triage-clean">
                        <div class="phpinfowp-clean-icon">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <h3 class="phpinfowp-clean-title"><?php _e('All Vital Systems Healthy', 'phpinfo-wp'); ?></h3>
                        <p class="phpinfowp-clean-desc"><?php _e('No immediate security bottlenecks, critical directive failures, or overdue background tasks detected. Your server configuration meets modern WordPress standards.', 'phpinfo-wp'); ?></p>
                        <div class="phpinfowp-clean-timestamp">
                            <?php printf(__('Last verified: %s UTC', 'phpinfo-wp'), gmdate('Y-m-d H:i')); ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="phpinfowp-pro-card-teaser" style="padding:28px 20px; text-align:center;">
                    <div class="phpinfowp-pct-icon">
                        <span class="dashicons dashicons-lock"></span>
                    </div>
                    <strong class="phpinfowp-pct-title"><?php _e('Security Triage & Priority Alerts', 'phpinfo-wp'); ?></strong>
                    <p class="phpinfowp-pct-desc"><?php _e('Automatically surface your top PHP security risks, config inconsistencies, and overdue background tasks — ranked by severity with one-click remediation.', 'phpinfo-wp'); ?></p>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button" style="background:#6366f1; border-color:#4f46e5; color:#fff; font-weight:700; font-size:12px; margin-top:4px; text-decoration:none;">
                        <span class="dashicons dashicons-star-filled" style="font-size:13px; width:13px; height:13px; color:#fbbf24; margin-right:4px; vertical-align:middle;"></span>
                        <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- 4. VITAL TELEMETRY 4-CARD GRID (Clean Free vs Pro Gating) -->
    <div class="phpinfowp-telemetry-grid">
        
        <!-- Card 1: Memory & Resource Engine (FREE) -->
        <div class="phpinfowp-telemetry-card">
            <div class="phpinfowp-tel-head">
                <div>
                    <h3 class="phpinfowp-tel-title"><?php _e('PHP Memory Engine', 'phpinfo-wp'); ?></h3>
                    <span class="phpinfowp-tel-sub"><?php _e('Peak memory footprint vs allocated limit', 'phpinfo-wp'); ?></span>
                </div>
                <div class="phpinfowp-tel-icon" style="background:#eef2ff; color:#4f46e5;">
                    <span class="dashicons dashicons-dashboard"></span>
                </div>
            </div>

            <div class="phpinfowp-tel-body">
                <div class="phpinfowp-gauge-wrap">
                    <svg width="72" height="72" viewBox="0 0 36 36" class="phpinfowp-donut">
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#f1f5f9" stroke-width="4"/>
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?php echo esc_attr($mem_color); ?>" stroke-width="4" stroke-dasharray="<?php echo esc_attr($dasharray); ?>" />
                        <text x="18" y="21" class="phpinfowp-donut-text" fill="#0f172a"><?php echo (int)$mem_pct; ?>%</text>
                    </svg>
                    <div>
                        <div class="phpinfowp-stat-main"><?php echo esc_html(size_format($mem_peak_b)); ?></div>
                        <div class="phpinfowp-stat-limit"><?php printf(__('Limit: %s', 'phpinfo-wp'), esc_html($mem['limit_label'])); ?></div>
                    </div>
                </div>

                <div class="phpinfowp-substats-list">
                    <div class="phpinfowp-substat-row">
                        <span><?php _e('Upload Max Filesize:', 'phpinfo-wp'); ?></span>
                        <strong><?php echo esc_html($upload_max); ?></strong>
                    </div>
                    <div class="phpinfowp-substat-row">
                        <span><?php _e('Post Max Size:', 'phpinfo-wp'); ?></span>
                        <strong><?php echo esc_html($post_max); ?></strong>
                    </div>
                    <div class="phpinfowp-substat-row">
                        <span><?php _e('Max Input Vars:', 'phpinfo-wp'); ?></span>
                        <strong><?php echo esc_html($max_vars); ?></strong>
                    </div>
                </div>
            </div>

            <div class="phpinfowp-tel-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-tools')); ?>" class="phpinfowp-tel-link">
                    <?php _e('Tune in PHP Config Editor', 'phpinfo-wp'); ?> &rarr;
                </a>
            </div>
        </div>

        <!-- Card 2: Database & Autoload Footprint (PRO Gated) -->
        <div class="phpinfowp-telemetry-card">
            <div class="phpinfowp-tel-head">
                <div>
                    <h3 class="phpinfowp-tel-title"><?php _e('Database & Autoload', 'phpinfo-wp'); ?></h3>
                    <span class="phpinfowp-tel-sub"><?php _e('wp_options autoload footprint & table load', 'phpinfo-wp'); ?></span>
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <?php if (!$is_pro): ?><span class="phpinfowp-pro-tag">PRO</span><?php endif; ?>
                    <div class="phpinfowp-tel-icon" style="background:#ecfdf5; color:#059669;">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                </div>
            </div>

            <div class="phpinfowp-tel-body">
                <?php if ($is_pro): ?>
                    <div class="phpinfowp-db-stat-box">
                        <div class="phpinfowp-db-main-val" style="color:<?php echo esc_attr($db_color); ?>;">
                            <?php echo esc_html($db_kb); ?> <span class="phpinfowp-unit">KB</span>
                        </div>
                        <span class="phpinfowp-badge" style="background:<?php echo esc_attr($db_color); ?>15; color:<?php echo esc_attr($db_color); ?>; font-weight:700;">
                            <?php echo esc_html($db_status_text); ?>
                        </span>
                    </div>

                    <div class="phpinfowp-progress-track" style="margin: 12px 0 16px;">
                        <div class="phpinfowp-progress-fill" style="width:<?php echo min(100, $db_pct); ?>%; background:<?php echo esc_attr($db_color); ?>;"></div>
                    </div>

                    <div class="phpinfowp-substats-list">
                        <div class="phpinfowp-substat-row">
                            <span><?php _e('Database Total Size:', 'phpinfo-wp'); ?></span>
                            <strong><?php echo esc_html($total_db_size_fmt); ?></strong>
                        </div>
                        <div class="phpinfowp-substat-row">
                            <span><?php _e('Total Schema Tables:', 'phpinfo-wp'); ?></span>
                            <strong><?php echo esc_html($table_count); ?></strong>
                        </div>
                        <div class="phpinfowp-substat-row">
                            <span><?php _e('Autoload Count:', 'phpinfo-wp'); ?></span>
                            <strong><?php echo number_format((int)($db_stats['count'] ?? 0)); ?> <?php _e('keys', 'phpinfo-wp'); ?></strong>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="phpinfowp-pro-card-teaser">
                        <div class="phpinfowp-pct-icon">
                            <span class="dashicons dashicons-lock"></span>
                        </div>
                        <strong class="phpinfowp-pct-title"><?php _e('Database Health & Autoload Analysis', 'phpinfo-wp'); ?></strong>
                        <p class="phpinfowp-pct-desc"><?php _e('Monitor wp_options autoload bloat, total database size, schema table count, and get one-click cleanup recommendations.', 'phpinfo-wp'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="phpinfowp-tel-footer">
                <a href="<?php echo esc_url($is_pro ? admin_url('admin.php?page=piwp-db-health') : 'https://exeebit.com/phpinfo-wp#pricing'); ?>" <?php echo $is_pro ? '' : 'target="_blank" rel="noopener"'; ?> class="phpinfowp-tel-link">
                    <?php echo $is_pro ? __('Database Health & Optimization', 'phpinfo-wp') : __('Unlock Database Health &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>

        <!-- Card 3: Outbound API & Latency Monitor (PRO Feature) -->
        <div class="phpinfowp-telemetry-card">
            <div class="phpinfowp-tel-head">
                <div>
                    <h3 class="phpinfowp-tel-title"><?php _e('Outbound API Latency', 'phpinfo-wp'); ?></h3>
                    <span class="phpinfowp-tel-sub"><?php _e('External HTTP tracking & TTFB bottlenecks', 'phpinfo-wp'); ?></span>
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <?php if (!$is_pro): ?><span class="phpinfowp-pro-tag">PRO</span><?php endif; ?>
                    <div class="phpinfowp-tel-icon" style="background:#eff6ff; color:#2563eb;">
                        <span class="dashicons dashicons-networking"></span>
                    </div>
                </div>
            </div>

            <div class="phpinfowp-tel-body">
                <?php if ($is_pro): ?>
                    <?php if (!empty($top_apis)): ?>
                        <div class="phpinfowp-api-top-list">
                            <?php foreach ($top_apis as $host => $time): 
                                $bar_w = min(100, ($time / max(1.5, $time)) * 100);
                                $bar_c = $time > 2.0 ? '#dc2626' : ($time > 0.8 ? '#d97706' : '#16a34a');
                            ?>
                                <div class="phpinfowp-api-row">
                                    <div class="phpinfowp-api-labels">
                                        <span class="phpinfowp-api-host" title="<?php echo esc_attr($host); ?>"><?php echo esc_html($host); ?></span>
                                        <strong style="color:<?php echo esc_attr($bar_c); ?>; font-size:12px;"><?php echo number_format($time, 2); ?>s</strong>
                                    </div>
                                    <div class="phpinfowp-progress-track" style="height:5px;">
                                        <div class="phpinfowp-progress-fill" style="width:<?php echo esc_attr($bar_w); ?>%; background:<?php echo esc_attr($bar_c); ?>;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="phpinfowp-empty-api-box">
                            <span class="dashicons dashicons-cloud" style="font-size:24px; width:24px; height:24px; color:#94a3b8;"></span>
                            <p style="margin:4px 0 0; font-size:12px; color:#64748b;"><?php _e('No external bottlenecks recorded in 24h window.', 'phpinfo-wp'); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="phpinfowp-substats-list" style="margin-top:12px;">
                        <div class="phpinfowp-substat-row">
                            <span><?php _e('Tracked API Requests:', 'phpinfo-wp'); ?></span>
                            <strong><?php echo number_format($total_api_calls); ?></strong>
                        </div>
                        <div class="phpinfowp-substat-row">
                            <span><?php _e('Average Response Time:', 'phpinfo-wp'); ?></span>
                            <strong><?php echo number_format($avg_api_time, 2); ?>s</strong>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="phpinfowp-pro-card-teaser">
                        <div class="phpinfowp-pct-icon">
                            <span class="dashicons dashicons-lock"></span>
                        </div>
                        <strong class="phpinfowp-pct-title"><?php _e('Track Slow 3rd-Party HTTP Calls', 'phpinfo-wp'); ?></strong>
                        <p class="phpinfowp-pct-desc"><?php _e('Detect external API latency (payment gateways, CRMs, webhooks) silently degrading your server TTFB.', 'phpinfo-wp'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="phpinfowp-tel-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-api-monitor')); ?>" class="phpinfowp-tel-link">
                    <?php echo $is_pro ? __('Outbound API Telemetry', 'phpinfo-wp') : __('Unlock API Monitor', 'phpinfo-wp'); ?> &rarr;
                </a>
            </div>
        </div>

        <!-- Card 4: Background Tasks & WP-Cron (PRO Gated) -->
        <div class="phpinfowp-telemetry-card">
            <div class="phpinfowp-tel-head">
                <div>
                    <h3 class="phpinfowp-tel-title"><?php _e('WP-Cron & Queue', 'phpinfo-wp'); ?></h3>
                    <span class="phpinfowp-tel-sub"><?php _e('Scheduled background tasks & execution', 'phpinfo-wp'); ?></span>
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <?php if (!$is_pro): ?><span class="phpinfowp-pro-tag">PRO</span><?php endif; ?>
                    <div class="phpinfowp-tel-icon" style="background:#faf5ff; color:#7c3aed;">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                </div>
            </div>

            <div class="phpinfowp-tel-body">
                <?php if ($is_pro): ?>
                    <div class="phpinfowp-cron-stat-box">
                        <div>
                            <div class="phpinfowp-cron-big-val"><?php echo $cron_total; ?></div>
                            <div class="phpinfowp-cron-sub-lbl"><?php _e('Scheduled Events', 'phpinfo-wp'); ?></div>
                        </div>
                        <div class="phpinfowp-cron-overdue-wrap">
                            <span class="phpinfowp-badge <?php echo $cron_overdue > 0 ? 'badge-danger' : 'badge-success'; ?>" style="font-size:12px; padding:4px 8px;">
                                <?php echo $cron_overdue > 0 ? sprintf(__('%d Overdue', 'phpinfo-wp'), $cron_overdue) : __('0 Overdue ✓', 'phpinfo-wp'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="phpinfowp-substats-list" style="margin-top:14px;">
                        <div class="phpinfowp-substat-row">
                            <span><?php _e('Runner Mode:', 'phpinfo-wp'); ?></span>
                            <strong><?php echo $cron_disabled ? __('WP_CRON Disabled', 'phpinfo-wp') : __('WP_CRON Native', 'phpinfo-wp'); ?></strong>
                        </div>
                        <div class="phpinfowp-substat-row">
                            <span><?php _e('Orphan Callbacks:', 'phpinfo-wp'); ?></span>
                            <strong><?php echo (int)($cron_summary['orphan'] ?? 0); ?></strong>
                        </div>
                        <div class="phpinfowp-substat-row">
                            <span><?php _e('Due in < 60s:', 'phpinfo-wp'); ?></span>
                            <strong><?php echo (int)($cron_summary['imminent'] ?? 0); ?></strong>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="phpinfowp-pro-card-teaser">
                        <div class="phpinfowp-pct-icon">
                            <span class="dashicons dashicons-lock"></span>
                        </div>
                        <strong class="phpinfowp-pct-title"><?php _e('WP-Cron Queue Inspector', 'phpinfo-wp'); ?></strong>
                        <p class="phpinfowp-pct-desc"><?php _e('See all scheduled events, detect overdue cron jobs, spot orphaned callbacks, and monitor upcoming tasks — with health alerts when jobs go missing.', 'phpinfo-wp'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="phpinfowp-tel-footer">
                <a href="<?php echo esc_url($is_pro ? admin_url('admin.php?page=piwp-cron') : 'https://exeebit.com/phpinfo-wp#pricing'); ?>" <?php echo $is_pro ? '' : 'target="_blank" rel="noopener"'; ?> class="phpinfowp-tel-link">
                    <?php echo $is_pro ? __('Inspect WP-Cron Schedule', 'phpinfo-wp') : __('Unlock WP-Cron Manager &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>

    </div>

    <!-- 5. PRO FOOTER UPSELL BANNER (Only shown when not Pro) -->
    <?php if (!$is_pro): ?>
        <div class="phpinfowp-dash-pro-banner">
            <div class="phpinfowp-dpb-left">
                <div class="phpinfowp-dpb-icon">
                    <span class="dashicons dashicons-awards"></span>
                </div>
                <div>
                    <h3 class="phpinfowp-dpb-title"><?php _e('Unlock Full Server Intelligence & Auto-Fixing with phpinfo() WP Pro', 'phpinfo-wp'); ?></h3>
                    <p class="phpinfowp-dpb-desc"><?php _e('Get instant 1-click directive auto-fixing, live outbound HTTP latency monitoring, runtime security headers injection, and client-ready audit reports.', 'phpinfo-wp'); ?></p>
                </div>
            </div>
            <div class="phpinfowp-dpb-right">
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary phpinfowp-dpb-btn">
                    <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- STYLESHEET FOR REDESIGNED DASHBOARD -->
<style>
/* Reset & Scope */
.phpinfowp-dashboard {
    max-width: 1320px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    color: #1e293b;
}

/* 1. Header */
.phpinfowp-dash-topbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 16px;
}
.phpinfowp-dash-live-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #4f46e5;
    background: #eef2ff;
    padding: 3px 10px;
    border-radius: 20px;
    margin-bottom: 8px;
    border: 1px solid #e0e7ff;
}
.phpinfowp-plan-pill {
    font-size: 10px;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 10px;
    background: #e2e8f0;
    color: #475569;
    letter-spacing: 0.4px;
}
.phpinfowp-plan-pill.pro-active {
    background: #777BB3;
    color: #ffffff;
}
.phpinfowp-pulse-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #4f46e5;
    box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.7);
    animation: phpinfowp-pulse 1.8s infinite;
}
@keyframes phpinfowp-pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(79, 70, 229, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
}
.phpinfowp-dash-title {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.2;
    color: #0f172a;
    margin: 0 0 6px 0;
    letter-spacing: -0.4px;
}
.phpinfowp-dash-subtitle {
    margin: 0;
    font-size: 13.5px;
    color: #64748b;
    max-width: 680px;
}
.phpinfowp-dash-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}
.phpinfowp-btn-brand {
    background: #777BB3 !important;
    border-color: #777BB3 !important;
    color: #fff !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    height: 36px !important;
    line-height: 34px !important;
    padding: 0 16px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    box-shadow: 0 1px 2px rgba(119, 123, 179, 0.2) !important;
    transition: all 0.15s ease !important;
}
.phpinfowp-btn-brand:hover {
    background: #6569a1 !important;
    border-color: #6569a1 !important;
}
.phpinfowp-btn-upgrade-top {
    background: linear-gradient(135deg, #4f46e5 0%, #777BB3 100%) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    height: 36px !important;
    line-height: 36px !important;
    padding: 0 16px !important;
    display: inline-flex !important;
    align-items: center !important;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25) !important;
    transition: all 0.15s ease !important;
}
.phpinfowp-btn-upgrade-top:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.35) !important;
    color: #ffffff !important;
}
.phpinfowp-btn-glow {
    font-weight: 600 !important;
    font-size: 13px !important;
    height: 36px !important;
    line-height: 34px !important;
    padding: 0 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
}

/* 2. Specs Ribbon */
.phpinfowp-specs-ribbon {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 18px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    gap: 12px;
}
@media (max-width: 1080px) {
    .phpinfowp-specs-ribbon { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 600px) {
    .phpinfowp-specs-ribbon { grid-template-columns: repeat(2, 1fr); }
}
.phpinfowp-spec-item {
    display: flex;
    flex-direction: column;
    gap: 3px;
    border-right: 1px solid #f1f5f9;
    padding-right: 12px;
}
.phpinfowp-spec-item:last-child {
    border-right: none;
    padding-right: 0;
}
.phpinfowp-spec-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
}
.phpinfowp-spec-val-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.phpinfowp-spec-value {
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
}
.phpinfowp-badge {
    font-size: 10.5px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    display: inline-block;
}
.badge-success { background: #dcfce7; color: #15803d; }
.badge-warning { background: #fef3c7; color: #b45309; }
.badge-danger  { background: #fee2e2; color: #b91c1c; }

/* 3. Command Center Grid */
.phpinfowp-command-grid {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 20px;
    margin-bottom: 24px;
}
@media (max-width: 980px) {
    .phpinfowp-command-grid { grid-template-columns: 1fr; }
}
.phpinfowp-command-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.phpinfowp-hero-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 18px;
}
.phpinfowp-card-heading {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px 0;
    letter-spacing: -0.2px;
}
.phpinfowp-card-subheading {
    margin: 0;
    font-size: 12.5px;
    color: #64748b;
}
.phpinfowp-grade-badge {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 900;
    flex-shrink: 0;
}
.grade-aplus, .grade-a { background: #dcfce7; color: #15803d; border: 2px solid #bbf7d0; }
.grade-b { background: #fef9c3; color: #854d0e; border: 2px solid #fde047; }
.grade-c { background: #ffedd5; color: #c2410c; border: 2px solid #fed7aa; }
.grade-d, .grade-f { background: #fee2e2; color: #b91c1c; border: 2px solid #fecaca; }

.phpinfowp-score-meter-wrap {
    margin-bottom: 18px;
}
.phpinfowp-score-numbers {
    display: flex;
    align-items: baseline;
    gap: 6px;
    margin-bottom: 8px;
}
.phpinfowp-score-big {
    font-size: 34px;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
}
.phpinfowp-score-max {
    font-size: 16px;
    font-weight: 600;
    color: #94a3b8;
}
.phpinfowp-score-status {
    margin-left: auto;
    font-size: 13px;
    font-weight: 700;
}
.phpinfowp-progress-track {
    width: 100%;
    height: 8px;
    background: #f1f5f9;
    border-radius: 6px;
    overflow: hidden;
}
.phpinfowp-progress-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 0.4s ease;
}

.phpinfowp-pills-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.phpinfowp-pill-stat {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-success { background: #dcfce7; color: #15803d; font-weight: 600; }
.pill-warning { background: #fef3c7; color: #b45309; font-weight: 600; }
.pill-danger  { background: #fee2e2; color: #b91c1c; font-weight: 600; }
.pill-info    { background: #f1f5f9; color: #475569; font-weight: 600; }
.pill-muted   { background: #f8fafc; color: #94a3b8; }

.phpinfowp-hero-footer {
    display: flex;
    align-items: center;
    gap: 14px;
    border-top: 1px solid #f1f5f9;
    padding-top: 16px;
    flex-wrap: wrap;
}
.phpinfowp-hero-hint {
    font-size: 12px;
    color: #64748b;
}

/* Triage Card */
.phpinfowp-triage-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.phpinfowp-triage-count {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 12px;
}
.phpinfowp-triage-count.triage-has-issues {
    background: #fee2e2;
    color: #b91c1c;
}
.phpinfowp-triage-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.phpinfowp-triage-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    border-left-width: 4px;
}
.phpinfowp-triage-icon-wrap {
    font-size: 20px;
    line-height: 1;
}
.phpinfowp-triage-body {
    flex: 1;
}
.phpinfowp-triage-title {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 2px;
}
.phpinfowp-triage-desc {
    margin: 0;
    font-size: 12px;
    color: #475569;
    line-height: 1.4;
}
.phpinfowp-triage-btn {
    font-size: 11.5px !important;
    font-weight: 600 !important;
    height: 28px !important;
    line-height: 26px !important;
    padding: 0 10px !important;
    flex-shrink: 0;
}

.phpinfowp-triage-card {
    justify-content: flex-start;
}
.phpinfowp-triage-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 14px;
}
.phpinfowp-triage-clean {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 32px 20px;
    margin-top: 14px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}
.phpinfowp-clean-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #dcfce7;
    color: #15803d;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
}
.phpinfowp-clean-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}
.phpinfowp-clean-title {
    font-size: 14.5px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px 0;
}
.phpinfowp-clean-desc {
    margin: 0 0 10px 0;
    font-size: 12.5px;
    color: #64748b;
    max-width: 440px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.45;
}
.phpinfowp-clean-timestamp {
    font-size: 11px;
    color: #94a3b8;
}

/* 4. Telemetry 4-Card Grid */
.phpinfowp-telemetry-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 1100px) {
    .phpinfowp-telemetry-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
    .phpinfowp-telemetry-grid { grid-template-columns: 1fr; }
}
.phpinfowp-telemetry-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.phpinfowp-telemetry-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.phpinfowp-tel-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 14px;
}
.phpinfowp-tel-title {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 2px 0;
}
.phpinfowp-tel-sub {
    font-size: 11.5px;
    color: #64748b;
}
.phpinfowp-tel-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.phpinfowp-tel-icon .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.phpinfowp-pro-tag {
    font-size: 9.5px;
    font-weight: 800;
    background: #777BB3;
    color: #ffffff;
    padding: 2px 6px;
    border-radius: 4px;
    letter-spacing: 0.5px;
}

.phpinfowp-gauge-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 14px;
}
.phpinfowp-donut {
    display: block;
    transform: rotate(-90deg);
}
.phpinfowp-donut-text {
    font-family: inherit;
    font-size: 9px;
    font-weight: 800;
    text-anchor: middle;
    transform: rotate(90deg);
    transform-origin: center;
}
.phpinfowp-stat-main {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
}
.phpinfowp-stat-limit {
    font-size: 12px;
    color: #64748b;
}

.phpinfowp-substats-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12px;
    border-top: 1px solid #f1f5f9;
    padding-top: 10px;
}
.phpinfowp-substat-row {
    display: flex;
    justify-content: space-between;
    color: #475569;
}
.phpinfowp-substat-row strong {
    color: #0f172a;
}

.phpinfowp-tel-footer {
    margin-top: 14px;
    border-top: 1px solid #f1f5f9;
    padding-top: 10px;
}
.phpinfowp-tel-link {
    font-size: 12px;
    font-weight: 700;
    color: #777BB3;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.phpinfowp-tel-link:hover {
    color: #555994;
    text-decoration: underline;
}

/* DB Subcard */
.phpinfowp-db-stat-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.phpinfowp-db-main-val {
    font-size: 22px;
    font-weight: 800;
    line-height: 1;
}
.phpinfowp-unit {
    font-size: 13px;
    color: #64748b;
}

/* API Subcard */
.phpinfowp-api-top-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 4px;
}
.phpinfowp-api-row {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.phpinfowp-api-labels {
    display: flex;
    justify-content: space-between;
    font-size: 11.5px;
}
.phpinfowp-api-host {
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #334155;
    font-weight: 500;
}
.phpinfowp-empty-api-box {
    text-align: center;
    padding: 10px;
    background: #f8fafc;
    border-radius: 6px;
}

/* Pro Card Teaser */
.phpinfowp-pro-card-teaser {
    padding: 14px 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    text-align: center;
    margin: 4px 0 10px 0;
}
.phpinfowp-pct-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #eef2ff;
    color: #777BB3;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 6px;
}
.phpinfowp-pct-icon .dashicons {
    font-size: 15px;
    width: 15px;
    height: 15px;
}
.phpinfowp-pct-title {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 4px;
}
.phpinfowp-pct-desc {
    margin: 0;
    font-size: 11.5px;
    color: #64748b;
    line-height: 1.4;
}

/* Cron Subcard */
.phpinfowp-cron-stat-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.phpinfowp-cron-big-val {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
}
.phpinfowp-cron-sub-lbl {
    font-size: 11.5px;
    color: #64748b;
}

/* 5. Pro Banner */
.phpinfowp-dash-pro-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
    border-radius: 12px;
    padding: 24px 28px;
    color: #ffffff;
    gap: 20px;
    flex-wrap: wrap;
    box-shadow: 0 4px 20px rgba(49, 46, 129, 0.2);
}
.phpinfowp-dpb-left {
    display: flex;
    align-items: center;
    gap: 16px;
    max-width: 800px;
}
.phpinfowp-dpb-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.phpinfowp-dpb-icon .dashicons {
    font-size: 26px;
    width: 26px;
    height: 26px;
    color: #fbbf24;
}
.phpinfowp-dpb-title {
    font-size: 16px;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 4px 0;
}
.phpinfowp-dpb-desc {
    margin: 0;
    font-size: 13px;
    color: #c7d2fe;
    line-height: 1.45;
}
.phpinfowp-dpb-btn {
    background: #fbbf24 !important;
    border-color: #fbbf24 !important;
    color: #1e1b4b !important;
    font-weight: 800 !important;
    font-size: 13.5px !important;
    height: 40px !important;
    line-height: 38px !important;
    padding: 0 20px !important;
    box-shadow: 0 2px 8px rgba(251, 191, 36, 0.3) !important;
    transition: all 0.15s ease !important;
}
.phpinfowp-dpb-btn:hover {
    background: #f59e0b !important;
    border-color: #f59e0b !important;
    transform: translateY(-1px);
}
</style>