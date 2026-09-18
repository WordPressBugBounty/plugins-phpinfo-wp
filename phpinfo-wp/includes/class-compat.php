<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Compat {

    const OPT_RESULT  = 'phpinfowp_compat_result';
    const OPT_IGNORED = 'phpinfowp_compat_ignored';
    const TARGETS     = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];
    const MAX_FILE_SIZE = 1048576;

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    private static function rules(): array {
        return [
            'func_exact' => [
                'split'                => ['name' => 'split()', 'in' => '5.3', 'out' => '7.0', 'severity' => 'removed', 'fix' => 'Use preg_split() or explode()'],
                'sql_regcase'          => ['name' => 'sql_regcase()', 'in' => '5.3', 'out' => '7.0', 'severity' => 'removed', 'fix' => 'No replacement — build the pattern manually'],
                'create_function'      => ['name' => 'create_function()', 'in' => '7.2', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Use anonymous functions (closures)'],
                'each'                 => ['name' => 'each()', 'in' => '7.2', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Use foreach()'],
                'get_magic_quotes_gpc' => ['name' => 'get_magic_quotes_gpc()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Always returns false in 7.4+; remove the call'],
                'get_magic_quotes_runtime' => ['name' => 'get_magic_quotes_runtime()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Always returns false in 7.4+; remove the call'],
                'money_format'         => ['name' => 'money_format()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Use NumberFormatter from the intl extension'],
                'image2wbmp'           => ['name' => 'image2wbmp()', 'in' => '7.3', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Use imagewbmp()'],
                'convert_cyr_string'   => ['name' => 'convert_cyr_string()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Use iconv() or mb_convert_encoding()'],
                'hebrevc'              => ['name' => 'hebrevc()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Use hebrev() + nl2br()'],
                'is_real'              => ['name' => 'is_real()', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Use is_float()'],
                'restore_include_path' => ['name' => 'restore_include_path() with args', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Call with no arguments'],
                'strftime'             => ['name' => 'strftime()', 'in' => '8.1', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use IntlDateFormatter::format() or date_format()'],
                'gmstrftime'           => ['name' => 'gmstrftime()', 'in' => '8.1', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use IntlDateFormatter with UTC timezone'],
                'date_sunrise'         => ['name' => 'date_sunrise()', 'in' => '8.1', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use date_sun_info()'],
                'date_sunset'          => ['name' => 'date_sunset()', 'in' => '8.1', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use date_sun_info()'],
                'utf8_encode'          => ['name' => 'utf8_encode()', 'in' => '8.2', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use mb_convert_encoding($s, "UTF-8", "ISO-8859-1")'],
                'utf8_decode'          => ['name' => 'utf8_decode()', 'in' => '8.2', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use mb_convert_encoding($s, "ISO-8859-1", "UTF-8")'],
                'get_class'            => ['name' => 'get_class() with no args', 'in' => '8.3', 'out' => '8.4', 'severity' => 'deprecated', 'fix' => 'Use get_class($this) or self::class'],
                'get_parent_class'     => ['name' => 'get_parent_class() with no args', 'in' => '8.3', 'out' => '8.4', 'severity' => 'deprecated', 'fix' => 'Use get_parent_class($this) or parent::class'],
                'xml_set_object'       => ['name' => 'xml_set_object()', 'in' => '8.4', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Pass callable directly to xml_set_*_handler()'],
            ],
            'func_prefix' => [
                'mysql_'  => ['name' => 'mysql_*()', 'in' => '5.5', 'out' => '7.0', 'severity' => 'removed', 'fix' => 'Use mysqli_* or PDO'],
                'ereg'    => ['name' => 'ereg_*()', 'in' => '5.3', 'out' => '7.0', 'severity' => 'removed', 'fix' => 'Use preg_* equivalents'],
                'mcrypt_' => ['name' => 'mcrypt_*()', 'in' => '7.1', 'out' => '7.2', 'severity' => 'removed', 'fix' => 'Use openssl_encrypt/decrypt or sodium_crypto_*'],
                'mhash'   => ['name' => 'mhash_*()', 'in' => '8.1', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use hash_* functions'],
            ],
            'special' => [
                'real_cast'  => ['name' => '(real) cast', 'in' => '7.4', 'out' => '8.0', 'severity' => 'removed', 'fix' => 'Use (float) instead'],
                'dollar_brace'=>['name' => '${var} string interpolation', 'in' => '8.2', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use {$var} syntax'],
                'short_open' => ['name' => 'Short open tag <?', 'in' => '7.4', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use <?php — short_open_tag may be off'],
            ]
        ];
    }

    public static function targets(): array { return self::TARGETS; }

    public static function get_result(): ?array {
        $r = get_option(self::OPT_RESULT, null);
        return is_array($r) ? $r : null;
    }

    public static function clear(): void {
        delete_option(self::OPT_RESULT);
    }

    public static function get_ignored(): array {
        $ign = get_option(self::OPT_IGNORED, []);
        return is_array($ign) ? $ign : [];
    }

    public static function ignore_issue(string $hash): void {
        $ign = self::get_ignored();
        $ign[$hash] = time();
        update_option(self::OPT_IGNORED, $ign, false);
    }

    public static function unignore_issue(string $hash): void {
        $ign = self::get_ignored();
        unset($ign[$hash]);
        update_option(self::OPT_IGNORED, $ign, false);
    }

    public static function scan(string $target = '8.4'): array {
        @set_time_limit(180);
        $started = microtime(true);

        $current_minor = (float)(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);
        $target_minor  = (float)$target;
        $is_delta_scan = ($target_minor > $current_minor);

        $rules = self::filter_rules($target);
        if (!$rules) return ['error' => 'Target version invalid.'];

        $ignored_hashes = self::get_ignored();
        $issues_by_owner = [];
        $files_scanned   = 0;
        $files_skipped   = 0;
        $owners_seen     = [];

        $roots = [
            'plugins'    => WP_PLUGIN_DIR,
            'themes'     => get_theme_root(),
            'mu-plugins' => defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins',
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
                if (strtolower($file->getExtension()) !== 'php') continue;
                if ($file->getSize() > self::MAX_FILE_SIZE) { $files_skipped++; continue; }

                $abs = $file->getPathname();
                $rel = ltrim(str_replace($root, '', $abs), '/\\');
                $owner = self::owner_of($type, $rel);
                if ($owner === null) continue;

                $content = @file_get_contents($abs);
                if ($content === false) { $files_skipped++; continue; }
                if (strpos($content, '<?') === false) { $files_scanned++; continue; }

                $files_scanned++;
                $owners_seen[$owner] = true;

                // Smart Tokenizer Loop
                try {
                    $tokens = @token_get_all($content);
                } catch (Throwable $e) {
                    continue;
                }
                
                $count = count($tokens);
                for ($i = 0; $i < $count; $i++) {
                    $t = $tokens[$i];
                    if (!is_array($t)) continue;
                    
                    $token_id = $t[0];
                    $text     = $t[1];
                    $line     = $t[2];
                    
                    $match_rule = null;
                    $snippet = $text;

                    if ($token_id === T_STRING) {
                        $lower = strtolower($text);
                        
                        // Look backwards to ensure it's not a method call ($obj->func) or declaration
                        $prev_is_safe = false;
                        for ($j = $i - 1; $j >= 0; $j--) {
                            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) continue;
                            if (is_array($tokens[$j])) {
                                $pt = $tokens[$j][0];
                                if ($pt === T_OBJECT_OPERATOR || $pt === T_DOUBLE_COLON || $pt === T_FUNCTION || $pt === T_NEW) {
                                    $prev_is_safe = true;
                                }
                            }
                            break;
                        }
                        if ($prev_is_safe) continue;

                        // Look forwards to ensure it has opening parentheses
                        $next_is_paren = false;
                        $paren_idx = -1;
                        for ($j = $i + 1; $j < $count; $j++) {
                            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) continue;
                            if ($tokens[$j] === '(') {
                                $next_is_paren = true;
                                $paren_idx = $j;
                            }
                            break;
                        }
                        if (!$next_is_paren) continue;

                        // Argument inspection: only calls with ZERO arguments are deprecated for get_class / get_parent_class
                        if (in_array($lower, ['get_class', 'get_parent_class'], true)) {
                            $has_arg = false;
                            for ($k = $paren_idx + 1; $k < $count; $k++) {
                                $tok_k = $tokens[$k];
                                if (is_array($tok_k) && in_array($tok_k[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                                    continue;
                                }
                                if ($tok_k === ')') {
                                    $has_arg = false;
                                } else {
                                    $has_arg = true;
                                }
                                break;
                            }
                            // get_class($this) and get_class($object) are 100% valid in PHP 8.4+
                            if ($has_arg) continue;
                        } elseif ($lower === 'restore_include_path') {
                            $has_arg = false;
                            for ($k = $paren_idx + 1; $k < $count; $k++) {
                                $tok_k = $tokens[$k];
                                if (is_array($tok_k) && in_array($tok_k[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                                    continue;
                                }
                                if ($tok_k === ')') {
                                    $has_arg = false;
                                } else {
                                    $has_arg = true;
                                }
                                break;
                            }
                            // restore_include_path() with no args is valid in PHP 8.0+
                            if (!$has_arg) continue;
                        }

                        // Comprehensive Guard Check
                        if (self::is_guarded($tokens, $i, $lower, $content, $rel)) {
                            continue;
                        }

                        if (isset($rules['func_exact'][$lower])) {
                            $match_rule = $rules['func_exact'][$lower];
                            $snippet = $text . '()';
                        } else {
                            foreach ($rules['func_prefix'] as $prefix => $rule) {
                                if (strpos($lower, $prefix) === 0) {
                                    $match_rule = $rule;
                                    $snippet = $text . '()';
                                    break;
                                }
                            }
                        }

                    } elseif ($token_id === T_DOUBLE_CAST) {
                        if (strpos(strtolower($text), 'real') !== false && isset($rules['special']['real_cast'])) {
                            $match_rule = $rules['special']['real_cast'];
                            $snippet = '(real)';
                        }
                    } elseif ($token_id === T_DOLLAR_OPEN_CURLY_BRACES) {
                        if (isset($rules['special']['dollar_brace'])) {
                            $match_rule = $rules['special']['dollar_brace'];
                            $snippet = '${';
                        }
                    } elseif ($token_id === T_OPEN_TAG) {
                        if (trim($text) === '<?' && isset($rules['special']['short_open'])) {
                            $match_rule = $rules['special']['short_open'];
                            $snippet = '<?';
                        }
                    }

                    if ($match_rule) {
                        $hash = md5($rel . ':' . $line . ':' . $match_rule['name']);
                        $is_ignored = !empty($ignored_hashes[$hash]);

                        $issues_by_owner[$owner][] = [
                            'hash'     => $hash,
                            'file'     => $rel,
                            'line'     => $line,
                            'match'    => $snippet,
                            'name'     => $match_rule['name'],
                            'fix'      => $match_rule['fix'],
                            'severity' => $match_rule['severity'],
                            'in'       => $match_rule['in'],
                            'out'      => $match_rule['out'],
                            'ignored'  => $is_ignored,
                        ];
                    }
                }
            }
        }

        $total = 0;
        $total_removed = 0;
        $total_deprecated = 0;
        $owners_with_removed = 0;
        $owners_with_deprecated = 0;

        foreach ($issues_by_owner as $owner => $list) {
            $has_rem = false;
            $has_dep = false;
            foreach ($list as $iss) {
                if (!empty($iss['ignored'])) continue;
                $total++;
                if (($iss['severity'] ?? '') === 'removed') {
                    $total_removed++;
                    $has_rem = true;
                } else {
                    $total_deprecated++;
                    $has_dep = true;
                }
            }
            if ($has_rem) {
                $owners_with_removed++;
            } elseif ($has_dep) {
                $owners_with_deprecated++;
            }
        }

        // Single-Verdict Arbitration (Update Guard Model)
        if ($total_removed > 0) {
            $verdict = 'incompatible'; // Tier 1: Fatal breaking changes
            $verdict_label = sprintf(_n('%d BREAKING', '%d BREAKING', $total_removed, 'phpinfo-wp'), $total_removed);
        } elseif ($total_deprecated > 0) {
            $verdict = 'deprecated';   // Tier 2: Non-breaking deprecation notices (Upgrade Safe)
            $verdict_label = sprintf(_n('%d DEPRECATED', '%d DEPRECATED', $total_deprecated, 'phpinfo-wp'), $total_deprecated);
        } else {
            $verdict = 'passed';       // Tier 3: Clean
            $verdict_label = __('PASSED', 'phpinfo-wp');
        }

        $result = [
            'target'                 => $target,
            'current_php'            => PHP_VERSION,
            'is_delta_scan'          => $is_delta_scan,
            'total'                  => $total,
            'total_removed'          => $total_removed,
            'total_deprecated'       => $total_deprecated,
            'verdict'                => $verdict,
            'verdict_label'          => $verdict_label,
            'owners_with_removed'    => $owners_with_removed,
            'owners_with_deprecated' => $owners_with_deprecated,
            'owners'                 => count($owners_seen),
            'with_issues'            => count($issues_by_owner),
            'files'                  => $files_scanned,
            'skipped'                => $files_skipped,
            'truncated'              => false,
            'duration'               => round(microtime(true) - $started, 2),
            'scanned_at'             => time(),
            'issues'                 => $issues_by_owner,
            'is_pro_result'          => self::_pro(),
        ];

        update_option(self::OPT_RESULT, $result, false);
        return $result;
    }

    /**
     * Compute single-verdict arbitration from a scan result.
     */
    public static function verdict(array $result): array {
        $total_removed = (int) ($result['total_removed'] ?? 0);
        $total_deprecated = (int) ($result['total_deprecated'] ?? 0);

        if (!isset($result['total_removed']) && !empty($result['issues'])) {
            foreach ($result['issues'] as $list) {
                foreach ($list as $iss) {
                    if (!empty($iss['ignored'])) continue;
                    if (($iss['severity'] ?? '') === 'removed') $total_removed++;
                    else $total_deprecated++;
                }
            }
        }

        if ($total_removed > 0) {
            return [
                'tier'        => 'incompatible',
                'badge_color' => '#b91c1c',
                'badge_text'  => sprintf(_n('%d BREAKING', '%d BREAKING', $total_removed, 'phpinfo-wp'), $total_removed),
                'plugin_color'=> '#b91c1c',
                'title'       => sprintf(__('Breaking incompatibilities detected on PHP %s', 'phpinfo-wp'), esc_html($result['target'] ?? '')),
            ];
        }

        if ($total_deprecated > 0) {
            return [
                'tier'        => 'deprecated',
                'badge_color' => '#b45309',
                'badge_text'  => sprintf(_n('%d DEPRECATED', '%d DEPRECATED', $total_deprecated, 'phpinfo-wp'), $total_deprecated),
                'plugin_color'=> '#b45309',
                'title'       => sprintf(__('Deprecation notices only — safe to upgrade to PHP %s', 'phpinfo-wp'), esc_html($result['target'] ?? '')),
            ];
        }

        return [
            'tier'        => 'passed',
            'badge_color' => '#15803d',
            'badge_text'  => __('PASSED', 'phpinfo-wp'),
            'plugin_color'=> '#15803d',
            'title'       => sprintf(__('Fully compatible with PHP %s', 'phpinfo-wp'), esc_html($result['target'] ?? '')),
        ];
    }

    private static function is_guarded(array &$tokens, int $index, string $func_name, string &$content, string $rel): bool {
        $lower_rel = strtolower($rel);
        $is_vendor  = (
            strpos($lower_rel, 'vendor/') !== false || 
            strpos($lower_rel, 'third-party/') !== false || 
            strpos($lower_rel, 'packages/') !== false ||
            strpos($lower_rel, 'node_modules/') !== false
        );

        $is_diagnostic = (
            strpos($lower_rel, 'system-status') !== false ||
            strpos($lower_rel, 'system_status') !== false ||
            strpos($lower_rel, 'diagnostic') !== false ||
            strpos($lower_rel, 'debug') !== false ||
            strpos($lower_rel, 'polyfill') !== false ||
            strpos($lower_rel, 'compat') !== false ||
            strpos($lower_rel, 'tests/') !== false
        );

        $prefix = explode('_', $func_name)[0];

        // 1. File-level polyfill/shim check
        if ($is_vendor || $is_diagnostic) {
            if (
                stripos($content, "extension_loaded('$prefix'") !== false ||
                stripos($content, 'extension_loaded("' . $prefix . '"') !== false ||
                stripos($content, "extension_loaded('mysqli'") !== false ||
                stripos($content, 'extension_loaded("mysqli"') !== false ||
                stripos($content, "function_exists('$func_name'") !== false ||
                stripos($content, 'function_exists("' . $func_name . '"') !== false ||
                stripos($content, "function_exists('{$prefix}_") !== false ||
                stripos($content, 'PHP_VERSION_ID') !== false ||
                stripos($content, 'version_compare') !== false
            ) {
                return true;
            }
        }

        // 2. Token-level lookback check (up to 80 tokens)
        $lookback = max(0, $index - 80);
        for ($j = $index - 1; $j >= $lookback; $j--) {
            $tok = $tokens[$j];
            if (!is_array($tok)) continue;
            
            $t_id  = $tok[0];
            $t_str = strtolower($tok[1]);

            if ($t_id === T_STRING) {
                if (in_array($t_str, ['function_exists', 'extension_loaded', 'is_callable', 'defined'], true)) {
                    return true;
                }
            } elseif ($t_id === T_VARIABLE && ($t_str === '$php_version' || $t_str === '$is_php8' || $t_str === '$is_php7')) {
                return true;
            }
        }

        return false;
    }

    private static function filter_rules(string $target): array {
        $t = (float) $target;
        if ($t < 7.0 || $t > 8.4) return [];

        $current = (float) (PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);
        $is_forward_upgrade = ($t > $current);

        $filtered = ['func_exact' => [], 'func_prefix' => [], 'special' => []];
        $all = self::rules();

        foreach ($all as $category => $rules) {
            foreach ($rules as $key => $r) {
                $in  = (float) $r['in'];
                $out = $r['out'] ? (float) $r['out'] : null;

                if ($is_forward_upgrade) {
                    // Forward Upgrade Delta:
                    // Only test changes introduced AFTER current running PHP version up to the target version
                    if ($out !== null && $out > $current && $t >= $out) {
                        $filtered[$category][$key] = $r;
                        continue;
                    }
                    if ($r['severity'] === 'deprecated' && $in > $current && $t >= $in) {
                        $filtered[$category][$key] = $r;
                        continue;
                    }
                } else {
                    // Full / Legacy Scan: Include all rules up to target version
                    if ($out !== null && $t >= $out) {
                        $filtered[$category][$key] = $r;
                        continue;
                    }
                    if ($r['severity'] === 'deprecated' && $t >= $in) {
                        $filtered[$category][$key] = $r;
                        continue;
                    }
                }
            }
        }
        
        return $filtered;
    }

    private static function owner_of(string $type, string $rel): ?string {
        $parts = preg_split('#[\\\\/]+#', $rel);
        if (!$parts) return null;
        $first = $parts[0];
        if ($first === '' || strncmp($first, '.', strlen('.')) === 0) return null;
        if ($type !== 'themes' && count($parts) === 1) {
            return $type . '/' . pathinfo($first, PATHINFO_FILENAME);
        }
        return $type . '/' . $first;
    }

    public static function register_update_warnings(): void {
        add_action('admin_init', function() {
            $updates = get_site_transient('update_plugins');
            if (!$updates || empty($updates->response)) return;
            foreach ($updates->response as $file => $info) {
                add_action('in_plugin_update_message-' . $file, [self::class, 'render_update_warning'], 10, 2);
            }
        });
    }

    public static function register(): void {
        self::register_update_warnings();
        add_action('wp_ajax_phpinfowp_compat_scan',   [__CLASS__, 'ajax_scan']);
        add_action('wp_ajax_phpinfowp_compat_clear',  [__CLASS__, 'ajax_clear']);
        add_action('wp_ajax_phpinfowp_compat_ignore', [__CLASS__, 'ajax_ignore']);
    }

    public static function ajax_scan(): void {
        check_ajax_referer('phpinfowp_compat_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        $target = sanitize_text_field($_POST['target'] ?? '8.4');
        if (!in_array($target, self::TARGETS, true)) {
            $target = '8.4';
        }

        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::start('compat', ['target' => $target]);
        }

        $result = self::scan($target);
        if (isset($result['error'])) {
            if (class_exists('Phpinfo_WP_Background_Scan')) {
                Phpinfo_WP_Background_Scan::clear('compat');
            }
            wp_send_json_error(['message' => $result['error']]);
        }

        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::complete('compat', [
                'target' => $target,
                'files'  => $result['files'] ?? 0,
                'total'  => $result['total'] ?? 0,
            ]);
        }

        wp_send_json_success($result);
    }

    public static function ajax_clear(): void {
        check_ajax_referer('phpinfowp_compat_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        self::clear();
        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::clear('compat');
        }
        wp_send_json_success();
    }

    public static function ajax_ignore(): void {
        check_ajax_referer('phpinfowp_compat_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        $hash   = sanitize_text_field($_POST['hash'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? 'ignore');

        if (!$hash) {
            wp_send_json_error(['message' => __('Invalid hash.', 'phpinfo-wp')]);
        }

        if ($status === 'unignore') {
            self::unignore_issue($hash);
        } else {
            self::ignore_issue($hash);
        }

        wp_send_json_success(['hash' => $hash, 'status' => $status]);
    }

    public static function render_update_warning($plugin_data, $response): void {
        $req_php = '';
        if (is_object($response) && !empty($response->requires_php)) {
            $req_php = (string) $response->requires_php;
        } elseif (is_array($response) && !empty($response['requires_php'])) {
            $req_php = (string) $response['requires_php'];
        }

        if (!$req_php) return;
        if (version_compare(PHP_VERSION, $req_php, '>=')) return;

        $is_pro = self::_pro();
        $upgrade_url = $is_pro
            ? admin_url('admin.php?page=piwp-compat')
            : 'https://exeebit.com/phpinfo-wp#pricing';
        $cta = $is_pro ? 'Run full compatibility scan →' : 'Get pre-upgrade auto-scan with Pro →';

        printf(
            '<br><span style="display:inline-block;margin-top:8px;padding:6px 10px;background:#fcf0f1;border-left:3px solid #d63638;color:#7a1a1a;font-weight:600">' .
            '⚠ This update requires PHP %s but your site runs PHP %s — installing it will likely break the plugin. ' .
            '<a href="%s" style="color:#7a1a1a;text-decoration:underline" target="_blank">%s</a>' .
            '</span>',
            esc_html($req_php),
            esc_html(PHP_VERSION),
            esc_url($upgrade_url),
            esc_html($cta)
        );
    }
}
