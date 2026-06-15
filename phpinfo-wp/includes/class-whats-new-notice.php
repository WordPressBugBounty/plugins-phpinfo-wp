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
            '7.2.2' => [
                'headline' => 'phpinfo() WP 7.2 is here — Web Server Snippets, API Monitor & more.',
                'bullets'  => [
                    '<strong>New: Web Server Snippet Library</strong> — optimised Nginx & Apache config blocks for caching, security headers, and blocking bad bots. 1-click injection for Apache.',
                    '<strong>New: External API Monitor</strong> — track response times and uptime of 3rd-party services your site depends on.',
                    '<strong>Improved: Update Guard</strong> — now runs automatically before every core update and scores plugin abandonment risk.',
                    '<strong>Pro:</strong> all new features + white-label PDF reports, one-click fixes, SSL/security monitors. <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener"><strong>Upgrade from $29/year →</strong></a>',
                ],
            ],
        ];

        if (!isset($catalog[PHPINFOWP_VERSION])) return;

        $notice      = $catalog[PHPINFOWP_VERSION];
        $dismiss_url = wp_nonce_url(
            add_query_arg('phpinfowp_dismiss_notice', '1'),
            'phpinfowp_dismiss_notice'
        );
        $is_pro      = Phpinfo_WP_License::is_valid();
        ?>
        <style>
        .phpinfowp-whats-new-notice {
            margin-left: 0 !important;
            margin-right: 0 !important;
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
                <span style="background:#777BB3; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:3px; letter-spacing:.4px; text-transform:uppercase; flex-shrink: 0; line-height: 1.4;">v<?php echo esc_html(PHPINFOWP_VERSION); ?></span>
                <p style="font-size:13px; color:#1d2327; line-height:1.45;">
                    <?php if ($is_pro): ?>
                        🎉 <strong>What's New in Pro 7.2:</strong> Accelerate loading with Web Server Snippets, isolate integrations using External API Monitor, and enjoy auto-Update Guard scans before updates.
                    <?php else: ?>
                        🚀 <strong>Boost speed & security with 7.2:</strong> Stop site breaks using auto-Update Guard, load pages faster with 1-click Server Snippets, and detect API latency.
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <?php if ($is_pro): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-htaccess')); ?>" class="phpinfowp-btn-upgrade-notice">
                        Configure Snippets →
                    </a>
                <?php else: ?>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="phpinfowp-btn-upgrade-notice">
                        Get Pro & Unlock →
                    </a>
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
