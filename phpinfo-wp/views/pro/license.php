<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_valid  = Phpinfo_WP_License::is_valid();
$is_locked = Phpinfo_WP_License::is_locked();
$key       = Phpinfo_WP_License::get_key();
$message   = '';
$msg_type  = 'success';

if (isset($_POST['phpinfowp_license_action']) && check_admin_referer('phpinfowp_license_nonce')) {
    $action = sanitize_text_field($_POST['phpinfowp_license_action']);

    if ($action === 'activate' && !empty($_POST['license_key'])) {
        $submitted = sanitize_text_field(trim($_POST['license_key']));
        $reason    = '';
        $ok        = Phpinfo_WP_License::activate($submitted, $reason);
        if ($ok) {
            $message   = __('License activated successfully. Pro features are now unlocked.', 'phpinfo-wp');
            $is_valid  = true;
            $is_locked = false;
            $key       = $submitted;
        } else {
            if ($reason === 'v1_deprecated') {
                $message = __('Activation failed: Legacy v1 license keys can no longer be activated. Please contact <a href="mailto:support@exeebit.com">support@exeebit.com</a> for an updated v2 key.', 'phpinfo-wp');
            } elseif ($reason === 'site_limit_exceeded') {
                $message = __('Activation failed: This license has reached its maximum site limit. Upgrade your license or deactivate another site.', 'phpinfo-wp');
            } elseif ($reason === 'revoked' || $reason === 'refunded' || $reason === 'disputed') {
                $message = __('Activation failed: This license has been revoked or refunded. Contact <a href="mailto:support@exeebit.com">support@exeebit.com</a>.', 'phpinfo-wp');
            } else {
                $message = __('License key invalid, expired, or unrecognised. If you just purchased, allow a moment for activation to propagate, then try again. Contact <a href="mailto:support@exeebit.com">support@exeebit.com</a> if the problem persists.', 'phpinfo-wp');
            }
            $msg_type  = 'error';
            $is_valid  = false;
            $is_locked = Phpinfo_WP_License::is_locked();
        }
    } elseif ($action === 'deactivate') {
        Phpinfo_WP_License::deactivate();
        delete_option('phpinfowp_free_trial_expires');
        delete_option('phpinfowp_free_trial_email');
        $message   = __('License deactivated. Pro features are disabled.', 'phpinfo-wp');
        $msg_type  = 'info';
        $is_valid  = false;
        $is_locked = false;
        $key       = '';
    }
} else {
    // Non-blocking background verification when viewing license screen
    if ($is_valid && !empty($key)) {
        $last_check = (int) get_option(Phpinfo_WP_License::OPT_LAST_CHECK, 0);
        if (time() - $last_check > HOUR_IN_SECONDS) {
            if (!wp_next_scheduled('phpinfowp_license_ping_async')) {
                wp_schedule_single_event(time(), 'phpinfowp_license_ping_async');
            }
        }
    }
}

$pillars = [
    [
        'icon'  => 'dashicons-shield',
        'name'  => __('Safeguard', 'phpinfo-wp'),
        'tag'   => __("Don't break your site.", 'phpinfo-wp'),
        'items' => [
            __('Update Guard (Core audit)', 'phpinfo-wp'),
            __('PHP Compatibility Scanner', 'phpinfo-wp'),
            __('Config Snapshots & diff', 'phpinfo-wp'),
            __('Security Headers audit', 'phpinfo-wp'),
        ],
    ],
    [
        'icon'  => 'dashicons-performance',
        'name'  => __('Optimize', 'phpinfo-wp'),
        'tag'   => __('Performance & Best Practices.', 'phpinfo-wp'),
        'items' => [
            __('Full Config Grader with fixes', 'phpinfo-wp'),
            __('Web Server Snippet Library', 'phpinfo-wp'),
            __('Database health & autoload', 'phpinfo-wp'),
            __('OPcache dashboard', 'phpinfo-wp'),
        ],
    ],
    [
        'icon'  => 'dashicons-visibility',
        'name'  => __('Monitor', 'phpinfo-wp'),
        'tag'   => __("Know what's wrong before clients call.", 'phpinfo-wp'),
        'items' => [
            __('SSL certificate monitor', 'phpinfo-wp'),
            __('External API Monitor', 'phpinfo-wp'),
            __('Error Log viewer', 'phpinfo-wp'),
            __('WP Cron monitor', 'phpinfo-wp'),
            __('Mail deliverability', 'phpinfo-wp'),
        ],
    ],
    [
        'icon'  => 'dashicons-portfolio',
        'name'  => __('Deliver', 'phpinfo-wp'),
        'tag'   => __('Look professional to clients.', 'phpinfo-wp'),
        'items' => [
            __('White-label PDF Audit Report', 'phpinfo-wp'),
            __('Email alerts on issues', 'phpinfo-wp'),
            __('Weekly health digest', 'phpinfo-wp'),
            __('Slack / Discord webhooks', 'phpinfo-wp'),
            __('Multi-site (Network) support', 'phpinfo-wp'),
        ],
    ],
];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>
                <?php _e('License', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Activate your license key to unlock all Pro diagnostics, auto-fixes, and guard tools.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="phpinfowp-license-alert is-<?php echo $msg_type === 'error' ? 'error' : ($msg_type === 'info' ? 'warning' : 'success'); ?>" style="margin:0 0 20px">
            <span class="dashicons dashicons-<?php echo $msg_type === 'error' ? 'warning' : ($msg_type === 'info' ? 'clock' : 'yes-alt'); ?>"></span>
            <div class="phpinfowp-license-alert-content"><?php echo wp_kses($message, ['strong' => [], 'a' => ['href' => []]]); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($is_locked): ?>
        <?php 
        $revoke_reason = Phpinfo_WP_License::get_revoke_reason();
        if ($revoke_reason === 'revoked' || $revoke_reason === 'refunded' || $revoke_reason === 'disputed'): ?>
            <div class="phpinfowp-license-alert is-error" style="margin:0 0 20px">
                <span class="dashicons dashicons-dismiss"></span>
                <div class="phpinfowp-license-alert-content">
                    <strong><?php _e('License Revoked.', 'phpinfo-wp'); ?></strong>
                    <?php _e('This license has been revoked or refunded. Pro features have been disabled. Contact <a href="mailto:support@exeebit.com">support@exeebit.com</a> if you believe this is an error.', 'phpinfo-wp'); ?>
                </div>
            </div>
        <?php elseif ($revoke_reason === 'site_limit_exceeded'): ?>
            <div class="phpinfowp-license-alert is-warning" style="margin:0 0 20px">
                <span class="dashicons dashicons-warning"></span>
                <div class="phpinfowp-license-alert-content">
                    <strong><?php _e('Site Limit Exceeded.', 'phpinfo-wp'); ?></strong>
                    <?php _e('This license is active on too many sites. Please upgrade to an Unlimited tier or deactivate unused sites.', 'phpinfo-wp'); ?>
                </div>
            </div>
        <?php else: ?>
            <div class="phpinfowp-license-alert is-warning" style="margin:0 0 20px">
                <span class="dashicons dashicons-lock"></span>
                <div class="phpinfowp-license-alert-content">
                    <strong><?php _e('License locked.', 'phpinfo-wp'); ?></strong>
                    <?php _e('Your license could not be verified with our servers. Re-enter your key to unlock, or contact <a href="mailto:support@exeebit.com">support@exeebit.com</a>.', 'phpinfo-wp'); ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($is_valid && Phpinfo_WP_License::is_legacy()): ?>
        <div class="phpinfowp-license-alert is-warning" style="margin:0 0 20px">
            <span class="dashicons dashicons-info"></span>
            <div class="phpinfowp-license-alert-content">
                <strong><?php _e('Legacy License Notice:', 'phpinfo-wp'); ?></strong>
                <?php _e('You are using a legacy v1 license key. All Pro features remain fully active on this site. Your license key will be updated automatically and your new key will be sent to your email. No action is required on your part.', 'phpinfo-wp'); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- License status card -->
    <div class="phpinfowp-license-card <?php echo $is_valid ? 'is-active' : 'is-inactive'; ?>">
        <div class="phpinfowp-license-status">
            <?php if ($is_valid): ?>
                <span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span>
                <div>
                    <div class="phpinfowp-license-status-title"><?php _e('Pro license is active', 'phpinfo-wp'); ?></div>
                    <div class="phpinfowp-license-status-sub"><?php _e('All Pro features are unlocked on this site.', 'phpinfo-wp'); ?></div>
                </div>
            <?php else: ?>
                <span class="dashicons dashicons-dismiss" style="color:#d63638"></span>
                <div>
                    <div class="phpinfowp-license-status-title"><?php _e('No active license', 'phpinfo-wp'); ?></div>
                    <div class="phpinfowp-license-status-sub"><?php _e('Enter a valid license key to unlock Pro features.', 'phpinfo-wp'); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_valid): ?>
            <?php $meta = Phpinfo_WP_License::payload(); ?>
            <div class="phpinfowp-license-key-display">
                <span class="phpinfowp-license-key-label"><?php _e('License key', 'phpinfo-wp'); ?></span>
                <code><?php echo esc_html(substr($key, 0, 12) . str_repeat('•', 20)); ?></code>
            </div>
            <?php if ($meta): ?>
                <div class="phpinfowp-license-meta">
                    <div class="phpinfowp-license-meta-row">
                        <span class="phpinfowp-license-meta-label"><?php _e('Registered to', 'phpinfo-wp'); ?></span>
                        <span class="phpinfowp-license-meta-value"><?php echo esc_html($meta['email']); ?></span>
                    </div>
                    <div class="phpinfowp-license-meta-row">
                        <span class="phpinfowp-license-meta-label"><?php _e('Expires', 'phpinfo-wp'); ?></span>
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
                        <span class="phpinfowp-license-meta-label"><?php _e('Activated', 'phpinfo-wp'); ?></span>
                        <span class="phpinfowp-license-meta-value"><?php echo esc_html(date_i18n(get_option('date_format'), $meta['iat'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <form id="phpinfowp-license-deactivate-form" method="post" style="margin-top:14px">
                <?php wp_nonce_field('phpinfowp_license_nonce'); ?>
                <input type="hidden" name="phpinfowp_license_action" value="deactivate">
                <button type="button" class="button button-secondary" id="phpinfowp-license-deactivate-btn"><?php _e('Deactivate License', 'phpinfo-wp'); ?></button>
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
                            <h2 id="phpinfowp-license-dm-title" class="phpinfowp-dm-title"><?php _e('Deactivate your Pro license?', 'phpinfo-wp'); ?></h2>
                            <p class="phpinfowp-dm-sub"><?php _e('The plugin keeps running, but every Pro feature below stops the moment you confirm.', 'phpinfo-wp'); ?></p>
                        </div>
                    </div>

                    <div class="phpinfowp-dm-body">
                        <div class="phpinfowp-dm-col phpinfowp-dm-col-pro">
                            <div class="phpinfowp-dm-col-label"><?php _e('Pro features that will lock', 'phpinfo-wp'); ?></div>
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
                            <strong><?php _e('Nothing is deleted.', 'phpinfo-wp'); ?></strong> Your license key, white-label branding, snapshots, and alert settings stay on this site. Paste your key back in to restore Pro instantly — your license remains valid on Exeebit's servers.
                        </div>
                    </div>

                    <div class="phpinfowp-dm-actions">
                        <button type="button" class="button button-primary button-large phpinfowp-dm-keep"><?php _e('Keep Pro active', 'phpinfo-wp'); ?></button>
                        <button type="button" class="phpinfowp-dm-confirm" id="phpinfowp-license-dm-confirm" style="background:none;border:none;cursor:pointer"><?php _e('Deactivate anyway →', 'phpinfo-wp'); ?></button>
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
                confirm.addEventListener('click', function () {
                    confirm.disabled = true;
                    confirm.innerHTML = '<?php echo esc_js(__('Deactivating...', 'phpinfo-wp')); ?>';

                    var data = new FormData();
                    data.append('action', 'phpinfowp_deactivate_license');
                    data.append('nonce', '<?php echo wp_create_nonce('phpinfowp_license_nonce'); ?>');

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: data
                    })
                    .then(function() {
                        window.location.reload();
                    })
                    .catch(function() {
                        form.submit();
                    });
                });
            }());
            </script>
        <?php else: ?>
            <?php
            $rate_key = 'phpinfowp_lic_rate_' . get_current_user_id();
            $attempts = (int) get_transient($rate_key);
            $is_rate_limited = ($attempts >= 5);
            ?>
            <form id="phpinfowp-license-activate-form" method="post" class="phpinfowp-license-form">
                <?php wp_nonce_field('phpinfowp_license_nonce', 'phpinfowp_license_nonce'); ?>
                <input type="hidden" name="phpinfowp_license_action" value="activate">
                <?php if ($is_rate_limited): ?>
                    <div id="phpinfowp-license-ajax-msg" class="phpinfowp-license-alert is-warning" style="display:flex;">
                        <span class="dashicons dashicons-clock"></span>
                        <div class="phpinfowp-license-alert-content">
                            <strong><?php _e('Too many activation attempts', 'phpinfo-wp'); ?></strong>
                            <?php _e('Please wait an hour before trying again.', 'phpinfo-wp'); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div id="phpinfowp-license-ajax-msg" style="display:none;"></div>
                <?php endif; ?>
                <label for="license_key" class="phpinfowp-license-input-label"><?php _e('Enter your license key', 'phpinfo-wp'); ?></label>
                <div class="phpinfowp-license-input-row">
                    <input type="text" id="license_key" name="license_key"
                           placeholder="<?php echo esc_attr__('Paste your Pro license key here', 'phpinfo-wp'); ?>"
                           autocomplete="off" spellcheck="false"
                           value="<?php echo esc_attr($key); ?>">
                    <button type="submit" id="phpinfowp-license-activate-btn" class="button button-primary"><?php _e('Activate', 'phpinfo-wp'); ?></button>
                </div>
                <p class="description" style="margin-top:10px">
                    This site: <strong><?php echo esc_html(get_site_url()); ?></strong><br>
                    Don't have a license? <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank"><?php _e('Get phpinfo() WP Pro →', 'phpinfo-wp'); ?></a>
                </p>
            </form>

            <script>
            (function() {
                var form = document.getElementById('phpinfowp-license-activate-form');
                if (!form) return;
                var btn = document.getElementById('phpinfowp-license-activate-btn');
                var input = document.getElementById('license_key');
                var msgBox = document.getElementById('phpinfowp-license-ajax-msg');

                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var key = (input.value || '').trim();
                    if (!key) {
                        input.focus();
                        return;
                    }

                    var origText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-right:6px;font-size:16px;width:16px;height:16px;vertical-align:middle;animation:phpinfowp-spin 1s linear infinite;"></span> <?php echo esc_js(__('Verifying...', 'phpinfo-wp')); ?>';
                    msgBox.style.display = 'none';

                    var data = new FormData();
                    data.append('action', 'phpinfowp_activate_license');
                    data.append('license_key', key);
                    data.append('nonce', '<?php echo wp_create_nonce('phpinfowp_license_nonce'); ?>');

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: data
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res && res.success) {
                            msgBox.className = 'phpinfowp-license-alert is-success';
                            msgBox.innerHTML = '<span class="dashicons dashicons-yes-alt"></span><div class="phpinfowp-license-alert-content"><strong><?php echo esc_js(__('Success!', 'phpinfo-wp')); ?></strong> ' + (res.data.message || '<?php echo esc_js(__('License activated! Reloading...', 'phpinfo-wp')); ?>') + '</div>';
                            msgBox.style.display = 'flex';
                            btn.innerHTML = '✓ <?php echo esc_js(__('Activated', 'phpinfo-wp')); ?>';
                            setTimeout(function() {
                                window.location.reload();
                            }, 800);
                        } else {
                            var errMsg = (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js(__('Activation failed. Please check your key.', 'phpinfo-wp')); ?>';
                            var isRateLimit = errMsg.toLowerCase().indexOf('too many') !== -1;
                            msgBox.className = 'phpinfowp-license-alert ' + (isRateLimit ? 'is-warning' : 'is-error');
                            var icon = isRateLimit ? 'dashicons-clock' : 'dashicons-warning';
                            var title = isRateLimit ? '<?php echo esc_js(__('Too many activation attempts', 'phpinfo-wp')); ?>' : '<?php echo esc_js(__('Activation failed', 'phpinfo-wp')); ?>';
                            msgBox.innerHTML = '<span class="dashicons ' + icon + '"></span><div class="phpinfowp-license-alert-content"><strong>' + title + '</strong>' + errMsg + '</div>';
                            msgBox.style.display = 'flex';
                            btn.disabled = false;
                            btn.innerHTML = origText;
                        }
                    })
                    .catch(function() {
                        msgBox.className = 'phpinfowp-license-alert is-error';
                        msgBox.innerHTML = '<span class="dashicons dashicons-warning"></span><div class="phpinfowp-license-alert-content"><strong><?php echo esc_js(__('Connection error', 'phpinfo-wp')); ?></strong><?php echo esc_js(__('Connection error. Please try again.', 'phpinfo-wp')); ?></div>';
                        msgBox.style.display = 'flex';
                        btn.disabled = false;
                        btn.innerHTML = origText;
                    });
                });
            })();
            </script>
        <?php endif; ?>
    </div>

    <?php if (!$is_valid): ?>
        <!-- Plan comparison table -->
        <?php
        // Cell values: true / false / string. Keep in sync with the frontend
        // comparison table at exeebit.com/phpinfo-wp.
        $compare_rows = [
            // Sites & licensing
            [__('Sites', 'phpinfo-wp'),          '1',                                __('1', 'phpinfo-wp'),                  __('Unlimited', 'phpinfo-wp'),          __('Unlimited', 'phpinfo-wp')],
            [__('Updates', 'phpinfo-wp'),        __('While free version is supported', 'phpinfo-wp'),  __('1 year', 'phpinfo-wp'),             __('1 year', 'phpinfo-wp'),             __('Lifetime', 'phpinfo-wp')],
            [__('Support', 'phpinfo-wp'),        __('Community (WP.org forum)', 'phpinfo-wp'),         __('Email', 'phpinfo-wp'),              __('Priority email', 'phpinfo-wp'),     __('Priority forever', 'phpinfo-wp')],
            // Free-tier features (available on all plans)
            [__('phpinfo() viewer', 'phpinfo-wp'),                           true, true, true, true],
            [__('.htaccess editor', 'phpinfo-wp'),                           true, true, true, true],
            [__('PHP EOL Timeline', 'phpinfo-wp'),                           true, true, true, true],
            [__('Config Grade & Score', 'phpinfo-wp'),                       true, true, true, true],
            [__('Troubleshooting Mode (Per-user)', 'phpinfo-wp'),             true, true, true, true],
            [__('PHP Compatibility Scanner (PHP 7.4–8.4)', 'phpinfo-wp'),    true, true, true, true],
            // Pro-depth features
            [__('Update Guard Suite (Pre/Post update health checks)', 'phpinfo-wp'), false, true, true, true],
            [__('Admin Security Activity Log (Real IP & logins)', 'phpinfo-wp'),     false, true, true, true],
            [__('Autoload Bloat & Missing MySQL Index Scanner', 'phpinfo-wp'),       false, true, true, true],
            [__('Live OPcache & Object Cache Telemetry', 'phpinfo-wp'),              false, true, true, true],
            [__('Detailed Directives & 1-Click Fixes', 'phpinfo-wp'),                false, true, true, true],
            [__('Web Server Snippet Library with Rollback', 'phpinfo-wp'),           false, true, true, true],
            [__('Security Headers audit', 'phpinfo-wp'),                             false, true, true, true],
            [__('SSL certificate monitor', 'phpinfo-wp'),                            false, true, true, true],
            [__('Error Log viewer', 'phpinfo-wp'),                                   false, true, true, true],
            [__('WP-Cron monitor & queue health', 'phpinfo-wp'),                     false, true, true, true],
            [__('Mail deliverability check', 'phpinfo-wp'),                          false, true, true, true],
            [__('Native AI Plain-English Fix Explanations', 'phpinfo-wp'),           false, true, true, true],
            // Deliverables & integrations
            [__('Email alerts on issues', 'phpinfo-wp'),            false, true,          true,          true],
            [__('External API Monitor', 'phpinfo-wp'),              false, __('1 Endpoint', 'phpinfo-wp'),  true,          true],
            [__('Config Snapshots & diff', 'phpinfo-wp'),           false, __('Latest 3', 'phpinfo-wp'),    true,          true],
            [__('PDF Audit Reports', 'phpinfo-wp'),                 false, __('Branded', 'phpinfo-wp'),     __('White-label', 'phpinfo-wp'), __('White-label', 'phpinfo-wp')],
            [__('Weekly health digest', 'phpinfo-wp'),              false, false,         true,          true],
            [__('Slack / Discord webhooks', 'phpinfo-wp'),          false, false,         true,          true],
            [__('Multi-site (Network) support', 'phpinfo-wp'),      false, false,         true,          true],
        ];

        $compare_cols = [
            ['name' => __('Free', 'phpinfo-wp'),         'price' => '$0',   'cadence' => 'WP.org'],
            ['name' => __('Single Site', 'phpinfo-wp'),  'price' => '$39',  'cadence' => __('/year', 'phpinfo-wp')],
            ['name' => __('Unlimited', 'phpinfo-wp'),    'price' => '$69',  'cadence' => __('/1st yr', 'phpinfo-wp'), 'featured' => true],
            ['name' => __('Lifetime', 'phpinfo-wp'),     'price' => '$149', 'cadence' => __('once', 'phpinfo-wp')],
        ];

        $render_cell = static function ($v) {
            if ($v === true)  return '<span class="dashicons dashicons-yes-alt" style="color:#7c3aed" aria-label="Included"></span>';
            if ($v === false) return '<span class="dashicons dashicons-minus" style="color:#c8c8d0" aria-label="Not included"></span>';
            return '<span style="font-size:12px;color:#3c434a">' . esc_html((string) $v) . '</span>';
        };
        ?>
        <h2 class="phpinfowp-section-heading" style="margin-top:36px"><?php _e('Compare plans', 'phpinfo-wp'); ?></h2>
        <p style="color:#646970;margin:0 0 14px;max-width:560px"><?php _e('Everything in Free, plus the Pro depth — see exactly what you get at each tier.', 'phpinfo-wp'); ?></p>
        <div class="phpinfowp-compare-wrap">
            <table class="phpinfowp-compare">
                <thead>
                    <tr>
                        <th scope="col" class="phpinfowp-compare-feat"><?php _e('Feature', 'phpinfo-wp'); ?></th>
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
                <tfoot>
                    <tr>
                        <td></td>
                        <td></td>
                        <td>
                            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button"><?php _e('Buy Single Site', 'phpinfo-wp'); ?></a>
                        </td>
                        <td class="is-featured">
                            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary"><?php _e('Buy Unlimited', 'phpinfo-wp'); ?></a>
                        </td>
                        <td>
                            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button"><?php _e('Buy Lifetime', 'phpinfo-wp'); ?></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- 3-pillar features -->
        <h2 class="phpinfowp-section-heading" style="margin-top:36px"><?php _e('What Pro unlocks', 'phpinfo-wp'); ?></h2>
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

        <!-- Pricing -->
        <h2 class="phpinfowp-section-heading" style="margin-top:36px"><?php _e('Pricing', 'phpinfo-wp'); ?></h2>
        <div class="phpinfowp-pricing">
            <!-- Tier 1: Single Site -->
            <div class="phpinfowp-pricing-tier">
                <div class="phpinfowp-pricing-name"><?php _e('Single Site', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-pricing-price">$39<span>/year</span></div>
                <p class="phpinfowp-pricing-blurb"><?php _e('You own one site and want it running at its best.', 'phpinfo-wp'); ?></p>
                <ul class="phpinfowp-pricing-list">
                    <li><?php _e('All Pro v8.0 features on 1 site', 'phpinfo-wp'); ?></li>
                    <li><?php _e('Standard PDF reports (Branded)', 'phpinfo-wp'); ?></li>
                    <li><?php _e('1 External API monitor & 3 config snapshots', 'phpinfo-wp'); ?></li>
                    <li><?php _e('1 year of updates & email support', 'phpinfo-wp'); ?></li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary phpinfowp-pricing-btn"><?php _e('Buy Single Site', 'phpinfo-wp'); ?></a>
            </div>

            <!-- Tier 2: Unlimited (Featured) -->
            <div class="phpinfowp-pricing-tier is-featured">
                <div class="phpinfowp-pricing-flag"><?php _e('Most Popular · Increases Sept 30', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-pricing-name"><?php _e('Unlimited', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-pricing-price">
                    <del>$79</del> $69<span>/1st yr</span>
                </div>
                <p class="phpinfowp-pricing-blurb"><?php _e('You manage multiple sites. One license covers every one of them.', 'phpinfo-wp'); ?></p>
                <div class="phpinfowp-pricing-alert is-purple">
                    <strong>⚡ <?php _e('Price Increases Sept 30:', 'phpinfo-wp'); ?></strong> <?php _e('Get Unlimited at $69 for your first year before it increases to $79/year on September 30. Subsequent renewals at $79/year.', 'phpinfo-wp'); ?>
                </div>
                <ul class="phpinfowp-pricing-list">
                    <li><?php _e('All Pro v8.0 features on unlimited sites', 'phpinfo-wp'); ?></li>
                    <li><?php _e('Fully white-labeled PDF reports (Custom Logo)', 'phpinfo-wp'); ?></li>
                    <li><?php _e('Weekly digests & Slack/Discord alerts', 'phpinfo-wp'); ?></li>
                    <li><?php _e('Unlimited snapshots & API monitors', 'phpinfo-wp'); ?></li>
                    <li><?php _e('Priority email support & Multi-site support', 'phpinfo-wp'); ?></li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary phpinfowp-pricing-btn is-featured-btn"><?php _e('Buy Unlimited', 'phpinfo-wp'); ?></a>
            </div>

            <!-- Tier 3: Lifetime -->
            <div class="phpinfowp-pricing-tier">
                <div class="phpinfowp-pricing-flag is-warn"><?php _e('52 of 55 Claimed · $249 After 55 Filled', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-pricing-name"><?php _e('Lifetime', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-pricing-price">
                    <del>$249</del> $149<span><?php _e('once', 'phpinfo-wp'); ?></span>
                </div>
                <p class="phpinfowp-pricing-blurb"><?php _e('One payment. Updates and support forever. Zero renewal fees.', 'phpinfo-wp'); ?></p>
                <div class="phpinfowp-pricing-alert is-amber">
                    <strong>⚡ <?php _e('Price increases to $249 after 55 spots:', 'phpinfo-wp'); ?></strong> <?php _e('Only 3 spots remaining at $149 before price jumps to $249. One payment, zero renewal fees forever.', 'phpinfo-wp'); ?>
                </div>
                <div class="phpinfowp-pricing-progress-wrap">
                    <div class="phpinfowp-pricing-progress-labels">
                        <span><?php _e('52 SOLD', 'phpinfo-wp'); ?></span>
                        <span><?php _e('3 LEFT AT $149', 'phpinfo-wp'); ?></span>
                    </div>
                    <div class="phpinfowp-pricing-progress-bar">
                        <div class="phpinfowp-pricing-progress-fill" style="width: 94.5%;"></div>
                    </div>
                </div>
                <ul class="phpinfowp-pricing-list">
                    <li><?php _e('All Pro v8.0 features on unlimited sites', 'phpinfo-wp'); ?></li>
                    <li><?php _e('Fully white-labeled PDF reports (Custom Logo)', 'phpinfo-wp'); ?></li>
                    <li><?php _e('Weekly digests & Slack/Discord alerts', 'phpinfo-wp'); ?></li>
                    <li><?php _e('Lifetime updates & priority support forever', 'phpinfo-wp'); ?></li>
                </ul>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary phpinfowp-pricing-btn"><?php _e('Buy Lifetime', 'phpinfo-wp'); ?></a>
            </div>
        </div>
        <p class="phpinfowp-pricing-foot"><?php _e('14-day money-back guarantee · Instant license delivery · Cancel anytime', 'phpinfo-wp'); ?></p>
    <?php endif; ?>

</div>
