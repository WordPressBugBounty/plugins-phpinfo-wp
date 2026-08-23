<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Compat {

    const OPT_RESULT = 'phpinfowp_compat_result';
    const TARGETS    = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];
    const MAX_FILE_SIZE = 1048576;
    const MAX_FILES_PER_RUN = 5000;

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    const FREE_MAX_FILES = 1500;

    // We now use a token-based rule engine.
    // 'func_exact' maps exact function names to rules.
    // 'func_prefix' maps prefixes.
    // 'special' maps other PHP tokens.
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
                'get_class'            => ['name' => 'get_class() with no args', 'in' => '8.3', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use get_class($this) or self::class'],
                'get_parent_class'     => ['name' => 'get_parent_class() with no args', 'in' => '8.3', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use get_parent_class($this) or parent::class'],
            ],
            'func_prefix' => [
                'mysql_'  => ['name' => 'mysql_*()', 'in' => '5.5', 'out' => '7.0', 'severity' => 'removed', 'fix' => 'Use mysqli_* or PDO'],
                'ereg'    => ['name' => 'ereg_*()', 'in' => '5.3', 'out' => '7.0', 'severity' => 'removed', 'fix' => 'Use preg_* equivalents'], // catches ereg, eregi, ereg_replace
                'mcrypt_' => ['name' => 'mcrypt_*()', 'in' => '7.1', 'out' => '7.2', 'severity' => 'removed', 'fix' => 'Use openssl_encrypt/decrypt or sodium_crypto_*'],
                'mhash'   => ['name' => 'mhash_*()', 'in' => '8.1', 'out' => null, 'severity' => 'deprecated', 'fix' => 'Use hash_* functions'], // catches mhash, mhash_keygen_s2k
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

    public static function scan(string $target = '8.2'): array {
        @set_time_limit(120);
        $started = microtime(true);

        $rules = self::filter_rules($target);
        if (!$rules) return ['error' => 'Target version invalid.'];

        $max_files = self::_pro() ? self::MAX_FILES_PER_RUN : self::FREE_MAX_FILES;

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
                if ($files_scanned + $files_skipped >= $max_files) break 2;
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
                        // Function call check
                        $lower = strtolower($text);
                        
                        // Look backwards to ensure it's not a method or function declaration
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
                        for ($j = $i + 1; $j < $count; $j++) {
                            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) continue;
                            if ($tokens[$j] === '(') {
                                $next_is_paren = true;
                            }
                            break;
                        }
                        if (!$next_is_paren) continue;

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
                        $issues_by_owner[$owner][] = [
                            'file'     => $rel,
                            'line'     => $line,
                            'match'    => $snippet,
                            'name'     => $match_rule['name'],
                            'fix'      => $match_rule['fix'],
                            'severity' => $match_rule['severity'],
                            'in'       => $match_rule['in'],
                            'out'      => $match_rule['out'],
                        ];
                    }
                }
            }
        }

        $total = 0;
        foreach ($issues_by_owner as $list) $total += count($list);

        $result = [
            'target'        => $target,
            'total'         => $total,
            'owners'        => count($owners_seen),
            'with_issues'   => count($issues_by_owner),
            'files'         => $files_scanned,
            'skipped'       => $files_skipped,
            'max_files'     => $max_files,
            'truncated'     => ($files_scanned + $files_skipped) >= $max_files,
            'duration'      => round(microtime(true) - $started, 2),
            'scanned_at'    => time(),
            'issues'        => $issues_by_owner,
            'is_pro_result' => self::_pro(),
        ];

        update_option(self::OPT_RESULT, $result, false);
        return $result;
    }

    private static function filter_rules(string $target): array {
        $t = (float) $target;
        if ($t < 7.0 || $t > 8.4) return [];

        $filtered = ['func_exact' => [], 'func_prefix' => [], 'special' => []];
        $all = self::rules();

        foreach ($all as $category => $rules) {
            foreach ($rules as $key => $r) {
                $in  = (float) $r['in'];
                $out = $r['out'] ? (float) $r['out'] : null;
                if ($out !== null && $t >= $out) { $filtered[$category][$key] = $r; continue; }
                if ($r['severity'] === 'deprecated' && $t >= $in) { $filtered[$category][$key] = $r; continue; }
            }
        }
        
        // If all sub-arrays are empty, return empty array
        if (empty($filtered['func_exact']) && empty($filtered['func_prefix']) && empty($filtered['special'])) {
            return [];
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
