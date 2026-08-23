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

    // -------------------------------------------------------------------------
    // Hook registration
    // -------------------------------------------------------------------------

    public static function register(): void {
        add_action('upgrader_process_complete', [self::class, 'on_update_complete'], 10, 2);
        add_action(self::HEALTH_HOOK,           [self::class, 'deferred_health_check']);
        add_action('admin_notices',             [self::class, 'maybe_show_health_alert']);
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
                'new_errors'    => $new_errors,
                'cron_ok'       => $health['cron_ok'],
                'checked_at'    => time(),
            ];

            // Determine if there's a problem
            $problems = [];
            if (!$health['loopback'])  $problems[] = __('Site returned non-200 response', 'phpinfo-wp');
            if (!$health['admin_ok'])  $problems[] = __('Admin dashboard unreachable', 'phpinfo-wp');
            if ($new_errors > 0)       $problems[] = sprintf(__('%d new error(s) in log', 'phpinfo-wp'), $new_errors);
            if (!$health['cron_ok'])    $problems[] = __('WP-Cron events may be disrupted', 'phpinfo-wp');

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
        return [
            'loopback'   => $health['loopback'],
            'admin_ok'   => $health['admin_ok'],
            'cron_ok'    => $health['cron_ok'],
            'error_log'  => $error_path ? true : false,
            'error_lines'=> $error_lines,
            'checked_at' => time(),
        ];
    }

    /**
     * Run the 4 health checks. Returns an associative array of booleans.
     */
    private static function run_health_checks(): array {
        $result = [
            'loopback' => true,
            'admin_ok' => true,
            'cron_ok'  => true,
        ];

        // 1. Loopback: does the site still return 200?
        $resp = wp_remote_get(home_url('/'), [
            'timeout'    => 10,
            'sslverify'  => false,
            'user-agent' => 'phpinfo-wp Update Guard Health Check',
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) >= 500) {
            $result['loopback'] = false;
        }

        // 2. Admin reachable: does wp-admin return 200 or 302 (login redirect)?
        $resp = wp_remote_get(admin_url('/'), [
            'timeout'    => 10,
            'sslverify'  => false,
            'user-agent' => 'phpinfo-wp Update Guard Health Check',
            'redirection' => 0, // don't follow redirects
        ]);
        if (is_wp_error($resp)) {
            $result['admin_ok'] = false;
        } else {
            $code = wp_remote_retrieve_response_code($resp);
            // 200 (logged in), 302 (redirect to login), 301 (force SSL) are all fine
            $result['admin_ok'] = $code < 500;
        }

        // 3. Cron integrity: are core events still scheduled?
        $cron_array = _get_cron_array();
        if (!is_array($cron_array) || empty($cron_array)) {
            $result['cron_ok'] = false;
        } else {
            // Check that at least one core event exists (wp_version_check, wp_update_plugins, etc.)
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

        echo '<div class="notice notice-warning is-dismissible">';
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
     * Clear all history.
     */
    public static function clear(): void {
        delete_option(self::OPT);
        delete_transient(self::OPT_HEALTH_ALERT);
    }
}
