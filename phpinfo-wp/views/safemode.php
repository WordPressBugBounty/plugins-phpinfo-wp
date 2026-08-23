<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');

$notice = '';
$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['phpinfowp_safemode_start']) && check_admin_referer('phpinfowp_safemode_start_nonce')) {
        $disabled = isset($_POST['disable']) && is_array($_POST['disable'])
            ? array_map('sanitize_text_field', (array) $_POST['disable'])
            : [];
        $disable_theme = !empty($_POST['disable_theme']);
        $duration = (int) ($_POST['duration'] ?? Phpinfo_WP_Safemode::DEFAULT_DURATION);
        $result = Phpinfo_WP_Safemode::start($disabled, $disable_theme, $duration);
        $action = 'started';
        $start_result = $result;
        // Redirect so the new cookie/transient applies on the next request
        wp_safe_redirect(add_query_arg(['safemode' => 'started'], admin_url('admin.php?page=piwp-safemode')));
        exit;
    }
    if (isset($_POST['phpinfowp_safemode_stop']) && check_admin_referer('phpinfowp_safemode_stop_nonce')) {
        Phpinfo_WP_Safemode::stop();
        wp_safe_redirect(add_query_arg(['safemode' => 'stopped'], admin_url('admin.php?page=piwp-safemode')));
        exit;
    }
    if (isset($_POST['phpinfowp_safemode_remove_mu']) && check_admin_referer('phpinfowp_safemode_remove_mu_nonce')) {
        $ok = Phpinfo_WP_Safemode::uninstall_mu_plugin();
        $notice = $ok ? 'mu-plugin removed.' : 'Could not remove mu-plugin (check file permissions).';
    }
}

if (isset($_GET['safemode']) && $_GET['safemode'] === 'started') $notice = 'Troubleshooting Mode engaged — only your admin session sees plugins disabled. The site is normal for other visitors.';
if (isset($_GET['safemode']) && $_GET['safemode'] === 'stopped') $notice = 'Troubleshooting Mode ended. All plugins restored.';

$is_pro = Phpinfo_WP_License::is_valid();
$show_success_modal = isset($_GET['safemode']) && $_GET['safemode'] === 'stopped' && !$is_pro;
if ($show_success_modal) {
    $notice = '';
}

$session     = Phpinfo_WP_Safemode::current_session();
$is_active   = $session !== null;
$mu_present  = Phpinfo_WP_Safemode::mu_plugin_installed();

if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
$all_plugins    = get_plugins();
$active_plugins = (array) get_option('active_plugins', []);
?>

<div class="phpinfowp-info-page phpinfowp-safemode">
    <style>
    /* Safe mode wrapper classes */
    .phpinfowp-safemode {
        max-width: 1000px;
        padding-top: 20px;
    }

    /* Custom Table Styles */
    .phpinfowp-table {
        width: 100%;
        border-collapse: collapse;
        margin: 16px 0 24px;
        background: #fff;
        border: 1px solid #dcdcde;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: none;
    }
    .phpinfowp-table th {
        background: #f8fafc;
        color: #666;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 14px;
        text-align: left;
        border-bottom: 1px solid #dcdcde;
    }
    .phpinfowp-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #f0f0f1;
        font-size: 13px;
        color: #333;
        vertical-align: middle;
    }
    .phpinfowp-table tr:last-child td {
        border-bottom: none;
    }
    .phpinfowp-table tr:hover td {
        background: #f8fafc;
    }

    /* Badge styles */
    .phpinfowp-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .phpinfowp-badge-success {
        background: #d1fae5;
        color: #065f46;
    }
    .phpinfowp-badge-neutral {
        background: #f1f5f9;
        color: #475569;
    }

    /* Custom alert banner style */
    .phpinfowp-custom-alert {
        margin-bottom: 20px;
        padding: 12px 16px;
        border-radius: 6px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }



    /* Styled buttons */
    .phpinfowp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        border: 1px solid transparent;
        text-decoration: none;
        line-height: 1.4;
    }
    .phpinfowp-btn-primary {
        background: #777BB3;
        color: #fff;
        border-color: #696da6;
    }
    .phpinfowp-btn-primary:hover {
        background: #696da6;
        color: #fff;
    }
    .phpinfowp-btn-primary:active {
        background: #595d92;
    }
    .phpinfowp-btn-primary:disabled {
        background: #cbd5e1;
        border-color: #cbd5e1;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .phpinfowp-btn-secondary {
        background: #fff;
        color: #334155;
        border-color: #cbd5e1;
    }
    .phpinfowp-btn-secondary:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
    }

    .phpinfowp-btn-danger {
        background: #ef4444;
        color: #fff;
        border-color: #ef4444;
    }
    .phpinfowp-btn-danger:hover {
        background: #dc2626;
        border-color: #dc2626;
        color: #fff;
    }

    /* Pulse Animation */
    @keyframes phpinfowp-pulse {
        0% { transform: scale(0.95); opacity: 0.5; }
        50% { transform: scale(1.15); opacity: 1; }
        100% { transform: scale(0.95); opacity: 0.5; }
    }
    .phpinfowp-pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        display: inline-block;
        animation: phpinfowp-pulse 2s infinite ease-in-out;
    }
    </style>

    <div class="phpinfowp-page-header">
        <div>
            <h1><?php _e('Troubleshooting Mode', 'phpinfo-wp'); ?></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Safely disable plugins or revert to a default theme <strong>just for your own admin session</strong> to debug
                conflicts. Visitors see the site normally. Everything is reversible — no plugin is ever deactivated in the
                database. Session expires automatically; you can also exit any time.', 'phpinfo-wp'); ?></p>
        </div>
    </div>

    <!-- Notices bar -->
    <?php if ($notice): ?>
        <div class="phpinfowp-custom-alert" style="background:#f0fdf4; border:1px solid #bbf7d0; border-left:4px solid #22c55e;">
            <span class="dashicons dashicons-yes" style="color: #22c55e; font-size: 20px; width: 20px; height: 20px; flex-shrink: 0;"></span>
            <p style="color: #166534; font-weight: 500;"><?php echo esc_html($notice); ?></p>
        </div>
    <?php endif; ?>

    <!-- Switch rendering based on active status -->
    <?php if ($is_active): ?>
        <?php
            $remaining = max(0, $session['expires'] - time());
            $mins      = (int) floor($remaining / 60);
            $secs      = $remaining % 60;
            $disabled  = (array) ($session['disabled_plugins'] ?? []);
        ?>
        
        <!-- Active Troubleshooting Mode Panel -->
        <div style="background: linear-gradient(135deg, #fef3c7, #fffbeb); border: 1px solid #fde047; border-left: 5px solid #eab308; border-radius: 10px; padding: 24px; margin-bottom: 28px; box-shadow: 0 4px 15px rgba(234, 179, 8, 0.08);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; color: #854d0e; font-size: 16px; font-weight: 800;">
                        <span class="phpinfowp-pulse-dot" style="background: #eab308; width: 8px; height: 8px;"></span>
                        Troubleshooting Mode is Active
                    </div>
                    <p style="margin: 8px 0 0; color: #713f12; font-size: 13.5px; line-height: 1.5;">
                        We've disabled <strong><?php echo count($disabled); ?> plugin<?php echo count($disabled) === 1 ? '' : 's'; ?></strong> just for your current administrator session. 
                        <?php if (!empty($session['disable_theme'])): ?>Additionally, the default theme is temporarily active.<?php endif; ?>
                        All other website visitors will see your site active and normal with no plugins disabled.
                    </p>
                </div>
                <div style="text-align: right; background: #fff; padding: 12px 20px; border-radius: 8px; border: 1px solid #fef08a; box-shadow: 0 2px 6px rgba(0,0,0,0.02); min-width: 120px;">
                    <div id="phpinfowp-safemode-countdown"
                         data-expires="<?php echo esc_attr($session['expires']); ?>"
                         style="font-size: 26px; font-weight: 800; color: #854d0e; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; letter-spacing: -0.5px;">
                        <?php printf('%d:%02d', $mins, $secs); ?>
                    </div>
                    <div style="font-size: 10px; color: #a16207; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;"><?php _e('remaining', 'phpinfo-wp'); ?></div>
                </div>
            </div>

            <form method="post" style="margin-top: 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <?php wp_nonce_field('phpinfowp_safemode_stop_nonce'); ?>
                <input type="hidden" name="phpinfowp_safemode_stop" value="1">
                <button type="submit" class="phpinfowp-btn phpinfowp-btn-danger" style="padding: 10px 20px;">
                    <span class="dashicons dashicons-dismiss" style="font-size:16px; width:16px; height:16px; margin-top:1px;"></span>
                    End Troubleshooting Session
                </button>
                <a href="<?php echo esc_url(admin_url()); ?>" class="phpinfowp-btn phpinfowp-btn-secondary" style="padding: 10px 18px;">
                    Browse Site Admin →
                </a>
            </form>
        </div>

        <h2 class="phpinfowp-section-heading"><?php _e('Plugins disabled in this session', 'phpinfo-wp'); ?></h2>
        
        <table class="phpinfowp-table" style="max-width:780px">
            <thead>
                <tr>
                    <th><?php _e('Plugin', 'phpinfo-wp'); ?></th>
                    <th style="width:200px"><?php _e('Status in DB', 'phpinfo-wp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($disabled as $slug): $info = $all_plugins[$slug] ?? null; ?>
                    <tr>
                        <td>
                            <strong style="color: #0f172a; font-size: 13.5px;"><?php echo esc_html($info['Name'] ?? $slug); ?></strong>
                            <div style="font-size:11px; color:#64748b; font-family:monospace; margin-top:2px;"><?php echo esc_html($slug); ?></div>
                        </td>
                        <td>
                            <span style="color:#0f9d58; font-weight:700; display:flex; align-items:center; gap:4px;">
                                <span class="phpinfowp-pulse-dot" style="background:#0f9d58;"></span>
                                Active in database
                            </span>
                            <span style="color:#64748b; font-size:11px; display:block; margin-top:2px;">(hidden from your session view only)</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        (function() {
            var el = document.getElementById('phpinfowp-safemode-countdown');
            if (!el) return;
            var expires = parseInt(el.dataset.expires, 10);
            function tick() {
                var remaining = Math.max(0, expires - Math.floor(Date.now() / 1000));
                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                if (remaining <= 0) {
                    el.textContent = 'expired';
                    setTimeout(function() { window.location.reload(); }, 1500);
                    return;
                }
                setTimeout(tick, 1000);
            }
            tick();
        })();
        </script>

    <?php else: ?>

        <!-- Difference Feature Grid -->
        <div class="phpinfowp-diff-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; margin-bottom:28px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
            <div style="font-size:16px; font-weight:700; color:#0f172a; margin-bottom:18px; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-info" style="color:#777BB3; font-size:20px; width:20px; height:20px;"></span>
                How this differs from the official Health Check plugin
            </div>
            <div class="phpinfowp-diff-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:18px;">
                <!-- Item 1 -->
                <div class="phpinfowp-diff-item" style="border:1px solid #f1f5f9; background:#f8fafc; border-radius:8px; padding:16px; transition: all 0.2s ease;">
                    <div style="display:flex; align-items:center; gap:8px; font-weight:700; color:#1e293b; font-size:13px; margin-bottom:6px;">
                        <span class="dashicons dashicons-shield-alt" style="color:#10b981; font-size:16px; width:16px; height:16px;"></span>
                        Safe DB Sandbox
                    </div>
                    <div style="font-size:12.5px; color:#64748b; line-height:1.5;">
                        The actual <code>active_plugins</code> option is never modified — your site cannot be left broken.
                    </div>
                </div>
                <!-- Item 2 -->
                <div class="phpinfowp-diff-item" style="border:1px solid #f1f5f9; background:#f8fafc; border-radius:8px; padding:16px; transition: all 0.2s ease;">
                    <div style="display:flex; align-items:center; gap:8px; font-weight:700; color:#1e293b; font-size:13px; margin-bottom:6px;">
                        <span class="dashicons dashicons-lock" style="color:#3b82f6; font-size:16px; width:16px; height:16px;"></span>
                        Session Bound
                    </div>
                    <div style="font-size:12.5px; color:#64748b; line-height:1.5;"><?php _e('Cookie is bound to your user account, time-limited, and expires automatically.', 'phpinfo-wp'); ?></div>
                </div>
                <!-- Item 3 -->
                <div class="phpinfowp-diff-item" style="border:1px solid #f1f5f9; background:#f8fafc; border-radius:8px; padding:16px; transition: all 0.2s ease;">
                    <div style="display:flex; align-items:center; gap:8px; font-weight:700; color:#1e293b; font-size:13px; margin-bottom:6px;">
                        <span class="dashicons dashicons-dismiss" style="color:#ef4444; font-size:16px; width:16px; height:16px;"></span>
                        No Lockouts
                    </div>
                    <div style="font-size:12.5px; color:#64748b; line-height:1.5;"><?php _e('Every page has an explicit <em>End</em> button. You cannot lock yourself out.', 'phpinfo-wp'); ?></div>
                </div>
                <!-- Item 4 -->
                <div class="phpinfowp-diff-item" style="border:1px solid #f1f5f9; background:#f8fafc; border-radius:8px; padding:16px; transition: all 0.2s ease;">
                    <div style="display:flex; align-items:center; gap:8px; font-weight:700; color:#1e293b; font-size:13px; margin-bottom:6px;">
                        <span class="dashicons dashicons-groups" style="color:#8b5cf6; font-size:16px; width:16px; height:16px;"></span>
                        Zero Visitor Impact
                    </div>
                    <div style="font-size:12.5px; color:#64748b; line-height:1.5;"><?php _e('Other visitors are unaffected — they see the live site with all plugins active.', 'phpinfo-wp'); ?></div>
                </div>
            </div>
        </div>

        <!-- Installation Status warning helper -->
        <?php if (!$mu_present): ?>
            <div class="phpinfowp-custom-alert" style="background:#eff6ff; border:1px solid #bfdbfe; border-left:4px solid #3b82f6; border-radius:8px; padding:16px 20px; margin-bottom:24px; display:flex; gap:12px; align-items:flex-start;">
                <span class="dashicons dashicons-info-outline" style="color:#3b82f6; font-size:20px; width:20px; height:20px; flex-shrink:0; margin-top:2px;"></span>
                <div>
                    <strong style="color:#1e3a8a; font-size:13.5px; display:block; margin-bottom:4px;"><?php _e('Request-Level Isolation Recommended', 'phpinfo-wp'); ?></strong>
                    <p style="margin:0; font-size:13px; color:#1e40af; line-height:1.5;">
                        The optional <code>mu-plugin</code> helper isn't installed yet. Without it, disabled plugins are only hidden in the <em>admin panel and AJAX requests</em>, but will run normally on frontend pages. 
                        Engaging Troubleshooting Mode will attempt to install it automatically; if your directory is write-protected, it will fallback to admin-only isolation.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="phpinfowp-custom-alert" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:14px 18px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:18px; width:18px; height:18px;"></span>
                    <span style="font-size:13px; color:#166534; font-weight:600;">
                        ✓ Full Request-Level Isolation Available
                    </span>
                    <span style="font-size:12px; color:#4b5563; font-family:monospace; background:#f3f4f6; padding:2px 6px; border-radius:4px; border:1px solid #e5e7eb; margin-left:4px;">
                        mu-plugins/<?php echo esc_html(Phpinfo_WP_Safemode::MU_FILE); ?>
                    </span>
                </div>
                <form method="post" style="display:inline; margin:0;">
                    <?php wp_nonce_field('phpinfowp_safemode_remove_mu_nonce'); ?>
                    <input type="hidden" name="phpinfowp_safemode_remove_mu" value="1">
                    <button type="submit" class="phpinfowp-btn phpinfowp-btn-danger" style="padding:4px 10px; font-size:11.5px; border-radius:4px;"
                            onclick="return confirm('Remove the helper mu-plugin? Troubleshooting Mode will fallback to admin-only isolation.')"><?php _e('Uninstall Helper', 'phpinfo-wp'); ?></button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Form Setup -->
        <form method="post">
            <?php wp_nonce_field('phpinfowp_safemode_start_nonce'); ?>
            <input type="hidden" name="phpinfowp_safemode_start" value="1">

            <h2 class="phpinfowp-section-heading"><?php _e('Pick what to disable', 'phpinfo-wp'); ?></h2>

            <div style="margin:8px 0 16px">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600; font-size:13px; color:#1e293b; cursor:pointer;">
                    <input type="checkbox" id="phpinfowp-toggle-all" onchange="phpinfowpToggleAll(this.checked)">
                    Select all active plugins
                </label>
            </div>

            <!-- Sleek Table list of plugins -->
            <table class="phpinfowp-table" style="max-width:780px">
                <thead>
                    <tr>
                        <th style="width:40px"></th>
                        <th><?php _e('Plugin', 'phpinfo-wp'); ?></th>
                        <th style="width:140px"><?php _e('Version', 'phpinfo-wp'); ?></th>
                        <th style="width:120px"><?php _e('Status', 'phpinfo-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_plugins as $slug => $info):
                        $is_active = in_array($slug, $active_plugins, true);
                    ?>
                        <tr class="<?php echo $is_active ? 'active-row' : 'inactive-row'; ?>" style="<?php echo $is_active ? '' : 'opacity:.55'; ?>">
                            <td>
                                <input type="checkbox" name="disable[]" value="<?php echo esc_attr($slug); ?>"
                                       class="phpinfowp-disable-checkbox"
                                       <?php disabled(!$is_active); ?>>
                            </td>
                            <td>
                                <strong style="color: #0f172a; font-size: 13.5px;"><?php echo esc_html($info['Name']); ?></strong>
                                <div style="font-size:11px; color:#64748b; font-family:monospace; margin-top:2px;"><?php echo esc_html($slug); ?></div>
                            </td>
                            <td style="font-family:monospace; font-size:12px; color:#475569;"><?php echo esc_html($info['Version']); ?></td>
                            <td>
                                <?php if ($is_active): ?>
                                    <span class="phpinfowp-badge phpinfowp-badge-success" style="padding: 2px 6px;"><?php _e('Active', 'phpinfo-wp'); ?></span>
                                <?php else: ?>
                                    <span class="phpinfowp-badge phpinfowp-badge-neutral" style="padding: 2px 6px;"><?php _e('Inactive', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Theme Card -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:20px; box-shadow:0 1px 2px rgba(0,0,0,0.01); max-width:780px;">
                <h2 style="margin:0 0 10px; font-size:15px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-admin-appearance" style="color:#777BB3; font-size:18px; width:18px; height:18px;"></span>
                    Theme Isolation
                </h2>
                <p style="margin:0 0 12px; font-size:12.5px; color:#64748b; line-height:1.4;"><?php _e('Revert your session to a default theme to verify if a layout conflict is originating from your current theme.', 'phpinfo-wp'); ?></p>
                <label style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px; color:#1e293b; cursor:pointer;">
                    <input type="checkbox" name="disable_theme" value="1">
                    Also revert to default WordPress theme (<?php echo esc_html(defined('WP_DEFAULT_THEME') && WP_DEFAULT_THEME ? WP_DEFAULT_THEME : 'twentytwentyfour'); ?>)
                </label>
            </div>

            <!-- Duration Select -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:24px; box-shadow:0 1px 2px rgba(0,0,0,0.01); max-width:780px;">
                <h2 style="margin:0 0 10px; font-size:15px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-clock" style="color:#777BB3; font-size:18px; width:18px; height:18px;"></span>
                    Session Duration
                </h2>
                <p style="margin:0 0 12px; font-size:12.5px; color:#64748b; line-height:1.4;"><?php _e('Select how long you want Troubleshooting Mode to remain active before automatically expiring.', 'phpinfo-wp'); ?></p>
                <select name="duration" style="min-width:240px; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px; background:#fff; color:#334155; outline:none;">
                    <option value="900"><?php _e('15 minutes', 'phpinfo-wp'); ?></option>
                    <option value="1800"><?php _e('30 minutes', 'phpinfo-wp'); ?></option>
                    <option value="3600" selected><?php _e('1 hour (recommended)', 'phpinfo-wp'); ?></option>
                    <option value="7200"><?php _e('2 hours', 'phpinfo-wp'); ?></option>
                    <option value="14400"><?php _e('4 hours (max)', 'phpinfo-wp'); ?></option>
                </select>
            </div>

            <!-- Start Trigger action -->
            <div style="display:flex; align-items:center; gap:16px; margin-top:24px;">
                <button type="submit" class="phpinfowp-btn phpinfowp-btn-primary" style="padding:12px 24px; font-size:14px; border-radius:6px; height:auto;" id="phpinfowp-start-btn" disabled>
                    <span class="dashicons dashicons-shield-alt" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                    Engage Troubleshooting Mode
                </button>
                <span id="phpinfowp-btn-helper" style="color:#94a3b8; font-size:12.5px;"><?php _e('Select at least one plugin to enable the button', 'phpinfo-wp'); ?></span>
            </div>
        </form>

        <script>
        function phpinfowpToggleAll(checked) {
            document.querySelectorAll('.phpinfowp-disable-checkbox:not(:disabled)').forEach(function(cb) {
                cb.checked = checked;
            });
            phpinfowpUpdateStartBtn();
        }
        function phpinfowpUpdateStartBtn() {
            var checked = document.querySelectorAll('.phpinfowp-disable-checkbox:checked').length > 0;
            var btn = document.getElementById('phpinfowp-start-btn');
            var helper = document.getElementById('phpinfowp-btn-helper');
            btn.disabled = !checked;
            if (checked) {
                helper.textContent = "Ready to engage.";
                helper.style.color = "#10b981";
            } else {
                helper.textContent = "Select at least one plugin to enable the button";
                helper.style.color = "#94a3b8";
            }
        }
        document.querySelectorAll('.phpinfowp-disable-checkbox').forEach(function(cb) {
            cb.addEventListener('change', phpinfowpUpdateStartBtn);
        });
        </script>

    <?php endif; ?>
</div>

<?php if ($show_success_modal): ?>
<!-- Troubleshooting Mode Success Modal -->
<div id="phpinfowp-success-modal" class="phpinfowp-success-modal" role="dialog" aria-modal="true" aria-labelledby="phpinfowp-success-modal-title">
    <div class="phpinfowp-success-modal-content">
        <div style="width:54px; height:54px; border-radius:50%; background:rgba(34, 197, 94, 0.12); display:flex; align-items:center; justify-content:center; margin:0 auto 18px;">
            <span class="dashicons dashicons-yes" style="font-size:32px; width:32px; height:32px; color:#22c55e;"></span>
        </div>
        <h2 id="phpinfowp-success-modal-title" style="margin: 0 0 10px; font-size: 20px; font-weight: 600; color: #1d2327;"><?php _e('Conflict resolved!', 'phpinfo-wp'); ?></h2>
        <p style="margin: 0 0 24px; font-size: 13.5px; color: #475569; line-height: 1.5; text-align: center;">
            <?php _e('Pro\'s Update Guard automatically intercepts updates and warns you about version incompatibilities before you activate them. Never experience another broken dashboard.', 'phpinfo-wp'); ?>
        </p>
        <div style="display:flex; justify-content:center; gap:12px;">
            <button type="button" class="button phpinfowp-success-modal-close" style="height:40px; line-height:38px; font-size:13.5px; font-weight:600; padding:0 20px; border-radius:6px;"><?php _e('Dismiss', 'phpinfo-wp'); ?></button>
            <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary" style="background:#777BB3; border-color:#777BB3; height:40px; line-height:38px; font-size:13.5px; font-weight:600; padding:0 20px; border-radius:6px; text-decoration:none; display:inline-block;"><?php _e('Explore Pro Security', 'phpinfo-wp'); ?></a>
        </div>
    </div>
</div>

<style>
.phpinfowp-success-modal {
    display: none;
    position: fixed;
    z-index: 999999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.phpinfowp-success-modal.is-open {
    display: flex;
    opacity: 1;
}
.phpinfowp-success-modal-content {
    background-color: #fff;
    padding: 36px 30px;
    border: 1px solid #e2e8f0;
    width: 90%;
    max-width: 500px;
    border-radius: 10px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
    transform: scale(0.95);
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.phpinfowp-success-modal.is-open .phpinfowp-success-modal-content {
    transform: scale(1);
}
</style>

<script>
jQuery(document).ready(function($) {
    var $modal = $('#phpinfowp-success-modal');
    setTimeout(function() {
        $modal.addClass('is-open');
    }, 150);
    
    $(document).on('click', '.phpinfowp-success-modal-close, .phpinfowp-success-modal', function(e) {
        if ($(e.target).hasClass('phpinfowp-success-modal') || $(e.target).hasClass('phpinfowp-success-modal-close')) {
            $modal.removeClass('is-open');
            if (window.history && window.history.replaceState) {
                var url = window.location.href.split('?')[0] + '?page=piwp-safemode';
                window.history.replaceState({}, document.title, url);
            }
        }
    });
});
</script>
<?php endif; ?>
