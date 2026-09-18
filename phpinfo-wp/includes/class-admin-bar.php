<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Phpinfo_WP_Admin_Bar
 *
 * Enterprise-grade real-time server telemetry HUD (Heads-Up Display) and sentinel
 * cockpit in the WordPress Admin Bar.
 *
 * Designed to seamlessly match phpinfo() WP v8 design system:
 * - Clean white card surface with 12px border radius
 * - Generous 16px inner padding across all sections
 * - 6px/8px component radius with crisp #e2e8f0 borders
 * - Brand indigo/purple primary buttons (#6366f1) and clean secondary buttons
 * - Zero SaaS tracking & zero-runtime database overhead
 */
class Phpinfo_WP_Admin_Bar {

    const OPT_LAST_UNHEALTHY   = 'phpinfowp_last_unhealthy_ts';
    const OPT_OPCACHE_BASELINE = 'phpinfowp_opcache_baseline_hit';

    private const COLOR_GOOD = '#10b981';
    private const COLOR_WARN = '#f59e0b';
    private const COLOR_BAD  = '#ef4444';

    public static function init(): void {
        add_action('admin_head', [__CLASS__, 'print_styles']);
        add_action('wp_head',    [__CLASS__, 'print_styles']);
        add_action('wp_ajax_phpinfowp_bar_flush_opcache', [__CLASS__, 'ajax_flush_opcache']);
        add_action('admin_post_phpinfowp_flush_opcache',   [__CLASS__, 'handle_flush_opcache']);
    }

    /**
     * Render the admin bar pill and dropdown micro-cockpit
     */
    public static function render(WP_Admin_Bar $bar): void {
        if (!current_user_can('manage_options')) return;

        $status = self::status();
        self::persist_streak_state($status);

        // Top root indicator pill in WP Admin Bar (omits title attribute so native tooltip doesn't overlap)
        $bar->add_node([
            'id'    => 'phpinfowp-indicator',
            'title' => self::pill_title($status),
            'href'  => self::primary_href($status),
            'meta'  => [
                'class' => 'phpinfowp-adminbar-node',
            ],
        ]);

        // Generate Markdown System Spec for 1-Click Clipboard
        $specs = self::generate_system_specs($status);

        // Inject High-Density Micro-Cockpit HUD inside dropdown
        $bar->add_node([
            'parent' => 'phpinfowp-indicator',
            'id'     => 'phpinfowp-hud-card',
            'title'  => self::generate_hud_html($status, $specs),
            'href'   => false,
            'meta'   => [
                'class' => 'phpinfowp-hud-container-node',
            ],
        ]);
    }

    /**
     * Generate formatted system specs for support tickets / GitHub issues
     */
    public static function generate_system_specs(array $status): string {
        $theme = wp_get_theme();
        $theme_label = $theme ? $theme->get('Name') . ' v' . $theme->get('Version') : 'Unknown';
        $active_plugins = (array) get_option('active_plugins', []);
        $plugin_count = count($active_plugins);

        $opcache_text = 'Disabled';
        if ($status['opcache'] && !empty($status['opcache']['hit_rate'])) {
            $opcache_text = 'Enabled (' . $status['opcache']['hit_rate'] . '% hit rate, ' . number_format($status['opcache']['cached_scripts']) . ' scripts)';
        }

        $obj_cache = !empty($status['object_cache']) ? ucfirst($status['object_cache']) . ' (Active)' : 'None (Disk-bound)';

        $lines = [
            "### 🖥️ WordPress & Server Telemetry (phpinfo() WP v8.0)",
            "- **PHP Version**: " . PHP_VERSION . " (" . php_sapi_name() . ")",
            "- **Memory Limit**: " . ini_get('memory_limit') . " (Peak: " . size_format($status['memory']['peak_bytes']) . " / " . $status['memory']['pct'] . "%)",
            "- **OPcache**: " . $opcache_text,
            "- **Database**: " . $status['db']['engine'] . " " . $status['db']['version'] . " (Autoload: " . ($status['db']['autoload_size'] ?: 'N/A') . ")",
            "- **Object Cache**: " . $obj_cache,
            "- **Web Server**: " . self::detect_web_server(),
            "- **WordPress**: v" . get_bloginfo('version') . " (Site: " . home_url() . ")",
            "- **Theme**: " . $theme_label,
            "- **Active Plugins**: " . $plugin_count . " active plugins",
            "- **Config Grade**: Grade " . $status['grade'] . (!empty($status['is_capped']) ? '*' : '') . " (" . $status['score'] . "/100)" . (!empty($status['is_capped']) ? ' [Capped by host-locked settings]' : ''),
        ];

        return implode("\n", $lines);
    }

    /**
     * Generate complete Micro-Cockpit HTML layout matching plugin design system
     */
    private static function generate_hud_html(array $status, string $specs): string {
        $sapi = php_sapi_name();
        $sapi_short = (stripos($sapi, 'fpm') !== false) ? 'FPM' : ((stripos($sapi, 'cgi') !== false) ? 'FastCGI' : $sapi);
        $web_server = self::detect_web_server();

        $page_time   = function_exists('timer_stop') ? (float) timer_stop(0, 3) : null;
        $query_count = function_exists('get_num_queries') ? (int) get_num_queries() : null;

        // Speed Pill Color
        $speed_class = 'speed-fast';
        $speed_label = 'Fast';
        if ($page_time !== null) {
            if ($page_time > 0.75) {
                $speed_class = 'speed-slow';
                $speed_label = 'Slow';
            } elseif ($page_time > 0.25) {
                $speed_class = 'speed-avg';
                $speed_label = 'Normal';
            }
        }

        // Memory calculations
        $mem_pct = $status['memory']['pct'];
        $mem_fill_class = $mem_pct >= 85 ? 'fill-danger' : ($mem_pct >= 70 ? 'fill-warn' : 'fill-good');
        $mem_display = size_format($status['memory']['peak_bytes'] ?: memory_get_usage(true));

        // Nonce for OPcache reset
        $nonce = wp_create_nonce('phpinfowp_bar_nonce');
        $flush_direct_url = wp_nonce_url(admin_url('admin-post.php?action=phpinfowp_flush_opcache'), 'phpinfowp_flush_opcache');

        ob_start();
        ?>
        <div class="phpinfowp-adminbar-hud" onclick="event.stopPropagation();">
            <!-- 1. Header: Dual Telemetry Inline -->
            <div class="phpinfowp-hud-section phpinfowp-hud-header">
                <div class="phpinfowp-hud-header-top">
                    <!-- Server Telemetry -->
                    <div class="phpinfowp-hud-telem-pair">
                        <span class="phpinfowp-hud-dot <?php echo esc_attr($status['overall']); ?>"></span>
                        <span class="phpinfowp-brand-text">Server Telemetry:</span>
                        <?php if ($status['is_pro']): ?>
                            <?php
                            $hud_grade_display = esc_html($status['grade']) . (!empty($status['is_capped']) ? '*' : '');
                            $hud_title = !empty($status['is_capped'])
                                ? sprintf(__('Config grade capped at %1$s*. Site is %2$s (%3$d/100), Server is %4$s (%5$d/100) with %6$d host-locked setting(s) — Click to see host-locked settings', 'phpinfo-wp'), $status['grade'], $status['grade_controllable'] ?? 'A+', (int)($status['score_controllable'] ?? 100), $status['grade_raw'] ?? $status['grade'], (int)($status['score_raw'] ?? $status['score']), (int)($status['locked_count'] ?? 0))
                                : sprintf(__('Server config grade %1$s (%2$d/100) — Click to see host-locked settings', 'phpinfo-wp'), $status['grade'], (int)$status['score']);
                            ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-config-grader&tab=locked')); ?>" class="phpinfowp-hud-grade-badge grade-<?php echo strtolower(esc_attr(substr($status['grade'], 0, 1))); ?>" title="<?php echo esc_attr($hud_title); ?>">
                                <?php echo $hud_grade_display; ?> <span style="font-weight:400;opacity:.75;font-size:10px;">(<?php echo (int)$status['score']; ?>)</span>
                            </a>
                        <?php else: ?>
                            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener noreferrer" class="phpinfowp-hud-grade-badge grade-pro-only" title="<?php esc_attr_e('Server Telemetry & host-locked analysis is a Pro feature — Click to unlock', 'phpinfo-wp'); ?>">
                                <?php _e('PRO ONLY', 'phpinfo-wp'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <!-- Divider -->
                    <span class="phpinfowp-hud-telem-div">|</span>
                    <!-- Site Telemetry (Site Controllable) -->
                    <div class="phpinfowp-hud-telem-pair">
                        <span class="phpinfowp-brand-text">Site:</span>
                        <?php if ($status['is_pro']): ?>
                            <?php
                            $site_ctl_grade = $status['grade_controllable'] ?? 'A+';
                            $site_ctl_score = (int)($status['score_controllable'] ?? 100);
                            $site_ctl_class = strtolower(substr($site_ctl_grade, 0, 1));
                            ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-config-grader&tab=issues')); ?>" class="phpinfowp-hud-grade-badge grade-<?php echo esc_attr($site_ctl_class); ?>" title="<?php echo esc_attr(sprintf(__('Site Controllable grade %1$s (%2$d/100) — Click to see actionable issues', 'phpinfo-wp'), $site_ctl_grade, $site_ctl_score)); ?>">
                                <?php echo esc_html($site_ctl_grade); ?> <span style="font-weight:400;opacity:.75;font-size:10px;">(<?php echo $site_ctl_score; ?>)</span>
                            </a>
                        <?php else: ?>
                            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener noreferrer" class="phpinfowp-hud-grade-badge grade-pro-only" title="<?php esc_attr_e('Site Controllable actionable issue audit is a Pro feature — Click to unlock', 'phpinfo-wp'); ?>">
                                <?php _e('PRO ONLY', 'phpinfo-wp'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="phpinfowp-hud-env-pills">
                    <span class="phpinfowp-env-pill">PHP <?php echo esc_html(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ' ' . $sapi_short); ?></span>
                    <span class="phpinfowp-env-pill"><?php echo esc_html($status['db']['engine'] . ' ' . $status['db']['minor']); ?></span>
                    <span class="phpinfowp-env-pill"><?php echo esc_html($web_server); ?></span>
                </div>
            </div>

            <!-- 2. Live Request Telemetry (2x2 Grid) -->
            <div class="phpinfowp-hud-grid">
                <!-- Cell 1: Render Speed -->
                <div class="phpinfowp-hud-cell">
                    <div class="phpinfowp-hud-cell-lbl">⏱ Render Latency</div>
                    <div class="phpinfowp-hud-cell-val">
                        <?php if ($page_time !== null): ?>
                            <span class="phpinfowp-val-main"><?php echo esc_html($page_time); ?>s</span>
                            <span class="phpinfowp-speed-pill <?php echo esc_attr($speed_class); ?>"><?php echo esc_html($speed_label); ?></span>
                        <?php else: ?>
                            <span class="phpinfowp-val-main">N/A</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cell 2: SQL Queries -->
                <div class="phpinfowp-hud-cell">
                    <div class="phpinfowp-hud-cell-lbl">🗄️ SQL Queries</div>
                    <div class="phpinfowp-hud-cell-val">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-db-health')); ?>" class="phpinfowp-cell-link">
                            <span class="phpinfowp-val-main"><?php echo esc_html($query_count !== null ? $query_count : '—'); ?></span>
                            <span class="phpinfowp-val-sub">queries</span>
                        </a>
                    </div>
                </div>

                <!-- Cell 3: Peak RAM & Bar -->
                <div class="phpinfowp-hud-cell">
                    <div class="phpinfowp-hud-cell-lbl">💾 Peak RAM</div>
                    <div class="phpinfowp-hud-cell-val">
                        <span class="phpinfowp-val-main"><?php echo esc_html($mem_display); ?></span>
                        <span class="phpinfowp-val-sub">/ <?php echo esc_html($status['memory']['limit_label']); ?> (<?php echo esc_html($mem_pct); ?>%)</span>
                    </div>
                    <div class="phpinfowp-mini-meter">
                        <div class="phpinfowp-mini-meter-fill <?php echo esc_attr($mem_fill_class); ?>" style="width: <?php echo max(4, min(100, $mem_pct)); ?>%;"></div>
                    </div>
                </div>

                <!-- Cell 4: OPcache -->
                <div class="phpinfowp-hud-cell">
                    <div class="phpinfowp-hud-cell-lbl">🚀 OPcache</div>
                    <div class="phpinfowp-hud-cell-val">
                        <?php if ($status['opcache'] && $status['opcache']['hit_rate'] !== null): ?>
                            <span class="phpinfowp-val-main" id="piwp-hud-opcache-val"><?php echo esc_html($status['opcache']['hit_rate']); ?>%</span>
                            <span class="phpinfowp-val-sub" id="piwp-hud-opcache-sub"><?php echo esc_html(number_format($status['opcache']['cached_scripts'])); ?> files</span>
                        <?php else: ?>
                            <span class="phpinfowp-val-sub" style="color:#64748b; font-weight:600;">Disabled</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 3. Infrastructure Sentinels (Object Cache & DB Autoload) -->
            <div class="phpinfowp-hud-sentinels">
                <div class="phpinfowp-sentinel-row">
                    <div class="phpinfowp-sentinel-left">
                        <span class="phpinfowp-sentinel-icon">⚡</span>
                        <span class="phpinfowp-sentinel-label">Object Cache:</span>
                    </div>
                    <div class="phpinfowp-sentinel-right">
                        <?php if (!empty($status['object_cache'])): ?>
                            <span class="phpinfowp-badge-good">✓ <?php echo esc_html(ucfirst($status['object_cache'])); ?> (Active)</span>
                        <?php else: ?>
                            <span class="phpinfowp-badge-dim">Disk / None (⚠️ Inactive)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="phpinfowp-sentinel-row">
                    <div class="phpinfowp-sentinel-left">
                        <span class="phpinfowp-sentinel-icon">🗃️</span>
                        <span class="phpinfowp-sentinel-label">DB Autoload:</span>
                    </div>
                    <div class="phpinfowp-sentinel-right">
                        <?php 
                        $autoload_str = $status['db']['autoload_size'] ?: 'Unknown';
                        $is_bloat = (stripos($autoload_str, 'MB') !== false && (float)$autoload_str > 1.0);
                        ?>
                        <?php if ($is_bloat): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-db-health')); ?>" class="phpinfowp-badge-warn">
                                ⚠️ <?php echo esc_html($autoload_str); ?> (Bloat Alert)
                            </a>
                        <?php else: ?>
                            <span class="phpinfowp-badge-good">✓ <?php echo esc_html($autoload_str); ?> (Clean)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 4. Active Incident Radar (if any issues exist) -->
            <?php if (!empty($status['issues'])): ?>
                <div class="phpinfowp-hud-issues">
                    <div class="phpinfowp-hud-issues-title">
                        <span>🚨 ACTIVE HEALTH NOTICES (<?php echo count($status['issues']); ?>)</span>
                    </div>
                    <?php foreach (array_slice($status['issues'], 0, 3) as $issue): ?>
                        <a href="<?php echo esc_url($issue['href']); ?>" class="phpinfowp-hud-issue-item level-<?php echo esc_attr($issue['level']); ?>">
                            <div class="phpinfowp-issue-left">
                                <span class="phpinfowp-issue-dot"></span>
                                <span class="phpinfowp-issue-label"><?php echo esc_html($issue['label']); ?></span>
                            </div>
                            <span class="phpinfowp-issue-action">Inspect →</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- 5. Instant Quick Actions Bar -->
            <div class="phpinfowp-hud-actions">
                <?php if (function_exists('opcache_reset') && strpos(ini_get('disable_functions'), 'opcache_reset') === false): ?>
                    <button type="button" 
                            id="piwp-bar-btn-flush" 
                            class="phpinfowp-hud-btn phpinfowp-hud-btn-primary" 
                            data-nonce="<?php echo esc_attr($nonce); ?>"
                            data-fallback="<?php echo esc_url($flush_direct_url); ?>"
                            title="Purge OPcache bytecode in memory without page reload">
                        <span class="piwp-btn-icon">⚡</span>
                        <span class="piwp-btn-text">Flush OPcache</span>
                    </button>
                <?php endif; ?>

                <button type="button" 
                        id="piwp-bar-btn-specs" 
                        class="phpinfowp-hud-btn phpinfowp-hud-btn-secondary" 
                        data-specs="<?php echo esc_attr($specs); ?>"
                        title="Copy formatted Markdown system specs for hosting/plugin support">
                    <span class="piwp-btn-icon">📋</span>
                    <span class="piwp-btn-text">Copy Spec</span>
                </button>

                <?php if ($status['is_pro']): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-report')); ?>" class="phpinfowp-hud-btn phpinfowp-hud-btn-primary" title="Export white-label client PDF audit">
                        <span class="piwp-btn-icon">📄</span>
                        <span class="piwp-btn-text">PDF Audit</span>
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-info')); ?>" class="phpinfowp-hud-btn phpinfowp-hud-btn-secondary" title="Open full Diagnostics Dashboard">
                        <span class="piwp-btn-icon">⚙️</span>
                        <span class="piwp-btn-text">Dashboard</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- 6. Footer: Sentinel Status or Pro Upgrade Teaser -->
            <div class="phpinfowp-hud-footer">
                <?php if ($status['is_pro']): ?>
                    <div class="phpinfowp-hud-pro-pill">
                        <span class="phpinfowp-pro-chip">
                            <span class="phpinfowp-pro-shield">🛡️</span>
                            <span>PRO SENTINEL ACTIVE</span>
                        </span>
                        <span class="phpinfowp-saas-tag">Zero SaaS Tracking</span>
                    </div>
                <?php else: ?>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener noreferrer" class="phpinfowp-hud-upsell-link">
                        <span class="piwp-upsell-tag"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                        <span class="piwp-upsell-copy"><?php _e('Unlock 1-Click Fixes &amp; Update Guard', 'phpinfo-wp'); ?></span>
                        <span class="piwp-upsell-arrow">&rarr;</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler for 1-Click OPcache Reset with instant feedback
     */
    public static function ajax_flush_opcache(): void {
        check_ajax_referer('phpinfowp_bar_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'phpinfo-wp')]);
        }

        if (function_exists('opcache_reset') && strpos(ini_get('disable_functions'), 'opcache_reset') === false) {
            $result = @opcache_reset();
            if ($result) {
                wp_send_json_success(['message' => __('OPcache successfully purged!', 'phpinfo-wp')]);
            }
        }

        wp_send_json_error(['message' => __('OPcache flush failed or restricted by host.', 'phpinfo-wp')]);
    }

    /**
     * Fallback standard POST handler for OPcache Flush
     */
    public static function handle_flush_opcache(): void {
        if (!current_user_can('manage_options') || !check_admin_referer('phpinfowp_flush_opcache')) {
            wp_die(__('Unauthorized.', 'phpinfo-wp'));
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        $referer = wp_get_referer();
        wp_safe_redirect($referer ?: admin_url());
        exit;
    }

    /**
     * Detect web server software
     */
    private static function detect_web_server(): string {
        $sw = $_SERVER['SERVER_SOFTWARE'] ?? '';
        if (stripos($sw, 'nginx') !== false) return 'Nginx';
        if (stripos($sw, 'litespeed') !== false) return 'LiteSpeed';
        if (stripos($sw, 'apache') !== false) return 'Apache';
        if (stripos($sw, 'caddy') !== false) return 'Caddy';
        return 'Web Server';
    }

    /**
     * Outputs scoped CSS & JS into head (Light / Clean White Theme)
     */
    public static function print_styles(): void {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) return;
        ?>
        <style id="phpinfowp-adminbar-css">
        @keyframes phpinfowp-pulse-glow {
            0%   { transform: scale(0.9); opacity: 0.7; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70%  { transform: scale(1.15); opacity: 1; box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.9); opacity: 0.7; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        @keyframes phpinfowp-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Top WP Admin Bar Indicator Pill */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator > .ab-item {
            display: flex !important;
            align-items: center !important;
            font-weight: 600 !important;
            letter-spacing: -0.01em !important;
        }

        /* Reset WordPress Admin Bar Dropdown Containers */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .ab-sub-wrapper {
            min-width: 400px !important;
            width: 400px !important;
            max-width: 95vw !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 8px 0 0 0 !important;
            border: none !important;
            margin: 0 !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator ul.ab-submenu {
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
            min-width: 400px !important;
            width: 400px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator li.phpinfowp-hud-container-node {
            height: auto !important;
            background: transparent !important;
            line-height: normal !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator li.phpinfowp-hud-container-node > .ab-item {
            height: auto !important;
            padding: 0 !important;
            background: transparent !important;
            color: inherit !important;
            cursor: default !important;
            white-space: normal !important;
            display: block !important;
            width: 100% !important;
        }

        /* Main Micro-Cockpit Card HUD */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-adminbar-hud {
            width: 400px !important;
            max-width: 95vw !important;
            background: #ffffff !important;
            color: #0f172a !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            box-shadow: 0 12px 32px -4px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0, 0, 0, 0.04) !important;
            padding: 14px 16px !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important;
            font-size: 12px !important;
            line-height: 1.4 !important;
            text-align: left !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            box-sizing: border-box !important;
        }

        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-adminbar-hud * {
            box-sizing: border-box !important;
            font-family: inherit !important;
            user-select: none !important;
            -webkit-user-select: none !important;
        }

        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-adminbar-hud *::selection {
            background: transparent !important;
            color: inherit !important;
        }

        /* Header */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-header {
            display: flex !important;
            flex-direction: column !important;
            gap: 6px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-header-top {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 10px !important;
            flex-wrap: wrap !important;
            margin-bottom: 8px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-telem-pair {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-telem-div {
            color: #cbd5e1 !important;
            font-size: 14px !important;
            font-weight: 300 !important;
            line-height: 1 !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-brand-text {
            font-size: 11px !important;
            font-weight: 800 !important;
            letter-spacing: 0.05em !important;
            color: #64748b !important;
            text-transform: uppercase !important;
            white-space: nowrap !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-dot {
            width: 8px !important;
            height: 8px !important;
            border-radius: 50% !important;
            background: #10b981 !important;
            flex-shrink: 0 !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-dot.warning { background: #f59e0b !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-dot.critical { background: #ef4444 !important; animation: phpinfowp-pulse-glow 1.6s infinite ease-in-out !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-grade-badge {
            display: inline-flex !important;
            align-items: center !important;
            padding: 2px 8px !important;
            border-radius: 6px !important;
            font-size: 11px !important;
            font-weight: 800 !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            transition: all 0.15s ease !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-grade-badge.grade-a { background: #dcfce7 !important; color: #15803d !important; border: 1px solid #bbf7d0 !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-grade-badge.grade-b { background: #fef9c3 !important; color: #854d0e !important; border: 1px solid #fde047 !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-grade-badge.grade-c { background: #ffedd5 !important; color: #c2410c !important; border: 1px solid #fed7aa !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-grade-badge.grade-d,
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-grade-badge.grade-f { background: #fee2e2 !important; color: #b91c1c !important; border: 1px solid #fecaca !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-grade-badge.grade-pro-only {
            background: #eef2ff !important;
            color: #6366f1 !important;
            border: 1px solid #c7d2fe !important;
            font-size: 10px !important;
            font-weight: 800 !important;
            letter-spacing: 0.04em !important;
            padding: 2px 7px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-grade-badge.grade-pro-only:hover {
            background: #e0e7ff !important;
            color: #4f46e5 !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-grade-badge:hover { filter: brightness(0.95) !important; }

        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-env-pills {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-env-pill {
            display: inline-flex !important;
            align-items: center !important;
            padding: 2px 8px !important;
            border-radius: 6px !important;
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            font-size: 10.5px !important;
            color: #475569 !important;
            font-weight: 600 !important;
            white-space: nowrap !important;
        }

        /* 2x2 Telemetry Grid */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 8px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-cell {
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 8px 10px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-cell-lbl {
            font-size: 10px !important;
            font-weight: 700 !important;
            color: #64748b !important;
            text-transform: uppercase !important;
            letter-spacing: 0.03em !important;
            margin-bottom: 3px !important;
            white-space: nowrap !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-cell-val {
            display: flex !important;
            align-items: baseline !important;
            gap: 4px !important;
            font-size: 12px !important;
            color: #0f172a !important;
            white-space: nowrap !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-val-main {
            font-size: 13px !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            white-space: nowrap !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-cell-link {
            display: inline-flex !important;
            align-items: baseline !important;
            gap: 4px !important;
            text-decoration: none !important;
            color: inherit !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-cell-link:hover .phpinfowp-val-main { color: #6366f1 !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-val-sub {
            font-size: 10.5px !important;
            color: #64748b !important;
            font-weight: 500 !important;
            white-space: nowrap !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-speed-pill {
            font-size: 9px !important;
            padding: 1px 5px !important;
            border-radius: 4px !important;
            font-weight: 700 !important;
            margin-left: auto !important;
            white-space: nowrap !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-speed-pill.speed-fast { background: #dcfce7 !important; color: #15803d !important; border: 1px solid #bbf7d0 !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-speed-pill.speed-avg  { background: #fef3c7 !important; color: #b45309 !important; border: 1px solid #fde68a !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-speed-pill.speed-slow { background: #fee2e2 !important; color: #b91c1c !important; border: 1px solid #fecaca !important; }

        /* Mini Meter */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-mini-meter {
            height: 4px !important;
            background: #e2e8f0 !important;
            border-radius: 2px !important;
            overflow: hidden !important;
            margin-top: 5px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-mini-meter-fill {
            height: 100% !important;
            border-radius: 2px !important;
            transition: width 0.3s ease !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-mini-meter-fill.fill-good   { background: #10b981 !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-mini-meter-fill.fill-warn   { background: #f59e0b !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-mini-meter-fill.fill-danger { background: #ef4444 !important; }

        /* Sentinels */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-sentinels {
            display: flex !important;
            flex-direction: column !important;
            gap: 5px !important;
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 7px 10px !important;
            font-size: 11.5px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-sentinel-row {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 8px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-sentinel-left {
            display: flex !important;
            align-items: center !important;
            gap: 5px !important;
            color: #475569 !important;
            font-weight: 600 !important;
            white-space: nowrap !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-sentinel-right {
            text-align: right !important;
            white-space: nowrap !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-badge-good { color: #15803d !important; font-weight: 700 !important; font-size: 11px !important; background: #dcfce7 !important; padding: 1px 7px !important; border-radius: 5px !important; border: 1px solid #bbf7d0 !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-badge-dim  { color: #64748b !important; font-size: 11px !important; font-weight: 600 !important; background: #ffffff !important; padding: 1px 7px !important; border-radius: 5px !important; border: 1px solid #e2e8f0 !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-badge-warn { color: #b45309 !important; font-weight: 700 !important; font-size: 11px !important; background: #fef3c7 !important; padding: 1px 7px !important; border-radius: 5px !important; border: 1px solid #fde68a !important; text-decoration: none !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-badge-warn:hover { text-decoration: underline !important; }

        /* Issues */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-issues {
            padding: 8px 10px !important;
            border: 1px solid #ffedd5 !important;
            background: #fff7ed !important;
            border-radius: 8px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-issues-title {
            font-size: 10px !important;
            font-weight: 800 !important;
            color: #c2410c !important;
            letter-spacing: 0.05em !important;
            margin-bottom: 5px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-issue-item {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 5px 8px !important;
            border-radius: 6px !important;
            text-decoration: none !important;
            margin-bottom: 3px !important;
            background: #ffffff !important;
            border: 1px solid #fed7aa !important;
            transition: all 0.15s ease !important;
            gap: 8px !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-issue-item:hover { border-color: #fb923c !important; background: #fffaf5 !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-issue-left {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-issue-dot { width: 6px !important; height: 6px !important; border-radius: 50% !important; background: #f59e0b !important; flex-shrink: 0 !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .level-critical .phpinfowp-issue-dot { background: #ef4444 !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-issue-label { font-size: 11px !important; font-weight: 600 !important; color: #0f172a !important; white-space: nowrap !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-issue-action { font-size: 11px !important; color: #6366f1 !important; font-weight: 700 !important; white-space: nowrap !important; margin-left: auto !important; }

        /* Actions Bar */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-actions {
            display: flex !important;
            gap: 8px !important;
            width: 100% !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-btn {
            flex: 1 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            height: 32px !important;
            line-height: 30px !important;
            padding: 0 10px !important;
            border-radius: 6px !important;
            font-size: 11.5px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            text-decoration: none !important;
            transition: all 0.15s ease !important;
            white-space: nowrap !important;
            margin: 0 !important;
        }
        /* Secondary Button Style */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-btn-secondary {
            background: #ffffff !important;
            color: #334155 !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-btn-secondary:hover {
            background: #f8fafc !important;
            border-color: #94a3b8 !important;
            color: #0f172a !important;
            transform: translateY(-1px) !important;
        }
        /* Primary Button Style */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-btn-primary {
            background: #6366f1 !important;
            color: #ffffff !important;
            border: 1px solid #4f46e5 !important;
            box-shadow: 0 1px 2px rgba(99, 102, 241, 0.25) !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-btn-primary:hover {
            background: #4f46e5 !important;
            color: #ffffff !important;
            transform: translateY(-1px) !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-btn:active { transform: translateY(0) !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-btn.is-loading .piwp-btn-icon {
            display: inline-block !important;
            animation: phpinfowp-spin 0.8s linear infinite !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-btn.is-success {
            background: #ecfdf5 !important;
            border-color: #10b981 !important;
            color: #059669 !important;
        }

        /* Footer */
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-footer {
            padding-top: 8px !important;
            border-top: 1px solid #f1f5f9 !important;
            font-size: 11px !important;
            width: 100% !important;
            user-select: none !important;
            -webkit-user-select: none !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-pro-pill {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            font-size: 10.5px !important;
            font-weight: 500 !important;
            width: 100% !important;
            user-select: none !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-pro-chip {
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            padding: 2px 7px !important;
            border-radius: 999px !important;
            background: #eef2ff !important;
            border: 1px solid #e0e7ff !important;
            color: #4f46e5 !important;
            font-size: 10px !important;
            font-weight: 700 !important;
            letter-spacing: 0.03em !important;
            user-select: none !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-saas-tag {
            color: #94a3b8 !important;
            user-select: none !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-upsell-link {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 8px !important;
            color: #6366f1 !important;
            text-decoration: none !important;
            font-weight: 600 !important;
            width: 100% !important;
            transition: all 0.15s ease !important;
            padding: 2px 0 !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-upsell-link:hover { color: #4338ca !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .phpinfowp-hud-upsell-link:hover .piwp-upsell-arrow { transform: translateX(2px) !important; }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .piwp-upsell-tag {
            background: #6366f1 !important;
            color: #fff !important;
            padding: 2px 6px !important;
            border-radius: 4px !important;
            font-size: 9.5px !important;
            font-weight: 800 !important;
            letter-spacing: 0.04em !important;
            flex-shrink: 0 !important;
            line-height: 1.2 !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .piwp-upsell-copy {
            font-size: 11px !important;
            line-height: 1.3 !important;
            flex: 1 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        #wpadminbar #wp-admin-bar-phpinfowp-indicator .piwp-upsell-arrow {
            font-size: 13px !important;
            font-weight: 700 !important;
            flex-shrink: 0 !important;
            transition: transform 0.15s ease !important;
            line-height: 1 !important;
        }
        </style>

        <script id="phpinfowp-adminbar-js">
        document.addEventListener('DOMContentLoaded', function() {
            // 1-Click Copy Specs to Clipboard
            var specBtn = document.getElementById('piwp-bar-btn-specs');
            if (specBtn) {
                specBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var specs = this.getAttribute('data-specs');
                    var origText = this.innerHTML;
                    var btn = this;

                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(specs).then(function() {
                            showSuccess();
                        }).catch(function() {
                            fallbackCopy(specs);
                        });
                    } else {
                        fallbackCopy(specs);
                    }

                    function fallbackCopy(text) {
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.focus();
                        ta.select();
                        try {
                            document.execCommand('copy');
                            showSuccess();
                        } catch(err) {
                            alert('Copy failed, please copy manually.');
                        }
                        document.body.removeChild(ta);
                    }

                    function showSuccess() {
                        btn.classList.add('is-success');
                        btn.innerHTML = '<span>✓</span> <span>Copied Spec!</span>';
                        setTimeout(function() {
                            btn.classList.remove('is-success');
                            btn.innerHTML = origText;
                        }, 2200);
                    }
                });
            }

            // 1-Click AJAX Flush OPcache
            var flushBtn = document.getElementById('piwp-bar-btn-flush');
            if (flushBtn) {
                flushBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var btn = this;
                    var nonce = this.getAttribute('data-nonce');
                    var fallback = this.getAttribute('data-fallback');
                    var origText = this.innerHTML;

                    btn.classList.add('is-loading');
                    btn.innerHTML = '<span class="piwp-btn-icon">⌛</span> <span>Purging...</span>';

                    if (typeof ajaxurl === 'undefined') {
                        window.location.href = fallback;
                        return;
                    }

                    var formData = new FormData();
                    formData.append('action', 'phpinfowp_bar_flush_opcache');
                    formData.append('nonce', nonce);

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        btn.classList.remove('is-loading');
                        if (data && data.success) {
                            btn.classList.add('is-success');
                            btn.innerHTML = '<span>✓</span> <span>Purged!</span>';
                            var opcacheVal = document.getElementById('piwp-hud-opcache-val');
                            var opcacheSub = document.getElementById('piwp-hud-opcache-sub');
                            if (opcacheVal) opcacheVal.innerText = '100%';
                            if (opcacheSub) opcacheSub.innerText = '0 files';
                            setTimeout(function() {
                                btn.classList.remove('is-success');
                                btn.innerHTML = origText;
                            }, 2500);
                        } else {
                            window.location.href = fallback;
                        }
                    })
                    .catch(function() {
                        window.location.href = fallback;
                    });
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Builds the unified status payload from all data sources.
     */
    public static function status(): array {
        $is_pro  = Phpinfo_WP_License::is_valid();
        $grader  = Phpinfo_WP_Config_Grader::summary();
        $eol     = Phpinfo_WP_EOL::status();
        $issues  = [];

        // --- EOL (free) ---
        if ($eol['status'] === 'eol') {
            $issues[] = [
                'level'   => 'critical',
                'label'   => 'PHP ' . $eol['minor'] . ' EOL',
                'detail'  => 'EOL ' . $eol['eol'] . ' — past',
                'href'    => admin_url('admin.php?page=piwp-eol'),
                'sort'    => 0,
            ];
        } elseif ($eol['status'] === 'warning') {
            $issues[] = [
                'level'   => 'warning',
                'label'   => 'EOL in ' . $eol['days'] . 'd',
                'detail'  => 'PHP ' . $eol['minor'] . ' EOL ' . $eol['eol'],
                'href'    => admin_url('admin.php?page=piwp-eol'),
                'sort'    => 10,
            ];
        }

        // --- Config Grader (Actionable Issues) ---
        if ($grader && ($grader['fails'] ?? 0) > 0) {
            $fails = (int) $grader['fails'];
            $issues[] = [
                'level'  => $fails >= 3 ? 'critical' : 'warning',
                'label'  => $fails . ' actionable issue' . ($fails === 1 ? '' : 's'),
                'detail' => 'Grade ' . ($grader['grade_effective'] ?? ($grader['grade'] ?? 'F')) . (!empty($grader['is_capped']) ? '*' : '') . ' (' . $fails . ' failing checks)',
                'href'   => admin_url('admin.php?page=piwp-config-grader&tab=issues'),
                'sort'   => 12,
            ];
        }

        // --- Config Grader (Consistency Checks) ---
        if ($grader && ($grader['cross'] ?? 0) > 0) {
            $cross = (int) $grader['cross'];
            $issues[] = [
                'level'  => 'warning',
                'label'  => $cross . ' consistency check' . ($cross === 1 ? '' : 's'),
                'detail' => 'Contradictory directives detected',
                'href'   => admin_url('admin.php?page=piwp-config-grader&tab=consistency'),
                'sort'   => 13,
            ];
        }

        // --- Error Log Count ---
        $err_count = Phpinfo_WP_Error_Log::today_count();
        if ($err_count > 0) {
            $issues[] = [
                'level'  => $err_count >= 100 ? 'critical' : 'warning',
                'label'  => $err_count . ' error' . ($err_count === 1 ? '' : 's') . ' today',
                'detail' => $err_count . ' PHP errors logged',
                'href'   => admin_url('admin.php?page=piwp-error-log'),
                'sort'   => 30,
            ];
        }

        // --- Pro Signals ---
        $opcache = null;
        if ($is_pro) {
            // SSL
            $site_host = parse_url(get_site_url(), PHP_URL_HOST) ?: '';
            $hosts     = array_unique(array_filter(array_merge(
                [$site_host],
                Phpinfo_WP_SSL::get_extra_domains()
            )));
            foreach ($hosts as $h) {
                $cached = Phpinfo_WP_SSL::get_result_for($h);
                if (!$cached || !empty($cached['error'])) continue;
                $d = (int) ($cached['days'] ?? 999);
                if ($d < 0) {
                    $issues[] = [
                        'level' => 'critical', 'label' => 'SSL expired (' . $h . ')',
                        'detail' => $h . ' expired ' . abs($d) . 'd ago',
                        'href' => admin_url('admin.php?page=piwp-ssl'),
                        'sort' => 1,
                    ];
                } elseif ($d < 7) {
                    $issues[] = [
                        'level' => 'critical', 'label' => 'SSL ' . $d . 'd (' . $h . ')',
                        'detail' => $h . ' expires in ' . $d . ' days',
                        'href' => admin_url('admin.php?page=piwp-ssl'),
                        'sort' => 2,
                    ];
                } elseif ($d < 30) {
                    $issues[] = [
                        'level' => 'warning', 'label' => 'SSL ' . $d . 'd',
                        'detail' => $h . ' expires in ' . $d . ' days',
                        'href' => admin_url('admin.php?page=piwp-ssl'),
                        'sort' => 15,
                    ];
                }
            }

            // Cron health
            $cron = Phpinfo_WP_Cron_Monitor::summary();
            if ($cron) {
                if (!empty($cron['disabled'])) {
                    $issues[] = [
                        'level' => 'critical', 'label' => 'WP-Cron disabled',
                        'detail' => 'DISABLE_WP_CRON is true',
                        'href' => admin_url('admin.php?page=piwp-cron'),
                        'sort' => 3,
                    ];
                } elseif (!empty($cron['overdue'])) {
                    $issues[] = [
                        'level' => 'warning', 'label' => $cron['overdue'] . ' cron overdue',
                        'detail' => $cron['overdue'] . ' scheduled events past due',
                        'href' => admin_url('admin.php?page=piwp-cron'),
                        'sort' => 20,
                    ];
                }
            }

            // Security headers
            $hdr_cache = get_transient('phpinfowp_sec_headers');
            if (is_array($hdr_cache) && isset($hdr_cache['grade'])) {
                $g = $hdr_cache['grade'];
                if (in_array($g, ['F', 'D'], true)) {
                    $issues[] = [
                        'level' => 'warning', 'label' => 'Security Headers ' . $g,
                        'detail' => 'Score: ' . ($hdr_cache['score'] ?? 0) . '/100',
                        'href' => admin_url('admin.php?page=piwp-security-headers'),
                        'sort' => 22,
                    ];
                }
            }

            // OPcache
            $oc = Phpinfo_WP_OPcache::status();
            if ($oc && $oc['enabled'] && $oc['hit_rate'] !== null) {
                $baseline = (float) get_option(self::OPT_OPCACHE_BASELINE, 0);
                if ($baseline === 0.0 || $oc['hit_rate'] > $baseline) {
                    update_option(self::OPT_OPCACHE_BASELINE, $oc['hit_rate'], false);
                    $baseline = $oc['hit_rate'];
                }
                $delta = $oc['hit_rate'] - $baseline;
                $opcache = [
                    'hit_rate'       => $oc['hit_rate'],
                    'baseline'       => $baseline,
                    'delta'          => $delta,
                    'cached_scripts' => $oc['cached_scripts'] ?? 0,
                ];

                if (!empty($oc['full'])) {
                    $issues[] = [
                        'level' => 'critical', 'label' => 'OPcache full',
                        'detail' => 'Cache is full — increase memory',
                        'href' => admin_url('admin.php?page=piwp-opcache'),
                        'sort' => 4,
                    ];
                } elseif ($delta < -15) {
                    $issues[] = [
                        'level' => 'warning', 'label' => 'OPcache ↓ ' . round($delta) . '%',
                        'detail' => 'Hit rate ' . $oc['hit_rate'] . '% vs baseline',
                        'href' => admin_url('admin.php?page=piwp-opcache'),
                        'sort' => 28,
                    ];
                }
            }

            // Update Guard
            if (class_exists('Phpinfo_WP_Update_Guard')) {
                $ug_res = Phpinfo_WP_Update_Guard::get_result();
                if ($ug_res && !empty($ug_res['risky_count'])) {
                    $issues[] = [
                        'level'  => 'critical',
                        'label'  => $ug_res['risky_count'] . ' risky update' . ($ug_res['risky_count'] === 1 ? '' : 's'),
                        'detail' => 'Changelog / compatibility risks detected',
                        'href'   => admin_url('admin.php?page=piwp-update-audit'),
                        'sort'   => 5,
                    ];
                }
            }
        }

        // Troubleshooting Mode
        if (class_exists('Phpinfo_WP_Safemode') && Phpinfo_WP_Safemode::is_active()) {
            $issues[] = [
                'level'  => 'warning',
                'label'  => 'Troubleshooting Active',
                'detail' => 'Isolated admin debug session',
                'href'   => admin_url('admin.php?page=piwp-troubleshoot'),
                'sort'   => -1,
            ];
        }

        // Database info & Autoload (12h cached)
        global $wpdb;
        $db_raw = $wpdb->db_version();
        preg_match('/^(\d+\.\d+)/', (string) $db_raw, $m);
        $db_minor = $m ? $m[1] : (string) $db_raw;
        $autoload_cached = get_transient('phpinfowp_autoload_summary');
        if ($autoload_cached === false) {
            $autoload_bytes = (int) $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'");
            $autoload_cached = $autoload_bytes > 0 ? size_format($autoload_bytes) : '';
            set_transient('phpinfowp_autoload_summary', $autoload_cached, 12 * HOUR_IN_SECONDS);
        }
        $db_info = [
            'engine'        => (stripos($wpdb->get_var('SELECT VERSION()'), 'mariadb') !== false) ? 'MariaDB' : 'MySQL',
            'version'       => $db_raw,
            'minor'         => $db_minor,
            'autoload_size' => $autoload_cached,
        ];

        $object_cache = class_exists('Phpinfo_WP_Object_Cache') ? Phpinfo_WP_Object_Cache::active_type() : null;

        // Sort issues
        usort($issues, function($a, $b) {
            $rank = ['critical' => 0, 'warning' => 1];
            $ra = $rank[$a['level']] ?? 9;
            $rb = $rank[$b['level']] ?? 9;
            if ($ra !== $rb) return $ra <=> $rb;
            return ($a['sort'] ?? 99) <=> ($b['sort'] ?? 99);
        });

        $crit_count = 0;
        $warn_count = 0;
        foreach ($issues as $i) {
            if ($i['level'] === 'critical') $crit_count++;
            elseif ($i['level'] === 'warning') $warn_count++;
        }

        $overall = $crit_count > 0 ? 'critical' : ($warn_count > 0 ? 'warning' : 'good');

        $site = self::site_telemetry();

        $grade_eff  = $grader['grade_effective'] ?? ($grader['grade'] ?? '?');
        $score_eff  = $grader['score_effective'] ?? ($grader['score'] ?? 0);
        $is_capped  = !empty($grader['is_capped']);
        $grade_raw  = $grader['grade'] ?? '?';
        $score_raw  = $grader['score'] ?? 0;
        $grade_ctl  = $grader['grade_controllable'] ?? $grade_raw;
        $score_ctl  = $grader['score_controllable'] ?? $score_raw;

        return [
            'is_pro'             => $is_pro,
            'grade'              => $grade_eff,
            'score'              => $score_eff,
            'grade_raw'          => $grade_raw,
            'score_raw'          => $score_raw,
            'grade_controllable' => $grade_ctl,
            'score_controllable' => $score_ctl,
            'is_capped'          => $is_capped,
            'locked_count'       => $grader['locked_count'] ?? 0,
            'cap_reason'         => $grader['cap_reason'] ?? '',
            'eol'                => $eol,
            'issues'             => $issues,
            'crit_count'         => $crit_count,
            'warn_count'         => $warn_count,
            'overall'            => $overall,
            'memory'             => self::memory_payload(),
            'opcache'            => $opcache,
            'db'                 => $db_info,
            'object_cache'       => $object_cache,
            'streak_days'        => self::streak_days(),
            'site_grade'         => $grade_ctl,
            'site_score'         => $score_ctl,
            'wp_health_grade'    => $site['grade'],
            'wp_health_score'    => $site['score'],
        ];
    }

    /**
     * Compute a simple WordPress Site Health grade from WP-native signals.
     * No DB overhead — all checks use already-cached WP globals / options.
     *
     * Scoring buckets (out of 100):
     *  - WP_DEBUG off in non-local env   : 25 pts
     *  - No WP core update pending        : 20 pts
     *  - Plugin updates <= 0              : 20 pts  (lose 5 per update, floor 0)
     *  - wp-cron not disabled (or offload): 15 pts
     *  - Active plugin count <= 20        : 10 pts  (lose 5 if > 30)
     *  - Not a debug-log leak             : 10 pts
     */
    private static function site_telemetry(): array {
        $score = 100;

        // 1. WP_DEBUG (-25 if on and not running locally)
        $is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
                 || (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local');
        if (defined('WP_DEBUG') && WP_DEBUG && !$is_local) {
            $score -= 25;
        }

        // 2. WP core update available (-20)
        $update_core = get_site_transient('update_core');
        if ($update_core && !empty($update_core->updates)) {
            foreach ($update_core->updates as $u) {
                if (isset($u->response) && $u->response === 'upgrade') {
                    $score -= 20;
                    break;
                }
            }
        }

        // 3. Plugin updates pending (-5 per pending update, capped at -20)
        $update_plugins = get_site_transient('update_plugins');
        $plugin_updates = (!empty($update_plugins->response)) ? count($update_plugins->response) : 0;
        $score -= min(20, $plugin_updates * 5);

        // 4. WP_CRON disabled without external cron (-15)
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        // Check for known cron-offload plugins (WP Crontrol, Action Scheduler, EasyCron)
        $cron_offload_plugins = ['wp-crontrol/wp-crontrol.php', 'action-scheduler/action-scheduler.php'];
        $active = (array) get_option('active_plugins', []);
        $has_cron_offload = (bool) array_intersect($cron_offload_plugins, $active);
        if ($cron_disabled && !$has_cron_offload) {
            $score -= 15;
        }

        // 5. Plugin count > 30 (-10) or > 20 (-5)
        $plugin_count = count($active);
        if ($plugin_count > 30) $score -= 10;
        elseif ($plugin_count > 20) $score -= 5;

        // 6. WP_DEBUG_LOG leaking to web (-10)
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
            $score -= 10;
        }

        $score = max(0, min(100, $score));

        // Convert to letter grade (same scale as config grader)
        if ($score >= 95) $grade = 'A+';
        elseif ($score >= 90) $grade = 'A';
        elseif ($score >= 80) $grade = 'B';
        elseif ($score >= 70) $grade = 'C';
        elseif ($score >= 60) $grade = 'D';
        else $grade = 'F';

        return ['score' => $score, 'grade' => $grade];
    }

    private static function pill_title(array $s): string {
        $color = self::color_for($s['overall']);

        if ($s['overall'] === 'critical') {
            $label = $s['crit_count'] > 1
                ? $s['crit_count'] . ' Issues'
                : $s['issues'][0]['label'];
            $extra = $s['warn_count'] > 0 ? ' +' . $s['warn_count'] : '';

            $dot = '<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#ef4444;margin-right:6px;vertical-align:middle;animation:phpinfowp-pulse-glow 1.6s infinite ease-in-out;"></span>';
            return $dot . '<span style="color:' . $color . ';font-weight:700">' . esc_html($label . $extra) . '</span>';
        }

        $grade_display = esc_html($s['grade']) . (!empty($s['is_capped']) ? '*' : '');

        if ($s['overall'] === 'warning') {
            $lead = $s['issues'][0]['label'];
            $extra = $s['warn_count'] > 1 ? ' (+' . ($s['warn_count'] - 1) . ')' : '';
            return '<span style="color:' . $color . ';font-weight:600">'
                 . esc_html($grade_display . ' · ' . $lead . $extra)
                 . '</span>';
        }

        return '<span style="color:' . $color . ';font-weight:600">'
             . esc_html($grade_display . ' · PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) . ' ✓'
             . '</span>';
    }

    private static function memory_payload(): array {
        $key   = 'phpinfowp_mem_peak_' . date('Ymd');
        $peak  = (int) get_transient($key);
        $limit_raw = ini_get('memory_limit');
        $limit_bytes = self::parse_bytes($limit_raw);
        $pct = ($peak > 0 && $limit_bytes > 0) ? (int) round($peak / $limit_bytes * 100) : 0;
        return [
            'peak_bytes'  => $peak,
            'limit_bytes' => $limit_bytes,
            'limit_label' => $limit_raw,
            'pct'         => $pct,
        ];
    }

    public static function record_memory_peak(): void {
        $peak = memory_get_peak_usage(true);
        $key  = 'phpinfowp_mem_peak_' . date('Ymd');
        $cur  = (int) get_transient($key);
        if ($peak > $cur) {
            set_transient($key, $peak, 36 * HOUR_IN_SECONDS);
        }
    }

    private static function streak_days(): int {
        $ts = (int) get_option(self::OPT_LAST_UNHEALTHY, 0);
        if ($ts <= 0) {
            $install = (int) get_option('phpinfowp_install_ts', 0);
            if ($install <= 0) {
                update_option('phpinfowp_install_ts', time(), false);
                return 0;
            }
            $ts = $install;
        }
        return max(0, (int) floor((time() - $ts) / DAY_IN_SECONDS));
    }

    private static function persist_streak_state(array $status): void {
        if ($status['overall'] === 'good') return;
        $existing = (int) get_option(self::OPT_LAST_UNHEALTHY, 0);
        if (time() - $existing > DAY_IN_SECONDS) {
            update_option(self::OPT_LAST_UNHEALTHY, time(), false);
        }
    }

    private static function color_for(string $level): string {
        switch ($level) {
            case 'critical':
                return self::COLOR_BAD;
            case 'warning':
                return self::COLOR_WARN;
            default:
                return self::COLOR_GOOD;
        }
    }

    private static function primary_href(array $s): string {
        if ($s['issues']) return $s['issues'][0]['href'];
        return admin_url('admin.php?page=piwp-config-grader');
    }

    private static function parse_bytes(string $val): int {
        $val = trim($val);
        if ($val === '' || $val === '-1') return 0;
        $last = strtolower($val[strlen($val) - 1]);
        $num  = (int) $val;
        switch ($last) {
            case 'g':
                return $num * GB_IN_BYTES;
            case 'm':
                return $num * MB_IN_BYTES;
            case 'k':
                return $num * KB_IN_BYTES;
            default:
                return $num;
        }
    }
}
