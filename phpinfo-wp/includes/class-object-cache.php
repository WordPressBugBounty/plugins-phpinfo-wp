<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Object_Cache {

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    public static function has_dropin(): bool {
        return file_exists(WP_CONTENT_DIR . '/object-cache.php');
    }

    public static function is_redis_extension_loaded(): bool {
        return extension_loaded('redis');
    }

    public static function is_memcached_extension_loaded(): bool {
        return extension_loaded('memcached') || extension_loaded('memcache');
    }

    public static function active_type(): ?string {
        if (!self::has_dropin()) return null;
        
        $dropin = file_get_contents(WP_CONTENT_DIR . '/object-cache.php');
        if (stripos($dropin, 'redis') !== false) {
            return 'redis';
        }
        if (stripos($dropin, 'memcache') !== false) {
            return 'memcached';
        }
        return 'unknown';
    }

    public static function status(): ?array {
        $type = self::active_type();
        if (!$type) return null;

        $status = [
            'type' => $type,
            'hit_rate' => null,
            'hits' => 0,
            'misses' => 0,
            'memory_used' => 0,
            'memory_total' => 0,
            'memory_pct' => 0,
            'evictions' => 0,
            'keys' => 0,
            'connection' => 'unknown',
        ];

        global $wp_object_cache;

        // Try to get stats from the drop-in object
        if (is_object($wp_object_cache)) {
            // Some drop-ins store connection info here
            if ($type === 'redis') {
                if (method_exists($wp_object_cache, 'redis_status')) {
                    // Try to infer from redis Object Cache dropin
                    if (isset($wp_object_cache->redis_client)) {
                        try {
                            $info = $wp_object_cache->redis_client->info();
                            if ($info) {
                                $status['memory_used'] = $info['used_memory'] ?? 0;
                                $status['memory_total'] = $info['maxmemory'] ?? 0;
                                $status['evictions'] = $info['evicted_keys'] ?? 0;
                                if (isset($info['db0'])) {
                                    preg_match('/keys=(\d+)/', $info['db0'], $matches);
                                    $status['keys'] = isset($matches[1]) ? (int)$matches[1] : 0;
                                }
                                $status['hits'] = $info['keyspace_hits'] ?? 0;
                                $status['misses'] = $info['keyspace_misses'] ?? 0;
                            }
                        } catch (Exception $e) {}
                    }
                }
                
                // Connection type
                if (defined('WP_REDIS_SCHEME') && WP_REDIS_SCHEME === 'unix') {
                    $status['connection'] = 'UNIX Socket (Fast)';
                } elseif (defined('WP_REDIS_HOST')) {
                    $status['connection'] = 'TCP ' . WP_REDIS_HOST . ':' . (defined('WP_REDIS_PORT') ? WP_REDIS_PORT : '6379');
                }
            } elseif ($type === 'memcached') {
                if (method_exists($wp_object_cache, 'get_stats')) {
                    $stats = $wp_object_cache->get_stats();
                    if (!empty($stats) && is_array($stats)) {
                        $first = reset($stats); // usually keyed by server
                        if (is_array($first)) {
                            $status['hits'] = $first['get_hits'] ?? 0;
                            $status['misses'] = $first['get_misses'] ?? 0;
                            $status['memory_used'] = $first['bytes'] ?? 0;
                            $status['memory_total'] = $first['limit_maxbytes'] ?? 0;
                            $status['evictions'] = $first['evictions'] ?? 0;
                            $status['keys'] = $first['curr_items'] ?? 0;
                        }
                    }
                }
                $status['connection'] = 'TCP (Standard)';
            }

            // General WP Cache stats fallback
            if ($status['hits'] === 0 && isset($wp_object_cache->cache_hits)) {
                $status['hits'] = $wp_object_cache->cache_hits;
                $status['misses'] = $wp_object_cache->cache_misses;
            }
        }

        if (($status['hits'] + $status['misses']) > 0) {
            $status['hit_rate'] = round($status['hits'] / ($status['hits'] + $status['misses']) * 100, 2);
        }
        
        if ($status['memory_total'] > 0) {
            $status['memory_pct'] = round($status['memory_used'] / $status['memory_total'] * 100, 1);
        }

        return $status;
    }

    public static function flush(): bool {
        if (!self::_pro()) return false;
        if (function_exists('wp_cache_flush')) {
            return wp_cache_flush();
        }
        return false;
    }

    public static function format_bytes(int $bytes): string {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public static function hit_rate_class(float $rate): string {
        if ($rate >= 90) return 'grade-a';
        if ($rate >= 70) return 'grade-b';
        if ($rate >= 50) return 'grade-c';
        return 'grade-f';
    }
}
