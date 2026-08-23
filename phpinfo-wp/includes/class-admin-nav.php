<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Admin navigation — collapses 18+ submenu items into 5 logical groups.
 *
 * The sidebar shows only: Dashboard, Audit, Tools, Reports, License.
 * Within Audit / Tools / Reports, a horizontal tab bar lets users move
 * between related screens. All legacy slugs (?page=piwp-eol etc.)
 * still work for backward compatibility — direct links from the admin
 * bar, dashboard widget, and admin notices keep functioning.
 */
class Phpinfo_WP_Admin_Nav {

    /**
     * @var bool
     */
    private static $tabs_rendered = false;

    /**
     * Group definitions: each group has a label, a slug for its sidebar
     * entry, and an ordered map of tab-slug => tab-info.
     */
    public static function groups(): array {
        return [
            'performance' => [
                'label' => __('Performance', 'piwp'),
                'slug'  => 'piwp-performance',
                'desc'  => __('Analyze and optimize speed, caching, and database bottlenecks', 'piwp'),
                'tabs'  => [
                    'piwp-config-grader'    => ['label' => __('Config Grader', 'piwp'),     'pro' => false, 'icon' => 'dashicons-chart-bar'],
                    'piwp-opcache'          => ['label' => __('OPcache', 'piwp'),           'pro' => true,  'icon' => 'dashicons-performance'],
                    'piwp-object-cache'     => ['label' => __('Object Cache', 'piwp'),      'pro' => true,  'icon' => 'dashicons-database'],
                    'piwp-db-health'        => ['label' => __('Database', 'piwp'),          'pro' => true,  'icon' => 'dashicons-database'],
                    'piwp-api-monitor'      => ['label' => __('API Monitor', 'piwp'),       'pro' => true,  'icon' => 'dashicons-networking'],
                ],
            ],
            'audit' => [
                'label' => __('Security & Core', 'piwp'),
                'slug'  => 'piwp-audit',
                'desc'  => __('Read-only health checks across PHP, config, security, and infrastructure', 'piwp'),
                'tabs'  => [
                    'piwp-eol'              => ['label' => __('PHP EOL', 'piwp'),           'pro' => false, 'icon' => 'dashicons-calendar-alt'],
                    'piwp-compat'           => ['label' => __('PHP Compatibility', 'piwp'), 'pro' => false, 'icon' => 'dashicons-yes-alt'],
                    'piwp-update-audit'     => ['label' => __('Update Guard', 'piwp'),      'pro' => false, 'icon' => 'dashicons-shield'],
                    'piwp-permissions'      => ['label' => __('Permissions Audit', 'piwp'), 'pro' => true,  'icon' => 'dashicons-admin-network'],
                    'piwp-security-headers' => ['label' => __('Security Headers', 'piwp'),  'pro' => true,  'icon' => 'dashicons-shield-alt'],
                    'piwp-ssl'              => ['label' => __('SSL Monitor', 'piwp'),       'pro' => true,  'icon' => 'dashicons-lock'],
                ],
            ],
            'tools' => [
                'label' => __('Page Audit Tools', 'piwp'),
                'slug'  => 'piwp-tools',
                'desc'  => __('Active operations — edit config, troubleshoot, take snapshots, run diagnostics', 'piwp'),
                'tabs'  => [
                    'piwp-viewer'     => ['label' => __('phpinfo() Viewer', 'piwp'),  'pro' => false, 'icon' => 'dashicons-info'],
                    'piwp-htaccess'   => ['label' => __('PHP Config Editor', 'piwp'), 'pro' => false, 'icon' => 'dashicons-editor-code'],
                    'piwp-safemode'   => ['label' => __('Troubleshooting', 'piwp'),   'pro' => false, 'icon' => 'dashicons-sos'],
                    'piwp-info'       => ['label' => __('Basic Info', 'piwp'),        'pro' => false, 'icon' => 'dashicons-clipboard'],
                    'piwp-extensions' => ['label' => __('Extensions', 'piwp'),        'pro' => false, 'icon' => 'dashicons-admin-plugins'],
                    'piwp-snapshots'  => ['label' => __('Config Snapshots', 'piwp'),  'pro' => true,  'icon' => 'dashicons-camera'],
                ],
            ],
            'reports' => [
                'label' => __('Reports & Logs', 'piwp'),
                'slug'  => 'piwp-reports',
                'desc'  => __('Monitoring tools, logs, and outbound alerts', 'piwp'),
                'tabs'  => [
                    'piwp-log'        => ['label' => __('Activity Log', 'piwp'),      'pro' => false, 'icon' => 'dashicons-list-view'],
                    'piwp-report'     => ['label' => __('Audit Report', 'piwp'),      'pro' => true,  'icon' => 'dashicons-media-document'],
                    'piwp-cron'       => ['label' => __('WP-Cron Monitor', 'piwp'),   'pro' => true,  'icon' => 'dashicons-clock'],
                    'piwp-mail'       => ['label' => __('Mail', 'piwp'),              'pro' => true,  'icon' => 'dashicons-email-alt'],
                    'piwp-alerts'     => ['label' => __('Alerts', 'piwp'),            'pro' => true,  'icon' => 'dashicons-bell'],
                    'piwp-error-log'  => ['label' => __('Error Log', 'piwp'),         'pro' => true,  'icon' => 'dashicons-warning'],
                ],
            ],
            'license' => [
                'label' => __('License', 'piwp'),
                'slug'  => 'piwp-license',
                'desc'  => __('Manage your PRO license subscription', 'piwp'),
                'tabs'  => [
                    'piwp-license'          => ['label' => __('License', 'piwp'),           'pro' => false, 'icon' => 'dashicons-admin-network'],
                ],
            ],
            'support' => [
                'label' => __('Support', 'piwp'),
                'slug'  => 'piwp-support',
                'desc'  => __('Get help from our engineers', 'piwp'),
                'tabs'  => [
                    'piwp-support'          => ['label' => __('Support', 'piwp'),           'pro' => false, 'icon' => 'dashicons-sos'],
                ],
            ],
        ];
    }

    /**
     * Look up which group/tab a slug belongs to. Returns
     * ['group' => key, 'group_info' => array, 'tab' => slug, 'tab_info' => array]
     * or null if the slug isn't in any group.
     */
    public static function find(string $slug): ?array {
        foreach (self::groups() as $key => $group) {
            if (isset($group['tabs'][$slug])) {
                return [
                    'group'      => $key,
                    'group_info' => $group,
                    'tab'        => $slug,
                    'tab_info'   => $group['tabs'][$slug],
                ];
            }
        }
        return null;
    }

    /**
     * The first tab in a group — used as the default when the user lands
     * on the group page without a specific tab.
     */
    public static function first_tab(string $group_key): ?string {
        $g = self::groups()[$group_key] ?? null;
        if (!$g) return null;
        return array_key_first($g['tabs']);
    }

    /**
     * Render the vertical secondary sidebar for the current screen. Pass the
     * current page slug; if omitted we read it from $_GET['page']. Silently
     * no-ops if the slug isn't part of any group.
     *
     * Layout mirrors Elementor's in-page secondary nav: a fixed-width column
     * on the left of #wpbody-content with grouped vertical links. CSS pushes
     * the page's .wrap to the right via a body class added in admin_body_class.
     */
    public static function render_tabs(?string $current_slug = null): void {
        // Idempotent — render_group() calls this, then dispatches to a view
        // file that may also call it. Render only once per request.
        if (self::$tabs_rendered) return;

        $current_slug = $current_slug ?? self::current_tab_slug() ?? sanitize_key($_GET['page'] ?? '');
        $info = self::find($current_slug);
        if (!$info || count($info['group_info']['tabs']) <= 1) return;

        self::$tabs_rendered = true;

        $is_pro = Phpinfo_WP_License::is_valid();
        $group  = $info['group_info'];

        echo '<aside class="phpinfowp-side-nav" role="navigation" aria-label="' . esc_attr($group['label']) . '">';
        echo '<ul class="phpinfowp-side-nav-list">';
        foreach ($group['tabs'] as $slug => $tab) {
            $is_active = $slug === $current_slug;
            $url       = admin_url('admin.php?page=' . $slug);
            $classes   = 'phpinfowp-side-nav-item' . ($is_active ? ' is-active' : '');
            $icon      = !empty($tab['icon']) ? $tab['icon'] : 'dashicons-admin-generic';
            $badge     = (!$is_pro && !empty($tab['pro']))
                ? '<span class="phpinfowp-side-nav-pro">PRO</span>'
                : '';
            echo '<li><a href="' . esc_url($url) . '" class="' . esc_attr($classes) . '">';
            echo '<span class="dashicons ' . esc_attr($icon) . ' phpinfowp-side-nav-icon" aria-hidden="true"></span>';
            echo '<span class="phpinfowp-side-nav-label-text">' . esc_html($tab['label']) . '</span>' . $badge;
            echo '</a></li>';
        }
        echo '</ul>';
        echo '</aside>';
    }

    public static $current_dispatched_tab = '';

    /**
     * Render the group landing page. The tab bar itself is injected by the
     * admin_notices hook (see auto_render below) so it appears above every
     * grouped page — not just the group landing. Here we just dispatch to
     * the active tab's view file.
     */
    public static function render_group(string $group_key): void {
        $groups = self::groups();
        if (!isset($groups[$group_key])) wp_die(__('Unknown group.', 'piwp'));

        $tab = self::resolve_group_tab($group_key);

        // Store the active tab securely without mutating the global $_GET array
        self::$current_dispatched_tab = $tab;
        self::dispatch_view($tab);
        self::$current_dispatched_tab = '';
    }

    /**
     * Resolve which tab is active when on a group landing page. Reads ?tab=
     * if present and valid; otherwise returns the first tab.
     */
    private static function resolve_group_tab(string $group_key): string {
        $groups = self::groups();
        $tabs = $groups[$group_key]['tabs'] ?? [];
        if (empty($tabs)) return $group_key;

        $req_tab = sanitize_key($_GET['tab'] ?? '');
        return isset($tabs[$req_tab]) ? $req_tab : array_key_first($tabs);
    }

    /**
     * Securely checks if the currently dispatched page matches the target slug.
     * Use this instead of checking $_GET['page'] directly.
     */
    public static function is_page(string $slug): bool {
        if (self::$current_dispatched_tab !== '') {
            return self::$current_dispatched_tab === $slug;
        }
        return sanitize_key($_GET['page'] ?? '') === $slug;
    }

    /**
     * Securely checks if the current page belongs to this plugin (starts with piwp).
     */
    public static function is_plugin_page(): bool {
        $page = self::$current_dispatched_tab !== '' ? self::$current_dispatched_tab : sanitize_key($_GET['page'] ?? '');
        return $page === PHPINFOWP_SLUG_PREFIX || strncmp($page, PHPINFOWP_SLUG_PREFIX . '-', strlen(PHPINFOWP_SLUG_PREFIX . '-')) === 0;
    }

    /**
     * Map group-landing slugs back to their group key. Used by auto_render
     * to know "if user is on phpinfowp-performance or phpinfowp-audit, what group is that?"
     */
    public static function group_for_slug(string $slug): ?string {
        $m = [
            'piwp-performance' => 'performance',
            'piwp-audit'       => 'audit',
            'piwp-tools'       => 'tools',
            'piwp-reports'     => 'reports',
            'piwp-license'     => 'license',
            'piwp-support'     => 'support',
        ];
        return $m[$slug] ?? null;
    }

    /**
     * Hook target — emits the tab bar at the very top of every grouped page,
     * including direct legacy slugs and the new group landing slugs.
     */
    public static function auto_render(): void {
        if (!self::is_group_page()) return;

        // admin_notices fires above the page's .wrap. The sidebar is
        // position:absolute via CSS, so it lifts out of normal flow and the
        // body-class added in admin_body_class pushes .wrap right to make room.
        self::render_tabs(self::current_tab_slug());
    }

    /**
     * True when the current admin page belongs to a grouped phpinfo screen
     * (so it needs the secondary sidebar layout). Used by admin_body_class.
     */
    public static function is_group_page(): bool {
        $tab = self::current_tab_slug();
        if (!$tab) return false;
        
        $info = self::find($tab);
        return $info && count($info['group_info']['tabs']) > 1;
    }

    /**
     * Resolve the active tab slug for the current request, or null if the
     * current page isn't part of any group.
     */
    private static function current_tab_slug(): ?string {
        $page = sanitize_key($_GET['page'] ?? '');
        if (!$page || ($page !== PHPINFOWP_SLUG_PREFIX && strncmp($page, PHPINFOWP_SLUG_PREFIX . '-', strlen(PHPINFOWP_SLUG_PREFIX . '-')) !== 0)) return null;
        if (self::find($page)) return $page;
        $group_key = self::group_for_slug($page);
        return $group_key ? self::resolve_group_tab($group_key) : null;
    }

    /**
     * Slug → view file mapping. Mirrors the require statements that the
     * Phpinfo_wp view_* methods used to do, keeping a single source of truth.
     */
    private static function dispatch_view(string $slug): void {
        $map = [
            'piwp-config-grader'    => 'views/pro/config-grader.php',
            'piwp-eol'              => 'views/pro/eol.php',
            'piwp-compat'           => 'views/pro/compat.php',
            'piwp-security-headers' => 'views/pro/security-headers.php',
            'piwp-ssl'              => 'views/pro/ssl.php',
            'piwp-opcache'          => 'views/pro/opcache.php',
            'piwp-object-cache'     => 'views/pro/object-cache.php',
            'piwp-db-health'        => 'views/pro/db-health.php',
            'piwp-api-monitor'      => 'views/pro/api-monitor.php',
            'piwp-permissions'      => 'views/pro/permissions.php',
            'piwp-htaccess'         => 'views/htaccess.php',
            'piwp-safemode'         => 'views/safemode.php',
            'piwp-viewer'           => 'views/phpinfo.php',
            'piwp-extensions'       => 'views/extension.php',
            'piwp-info'             => 'views/info.php',
            'piwp-snapshots'        => 'views/pro/snapshots.php',
            'piwp-cron'             => 'views/pro/cron.php',
            'piwp-mail'             => 'views/pro/mail.php',
            'piwp-error-log'        => 'views/pro/error-log.php',
            'piwp-log'              => 'views/log.php',
            'piwp-report'           => 'views/pro/report.php',
            'piwp-alerts'           => 'views/pro/alerts.php',
        ];
        $file = $map[$slug] ?? null;
        if (!$file) { echo '<p>' . __('Unknown view.', 'piwp') . '</p>'; return; }
        require PHPINFOWP_DIR . $file;
    }
}
