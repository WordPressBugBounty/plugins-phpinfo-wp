<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * One-time "What's New" admin notice for Pro.
 *
 * Fires once per admin user the first time they load the WP admin after
 * the plugin updates to a version listed in $catalog below. Dismissed state
 * is stored in user meta so it never re-appears.
 *
 * To add copy for a future release, add an entry to $catalog in render().
 */
class Phpinfo_WP_Whats_New_Notice {

    const META_KEY = 'phpinfowp_dismissed_notice';

    public static function register(): void {
        add_action('admin_notices',  [self::class, 'render']);
        add_action('admin_init',     [self::class, 'handle_dismiss']);
        add_action('wp_ajax_phpinfowp_dismiss_whats_new', [self::class, 'ajax_dismiss']);
    }

    public static function ajax_dismiss(): void {
        check_ajax_referer('phpinfowp_dismiss_notice', 'nonce');
        if (current_user_can('manage_options')) {
            update_user_meta(get_current_user_id(), self::META_KEY, PHPINFOWP_VERSION);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public static function handle_dismiss(): void {
        if (
            isset($_GET['phpinfowp_dismiss_notice']) &&
            current_user_can('manage_options') &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'phpinfowp_dismiss_notice')
        ) {
            update_user_meta(get_current_user_id(), self::META_KEY, PHPINFOWP_VERSION);
            wp_safe_redirect(remove_query_arg(['phpinfowp_dismiss_notice', '_wpnonce']));
            exit;
        }
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) return;

        $dismissed = get_user_meta(get_current_user_id(), self::META_KEY, true);
        if ($dismissed === PHPINFOWP_VERSION) return;

        // Only show notices for versions we've written copy for.
        $catalog = [
            '7.2.4' => [
                'headline' => 'phpinfo() WP 7.2 is here - Web Server Snippets, API Monitor & more.',
                'bullets'  => [
                    '<strong>New: Web Server Snippet Library</strong> - optimised Nginx & Apache config blocks for caching, security headers, and blocking bad bots. 1-click injection for Apache.',
                    '<strong>New: External API Monitor</strong> - track response times and uptime of 3rd-party services your site depends on.',
                    '<strong>Improved: Update Guard</strong> - now runs automatically before every core update and scores plugin abandonment risk.',
                    '<strong>Pro:</strong> all new features + white-label PDF reports, one-click fixes, SSL/security monitors. <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener"><strong>Upgrade from $29/year →</strong></a>',
                ],
            ],
            '7.2.5' => [
                'headline' => 'phpinfo() WP 7.2.5',
            ],
            '7.2.6' => [
                'headline' => 'WordPress 7.1 Update Guard & Compatibility Ready',
            ],
            '7.2.7' => [
                'headline' => 'phpinfo() WP 7.2.7 — Security & Performance Update',
            ],
        ];

        if (!isset($catalog[PHPINFOWP_VERSION])) return;

        $notice      = $catalog[PHPINFOWP_VERSION];
        $dismiss_url = wp_nonce_url(
            add_query_arg('phpinfowp_dismiss_notice', '1'),
            'phpinfowp_dismiss_notice'
        );
        $is_pro = Phpinfo_WP_License::is_valid();

        // Pull real site data + WP 7.1 Update Guard hook to drive Pro conversions.
        $site_msg   = '';
        $cta_label  = __('Audit for WP 7.1 & Lock $29 →', 'piwp');
        if (version_compare(PHPINFOWP_VERSION, '7.2.5', '>=') && !$is_pro) {
            $grader = Phpinfo_WP_Config_Grader::summary();
            $fails  = (int) ($grader['fails'] ?? 0);
            $grade  = $grader['grade'] ?? '';
            $days_left = (int) ceil((strtotime('2026-08-31') - time()) / DAY_IN_SECONDS);

            if ($fails > 0 && $grade) {
                $site_msg = sprintf(
                    /* translators: 1: letter grade, 2: number of failing checks */
                    __('🛡️ <strong>WordPress 7.1 is here — is your site ready?</strong> Your server scores <strong>%1$s</strong> (%2$d failing check%3$s). Scan plugins & PHP with Update Guard before updating core.', 'piwp'),
                    esc_html($grade),
                    $fails,
                    $fails === 1 ? '' : 's'
                );
            } else {
                $site_msg = __('🛡️ <strong>WordPress 7.1 is here — is your site ready to update?</strong> Scan plugins, themes, and PHP compatibility with Update Guard before upgrading to prevent silent 500 errors.', 'piwp');
            }

            if ($days_left > 0) {
                $site_msg .= ' ' . sprintf(
                    /* translators: %d: days remaining before price increase */
                    __('<strong>Lock in $29/yr before Aug 31 ($39/yr in %d days).</strong>', 'piwp'),
                    $days_left
                );
                $cta_label = sprintf(__('Audit for WP 7.1 & Lock $29 (%dd left) →', 'piwp'), $days_left);
            } else {
                $cta_label = __('Audit for WP 7.1 with Pro →', 'piwp');
            }
        }

        ?>
        <style>
        .phpinfowp-whats-new-notice {
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-bottom: 16px !important;
            border-left-color: #777BB3 !important;
            padding: 10px 16px !important;
            padding-right: 46px !important; /* Make room for absolute X close button */
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            flex-wrap: wrap !important;
            gap: 12px !important;
            background: #fff !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
            border-radius: 6px !important;
        }
        .phpinfowp-whats-new-notice p {
            margin: 0 !important;
            padding: 0 !important;
        }
        .phpinfowp-btn-upgrade-notice {
            background: #777BB3 !important;
            color: #fff !important;
            padding: 6px 14px !important;
            border-radius: 4px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            transition: background 0.15s ease !important;
        }
        .phpinfowp-btn-upgrade-notice:hover {
            background: #62659e !important;
            color: #fff !important;
        }
        </style>
        <div class="notice notice-info is-dismissible phpinfowp-whats-new-notice">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 280px;">
                <span style="background:#777BB3; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:3px; letter-spacing:.4px; text-transform:uppercase; flex-shrink: 0; line-height: 1.4;">v<?php echo esc_html(PHPINFOWP_VERSION); ?> · WP 7.1</span>
                <p style="font-size:13px; color:#1d2327; line-height:1.45;">
                    <?php if ($is_pro): ?>
                        ✅ <strong><?php _e('phpinfo() WP Pro is ready for WordPress 7.1.', 'piwp'); ?></strong> <?php _e('Your server health audit and Update Guard are fully up to date.', 'piwp'); ?>
                    <?php elseif ($site_msg): ?>
                        <?php echo wp_kses($site_msg, ['strong' => [], 'a' => ['href' => [], 'target' => [], 'rel' => []]]); ?>
                    <?php else: ?>
                        🚀 <strong><?php printf(esc_html__('phpinfo() WP %s is ready.', 'piwp'), esc_html(PHPINFOWP_VERSION)); ?></strong> <?php _e('Upgrade to Pro to unlock one-click fixes, SSL monitor, security headers, and white-label PDF reports.', 'piwp'); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <?php if ($is_pro): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=piwp')); ?>" class="phpinfowp-btn-upgrade-notice">
                        <?php _e('View Dashboard →', 'piwp'); ?>
                    </a>
                <?php else: ?>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="phpinfowp-btn-upgrade-notice"><?php echo esc_html($cta_label); ?></a>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var notice = document.querySelector('.phpinfowp-whats-new-notice');
            if (!notice) return;

            var dismiss = function(e) {
                var data = new FormData();
                data.append('action', 'phpinfowp_dismiss_whats_new');
                data.append('nonce', '<?php echo esc_js(wp_create_nonce("phpinfowp_dismiss_notice")); ?>');
                fetch('<?php echo esc_url_raw(admin_url("admin-ajax.php")); ?>', {
                    method: 'POST',
                    body: data
                });
                notice.style.transition = 'opacity 0.3s ease';
                notice.style.opacity = '0';
                setTimeout(function() {
                    notice.remove();
                }, 300);
            };

            notice.addEventListener('click', function(e) {
                if (e.target.closest('.notice-dismiss')) {
                    dismiss(e);
                }
            });
        });
        </script>
        <?php
    }
}
