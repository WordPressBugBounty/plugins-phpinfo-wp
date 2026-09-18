<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Update History — persistent log of every plugin, theme, and core update
 * with automated post-update health checks.
 *
 * Hooks into `upgrader_process_complete` to capture every update event. After
 * recording, schedules a 60-second deferred health check that verifies:
 *   1. Site loopback (HTTP 200?)
 *   2. Admin reachable (wp-admin loads?)
 *   3. Error log growth (new fatal errors?)
 *   4. Cron integrity (events still registered?)
 *
 * If health degrades, surfaces an admin notice and (Pro) sends alerts.
 *
 * Data is stored in wp_options (no custom tables) as a capped JSON array.
 * Free: last 30 entries. Pro: last 200.
 */
class Phpinfo_WP_Update_History {

    const OPT             = 'phpinfowp_update_history';
    const OPT_HEALTH_ALERT = 'phpinfowp_ug_health_alert'; // transient-like flag
    const FREE_MAX        = 30;
    const PRO_MAX         = 200;
    const HEALTH_HOOK     = 'phpinfowp_deferred_health_check';

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    const OPT_SNAPSHOTS   = 'piwp_ug_latest_snapshot';
    const OPT_ROLLBACK_LOG = 'piwp_ug_rollback_log';

    public static function register(): void {
        add_filter('upgrader_pre_install',      [self::class, 'pre_install_snapshot'], 10, 2);
        add_action('upgrader_process_complete', [self::class, 'on_update_complete'], 10, 2);
        add_action(self::HEALTH_HOOK,           [self::class, 'deferred_health_check']);
        add_action('admin_notices',             [self::class, 'maybe_show_health_alert']);
        add_action('wp_ajax_phpinfowp_uh_health_check', [self::class, 'ajax_health_check']);
        add_action('wp_ajax_phpinfowp_uh_rollback',     [self::class, 'ajax_rollback']);
        add_action('wp_ajax_phpinfowp_uh_clear',        [self::class, 'ajax_clear']);
    }

    public static function ajax_health_check(): void {
        check_ajax_referer('phpinfowp_uh_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        $result = self::run_live_health_check();
        wp_send_json_success($result);
    }

    public static function ajax_clear(): void {
        check_ajax_referer('phpinfowp_uh_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        self::clear();
        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // Record update events
    // -------------------------------------------------------------------------

    /**
     * Fires after any update completes (core, plugin, theme, translation).
     *
     * @param WP_Upgrader $upgrader
     * @param array       $options  Contains 'action', 'type', 'plugins'/'themes' etc.
     */
    public static function on_update_complete($upgrader, $options): void {
        if (empty($options['action']) || $options['action'] !== 'update') return;

        $type    = $options['type'] ?? '';
        $entries = [];

        switch ($type) {
            case 'plugin':
                $entries = self::record_plugin_updates($options);
                break;
            case 'theme':
                $entries = self::record_theme_updates($options);
                break;
            case 'core':
                $entries = self::record_core_update();
                break;
            default:
                return; // translations, etc. — not tracked
        }

        if (empty($entries)) return;

        // Snapshot error log line count BEFORE saving (we compare after 60s)
        $error_count = self::count_error_log_lines();

        $history = self::all();
        foreach ($entries as &$entry) {
            $entry['error_lines_before'] = $error_count;
            $entry['health']             = null; // will be filled by deferred check
        }
        unset($entry);

        $history = array_merge($history, $entries);

        // Cap the history
        $max = self::_pro() ? self::PRO_MAX : self::FREE_MAX;
        if (count($history) > $max) {
            $history = array_slice($history, -$max);
        }

        // Re-index
        $history = array_values($history);
        update_option(self::OPT, $history, false);

        // Schedule deferred health check (60 seconds from now)
        if (!wp_next_scheduled(self::HEALTH_HOOK)) {
            wp_schedule_single_event(time() + 60, self::HEALTH_HOOK);
        }
    }

    private static function record_plugin_updates(array $options): array {
        $entries = [];
        $items = [];

        if (!empty($options['plugins']) && is_array($options['plugins'])) {
            $items = $options['plugins'];
        } elseif (!empty($options['plugin'])) {
            $items = [(string) $options['plugin']];
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();

        foreach ($items as $file) {
            $file = (string) $file;
            $data = $all_plugins[$file] ?? [];
            $entries[] = [
                'type'   => 'plugin',
                'slug'   => $file,
                'name'   => $data['Name'] ?? dirname($file),
                'from'   => '', // WP doesn't reliably expose the old version in this hook
                'to'     => $data['Version'] ?? '',
                'at'     => time(),
                'user'   => get_current_user_id(),
            ];
        }

        return $entries;
    }

    private static function record_theme_updates(array $options): array {
        $entries = [];
        $items = [];

        if (!empty($options['themes']) && is_array($options['themes'])) {
            $items = $options['themes'];
        } elseif (!empty($options['theme'])) {
            $items = [(string) $options['theme']];
        }

        foreach ($items as $slug) {
            $slug  = (string) $slug;
            $theme = wp_get_theme($slug);
            $entries[] = [
                'type'   => 'theme',
                'slug'   => 'theme/' . $slug,
                'name'   => $theme->exists() ? $theme->get('Name') : $slug,
                'from'   => '',
                'to'     => $theme->exists() ? $theme->get('Version') : '',
                'at'     => time(),
                'user'   => get_current_user_id(),
            ];
        }

        return $entries;
    }

    private static function record_core_update(): array {
        return [[
            'type'   => 'core',
            'slug'   => 'wordpress',
            'name'   => 'WordPress',
            'from'   => '',
            'to'     => get_bloginfo('version'),
            'at'     => time(),
            'user'   => get_current_user_id(),
        ]];
    }

    // -------------------------------------------------------------------------
    // Deferred health check
    // -------------------------------------------------------------------------

    /**
     * Runs ~60 seconds after an update completes. Performs lightweight checks
     * and updates the most recent history entries with health data.
     */
    public static function deferred_health_check(): void {
        $history = self::all();
        if (empty($history)) return;

        // Find entries that don't have health data yet (just recorded)
        $unchecked = [];
        foreach ($history as $i => $entry) {
            if ($entry['health'] === null) {
                $unchecked[] = $i;
            }
        }

        if (empty($unchecked)) return;

        // Run the health checks once
        $health = self::run_health_checks();

        // Count error log lines now
        $error_lines_after = self::count_error_log_lines();

        // Apply to all unchecked entries
        $any_issues = false;
        foreach ($unchecked as $i) {
            $lines_before = $history[$i]['error_lines_before'] ?? 0;
            $new_errors   = max(0, $error_lines_after - $lines_before);

            $entry_health = [
                'status'        => 'ok',
                'loopback'      => $health['loopback'],
                'admin_ok'      => $health['admin_ok'],
                'rest_ok'       => $health['rest_ok'] ?? true,
                'singular_ok'   => $health['singular_ok'] ?? true,
                'ecommerce_ok'  => $health['ecommerce_ok'] ?? null,
                'new_errors'    => $new_errors,
                'cron_ok'       => $health['cron_ok'],
                'checked_at'    => time(),
            ];

            // Determine if there's a problem
            $problems = [];
            if (!$health['loopback']) $problems[] = __('Site homepage returned 500 error or failed to load', 'phpinfo-wp');
            if (!$health['admin_ok']) $problems[] = __('Admin dashboard unreachable', 'phpinfo-wp');
            if (isset($health['rest_ok']) && !$health['rest_ok']) $problems[] = __('REST API (/wp-json/) returned 500 error', 'phpinfo-wp');
            if (isset($health['singular_ok']) && !$health['singular_ok']) $problems[] = __('Singular page/post template failed to render', 'phpinfo-wp');
            if (isset($health['ecommerce_ok']) && $health['ecommerce_ok'] === false) $problems[] = __('E-commerce shop/checkout endpoint returned 500 error', 'phpinfo-wp');
            if ($new_errors > 0)      $problems[] = sprintf(__('%d new error(s) in log', 'phpinfo-wp'), $new_errors);
            if (!$health['cron_ok'])  $problems[] = __('WP-Cron events may be disrupted', 'phpinfo-wp');

            if (!empty($problems)) {
                $entry_health['status']   = 'issues';
                $entry_health['problems'] = $problems;
                $any_issues = true;
            }

            $history[$i]['health'] = $entry_health;
            // Clean up temp field
            unset($history[$i]['error_lines_before']);
        }

        update_option(self::OPT, array_values($history), false);

        // Surface alert if issues detected
        if ($any_issues) {
            $alert_data = [];
            foreach ($unchecked as $i) {
                if (($history[$i]['health']['status'] ?? '') === 'issues') {
                    $alert_data[] = [
                        'name'     => $history[$i]['name'] ?? '',
                        'to'       => $history[$i]['to'] ?? '',
                        'problems' => $history[$i]['health']['problems'] ?? [],
                    ];
                }
            }
            set_transient(self::OPT_HEALTH_ALERT, $alert_data, 3 * DAY_IN_SECONDS);

            // Auto-rollback if enabled and site is critically broken (500 / unreachable admin)
            $allow_auto = apply_filters('phpinfowp_ug_enable_auto_rollback', (bool) get_option('phpinfowp_ug_auto_rollback', false));
            if ($allow_auto && (!$health['loopback'] || !$health['admin_ok'])) {
                $snapshots = self::get_snapshots();
                foreach ($unchecked as $i) {
                    $item_slug = $history[$i]['slug'] ?? '';
                    if (($history[$i]['type'] ?? '') === 'plugin' && !empty($snapshots[$item_slug])) {
                        self::rollback_plugin($item_slug, __('Critical failure detected in post-update health check (site unreachable / loopback failed)', 'phpinfo-wp'));
                    }
                }
            }

            // Pro: send email/webhook if alerts are enabled
            if (self::_pro() && class_exists('Phpinfo_WP_Alerts')) {
                $settings = Phpinfo_WP_Alerts::get_settings();
                if (!empty($settings['enabled'])) {
                    $subject = __('Post-update health issues detected', 'phpinfo-wp');
                    $body    = __('Update Guard detected issues after recent updates:', 'phpinfo-wp') . "\n\n";
                    foreach ($alert_data as $ad) {
                        $body .= '• ' . $ad['name'] . ' → ' . $ad['to'] . "\n";
                        foreach ($ad['problems'] as $p) {
                            $body .= '  - ' . $p . "\n";
                        }
                    }
                    $body .= "\n" . __('Review:', 'phpinfo-wp') . ' ' . admin_url('admin.php?page=piwp-update-audit');

                    Phpinfo_WP_Alerts::send($subject, $body);
                }
            }
        }
    }

    /**
     * Run a live diagnostic health check on demand.
     */
    public static function run_live_health_check(): array {
        $health = self::run_health_checks();
        $error_path = class_exists('Phpinfo_WP_Error_Log') ? Phpinfo_WP_Error_Log::find_path() : null;
        $error_lines = self::count_error_log_lines();
        $data = [
            'loopback'     => $health['loopback'],
            'admin_ok'     => $health['admin_ok'],
            'rest_ok'      => $health['rest_ok'] ?? true,
            'singular_ok'  => $health['singular_ok'] ?? true,
            'ecommerce_ok' => $health['ecommerce_ok'] ?? null,
            'cron_ok'      => $health['cron_ok'],
            'error_log'    => $error_path ? true : false,
            'error_lines'  => $error_lines,
            'checked_at'   => time(),
        ];
        update_option('phpinfowp_ug_latest_health', $data, false);
        return $data;
    }

    /**
     * Get the last run live health check result.
     */
    public static function get_latest_health(): ?array {
        $data = get_option('phpinfowp_ug_latest_health', null);
        return is_array($data) ? $data : null;
    }

    /**
     * Run multi-endpoint health checks.
     * Tests:
     *   1. Front-end homepage (loopback)
     *   2. Admin dashboard (wp-admin)
     *   3. REST API (/wp-json/)
     *   4. Singular template (single post/page)
     *   5. E-commerce checkout/shop (if WooCommerce or EDD active)
     *   6. WP-Cron scheduled integrity
     */
    private static function run_health_checks(): array {
        $result = [
            'loopback'     => true,
            'admin_ok'     => true,
            'rest_ok'      => true,
            'singular_ok'  => true,
            'ecommerce_ok' => null,
            'cron_ok'      => true,
        ];

        $ua = 'phpinfo-wp Update Guard Multi-Endpoint Diagnostic';

        // 1. Loopback: does the homepage return 200/non-500?
        $resp = wp_remote_get(home_url('/'), [
            'timeout'    => 10,
            'sslverify'  => false,
            'user-agent' => $ua,
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) >= 500) {
            $result['loopback'] = false;
        }

        // 2. Admin reachable: does wp-admin return 200 or 302 (login redirect)?
        $resp = wp_remote_get(admin_url('/'), [
            'timeout'     => 10,
            'sslverify'   => false,
            'user-agent'  => $ua,
            'redirection' => 0, // don't follow redirects
        ]);
        if (is_wp_error($resp)) {
            $result['admin_ok'] = false;
        } else {
            $code = wp_remote_retrieve_response_code($resp);
            $result['admin_ok'] = $code < 500;
        }

        // 3. REST API: does /wp-json/ return valid 200 JSON without fatal error?
        if (function_exists('rest_url')) {
            $rest_resp = wp_remote_get(rest_url('/'), [
                'timeout'    => 8,
                'sslverify'  => false,
                'user-agent' => $ua,
            ]);
            if (is_wp_error($rest_resp) || wp_remote_retrieve_response_code($rest_resp) >= 500) {
                $result['rest_ok'] = false;
            }
        }

        // 4. Singular content: does a real single post/page template render?
        $sample_posts = get_posts([
            'numberposts' => 1,
            'post_status' => 'publish',
            'post_type'   => ['post', 'page'],
        ]);
        if (!empty($sample_posts) && isset($sample_posts[0]->ID)) {
            $single_url = get_permalink($sample_posts[0]->ID);
            if ($single_url) {
                $sing_resp = wp_remote_get($single_url, [
                    'timeout'    => 8,
                    'sslverify'  => false,
                    'user-agent' => $ua,
                ]);
                if (is_wp_error($sing_resp) || wp_remote_retrieve_response_code($sing_resp) >= 500) {
                    $result['singular_ok'] = false;
                }
            }
        }

        // 5. E-Commerce check: test shop/checkout if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : null;
            if (!$checkout_url && function_exists('wc_get_page_permalink')) {
                $checkout_url = wc_get_page_permalink('shop');
            }
            if ($checkout_url) {
                $ecom_resp = wp_remote_get($checkout_url, [
                    'timeout'    => 8,
                    'sslverify'  => false,
                    'user-agent' => $ua,
                ]);
                $result['ecommerce_ok'] = !(is_wp_error($ecom_resp) || wp_remote_retrieve_response_code($ecom_resp) >= 500);
            }
        }

        // 6. Cron integrity: are core events still scheduled?
        $cron_array = _get_cron_array();
        if (!is_array($cron_array) || empty($cron_array)) {
            $result['cron_ok'] = false;
        } else {
            $has_core_event = false;
            foreach ($cron_array as $ts => $hooks) {
                if (!is_array($hooks)) continue;
                foreach ($hooks as $hook => $_) {
                    if (in_array($hook, ['wp_version_check', 'wp_update_plugins', 'wp_update_themes'], true)) {
                        $has_core_event = true;
                        break 2;
                    }
                }
            }
            $result['cron_ok'] = $has_core_event;
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Error log inspection
    // -------------------------------------------------------------------------

    /**
     * Count the number of lines in the PHP error log. We compare before/after
     * to detect new errors added during the update window.
     */
    private static function count_error_log_lines(): int {
        if (!class_exists('Phpinfo_WP_Error_Log')) return 0;

        $path = Phpinfo_WP_Error_Log::find_path();
        if (!$path || !is_readable($path)) return 0;

        // For large files, count efficiently without reading entire file
        $size = @filesize($path);
        if ($size === false || $size === 0) return 0;

        // Fast line count: read last 1MB max and count newlines
        $fp = @fopen($path, 'rb');
        if (!$fp) return 0;

        $count = 0;
        $chunk_size = 65536;
        if ($size > 1048576) {
            // Large file: count only from the last 1MB
            fseek($fp, -1048576, SEEK_END);
        }
        while (!feof($fp)) {
            $chunk = fread($fp, $chunk_size);
            if ($chunk === false) break;
            $count += substr_count($chunk, "\n");
        }
        fclose($fp);
        return $count;
    }

    // -------------------------------------------------------------------------
    // Admin notice for health issues
    // -------------------------------------------------------------------------

    /**
     * Display an admin notice when the deferred health check found issues.
     * The transient auto-expires after 3 days, or is dismissed manually.
     */
    public static function maybe_show_health_alert(): void {
        if (!current_user_can('update_plugins')) return;

        $notice = get_transient('phpinfowp_ug_admin_notice');
        if ($notice && is_array($notice)) {
            $class = !empty($notice['success']) ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible piwp-notice">';
            echo '<p><strong>' . esc_html__('Update Guard Rollback Notice:', 'phpinfo-wp') . '</strong> ';
            if (!empty($notice['success'])) {
                printf(esc_html__('Plugin "%s" was successfully rolled back to its previous version. Note: Only files were restored; database migrations (if any) remain unchanged.', 'phpinfo-wp'), esc_html($notice['slug']));
            } else {
                printf(esc_html__('Rollback failed for plugin "%s". Reason: %s', 'phpinfo-wp'), esc_html($notice['slug']), esc_html($notice['reason']));
            }
            echo '</p></div>';
            delete_transient('phpinfowp_ug_admin_notice');
        }

        // Handle dismissal
        if (isset($_GET['phpinfowp_dismiss_health']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'phpinfowp_dismiss_health')) {
            delete_transient(self::OPT_HEALTH_ALERT);
            return;
        }

        $alerts = get_transient(self::OPT_HEALTH_ALERT);
        if (!is_array($alerts) || empty($alerts)) return;

        $dismiss_url = wp_nonce_url(
            add_query_arg('phpinfowp_dismiss_health', '1'),
            'phpinfowp_dismiss_health'
        );
        $detail_url = admin_url('admin.php?page=piwp-update-audit');

        echo '<div class="notice notice-warning is-dismissible piwp-notice">';
        echo '<p><strong>' . esc_html__('Update Guard — Post-Update Health Check:', 'phpinfo-wp') . '</strong></p>';
        echo '<ul style="margin:0 0 8px 16px;list-style:disc">';
        foreach ($alerts as $a) {
            echo '<li>';
            echo '<strong>' . esc_html($a['name']) . '</strong>';
            if ($a['to']) echo ' → ' . esc_html($a['to']);
            if (!empty($a['problems'])) {
                echo ': ' . esc_html(implode(', ', $a['problems']));
            }
            echo '</li>';
        }
        echo '</ul>';
        printf(
            '<p><a href="%s" class="button button-small">%s</a> <a href="%s" style="margin-left:8px">%s</a></p>',
            esc_url($detail_url),
            esc_html__('View Full Report', 'phpinfo-wp'),
            esc_url($dismiss_url),
            esc_html__('Dismiss', 'phpinfo-wp')
        );
        echo '</div>';
    }

    // -------------------------------------------------------------------------
    // Data accessors
    // -------------------------------------------------------------------------

    /** Get all update history entries. */
    public static function all(): array {
        $h = get_option(self::OPT, []);
        return is_array($h) ? $h : [];
    }

    /** Get history for a specific slug (plugin file path or 'theme/slug' or 'wordpress'). */
    public static function for_slug(string $slug): array {
        return array_values(array_filter(self::all(), function ($entry) use ($slug) {
            return ($entry['slug'] ?? '') === $slug;
        }));
    }

    /**
     * Stability score for a slug (0–100).
     * 100 = all past updates were healthy. Each issue subtracts 20 points.
     * No history = 50 (neutral/unknown).
     */
    public static function stability_score(string $slug): int {
        $entries = self::for_slug($slug);
        if (empty($entries)) return 50; // no data = neutral

        // Look at the last 5 entries
        $recent = array_slice($entries, -5);
        $score  = 100;
        foreach ($recent as $entry) {
            $health = $entry['health'] ?? null;
            if ($health === null) continue; // unchecked yet
            if (($health['status'] ?? 'ok') !== 'ok') {
                $score -= 20;
            }
        }

        return max(0, $score);
    }

    /**
     * Get the last N update events (newest first). For the view.
     */
    public static function recent(int $limit = 20): array {
        $history = self::all();
        $history = array_reverse($history);
        return array_slice($history, 0, $limit);
    }

    /**
     * Initialize secure backup directory for pre-install snapshots.
     */
    public static function init_backup_dir(): string {
        $upload_dir = wp_upload_dir();
        $dir        = trailingslashit($upload_dir['basedir']) . 'piwp-update-guard-backups';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
            @file_put_contents(trailingslashit($dir) . '.htaccess', "Deny from all\n");
            @file_put_contents(trailingslashit($dir) . 'index.php', "<?php // Silence is golden.\n");
        }
        return $dir;
    }

    /**
     * Hooked to upgrader_pre_install. Creates a timestamped ZipArchive snapshot
     * of the existing plugin directory before core replaces it.
     */
    public static function pre_install_snapshot($response, $hook_extra) {
        if (empty($hook_extra['plugin'])) return $response;

        $plugin_file = $hook_extra['plugin'];
        $slug        = strpos($plugin_file, '/') !== false ? dirname($plugin_file) : basename($plugin_file, '.php');
        $plugin_dir  = trailingslashit(WP_PLUGIN_DIR) . $slug;

        if (!is_dir($plugin_dir) || !class_exists('ZipArchive')) return $response;

        $backup_dir  = self::init_backup_dir();
        $backup_path = trailingslashit($backup_dir) . sprintf('%s-%s.zip', sanitize_file_name($slug), time());
        $zip         = new ZipArchive();

        if ($zip->open($backup_path, ZipArchive::CREATE) !== true) return $response;

        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $file) {
                if (!$file->isFile()) continue;
                $abs = $file->getPathname();
                $local_name = 'plugin/' . substr($abs, strlen($plugin_dir) + 1);
                $zip->addFile($abs, $local_name);
            }
            $zip->close();

            $from_ver = null;
            if (function_exists('get_plugin_data') && file_exists(trailingslashit(WP_PLUGIN_DIR) . $plugin_file)) {
                $pdata = get_plugin_data(trailingslashit(WP_PLUGIN_DIR) . $plugin_file, false, false);
                $from_ver = $pdata['Version'] ?? null;
            }

            $snapshots = get_option(self::OPT_SNAPSHOTS, []);
            if (!is_array($snapshots)) $snapshots = [];
            $snapshots[$slug] = [
                'path'         => $backup_path,
                'time'         => time(),
                'from_version' => $from_ver,
                'plugin_file'  => $plugin_file,
                'slug'         => $slug,
            ];
            update_option(self::OPT_SNAPSHOTS, $snapshots, false);
        } catch (Throwable $e) {
            // Never break standard update process on snapshot error
        }

        return $response;
    }

    /**
     * Restore a plugin from its latest snapshot archive.
     */
    public static function rollback_plugin(string $slug, string $reason = 'Manual rollback requested'): bool {
        $snapshots = get_option(self::OPT_SNAPSHOTS, []);
        if (empty($snapshots[$slug]['path']) || !file_exists($snapshots[$slug]['path'])) {
            self::log_rollback_event($slug, 'rollback_failed', 'No snapshot available: ' . $reason);
            self::notify_admin_rollback($slug, $reason, false);
            return false;
        }

        if (!class_exists('ZipArchive')) {
            self::log_rollback_event($slug, 'rollback_failed', 'ZipArchive unavailable on server');
            self::notify_admin_rollback($slug, $reason, false);
            return false;
        }

        $plugin_file = $snapshots[$slug]['plugin_file'] ?? ($slug . '/' . $slug . '.php');
        $plugin_dir  = trailingslashit(WP_PLUGIN_DIR) . $slug;

        $was_active = is_plugin_active($plugin_file);
        if ($was_active) {
            deactivate_plugins($plugin_file, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($snapshots[$slug]['path']) !== true) {
            self::log_rollback_event($slug, 'rollback_failed', 'Could not open backup archive');
            self::notify_admin_rollback($slug, $reason, false);
            return false;
        }

        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $tmp_restore = trailingslashit(WP_PLUGIN_DIR) . 'tmp-restore-' . $slug;
        $zip->extractTo($tmp_restore);
        $zip->close();

        if (is_dir($plugin_dir)) {
            $wp_filesystem->delete($plugin_dir, true);
        }

        $source = trailingslashit($tmp_restore) . 'plugin';
        if (is_dir($source)) {
            $wp_filesystem->move($source, $plugin_dir, true);
        } else {
            $wp_filesystem->move($tmp_restore, $plugin_dir, true);
        }
        $wp_filesystem->delete($tmp_restore, true);

        if ($was_active && file_exists(trailingslashit(WP_PLUGIN_DIR) . $plugin_file)) {
            activate_plugin($plugin_file, '', false, true);
        }

        $ver = $snapshots[$slug]['from_version'] ?? '';
        $detail = $reason . ($ver ? ' — restored to v' . $ver : '');
        self::log_rollback_event($slug, 'rollback_success', $detail);
        self::notify_admin_rollback($slug, $reason, true);

        return true;
    }

    /**
     * AJAX handler for 1-click rollback from the admin dashboard.
     */
    public static function ajax_rollback(): void {
        check_ajax_referer('phpinfowp_uh_nonce', 'nonce');
        if (!current_user_can('update_plugins')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        $slug = isset($_POST['slug']) ? sanitize_key($_POST['slug']) : '';
        if (!$slug) {
            wp_send_json_error(['message' => __('Invalid plugin slug.', 'phpinfo-wp')]);
        }

        $ok = self::rollback_plugin($slug, __('Manual 1-click rollback requested from dashboard', 'phpinfo-wp'));
        if ($ok) {
            wp_send_json_success(['message' => sprintf(__('Successfully rolled back %s.', 'phpinfo-wp'), esc_html($slug))]);
        } else {
            wp_send_json_error(['message' => sprintf(__('Failed to rollback %s. Check logs.', 'phpinfo-wp'), esc_html($slug))]);
        }
    }

    public static function get_snapshots(): array {
        $s = get_option(self::OPT_SNAPSHOTS, []);
        return is_array($s) ? $s : [];
    }

    public static function get_rollback_log(): array {
        $log = get_option(self::OPT_ROLLBACK_LOG, []);
        return is_array($log) ? $log : [];
    }

    public static function log_rollback_event(string $slug, string $event, string $detail): void {
        $log = self::get_rollback_log();
        array_unshift($log, [
            'time'   => current_time('mysql'),
            'slug'   => $slug,
            'event'  => $event,
            'detail' => $detail,
        ]);
        $log = array_slice($log, 0, 50);
        update_option(self::OPT_ROLLBACK_LOG, $log, false);
    }

    private static function notify_admin_rollback(string $slug, string $reason, bool $success): void {
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $subject = $success
                ? sprintf(__('[phpinfo() WP] Auto-rollback executed for %s', 'phpinfo-wp'), $slug)
                : sprintf(__('[phpinfo() WP] Update problem detected for %s — rollback failed', 'phpinfo-wp'), $slug);
            $body = $success
                ? sprintf("The update to \"%s\" caused an issue and was rolled back automatically.\n\nReason: %s\n\nNote: Files have been restored to the previous version. If the update executed database migrations, those require manual review.", $slug, $reason)
                : sprintf("A problem was detected after updating \"%s\", but automatic rollback could not complete.\n\nReason: %s\n\nPlease check your site immediately.", $slug, $reason);
            @wp_mail($admin_email, $subject, $body);
        }

        set_transient('phpinfowp_ug_admin_notice', [
            'slug'    => $slug,
            'reason'  => $reason,
            'success' => $success,
            'time'    => time(),
        ], DAY_IN_SECONDS);
    }

    /**
     * Clear all history and rollback logs.
     */
    public static function clear(): void {
        delete_option(self::OPT);
        delete_transient(self::OPT_HEALTH_ALERT);
        delete_option('phpinfowp_ug_latest_health');
        delete_option(self::OPT_ROLLBACK_LOG);
    }
}
