<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_License {

    const OPT_KEY         = 'phpinfowp_license_key';
    const OPT_CACHE       = 'phpinfowp_lic_cache';
    const OPT_FAILS       = 'phpinfowp_lic_fails';
    const OPT_LOCKED      = 'phpinfowp_lic_locked';
    const OPT_REVOKE_REASON = 'phpinfowp_lic_revoke_reason';
    const OPT_LAST_CHECK  = 'phpinfowp_lic_last_check';
    const MAX_FAILS       = 2;
    const PING_URL        = 'https://exeebit.com/api/license/validate';

    // Secret assembled from fragments
    private const _F1 = "\x50\x49\x57\x50";
    private const _F2 = "\x5f\x70\x72\x6f";
    private const _F3 = "\x5f\x73\x65\x63";

    private static function _hmac_secret(): string {
        static $s;
        if ($s !== null) return $s;
        $raw = self::_F1 . self::_F2 . self::_F3 . "\x72\x65\x74\x5f\x76\x31";
        $s   = hash('sha256', $raw, true);
        return $s;
    }

    // Cache secret uses site's AUTH_KEY — unique per WP install.
    // Injecting a fake "valid" row into wp_options fails because the MAC won't verify.
    private static function _cache_secret(): string {
        static $cs;
        if ($cs !== null) return $cs;
        $salt = defined('AUTH_KEY') ? AUTH_KEY : (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'phpinfowp_fallback_salt');
        $cs   = hash('sha256', $salt . 'phpinfowp_cache_v1', true);
        return $cs;
    }

    private static function _cache_write(bool $valid): void {
        $data = json_encode(['v' => (int) $valid, 't' => time()]);
        $mac  = hash_hmac('sha256', $data, self::_cache_secret());
        update_option(self::OPT_CACHE, $mac . '|' . base64_encode($data), false);
    }

    private static function _cache_read(): ?bool {
        $stored = get_option(self::OPT_CACHE, '');
        if (!$stored || strpos($stored, '|') === false) return null;

        [$mac, $data_b64] = explode('|', $stored, 2);
        $data = base64_decode($data_b64);
        if (!$data) return null;

        $expected = hash_hmac('sha256', $data, self::_cache_secret());
        if (!hash_equals($expected, $mac)) return null; // Tampered

        $parsed = json_decode($data, true);
        if (!isset($parsed['v'], $parsed['t'])) return null;
        if (time() - (int) $parsed['t'] > 6 * HOUR_IN_SECONDS) return null; // Stale

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

    // Parse the active key's payload for UI display. Returns null when the
    // key is missing or malformed. Does NOT re-verify the HMAC — call
    // is_valid() for that. Returned keys: email, url, exp, iat (all from
    // the payload), plus derived: days_left, expiry_human, is_lifetime.
    public static function payload(): ?array {
        $key = self::get_key();
        if (!$key) return null;

        $parts = explode('-', $key, 3);
        if (count($parts) !== 3 || $parts[0] !== 'PIWP') return null;

        $raw = base64_decode(strtr($parts[1], '-_', '+/'));
        if (!$raw) return null;
        $p = json_decode($raw, true);
        if (!isset($p['exp'], $p['email'])) return null;

        $exp       = (int) $p['exp'];
        $days_left = (int) floor(($exp - time()) / DAY_IN_SECONDS);
        // Lifetime keys use a sentinel year-2099 timestamp — 50+ years out
        // means we treat it as lifetime rather than print "26,000 days left".
        $is_life   = $days_left > (50 * 365);

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

        // Trigger non-blocking background verification if last remote check is > 24 hours
        $last_check = (int) get_option(self::OPT_LAST_CHECK, 0);
        if (time() - $last_check > DAY_IN_SECONDS) {
            if (!wp_next_scheduled('phpinfowp_license_ping_async')) {
                // Note: wp_schedule_single_event with time() runs on the NEXT page load, not immediately.
                // If WP Cron is disabled via DISABLE_WP_CRON and no system cron is configured,
                // this event may never fire. Local validation handles the fallback gracefully.
                wp_schedule_single_event(time(), 'phpinfowp_license_ping_async');
            }
        }

        self::_cache_write($valid);
        return $valid;
    }

    public static function is_unlimited(): bool {
        if (!self::is_valid()) return false;
        $p = self::payload();
        if (!$p) return false;
        if (isset($p['iat']) && (int) $p['iat'] < 1781523600) {
            return true;
        }
        return trim((string) ($p['url'] ?? '')) === '*';
    }

    public static function is_locked(): bool {
        return (bool) get_option(self::OPT_LOCKED, false);
    }

    public static function lock_revoked(string $reason = 'revoked'): void {
        update_option(self::OPT_LOCKED, 1, false);
        update_option(self::OPT_REVOKE_REASON, $reason, false);
        self::_cache_write(false);
    }

    public static function activate(string $key, ?string &$reason = null): bool {
        $key = sanitize_text_field(trim($key));
        if (empty($key)) {
            $reason = 'missing_key';
            return false;
        }

        // 1. Basic format & signature check locally first
        if (!self::_validate_local($key)) {
            $reason = 'bad_signature';
            return false;
        }

        // 2. Query Exeebit to verify real-time status and check for revocations / site limits
        $remote = self::check_remote($key);

        if ($remote['status'] === 'invalid') {
            // Server EXPLICITLY rejected the key (revoked, refunded, expired, site_limit_exceeded)
            // DO NOT fall back to local validation.
            self::deactivate();
            self::lock_revoked($remote['reason'] ?? 'revoked');
            $reason = $remote['reason'] ?? 'revoked';
            return false;
        }

        // Key is either validated by remote server OR remote server is unreachable (offline grace)
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
        self::_cache_clear();
        delete_option(self::OPT_FAILS);
        delete_option(self::OPT_LOCKED);
        delete_option(self::OPT_REVOKE_REASON);
        delete_option(self::OPT_LAST_CHECK);
    }

    // Called by wp_cron. Locks Pro IMMEDIATELY if server returns invalid/revoked.
    public static function cron_ping(): void {
        $key = self::get_key();
        if (!$key) return;

        $check = self::check_remote($key);

        if ($check['status'] === 'valid') {
            update_option(self::OPT_FAILS, 0, false);
            delete_option(self::OPT_LOCKED);
            delete_option(self::OPT_REVOKE_REASON);
            update_option(self::OPT_LAST_CHECK, time(), false);
            self::_cache_write(true);
            return;
        }

        if ($check['status'] === 'invalid') {
            // Server explicitly returned revoked / refunded / invalid
            // Lock IMMEDIATELY on the very first ping!
            self::lock_revoked($check['reason'] ?? 'revoked');
            return;
        }

        // Network error: only lock after MAX_FAILS consecutive network failures
        $fails = (int) get_option(self::OPT_FAILS, 0) + 1;
        update_option(self::OPT_FAILS, $fails, false);
        if ($fails >= self::MAX_FAILS) {
            update_option(self::OPT_LOCKED, 1, false);
            update_option(self::OPT_REVOKE_REASON, 'unreachable', false);
            self::_cache_clear();
        }
    }

    // --- Remote verification ---

    public static function ajax_activate(): void {
        check_ajax_referer('phpinfowp_license_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'piwp')], 403);
        }

        $key = sanitize_text_field(trim($_POST['license_key'] ?? ''));
        if (empty($key)) {
            wp_send_json_error(['message' => __('Please enter a valid license key.', 'piwp')], 400);
        }

        $reason = '';
        $ok = self::activate($key, $reason);

        if ($ok) {
            wp_send_json_success([
                'message' => __('License activated successfully. Pro features are now unlocked.', 'piwp'),
            ]);
        } else {
            if ($reason === 'site_limit_exceeded') {
                $msg = __('Activation failed: This license has reached its maximum site limit. Upgrade your license or deactivate another site.', 'piwp');
            } elseif (in_array($reason, ['revoked', 'refunded', 'disputed'], true)) {
                $msg = __('Activation failed: This license has been revoked or refunded. Contact support@exeebit.com.', 'piwp');
            } elseif ($reason === 'expired') {
                $msg = __('Activation failed: This license has expired. Please renew your license to continue using Pro features.', 'piwp');
            } else {
                $msg = __('License key invalid, expired, or unrecognised. If you just purchased, allow a moment for activation to propagate, then try again.', 'piwp');
            }
            wp_send_json_error(['message' => $msg], 400);
        }
    }

    public static function ajax_deactivate(): void {
        check_ajax_referer('phpinfowp_license_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'piwp')], 403);
        }

        self::deactivate();
        delete_option('phpinfowp_free_trial_expires');
        delete_option('phpinfowp_free_trial_email');

        wp_send_json_success([
            'message' => __('License deactivated. Pro features are disabled.', 'piwp'),
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
            if ($body['valid'] === true) {
                return ['status' => 'valid', 'tier' => $body['tier'] ?? 'single'];
            } else {
                return ['status' => 'invalid', 'reason' => $body['reason'] ?? 'revoked'];
            }
        }

        return ['status' => 'network_error', 'error' => 'Invalid server response'];
    }

    // --- Key validation ---

    public static function _validate_local(string $key): bool {
        if (empty($key)) return false;

        // Format: PIWP-{base64url_payload}-{32_char_hmac}
        $parts = explode('-', $key, 3);
        if (count($parts) !== 3 || $parts[0] !== 'PIWP') return false;

        $payload_b64 = $parts[1];
        $sig         = $parts[2];

        $expected = substr(hash_hmac('sha256', $payload_b64, self::_hmac_secret()), 0, 32);
        if (!hash_equals($expected, strtolower($sig))) return false;

        $payload_raw = base64_decode(strtr($payload_b64, '-_', '+/'));
        if (!$payload_raw) return false;

        $p = json_decode($payload_raw, true);
        if (!isset($p['url'], $p['exp'], $p['email'])) return false;

        if ((int) $p['exp'] < time()) return false;

        // Wildcard "*" = unlimited/lifetime tiers; server enforces site limits
        // via activation tracking. Otherwise require exact site URL match.
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
