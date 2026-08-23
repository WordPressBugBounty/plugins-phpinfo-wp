<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Intercepts outbound HTTP requests to build a 24-hour rolling profile
 * of external API performance. Highlights silent bottlenecks (e.g., slow CRM syncs).
 */
class Phpinfo_WP_API_Monitor {

    const TRANSIENT_KEY = 'phpinfowp_api_stats_v1';
    
    private static $starts   = [];
    private static $stats    = null;
    private static $is_dirty = false;

    public static function register(): void {
        add_filter('http_request_args', [__CLASS__, 'mark_start'], 9999, 2);
        add_action('http_api_debug',    [__CLASS__, 'mark_end'],   9999, 5);
        add_action('shutdown',          [__CLASS__, 'save_stats']);
        
        add_action('wp_ajax_phpinfowp_clear_api_stats', [__CLASS__, 'ajax_clear_stats']);
        add_action('wp_ajax_phpinfowp_test_api', [__CLASS__, 'ajax_test_api']);
    }

    public static function mark_start($args, $url) {
        // Record the start time. We use the URL as the key.
        self::$starts[$url] = microtime(true);
        return $args;
    }

    public static function mark_end($response, $context, $class, $parsed_args, $url): void {
        if (!isset(self::$starts[$url])) return;

        $duration = microtime(true) - self::$starts[$url];
        unset(self::$starts[$url]);

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) $host = 'unknown';

        $is_error = is_wp_error($response);
        $status   = !$is_error ? wp_remote_retrieve_response_code($response) : 0;

        self::record_stat($host, $duration, $is_error, $status);
    }

    private static function record_stat(string $host, float $duration, bool $is_error, int $status): void {
        if (self::$stats === null) {
            self::$stats = get_transient(self::TRANSIENT_KEY) ?: [];
        }

        if (!isset(self::$stats[$host])) {
            // Cap at 100 unique hosts to prevent transient bloat in wp_options
            if (count(self::$stats) >= 100) return;
            
            self::$stats[$host] = [
                'count'      => 0,
                'total_time' => 0.0,
                'max_time'   => 0.0,
                'errors'     => 0,
            ];
        }

        self::$stats[$host]['count']++;
        self::$stats[$host]['total_time'] += $duration;
        
        if ($duration > self::$stats[$host]['max_time']) {
            self::$stats[$host]['max_time'] = $duration;
        }
        
        if ($is_error || $status >= 400) {
            self::$stats[$host]['errors']++;
        }

        self::$is_dirty = true;
    }

    public static function save_stats(): void {
        if (self::$is_dirty && self::$stats !== null) {
            // Keep for 24 hours
            set_transient(self::TRANSIENT_KEY, self::$stats, DAY_IN_SECONDS);
        }
    }

    public static function get_stats(): array {
        $stats = get_transient(self::TRANSIENT_KEY) ?: [];
        
        // Sort by total time descending (slowest hosts first)
        uasort($stats, function($a, $b) {
            return $b['total_time'] <=> $a['total_time'];
        });
        
        return $stats;
    }

    public static function ajax_clear_stats(): void {
        check_ajax_referer('phpinfowp_api_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        
        delete_transient(self::TRANSIENT_KEY);
        wp_send_json_success();
    }

    public static function ajax_test_api(): void {
        check_ajax_referer('phpinfowp_api_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        
        // This will take ~1.5s and trigger our hooks
        wp_remote_get('https://httpstat.us/200?sleep=1500', ['timeout' => 5]);
        
        // Force save immediately so the reload sees it
        self::save_stats();
        
        wp_send_json_success();
    }
}
