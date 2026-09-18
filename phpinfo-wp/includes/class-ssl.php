<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_SSL {

    const OPT_DOMAINS = 'phpinfowp_ssl_domains';
    const OPT_MIXED   = 'phpinfowp_ssl_mixed_cache';

    private static function _pro(): bool {
        return Phpinfo_WP_License::is_valid();
    }

    public static function can_monitor_extra(): bool {
        if (!self::_pro()) return false;
        return class_exists('Phpinfo_WP_License') && Phpinfo_WP_License::can_monitor_extra_domains();
    }

    public static function register(): void {
        add_action('wp_ajax_phpinfowp_ssl_recheck',       [__CLASS__, 'ajax_recheck']);
        add_action('wp_ajax_phpinfowp_ssl_scan',          [__CLASS__, 'ajax_scan']);
        add_action('wp_ajax_phpinfowp_ssl_clear',         [__CLASS__, 'ajax_clear']);
        add_action('wp_ajax_phpinfowp_ssl_scan_mixed',    [__CLASS__, 'ajax_scan_mixed']);
        add_action('wp_ajax_phpinfowp_ssl_save_domains',  [__CLASS__, 'ajax_save_domains']);
        add_action('wp_ajax_phpinfowp_ssl_check_single',  [__CLASS__, 'ajax_check_single']);
        add_action('wp_ajax_phpinfowp_ssl_remove_domain', [__CLASS__, 'ajax_remove_domain']);
        add_action('wp_ajax_phpinfowp_ssl_scan_all_extra',[__CLASS__, 'ajax_scan_all_extra']);
    }

    /**
     * Checks an individual host's SSL certificate.
     */
    public static function check(string $host, int $port = 443, bool $force_refresh = false): array {
        $host = strtolower(trim($host));
        if (!$host) return ['error' => __('No host provided.', 'phpinfo-wp')];

        $cache_key = 'phpinfowp_ssl_' . md5($host . ':' . $port);
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                $cached['cached'] = true;
                return $cached;
            }
        }

        $result = self::_check_via_stream($host, $port);
        if (isset($result['error']) && function_exists('curl_init')) {
            $result = self::_check_via_curl($host, $port);
        }
        if (!isset($result['error'])) {
            set_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);
            set_transient('phpinfowp_ssl_' . md5($host), $result, 6 * HOUR_IN_SECONDS);
        } else {
            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
            set_transient('phpinfowp_ssl_' . md5($host), $result, 10 * MINUTE_IN_SECONDS);
        }
        return $result;
    }

    private static function _check_via_stream(string $host, int $port): array {
        if (!function_exists('stream_socket_client')) {
            return ['error' => 'stream_socket_client not available.'];
        }

        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'SNI_enabled'       => true,
                'peer_name'         => $host,
            ],
        ]);

        $socket = @stream_socket_client(
            "ssl://{$host}:{$port}", $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT, $ctx
        );

        if (!$socket) {
            return ['error' => $errstr ?: sprintf(__('Could not connect to %s:%d', 'phpinfo-wp'), $host, $port)];
        }

        $params = stream_context_get_params($socket);
        $cert   = $params['options']['ssl']['peer_certificate'] ?? null;
        fclose($socket);

        if (!$cert) return ['error' => __('Connected but no certificate was returned.', 'phpinfo-wp')];
        return self::_parse_cert($cert, $host);
    }

    private static function _check_via_curl(string $host, int $port): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://{$host}:{$port}/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_CERTINFO       => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 15,
        ]);
        curl_exec($ch);

        $info     = curl_getinfo($ch);
        $certinfo = $info['certinfo'] ?? [];
        curl_close($ch);

        if (empty($certinfo[0])) {
            return ['error' => __('Could not retrieve certificate via cURL.', 'phpinfo-wp')];
        }

        $c      = $certinfo[0];
        $expiry = isset($c['Expire date']) ? strtotime($c['Expire date']) : 0;
        $issued = isset($c['Start date'])  ? strtotime($c['Start date'])  : 0;
        $days   = $expiry ? (int) round(($expiry - time()) / DAY_IN_SECONDS) : 0;

        return [
            'host'       => $host,
            'cn'         => $c['Subject'] ?? $host,
            'issuer'     => $c['Issuer'] ?? '—',
            'issued'     => $issued ? gmdate('Y-m-d', $issued) : '—',
            'expiry'     => $expiry ? gmdate('Y-m-d', $expiry) : '—',
            'expiry_ts'  => $expiry,
            'days'       => $days,
            'status'     => self::_status($days),
            'sans'       => [],
            'sig_alg'    => '—',
            'serial'     => '—',
            'key_size'   => '—',
            'error'      => null,
        ];
    }

    private static function _parse_cert($cert, string $host): array {
        $info = openssl_x509_parse($cert);
        if (!$info) return ['error' => __('Could not parse certificate data.', 'phpinfo-wp')];
        $expiry = (int) ($info['validTo_time_t']   ?? 0);
        $issued = (int) ($info['validFrom_time_t']  ?? 0);
        $days   = $expiry ? (int) round(($expiry - time()) / DAY_IN_SECONDS) : 0;

        $sans = [];
        if (!empty($info['extensions']['subjectAltName'])) {
            preg_match_all('/DNS:([^,\s]+)/', $info['extensions']['subjectAltName'], $m);
            $sans = $m[1] ?? [];
        }

        $cn     = $info['subject']['CN'] ?? $host;
        $issuer = $info['issuer']['O']   ?? ($info['issuer']['CN'] ?? '—');
        $sig_alg = $info['signatureTypeSN'] ?? ($info['signatureTypeLN'] ?? 'SHA-256');
        $serial  = $info['serialNumberHex'] ?? ($info['serialNumber'] ?? '—');

        $pub_key = openssl_pkey_get_public($cert);
        $key_size = '—';
        if ($pub_key) {
            $key_details = openssl_pkey_get_details($pub_key);
            if (!empty($key_details['bits'])) {
                $type = ($key_details['type'] ?? 0) === OPENSSL_KEYTYPE_RSA ? 'RSA' : 'EC';
                $key_size = $type . ' ' . $key_details['bits'] . '-bit';
            }
        }

        return [
            'host'       => $host,
            'cn'         => $cn,
            'issuer'     => $issuer,
            'issued'     => $issued ? gmdate('Y-m-d', $issued) : '—',
            'expiry'     => $expiry ? gmdate('Y-m-d', $expiry) : '—',
            'expiry_ts'  => $expiry,
            'days'       => $days,
            'status'     => self::_status($days),
            'sans'       => $sans,
            'sig_alg'    => $sig_alg,
            'serial'     => $serial,
            'key_size'   => $key_size,
            'error'      => null,
        ];
    }

    private static function _status(int $days): string {
        if ($days < 0)  return 'expired';
        if ($days < 7)  return 'critical';
        if ($days < 30) return 'warning';
        return 'ok';
    }

    const OPT_HEALTH_CACHE = 'phpinfowp_ssl_health_cache';

    /**
     * Complete HTTPS & protocol diagnostic audit for the current site.
     */
    public static function audit_health(bool $force_refresh = false): array {
        if (!$force_refresh) {
            $cached = get_transient(self::OPT_HEALTH_CACHE);
            if (is_array($cached)) {
                $cached['cached'] = true;
                return $cached;
            }
        }

        $site_url = get_site_url();
        $home_url = get_home_url();
        $host     = wp_parse_url($site_url, PHP_URL_HOST) ?: '';

        // 1. Check HTTP to HTTPS auto-redirect
        $http_url = 'http://' . $host . '/';
        $redirect_ok = false;
        $redirect_code = 0;
        $resp = wp_remote_head($http_url, [
            'timeout'     => 6,
            'redirection' => 0,
            'sslverify'   => false,
        ]);
        if (!is_wp_error($resp)) {
            $redirect_code = (int) wp_remote_retrieve_response_code($resp);
            $location = wp_remote_retrieve_header($resp, 'location');
            if (in_array($redirect_code, [301, 302, 307, 308], true) && strpos($location, 'https://') === 0) {
                $redirect_ok = true;
            }
        }

        // 2. WordPress URLs scheme
        $wp_urls_ok = (strpos($site_url, 'https://') === 0) && (strpos($home_url, 'https://') === 0);

        // 3. Admin SSL enforcement
        $force_ssl_admin = defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN;

        // 4. HSTS header check
        $hsts_ok = false;
        $resp_https = wp_remote_head($site_url, [
            'timeout'   => 6,
            'sslverify' => false,
        ]);
        if (!is_wp_error($resp_https)) {
            $hsts = wp_remote_retrieve_header($resp_https, 'strict-transport-security');
            if (!empty($hsts) && strpos($hsts, 'max-age') !== false) {
                $hsts_ok = true;
            }
        }

        // 5. Mixed Content cached result
        $mixed = get_transient(self::OPT_MIXED);

        $result = [
            'host'            => $host,
            'redirect_ok'     => $redirect_ok,
            'redirect_code'   => $redirect_code,
            'wp_urls_ok'      => $wp_urls_ok,
            'force_ssl_admin' => $force_ssl_admin,
            'hsts_ok'         => $hsts_ok,
            'openssl_ver'     => OPENSSL_VERSION_TEXT,
            'mixed'           => $mixed !== false ? $mixed : null,
        ];

        set_transient(self::OPT_HEALTH_CACHE, $result, 6 * HOUR_IN_SECONDS);
        return $result;
    }

    /**
     * Scans homepage HTML for insecure HTTP assets (Mixed Content).
     */
    public static function scan_mixed_content(): array {
        $url = home_url('/');
        $resp = wp_remote_get($url, [
            'timeout'   => 12,
            'sslverify' => false,
        ]);

        if (is_wp_error($resp)) {
            return ['error' => $resp->get_error_message(), 'count' => 0, 'items' => []];
        }

        $html = wp_remote_retrieve_body($resp);
        $items = [];

        // Match insecure images, scripts, styles, iframes
        if (preg_match_all('/<(img|script|link|iframe)[^>]+(src|href)=["\'](http:\/\/[^"\']+)["\']/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $items[] = [
                    'tag' => strtolower($m[1]),
                    'url' => esc_url_raw($m[3]),
                ];
            }
        }

        $result = [
            'count'      => count($items),
            'items'      => array_slice($items, 0, 15),
            'scanned_at' => time(),
        ];

        set_transient(self::OPT_MIXED, $result, HOUR_IN_SECONDS);
        return $result;
    }

    public static function check_all(bool $force_refresh = false): array {
        if (!self::_pro()) return [];
        $site_host = parse_url(get_site_url(), PHP_URL_HOST) ?: '';
        $hosts     = [$site_host];

        $extra = self::get_extra_domains();
        foreach ($extra as $h) {
            if ($h && $h !== $site_host) $hosts[] = $h;
        }

        $results = [];
        foreach (array_unique($hosts) as $host) {
            $results[] = self::check($host, 443, $force_refresh);
        }
        return $results;
    }

    public static function bust_cache(): void {
        $hosts = [parse_url(get_site_url(), PHP_URL_HOST) ?: ''];
        foreach (self::get_extra_domains() as $h) $hosts[] = $h;
        foreach ($hosts as $h) {
            delete_transient('phpinfowp_ssl_' . md5($h . ':443'));
            delete_transient('phpinfowp_ssl_' . md5($h));
        }
        delete_transient(self::OPT_MIXED);
        delete_transient(self::OPT_HEALTH_CACHE);
    }

    public static function get_extra_domains(): array {
        if (!self::can_monitor_extra()) return [];
        $raw = get_option(self::OPT_DOMAINS, '');
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $raw))));
    }

    public static function save_extra_domains(string $raw, bool $auto_probe = true): void {
        if (!self::can_monitor_extra()) return;
        $domains = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $raw))));
        $clean = array_values(array_unique(array_filter($domains, function ($d) {
            return preg_match('/^[a-z0-9._-]+$/i', $d);
        })));
        update_option(self::OPT_DOMAINS, implode("\n", $clean), false);
        self::bust_cache();
        if ($auto_probe && !empty($clean)) {
            // Auto probe up to 10 domains synchronously so results are immediately ready
            foreach (array_slice($clean, 0, 10) as $host) {
                self::check($host, 443, true);
            }
        }
    }

    public static function status_color(string $status): string {
        switch ($status) {
            case 'expired':
            case 'critical':
                return '#d63638';
            case 'warning':
                return '#dba617';
            case 'ok':
                return '#00a32a';
            default:
                return '#666';
        }
    }

    public static function status_label(string $status): string {
        switch ($status) {
            case 'expired':
                return 'EXPIRED';
            case 'critical':
                return 'CRITICAL';
            case 'warning':
                return 'EXPIRING SOON';
            case 'ok':
                return 'VALID';
            default:
                return 'UNKNOWN';
        }
    }

    public static function get_result(): ?array {
        $host = wp_parse_url(get_site_url(), PHP_URL_HOST);
        if (!$host) return null;
        return self::get_result_for($host, 443);
    }

    public static function get_result_for(string $host, int $port = 443, bool $auto_fetch = false): ?array {
        $host = strtolower(trim($host));
        if (!$host) return null;
        $cache_key = 'phpinfowp_ssl_' . md5($host . ':' . $port);
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $cached_legacy = get_transient('phpinfowp_ssl_' . md5($host));
        if (is_array($cached_legacy)) return $cached_legacy;

        if ($auto_fetch) {
            return self::check($host, $port, false);
        }
        return null;
    }

    public static function get_health_cached(): ?array {
        $cached = get_transient(self::OPT_HEALTH_CACHE);
        return is_array($cached) ? $cached : null;
    }

    public static function scan(bool $force_refresh = false): array {
        if ($force_refresh) {
            self::bust_cache();
        }
        $results = self::_pro() ? self::check_all() : [self::check((string) wp_parse_url(get_site_url(), PHP_URL_HOST))];
        $health  = self::audit_health($force_refresh);
        return [
            'results' => $results,
            'health'  => $health,
        ];
    }

    /* ── AJAX Endpoints ─────────────────────────────────────────────────── */

    public static function ajax_scan(): void {
        check_ajax_referer('phpinfowp_ssl_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }
        $data = self::scan(true);
        wp_send_json_success($data);
    }

    public static function ajax_clear(): void {
        check_ajax_referer('phpinfowp_ssl_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }
        self::bust_cache();
        wp_send_json_success();
    }

    public static function ajax_recheck(): void {
        check_ajax_referer('phpinfowp_ssl_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }
        self::bust_cache();
        self::check_all();
        self::audit_health(true);
        wp_send_json_success(['message' => __('SSL cache cleared and re-audited.', 'phpinfo-wp')]);
    }

    public static function ajax_scan_mixed(): void {
        check_ajax_referer('phpinfowp_ssl_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }
        $result = self::scan_mixed_content();
        wp_send_json_success($result);
    }

    public static function ajax_save_domains(): void {
        check_ajax_referer('phpinfowp_ssl_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }
        if (!self::can_monitor_extra()) {
            wp_send_json_error(['message' => __('Additional domain monitoring is only available on Unlimited and Lifetime plans. Please upgrade your license to monitor extra domains.', 'phpinfo-wp')]);
        }
        $raw = sanitize_textarea_field($_POST['ssl_domains'] ?? '');
        self::save_extra_domains($raw, true);
        wp_send_json_success(['message' => __('Domains saved and SSL certificates verified.', 'phpinfo-wp')]);
    }

    public static function ajax_check_single(): void {
        check_ajax_referer('phpinfowp_ssl_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $domain = preg_replace('#^https?://#i', '', trim($domain));
        $domain = trim($domain, '/');
        if (!$domain) {
            wp_send_json_error(['message' => __('Invalid domain.', 'phpinfo-wp')]);
        }
        $site_host = strtolower((string) wp_parse_url(get_site_url(), PHP_URL_HOST));
        if (strtolower($domain) !== $site_host && !self::can_monitor_extra()) {
            wp_send_json_error(['message' => __('Additional domain monitoring is only available on Unlimited and Lifetime plans.', 'phpinfo-wp')]);
        }
        $res = self::check($domain, 443, true);
        wp_send_json_success($res);
    }

    public static function ajax_remove_domain(): void {
        check_ajax_referer('phpinfowp_ssl_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }
        if (!self::can_monitor_extra()) {
            wp_send_json_error(['message' => __('Additional domain monitoring is only available on Unlimited and Lifetime plans.', 'phpinfo-wp')]);
        }
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $domain = preg_replace('#^https?://#i', '', trim($domain));
        $domain = trim($domain, '/');
        if (!$domain) {
            wp_send_json_error(['message' => __('Invalid domain.', 'phpinfo-wp')]);
        }
        $domains = self::get_extra_domains();
        $filtered = array_values(array_filter($domains, function($d) use ($domain) {
            return strtolower($d) !== strtolower($domain);
        }));
        update_option(self::OPT_DOMAINS, implode("\n", $filtered), false);
        delete_transient('phpinfowp_ssl_' . md5($domain . ':443'));
        delete_transient('phpinfowp_ssl_' . md5($domain));
        wp_send_json_success([
            'message' => sprintf(__('Domain %s removed from monitoring.', 'phpinfo-wp'), $domain),
            'remaining' => count($filtered),
            'domains_text' => implode("\n", $filtered),
        ]);
    }

    public static function ajax_scan_all_extra(): void {
        check_ajax_referer('phpinfowp_ssl_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }
        if (!self::can_monitor_extra()) {
            wp_send_json_error(['message' => __('Additional domain monitoring is only available on Unlimited and Lifetime plans.', 'phpinfo-wp')]);
        }
        $domains = self::get_extra_domains();
        $results = [];
        foreach ($domains as $d) {
            $results[$d] = self::check($d, 443, true);
        }
        wp_send_json_success($results);
    }
}
