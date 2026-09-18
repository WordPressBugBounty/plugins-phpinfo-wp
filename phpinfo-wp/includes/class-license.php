<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_License {

    const OPT_KEY         = 'phpinfowp_license_key';
    const OPT_CACHE       = 'phpinfowp_lic_cache';
    const OPT_FAILS       = 'phpinfowp_lic_fails';
    const OPT_LOCKED      = 'phpinfowp_lic_locked';
    const OPT_REVOKE_REASON = 'phpinfowp_lic_revoke_reason';
    const OPT_LAST_CHECK  = 'phpinfowp_lic_last_check';
    const OPT_TIER        = 'phpinfowp_lic_tier';
    const MAX_FAILS       = 5;
    const PING_URL        = 'https://exeebit.com/api/license/validate';
    const DEACTIVATE_URL  = 'https://exeebit.com/api/license/deactivate';

    private const ED25519_PUB = 'IQLJxAElu4pgVtSoh1KB7aZ4GIUrmJpxaQOu0/gL7Sc=';

    private const KEY_V2 = 'PIWP2';
    private const KEY_V1 = 'PIWP';

    private const LEGACY_HASHES = [
        '3f5ebdcd40421825ec3dbe7d4cd7b17ca0e522d602bb3eb97148cc9be50e5d7f',
        'f99081ab8dd5a07aff31f46b4682ed4b9fc0206fef5c97813bbed7ff768ed711',
        'c580df0d5b85b9d06900c6822758f7aa2c3c4a75b66917f2b8c0d4c7bbde9040',
        'b067fa89a56cf20abb8e01c99b3fd6ad5a369aa6009e1bfab3e712a42e7d4f80',
        '38b6e55453d3808fd7c32a9c421c3155a42dd802a3eeb9bf4106e2c7cd645e90',
        '7b07d4d3de2d47d9b4c9c14e96b5408eec9020e07550040857bc5ad8472eb9a3',
        '52e401e02bf8e9e2f0df17920fa8abb7748c9defd965b578dbb3b4daf474f6f6',
        '9295077561d76890959ab4384ad5f36e651929fbd5a8f10c1163b40be5be8de4',
        '4589ae366afb8506f74bae1c8389438b08f1716d073a0cbbd5937e655cfc24c5',
        '8d2b34a8e8451dcd7db33ea849567e864665e9be9626e59fec8c218819edf19c',
        '012ef14e44d3ea9daa44f1b95d26ca76645c9d202d383f76811bcea0138e96d0',
        '72f51971ca8d267725752cb19e7a4f80b18a6bb99207b11a51392bdcd8ba0fc6',
        '04279493a3c0f7e72503c92097c54dd4ba0023958a74ed38ae6a26b0e0e5a53a',
        'd3e29ab425928fcb781aabeb64448c98e111c164e2d295ac2129fe998d077215',
        'c6fdc6b4a8a38adcddfe0f27fb1405c757ba4e35fe5c618848c02907d188d677',
        'dbf0080cd681390a08c570c7661eae9de0b749832a22599f084a0ea86fb46085',
        'f8b146873ec4c9ad0abe88e283cdfd2d11eb9992e63aa35392d093f61b93f2e9',
        '722529e43a68a51e4cf16a5a290e8cd40ece5f6cb74d5bc527fbc1f8dded7b68',
        '70268431aa5594c3a3ea2589eb48e341845a3bf4ac8420392958435c362ff170',
        'a247102b538f951aaa7795f794c9d17b0424a1065dd7531675e8848dae76788a',
        '4c91895dd6cba6f0046cbe4ebc7cbc06cb018614317af0474a00ab46270f655b',
        '6a15c215d92da933cfd5e32ac29de02de399579a6979f3b4ee121ad25defab32',
        'a0e9d95c0550fff716adbf13c12a52440f322e50f964bf1964743c9669579eee',
        '03de0b034cd26b4a26d76295876c804c85cee14d6742006b60b2bae7889a0f02',
        'bb6610d5c9d3905880e9f97e2483424cd5c61e5bb6721940dbf2f27285cd3206',
        '4705d243f6340a291138eaa73afd964ced10ed53fbdf87c2bb42fbc22091d32f',
        'c4d5822acc40f8549ced81c766212f1fafa12c77ad13cb669981e2bf4c6da34b',
    ];

    private static function _pub_key(): string {
        return self::ED25519_PUB;
    }

    private static function _cache_secret(string $key = ''): string {
        $salt = defined('AUTH_KEY') ? AUTH_KEY : (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'phpinfowp_fallback_salt');
        if ($key === '') {
            $key = (string) get_option(self::OPT_KEY, '');
        }
        return hash('sha256', $salt . 'phpinfowp_cache_v2_' . $key, true);
    }

    private static function _cache_write(bool $valid): void {
        $key  = self::get_key();
        $data = json_encode([
            'v' => (int) $valid,
            't' => time(),
            'k' => hash('sha256', $key),
        ]);
        $mac  = hash_hmac('sha256', $data, self::_cache_secret($key));
        update_option(self::OPT_CACHE, $mac . '|' . base64_encode($data), false);
    }

    private static function _cache_read(): ?bool {
        $stored = get_option(self::OPT_CACHE, '');
        if (!$stored || strpos($stored, '|') === false) return null;

        [$mac, $data_b64] = explode('|', $stored, 2);
        $data = base64_decode($data_b64);
        if (!$data) return null;

        $key      = self::get_key();
        $expected = hash_hmac('sha256', $data, self::_cache_secret($key));
        if (!hash_equals($expected, $mac)) return null;

        $parsed = json_decode($data, true);
        if (!isset($parsed['v'], $parsed['t'])) return null;
        if (time() - (int) $parsed['t'] > 6 * HOUR_IN_SECONDS) return null;

        if (isset($parsed['k']) && !hash_equals(hash('sha256', $key), (string) $parsed['k'])) {
            return null;
        }

        return (bool) $parsed['v'];
    }

    private static function _cache_clear(): void {
        delete_option(self::OPT_CACHE);
    }

    // --- Public API ---

    public static function get_key(): string {
        return (string) get_option(self::OPT_KEY, '');
    }

    public static function get_revoke_reason(): string {
        return (string) get_option(self::OPT_REVOKE_REASON, '');
    }

    public static function is_legacy(?string $key = null): bool {
        $key = $key !== null ? $key : self::get_key();
        if (empty($key) || substr($key, 0, 5) !== self::KEY_V1 . '-') {
            return false;
        }
        return in_array(hash('sha256', $key), self::LEGACY_HASHES, true);
    }

    public static function payload( ?string $key = null ): ?array {
        $key = $key !== null ? $key : self::get_key();
        if (!$key) return null;

        $parts = explode('-', $key, 3);
        if (count($parts) !== 3) return null;
        if ($parts[0] !== self::KEY_V1 && $parts[0] !== self::KEY_V2) return null;

        $raw = self::_b64url_decode($parts[1]);
        if (!$raw) return null;
        $p = json_decode($raw, true);
        if (!isset($p['exp'], $p['email'])) return null;

        $exp       = (int) $p['exp'];
        $days_left = (int) floor(($exp - time()) / DAY_IN_SECONDS);
        $is_life   = !empty($p['is_lifetime']) || ($days_left > (50 * 365));

        return [
            'email'        => (string) $p['email'],
            'url'          => (string) ($p['url'] ?? ''),
            'exp'          => $exp,
            'iat'          => (int) ($p['iat'] ?? 0),
            'days_left'    => $days_left,
            'expiry_human' => $is_life ? 'Lifetime' : date_i18n(get_option('date_format'), $exp),
            'is_lifetime'  => $is_life,
        ];
    }

    public static function is_valid(): bool {
        if (!self::_canary_check()) {
            self::_cache_clear();
            return false;
        }

        if (self::is_locked()) return false;

        $cached = self::_cache_read();
        if ($cached !== null) return $cached;

        $key = self::get_key();
        if (empty($key)) return false;

        $valid = self::_validate_local($key);
        if (!$valid) {
            self::_cache_write(false);
            return false;
        }

        $last_check    = (int) get_option(self::OPT_LAST_CHECK, 0);
        $needs_upgrade = (substr(self::get_key(), 0, 5) === self::KEY_V1 . '-');
        if ($needs_upgrade || $last_check <= 0 || $last_check > time() + HOUR_IN_SECONDS || (time() - $last_check > DAY_IN_SECONDS)) {
            if (!wp_next_scheduled('phpinfowp_license_ping_async')) {
                wp_schedule_single_event(time(), 'phpinfowp_license_ping_async');
            }
        }

        self::_cache_write($valid);
        return $valid;
    }

    public static function is_unlimited(): bool {
        if (!self::is_valid()) return false;
        $tier = (string) get_option(self::OPT_TIER, '');
        if ($tier === 'unlimited') return true;
        if ($tier === 'single') return false;

        $p = self::payload();
        if (!$p) return false;
        if (isset($p['tier'])) {
            return $p['tier'] === 'unlimited';
        }
        if (isset($p['iat']) && (int) $p['iat'] < 1781523600) {
            return true;
        }
        return trim((string) ($p['url'] ?? '')) === '*';
    }

    public static function is_lifetime(): bool {
        if (!self::is_valid()) return false;
        $tier = (string) get_option(self::OPT_TIER, '');
        if ($tier === 'lifetime') return true;
        if ($tier === 'single') return false;

        $p = self::payload();
        return !empty($p['is_lifetime']);
    }

    public static function can_monitor_extra_domains(): bool {
        return self::is_unlimited() || self::is_lifetime();
    }

    public static function is_locked(): bool {
        $stored = get_option(self::OPT_LOCKED, '');
        if ($stored === '1' || $stored === 1) return true;
        if (!$stored || strpos((string) $stored, '|') === false) return false;

        [$mac, $data_b64] = explode('|', (string) $stored, 2);
        $data     = base64_decode($data_b64);
        if (!$data) return true;
        $expected = hash_hmac('sha256', $data, self::_cache_secret());
        if (!hash_equals($expected, $mac)) return true;

        $parsed = json_decode($data, true);
        return isset($parsed['locked']) && (bool) $parsed['locked'];
    }

    public static function lock_revoked(string $reason = 'revoked'): void {
        $data = json_encode([
            'locked' => true,
            'reason' => $reason,
            'at'     => time(),
        ]);
        $mac = hash_hmac('sha256', $data, self::_cache_secret());
        update_option(self::OPT_LOCKED, $mac . '|' . base64_encode($data), false);
        update_option(self::OPT_REVOKE_REASON, $reason, false);
        self::_cache_write(false);
    }

    public static function activate(string $key, ?string &$reason = null): bool {
        $key = sanitize_text_field(trim($key));
        if (empty($key)) {
            $reason = 'missing_key';
            return false;
        }

        if (substr($key, 0, 6) !== self::KEY_V2 . '-' && substr($key, 0, 5) !== self::KEY_V1 . '-') {
            $reason = 'invalid_format';
            return false;
        }

        if (!self::_validate_local($key)) {
            $reason = 'bad_signature';
            return false;
        }

        $remote = self::check_remote($key);

        if ($remote['status'] === 'invalid') {
            $reason = (string) ($remote['reason'] ?? 'revoked');
            $fatal_lock = ['revoked', 'refund', 'refunded', 'dispute', 'disputed'];
            foreach ($fatal_lock as $fl) {
                if (stripos($reason, $fl) !== false) {
                    self::deactivate();
                    self::lock_revoked($reason);
                    break;
                }
            }
            return false;
        }

        if (!empty($remote['tier'])) {
            update_option(self::OPT_TIER, sanitize_text_field($remote['tier']), false);
        }
        update_option(self::OPT_KEY, $key, false);
        self::_cache_clear();
        delete_option(self::OPT_FAILS);
        delete_option(self::OPT_LOCKED);
        delete_option(self::OPT_REVOKE_REASON);
        update_option(self::OPT_LAST_CHECK, time(), false);

        self::_cache_write(true);
        return true;
    }

    public static function deactivate(): void {
        delete_option(self::OPT_KEY);
        delete_option(self::OPT_TIER);
        self::_cache_clear();
        delete_option(self::OPT_FAILS);
        delete_option(self::OPT_LOCKED);
        delete_option(self::OPT_REVOKE_REASON);
        delete_option(self::OPT_LAST_CHECK);
    }

    public static function cron_ping(): void {
        $key = self::get_key();
        if (!$key) return;

        $check = self::check_remote($key);

        if ($check['status'] === 'valid') {
            update_option(self::OPT_FAILS, 0, false);
            delete_option(self::OPT_LOCKED);
            delete_option(self::OPT_REVOKE_REASON);
            update_option(self::OPT_LAST_CHECK, time(), false);
            if (!empty($check['tier'])) {
                update_option(self::OPT_TIER, sanitize_text_field($check['tier']), false);
            }
            self::_cache_write(true);
            return;
        }

        if ($check['status'] === 'invalid') {
            self::lock_revoked((string) ($check['reason'] ?? 'revoked'));
            return;
        }

        $fails = (int) get_option(self::OPT_FAILS, 0) + 1;
        update_option(self::OPT_FAILS, $fails, false);
        if ($fails >= self::MAX_FAILS) {
            self::lock_revoked('unreachable');
        }
    }

    // --- Remote verification ---

    public static function ajax_activate(): void {
        check_ajax_referer('phpinfowp_license_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'phpinfo-wp')], 403);
        }

        $rate_key = 'phpinfowp_lic_rate_' . get_current_user_id();
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 5) {
            wp_send_json_error(['message' => __('Too many activation attempts. Please wait an hour before trying again.', 'phpinfo-wp')], 429);
        }
        set_transient($rate_key, $attempts + 1, defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600);

        $key = sanitize_text_field(trim($_POST['license_key'] ?? ''));
        if (empty($key)) {
            wp_send_json_error(['message' => __('Please enter a valid license key.', 'phpinfo-wp')], 400);
        }

        $reason = '';
        $ok = self::activate($key, $reason);

        if ($ok) {
            wp_send_json_success([
                'message' => __('License activated successfully. Pro features are now unlocked.', 'phpinfo-wp'),
            ]);
        } else {
            if ($reason === 'v1_deprecated') {
                $msg = __('Activation failed: Legacy v1 license keys can no longer be activated. Please contact support@exeebit.com for an updated v2 key.', 'phpinfo-wp');
            } elseif ($reason === 'active_site_conflict') {
                $msg = __('Activation failed: This single-site license is currently active on another domain. Please deactivate it on your other website first before activating here.', 'phpinfo-wp');
            } elseif ($reason === 'domain_migration_limit_reached') {
                $msg = __('Activation failed: This single-site license has reached its lifetime limit of 2 domains (1 site transfer allowed). Moving to an additional domain is blocked. Please upgrade to an Unlimited license to use this plugin on more sites.', 'phpinfo-wp');
            } elseif ($reason === 'site_limit_exceeded') {
                $msg = __('Activation failed: This license has reached its maximum site limit. Upgrade your license or deactivate another site.', 'phpinfo-wp');
            } elseif (in_array($reason, ['revoked', 'refund', 'refunded', 'dispute', 'disputed'], true)) {
                $msg = __('Activation failed: This license has been revoked or refunded. Contact support@exeebit.com.', 'phpinfo-wp');
            } elseif ($reason === 'expired') {
                $msg = __('Activation failed: This license has expired. Please renew your license to continue using Pro features.', 'phpinfo-wp');
            } else {
                $msg = __('License key invalid, expired, or unrecognised. If you just purchased, allow a moment for activation to propagate, then try again.', 'phpinfo-wp');
            }
            wp_send_json_error(['message' => $msg], 400);
        }
    }

    public static function deactivate_remote(string $key): void {
        if (empty($key)) return;
        wp_remote_post(self::DEACTIVATE_URL, [
            'timeout'  => 5,
            'blocking' => false,
            'body'     => [
                'license_key' => $key,
                'site_url'    => get_site_url(),
            ],
        ]);
    }

    public static function ajax_deactivate(): void {
        check_ajax_referer('phpinfowp_license_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'phpinfo-wp')], 403);
        }

        $key = self::get_key();
        if (!empty($key)) {
            self::deactivate_remote($key);
        }

        self::deactivate();
        delete_option('phpinfowp_free_trial_expires');
        delete_option('phpinfowp_free_trial_email');

        wp_send_json_success([
            'message' => __('License deactivated. Pro features are disabled.', 'phpinfo-wp'),
        ]);
    }

    public static function check_remote(string $key): array {
        $resp = wp_remote_post(self::PING_URL, [
            'timeout' => 8,
            'body'    => [
                'license_key' => $key,
                'site_url'    => get_site_url(),
                'plugin_v'    => PHPINFOWP_VERSION,
            ],
        ]);

        if (is_wp_error($resp)) {
            return ['status' => 'network_error', 'error' => $resp->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($resp);
        if ($code >= 500 || $code === 429) {
            return ['status' => 'network_error', 'error' => 'HTTP ' . $code];
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (is_array($body) && isset($body['valid'])) {
            if (!empty($body['upgrade_key']) && is_string($body['upgrade_key']) && substr($body['upgrade_key'], 0, 6) === self::KEY_V2 . '-') {
                if (self::_validate_ed25519($body['upgrade_key'])) {
                    update_option(self::OPT_KEY, sanitize_text_field($body['upgrade_key']), false);
                    self::_cache_clear();
                }
            }
            if ($body['valid'] === true) {
                $tier = sanitize_text_field($body['tier'] ?? 'single');
                update_option(self::OPT_TIER, $tier, false);
                return ['status' => 'valid', 'tier' => $tier];
            } else {
                return ['status' => 'invalid', 'reason' => $body['reason'] ?? 'revoked'];
            }
        }

        return ['status' => 'network_error', 'error' => 'Invalid server response'];
    }

    private static function _canary_check(): bool {
        if (self::_validate_local('PIWP2-TAMPER-CANARY-FAIL') !== false) {
            return false;
        }
        if (self::_validate_local('') !== false) {
            return false;
        }
        return true;
    }

    private static function _validate_local(string $key): bool {
        if (empty($key)) return false;

        if (substr($key, 0, 6) === self::KEY_V2 . '-') {
            return self::_validate_ed25519($key);
        }

        if (substr($key, 0, 5) === self::KEY_V1 . '-') {
            return self::is_legacy($key);
        }

        return false;
    }

    private static function _b64url_decode(string $str): ?string {
        $pad = strlen($str) % 4;
        if ($pad === 1) {
            return null;
        }
        if ($pad > 1) {
            $str .= str_repeat('=', 4 - $pad);
        }
        $res = base64_decode(strtr($str, '-_', '+/'), true);
        return ($res !== false) ? $res : null;
    }

    private static function _validate_ed25519(string $key): bool {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        $parts = explode('-', $key, 3);
        if (count($parts) !== 3 || $parts[0] !== self::KEY_V2) return false;

        $payload_b64 = $parts[1];
        $sig_b64     = $parts[2];

        $payload_raw = self::_b64url_decode($payload_b64);
        $sig_raw     = self::_b64url_decode($sig_b64);
        $pub_raw     = base64_decode(self::_pub_key(), true);

        if (!$payload_raw || !$sig_raw || !$pub_raw)                return false;
        if (strlen($sig_raw) !== SODIUM_CRYPTO_SIGN_BYTES)          return false;
        if (strlen($pub_raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) return false;

        if (!sodium_crypto_sign_verify_detached($sig_raw, $payload_raw, $pub_raw)) return false;

        $p = json_decode($payload_raw, true);
        if (!isset($p['url'], $p['exp'], $p['email'])) return false;
        if ((int) $p['exp'] < time()) return false;

        $key_url = trim((string) $p['url']);
        if ($key_url !== '*') {
            $site = rtrim(strtolower(get_site_url()), '/');
            if ($site !== rtrim(strtolower($key_url), '/')) return false;
        }

        return true;
    }

    private static function _ping_remote(string $key): bool {
        $check = self::check_remote($key);
        return $check['status'] === 'valid';
    }

    public static function schedule_remote_check_event(): void {
        if (!wp_next_scheduled('phpinfowp_license_ping')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'phpinfowp_license_ping');
        }
    }

}
