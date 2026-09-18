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
    const TRANSIENT_DETAIL = 'phpinfowp_ug_detail_v3_';  // + md5(slug)
    const META_TTL         = 12 * HOUR_IN_SECONDS;
    const MAX_CHANGELOG    = 8000;  // max bytes of changelog to parse

    /** Words appearing before a risk keyword that indicate a bug fix, not a hazard. */
    const FIX_SIGNALS = [
        'fix', 'fixed', 'fixes', 'resolv', 'resolved', 'resolves', 'patch', 'patched',
        'addressed', 'corrected', 'prevent', 'prevents', 'prevented', 'no longer',
        'eliminated', 'eliminates', 'stopped', 'squash', 'squashed', 'workaround',
    ];

    /** Words/phrases indicating a genuine forward-looking risk or breaking condition. */
    const RISK_SIGNALS = [
        'may', 'might', 'could', 'if you', 'if your', 'when using', 'unless',
        'warning', 'caution', 'requires', 'before updating', 'breaking',
        'incompatible', 'will no longer work', 'users on', 'sites running',
        'action required', 'migration required', 'breaking change', 'drops support',
    ];

    /** Routine maintenance phrases that must never be treated as risks. */
    const DISCARD_PHRASES = [
        'removed unused', 'removed deprecated', 'removed old', 'removed legacy',
        'cleanup', 'code cleanup', 'refactor', 'refactored', 'optimized', 'optimised',
        'minor fix', 'housekeeping', 'internal', 'tidy', 'styling update',
    ];

    /** Core risk keywords analyzed strictly in sentence context with negation lookback. */
    const RISK_KEYWORDS = [
        'fatal', 'crash', 'crashed', 'broken', 'breaking change', 'incompatible',
        'drops support', 'deprecated', 'backward-incompatible', 'backwards-incompatible',
    ];

    /**
     * Check if the "tested up to" gap is genuinely significant.
     * In WordPress, a 1-release gap (e.g. 7.0 vs 7.1) is routine WP.org release lag.
     * Only flag if the plugin is more than 1 major WordPress cycle behind (gap >= 2).
     */
    private static function is_tested_gap_significant(string $site_wp, string $tested): bool {
        if (empty($tested) || empty($site_wp)) return false;
        $s_parts = explode('.', $site_wp);
        $t_parts = explode('.', $tested);
        $s_val = ((int)$s_parts[0]) * 10 + (int)($s_parts[1] ?? 0);
        $t_val = ((int)$t_parts[0]) * 10 + (int)($t_parts[1] ?? 0);
        return ($s_val - $t_val) > 1;
    }

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

        $stability = 50; // Neutral default
        if (class_exists('Phpinfo_WP_Update_History')) {
            $stability = Phpinfo_WP_Update_History::stability_score($file);
        }

        // 3. Major version jump vs Routine Sprint Cadence
        $jump = self::version_jump_type($cur_ver, $new_ver);
        if ($jump === 'major') {
            if (self::is_sprint_cadence($slug, $cur_ver, $new_ver)) {
                $tp = explode('.', $new_ver);
                $signals[] = [
                    'type'     => 'sprint_cycle',
                    'severity' => 'info',
                    'label'    => sprintf(
                        __('Scheduled release cycle (v%s.x) — Routine maintenance', 'phpinfo-wp'),
                        esc_html($tp[0] ?? $new_ver)
                    ),
                    'detail'   => __('Standard bi-weekly sprint release with low architectural breaking risk', 'phpinfo-wp'),
                ];
            } else {
                $is_mitigated = ($stability >= 80);
                $tp = explode('.', $new_ver);
                $new_major = $tp[0] ?? $new_ver;
                $signals[] = [
                    'type'     => 'major_jump',
                    'severity' => $is_mitigated ? 'info' : 'caution',
                    'label'    => sprintf(
                        __('Major milestone upgrade (v%s.0)', 'phpinfo-wp'),
                        esc_html($new_major)
                    ),
                    'detail'   => $is_mitigated
                        ? __('Clean site update history mitigates version bump risk', 'phpinfo-wp')
                        : __('May adjust widget templates, styling hooks, or API signatures. Backup recommended before updating.', 'phpinfo-wp'),
                ];
            }
        }

        // 4. "Tested up to" gap (only flag when > 1 WordPress cycle behind)
        if ($tested && self::is_tested_gap_significant($site_wp, $tested)) {
            $signals[] = [
                'type'     => 'tested_gap',
                'severity' => 'caution',
                'label'    => sprintf(
                    __('Tested up to WordPress %s (you run %s) — multiple releases behind', 'phpinfo-wp'),
                    $tested, $site_wp
                ),
            ];
        }

        // --- Pro signals ---
        if ($is_pro) {
            // 5. Changelog risk keywords (negation-filtered)
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
                } elseif ($stale_days !== null && $stale_days <= 3) {
                    $signals[] = [
                        'type'     => 'fresh_release',
                        'severity' => 'caution',
                        'label'    => sprintf(
                            _n('Fresh release (%d day old) — Early adoption window', 'Fresh release (%d days old) — Early adoption window', max(1, $stale_days), 'phpinfo-wp'),
                            max(1, $stale_days)
                        ),
                        'detail'   => __('Consider waiting 48–72 hours for initial post-release patch revisions (.1)', 'phpinfo-wp'),
                    ];
                } elseif ($stale_days !== null && $stale_days >= 14 && $stale_days <= 180 && !empty($meta['last_updated'])) {
                    $signals[] = [
                        'type'     => 'matured_release',
                        'severity' => 'info',
                        'label'    => sprintf(
                            __('Matured release (%s ago) — Stable adoption in the wild', 'phpinfo-wp'),
                            human_time_diff(strtotime($meta['last_updated']))
                        ),
                        'detail'   => '',
                    ];
                }
            }

            // 7. Cross-dependency check (WP 6.5+ Requires Plugins header)
            $requires_plugins = $update->requires_plugins ?? null;
            if (!empty($requires_plugins)) {
                $deps = is_array($requires_plugins)
                    ? array_filter(array_map('trim', $requires_plugins))
                    : array_filter(array_map('trim', explode(',', (string) $requires_plugins)));
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
                // Tier 1: Confirmed runtime failure check
                $rollbacks = Phpinfo_WP_Update_History::get_rollback_log();
                foreach ($rollbacks as $rb) {
                    if (($rb['slug'] ?? '') === $slug || ($rb['slug'] ?? '') === $file) {
                        if (in_array($rb['event'] ?? '', ['rollback_success', 'rollback_failed'], true)) {
                            $signals[] = [
                                'type'     => 'runtime_breakage',
                                'severity' => 'risky',
                                'label'    => sprintf(
                                    __('Confirmed runtime failure: Past update required rollback on this site (%s)', 'phpinfo-wp'),
                                    $rb['time'] ?? 'recently'
                                ),
                            ];
                            break;
                        }
                    }
                }

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

            // 9. Activation-Only Migration check (WP footgun)
            if (self::check_activation_migration_risk($file)) {
                $signals[] = [
                    'type'     => 'activation_migration',
                    'severity' => 'caution',
                    'label'    => __('Uses activation hook for DB migration — new tables/columns may not create on update', 'phpinfo-wp'),
                ];
            }

            // 10. Cross-Plugin Class Inheritance Risk (PHP 8.x declaration incompatibilities)
            if (self::check_class_inheritance_risk($slug, $jump)) {
                $signals[] = [
                    'type'     => 'inheritance_risk',
                    'severity' => 'caution',
                    'label'    => __('Other active plugins extend this plugin\'s classes — major update may cause PHP 8 declaration incompatibilities', 'phpinfo-wp'),
                ];
            }
        }

        // Single-Verdict Arbitration Engine
        $verdict = 'safe';
        $has_risky = false;
        $has_caution = false;
        $hard_caution = false;

        foreach ($signals as $s) {
            if ($s['severity'] === 'risky') {
                $has_risky = true;
            }
            if ($s['severity'] === 'caution') {
                $has_caution = true;
                if (in_array($s['type'], ['runtime_breakage', 'history_issues', 'missing_dep', 'activation_migration', 'inheritance_risk'], true)) {
                    $hard_caution = true;
                }
            }
        }

        if ($has_risky) {
            $verdict = 'risky';
        } elseif ($has_caution) {
            // Stability Dampener: 100% clean history dampens weak heuristic notes
            if ($stability === 100 && !$hard_caution) {
                $verdict = 'safe';
                foreach ($signals as &$sig) {
                    if ($sig['severity'] === 'caution') {
                        $sig['severity'] = 'info';
                        $sig['label'] .= ' — ' . __('(Mitigated: 100% site track record)', 'phpinfo-wp');
                    }
                }
                unset($sig);
            } else {
                $verdict = 'caution';
            }
        }

        return [
            'type'      => 'plugin',
            'slug'      => $slug,
            'file'      => $file,
            'name'      => $name,
            'from'      => $cur_ver,
            'to'        => $new_ver,
            'stability' => $stability,
            'verdict'   => $verdict,
            'signals'   => $signals,
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

        $stability = 50;
        if (class_exists('Phpinfo_WP_Update_History')) {
            $stability = Phpinfo_WP_Update_History::stability_score('theme/' . $slug);
        }

        // 3. Major version jump vs Routine Sprint Cadence
        $jump = self::version_jump_type($cur_ver, $new_ver);
        if ($jump === 'major') {
            if (self::is_sprint_cadence($slug, $cur_ver, $new_ver)) {
                $tp = explode('.', $new_ver);
                $signals[] = [
                    'type'     => 'sprint_cycle',
                    'severity' => 'info',
                    'label'    => sprintf(
                        __('Scheduled release cycle (v%s.x) — Routine maintenance', 'phpinfo-wp'),
                        esc_html($tp[0] ?? $new_ver)
                    ),
                    'detail'   => __('Standard sprint release with low architectural breaking risk', 'phpinfo-wp'),
                ];
            } else {
                $is_mitigated = ($stability >= 80);
                $tp = explode('.', $new_ver);
                $new_major = $tp[0] ?? $new_ver;
                $signals[] = [
                    'type'     => 'major_jump',
                    'severity' => $is_mitigated ? 'info' : 'caution',
                    'label'    => sprintf(
                        __('Major milestone upgrade (v%s.0)', 'phpinfo-wp'),
                        esc_html($new_major)
                    ),
                    'detail'   => $is_mitigated
                        ? __('Clean site update history mitigates version bump risk', 'phpinfo-wp')
                        : __('Major theme release may modify template hierarchy or CSS classes. Test layout after update.', 'phpinfo-wp'),
                ];
            }
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
                } elseif ($stale_days !== null && $stale_days <= 3) {
                    $signals[] = [
                        'type'     => 'fresh_release',
                        'severity' => 'caution',
                        'label'    => sprintf(
                            _n('Fresh release (%d day old) — Early adoption window', 'Fresh release (%d days old) — Early adoption window', max(1, $stale_days), 'phpinfo-wp'),
                            max(1, $stale_days)
                        ),
                        'detail'   => __('Consider waiting 48–72 hours for initial post-release patch revisions (.1)', 'phpinfo-wp'),
                    ];
                } elseif ($stale_days !== null && $stale_days >= 14 && $stale_days <= 180 && !empty($meta['last_updated'])) {
                    $signals[] = [
                        'type'     => 'matured_release',
                        'severity' => 'info',
                        'label'    => sprintf(
                            __('Matured release (%s ago) — Stable adoption in the wild', 'phpinfo-wp'),
                            human_time_diff(strtotime($meta['last_updated']))
                        ),
                        'detail'   => '',
                    ];
                }
            }

            // Stability history
            if (class_exists('Phpinfo_WP_Update_History')) {
                // Tier 1: Confirmed runtime failure check
                $rollbacks = Phpinfo_WP_Update_History::get_rollback_log();
                foreach ($rollbacks as $rb) {
                    if (($rb['slug'] ?? '') === $slug || ($rb['slug'] ?? '') === 'theme/' . $slug) {
                        if (in_array($rb['event'] ?? '', ['rollback_success', 'rollback_failed'], true)) {
                            $signals[] = [
                                'type'     => 'runtime_breakage',
                                'severity' => 'risky',
                                'label'    => sprintf(
                                    __('Confirmed runtime failure: Past update required rollback on this site (%s)', 'phpinfo-wp'),
                                    $rb['time'] ?? 'recently'
                                ),
                            ];
                            break;
                        }
                    }
                }

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

        // Single-Verdict Arbitration Engine
        $verdict = 'safe';
        $has_risky = false;
        $has_caution = false;
        $hard_caution = false;

        foreach ($signals as $s) {
            if ($s['severity'] === 'risky') {
                $has_risky = true;
            }
            if ($s['severity'] === 'caution') {
                $has_caution = true;
                if (in_array($s['type'], ['runtime_breakage', 'history_issues', 'missing_dep', 'activation_migration', 'inheritance_risk'], true)) {
                    $hard_caution = true;
                }
            }
        }

        if ($has_risky) {
            $verdict = 'risky';
        } elseif ($has_caution) {
            if ($stability === 100 && !$hard_caution) {
                $verdict = 'safe';
                foreach ($signals as &$sig) {
                    if ($sig['severity'] === 'caution') {
                        $sig['severity'] = 'info';
                        $sig['label'] .= ' — ' . __('(Mitigated: 100% site track record)', 'phpinfo-wp');
                    }
                }
                unset($sig);
            } else {
                $verdict = 'caution';
            }
        }

        return [
            'type'      => 'theme',
            'slug'      => $slug,
            'file'      => $slug,
            'name'      => $name,
            'from'      => $cur_ver,
            'to'        => $new_ver,
            'stability' => $stability,
            'verdict'   => $verdict,
            'signals'   => $signals,
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
        $cached = get_transient(self::TRANSIENT_DETAIL . md5('cl_v3:' . $slug));
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

        set_transient(self::TRANSIENT_DETAIL . md5('cl_v3:' . $slug), $signals, self::META_TTL);
        return $signals;
    }

    /** Theme changelog from WP.org. */
    private static function parse_changelog_risk_theme(string $slug): array {
        $cached = get_transient(self::TRANSIENT_DETAIL . md5('tcl_v3:' . $slug));
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

        set_transient(self::TRANSIENT_DETAIL . md5('tcl_v3:' . $slug), $signals, self::META_TTL);
        return $signals;
    }

    /**
     * Parse a changelog HTML string for genuine risk conditions using sentence
     * splitting, routine phrase discard, and 6-word negation lookback.
     */
    private static function scan_changelog_text(string $html): array {
        $text = wp_strip_all_tags($html);
        $text = substr($text, 0, self::MAX_CHANGELOG);

        // Isolate the latest version section (up to the second version header)
        if (preg_match('/^(.*?)(?:=\s*\d+\.\d+)/s', $text, $m, 0, 10)) {
            $latest_section = substr($text, 0, strpos($text, $m[0], 10) ?: strlen($text));
        } else {
            $latest_section = substr($text, 0, 2000);
        }

        // Split into sentences using punctuation or line breaks to avoid cross-sentence bleed
        $sentences = preg_split('/(?<=[.!?\n\r])\s+/', $latest_section, -1, PREG_SPLIT_NO_EMPTY);
        $signals = [];
        $found = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) < 4) continue;
            $lower = strtolower($sentence);

            // 1. Routine maintenance phrases are discarded completely
            foreach (self::DISCARD_PHRASES as $phrase) {
                if (strpos($lower, $phrase) !== false) {
                    continue 2; // skip to next sentence
                }
            }

            // 2. Scan for risk keywords
            foreach (self::RISK_KEYWORDS as $kw) {
                $kw_pos = strpos($lower, $kw);
                if ($kw_pos === false || isset($found[$kw])) {
                    continue;
                }

                // Extract up to 6 words immediately preceding the keyword on the same sentence
                $before_text = substr($lower, 0, $kw_pos);
                $words_before = preg_split('/\s+/', trim($before_text));
                $window = implode(' ', array_slice($words_before, -6));

                // 3. If lookback window contains a fix signal, this is a bug fix (good news!), not a risk
                $is_fix = false;
                foreach (self::FIX_SIGNALS as $fix_sig) {
                    if (strpos($window, $fix_sig) !== false) {
                        $is_fix = true;
                        break;
                    }
                }
                if ($is_fix) {
                    continue; // Discard: bug fix mention
                }

                // 4. Require genuine forward-looking risk framing
                $is_risk = false;
                foreach (self::RISK_SIGNALS as $risk_sig) {
                    if (strpos($lower, $risk_sig) !== false) {
                        $is_risk = true;
                        break;
                    }
                }

                if ($is_risk) {
                    $found[$kw] = true;
                    $signals[] = [
                        'type'     => 'changelog_risk',
                        'severity' => 'caution',
                        'label'    => sprintf(
                            __('Changelog note: "%s"', 'phpinfo-wp'),
                            wp_trim_words($sentence, 14, '...')
                        ),
                        'detail'   => $kw,
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

    /**
     * Determine if a plugin uses CalVer, high-number sprint cycles, or continuous release cadences
     * where major segment increments do NOT denote breaking architectural rewrites.
     */
    private static function is_sprint_cadence(string $slug, string $from, string $to): bool {
        $fp = explode('.', $from);
        $tp = explode('.', $to);
        $from_major = (int) ($fp[0] ?? 0);
        $to_major   = (int) ($tp[0] ?? 0);

        // Known rapid-cadence / bi-weekly sprint plugins
        $known_cadence = [
            'wordpress-seo',
            'wordpress-seo-premium',
            'all-in-one-seo-pack',
            'nextgen-gallery',
        ];
        if (in_array($slug, $known_cadence, true)) {
            return true;
        }

        // Continuous milestone cadence: first segment >= 15 indicates bi-weekly / sprint versioning rather than breaking SemVer
        if ($from_major >= 15 || $to_major >= 15) {
            return true;
        }

        return false;
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

    /**
     * Check if a plugin relies on register_activation_hook for database migrations
     * without a runtime version comparison (classic WP footgun on updates).
     */
    private static function check_activation_migration_risk(string $file): bool {
        $plugin_path = trailingslashit(WP_PLUGIN_DIR) . $file;
        if (!file_exists($plugin_path)) return false;

        $content = @file_get_contents($plugin_path);
        if ($content === false) return false;

        // If main file doesn't have register_activation_hook, check files in root directory
        if (strpos($content, 'register_activation_hook') === false) {
            $dir = dirname($plugin_path);
            if ($dir !== WP_PLUGIN_DIR && is_dir($dir)) {
                $files = glob($dir . '/*.php');
                if (is_array($files)) {
                    foreach (array_slice($files, 0, 5) as $f) {
                        $c = @file_get_contents($f);
                        if ($c && strpos($c, 'register_activation_hook') !== false) {
                            $content .= "\n" . $c;
                            break;
                        }
                    }
                }
            }
        }

        if (strpos($content, 'register_activation_hook') === false) {
            return false;
        }

        // Check for DB schema creation
        $has_db_creation = (
            stripos($content, 'dbDelta') !== false ||
            stripos($content, 'CREATE TABLE') !== false ||
            stripos($content, 'ALTER TABLE') !== false
        );

        if (!$has_db_creation) {
            return false;
        }

        // Check if there is runtime upgrade logic (get_option check on version)
        $has_runtime_check = (
            preg_match('/get_option\s*\(\s*[\'"][a-zA-Z0-9_\-]+(?:version|db_version|schema)[\'"]\s*\)/i', $content) ||
            (stripos($content, 'update_option') !== false && stripos($content, 'version') !== false)
        );

        return !$has_runtime_check;
    }

    /**
     * Detect if other active plugins declare classes extending this plugin's classes
     * and this update represents a major jump (PHP 8 declaration incompatibility danger).
     */
    private static function check_class_inheritance_risk(string $slug, string $jump_type): bool {
        if ($jump_type !== 'major') return false;

        $active_plugins = (array) get_option('active_plugins', []);
        $known_frameworks = [
            'woocommerce'            => ['WC_Payment_Gateway', 'WC_Shipping_Method', 'WC_Integration'],
            'advanced-custom-fields' => ['acf_field'],
            'elementor'              => ['Widget_Base'],
            'easy-digital-downloads' => ['EDD_Payment_Gateway'],
        ];

        if (isset($known_frameworks[$slug])) {
            $base_classes = $known_frameworks[$slug];
            foreach ($active_plugins as $ap) {
                if (dirname($ap) === $slug) continue;
                $ap_path = trailingslashit(WP_PLUGIN_DIR) . $ap;
                if (!file_exists($ap_path)) continue;
                $c = @file_get_contents($ap_path);
                if ($c) {
                    foreach ($base_classes as $bc) {
                        if (stripos($c, 'extends ' . $bc) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Admin integration — inject risk badges on the Updates page
    // -------------------------------------------------------------------------

    public static function register(): void {
        // Inject badge notices on the core update page
        add_action('admin_notices',             [self::class, 'updates_page_badge']);
        add_action('load-plugins.php',          [self::class, 'setup_plugin_update_badges']);
        add_action('load-update-core.php',      [self::class, 'setup_plugin_update_badges']);
        add_action('admin_head-plugins.php',    [self::class, 'inject_plugins_page_assets']);
        add_action('admin_head-update-core.php', [self::class, 'inject_plugins_page_assets']);
        add_action('core_upgrade_preamble',     [self::class, 'render_core_preamble']);

        // Background async prefetch cron (twicedaily)
        add_action('piwp_ug_prefetch_cron',     [self::class, 'scan']);
        if (!wp_next_scheduled('piwp_ug_prefetch_cron')) {
            wp_schedule_event(time() + 3600, 'twicedaily', 'piwp_ug_prefetch_cron');
        }

        add_action('wp_ajax_phpinfowp_ug_scan',           [self::class, 'ajax_scan']);
        add_action('wp_ajax_phpinfowp_ug_manual_refresh', [self::class, 'ajax_scan']);
        add_action('wp_ajax_phpinfowp_ug_clear',          [self::class, 'ajax_clear']);
    }

    public static function ajax_scan(): void {
        check_ajax_referer('phpinfowp_ug_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::start('update_guard');
        }

        $result = self::scan();

        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::complete('update_guard', [
                'total'       => $result['total'] ?? 0,
                'risky_count' => $result['risky_count'] ?? 0,
            ]);
        }

        wp_send_json_success($result);
    }

    public static function ajax_clear(): void {
        check_ajax_referer('phpinfowp_ug_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        self::clear();
        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::clear('update_guard');
        }
        wp_send_json_success();
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
                '<div class="notice notice-info piwp-notice"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
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
                '<div class="notice notice-success piwp-notice"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
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
                '<div class="notice notice-%s piwp-notice"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
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

    /**
     * Retrieve risk assessment score for a specific plugin file.
     */
    public static function get_plugin_score(string $file): ?array {
        $result = self::get_result();
        if ($result && !empty($result['items'])) {
            foreach ($result['items'] as $item) {
                if (($item['file'] ?? '') === $file) {
                    return $item;
                }
            }
        }

        // Fallback: evaluate single plugin against current environment
        $updates = self::pending_plugin_updates();
        if (isset($updates[$file])) {
            return self::score_plugin($updates[$file], $file, PHP_VERSION, get_bloginfo('version'), self::_pro());
        }

        return null;
    }

    /**
     * Set up hooks for pending plugin update rows in wp-admin/plugins.php.
     */
    public static function setup_plugin_update_badges(): void {
        $updates = self::pending_plugin_updates();
        foreach (array_keys($updates) as $file) {
            add_action("in_plugin_update_message-{$file}", [self::class, 'render_plugin_update_message'], 10, 2);
        }
    }

    /**
     * Render inline badge inside WordPress core's plugin update notification.
     */
    public static function render_plugin_update_message($plugin_data, $response): void {
        $file = is_array($plugin_data) ? ($plugin_data['plugin'] ?? '') : '';
        if (!$file && is_object($response) && !empty($response->plugin)) {
            $file = $response->plugin;
        }
        if (!$file && is_object($response) && !empty($response->slug)) {
            foreach (array_keys(self::pending_plugin_updates()) as $f) {
                if (dirname($f) === $response->slug || basename($f, '.php') === $response->slug) {
                    $file = $f;
                    break;
                }
            }
        }
        if (!$file) return;

        $score = self::get_plugin_score($file);
        if (!$score) return;

        // Always show green for our own plugin.
        $own_plugin = 'phpinfo-wp/phpinfo-wp.php';
        if ($file === $own_plugin || basename( dirname( $file ) ) === 'phpinfo-wp') {
            $verdict = 'safe';
            $signals = [];
        } else {
            $verdict = $score['verdict'] ?? 'safe';
            $signals = $score['signals'] ?? [];
        }
        $bg     = $verdict === 'risky' ? '#fef2f2' : ($verdict === 'caution' ? '#fefce8' : '#f0fdf4');
        $border = $verdict === 'risky' ? '#ef4444' : ($verdict === 'caution' ? '#eab308' : '#22c55e');
        $text   = $verdict === 'risky' ? '#991b1b' : ($verdict === 'caution' ? '#854d0e' : '#166534');
        $icon   = $verdict === 'risky' ? 'dashicons-dismiss' : ($verdict === 'caution' ? 'dashicons-warning' : 'dashicons-yes-alt');
        $guard_label = $verdict === 'risky'
            ? __('Update Guard: High Risk', 'phpinfo-wp')
            : ($verdict === 'caution' ? __('Update Guard: Caution', 'phpinfo-wp') : __('Update Guard: Safe', 'phpinfo-wp'));

        echo '<span class="piwp-ug-inline-badge piwp-ug-' . esc_attr($verdict) . '" style="display:block;margin:8px 0 4px;padding:6px 12px;background:' . esc_attr($bg) . ';border-left:3px solid ' . esc_attr($border) . ';color:' . esc_attr($text) . ';font-size:12px;border-radius:2px;line-height:1.5;">';
        echo '<span class="dashicons ' . esc_attr($icon) . '" style="font-size:15px;width:15px;height:15px;vertical-align:text-top;margin-right:4px;"></span>';
        echo '<strong>' . esc_html($guard_label) . '</strong>';

        if (!empty($signals)) {
            echo ' — ';
            $labels = array_column($signals, 'label');
            echo esc_html(implode(' | ', array_slice($labels, 0, 2)));
        } else {
            echo ' — ' . esc_html__('Environment compatible. Pre-flight backup snapshot enabled.', 'phpinfo-wp');
        }

        $audit_url = admin_url('admin.php?page=piwp-update-audit');
        echo ' <a href="' . esc_url($audit_url) . '" style="color:inherit;text-decoration:underline;margin-left:6px;">' . esc_html__('Details &rarr;', 'phpinfo-wp') . '</a>';
        echo ' <span style="color:#999;font-size:11px;margin-left:10px;">by phpinfo() WP</span>';
        echo '</span>';
    }

    /**
     * Inject inline CSS and click interceptor JS on plugins.php and update-core.php.
     */
    public static function inject_plugins_page_assets(): void {
        $updates = self::pending_plugin_updates();
        if (empty($updates)) return;

        $risky_map = [];
        foreach ($updates as $file => $u) {
            $score = self::get_plugin_score($file);
            if ($score && ($score['verdict'] ?? '') === 'risky') {
                $reasons = array_column($score['signals'] ?? [], 'label');
                $risky_map[$file] = !empty($reasons) ? implode('; ', $reasons) : __('High risk incompatibility detected', 'phpinfo-wp');
            }
        }
        ?>
        <style>
            .piwp-ug-inline-badge { box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
            .piwp-ug-inline-badge a:hover { opacity: 0.8; }
            .plugins .plugin-update-tr .update-message p:empty { display: none !important; }
            .plugins .plugin-update-tr .update-message p:empty:before { display: none !important; content: none !important; }
        </style>
        <?php if (!empty($risky_map)): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var riskyMap = <?php echo wp_json_encode($risky_map); ?>;
            document.addEventListener('click', function(e) {
                var target = e.target.closest('a.update-link, .update-link, [id*="update-selected"]');
                if (!target) return;

                var row = target.closest('tr[data-plugin]');
                var plugin = row ? row.getAttribute('data-plugin') : '';
                if (!plugin && target.href) {
                    var m = target.href.match(/plugin=([^&]+)/);
                    if (m) plugin = decodeURIComponent(m[1]);
                }

                if (plugin && riskyMap[plugin]) {
                    var msg = "⚠️ Update Guard Warning:\n\n" +
                              "This update has been flagged as HIGH RISK:\n" +
                              riskyMap[plugin] + "\n\n" +
                              "A pre-flight zip snapshot will be taken, but the site may experience issues. Proceed with update?";
                    if (!confirm(msg)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        return false;
                    }
                }
            }, true);
        });
        </script>
        <?php endif;
    }

    /**
     * Render WordPress Core upgrade readiness banner on update-core.php.
     */
    public static function render_core_preamble(): void {
        if (!class_exists('Phpinfo_WP_Update_Audit')) return;
        $target_ver = Phpinfo_WP_Update_Audit::get_target_wp();
        $target_php = Phpinfo_WP_Update_Audit::get_target_php();

        $cached = get_option('phpinfowp_update_audit_cache', null);
        $audit_url = admin_url('admin.php?page=piwp-update-audit');

        echo '<div class="notice notice-info piwp-core-preamble" style="padding:14px 16px;margin:15px 0;background:#fff;border-left:4px solid #3b82f6;box-shadow:0 1px 3px rgba(0,0,0,0.06);border-radius:2px;">';
        echo '<h4 style="margin:0 0 6px 0;font-size:14px;color:#1e293b;"><span class="dashicons dashicons-shield" style="color:#3b82f6;vertical-align:text-top;margin-right:4px;"></span> ' . esc_html__('Update Guard — Core Readiness Audit', 'phpinfo-wp') . '</h4>';

        if ($cached && !empty($cached['summary'])) {
            $removals     = (int) ($cached['summary']['removals'] ?? 0);
            $deprecations = (int) ($cached['summary']['deprecations'] ?? 0);
            $items        = (int) ($cached['summary']['items_with_issues'] ?? 0);

            if ($removals === 0) {
                echo '<p style="margin:0 0 10px 0;color:#16a34a;font-size:13px;"><strong>✓ ' . sprintf(esc_html__('No breaking function/hook removals detected for WordPress %s.', 'phpinfo-wp'), esc_html($target_ver)) . '</strong> (' . sprintf(esc_html__('%d informational deprecation notices', 'phpinfo-wp'), $deprecations) . ')</p>';
            } else {
                echo '<p style="margin:0 0 10px 0;color:#dc2626;font-size:13px;"><strong>⚠ ' . sprintf(esc_html__('%d breaking removal(s) detected across %d plugin(s)/theme(s) for WordPress %s.', 'phpinfo-wp'), $removals, $items, esc_html($target_ver)) . '</strong></p>';
            }
        } else {
            echo '<p style="margin:0 0 10px 0;color:#64748b;font-size:13px;">' . sprintf(esc_html__('Targeting WordPress %s &amp; PHP %s. Run a pre-upgrade audit to test your active plugins and themes for compatibility.', 'phpinfo-wp'), esc_html($target_ver), esc_html($target_php)) . '</p>';
        }

        echo '<a href="' . esc_url($audit_url) . '" class="button button-secondary button-small">' . esc_html__('Open Update Guard Cockpit', 'phpinfo-wp') . ' &rarr;</a>';
        echo '</div>';
    }
}
