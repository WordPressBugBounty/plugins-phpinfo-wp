<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Security_Headers {

    const OPT_ENABLED = 'phpinfowp_sec_headers_enabled';
    const MARK_BEGIN  = '# BEGIN phpinfo-wp-security-headers';
    const MARK_END    = '# END phpinfo-wp-security-headers';

    private static function _pro(): bool {
        return Phpinfo_WP_License::is_valid();
    }

    /**
     * @var mixed[]
     */
    private static $headers = [
        'content-security-policy'   => ['label' => 'Content-Security-Policy',   'desc' => 'Prevents XSS and data injection attacks by declaring approved content sources.'],
        'strict-transport-security' => ['label' => 'Strict-Transport-Security', 'desc' => 'Forces HTTPS connections, preventing protocol downgrade attacks.'],
        'x-frame-options'           => ['label' => 'X-Frame-Options',           'desc' => 'Prevents clickjacking by controlling whether the page can be framed.'],
        'x-content-type-options'    => ['label' => 'X-Content-Type-Options',    'desc' => 'Stops MIME-type sniffing, forcing the declared Content-Type.'],
        'referrer-policy'           => ['label' => 'Referrer-Policy',           'desc' => 'Controls how much referrer information is sent with requests.'],
        'permissions-policy'        => ['label' => 'Permissions-Policy',        'desc' => 'Restricts which browser features the page can use (camera, mic, etc.).'],
    ];

    public static function register(): void {
        add_action('send_headers', [__CLASS__, 'emit_headers'], 1);
        add_action('wp_ajax_phpinfowp_sec_autofix', [__CLASS__, 'ajax_autofix']);
        add_action('wp_ajax_phpinfowp_sec_revert',  [__CLASS__, 'ajax_revert']);
        add_action('wp_ajax_phpinfowp_sec_recheck', [__CLASS__, 'ajax_recheck']);
        add_action('wp_ajax_phpinfowp_sec_scan',    [__CLASS__, 'ajax_scan']);
        add_action('wp_ajax_phpinfowp_sec_clear',   [__CLASS__, 'ajax_clear']);
    }

    /**
     * Emits security headers at the PHP runtime level when active.
     */
    public static function emit_headers(): void {
        if (headers_sent()) return;

        $enabled = get_option(self::OPT_ENABLED, []);
        if (!is_array($enabled) || empty($enabled)) return;

        foreach ($enabled as $key) {
            switch ($key) {
                case 'x-content-type-options':
                    @header('X-Content-Type-Options: nosniff');
                    break;
                case 'x-frame-options':
                    @header('X-Frame-Options: SAMEORIGIN');
                    break;
                case 'referrer-policy':
                    @header('Referrer-Policy: strict-origin-when-cross-origin');
                    break;
                case 'permissions-policy':
                    @header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
                    break;
                case 'strict-transport-security':
                    if (is_ssl()) {
                        @header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
                    }
                    break;
                case 'content-security-policy':
                    if (is_ssl()) {
                        @header("Content-Security-Policy: upgrade-insecure-requests;");
                    }
                    break;
            }
        }
    }

    public static function is_active(): bool {
        $opt = get_option(self::OPT_ENABLED, []);
        if (!empty($opt)) return true;

        $htaccess = self::get_htaccess_path();
        if ($htaccess && file_exists($htaccess)) {
            $content = @file_get_contents($htaccess) ?: '';
            if (strpos($content, self::MARK_BEGIN) !== false) return true;
        }
        return false;
    }

    public static function audit(string $url = ''): array {
        if (!$url) $url = get_site_url();

        $response = wp_remote_head($url, [
            'timeout'     => 15,
            'redirection' => 5,
            'sslverify'   => false,
            'user-agent'  => 'phpinfo-WP-auditor/' . PHPINFOWP_VERSION,
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $raw     = wp_remote_retrieve_headers($response);
        $status  = wp_remote_retrieve_response_code($response);
        $results = [];
        $passed  = 0;
        $total   = count(self::$headers);

        foreach (self::$headers as $key => $meta) {
            $value = $raw[$key] ?? null;
            if ($value === null) {
                $value = wp_remote_retrieve_header($response, $key);
                if ($value === '') $value = null;
            }

            if (is_array($value)) {
                $flat = [];
                array_walk_recursive($value, function ($v) use (&$flat) {
                    $str = trim((string) $v);
                    if ($str !== '') $flat[] = $str;
                });
                $separator = ($key === 'content-security-policy') ? '; ' : ', ';
                $value = !empty($flat) ? implode($separator, $flat) : null;
            } elseif ($value !== null) {
                $value = trim((string) $value);
                if ($value === '' || $value === 'Array') {
                    $value = null;
                }
            }

            $present = ($value !== null);
            if ($present) $passed++;

            $results[] = [
                'key'     => $key,
                'label'   => $meta['label'],
                'desc'    => $meta['desc'],
                'present' => $present,
                'value'   => $present ? $value : null,
                'warning' => self::_warn($key, $present ? $value : null),
            ];
        }

        usort($results, function ($a, $b) {
            $score_a = !$a['present'] ? 0 : (!empty($a['warning']) ? 1 : 2);
            $score_b = !$b['present'] ? 0 : (!empty($b['warning']) ? 1 : 2);
            if ($score_a !== $score_b) {
                return $score_a <=> $score_b;
            }
            return strcmp($a['label'], $b['label']);
        });

        $score = $total > 0 ? (int) round(($passed / $total) * 100) : 0;

        return [
            'url'     => $url,
            'status'  => $status,
            'results' => $results,
            'score'   => $score,
            'grade'   => self::_grade($score),
            'cached'  => false,
        ];
    }

    public static function get_result(): ?array {
        $cached = get_transient('phpinfowp_sec_headers');
        if (is_array($cached)) {
            // Auto-heal stale cache containing literal 'Array' string
            if (!empty($cached['results']) && is_array($cached['results'])) {
                foreach ($cached['results'] as $row) {
                    if (($row['value'] ?? '') === 'Array') {
                        delete_transient('phpinfowp_sec_headers');
                        return null;
                    }
                }
            }
            $cached['cached'] = true;
            return $cached;
        }
        return null;
    }

    public static function scan(bool $force_refresh = false): array {
        if (!$force_refresh) {
            $cached = self::get_result();
            if ($cached !== null) return $cached;
        }
        $result = self::audit();
        if (!isset($result['error'])) {
            set_transient('phpinfowp_sec_headers', $result, HOUR_IN_SECONDS);
        }
        return $result;
    }

    public static function get_cached(): ?array {
        return self::get_result();
    }

    public static function bust_cache(): void {
        delete_transient('phpinfowp_sec_headers');
    }

    private static function _grade(int $score): string {
        if ($score >= 95) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 65) return 'B';
        if ($score >= 50) return 'C';
        if ($score >= 35) return 'D';
        return 'F';
    }

    private static function _warn(string $key, ?string $value): ?string {
        if ($value === null) return __('Missing — add this header to secure your HTTP responses.', 'phpinfo-wp');

        switch ($key) {
            case 'strict-transport-security':
                if (strpos($value, 'max-age') === false) return __('max-age directive is missing.', 'phpinfo-wp');
                preg_match('/max-age=(\d+)/', $value, $m);
                if (isset($m[1]) && (int)$m[1] < 31536000) return __('max-age is below recommended 31536000 (1 year).', 'phpinfo-wp');
                break;
            case 'x-frame-options':
                $v = strtoupper($value);
                if (!in_array($v, ['DENY', 'SAMEORIGIN'], true)) return __('Value should be DENY or SAMEORIGIN.', 'phpinfo-wp');
                break;
            case 'x-content-type-options':
                if (strtolower(trim($value)) !== 'nosniff') return __('Value must be exactly "nosniff".', 'phpinfo-wp');
                break;
        }
        return null;
    }

    public static function get_htaccess_path(): ?string {
        $home_path = function_exists('get_home_path') ? get_home_path() : ABSPATH;
        $path = $home_path . '.htaccess';
        return file_exists($path) || is_writable($home_path) ? $path : null;
    }

    public static function is_apache_or_litespeed(): bool {
        $server = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
        return strpos($server, 'apache') !== false || strpos($server, 'litespeed') !== false;
    }

    public static function generate_htaccess_rules(array $keys): string {
        $lines = [self::MARK_BEGIN, "<IfModule mod_headers.c>"];
        foreach ($keys as $k) {
            switch ($k) {
                case 'x-content-type-options':
                    $lines[] = '    Header set X-Content-Type-Options "nosniff"';
                    break;
                case 'x-frame-options':
                    $lines[] = '    Header set X-Frame-Options "SAMEORIGIN"';
                    break;
                case 'referrer-policy':
                    $lines[] = '    Header set Referrer-Policy "strict-origin-when-cross-origin"';
                    break;
                case 'permissions-policy':
                    $lines[] = '    Header set Permissions-Policy "camera=(), microphone=(), geolocation=()"';
                    break;
                case 'strict-transport-security':
                    $lines[] = '    Header set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=HTTPS';
                    break;
                case 'content-security-policy':
                    $lines[] = '    Header set Content-Security-Policy "upgrade-insecure-requests;"';
                    break;
            }
        }
        $lines[] = "</IfModule>";
        $lines[] = self::MARK_END;
        return implode("\n", $lines);
    }

    /**
     * Applies security headers with automatic loopback verification and rollback on error.
     */
    public static function autofix(?array $keys_to_fix = null): array {
        if (!self::_pro()) {
            return ['ok' => false, 'error' => __('Pro license required.', 'phpinfo-wp')];
        }

        $all_keys = ['x-content-type-options', 'x-frame-options', 'referrer-policy', 'permissions-policy'];
        if (is_ssl()) {
            $all_keys[] = 'strict-transport-security';
            $all_keys[] = 'content-security-policy';
        }
        if (empty($keys_to_fix)) {
            $keys_to_fix = $all_keys;
        }

        // 1. Backup original state
        $htaccess_path = self::get_htaccess_path();
        $orig_htaccess = ($htaccess_path && file_exists($htaccess_path)) ? @file_get_contents($htaccess_path) : null;
        $orig_option   = get_option(self::OPT_ENABLED, []);

        // 2. Prepare enabled list
        $new_enabled = array_values(array_unique(array_merge(is_array($orig_option) ? $orig_option : [], $keys_to_fix)));
        update_option(self::OPT_ENABLED, $new_enabled);

        // 3. Write .htaccess block if writable & server is Apache/LiteSpeed
        $wrote_htaccess = false;
        if ($htaccess_path && @is_writable($htaccess_path) && self::is_apache_or_litespeed()) {
            $rules = self::generate_htaccess_rules($new_enabled);
            $current_content = $orig_htaccess ?: '';
            $clean = preg_replace('/' . preg_quote(self::MARK_BEGIN, '/') . '.*?' . preg_quote(self::MARK_END, '/') . '\s*/s', '', $current_content);
            $new_content = rtrim($clean) . "\n\n" . $rules . "\n";
            @file_put_contents($htaccess_path, $new_content);
            $wrote_htaccess = true;
        }

        // 4. VERIFY SITE SAFETY VIA LOOPBACK HTTP TEST
        $verify = self::verify_site();
        if (!$verify['ok']) {
            // AUTOMATIC ROLLBACK
            if ($wrote_htaccess && $orig_htaccess !== null && $htaccess_path) {
                @file_put_contents($htaccess_path, $orig_htaccess);
            } elseif ($wrote_htaccess && $orig_htaccess === null && $htaccess_path) {
                @unlink($htaccess_path);
            }
            update_option(self::OPT_ENABLED, $orig_option);
            self::bust_cache();

            return [
                'ok' => false,
                'error' => sprintf(__('Site safety check failed (HTTP %s). All security header rules were automatically rolled back to prevent downtime.', 'phpinfo-wp'), esc_html($verify['code'] ?? 'Error')),
            ];
        }

        // 5. Success! Bust cache and re-audit
        self::bust_cache();
        $audit = self::get_cached();

        return [
            'ok'      => true,
            'message' => __('Security headers successfully applied and verified safe!', 'phpinfo-wp'),
            'audit'   => $audit,
        ];
    }

    /**
     * Reverts all applied security header rules.
     */
    public static function revert(): array {
        if (!self::_pro()) {
            return ['ok' => false, 'error' => __('Pro license required.', 'phpinfo-wp')];
        }

        $htaccess_path = self::get_htaccess_path();
        if ($htaccess_path && @is_writable($htaccess_path) && file_exists($htaccess_path)) {
            $current = @file_get_contents($htaccess_path) ?: '';
            $clean = preg_replace('/' . preg_quote(self::MARK_BEGIN, '/') . '.*?' . preg_quote(self::MARK_END, '/') . '\s*/s', '', $current);
            @file_put_contents($htaccess_path, rtrim($clean) . "\n");
        }

        delete_option(self::OPT_ENABLED);
        self::bust_cache();

        return [
            'ok'      => true,
            'message' => __('Security header rules reverted successfully.', 'phpinfo-wp'),
        ];
    }

    private static function verify_site(): array {
        $url = home_url('/');
        $resp = wp_remote_head($url, [
            'timeout'     => 8,
            'sslverify'   => false,
            'redirection' => 3,
        ]);

        if (is_wp_error($resp)) {
            $resp = wp_remote_get($url, [
                'timeout'     => 8,
                'sslverify'   => false,
                'redirection' => 3,
            ]);
        }

        if (is_wp_error($resp)) {
            return ['ok' => false, 'code' => $resp->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code >= 500 || $code === 0) {
            return ['ok' => false, 'code' => (string) $code];
        }

        return ['ok' => true, 'code' => (string) $code];
    }

    /* ── AJAX Endpoints ─────────────────────────────────────────────────── */

    public static function ajax_autofix(): void {
        check_ajax_referer('phpinfowp_sec_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        $key = sanitize_text_field($_POST['header_key'] ?? '');
        $keys = $key ? [$key] : null;

        $res = self::autofix($keys);
        if ($res['ok']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error(['message' => $res['error'] ?? __('Auto-fix failed.', 'phpinfo-wp')]);
        }
    }

    public static function ajax_revert(): void {
        check_ajax_referer('phpinfowp_sec_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        $res = self::revert();
        if ($res['ok']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error(['message' => $res['error'] ?? __('Revert failed.', 'phpinfo-wp')]);
        }
    }

    public static function ajax_recheck(): void {
        check_ajax_referer('phpinfowp_sec_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        self::bust_cache();
        $audit = self::scan(true);
        wp_send_json_success(['audit' => $audit]);
    }

    public static function ajax_scan(): void {
        check_ajax_referer('phpinfowp_sec_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        $audit = self::scan(true);
        if (isset($audit['error'])) {
            wp_send_json_error(['message' => $audit['error']]);
        }
        wp_send_json_success(['audit' => $audit]);
    }

    public static function ajax_clear(): void {
        check_ajax_referer('phpinfowp_sec_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        self::bust_cache();
        wp_send_json_success();
    }
}
