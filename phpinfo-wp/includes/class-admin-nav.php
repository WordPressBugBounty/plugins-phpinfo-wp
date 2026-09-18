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
                'label' => __('Performance', 'phpinfo-wp'),
                'slug'  => 'piwp-performance',
                'desc'  => __('Analyze and optimize speed, caching, and database bottlenecks', 'phpinfo-wp'),
                'tabs'  => [
                    'piwp-config-grader'    => ['label' => __('Config Grader', 'phpinfo-wp'),     'pro' => false, 'icon' => 'dashicons-chart-bar'],
                    'piwp-opcache'          => ['label' => __('OPcache', 'phpinfo-wp'),           'pro' => true,  'icon' => 'dashicons-performance'],
                    'piwp-object-cache'     => ['label' => __('Object Cache', 'phpinfo-wp'),      'pro' => true,  'icon' => 'dashicons-database'],
                    'piwp-db-health'        => ['label' => __('Database', 'phpinfo-wp'),          'pro' => true,  'icon' => 'dashicons-database'],
                    'piwp-api-monitor'      => ['label' => __('API Monitor', 'phpinfo-wp'),       'pro' => true,  'icon' => 'dashicons-networking'],
                ],
            ],
            'audit' => [
                'label' => __('Security & Core', 'phpinfo-wp'),
                'slug'  => 'piwp-audit',
                'desc'  => __('Read-only health checks across PHP, config, security, and infrastructure', 'phpinfo-wp'),
                'tabs'  => [
                    'piwp-eol'              => ['label' => __('PHP EOL', 'phpinfo-wp'),           'pro' => false, 'icon' => 'dashicons-calendar-alt'],
                    'piwp-compat'           => ['label' => __('PHP Compatibility', 'phpinfo-wp'), 'pro' => false, 'icon' => 'dashicons-yes-alt'],
                    'piwp-update-audit'     => ['label' => __('Update Guard', 'phpinfo-wp'),      'pro' => false, 'icon' => 'dashicons-shield'],
                    'piwp-permissions'      => ['label' => __('Permissions Audit', 'phpinfo-wp'), 'pro' => true,  'icon' => 'dashicons-admin-network'],
                    'piwp-security-headers' => ['label' => __('Security Headers', 'phpinfo-wp'),  'pro' => true,  'icon' => 'dashicons-shield-alt'],
                    'piwp-ssl'              => ['label' => __('SSL Monitor', 'phpinfo-wp'),       'pro' => true,  'icon' => 'dashicons-lock'],
                ],
            ],
            'tools' => [
                'label' => __('Page Audit Tools', 'phpinfo-wp'),
                'slug'  => 'piwp-tools',
                'desc'  => __('Active operations — edit config, troubleshoot, take snapshots, run diagnostics', 'phpinfo-wp'),
                'tabs'  => [
                    'piwp-viewer'     => ['label' => __('phpinfo() Viewer', 'phpinfo-wp'),  'pro' => false, 'icon' => 'dashicons-info'],
                    'piwp-htaccess'   => ['label' => __('PHP Config Editor', 'phpinfo-wp'), 'pro' => false, 'icon' => 'dashicons-editor-code'],
                    'piwp-safemode'   => ['label' => __('Troubleshooting', 'phpinfo-wp'),   'pro' => false, 'icon' => 'dashicons-sos'],
                    'piwp-info'       => ['label' => __('Basic Info', 'phpinfo-wp'),        'pro' => false, 'icon' => 'dashicons-clipboard'],
                    'piwp-extensions' => ['label' => __('Extensions', 'phpinfo-wp'),        'pro' => false, 'icon' => 'dashicons-admin-plugins'],
                    'piwp-snapshots'  => ['label' => __('Config Snapshots', 'phpinfo-wp'),  'pro' => true,  'icon' => 'dashicons-camera'],
                ],
            ],
            'reports' => [
                'label' => __('Reports & Logs', 'phpinfo-wp'),
                'slug'  => 'piwp-reports',
                'desc'  => __('Monitoring tools, logs, and outbound alerts', 'phpinfo-wp'),
                'tabs'  => [
                    'piwp-log'        => ['label' => __('Operations Log', 'phpinfo-wp'),    'pro' => false, 'icon' => 'dashicons-list-view'],
                    'piwp-admin-log'  => ['label' => __('Admin Log', 'phpinfo-wp'),         'pro' => true,  'icon' => 'dashicons-shield'],
                    'piwp-report'     => ['label' => __('Audit Report', 'phpinfo-wp'),      'pro' => true,  'icon' => 'dashicons-media-document'],
                    'piwp-cron'       => ['label' => __('WP-Cron Monitor', 'phpinfo-wp'),   'pro' => true,  'icon' => 'dashicons-clock'],
                    'piwp-mail'       => ['label' => __('Mail', 'phpinfo-wp'),              'pro' => true,  'icon' => 'dashicons-email-alt'],
                    'piwp-alerts'     => ['label' => __('Alerts', 'phpinfo-wp'),            'pro' => true,  'icon' => 'dashicons-bell'],
                    'piwp-error-log'  => ['label' => __('Error Log', 'phpinfo-wp'),         'pro' => true,  'icon' => 'dashicons-warning'],
                ],
            ],
            'license' => [
                'label' => __('License', 'phpinfo-wp'),
                'slug'  => 'piwp-license',
                'desc'  => __('Manage your PRO license subscription', 'phpinfo-wp'),
                'tabs'  => [
                    'piwp-license'          => ['label' => __('License', 'phpinfo-wp'),           'pro' => false, 'icon' => 'dashicons-admin-network'],
                ],
            ],
            'support' => [
                'label' => __('Support', 'phpinfo-wp'),
                'slug'  => 'piwp-support',
                'desc'  => __('Get help from our engineers', 'phpinfo-wp'),
                'tabs'  => [
                    'piwp-support'          => ['label' => __('Support', 'phpinfo-wp'),           'pro' => false, 'icon' => 'dashicons-sos'],
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

        echo '<div class="phpinfowp-sidebar-container">';
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

        if ($current_slug === 'piwp-config-grader' && class_exists('Phpinfo_WP_Config_Grader')) {
            $summary = Phpinfo_WP_Config_Grader::summary();
            $grade   = $summary['grade'];
            $score   = $summary['score'];
            $fails   = (int) $summary['fails'];
            $warns   = (int) $summary['warns'];
            $passes  = (int) $summary['passes'];
            $total   = (int) $summary['total'];
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('Config Grader', 'phpinfo-wp'); ?>
                        </div>
                        <?php
                        $score_controllable = $summary['score_controllable'] ?? $score;
                        $grade_controllable = $summary['grade_controllable'] ?? $grade;
                        $is_capped          = $summary['is_capped'] ?? false;
                        $grade_effective    = $summary['grade_effective'] ?? $grade_controllable;
                        $score_effective    = $summary['score_effective'] ?? $score_controllable;
                        $show_dual          = ($score !== $score_controllable);
                        $locked_count       = $summary['locked_count'] ?? max(0, (int)($summary['total'] ?? 0) - (int)($summary['passes'] ?? 0) - (int)($summary['fails'] ?? 0) - (int)($summary['warns'] ?? 0));

                        if ($is_capped) {
                            $badge_color = ($score_effective >= 85) ? '#15803d' : (($score_effective >= 75) ? '#b45309' : '#b91c1c');
                            $badge_label = sprintf(__('GRADE %s*', 'phpinfo-wp'), esc_html($grade_effective));
                            $badge_title = sprintf(__('Overall Grade %1$s* (%2$d/100): Balanced 50/50 average of Site Controllable (%3$d/100) and Server Reality (%4$d/100) with %5$d server-locked setting(s).', 'phpinfo-wp'), $grade_effective, $score_effective, $score_controllable, $score, $locked_count);
                        } else {
                            $badge_color = ($score_effective >= 85) ? '#15803d' : (($score_effective >= 75) ? '#b45309' : '#b91c1c');
                            $badge_label = sprintf(__('GRADE %s', 'phpinfo-wp'), esc_html($grade_effective));
                            $badge_title = $show_dual
                                ? sprintf(__('Site: %1$s (%2$d/100). Server: %3$s (%4$d/100).', 'phpinfo-wp'), $grade_controllable, $score_controllable, $grade, $score)
                                : sprintf(__('Grade %1$s (%2$d/100)', 'phpinfo-wp'), $grade_effective, $score_effective);
                        }
                        ?>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo $badge_color; ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; letter-spacing:0.4px; white-space:nowrap; flex-shrink:0;" title="<?php echo esc_attr($badge_title); ?>">
                            <?php echo esc_html($badge_label); ?>
                        </span>
                    </div>
                    <?php if ($show_dual): ?>
                    <div style="font-size:11px; margin-top:8px; padding:6px 9px; background:#fffbeb; border:1px solid #fef3c7; border-radius:6px; color:#92400e; line-height:1.4;">
                        <?php if ($is_capped): ?>
                            <?php if ($is_pro): ?>
                                <strong><?php printf(__('Overall %s (%d/100)', 'phpinfo-wp'), esc_html($grade_effective), (int)$score_effective); ?></strong>: <?php printf(__('Blended average of Site (%s) and Server (%s) &middot; %d setting(s) affecting grade.', 'phpinfo-wp'), esc_html($grade_controllable), esc_html($grade), $locked_count); ?>
                            <?php else: ?>
                                <strong><?php printf(__('Overall (%d/100)', 'phpinfo-wp'), (int)$score_effective); ?></strong>: <?php printf(__('Blended average of Site and Server &middot; %d setting(s) affecting grade.', 'phpinfo-wp'), $locked_count); ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($is_pro): ?>
                                <?php printf(__('Server reality: %s (%d/100) &middot; %d setting(s) affecting grade', 'phpinfo-wp'), esc_html($grade), (int)$score, $locked_count); ?>
                            <?php else: ?>
                                <?php printf(__('Server reality: (%d/100) &middot; %d setting(s) affecting grade', 'phpinfo-wp'), (int)$score, $locked_count); ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php _e('PHP Runtime Configuration Audit', 'phpinfo-wp'); ?>
                    </div>
                </div>
                <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Site Controllable:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                        <span style="color:#15803d; font-weight:800; font-size:13px;"><?php echo esc_html($grade_controllable); ?> (<?php echo (int) $score_controllable; ?>/100)</span>
                        <?php else: ?>
                        <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($show_dual):
                        $server_color = ($score >= 90) ? '#15803d' : (($score >= 80) ? '#dba617' : (($score >= 70) ? '#ea580c' : '#dc2626'));
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Server Reality:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                        <span style="color:<?php echo $server_color; ?>; font-weight:800; font-size:13px;"><?php echo esc_html($grade); ?> (<?php echo (int) $score; ?>/100)</span>
                        <?php else: ?>
                        <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($fails > 0): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#b91c1c; font-weight:600;"><?php _e('Failing:', 'phpinfo-wp'); ?></span>
                            <span style="color:#b91c1c; font-weight:800; font-size:14px;"><?php echo $fails; ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($warns > 0): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#b45309; font-weight:600;"><?php _e('Warnings:', 'phpinfo-wp'); ?></span>
                            <span style="color:#b45309; font-weight:800; font-size:14px;"><?php echo $warns; ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#15803d; font-weight:600;"><?php _e('Passing:', 'phpinfo-wp'); ?></span>
                        <span style="color:#15803d; font-weight:800; font-size:14px;"><?php echo $passes; ?></span>
                    </div>
                    <div style="font-size:12.5px; margin-top:4px; border-top:1px dashed #cbd5e1; padding-top:6px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Total Checks:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700;"><?php echo $total; ?></span>
                    </div>
                </div>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-db-health' && class_exists('Phpinfo_WP_DB_Health')) {
            $db_cache   = Phpinfo_WP_DB_Health::get_result();
            $server     = $db_cache['server'] ?? Phpinfo_WP_DB_Health::server_info();
            $db_size    = $db_cache['db_size'] ?? ['total' => 0, 'tables' => 0, 'free' => 0];
            $autoload   = $db_cache['autoload'] ?? ['bytes' => 0, 'status' => 'ok'];
            $transients = $db_cache['transients'] ?? ['total' => 0, 'expired' => 0];

            $status_color = ['ok' => '#00a32a', 'warning' => '#dba617', 'fail' => '#d63638', 'eol' => '#d63638', 'unknown' => '#888'];
            $status_label = ['ok' => 'HEALTHY', 'warning' => 'WARNING', 'fail' => 'CRITICAL', 'eol' => 'END OF LIFE', 'unknown' => 'UNKNOWN'];

            $srv_color = $status_color[$server['status'] ?? 'unknown'] ?? '#888';
            $srv_label = $status_label[$server['status'] ?? 'unknown'] ?? 'UNKNOWN';

            $a_status = $autoload['status'] ?? 'ok';
            $a_color  = $status_color[$a_status] ?? '#00a32a';
            $a_label  = $status_label[$a_status] ?? 'HEALTHY';
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php echo esc_html($server['engine'] ?? 'Database'); ?> <?php echo esc_html($server['version'] ?? ''); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo $is_pro ? esc_attr($srv_color) : '#64748b'; ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo $is_pro ? esc_html($srv_label) : __('PRO', 'phpinfo-wp'); ?>
                        </span>
                    </div>
                    <?php if (!empty($server['eol'])): ?>
                        <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                            EOL: <strong><?php echo esc_html($server['eol']); ?></strong>
                            <?php if ($server['days'] !== null): ?>
                                &middot; <?php echo (int) $server['days']; ?>d left
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Database Size:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:800; font-size:13.5px;">
                            <?php echo size_format($db_size['total'] ?? 0); ?>
                            <span style="font-size:11px; color:#64748b; font-weight:500;">(<?php echo (int)($db_size['tables'] ?? 0); ?>)</span>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Autoload Data:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:<?php echo esc_attr($a_color); ?>; font-weight:800; font-size:13.5px;">
                                <?php echo size_format($autoload['bytes'] ?? 0); ?>
                                <span style="font-size:9.5px; background:<?php echo esc_attr($a_color); ?>; color:#fff; padding:1px 4px; border-radius:3px; margin-left:2px; font-weight:700;"><?php echo esc_html($a_label); ?></span>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Transients:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:#0f172a; font-weight:800; font-size:13.5px;">
                                <?php echo (int)($transients['total'] ?? 0); ?>
                                <?php if (!empty($transients['expired'])): ?>
                                    <span style="font-size:11px; color:#b91c1c; font-weight:700;">(<?php echo (int)$transients['expired']; ?> exp)</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="font-size:12.5px; margin-top:4px; border-top:1px dashed #cbd5e1; padding-top:6px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Overhead:', 'phpinfo-wp'); ?></span>
                        <span style="color:<?php echo ($db_size['free'] ?? 0) > 1048576 ? '#b45309' : '#0f172a'; ?>; font-weight:700; font-size:12px;">
                            <?php echo size_format($db_size['free'] ?? 0); ?>
                        </span>
                    </div>
                </div>

                <?php if ($is_pro && !empty($transients['expired'])): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field('phpinfowp_db_nonce'); ?>
                            <button type="submit" name="phpinfowp_purge_transients" value="1" class="button button-secondary" style="width:100%; display:flex; align-items:center; justify-content:center; gap:6px; font-size:12px; font-weight:600; padding:5px 10px; height:auto; color:#b91c1c; border-color:#fca5a5; background:#fef2f2;">
                                <span class="dashicons dashicons-trash" style="font-size:15px; width:15px; height:15px; line-height:15px;"></span>
                                <?php printf(__('Purge %d expired transients', 'phpinfo-wp'), (int)$transients['expired']); ?>
                            </button>
                        </form>
                    </div>
                <?php elseif (!$is_pro): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; height:32px; line-height:30px; background:#777BB3; border-color:#777BB3; text-decoration:none;">
                            <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <?php _e('Unlock DB Optimizer &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-api-monitor' && class_exists('Phpinfo_WP_API_Monitor')) {
            $stats = Phpinfo_WP_API_Monitor::get_stats();
            $total_time = 0;
            $total_reqs = 0;
            $slowest_domain = null;
            $slowest_max = 0;
            $total_errors = 0;

            foreach ($stats as $host => $data) {
                $total_time += $data['total_time'];
                $total_reqs += $data['count'];
                $total_errors += ($data['errors'] ?? 0);
                if ($data['max_time'] > $slowest_max) {
                    $slowest_max = $data['max_time'];
                    $slowest_domain = $host;
                }
            }

            $max_color = $slowest_max > 2.0 ? '#b91c1c' : ($slowest_max > 1.0 ? '#b45309' : '#15803d');
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('API Monitor', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo ($is_pro && $total_reqs > 0) ? '#15803d' : '#64748b'; ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo $is_pro ? ($total_reqs > 0 ? __('ACTIVE', 'phpinfo-wp') : __('IDLE', 'phpinfo-wp')) : __('PRO', 'phpinfo-wp'); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php _e('Outbound HTTP Tracking (24h)', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Waiting Time:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:800; font-size:13.5px;">
                            <?php echo number_format($total_time, 2); ?>s
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Total Calls:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:800; font-size:13.5px;">
                            <?php echo number_format($total_reqs); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Slowest API:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:<?php echo esc_attr($max_color); ?>; font-weight:800; font-size:13.5px;">
                                <?php echo $slowest_max > 0 ? number_format($slowest_max, 2) . 's' : '0.00s'; ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_pro && $slowest_domain): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:11.5px; color:#64748b;">
                            <span style="color:#64748b; font-weight:500;"><?php _e('Slowest Host:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:600; max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($slowest_domain); ?>">
                                <?php echo esc_html($slowest_domain); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_pro && $total_errors > 0): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#b91c1c; font-weight:600;"><?php _e('Errors/Timeouts:', 'phpinfo-wp'); ?></span>
                            <span style="color:#b91c1c; font-weight:800; font-size:13.5px;">
                                <?php echo number_format($total_errors); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($is_pro): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:6px;">
                        <button id="phpinfowp-test-api" class="button button-secondary" style="width:100%; font-size:12px; font-weight:600; padding:4px 8px; height:auto; text-align:center;">
                            <?php _e('Test Slow API (1.5s)', 'phpinfo-wp'); ?>
                        </button>
                        <?php if ($stats): ?>
                            <button id="phpinfowp-clear-api-stats" class="button" style="width:100%; font-size:11.5px; font-weight:600; padding:3px 8px; height:auto; color:#b91c1c; border-color:#fca5a5; background:#fef2f2; text-align:center;">
                                <?php _e('Reset Stats', 'phpinfo-wp'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; height:32px; line-height:30px; background:#777BB3; border-color:#777BB3; text-decoration:none;">
                            <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <?php _e('Unlock API Monitor &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-eol' && class_exists('Phpinfo_WP_EOL')) {
            $current = Phpinfo_WP_EOL::status();
            $status_color = ['ok' => '#00a32a', 'warning' => '#dba617', 'eol' => '#d63638', 'unknown' => '#888'];
            $status_label = ['ok' => __('SUPPORTED', 'phpinfo-wp'), 'warning' => __('EXPIRING SOON', 'phpinfo-wp'), 'eol' => __('END OF LIFE', 'phpinfo-wp'), 'unknown' => __('UNKNOWN', 'phpinfo-wp')];

            $badge_color = $status_color[$current['status'] ?? 'unknown'] ?? '#888';
            $badge_label = $status_label[$current['status'] ?? 'unknown'] ?? __('UNKNOWN', 'phpinfo-wp');
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php printf(__('PHP %s', 'phpinfo-wp'), esc_html($current['minor'] ?? '')); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo esc_attr($badge_color); ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo esc_html($badge_label); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php printf(__('Running on PHP %s', 'phpinfo-wp'), esc_html(PHP_VERSION)); ?>
                    </div>
                </div>

                <div style="font-size:12.5px; line-height:1.6; border-top:1px solid #e2e8f0; padding-top:10px; color:#334155;">
                    <?php if (($current['status'] ?? '') === 'eol'): ?>
                        <p style="margin:0; color:#b91c1c; font-weight:600;">
                            <?php printf(__('Reached end-of-life on %s. No security patches are being issued.', 'phpinfo-wp'), '<strong>' . esc_html($current['eol'] ?? '') . '</strong>'); ?>
                        </p>
                    <?php elseif (($current['status'] ?? '') === 'warning'): ?>
                        <p style="margin:0; color:#b45309; font-weight:600;">
                            <?php printf(__('Reaches EOL on %1$s (%2$s days left). Plan upgrade soon.', 'phpinfo-wp'), '<strong>' . esc_html($current['eol'] ?? '') . '</strong>', '<strong>' . (int)($current['days'] ?? 0) . '</strong>'); ?>
                        </p>
                    <?php elseif (($current['status'] ?? '') === 'ok'): ?>
                        <p style="margin:0; color:#15803d; font-weight:600;">
                            <?php printf(__('Actively supported until %1$s (%2$s days left).', 'phpinfo-wp'), '<strong>' . esc_html($current['eol'] ?? '') . '</strong>', '<strong>' . (int)($current['days'] ?? 0) . '</strong>'); ?>
                        </p>
                    <?php else: ?>
                        <p style="margin:0; color:#64748b;">
                            <?php printf(__('EOL date not found for PHP %s.', 'phpinfo-wp'), esc_html($current['minor'] ?? '')); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-compat' && class_exists('Phpinfo_WP_Compat')) {
            $result = Phpinfo_WP_Compat::get_result();
            if ($result && !isset($result['error'])) {
                $verdict     = Phpinfo_WP_Compat::verdict($result);
                $target_ver  = esc_html($result['target'] ?? '8.4');
                ?>
                <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                                <?php _e('PHP Compatibility', 'phpinfo-wp'); ?>
                            </div>
                            <span class="phpinfowp-dbh-badge" style="background:<?php echo !$is_pro ? '#64748b' : $verdict['badge_color']; ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;" title="<?php echo esc_attr($verdict['title']); ?>">
                                <?php echo !$is_pro ? __('PRO', 'phpinfo-wp') : esc_html($verdict['badge_text']); ?>
                            </span>
                        </div>
                        <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                            <?php printf(__('Target: PHP %s Scanner', 'phpinfo-wp'), $target_ver); ?>
                        </div>
                    </div>

                    <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('Affected Plugins:', 'phpinfo-wp'); ?></span>
                            <span style="color:<?php echo $result['with_issues'] > 0 ? $verdict['plugin_color'] : '#15803d'; ?>; font-weight:800; font-size:13.5px;">
                                <?php echo (int) $result['with_issues']; ?> / <?php echo (int) $result['owners']; ?>
                            </span>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('Files Scanned:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:800; font-size:13.5px;">
                                <?php echo number_format($result['files']); ?>
                            </span>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('Scan Duration:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:700; font-size:13.5px;">
                                <?php echo esc_html($result['duration']); ?>s
                            </span>
                        </div>

                        <?php if (!empty($result['scanned_at'])): ?>
                            <div style="font-size:11.5px; margin-top:4px; border-top:1px dashed #cbd5e1; padding-top:6px; color:#64748b; text-align:center;">
                                <?php printf(__('Scanned %s', 'phpinfo-wp'), esc_html(human_time_diff($result['scanned_at']) . ' ago')); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
        } elseif ($current_slug === 'piwp-update-audit') {
            $subtab = sanitize_key($_GET['tab'] ?? '');
            $pending_count = class_exists('Phpinfo_WP_Update_Guard') ? Phpinfo_WP_Update_Guard::pending_count() : 0;
            if (!$subtab) {
                $subtab = ($pending_count > 0) ? 'plugins' : 'core';
            }

            $verdict_meta = [
                'safe'        => ['label' => __('Safe to update', 'phpinfo-wp'),       'badge' => __('SAFE', 'phpinfo-wp'),        'grade' => 'grade-a', 'icon' => '✓', 'color' => '#15803d'],
                'likely_safe' => ['label' => __('Likely safe to update', 'phpinfo-wp'),'badge' => __('LIKELY SAFE', 'phpinfo-wp'), 'grade' => 'grade-a', 'icon' => '✓', 'color' => '#15803d'],
                'caution'     => ['label' => __('Update with caution', 'phpinfo-wp'),   'badge' => __('CAUTION', 'phpinfo-wp'),     'grade' => 'grade-c', 'icon' => '!', 'color' => '#b45309'],
                'risky'       => ['label' => __('Risky — review first', 'phpinfo-wp'), 'badge' => __('RISKY', 'phpinfo-wp'),       'grade' => 'grade-f', 'icon' => '✕', 'color' => '#b91c1c'],
            ];

            $inplace_verdict_meta = [
                'clean'        => ['label' => __('Running cleanly', 'phpinfo-wp'),       'badge' => __('HEALTHY', 'phpinfo-wp'),     'grade' => 'grade-a', 'icon' => '✓', 'color' => '#15803d'],
                'safe'         => ['label' => __('Running cleanly', 'phpinfo-wp'),       'badge' => __('HEALTHY', 'phpinfo-wp'),     'grade' => 'grade-a', 'icon' => '✓', 'color' => '#15803d'],
                'latent_debt'  => ['label' => __('Technical debt noted', 'phpinfo-wp'),  'badge' => __('TECH DEBT', 'phpinfo-wp'),   'grade' => 'grade-c', 'icon' => '!', 'color' => '#b45309'],
                'caution'      => ['label' => __('Technical debt noted', 'phpinfo-wp'),  'badge' => __('TECH DEBT', 'phpinfo-wp'),   'grade' => 'grade-c', 'icon' => '!', 'color' => '#b45309'],
                'active_issue' => ['label' => __('Active issue detected', 'phpinfo-wp'), 'badge' => __('ISSUES', 'phpinfo-wp'),      'grade' => 'grade-f', 'icon' => '✕', 'color' => '#b91c1c'],
                'risky'        => ['label' => __('Active issue detected', 'phpinfo-wp'), 'badge' => __('ISSUES', 'phpinfo-wp'),      'grade' => 'grade-f', 'icon' => '✕', 'color' => '#b91c1c'],
            ];

            if ($subtab === 'core' && class_exists('Phpinfo_WP_Update_Audit')) {
                $core_result = Phpinfo_WP_Update_Audit::get_result();
                if ($core_result && !isset($core_result['error'])) {
                    $current_wp = Phpinfo_WP_Update_Audit::current_wp();
                    $target_wp  = $core_result['target'] ?? $current_wp;
                    $mode       = $core_result['mode'] ?? (version_compare($target_wp, $current_wp, '>') ? 'forward_dry_run' : 'in_place_audit');
                    $cv         = $core_result['verdict'] ?? 'safe';

                    if ($mode === 'in_place_audit') {
                        $cvm          = $inplace_verdict_meta[$cv] ?? $inplace_verdict_meta['clean'];
                        $card_title   = __('Site Health', 'phpinfo-wp');
                        $card_sub     = sprintf(__('In-Place (WordPress %s)', 'phpinfo-wp'), esc_html($target_wp));
                    } else {
                        $cvm          = $verdict_meta[$cv] ?? $verdict_meta['caution'];
                        $card_title   = __('Upgrade Risk', 'phpinfo-wp');
                        $card_sub     = sprintf(__('Target: WordPress %s', 'phpinfo-wp'), esc_html($target_wp));
                    }
                    ?>
                    <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                        <div style="margin-bottom:12px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                                <div style="font-size:15px; font-weight:800; color:#0f172a; line-height:1.2;">
                                    <?php echo esc_html($card_title); ?>
                                </div>
                                <span class="phpinfowp-dbh-badge" style="background:<?php echo !$is_pro ? '#64748b' : esc_attr($cvm['color']); ?>; color:#fff; font-size:10px; font-weight:700; padding:2.5px 8px; border-radius:10px; letter-spacing:0.4px; white-space:nowrap; flex-shrink:0; line-height:1.3;">
                                    <?php echo !$is_pro ? __('PRO', 'phpinfo-wp') : esc_html($cvm['badge'] ?? strtoupper($cvm['label'])); ?>
                                </span>
                            </div>
                            <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                                <?php echo esc_html($card_sub); ?>
                            </div>
                        </div>

                        <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                            <?php if ($mode === 'in_place_audit'): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="color:#334155; font-weight:600;"><?php _e('Active Issues:', 'phpinfo-wp'); ?></span>
                                    <span style="color:<?php echo (int)($core_result['total_breaks'] ?? 0) > 0 ? '#b91c1c' : '#15803d'; ?>; font-weight:800; font-size:13.5px;">
                                        <?php echo (int)($core_result['total_breaks'] ?? 0); ?>
                                    </span>
                                </div>

                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="color:#334155; font-weight:600;"><?php _e('Latent Debt:', 'phpinfo-wp'); ?></span>
                                    <span style="color:<?php echo (int)($core_result['total_depr'] ?? 0) > 0 ? '#b45309' : '#0f172a'; ?>; font-weight:800; font-size:13.5px;">
                                        <?php echo (int)($core_result['total_depr'] ?? 0); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="color:#334155; font-weight:600;"><?php _e('Hard Breaks:', 'phpinfo-wp'); ?></span>
                                    <span style="color:<?php echo (int)($core_result['total_breaks'] ?? 0) > 0 ? '#b91c1c' : '#15803d'; ?>; font-weight:800; font-size:13.5px;">
                                        <?php echo (int)($core_result['total_breaks'] ?? 0); ?>
                                    </span>
                                </div>

                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="color:#334155; font-weight:600;"><?php _e('Deprecations:', 'phpinfo-wp'); ?></span>
                                    <span style="color:<?php echo (int)($core_result['total_depr'] ?? 0) > 0 ? '#b45309' : '#0f172a'; ?>; font-weight:800; font-size:13.5px;">
                                        <?php echo (int)($core_result['total_depr'] ?? 0); ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($core_result['total_mitigated']) && $core_result['total_mitigated'] > 0): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="color:#334155; font-weight:600;"><?php _e('Mitigated:', 'phpinfo-wp'); ?></span>
                                    <span style="color:#15803d; font-weight:700; font-size:13.5px;">
                                        <?php echo (int) $core_result['total_mitigated']; ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="color:#334155; font-weight:600;"><?php _e('Affected Plugins/Themes:', 'phpinfo-wp'); ?></span>
                                <?php if ($is_pro): ?>
                                <span style="color:<?php echo (int)($core_result['with_issues'] ?? 0) > 0 ? '#b45309' : '#15803d'; ?>; font-weight:700; font-size:13.5px;">
                                    <?php printf(__('%d of %d', 'phpinfo-wp'), (int)($core_result['with_issues'] ?? 0), (int)($core_result['owner_count'] ?? 0)); ?>
                                </span>
                                <?php else: ?>
                                <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="color:#334155; font-weight:600;"><?php _e('Files Scanned:', 'phpinfo-wp'); ?></span>
                                <?php if ($is_pro): ?>
                                <span style="color:#0f172a; font-weight:700; font-size:13.5px;">
                                    <?php echo number_format($core_result['files'] ?? 0); ?>
                                </span>
                                <?php else: ?>
                                <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="color:#334155; font-weight:600;"><?php _e('jQuery Migrate:', 'phpinfo-wp'); ?></span>
                                <span style="color:<?php echo ($core_result['jquery_migrate'] ?? '') === 'present' ? '#15803d' : '#64748b'; ?>; font-weight:700; font-size:13.5px;">
                                    <?php echo ($core_result['jquery_migrate'] ?? '') === 'present' ? __('Loaded', 'phpinfo-wp') : __('Not Loaded', 'phpinfo-wp'); ?>
                                </span>
                            </div>

                            <?php if (!empty($core_result['scanned_at'])): ?>
                                <div style="font-size:11.5px; margin-top:4px; border-top:1px dashed #cbd5e1; padding-top:6px; color:#64748b; text-align:center;">
                                    <?php printf(__('Scanned %s', 'phpinfo-wp'), esc_html(human_time_diff($core_result['scanned_at']) . ' ago')); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            } elseif ($subtab === 'plugins' && class_exists('Phpinfo_WP_Update_Guard')) {
                $guard_result = Phpinfo_WP_Update_Guard::get_result();
                if ($guard_result) {
                    $gv = $guard_result['verdict'] ?? 'safe';
                    $gvm = $verdict_meta[$gv] ?? $verdict_meta['safe'];
                    ?>
                    <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                        <div style="margin-bottom:12px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                                <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                                    <?php _e('Update Guard', 'phpinfo-wp'); ?>
                                </div>
                                <span class="phpinfowp-dbh-badge" style="background:<?php echo !$is_pro ? '#64748b' : ($gv === 'risky' ? '#b91c1c' : ($gv === 'caution' ? '#b45309' : '#15803d')); ?>; color:#fff; font-size:10px; font-weight:700; padding:2.5px 8px; border-radius:10px; letter-spacing:0.4px; white-space:nowrap; flex-shrink:0; line-height:1.3;">
                                    <?php echo !$is_pro ? __('PRO', 'phpinfo-wp') : esc_html($gvm['badge'] ?? strtoupper($gvm['label'])); ?>
                                </span>
                            </div>
                            <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                                <?php printf(__('%d Updates Scanned', 'phpinfo-wp'), (int)($guard_result['total'] ?? 0)); ?>
                            </div>
                        </div>

                        <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="color:#334155; font-weight:600;"><?php _e('Risky:', 'phpinfo-wp'); ?></span>
                                <span style="color:<?php echo (int)($guard_result['risky_count'] ?? 0) > 0 ? '#b91c1c' : '#15803d'; ?>; font-weight:800; font-size:13.5px;">
                                    <?php echo (int)($guard_result['risky_count'] ?? 0); ?>
                                </span>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="color:#334155; font-weight:600;"><?php _e('Caution:', 'phpinfo-wp'); ?></span>
                                <span style="color:<?php echo (int)($guard_result['caution_count'] ?? 0) > 0 ? '#b45309' : '#0f172a'; ?>; font-weight:800; font-size:13.5px;">
                                    <?php echo (int)($guard_result['caution_count'] ?? 0); ?>
                                </span>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="color:#334155; font-weight:600;"><?php _e('Safe:', 'phpinfo-wp'); ?></span>
                                <span style="color:#15803d; font-weight:800; font-size:13.5px;">
                                    <?php echo (int)($guard_result['safe_count'] ?? 0); ?>
                                </span>
                            </div>

                            <?php if (!empty($guard_result['scanned_at'])): ?>
                                <div style="font-size:11.5px; margin-top:4px; border-top:1px dashed #cbd5e1; padding-top:6px; color:#64748b; text-align:center;">
                                    <?php printf(__('Scanned %s', 'phpinfo-wp'), esc_html(human_time_diff($guard_result['scanned_at']) . ' ago')); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            } elseif ($subtab === 'history' && class_exists('Phpinfo_WP_Update_History')) {
                $live_health = Phpinfo_WP_Update_History::get_latest_health();
                $history_items = Phpinfo_WP_Update_History::recent(50);
                $is_healthy = $live_health ? (!empty($live_health['loopback']) && !empty($live_health['admin_ok'])) : true;
                ?>
                <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                                <?php _e('Site Health', 'phpinfo-wp'); ?>
                            </div>
                            <span class="phpinfowp-dbh-badge" style="background:<?php echo $is_healthy ? '#15803d' : '#b91c1c'; ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                                <?php echo $is_healthy ? __('HEALTHY', 'phpinfo-wp') : __('ISSUES FOUND', 'phpinfo-wp'); ?>
                            </span>
                        </div>
                        <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                            <?php _e('Live Runtime & Loopback Status', 'phpinfo-wp'); ?>
                        </div>
                    </div>

                    <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('HTTP Loopback:', 'phpinfo-wp'); ?></span>
                            <span style="color:<?php echo (!empty($live_health['loopback'])) ? '#15803d' : ((isset($live_health['loopback']) && !$live_health['loopback']) ? '#b91c1c' : '#15803d'); ?>; font-weight:800; font-size:13.5px;">
                                <?php echo (!empty($live_health['loopback'])) ? '200 OK' : ((isset($live_health['loopback']) && !$live_health['loopback']) ? 'Failed' : '200 OK'); ?>
                            </span>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('Admin Access:', 'phpinfo-wp'); ?></span>
                            <span style="color:<?php echo (!empty($live_health['admin_ok'])) ? '#15803d' : ((isset($live_health['admin_ok']) && !$live_health['admin_ok']) ? '#b91c1c' : '#15803d'); ?>; font-weight:800; font-size:13.5px;">
                                <?php echo (!empty($live_health['admin_ok'])) ? 'Reachable' : ((isset($live_health['admin_ok']) && !$live_health['admin_ok']) ? 'Failed' : 'Reachable'); ?>
                            </span>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('WP-Cron Events:', 'phpinfo-wp'); ?></span>
                            <span style="color:<?php echo (!empty($live_health['cron_ok'])) ? '#15803d' : '#b45309'; ?>; font-weight:800; font-size:13.5px;">
                                <?php echo (!empty($live_health['cron_ok'])) ? 'Active' : 'Warning'; ?>
                            </span>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('Error Log:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:700; font-size:13.5px;">
                                <?php echo !empty($live_health['error_log']) ? sprintf(__('%s lines', 'phpinfo-wp'), number_format($live_health['error_lines'])) : __('Clean', 'phpinfo-wp'); ?>
                            </span>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('Logged Updates:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:700; font-size:13.5px;">
                                <?php echo count($history_items); ?>
                            </span>
                        </div>

                        <?php if (!empty($live_health['checked_at'])): ?>
                            <div style="font-size:11.5px; margin-top:4px; border-top:1px dashed #cbd5e1; padding-top:6px; color:#64748b; text-align:center;">
                                <?php printf(__('Checked %s', 'phpinfo-wp'), esc_html(human_time_diff($live_health['checked_at']) . ' ago')); ?>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top:8px; border-top:1px solid #e2e8f0; padding-top:10px;">
                            <form id="phpinfowp-uh-form" method="post" style="margin:0; display:flex; gap:6px;">
                                <?php wp_nonce_field('phpinfowp_uh_nonce'); ?>
                                <button type="submit" id="phpinfowp-uh-check-btn" name="phpinfowp_uh_health_check" value="1" class="button button-primary" style="flex:1; text-align:center; justify-content:center; display:flex; align-items:center; gap:4px; font-size:12px; height:32px; line-height:30px;">
                                    <span class="dashicons dashicons-heart" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span>
                                    <?php _e('Run Health Check', 'phpinfo-wp'); ?>
                                </button>
                                <?php if (!empty($history_items) || $live_health): ?>
                                    <button type="button" id="phpinfowp-uh-clear-btn" name="phpinfowp_uh_clear" value="1" class="button button-secondary" style="font-size:12px; height:32px; line-height:30px; padding:0 8px;">
                                        <?php _e('Clear', 'phpinfo-wp'); ?>
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php
            }
        } elseif ($current_slug === 'piwp-permissions' && class_exists('Phpinfo_WP_Permissions')) {
            $is_pro = Phpinfo_WP_License::is_valid();
            $scan_results = Phpinfo_WP_Permissions::get_result();
            $wp_config = $scan_results['wp_config'] ?? null;
            $dangerous = $scan_results['dangerous'] ?? [];
            $mismatch  = $scan_results['mismatch'] ?? [];
            $scanned   = $scan_results['scanned'] ?? null;
            $php_user  = $scan_results['php_user'] ?? Phpinfo_WP_Permissions::get_php_user();
            $php_uid   = $scan_results['php_uid'] ?? (function_exists('posix_geteuid') ? posix_geteuid() : (function_exists('getmyuid') ? getmyuid() : ''));
            $has_issues = $is_pro && $scan_results !== null && (!empty($dangerous) || !empty($mismatch) || ($wp_config && !empty($wp_config['is_dangerous'])));
            $is_pending = ($scan_results === null);
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('Permissions Audit', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo !$is_pro ? '#64748b' : ($is_pending ? '#64748b' : ($has_issues ? '#b91c1c' : '#15803d')); ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo !$is_pro ? __('PRO', 'phpinfo-wp') : ($is_pending ? __('READY', 'phpinfo-wp') : ($has_issues ? __('ACTION NEEDED', 'phpinfo-wp') : __('SECURE', 'phpinfo-wp'))); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php _e('File System & Ownership Security', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Exe. User:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:13px;">
                            <code><?php echo esc_html($php_user); ?></code><?php echo $php_uid !== '' ? ' (' . esc_html($php_uid) . ')' : ''; ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Files Scanned:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:13.5px;">
                            <?php echo $scanned !== null ? number_format($scanned) : '<span style="color:#94a3b8;">' . __('Pending', 'phpinfo-wp') . '</span>'; ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Insecure Files:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:<?php echo count($dangerous) > 0 ? '#b91c1c' : '#15803d'; ?>; font-weight:800; font-size:13.5px;">
                                <?php echo count($dangerous); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Owner Mismatches:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <?php $m_count = $scan_results['mismatch_count'] ?? count($mismatch); ?>
                            <span style="color:<?php echo $m_count > 0 ? '#b45309' : '#15803d'; ?>; font-weight:800; font-size:13.5px;">
                                <?php echo (int) $m_count; ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_pro): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; height:32px; line-height:30px; background:#777BB3; border-color:#777BB3; text-decoration:none;">
                            <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <?php _e('Unlock Permissions Audit &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-security-headers' && class_exists('Phpinfo_WP_Security_Headers')) {
            $is_pro = Phpinfo_WP_License::is_valid();
            $audit = Phpinfo_WP_Security_Headers::get_cached();
            $grade = $audit['grade'] ?? '—';
            $score = (int) ($audit['score'] ?? 0);
            $grade_class = $is_pro ? 'grade-' . strtolower(str_replace('+', 'plus', $grade)) : 'grade-b';
            $passed  = count(array_filter($audit['results'] ?? [], function ($r) { return !empty($r['present']); }));
            $total   = count($audit['results'] ?? []);
            $missing = $total - $passed;
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('Security Headers', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo !$is_pro ? '#64748b' : (($score >= 80) ? '#15803d' : (($score >= 50) ? '#b45309' : '#b91c1c')); ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; letter-spacing:0.4px; white-space:nowrap; flex-shrink:0;" title="<?php printf(esc_attr__('Score: %d/100', 'phpinfo-wp'), (int)$score); ?>">
                            <?php echo $is_pro ? sprintf(__('GRADE %s', 'phpinfo-wp'), esc_html($grade)) : __('PRO', 'phpinfo-wp'); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php _e('HTTP Security Header Defense', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Score:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:<?php echo ($score >= 80) ? '#15803d' : (($score >= 50) ? '#b45309' : '#b91c1c'); ?>; font-weight:800; font-size:13.5px;">
                                <?php echo (int) $score; ?> / 100
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Headers Set:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:#15803d; font-weight:800; font-size:13.5px;">
                                <?php echo (int) $passed; ?> / <?php echo (int) $total; ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Missing Headers:', 'phpinfo-wp'); ?></span>
                        <span style="color:<?php echo $missing > 0 ? '#b91c1c' : '#15803d'; ?>; font-weight:800; font-size:13.5px;">
                            <?php echo (int) $missing; ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Audited URL:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12px; max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($audit['url'] ?? ''); ?>">
                            <code><?php echo esc_html(wp_parse_url($audit['url'] ?? home_url(), PHP_URL_HOST)); ?></code>
                        </span>
                    </div>
                </div>

                <?php if (!$is_pro): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; height:32px; line-height:30px; background:#777BB3; border-color:#777BB3; text-decoration:none;">
                            <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <?php _e('Unlock Security Headers &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-ssl' && class_exists('Phpinfo_WP_SSL')) {
            $is_pro  = Phpinfo_WP_License::is_valid();
            $primary = Phpinfo_WP_SSL::get_result();
            $has_ssl = ($primary && empty($primary['error']));
            $status = $primary['status'] ?? 'ok';
            $days = (int) ($primary['days'] ?? 0);
            $is_valid = $status === 'ok';
            $grade_class = !$has_ssl ? 'grade-c' : ($is_valid ? 'grade-aplus' : ($status === 'warning' ? 'grade-c' : 'grade-f'));
            $grade_icon  = !$has_ssl ? '—' : ($is_valid ? '✓' : ($status === 'warning' ? '!' : '✕'));
            $status_label = $has_ssl ? Phpinfo_WP_SSL::status_label($status) : __('PENDING SCAN', 'phpinfo-wp');
            $status_color = $has_ssl ? Phpinfo_WP_SSL::status_color($status) : '#64748b';
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('SSL Monitor', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo $is_pro ? esc_attr($status_color) : '#64748b'; ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo $is_pro ? esc_html(strtoupper($status_label)) : __('PRO', 'phpinfo-wp'); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php echo $has_ssl ? ($days >= 0 ? sprintf(__('%d Days Left · HTTPS Active', 'phpinfo-wp'), $days) : __('Certificate Expired', 'phpinfo-wp')) : __('HTTPS Certificate &amp; Expiry Tracking', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Domain:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12.5px; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($primary['host'] ?? (string) wp_parse_url(get_site_url(), PHP_URL_HOST)); ?>">
                            <code><?php echo esc_html($primary['host'] ?? (string) wp_parse_url(get_site_url(), PHP_URL_HOST)); ?></code>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Expires:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:13px;">
                            <?php echo esc_html($primary['expiry'] ?? '—'); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Issuer:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:600; font-size:12px; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($primary['issuer'] ?? '—'); ?>">
                            <?php echo esc_html($primary['issuer'] ?? '—'); ?>
                        </span>
                    </div>
                </div>

                <?php if (!$is_pro): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; height:32px; line-height:30px; background:#777BB3; border-color:#777BB3; text-decoration:none;">
                            <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <?php _e('Unlock SSL Monitor &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-htaccess') {
            if (!function_exists('get_home_path')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $root_dir = get_home_path();
            $sapi = php_sapi_name();
            $server_software = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
            $is_litespeed = strpos($server_software, 'litespeed') !== false;
            $mode = ($sapi === 'apache2handler' && !$is_litespeed) ? 'htaccess' : 'userini';
            $mode_label = ($mode === 'htaccess') ? 'Apache (.htaccess)' : 'PHP-FPM / Nginx / LiteSpeed';
            $mode_file  = ($mode === 'htaccess') ? '.htaccess' : '.user.ini';
            $target_file = $root_dir . $mode_file;
            $writable = is_writable($root_dir);
            $user_ini_ttl = (int) ini_get('user_ini.cache_ttl') ?: 300;
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('Server Config', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:#2563eb; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo esc_html(strtoupper($mode_file)); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php echo esc_html($mode_label); ?>
                    </div>
                </div>

                <div style="font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Server:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12px; max-width:125px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($mode_label); ?>">
                            <?php echo esc_html($mode === 'htaccess' ? 'Apache' : 'PHP-FPM/Nginx'); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Target File:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12.5px;">
                            <code><?php echo esc_html($mode_file); ?></code>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Propagation:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12px;">
                            <?php echo $mode === 'userini' ? '~' . (int)$user_ini_ttl . 's (TTL)' : __('Instant', 'phpinfo-wp'); ?>
                        </span>
                    </div>

                    <div style="font-size:11px; color:#64748b; font-family:monospace; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:4px 6px; word-break:break-all; margin-top:4px;">
                        <?php echo esc_html($target_file); ?>
                    </div>
                </div>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-safemode') {
            $session = Phpinfo_WP_Safemode::current_session();
            $is_active = $session !== null;
            $mu_present = Phpinfo_WP_Safemode::mu_plugin_installed();
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('Safe Sandbox', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo $is_active ? '#b45309' : '#15803d'; ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo $is_active ? __('ACTIVE', 'phpinfo-wp') : __('READY', 'phpinfo-wp'); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php _e('Zero-Risk Plugin Troubleshooting', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div style="font-size:12.5px; line-height:1.7; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Isolation Level:', 'phpinfo-wp'); ?></span>
                        <span style="color:<?php echo $mu_present ? '#15803d' : '#2563eb'; ?>; font-weight:700; font-size:12px;">
                            <?php echo $mu_present ? __('Full Request ✓', 'phpinfo-wp') : __('Admin & AJAX', 'phpinfo-wp'); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Database Safety:', 'phpinfo-wp'); ?></span>
                        <span style="color:#15803d; font-weight:700; font-size:12px;">
                            <?php _e('Untouched ✓', 'phpinfo-wp'); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Public Visitors:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12px;">
                            <?php _e('Unaffected', 'phpinfo-wp'); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Lockout Protection:', 'phpinfo-wp'); ?></span>
                        <span style="color:#15803d; font-weight:700; font-size:12px;">
                            <?php _e('Auto-Expires', 'phpinfo-wp'); ?>
                        </span>
                    </div>
                </div>


            </div>
            <?php
        } elseif ($current_slug === 'piwp-info') {
            global $wpdb;
            $eol = Phpinfo_WP_EOL::status();
            $eol_colors = ['ok' => '#15803d', 'warning' => '#b45309', 'eol' => '#b91c1c', 'unknown' => '#64748b'];
            $eol_color  = $eol_colors[$eol['status']] ?? '#64748b';
            $eol_badge  = $eol['status'] === 'eol' ? __('END OF LIFE', 'phpinfo-wp') : ($eol['status'] === 'warning' ? __('EOL SOON', 'phpinfo-wp') : __('SUPPORTED', 'phpinfo-wp'));

            $mem_limit   = ini_get('memory_limit');
            $mem_used    = memory_get_usage(true);
            $mem_limit_b = wp_convert_hr_to_bytes($mem_limit);
            $mem_pct     = $mem_limit_b > 0 ? round($mem_used / $mem_limit_b * 100) : 0;
            $mem_used_fmt = $mem_used >= GB_IN_BYTES ? round($mem_used / GB_IN_BYTES, 2) . ' GB' : ($mem_used >= MB_IN_BYTES ? round($mem_used / MB_IN_BYTES, 1) . ' MB' : round($mem_used / KB_IN_BYTES, 1) . ' KB');

            $db_version  = $wpdb->get_var('SELECT VERSION()') ?? '—';
            $db_size_raw = get_transient('phpinfowp_db_size_raw');
            if (false === $db_size_raw) {
                $db_size_raw = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = %s",
                    DB_NAME
                ));
                set_transient('phpinfowp_db_size_raw', (int)$db_size_raw, HOUR_IN_SECONDS * 6);
            }
            $db_size = $db_size_raw ? ($db_size_raw >= GB_IN_BYTES ? round($db_size_raw / GB_IN_BYTES, 2) . ' GB' : ($db_size_raw >= MB_IN_BYTES ? round($db_size_raw / MB_IN_BYTES, 1) . ' MB' : round($db_size_raw / KB_IN_BYTES, 1) . ' KB')) : '—';
            $active_plugins_count = count((array)get_option('active_plugins', []));
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('Server Specs', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo esc_attr($eol_color); ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo esc_html($eol_badge); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php printf(__('PHP %s · WordPress %s', 'phpinfo-wp'), esc_html(PHP_VERSION), esc_html(get_bloginfo('version'))); ?>
                    </div>
                </div>

                <div style="font-size:12.5px; line-height:1.7; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('WordPress:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12.5px;">
                            v<?php echo esc_html(get_bloginfo('version')); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Active Plugins:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12px;">
                            <?php echo (int)$active_plugins_count; ?> <?php echo is_multisite() ? __('(Multisite)', 'phpinfo-wp') : __('(Single)', 'phpinfo-wp'); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Database:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12px; max-width:125px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($db_version); ?>">
                            <?php echo esc_html(explode('-', $db_version)[0]); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('DB Size:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12px;">
                            <?php echo esc_html($db_size); ?>
                        </span>
                    </div>
                </div>

                <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <span style="color:#334155; font-weight:600; font-size:12px;"><?php _e('Memory Usage:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12px;"><?php echo esc_html($mem_used_fmt); ?> / <?php echo esc_html($mem_limit); ?></span>
                    </div>
                    <div style="background:#f1f5f9; border-radius:4px; height:6px; overflow:hidden; border:1px solid #e2e8f0;">
                        <div style="height:100%; width:<?php echo min(100, $mem_pct); ?>%; background:<?php echo $mem_pct > 85 ? '#b91c1c' : '#777BB3'; ?>;"></div>
                    </div>
                    <div style="font-size:11px; color:#64748b; text-align:right; margin-top:2px;">
                        <?php echo (int)$mem_pct; ?>% <?php _e('allocated', 'phpinfo-wp'); ?>
                    </div>
                </div>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-snapshots') {
            $is_pro = Phpinfo_WP_License::is_valid();
            $snapshots = $is_pro ? Phpinfo_WP_Snapshots::list(50) : [];
            $tracked_count = count(Phpinfo_WP_Snapshots::TRACKED);
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('Config Snapshots', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo $is_pro ? '#15803d' : '#64748b'; ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo $is_pro ? __('AUTO-BACKUP', 'phpinfo-wp') : __('PRO', 'phpinfo-wp'); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php _e('PHP & Server Configuration State History', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div style="font-size:12.5px; line-height:1.7; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Tracked Dir:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12px;">
                            <?php echo (int)$tracked_count; ?> <?php _e('dirs', 'phpinfo-wp'); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Auto-Backup:', 'phpinfo-wp'); ?></span>
                        <span style="color:<?php echo $is_pro ? '#15803d' : '#777BB3'; ?>; font-weight:700; font-size:12px;">
                            <?php echo $is_pro ? __('Weekly (Cron) ✓', 'phpinfo-wp') : __('Pro Only 🔒', 'phpinfo-wp'); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Saved Snapshots:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:#0f172a; font-weight:700; font-size:12px;">
                                <?php echo count($snapshots); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($snapshots)): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('Latest:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:700; font-size:11.5px;">
                                <?php echo esc_html(human_time_diff(strtotime($snapshots[0]->created_at)) . ' ago'); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!$is_pro): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; height:32px; line-height:30px; background:#777BB3; border-color:#777BB3; text-decoration:none;">
                            <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <?php _e('Unlock Config Snapshots &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-cron') {
            $is_pro = Phpinfo_WP_License::is_valid();
            $cron_summary = Phpinfo_WP_Cron_Monitor::summary();
            $has_issues = $is_pro && (!empty($cron_summary['overdue']) || !empty($cron_summary['orphan']) || !empty($cron_summary['disabled']));
            $cron_status = !$is_pro ? __('PRO', 'phpinfo-wp') : (!empty($cron_summary['disabled']) ? __('WP_CRON DISABLED', 'phpinfo-wp') : ($has_issues ? __('ATTENTION NEEDED', 'phpinfo-wp') : __('HEALTHY', 'phpinfo-wp')));
            $status_color = !$is_pro ? '#64748b' : (!empty($cron_summary['disabled']) || !empty($cron_summary['overdue']) ? '#b91c1c' : (!empty($cron_summary['orphan']) ? '#b45309' : '#15803d'));
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('WP-Cron Monitor', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo esc_attr($status_color); ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo $is_pro ? esc_html(strtoupper($cron_status)) : __('PRO', 'phpinfo-wp'); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php _e('Scheduled Tasks & Background Queue', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div style="font-size:12.5px; line-height:1.7; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Scheduled Events:', 'phpinfo-wp'); ?></span>
                        <span style="color:#0f172a; font-weight:700; font-size:12.5px;">
                            <?php echo (int)($cron_summary['total'] ?? 0); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:<?php echo !empty($cron_summary['overdue']) ? '#b91c1c' : '#334155'; ?>; font-weight:600;"><?php _e('Overdue:', 'phpinfo-wp'); ?></span>
                        <span style="color:<?php echo !empty($cron_summary['overdue']) ? '#b91c1c' : '#15803d'; ?>; font-weight:700; font-size:12px;">
                            <?php echo (int)($cron_summary['overdue'] ?? 0); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:<?php echo !empty($cron_summary['orphan']) ? '#b45309' : '#334155'; ?>; font-weight:600;"><?php _e('Orphans (No Callback):', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:<?php echo !empty($cron_summary['orphan']) ? '#b45309' : '#15803d'; ?>; font-weight:700; font-size:12px;">
                                <?php echo (int)($cron_summary['orphan'] ?? 0); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Due Within 60s:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:#0f172a; font-weight:700; font-size:12px;">
                                <?php echo (int)($cron_summary['imminent'] ?? 0); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_pro): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; height:32px; line-height:30px; background:#777BB3; border-color:#777BB3; text-decoration:none;">
                            <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <?php _e('Unlock WP-Cron Monitor &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-mail') {
            $is_pro = Phpinfo_WP_License::is_valid();
            $mail_audit = Phpinfo_WP_Mail_Check::get_result();
            $routing = Phpinfo_WP_Mail_Check::get_routing_cached();
            $from_email = $mail_audit['from_email'] ?? $routing['from_email'] ?? '';
            $domain = $mail_audit['domain'] ?? $routing['domain'] ?? '';
            $smtp_plugin = $mail_audit['smtp_plugin'] ?? $routing['smtp_plugin'] ?? null;
            $has_smtp = !empty($smtp_plugin);
            $has_spf = $is_pro && !empty($mail_audit['spf']['present']);
            $has_dmarc = $is_pro && !empty($mail_audit['dmarc']['present']);
            $has_scanned = !empty($mail_audit);
            
            $deliverability_score = $has_scanned ? (($has_smtp ? 40 : 10) + ($has_spf ? 30 : 0) + ($has_dmarc ? 30 : 0)) : null;
            $grade_class = !$is_pro ? 'grade-b' : ($deliverability_score !== null ? ($deliverability_score >= 80 ? 'grade-aplus' : ($deliverability_score >= 50 ? 'grade-c' : 'grade-f')) : 'grade-b');
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('Mail Health', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo !$is_pro ? '#64748b' : ($has_smtp ? '#15803d' : ($has_scanned ? '#b45309' : '#64748b')); ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo !$is_pro ? __('PRO', 'phpinfo-wp') : ($has_smtp ? __('SMTP ACTIVE', 'phpinfo-wp') : ($has_scanned ? __('PHP MAIL()', 'phpinfo-wp') : __('READY', 'phpinfo-wp'))); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php _e('SPF, DMARC & Deliverability Health', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div style="font-size:12.5px; line-height:1.7; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:6px;">
                    <div>
                        <div style="color:#334155; font-weight:600;"><?php _e('Sending From:', 'phpinfo-wp'); ?></div>
                        <div style="color:#0f172a; font-weight:700; font-size:12px; word-break:break-all;" title="<?php echo esc_attr($from_email); ?>">
                            <?php echo esc_html($from_email); ?>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Domain:', 'phpinfo-wp'); ?></span>
                        <code style="color:#0f172a; font-size:11.5px;"><?php echo esc_html($domain); ?></code>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Mail Method:', 'phpinfo-wp'); ?></span>
                        <span style="color:<?php echo $has_smtp ? '#15803d' : '#b45309'; ?>; font-weight:700; font-size:12px;">
                            <?php echo $has_smtp ? __('SMTP Active ✓', 'phpinfo-wp') : __('PHP mail() ⚠️', 'phpinfo-wp'); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('SPF Record:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:<?php echo $has_spf ? '#15803d' : ($has_scanned ? '#b91c1c' : '#64748b'); ?>; font-weight:700; font-size:12px;">
                                <?php echo $has_spf ? __('Valid ✓', 'phpinfo-wp') : ($has_scanned ? __('Missing ✕', 'phpinfo-wp') : __('Pending', 'phpinfo-wp')); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('DMARC Record:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:<?php echo $has_dmarc ? '#15803d' : ($has_scanned ? '#b91c1c' : '#64748b'); ?>; font-weight:700; font-size:12px;">
                                <?php echo $has_dmarc ? __('Valid ✓', 'phpinfo-wp') : ($has_scanned ? __('Missing ✕', 'phpinfo-wp') : __('Pending', 'phpinfo-wp')); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_pro): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; height:32px; line-height:30px; background:#777BB3; border-color:#777BB3; text-decoration:none;">
                            <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <?php _e('Unlock Mail Health Monitor &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-alerts') {
            $alert_settings = Phpinfo_WP_Alerts::get_settings();
            $is_pro = Phpinfo_WP_License::is_valid();
            $alerts_active = $is_pro && !empty($alert_settings['enabled']);
            $next_cron = wp_next_scheduled('phpinfowp_weekly_maintenance');
            $grade_class = $alerts_active ? 'grade-aplus' : ($is_pro ? 'grade-b' : 'grade-c');
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('Alert Engine', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo !$is_pro ? '#64748b' : ($alerts_active ? '#15803d' : '#b45309'); ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo !$is_pro ? __('PRO', 'phpinfo-wp') : ($alerts_active ? __('ENABLED', 'phpinfo-wp') : __('DISABLED', 'phpinfo-wp')); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php _e('Email, Slack & Discord Notifications', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div style="font-size:12.5px; line-height:1.7; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:6px;">
                    <div>
                        <div style="color:#334155; font-weight:600;"><?php _e('Email:', 'phpinfo-wp'); ?></div>
                        <div style="color:#0f172a; font-weight:700; font-size:12px; word-break:break-all;" title="<?php echo esc_attr(get_option('admin_email')); ?>">
                            <?php echo esc_html(get_option('admin_email')); ?>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Webhooks:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:#0f172a; font-weight:700; font-size:12px;">
                                <?php echo !empty($alert_settings['webhook_url']) ? esc_html(ucfirst($alert_settings['webhook_type'] ?? 'webhook')) : __('Slack · Discord', 'phpinfo-wp'); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#334155; font-weight:600;"><?php _e('Weekly Scan:', 'phpinfo-wp'); ?></span>
                        <?php if ($is_pro): ?>
                            <span style="color:<?php echo $next_cron ? '#15803d' : '#64748b'; ?>; font-weight:700; font-size:11.5px;">
                                <?php echo $next_cron ? esc_html(wp_date('M j, H:i', $next_cron)) . ' UTC' : __('Automatic', 'phpinfo-wp'); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_pro): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; height:32px; line-height:30px; background:#777BB3; border-color:#777BB3; text-decoration:none;">
                            <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <?php _e('Unlock Alert Engine &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } elseif ($current_slug === 'piwp-error-log') {
            $is_pro = Phpinfo_WP_License::is_valid();
            $log_path = class_exists('Phpinfo_WP_Error_Log') ? Phpinfo_WP_Error_Log::get_active_log_path() : null;
            $log_size = $log_path ? Phpinfo_WP_Error_Log::format_bytes(Phpinfo_WP_Error_Log::size($log_path)) : '0 B';
            $log_lines = $log_path ? Phpinfo_WP_Error_Log::tail($log_path) : [];
            $line_count = count($log_lines);
            $has_log = !empty($log_path);
            $is_wp = ($log_path && strpos($log_path, 'debug.log') !== false);
            $log_type_label = $is_wp ? __('WordPress debug.log', 'phpinfo-wp') : __('PHP Server error_log', 'phpinfo-wp');
            $grade_class = !$is_pro ? 'grade-b' : ($has_log ? ($line_count > 0 ? 'grade-c' : 'grade-aplus') : 'grade-b');
            ?>
            <div class="phpinfowp-side-score-card" style="padding:16px 14px; border:1px solid #d8dae5; box-shadow:0 2px 5px rgba(0,0,0,0.04);">
                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.2;">
                            <?php _e('PHP Error Log', 'phpinfo-wp'); ?>
                        </div>
                        <span class="phpinfowp-dbh-badge" style="background:<?php echo !$is_pro ? '#64748b' : ($has_log ? ($line_count > 0 ? '#b91c1c' : '#15803d') : '#64748b'); ?>; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">
                            <?php echo !$is_pro ? __('PRO', 'phpinfo-wp') : ($has_log ? ($line_count > 0 ? sprintf(_n('%d ERROR', '%d ERRORS', $line_count, 'phpinfo-wp'), $line_count) : __('CLEAN', 'phpinfo-wp')) : __('NO LOG', 'phpinfo-wp')); ?>
                        </span>
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                        <?php echo esc_html($log_type_label); ?>
                    </div>
                </div>

                <div style="font-size:12.5px; line-height:1.7; border-top:1px solid #e2e8f0; padding-top:10px; display:flex; flex-direction:column; gap:6px;">
                    <?php if ($is_pro && $has_log): ?>
                        <div>
                            <div style="color:#334155; font-weight:600;"><?php _e('Log File:', 'phpinfo-wp'); ?></div>
                            <code style="color:#0f172a; font-size:11px; word-break:break-all; display:block; margin-top:2px; background:#f8fafc; padding:4px 6px; border-radius:4px; border:1px solid #e2e8f0;" title="<?php echo esc_attr($log_path); ?>">
                                <?php echo esc_html(Phpinfo_WP_Error_Log::mask_path($log_path)); ?>
                            </code>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('File Size:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:700; font-size:12px;">
                                <?php echo esc_html($log_size); ?>
                            </span>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('Recorded:', 'phpinfo-wp'); ?></span>
                            <span style="color:<?php echo $line_count > 0 ? '#b91c1c' : '#15803d'; ?>; font-weight:700; font-size:12px;">
                                <?php echo sprintf(_n('%d line', '%d lines', $line_count, 'phpinfo-wp'), $line_count); ?>
                            </span>
                        </div>
                    <?php elseif ($is_pro): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('WP_DEBUG:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:700; font-size:12px;">
                                <?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('WP_DEBUG_LOG:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:700; font-size:12px;">
                                <?php echo defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'true' : 'false'; ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('Log File Path:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:700; font-size:12px; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($log_path ?? __('Not Found', 'phpinfo-wp')); ?>">
                                <?php echo esc_html($log_path ? wp_basename($log_path) : __('Not Found', 'phpinfo-wp')); ?>
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('File Size:', 'phpinfo-wp'); ?></span>
                            <span style="color:#0f172a; font-weight:700; font-size:12px;">
                                <?php echo esc_html($log_size); ?>
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#334155; font-weight:600;"><?php _e('Recent Errors:', 'phpinfo-wp'); ?></span>
                            <span style="color:#777BB3; font-weight:700; font-size:11.5px;"><?php _e('Pro Only 🔒', 'phpinfo-wp'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!$is_pro): ?>
                    <div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; height:32px; line-height:30px; background:#777BB3; border-color:#777BB3; text-decoration:none;">
                            <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span>
                            <?php _e('Unlock Error Log &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        echo '</div>'; // close .phpinfowp-sidebar-container
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
        if (!isset($groups[$group_key])) wp_die(__('Unknown group.', 'phpinfo-wp'));

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
            'piwp-admin-log'        => 'views/admin-log.php',
            'piwp-report'           => 'views/pro/report.php',
            'piwp-alerts'           => 'views/pro/alerts.php',
        ];
        $file = $map[$slug] ?? null;
        if (!$file) { echo '<p>' . __('Unknown view.', 'phpinfo-wp') . '</p>'; return; }
        require PHPINFOWP_DIR . $file;
    }
}
