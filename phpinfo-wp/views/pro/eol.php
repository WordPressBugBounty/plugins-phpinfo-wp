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
            <h1><?php _e('PHP EOL Timeline', 'phpinfo-wp'); ?></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('End-of-life dates and support status for every PHP version', 'phpinfo-wp'); ?></p>
        </div>
    </div>

    <?php
    $eol_cfg = [
        'eol'     => ['bg' => '#fff4f4', 'border' => '#d63638', 'badge_bg' => '#d63638', 'label' => __('END OF LIFE', 'phpinfo-wp')],
        'warning' => ['bg' => '#fffbf0', 'border' => '#dba617', 'badge_bg' => '#dba617', 'label' => __('EXPIRING SOON', 'phpinfo-wp')],
        'ok'      => ['bg' => '#f0faf2', 'border' => '#00a32a', 'badge_bg' => '#00a32a', 'label' => __('SUPPORTED', 'phpinfo-wp')],
        'unknown' => ['bg' => '#f6f7f7', 'border' => '#888',    'badge_bg' => '#888',    'label' => __('UNKNOWN', 'phpinfo-wp')],
    ];
    $cfg = $eol_cfg[$current['status']] ?? $eol_cfg['unknown'];
    ?>

    <!-- Current version hero card -->
    <div style="background:<?php echo $cfg['bg']; ?>;border:1px solid <?php echo $cfg['border']; ?>;border-left:5px solid <?php echo $cfg['border']; ?>;border-radius:8px;padding:24px 28px;display:flex;align-items:center;gap:28px;flex-wrap:wrap;margin-bottom:32px">
        <div style="text-align:center;flex-shrink:0">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#888;margin-bottom:4px"><?php _e('Running on', 'phpinfo-wp'); ?></div>
            <div style="font-size:42px;font-weight:800;line-height:1;color:#1d2327"><?php printf( __('PHP %s', 'phpinfo-wp'), esc_html($current['minor']) ); ?></div>
            <div style="font-size:12px;color:#666;margin-top:4px"><?php echo esc_html(PHP_VERSION); ?></div>
        </div>
        <div style="flex:1;min-width:220px">
            <span style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:.5px;background:<?php echo $cfg['badge_bg']; ?>;color:#fff;margin-bottom:10px">
                <?php echo esc_html($cfg['label']); ?>
            </span>
            <?php if ($current['status'] === 'eol'): ?>
                <p style="margin:0;font-size:14px"><?php printf( __('Reached end-of-life on <strong>%s</strong>. No security patches are being issued. Contact your host and request a PHP upgrade to 8.2 or newer immediately.', 'phpinfo-wp'), esc_html($current['eol']) ); ?></p>
            <?php elseif ($current['status'] === 'warning'): ?>
                <p style="margin:0;font-size:14px"><?php printf( __('Reaches end-of-life on <strong>%1$s</strong> — <strong>%2$s days</strong> from now. Plan your PHP upgrade before that date.', 'phpinfo-wp'), esc_html($current['eol']), esc_html($current['days']) ); ?></p>
            <?php elseif ($current['status'] === 'ok'): ?>
                <p style="margin:0;font-size:14px"><?php printf( __('Actively supported until <strong>%1$s</strong> — <strong>%2$s days</strong> from now. You\'re good.', 'phpinfo-wp'), esc_html($current['eol']), esc_html($current['days']) ); ?></p>
            <?php else: ?>
                <p style="margin:0;font-size:14px"><?php printf( __('EOL date not found for PHP %s. Check <a href="https://www.php.net/supported-versions.php" target="_blank">php.net/supported-versions</a>.', 'phpinfo-wp'), esc_html($current['minor']) ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Version timeline -->
    <h2 class="phpinfowp-section-heading"><?php _e('Version Lifecycle', 'phpinfo-wp'); ?></h2>
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