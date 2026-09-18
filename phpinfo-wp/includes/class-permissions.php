<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Permissions {

    public static function register(): void {
        add_action('wp_ajax_phpinfowp_perms_scan',  [self::class, 'ajax_scan']);
        add_action('wp_ajax_phpinfowp_perms_clear', [self::class, 'ajax_clear']);
        add_action('wp_ajax_phpinfowp_perms_fix',   [self::class, 'ajax_fix']);
    }

    private static function _pro(): bool {
        return class_exists('Phpinfo_WP_License') && Phpinfo_WP_License::is_valid();
    }

    public static function ajax_fix(): void {
        check_ajax_referer('phpinfowp_perms_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }
        if (!self::_pro()) {
            wp_send_json_error(['message' => __('Pro license required to auto-fix permissions.', 'phpinfo-wp')]);
        }

        $result = self::auto_fix();
        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::complete('perms', [
                'scanned'        => $result['scanned'] ?? 0,
                'dangerous'      => $result['dangerous_left'] ?? 0,
                'mismatch_count' => $result['mismatch_count'] ?? 0,
            ]);
        }
        wp_send_json_success($result);
    }

    public static function auto_fix(): array {
        if (!self::_pro()) {
            return ['error' => __('Pro license required.', 'phpinfo-wp')];
        }

        @set_time_limit(180);
        $fixed  = [];
        $failed = [];

        // 1. wp-config.php hardening
        $wp_config_path = self::get_wp_config_path();
        if ($wp_config_path && file_exists($wp_config_path)) {
            $perms = @fileperms($wp_config_path);
            if ($perms !== false) {
                $current_mode = $perms & 0777;
                if ($current_mode > 0600) {
                    $ok = @chmod($wp_config_path, 0600);
                    if (!$ok) {
                        $ok = @chmod($wp_config_path, 0640);
                    }
                    if ($ok) {
                        clearstatcache(true, $wp_config_path);
                        $new_perms = fileperms($wp_config_path) & 0777;
                        $fixed[] = [
                            'path' => $wp_config_path,
                            'from' => sprintf('%04o', $current_mode),
                            'to'   => sprintf('%04o', $new_perms),
                            'type' => 'file',
                        ];
                    } else {
                        $failed[] = [
                            'path'   => $wp_config_path,
                            'perms'  => sprintf('%04o', $current_mode),
                            'reason' => __('Ownership restriction (chmod denied)', 'phpinfo-wp'),
                            'type'   => 'file',
                        ];
                    }
                }
            }
        }

        // 2. Critical entrypoints & security files in root
        $critical = [
            ABSPATH . '.htaccess',
            ABSPATH . '.user.ini',
            ABSPATH . 'php.ini',
            ABSPATH . 'index.php',
            WP_CONTENT_DIR . '/debug.log',
        ];
        foreach ($critical as $c_path) {
            if (!file_exists($c_path)) continue;
            $perms = @fileperms($c_path);
            if ($perms === false) continue;
            $current_mode = $perms & 0777;
            if ($current_mode === 0666 || $current_mode === 0777 || $current_mode > 0644) {
                $target_mode = ($c_path === WP_CONTENT_DIR . '/debug.log') ? 0600 : 0644;
                $ok = @chmod($c_path, $target_mode);
                if ($ok) {
                    clearstatcache(true, $c_path);
                    $new_perms = fileperms($c_path) & 0777;
                    $fixed[] = [
                        'path' => $c_path,
                        'from' => sprintf('%04o', $current_mode),
                        'to'   => sprintf('%04o', $new_perms),
                        'type' => 'file',
                    ];
                } else {
                    $failed[] = [
                        'path'   => $c_path,
                        'perms'  => sprintf('%04o', $current_mode),
                        'reason' => __('Ownership restriction (chmod denied)', 'phpinfo-wp'),
                        'type'   => 'file',
                    ];
                }
            }
        }

        // 3. Scan results (dangerous world-writable files & directories)
        $scan_res  = self::scan(false);
        $dangerous = $scan_res['dangerous'] ?? [];
        foreach ($dangerous as $item) {
            $path = $item['path'] ?? '';
            if (!$path || !file_exists($path)) continue;
            $perms = @fileperms($path);
            if ($perms === false) continue;
            $current_mode = $perms & 0777;
            $is_dir = is_dir($path);
            $target_mode = $is_dir ? 0755 : 0644;

            if ($current_mode !== $target_mode) {
                $ok = @chmod($path, $target_mode);
                if ($ok) {
                    clearstatcache(true, $path);
                    $new_perms = fileperms($path) & 0777;
                    $fixed[] = [
                        'path' => $path,
                        'from' => sprintf('%04o', $current_mode),
                        'to'   => sprintf('%04o', $new_perms),
                        'type' => $is_dir ? 'dir' : 'file',
                    ];
                } else {
                    $failed[] = [
                        'path'   => $path,
                        'perms'  => sprintf('%04o', $current_mode),
                        'reason' => __('Ownership restriction (chmod denied)', 'phpinfo-wp'),
                        'type'   => $is_dir ? 'dir' : 'file',
                    ];
                }
            }
        }

        // 4. Force re-scan to immediately update the cache
        self::clear();
        $fresh = self::scan(true);

        return [
            'fixed_count'    => count($fixed),
            'failed_count'   => count($failed),
            'fixed'          => array_slice($fixed, 0, 50),
            'failed'         => array_slice($failed, 0, 50),
            'scanned'        => $fresh['scanned'] ?? 0,
            'dangerous_left' => count($fresh['dangerous'] ?? []),
            'wp_config'      => $fresh['wp_config'] ?? null,
            'mismatch_count' => $fresh['mismatch_count'] ?? 0,
        ];
    }

    public static function ajax_scan(): void {
        check_ajax_referer('phpinfowp_perms_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }

        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::start('perms');
        }

        $result = self::scan(true);

        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::complete('perms', [
                'scanned'        => $result['scanned'] ?? 0,
                'dangerous'      => count($result['dangerous'] ?? []),
                'mismatch_count' => $result['mismatch_count'] ?? 0,
            ]);
        }

        wp_send_json_success($result);
    }

    public static function get_result(): ?array {
        $cached = get_transient('phpinfowp_perms_scan');
        return is_array($cached) ? $cached : null;
    }

    public static function clear(): void {
        delete_transient('phpinfowp_perms_scan');
    }

    public static function ajax_clear(): void {
        check_ajax_referer('phpinfowp_perms_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'phpinfo-wp')]);
        }
        self::clear();
        if (class_exists('Phpinfo_WP_Background_Scan')) {
            Phpinfo_WP_Background_Scan::clear('perms');
        }
        wp_send_json_success();
    }

    public static function get_php_user(): string {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $user = posix_getpwuid(posix_geteuid());
            return $user['name'] ?? get_current_user();
        }
        return get_current_user();
    }

    public static function scan(bool $force_refresh = false): array {
        static $memo = null;
        if (!$force_refresh && $memo !== null) {
            return $memo;
        }

        if (!$force_refresh) {
            $cached = get_transient('phpinfowp_perms_scan');
            if (is_array($cached)) {
                $memo = $cached;
                return $cached;
            }
        }

        @set_time_limit(180);
        $started  = microtime(true);
        $deadline = $started + 25.0; // Circuit-breaker safety deadline (in seconds) for rare slow NFS network mounts

        $php_uid  = function_exists('posix_geteuid') ? posix_geteuid() : (function_exists('getmyuid') ? getmyuid() : false);
        $php_user = self::get_php_user();

        $results = [
            'dangerous'      => [],
            'mismatch'       => [],
            'mismatch_count' => 0,
            'scanned'        => 0,
            'scanned_dirs'   => 0,
            'scanned_files'  => 0,
            'truncated'      => false,
            'wp_config'      => null,
            'php_uid'        => $php_uid,
            'php_user'       => $php_user,
            'duration'       => 0,
            'scanned_at'     => time(),
        ];

        $audited_paths = [];

        // 1. Critical configuration, entrypoints & security files
        $critical_files = [
            'wp-config.php'   => self::get_wp_config_path(),
            '.htaccess'       => file_exists(ABSPATH . '.htaccess') ? ABSPATH . '.htaccess' : null,
            '.user.ini'       => file_exists(ABSPATH . '.user.ini') ? ABSPATH . '.user.ini' : null,
            'php.ini'         => file_exists(ABSPATH . 'php.ini') ? ABSPATH . 'php.ini' : null,
            'web.config'      => file_exists(ABSPATH . 'web.config') ? ABSPATH . 'web.config' : null,
            'debug.log'       => file_exists(WP_CONTENT_DIR . '/debug.log') ? WP_CONTENT_DIR . '/debug.log' : null,
            'index.php'       => file_exists(ABSPATH . 'index.php') ? ABSPATH . 'index.php' : null,
            'wp-settings.php' => file_exists(ABSPATH . 'wp-settings.php') ? ABSPATH . 'wp-settings.php' : null,
            'wp-cron.php'     => file_exists(ABSPATH . 'wp-cron.php') ? ABSPATH . 'wp-cron.php' : null,
            'wp-login.php'    => file_exists(ABSPATH . 'wp-login.php') ? ABSPATH . 'wp-login.php' : null,
        ];

        foreach ($critical_files as $label => $file_path) {
            if (!$file_path || !file_exists($file_path)) continue;
            $real = realpath($file_path) ?: $file_path;
            if (isset($audited_paths[$real])) continue;
            $audited_paths[$real] = true;

            $perms = @fileperms($file_path);
            if ($perms === false) continue;
            $perms = $perms & 0777;

            $owner = @fileowner($file_path);
            $owner_name = ($owner !== false && function_exists('posix_getpwuid')) ? (posix_getpwuid($owner)['name'] ?? (string)$owner) : (string)$owner;
            $is_dangerous = ($perms === 0666 || $perms === 0777);

            if ($label === 'wp-config.php') {
                $results['wp_config'] = [
                    'path'         => $file_path,
                    'perms'        => sprintf('%04o', $perms),
                    'owner'        => $owner_name,
                    'is_dangerous' => $is_dangerous,
                ];
            } elseif ($is_dangerous) {
                $results['dangerous'][] = [
                    'path'  => $file_path,
                    'perms' => sprintf('%04o', $perms),
                    'type'  => 'file',
                ];
            }

            if ($owner !== false && $php_uid !== false && $owner !== $php_uid) {
                $results['mismatch_count']++;
                if (count($results['mismatch']) < 50) {
                    $results['mismatch'][] = [
                        'path'  => $file_path,
                        'owner' => $owner_name,
                        'type'  => 'file',
                    ];
                }
            }

            $results['scanned']++;
            $results['scanned_files']++;
        }

        // 2. Targeted directory scan with security-first exclusion & inclusion criteria
        $upload_dir = function_exists('wp_upload_dir') ? (wp_upload_dir()['basedir'] ?? (WP_CONTENT_DIR . '/uploads')) : (WP_CONTENT_DIR . '/uploads');

        $targets = [
            'wp_admin'    => ABSPATH . 'wp-admin',
            'wp_includes' => ABSPATH . WPINC,
            'plugins'     => WP_PLUGIN_DIR,
            'mu_plugins'  => defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : (WP_CONTENT_DIR . '/mu-plugins'),
            'themes'      => function_exists('get_theme_root') ? get_theme_root() : (WP_CONTENT_DIR . '/themes'),
            'languages'   => defined('WP_LANG_DIR') ? WP_LANG_DIR : (WP_CONTENT_DIR . '/languages'),
            'uploads'     => $upload_dir,
        ];

        // Directories to always skip traversing into
        $skip_dirs = [
            '.git', '.svn', '.hg', '.bzr', '.github',
            'node_modules', 'bower_components', 'vendor/bin',
            'cache', 'tmp', 'temp', 'w3tc-cache', 'wp-rocket-config',
            'autoptimize_cache', 'et-cache', '.well-known', '.idea', '.vscode'
        ];

        // File extensions to skip across code directories (static media & binary assets that never execute)
        $skip_extensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'tiff', 'psd',
            'mp4', 'm4v', 'webm', 'mov', 'avi', 'mkv', 'mp3', 'wav', 'ogg', 'flac', 'aac',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'tar', 'gz', 'bz2', '7z', 'rar',
            'woff', 'woff2', 'ttf', 'eot', 'otf', 'map', 'po', 'mo', 'csv'
        ];

        // Executable / critical extensions to look for inside uploads directory
        $executable_extensions = [
            'php', 'phtml', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar', 'inc',
            'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'bin', 'htaccess', 'ini', 'conf', 'env'
        ];

        // Scan root directory files (shallow scan of ABSPATH directly)
        if (is_dir(ABSPATH)) {
            try {
                $root_items = @scandir(ABSPATH);
                if (is_array($root_items)) {
                    foreach ($root_items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $item_path = rtrim(ABSPATH, '/\\') . '/' . $item;
                        $real = realpath($item_path) ?: $item_path;
                        if (isset($audited_paths[$real])) continue;
                        $audited_paths[$real] = true;

                        $is_dir = is_dir($item_path);
                        $perms = @fileperms($item_path);
                        if ($perms === false) continue;
                        $perms = $perms & 0777;

                        $owner = @fileowner($item_path);
                        $owner_name = ($owner !== false && function_exists('posix_getpwuid')) ? (posix_getpwuid($owner)['name'] ?? (string)$owner) : (string)$owner;

                        if ($is_dir) {
                            $results['scanned_dirs']++;
                            if ($perms === 0777) {
                                $results['dangerous'][] = ['path' => $item_path, 'perms' => '0777', 'type' => 'dir'];
                            }
                        } else {
                            $results['scanned_files']++;
                            if ($perms === 0666 || $perms === 0777) {
                                $results['dangerous'][] = ['path' => $item_path, 'perms' => sprintf('%04o', $perms), 'type' => 'file'];
                            }
                        }

                        if ($owner !== false && $php_uid !== false && $owner !== $php_uid) {
                            $results['mismatch_count']++;
                            if (count($results['mismatch']) < 50) {
                                $results['mismatch'][] = ['path' => $item_path, 'owner' => $owner_name, 'type' => $is_dir ? 'dir' : 'file'];
                            }
                        }
                        $results['scanned']++;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Recursive traversal of targets
        foreach ($targets as $target_key => $dir) {
            if (!is_dir($dir)) continue;

            try {
                $dir_iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
                $is_uploads = ($target_key === 'uploads');

                $filter_iterator = new RecursiveCallbackFilterIterator($dir_iterator, function ($current, $key, $iterator) use ($skip_dirs, $skip_extensions, $executable_extensions, $is_uploads) {
                    $filename = $current->getFilename();
                    $lower_name = strtolower($filename);

                    if ($current->isDir()) {
                        return !in_array($lower_name, $skip_dirs, true);
                    }

                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    // In uploads directory: ONLY check executable scripts & configs (media assets are skipped from recursion)
                    if ($is_uploads) {
                        return in_array($ext, $executable_extensions, true) || in_array($lower_name, ['.htaccess', '.user.ini', 'php.ini', 'web.config'], true);
                    }

                    // In code directories: skip media/binary/font assets, keep all code, templates, and config files
                    return !in_array($ext, $skip_extensions, true);
                });

                $iterator = new RecursiveIteratorIterator($filter_iterator, RecursiveIteratorIterator::SELF_FIRST);

                foreach ($iterator as $file) {
                    // Safety check: ensure we do not exceed deadline on slow network shares
                    if (microtime(true) > $deadline) {
                        $results['truncated'] = true;
                        break 2;
                    }

                    $path   = $file->getPathname();
                    $real   = realpath($path) ?: $path;
                    if (isset($audited_paths[$real])) continue;
                    $audited_paths[$real] = true;

                    $is_dir = $file->isDir();
                    $perms  = $file->getPerms() & 0777;
                    $owner  = $file->getOwner();
                    $owner_name = ($owner !== false && function_exists('posix_getpwuid')) ? (posix_getpwuid($owner)['name'] ?? (string)$owner) : (string)$owner;

                    if ($is_dir) {
                        $results['scanned_dirs']++;
                    } else {
                        $results['scanned_files']++;
                    }
                    $results['scanned']++;

                    // Dangerous permissions check: 777 on dirs, 666/777 on files
                    if ($is_dir && $perms === 0777) {
                        $results['dangerous'][] = ['path' => $path, 'perms' => '0777', 'type' => 'dir'];
                    } elseif (!$is_dir) {
                        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                        // In uploads: any executable PHP script is high risk regardless of permissions
                        if ($is_uploads && in_array($ext, ['php', 'phtml', 'phar', 'sh', 'cgi', 'pl'], true)) {
                            $results['dangerous'][] = [
                                'path'   => $path,
                                'perms'  => sprintf('%04o', $perms),
                                'type'   => 'file',
                                'reason' => 'Executable script in uploads directory',
                            ];
                        } elseif ($perms === 0666 || $perms === 0777) {
                            $results['dangerous'][] = ['path' => $path, 'perms' => sprintf('%04o', $perms), 'type' => 'file'];
                        }
                    }

                    // Ownership mismatch check
                    if ($owner !== false && $php_uid !== false && $owner !== $php_uid) {
                        $results['mismatch_count']++;
                        if (count($results['mismatch']) < 50) {
                            $results['mismatch'][] = ['path' => $path, 'owner' => $owner_name, 'type' => $is_dir ? 'dir' : 'file'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Gracefully continue on unreadable directories
                continue;
            }
        }

        $results['duration'] = round(microtime(true) - $started, 3);

        set_transient('phpinfowp_perms_scan', $results, HOUR_IN_SECONDS);
        $memo = $results;
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
