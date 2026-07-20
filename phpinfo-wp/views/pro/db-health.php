<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) {
    phpinfowp_render_feature_lock([
        'feature'  => 'Database Health',
        'icon'     => 'dashicons-database',
        'tagline'  => 'MySQL/MariaDB version, EOL status, total database size, and autoload bloat that slows TTFB.',
        'previews' => [
            'Engine: <strong>—</strong>',
            'DB size: <strong>— MB</strong>',
            'Autoload: <strong>— KB</strong>',
            'EOL status: <strong>—</strong>',
        ],
    ]);
    return;
}

if (isset($_POST['phpinfowp_purge_transients']) && check_admin_referer('phpinfowp_db_nonce')) {
    $n = Phpinfo_WP_DB_Health::purge_expired_transients();
    $purge_msg = "Purged {$n} expired transients.";
}

$server    = Phpinfo_WP_DB_Health::server_info();
$autoload  = Phpinfo_WP_DB_Health::autoload_size();
$transients= Phpinfo_WP_DB_Health::transients();
$tables    = Phpinfo_WP_DB_Health::tables();
$db_size   = Phpinfo_WP_DB_Health::db_size();
$missing_idx = Phpinfo_WP_DB_Health::missing_indexes();
$has_autoload_idx = Phpinfo_WP_DB_Health::check_autoload_index();

$status_color = ['ok' => '#00a32a', 'warning' => '#dba617', 'fail' => '#d63638', 'eol' => '#d63638', 'unknown' => '#888'];
$status_label = ['ok' => 'HEALTHY', 'warning' => 'WARNING', 'fail' => 'CRITICAL', 'eol' => 'END OF LIFE', 'unknown' => 'UNKNOWN'];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Advanced DB Health & Schema Analyzer <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Analyze MySQL/MariaDB version, structural schema issues, and TTFB-killing autoload bloat.', 'phpinfo-wp'); ?></p>
        </div>
    </div>

    <?php if (isset($purge_msg)): ?>
        <div class="notice notice-success inline" style="margin:0 0 20px"><p><?php echo esc_html($purge_msg); ?></p></div>
    <?php endif; ?>

    <!-- DB server card -->
    <?php if ($server):
        $color = $status_color[$server['status']];
        $label = $status_label[$server['status']];
    ?>
    <div class="phpinfowp-dbh-server" style="border-left-color:<?php echo $color; ?>">
        <div>
            <div class="phpinfowp-dbh-server-engine"><?php echo esc_html($server['engine']); ?> <?php echo esc_html($server['version']); ?></div>
            <div class="phpinfowp-dbh-server-full"><?php echo esc_html($server['full']); ?></div>
        </div>
        <div class="phpinfowp-dbh-server-status">
            <span class="phpinfowp-dbh-badge" style="background:<?php echo $color; ?>"><?php echo $label; ?></span>
            <?php if ($server['eol']): ?>
                <div class="phpinfowp-dbh-server-eol">
                    EOL: <strong><?php echo esc_html($server['eol']); ?></strong>
                    <?php if ($server['days'] !== null): ?>
                        &middot;
                        <?php if ($server['days'] < 0): ?>
                            <span style="color:#d63638"><?php echo abs($server['days']); ?> days ago</span>
                        <?php else: ?>
                            <?php echo (int)$server['days']; ?> days
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Key metrics row -->
    <div class="phpinfowp-dbh-metrics">

        <div class="phpinfowp-dbh-metric">
            <div class="phpinfowp-dbh-metric-label"><?php _e('Database size', 'phpinfo-wp'); ?></div>
            <div class="phpinfowp-dbh-metric-value"><?php echo size_format($db_size['total']); ?></div>
            <div class="phpinfowp-dbh-metric-sub"><?php echo (int)$db_size['tables']; ?> tables</div>
        </div>

        <?php
        $a_color = $status_color[$autoload['status']];
        $a_label = $status_label[$autoload['status']];
        ?>
        <div class="phpinfowp-dbh-metric" style="border-top-color:<?php echo $a_color; ?>">
            <div class="phpinfowp-dbh-metric-label">Autoload data <span class="phpinfowp-dbh-metric-badge" style="background:<?php echo $a_color; ?>"><?php echo $a_label; ?></span></div>
            <div class="phpinfowp-dbh-metric-value"><?php echo size_format($autoload['bytes']); ?></div>
            <div class="phpinfowp-dbh-metric-sub"><?php echo (int)$autoload['count']; ?> options &middot; loaded on every page</div>
        </div>

        <div class="phpinfowp-dbh-metric">
            <div class="phpinfowp-dbh-metric-label"><?php _e('Transients', 'phpinfo-wp'); ?></div>
            <div class="phpinfowp-dbh-metric-value"><?php echo (int)$transients['total']; ?></div>
            <div class="phpinfowp-dbh-metric-sub">
                <?php if ($transients['expired']): ?>
                    <span style="color:#d63638"><strong><?php echo (int)$transients['expired']; ?></strong> expired</span> — clogging the table
                <?php else: ?>
                    All current
                <?php endif; ?>
            </div>
        </div>

        <div class="phpinfowp-dbh-metric">
            <div class="phpinfowp-dbh-metric-label"><?php _e('Overhead', 'phpinfo-wp'); ?></div>
            <div class="phpinfowp-dbh-metric-value"><?php echo size_format($db_size['free']); ?></div>
            <div class="phpinfowp-dbh-metric-sub"><?php _e('Reclaimable via OPTIMIZE TABLE', 'phpinfo-wp'); ?></div>
        </div>

    </div>

    <?php if ($transients['expired']): ?>
        <form method="post" style="margin-bottom:24px">
            <?php wp_nonce_field('phpinfowp_db_nonce'); ?>
            <button type="submit" name="phpinfowp_purge_transients" value="1" class="button button-secondary">
                <span class="dashicons dashicons-trash" style="vertical-align:middle"></span>
                Purge <?php echo (int)$transients['expired']; ?> expired transients
            </button>
        </form>
    <?php endif; ?>

    <!-- Autoload Bloat Visualizer -->
    <?php if ($autoload['top']): ?>
        <h2 class="phpinfowp-section-heading" style="margin-top:40px; display:flex; align-items:center; gap:8px;">
            <span class="dashicons dashicons-database" style="color:#007cba;"></span> Autoload Bloat Visualizer
        </h2>
        <p class="description" style="margin:0 0 16px; max-width:800px;">
            Options with <code>autoload=yes</code> are loaded into PHP RAM on <strong><?php _e('every single page request', 'phpinfo-wp'); ?></strong>. Massive strings (like old transient data, bloated theme settings, or heavy cron schedules) are silent TTFB killers.
        </p>
        
        <?php if ($autoload['bytes'] > 1048576): ?>
            <div class="notice notice-warning inline" style="margin-bottom:20px; border-left-color:#d63638;">
                <p><strong><?php _e('Warning: High Autoload Size!', 'phpinfo-wp'); ?></strong> You are loading <?php echo size_format($autoload['bytes']); ?> of options data on every page load. WordPress recommends keeping this under 1 MB.</p>
            </div>
        <?php endif; ?>

        <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; overflow:hidden; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
            <table class="wp-list-table widefat striped" style="border:none; margin:0;">
                <thead>
                    <tr>
                        <th style="width:50%; padding-left:16px;"><?php _e('Option Name', 'phpinfo-wp'); ?></th>
                        <th style="width:25%"><?php _e('Memory Footprint', 'phpinfo-wp'); ?></th>
                        <th style="width:25%"><?php _e('Recommendation', 'phpinfo-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($autoload['top'] as $opt):
                        // Flag anything over 500KB as critical, over 100KB as warning
                        $is_critical = $opt->size > 512000;
                        $is_warning = $opt->size > 102400;
                        $opt_color = $is_critical ? '#d63638' : ($is_warning ? '#dba617' : '#555');
                    ?>
                        <tr>
                            <td style="padding-left:16px;">
                                <code><?php echo esc_html($opt->option_name); ?></code>
                                <?php if (strpos($opt->option_name, '_transient_') === 0): ?>
                                    <span style="display:inline-block; margin-left:8px; padding:1px 6px; background:#e5e5e5; border-radius:3px; font-size:10px; color:#555;"><?php _e('Transient', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="color:<?php echo $opt_color; ?>; font-weight:600; font-size:14px;">
                                <?php echo size_format((int)$opt->size); ?>
                            </td>
                            <td>
                                <?php if ($is_critical): ?>
                                    <span style="color:#d63638; font-weight:600; font-size:12px;"><?php _e('TTFB KILLER', 'phpinfo-wp'); ?></span>
                                    <div style="font-size:11px; color:#666; margin-top:2px;">Must delete or set autoload=no</div>
                                <?php elseif ($is_warning): ?>
                                    <span style="color:#dba617; font-weight:600; font-size:12px;"><?php _e('BLOAT', 'phpinfo-wp'); ?></span>
                                    <div style="font-size:11px; color:#666; margin-top:2px;">Consider autoload=no</div>
                                <?php else: ?>
                                    <span style="color:#00a32a; font-size:12px;"><?php _e('Normal', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Missing Index Scanner -->
    <h2 class="phpinfowp-section-heading" style="margin-top:40px; display:flex; align-items:center; gap:8px;">
        <span class="dashicons dashicons-search" style="color:#d63638;"></span> Missing Index Scanner
    </h2>
    <p class="description" style="margin:0 0 16px; max-width:800px;"><?php _e('Analyzes your database schema to find custom tables (often created by poorly coded plugins) that are missing critical MySQL indexes. Queries against these tables trigger <strong>full table scans</strong>, destroying database performance.', 'phpinfo-wp'); ?></p>

    <?php if (!$has_autoload_idx): ?>
        <div class="notice notice-error inline" style="margin-bottom:20px;">
            <p><strong><?php _e('CRITICAL:', 'phpinfo-wp'); ?></strong> Your <code><?php global $wpdb; echo $wpdb->options; ?></code> table is missing the <code>autoload</code> index! This is a known issue on older WordPress installs and severely impacts performance. You must manually add it via phpMyAdmin or WP-CLI.</p>
        </div>
    <?php endif; ?>

    <?php if (!$missing_idx): ?>
        <div style="background:#e8f5e9; border:1px solid #c8e6c9; padding:16px; border-radius:4px; display:flex; align-items:center; gap:12px;">
            <span class="dashicons dashicons-yes-alt" style="color:#00a32a; font-size:24px; width:24px; height:24px;"></span>
            <div>
                <strong style="color:#1b5e20;"><?php _e('Schema Optimized', 'phpinfo-wp'); ?></strong>
                <p style="margin:4px 0 0; color:#2e7d32; font-size:13px;"><?php _e('No large tables are missing secondary indexes.', 'phpinfo-wp'); ?></p>
            </div>
        </div>
    <?php else: ?>
        <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; overflow:hidden; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
            <table class="wp-list-table widefat striped" style="border:none; margin:0;">
                <thead>
                    <tr>
                        <th style="width:40%; padding-left:16px;"><?php _e('Table Name', 'phpinfo-wp'); ?></th>
                        <th style="width:20%"><?php _e('Rows (Approx)', 'phpinfo-wp'); ?></th>
                        <th style="width:20%"><?php _e('Data Size', 'phpinfo-wp'); ?></th>
                        <th style="width:20%"><?php _e('Risk Level', 'phpinfo-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($missing_idx as $idx):
                        $rows = (int) $idx['TABLE_ROWS'];
                        $risk = $rows > 50000 ? 'Critical' : ($rows > 10000 ? 'High' : 'Medium');
                        $risk_color = $rows > 50000 ? '#d63638' : ($rows > 10000 ? '#dba617' : '#555');
                    ?>
                        <tr>
                            <td style="padding-left:16px;"><code><?php echo esc_html($idx['TABLE_NAME']); ?></code></td>
                            <td style="font-weight:600;"><?php echo number_format($rows); ?></td>
                            <td><?php echo size_format($idx['DATA_LENGTH']); ?></td>
                            <td>
                                <span style="display:inline-block; background:<?php echo $risk_color; ?>; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:12px; letter-spacing:0.3px;">
                                    <?php echo $risk; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="description" style="margin-top:12px; font-size:12px;"><?php _e('These tables have > 500 rows but absolutely zero secondary indexes. Consider reporting this to the plugin developer, or creating indexes manually if you know which columns are queried frequently.', 'phpinfo-wp'); ?></p>
    <?php endif; ?>

    <!-- All tables -->
    <h2 class="phpinfowp-section-heading" style="margin-top:32px"><?php _e('All Tables', 'phpinfo-wp'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Table', 'phpinfo-wp'); ?></th>
                <th style="width:90px"><?php _e('Rows', 'phpinfo-wp'); ?></th>
                <th style="width:100px"><?php _e('Data', 'phpinfo-wp'); ?></th>
                <th style="width:100px"><?php _e('Index', 'phpinfo-wp'); ?></th>
                <th style="width:110px"><?php _e('Overhead', 'phpinfo-wp'); ?></th>
                <th style="width:80px"><?php _e('Engine', 'phpinfo-wp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tables as $t):
                $ovr_color = $t['overhead_pct'] > 30 ? '#d63638' : ($t['overhead_pct'] > 10 ? '#dba617' : '#888');
            ?>
                <tr>
                    <td><code><?php echo esc_html($t['name']); ?></code></td>
                    <td><?php echo number_format($t['rows']); ?></td>
                    <td><?php echo size_format($t['data']); ?></td>
                    <td><?php echo size_format($t['index']); ?></td>
                    <td style="color:<?php echo $ovr_color; ?>">
                        <?php if ($t['free'] > 0): ?>
                            <?php echo size_format($t['free']); ?> <span style="font-size:11px">(<?php echo esc_html($t['overhead_pct']); ?>%)</span>
                        <?php else: ?>
                            <span style="color:#aaa">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span style="font-size:11px;color:#666"><?php echo esc_html($t['engine']); ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>
