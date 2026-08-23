<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));
$buy_url     = 'https://exeebit.com/phpinfo-wp#pricing';
$license_url = admin_url('admin.php?page=piwp-license');

$pillars = [
    [
        'icon'  => 'dashicons-shield',
        'name'  => 'Safeguard',
        'tag'   => "Don't break your site.",
        'items' => ['Update Guard (Core audit)', 'PHP Compatibility Scanner', 'Config Snapshots & diff', 'Security Headers audit'],
    ],
    [
        'icon'  => 'dashicons-performance',
        'name'  => 'Optimize',
        'tag'   => 'Performance & Best Practices.',
        'items' => ['Full Config Grader with fixes', 'Web Server Snippet Library', 'Database health & autoload', 'OPcache dashboard'],
    ],
    [
        'icon'  => 'dashicons-visibility',
        'name'  => 'Monitor',
        'tag'   => "Know what's wrong before clients call.",
        'items' => ['SSL certificate monitor', 'External API Monitor', 'Error Log viewer', 'WP Cron monitor', 'Mail deliverability'],
    ],
    [
        'icon'  => 'dashicons-portfolio',
        'name'  => 'Deliver',
        'tag'   => 'Look professional to clients.',
        'items' => ['White-label PDF Audit Report', 'Email alerts on issues', 'Weekly health digest', 'Slack / Discord webhooks', 'Multi-site (Network) support'],
    ],
];
?>
<div class="phpinfowp-upgrade-gate">
    <div class="phpinfowp-upgrade-inner">

        <div class="phpinfowp-upgrade-badge"><?php _e('PRO', 'phpinfo-wp'); ?></div>
        <h2 class="phpinfowp-upgrade-title"><?php _e('Upgrade to phpinfo() WP Pro', 'phpinfo-wp'); ?></h2>
        <p class="phpinfowp-upgrade-subtitle"><?php _e('The WordPress server health audit you can hand to clients.', 'phpinfo-wp'); ?></p>

        <div class="phpinfowp-upgrade-pillars">
            <?php foreach ($pillars as $p): ?>
            <div class="phpinfowp-upgrade-pillar">
                <div class="phpinfowp-upgrade-pillar-icon">
                    <span class="dashicons <?php echo esc_attr($p['icon']); ?>"></span>
                </div>
                <div class="phpinfowp-upgrade-pillar-name"><?php echo esc_html($p['name']); ?></div>
                <div class="phpinfowp-upgrade-pillar-tag"><?php echo esc_html($p['tag']); ?></div>
                <ul class="phpinfowp-upgrade-pillar-items">
                    <?php foreach ($p['items'] as $item): ?>
                        <li><?php echo esc_html($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="phpinfowp-upgrade-plans-header">
            <h3 class="phpinfowp-upgrade-plans-title"><?php _e('Choose your plan', 'phpinfo-wp'); ?></h3>
            <p class="phpinfowp-upgrade-plans-note"><?php _e('Every plan unlocks <strong>every feature above</strong> — the only difference is the number of sites and update window.', 'phpinfo-wp'); ?></p>
        </div>

        <div class="phpinfowp-upgrade-pricing">
            <div class="phpinfowp-upgrade-price-tier">
                <div class="phpinfowp-upgrade-price-name"><?php _e('Single', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-upgrade-price-value">$29<small>/yr</small></div>
                <div class="phpinfowp-upgrade-price-sub"><?php _e('1 site · Essential features · Branded reports', 'phpinfo-wp'); ?></div>
            </div>
            <div class="phpinfowp-upgrade-price-tier is-featured">
                <div class="phpinfowp-upgrade-price-name"><?php _e('Unlimited', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-upgrade-price-value">$69<small>/yr</small></div>
                <div class="phpinfowp-upgrade-price-sub"><?php _e('Unlimited sites · White-labeled · Weekly digests', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-upgrade-price-best"><?php _e('Best value', 'phpinfo-wp'); ?></div>
            </div>
            <div class="phpinfowp-upgrade-price-tier">
                <div class="phpinfowp-upgrade-price-name"><?php _e('Lifetime', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-upgrade-price-value">$149<small>once</small></div>
                <div class="phpinfowp-upgrade-price-sub"><?php _e('Unlimited sites · Lifetime updates · White-labeled', 'phpinfo-wp'); ?></div>
            </div>
        </div>

        <div class="phpinfowp-upgrade-actions">
            <a href="<?php echo esc_url($buy_url); ?>" target="_blank" class="phpinfowp-upgrade-cta">
                Get Pro &rarr;
            </a>
            <a href="<?php echo esc_url($license_url); ?>" class="phpinfowp-upgrade-secondary">
                I already have a license
            </a>
        </div>

        <p class="phpinfowp-upgrade-footer"><?php _e('14-day money-back guarantee · Instant delivery · Site-locked license', 'phpinfo-wp'); ?></p>
    </div>
</div>
