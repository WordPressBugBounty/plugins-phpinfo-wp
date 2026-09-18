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
            <h1>
                <?php _e('Network Dashboard', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Server-level info and per-site environment statistics across your WordPress Multisite network.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
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

    <?php
    $net_per_page    = 15;
    $net_total       = count($sites);
    $net_total_pages = max(1, (int) ceil($net_total / $net_per_page));
    $net_paged       = max(1, min($net_total_pages, (int) ($_GET['paged'] ?? 1)));
    $paged_sites     = array_slice($sites, ($net_paged - 1) * $net_per_page, $net_per_page);
    ?>

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
            <?php foreach ($paged_sites as $site):
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

    <!-- Pagination Controls -->
        <?php if ($net_total > 0): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px;">
                <div style="font-size:13px; color:#64748b;">
                    <?php printf(
                        __("Showing %1\$d &ndash; %2\$d of %3\$s sites", "phpinfo-wp"),
                        ($net_paged - 1) * $net_per_page + 1,
                        min($net_total, $net_paged * $net_per_page),
                        number_format($net_total)
                    ); ?>
                </div>
                <div style="display:flex; gap:6px; align-items:center;">
                    <?php if ($net_paged > 2): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => 1])); ?>" class="button button-secondary" title="<?php esc_attr_e("First page", "phpinfo-wp"); ?>">&laquo;&laquo;</a>
                    <?php endif; ?>
                    <?php if ($net_paged > 1): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => $net_paged - 1])); ?>" class="button button-secondary">&laquo; <?php _e("Previous", "phpinfo-wp"); ?></a>
                    <?php else: ?>
                        <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;">&laquo; <?php _e("Previous", "phpinfo-wp"); ?></span>
                    <?php endif; ?>
                    <span style="font-size:13px; line-height:30px; padding:0 8px; color:#334155; font-weight:600;">
                        <?php printf(__("Page %d of %d", "phpinfo-wp"), $net_paged, max(1, $net_total_pages)); ?>
                    </span>
                    <?php if ($net_paged < $net_total_pages): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => $net_paged + 1])); ?>" class="button button-secondary"><?php _e("Next", "phpinfo-wp"); ?> &raquo;</a>
                    <?php else: ?>
                        <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;"><?php _e("Next", "phpinfo-wp"); ?> &raquo;</span>
                    <?php endif; ?>
                    <?php if ($net_paged < $net_total_pages - 1): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => $net_total_pages])); ?>" class="button button-secondary" title="<?php esc_attr_e("Last page", "phpinfo-wp"); ?>">&raquo;&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
</div>
