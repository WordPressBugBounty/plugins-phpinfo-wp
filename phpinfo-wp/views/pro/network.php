<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$eol      = Phpinfo_WP_EOL::status();
$is_pro   = Phpinfo_WP_License::is_valid();
$sites    = Phpinfo_WP_Network::sites();
$db       = $is_pro ? Phpinfo_WP_DB_Health::server_info() : null;
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1><?php _e('phpinfo() WP — Network', 'phpinfo-wp'); ?></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Server-level info applies to all sites in the network. Per-site stats appear below.', 'phpinfo-wp'); ?></p>
        </div>
    </div>

    <!-- Shared server info -->
    <div class="phpinfowp-report-grid">
        <div class="phpinfowp-report-stat">
            <div class="phpinfowp-report-stat-label"><?php _e('PHP', 'phpinfo-wp'); ?></div>
            <div class="phpinfowp-report-stat-value"><?php echo esc_html(PHP_VERSION); ?></div>
            <div class="phpinfowp-report-stat-sub">
                <?php if ($eol['status'] === 'eol'): ?>
                    <span style="color:#d63638"><?php _e('End of life', 'phpinfo-wp'); ?></span>
                <?php elseif ($eol['status'] === 'warning'): ?>
                    <span style="color:#dba617">EOL in <?php echo (int)$eol['days']; ?> days</span>
                <?php else: ?>
                    <span style="color:#00a32a"><?php _e('Supported', 'phpinfo-wp'); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="phpinfowp-report-stat">
            <div class="phpinfowp-report-stat-label"><?php _e('WordPress', 'phpinfo-wp'); ?></div>
            <div class="phpinfowp-report-stat-value"><?php echo esc_html(get_bloginfo('version')); ?></div>
            <div class="phpinfowp-report-stat-sub"><?php echo count($sites); ?> sites</div>
        </div>
        <div class="phpinfowp-report-stat">
            <div class="phpinfowp-report-stat-label"><?php _e('Memory', 'phpinfo-wp'); ?></div>
            <div class="phpinfowp-report-stat-value"><?php echo esc_html(ini_get('memory_limit')); ?></div>
        </div>
        <?php if ($db): ?>
        <div class="phpinfowp-report-stat">
            <div class="phpinfowp-report-stat-label"><?php _e('Database', 'phpinfo-wp'); ?></div>
            <div class="phpinfowp-report-stat-value"><?php echo esc_html($db['engine'] . ' ' . $db['version']); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <h2 class="phpinfowp-section-heading" style="margin-top:28px"><?php _e('Per-site health', 'phpinfo-wp'); ?></h2>

    <?php if (!$is_pro): ?>
        <div class="notice notice-info inline"><p>Per-site autoload analysis requires a Pro license. <a href="<?php echo esc_url(network_admin_url('admin.php?page=piwp-network')); ?>">Activate one</a>.</p></div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:60px"><?php _e('ID', 'phpinfo-wp'); ?></th>
                <th><?php _e('Site', 'phpinfo-wp'); ?></th>
                <th><?php _e('URL', 'phpinfo-wp'); ?></th>
                <th style="width:160px"><?php _e('Autoload data', 'phpinfo-wp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sites as $site):
                $color = '#888';
                if ($site['autoload_status'] === 'warning') $color = '#dba617';
                if ($site['autoload_status'] === 'fail')    $color = '#d63638';
                if ($site['autoload_status'] === 'ok')      $color = '#00a32a';
            ?>
                <tr>
                    <td><?php echo (int)$site['blog_id']; ?></td>
                    <td><?php echo esc_html($site['name']); ?></td>
                    <td><a href="<?php echo esc_url($site['url']); ?>" target="_blank"><?php echo esc_html($site['url']); ?></a></td>
                    <td>
                        <?php if ($site['autoload'] !== null): ?>
                            <span style="color:<?php echo $color; ?>;font-weight:600">
                                <?php echo size_format((int)$site['autoload']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#aaa">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
