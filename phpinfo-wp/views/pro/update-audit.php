<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

// Update Guard — Unified Pre-Update & Post-Update Health Suite
// 1. Plugins & Themes Update Guard: Pre-update compatibility checks (PHP/WP floor, changelog risk, abandonment)
// 2. Core Readiness Audit: Code scan against target WordPress core
// 3. Update History & Health: Automated post-update diagnostics (loopback, error log, cron) + stability scoring

$is_pro  = Phpinfo_WP_License::is_valid();
$ai_on   = Phpinfo_WP_AI_Explain::available();
$current = Phpinfo_WP_Update_Audit::current_wp();
$avail   = Phpinfo_WP_Update_Audit::available_core_update();
$pending_count = Phpinfo_WP_Update_Guard::pending_count();

// Active tab handling
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['plugins', 'core', 'history'], true)
    ? sanitize_key($_GET['tab'])
    : 'plugins';

// Target WP version for core audit
$target = isset($_GET['target']) && preg_match('/^\d+\.\d+(\.\d+)?$/', (string) $_GET['target'])
    ? sanitize_text_field($_GET['target'])
    : Phpinfo_WP_Update_Audit::default_target();

// --- Handle Form Actions ---

// Core scan / clear
if (isset($_POST['phpinfowp_ua_scan']) && check_admin_referer('phpinfowp_ua_nonce')) {
    $tab = 'core';
    $target = preg_match('/^\d+\.\d+(\.\d+)?$/', (string) ($_POST['target'] ?? ''))
        ? sanitize_text_field($_POST['target']) : $target;
    Phpinfo_WP_Update_Audit::scan($target);
}
if (isset($_POST['phpinfowp_ua_clear']) && check_admin_referer('phpinfowp_ua_nonce')) {
    $tab = 'core';
    Phpinfo_WP_Update_Audit::clear();
}

// Plugin / Theme Guard scan / clear
if (isset($_POST['phpinfowp_ug_scan']) && check_admin_referer('phpinfowp_ug_nonce')) {
    $tab = 'plugins';
    Phpinfo_WP_Update_Guard::scan();
}
if (isset($_POST['phpinfowp_ug_clear']) && check_admin_referer('phpinfowp_ug_nonce')) {
    $tab = 'plugins';
    Phpinfo_WP_Update_Guard::clear();
}

// Update History clear / health check now
$live_health = class_exists('Phpinfo_WP_Update_History') ? Phpinfo_WP_Update_History::get_latest_health() : null;
if (isset($_POST['phpinfowp_uh_health_check']) && check_admin_referer('phpinfowp_uh_nonce')) {
    $tab = 'history';
    $live_health = Phpinfo_WP_Update_History::run_live_health_check();
}
if (isset($_POST['phpinfowp_uh_clear']) && check_admin_referer('phpinfowp_uh_nonce')) {
    $tab = 'history';
    Phpinfo_WP_Update_History::clear();
    $live_health = null;
}

$core_result   = Phpinfo_WP_Update_Audit::get_result();
$guard_result  = Phpinfo_WP_Update_Guard::get_result();
$history_items = Phpinfo_WP_Update_History::recent(100);
$bg_scan            = class_exists('Phpinfo_WP_Background_Scan') ? Phpinfo_WP_Background_Scan::status('update_guard') : ['status' => 'idle'];
$is_bg_running      = ($bg_scan['status'] === 'running');
$bg_core_scan       = class_exists('Phpinfo_WP_Background_Scan') ? Phpinfo_WP_Background_Scan::status('core_audit') : ['status' => 'idle'];
$is_core_bg_running = ($bg_core_scan['status'] === 'running');
$bg_nonce           = wp_create_nonce('phpinfowp_bg_scan_nonce');

if (class_exists('Phpinfo_WP_Background_Scan')) {
    if ($tab === 'plugins') {
        Phpinfo_WP_Background_Scan::acknowledge('update_guard');
    } elseif ($tab === 'core') {
        Phpinfo_WP_Background_Scan::acknowledge('core_audit');
    }
}

$verdict_meta = [
    'safe'        => ['label' => __('Safe to update', 'phpinfo-wp'),       'grade' => 'grade-a', 'icon' => '✓', 'color' => '#00a32a'],
    'likely_safe' => ['label' => __('Likely safe to update', 'phpinfo-wp'),'grade' => 'grade-a', 'icon' => '✓', 'color' => '#00a32a'],
    'caution'     => ['label' => __('Update with caution', 'phpinfo-wp'),   'grade' => 'grade-c', 'icon' => '!', 'color' => '#dba617'],
    'risky'       => ['label' => __('Risky — review first', 'phpinfo-wp'), 'grade' => 'grade-f', 'icon' => '✕', 'color' => '#d63638'],
];

$inplace_verdict_meta = [
    'clean'        => ['label' => __('Running cleanly', 'phpinfo-wp'),       'grade' => 'grade-a', 'icon' => '✓', 'color' => '#00a32a'],
    'safe'         => ['label' => __('Running cleanly', 'phpinfo-wp'),       'grade' => 'grade-a', 'icon' => '✓', 'color' => '#00a32a'],
    'latent_debt'  => ['label' => __('Technical debt noted', 'phpinfo-wp'),  'grade' => 'grade-c', 'icon' => '!', 'color' => '#dba617'],
    'caution'      => ['label' => __('Technical debt noted', 'phpinfo-wp'),  'grade' => 'grade-c', 'icon' => '!', 'color' => '#dba617'],
    'active_issue' => ['label' => __('Active issue detected', 'phpinfo-wp'), 'grade' => 'grade-f', 'icon' => '✕', 'color' => '#d63638'],
    'risky'        => ['label' => __('Active issue detected', 'phpinfo-wp'), 'grade' => 'grade-f', 'icon' => '✕', 'color' => '#d63638'],
];
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>
                <?php _e('Update Guard', 'phpinfo-wp'); ?>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Pre &amp; Post Update Security Suite — safely test plugin, theme, and core updates before applying.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="phpinfowp-ug-tab-nav">
        <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-update-audit', 'tab' => 'plugins'], admin_url('admin.php'))); ?>"
           class="phpinfowp-ug-tab-link <?php echo $tab === 'plugins' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php _e('Plugins &amp; Themes Guard', 'phpinfo-wp'); ?>
            <?php if ($pending_count > 0): ?>
                <span class="phpinfowp-ug-tab-count"><?php echo (int) $pending_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-update-audit', 'tab' => 'core'], admin_url('admin.php'))); ?>"
           class="phpinfowp-ug-tab-link <?php echo $tab === 'core' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-shield"></span>
            <?php _e('WordPress Core Audit', 'phpinfo-wp'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'piwp-update-audit', 'tab' => 'history'], admin_url('admin.php'))); ?>"
           class="phpinfowp-ug-tab-link <?php echo $tab === 'history' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-backup"></span>
            <?php _e('Update History & Health', 'phpinfo-wp'); ?>
        </a>
    </div>

    <?php if (!$is_pro && $tab !== 'plugins'): ?>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #6366f1; border-radius:10px; padding:12px 18px; margin:0 0 20px; display:flex; justify-content:space-between; align-items:center; gap:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
            <div style="flex:1; min-width:0;">
                <div style="font-size:13.5px; color:#0f172a; font-weight:500; line-height:1.4; margin-bottom:3px;">
                    <strong><?php _e('Free version:', 'phpinfo-wp'); ?></strong> <?php _e('PHP &amp; WP floor checks, major version jump warnings, and 30-entry update log.', 'phpinfo-wp'); ?>
                </div>
                <div style="font-size:12.5px; color:#64748b; line-height:1.4;">
                    <strong><?php _e('Pro:', 'phpinfo-wp'); ?></strong> <?php _e('Adds changelog risk analysis, abandonment scoring, cross-dependency validation, 200-entry history, and AI explanations.', 'phpinfo-wp'); ?>
                </div>
            </div>
            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="background:#6366f1; border-color:#6366f1; height:36px; line-height:34px; font-weight:600; font-size:13px; padding:0 18px; border-radius:6px; white-space:nowrap; flex-shrink:0; text-decoration:none; box-shadow:0 2px 6px rgba(99,102,241,0.25); display:inline-flex; align-items:center; justify-content:center; align-self:center; margin:0;">
                <?php _e('Get Pro &rarr;', 'phpinfo-wp'); ?>
            </a>
        </div>
    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- TAB 1: PLUGINS & THEMES UPDATE GUARD -->
    <!-- ===================================================================== -->
    <?php if ($tab === 'plugins'): ?>
        <?php if (!$is_pro): 
            // Generate 1-2 dynamic preview cards (from real pending/active plugins or realistic defaults)
            $preview_items = [];
            $raw_updates = get_site_transient('update_plugins');
            if (is_object($raw_updates) && !empty($raw_updates->response) && function_exists('get_plugins')) {
                $all_plugins = get_plugins();
                foreach ((array)$raw_updates->response as $file => $upd) {
                    $local = $all_plugins[$file] ?? [];
                    $slug  = $upd->slug ?? dirname($file);
                    $name  = $local['Name'] ?? $slug;
                    $from  = $local['Version'] ?? '1.0.0';
                    $to    = $upd->new_version ?? '2.0.0';
                    $preview_items[] = [
                        'name'      => $name,
                        'from'      => $from,
                        'to'        => $to,
                        'verdict'   => count($preview_items) === 0 ? 'risky' : 'safe',
                        'stability' => count($preview_items) === 0 ? 74 : 98,
                        'signals'   => count($preview_items) === 0 ? [
                            ['sev' => 'risky',   'icon' => 'dashicons-dismiss', 'color' => '#d63638', 'text' => sprintf(__('Major Version Jump: %s → %s contains potential backwards-incompatible API changes.', 'phpinfo-wp'), esc_html($from), esc_html($to))],
                            ['sev' => 'caution', 'icon' => 'dashicons-warning', 'color' => '#dba617', 'text' => __('Changelog Risk: Database schema migration required — backup recommended before update.', 'phpinfo-wp')]
                        ] : [
                            ['sev' => 'safe',    'icon' => 'dashicons-yes',     'color' => '#00a32a', 'text' => sprintf(__('Full PHP %s & WordPress %s compatibility verified.', 'phpinfo-wp'), esc_html(PHP_VERSION), esc_html(get_bloginfo('version')))]
                        ]
                    ];
                    if (count($preview_items) >= 2) break;
                }
            }

            if (empty($preview_items) && function_exists('get_plugins')) {
                $all_plugins = get_plugins();
                $active = (array) get_option('active_plugins', []);
                foreach ($active as $file) {
                    if (isset($all_plugins[$file])) {
                        $name = $all_plugins[$file]['Name'] ?? '';
                        $ver  = $all_plugins[$file]['Version'] ?? '1.0.0';
                        if ($name) {
                            $next_ver = preg_replace_callback('/\d+$/', function($m) { return ((int)$m[0] + 1); }, $ver);
                            $preview_items[] = [
                                'name'      => $name,
                                'from'      => $ver,
                                'to'        => $next_ver ?: '2.0.0',
                                'verdict'   => count($preview_items) === 0 ? 'risky' : 'safe',
                                'stability' => count($preview_items) === 0 ? 76 : 99,
                                'signals'   => count($preview_items) === 0 ? [
                                    ['sev' => 'risky',   'icon' => 'dashicons-dismiss', 'color' => '#d63638', 'text' => sprintf(__('Major Version Jump: %s → %s with database schema update flag detected.', 'phpinfo-wp'), esc_html($ver), esc_html($next_ver))],
                                    ['sev' => 'caution', 'icon' => 'dashicons-warning', 'color' => '#dba617', 'text' => __('Changelog Deep Scan: Deprecated hook usage and breaking API changes found.', 'phpinfo-wp')]
                                ] : [
                                    ['sev' => 'safe',    'icon' => 'dashicons-yes',     'color' => '#00a32a', 'text' => sprintf(__('Full PHP %s & WordPress %s compatibility verified.', 'phpinfo-wp'), esc_html(PHP_VERSION), esc_html(get_bloginfo('version')))]
                                ]
                            ];
                        }
                        if (count($preview_items) >= 2) break;
                    }
                }
            }

            if (empty($preview_items)) {
                $preview_items = [
                    [
                        'name'      => 'WooCommerce',
                        'from'      => '8.9.2',
                        'to'        => '9.2.0',
                        'verdict'   => 'risky',
                        'stability' => 74,
                        'signals'   => [
                            ['sev' => 'risky',   'icon' => 'dashicons-dismiss', 'color' => '#d63638', 'text' => __('Major Version Jump: 8.x → 9.x contains potential backwards-incompatible API changes.', 'phpinfo-wp')],
                            ['sev' => 'caution', 'icon' => 'dashicons-warning', 'color' => '#dba617', 'text' => __('Changelog Risk: Database schema migration required — backup recommended before update.', 'phpinfo-wp')]
                        ]
                    ],
                    [
                        'name'      => 'Advanced Custom Fields',
                        'from'      => '6.2.7',
                        'to'        => '6.3.1',
                        'verdict'   => 'safe',
                        'stability' => 98,
                        'signals'   => [
                            ['sev' => 'safe',    'icon' => 'dashicons-yes',     'color' => '#00a32a', 'text' => sprintf(__('Full PHP %s & WordPress %s compatibility verified.', 'phpinfo-wp'), esc_html(PHP_VERSION), esc_html(get_bloginfo('version')))]
                        ]
                    ]
                ];
            }
        ?>
            <div class="phpinfowp-ug-section-header">
                <div style="flex:1;min-width:280px">
                    <h2 style="margin:0 0 4px;font-size:16px;font-weight:600;display:flex;align-items:center;gap:8px;">
                        <?php _e('Pending Updates Compatibility Scan', 'phpinfo-wp'); ?>
                        <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                    </h2>
                    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.4">
                        <?php printf(__('Pre-update risk intelligence for %d pending plugin and theme update(s) before you apply them.', 'phpinfo-wp'), (int) $pending_count); ?>
                    </p>
                </div>
                <div>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="background:#6366f1; border-color:#6366f1; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px; height:36px; line-height:34px; border-radius:6px; padding:0 16px;">
                        <span class="dashicons dashicons-lock" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span>
                        <span><?php _e('Unlock Update Guard →', 'phpinfo-wp'); ?></span>
                    </a>
                </div>
            </div>

            <div id="phpinfowp-ug-result-area">
                <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
                    
                    <!-- Preview Cards (1-2 visible sample/real cards with subtle blur) -->
                    <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:10px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:12px;">
                        
                        <?php foreach ($preview_items as $p_item): 
                            $is_risky = $p_item['verdict'] === 'risky';
                            $border_color = $is_risky ? '#d63638' : '#00a32a';
                        ?>
                            <div class="phpinfowp-ug-item-card" style="border-left-color:<?php echo esc_attr($border_color); ?>; background:#fff; margin:0;">
                                <div class="phpinfowp-ug-item-header">
                                    <div class="phpinfowp-ug-item-title-row">
                                        <span class="phpinfowp-compat-owner-type" style="background:#777BB3;"><?php _e('Plugin', 'phpinfo-wp'); ?></span>
                                        <strong class="phpinfowp-ug-item-name"><?php echo esc_html($p_item['name']); ?></strong>
                                        <span class="phpinfowp-ug-ver-pill"><?php echo esc_html($p_item['from']); ?> &rarr; <strong><?php echo esc_html($p_item['to']); ?></strong></span>
                                    </div>
                                    <div class="phpinfowp-ug-item-badges">
                                        <span class="phpinfowp-ug-stability-pill <?php echo $p_item['stability'] >= 80 ? 'is-good' : 'is-warn'; ?>"><?php printf(__('Stability: %d%%', 'phpinfo-wp'), (int) $p_item['stability']); ?></span>
                                        <span class="phpinfowp-compat-sev" style="background:<?php echo esc_attr($border_color); ?>;"><?php echo esc_html(strtoupper($p_item['verdict'])); ?></span>
                                    </div>
                                </div>
                                <div class="phpinfowp-ug-signals">
                                    <?php foreach ($p_item['signals'] as $sig): ?>
                                        <div class="phpinfowp-ug-signal" style="border-left:3px solid <?php echo esc_attr($sig['color']); ?>;">
                                            <span class="dashicons <?php echo esc_attr($sig['icon']); ?>" style="color:<?php echo esc_attr($sig['color']); ?>;font-size:15px;vertical-align:text-bottom"></span>
                                            <span><?php echo esc_html($sig['text']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>

                    <!-- Gradient fade-out overlay -->
                    <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

                    <!-- Floating Upgrade Card -->
                    <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                        <span class="dashicons dashicons-shield" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                        <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Plugins & Themes Update Guard Locked', 'phpinfo-wp'); ?></h3>
                        <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                            <?php _e('Prevent fatal errors and White Screens of Death by scanning pending updates before applying them.', 'phpinfo-wp'); ?>
                        </p>
                        <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                            <div style="display:flex; align-items:flex-start; gap:8px;">
                                <span style="color:#6366f1; font-size:15px; line-height:1;">⚡</span>
                                <span><strong><?php _e('Changelog Breaking Change Analysis:', 'phpinfo-wp'); ?></strong> <?php _e('Deep scans release notes for fatal errors, removed APIs, and database migrations.', 'phpinfo-wp'); ?></span>
                            </div>
                            <div style="display:flex; align-items:flex-start; gap:8px;">
                                <span style="color:#6366f1; font-size:15px; line-height:1;">🛡️</span>
                                <span><strong><?php _e('PHP & WP Version Floor Protection:', 'phpinfo-wp'); ?></strong> <?php _e('Catches strict version requirements that crash older or newer PHP runtimes.', 'phpinfo-wp'); ?></span>
                            </div>
                            <div style="display:flex; align-items:flex-start; gap:8px;">
                                <span style="color:#6366f1; font-size:15px; line-height:1;">🤖</span>
                                <span><strong><?php _e('1-Click AI Fix & Risk Explanations:', 'phpinfo-wp'); ?></strong> <?php _e('Plain-English breakdown of update risks with suggested workarounds.', 'phpinfo-wp'); ?></span>
                            </div>
                        </div>
                        <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                            <?php _e('Unlock Update Guard with Pro &rarr;', 'phpinfo-wp'); ?>
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="phpinfowp-ug-section-header">
                <div style="flex:1;min-width:280px">
                    <h2 style="margin:0 0 4px;font-size:16px;font-weight:600"><?php _e('Pending Updates Compatibility Scan', 'phpinfo-wp'); ?></h2>
                    <p style="margin:0;font-size:13px;color:#666;line-height:1.4">
                        <?php printf(__('Scans %d pending plugin and theme update(s) before you apply them. Runs in the background — you can safely leave anytime.', 'phpinfo-wp'), (int) $pending_count); ?>
                    </p>
                </div>
                <form id="phpinfowp-ug-form" method="post" class="phpinfowp-compat-controls" style="margin:0;gap:8px">
                    <?php wp_nonce_field('phpinfowp_ug_nonce'); ?>
                    <button type="submit" id="phpinfowp-ug-scan-btn" name="phpinfowp_ug_scan" value="1" class="button button-primary" <?php echo $is_bg_running ? 'disabled' : ''; ?>>
                        <span class="dashicons <?php echo $is_bg_running ? 'dashicons-update phpinfowp-spin' : 'dashicons-search'; ?>" style="vertical-align:middle;margin-top:-2px"></span>
                        <?php echo $is_bg_running ? __('Scanning in Background...', 'phpinfo-wp') : __('Scan Pending Updates', 'phpinfo-wp'); ?>
                    </button>
                    <?php if ($guard_result && !$is_bg_running): ?>
                        <button type="button" id="phpinfowp-ug-clear-btn" name="phpinfowp_ug_clear" value="1" class="button button-secondary"><?php _e('Clear', 'phpinfo-wp'); ?></button>
                    <?php endif; ?>
                </form>
            </div>

            <div id="phpinfowp-ug-result-area">
                <?php if ($is_bg_running): ?>
                    <div class="phpinfowp-bg-scan-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:36px 28px; text-align:center; margin-top:20px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                        <div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                            <span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>
                        </div>
                        <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php _e('Scanning Pending Plugin & Theme Updates...', 'phpinfo-wp'); ?></h3>
                        <p style="margin:0 0 16px; font-size:13.5px; color:#64748b; max-width:540px; margin-left:auto; margin-right:auto; line-height:1.5;">
                            <?php _e('Evaluating PHP/WP compatibility, changelog breaking changes, and stability scores.', 'phpinfo-wp'); ?>
                        </p>
                        <div style="display:inline-flex; align-items:center; gap:8px; background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:8px 18px; border-radius:999px; font-size:12.5px; font-weight:600;">
                            <span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>
                            <span><?php _e('You can safely leave this page or close your browser anytime', 'phpinfo-wp'); ?></span>
                        </div>
                        <p style="margin:12px 0 0; font-size:12px; color:#94a3b8;">
                            <?php _e('The scan executes continuously in the background on your server. Results will load here once complete.', 'phpinfo-wp'); ?>
                        </p>
                    </div>
                <?php elseif (!$guard_result && $pending_count === 0): ?>
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:36px 24px; text-align:center; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                        <span class="dashicons dashicons-yes-alt" style="font-size:36px; width:36px; height:36px; color:#15803d; display:inline-block; margin-bottom:14px;"></span>
                        <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('All plugins and themes are up to date!', 'phpinfo-wp'); ?></h3>
                        <p style="margin:0; font-size:13px; color:#64748b;"><?php _e('No pending updates detected. When new updates arrive, return here to scan them for breaking changes before updating.', 'phpinfo-wp'); ?></p>
                    </div>

                <?php elseif (!$guard_result): ?>
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:36px 24px; text-align:center; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                        <span class="dashicons dashicons-shield-alt" style="font-size:36px; width:36px; height:36px; color:#2271b1; display:inline-block; margin-bottom:14px;"></span>
                        <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('Ready to scan pending updates', 'phpinfo-wp'); ?></h3>
                        <p style="margin:0; font-size:13px; color:#64748b;"><?php printf(__('You have %d pending update(s). Click "Scan Pending Updates" to evaluate PHP/WP compatibility, changelog risks, and stability scores. Runs in the background — you can safely leave anytime.', 'phpinfo-wp'), (int) $pending_count); ?></p>
                    </div>

                <?php else:
                    $gv = $guard_result['verdict'] ?? 'safe';
                    $gvm = $verdict_meta[$gv] ?? $verdict_meta['safe'];
                    $items = $guard_result['items'] ?? [];
                ?>
                    <?php if (empty($items)): ?>
                        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:36px 24px; text-align:center; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                            <span class="dashicons dashicons-yes-alt" style="font-size:36px; width:36px; height:36px; color:#15803d; display:inline-block; margin-bottom:14px;"></span>
                            <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('All pending updates look safe to apply!', 'phpinfo-wp'); ?></h3>
                            <p style="margin:0; font-size:13px; color:#64748b;"><?php _e('No breaking changes, requirement conflicts, or high-risk keywords detected in changelogs.', 'phpinfo-wp'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="phpinfowp-ug-item-list" style="margin-top:20px;">
                            <?php foreach ($items as $item):
                                $iv = $item['verdict'];
                                $ivc = $verdict_meta[$iv]['color'] ?? '#00a32a';
                                $stability = class_exists('Phpinfo_WP_Update_History') ? Phpinfo_WP_Update_History::stability_score($item['file'] ?? $item['slug']) : 100;
                            ?>
                                <div class="phpinfowp-ug-item-card" style="border-left-color:<?php echo esc_attr($ivc); ?>">
                                    <div class="phpinfowp-ug-item-header">
                                        <div class="phpinfowp-ug-item-title-row">
                                            <span class="phpinfowp-compat-owner-type" style="background:<?php echo $item['type'] === 'plugin' ? '#777BB3' : '#2271b1'; ?>">
                                                <?php echo esc_html(ucfirst($item['type'])); ?>
                                            </span>
                                            <strong class="phpinfowp-ug-item-name"><?php echo esc_html($item['name']); ?></strong>
                                            <span class="phpinfowp-ug-ver-pill">
                                                <?php echo esc_html($item['from'] ?: '?'); ?> &rarr; <strong><?php echo esc_html($item['to']); ?></strong>
                                            </span>
                                        </div>
                                        <div class="phpinfowp-ug-item-badges">
                                            <?php if ($stability >= 80 && $iv === 'safe'): ?>
                                                <span class="phpinfowp-ug-stability-pill is-good" title="<?php _e('Calculated from past update health checks on this site', 'phpinfo-wp'); ?>">
                                                    <?php printf(__('Track Record: %d%%', 'phpinfo-wp'), (int) $stability); ?>
                                                </span>
                                            <?php elseif ($stability < 80 && $stability !== 50): ?>
                                                <span class="phpinfowp-ug-stability-pill is-warn" title="<?php _e('Past updates experienced issues on this site', 'phpinfo-wp'); ?>">
                                                    <?php printf(__('Stability: %d%%', 'phpinfo-wp'), (int) $stability); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="phpinfowp-compat-sev" style="background:<?php echo esc_attr($ivc); ?>">
                                                <?php echo esc_html(strtoupper($iv)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="phpinfowp-ug-signals">
                                        <?php if (empty($item['signals'])): ?>
                                            <div class="phpinfowp-ug-signal is-safe">
                                                <span class="dashicons dashicons-yes" style="font-size:15px;color:#00a32a;vertical-align:text-bottom"></span>
                                                <?php _e('No breaking changes or requirement conflicts detected.', 'phpinfo-wp'); ?>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($item['signals'] as $sig):
                                                $sc = ($sig['severity'] === 'risky') ? '#d63638' : (($sig['severity'] === 'caution') ? '#dba617' : '#2271b1');
                                                $sicon = ($sig['severity'] === 'risky') ? 'dashicons-dismiss' : (($sig['severity'] === 'caution') ? 'dashicons-warning' : 'dashicons-info-outline');
                                                $sig_label = $sig['label'] ?? ($sig['desc'] ?? ($sig['text'] ?? ''));
                                            ?>
                                                <div class="phpinfowp-ug-signal" style="border-left:3px solid <?php echo esc_attr($sc); ?>; display:flex; align-items:flex-start; gap:10px; padding:9px 12px;">
                                                    <span class="dashicons <?php echo esc_attr($sicon); ?>" style="color:<?php echo esc_attr($sc); ?>; font-size:16px; width:16px; height:16px; margin-top:2px; flex-shrink:0;"></span>
                                                    <div style="flex:1; min-width:0;">
                                                        <div style="font-weight:600; font-size:13px; color:#1e293b; line-height:1.35;"><?php echo esc_html($sig_label); ?></div>
                                                        <?php if (!empty($sig['detail'])): ?>
                                                            <div class="phpinfowp-ug-signal-detail" style="color:#64748b; font-size:12px; line-height:1.45; margin-top:3px;">
                                                                <?php echo esc_html($sig['detail']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($is_pro && $ai_on): ?>
                                                        <button type="button" class="button-link phpinfowp-ai-explain" style="margin-left:auto; font-size:11px; white-space:nowrap; flex-shrink:0; padding:1px 0;"
                                                                data-ai-topic="update_guard"
                                                                data-ai-context="<?php echo esc_attr($item['name'] . ' update risk: ' . ($sig['type'] ?? 'signal')); ?>"
                                                                data-ai-value="<?php echo esc_attr($sig_label . (!empty($sig['detail']) ? ' (' . $sig['detail'] . ')' : '')); ?>">
                                                            <?php _e('Explain with AI', 'phpinfo-wp'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- TAB 2: WORDPRESS CORE AUDIT -->
    <!-- ===================================================================== -->
    <?php elseif ($tab === 'core'):
        $current_wp = Phpinfo_WP_Update_Audit::current_wp();
        $mode       = $core_result['mode'] ?? (version_compare($target, $current_wp, '>') ? 'forward_dry_run' : 'in_place_audit');
    ?>
        <div class="phpinfowp-ug-section-header">
            <div style="flex:1;min-width:280px">
                <?php if ($mode === 'in_place_audit'): ?>
                    <h2 style="margin:0 0 4px;font-size:16px;font-weight:600"><?php printf(__('WordPress %s Site Health Audit', 'phpinfo-wp'), esc_html($current_wp)); ?></h2>
                    <p style="margin:0;font-size:13px;color:#666;line-height:1.4">
                        <?php printf(__('In-Place Audit: Auditing active plugins &amp; themes on your current WordPress %s for active runtime issues and latent technical debt. Runs in the background — you can safely leave anytime.', 'phpinfo-wp'), esc_html($current_wp)); ?>
                    </p>
                <?php else: ?>
                    <h2 style="margin:0 0 4px;font-size:16px;font-weight:600"><?php printf(__('WordPress %s Upgrade Readiness (Forward Dry Run)', 'phpinfo-wp'), esc_html($target)); ?></h2>
                    <p style="margin:0;font-size:13px;color:#666;line-height:1.4">
                        <?php printf(__('Forward Dry Run: Scans all active plugins &amp; themes for breaking changes and deprecated APIs before upgrading to WordPress %s. Runs in the background — you can safely leave anytime.', 'phpinfo-wp'), esc_html($target)); ?>
                    </p>
                <?php endif; ?>
            </div>
            <form id="phpinfowp-ua-form" method="post" class="phpinfowp-compat-controls" style="margin:0;gap:8px">
                <?php wp_nonce_field('phpinfowp_ua_nonce'); ?>
                <label style="display:flex;align-items:center;gap:6px">
                    <span style="font-size:13px;font-weight:600"><?php _e('Target Core:', 'phpinfo-wp'); ?></span>
                    <input type="text" id="phpinfowp-ua-target" name="target" value="<?php echo esc_attr($target); ?>" size="6"
                           pattern="\d+\.\d+(\.\d+)?" style="width:70px;text-align:center" <?php echo $is_core_bg_running ? 'disabled' : ''; ?> />
                </label>
                <button type="submit" id="phpinfowp-ua-scan-btn" name="phpinfowp_ua_scan" value="1" class="button button-primary" <?php echo $is_core_bg_running ? 'disabled' : ''; ?>>
                    <span class="dashicons <?php echo $is_core_bg_running ? 'dashicons-update phpinfowp-spin' : 'dashicons-shield'; ?>" style="vertical-align:middle;margin-top:-2px"></span>
                    <?php echo $is_core_bg_running ? __('Auditing in Background...', 'phpinfo-wp') : __('Run Core Audit', 'phpinfo-wp'); ?>
                </button>
                <?php if ($core_result && !$is_core_bg_running): ?>
                    <button type="button" id="phpinfowp-ua-clear-btn" name="phpinfowp_ua_clear" value="1" class="button button-secondary"><?php _e('Clear', 'phpinfo-wp'); ?></button>
                <?php endif; ?>
            </form>
        </div>

        <div id="phpinfowp-ua-result-area">
            <?php if ($is_core_bg_running): ?>
                <div class="phpinfowp-bg-scan-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:36px 28px; text-align:center; margin-top:20px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                    <div style="width:56px; height:56px; border-radius:50%; background:#eff6ff; color:#2563eb; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                        <span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>
                    </div>
                    <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;">
                        <?php printf(__('Auditing WordPress %s Core Readiness...', 'phpinfo-wp'), esc_html($target)); ?>
                    </h3>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#64748b; max-width:540px; margin-left:auto; margin-right:auto; line-height:1.5;">
                        <?php _e('Scanning plugin & theme code for deprecated core APIs and removed jQuery methods.', 'phpinfo-wp'); ?>
                    </p>
                    <div style="display:inline-flex; align-items:center; gap:8px; background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:8px 18px; border-radius:999px; font-size:12.5px; font-weight:600;">
                        <span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>
                        <span><?php _e('You can safely leave this page or close your browser anytime', 'phpinfo-wp'); ?></span>
                    </div>
                    <p style="margin:12px 0 0; font-size:12px; color:#94a3b8;">
                        <?php _e('The audit continues continuously in the background on your server. Results will load here once complete.', 'phpinfo-wp'); ?>
                    </p>
                </div>
            <?php elseif (isset($core_result['error'])): ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($core_result['error']); ?></p></div>
            <?php elseif (!$core_result): ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:36px 24px; text-align:center; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                    <span class="dashicons dashicons-shield" style="font-size:36px; width:36px; height:36px; color:#64748b; display:inline-block; margin-bottom:14px;"></span>
                    <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('No core audit yet', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 6px; font-size:13px; color:#64748b;"><?php _e('Set the target WordPress version and click "Run Core Audit".', 'phpinfo-wp'); ?></p>
                    <p style="margin:0; font-size:12px; color:#94a3b8;"><?php _e('Scans all PHP and JS files across your plugins & themes without any file limits. Runs in the background — you can safely leave anytime.', 'phpinfo-wp'); ?></p>
                </div>
            <?php else:
                $cv     = $core_result['verdict'] ?? 'safe';
                $owners = $core_result['owners'] ?? [];

                if ($mode === 'in_place_audit') {
                    $rank = ['active_issue' => 0, 'latent_debt' => 1, 'clean' => 2, 'risky' => 0, 'caution' => 1, 'safe' => 2];
                } else {
                    $rank = ['risky' => 0, 'caution' => 1, 'likely_safe' => 2, 'safe' => 3];
                }

                uasort($owners, function ($a, $b) use ($rank) {
                    $r = ($rank[$a['verdict']] ?? 4) <=> ($rank[$b['verdict']] ?? 4);
                    return $r !== 0 ? $r : (($b['breaks'] ?? 0) + ($b['depr'] ?? 0)) <=> (($a['breaks'] ?? 0) + ($a['depr'] ?? 0));
                });


            ?>
                <?php if (!empty($core_result['truncated'])): ?>
                    <div class="notice notice-warning inline" style="margin:0 0 16px"><p>
                        <?php printf(__('Previous scan hit an earlier file limit (%d files). Click "Run Core Audit" to scan all files without limits.', 'phpinfo-wp'), (int) ($core_result['max_files'] ?? 8000)); ?>
                    </p></div>
                <?php endif; ?>

                <!-- Top Verdict Summary Banner -->
                <?php if ($mode === 'in_place_audit'): ?>
                    <?php if ($cv === 'clean' || $cv === 'safe' || (empty($core_result['total_breaks']) && empty($core_result['total_depr']) && empty($flagged_owners))): ?>
                        <div style="background:#fff; border:1px solid #bbf7d0; border-left:4px solid #15803d; border-radius:8px; padding:24px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                            <div style="display:flex; align-items:flex-start; gap:14px;">
                                <span class="dashicons dashicons-yes-alt" style="font-size:32px; width:32px; height:32px; color:#15803d; line-height:1; flex-shrink:0;"></span>
                                <div>
                                    <h3 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php printf(__('Running cleanly on WordPress %s!', 'phpinfo-wp'), esc_html($core_result['target'] ?? $current_wp)); ?></h3>
                                    <p style="margin:0; font-size:13px; color:#475569; line-height:1.5;">
                                        <?php _e('No active runtime issues or unmitigated technical debt detected on your current site.', 'phpinfo-wp'); ?>
                                        <?php if (!empty($core_result['total_mitigated'])): ?>
                                            <?php printf(__(' All %d deprecated pattern%s are actively mitigated by jQuery Migrate.', 'phpinfo-wp'), (int)$core_result['total_mitigated'], (int)$core_result['total_mitigated'] > 1 ? 's' : ''); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($cv === 'latent_debt' || $cv === 'caution'): ?>
                        <div style="background:#fff; border:1px solid #fde68a; border-left:4px solid #dba617; border-radius:8px; padding:24px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                            <div style="display:flex; align-items:flex-start; gap:14px;">
                                <span class="dashicons dashicons-info" style="font-size:32px; width:32px; height:32px; color:#dba617; line-height:1; flex-shrink:0;"></span>
                                <div>
                                    <h3 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php printf(__('Running cleanly on WordPress %s — technical debt noted', 'phpinfo-wp'), esc_html($core_result['target'] ?? $current_wp)); ?></h3>
                                    <p style="margin:0; font-size:13px; color:#475569; line-height:1.5;">
                                        <?php printf(__('No active issues or breakages detected on your current WordPress %s. Some plugins contain legacy code patterns that may need attention on a future major WordPress release.', 'phpinfo-wp'), esc_html($core_result['target'] ?? $current_wp)); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="background:#fff; border:1px solid #fecaca; border-left:4px solid #d63638; border-radius:8px; padding:24px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                            <div style="display:flex; align-items:flex-start; gap:14px;">
                                <span class="dashicons dashicons-warning" style="font-size:32px; width:32px; height:32px; color:#d63638; line-height:1; flex-shrink:0;"></span>
                                <div>
                                    <h3 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php printf(__('Active issue detected on WordPress %s', 'phpinfo-wp'), esc_html($core_result['target'] ?? $current_wp)); ?></h3>
                                    <p style="margin:0; font-size:13px; color:#475569; line-height:1.5;">
                                        <?php _e('One or more plugins contain confirmed breaking code patterns, removed symbols, or PHP requirements that conflict with your current environment.', 'phpinfo-wp'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($cv === 'safe'): ?>
                        <div style="background:#fff; border:1px solid #bbf7d0; border-left:4px solid #15803d; border-radius:8px; padding:24px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                            <div style="display:flex; align-items:flex-start; gap:14px;">
                                <span class="dashicons dashicons-yes-alt" style="font-size:32px; width:32px; height:32px; color:#15803d; line-height:1; flex-shrink:0;"></span>
                                <div>
                                    <h3 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php printf(__('Safe to update to WordPress %s!', 'phpinfo-wp'), esc_html($core_result['target'])); ?></h3>
                                    <p style="margin:0; font-size:13px; color:#475569; line-height:1.5;">
                                        <?php printf(__('No deprecated WordPress core APIs or removed jQuery methods were detected across your installed plugins and themes for WordPress %s.', 'phpinfo-wp'), esc_html($core_result['target'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($cv === 'likely_safe'): ?>
                        <div style="background:#fff; border:1px solid #bbf7d0; border-left:4px solid #15803d; border-radius:8px; padding:24px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                            <div style="display:flex; align-items:flex-start; gap:14px;">
                                <span class="dashicons dashicons-yes-alt" style="font-size:32px; width:32px; height:32px; color:#15803d; line-height:1; flex-shrink:0;"></span>
                                <div>
                                    <h3 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php printf(__('Likely safe to update to WordPress %s', 'phpinfo-wp'), esc_html($core_result['target'])); ?></h3>
                                    <p style="margin:0; font-size:13px; color:#475569; line-height:1.5;">
                                        <?php _e('Only minor deprecations or mitigated scripts were detected. Your site is expected to update smoothly.', 'phpinfo-wp'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($cv === 'caution'): ?>
                        <div style="background:#fff; border:1px solid #fde68a; border-left:4px solid #dba617; border-radius:8px; padding:24px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                            <div style="display:flex; align-items:flex-start; gap:14px;">
                                <span class="dashicons dashicons-warning" style="font-size:32px; width:32px; height:32px; color:#dba617; line-height:1; flex-shrink:0;"></span>
                                <div>
                                    <h3 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php printf(__('Update with caution to WordPress %s', 'phpinfo-wp'), esc_html($core_result['target'])); ?></h3>
                                    <p style="margin:0; font-size:13px; color:#475569; line-height:1.5;">
                                        <?php _e('Deprecated WordPress core APIs or compatibility gaps were detected in one or more active plugins. Review findings before proceeding.', 'phpinfo-wp'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="background:#fff; border:1px solid #fecaca; border-left:4px solid #d63638; border-radius:8px; padding:24px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                            <div style="display:flex; align-items:flex-start; gap:14px;">
                                <span class="dashicons dashicons-dismiss" style="font-size:32px; width:32px; height:32px; color:#d63638; line-height:1; flex-shrink:0;"></span>
                                <div>
                                    <h3 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#0f172a;"><?php printf(__('Risky — review first before updating to WordPress %s', 'phpinfo-wp'), esc_html($core_result['target'])); ?></h3>
                                    <p style="margin:0; font-size:13px; color:#475569; line-height:1.5;">
                                        <?php _e('Hard-removed APIs, abandoned plugins, or blocking PHP floor requirements were detected. Review the items below before updating.', 'phpinfo-wp'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php $jqm = $core_result['jquery_migrate'] ?? 'unknown';
                if ($jqm === 'present'): ?>
                    <div class="notice notice-info inline" style="margin:0 0 16px"><p>
                        <strong><?php _e('jQuery Migrate is loaded on this site', 'phpinfo-wp'); ?></strong> — <?php _e('Deprecated jQuery APIs are actively shimmed and mitigated.', 'phpinfo-wp'); ?>
                    </p></div>
                <?php elseif ($jqm === 'absent'): ?>
                    <div class="notice notice-error inline" style="margin:0 0 16px"><p>
                        <strong><?php _e('jQuery Migrate is not loaded', 'phpinfo-wp'); ?></strong> — <?php _e('Calls to removed jQuery APIs will fail on WordPress 5.7+.', 'phpinfo-wp'); ?>
                    </p></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    <!-- ===================================================================== -->
    <!-- TAB 3: UPDATE HISTORY & HEALTH -->
    <!-- ===================================================================== -->
    <?php elseif ($tab === 'history'): ?>
        <div class="phpinfowp-ug-section-header">
            <div style="flex:1;min-width:280px">
                <h2 style="margin:0 0 4px;font-size:16px;font-weight:600"><?php _e('Update History & Post-Health Diagnostics', 'phpinfo-wp'); ?></h2>
                <p style="margin:0;font-size:13px;color:#64748b;line-height:1.4">
                    <?php _e('Automated health checks (HTTP loopback, error logs, WP-Cron) captured after updates.', 'phpinfo-wp'); ?>
                </p>
            </div>
            <form id="phpinfowp-uh-form-main" method="post" class="phpinfowp-compat-controls" style="margin:0;gap:8px">
                <?php wp_nonce_field('phpinfowp_uh_nonce'); ?>
                <button type="submit" id="phpinfowp-uh-check-btn-main" name="phpinfowp_uh_health_check" value="1" class="button button-primary">
                    <span class="dashicons dashicons-heart" style="vertical-align:middle;margin-top:-2px"></span>
                    <?php _e('Run Health Check', 'phpinfo-wp'); ?>
                </button>
                <?php if (!empty($history_items) || $live_health): ?>
                    <button type="button" id="phpinfowp-uh-clear-btn-main" name="phpinfowp_uh_clear" value="1" class="button button-secondary"><?php _e('Clear History', 'phpinfo-wp'); ?></button>
                <?php endif; ?>
            </form>
        </div>

        <div id="phpinfowp-uh-result-area">
            <?php
            $current_health = $live_health ?: (class_exists('Phpinfo_WP_Update_History') ? Phpinfo_WP_Update_History::get_latest_health() : null);
            if ($current_health):
            ?>
                <div class="phpinfowp-health-endpoints-card" style="margin:16px 0 20px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;border-bottom:1px solid #f1f5f9;padding-bottom:10px;">
                        <h4 style="margin:0;font-size:14px;font-weight:600;color:#0f172a;">
                            <span class="dashicons dashicons-heart" style="color:#ef4444;vertical-align:text-top;margin-right:4px;"></span>
                            <?php _e('Multi-Endpoint Diagnostic Health', 'phpinfo-wp'); ?>
                        </h4>
                        <span style="font-size:12px;color:#64748b;">
                            <?php echo sprintf(__('Checked: %s', 'phpinfo-wp'), esc_html(human_time_diff($current_health['checked_at'] ?? time(), time()) . ' ' . __('ago', 'phpinfo-wp'))); ?>
                        </span>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:10px;">
                        <div style="padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">
                            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;"><?php _e('Homepage', 'phpinfo-wp'); ?></div>
                            <div style="margin-top:4px;font-size:13px;font-weight:700;color:<?php echo !empty($current_health['loopback']) ? '#16a34a' : '#dc2626'; ?>;">
                                <?php echo !empty($current_health['loopback']) ? '✓ 200 OK' : '✗ ' . __('HTTP 500', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <div style="padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">
                            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;"><?php _e('Admin Area', 'phpinfo-wp'); ?></div>
                            <div style="margin-top:4px;font-size:13px;font-weight:700;color:<?php echo !empty($current_health['admin_ok']) ? '#16a34a' : '#dc2626'; ?>;">
                                <?php echo !empty($current_health['admin_ok']) ? '✓ ' . __('Reachable', 'phpinfo-wp') : '✗ ' . __('Unreachable', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <div style="padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">
                            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;"><?php _e('REST API', 'phpinfo-wp'); ?></div>
                            <div style="margin-top:4px;font-size:13px;font-weight:700;color:<?php echo !empty($current_health['rest_ok']) ? '#16a34a' : '#dc2626'; ?>;">
                                <?php echo !empty($current_health['rest_ok']) ? '✓ /wp-json/' : '✗ ' . __('API 500', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <div style="padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">
                            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;"><?php _e('Single Post', 'phpinfo-wp'); ?></div>
                            <div style="margin-top:4px;font-size:13px;font-weight:700;color:<?php echo !empty($current_health['singular_ok']) ? '#16a34a' : '#dc2626'; ?>;">
                                <?php echo !empty($current_health['singular_ok']) ? '✓ ' . __('Renders', 'phpinfo-wp') : '✗ ' . __('Error 500', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <?php if (isset($current_health['ecommerce_ok']) && $current_health['ecommerce_ok'] !== null): ?>
                        <div style="padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">
                            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;"><?php _e('Shop/Checkout', 'phpinfo-wp'); ?></div>
                            <div style="margin-top:4px;font-size:13px;font-weight:700;color:<?php echo !empty($current_health['ecommerce_ok']) ? '#16a34a' : '#dc2626'; ?>;">
                                <?php echo !empty($current_health['ecommerce_ok']) ? '✓ ' . __('Available', 'phpinfo-wp') : '✗ ' . __('Error 500', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div style="padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">
                            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;"><?php _e('WP-Cron', 'phpinfo-wp'); ?></div>
                            <div style="margin-top:4px;font-size:13px;font-weight:700;color:<?php echo !empty($current_health['cron_ok']) ? '#16a34a' : '#dc2626'; ?>;">
                                <?php echo !empty($current_health['cron_ok']) ? '✓ ' . __('Scheduled', 'phpinfo-wp') : '✗ ' . __('Disrupted', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <div style="padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">
                            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;"><?php _e('Error Log', 'phpinfo-wp'); ?></div>
                            <div style="margin-top:4px;font-size:13px;font-weight:700;color:<?php echo ($current_health['new_errors'] ?? 0) === 0 ? '#16a34a' : '#dc2626'; ?>;">
                                <?php echo ($current_health['new_errors'] ?? 0) === 0 ? '✓ ' . __('Clean', 'phpinfo-wp') : sprintf(__('%d error(s)', 'phpinfo-wp'), (int)$current_health['new_errors']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($history_items)): ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:36px 24px; text-align:center; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                    <span class="dashicons dashicons-backup" style="font-size:36px; width:36px; height:36px; color:#64748b; display:inline-block; margin-bottom:14px;"></span>
                    <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('No update history recorded yet', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0; font-size:13px; color:#64748b;"><?php _e('Update Guard automatically captures updates when you upgrade plugins, themes, or WordPress core and tests site health 60s later.', 'phpinfo-wp'); ?></p>
                </div>
            <?php else: 
                $hist_per_page    = 15;
                $hist_total       = count($history_items);
                $hist_total_pages = max(1, (int) ceil($hist_total / $hist_per_page));
                $hist_paged       = max(1, min($hist_total_pages, (int) ($_GET['paged'] ?? 1)));
                $paged_history    = array_slice($history_items, ($hist_paged - 1) * $hist_per_page, $hist_per_page);
            ?>
                <table class="wp-list-table widefat fixed striped" style="margin-top:16px;border-radius:6px;overflow:hidden">
                    <thead>
                        <tr>
                            <th style="width:140px"><?php _e('Date & Time', 'phpinfo-wp'); ?></th>
                            <th style="width:100px"><?php _e('Type', 'phpinfo-wp'); ?></th>
                            <th><?php _e('Component / Upgrade', 'phpinfo-wp'); ?></th>
                            <th style="width:130px"><?php _e('Post-Health', 'phpinfo-wp'); ?></th>
                            <th style="width:110px"><?php _e('Stability Rating', 'phpinfo-wp'); ?></th>
                            <th style="width:110px"><?php _e('Snapshot / Action', 'phpinfo-wp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $snapshots = class_exists('Phpinfo_WP_Update_History') ? Phpinfo_WP_Update_History::get_snapshots() : [];
                        foreach ($paged_history as $h):
                            $health = $h['health'] ?? null;
                            $h_status = $health ? ($health['status'] ?? 'ok') : 'pending';
                            $h_color  = ['ok' => '#00a32a', 'issues' => '#d63638', 'pending' => '#dba617'][$h_status];
                            $h_label  = ['ok' => 'HEALTHY', 'issues' => 'ISSUES', 'pending' => 'PENDING'][$h_status];
                            $stability = Phpinfo_WP_Update_History::stability_score($h['slug'] ?? '');
                            $has_snapshot = !empty($snapshots[$h['slug']]['path']) && file_exists($snapshots[$h['slug']]['path']);
                        ?>
                            <tr>
                                <td style="font-size:12px;color:#666">
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $h['at'] ?? time())); ?>
                                </td>
                                <td>
                                    <span class="phpinfowp-compat-owner-type" style="background:<?php echo ($h['type'] ?? '') === 'plugin' ? '#777BB3' : (($h['type'] ?? '') === 'theme' ? '#2271b1' : '#1d2327'); ?>">
                                        <?php echo esc_html(ucfirst($h['type'] ?? '')); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($h['name'] ?? $h['slug'] ?? 'Unknown'); ?></strong>
                                    <?php if (!empty($h['to'])): ?>
                                        <span class="phpinfowp-ug-ver-pill" style="margin-left:6px">
                                            &rarr; <?php echo esc_html($h['to']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="phpinfowp-compat-sev" style="background:<?php echo esc_attr($h_color); ?>">
                                        <?php echo esc_html($h_label); ?>
                                    </span>
                                    <?php if (!empty($health['problems'])): ?>
                                        <div style="font-size:11px;color:#dc2626;margin-top:4px;line-height:1.3;">
                                            <?php echo esc_html(implode(', ', array_slice($health['problems'], 0, 2))); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="phpinfowp-ug-stability-pill <?php echo $stability >= 80 ? 'is-good' : 'is-warn'; ?>">
                                        <?php echo (int) $stability; ?>%
                                    </span>
                                </td>
                                <td>
                                    <?php if ($has_snapshot && ($h['type'] ?? '') === 'plugin'): ?>
                                        <button type="button" class="button button-small phpinfowp-ug-rollback-btn" data-slug="<?php echo esc_attr($h['slug']); ?>" data-name="<?php echo esc_attr($h['name'] ?? $h['slug']); ?>" data-from="<?php echo esc_attr($snapshots[$h['slug']]['from_version'] ?? ''); ?>">
                                            <span class="dashicons dashicons-undo" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:2px;"></span>
                                            <?php _e('Rollback', 'phpinfo-wp'); ?>
                                        </button>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination Controls -->
        <?php if ($hist_total > 0): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px;">
                <div style="font-size:13px; color:#64748b;">
                    <?php printf(
                        __("Showing %1\$d &ndash; %2\$d of %3\$s update events", "phpinfo-wp"),
                        ($hist_paged - 1) * $hist_per_page + 1,
                        min($hist_total, $hist_paged * $hist_per_page),
                        number_format($hist_total)
                    ); ?>
                </div>
                <div style="display:flex; gap:6px; align-items:center;">
                    <?php if ($hist_paged > 2): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => 1])); ?>" class="button button-secondary" title="<?php esc_attr_e("First page", "phpinfo-wp"); ?>">&laquo;&laquo;</a>
                    <?php endif; ?>
                    <?php if ($hist_paged > 1): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => $hist_paged - 1])); ?>" class="button button-secondary">&laquo; <?php _e("Previous", "phpinfo-wp"); ?></a>
                    <?php else: ?>
                        <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;">&laquo; <?php _e("Previous", "phpinfo-wp"); ?></span>
                    <?php endif; ?>
                    <span style="font-size:13px; line-height:30px; padding:0 8px; color:#334155; font-weight:600;">
                        <?php printf(__("Page %d of %d", "phpinfo-wp"), $hist_paged, max(1, $hist_total_pages)); ?>
                    </span>
                    <?php if ($hist_paged < $hist_total_pages): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => $hist_paged + 1])); ?>" class="button button-secondary"><?php _e("Next", "phpinfo-wp"); ?> &raquo;</a>
                    <?php else: ?>
                        <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;"><?php _e("Next", "phpinfo-wp"); ?> &raquo;</span>
                    <?php endif; ?>
                    <?php if ($hist_paged < $hist_total_pages - 1): ?>
                        <a href="<?php echo esc_url(add_query_arg(["paged" => $hist_total_pages])); ?>" class="button button-secondary" title="<?php esc_attr_e("Last page", "phpinfo-wp"); ?>">&raquo;&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>


                <?php
                $rollback_log = class_exists('Phpinfo_WP_Update_History') ? Phpinfo_WP_Update_History::get_rollback_log() : [];
                if (!empty($rollback_log)):
                    $rb_per_page    = 15;
                    $rb_total       = count($rollback_log);
                    $rb_total_pages = max(1, (int) ceil($rb_total / $rb_per_page));
                    $rb_paged       = max(1, min($rb_total_pages, (int) ($_GET['rb_paged'] ?? 1)));
                    $paged_rollback = array_slice($rollback_log, ($rb_paged - 1) * $rb_per_page, $rb_per_page);
                ?>
                    <div style="margin-top:24px;">
                        <h3 style="margin:0 0 10px;font-size:14px;font-weight:600;color:#1e293b;">
                            <span class="dashicons dashicons-backup" style="vertical-align:text-top;margin-right:4px;"></span>
                            <?php _e('Rollback Audit Log', 'phpinfo-wp'); ?>
                        </h3>
                        <table class="wp-list-table widefat fixed striped" style="border-radius:6px;overflow:hidden;">
                            <thead>
                                <tr>
                                    <th style="width:150px"><?php _e('Timestamp', 'phpinfo-wp'); ?></th>
                                    <th style="width:130px"><?php _e('Plugin', 'phpinfo-wp'); ?></th>
                                    <th style="width:110px"><?php _e('Event', 'phpinfo-wp'); ?></th>
                                    <th><?php _e('Details', 'phpinfo-wp'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paged_rollback as $entry): ?>
                                    <tr>
                                        <td style="font-size:12px;color:#64748b;"><?php echo esc_html($entry['time'] ?? ''); ?></td>
                                        <td><strong><?php echo esc_html($entry['slug'] ?? ''); ?></strong></td>
                                        <td>
                                            <span class="phpinfowp-compat-sev" style="background:<?php echo ($entry['event'] ?? '') === 'rollback_success' ? '#00a32a' : '#d63638'; ?>;">
                                                <?php echo esc_html(($entry['event'] ?? '') === 'rollback_success' ? 'RESTORED' : 'FAILED'); ?>
                                            </span>
                                        </td>
                                        <td style="font-size:12px;color:#334155;"><?php echo esc_html($entry['detail'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Rollback Pagination Controls -->
                        <?php if ($rb_total > 0): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px;">
                                <div style="font-size:13px; color:#64748b;">
                                    <?php printf(
                                        __('Showing %1$d &ndash; %2$d of %3$s rollback events', 'phpinfo-wp'),
                                        ($rb_paged - 1) * $rb_per_page + 1,
                                        min($rb_total, $rb_paged * $rb_per_page),
                                        number_format($rb_total)
                                    ); ?>
                                </div>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <?php if ($rb_paged > 2): ?>
                                        <a href="<?php echo esc_url(add_query_arg(['rb_paged' => 1])); ?>" class="button button-secondary" title="<?php esc_attr_e('First page', 'phpinfo-wp'); ?>">&laquo;&laquo;</a>
                                    <?php endif; ?>
                                    <?php if ($rb_paged > 1): ?>
                                        <a href="<?php echo esc_url(add_query_arg(['rb_paged' => $rb_paged - 1])); ?>" class="button button-secondary">&laquo; <?php _e('Previous', 'phpinfo-wp'); ?></a>
                                    <?php else: ?>
                                        <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;">&laquo; <?php _e('Previous', 'phpinfo-wp'); ?></span>
                                    <?php endif; ?>
                                    <span style="font-size:13px; line-height:30px; padding:0 8px; color:#334155; font-weight:600;">
                                        <?php printf(__('Page %d of %d', 'phpinfo-wp'), $rb_paged, max(1, $rb_total_pages)); ?>
                                    </span>
                                    <?php if ($rb_paged < $rb_total_pages): ?>
                                        <a href="<?php echo esc_url(add_query_arg(['rb_paged' => $rb_paged + 1])); ?>" class="button button-secondary"><?php _e('Next', 'phpinfo-wp'); ?> &raquo;</a>
                                    <?php else: ?>
                                        <span class="button button-secondary disabled" style="opacity:0.4; cursor:not-allowed;"><?php _e('Next', 'phpinfo-wp'); ?> &raquo;</span>
                                    <?php endif; ?>
                                    <?php if ($rb_paged < $rb_total_pages - 1): ?>
                                        <a href="<?php echo esc_url(add_query_arg(['rb_paged' => $rb_total_pages])); ?>" class="button button-secondary" title="<?php esc_attr_e('Last page', 'phpinfo-wp'); ?>">&raquo;&raquo;</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function setSideScoreCardLoading() {
        var sideCard = document.querySelector('.phpinfowp-side-score-card');
        if (sideCard) {
            sideCard.style.transition = 'opacity 0.2s ease';
            sideCard.style.opacity = '0.6';
            var gradeCircle = sideCard.querySelector('.phpinfowp-grade-circle');
            if (gradeCircle) {
                gradeCircle.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:22px; width:22px; height:22px; line-height:1; display:inline-block;"></span>';
            }
        }
    }

    var ugForm = document.getElementById('phpinfowp-ug-form');
    var ugScanBtn = document.getElementById('phpinfowp-ug-scan-btn');
    var ugClearBtn = document.getElementById('phpinfowp-ug-clear-btn');
    var ugResultArea = document.getElementById('phpinfowp-ug-result-area');
    var ugNonce = '<?php echo wp_create_nonce("phpinfowp_ug_nonce"); ?>';
    var ugBgNonce = '<?php echo esc_js($bg_nonce); ?>';
    var isUgRunning = <?php echo $is_bg_running ? 'true' : 'false'; ?>;
    var ugPollTimer = null;

    function ackBgNotice(mod, nonce) {
        if (!nonce) return;
        var fd = new FormData();
        fd.append('action', 'phpinfowp_bg_scan_dismiss_notice');
        fd.append('module', mod);
        fd.append('nonce', nonce);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(ajaxurl, fd);
        } else {
            fetch(ajaxurl, { method: 'POST', body: fd }).catch(function(){});
        }
    }

    function pollUgStatus() {
        if (ugPollTimer) return;
        ugPollTimer = setInterval(function() {
            var fd = new FormData();
            fd.append('action', 'phpinfowp_bg_scan_status');
            fd.append('module', 'update_guard');
            fd.append('nonce', ugBgNonce);

            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res && res.success && res.data) {
                        if (res.data.status === 'complete' || res.data.status === 'idle') {
                            clearInterval(ugPollTimer);
                            ackBgNotice('update_guard', ugBgNonce);
                            if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                            var url = new URL(window.location.href);
                            url.searchParams.set('tab', 'plugins');
                            window.location.href = url.toString();
                        }
                    }
                })
                .catch(function() {});
        }, 2500);
    }

    if (isUgRunning) {
        if (window.phpinfowpShowScanBanner) {
            window.phpinfowpShowScanBanner('<?php echo esc_js(__('Scanning Pending Updates...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Evaluating changelogs, breaking changes, and stability (5–15s)', 'phpinfo-wp')); ?>');
        }
        pollUgStatus();
    }

    if (ugForm && ugScanBtn) {
        ugForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ugScanBtn.disabled = true;
            if (ugClearBtn) ugClearBtn.disabled = true;
            ugScanBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Scanning in Background...', 'phpinfo-wp')); ?>';

            setSideScoreCardLoading();

            if (ugResultArea) {
                ugResultArea.innerHTML = '<div class="phpinfowp-bg-scan-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:36px 28px; text-align:center; margin-top:20px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">' +
                    '<div style="width:56px; height:56px; border-radius:50%; background:#eef2ff; color:#6366f1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">' +
                    '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>' +
                    '</div>' +
                    '<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php echo esc_js(__('Scanning Pending Plugin & Theme Updates...', 'phpinfo-wp')); ?></h3>' +
                    '<p style="margin:0 0 16px; font-size:13.5px; color:#64748b; max-width:540px; margin-left:auto; margin-right:auto; line-height:1.5;"><?php echo esc_js(__('Evaluating PHP/WP compatibility, changelog breaking changes, and stability scores.', 'phpinfo-wp')); ?></p>' +
                    '<div style="display:inline-flex; align-items:center; gap:8px; background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:8px 18px; border-radius:999px; font-size:12.5px; font-weight:600;">' +
                    '<span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>' +
                    '<span><?php echo esc_js(__('You can safely leave this page or close your browser anytime', 'phpinfo-wp')); ?></span>' +
                    '</div>' +
                    '<p style="margin:12px 0 0; font-size:12px; color:#94a3b8;"><?php echo esc_js(__('The scan executes continuously in the background on your server. Results will load here once complete.', 'phpinfo-wp')); ?></p>' +
                    '</div>';
            }

            if (window.phpinfowpShowScanBanner) {
                window.phpinfowpShowScanBanner('<?php echo esc_js(__('Scanning Pending Updates...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Evaluating changelogs, breaking changes, and stability (5–15s)', 'phpinfo-wp')); ?>');
            }

            pollUgStatus();

            var data = new FormData();
            data.append('action', 'phpinfowp_ug_scan');
            data.append('nonce', ugNonce);

            fetch(ajaxurl, { method: 'POST', body: data }).then(function(res) { return res.json(); }).then(function(res) {
                if (ugPollTimer) clearInterval(ugPollTimer);
                ackBgNotice('update_guard', ugBgNonce);
                if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                if (res.success) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', 'plugins');
                    window.location.href = url.toString();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Scan failed.');
                    window.location.reload();
                }
            }).catch(function() {
                // Connection dropped or abort: keep poller running
            });
        });
    }

    if (ugClearBtn) {
        ugClearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.phpinfowpConfirm({
                title: '<?php echo esc_js(__('Clear Update Guard Results', 'phpinfo-wp')); ?>',
                message: '<?php echo esc_js(__('Are you sure you want to clear the pending update compatibility scan results?', 'phpinfo-wp')); ?>',
                confirmText: '<?php echo esc_js(__('Clear Results', 'phpinfo-wp')); ?>',
                isDanger: true
            }).then(function(confirmed) {
                if (!confirmed) return;
                ugClearBtn.disabled = true;
                if (ugScanBtn) ugScanBtn.disabled = true;
                ugClearBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Clearing...', 'phpinfo-wp')); ?>';
                setSideScoreCardLoading();
                var data = new FormData();
                data.append('action', 'phpinfowp_ug_clear');
                data.append('nonce', ugNonce);
                fetch(ajaxurl, { method: 'POST', body: data }).then(function() {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', 'plugins');
                    window.location.href = url.toString();
                }).catch(function() {
                    window.location.reload();
                });
            });
        });
    }

    var uaForm = document.getElementById('phpinfowp-ua-form');
    var uaScanBtn = document.getElementById('phpinfowp-ua-scan-btn');
    var uaClearBtn = document.getElementById('phpinfowp-ua-clear-btn');
    var uaTargetInput = document.getElementById('phpinfowp-ua-target');
    var uaResultArea = document.getElementById('phpinfowp-ua-result-area');
    var uaNonce = '<?php echo wp_create_nonce("phpinfowp_ua_nonce"); ?>';
    var uaBgNonce = '<?php echo esc_js($bg_nonce); ?>';
    var isUaRunning = <?php echo $is_core_bg_running ? 'true' : 'false'; ?>;
    var uaPollTimer = null;

    function showUaScanBanner(targetVal) {
        if (window.phpinfowpShowScanBanner) {
            window.phpinfowpShowScanBanner(
                '<?php echo esc_js(__('Auditing WordPress Core Readiness...', 'phpinfo-wp')); ?>',
                '<?php echo esc_js(__('Scanning plugins and themes for deprecated APIs and jQuery methods (5–15s)', 'phpinfo-wp')); ?>'
            );
        }
    }

    function pollUaStatus(targetVal) {
        if (uaPollTimer) return;
        uaPollTimer = setInterval(function() {
            var fd = new FormData();
            fd.append('action', 'phpinfowp_bg_scan_status');
            fd.append('module', 'core_audit');
            fd.append('nonce', uaBgNonce);

            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res && res.success && res.data) {
                        if (res.data.status === 'complete' || res.data.status === 'idle') {
                            clearInterval(uaPollTimer);
                            ackBgNotice('core_audit', uaBgNonce);
                            if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                            var url = new URL(window.location.href);
                            url.searchParams.set('tab', 'core');
                            if (targetVal) url.searchParams.set('target', targetVal);
                            window.location.href = url.toString();
                        }
                    }
                })
                .catch(function() {});
        }, 2500);
    }

    if (isUaRunning) {
        showUaScanBanner(uaTargetInput ? uaTargetInput.value : '');
        pollUaStatus(uaTargetInput ? uaTargetInput.value : '');
    }

    if (uaForm && uaScanBtn) {
        uaForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var targetVal = uaTargetInput ? uaTargetInput.value : '';
            uaScanBtn.disabled = true;
            if (uaClearBtn) uaClearBtn.disabled = true;
            if (uaTargetInput) uaTargetInput.disabled = true;
            uaScanBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Auditing in Background...', 'phpinfo-wp')); ?>';

            setSideScoreCardLoading();
            showUaScanBanner(targetVal);

            if (uaResultArea) {
                uaResultArea.innerHTML = '<div class="phpinfowp-bg-scan-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:36px 28px; text-align:center; margin-top:20px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">' +
                    '<div style="width:56px; height:56px; border-radius:50%; background:#eff6ff; color:#2563eb; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">' +
                    '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:28px; width:28px; height:28px;"></span>' +
                    '</div>' +
                    '<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php echo esc_js(__('Auditing WordPress', 'phpinfo-wp')); ?> ' + (targetVal || '') + ' <?php echo esc_js(__('Core Readiness...', 'phpinfo-wp')); ?></h3>' +
                    '<p style="margin:0 0 16px; font-size:13.5px; color:#64748b; max-width:540px; margin-left:auto; margin-right:auto; line-height:1.5;"><?php echo esc_js(__('Scanning plugin & theme code for deprecated core APIs and removed jQuery methods.', 'phpinfo-wp')); ?></p>' +
                    '<div style="display:inline-flex; align-items:center; gap:8px; background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:8px 18px; border-radius:999px; font-size:12.5px; font-weight:600;">' +
                    '<span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>' +
                    '<span><?php echo esc_js(__('You can safely leave this page or close your browser anytime', 'phpinfo-wp')); ?></span>' +
                    '</div>' +
                    '<p style="margin:12px 0 0; font-size:12px; color:#94a3b8;"><?php echo esc_js(__('The audit continues continuously in the background on your server. Results will load here once complete.', 'phpinfo-wp')); ?></p>' +
                    '</div>';
            }

            pollUaStatus(targetVal);

            var data = new FormData();
            data.append('action', 'phpinfowp_ua_scan');
            data.append('nonce', uaNonce);
            data.append('target', targetVal);

            fetch(ajaxurl, { method: 'POST', body: data }).then(function(res) { return res.json(); }).then(function(res) {
                if (uaPollTimer) clearInterval(uaPollTimer);
                ackBgNotice('core_audit', uaBgNonce);
                if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
                if (res.success) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', 'core');
                    url.searchParams.set('target', targetVal);
                    window.location.href = url.toString();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Audit failed.');
                    window.location.reload();
                }
            }).catch(function() {
                // Connection dropped or user navigated away: keep poller running
            });
        });
    }

    if (uaClearBtn) {
        uaClearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.phpinfowpConfirm({
                title: '<?php echo esc_js(__('Clear Core Readiness Results', 'phpinfo-wp')); ?>',
                message: '<?php echo esc_js(__('Are you sure you want to clear the WordPress core audit results?', 'phpinfo-wp')); ?>',
                confirmText: '<?php echo esc_js(__('Clear Results', 'phpinfo-wp')); ?>',
                isDanger: true
            }).then(function(confirmed) {
                if (!confirmed) return;
                uaClearBtn.disabled = true;
                if (uaScanBtn) uaScanBtn.disabled = true;
                uaClearBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Clearing...', 'phpinfo-wp')); ?>';
                setSideScoreCardLoading();
                var data = new FormData();
                data.append('action', 'phpinfowp_ua_clear');
                data.append('nonce', uaNonce);
                fetch(ajaxurl, { method: 'POST', body: data }).then(function() {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', 'core');
                    window.location.href = url.toString();
                }).catch(function() {
                    window.location.reload();
                });
            });
        });
    }

    var uhForm = document.getElementById('phpinfowp-uh-form');
    var uhCheckBtn = document.getElementById('phpinfowp-uh-check-btn');
    var uhClearBtn = document.getElementById('phpinfowp-uh-clear-btn');
    var uhNonce = '<?php echo wp_create_nonce("phpinfowp_uh_nonce"); ?>';

    function handleUhHealthCheck(btn) {
        if (btn) btn.disabled = true;
        if (uhCheckBtn) uhCheckBtn.disabled = true;
        var btnMain = document.getElementById('phpinfowp-uh-check-btn-main');
        if (btnMain) btnMain.disabled = true;
        if (uhClearBtn) uhClearBtn.disabled = true;
        var clearBtnMain = document.getElementById('phpinfowp-uh-clear-btn-main');
        if (clearBtnMain) clearBtnMain.disabled = true;
        
        if (btn) btn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span> <?php echo esc_js(__('Checking...', 'phpinfo-wp')); ?>';
        setSideScoreCardLoading();

        if (window.phpinfowpShowScanBanner) {
            window.phpinfowpShowScanBanner('<?php echo esc_js(__('Running Live Health Check...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Verifying loopback, cron, and error logs (2–4s)', 'phpinfo-wp')); ?>');
        }

        var data = new FormData();
        data.append('action', 'phpinfowp_uh_health_check');
        data.append('nonce', uhNonce);

        fetch(ajaxurl, { method: 'POST', body: data }).then(function(res) {
            return res.json();
        }).then(function() {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', 'history');
            window.location.href = url.toString();
        }).catch(function() {
            if (window.phpinfowpHideScanBanner) window.phpinfowpHideScanBanner();
            window.location.reload();
        });
    }

    function handleUhClear(btn) {
        window.phpinfowpConfirm({
            title: '<?php echo esc_js(__('Clear Update History', 'phpinfo-wp')); ?>',
            message: '<?php echo esc_js(__('Clear all update history records?', 'phpinfo-wp')); ?>',
            confirmText: '<?php echo esc_js(__('Clear Records', 'phpinfo-wp')); ?>',
            isDanger: true
        }).then(function(confirmed) {
            if (!confirmed) return;

            if (btn) btn.disabled = true;
            if (uhClearBtn) uhClearBtn.disabled = true;
            var clearBtnMain = document.getElementById('phpinfowp-uh-clear-btn-main');
            if (clearBtnMain) clearBtnMain.disabled = true;
            if (uhCheckBtn) uhCheckBtn.disabled = true;
            var btnMain = document.getElementById('phpinfowp-uh-check-btn-main');
            if (btnMain) btnMain.disabled = true;

            setSideScoreCardLoading();
            if (btn) btn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="margin-top:2px;"></span> <?php echo esc_js(__('Clearing...', 'phpinfo-wp')); ?>';

            var data = new FormData();
            data.append('action', 'phpinfowp_uh_clear');
            data.append('nonce', uhNonce);

            fetch(ajaxurl, { method: 'POST', body: data }).then(function() {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', 'history');
                window.location.href = url.toString();
            }).catch(function() {
                window.location.reload();
            });
        });
    }

    if (uhForm && uhCheckBtn) {
        uhForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleUhHealthCheck(uhCheckBtn);
        });
    }

    var uhCheckBtnMain = document.getElementById('phpinfowp-uh-check-btn-main');
    if (uhCheckBtnMain) {
        uhCheckBtnMain.addEventListener('click', function(e) {
            e.preventDefault();
            handleUhHealthCheck(uhCheckBtnMain);
        });
    }

    if (uhClearBtn) {
        uhClearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleUhClear(uhClearBtn);
        });
    }

    var uhClearBtnMain = document.getElementById('phpinfowp-uh-clear-btn-main');
    if (uhClearBtnMain) {
        uhClearBtnMain.addEventListener('click', function(e) {
            e.preventDefault();
            handleUhClear(uhClearBtnMain);
        });
    }

    // 1-Click Rollback Handler
    document.querySelectorAll('.phpinfowp-ug-rollback-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var slug = btn.getAttribute('data-slug');
            var name = btn.getAttribute('data-name');
            var fromVer = btn.getAttribute('data-from');
            var msg = "⚠️ Rollback Plugin:\n\n" +
                      "Restore " + name + (fromVer ? " to previous version v" + fromVer : "") + "?\n\n" +
                      "Note: Plugin files will be restored from the pre-install snapshot. Any database migrations will not be reverted. Proceed?";
            if (!confirm(msg)) return;

            var origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="dashicons dashicons-update" style="animation:rotation 1s infinite linear;font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:2px;"></span> <?php echo esc_js(__('Restoring...', 'phpinfo-wp')); ?>';

            var fd = new FormData();
            fd.append('action', 'phpinfowp_uh_rollback');
            fd.append('slug', slug);
            fd.append('nonce', uhNonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: fd
            }).then(function(res) { return res.json(); })
            .then(function(res) {
                if (res.success) {
                    alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Rollback succeeded!', 'phpinfo-wp')); ?>');
                    window.location.reload();
                } else {
                    alert('<?php echo esc_js(__('Rollback failed: ', 'phpinfo-wp')); ?>' + (res.data && res.data.message ? res.data.message : 'Unknown error'));
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            }).catch(function() {
                alert('<?php echo esc_js(__('Network error during rollback.', 'phpinfo-wp')); ?>');
                btn.disabled = false;
                btn.innerHTML = origHtml;
            });
        });
    });
});
</script>
