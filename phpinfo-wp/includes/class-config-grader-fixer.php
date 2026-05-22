<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * One-click Config Grader auto-fix engine.
 *
 * Maps each Grader check to a recommended runtime value, writes it via
 * .htaccess (`php_value`) on Apache+mod_php or .user.ini on FPM/CGI, then
 * verifies the site still returns 200. On 500 we restore the backup —
 * the user can't break their own site.
 *
 * Directives that require PHP_INI_SYSTEM (only settable in php.ini, not
 * per-directory) are detected and surfaced as "Can't auto-fix — copy this
 * line into your php.ini." Honest is better than a silent failure.
 *
 * Pro only.
 */
class Phpinfo_WP_Config_Grader_Fixer {

    // Regex fragments that match either comment style (backward-compatible).
    const MARK_RE_BEGIN = '[#;] BEGIN phpinfo-wp-autofix';
    const MARK_RE_END   = '[#;] END phpinfo-wp-autofix';

    private static function mark_begin(string $mode): string {
        return ($mode === 'htaccess' ? '#' : ';') . ' BEGIN phpinfo-wp-autofix';
    }
    private static function mark_end(string $mode): string {
        return ($mode === 'htaccess' ? '#' : ';') . ' END phpinfo-wp-autofix';
    }

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    /**
     * Map: ini key → ['value' => string|null, 'requires_php_ini' => bool, 'note' => ?string]
     * value=null means the key cannot be fixed via .htaccess/.user.ini.
     *
     * Context-aware: pulls recommended values from the Grader's context so a
     * WooCommerce site gets memory_limit=512M, a blog gets 256M, an Elementor
     * site gets max_input_vars=5000, etc.
     */
    public static function fix_map(): array {
        $is_https = is_ssl();
        $ctx      = class_exists('Phpinfo_WP_Config_Grader') ? Phpinfo_WP_Config_Grader::context() : [];
        $mem      = Phpinfo_WP_Config_Grader::rec_memory($ctx);
        $vars     = Phpinfo_WP_Config_Grader::rec_input_vars($ctx);
        $exec     = Phpinfo_WP_Config_Grader::rec_exec_time($ctx);
        $upload   = Phpinfo_WP_Config_Grader::rec_upload($ctx);

        return [
            // ── Per-site tuned ──
            'memory_limit'              => ['value' => $mem['mb']  . 'M',   'requires_php_ini' => false],
            'max_execution_time'        => ['value' => (string) $exec['s'], 'requires_php_ini' => false],
            'max_input_vars'            => ['value' => (string) $vars['n'], 'requires_php_ini' => false],
            'upload_max_filesize'       => ['value' => $upload     . 'M',   'requires_php_ini' => false],
            'post_max_size'             => ['value' => $upload     . 'M',   'requires_php_ini' => false],
            'max_input_time'            => ['value' => '60',                'requires_php_ini' => false],
            'max_file_uploads'          => ['value' => '20',                'requires_php_ini' => false],

            // ── Security ──
            'display_errors'            => ['value' => '0',                 'requires_php_ini' => false],
            'log_errors'                => ['value' => '1',                 'requires_php_ini' => false],
            'session.cookie_httponly'   => ['value' => '1',                 'requires_php_ini' => false],
            'session.use_strict_mode'   => ['value' => '1',                 'requires_php_ini' => false],
            'session.cookie_secure'     => ['value' => $is_https ? '1' : '0', 'requires_php_ini' => false],

            // ── Filesystem & misc (new in 7.0.3) ──
            'realpath_cache_size'       => ['value' => '4096K',             'requires_php_ini' => false],
            'realpath_cache_ttl'        => ['value' => '600',               'requires_php_ini' => false],
            'date.timezone'             => ['value' => date_default_timezone_get() ?: 'UTC', 'requires_php_ini' => false],
            'output_buffering'          => ['value' => '4096',              'requires_php_ini' => false],

            // ── PHP_INI_SYSTEM — cannot be set per-directory ──
            'expose_php'                => ['value' => null, 'requires_php_ini' => true,
                'note' => 'expose_php = Off must be set in php.ini and PHP restarted.'],
            'allow_url_include'         => ['value' => null, 'requires_php_ini' => true,
                'note' => 'allow_url_include = Off must be set in php.ini.'],
            'opcache.enable'            => ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.enable must be set in php.ini and PHP restarted.'],
            'opcache.memory_consumption'=> ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.memory_consumption must be set in php.ini.'],
            'opcache.max_accelerated_files' => ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.max_accelerated_files must be set in php.ini.'],
            'opcache.validate_timestamps'   => ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.validate_timestamps must be set in php.ini.'],
            'opcache.jit'               => ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.jit must be set in php.ini (PHP 8.0+) and PHP restarted.'],
            'opcache.jit_buffer_size'   => ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.jit_buffer_size must be set in php.ini.'],
            'opcache.huge_code_pages'   => ['value' => null, 'requires_php_ini' => true,
                'note' => 'opcache.huge_code_pages must be set in php.ini and requires Linux transparent_hugepage.'],
        ];
    }

    public static function can_fix(string $key): bool {
        $map = self::fix_map();
        return isset($map[$key]) && $map[$key]['value'] !== null && self::target_writable();
    }

    public static function manual_note(string $key): ?string {
        $map = self::fix_map();
        return $map[$key]['note'] ?? null;
    }

    /** Detects which target file applies to this server. */
    public static function detect_target(): array {
        $sapi            = php_sapi_name();
        $server_software = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
        $is_litespeed    = strpos($server_software, 'litespeed') !== false;
        $mode            = ($sapi === 'apache2handler' && !$is_litespeed) ? 'htaccess' : 'userini';
        if (!function_exists('get_home_path')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $root            = get_home_path();
        $file            = $root . ($mode === 'htaccess' ? '.htaccess' : '.user.ini');
        return ['mode' => $mode, 'file' => $file, 'root' => $root];
    }

    public static function target_writable(): bool {
        $t = self::detect_target();
        if (!@is_writable($t['root'])) return false;
        if (@file_exists($t['file']) && !@is_writable($t['file'])) return false;
        return true;
    }

    /**
     * Apply one or more fixes. Returns ['ok' => bool, 'applied' => [keys], 'skipped' => [...], 'error' => ?string].
     */
    public static function apply(array $keys): array {
        if (!self::_pro()) {
            return ['ok' => false, 'error' => 'Pro license required for one-click fixes.'];
        }
        if (!current_user_can('manage_options')) {
            return ['ok' => false, 'error' => 'Insufficient permissions.'];
        }

        $target  = self::detect_target();
        $file    = $target['file'];
        $mode    = $target['mode'];
        $map     = self::fix_map();

        if (!self::target_writable()) {
            return ['ok' => false, 'error' => 'Config file is not writable: ' . esc_html($file)];
        }

        // Resolve which keys to actually fix vs note as manual
        $to_write = [];
        $skipped  = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            if (!isset($map[$k])) { $skipped[$k] = 'unknown directive'; continue; }
            if ($map[$k]['value'] === null) { $skipped[$k] = 'requires php.ini'; continue; }
            $to_write[$k] = $map[$k]['value'];
        }
        if (!$to_write) {
            return ['ok' => false, 'error' => 'Nothing to write — all selected directives require manual php.ini changes.', 'skipped' => $skipped];
        }

        $current = @file_exists($file) ? @file_get_contents($file) : '';
        // Backup
        $backup_path = $file . '.phpinfowp-autofix.bak';
        @file_put_contents($backup_path, $current);

        // Merge with any existing autofix block (overwrite directives we manage)
        $managed = self::extract_managed($current, $mode);
        foreach ($to_write as $k => $v) $managed[$k] = $v;
        $block = self::render_block($managed, $mode);

        $base = self::strip_managed_block($current);
        $new  = rtrim($base) . "\n\n" . $block . "\n";
        $ok   = (bool) @file_put_contents($file, $new);
        if (!$ok) return ['ok' => false, 'error' => 'Failed to write ' . esc_html($file)];

        // Verify site still responds
        $verify = self::verify_site();
        if (!$verify['ok']) {
            // Restore
            @file_put_contents($file, $current);
            return [
                'ok' => false,
                'error' => 'Configuration caused HTTP ' . $verify['code'] . ' — original config restored.',
                'rolled_back' => true,
            ];
        }

        // Log to activity log
        self::log_change('autofix applied: ' . implode(', ', array_keys($to_write)));

        return [
            'ok'      => true,
            'applied' => array_keys($to_write),
            'skipped' => $skipped,
            'mode'    => $mode,
            'file'    => $file,
        ];
    }

    // Compare the autofix block on disk against what PHP actually reports.
    // Returns rows where we wrote a value but PHP is using a different one —
    // usually a competing .user.ini in a parent directory, a stale cache,
    // or a host-level lockdown.
    //
    // Returns: [['key', 'expected', 'actual'], ...] — empty if all match.
    public static function detect_overrides(): array {
        $t = self::detect_target();
        if (!@file_exists($t['file'])) return [];

        $managed = self::extract_managed((string) @file_get_contents($t['file']), $t['mode']);
        $out = [];
        foreach ($managed as $key => $expected) {
            $actual = ini_get($key);
            if ($actual === false) continue;
            if (!self::values_match($key, $expected, (string) $actual)) {
                $out[] = ['key' => $key, 'expected' => $expected, 'actual' => (string) $actual];
            }
        }
        return $out;
    }

    // Compare two ini values smartly. PHP normalizes "On"/"1"/"true" to "1",
    // "Off"/"0"/"" to "", so a raw string compare gives false negatives.
    private static function values_match(string $key, string $expected, string $actual): bool {
        $boolish = ['1' => '1', 'on' => '1', 'true' => '1', 'yes' => '1',
                    '0' => '0', 'off' => '0', 'false' => '0', 'no' => '0', '' => '0'];
        $e = $boolish[strtolower(trim($expected))] ?? trim($expected);
        $a = $boolish[strtolower(trim($actual))]   ?? trim($actual);
        // Size shorthand: 256M vs 268435456, etc — normalize via bytes.
        if (preg_match('/^\d+\s*[kmg]?$/i', $e) && preg_match('/^\d+\s*[kmg]?$/i', $a)) {
            return self::to_bytes($e) === self::to_bytes($a);
        }
        return $e === $a;
    }

    private static function to_bytes(string $v): int {
        $v = trim($v);
        $last = strtolower(substr($v, -1));
        $n = (int) $v;
        if ($last === 'g') return $n * 1024 * 1024 * 1024;
        if ($last === 'm') return $n * 1024 * 1024;
        if ($last === 'k') return $n * 1024;
        return $n;
    }

    public static function revert_all(): array {
        if (!self::_pro()) return ['ok' => false, 'error' => 'Pro license required.'];
        $t = self::detect_target();
        if (!@is_writable($t['file'])) return ['ok' => false, 'error' => 'Config file not writable.'];

        $current = @file_exists($t['file']) ? @file_get_contents($t['file']) : '';
        if (!preg_match('/' . self::MARK_RE_BEGIN . '/', $current)) {
            return ['ok' => true, 'reverted' => false, 'message' => 'No autofix block to revert.'];
        }
        $base = self::strip_managed_block($current);
        @file_put_contents($t['file'], rtrim($base) . "\n");
        $verify = self::verify_site();
        if (!$verify['ok']) {
            @file_put_contents($t['file'], $current);
            return ['ok' => false, 'error' => 'Reverting caused HTTP ' . $verify['code'] . '.'];
        }
        self::log_change('autofix block reverted');
        return ['ok' => true, 'reverted' => true];
    }

    private static function extract_managed(string $content, string $mode): array {
        if (!preg_match('/' . self::MARK_RE_BEGIN . '\s*(.*?)\s*' . self::MARK_RE_END . '/s', $content, $m)) {
            return [];
        }
        $block = $m[1];
        $out   = [];
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '' || strncmp($line, '#', strlen('#')) === 0 || strncmp($line, ';', strlen(';')) === 0) continue;
            if ($mode === 'htaccess') {
                if (preg_match('/^php_value\s+([^\s]+)\s+(.+)$/', $line, $mm)) {
                    $out[$mm[1]] = trim($mm[2], "\"' ");
                }
            } else {
                if (preg_match('/^([^=\s]+)\s*=\s*(.+)$/', $line, $mm)) {
                    $out[$mm[1]] = trim($mm[2], "\"' ");
                }
            }
        }
        return $out;
    }

    private static function strip_managed_block(string $content): string {
        return preg_replace(
            '/\n*' . self::MARK_RE_BEGIN . '.*?' . self::MARK_RE_END . '\n*/s',
            "\n",
            $content
        );
    }

    private static function render_block(array $pairs, string $mode): string {
        $c     = $mode === 'htaccess' ? '#' : ';';
        $lines = [self::mark_begin($mode), "$c Managed by phpinfo() WP — auto-fix. Edit via the Config Grader page."];
        if ($mode === 'htaccess') {
            foreach ($pairs as $k => $v) $lines[] = "php_value {$k} \"{$v}\"";
        } else {
            foreach ($pairs as $k => $v) $lines[] = "{$k} = {$v}";
        }
        $lines[] = self::mark_end($mode);
        return implode("\n", $lines);
    }

    private static function verify_site(): array {
        $url = get_site_url();
        $resp = wp_remote_get($url, ['timeout' => 8, 'sslverify' => false, 'redirection' => 1]);
        if (is_wp_error($resp)) {
            // If we can't reach the site at all, treat as failure
            return ['ok' => false, 'code' => 0, 'err' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        // 500-range is bad; anything else (200, 3xx, even 401/403 for staging) is fine
        return ['ok' => $code < 500, 'code' => $code];
    }

    private static function log_change(string $msg): void {
        $log_dir  = WP_CONTENT_DIR . '/logs/phpinfo-WP';
        $log_file = $log_dir . '/log.txt';
        if (!@file_exists($log_dir)) @wp_mkdir_p($log_dir);
        $user = wp_get_current_user();
        $line = sprintf("Config %s on %s by %s<br />",
            $msg, current_time('mysql'), $user->user_login ?: 'unknown');
        @file_put_contents($log_file, $line, FILE_APPEND);
    }
}
