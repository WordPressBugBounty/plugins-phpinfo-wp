<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Error_Log {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    public static function find_path(): ?string {
        foreach (self::candidate_paths() as $c) {
            if (@file_exists($c)) return $c;
        }
        return null;
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
            } else {
                $entry['note'] = 'Not found.';
            }
            $checks[] = $entry;
        }

        // Build a one-line summary
        if ($found) {
            $summary = 'Log file found.';
            $verdict = 'found';
        } elseif (!$debug_log_const) {
            $summary = 'WP_DEBUG_LOG is off, so WordPress is not writing a log. If your host writes PHP errors somewhere else, point WP_DEBUG_LOG at that path (or check your host control panel).';
            $verdict = 'disabled';
        } else {
            $summary = "WP_DEBUG_LOG is on but no log file has been created — your site hasn't logged any PHP errors recently. That's usually a good thing. If you expected errors and don't see them, your host may be writing logs to a path we don't check (check your hosting control panel for an error_log location).";
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

    // Returns last $lines lines, newest first
    public static function tail(string $path, int $lines = 150): array {
        if (!self::_pro()) return [];
        if (!@file_exists($path) || !@is_readable($path)) return [];

        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $total = $file->key();

        $start  = max(0, $total - $lines);
        $result = [];

        $file->seek($start);
        while (!$file->eof()) {
            $line = rtrim((string) $file->current(), "\r\n");
            if ($line !== '') $result[] = $line;
            $file->next();
        }

        return array_reverse($result);
    }

    public static function size(string $path): int {
        return @file_exists($path) ? (int) @filesize($path) : 0;
    }

    public static function clear(string $path): bool {
        if (!self::_pro()) return false;
        if (!@file_exists($path) || !@is_writable($path)) return false;
        return (bool) @file_put_contents($path, '');
    }

    public static function format_bytes(int $bytes): string {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    // Counts PHP error log lines stamped with today's date.
    // Free-safe: returns 0 if no log discovered. Reads only the tail (~512 KB) to stay cheap on huge logs.
    public static function today_count(?string $path = null): int {
        $path = $path ?? self::find_path();
        if (!$path || !@is_readable($path)) return 0;

        $size = (int) @filesize($path);
        if ($size <= 0) return 0;

        $chunk  = 512 * 1024;
        $offset = max(0, $size - $chunk);

        $fh = @fopen($path, 'rb');
        if (!$fh) return 0;
        @fseek($fh, $offset);
        $data = @fread($fh, $chunk);
        @fclose($fh);
        if ($data === false || $data === '') return 0;

        // PHP error log lines start with: "[DD-Mon-YYYY HH:MM:SS TZ] ..."
        // Use local date — error_log writes timestamps in PHP's configured timezone.
        $today = date('d-M-Y');
        $count = preg_match_all('/^\[' . preg_quote($today, '/') . ' /m', $data);
        return (int) $count;
    }

    // Classifies a log line for color-coding
    public static function classify(string $line): string {
        $l = strtolower($line);
        if (str_contains($l, 'fatal error') || str_contains($l, 'uncaught'))          return 'log-fatal';
        if (str_contains($l, 'parse error'))                                            return 'log-fatal';
        if (str_contains($l, 'warning'))                                                return 'log-warning';
        if (str_contains($l, 'notice') || str_contains($l, 'deprecated'))              return 'log-notice';
        if (str_contains($l, 'wp_debug') || str_contains($l, '[debug]'))               return 'log-debug';
        return 'log-default';
    }
}
