<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro    = Phpinfo_WP_License::is_valid();
$grader    = Phpinfo_WP_Config_Grader::summary();
$bar       = Phpinfo_WP_Admin_Bar::status();

// Fetch new stats for visual dashboard
$api_stats = class_exists('Phpinfo_WP_API_Monitor') ? Phpinfo_WP_API_Monitor::get_stats() : [];
if (!is_array($api_stats)) $api_stats = [];
$db_stats  = class_exists('Phpinfo_WP_DB_Health') ? Phpinfo_WP_DB_Health::autoload_size() : ['total_kb' => 0];
if (!is_array($db_stats)) $db_stats = ['total_kb' => 0];

$grade        = $grader['grade'];
$score        = $grader['score'];
$mem          = $bar['memory'] ?? ['peak_bytes' => 0, 'limit_label' => ini_get('memory_limit'), 'pct' => 0];

// Calculate Donut Chart (Memory)
$mem_pct = $mem['pct'];
$mem_color = $mem_pct > 85 ? '#d63638' : ($mem_pct > 65 ? '#dba617' : '#00a32a');
$dasharray = $mem_pct . ' ' . (100 - $mem_pct);

// Calculate API Chart
$slowest_apis = [];
foreach ($api_stats as $host => $data) {
    $slowest_apis[$host] = $data['max_time'];
}
arsort($slowest_apis);
$top_apis = array_slice($slowest_apis, 0, 3, true);

// Calculate DB Autoload Bar
$db_bytes = $db_stats['bytes'] ?? 0;
$db_kb = round($db_bytes / 1024, 1);
// 800KB is danger zone. Max scale is 1200KB.
$db_pct = min(100, ($db_kb / 1200) * 100);
$db_color = $db_kb > 800 ? '#d63638' : ($db_kb > 400 ? '#dba617' : '#00a32a');

// Config Grade Bar
$grade_pct = $score;
$grade_color = $score < 60 ? '#d63638' : ($score < 80 ? '#dba617' : '#00a32a');

// Fetch the current nav arrays
$nav_cache = Phpinfo_WP_Admin_Nav::groups();

if (!function_exists('render_dash_grid')) {
    function render_dash_grid($group_id, $nav_cache, $is_pro) {
        if (!isset($nav_cache[$group_id])) return;
        $group = $nav_cache[$group_id];
        echo '<div class="phpinfowp-dash-section">';
        echo '<h2 class="phpinfowp-dash-section-title">' . esc_html($group['label']) . '</h2>';
        echo '<div class="phpinfowp-dash-grid">';
        foreach ($group['tabs'] as $slug => $tab) {
            $locked = $tab['pro'] && !$is_pro;
            echo '<a href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '" class="phpinfowp-dash-card' . ($locked ? ' is-locked' : '') . '">';
            echo '<span class="dashicons ' . esc_attr($tab['icon']) . '"></span>';
            echo '<div class="phpinfowp-dash-card-title">';
            echo esc_html($tab['label']);
            if ($tab['pro']) {
                echo '<span class="phpinfowp-dash-card-pro">' . __('PRO', 'phpinfo-wp') . '</span>';
            }
            echo '</div>';
            echo '</a>';
        }
        echo '</div></div>';
    }
}
?>

<div class="phpinfowp-pro-page phpinfowp-dashboard">

    <div class="phpinfowp-page-header">
        <div>
            <h1><?php _e('Dashboard', 'phpinfo-wp'); ?></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Visual health overview and system shortcuts', 'phpinfo-wp'); ?></p>
        </div>
    </div>



    <!-- VISUAL INSIGHTS WIDGETS -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:32px;">

        <!-- 1. Config Grader Gauge -->
        <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-config-grader')); ?>" style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px; text-decoration:none; color:inherit; box-shadow:0 1px 2px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <h3 style="margin:0 0 12px 0; font-size:14px; color:#555; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('Setup Optimization', 'phpinfo-wp'); ?></h3>
                <div style="font-size:32px; font-weight:300; line-height:1; margin-bottom:8px; color:#1d2327;">
                    <?php printf(
                        /* translators: %s: score value */
                        __('%s / 100', 'phpinfo-wp'),
                        (int)$score
                    ); ?>
                </div>
            </div>
            <div>
                <div style="width:100%; height:8px; background:#f0f0f1; border-radius:4px; overflow:hidden; margin-bottom:6px;">
                    <div style="height:100%; width:<?php echo $grade_pct; ?>%; background:<?php echo $grade_color; ?>; border-radius:4px;"></div>
                </div>
                <div style="font-size:12px; color:#777;">
                    <?php _e('Current Grade:', 'phpinfo-wp'); ?> <strong class="grade-<?php echo esc_attr(strtolower(str_replace('+', 'plus', $grade))); ?>"><?php echo esc_html($grade); ?></strong>
                </div>
            </div>
        </a>

        <!-- 2. Memory Donut Chart -->
        <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-info')); ?>" style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px; text-decoration:none; color:inherit; box-shadow:0 1px 2px rgba(0,0,0,0.02); display:flex; align-items:center; justify-content:space-between;">
            <div>
                <h3 style="margin:0 0 8px 0; font-size:14px; color:#555; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('Memory Peak', 'phpinfo-wp'); ?></h3>
                <div style="font-size:24px; font-weight:300; line-height:1.2; margin-bottom:4px; color:#1d2327;">
                    <?php echo esc_html(size_format($mem['peak_bytes'] > 0 ? $mem['peak_bytes'] : memory_get_usage(true))); ?>
                </div>
                <div style="font-size:12px; color:#777;">
                    <?php _e('Limit:', 'phpinfo-wp'); ?> <?php echo esc_html($mem['limit_label']); ?>
                </div>
            </div>
            <svg width="80" height="80" viewBox="0 0 36 36" style="display:block;">
                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#f0f0f1" stroke-width="4"/>
                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?php echo $mem_color; ?>" stroke-width="4" stroke-dasharray="<?php echo $dasharray; ?>" />
                <text x="18" y="20.5" font-family="sans-serif" font-size="9" font-weight="bold" fill="#555" text-anchor="middle"><?php echo $mem_pct; ?>%</text>
            </svg>
        </a>

        <!-- 3. DB Autoload Progress -->
        <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-db-health')); ?>" style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px; text-decoration:none; color:inherit; box-shadow:0 1px 2px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <h3 style="margin:0 0 12px 0; font-size:14px; color:#555; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('Autoload Bloat', 'phpinfo-wp'); ?></h3>
                <div style="font-size:24px; font-weight:300; line-height:1; margin-bottom:8px; color:<?php echo $db_color; ?>;">
                    <?php echo $db_kb; ?> <span style="font-size:16px; color:#888;">KB</span>
                </div>
            </div>
            <div>
                <div style="width:100%; height:8px; background:#f0f0f1; border-radius:4px; overflow:hidden; margin-bottom:6px;">
                    <div style="height:100%; width:<?php echo $db_pct; ?>%; background:<?php echo $db_color; ?>; border-radius:4px;"></div>
                </div>
                <div style="font-size:12px; color:#777;">
                    <?php echo $db_kb > 800 ? __('Danger: High TTFB Impact', 'phpinfo-wp') : __('Healthy footprint', 'phpinfo-wp'); ?>
                </div>
            </div>
        </a>

        <!-- 4. API Monitor Bar Chart -->
        <?php if ($is_pro): ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-api-monitor')); ?>" style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px; text-decoration:none; color:inherit; box-shadow:0 1px 2px rgba(0,0,0,0.02); display:flex; flex-direction:column;">
            <h3 style="margin:0 0 12px 0; font-size:14px; color:#555; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('Slowest External APIs', 'phpinfo-wp'); ?></h3>
            <?php if (empty($top_apis)): ?>
                <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#888; font-size:13px; font-style:italic;">
                    <?php _e('No slow API calls detected', 'phpinfo-wp'); ?>
                </div>
            <?php else: ?>
                <div style="flex:1; display:flex; flex-direction:column; justify-content:space-around;">
                    <?php 
                    $max_t = max(2.0, max($top_apis)); // base scale on 2.0s or highest
                    foreach($top_apis as $host => $time): 
                        $w = min(100, ($time / $max_t) * 100);
                        $c = $time > 2.0 ? '#d63638' : ($time > 1.0 ? '#dba617' : '#00a32a');
                    ?>
                        <div style="margin-bottom:6px;">
                            <div style="display:flex; justify-content:space-between; font-size:11px; color:#555; margin-bottom:2px;">
                                <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:70%;"><?php echo esc_html($host); ?></span>
                                <strong><?php echo number_format($time, 1); ?>s</strong>
                            </div>
                            <div style="width:100%; height:4px; background:#f0f0f1; border-radius:2px;">
                                <div style="height:100%; width:<?php echo $w; ?>%; background:<?php echo $c; ?>; border-radius:2px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </a>
        <?php else: ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-api-monitor')); ?>" style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px; text-decoration:none; color:inherit; box-shadow:0 1px 2px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                    <h3 style="margin:0; font-size:14px; color:#555; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('Slowest External APIs', 'phpinfo-wp'); ?></h3>
                    <span class="phpinfowp-dash-card-pro"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                </div>
                <div style="font-size:13px; color:#64748b; line-height:1.45; margin-bottom:8px;">
                    <span class="dashicons dashicons-lock" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom; color:#777BB3; margin-right:2px;"></span>
                    <?php _e('Track outbound HTTP latency & slow 3rd-party services impacting TTFB.', 'phpinfo-wp'); ?>
                </div>
            </div>
            <div style="font-size:12px; font-weight:600; color:#777BB3; display:flex; align-items:center; gap:4px;">
                <?php _e('Unlock API Monitor &rarr;', 'phpinfo-wp'); ?>
            </div>
        </a>
        <?php endif; ?>

    </div>

    <!-- Active issues panel -->
    <?php if (!empty($bar['issues'])): ?>
    <div class="phpinfowp-dash-section">
        <h2 class="phpinfowp-dash-section-title"><?php _e('Active Issues', 'phpinfo-wp'); ?></h2>
        <div class="phpinfowp-dash-issues">
            <?php foreach ($bar['issues'] as $issue):
                $c = $issue['level'] === 'critical' ? '#d63638' : '#dba617';
            ?>
                <a href="<?php echo esc_url($issue['href']); ?>" class="phpinfowp-dash-issue">
                    <span class="phpinfowp-dash-issue-dot" style="background:<?php echo $c; ?>"></span>
                    <strong><?php echo esc_html($issue['label']); ?></strong>
                    <span class="phpinfowp-dash-issue-detail"><?php echo esc_html($issue['detail']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Nav Grids -->
    <?php 
    render_dash_grid('performance', $nav_cache, $is_pro);
    render_dash_grid('audit', $nav_cache, $is_pro);
    render_dash_grid('tools', $nav_cache, $is_pro);
    render_dash_grid('reports', $nav_cache, $is_pro);
    ?>

    <?php if (!$is_pro): ?>
    <div style="margin-top:24px;padding:14px 16px;background:#f6f7f7;border:1px solid #e5e7ea;border-radius:4px;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:13px;color:#555;">
            <?php _e('🔒 Running <strong>Pro features</strong> in preview mode.', 'phpinfo-wp'); ?>
        </span>
        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener"
           style="background:#777BB3;color:#fff;padding:6px 14px;border-radius:4px;font-size:12.5px;font-weight:600;text-decoration:none;">
            <?php _e('Unlock full access →', 'phpinfo-wp'); ?>
        </a>
    </div>
    <?php endif; ?>

</div>