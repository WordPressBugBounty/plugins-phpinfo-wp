<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro   = Phpinfo_WP_License::is_valid();
$current  = Phpinfo_WP_EOL::status();
$timeline = Phpinfo_WP_EOL::timeline();

$status_labels = [
    'eol'     => ['label' => __('End of Life', 'phpinfo-wp'),    'class' => 'eol-status-eol'],
    'warning' => ['label' => __('EOL < 90 days', 'phpinfo-wp'),  'class' => 'eol-status-warning'],
    'ok'      => ['label' => __('Supported', 'phpinfo-wp'),       'class' => 'eol-status-ok'],
    'unknown' => ['label' => __('Unknown', 'phpinfo-wp'),         'class' => 'eol-status-unknown'],
];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>
                <?php _e('PHP EOL Timeline', 'phpinfo-wp'); ?>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Official end-of-life dates and security support schedules for every PHP runtime version.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>



    <!-- Version timeline -->
    <table class="wp-list-table widefat fixed striped phpinfowp-eol-table">
        <thead>
            <tr>
                <th style="width:140px"><?php _e('Version', 'phpinfo-wp'); ?></th>
                <th style="width:130px"><?php _e('EOL Date', 'phpinfo-wp'); ?></th>
                <th style="width:150px"><?php _e('Status', 'phpinfo-wp'); ?></th>
                <th><?php _e('Days', 'phpinfo-wp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_reverse($timeline) as $row): ?>
                <tr <?php if ($row['current']) echo 'style="background:#f3f0ff;font-weight:600"'; ?>>
                    <td>
                        <?php printf( __('PHP %s', 'phpinfo-wp'), esc_html($row['version']) ); ?>
                        <?php if ($row['current']): ?>
                            <span style="font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;background:#777BB3;color:#fff;margin-left:4px"><?php _e('YOU', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($row['eol']); ?></td>
                    <td>
                        <span class="eol-badge eol-badge-<?php echo esc_attr($row['status']); ?>">
                            <?php echo esc_html($status_labels[$row['status']]['label']); ?>
                        </span>
                    </td>
                    <td style="color:<?php echo $row['days'] < 0 ? '#d63638' : ($row['days'] < 90 ? '#dba617' : '#555'); ?>">
                        <?php if ($row['days'] < 0): ?>
                            <?php printf( _n('%s day ago', '%s days ago', abs($row['days']), 'phpinfo-wp'), esc_html(abs($row['days'])) ); ?>
                        <?php else: ?>
                            <?php printf( _n('%s day', '%s days', $row['days'], 'phpinfo-wp'), esc_html($row['days']) ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="description" style="margin-top:10px">
        <?php printf(
            __('Source: %s — updated in each plugin release.', 'phpinfo-wp'),
            '<a href="https://www.php.net/supported-versions.php" target="_blank">php.net/supported-versions</a>'
        ); ?>
    </p>

    <?php if (!$is_pro && in_array($current['status'], ['warning', 'eol'], true)): ?>
        <div class="phpinfowp-eol-upsell">
            <div class="phpinfowp-eol-upsell-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="phpinfowp-eol-upsell-body">
                <h3><?php printf( __('Plan your PHP %s upgrade with confidence', 'phpinfo-wp'), esc_html($current['minor']) ); ?></h3>
                <p>
                    <?php _e('Before you upgrade, scan every plugin and theme for compatibility with your target PHP version. Save snapshots, monitor changes, and email yourself a digest.', 'phpinfo-wp'); ?>
                </p>
                <ul class="phpinfowp-eol-upsell-list">
                    <li><strong><?php _e('PHP Compatibility Scanner', 'phpinfo-wp'); ?></strong> — <?php _e('find breaking changes across all plugins/themes', 'phpinfo-wp'); ?></li>
                    <li><strong><?php _e('Config Snapshots', 'phpinfo-wp'); ?></strong> — <?php _e('diff php.ini before and after the upgrade', 'phpinfo-wp'); ?></li>
                    <li><strong><?php _e('Audit Report', 'phpinfo-wp'); ?></strong> — <?php _e('single-page PDF for clients or your records', 'phpinfo-wp'); ?></li>
                    <li><strong><?php _e('Email Alerts', 'phpinfo-wp'); ?></strong> — <?php _e('get warned 90/30/7 days before any PHP EOL', 'phpinfo-wp'); ?></li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="phpinfowp-upgrade-cta">
                    <?php _e('Upgrade safely — Get Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>