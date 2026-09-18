<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Activity_Log {

    const DB_VERSION_OPT = 'phpinfowp_activity_log_db_ver';
    const DB_VERSION     = '1.0.1';
    const RETENTION_OPT  = 'phpinfowp_activity_log_retention';
    const DEFAULT_RETENTION_DAYS = 60;

    private static $captured_options = [
        'admin_email'         => 'Admin Email',
        'siteurl'             => 'Site URL',
        'home'                => 'Home URL',
        'blogname'            => 'Site Title',
        'blogdescription'     => 'Tagline',
        'users_can_register'  => 'Anyone Can Register',
        'default_role'        => 'Default New User Role',
        'permalink_structure' => 'Permalink Structure',
        'WPLANG'              => 'Site Language',
        'timezone_string'     => 'Timezone',
        'date_format'         => 'Date Format',
        'time_format'         => 'Time Format',
    ];

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'phpinfowp_activity_log';
    }

    public static function init(): void {
        self::maybe_create_table();
        self::register_hooks();
        self::register_cron();
    }

    public static function maybe_create_table(): void {
        if (get_option(self::DB_VERSION_OPT) === self::DB_VERSION) {
            return;
        }

        global $wpdb;
        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            user_login varchar(60) NOT NULL DEFAULT '',
            user_role varchar(50) NOT NULL DEFAULT '',
            ip_address varchar(45) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            category varchar(30) NOT NULL DEFAULT 'general',
            action varchar(50) NOT NULL DEFAULT '',
            object_id varchar(100) NOT NULL DEFAULT '',
            object_name varchar(255) NOT NULL DEFAULT '',
            severity varchar(20) NOT NULL DEFAULT 'info',
            details longtext DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY category (category),
            KEY action (action),
            KEY severity (severity),
            KEY user_login (user_login)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Clean any noisy auto-drafts / global styles logged during initial setup
        $wpdb->query("DELETE FROM {$table_name} WHERE object_name LIKE '%Auto Draft%' OR object_name LIKE '%Global Styles%' OR object_name LIKE '%(Untitled%'");

        update_option(self::DB_VERSION_OPT, self::DB_VERSION, false);
    }

    public static function log(
        string $category,
        string $action,
        string $object_name = '',
        array $details = [],
        string $severity = 'info',
        ?int $user_id = null,
        string $object_id = ''
    ): bool {
        global $wpdb;
        $table_name = self::table_name();

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $user_login = '';
        $user_role  = '';

        if ($user_id > 0) {
            $u = get_userdata($user_id);
            if ($u) {
                $user_login = $u->user_login;
                $user_role  = !empty($u->roles) ? (string) reset($u->roles) : '';
            }
        } elseif (!empty($details['attempted_login'])) {
            $user_login = (string) $details['attempted_login'];
        }

        $ip_address = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : '';

        $json_details = !empty($details) ? wp_json_encode($details) : null;

        $inserted = $wpdb->insert(
            $table_name,
            [
                'created_at'  => current_time('mysql', 1), // UTC time
                'user_id'     => $user_id ?: null,
                'user_login'  => substr($user_login, 0, 60),
                'user_role'   => substr($user_role, 0, 50),
                'ip_address'  => substr($ip_address, 0, 45),
                'user_agent'  => $user_agent,
                'category'    => substr($category, 0, 30),
                'action'      => substr($action, 0, 50),
                'object_id'   => substr($object_id, 0, 100),
                'object_name' => substr($object_name, 0, 255),
                'severity'    => in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'info',
                'details'     => $json_details,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $inserted !== false;
    }

    private static function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER[$h])));
                $ip  = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    public static function register_hooks(): void {
        // 1. Authentication Hooks
        add_action('wp_login', [__CLASS__, 'on_wp_login'], 10, 2);
        add_action('wp_login_failed', [__CLASS__, 'on_wp_login_failed'], 10, 1);
        add_action('clear_auth_cookie', [__CLASS__, 'on_wp_logout'], 10, 0);

        // 2. Plugins & Themes Hooks
        add_action('activated_plugin', [__CLASS__, 'on_plugin_activated'], 10, 2);
        add_action('deactivated_plugin', [__CLASS__, 'on_plugin_deactivated'], 10, 2);
        add_action('deleted_plugin', [__CLASS__, 'on_plugin_deleted'], 10, 2);
        add_action('switch_theme', [__CLASS__, 'on_switch_theme'], 10, 3);
        add_action('deleted_theme', [__CLASS__, 'on_deleted_theme'], 10, 2);
        add_action('upgrader_process_complete', [__CLASS__, 'on_upgrader_process_complete'], 10, 2);

        // 3. Media & Uploads Hooks
        add_action('add_attachment', [__CLASS__, 'on_add_attachment'], 10, 1);
        add_action('delete_attachment', [__CLASS__, 'on_delete_attachment'], 10, 1);

        // 4. Menus & Navigation
        add_action('wp_create_nav_menu', [__CLASS__, 'on_create_nav_menu'], 10, 2);
        add_action('wp_update_nav_menu', [__CLASS__, 'on_update_nav_menu'], 10, 2);
        add_action('wp_delete_nav_menu', [__CLASS__, 'on_delete_nav_menu'], 10, 1);

        // 5. Taxonomies (Categories / Tags)
        add_action('create_term', [__CLASS__, 'on_create_term'], 10, 3);
        add_action('delete_term', [__CLASS__, 'on_delete_term'], 10, 5);

        // 6. Comments
        add_action('wp_insert_comment', [__CLASS__, 'on_insert_comment'], 10, 2);
        add_action('transition_comment_status', [__CLASS__, 'on_transition_comment_status'], 10, 3);

        // 7. Core Settings Hooks
        add_action('updated_option', [__CLASS__, 'on_updated_option'], 10, 3);

        // 8. Users Hooks
        add_action('user_register', [__CLASS__, 'on_user_register'], 10, 1);
        add_action('profile_update', [__CLASS__, 'on_profile_update'], 10, 2);
        add_action('set_user_role', [__CLASS__, 'on_set_user_role'], 10, 3);
        add_action('delete_user', [__CLASS__, 'on_delete_user'], 10, 2);

        // 9. Posts / Pages / Content Hooks
        add_action('transition_post_status', [__CLASS__, 'on_transition_post_status'], 10, 3);
        add_action('before_delete_post', [__CLASS__, 'on_before_delete_post'], 10, 1);

        // 10. AJAX Handlers
        add_action('wp_ajax_phpinfowp_export_activity_log', [__CLASS__, 'ajax_export_csv']);
    }

    // --- Authentication Listeners ---
    public static function on_wp_login(string $user_login, WP_User $user): void {
        self::log(
            'auth',
            'login_success',
            sprintf(__('User "%s" logged in', 'phpinfo-wp'), $user_login),
            ['roles' => (array) $user->roles],
            'info',
            $user->ID
        );
    }

    public static function on_wp_login_failed(string $username): void {
        self::log(
            'auth',
            'login_failed',
            sprintf(__('Failed login attempt for "%s"', 'phpinfo-wp'), $username),
            ['attempted_login' => $username],
            'warning',
            0
        );
    }

    public static function on_wp_logout(): void {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $u = get_userdata($user_id);
            $login = $u ? $u->user_login : 'User';
            self::log(
                'auth',
                'logout',
                sprintf(__('User "%s" logged out', 'phpinfo-wp'), $login),
                [],
                'info',
                $user_id
            );
        }
    }

    // --- Plugins & Themes Listeners ---
    public static function on_plugin_activated(string $plugin, bool $network_wide = false): void {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
        $name        = !empty($plugin_data['Name']) ? $plugin_data['Name'] : $plugin;

        self::log(
            'plugins',
            'plugin_activated',
            sprintf(__('Activated plugin "%s"', 'phpinfo-wp'), $name),
            [
                'plugin_file'  => $plugin,
                'version'      => $plugin_data['Version'] ?? '',
                'network_wide' => $network_wide,
            ],
            'info'
        );
    }

    public static function on_plugin_deactivated(string $plugin, bool $network_wide = false): void {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
        $name        = !empty($plugin_data['Name']) ? $plugin_data['Name'] : $plugin;

        self::log(
            'plugins',
            'plugin_deactivated',
            sprintf(__('Deactivated plugin "%s"', 'phpinfo-wp'), $name),
            [
                'plugin_file'  => $plugin,
                'network_wide' => $network_wide,
            ],
            'warning'
        );
    }

    public static function on_plugin_deleted(string $plugin, bool $deleted): void {
        if (!$deleted) return;
        self::log(
            'plugins',
            'plugin_deleted',
            sprintf(__('Deleted plugin "%s"', 'phpinfo-wp'), $plugin),
            ['plugin_file' => $plugin],
            'warning'
        );
    }

    public static function on_switch_theme(string $new_name, WP_Theme $new_theme, $old_theme = null): void {
        self::log(
            'themes',
            'theme_switched',
            sprintf(__('Switched active theme to "%s"', 'phpinfo-wp'), $new_name),
            [
                'new_theme' => $new_name,
                'version'   => $new_theme->get('Version'),
            ],
            'info'
        );
    }

    public static function on_deleted_theme(string $stylesheet, bool $deleted): void {
        if (!$deleted) return;
        $theme_name = ucfirst(str_replace(['-', '_'], ' ', $stylesheet));
        self::log(
            'themes',
            'theme_deleted',
            sprintf(__('Deleted theme "%s"', 'phpinfo-wp'), $theme_name),
            ['stylesheet' => $stylesheet],
            'warning'
        );
    }

    public static function on_upgrader_process_complete($upgrader, array $hook_extra): void {
        $type   = $hook_extra['type'] ?? '';
        $action = $hook_extra['action'] ?? '';

        if ($type === 'plugin') {
            if ($action === 'install') {
                $name = '';
                if (!empty($upgrader->skin->api->name)) {
                    $name = $upgrader->skin->api->name;
                } elseif (!empty($upgrader->result['destination_name'])) {
                    $name = ucfirst(str_replace(['-', '_'], ' ', $upgrader->result['destination_name']));
                } else {
                    $name = __('New Plugin', 'phpinfo-wp');
                }
                self::log('plugins', 'plugin_installed', sprintf(__('Installed plugin "%s"', 'phpinfo-wp'), $name), ['plugin' => $name], 'info');
            } elseif ($action === 'update') {
                $plugins = $hook_extra['plugins'] ?? [];
                foreach ($plugins as $p) {
                    $plugin_data = file_exists(WP_PLUGIN_DIR . '/' . $p) ? get_plugin_data(WP_PLUGIN_DIR . '/' . $p, false, false) : [];
                    $p_name = !empty($plugin_data['Name']) ? $plugin_data['Name'] : $p;
                    self::log('plugins', 'plugin_updated', sprintf(__('Updated plugin "%s"', 'phpinfo-wp'), $p_name), ['plugin' => $p], 'info');
                }
            }
        } elseif ($type === 'theme') {
            if ($action === 'install') {
                $name = '';
                if (!empty($upgrader->skin->api->name)) {
                    $name = $upgrader->skin->api->name;
                } elseif (!empty($upgrader->result['destination_name'])) {
                    $slug  = $upgrader->result['destination_name'];
                    $theme = wp_get_theme($slug);
                    $name  = $theme->exists() ? $theme->get('Name') : ucfirst(str_replace(['-', '_'], ' ', $slug));
                } else {
                    $name = __('New Theme', 'phpinfo-wp');
                }
                self::log('themes', 'theme_installed', sprintf(__('Installed theme "%s"', 'phpinfo-wp'), $name), ['theme' => $name], 'info');
            } elseif ($action === 'update') {
                $themes = $hook_extra['themes'] ?? [];
                foreach ($themes as $t) {
                    $theme_obj = wp_get_theme($t);
                    $t_name = $theme_obj->exists() ? $theme_obj->get('Name') : $t;
                    self::log('themes', 'theme_updated', sprintf(__('Updated theme "%s"', 'phpinfo-wp'), $t_name), ['theme' => $t], 'info');
                }
            }
        } elseif ($type === 'core' && $action === 'update') {
            self::log('core', 'core_updated', __('Updated WordPress Core', 'phpinfo-wp'), ['version' => get_bloginfo('version')], 'info');
        }
    }

    // --- Media & Uploads Listeners ---
    public static function on_add_attachment(int $post_id): void {
        $file = get_attached_file($post_id);
        $filename = $file ? basename($file) : sprintf(__('Media #%d', 'phpinfo-wp'), $post_id);
        $mime = get_post_mime_type($post_id);
        self::log(
            'media',
            'media_uploaded',
            sprintf(__('Uploaded media "%s"', 'phpinfo-wp'), $filename),
            [
                'post_id'   => $post_id,
                'file_name' => $filename,
                'mime_type' => $mime,
            ],
            'info',
            null,
            (string)$post_id
        );
    }

    public static function on_delete_attachment(int $post_id): void {
        $file = get_attached_file($post_id);
        $filename = $file ? basename($file) : sprintf(__('Media #%d', 'phpinfo-wp'), $post_id);
        self::log(
            'media',
            'media_deleted',
            sprintf(__('Deleted media "%s"', 'phpinfo-wp'), $filename),
            ['post_id' => $post_id, 'file_name' => $filename],
            'warning',
            null,
            (string)$post_id
        );
    }

    // --- Menus Listeners ---
    public static function on_create_nav_menu(int $term_id, array $menu_data = []): void {
        $name = !empty($menu_data['menu-name']) ? $menu_data['menu-name'] : "Menu #$term_id";
        self::log('settings', 'menu_created', sprintf(__('Created navigation menu "%s"', 'phpinfo-wp'), $name), ['menu_id' => $term_id], 'info');
    }

    public static function on_update_nav_menu(int $term_id, array $menu_data = []): void {
        $menu_obj = wp_get_nav_menu_object($term_id);
        $name = $menu_obj ? $menu_obj->name : (!empty($menu_data['menu-name']) ? $menu_data['menu-name'] : "Menu #$term_id");
        self::log('settings', 'menu_updated', sprintf(__('Updated navigation menu "%s"', 'phpinfo-wp'), $name), ['menu_id' => $term_id], 'info');
    }

    public static function on_delete_nav_menu(int $term_id): void {
        self::log('settings', 'menu_deleted', sprintf(__('Deleted navigation menu ID #%d', 'phpinfo-wp'), $term_id), ['menu_id' => $term_id], 'warning');
    }

    // --- Taxonomy Listeners ---
    public static function on_create_term(int $term_id, int $tt_id, string $taxonomy): void {
        if ($taxonomy === 'nav_menu') return;
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) return;
        $tax_obj = get_taxonomy($taxonomy);
        $tax_label = $tax_obj ? $tax_obj->labels->singular_name : $taxonomy;
        self::log('posts', 'term_created', sprintf(__('Created %1$s "%2$s"', 'phpinfo-wp'), $tax_label, $term->name), ['term_id' => $term_id, 'taxonomy' => $taxonomy], 'info');
    }

    public static function on_delete_term(int $term_id, int $tt_id, string $taxonomy, $deleted_term, array $object_ids = []): void {
        if ($taxonomy === 'nav_menu') return;
        $name = is_object($deleted_term) && isset($deleted_term->name) ? $deleted_term->name : "ID #$term_id";
        $tax_obj = get_taxonomy($taxonomy);
        $tax_label = $tax_obj ? $tax_obj->labels->singular_name : $taxonomy;
        self::log('posts', 'term_deleted', sprintf(__('Deleted %1$s "%2$s"', 'phpinfo-wp'), $tax_label, $name), ['term_id' => $term_id, 'taxonomy' => $taxonomy], 'warning');
    }

    // --- Comments Listeners ---
    public static function on_insert_comment(int $comment_id, WP_Comment $comment): void {
        $post = get_post($comment->comment_post_ID);
        $post_title = $post ? $post->post_title : "Post #{$comment->comment_post_ID}";
        $author = $comment->comment_author ?: __('Anonymous', 'phpinfo-wp');
        self::log('posts', 'comment_added', sprintf(__('Comment submitted by "%1$s" on "%2$s"', 'phpinfo-wp'), $author, $post_title), ['comment_id' => $comment_id], 'info');
    }

    public static function on_transition_comment_status(string $new_status, string $old_status, $comment): void {
        if ($new_status === $old_status) return;
        $comment_obj = is_numeric($comment) ? get_comment($comment) : $comment;
        if (!$comment_obj) return;
        $post = get_post($comment_obj->comment_post_ID);
        $post_title = $post ? $post->post_title : "Post #{$comment_obj->comment_post_ID}";

        $sev = ($new_status === 'spam' || $new_status === 'trash') ? 'warning' : 'info';
        self::log('posts', 'comment_status_changed', sprintf(__('Comment status changed to %1$s on "%2$s"', 'phpinfo-wp'), ucfirst($new_status), $post_title), ['comment_id' => $comment_obj->comment_ID, 'status' => $new_status], $sev);
    }

    // --- Settings Listeners ---
    public static function on_updated_option(string $option, $old_value, $value): void {
        if (!isset(self::$captured_options[$option])) {
            return;
        }

        if ($old_value === $value) {
            return;
        }

        $label = self::$captured_options[$option];

        self::log(
            'settings',
            'setting_updated',
            sprintf(__('Changed setting "%s"', 'phpinfo-wp'), $label),
            [
                'option'    => $option,
                'old_value' => is_scalar($old_value) ? (string) $old_value : '(array/object)',
                'new_value' => is_scalar($value) ? (string) $value : '(array/object)',
            ],
            'warning'
        );
    }

    // --- Users Listeners ---
    public static function on_user_register(int $user_id): void {
        $u = get_userdata($user_id);
        if (!$u) return;

        self::log(
            'users',
            'user_created',
            sprintf(__('Created new user "%s"', 'phpinfo-wp'), $u->user_login),
            [
                'user_id' => $user_id,
                'roles'   => (array) $u->roles,
                'email'   => $u->user_email,
            ],
            'info'
        );
    }

    public static function on_profile_update(int $user_id, $old_user_data = null): void {
        // Ignore background REST/Gutenberg user meta updates and AJAX heartbeat
        if (defined('REST_REQUEST') && REST_REQUEST) return;
        if (wp_doing_ajax()) return;

        // Only log when triggered from actual profile/user edit screens
        global $pagenow;
        if (!in_array($pagenow, ['profile.php', 'user-edit.php'], true)) {
            return;
        }

        $u = get_userdata($user_id);
        if (!$u) return;

        self::log(
            'users',
            'user_updated',
            sprintf(__('Updated profile for user "%s"', 'phpinfo-wp'), $u->user_login),
            ['user_id' => $user_id],
            'info'
        );
    }

    public static function on_set_user_role(int $user_id, string $new_role, array $old_roles = []): void {
        $u = get_userdata($user_id);
        if (!$u) return;

        $old_role_str = !empty($old_roles) ? implode(', ', $old_roles) : 'none';

        self::log(
            'users',
            'user_role_changed',
            sprintf(__('Changed role of "%s" from %s to %s', 'phpinfo-wp'), $u->user_login, $old_role_str, $new_role),
            [
                'user_id'  => $user_id,
                'old_role' => $old_role_str,
                'new_role' => $new_role,
            ],
            'warning'
        );
    }

    public static function on_delete_user(int $user_id, $reassign = null): void {
        $u = get_userdata($user_id);
        $name = $u ? $u->user_login : "ID #$user_id";

        self::log(
            'users',
            'user_deleted',
            sprintf(__('Deleted user "%s"', 'phpinfo-wp'), $name),
            [
                'user_id'  => $user_id,
                'reassign' => $reassign,
            ],
            'critical'
        );
    }

    // --- Content Listeners ---
    public static function on_transition_post_status(string $new_status, string $old_status, WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;

        // Never log auto-drafts or system status
        if ($new_status === 'auto-draft' || $new_status === 'inherit') return;

        // Ignore internal WordPress CPTs and block editor styles
        $ignored_types = [
            'revision', 'auto-draft', 'nav_menu_item', 'custom_css',
            'customize_changeset', 'oembed_cache', 'user_request',
            'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles',
            'wp_navigation', 'wp_font_family', 'wp_font_face'
        ];
        if (in_array($post->post_type, $ignored_types, true)) return;

        $type_obj   = get_post_type_object($post->post_type);
        $type_label = $type_obj ? $type_obj->labels->singular_name : ucfirst($post->post_type);
        $title      = trim($post->post_title) ? $post->post_title : sprintf(__('(Untitled ID #%d)', 'phpinfo-wp'), $post->ID);

        // Draft created
        if ($old_status === 'auto-draft' && $new_status === 'draft') {
            self::log(
                'posts',
                'post_created_draft',
                sprintf(__('Created draft %1$s "%2$s"', 'phpinfo-wp'), $type_label, $title),
                ['post_id' => $post->ID, 'type' => $post->post_type, 'status' => 'draft'],
                'info',
                null,
                (string)$post->ID
            );
        } elseif ($new_status === 'publish' && $old_status !== 'publish') {
            self::log(
                'posts',
                'post_published',
                sprintf(__('Published %1$s "%2$s"', 'phpinfo-wp'), $type_label, $title),
                ['post_id' => $post->ID, 'type' => $post->post_type],
                'info',
                null,
                (string)$post->ID
            );
        } elseif ($new_status === 'publish' && $old_status === 'publish') {
            self::log(
                'posts',
                'post_updated',
                sprintf(__('Updated %1$s "%2$s"', 'phpinfo-wp'), $type_label, $title),
                ['post_id' => $post->ID, 'type' => $post->post_type],
                'info',
                null,
                (string)$post->ID
            );
        } elseif ($new_status === 'trash') {
            self::log(
                'posts',
                'post_trashed',
                sprintf(__('Trashed %1$s "%2$s"', 'phpinfo-wp'), $type_label, $title),
                ['post_id' => $post->ID, 'type' => $post->post_type],
                'warning',
                null,
                (string)$post->ID
            );
        } elseif ($old_status === 'trash' && $new_status !== 'trash') {
            self::log(
                'posts',
                'post_restored',
                sprintf(__('Restored %1$s "%2$s" from trash', 'phpinfo-wp'), $type_label, $title),
                ['post_id' => $post->ID, 'type' => $post->post_type, 'status' => $new_status],
                'info',
                null,
                (string)$post->ID
            );
        }
    }

    public static function on_before_delete_post(int $post_id): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        $post = get_post($post_id);
        if (!$post) return;

        $type_obj = get_post_type_object($post->post_type);
        $type_label = $type_obj ? $type_obj->labels->singular_name : $post->post_type;
        $title = $post->post_title ?: "(Untitled ID #{$post_id})";

        self::log(
            'posts',
            'post_deleted_permanently',
            sprintf(__('Permanently deleted %1$s "%2$s"', 'phpinfo-wp'), $type_label, $title),
            ['post_id' => $post_id, 'type' => $post->post_type],
            'critical',
            null,
            (string)$post_id
        );
    }

    // --- Querying & Pagination ---
    public static function get_logs(array $args = []): array {
        global $wpdb;
        $table_name = self::table_name();

        $defaults = [
            'category'  => '',
            'severity'  => '',
            'user'      => '',
            'search'    => '',
            'date_from' => '',
            'date_to'   => '',
            'paged'     => 1,
            'per_page'  => 10,
            'orderby'   => 'created_at',
            'order'     => 'DESC',
        ];

        $r = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $params = [];

        if (!empty($r['category']) && $r['category'] !== 'all') {
            if (is_array($r['category'])) {
                $placeholders = implode(', ', array_fill(0, count($r['category']), '%s'));
                $where[] = "category IN ($placeholders)";
                foreach ($r['category'] as $cat) {
                    $params[] = $cat;
                }
            } else {
                $where[]  = 'category = %s';
                $params[] = $r['category'];
            }
        }

        if (!empty($r['severity']) && $r['severity'] !== 'all') {
            $where[]  = 'severity = %s';
            $params[] = $r['severity'];
        }

        if (!empty($r['user'])) {
            $where[]  = 'user_login = %s';
            $params[] = $r['user'];
        }

        if (!empty($r['search'])) {
            $like     = '%' . $wpdb->esc_like($r['search']) . '%';
            $where[]  = '(object_name LIKE %s OR user_login LIKE %s OR ip_address LIKE %s OR action LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($r['date_from'])) {
            $where[]  = 'created_at >= %s';
            $params[] = $r['date_from'] . ' 00:00:00';
        }

        if (!empty($r['date_to'])) {
            $where[]  = 'created_at <= %s';
            $params[] = $r['date_to'] . ' 23:59:59';
        }

        $where_sql = implode(' AND ', $where);

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
        $total = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int) $wpdb->get_var($count_sql);

        // Pagination
        $per_page = max(1, (int) $r['per_page']);
        $paged    = max(1, (int) $r['paged']);
        $offset   = ($paged - 1) * $per_page;

        $orderby = in_array(strtolower($r['orderby']), ['id', 'created_at', 'severity', 'category'], true) ? strtolower($r['orderby']) : 'created_at';
        $order   = strtoupper($r['order']) === 'ASC' ? 'ASC' : 'DESC';

        $query_sql = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$offset}, {$per_page}";
        $items = !empty($params) ? $wpdb->get_results($wpdb->prepare($query_sql, $params), ARRAY_A) : $wpdb->get_results($query_sql, ARRAY_A);

        return [
            'items'       => $items ?: [],
            'total'       => $total,
            'total_pages' => ceil($total / $per_page),
            'paged'       => $paged,
            'per_page'    => $per_page,
        ];
    }

    public static function get_category_counts(): array {
        global $wpdb;
        $table_name = self::table_name();

        $rows = $wpdb->get_results("SELECT category, COUNT(*) as count FROM {$table_name} GROUP BY category", ARRAY_A);
        $counts = ['all' => 0];

        if ($rows) {
            foreach ($rows as $r) {
                $counts[$r['category']] = (int) $r['count'];
                $counts['all'] += (int) $r['count'];
            }
        }

        return $counts;
    }

    // --- Cron Pruning & Retention ---
    public static function register_cron(): void {
        if (!wp_next_scheduled('phpinfowp_activity_log_prune_cron')) {
            wp_schedule_event(time() + 3600, 'daily', 'phpinfowp_activity_log_prune_cron');
        }
        add_action('phpinfowp_activity_log_prune_cron', [__CLASS__, 'prune_logs']);
    }

    public static function prune_logs(int $days = 0): int {
        global $wpdb;
        $table_name = self::table_name();

        if ($days <= 0) {
            $days = (int) get_option(self::RETENTION_OPT, self::DEFAULT_RETENTION_DAYS);
        }
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE created_at < %s", $cutoff));
        return (int) $deleted;
    }

    // --- AJAX Endpoints ---
    public static function ajax_export_csv(): void {
        check_ajax_referer('phpinfowp_activity_log_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized.', 'phpinfo-wp'));
        }

        global $wpdb;
        $table_name = self::table_name();

        $category = sanitize_text_field($_GET['category'] ?? '');
        $severity = sanitize_text_field($_GET['severity'] ?? '');
        $search   = sanitize_text_field($_GET['search'] ?? '');

        $where = ['1=1'];
        $params = [];

        if ($category && $category !== 'all') {
            $where[]  = 'category = %s';
            $params[] = $category;
        }
        if ($severity && $severity !== 'all') {
            $where[]  = 'severity = %s';
            $params[] = $severity;
        }
        if ($search) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = '(object_name LIKE %s OR user_login LIKE %s OR ip_address LIKE %s OR action LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT id, created_at, user_login, user_role, ip_address, category, action, object_name, severity, details FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT 5000";

        $rows = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="activity-log-' . gmdate('Y-m-d-His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Timestamp (UTC)', 'User', 'Role', 'IP Address', 'Category', 'Action', 'Description', 'Severity', 'Details']);

        if ($rows) {
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['id'],
                    $r['created_at'],
                    $r['user_login'],
                    $r['user_role'],
                    $r['ip_address'],
                    $r['category'],
                    $r['action'],
                    $r['object_name'],
                    $r['severity'],
                    $r['details']
                ]);
            }
        }

        fclose($out);
        exit;
    }
}
