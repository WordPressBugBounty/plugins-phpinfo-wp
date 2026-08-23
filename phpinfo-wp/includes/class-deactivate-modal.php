<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Renders a retention modal when the user clicks "Deactivate" on the
 * phpinfo() WP row in the WordPress Plugins screen. Shows them which
 * features they'd lose so they don't blindly deactivate a plugin that
 * was protecting them (PHP EOL warnings, Config Grader, SSL monitor, etc.).
 *
 * The modal HTML + JS are injected only on /wp-admin/plugins.php to keep
 * the rest of the admin clean. The "Deactivate anyway" button just lets
 * the original deactivate link follow through.
 *
 * Free users see the free feature list; Pro users see both.
 */
class Phpinfo_WP_Deactivate_Modal {

    public static function register(): void {
        add_action('admin_footer-plugins.php', [__CLASS__, 'render']);
    }

    public static function render(): void {
        $is_pro       = Phpinfo_WP_License::is_valid();
        $plugin_basename = plugin_basename(PHPINFOWP_DIR . 'phpinfo-wp.php');

        $free_features = [
            'phpinfo() viewer + .htaccess editor',
            'PHP EOL Timeline + admin-bar PHP version warning',
            'Config Grader summary (A–F grade)',
            'Troubleshooting Mode (per-user safe-mode with one-click undo)',
            'PHP Compatibility Scanner (catches plugin breakages before PHP upgrades)',
            'Pre-update PHP-version warnings on the Plugins screen',
            'Activity Log of every config change',
        ];
        $pro_features = [
            'White-label PDF Audit Report (your branding, hand to clients)',
            'One-click Config Auto-Fix with rollback',
            'Security Headers + SSL Certificate monitors',
            'OPcache Dashboard + PHP Error Log viewer',
            'Database Health + Autoload bloat detection',
            'WP-Cron Monitor + orphan-hook purge',
            'Email Alerts, Weekly Digest, Slack/Discord webhooks',
            'Multi-site (Network) support',
        ];
        ?>
        <div id="phpinfowp-deactivate-modal" class="phpinfowp-dm" aria-hidden="true" role="dialog" aria-labelledby="phpinfowp-dm-title">
            <div class="phpinfowp-dm-backdrop"></div>
            <div class="phpinfowp-dm-dialog" role="document">
                <button type="button" class="phpinfowp-dm-close" aria-label="Close">&times;</button>

                <div class="phpinfowp-dm-header">
                    <img src="<?php echo esc_url(PHPINFOWP_URL . 'assets/icon-128x128.png'); ?>"
                         alt="" width="48" height="48" class="phpinfowp-dm-logo"
                         onerror="this.style.display='none'">
                    <div>
                        <h2 id="phpinfowp-dm-title" class="phpinfowp-dm-title">Before you deactivate phpinfo() WP…</h2>
                        <p class="phpinfowp-dm-sub"><?php _e('Here\'s what stops working the moment this plugin goes off:', 'piwp'); ?></p>
                    </div>
                </div>

                <div class="phpinfowp-dm-body">
                    <div class="phpinfowp-dm-col">
                        <div class="phpinfowp-dm-col-label"><?php _e('You\'ll lose (Free)', 'piwp'); ?></div>
                        <ul class="phpinfowp-dm-list">
                            <?php foreach ($free_features as $f): ?>
                                <li><span class="dashicons dashicons-no-alt phpinfowp-dm-x"></span><?php echo esc_html($f); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php if ($is_pro): ?>
                    <div class="phpinfowp-dm-col phpinfowp-dm-col-pro">
                        <div class="phpinfowp-dm-col-label"><?php _e('You\'ll also lose (Pro)', 'piwp'); ?></div>
                        <ul class="phpinfowp-dm-list">
                            <?php foreach ($pro_features as $f): ?>
                                <li><span class="dashicons dashicons-no-alt phpinfowp-dm-x"></span><?php echo esc_html($f); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="phpinfowp-dm-warn">
                    <span class="dashicons dashicons-warning"></span>
                    <div>
                        Deactivating <strong><?php _e('doesn\'t delete', 'piwp'); ?></strong> your settings or logs — you can re-activate any time.
                        <?php if ($is_pro): ?>
                            Your license stays on this site; Pro features simply pause while the plugin is off.
                        <?php else: ?>
                            But your site will lose the safety net the moment it's off.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="phpinfowp-dm-actions">
                    <button type="button" class="button button-primary button-large phpinfowp-dm-keep"><?php _e('Keep it active', 'piwp'); ?></button>
                    <a href="#" class="phpinfowp-dm-confirm" data-href=""><?php _e('Deactivate anyway →', 'piwp'); ?></a>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var slug   = <?php echo wp_json_encode($plugin_basename); ?>;
            var modal  = document.getElementById('phpinfowp-deactivate-modal');
            if (!modal) return;
            var confirmLink = modal.querySelector('.phpinfowp-dm-confirm');
            var closeBtns   = modal.querySelectorAll('.phpinfowp-dm-close, .phpinfowp-dm-keep, .phpinfowp-dm-backdrop');

            function open(url) {
                confirmLink.setAttribute('href', url);
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }
            function close() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
            closeBtns.forEach(function (b) { b.addEventListener('click', close); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
            });

            // Match WP's deactivate link: tr[data-plugin="…/phpinfo-wp.php"] .deactivate a
            // The slug differs between standard and must-use installs, so we
            // also match the action=deactivate URL fragment as a safety net.
            document.addEventListener('click', function (e) {
                var a = e.target.closest && e.target.closest('a');
                if (!a) return;
                var href = a.getAttribute('href') || '';
                if (!/action=deactivate/.test(href)) return;
                var row = a.closest('tr[data-plugin]');
                var matchesRow = row && row.getAttribute('data-plugin') === slug;
                var matchesUrl = href.indexOf(encodeURIComponent(slug)) !== -1;
                if (!matchesRow && !matchesUrl) return;
                if (a.closest('.phpinfowp-dm')) return;
                e.preventDefault();
                open(href);
            }, true);
        }());
        </script>
        <?php
    }
}
