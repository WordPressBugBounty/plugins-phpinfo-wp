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

        // Never show sales or upgrade notices to Pro users.
        if (class_exists('Phpinfo_WP_License') && Phpinfo_WP_License::is_valid()) {
            return;
        }

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
            '8.0.0' => [
                'headline' => 'phpinfo() WP 8.0.0 — Server Intelligence & Security Suite',
            ],
        ];

        if (!isset($catalog[PHPINFOWP_VERSION])) return;

        $notice      = $catalog[PHPINFOWP_VERSION];
        $dismiss_url = wp_nonce_url(
            add_query_arg('phpinfowp_dismiss_notice', '1'),
            'phpinfowp_dismiss_notice'
        );
        // Pull real site data + Pro v8.0 features to drive conversions.
        $deadline    = strtotime('2026-09-30 23:59:59');
        $days_left   = (int) ceil(($deadline - time()) / DAY_IN_SECONDS);
        $urgency_txt = ($days_left > 0)
            ? sprintf(
                /* translators: %d: days remaining, %s: plural s */
                __(' <strong>Lock in early-bird rates before prices rise Sept 30 (%d day%s left).</strong>', 'phpinfo-wp'),
                $days_left,
                $days_left === 1 ? '' : 's'
            )
            : '';

        // 1. Live Memory Analysis
        $mem_limit_raw   = ini_get('memory_limit') ?: '128M';
        $mem_limit_bytes = function_exists('wp_convert_hr_to_bytes') ? wp_convert_hr_to_bytes($mem_limit_raw) : 0;
        $peak_bytes      = memory_get_peak_usage(true);
        $cached_peak     = (int) get_transient('phpinfowp_mem_peak_' . date('Ymd'));
        if ($cached_peak > $peak_bytes) {
            $peak_bytes = $cached_peak;
        }
        $mem_pct = ($mem_limit_bytes > 0) ? (int) round(($peak_bytes / $mem_limit_bytes) * 100) : 0;
        $mem_used_label = function_exists('size_format') ? size_format($peak_bytes) : (round($peak_bytes / 1048576, 1) . ' MB');

        // 2. PHP EOL Detection
        $eol = class_exists('Phpinfo_WP_EOL') ? Phpinfo_WP_EOL::status() : [];
        $is_eol = ($eol['status'] ?? '') === 'eol';

        // 3. Error Log count
        $err_count = class_exists('Phpinfo_WP_Error_Log') ? (int) Phpinfo_WP_Error_Log::today_count() : 0;

        // 4. OPcache
        $opcache_off = !function_exists('opcache_get_status') || !is_array(@opcache_get_status());

        // 5. Expose PHP
        $expose_php_on = (bool) ini_get('expose_php');

        // 6. Upload limit
        $upload_raw   = ini_get('upload_max_filesize') ?: '2M';
        $upload_bytes = function_exists('wp_convert_hr_to_bytes') ? wp_convert_hr_to_bytes($upload_raw) : 0;
        $is_upload_tight = ($upload_bytes > 0 && $upload_bytes <= 2097152); // <= 2MB

        // 7. Config Grader
        $grader = class_exists('Phpinfo_WP_Config_Grader') ? Phpinfo_WP_Config_Grader::summary() : [];
        $fails  = (int) ($grader['fails'] ?? 0);
        $grade  = $grader['grade'] ?? '';

        // Select the most pressing real issue on the site
        if ($mem_limit_bytes > 0 && $mem_pct >= 65) {
            // Case 1: High RAM utilization (e.g. 89MB of 128MB)
            $badge_text = sprintf(__('v%s · %d%% RAM Peak', 'phpinfo-wp'), esc_html(PHPINFOWP_VERSION), $mem_pct);
            $site_msg   = sprintf(
                /* translators: 1: used memory, 2: limit, 3: percentage */
                __('💾 <strong>Memory Ceiling Alert:</strong> Peak RAM consumed <strong>%1$s of %2$s (%3$d%% capacity)</strong>. You risk fatal 500 out-of-memory crashes during updates or traffic spikes. Auto-boost memory limits with <strong>1-Click Directive Fixes</strong> and safeguard core with <strong>Update Guard</strong>.', 'phpinfo-wp'),
                esc_html($mem_used_label),
                esc_html($mem_limit_raw),
                $mem_pct
            ) . $urgency_txt;
            $cta_label = ($days_left > 0)
                ? sprintf(__('1-Click Memory Fix (%dd left) →', 'phpinfo-wp'), $days_left)
                : __('1-Click Memory Fix with Pro →', 'phpinfo-wp');

        } elseif ($is_eol) {
            // Case 2: PHP End of Life
            $badge_text = sprintf(__('v%s · PHP %s EOL', 'phpinfo-wp'), esc_html(PHPINFOWP_VERSION), esc_html($eol['minor'] ?? PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION));
            $site_msg   = sprintf(
                /* translators: 1: minor version, 2: eol date */
                __('⚠️ <strong>Security Risk:</strong> Running PHP %1$s which reached <strong>End-Of-Life on %2$s</strong> and receives zero security patches. Use <strong>PHP Compatibility Scanner</strong> to safely audit plugins for PHP 8.3/8.4 before upgrading.', 'phpinfo-wp'),
                esc_html($eol['minor'] ?? PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION),
                esc_html($eol['eol'] ?? 'past')
            ) . $urgency_txt;
            $cta_label = ($days_left > 0)
                ? sprintf(__('Scan PHP Compatibility (%dd left) →', 'phpinfo-wp'), $days_left)
                : __('Scan PHP Compatibility with Pro →', 'phpinfo-wp');

        } elseif ($err_count > 0) {
            // Case 3: Live PHP errors recorded today
            $badge_text = sprintf(__('v%s · %d Errors Today', 'phpinfo-wp'), esc_html(PHPINFOWP_VERSION), $err_count);
            $site_msg   = sprintf(
                /* translators: 1: error count, 2: plural s */
                __('🚨 <strong>%1$d PHP error%2$s logged today</strong> silently degrading site stability. Inspect stack traces and get instant <strong>Native AI Plain-English Fix Explanations</strong> to resolve bottlenecks before clients notice.', 'phpinfo-wp'),
                $err_count,
                $err_count === 1 ? '' : 's'
            ) . $urgency_txt;
            $cta_label = ($days_left > 0)
                ? sprintf(__('Explain & Fix by One Click (%dd left) →', 'phpinfo-wp'), $days_left)
                : __('Explain & Fix by One Click →', 'phpinfo-wp');

        } elseif ($opcache_off) {
            // Case 4: OPcache disabled
            $badge_text = sprintf(__('v%s · OPcache Off', 'phpinfo-wp'), esc_html(PHPINFOWP_VERSION));
            $site_msg   = __('⚡ <strong>Performance Bottleneck:</strong> OPcache bytecode caching is disabled. PHP recompiles scripts on every hit, adding heavy server latency. Unlock <strong>Live OPcache Telemetry</strong> and 1-click server snippets to restore full speed.', 'phpinfo-wp') . $urgency_txt;
            $cta_label  = ($days_left > 0)
                ? sprintf(__('Optimize Server Speed (%dd left) →', 'phpinfo-wp'), $days_left)
                : __('Optimize Server Speed with Pro →', 'phpinfo-wp');

        } elseif ($mem_limit_bytes > 0 && $mem_limit_bytes <= 134217728) {
            // Case 5: Low memory ceiling (<= 128M)
            $badge_text = sprintf(__('v%s · RAM Limit: %s', 'phpinfo-wp'), esc_html(PHPINFOWP_VERSION), esc_html($mem_limit_raw));
            $site_msg   = sprintf(
                /* translators: 1: current limit */
                __('💾 <strong>Low Memory Limit:</strong> <code>memory_limit</code> is capped at <strong>%s</strong> (WordPress recommends 256M+). Prevent out-of-memory crashes with <strong>1-Click Directive Fixes</strong> and protect updates with <strong>Update Guard</strong>.', 'phpinfo-wp'),
                esc_html($mem_limit_raw)
            ) . $urgency_txt;
            $cta_label = ($days_left > 0)
                ? sprintf(__('1-Click Memory Fix (%dd left) →', 'phpinfo-wp'), $days_left)
                : __('1-Click Memory Fix with Pro →', 'phpinfo-wp');

        } elseif ($expose_php_on) {
            // Case 6: Expose PHP leaking version
            $badge_text = sprintf(__('v%s · Security Leak', 'phpinfo-wp'), esc_html(PHPINFOWP_VERSION));
            $site_msg   = sprintf(
                /* translators: 1: PHP version */
                __('🛡️ <strong>Security Header Leak:</strong> <code>expose_php</code> is active, broadcasting your exact PHP version (%s) to bot scanners in every HTTP response. Turn it off in 1 click and audit headers with Pro.', 'phpinfo-wp'),
                esc_html(PHP_VERSION)
            ) . $urgency_txt;
            $cta_label = ($days_left > 0)
                ? sprintf(__('1-Click Security Fix (%dd left) →', 'phpinfo-wp'), $days_left)
                : __('1-Click Security Fix with Pro →', 'phpinfo-wp');

        } elseif ($is_upload_tight) {
            // Case 7: Upload limit is <= 2M
            $badge_text = sprintf(__('v%s · Upload: %s', 'phpinfo-wp'), esc_html(PHPINFOWP_VERSION), esc_html($upload_raw));
            $site_msg   = sprintf(
                /* translators: 1: upload limit */
                __('⚠️ <strong>Restricted Upload Limit:</strong> <code>upload_max_filesize</code> is restricted to <strong>%s</strong>, causing media and plugin installations to fail. Auto-raise upload & POST limits with <strong>1-Click Directive Fixes</strong>.', 'phpinfo-wp'),
                esc_html($upload_raw)
            ) . $urgency_txt;
            $cta_label = ($days_left > 0)
                ? sprintf(__('1-Click Upload Fix (%dd left) →', 'phpinfo-wp'), $days_left)
                : __('1-Click Upload Fix with Pro →', 'phpinfo-wp');

        } elseif ($fails > 0 && $grade) {
            // Case 8: General Config Grader fails
            $badge_text = sprintf(__('v%s · %d Issues Found', 'phpinfo-wp'), esc_html(PHPINFOWP_VERSION), $fails);
            $site_msg   = sprintf(
                /* translators: 1: grade, 2: fails, 3: plural */
                __('⚠️ <strong>Your server scores %1$s with %2$d failing check%3$s.</strong> Auto-patch directives with <strong>1-Click Fixes</strong>, unlock <strong>Native AI Explanations</strong>, and prevent 500 errors with <strong>Update Guard</strong>.', 'phpinfo-wp'),
                esc_html($grade),
                $fails,
                $fails === 1 ? '' : 's'
            ) . $urgency_txt;
            $cta_label = ($days_left > 0)
                ? sprintf(__('1-Click Fix with Pro (%dd left) →', 'phpinfo-wp'), $days_left)
                : sprintf(__('Resolve %d Issue%s with Pro →', 'phpinfo-wp'), $fails, $fails === 1 ? '' : 's');

        } else {
            // Case 9: Pre-Update Risk Sentinel (Never generic "running cleanly")
            $badge_text = sprintf(__('v%s · Pre-Update Sentinel', 'phpinfo-wp'), esc_html(PHPINFOWP_VERSION));
            $site_msg   = __('🛡️ <strong>WordPress 7.1.1 Pre-Update Sentinel:</strong> Prevent white-screen crashes before updating core or plugins. Simulate updates with <strong>Update Guard</strong>, scan for abandoned plugins, and generate <strong>White-Label PDF Reports</strong>.', 'phpinfo-wp') . $urgency_txt;
            $cta_label  = ($days_left > 0)
                ? sprintf(__('Audit Before Updating (%dd left) →', 'phpinfo-wp'), $days_left)
                : __('Audit Before Updating with Pro →', 'phpinfo-wp');
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
                <span style="background:#777BB3; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:3px; letter-spacing:.4px; text-transform:uppercase; flex-shrink: 0; line-height: 1.4;"><?php echo esc_html($badge_text); ?></span>
                <p style="font-size:13px; color:#1d2327; line-height:1.45;">
                    <?php echo wp_kses($site_msg, ['strong' => [], 'a' => ['href' => [], 'target' => [], 'rel' => []]]); ?>
                </p>
            </div>
            <div>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="phpinfowp-btn-upgrade-notice"><?php echo esc_html($cta_label); ?></a>
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
