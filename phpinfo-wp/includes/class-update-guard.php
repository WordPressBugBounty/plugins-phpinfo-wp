<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Plugin/Theme Update Guard — pre-update compatibility scanner for individual
 * plugin and theme updates (not just core).
 *
 * Where Phpinfo_WP_Update_Audit answers "will my code survive a core upgrade?",
 * this class answers "will THIS specific plugin/theme update break my site?"
 *
 * Signals per pending update:
 *   1. PHP floor — new version needs a PHP the site doesn't have
 *   2. WP floor  — new version needs a WP the site doesn't run
 *   3. Major version jump — e.g. 8.x→9.x carries more risk than 9.1→9.2
 *   4. Changelog risk keywords — "breaking", "removed", "deprecated" etc.  (Pro)
 *   5. Abandonment / closure on WP.org                                     (Pro)
 *   6. Cross-dependency — Requires Plugins header references missing items  (Pro)
 *   7. Stability history — past update track record on THIS site           (Pro)
 *
 * Free shows signals 1-3 + overall verdict.
 * Pro  adds 4-7 + AI explanations + email/webhook alerts.
 */
class Phpinfo_WP_Update_Guard {

    const OPT_RESULT       = 'phpinfowp_update_guard_result';
    const TRANSIENT_DETAIL = 'phpinfowp_ug_detail_';  // + md5(slug)
    const META_TTL         = 12 * HOUR_IN_SECONDS;
    const MAX_CHANGELOG    = 8000;  // max bytes of changelog to parse
    const RISK_KEYWORDS    = [
        'high'   => ['breaking change', 'removed', 'drops support', 'no longer support',
                      'backwards-incompatible', 'backward-incompatible', 'fatal'],
        'medium' => ['deprecated', 'minimum php', 'minimum wordpress', 'requires php',
                      'requires wordpress', 'major update', 'migration required',
                      'database migration', 'schema change', 'action required'],
        'low'    => ['experimental', 'beta', 'alpha', 'release candidate', 'important'],
    ];

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Scan all pending plugin + theme updates. Returns a structured result
     * array stored in OPT_RESULT and returned for immediate display.
     */
    public static function scan(): array {
        @set_time_limit(120);
        $started = microtime(true);

        $site_php = PHP_VERSION;
        $site_wp  = get_bloginfo('version');
        $is_pro   = self::_pro();

        // Gather pending updates from WP transients
        $plugin_updates = self::pending_plugin_updates();
        $theme_updates  = self::pending_theme_updates();

        $items = [];

        foreach ($plugin_updates as $file => $update) {
            $items[] = self::score_plugin($update, $file, $site_php, $site_wp, $is_pro);
        }

        foreach ($theme_updates as $slug => $update) {
            $items[] = self::score_theme($update, $slug, $site_php, $site_wp, $is_pro);
        }

        // Sort: risky first, then caution, then safe
        $rank = ['risky' => 0, 'caution' => 1, 'safe' => 2];
        usort($items, function ($a, $b) use ($rank) {
            $r = ($rank[$a['verdict']] ?? 3) <=> ($rank[$b['verdict']] ?? 3);
            return $r !== 0 ? $r : count($b['signals']) <=> count($a['signals']);
        });

        $overall = 'safe';
        foreach ($items as $item) {
            if ($item['verdict'] === 'risky')  { $overall = 'risky'; break; }
            if ($item['verdict'] === 'caution') { $overall = 'caution'; }
        }

        $result = [
            'verdict'     => $overall,
            'items'       => $items,
            'total'       => count($items),
            'risky_count' => count(array_filter($items, function ($i) { return $i['verdict'] === 'risky'; })),
            'caution_count' => count(array_filter($items, function ($i) { return $i['verdict'] === 'caution'; })),
            'safe_count'  => count(array_filter($items, function ($i) { return $i['verdict'] === 'safe'; })),
            'duration'    => round(microtime(true) - $started, 2),
            'scanned_at'  => time(),
            'is_pro'      => $is_pro,
        ];

        update_option(self::OPT_RESULT, $result, false);
        return $result;
    }

    /** Get cached scan result. */
    public static function get_result(): ?array {
        $r = get_option(self::OPT_RESULT, null);
        return is_array($r) ? $r : null;
    }

    /** Clear cached result. */
    public static function clear(): void {
        delete_option(self::OPT_RESULT);
    }

    /** Count pending updates (plugins + themes). Lightweight — no API calls. */
    public static function pending_count(): int {
        return count(self::pending_plugin_updates()) + count(self::pending_theme_updates());
    }

    // -------------------------------------------------------------------------
    // Pending update discovery
    // -------------------------------------------------------------------------

    private static function pending_plugin_updates(): array {
        $updates = get_site_transient('update_plugins');
        if (!is_object($updates) || empty($updates->response)) return [];
        return (array) $updates->response;
    }

    private static function pending_theme_updates(): array {
        $updates = get_site_transient('update_themes');
        if (!is_object($updates) || empty($updates->response)) return [];
        return (array) $updates->response;
    }

    // -------------------------------------------------------------------------
    // Per-item scoring
    // -------------------------------------------------------------------------

    private static function score_plugin(object $update, string $file, string $site_php, string $site_wp, bool $is_pro): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        $local       = $all_plugins[$file] ?? [];

        $slug     = $update->slug ?? dirname($file);
        $name     = $local['Name'] ?? $slug;
        $cur_ver  = $local['Version'] ?? '0';
        $new_ver  = $update->new_version ?? '0';
        $new_php  = $update->requires_php ?? ($local['RequiresPHP'] ?? '');
        $new_wp   = $update->requires ?? ($local['RequiresWP'] ?? '');
        $tested   = $update->tested ?? '';

        $signals = [];

        // 1. PHP floor
        if ($new_php && version_compare($site_php, $new_php, '<')) {
            $signals[] = [
                'type'     => 'php_floor',
                'severity' => 'risky',
                'label'    => sprintf(
                    __('Requires PHP %s — you run %s', 'phpinfo-wp'),
                    $new_php, $site_php
                ),
            ];
        }

        // 2. WP floor
        if ($new_wp && version_compare($site_wp, $new_wp, '<')) {
            $signals[] = [
                'type'     => 'wp_floor',
                'severity' => 'risky',
                'label'    => sprintf(
                    __('Requires WordPress %s — you run %s', 'phpinfo-wp'),
                    $new_wp, $site_wp
                ),
            ];
        }

        // 3. Major version jump (free)
        $jump = self::version_jump_type($cur_ver, $new_ver);
        if ($jump === 'major') {
            $signals[] = [
                'type'     => 'major_jump',
                'severity' => 'caution',
                'label'    => sprintf(
                    __('Major version jump: %s → %s', 'phpinfo-wp'),
                    $cur_ver, $new_ver
                ),
            ];
        }

        // 4. "Tested up to" gap (free)
        if ($tested && version_compare($site_wp, $tested, '>')) {
            $signals[] = [
                'type'     => 'tested_gap',
                'severity' => 'caution',
                'label'    => sprintf(
                    __('Tested up to WordPress %s (you run %s)', 'phpinfo-wp'),
                    $tested, $site_wp
                ),
            ];
        }

        // --- Pro signals ---
        if ($is_pro) {
            // 5. Changelog risk keywords
            $changelog_signals = self::parse_changelog_risk($slug);
            $signals = array_merge($signals, $changelog_signals);

            // 6. Abandonment
            $meta = self::wporg_plugin_meta($slug);
            if ($meta) {
                if (!empty($meta['closed'])) {
                    $signals[] = [
                        'type'     => 'closed',
                        'severity' => 'risky',
                        'label'    => __('Plugin is closed on WordPress.org', 'phpinfo-wp'),
                    ];
                }
                $stale_days = $meta['stale_days'] ?? null;
                if ($stale_days !== null && $stale_days > 730) {
                    $signals[] = [
                        'type'     => 'abandoned',
                        'severity' => 'risky',
                        'label'    => sprintf(
                            __('Not updated in %d months — likely abandoned', 'phpinfo-wp'),
                            (int) round($stale_days / 30)
                        ),
                    ];
                }
            }

            // 7. Cross-dependency check (WP 6.5+ Requires Plugins header)
            $requires_plugins = $update->requires_plugins ?? '';
            if ($requires_plugins) {
                $deps = array_filter(array_map('trim', explode(',', $requires_plugins)));
                foreach ($deps as $dep_slug) {
                    if (!self::is_plugin_active_by_slug($dep_slug)) {
                        $signals[] = [
                            'type'     => 'missing_dep',
                            'severity' => 'caution',
                            'label'    => sprintf(
                                __('Requires plugin "%s" which is not active', 'phpinfo-wp'),
                                $dep_slug
                            ),
                        ];
                    }
                }
            }

            // 8. Stability history (from Update History class)
            if (class_exists('Phpinfo_WP_Update_History')) {
                $history = Phpinfo_WP_Update_History::for_slug($file);
                $last_bad = 0;
                foreach (array_slice($history, -5) as $h) {
                    if (!empty($h['health']) && ($h['health']['status'] ?? '') !== 'ok') {
                        $last_bad++;
                    }
                }
                if ($last_bad > 0) {
                    $signals[] = [
                        'type'     => 'history_issues',
                        'severity' => 'caution',
                        'label'    => sprintf(
                            __('Past updates: %d of last %d had issues on this site', 'phpinfo-wp'),
                            $last_bad, min(5, count($history))
                        ),
                    ];
                }
            }
        }

        // Verdict
        $verdict = 'safe';
        foreach ($signals as $s) {
            if ($s['severity'] === 'risky')  { $verdict = 'risky'; break; }
            if ($s['severity'] === 'caution') { $verdict = 'caution'; }
        }

        return [
            'type'     => 'plugin',
            'slug'     => $slug,
            'file'     => $file,
            'name'     => $name,
            'from'     => $cur_ver,
            'to'       => $new_ver,
            'verdict'  => $verdict,
            'signals'  => $signals,
        ];
    }

    private static function score_theme(array $update, string $slug, string $site_php, string $site_wp, bool $is_pro): array {
        $theme   = wp_get_theme($slug);
        $name    = $theme->exists() ? $theme->get('Name') : $slug;
        $cur_ver = $theme->exists() ? $theme->get('Version') : '0';
        $new_ver = $update['new_version'] ?? '0';
        $new_php = $update['requires_php'] ?? '';
        $new_wp  = $update['requires']     ?? '';

        $signals = [];

        // 1. PHP floor
        if ($new_php && version_compare($site_php, $new_php, '<')) {
            $signals[] = [
                'type'     => 'php_floor',
                'severity' => 'risky',
                'label'    => sprintf(
                    __('Requires PHP %s — you run %s', 'phpinfo-wp'),
                    $new_php, $site_php
                ),
            ];
        }

        // 2. WP floor
        if ($new_wp && version_compare($site_wp, $new_wp, '<')) {
            $signals[] = [
                'type'     => 'wp_floor',
                'severity' => 'risky',
                'label'    => sprintf(
                    __('Requires WordPress %s — you run %s', 'phpinfo-wp'),
                    $new_wp, $site_wp
                ),
            ];
        }

        // 3. Major version jump
        $jump = self::version_jump_type($cur_ver, $new_ver);
        if ($jump === 'major') {
            $signals[] = [
                'type'     => 'major_jump',
                'severity' => 'caution',
                'label'    => sprintf(
                    __('Major version jump: %s → %s', 'phpinfo-wp'),
                    $cur_ver, $new_ver
                ),
            ];
        }

        // Pro signals for themes
        if ($is_pro) {
            $changelog_signals = self::parse_changelog_risk_theme($slug);
            $signals = array_merge($signals, $changelog_signals);

            $meta = self::wporg_theme_meta($slug);
            if ($meta) {
                $stale_days = $meta['stale_days'] ?? null;
                if ($stale_days !== null && $stale_days > 730) {
                    $signals[] = [
                        'type'     => 'abandoned',
                        'severity' => 'risky',
                        'label'    => sprintf(
                            __('Not updated in %d months — likely abandoned', 'phpinfo-wp'),
                            (int) round($stale_days / 30)
                        ),
                    ];
                }
            }

            // Stability history
            if (class_exists('Phpinfo_WP_Update_History')) {
                $history = Phpinfo_WP_Update_History::for_slug('theme/' . $slug);
                $last_bad = 0;
                foreach (array_slice($history, -5) as $h) {
                    if (!empty($h['health']) && ($h['health']['status'] ?? '') !== 'ok') {
                        $last_bad++;
                    }
                }
                if ($last_bad > 0) {
                    $signals[] = [
                        'type'     => 'history_issues',
                        'severity' => 'caution',
                        'label'    => sprintf(
                            __('Past updates: %d of last %d had issues on this site', 'phpinfo-wp'),
                            $last_bad, min(5, count($history))
                        ),
                    ];
                }
            }
        }

        $verdict = 'safe';
        foreach ($signals as $s) {
            if ($s['severity'] === 'risky')  { $verdict = 'risky'; break; }
            if ($s['severity'] === 'caution') { $verdict = 'caution'; }
        }

        return [
            'type'     => 'theme',
            'slug'     => $slug,
            'file'     => $slug,
            'name'     => $name,
            'from'     => $cur_ver,
            'to'       => $new_ver,
            'verdict'  => $verdict,
            'signals'  => $signals,
        ];
    }

    // -------------------------------------------------------------------------
    // Changelog risk parsing (Pro)
    // -------------------------------------------------------------------------

    /**
     * Fetch the latest changelog section from WP.org plugin API and scan for
     * risk keywords. Returns an array of signal entries.
     */
    private static function parse_changelog_risk(string $slug): array {
        $cached = get_transient(self::TRANSIENT_DETAIL . md5('cl:' . $slug));
        if (is_array($cached)) return $cached;

        $url = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information'
             . '&request[slug]=' . rawurlencode($slug)
             . '&request[fields][sections]=1';

        $resp = wp_remote_get($url, ['timeout' => 6]);
        $signals = [];

        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            $changelog = $body['sections']['changelog'] ?? '';
            if ($changelog) {
                $signals = self::scan_changelog_text($changelog);
            }
        }

        set_transient(self::TRANSIENT_DETAIL . md5('cl:' . $slug), $signals, self::META_TTL);
        return $signals;
    }

    /** Theme changelog from WP.org. */
    private static function parse_changelog_risk_theme(string $slug): array {
        $cached = get_transient(self::TRANSIENT_DETAIL . md5('tcl:' . $slug));
        if (is_array($cached)) return $cached;

        $url = 'https://api.wordpress.org/themes/info/1.2/?action=theme_information'
             . '&request[slug]=' . rawurlencode($slug)
             . '&request[fields][sections]=1';

        $resp = wp_remote_get($url, ['timeout' => 6]);
        $signals = [];

        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            $changelog = $body['sections']['changelog'] ?? '';
            if ($changelog) {
                $signals = self::scan_changelog_text($changelog);
            }
        }

        set_transient(self::TRANSIENT_DETAIL . md5('tcl:' . $slug), $signals, self::META_TTL);
        return $signals;
    }

    /**
     * Parse a changelog HTML string for risk keywords. Only examines the
     * latest version block (the first <h4> or list section) to avoid stale
     * warnings from years-old entries.
     */
    private static function scan_changelog_text(string $html): array {
        // Strip HTML, truncate to first section
        $text = wp_strip_all_tags($html);
        $text = substr($text, 0, self::MAX_CHANGELOG);

        // Try to isolate the latest version section: first h4/h3 block
        // Format is typically: "= 9.3.0 =" or "<h4>9.3.0</h4>" followed by a list.
        // We grab everything up to the second version header.
        if (preg_match('/^(.*?)(?:=\s*\d+\.\d+)/s', $text, $m, 0, 10)) {
            // Content from start to second version header
            $latest_section = substr($text, 0, strpos($text, $m[0], 10) ?: strlen($text));
        } else {
            $latest_section = substr($text, 0, 2000); // fallback: first 2000 chars
        }

        $lower = strtolower($latest_section);
        $signals = [];
        $found = [];

        foreach (self::RISK_KEYWORDS as $level => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($lower, $kw) !== false && !isset($found[$kw])) {
                    $found[$kw] = true;
                    $severity = ($level === 'high') ? 'caution' : 'info';
                    // Don't double-promote to 'risky' from changelog alone — it's
                    // a text signal, not a hard compatibility wall.
                    $signals[] = [
                        'type'     => 'changelog_' . $level,
                        'severity' => $severity,
                        'label'    => sprintf(
                            __('Changelog mentions: "%s"', 'phpinfo-wp'),
                            $kw
                        ),
                    ];
                }
            }
        }

        return $signals;
    }

    // -------------------------------------------------------------------------
    // WP.org metadata (Pro)
    // -------------------------------------------------------------------------

    private static function wporg_plugin_meta(string $slug): ?array {
        $cached = get_transient(self::TRANSIENT_DETAIL . md5('pm:' . $slug));
        if (is_array($cached)) return $cached ?: null;

        $url = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information'
             . '&request[slug]=' . rawurlencode($slug)
             . '&request[fields][last_updated]=1';

        $resp = wp_remote_get($url, ['timeout' => 5]);
        $meta = null;

        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            if (is_array($body) && !isset($body['error'])) {
                $updated = $body['last_updated'] ?? '';
                $meta = [
                    'last_updated' => $updated,
                    'stale_days'   => $updated ? (int) floor((time() - strtotime($updated)) / DAY_IN_SECONDS) : null,
                    'closed'       => !empty($body['closed']),
                ];
            }
        }

        set_transient(self::TRANSIENT_DETAIL . md5('pm:' . $slug), $meta ?: [], self::META_TTL);
        return $meta;
    }

    private static function wporg_theme_meta(string $slug): ?array {
        $cached = get_transient(self::TRANSIENT_DETAIL . md5('tm:' . $slug));
        if (is_array($cached)) return $cached ?: null;

        $url = 'https://api.wordpress.org/themes/info/1.2/?action=theme_information'
             . '&request[slug]=' . rawurlencode($slug)
             . '&request[fields][last_updated]=1';

        $resp = wp_remote_get($url, ['timeout' => 5]);
        $meta = null;

        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            if (is_array($body) && !isset($body['error'])) {
                $updated = $body['last_updated'] ?? '';
                $meta = [
                    'last_updated' => $updated,
                    'stale_days'   => $updated ? (int) floor((time() - strtotime($updated)) / DAY_IN_SECONDS) : null,
                ];
            }
        }

        set_transient(self::TRANSIENT_DETAIL . md5('tm:' . $slug), $meta ?: [], self::META_TTL);
        return $meta;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Classify a version transition.
     * Returns 'major' if the first numeric segment changes (e.g. 8.x → 9.x),
     * 'minor' for second segment change, 'patch' otherwise.
     */
    private static function version_jump_type(string $from, string $to): string {
        $fp = explode('.', $from);
        $tp = explode('.', $to);
        if (($fp[0] ?? '0') !== ($tp[0] ?? '0')) return 'major';
        if (($fp[1] ?? '0') !== ($tp[1] ?? '0')) return 'minor';
        return 'patch';
    }

    /** Check if a plugin slug is active (any file under that slug's directory). */
    private static function is_plugin_active_by_slug(string $slug): bool {
        $active = (array) get_option('active_plugins', []);
        foreach ($active as $file) {
            if (dirname($file) === $slug || pathinfo($file, PATHINFO_FILENAME) === $slug) {
                return true;
            }
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Admin integration — inject risk badges on the Updates page
    // -------------------------------------------------------------------------

    public static function register(): void {
        // Inject badge notices on the core update page
        add_action('admin_notices', [self::class, 'updates_page_badge']);
    }

    /**
     * On the wp-admin/update-core.php page, show a summary of pending update
     * risks if a scan has been run. If no scan exists, nudge the user.
     */
    public static function updates_page_badge(): void {
        if (!current_user_can('update_plugins')) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'update-core') return;

        $pending = self::pending_count();
        if ($pending === 0) return;

        $result = self::get_result();
        $audit_url = admin_url('admin.php?page=piwp-update-audit');

        if (!$result || (time() - ($result['scanned_at'] ?? 0)) > WEEK_IN_SECONDS) {
            printf(
                '<div class="notice notice-info"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
                esc_html__('Update Guard:', 'phpinfo-wp'),
                sprintf(
                    esc_html__('%d plugin/theme update(s) pending.', 'phpinfo-wp'),
                    $pending
                ),
                esc_url($audit_url),
                esc_html__('Run a pre-update compatibility scan →', 'phpinfo-wp')
            );
            return;
        }

        $v = $result['verdict'] ?? 'safe';
        if ($v === 'safe') {
            printf(
                '<div class="notice notice-success"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
                esc_html__('Update Guard:', 'phpinfo-wp'),
                sprintf(
                    esc_html__('All %d pending update(s) passed compatibility checks.', 'phpinfo-wp'),
                    (int) $result['total']
                ),
                esc_url($audit_url),
                esc_html__('View report', 'phpinfo-wp')
            );
        } else {
            $color = $v === 'risky' ? 'error' : 'warning';
            $risky = (int) ($result['risky_count'] ?? 0);
            $caution = (int) ($result['caution_count'] ?? 0);
            printf(
                '<div class="notice notice-%s"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
                esc_attr($color),
                esc_html__('Update Guard:', 'phpinfo-wp'),
                sprintf(
                    esc_html__('%d risky, %d caution among %d pending update(s).', 'phpinfo-wp'),
                    $risky, $caution, (int) $result['total']
                ),
                esc_url($audit_url),
                esc_html__('Review before updating →', 'phpinfo-wp')
            );
        }
    }
}
