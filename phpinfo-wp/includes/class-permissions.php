<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Permissions {

    public static function register(): void {
        // No background hooks needed. This is an on-demand auditing tool.
    }

    public static function get_php_user(): string {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $user = posix_getpwuid(posix_geteuid());
            return $user['name'] ?? get_current_user();
        }
        return get_current_user();
    }

    public static function scan(): array {
        $php_uid = function_exists('posix_geteuid') ? posix_geteuid() : getmyuid();

        $results = [
            'dangerous' => [],
            'mismatch'  => [],
            'scanned'   => 0,
            'truncated' => false,
            'wp_config' => null,
            'php_uid'   => $php_uid,
            'php_user'  => self::get_php_user(),
        ];

        // 1. Check wp-config.php specifically
        $wp_config_path = self::get_wp_config_path();
        if ($wp_config_path && file_exists($wp_config_path)) {
            $perms = fileperms($wp_config_path) & 0777;
            $owner = fileowner($wp_config_path);
            
            $results['wp_config'] = [
                'path'  => $wp_config_path,
                'perms' => sprintf('%04o', $perms),
                'owner' => function_exists('posix_getpwuid') ? (posix_getpwuid($owner)['name'] ?? $owner) : $owner,
                'is_dangerous' => ($perms === 0666 || $perms === 0777),
            ];
            $results['scanned']++;
        }

        // 2. Targeted scan
        $targets = [
            ABSPATH . WPINC,
            ABSPATH . 'wp-admin',
            WP_PLUGIN_DIR,
            get_theme_root(),
        ];

        $limit = 5000;

        foreach ($targets as $dir) {
            if (!is_dir($dir)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($results['scanned'] >= $limit) {
                    $results['truncated'] = true;
                    break 2;
                }
                
                $results['scanned']++;
                $path = $file->getPathname();
                $is_dir = $file->isDir();
                $perms = $file->getPerms() & 0777;
                $owner = $file->getOwner();

                // Dangerous permissions: 777 on dirs, 666/777 on files
                if ($is_dir && $perms === 0777) {
                    $results['dangerous'][] = ['path' => $path, 'perms' => '0777', 'type' => 'dir'];
                } elseif (!$is_dir && ($perms === 0666 || $perms === 0777)) {
                    $results['dangerous'][] = ['path' => $path, 'perms' => sprintf('%04o', $perms), 'type' => 'file'];
                }

                // Ownership mismatch: File owner doesn't match PHP executor UID
                if ($owner !== $php_uid) {
                    $owner_name = function_exists('posix_getpwuid') ? (posix_getpwuid($owner)['name'] ?? $owner) : $owner;
                    // Cap mismatch results to 20 to prevent memory exhaustion, 
                    // since one wrong `chown` causes thousands of mismatches.
                    if (count($results['mismatch']) < 20) {
                        $results['mismatch'][] = ['path' => $path, 'owner' => $owner_name, 'type' => $is_dir ? 'dir' : 'file'];
                    }
                }
            }
        }

        return $results;
    }

    private static function get_wp_config_path(): ?string {
        if (file_exists(ABSPATH . 'wp-config.php')) {
            return ABSPATH . 'wp-config.php';
        } elseif (file_exists(dirname(ABSPATH) . '/wp-config.php') && !file_exists(dirname(ABSPATH) . '/wp-settings.php')) {
            return dirname(ABSPATH) . '/wp-config.php';
        }
        return null;
    }
}
