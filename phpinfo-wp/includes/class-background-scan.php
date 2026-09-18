<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Enterprise Leave-Anytime Background Scan Manager.
 *
 * Provides non-blocking execution resilience via ignore_user_abort(true),
 * lock management, status polling, and global admin completion alerts across:
 *  - PHP Compatibility Scanner (piwp-compat)
 *  - Permissions & Ownership Auditor (piwp-permissions)
 *  - Update Guard (piwp-update-audit)
 */
class Phpinfo_WP_Background_Scan {

    const TIMEOUT_SECONDS = 180; // 3 minutes stale lock protection
    const MODULES = ['compat', 'perms', 'update_guard', 'core_audit'];

    public static function init(): void {
        add_action('wp_ajax_phpinfowp_bg_scan_status', [__CLASS__, 'ajax_status']);
        add_action('wp_ajax_phpinfowp_bg_scan_dismiss_notice', [__CLASS__, 'ajax_dismiss_notice']);
        add_action('admin_notices', [__CLASS__, 'render_admin_notices']);
    }

    /**
     * Start a background scan session for a given module.
     */
    public static function start(string $module, array $params = []): bool {
        if (!in_array($module, self::MODULES, true)) return false;

        $current = self::status($module);
        if ($current['status'] === 'running') {
            return false; // Already running and not stale
        }

        @ignore_user_abort(true);
        @set_time_limit(300);

        set_transient("phpinfowp_bg_{$module}", [
            'status'     => 'running',
            'started_at' => time(),
            'params'     => $params,
            'notified'   => false,
        ], 12 * HOUR_IN_SECONDS);

        return true;
    }

    /**
     * Mark a scan as successfully completed.
     */
    public static function complete(string $module, array $summary = []): void {
        if (!in_array($module, self::MODULES, true)) return;

        set_transient("phpinfowp_bg_{$module}", [
            'status'       => 'complete',
            'completed_at' => time(),
            'summary'      => $summary,
            'notified'     => false,
        ], 12 * HOUR_IN_SECONDS);
    }

    /**
     * Reset and clear scan state for a module.
     */
    public static function clear(string $module): void {
        delete_transient("phpinfowp_bg_{$module}");
    }

    /**
     * Retrieve the current state of a module's scan.
     */
    public static function status(string $module): array {
        if (!in_array($module, self::MODULES, true)) {
            return ['status' => 'idle'];
        }

        $state = get_transient("phpinfowp_bg_{$module}");
        if (!is_array($state) || empty($state['status'])) {
            return ['status' => 'idle'];
        }

        // Stale lock safeguard
        if ($state['status'] === 'running') {
            $started = (int) ($state['started_at'] ?? 0);
            if ($started > 0 && (time() - $started) > self::TIMEOUT_SECONDS) {
                self::clear($module);
                return ['status' => 'idle', 'was_stale' => true];
            }
            return [
                'status'     => 'running',
                'started_at' => $started,
                'elapsed'    => max(0, time() - $started),
                'params'     => $state['params'] ?? [],
            ];
        }

        return [
            'status'       => $state['status'],
            'completed_at' => $state['completed_at'] ?? 0,
            'summary'      => $state['summary'] ?? [],
            'notified'     => !empty($state['notified']),
        ];
    }

    /**
     * AJAX endpoint: return current status of a module.
     */
    public static function ajax_status(): void {
        check_ajax_referer('phpinfowp_bg_scan_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        $module = sanitize_key($_POST['module'] ?? '');
        $status = self::status($module);
        wp_send_json_success($status);
    }

    /**
     * Mark a completed scan as acknowledged / seen so it never fires global notices.
     */
    public static function acknowledge(string $module): void {
        if (!in_array($module, self::MODULES, true)) return;
        $state = get_transient("phpinfowp_bg_{$module}");
        if (is_array($state)) {
            $state['notified'] = true;
            set_transient("phpinfowp_bg_{$module}", $state, 12 * HOUR_IN_SECONDS);
        }
    }

    /**
     * AJAX endpoint: dismiss the completion notice.
     */
    public static function ajax_dismiss_notice(): void {
        check_ajax_referer('phpinfowp_bg_scan_nonce', 'nonce');
        $module = sanitize_key($_POST['module'] ?? '');
        self::acknowledge($module);
        wp_send_json_success();
    }

    /**
     * Render global admin completion notices if the user navigated away while a scan finished.
     */
    public static function render_admin_notices(): void {
        if (!current_user_can('manage_options')) return;

        $current_page = sanitize_key($_GET['page'] ?? '');
        $current_tab  = sanitize_key($_GET['tab'] ?? '');

        // Default tab mapping for multi-tab views
        if ($current_page === 'piwp-update-audit' && empty($current_tab)) {
            $current_tab = 'plugins';
        }

        $configs = [
            'compat' => [
                'page'     => 'piwp-compat',
                'title'    => __('PHP Compatibility Scan Completed', 'phpinfo-wp'),
                'icon'     => 'dashicons-yes-alt',
                'desc_fn'  => function($summary) {
                    $files = !empty($summary['files']) ? number_format($summary['files']) : null;
                    return $files
                        ? sprintf(__('Scanned %s PHP files. Review compatibility results and deprecation warnings.', 'phpinfo-wp'), $files)
                        : __('Background scan completed. Review compatibility results and deprecation warnings.', 'phpinfo-wp');
                },
            ],
            'perms' => [
                'page'     => 'piwp-permissions',
                'title'    => __('Permissions & Ownership Audit Completed', 'phpinfo-wp'),
                'icon'     => 'dashicons-shield',
                'desc_fn'  => function($summary) {
                    $items = !empty($summary['scanned']) ? number_format($summary['scanned']) : null;
                    return $items
                        ? sprintf(__('Audited %s filesystem items. Review security permissions and ownership.', 'phpinfo-wp'), $items)
                        : __('Filesystem audit completed. Review security permissions and ownership.', 'phpinfo-wp');
                },
            ],
            'update_guard' => [
                'page'     => 'piwp-update-audit',
                'tab'      => 'plugins',
                'title'    => __('Update Guard Audit Completed', 'phpinfo-wp'),
                'icon'     => 'dashicons-update',
                'desc_fn'  => function($summary) {
                    $total = !empty($summary['total']) ? (int)$summary['total'] : null;
                    return $total
                        ? sprintf(__('Evaluated %d pending update(s) across changelogs and compatibility history.', 'phpinfo-wp'), $total)
                        : __('Changelog evaluation completed. Review update risk assessments.', 'phpinfo-wp');
                },
            ],
            'core_audit' => [
                'page'     => 'piwp-update-audit',
                'tab'      => 'core',
                'title'    => __('WordPress Core Audit Completed', 'phpinfo-wp'),
                'icon'     => 'dashicons-wordpress',
                'desc_fn'  => function($summary) {
                    $target = !empty($summary['target']) ? $summary['target'] : '';
                    $files  = !empty($summary['files']) ? number_format($summary['files']) : null;
                    return $files
                        ? sprintf(__('Scanned %s files against WordPress %s core compatibility.', 'phpinfo-wp'), $files, esc_html($target))
                        : __('WordPress core audit completed. Review API compatibility warnings.', 'phpinfo-wp');
                },
            ],
        ];

        foreach ($configs as $mod => $cfg) {
            $state = get_transient("phpinfowp_bg_{$mod}");
            if (!is_array($state) || ($state['status'] ?? '') !== 'complete' || !empty($state['notified'])) {
                continue;
            }

            // If user is currently on the module's target page and tab, mark as acknowledged immediately
            $is_same_page = ($current_page === $cfg['page']);
            $is_same_tab  = empty($cfg['tab']) || ($current_tab === $cfg['tab']);
            if ($is_same_page && $is_same_tab) {
                self::acknowledge($mod);
                continue;
            }

            $summary  = $state['summary'] ?? [];
            $desc     = call_user_func($cfg['desc_fn'], $summary);
            $query_args = ['page' => $cfg['page']];
            if (!empty($cfg['tab'])) {
                $query_args['tab'] = $cfg['tab'];
            }
            $page_url = add_query_arg($query_args, admin_url('admin.php'));
            $nonce    = wp_create_nonce('phpinfowp_bg_scan_nonce');
            ?>
            <style>
            .notice.phpinfowp-bg-complete-notice {
                border-left-color: #777BB3 !important;
                background: #ffffff !important;
                border-top: 1px solid #e2e8f0 !important;
                border-right: 1px solid #e2e8f0 !important;
                border-bottom: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.06), 0 2px 6px rgba(0, 0, 0, 0.03) !important;
                padding: 12px 52px 12px 16px !important;
                margin: 16px 20px 16px 0 !important;
                position: relative !important;
            }
            .notice.phpinfowp-bg-complete-notice .notice-dismiss {
                position: absolute !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                right: 14px !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 28px !important;
                height: 28px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 6px !important;
                text-decoration: none !important;
                background: none !important;
                border: none !important;
                cursor: pointer !important;
                transition: all 0.15s ease !important;
            }
            .notice.phpinfowp-bg-complete-notice .notice-dismiss:hover {
                background: #f1f5f9 !important;
            }
            .notice.phpinfowp-bg-complete-notice .notice-dismiss:before {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 16px !important;
                height: 16px !important;
                line-height: 1 !important;
                font-size: 16px !important;
                margin: 0 !important;
                color: #94a3b8 !important;
            }
            .notice.phpinfowp-bg-complete-notice .notice-dismiss:hover:before {
                color: #ef4444 !important;
            }
            .phpinfowp-bg-btn {
                background: #777BB3 !important;
                border: 1px solid #63679d !important;
                color: #ffffff !important;
                border-radius: 6px !important;
                height: 32px !important;
                line-height: 30px !important;
                font-size: 12.5px !important;
                font-weight: 600 !important;
                padding: 0 16px !important;
                text-decoration: none !important;
                white-space: nowrap !important;
                flex-shrink: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                box-shadow: 0 2px 6px rgba(119, 123, 179, 0.28) !important;
                transition: all 0.15s ease !important;
            }
            .phpinfowp-bg-btn:hover,
            .phpinfowp-bg-btn:focus {
                background: #686ca5 !important;
                border-color: #585c94 !important;
                color: #ffffff !important;
                box-shadow: 0 3px 8px rgba(119, 123, 179, 0.38) !important;
                transform: translateY(-1px) !important;
            }
            </style>
            <div class="notice notice-info is-dismissible phpinfowp-bg-complete-notice" data-module="<?php echo esc_attr($mod); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; min-height:36px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:36px; height:36px; border-radius:8px; background:rgba(119,123,179,0.12); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <span class="dashicons <?php echo esc_attr($cfg['icon']); ?>" style="color:#777BB3; font-size:20px; width:20px; height:20px;"></span>
                        </div>
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <span style="background:rgba(119,123,179,0.12); color:#777BB3; font-size:10.5px; font-weight:700; letter-spacing:0.4px; padding:1px 6px; border-radius:4px; text-transform:uppercase;">phpinfo() WP</span>
                                <strong style="color:#0f172a; font-size:13.5px; font-weight:700;"><?php echo esc_html($cfg['title']); ?></strong>
                            </div>
                            <p style="margin:3px 0 0; color:#64748b; font-size:12.5px; line-height:1.4;"><?php echo esc_html($desc); ?></p>
                        </div>
                    </div>
                    <a href="<?php echo esc_url($page_url); ?>" class="phpinfowp-bg-btn">
                        <?php _e('View Results &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var notices = document.querySelectorAll('.phpinfowp-bg-complete-notice');
                notices.forEach(function(notice) {
                    var mod = notice.getAttribute('data-module');
                    var nce = notice.getAttribute('data-nonce');
                    notice.addEventListener('click', function(e) {
                        if (e.target && (e.target.classList.contains('notice-dismiss') || e.target.closest('.notice-dismiss'))) {
                            var fd = new FormData();
                            fd.append('action', 'phpinfowp_bg_scan_dismiss_notice');
                            fd.append('module', mod);
                            fd.append('nonce', nce);
                            fetch(ajaxurl, { method: 'POST', body: fd });
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }
}
