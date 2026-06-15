<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_valid  = Phpinfo_WP_License::is_valid();
$is_locked = Phpinfo_WP_License::is_locked();
$key       = Phpinfo_WP_License::get_key();
$message   = '';
$msg_type  = 'success';

if (isset($_POST['phpinfowp_license_action']) && check_admin_referer('phpinfowp_license_nonce')) {
    $action = sanitize_text_field($_POST['phpinfowp_license_action']);

    if ($action === 'activate' && !empty($_POST['license_key'])) {
        $submitted = sanitize_text_field(trim($_POST['license_key']));
        $ok = Phpinfo_WP_License::activate($submitted);
        if ($ok) {
            $message  = 'License activated successfully. Pro features are now unlocked.';
            $is_valid = true;
            $key      = $submitted;
        } else {
            $message  = 'License key invalid, expired, or revoked. If you just purchased, allow a moment for activation to propagate, then try again. Contact <a href="mailto:support@exeebit.com">support@exeebit.com</a> if the problem persists.';
            $msg_type = 'error';
        }
    } elseif ($action === 'deactivate') {
        Phpinfo_WP_License::deactivate();
        $message  = 'License deactivated. Pro features are disabled.';
        $msg_type = 'info';
        $is_valid = false;
        $key      = '';
    }
}

$pillars = [
    [
        'icon'  => 'dashicons-shield',
        'name'  => 'Safeguard',
        'tag'   => "Don't break your site.",
        'items' => [
            'Update Guard (Core audit)',
            'PHP Compatibility Scanner',
            'Config Snapshots & diff',
            'Security Headers audit',
        ],
    ],
    [
        'icon'  => 'dashicons-performance',
        'name'  => 'Optimize',
        'tag'   => 'Performance & Best Practices.',
        'items' => [
            'Full Config Grader with fixes',
            'Web Server Snippet Library',
            'Database health & autoload',
            'OPcache dashboard',
        ],
    ],
    [
        'icon'  => 'dashicons-visibility',
        'name'  => 'Monitor',
        'tag'   => "Know what's wrong before clients call.",
        'items' => [
            'SSL certificate monitor',
            'External API Monitor',
            'Error Log viewer',
            'WP Cron monitor',
            'Mail deliverability',
        ],
    ],
    [
        'icon'  => 'dashicons-portfolio',
        'name'  => 'Deliver',
        'tag'   => 'Look professional to clients.',
        'items' => [
            'White-label PDF Audit Report',
            'Email alerts on issues',
            'Weekly health digest',
            'Slack / Discord webhooks',
            'Multi-site (Network) support',
        ],
    ],
];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>License <span class="phpinfowp-pro-badge">PRO</span></h1>
            <p class="phpinfowp-page-subtitle">Activate your license to unlock Pro features on this site</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible" style="margin:0 0 20px">
            <p><?php echo wp_kses($message, ['strong' => [], 'a' => ['href' => []]]); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($is_locked): ?>
        <div class="notice notice-error inline" style="margin:0 0 20px">
            <p><strong>License locked.</strong> Your license could not be verified for 14+ days. Re-enter your key to unlock, or contact support at <a href="mailto:support@exeebit.com">support@exeebit.com</a>.</p>
        </div>
    <?php endif; ?>

    <!-- License status card -->
    <div class="phpinfowp-license-card <?php echo $is_valid ? 'is-active' : 'is-inactive'; ?>">
        <div class="phpinfowp-license-status">
            <?php if ($is_valid): ?>
                <span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span>
                <div>
                    <div class="phpinfowp-license-status-title">Pro license is active</div>
                    <div class="phpinfowp-license-status-sub">All Pro features are unlocked on this site.</div>
                </div>
            <?php else: ?>
                <span class="dashicons dashicons-dismiss" style="color:#d63638"></span>
                <div>
                    <div class="phpinfowp-license-status-title">No active license</div>
                    <div class="phpinfowp-license-status-sub">Enter a valid license key to unlock Pro features.</div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_valid): ?>
            <?php $meta = Phpinfo_WP_License::payload(); ?>
            <div class="phpinfowp-license-key-display">
                <span class="phpinfowp-license-key-label">License key</span>
                <code><?php echo esc_html(substr($key, 0, 12) . str_repeat('•', 20)); ?></code>
            </div>
            <?php if ($meta): ?>
                <div class="phpinfowp-license-meta">
                    <div class="phpinfowp-license-meta-row">
                        <span class="phpinfowp-license-meta-label">Registered to</span>
                        <span class="phpinfowp-license-meta-value"><?php echo esc_html($meta['email']); ?></span>
                    </div>
                    <div class="phpinfowp-license-meta-row">
                        <span class="phpinfowp-license-meta-label">Expires</span>
                        <span class="phpinfowp-license-meta-value">
                            <?php echo esc_html($meta['expiry_human']); ?>
                            <?php if (!$meta['is_lifetime']): ?>
                                <span style="color:<?php echo $meta['days_left'] < 30 ? '#d63638' : ($meta['days_left'] < 90 ? '#dba617' : '#666'); ?>;font-size:12px;margin-left:6px">
                                    (<?php echo (int) $meta['days_left']; ?> days left)
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if (!empty($meta['iat'])): ?>
                    <div class="phpinfowp-license-meta-row">
                        <span class="phpinfowp-license-meta-label">Activated</span>
                        <span class="phpinfowp-license-meta-value"><?php echo esc_html(date_i18n(get_option('date_format'), $meta['iat'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <form id="phpinfowp-license-deactivate-form" method="post" style="margin-top:14px">
                <?php wp_nonce_field('phpinfowp_license_nonce'); ?>
                <input type="hidden" name="phpinfowp_license_action" value="deactivate">
                <button type="button" class="button button-secondary" id="phpinfowp-license-deactivate-btn">Deactivate License</button>
            </form>

            <!-- License deactivation retention modal. Reuses .phpinfowp-dm CSS
                 from the Plugins-screen modal (same look). Only renders when
                 a Pro license is active. -->
            <div id="phpinfowp-license-dm" class="phpinfowp-dm" aria-hidden="true" role="dialog" aria-labelledby="phpinfowp-license-dm-title">
                <div class="phpinfowp-dm-backdrop"></div>
                <div class="phpinfowp-dm-dialog" role="document">
                    <button type="button" class="phpinfowp-dm-close" aria-label="Close">&times;</button>

                    <div class="phpinfowp-dm-header">
                        <div style="width:48px;height:48px;border-radius:10px;background:rgba(124,58,237,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <span class="dashicons dashicons-shield-alt" style="font-size:26px;width:26px;height:26px;color:#7c3aed"></span>
                        </div>
                        <div>
                            <h2 id="phpinfowp-license-dm-title" class="phpinfowp-dm-title">Deactivate your Pro license?</h2>
                            <p class="phpinfowp-dm-sub">The plugin keeps running, but every Pro feature below stops the moment you confirm.</p>
                        </div>
                    </div>

                    <div class="phpinfowp-dm-body">
                        <div class="phpinfowp-dm-col phpinfowp-dm-col-pro">
                            <div class="phpinfowp-dm-col-label">Pro features that will lock</div>
                            <ul class="phpinfowp-dm-list">
                                <li><span class="dashicons dashicons-no-alt phpinfowp-dm-x"></span>White-label PDF Audit Report</li>
                                <li><span class="dashicons dashicons-no-alt phpinfowp-dm-x"></span>One-click Config Auto-Fix with rollback</li>
                                <li><span class="dashicons dashicons-no-alt phpinfowp-dm-x"></span>Security Headers + SSL Certificate monitors</li>
                                <li><span class="dashicons dashicons-no-alt phpinfowp-dm-x"></span>OPcache Dashboard + PHP Error Log viewer</li>
                                <li><span class="dashicons dashicons-no-alt phpinfowp-dm-x"></span>Database Health + Autoload bloat detection</li>
                                <li><span class="dashicons dashicons-no-alt phpinfowp-dm-x"></span>WP-Cron Monitor + orphan-hook purge</li>
                                <li><span class="dashicons dashicons-no-alt phpinfowp-dm-x"></span>Email Alerts, Weekly Digest, Slack/Discord webhooks</li>
                                <li><span class="dashicons dashicons-no-alt phpinfowp-dm-x"></span>Multi-site (Network) support</li>
                            </ul>
                        </div>
                    </div>

                    <div class="phpinfowp-dm-warn">
                        <span class="dashicons dashicons-info-outline" style="color:#0073aa"></span>
                        <div>
                            <strong>Nothing is deleted.</strong> Your license key, white-label branding, snapshots, and alert settings stay on this site. Paste your key back in to restore Pro instantly — your license remains valid on Exeebit's servers.
                        </div>
                    </div>

                    <div class="phpinfowp-dm-actions">
                        <button type="button" class="button button-primary button-large phpinfowp-dm-keep">
                            Keep Pro active
                        </button>
                        <button type="button" class="phpinfowp-dm-confirm" id="phpinfowp-license-dm-confirm" style="background:none;border:none;cursor:pointer">
                            Deactivate anyway →
                        </button>
                    </div>
                </div>
            </div>

            <script>
            (function () {
                var modal = document.getElementById('phpinfowp-license-dm');
                var openBtn  = document.getElementById('phpinfowp-license-deactivate-btn');
                var confirm  = document.getElementById('phpinfowp-license-dm-confirm');
                var form     = document.getElementById('phpinfowp-license-deactivate-form');
                if (!modal || !openBtn || !confirm || !form) return;

                function open()  { modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow = 'hidden'; }
                function close() { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true');  document.body.style.overflow = ''; }

                openBtn.addEventListener('click', open);
                modal.querySelectorAll('.phpinfowp-dm-close, .phpinfowp-dm-keep, .phpinfowp-dm-backdrop').forEach(function (b) {
                    b.addEventListener('click', close);
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
                });
                confirm.addEventListener('click', function () { form.submit(); });
            }());
            </script>
        <?php else: ?>
            <form method="post" class="phpinfowp-license-form">
                <?php wp_nonce_field('phpinfowp_license_nonce'); ?>
                <input type="hidden" name="phpinfowp_license_action" value="activate">
                <label for="license_key" class="phpinfowp-license-input-label">Enter your license key</label>
                <div class="phpinfowp-license-input-row">
                    <input type="text" id="license_key" name="license_key"
                           placeholder="PIWP-xxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                           autocomplete="off" spellcheck="false"
                           value="<?php echo esc_attr($key); ?>">
                    <button type="submit" class="button button-primary">Activate</button>
                </div>
                <p class="description" style="margin-top:10px">
                    This site: <strong><?php echo esc_html(get_site_url()); ?></strong><br>
                    Don't have a license? <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank">Get phpinfo() WP Pro &rarr;</a>
                </p>
            </form>
        <?php endif; ?>
    </div>

    <!-- Plan comparison table -->
    <?php
    // Cell values: true / false / string. Keep in sync with the frontend
    // comparison table at exeebit.com/phpinfo-wp.
    $compare_rows = [
        // Sites & licensing
        ['Sites',          '1',                                '1',                  'Unlimited',          'Unlimited'],
        ['Updates',        'While free version is supported',  '1 year',             '1 year',             'Lifetime'],
        ['Support',        'Community (WP.org forum)',         'Email',              'Priority email',     'Priority forever'],
        // Free-tier
        ['phpinfo() viewer',                  true, true, true, true],
        ['.htaccess editor',                  true, true, true, true],
        ['PHP EOL Timeline',                  true, true, true, true],
        ['Config Grade & Score',              true, true, true, true],
        // Pro depth
        ['Update Guard (Core pre-update audit)', true, true, true, true],
        ['PHP Compatibility Scanner',         true, true, true, true],
        ['Detailed Directives & Fixes',       false, true, true, true],
        ['Web Server Snippet Library',        false, true, true, true],
        ['Security Headers audit',            false, true, true, true],
        ['SSL certificate monitor',           false, true, true, true],
        ['OPcache dashboard',                 false, true, true, true],
        ['Database health & autoload',        false, true, true, true],
        ['Error Log viewer',                  false, true, true, true],
        ['WP-Cron monitor',                   false, true, true, true],
        ['Mail deliverability check',         false, true, true, true],
        // Deliverable & integrations
        ['Email alerts on issues',            false, true,  true, true],
        ['External API Monitor',              false, '1 Endpoint', true, true],
        ['Config Snapshots & diff',           false, 'Latest 3', true, true],
        ['PDF Audit Reports',                 false, 'Branded', 'White-label', 'White-label'],
        ['Weekly health digest',              false, false, true, true],
        ['Slack / Discord webhooks',          false, false, true, true],
        ['Multi-site (Network) support',      false, false, true, true],
    ];

    $compare_cols = [
        ['name' => 'Free',         'price' => '$0',   'cadence' => 'WP.org'],
        ['name' => 'Single Site',  'price' => '$29',  'cadence' => '/year'],
        ['name' => 'Unlimited',    'price' => '$69',  'cadence' => '/year', 'featured' => true],
        ['name' => 'Lifetime',     'price' => '$149', 'cadence' => 'once'],
    ];

    $render_cell = static function ($v) {
        if ($v === true)  return '<span class="dashicons dashicons-yes-alt" style="color:#7c3aed" aria-label="Included"></span>';
        if ($v === false) return '<span class="dashicons dashicons-minus" style="color:#c8c8d0" aria-label="Not included"></span>';
        return '<span style="font-size:12px;color:#3c434a">' . esc_html((string) $v) . '</span>';
    };
    ?>
    <h2 class="phpinfowp-section-heading" style="margin-top:36px">Compare plans</h2>
    <p style="color:#646970;margin:0 0 14px;max-width:560px">Everything in Free, plus the Pro depth — see exactly what you get at each tier.</p>
    <div class="phpinfowp-compare-wrap">
        <table class="phpinfowp-compare">
            <thead>
                <tr>
                    <th scope="col" class="phpinfowp-compare-feat">Feature</th>
                    <?php foreach ($compare_cols as $col): ?>
                        <th scope="col" class="<?php echo !empty($col['featured']) ? 'is-featured' : ''; ?>">
                            <div class="phpinfowp-compare-tier"><?php echo esc_html($col['name']); ?></div>
                            <div class="phpinfowp-compare-price">
                                <span class="phpinfowp-compare-amount"><?php echo esc_html($col['price']); ?></span>
                                <span class="phpinfowp-compare-cadence"><?php echo esc_html($col['cadence']); ?></span>
                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($compare_rows as $row): ?>
                    <tr>
                        <th scope="row" class="phpinfowp-compare-feat"><?php echo esc_html($row[0]); ?></th>
                        <td><?php echo $render_cell($row[1]); ?></td>
                        <td><?php echo $render_cell($row[2]); ?></td>
                        <td class="is-featured"><?php echo $render_cell($row[3]); ?></td>
                        <td><?php echo $render_cell($row[4]); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (!$is_valid): ?>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button">Get Free</a>
                    </td>
                    <td>
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button">Get Single</a>
                    </td>
                    <td class="is-featured">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary">Get Unlimited</a>
                    </td>
                    <td>
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button">Get Lifetime</a>
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <!-- 3-pillar features -->
    <h2 class="phpinfowp-section-heading" style="margin-top:36px">What Pro unlocks</h2>
    <div class="phpinfowp-pillars">
        <?php foreach ($pillars as $p): ?>
            <div class="phpinfowp-pillar">
                <div class="phpinfowp-pillar-header">
                    <div class="phpinfowp-pillar-icon-box">
                        <span class="dashicons <?php echo esc_attr($p['icon']); ?>"></span>
                    </div>
                    <div>
                        <div class="phpinfowp-pillar-name"><?php echo esc_html($p['name']); ?></div>
                        <div class="phpinfowp-pillar-tag"><?php echo esc_html($p['tag']); ?></div>
                    </div>
                </div>
                <ul class="phpinfowp-pillar-items">
                    <?php foreach ($p['items'] as $item): ?>
                        <li><?php echo esc_html($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$is_valid): ?>
        <!-- Pricing -->
        <h2 class="phpinfowp-section-heading" style="margin-top:36px">Pricing</h2>
        <div class="phpinfowp-pricing">
            <div class="phpinfowp-pricing-tier">
                <div class="phpinfowp-pricing-name">Single Site</div>
                <div class="phpinfowp-pricing-price">$29<span>/year</span></div>
                <ul class="phpinfowp-pricing-list">
                    <li>Essential Pro features on 1 site</li>
                    <li>Standard PDF reports (Branded)</li>
                    <li>1 API monitor & 3 snapshots</li>
                    <li>1 year of updates & support</li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="button button-secondary">Get Single</a>
            </div>
            <div class="phpinfowp-pricing-tier is-featured">
                <div class="phpinfowp-pricing-flag">Most Popular</div>
                <div class="phpinfowp-pricing-name">Unlimited</div>
                <div class="phpinfowp-pricing-price">$69<span>/year</span></div>
                <ul class="phpinfowp-pricing-list">
                    <li>All Pro features on unlimited sites</li>
                    <li>White-labeled PDF reports (Logo)</li>
                    <li>Weekly digests & Slack/Discord</li>
                    <li>Priority support & Multi-site</li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="button button-primary">Get Unlimited</a>
            </div>
            <div class="phpinfowp-pricing-tier">
                <div class="phpinfowp-pricing-flag is-warn">Founders &mdash; First 50</div>
                <div class="phpinfowp-pricing-name">Lifetime</div>
                <div class="phpinfowp-pricing-price">$149<span>once</span></div>
                <ul class="phpinfowp-pricing-list">
                    <li>All Pro features on unlimited sites</li>
                    <li>White-labeled PDF reports (Logo)</li>
                    <li>Weekly digests & Slack/Discord</li>
                    <li>Lifetime updates & priority support</li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" class="button button-secondary">Get Lifetime</a>
            </div>
        </div>
        <p class="phpinfowp-pricing-foot">14-day money-back guarantee &middot; Instant license delivery &middot; Cancel anytime</p>
    <?php endif; ?>

</div>
