<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

// Non-blocking cached lookup
$report = Phpinfo_WP_DB_Health::get_result();

$server           = $report['server'] ?? null;
$autoload         = $report['autoload'] ?? null;
$transients       = $report['transients'] ?? null;
$tables           = $report['tables'] ?? [];
$db_size          = $report['db_size'] ?? null;
$missing_idx      = $report['missing_idx'] ?? [];
$has_autoload_idx = $report['has_autoload_idx'] ?? true;
$has_data         = ($report !== null);

$status_color = ['ok' => '#10b981', 'warning' => '#f59e0b', 'fail' => '#ef4444', 'eol' => '#ef4444', 'unknown' => '#64748b'];
$status_label = ['ok' => __('HEALTHY', 'phpinfo-wp'), 'warning' => __('WARNING', 'phpinfo-wp'), 'fail' => __('CRITICAL', 'phpinfo-wp'), 'eol' => __('END OF LIFE', 'phpinfo-wp'), 'unknown' => __('UNKNOWN', 'phpinfo-wp')];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
        <div>
            <h1 style="display:flex; align-items:center; gap:8px; margin:0; font-size:22px; font-weight:700; color:#0f172a;">
                <?php _e('Database Health & Schema', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Analyze MySQL/MariaDB version, structural schema indexes, table fragmentation, and TTFB-killing autoload bloat.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
        <?php if ($is_pro): ?>
            <div class="phpinfowp-compat-controls" style="margin:0; gap:8px; display:flex; align-items:center;">
                <?php if ($transients && ($transients['expired'] ?? 0) > 0): ?>
                    <button type="button" id="phpinfowp-db-purge-btn" class="button button-secondary" style="height:36px; line-height:34px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-trash" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                        <span><?php printf(__('Purge %d Expired Transients', 'phpinfo-wp'), (int) $transients['expired']); ?></span>
                    </button>
                <?php endif; ?>
                <button type="button" id="phpinfowp-db-recheck-btn" class="button <?php echo !$has_data ? 'button-primary' : 'button-secondary'; ?>" style="<?php echo !$has_data ? 'background:#6366f1; border-color:#6366f1; color:#fff;' : ''; ?> height:36px; line-height:34px; border-radius:6px; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-update" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                    <span><?php echo $has_data ? __('Re-analyze Database', 'phpinfo-wp') : __('Run Database Health Audit', 'phpinfo-wp'); ?></span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div id="phpinfowp-db-result-area">
        <?php if ($is_free_preview): ?>
            <!-- Free preview: contextual preview cards + centered upgrade card -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Background contextual preview cards -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                    
                    <?php
                    global $wpdb;
                    $real_db_size = Phpinfo_WP_DB_Health::db_size();
                    $real_autoload = Phpinfo_WP_DB_Health::autoload_size();
                    $real_transients = Phpinfo_WP_DB_Health::transients();
                    $real_server = Phpinfo_WP_DB_Health::server_info();
                    $options_table = $wpdb->options ?? ($wpdb->prefix . 'options');
                    ?>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px;">
                        <!-- Metric Card 1 -->
                        <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #6366f1; border-radius:8px; padding:12px 14px;">
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:2px;"><?php _e('Database Size', 'phpinfo-wp'); ?></div>
                            <div style="font-size:15px; font-weight:700; color:#0f172a;"><?php echo esc_html(size_format($real_db_size['total'] ?? 0)); ?></div>
                            <div style="font-size:11.5px; color:#64748b; margin-top:2px;"><?php printf(__('%d Tables · %s %s', 'phpinfo-wp'), (int)($real_db_size['tables'] ?? 0), esc_html($real_server['engine'] ?? 'MySQL'), esc_html($real_server['version'] ?? '')); ?></div>
                        </div>
                        <!-- Metric Card 2 -->
                        <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid <?php echo ($real_autoload['status'] ?? 'ok') === 'ok' ? '#00a32a' : '#d63638'; ?>; border-radius:8px; padding:12px 14px;">
                            <div style="font-size:11px; font-weight:700; color:<?php echo ($real_autoload['status'] ?? 'ok') === 'ok' ? '#15803d' : '#dc2626'; ?>; text-transform:uppercase; margin-bottom:2px;"><?php _e('Autoload Data', 'phpinfo-wp'); ?></div>
                            <div style="font-size:15px; font-weight:700; color:<?php echo ($real_autoload['status'] ?? 'ok') === 'ok' ? '#15803d' : '#dc2626'; ?>;"><?php echo esc_html(size_format($real_autoload['bytes'] ?? 0)); ?> (<?php echo esc_html(strtoupper($real_autoload['status'] ?? 'OK')); ?>)</div>
                            <div style="font-size:11.5px; color:#64748b; margin-top:2px;"><?php printf(__('%d Options loaded on every request', 'phpinfo-wp'), (int)($real_autoload['count'] ?? 0)); ?></div>
                        </div>
                        <!-- Metric Card 3 -->
                        <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid <?php echo !empty($real_transients['expired']) ? '#dba617' : '#00a32a'; ?>; border-radius:8px; padding:12px 14px;">
                            <div style="font-size:11px; font-weight:700; color:<?php echo !empty($real_transients['expired']) ? '#b45309' : '#15803d'; ?>; text-transform:uppercase; margin-bottom:2px;"><?php _e('Transients & Overhead', 'phpinfo-wp'); ?></div>
                            <div style="font-size:15px; font-weight:700; color:<?php echo !empty($real_transients['expired']) ? '#b45309' : '#0f172a'; ?>;"><?php printf(__('%d Transients', 'phpinfo-wp'), (int)($real_transients['total'] ?? 0)); ?></div>
                            <div style="font-size:11.5px; color:#64748b; margin-top:2px;"><?php echo !empty($real_transients['expired']) ? sprintf(__('%d expired transient records', 'phpinfo-wp'), (int)$real_transients['expired']) : sprintf(__('Overhead: %s', 'phpinfo-wp'), size_format($real_db_size['free'] ?? 0)); ?></div>
                        </div>
                    </div>

                    <!-- Table Preview Row -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong style="font-size:13px; color:#0f172a; font-family:monospace;"><?php echo esc_html($options_table); ?></strong>
                            <span style="color:#64748b; font-size:12px; margin-left:8px;"><?php printf(__('Engine: InnoDB · Autoload: %s', 'phpinfo-wp'), size_format($real_autoload['bytes'] ?? 0)); ?></span>
                        </div>
                        <span style="background:<?php echo !empty($real_transients['expired']) ? '#fef3c7' : '#dcfce7'; ?>; color:<?php echo !empty($real_transients['expired']) ? '#b45309' : '#15803d'; ?>; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo !empty($real_transients['expired']) ? sprintf(__('%d EXPIRED TRANSIENTS', 'phpinfo-wp'), (int)$real_transients['expired']) : __('CLEAN SCHEMA', 'phpinfo-wp'); ?></span>
                    </div>

                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

                <!-- Floating Upgrade Card -->
                <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                    <span class="dashicons dashicons-database" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                    <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Database Schema & Autoload Bloat Tools Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                        <?php _e('Identify bloated autoloaded options, add missing MySQL indexes, and optimize fragmented tables to speed up site performance.', 'phpinfo-wp'); ?>
                    </p>
                    <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">💾</span>
                            <span><strong><?php _e('Autoload Bloat Analyzer:', 'phpinfo-wp'); ?></strong> <?php _e('Pinpoint huge transients and dormant plugin options loaded on every single page view.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">⚡</span>
                            <span><strong><?php _e('Missing Index &amp; Slow Query Scanner:', 'phpinfo-wp'); ?></strong> <?php _e('Detect missing database composite indexes that cause MySQL table scans and high CPU usage.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🧹</span>
                            <span><strong><?php _e('1-Click Table Optimization:', 'phpinfo-wp'); ?></strong> <?php _e('Reclaim wasted storage overhead and defragment InnoDB/MyISAM tables safely.', 'phpinfo-wp'); ?></span>
                        </div>
                    </div>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                        <?php _e('Unlock Database Health with Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php elseif (!$has_data): ?>

            <!-- Ready to Audit Empty State -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                    <span class="dashicons dashicons-database" style="font-size:30px; width:30px; height:30px;"></span>
                </div>
                <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php _e('Ready to Audit Database Health & Schema', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 20px; color:#64748b; font-size:14px; max-width:520px; margin-left:auto; margin-right:auto; line-height:1.5;">
                    <?php _e('Click "Run Database Health Audit" above to asynchronously inspect MySQL version lifecycle, autoload memory footprint, missing indexes, and fragmented tables.', 'phpinfo-wp'); ?>
                </p>
                <div style="display:inline-flex; align-items:center; gap:8px; font-size:12px; color:#94a3b8;">
                    <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:16px; width:16px; height:16px;"></span>
                    <?php _e('Zero Page Lag — Runs via non-blocking asynchronous AJAX worker.', 'phpinfo-wp'); ?>
                </div>
            </div>

        <?php else: ?>

            <!-- SECTION 1: DATABASE OVERVIEW METRICS -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:20px;">
                <!-- Engine & Version -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                        <?php _e('Database Engine', 'phpinfo-wp'); ?>
                    </div>
                    <div style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:4px;">
                        <?php echo esc_html(($server['engine'] ?? 'MySQL') . ' ' . ($server['version'] ?? '')); ?>
                    </div>
                    <div style="font-size:12px; color:<?php echo $status_color[$server['status'] ?? 'unknown']; ?>; font-weight:600;">
                        <?php echo esc_html($status_label[$server['status'] ?? 'unknown']); ?>
                        <?php if (!empty($server['eol'])): ?>
                            <span style="font-weight:400; color:#64748b;">(EOL: <?php echo esc_html($server['eol']); ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Database Size -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                        <?php _e('Database Footprint', 'phpinfo-wp'); ?>
                    </div>
                    <div style="font-size:15px; font-weight:700; color:#0f172a; margin-bottom:4px;">
                        <?php echo size_format((int)($db_size['total'] ?? 0)); ?>
                    </div>
                    <div style="font-size:12px; color:#64748b;">
                        <?php printf(__('%d tables (%s data, %s index)', 'phpinfo-wp'), (int)($db_size['tables'] ?? 0), size_format((int)($db_size['data'] ?? 0)), size_format((int)($db_size['index'] ?? 0))); ?>
                    </div>
                </div>

                <!-- Autoload Size -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                        <?php _e('Autoload Options', 'phpinfo-wp'); ?>
                    </div>
                    <div style="font-size:15px; font-weight:700; color:<?php echo ($autoload['bytes'] ?? 0) > 1048576 ? '#b91c1c' : '#15803d'; ?>; margin-bottom:4px;">
                        <?php echo size_format((int)($autoload['bytes'] ?? 0)); ?>
                    </div>
                    <div style="font-size:12px; color:#64748b;">
                        <?php printf(__('%d options loaded on every page', 'phpinfo-wp'), (int)($autoload['count'] ?? 0)); ?>
                    </div>
                </div>

                <!-- Transients -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                        <?php _e('Transients State', 'phpinfo-wp'); ?>
                    </div>
                    <div style="font-size:15px; font-weight:700; color:<?php echo ($transients['expired'] ?? 0) > 100 ? '#b45309' : '#0f172a'; ?>; margin-bottom:4px;">
                        <?php echo number_format((int)($transients['total'] ?? 0)); ?> <?php _e('total', 'phpinfo-wp'); ?>
                    </div>
                    <div style="font-size:12px; color:#64748b;">
                        <?php printf(__('%d expired ready to clean', 'phpinfo-wp'), (int)($transients['expired'] ?? 0)); ?>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: AUTOLOAD BLOAT VISUALIZER -->
            <?php if (!empty($autoload['top'])): ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px 24px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <h2 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('Autoload Bloat Visualizer', 'phpinfo-wp'); ?></h2>
                    <p style="margin:0 0 16px; font-size:13px; color:#64748b;">
                        <?php _e('Options with <code>autoload=yes</code> are loaded into RAM on <strong>every single WordPress request</strong>. Heavy options kill Time to First Byte (TTFB).', 'phpinfo-wp'); ?>
                    </p>

                    <?php if (($autoload['bytes'] ?? 0) > 1048576): ?>
                        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:12px 14px; color:#b91c1c; font-size:13px; margin-bottom:16px;">
                            <strong><?php _e('High Autoload Size Detected:', 'phpinfo-wp'); ?></strong> <?php printf(__('Loading %s of options on every page request. WordPress recommends keeping total autoload under 1 MB.', 'phpinfo-wp'), size_format($autoload['bytes'])); ?>
                        </div>
                    <?php endif; ?>

                    <table class="widefat striped" style="border:1px solid #e2e8f0; border-radius:6px; overflow:hidden;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:50%;"><?php _e('Option Name', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:25%;"><?php _e('Memory Footprint', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:25%;"><?php _e('Status', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($autoload['top'] as $opt):
                                $is_critical = $opt->size > 512000;
                                $is_warning  = $opt->size > 102400;
                                $opt_color   = $is_critical ? '#ef4444' : ($is_warning ? '#f59e0b' : '#10b981');
                            ?>
                                <tr>
                                    <td style="padding:10px 14px; vertical-align:middle;">
                                        <code><?php echo esc_html($opt->option_name); ?></code>
                                        <?php if (strpos($opt->option_name, '_transient_') === 0): ?>
                                            <span style="display:inline-block; margin-left:8px; padding:1px 6px; background:#f1f5f9; border-radius:3px; font-size:10px; color:#475569;"><?php _e('Transient', 'phpinfo-wp'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px; vertical-align:middle; color:<?php echo $opt_color; ?>; font-weight:700; font-size:13.5px;">
                                        <?php echo size_format((int)$opt->size); ?>
                                    </td>
                                    <td style="padding:10px 14px; vertical-align:middle;">
                                        <?php if ($is_critical): ?>
                                            <span style="background:#ef4444; color:#fff; font-weight:700; font-size:11px; padding:2px 8px; border-radius:4px;"><?php _e('TTFB KILLER', 'phpinfo-wp'); ?></span>
                                        <?php elseif ($is_warning): ?>
                                            <span style="background:#f59e0b; color:#fff; font-weight:700; font-size:11px; padding:2px 8px; border-radius:4px;"><?php _e('HEAVY BLOAT', 'phpinfo-wp'); ?></span>
                                        <?php else: ?>
                                            <span style="background:#10b981; color:#fff; font-weight:700; font-size:11px; padding:2px 8px; border-radius:4px;"><?php _e('NORMAL', 'phpinfo-wp'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- SECTION 3: MISSING INDEX SCANNER -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px 24px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <h2 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('Missing Index & Schema Scanner', 'phpinfo-wp'); ?></h2>
                <p style="margin:0 0 16px; font-size:13px; color:#64748b;">
                    <?php _e('Analyzes tables with large record counts that lack secondary MySQL indexes. Unindexed queries trigger expensive <strong>full table scans</strong>.', 'phpinfo-wp'); ?>
                </p>

                <?php if (!$has_autoload_idx): ?>
                    <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:12px 14px; color:#b91c1c; font-size:13px; margin-bottom:16px;">
                        <strong><?php _e('CRITICAL: Missing Autoload Index', 'phpinfo-wp'); ?></strong> <?php _e('Your wp_options table is missing the autoload index, causing full table scans on every request.', 'phpinfo-wp'); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($missing_idx)): ?>
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:12px 16px; color:#15803d; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-yes-alt" style="font-size:18px; width:18px; height:18px;"></span>
                        <?php _e('Schema Optimized: No large database tables are missing secondary indexes.', 'phpinfo-wp'); ?>
                    </div>
                <?php else: ?>
                    <table class="widefat striped" style="border:1px solid #e2e8f0; border-radius:6px; overflow:hidden;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:40%;"><?php _e('Table Name', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:20%;"><?php _e('Estimated Rows', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:20%;"><?php _e('Data Size', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:20%;"><?php _e('Risk Level', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missing_idx as $idx):
                                $rows       = (int) $idx['TABLE_ROWS'];
                                $risk       = $rows > 50000 ? 'Critical' : ($rows > 10000 ? 'High' : 'Medium');
                                $risk_color = $rows > 50000 ? '#ef4444' : ($rows > 10000 ? '#f59e0b' : '#64748b');
                            ?>
                                <tr>
                                    <td style="padding:10px 14px; vertical-align:middle;"><code><?php echo esc_html($idx['TABLE_NAME']); ?></code></td>
                                    <td style="padding:10px 14px; vertical-align:middle; font-weight:600;"><?php echo number_format($rows); ?></td>
                                    <td style="padding:10px 14px; vertical-align:middle;"><?php echo size_format((int)$idx['DATA_LENGTH']); ?></td>
                                    <td style="padding:10px 14px; vertical-align:middle;">
                                        <span style="background:<?php echo $risk_color; ?>; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px;">
                                            <?php echo esc_html($risk); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- SECTION 4: ALL TABLES INSPECTOR -->
            <?php if (!empty($tables)): 
                $tbl_per_page    = 15;
                $tbl_total       = count($tables);
                $tbl_total_pages = max(1, (int) ceil($tbl_total / $tbl_per_page));
                $tbl_paged       = max(1, min($tbl_total_pages, (int) ($_GET['paged'] ?? 1)));
                $paged_tables    = array_slice($tables, ($tbl_paged - 1) * $tbl_per_page, $tbl_per_page);
            ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px 24px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <h2 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('All Tables & Fragmentation Inspector', 'phpinfo-wp'); ?></h2>
                    <p style="margin:0 0 16px; font-size:13px; color:#64748b;"><?php _e('Complete breakdown of database storage engines, index sizes, and table overhead.', 'phpinfo-wp'); ?></p>

                    <table class="widefat striped" style="border:1px solid #e2e8f0; border-radius:6px; overflow:hidden;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px; font-weight:600; color:#475569;"><?php _e('Table Name', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:100px;"><?php _e('Rows', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:110px;"><?php _e('Data Size', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:110px;"><?php _e('Index Size', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:110px;"><?php _e('Overhead', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px; font-weight:600; color:#475569; width:90px;"><?php _e('Engine', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paged_tables as $t):
                                $ovr_color = $t['overhead_pct'] > 30 ? '#ef4444' : ($t['overhead_pct'] > 10 ? '#f59e0b' : '#64748b');
                            ?>
                                <tr>
                                    <td style="padding:10px 14px; vertical-align:middle;"><code><?php echo esc_html($t['name']); ?></code></td>
                                    <td style="padding:10px 14px; vertical-align:middle; font-weight:600;"><?php echo number_format((int)$t['rows']); ?></td>
                                    <td style="padding:10px 14px; vertical-align:middle;"><?php echo size_format((int)$t['data']); ?></td>
                                    <td style="padding:10px 14px; vertical-align:middle;"><?php echo size_format((int)$t['index']); ?></td>
                                    <td style="padding:10px 14px; vertical-align:middle; color:<?php echo $ovr_color; ?>; font-weight:600;">
                                        <?php if ($t['free'] > 0): ?>
                                            <?php echo size_format((int)$t['free']); ?> <span style="font-size:11px; font-weight:400;">(<?php echo esc_html($t['overhead_pct']); ?>%)</span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px; vertical-align:middle; font-size:12px; color:#64748b;"><?php echo esc_html($t['engine']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination Controls -->
        <?php if ($tbl_total > 0): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px;">
                <div style="font-size:13px; color:#64748b;">
                    <?php printf(
                        __("Showing %1\$d &ndash; %2\$d of %3\$s database tables", "phpinfo-wp"),
                        ($tbl_paged - 1) * $tbl_per_page + 1,
                        min($tbl_total, $tbl_paged * $tbl_per_page),
                        number_format($tbl_total)
                    ); ?>
                </div>
                <div style="display:flex; gap:6px; align-items:center;">
                    <?php if ($tbl_paged > 2): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => 1])); ?>" class="button button-secondary" title="<?php esc_attr_e("First page", "phpinfo-wp"); ?>">&laquo;&laquo;</a>
                    <?php endif; ?>
                    <?php if ($tbl_paged > 1): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => $tbl_paged - 1])); ?>" class="button button-secondary">&laquo; <?php _e("Previous", "phpinfo-wp"); ?></a>
                    <?php else: ?>
                        <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;">&laquo; <?php _e("Previous", "phpinfo-wp"); ?></span>
                    <?php endif; ?>
                    <span style="font-size:13px; line-height:30px; padding:0 8px; color:#334155; font-weight:600;">
                        <?php printf(__("Page %d of %d", "phpinfo-wp"), $tbl_paged, max(1, $tbl_total_pages)); ?>
                    </span>
                    <?php if ($tbl_paged < $tbl_total_pages): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => $tbl_paged + 1])); ?>" class="button button-secondary"><?php _e("Next", "phpinfo-wp"); ?> &raquo;</a>
                    <?php else: ?>
                        <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;"><?php _e("Next", "phpinfo-wp"); ?> &raquo;</span>
                    <?php endif; ?>
                    <?php if ($tbl_paged < $tbl_total_pages - 1): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => $tbl_total_pages])); ?>" class="button button-secondary" title="<?php esc_attr_e("Last page", "phpinfo-wp"); ?>">&raquo;&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var nonce = '<?php echo wp_create_nonce("phpinfowp_db_nonce"); ?>';

    var recheckBtn = document.getElementById('phpinfowp-db-recheck-btn');
    if (recheckBtn) {
        recheckBtn.addEventListener('click', function(e) {
            e.preventDefault();
            recheckBtn.disabled = true;
            recheckBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span> <?php echo esc_js(__('Analyzing Database...', 'phpinfo-wp')); ?>';

            if (window.phpinfowpShowScanBanner) {
                window.phpinfowpShowScanBanner('<?php echo esc_js(__('Analyzing Database Health...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Inspecting tables, index overhead & bloat (2–5s)', 'phpinfo-wp')); ?>');
            }

            var resultArea = document.getElementById('phpinfowp-db-result-area');
            if (resultArea) {
                resultArea.innerHTML = '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:48px 24px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">' +
                    '<div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">' +
                    '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>' +
                    '</div>' +
                    '<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php echo esc_js(__('Analyzing Database Schema & Metrics...', 'phpinfo-wp')); ?></h3>' +
                    '<p style="margin:0; color:#64748b; font-size:14px;"><?php echo esc_js(__('Inspecting tables, autoload footprint, and secondary indexes asynchronously...', 'phpinfo-wp')); ?></p>' +
                    '</div>';
            }

            var data = new FormData();
            data.append('action', 'phpinfowp_db_scan');
            data.append('nonce', nonce);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function() { window.location.reload(); })
                .catch(function() { window.location.reload(); });
        });
    }

    var purgeBtn = document.getElementById('phpinfowp-db-purge-btn');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.phpinfowpConfirm({
                title: '<?php echo esc_js(__('Purge Expired Transients', 'phpinfo-wp')); ?>',
                message: '<?php echo esc_js(__('Are you sure you want to purge all expired transients from the database? This is safe and frees up storage space.', 'phpinfo-wp')); ?>',
                confirmText: '<?php echo esc_js(__('Purge Transients', 'phpinfo-wp')); ?>',
                isDanger: false
            }).then(function(confirmed) {
                if (!confirmed) return;
                purgeBtn.disabled = true;
                purgeBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span> <?php echo esc_js(__('Purging...', 'phpinfo-wp')); ?>';

                if (window.phpinfowpShowScanBanner) {
                    window.phpinfowpShowScanBanner('<?php echo esc_js(__('Purging Expired Transients...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Cleaning transient records from wp_options (1–3s)', 'phpinfo-wp')); ?>');
                }

                var data = new FormData();
                data.append('action', 'phpinfowp_db_purge_transients');
                data.append('nonce', nonce);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        window.location.reload();
                    })
                    .catch(function() { window.location.reload(); });
            });
        });
    }
});
</script>
