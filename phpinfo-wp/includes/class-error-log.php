<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Error_Log {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    public static function mask_path(string $path): string {
        $norm_path = wp_normalize_path($path);
        $norm_abs  = wp_normalize_path(ABSPATH);
        if (strpos($norm_path, $norm_abs) === 0) {
            return substr($norm_path, strlen($norm_abs));
        }
        $parts = explode('/', trim($norm_path, '/'));
        if (count($parts) > 3) {
            return '.../' . implode('/', array_slice($parts, -3));
        }
        return basename($norm_path);
    }

    public static function line_count(string $path): int {
        if (!@file_exists($path) || !@is_readable($path)) return 0;
        try {
            $file = new SplFileObject($path, 'r');
            $file->seek(PHP_INT_MAX);
            return (int) $file->key();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function get_available_logs(): array {
        $candidates = self::candidate_paths();
        $logs       = [];
        $wp_log     = (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '1' && WP_DEBUG_LOG !== 'true') ? WP_DEBUG_LOG : (WP_CONTENT_DIR . '/debug.log');

        $wp_count     = 0;
        $server_count = 0;

        foreach ($candidates as $p) {
            if (@file_exists($p) && @is_readable($p)) {
                $is_wp       = ($p === $wp_log || strpos($p, 'debug.log') !== false);
                $size        = (int) @filesize($p);
                $lines_count = self::line_count($p);
                $type        = $is_wp ? 'wp' : 'server';
                $label       = $is_wp ? __('WordPress Debug Log', 'phpinfo-wp') : __('PHP Server Error Log', 'phpinfo-wp');

                if ($is_wp) {
                    $key = $wp_count === 0 ? 'wp' : ('wp_' . ($wp_count + 1));
                    $wp_count++;
                } else {
                    $key = $server_count === 0 ? 'server' : ('server_' . ($server_count + 1));
                    $server_count++;
                }

                $logs[$key] = [
                    'key'         => $key,
                    'path'        => $p,
                    'name'        => basename($p),
                    'type'        => $type,
                    'label'       => $label,
                    'size'        => $size,
                    'size_human'  => self::format_bytes($size),
                    'lines_count' => $lines_count,
                ];
            }
        }
        return $logs;
    }

    public static function get_active_log_path(?string $requested = null): ?string {
        $available = self::get_available_logs();
        if (empty($available)) {
            return null;
        }

        // 1. Explicitly requested via key or path
        $req = $requested ?? (!empty($_GET['log']) ? sanitize_key($_GET['log']) : (!empty($_GET['log_file']) ? sanitize_text_field(wp_unslash($_GET['log_file'])) : null));
        if ($req) {
            if (isset($available[$req])) {
                return $available[$req]['path'];
            }
            foreach ($available as $info) {
                if ($info['path'] === $req) {
                    return $info['path'];
                }
            }
        }

        // 2. Prioritize a log that actually has recorded errors
        foreach ($available as $info) {
            if ($info['lines_count'] > 0) {
                return $info['path'];
            }
        }

        // 3. Fallback to first available log
        $first = reset($available);
        return $first['path'] ?? null;
    }

    public static function get_active_log_key(?string $requested = null): string {
        $available   = self::get_available_logs();
        $active_path = self::get_active_log_path($requested);
        foreach ($available as $key => $info) {
            if ($info['path'] === $active_path) {
                return $key;
            }
        }
        return array_key_first($available) ?: 'wp';
    }

    public static function find_path(?string $requested = null): ?string {
        return self::get_active_log_path($requested);
    }

    // List of every path the plugin considers a possible PHP error log. Used
    // both by find_path() and by the diagnostic view that explains why
    // nothing was discovered. Order is the discovery priority.
    public static function candidate_paths(): array {
        $paths = [];

        // 1. WP_DEBUG_LOG — true (default) or explicit path
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG !== false) {
            if (is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '1' && WP_DEBUG_LOG !== 'true') {
                $paths[] = WP_DEBUG_LOG;
            } else {
                $paths[] = WP_CONTENT_DIR . '/debug.log';
            }
        }

        // 2. php.ini error_log directive
        $ini = ini_get('error_log');
        if ($ini) $paths[] = $ini;

        // 3. Common WordPress + shared-host fallbacks
        $paths[] = WP_CONTENT_DIR . '/debug.log';
        $paths[] = ABSPATH . 'error_log';
        $paths[] = ABSPATH . 'php_errors.log';
        $paths[] = ABSPATH . 'php-error.log';
        $paths[] = dirname(ABSPATH) . '/error_log';
        $paths[] = dirname(ABSPATH) . '/php_errors.log';
        // Cloudways, SiteGround, Kinsta-style log directories above webroot
        $paths[] = dirname(ABSPATH, 2) . '/logs/error.log';
        $paths[] = dirname(ABSPATH, 2) . '/logs/php_errors.log';

        return array_values(array_unique(array_filter($paths)));
    }

    // Reports the full diagnostic: WP_DEBUG state, each candidate path's
    // status (defined/not-defined, found, missing, unreadable), and a
    // human summary that tells the user exactly what to do.
    public static function diagnose(): array {
        $debug_const         = defined('WP_DEBUG') && WP_DEBUG;
        $debug_log_const     = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG !== false;
        $debug_display_const = defined('WP_DEBUG_DISPLAY') ? (bool) WP_DEBUG_DISPLAY : true;

        $candidates = self::candidate_paths();
        $checks     = [];
        $found      = null;

        $target_log = (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '1' && WP_DEBUG_LOG !== 'true') ? WP_DEBUG_LOG : (WP_CONTENT_DIR . '/debug.log');

        foreach ($candidates as $p) {
            $entry = ['path' => $p, 'status' => 'missing', 'note' => ''];
            if (@file_exists($p)) {
                if (!@is_readable($p)) {
                    $entry['status'] = 'unreadable';
                    $entry['note']   = 'File exists but PHP cannot read it (check permissions).';
                } else {
                    $entry['status'] = 'found';
                    $entry['size']   = (int) @filesize($p);
                    $entry['mtime']  = (int) @filemtime($p);
                    if ($found === null) $found = $p;
                }
            } elseif ($debug_log_const && $p === $target_log) {
                $entry['status'] = 'target_pending';
                $entry['note']   = __('Active destination — WordPress will create this file automatically on the first PHP error.', 'phpinfo-wp');
            } else {
                $entry['note'] = 'Not found.';
            }
            $checks[] = $entry;
        }

        $found = self::get_active_log_path();

        // Build a one-line summary
        if ($found) {
            $summary = 'Log file found.';
            $verdict = 'found';
        } elseif (!$debug_log_const) {
            $summary = 'WP_DEBUG_LOG is off, so WordPress is not writing a log. If your host writes PHP errors somewhere else, point WP_DEBUG_LOG at that path (or check your host control panel).';
            $verdict = 'disabled';
        } else {
            $summary = "WP_DEBUG_LOG is on and actively monitoring. No log file has been created because your site hasn't logged any PHP errors or warnings yet — your site is running cleanly.";
            $verdict = 'empty';
        }

        return [
            'constants' => [
                'WP_DEBUG'         => $debug_const,
                'WP_DEBUG_LOG'     => $debug_log_const,
                'WP_DEBUG_DISPLAY' => $debug_display_const,
            ],
            'wp_debug_log_value' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : null,
            'ini_error_log'      => ini_get('error_log') ?: null,
            'checks'             => $checks,
            'found'              => $found,
            'summary'            => $summary,
            'verdict'            => $verdict,
        ];
    }

    // Returns lines, newest first. Safe against huge log files (reads from EOF).
    public static function tail(string $path, int $lines = 200): array {
        if (!@file_exists($path) || !@is_readable($path)) return [];
        if ($lines <= 0) $lines = 200;

        $fp = @fopen($path, 'rb');
        if (!$fp) return [];

        $size = @filesize($path) ?: 0;
        $max_bytes = 2 * 1024 * 1024;
        if ($size > $max_bytes) {
            fseek($fp, -$max_bytes, SEEK_END);
            fgets($fp);
        }

        $buffer = [];
        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line === false) break;
            $line = rtrim($line, "\r\n");
            if ($line !== '') {
                $buffer[] = $line;
            }
        }
        fclose($fp);

        if (count($buffer) > $lines) {
            $buffer = array_slice($buffer, -$lines);
        }

        return array_reverse($buffer);
    }

    public static function size(string $path): int {
        return @file_exists($path) ? (int) @filesize($path) : 0;
    }

    public static function clear(string $path): bool {
        if (!self::_pro()) return false;
        if (!@file_exists($path) || !@is_writable($path)) return false;
        return @file_put_contents($path, '') !== false;
    }

    public static function format_bytes(int $bytes): string {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    /**
     * Determines semantic severity of a log line.
     * Accurately isolates real PHP runtime errors/warnings from arbitrary plugin telemetry.
     */
    public static function get_line_severity(string $line): string {
        $l = strtolower(trim($line));
        if ($l === '') return 'info';

        // 1. Critical / Fatal Errors
        if (preg_match('/\bphp\s+(?:fatal error|parse error|catchable fatal error|recoverable fatal error|core error|compile error)\b|^(?:fatal error|parse error):|\buncaught\s+[a-z0-9_\\\\]*(?:exception|error)\b|allowed memory size of \d+ bytes exhausted|maximum execution time of \d+ seconds exceeded|\[(?:critical|emergency|alert|error)\]/i', $l)) {
            return 'fatal';
        }

        // 2. Warnings & DB Errors
        if (preg_match('/\bphp\s+(?:warning|core warning|compile warning)\b|^warning:|\bwordpress database error\b|\[warning\]/i', $l)) {
            return 'warning';
        }

        // 3. Notices & Deprecated
        if (preg_match('/\bphp\s+(?:notice|deprecated)\b|^(?:notice|deprecated):|\[notice\]/i', $l)) {
            return 'notice';
        }

        // 4. Debug / Trace
        if (preg_match('/\[debug\]|\[trace\]|\bwp_debug\b/i', $l)) {
            return 'debug';
        }

        // 5. Default / Application telemetry (e.g. [INFO] or plain plugin error_log strings)
        return 'info';
    }

    // Classifies a log line for color-coding
    public static function classify(string $line): string {
        $sev = self::get_line_severity($line);
        switch ($sev) {
            case 'fatal':
                return 'log-fatal';
            case 'warning':
                return 'log-warning';
            case 'notice':
            case 'deprecated':
                return 'log-notice';
            case 'debug':
                return 'log-debug';
            default:
                return 'log-default';
        }
    }

    /**
     * Returns structured error counts for today, separating real PHP errors from plugin logs.
     * Free-safe: returns 0s if no log discovered. Reads only tail (~512 KB) to stay fast on huge logs.
     */
    public static function today_breakdown(?string $path = null): array {
        $path = $path ?? self::find_path();
        $default = [
            'critical'     => 0,
            'warning'      => 0,
            'notice'       => 0,
            'deprecated'   => 0,
            'info'         => 0,
            'total_errors' => 0,
        ];
        if (!$path || !@is_readable($path)) return $default;

        $size = (int) @filesize($path);
        if ($size <= 0) return $default;

        $chunk  = 512 * 1024;
        $offset = max(0, $size - $chunk);

        $fh = @fopen($path, 'rb');
        if (!$fh) return $default;
        @fseek($fh, $offset);
        $data = @fread($fh, $chunk);
        @fclose($fh);
        if ($data === false || $data === '') return $default;

        // PHP writes timestamps in local or UTC timezone: [DD-Mon-YYYY HH:MM:SS TZ]
        $today_dates = array_unique([
            date('d-M-Y'),
            gmdate('d-M-Y'),
            date('Y-m-d'),
            gmdate('Y-m-d'),
        ]);

        $date_regex = implode('|', array_map('preg_quote', $today_dates));

        if (!preg_match_all('/^\[(?:' . $date_regex . ')[^\]]*\]\s*(.*)$/m', $data, $matches)) {
            return $default;
        }

        $lines      = $matches[1];
        $critical   = 0;
        $warning    = 0;
        $notice     = 0;
        $deprecated = 0;
        $info       = 0;

        foreach ($lines as $line) {
            $sev = self::get_line_severity($line);
            switch ($sev) {
                case 'fatal':
                    $critical++;
                    break;
                case 'warning':
                    $warning++;
                    break;
                case 'notice':
                    $notice++;
                    break;
                case 'deprecated':
                    $deprecated++;
                    break;
                default:
                    $info++;
                    break;
            }
        }

        return [
            'critical'     => $critical,
            'warning'      => $warning,
            'notice'       => $notice,
            'deprecated'   => $deprecated,
            'info'         => $info,
            'total_errors' => $critical + $warning + $notice + $deprecated,
        ];
    }

    // Counts genuine PHP runtime errors stamped with today's date (excludes benign plugin logs).
    // Set $actionable_only = true to count only fatal crashes + warnings.
    public static function today_count(?string $path = null, bool $actionable_only = false): int {
        $b = self::today_breakdown($path);
        return $actionable_only ? ($b['critical'] + $b['warning']) : $b['total_errors'];
    }

    /**
     * Scan a byte slice of the log file (from $offset_before to current EOF) to detect
     * genuine runtime errors that occurred during an update window.
     */
    public static function detect_new_errors(int $offset_before, ?string $path = null): array {
        $path = $path ?? self::find_path();
        $result = [
            'count'    => 0,
            'critical' => 0,
            'warning'  => 0,
            'details'  => [],
        ];
        if (!$path || !@is_readable($path)) return $result;

        $current_size = (int) @filesize($path);
        if ($current_size <= 0) return $result;

        // If file was cleared or rotated, read up to last 256KB
        if ($offset_before <= 0 || $current_size < $offset_before) {
            $read_bytes = min($current_size, 256 * 1024);
            $start_pos  = max(0, $current_size - $read_bytes);
        } else {
            // Read newly appended slice, capped at 512KB max
            $read_bytes = min($current_size - $offset_before, 512 * 1024);
            $start_pos  = $offset_before;
        }

        if ($read_bytes <= 0) return $result;

        $fp = @fopen($path, 'rb');
        if (!$fp) return $result;
        @fseek($fp, $start_pos);
        $data = (string) @fread($fp, $read_bytes);
        @fclose($fp);

        if ($data === '') return $result;

        $lines   = preg_split('/[\r\n]+/', $data);
        $count   = 0;
        $crit    = 0;
        $warn    = 0;
        $details = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $sev = self::get_line_severity($line);
            if ($sev === 'fatal' || $sev === 'warning') {
                $count++;
                if ($sev === 'fatal') {
                    $crit++;
                } else {
                    $warn++;
                }
                if (count($details) < 3) {
                    $clean = preg_replace('/^\[[^\]]+\]\s*/', '', $line);
                    $details[] = wp_strip_all_tags(substr($clean, 0, 120));
                }
            }
        }

        return [
            'count'    => $count,
            'critical' => $crit,
            'warning'  => $warn,
            'details'  => $details,
        ];
    }

    /**
     * Locate wp-config.php whether in ABSPATH or parent directory.
     */
    public static function get_wp_config_path(): ?string {
        if (@file_exists(ABSPATH . 'wp-config.php')) {
            return ABSPATH . 'wp-config.php';
        }
        if (@file_exists(dirname(ABSPATH) . '/wp-config.php') && !@file_exists(dirname(ABSPATH) . '/wp-settings.php')) {
            return dirname(ABSPATH) . '/wp-config.php';
        }
        return null;
    }

    /**
     * Check if WordPress error logging is active.
     */
    public static function is_logging_enabled(): bool {
        $mu = (defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins') . '/phpinfowp-error-logger.php';
        if (@file_exists($mu)) return true;

        $debug_log = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG !== false;
        $debug     = defined('WP_DEBUG') && WP_DEBUG;
        return $debug && $debug_log;
    }

    /**
     * Safely enable WordPress error logging without displaying errors to visitors.
     */
    public static function enable_logging(): array {
        if (!self::_pro()) {
            return [
                'success' => false,
                'message' => __('PRO license required to enable error logging.', 'phpinfo-wp'),
            ];
        }

        $config_path    = self::get_wp_config_path();
        $config_written = false;

        if ($config_path && @is_writable($config_path)) {
            $content = (string) @file_get_contents($config_path);
            if (!empty($content)) {
                @copy($config_path, $config_path . '.bak');

                // Strip any previous phpinfowp block
                $content = preg_replace("/\/\/\s*BEGIN PHPINFO_WP_DEBUG.*?\/\/\s*END PHPINFO_WP_DEBUG\s*/s", "", $content);

                $has_debug   = preg_match("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*[^)]+\);/i", $content);
                $has_log     = preg_match("/define\s*\(\s*['\"]WP_DEBUG_LOG['\"]\s*,\s*[^)]+\);/i", $content);
                $has_display = preg_match("/define\s*\(\s*['\"]WP_DEBUG_DISPLAY['\"]\s*,\s*[^)]+\);/i", $content);

                if ($has_debug) {
                    $content = preg_replace("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*[^)]+\);/i", "define( 'WP_DEBUG', true );", $content, 1);
                }
                if ($has_log) {
                    $content = preg_replace("/define\s*\(\s*['\"]WP_DEBUG_LOG['\"]\s*,\s*[^)]+\);/i", "define( 'WP_DEBUG_LOG', true );", $content, 1);
                }
                if ($has_display) {
                    $content = preg_replace("/define\s*\(\s*['\"]WP_DEBUG_DISPLAY['\"]\s*,\s*[^)]+\);/i", "define( 'WP_DEBUG_DISPLAY', false );", $content, 1);
                }

                // If any constant was missing, inject managed block
                if (!$has_debug || !$has_log || !$has_display) {
                    $block = "\n// BEGIN PHPINFO_WP_DEBUG\n";
                    if (!$has_debug)   $block .= "define( 'WP_DEBUG', true );\n";
                    if (!$has_log)     $block .= "define( 'WP_DEBUG_LOG', true );\n";
                    if (!$has_display) $block .= "define( 'WP_DEBUG_DISPLAY', false );\n";
                    $block .= "@ini_set( 'display_errors', '0' );\n";
                    $block .= "// END PHPINFO_WP_DEBUG\n";

                    $needle = "/* That's all, stop editing!";
                    $pos = strpos($content, $needle);
                    if ($pos !== false) {
                        $content = substr($content, 0, $pos) . $block . substr($content, $pos);
                    } else {
                        $content .= $block;
                    }
                }

                if (@file_put_contents($config_path, $content)) {
                    $config_written = true;
                }
            }
        }

        // If wp-config.php was not writable, fallback to an MU-plugin logger
        if (!$config_written) {
            $mu_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
            if (!@is_dir($mu_dir)) {
                @wp_mkdir_p($mu_dir);
            }
            $mu_file = $mu_dir . '/phpinfowp-error-logger.php';
            $mu_code = "<?php\n// Generated by phpinfo() WP - Safe Error Logging\ndefined('ABSPATH') or die();\nif (!defined('WP_DEBUG')) define('WP_DEBUG', true);\nif (!defined('WP_DEBUG_LOG')) define('WP_DEBUG_LOG', true);\nif (!defined('WP_DEBUG_DISPLAY')) define('WP_DEBUG_DISPLAY', false);\n@ini_set('log_errors', '1');\n@ini_set('error_log', WP_CONTENT_DIR . '/debug.log');\n@ini_set('display_errors', '0');\n";
            if (!@file_put_contents($mu_file, $mu_code)) {
                return [
                    'success' => false,
                    'message' => __('Could not update wp-config.php or create mu-plugins logger. Check file permissions.', 'phpinfo-wp'),
                ];
            }
        }

        self::protect_debug_log();

        // Create empty debug.log file if not present so viewer immediately attaches to it
        $target_log = WP_CONTENT_DIR . '/debug.log';
        if (!@file_exists($target_log) && @is_writable(WP_CONTENT_DIR)) {
            @touch($target_log);
        }

        return [
            'success' => true,
            'message' => __('Safe error logging enabled! Errors will be logged to wp-content/debug.log without leaking to site visitors.', 'phpinfo-wp'),
        ];
    }

    /**
     * Safely disable WordPress error logging.
     */
    public static function disable_logging(): array {
        if (!self::_pro()) {
            return [
                'success' => false,
                'message' => __('PRO license required to manage error logging.', 'phpinfo-wp'),
            ];
        }

        $config_path = self::get_wp_config_path();
        if ($config_path && @is_writable($config_path)) {
            $content = (string) @file_get_contents($config_path);
            if (!empty($content)) {
                $content = preg_replace("/\/\/\s*BEGIN PHPINFO_WP_DEBUG.*?\/\/\s*END PHPINFO_WP_DEBUG\s*/s", "", $content);
                $content = preg_replace("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*[^)]+\);/i", "define( 'WP_DEBUG', false );", $content);
                $content = preg_replace("/define\s*\(\s*['\"]WP_DEBUG_LOG['\"]\s*,\s*[^)]+\);/i", "define( 'WP_DEBUG_LOG', false );", $content);

                @file_put_contents($config_path, $content);
            }
        }

        $mu_file = (defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins') . '/phpinfowp-error-logger.php';
        if (@file_exists($mu_file)) {
            @unlink($mu_file);
        }

        return [
            'success' => true,
            'message' => __('WordPress error logging has been disabled.', 'phpinfo-wp'),
        ];
    }

    /**
     * Ensure web server denies direct HTTP access to debug.log.
     */
    public static function protect_debug_log(): void {
        $htaccess = WP_CONTENT_DIR . '/.htaccess';
        $rule = "\n# BEGIN PHPINFO_WP_LOG_PROTECT\n<Files \"debug.log\">\n    Require all denied\n</Files>\n# END PHPINFO_WP_LOG_PROTECT\n";
        if (@file_exists($htaccess)) {
            $c = (string) @file_get_contents($htaccess);
            if (strpos($c, 'PHPINFO_WP_LOG_PROTECT') === false && @is_writable($htaccess)) {
                @file_put_contents($htaccess, $c . $rule);
            }
        } elseif (@is_writable(WP_CONTENT_DIR)) {
            @file_put_contents($htaccess, $rule);
        }
    }
}
