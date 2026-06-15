<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Config Grader — context-aware PHP/WordPress configuration audit.
 *
 * Sophistication levers (vs the prior static-threshold version):
 *
 *  1. Context-aware thresholds. We detect active plugins (WooCommerce, Elementor,
 *     LearnDash, BuddyPress, page builders, big-import tools), the hosting
 *     environment (Kinsta / WP Engine / SiteGround / Cloudways / Pantheon /
 *     Flywheel / LiquidWeb / LiteSpeed), PHP version, and HTTPS state, then
 *     adjust recommended values per directive accordingly. A WooCommerce site
 *     wants 512M of memory; a blog wants 256M; we now recommend the right one.
 *
 *  2. Cross-directive consistency checks. post_max_size must be ≥
 *     upload_max_filesize. memory_limit must be ≥ post_max_size + headroom.
 *     max_input_time must not exceed max_execution_time. Each is its own
 *     check run after the per-directive pass.
 *
 *  3. Live-data corroboration. We read the OPcache live stats and the recent
 *     error-log tail. If memory_limit "passes" the static threshold but the
 *     error log shows recent OOM kills, we escalate. If opcache.memory looks
 *     fine but the cache is currently full, we escalate. The static rule is
 *     a starting point; reality overrides it.
 *
 *  4. PHP 8.x modern directives. Added opcache.jit, opcache.jit_buffer_size,
 *     opcache.huge_code_pages, realpath_cache_size, realpath_cache_ttl,
 *     date.timezone, output_buffering, max_file_uploads.
 *
 *  5. Host-aware remediation. For directives that can't be changed by the
 *     user on managed hosts, point them at the host's panel instead of
 *     telling them to edit php.ini.
 *
 *  6. Severity matrix. Critical / High / Medium / Low replaces the old
 *     weight 1/2/3 + pass/warn/fail combo. Severity is the per-check
 *     attribute; status (pass/warn/fail) is derived from current value.
 *
 *  7. Trend tracking. Each Pro page-load on a site records the current
 *     score in a rolling 30-day option. We compute delta from previous
 *     recording and surface it so weekly reports can say "B (-3 since
 *     last week — memory_limit regressed)".
 *
 * The return shape of run() is backward-compatible with the audit-report
 * consumer: `checks` is still an array of per-check rows with at least
 * `key/label/value/good/status`. New fields (severity, live_evidence,
 * host_fix, target_value) are additive.
 */
class Phpinfo_WP_Config_Grader {

    // Severity tiers
    const SEV_CRITICAL = 'critical'; // RCE / data leak risk
    const SEV_HIGH     = 'high';     // ≥10% perf hit OR strong security weakness
    const SEV_MEDIUM   = 'medium';   // Best practice / minor perf
    const SEV_LOW      = 'low';      // Nice to have

    // Severity → numeric weight used by the scoring formula
    private const SEV_WEIGHT = [
        self::SEV_CRITICAL => 4,
        self::SEV_HIGH     => 3,
        self::SEV_MEDIUM   => 2,
        self::SEV_LOW      => 1,
    ];

    const OPT_HISTORY = 'phpinfowp_grader_history';

    /**
     * @var mixed[]|null
     */
    private static $ctx_cache;

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    // ─────────────────────────────────────────────────────────────────
    //  Site context — detected once per request
    // ─────────────────────────────────────────────────────────────────

    public static function context(): array {
        if (self::$ctx_cache !== null) return self::$ctx_cache;
        return self::$ctx_cache = [
            'php_major'      => PHP_MAJOR_VERSION,
            'php_minor'      => PHP_MINOR_VERSION,
            'php_full'       => PHP_VERSION,
            'is_https'       => is_ssl(),
            'is_production'  => !(defined('WP_DEBUG') && WP_DEBUG),
            'plugins'        => self::detect_plugins(),
            'host'           => self::detect_host(),
            'server'         => self::detect_server(),
            'live_opcache'   => function_exists('opcache_get_status') ? @opcache_get_status(false) : null,
            'error_signals'  => self::error_log_signals(),
        ];
    }

    /** Map of detected workloads we tune for. */
    private static function detect_plugins(): array {
        $active = (array) get_option('active_plugins', []);
        if (is_multisite()) {
            $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }
        // basename → workload key
        $map = [
            'woocommerce/woocommerce.php'                            => 'woocommerce',
            'elementor/elementor.php'                                => 'elementor',
            'elementor-pro/elementor-pro.php'                        => 'elementor',
            'js_composer/js_composer.php'                            => 'wpbakery',
            'fusion-builder/fusion-builder.php'                      => 'avada',
            'oxygen/functions.php'                                   => 'oxygen',
            'beaver-builder-lite-version/fl-builder.php'             => 'beaver-builder',
            'bb-plugin/fl-builder.php'                               => 'beaver-builder',
            'buddypress/bp-loader.php'                               => 'buddypress',
            'sfwd-lms/sfwd_lms.php'                                  => 'learndash',
            'tutor/tutor.php'                                        => 'tutor',
            'lifterlms/lifterlms.php'                                => 'lifterlms',
            'easy-digital-downloads/easy-digital-downloads.php'      => 'edd',
            'wp-all-import/plugin.php'                               => 'wp-all-import',
            'wp-all-import-pro/wp-all-import-pro.php'                => 'wp-all-import',
            'wpforms-lite/wpforms.php'                               => 'forms-heavy',
            'wpforms/wpforms.php'                                    => 'forms-heavy',
            'formidable/formidable.php'                              => 'forms-heavy',
            'gravityforms/gravityforms.php'                          => 'forms-heavy',
            'updraftplus/updraftplus.php'                            => 'backup',
            'backwpup/backwpup.php'                                  => 'backup',
            'duplicator/duplicator.php'                              => 'backup',
            'duplicator-pro/duplicator-pro.php'                      => 'backup',
            'wp-rocket/wp-rocket.php'                                => 'wp-rocket',
            'litespeed-cache/litespeed-cache.php'                    => 'litespeed-cache',
        ];
        $detected = [];
        foreach ($active as $p) {
            if (isset($map[$p])) $detected[$map[$p]] = true;
        }
        // Theme detection — Divi
        $theme = wp_get_theme();
        if (!$theme->errors() && (strtolower($theme->get('Name') ?? '') === 'divi' || strtolower($theme->get('Template') ?? '') === 'divi')) {
            $detected['divi'] = true;
        }
        return $detected;
    }

    /**
     * Determines the single most critical "Application Profile" for the site to display in the UI banner.
     * Checks installed plugins in order of their impact on server requirements.
     */
    public static function dominant_profile(array $ctx): ?array {
        $p = $ctx['plugins'] ?? [];

        // 1. E-commerce
        if (!empty($p['woocommerce'])) {
            return [
                'id'    => 'woocommerce',
                'name'  => 'WooCommerce Profile',
                'icon'  => '🛒',
                'desc'  => 'E-commerce sites require significantly more memory (512M+) and execution time than standard blogs. Apply this profile to prevent cart abandonment and slow checkouts.',
            ];
        }
        if (!empty($p['edd'])) {
            return [
                'id'    => 'edd',
                'name'  => 'EDD E-commerce Profile',
                'icon'  => '🛒',
                'desc'  => 'Easy Digital Downloads requires higher memory ceilings and optimal caching. Apply this profile to ensure stable transactions.',
            ];
        }

        // 2. LMS / Courses
        if (!empty($p['learndash']) || !empty($p['lifterlms']) || !empty($p['tutor'])) {
            return [
                'id'    => 'lms',
                'name'  => 'LMS & Courses Profile',
                'icon'  => '🎓',
                'desc'  => 'Learning Management Systems have many concurrent logged-in users tracking progress. Apply this profile to prevent database and memory bottlenecks.',
            ];
        }

        // 3. Heavy Imports
        if (!empty($p['wp-all-import'])) {
            return [
                'id'    => 'import',
                'name'  => 'Heavy Import Profile',
                'icon'  => '📦',
                'desc'  => 'Mass data imports require extremely long maximum execution times and memory limits to prevent timing out halfway through.',
            ];
        }

        // 4. Page Builders
        if (!empty($p['elementor']) || !empty($p['wpbakery']) || !empty($p['divi']) || !empty($p['avada']) || !empty($p['oxygen']) || !empty($p['beaver-builder'])) {
            return [
                'id'    => 'builder',
                'name'  => 'Page Builder Profile',
                'icon'  => '🎨',
                'desc'  => 'Visual page builders construct pages using thousands of input variables. Apply this profile to prevent layout data from being silently truncated upon saving.',
            ];
        }

        // 5. Community
        if (!empty($p['buddypress'])) {
            return [
                'id'    => 'community',
                'name'  => 'BuddyPress Community Profile',
                'icon'  => '👥',
                'desc'  => 'Social and community sites have highly uncacheable, dynamic traffic. Apply this profile to ensure enough memory overhead is available for concurrent users.',
            ];
        }

        return null;
    }

    /** Managed-host fingerprint. Returns slug or null. */
    private static function detect_host(): ?string {
        if (defined('KINSTA_CACHE_ZONE'))                              return 'kinsta';
        if (defined('WPE_APIKEY') || !empty($_SERVER['IS_WPE']))       return 'wpengine';
        if (@file_exists('/var/lib/sgsystem'))                          return 'siteground';
        if (defined('PANTHEON_ENVIRONMENT'))                            return 'pantheon';
        if (defined('FLYWHEEL_CONFIG_DIR'))                             return 'flywheel';
        if (@file_exists('/etc/cloudways'))                             return 'cloudways';
        if (!empty($_SERVER['CLOUDWAYS_APP_ID']))                       return 'cloudways';
        if (defined('LIQUIDWEB_HOSTING'))                               return 'liquidweb';
        if (!empty($_SERVER['HTTP_X_LSCACHE']))                         return 'litespeed';
        return null;
    }

    /** Server software family. */
    private static function detect_server(): string {
        $sw = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
        if (strpos($sw, 'litespeed') !== false) return 'litespeed';
        if (strpos($sw, 'nginx') !== false)     return 'nginx';
        if (strpos($sw, 'apache') !== false)    return 'apache';
        if (strpos($sw, 'iis') !== false)       return 'iis';
        return 'unknown';
    }

    /** Tail the error log for actionable signals (memory OOM, time-exceeded, max-input-vars). */
    private static function error_log_signals(): array {
        if (!class_exists('Phpinfo_WP_Error_Log')) return [];
        $path = Phpinfo_WP_Error_Log::find_path();
        if (!$path || !@is_readable($path)) return [];
        $size = (int) @filesize($path);
        if ($size <= 0) return [];
        $chunk  = 256 * 1024;
        $offset = max(0, $size - $chunk);
        $fh = @fopen($path, 'rb');
        if (!$fh) return [];
        @fseek($fh, $offset);
        $data = (string) @fread($fh, $chunk);
        @fclose($fh);
        if ($data === '') return [];
        return [
            'memory_exhausted'        => (int) preg_match_all('/Allowed memory size of/i', $data),
            'max_time_exceeded'       => (int) preg_match_all('/Maximum execution time of \d+ seconds exceeded/i', $data),
            'max_input_vars_exceeded' => (int) preg_match_all('/Input variables exceeded/i', $data),
            'upload_too_large'        => (int) preg_match_all('/POST Content-Length .* exceeds the limit/i', $data),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Recommendations — context-aware thresholds
    // ─────────────────────────────────────────────────────────────────

    /** Recommended memory_limit in bytes, with the reason list. */
    public static function rec_memory(array $ctx): array {
        $mb = 256; $reasons = [];
        $p = $ctx['plugins'] ?? [];
        if (!empty($p['woocommerce']))   { $mb = max($mb, 512); $reasons[] = 'WooCommerce'; }
        if (!empty($p['learndash']))     { $mb = max($mb, 512); $reasons[] = 'LearnDash'; }
        if (!empty($p['lifterlms']))     { $mb = max($mb, 512); $reasons[] = 'LifterLMS'; }
        if (!empty($p['buddypress']))    { $mb = max($mb, 384); $reasons[] = 'BuddyPress'; }
        if (!empty($p['wp-all-import'])) { $mb = max($mb, 512); $reasons[] = 'WP All Import'; }
        if (!empty($p['backup']))        { $mb = max($mb, 384); $reasons[] = 'a backup plugin'; }
        return ['mb' => $mb, 'reasons' => $reasons];
    }

    /** Recommended max_input_vars + reasons. */
    public static function rec_input_vars(array $ctx): array {
        $n = 3000; $reasons = [];
        $p = $ctx['plugins'] ?? [];
        if (!empty($p['elementor']))      { $n = max($n, 5000); $reasons[] = 'Elementor'; }
        if (!empty($p['wpbakery']))       { $n = max($n, 5000); $reasons[] = 'WPBakery'; }
        if (!empty($p['divi']))           { $n = max($n, 5000); $reasons[] = 'Divi'; }
        if (!empty($p['avada']))          { $n = max($n, 5000); $reasons[] = 'Avada Fusion Builder'; }
        if (!empty($p['oxygen']))         { $n = max($n, 5000); $reasons[] = 'Oxygen Builder'; }
        if (!empty($p['beaver-builder'])) { $n = max($n, 4000); $reasons[] = 'Beaver Builder'; }
        if (!empty($p['forms-heavy']))    { $n = max($n, 5000); $reasons[] = 'a form-builder plugin'; }
        if (!empty($p['woocommerce']))    { $n = max($n, 5000); $reasons[] = 'WooCommerce'; }
        return ['n' => $n, 'reasons' => $reasons];
    }

    /** Recommended max_execution_time + reasons. */
    public static function rec_exec_time(array $ctx): array {
        $s = 60; $reasons = [];
        $p = $ctx['plugins'] ?? [];
        if (!empty($p['backup']))         { $s = max($s, 300); $reasons[] = 'a backup plugin'; }
        if (!empty($p['wp-all-import']))  { $s = max($s, 300); $reasons[] = 'WP All Import'; }
        if (!empty($p['woocommerce']))    { $s = max($s, 120); $reasons[] = 'WooCommerce'; }
        return ['s' => $s, 'reasons' => $reasons];
    }

    public static function rec_upload(array $ctx): int {
        $mb = 64;
        $p = $ctx['plugins'] ?? [];
        if (!empty($p['woocommerce']))   $mb = max($mb, 128);
        if (!empty($p['wp-all-import'])) $mb = max($mb, 256);
        if (!empty($p['backup']))        $mb = max($mb, 128);
        return $mb;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Check definitions — single source of truth
    // ─────────────────────────────────────────────────────────────────

    /**
     * Each check returns ['key','label','category','severity','target','target_label','pass','warn','note','php_min'].
     * `target` is the recommended raw value (string or int) for display + fixer.
     * `pass`/`warn` are closures accepting (current_value_string, target).
     * `php_min` is the minimum PHP version this directive applies to (null = always).
     */
    private static function checks(array $ctx): array {
        $mem    = self::rec_memory($ctx);
        $vars   = self::rec_input_vars($ctx);
        $exec   = self::rec_exec_time($ctx);
        $upload = self::rec_upload($ctx);
        $is_https = $ctx['is_https'];

        $checks = [
            // ── Memory & Execution ──
            [
                'key' => 'memory_limit', 'label' => 'Memory Limit', 'category' => 'Performance',
                'severity' => self::SEV_CRITICAL,
                'target' => $mem['mb'] . 'M',
                'target_label' => $mem['mb'] . 'M' . (count($mem['reasons']) ? ' (' . implode(', ', $mem['reasons']) . ')' : ''),
                'pass' => function ($v, $t) {
                    return trim($v) === '-1' || self::bytes($v) >= self::bytes($t);
                },
                'warn' => function ($v, $t) {
                    return trim($v) === '-1' || self::bytes($v) >= self::bytes($t) * 0.5;
                },
                'note' => 'PHP\'s memory ceiling. WordPress baseline is 256M; e-commerce and LMS workloads need 512M+.',
            ],
            [
                'key' => 'max_execution_time', 'label' => 'Max Execution Time', 'category' => 'Performance',
                'severity' => self::SEV_HIGH,
                'target' => (string) $exec['s'],
                'target_label' => $exec['s'] . ' seconds' . (count($exec['reasons']) ? ' (' . implode(', ', $exec['reasons']) . ')' : ''),
                'pass' => function ($v, $t) {
                    return (int) $v === 0 || (int) $v >= (int) $t;
                },
                'warn' => function ($v, $t) {
                    return (int) $v >= max(30, (int) $t / 2);
                },
                'note' => 'How long a single PHP request may run. Imports, updates, and backups time out below 60s.',
            ],
            [
                'key' => 'max_input_vars', 'label' => 'Max Input Vars', 'category' => 'Performance',
                'severity' => self::SEV_HIGH,
                'target' => (string) $vars['n'],
                'target_label' => $vars['n'] . ' or more' . (count($vars['reasons']) ? ' (' . implode(', ', $vars['reasons']) . ')' : ''),
                'pass' => function ($v, $t) {
                    return (int) $v >= (int) $t;
                },
                'warn' => function ($v, $t) {
                    return (int) $v >= max(1000, (int) $t / 2);
                },
                'note' => 'Maximum form fields PHP will accept per request. Page builders and big forms silently lose fields below 3000.',
            ],
            [
                'key' => 'upload_max_filesize', 'label' => 'Upload Max Filesize', 'category' => 'Performance',
                'severity' => self::SEV_MEDIUM,
                'target' => $upload . 'M',
                'target_label' => $upload . 'M or more',
                'pass' => function ($v, $t) {
                    return self::bytes($v) >= self::bytes($t);
                },
                'warn' => function ($v, $t) {
                    return self::bytes($v) >= self::bytes($t) * 0.5;
                },
                'note' => 'Largest single file PHP will accept. Caps media uploads, plugin zips, theme uploads.',
            ],
            [
                'key' => 'post_max_size', 'label' => 'Post Max Size', 'category' => 'Performance',
                'severity' => self::SEV_MEDIUM,
                'target' => $upload . 'M',
                'target_label' => 'At least equal to upload_max_filesize (' . $upload . 'M)',
                'pass' => function ($v, $t) {
                    return self::bytes($v) >= self::bytes($t);
                },
                'warn' => function ($v, $t) {
                    return self::bytes($v) >= self::bytes($t) * 0.5;
                },
                'note' => 'Caps the entire POST body. Must be ≥ upload_max_filesize or large uploads fail.',
            ],
            [
                'key' => 'max_input_time', 'label' => 'Max Input Time', 'category' => 'Performance',
                'severity' => self::SEV_LOW,
                'target' => '60',
                'target_label' => '60 seconds or -1 (unlimited)',
                'pass' => function ($v, $t) {
                    return (int) $v === -1 || (int) $v >= (int) $t;
                },
                'warn' => function ($v, $t) {
                    return (int) $v >= 30;
                },
                'note' => 'Time PHP spends parsing the request body. Affects multi-MB uploads on slow links.',
            ],
            [
                'key' => 'max_file_uploads', 'label' => 'Max File Uploads', 'category' => 'Performance',
                'severity' => self::SEV_LOW,
                'target' => '20',
                'target_label' => '20 or more',
                'pass' => function ($v, $t) {
                    return (int) $v >= (int) $t;
                },
                'warn' => function ($v, $t) {
                    return (int) $v >= 10;
                },
                'note' => 'Max files per single upload form. WordPress gallery uploads need 20+.',
            ],

            // ── Security & Error Handling ──
            [
                'key' => 'display_errors', 'label' => 'Display Errors', 'category' => 'Security',
                'severity' => $ctx['is_production'] ? self::SEV_CRITICAL : self::SEV_LOW,
                'target' => '0',
                'target_label' => 'Off (production) — leaks code paths to attackers',
                'pass' => function ($v) {
                    return in_array(strtolower($v), ['0', 'off', ''], true);
                },
                'note' => 'When on, PHP errors print to the response. Stack traces leak credentials, paths, and table prefixes.',
            ],
            [
                'key' => 'expose_php', 'label' => 'Expose PHP Version', 'category' => 'Security',
                'severity' => self::SEV_MEDIUM,
                'target' => '0',
                'target_label' => 'Off — hides X-Powered-By header',
                'pass' => function ($v) {
                    return in_array(strtolower($v), ['0', 'off', ''], true);
                },
                'note' => 'Stops PHP from advertising its version. Slows down version-specific exploit scanning.',
            ],
            [
                'key' => 'allow_url_include', 'label' => 'Allow URL Include', 'category' => 'Security',
                'severity' => self::SEV_CRITICAL,
                'target' => '0',
                'target_label' => 'Off — critical RCE vector if enabled',
                'pass' => function ($v) {
                    return in_array(strtolower($v), ['0', 'off', ''], true);
                },
                'note' => 'Lets PHP `include` a remote URL. Single biggest RCE foot-gun in the language.',
            ],
            [
                'key' => 'log_errors', 'label' => 'Log Errors', 'category' => 'Security',
                'severity' => self::SEV_MEDIUM,
                'target' => '1',
                'target_label' => 'On — write errors to file, not to visitors',
                'pass' => function ($v) {
                    return in_array(strtolower($v), ['1', 'on'], true);
                },
                'note' => 'Errors should be logged silently. Pair with display_errors=Off.',
            ],
            [
                'key' => 'session.cookie_httponly', 'label' => 'Session Cookie HttpOnly', 'category' => 'Security',
                'severity' => self::SEV_HIGH,
                'target' => '1',
                'target_label' => 'On — blocks JS access to session cookies (mitigates XSS)',
                'pass' => function ($v) {
                    return in_array(strtolower($v), ['1', 'on'], true);
                },
                'note' => 'When On, JavaScript cannot read the session cookie via document.cookie.',
            ],
            [
                'key' => 'session.use_strict_mode', 'label' => 'Session Strict Mode', 'category' => 'Security',
                'severity' => self::SEV_HIGH,
                'target' => '1',
                'target_label' => 'On — rejects uninitialized session IDs (anti-fixation)',
                'pass' => function ($v) {
                    return in_array(strtolower($v), ['1', 'on'], true);
                },
                'note' => 'Prevents attackers from forcing a chosen session ID onto a victim before they sign in.',
            ],
            [
                'key' => 'session.cookie_secure', 'label' => 'Session Cookie Secure', 'category' => 'Security',
                'severity' => $is_https ? self::SEV_HIGH : self::SEV_LOW,
                'target' => $is_https ? '1' : '0',
                'target_label' => $is_https ? 'On — required on HTTPS sites' : 'N/A (site is HTTP)',
                'pass' => function ($v) use ($is_https) {
                    return !$is_https || in_array(strtolower($v), ['1', 'on'], true);
                },
                'note' => 'Restricts the session cookie to HTTPS transport. Mandatory once your site serves HTTPS.',
            ],

            // ── OPcache (PHP's bytecode cache) ──
            [
                'key' => 'opcache.enable', 'label' => 'OPcache Enabled', 'category' => 'OPcache',
                'severity' => self::SEV_CRITICAL,
                'target' => '1',
                'target_label' => 'On — typical 50–80% PHP CPU reduction',
                'pass' => function ($v) {
                    return in_array(strtolower($v), ['1', 'on'], true);
                },
                'note' => 'Caches compiled PHP bytecode. Without it, WordPress recompiles every file on every request.',
            ],
            [
                'key' => 'opcache.memory_consumption', 'label' => 'OPcache Memory', 'category' => 'OPcache',
                'severity' => self::SEV_HIGH,
                'target' => '256',
                'target_label' => '256 MB (medium site) / 512 MB (busy site)',
                'pass' => function ($v) {
                    return (int) $v >= 256;
                },
                'warn' => function ($v) {
                    return (int) $v >= 128;
                },
                'note' => 'Memory budget for compiled bytecode. WordPress + 30 plugins easily exceeds 128M.',
            ],
            [
                'key' => 'opcache.max_accelerated_files', 'label' => 'OPcache Max Files', 'category' => 'OPcache',
                'severity' => self::SEV_HIGH,
                'target' => '20000',
                'target_label' => '20000 (WP + many plugins)',
                'pass' => function ($v) {
                    return (int) $v >= 20000;
                },
                'warn' => function ($v) {
                    return (int) $v >= 4000;
                },
                'note' => 'Limits how many PHP files OPcache can keep in memory. Below 4000 you get cache thrashing.',
            ],
            [
                'key' => 'opcache.validate_timestamps', 'label' => 'OPcache Validate Timestamps', 'category' => 'OPcache',
                'severity' => $ctx['is_production'] ? self::SEV_MEDIUM : self::SEV_LOW,
                'target' => $ctx['is_production'] ? '0' : '1',
                'target_label' => $ctx['is_production'] ? 'Off (production) — biggest single perf win' : 'On (development) — sees edits without restart',
                'pass' => function ($v) use ($ctx) {
                    return $ctx['is_production'] ? in_array(strtolower($v), ['0', 'off', ''], true) : true;
                },
                'note' => 'When Off, PHP never checks if source files changed. Massive perf win in prod, frustrating in dev.',
            ],
            [
                'key' => 'opcache.jit', 'label' => 'OPcache JIT', 'category' => 'OPcache',
                'severity' => self::SEV_MEDIUM,
                'php_min' => '8.0',
                'target' => 'tracing',
                'target_label' => 'tracing — PHP 8\'s tracing JIT compiler',
                'pass' => function ($v) {
                    return !in_array(strtolower(trim($v)), ['', 'disable', 'off', '0'], true);
                },
                'note' => 'PHP 8\'s Just-In-Time compiler. Adds 5–15% on top of OPcache for typical WP loads.',
            ],
            [
                'key' => 'opcache.jit_buffer_size', 'label' => 'OPcache JIT Buffer', 'category' => 'OPcache',
                'severity' => self::SEV_LOW,
                'php_min' => '8.0',
                'target' => '256M',
                'target_label' => '256M (JIT memory budget)',
                'pass' => function ($v) {
                    return self::bytes($v) >= 64 * MB_IN_BYTES;
                },
                'warn' => function ($v) {
                    return self::bytes($v) > 0;
                },
                'note' => 'Memory the JIT can use. Zero disables JIT entirely.',
            ],
            [
                'key' => 'opcache.huge_code_pages', 'label' => 'OPcache Huge Pages', 'category' => 'OPcache',
                'severity' => self::SEV_LOW,
                'target' => '1',
                'target_label' => 'On — reduces TLB pressure on Linux',
                'pass' => function ($v) {
                    return in_array(strtolower($v), ['1', 'on'], true);
                },
                'note' => 'Maps PHP code into 2MB huge pages. Small but measurable perf win on Linux with transparent_hugepage on.',
            ],

            // ── Filesystem ──
            [
                'key' => 'realpath_cache_size', 'label' => 'Realpath Cache Size', 'category' => 'Performance',
                'severity' => self::SEV_MEDIUM,
                'target' => '4096K',
                'target_label' => '4M or more',
                'pass' => function ($v) {
                    return self::bytes($v) >= 4 * MB_IN_BYTES;
                },
                'warn' => function ($v) {
                    return self::bytes($v) >= 1 * MB_IN_BYTES;
                },
                'note' => 'Caches resolved file paths. WordPress hits the filesystem hard — small cache = slow.',
            ],
            [
                'key' => 'realpath_cache_ttl', 'label' => 'Realpath Cache TTL', 'category' => 'Performance',
                'severity' => self::SEV_LOW,
                'target' => '600',
                'target_label' => '600 seconds or more',
                'pass' => function ($v) {
                    return (int) $v >= 600;
                },
                'warn' => function ($v) {
                    return (int) $v >= 120;
                },
                'note' => 'How long resolved paths stay cached. 600 (10 min) is standard for production.',
            ],

            // ── Output & misc ──
            [
                'key' => 'output_buffering', 'label' => 'Output Buffering', 'category' => 'Performance',
                'severity' => self::SEV_LOW,
                'target' => '4096',
                'target_label' => '4096 or higher (4K buffer)',
                'pass' => function ($v) {
                    return strtolower($v) === 'on' || (int) $v >= 4096;
                },
                'warn' => function ($v) {
                    return strtolower($v) === 'on' || (int) $v >= 1024;
                },
                'note' => 'Buffers output before sending. Required by some WordPress plugins; "On" works too.',
            ],
            [
                'key' => 'date.timezone', 'label' => 'Default Timezone', 'category' => 'Configuration',
                'severity' => self::SEV_LOW,
                'target' => 'UTC',
                'target_label' => 'UTC (or your local tz) — must not be empty',
                'pass' => function ($v) {
                    return trim((string) $v) !== '';
                },
                'note' => 'When unset, PHP guesses and logs a warning on every request that uses date functions.',
            ],
        ];

        // Filter out checks that don't apply to the running PHP version.
        $php_full = $ctx['php_full'];
        return array_values(array_filter($checks, function ($c) use ($php_full) {
            if (empty($c['php_min'])) return true;
            return version_compare($php_full, $c['php_min'], '>=');
        }));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Per-check evaluation + live-data corroboration + cross-checks
    // ─────────────────────────────────────────────────────────────────

    /**
     * Run all checks against the current PHP runtime. Returns:
     *   ['checks' => [...], 'cross' => [...], 'score' => 0-100, 'grade' => 'A-F',
     *    'severity_counts' => [...], 'categories' => [...], 'context' => [...],
     *    'trend' => [...] ]
     */
    public static function run(): array {
        $ctx     = self::context();
        $results = [];
        $values  = []; // for cross-checks
        $total_w = 0;
        $earned  = 0;
        $cats    = [];
        $sev_counts = [self::SEV_CRITICAL => 0, self::SEV_HIGH => 0, self::SEV_MEDIUM => 0, self::SEV_LOW => 0];

        foreach (self::checks($ctx) as $c) {
            $raw      = ini_get($c['key']);
            $cur      = $raw === false ? '' : (string) $raw;
            $target   = $c['target'];
            $pass     = (bool) ($c['pass'])($cur, $target);
            $warn_fn  = $c['warn'] ?? null;
            $warn     = !$pass && is_callable($warn_fn) ? (bool) $warn_fn($cur, $target) : false;
            $status   = $pass ? 'pass' : ($warn ? 'warn' : 'fail');
            $weight   = self::SEV_WEIGHT[$c['severity']] ?? 2;

            $total_w += $weight;
            if ($pass)      $earned += $weight;
            elseif ($warn)  $earned += $weight * 0.5;
            else            $sev_counts[$c['severity']]++;

            $values[$c['key']] = $cur;
            $cats[$c['category']] = true;

            $results[] = [
                'key'            => $c['key'],
                'label'          => $c['label'],
                'category'       => $c['category'],
                'severity'       => $c['severity'],
                'severity_label' => self::severity_label($c['severity']),
                'target'         => $target,
                'good'           => $c['target_label'], // backward-compat key for old consumers
                'target_label'   => $c['target_label'],
                'value'          => $cur === '' ? '(not set)' : $cur,
                'status'         => $status,
                'note'           => $c['note'],
                'why'            => $c['note'],          // backward-compat
                'live_evidence'  => null,                 // filled by corroborate()
                'host_fix'       => self::host_fix_hint($ctx, $c['key']),
            ];
        }

        // Live-data corroboration upgrades status/severity based on observed reality
        self::corroborate($results, $ctx);

        // Cross-directive consistency checks
        $cross = self::consistency_checks($values);
        foreach ($cross as $x) {
            $sev_counts[$x['severity']]++;
            $weight = self::SEV_WEIGHT[$x['severity']] ?? 2;
            $total_w += $weight;
            // Cross-checks always count as failing if present (they exist BECAUSE inconsistency was found)
        }

        $score = $total_w > 0 ? (int) round($earned / $total_w * 100) : 0;
        $grade = self::grade($score);

        // Record + load trend
        $trend = self::record_and_load_trend($score);

        return [
            'checks'          => $results,
            'cross'           => $cross,
            'score'           => $score,
            'grade'           => $grade,
            'categories'      => array_keys($cats),
            'severity_counts' => $sev_counts,
            'context'         => $ctx,
            'trend'           => $trend,
        ];
    }

    /** Free-tier teaser. */
    public static function summary(): array {
        $r = self::run();
        $passes = 0; $warns = 0; $fails = 0;
        foreach ($r['checks'] as $c) {
            if ($c['status'] === 'pass') $passes++;
            elseif ($c['status'] === 'warn') $warns++;
            else $fails++;
        }
        return [
            'score'  => $r['score'],
            'grade'  => $r['grade'],
            'passes' => $passes,
            'warns'  => $warns,
            'fails'  => $fails + count($r['cross']),
            'total'  => count($r['checks']) + count($r['cross']),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Live-data corroboration — upgrade severity when reality says so
    // ─────────────────────────────────────────────────────────────────

    private static function corroborate(array &$results, array $ctx): void {
        $op   = $ctx['live_opcache']  ?? null;
        $sigs = $ctx['error_signals'] ?? [];

        foreach ($results as &$r) {
            switch ($r['key']) {
                case 'memory_limit':
                    if (!empty($sigs['memory_exhausted'])) {
                        $r['live_evidence'] = sprintf(
                            'Error log shows %d recent "Allowed memory size of … exhausted" entr%s. Your current ceiling is being hit.',
                            (int) $sigs['memory_exhausted'],
                            $sigs['memory_exhausted'] === 1 ? 'y' : 'ies'
                        );
                        if ($r['status'] !== 'fail') {
                            $r['status']         = 'fail';
                            $r['severity']       = self::SEV_CRITICAL;
                            $r['severity_label'] = self::severity_label(self::SEV_CRITICAL);
                        }
                    }
                    break;

                case 'max_execution_time':
                    if (!empty($sigs['max_time_exceeded'])) {
                        $r['live_evidence'] = sprintf(
                            'Error log shows %d "Maximum execution time exceeded" entr%s in the last 256 KB.',
                            (int) $sigs['max_time_exceeded'],
                            $sigs['max_time_exceeded'] === 1 ? 'y' : 'ies'
                        );
                        if ($r['status'] === 'pass') $r['status'] = 'warn';
                        elseif ($r['status'] === 'warn') $r['status'] = 'fail';
                    }
                    break;

                case 'max_input_vars':
                    if (!empty($sigs['max_input_vars_exceeded'])) {
                        $r['live_evidence'] = sprintf(
                            'Error log shows %d "Input variables exceeded" entr%s — forms are losing fields silently.',
                            (int) $sigs['max_input_vars_exceeded'],
                            $sigs['max_input_vars_exceeded'] === 1 ? 'y' : 'ies'
                        );
                        $r['status']         = 'fail';
                        $r['severity']       = self::SEV_CRITICAL;
                        $r['severity_label'] = self::severity_label(self::SEV_CRITICAL);
                    }
                    break;

                case 'opcache.memory_consumption':
                    if (is_array($op) && !empty($op['cache_full'])) {
                        $mem = $op['memory_usage'] ?? [];
                        $used = is_array($mem) ? (int) ($mem['used_memory'] ?? 0) : 0;
                        $r['live_evidence'] = sprintf(
                            'OPcache memory is currently FULL (%s used). New scripts can\'t be cached — they recompile every hit.',
                            size_format($used)
                        );
                        if ($r['status'] !== 'fail') {
                            $r['status']         = 'fail';
                            $r['severity']       = self::SEV_HIGH;
                            $r['severity_label'] = self::severity_label(self::SEV_HIGH);
                        }
                    } elseif (is_array($op)) {
                        $stats  = $op['opcache_statistics'] ?? [];
                        $hits   = is_array($stats) ? (int) ($stats['hits']   ?? 0) : 0;
                        $misses = is_array($stats) ? (int) ($stats['misses'] ?? 0) : 0;
                        if (($hits + $misses) > 0) {
                            $rate = $hits / ($hits + $misses) * 100;
                            if ($rate < 90 && $r['status'] === 'pass') {
                                $r['live_evidence'] = sprintf('OPcache hit rate is %.1f%% (target: 95%%+). Memory is probably undersized.', $rate);
                                $r['status']         = 'warn';
                                $r['severity']       = self::SEV_HIGH;
                                $r['severity_label'] = self::severity_label(self::SEV_HIGH);
                            }
                        }
                    }
                    break;

                case 'upload_max_filesize':
                case 'post_max_size':
                    if (!empty($sigs['upload_too_large'])) {
                        $r['live_evidence'] = 'Error log shows recent "POST Content-Length exceeds the limit" entries — uploads are being rejected.';
                        if ($r['status'] !== 'fail') {
                            $r['status']         = 'fail';
                            $r['severity']       = self::SEV_HIGH;
                            $r['severity_label'] = self::severity_label(self::SEV_HIGH);
                        }
                    }
                    break;
            }
        }
        unset($r);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Cross-directive consistency checks
    // ─────────────────────────────────────────────────────────────────

    private static function consistency_checks(array $v): array {
        $issues = [];
        $upload = self::bytes((string) ($v['upload_max_filesize'] ?? '0'));
        $post   = self::bytes((string) ($v['post_max_size']       ?? '0'));
        $mem    = self::bytes((string) ($v['memory_limit']        ?? '-1'));
        $mit    = (int) ($v['max_input_time']    ?? 0);
        $met    = (int) ($v['max_execution_time']?? 0);

        if ($upload > 0 && $post > 0 && $post < $upload) {
            $issues[] = [
                'key'      => 'cross-post-vs-upload',
                'label'    => 'post_max_size must be ≥ upload_max_filesize',
                'category' => 'Performance',
                'severity' => self::SEV_HIGH,
                'severity_label' => self::severity_label(self::SEV_HIGH),
                'reason'   => sprintf(
                    'post_max_size is %s but upload_max_filesize is %s. PHP rejects uploads as soon as the request body exceeds post_max_size — large files fail before they\'re even checked against upload_max_filesize.',
                    size_format($post), size_format($upload)
                ),
                'fix'      => sprintf('Raise post_max_size to at least %s (match upload_max_filesize, or slightly higher).', size_format($upload)),
            ];
        }
        if ($mem > 0 && $post > 0 && $mem < $post + 64 * MB_IN_BYTES) {
            $issues[] = [
                'key'      => 'cross-memory-vs-post',
                'label'    => 'memory_limit should be ≥ post_max_size + 64M headroom',
                'category' => 'Performance',
                'severity' => self::SEV_HIGH,
                'severity_label' => self::severity_label(self::SEV_HIGH),
                'reason'   => sprintf(
                    'memory_limit is %s and post_max_size is %s. PHP needs memory_limit ≥ post_max_size + parsing headroom or large uploads OOM mid-request.',
                    size_format($mem), size_format($post)
                ),
                'fix'      => sprintf('Raise memory_limit to at least %s.', size_format($post + 128 * MB_IN_BYTES)),
            ];
        }
        if ($mit > 0 && $met > 0 && $mit > $met) {
            $issues[] = [
                'key'      => 'cross-input-vs-exec',
                'label'    => 'max_input_time must not exceed max_execution_time',
                'category' => 'Performance',
                'severity' => self::SEV_MEDIUM,
                'severity_label' => self::severity_label(self::SEV_MEDIUM),
                'reason'   => sprintf(
                    'max_input_time is %ds but max_execution_time is %ds. max_input_time counts against max_execution_time — when max_input_time is larger, you get fewer effective execution seconds than you think.',
                    $mit, $met
                ),
                'fix'      => sprintf('Either lower max_input_time to ≤ %ds, or raise max_execution_time to ≥ %ds.', $met, $mit),
            ];
        }
        return $issues;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Host-aware remediation hints
    // ─────────────────────────────────────────────────────────────────

    public static function host_fix_hint(array $ctx, string $directive): ?array {
        $host = $ctx['host'] ?? null;
        if (!$host) return null;
        static $matrix = null;
        if ($matrix === null) {
            $matrix = [
                'kinsta' => [
                    '*' => [
                        'panel' => 'MyKinsta',
                        'url'   => 'https://my.kinsta.com',
                        'how'   => 'Kinsta sets PHP limits at the platform level. Open MyKinsta → Sites → [your site] → PHP Engine. Memory and execution-time limits are tied to your plan tier; for higher limits open a support ticket from MyKinsta.',
                    ],
                ],
                'wpengine' => [
                    '*' => [
                        'panel' => 'WP Engine User Portal',
                        'url'   => 'https://my.wpengine.com',
                        'how'   => 'WP Engine manages php.ini at the platform level — most directives are read-only. Open a chat support ticket from the User Portal requesting the directive change.',
                    ],
                ],
                'siteground' => [
                    '*' => [
                        'panel' => 'Site Tools',
                        'url'   => 'https://my.siteground.com',
                        'how'   => 'Site Tools → Devs → PHP Manager → PHP Variables. Find the directive, edit the value, click Confirm. Changes apply within ~1 minute.',
                    ],
                ],
                'cloudways' => [
                    '*' => [
                        'panel' => 'Cloudways Platform',
                        'url'   => 'https://platform.cloudways.com',
                        'how'   => 'Application Settings → PHP FPM Settings (top right). Edit the value and click Save Changes. Restart PHP-FPM after via Server Management → Manage Services.',
                    ],
                ],
                'pantheon' => [
                    '*' => [
                        'panel' => 'Pantheon Dashboard',
                        'url'   => 'https://dashboard.pantheon.io',
                        'how'   => 'Add a pantheon.yml entry under php_version (for version) or commit a custom php.ini in private/ — see Pantheon docs for ini-set policies.',
                    ],
                ],
                'flywheel' => [
                    '*' => [
                        'panel' => 'Flywheel',
                        'url'   => 'https://app.getflywheel.com',
                        'how'   => 'Most ini values are platform-managed. Submit a ticket through the Flywheel app for memory/exec-time raises.',
                    ],
                ],
                'liquidweb' => [
                    '*' => [
                        'panel' => 'LiquidWeb Manage',
                        'url'   => 'https://my.liquidweb.com',
                        'how'   => 'For Managed WordPress: open a support chat. For Cloud Sites: edit php.ini under your site\'s php-fpm pool, then restart PHP.',
                    ],
                ],
                'litespeed' => [
                    '*' => [
                        'panel' => 'LiteSpeed WebAdmin',
                        'url'   => null,
                        'how'   => 'LiteSpeed reads .htaccess php_value directives just like Apache. Our auto-fix can write these for you if .htaccess is writable.',
                    ],
                ],
            ];
        }
        return $matrix[$host]['*'] ?? null;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Trend tracking — last 30 days of scores
    // ─────────────────────────────────────────────────────────────────

    private static function record_and_load_trend(int $current_score): array {
        $hist = (array) get_option(self::OPT_HISTORY, []);
        $today = wp_date('Y-m-d');
        // Only update once per day to avoid noise
        $changed = !isset($hist[$today]) || $hist[$today] !== $current_score;
        if ($changed) {
            $hist[$today] = $current_score;
            if (count($hist) > 30) $hist = array_slice($hist, -30, null, true);
            update_option(self::OPT_HISTORY, $hist, false);
        }
        return self::trend_from_history($hist, $current_score);
    }

    private static function trend_from_history(array $hist, int $current): array {
        if (count($hist) < 2) {
            return ['available' => false, 'current' => $current, 'history' => $hist];
        }
        $vals = array_values($hist);
        $prev = $vals[count($vals) - 2];
        return [
            'available' => true,
            'current'   => $current,
            'previous'  => (int) $prev,
            'delta'     => $current - (int) $prev,
            'history'   => $hist,
            'arrow'     => $current > $prev ? 'up' : ($current < $prev ? 'down' : 'flat'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Utility helpers
    // ─────────────────────────────────────────────────────────────────

    private static function grade(int $score): string {
        if ($score >= 95) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 75) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 45) return 'D';
        return 'F';
    }

    public static function severity_label(string $sev): string {
        switch ($sev) {
            case self::SEV_CRITICAL:
                return 'Critical';
            case self::SEV_HIGH:
                return 'High';
            case self::SEV_MEDIUM:
                return 'Medium';
            case self::SEV_LOW:
                return 'Low';
            default:
                return ucfirst($sev);
        }
    }

    public static function severity_color(string $sev): string {
        switch ($sev) {
            case self::SEV_CRITICAL:
                return '#d63638';
            case self::SEV_HIGH:
                return '#dba617';
            case self::SEV_MEDIUM:
                return '#777BB3';
            case self::SEV_LOW:
                return '#646970';
            default:
                return '#646970';
        }
    }

    // Convert shorthand (256M, 1G) to bytes
    private static function bytes(string $val): int {
        $val  = trim($val);
        if ($val === '') return 0;
        $last = strtolower(substr($val, -1));
        $num  = (int) $val;
        switch ($last) {
            case 'g': return $num * GB_IN_BYTES;
            case 'm': return $num * MB_IN_BYTES;
            case 'k': return $num * KB_IN_BYTES;
        }
        return $num;
    }
}
