<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Update Guard — pre-update WordPress core readiness audit.
 *
 * Sibling of Phpinfo_WP_Compat, but where that one targets a PHP version,
 * this one targets a WordPress *core* version and answers "what in my
 * plugins/themes will break (or start whining) when I click Update".
 *
 * Three signals are blended into one verdict (Safe / Caution / Risky):
 *
 *   A. Static code scan (on-server, no network) — the FREE headline engine.
 *      - PHP: calls to WordPress core functions that core has deprecated
 *        (they still run but emit _deprecated_function notices and are on the
 *        path to removal).
 *      - JS: jQuery APIs removed from the jQuery bundled with modern core
 *        (WP 5.7 dropped jQuery Migrate's default load). These break only when
 *        Migrate isn't loaded, so we check the live front end once and label
 *        them "breaks" (Migrate absent) vs "deprecated" (Migrate still present)
 *        rather than crying wolf.
 *
 *   B. Metadata risk (WP.org API) — PRO. Per installed plugin/theme:
 *      "Tested up to" gap vs the target core, abandonment (last-updated age,
 *      closed listing), and requires_php vs the PHP the new core needs.
 *
 * Free vs Pro split mirrors Phpinfo_WP_Compat: the scan engine and a verdict
 * are FREE (capture demand); Pro raises the file cap, adds the WP.org metadata
 * layer, the per-item AI explanation, the pre-update interception banner on
 * the core update screen, and per-file/line drill-down.
 */
class Phpinfo_WP_Update_Audit {

    const OPT_RESULT     = 'phpinfowp_update_audit_result';
    const TRANSIENT_META = 'phpinfowp_ua_meta_';        // + md5(slug)
    const TRANSIENT_RULESET = 'phpinfowp_ua_ruleset';   // cloud ruleset cache (Pro)
    const RULESET_URL    = 'https://exeebit.com/api/update-guard/ruleset';
    const MAX_FILE_SIZE  = 1048576;                     // 1 MB — skip bigger files
    const META_TTL       = 12 * HOUR_IN_SECONDS;
    const META_MAX_LOOKUPS = 40;                        // cap WP.org calls per run

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    // -------------------------------------------------------------------------
    // Version helpers
    // -------------------------------------------------------------------------

    public static function current_wp(): string {
        return (string) get_bloginfo('version');
    }

    /** The upgrade core is offering, if any (e.g. "7.0"). Null when up to date. */
    public static function available_core_update(): ?string {
        $u = get_site_transient('update_core');
        if (is_object($u) && !empty($u->updates) && is_array($u->updates)) {
            foreach ($u->updates as $upd) {
                if (($upd->response ?? '') === 'upgrade' && !empty($upd->current)) {
                    return (string) $upd->current;
                }
            }
        }
        return null;
    }

    /**
     * What core version to audit against. An offered update wins; otherwise we
     * synthesise the next major so the user can dry-run a future jump. An
     * explicit, validated ?target overrides both.
     */
    /**
     * Retrieve the actual latest stable WordPress version from the local core update transient.
     */
    public static function latest_real_wp(): string {
        $u = get_site_transient('update_core');
        if (is_object($u) && !empty($u->updates) && is_array($u->updates)) {
            foreach ($u->updates as $upd) {
                if (!empty($upd->current)) {
                    return (string) $upd->current;
                }
            }
        }
        return self::current_wp();
    }

    private static function major_minor(string $v): string {
        if (preg_match('/^(\d+\.\d+)/', $v, $m)) {
            return $m[1];
        }
        return $v;
    }

    private static function next_major(string $v): string {
        $v = self::major_minor($v);
        if (preg_match('/^(\d+)\.(\d+)/', $v, $m)) {
            $maj = (int) $m[1]; $min = (int) $m[2];
            if ($min >= 9) return ($maj + 1) . '.0';
            return $maj . '.' . ($min + 1);
        }
        return $v;
    }

    public static function default_target(): string {
        $avail = self::available_core_update();
        if ($avail) return self::major_minor($avail);
        
        $latest_real = self::latest_real_wp();
        $current     = self::current_wp();
        
        if (version_compare($current, $latest_real, '<')) {
            return self::major_minor($latest_real);
        }
        
        return self::major_minor($current);
    }

    // -------------------------------------------------------------------------
    // Rulesets
    // -------------------------------------------------------------------------

    /**
     * WordPress core functions deprecated by a given core version. `since` is
     * the core version that deprecated them. WordPress almost never *removes*
     * functions, so these are notice-level by default — but a deprecated call
     * is the clearest static signal that a plugin is no longer maintained
     * against current core, and core has removed deprecated APIs before.
     */
    private static function baked_php_rules(): array {
        return [
            ['re' => '/(?<![\w>$])get_currentuserinfo\s*\(/',        'name' => 'get_currentuserinfo()',        'since' => '4.5', 'fix' => 'Use wp_get_current_user()'],
            ['re' => '/(?<![\w>$])get_userdatabylogin\s*\(/',        'name' => 'get_userdatabylogin()',        'since' => '3.3', 'fix' => "Use get_user_by('login', …)"],
            ['re' => '/(?<![\w>$])get_user_by_email\s*\(/',          'name' => 'get_user_by_email()',          'since' => '3.3', 'fix' => "Use get_user_by('email', …)"],
            ['re' => '/(?<![\w>$])wp_get_http\s*\(/',                'name' => 'wp_get_http()',                'since' => '4.4', 'fix' => 'Use wp_remote_get()'],
            ['re' => '/(?<![\w>$])screen_icon\s*\(/',                'name' => 'screen_icon()',                'since' => '3.8', 'fix' => 'No replacement — remove the call'],
            ['re' => '/(?<![\w>$])get_settings\s*\(/',               'name' => 'get_settings()',               'since' => '2.1', 'fix' => 'Use get_option()'],
            ['re' => '/(?<![\w>$])attribute_escape\s*\(/',           'name' => 'attribute_escape()',           'since' => '2.8', 'fix' => 'Use esc_attr()'],
            ['re' => '/(?<![\w>$])clean_url\s*\(/',                  'name' => 'clean_url()',                  'since' => '3.0', 'fix' => 'Use esc_url()'],
            ['re' => '/(?<![\w>$])js_escape\s*\(/',                  'name' => 'js_escape()',                  'since' => '2.8', 'fix' => 'Use esc_js()'],
            ['re' => '/(?<![\w>$])wp_specialchars\s*\(/',            'name' => 'wp_specialchars()',            'since' => '2.8', 'fix' => 'Use esc_html()'],
            ['re' => '/(?<![\w>$])like_escape\s*\(/',                'name' => 'like_escape()',                'since' => '4.0', 'fix' => 'Use $wpdb->esc_like()'],
            ['re' => '/(?<![\w>$])image_resize\s*\(/',               'name' => 'image_resize()',               'since' => '3.5', 'fix' => 'Use wp_get_image_editor()'],
            ['re' => '/(?<![\w>$])wp_load_image\s*\(/',              'name' => 'wp_load_image()',              'since' => '3.5', 'fix' => 'Use wp_get_image_editor()'],
            ['re' => '/(?<![\w>$])add_object_page\s*\(/',            'name' => 'add_object_page()',            'since' => '4.5', 'fix' => "Use add_menu_page()"],
            ['re' => '/(?<![\w>$])add_utility_page\s*\(/',           'name' => 'add_utility_page()',           'since' => '4.5', 'fix' => 'Use add_menu_page()'],
            ['re' => '/(?<![\w>$])wp_get_sites\s*\(/',               'name' => 'wp_get_sites()',               'since' => '4.6', 'fix' => 'Use get_sites()'],
            ['re' => '/(?<![\w>$])wp_make_content_images_responsive\s*\(/', 'name' => 'wp_make_content_images_responsive()', 'since' => '5.5', 'fix' => 'Use wp_filter_content_tags()'],
            ['re' => '/(?<![\w>$])wp_get_user_request_data\s*\(/',   'name' => 'wp_get_user_request_data()',   'since' => '4.9.6', 'fix' => 'Use wp_get_user_request()'],
            ['re' => '/(?<![\w>$])get_page_by_title\s*\(/',          'name' => 'get_page_by_title()',          'since' => '6.2', 'fix' => 'Use WP_Query'],
            ['re' => '/(?<![\w>$])get_the_author_email\s*\(/',       'name' => 'get_the_author_email()',       'since' => '2.8', 'fix' => "Use get_the_author_meta('email')"],
            // PHP4-style constructors core no longer calls (real breakage on modern PHP).
            ['re' => '/function\s+WP_Widget\s*\(/', 'def' => true,   'name' => 'PHP4-style WP_Widget constructor', 'since' => '4.3', 'fix' => 'Use __construct() and parent::__construct()'],
        ];
    }

    /**
     * jQuery APIs removed from the jQuery core bundled with modern WordPress.
     * WP 5.6 shipped jQuery 3.5.x and stopped loading jQuery Migrate by
     * default, so code using these silently fails on any current core.
     * `since` = the core version where this became a hard break.
     */
    private static function baked_js_rules(): array {
        return [
            ['re' => '/(?:\$|jQuery)\s*\([^)]*\)[^;]*\.live\s*\(/',              'name' => 'jQuery(...).live()',              'since' => '5.7', 'fix' => 'Use .on() with delegation'],
            ['re' => '/(?:\$|jQuery)\s*\([^)]*\)[^;]*\.die\s*\(/',               'name' => 'jQuery(...).die()',               'since' => '5.7', 'fix' => 'Use .off()'],
            ['re' => '/(?:\$|jQuery)\s*\([^)]*\)[^;]*\.size\s*\(\s*\)/',         'name' => 'jQuery(...).size()',              'since' => '5.7', 'fix' => 'Use .length'],
            ['re' => '/\bjQuery\.browser\b/',                                    'name' => 'jQuery.browser',                   'since' => '5.7', 'fix' => 'Feature-detect instead'],
            ['re' => '/\$\.browser\b/',                                          'name' => '$.browser',                        'since' => '5.7', 'fix' => 'Feature-detect instead'],
            ['re' => '/(?:\$|jQuery)\s*\([^)]*\)[^;]*\.andSelf\s*\(/',           'name' => 'jQuery(...).andSelf()',           'since' => '5.7', 'fix' => 'Use .addBack()'],
            ['re' => '/(?:\$|jQuery)\s*\([^)]*\)[^;]*\.toggle\s*\(\s*function/', 'name' => 'jQuery(...).toggle(fn, fn)',      'since' => '5.7', 'fix' => 'Bind click handlers manually'],
            ['re' => '/\bjQuery\.sub\s*\(/',                                     'name' => 'jQuery.sub()',                     'since' => '5.7', 'fix' => 'No replacement — refactor'],
            ['re' => '/\bjQuery\.fn\.error\s*\(/',                               'name' => '.error() event',                   'since' => '5.7', 'fix' => "Use .on('error', …)"],
            ['re' => '/\bjQuery\.parseJSON\s*\(/',                               'name' => 'jQuery.parseJSON()',               'since' => '5.7', 'fix' => 'Use JSON.parse()'],
            ['re' => '/\b\$\.parseJSON\s*\(/',                                   'name' => '$.parseJSON()',                    'since' => '5.7', 'fix' => 'Use JSON.parse()'],
            ['re' => '/\bjQuery\.isArray\s*\(/',                                 'name' => 'jQuery.isArray()',                 'since' => '5.7', 'fix' => 'Use Array.isArray()'],
            ['re' => '/\bjQuery\.trim\s*\(/',                                    'name' => 'jQuery.trim()',                    'since' => '5.7', 'fix' => 'Use String.prototype.trim()'],
        ];
    }

    // -------------------------------------------------------------------------
    // Cloud ruleset (Tier 1) — Pro pulls the latest rules from exeebit so new
    // WordPress deprecations ship without a plugin update. Falls back to the
    // baked-in sets above when free, offline, or the payload looks wrong.
    // -------------------------------------------------------------------------

    private static function php_rules(): array {
        $r = self::remote_ruleset();
        return ($r && !empty($r['php_rules'])) ? $r['php_rules'] : self::baked_php_rules();
    }

    private static function js_rules(): array {
        $r = self::remote_ruleset();
        return ($r && !empty($r['js_rules'])) ? $r['js_rules'] : self::baked_js_rules();
    }

    /** Where the active ruleset came from — drives a small UI badge. */
    public static function ruleset_source(): array {
        $r = self::remote_ruleset();
        if ($r && (!empty($r['php_rules']) || !empty($r['js_rules']))) {
            return ['source' => 'cloud', 'version' => (string) ($r['schema_version'] ?? ''), 'at' => (int) ($r['fetched_at'] ?? 0)];
        }
        return ['source' => 'built-in', 'version' => '', 'at' => 0];
    }

    /**
     * Fetch + cache the cloud ruleset (Pro only). Cached 12h on success, 1h on
     * failure so a flaky network doesn't hammer the endpoint. Returns null to
     * mean "use baked-in".
     */
    private static function remote_ruleset(): ?array {
        static $mem = null;
        if ($mem !== null) return $mem ?: null;

        if (!self::_pro()) { $mem = false; return null; }

        $cached = get_transient(self::TRANSIENT_RULESET);
        if (is_array($cached)) { $mem = $cached ?: false; return $cached ?: null; }

        $resp = wp_remote_post(self::RULESET_URL, [
            'timeout' => 6,
            'body'    => [
                'license_key' => Phpinfo_WP_License::get_key(),
                'site_url'    => get_site_url(),
                'plugin_v'    => PHPINFOWP_VERSION,
            ],
        ]);

        $data = [];
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            if (is_array($body) && (!empty($body['php_rules']) || !empty($body['js_rules']))) {
                $data = [
                    'schema_version' => $body['schema_version'] ?? 0,
                    'php_rules'      => self::sanitize_rules($body['php_rules'] ?? []),
                    'js_rules'       => self::sanitize_rules($body['js_rules'] ?? []),
                    'core_php_floor' => (isset($body['core_php_floor']) && is_array($body['core_php_floor'])) ? $body['core_php_floor'] : [],
                    'fetched_at'     => time(),
                ];
            }
        }

        // Empty array = "fetched, nothing usable" → fall back, retry in 1h.
        set_transient(self::TRANSIENT_RULESET, $data, $data ? self::META_TTL : HOUR_IN_SECONDS);
        $mem = $data ?: false;
        return $data ?: null;
    }

    /**
     * Harden remotely-supplied rules before we ever run them through preg_*:
     * enforce types, a delimiter, a sane count, and that the pattern actually
     * compiles (rejects ReDoS-flavoured junk and the removed /e modifier).
     */
    private static function sanitize_rules($rules): array {
        if (!is_array($rules)) return [];
        $out = [];
        foreach (array_slice($rules, 0, 300) as $r) {
            if (!is_array($r)) continue;
            $re    = isset($r['re'])    ? (string) $r['re']    : '';
            $name  = isset($r['name'])  ? (string) $r['name']  : '';
            $since = isset($r['since']) ? (string) $r['since'] : '';
            $fix   = isset($r['fix'])   ? (string) $r['fix']   : '';
            if ($re === '' || $name === '' || $since === '') continue;
            if (strlen($re) < 3 || $re[0] !== '/') continue;       // require /…/ form
            if (@preg_match($re, '') === false) continue;          // must compile
            $rule = ['re' => $re, 'name' => $name, 'since' => $since, 'fix' => $fix];
            if (!empty($r['def'])) $rule['def'] = true;
            $out[] = $rule;
        }
        return $out;
    }

    // -------------------------------------------------------------------------
    // Result storage
    // -------------------------------------------------------------------------

    public static function get_result(): ?array {
        $r = get_option(self::OPT_RESULT, null);
        return is_array($r) ? $r : null;
    }

    public static function clear(): void {
        delete_option(self::OPT_RESULT);
    }

    // -------------------------------------------------------------------------
    // Scan
    // -------------------------------------------------------------------------

    /**
     * Determine if a JavaScript file is compiled or bundled output.
     * Bundles (Webpack, Rollup, Vite, Gutenberg blocks) contain polyfills,
     * Map/Set implementations, and minified code that produce false positive
     * regex hits (e.g. Map.prototype.size or Set.prototype.size).
     */
    public static function is_bundled_js(string $abs, string $rel, string $filename): bool {
        // 1. Minified, chunk, or bundle filename extensions
        if (preg_match('/(\.min|\.bundle|\.chunk|\.pack)\.js$/i', $filename)) {
            return true;
        }
        // 2. Hash-based filenames like index.a7b9c3.js or app.28e93892.chunk.js
        if (preg_match('/[\.-][a-f0-9]{8,}\.js$/i', $filename)) {
            return true;
        }
        // 3. Bundled or build directory paths
        $bundle_dirs = [
            '/dist/', '\\dist\\',
            '/build/', '\\build\\',
            '/builds/', '\\builds\\',
            '/chunks/', '\\chunks\\',
            '/bundle/', '\\bundle\\',
            '/bundles/', '\\bundles\\',
            '/assets/client/', '\\assets\\client\\',
            '/assets/dist/', '\\assets\\dist\\',
            '/assets/js/dist/', '\\assets\\js\\dist\\',
            '/assets/build/', '\\assets\\build\\',
            '/compiled/', '\\compiled\\',
            '/min/', '\\min\\',
        ];
        foreach ($bundle_dirs as $d) {
            if (stripos($abs, $d) !== false) return true;
        }
        return false;
    }

    public static function scan(string $target): array {
        @set_time_limit(180);
        $started = microtime(true);

        if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $target)) {
            return ['error' => 'Invalid target WordPress version.'];
        }

        $current   = self::current_wp();
        $is_pro    = self::_pro();
        $mode      = version_compare($target, $current, '>') ? 'forward_dry_run' : 'in_place_audit';

        // Pre-filter rules to those that apply at/under the target core.
        $php_rules = array_filter(self::php_rules(), function ($r) use ($target) {
            return version_compare($target, $r['since'], '>=');
        });
        $js_rules = array_filter(self::js_rules(), function ($r) use ($target) {
            return version_compare($target, $r['since'], '>=');
        });

        // jQuery removals only *break* when jQuery Migrate isn't loaded. Migrate
        // still ships with WordPress and many themes/plugins re-enqueue it, so we
        // check the live front end once and classify as mitigated when Migrate is present.
        $jqm = $js_rules ? self::jquery_migrate_status() : 'unknown';

        $issues_by_owner = [];
        $files_scanned   = 0;
        $files_skipped   = 0;
        $owners_seen     = [];

        // Don't audit ourselves: this plugin's own source lists every WP
        // deprecated function name (as rule patterns + labels), so scanning it
        // produces a comically false report on us.
        $self_dir   = basename(rtrim(PHPINFOWP_DIR, '/\\'));
        $self_owner = 'plugin/' . $self_dir;

        // Target active plugins, active theme, and parent theme to avoid scanning dormant/inactive code
        $active_plugins = (array) get_option('active_plugins', []);
        if (is_multisite()) {
            $sitewide = (array) get_site_option('active_sitewide_plugins', []);
            $active_plugins = array_merge($active_plugins, array_keys($sitewide));
        }
        $active_owners = [];
        foreach ($active_plugins as $ap) {
            $dir = dirname($ap);
            $slug = ($dir === '.' || $dir === '') ? pathinfo($ap, PATHINFO_FILENAME) : $dir;
            $active_owners['plugin/' . $slug] = true;
        }
        if (function_exists('wp_get_theme')) {
            $theme = wp_get_theme();
            $active_owners['theme/' . $theme->get_stylesheet()] = true;
            if ($theme->parent()) {
                $active_owners['theme/' . $theme->parent()->get_template()] = true;
            }
        }

        $roots = [
            'plugin'    => WP_PLUGIN_DIR,
            'theme'     => get_theme_root(),
            'mu-plugin' => defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins',
        ];

        foreach ($roots as $type => $root) {
            if (!is_dir($root)) continue;
            try {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
            } catch (Throwable $e) { continue; }

            foreach ($it as $file) {
                if (!$file->isFile()) continue;
                $ext = strtolower($file->getExtension());
                if ($ext !== 'php' && $ext !== 'js') continue;
                if ($file->getSize() > self::MAX_FILE_SIZE) { $files_skipped++; continue; }

                $abs = $file->getPathname();
                // Exclude compiled/bundled JS (dist/, build/, assets/client/, .min.js, hash names)
                if ($ext === 'js' && self::is_bundled_js($abs, '', $file->getFilename())) {
                    $files_skipped++;
                    continue;
                }

                // Skip third-party vendor and dependency bundles to avoid noise and excessive memory usage
                if (strpos($abs, '/vendor/') !== false || strpos($abs, '\\vendor\\') !== false ||
                    strpos($abs, '/node_modules/') !== false || strpos($abs, '\\node_modules\\') !== false) {
                    continue;
                }

                $rel   = ltrim(str_replace($root, '', $abs), '/\\');
                $owner = self::owner_of($type, $rel);
                if ($owner === null || $owner === $self_owner) continue;

                // Limit plugin and theme scanning to active items (mu-plugins are always active)
                if (($type === 'plugin' || $type === 'theme') && !isset($active_owners[$owner])) {
                    continue;
                }

                $content = @file_get_contents($abs);
                if ($content === false) { $files_skipped++; continue; }
                if ($ext === 'php' && strpos($content, '<?') === false) { $files_scanned++; continue; }

                $files_scanned++;
                $owners_seen[$owner] = true;

                if ($ext === 'php') {
                    // Token-stream state machine analysis (zero comment/docblock/string false positives)
                    $file_issues = self::scan_php_tokens($content, $php_rules, $current, $rel);
                    if (!empty($file_issues)) {
                        if (!isset($issues_by_owner[$owner])) $issues_by_owner[$owner] = [];
                        $issues_by_owner[$owner] = array_merge($issues_by_owner[$owner], $file_issues);
                    }
                } else {
                    // JS scanner with jQuery Migrate awareness and two-axis classification
                    $impact = ($jqm === 'present') ? 'mitigated' : (($jqm === 'absent') ? 'breaking' : 'latent');
                    $js_sev = ($impact === 'breaking') ? 'breaks' : 'deprecated';
                    foreach ($js_rules as $rule) {
                        if (preg_match_all($rule['re'], $content, $m, PREG_OFFSET_CAPTURE)) {
                            foreach ($m[0] as $hit) {
                                $line = substr_count((string) substr($content, 0, $hit[1]), "\n") + 1;
                                $issues_by_owner[$owner][] = [
                                    'kind'             => 'js',
                                    'file'             => $rel,
                                    'line'             => $line,
                                    'name'             => $rule['name'],
                                    'fix'              => $rule['fix'],
                                    'since'            => $rule['since'],
                                    'severity'         => $js_sev,
                                    'confirmed_impact' => $impact,
                                    'evidence_type'    => 'static_pattern',
                                    'migrate'          => true,
                                    'new_now'          => version_compare($rule['since'], $current, '>'),
                                ];
                            }
                        }
                    }
                }
            }
        }

        // ---- Metadata layer (Pro) ----
        $meta = [];
        if ($is_pro) {
            $meta = self::collect_metadata($target);
        }

        // ---- Per-owner rollup + verdict ----
        $owners = [];
        foreach ($owners_seen as $owner => $_) {
            $list      = $issues_by_owner[$owner] ?? [];
            $breaking  = 0;
            $latent    = 0;
            $mitigated = 0;
            foreach ($list as $i) {
                $imp = $i['confirmed_impact'] ?? ($i['severity'] === 'breaks' ? 'breaking' : 'latent');
                if ($imp === 'breaking') {
                    $breaking++;
                } elseif ($imp === 'mitigated') {
                    $mitigated++;
                } else {
                    $latent++;
                }
            }
            $m = $meta[$owner] ?? null;
            $owners[$owner] = [
                'breaks'    => $breaking,
                'depr'      => $latent,
                'mitigated' => $mitigated,
                'meta'      => $m,
                'verdict'   => self::owner_verdict($breaking, $latent, $mitigated, $m, $mode),
                'issues'    => $list,
            ];
        }

        $total_breaking  = array_sum(array_map(function ($o) { return $o['breaks']; }, $owners));
        $total_latent    = array_sum(array_map(function ($o) { return $o['depr']; }, $owners));
        $total_mitigated = array_sum(array_map(function ($o) { return $o['mitigated'] ?? 0; }, $owners));

        // Active owners with non-clean verdict (mitigated findings do not count as broken)
        $with_issues = count(array_filter($owners, function ($o) use ($mode) {
            if ($mode === 'in_place_audit') {
                return $o['verdict'] !== 'clean';
            }
            return $o['verdict'] !== 'safe' && $o['verdict'] !== 'likely_safe';
        }));

        $result = [
            'mode'            => $mode,
            'target'          => $target,
            'current'         => $current,
            'verdict'         => self::overall_verdict($owners, $mode),
            'owners'          => $owners,
            'owner_count'     => count($owners_seen),
            'with_issues'     => $with_issues,
            'total_breaks'    => $total_breaking,
            'total_depr'      => $total_latent,
            'total_breaking'  => $total_breaking,
            'total_latent'    => $total_latent,
            'total_mitigated' => $total_mitigated,
            'files'           => $files_scanned,
            'skipped'         => $files_skipped,
            'max_files'       => 0,
            'truncated'       => false,
            'duration'        => round(microtime(true) - $started, 2),
            'scanned_at'      => time(),
            'is_pro_result'   => $is_pro,
            'meta_checked'    => $is_pro,
            'jquery_migrate'  => $jqm,
        ];

        update_option(self::OPT_RESULT, $result, false);
        return $result;
    }

    private static function owner_of(string $type, string $rel): ?string {
        $parts = preg_split('#[\\\\/]+#', $rel);
        if (!$parts) return null;
        $first = $parts[0];
        if ($first === '' || strncmp($first, '.', 1) === 0) return null;
        // Themes always live in their own directory (style.css inside). A loose
        // file in the themes root — like WordPress's "silence is golden"
        // index.php — is not a theme.
        if ($type === 'theme') {
            return count($parts) >= 2 ? 'theme/' . $first : null;
        }
        // A single top-level .php file is a single-file plugin (e.g. Hello Dolly),
        // EXCEPT the WordPress drop-in silence guard, which isn't a plugin.
        if (count($parts) === 1) {
            return strtolower($first) === 'index.php' ? null : $type . '/' . pathinfo($first, PATHINFO_FILENAME);
        }
        return $type . '/' . $first;
    }

    /**
     * Is jQuery Migrate actually loaded on the front end? One cached loopback
     * request, parsed for the migrate script handle. 'present' | 'absent' |
     * 'unknown' (couldn't fetch — we then avoid the alarmist "breaks" label).
     */
    public static function jquery_migrate_status(): string {
        $cached = get_transient('phpinfowp_ua_jqm');
        if (is_string($cached) && $cached !== '') return $cached;

        $resp = wp_remote_get(home_url('/'), [
            'timeout'    => 6,
            'sslverify'  => false,
            'user-agent' => 'phpinfo-wp Update Guard',
        ]);
        $status = 'unknown';
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) < 400) {
            $body   = (string) wp_remote_retrieve_body($resp);
            $status = stripos($body, 'jquery-migrate') !== false ? 'present' : 'absent';
        }
        set_transient('phpinfowp_ua_jqm', $status, 6 * HOUR_IN_SECONDS);
        return $status;
    }

    // -------------------------------------------------------------------------
    // Verdict logic
    // -------------------------------------------------------------------------

    // A single plugin/theme:
    // In in_place_audit mode:
    //   - 'active_issue' if confirmed breaking, hard fatal, or PHP floor blocker
    //   - 'latent_debt' if latent/unmitigated deprecations exist
    //   - 'clean' if running cleanly or only mitigated findings
    // In forward_dry_run mode:
    //   - 'risky' if breaking issues, abandoned, or PHP blocker
    //   - 'caution' if meaningful tested gap or deprecations with no shim
    //   - 'likely_safe' if only latent/mitigated findings
    //   - 'safe' if no findings
    private static function owner_verdict(int $breaks, int $depr, int $mitigated, ?array $meta, string $mode = 'forward_dry_run'): string {
        if ($mode === 'in_place_audit') {
            if ($breaks > 0 || !empty($meta['php_blocks'])) {
                return 'active_issue';
            }
            if ($depr > 0) {
                return 'latent_debt';
            }
            // Mitigated findings (e.g. jQuery Migrate covering jQuery calls) or 0 issues
            return 'clean';
        }

        // Forward dry run (target > current)
        if ($breaks > 0 || !empty($meta['abandoned']) || !empty($meta['php_blocks'])) {
            return 'risky';
        }
        if ($depr > 0 || ((int) ($meta['tested_gap'] ?? 0) >= 1)) {
            return 'caution';
        }
        if ($mitigated > 0) {
            return 'likely_safe';
        }
        return 'safe';
    }

    private static function overall_verdict(array $owners, string $mode = 'forward_dry_run'): string {
        if ($mode === 'in_place_audit') {
            $worst = 'clean';
            foreach ($owners as $o) {
                if ($o['verdict'] === 'active_issue') return 'active_issue';
                if ($o['verdict'] === 'latent_debt')  $worst = 'latent_debt';
            }
            return $worst;
        }

        // Forward dry run
        $worst = 'safe';
        foreach ($owners as $o) {
            if ($o['verdict'] === 'risky')   return 'risky';
            if ($o['verdict'] === 'caution') $worst = 'caution';
            elseif ($o['verdict'] === 'likely_safe' && $worst === 'safe') $worst = 'likely_safe';
        }
        return $worst;
    }

    // -------------------------------------------------------------------------
    // Metadata layer (Pro) — WP.org plugin/theme directory
    // -------------------------------------------------------------------------

    private static function collect_metadata(string $target): array {
        $out      = [];
        $lookups  = 0;
        $php_need = self::core_php_requirement($target);
        // Hard wall-clock budget so a slow/unreachable WP.org never hangs the
        // scan. Cached slugs are near-instant; only live lookups burn the clock.
        $deadline = microtime(true) + 20.0;

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = function_exists('get_plugins') ? get_plugins() : [];
        $active_plugins = (array) get_option('active_plugins', []);
        if (is_multisite()) {
            $sitewide = (array) get_site_option('active_sitewide_plugins', []);
            $active_plugins = array_merge($active_plugins, array_keys($sitewide));
        }
        foreach ($active_plugins as $file) {
            if (!isset($plugins[$file])) continue;
            $data = $plugins[$file];
            if ($lookups >= self::META_MAX_LOOKUPS || microtime(true) > $deadline) break;
            $slug  = self::plugin_slug($file);
            $owner = 'plugin/' . dirname($file);
            if (dirname($file) === '.') $owner = 'plugin/' . pathinfo($file, PATHINFO_FILENAME);
            $info  = self::wporg_plugin($slug);
            $lookups++;
            $out[$owner] = self::score_meta($info, $target, $php_need, $data);
        }

        // Active theme (and parent) — themes break visibly, so always worth a look.
        if (function_exists('wp_get_theme')) {
            $theme = wp_get_theme();
            foreach (array_filter([$theme, $theme->parent() ?: null]) as $t) {
                if ($lookups >= self::META_MAX_LOOKUPS || microtime(true) > $deadline) break;
                $slug  = $t->get_stylesheet();
                $owner = 'theme/' . $slug;
                $info  = self::wporg_theme($slug);
                $lookups++;
                $out[$owner] = self::score_meta($info, $target, $php_need, [
                    'RequiresPHP' => $t->get('RequiresPHP'),
                ]);
            }
        }
        return $out;
    }

    /** Best-effort: what PHP the target core needs. Cloud floor map wins. */
    private static function core_php_requirement(string $target): string {
        $r = self::remote_ruleset();
        if ($r && !empty($r['core_php_floor']) && is_array($r['core_php_floor'])) {
            $floor = $r['core_php_floor'];
            $best  = $floor['default'] ?? '';
            $best_v = '';
            foreach ($floor as $wp => $php) {
                if ($wp === 'default' || !preg_match('/^\d+\.\d+/', (string) $wp)) continue;
                if (version_compare($target, (string) $wp, '>=') && version_compare((string) $wp, $best_v ?: '0', '>=')) {
                    $best   = (string) $php;
                    $best_v = (string) $wp;
                }
            }
            if ($best !== '') return $best;
        }
        // Baked fallback: WP 6.x needs PHP 7.2.24+; WP 7.0 raises it to 7.4.
        if (version_compare($target, '7.0', '>=')) return '7.4';
        return '7.2.24';
    }

    private static function plugin_slug(string $file): string {
        $dir = dirname($file);
        return $dir === '.' ? pathinfo($file, PATHINFO_FILENAME) : $dir;
    }

    private static function score_meta(?array $info, string $target, string $php_need, array $headers): array {
        // No directory listing → likely premium/custom; we can't crowd-judge it,
        // but the local RequiresPHP header still tells us about the PHP floor.
        $tested     = $info['tested']       ?? '';
        $updated    = $info['last_updated'] ?? '';
        $closed     = !empty($info['closed']);
        $req_php    = $headers['RequiresPHP'] ?? ($info['requires_php'] ?? '');

        // "Tested up to" gap, measured in WP minor releases (6.x ↔ 7.x bridged
        // by treating each major as 10 minors). 3+ minors behind → caution,
        // 6+ behind (e.g. tested 6.2, updating to 7.0) → strong signal.
        $tested_gap = 0;
        if ($tested && preg_match('/^(\d+)\.(\d+)/', $tested, $tm)
            && preg_match('/^(\d+)\.(\d+)/', $target, $gm)) {
            $diff = ((int) $gm[1] * 10 + (int) $gm[2]) - ((int) $tm[1] * 10 + (int) $tm[2]);
            $tested_gap = $diff >= 6 ? 2 : ($diff >= 3 ? 1 : 0);
        }

        $stale_days = $updated ? (int) floor((time() - strtotime($updated)) / DAY_IN_SECONDS) : null;
        $abandoned  = $closed || ($stale_days !== null && $stale_days > 730); // 2 years

        // The plugin requires a PHP newer than this site currently runs — so it
        // is already (or will be) incompatible regardless of the core jump.
        $php_blocks = $req_php && version_compare(PHP_VERSION, $req_php, '<');

        return [
            'in_directory' => $info !== null,
            'tested'       => $tested,
            'tested_gap'   => $tested_gap,
            'last_updated' => $updated,
            'stale_days'   => $stale_days,
            'abandoned'    => $abandoned,
            'requires_php' => $req_php,
            'php_need'     => $php_need,
            'php_blocks'   => $php_blocks,
        ];
    }

    private static function wporg_plugin(string $slug): ?array {
        $cached = get_transient(self::TRANSIENT_META . md5('p:' . $slug));
        if (is_array($cached)) return $cached ?: null;

        $url = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information'
             . '&request[slug]=' . rawurlencode($slug)
             . '&request[fields][tested]=1&request[fields][requires_php]=1&request[fields][last_updated]=1';
        $resp = wp_remote_get($url, ['timeout' => 5]);
        $info = self::parse_wporg($resp);
        set_transient(self::TRANSIENT_META . md5('p:' . $slug), $info ?: [], self::META_TTL);
        return $info;
    }

    private static function wporg_theme(string $slug): ?array {
        $cached = get_transient(self::TRANSIENT_META . md5('t:' . $slug));
        if (is_array($cached)) return $cached ?: null;

        $url = 'https://api.wordpress.org/themes/info/1.2/?action=theme_information'
             . '&request[slug]=' . rawurlencode($slug)
             . '&request[fields][tested]=1&request[fields][last_updated]=1';
        $resp = wp_remote_get($url, ['timeout' => 5]);
        $info = self::parse_wporg($resp);
        set_transient(self::TRANSIENT_META . md5('t:' . $slug), $info ?: [], self::META_TTL);
        return $info;
    }

    private static function parse_wporg($resp): ?array {
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($body) || isset($body['error'])) return null;
        return [
            'tested'       => (string) ($body['tested']       ?? ''),
            'requires_php' => (string) ($body['requires_php'] ?? ''),
            'last_updated' => (string) ($body['last_updated'] ?? ''),
            'closed'       => !empty($body['closed']),
        ];
    }

    // -------------------------------------------------------------------------
    // Pre-update interception (Pro) — banner on the core update screen
    // -------------------------------------------------------------------------

    public static function register(): void {
        add_action('admin_notices', [self::class, 'maybe_intercept']);
        add_action('wp_ajax_phpinfowp_ua_scan',  [self::class, 'ajax_scan']);
        add_action('wp_ajax_phpinfowp_ua_clear', [self::class, 'ajax_clear']);
    }

    public static function ajax_scan(): void {
        check_ajax_referer('phpinfowp_ua_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        $target = sanitize_text_field($_POST['target'] ?? '');
        if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $target)) {
            $target = self::default_target();
        }

        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::start('core_audit', ['target' => $target]);
        }

        $result = self::scan($target);
        if (isset($result['error'])) {
            if (class_exists('Phpinfo_WP_Background_Scan')) {
                Phpinfo_WP_Background_Scan::clear('core_audit');
            }
            wp_send_json_error(['message' => $result['error']]);
        }

        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::complete('core_audit', [
                'target' => $target,
                'files'  => $result['files'] ?? 0,
            ]);
        }

        wp_send_json_success($result);
    }

    public static function ajax_clear(): void {
        check_ajax_referer('phpinfowp_ua_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        self::clear();
        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::clear('core_audit');
        }
        wp_send_json_success();
    }

    public static function maybe_intercept(): void {
        if (!current_user_can('update_core')) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $base   = $screen ? $screen->id : '';
        if ($base !== 'update-core' && $base !== 'dashboard') return;

        $avail = self::available_core_update();
        if (!$avail) return; // nothing to update to — stay quiet

        $audit_url = admin_url('admin.php?page=piwp-update-audit&target=' . rawurlencode($avail));
        $result    = self::get_result();
        $fresh     = $result && ($result['target'] ?? '') === $avail
                     && (time() - (int) $result['scanned_at']) < WEEK_IN_SECONDS;

        // Free users get a soft nudge — only on the Updates screen, never on
        // the main Dashboard (that would nag on every login). The live verdict
        // banner and the Dashboard placement are the Pro perk.
        if (!self::_pro()) {
            if ($base !== 'update-core') return;
            printf(
                '<div class="notice notice-info piwp-notice"><p><strong>phpinfo() WP:</strong> WordPress %s is available. '
                . '<a href="%s">Run a pre-update audit</a> to see which plugins/themes may break. '
                . '<a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank">Pro</a> warns you here automatically before every core update.</p></div>',
                esc_html($avail), esc_url($audit_url)
            );
            return;
        }

        if (!$fresh) {
            printf(
                '<div class="notice notice-warning piwp-notice"><p><strong>Update Guard:</strong> WordPress %s is available but you haven\'t audited for it yet. '
                . '<a href="%s">Run the pre-update audit →</a></p></div>',
                esc_html($avail), esc_url($audit_url)
            );
            return;
        }

        $v = $result['verdict'];
        if ($v === 'safe') {
            printf(
                '<div class="notice notice-success piwp-notice"><p><strong>Update Guard:</strong> audited against WordPress %s — no breakages detected across %d plugins/themes. Safe to update. <a href="%s">View report</a></p></div>',
                esc_html($avail), (int) $result['owner_count'], esc_url($audit_url)
            );
        } else {
            $color = $v === 'risky' ? 'error' : 'warning';
            $word  = $v === 'risky' ? 'likely to break' : 'may need attention';
            printf(
                '<div class="notice notice-%s piwp-notice"><p><strong>Update Guard:</strong> %d plugin(s)/theme(s) are %s on WordPress %s '
                . '(%d hard breaks, %d deprecations). <a href="%s">Review before updating →</a></p></div>',
                esc_attr($color), (int) $result['with_issues'], esc_html($word), esc_html($avail),
                (int) $result['total_breaks'], (int) $result['total_depr'], esc_url($audit_url)
            );
        }
    }

    /**
     * Token-stream state machine scanner for PHP files.
     * Uses native ext-tokenizer (token_get_all) to accurately audit PHP code
     * without false positives from comments, docblocks, strings, or object methods.
     */
    private static function scan_php_tokens(string $content, array $php_rules, string $current_wp, string $rel): array {
        $issues = [];
        $tokens = @token_get_all($content);
        if (!is_array($tokens)) return [];

        // Build fast symbol lookup tables
        $func_lookup = [];
        $def_lookup  = [];

        foreach ($php_rules as $r) {
            if (!empty($r['def'])) {
                if (preg_match('/function(?:\\\\s\+|[\s\t]+)([A-Za-z0-9_]+)/i', $r['re'], $m)) {
                    $def_lookup[strtolower($m[1])] = $r;
                }
            } else {
                $clean = strtolower(rtrim($r['name'], '()'));
                $func_lookup[$clean] = $r;
            }
        }

        $count = count($tokens);
        $prev_token_id  = null;
        $brace_depth    = 0;
        $guard_depth    = null;
        $in_func_exists = false;

        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];

            if (is_string($t)) {
                if ($t === '{') {
                    $brace_depth++;
                } elseif ($t === '}') {
                    $brace_depth--;
                    if ($guard_depth !== null && $brace_depth < $guard_depth) {
                        $guard_depth = null; // Exited polyfill guard block
                    }
                }
                $prev_token_id = $t;
                continue;
            }

            $id   = $t[0];
            $text = $t[1];
            $line = $t[2];

            // Ignore whitespace, comments, docblocks, and inline HTML (kills 100% of docblock false alarms)
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT || $id === T_INLINE_HTML) {
                continue;
            }

            // Track polyfill detection: if (!function_exists('...'))
            if ($id === T_STRING && strtolower($text) === 'function_exists') {
                $in_func_exists = true;
            } elseif ($in_func_exists && $id === T_CONSTANT_ENCAPSED_STRING) {
                $fn_name = strtolower(trim($text, "'\""));
                if (isset($func_lookup[$fn_name])) {
                    $guard_depth = $brace_depth + 1;
                }
                $in_func_exists = false;
            } elseif ($id === T_STRING || (defined('T_NAME_FULLY_QUALIFIED') && $id === T_NAME_FULLY_QUALIFIED)) {
                $lower = strtolower(ltrim($text, '\\'));

                // 1. Check for function definition rules (e.g. PHP4 WP_Widget constructor)
                if (isset($def_lookup[$lower]) && $prev_token_id === T_FUNCTION) {
                    $next_idx = self::next_meaningful_token($tokens, $i + 1, $count);
                    if ($next_idx !== null && $tokens[$next_idx] === '(') {
                        $rule = $def_lookup[$lower];
                        $issues[] = [
                            'kind'             => 'php',
                            'file'             => $rel,
                            'line'             => $line,
                            'name'             => $rule['name'],
                            'fix'              => $rule['fix'],
                            'since'            => $rule['since'],
                            'severity'         => 'breaks',
                            'confirmed_impact' => 'breaking',
                            'evidence_type'    => 'static_pattern',
                            'migrate'          => false,
                            'new_now'          => version_compare($rule['since'], $current_wp, '>'),
                        ];
                    }
                }

                // 2. Check for deprecated global function calls
                if (isset($func_lookup[$lower])) {
                    // Skip if inside a polyfill guard block
                    if ($guard_depth === null || $brace_depth < $guard_depth) {
                        $is_definition  = ($prev_token_id === T_FUNCTION);
                        $is_method      = ($prev_token_id === T_OBJECT_OPERATOR || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $prev_token_id === T_NULLSAFE_OBJECT_OPERATOR));
                        $is_static      = ($prev_token_id === T_DOUBLE_COLON);
                        $is_non_root_ns = false;

                        if ($prev_token_id === T_NS_SEPARATOR) {
                            $before_ns = self::prev_meaningful_token($tokens, $i - 1);
                            if ($before_ns !== null && is_array($tokens[$before_ns]) && $tokens[$before_ns][0] === T_STRING) {
                                $is_non_root_ns = true;
                            }
                        }

                        if (!$is_definition && !$is_method && !$is_static && !$is_non_root_ns) {
                            $next_idx = self::next_meaningful_token($tokens, $i + 1, $count);
                            if ($next_idx !== null && $tokens[$next_idx] === '(') {
                                $rule = $func_lookup[$lower];
                                $issues[] = [
                                    'kind'             => 'php',
                                    'file'             => $rel,
                                    'line'             => $line,
                                    'name'             => $rule['name'],
                                    'fix'              => $rule['fix'],
                                    'since'            => $rule['since'],
                                    'severity'         => 'deprecated',
                                    'confirmed_impact' => 'latent',
                                    'evidence_type'    => 'static_pattern',
                                    'migrate'          => false,
                                    'new_now'          => version_compare($rule['since'], $current_wp, '>'),
                                ];
                            }
                        }
                    }
                }
            }

            $prev_token_id = $id;
        }

        unset($tokens);
        return $issues;
    }

    private static function next_meaningful_token(array &$tokens, int $start, int $count): ?int {
        for ($j = $start; $j < $count; $j++) {
            $t = $tokens[$j];
            if (is_array($t)) {
                $id = $t[0];
                if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) continue;
            }
            return $j;
        }
        return null;
    }

    private static function prev_meaningful_token(array &$tokens, int $start): ?int {
        for ($j = $start - 1; $j >= 0; $j--) {
            $t = $tokens[$j];
            if (is_array($t)) {
                $id = $t[0];
                if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) continue;
            }
            return $j;
        }
        return null;
    }
}
