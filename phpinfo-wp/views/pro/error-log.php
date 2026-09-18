<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;
$message         = '';
$msg_type        = 'success';

$available_logs = Phpinfo_WP_Error_Log::get_available_logs();
$active_key     = Phpinfo_WP_Error_Log::get_active_log_key();
$path           = Phpinfo_WP_Error_Log::get_active_log_path();

if ($is_pro && isset($_POST['phpinfowp_log_action']) && check_admin_referer('phpinfowp_log_nonce')) {
    $action = sanitize_key($_POST['phpinfowp_log_action']);
    if ($action === 'clear') {
        $clear_stream = !empty($_POST['clear_stream']) ? sanitize_key($_POST['clear_stream']) : $active_key;
        $clear_path   = $available_logs[$clear_stream]['path'] ?? $path;
        if ($clear_path && Phpinfo_WP_Error_Log::clear($clear_path)) {
            $stream_label = $available_logs[$clear_stream]['label'] ?? __('Log file', 'phpinfo-wp');
            $message = sprintf(__('%s cleared.', 'phpinfo-wp'), '<strong>' . esc_html($stream_label) . '</strong>');
        } else {
            $message  = __('Could not clear the log file (check file permissions).', 'phpinfo-wp');
            $msg_type = 'error';
        }
    } elseif ($action === 'enable') {
        $res = Phpinfo_WP_Error_Log::enable_logging();
        $message  = $res['message'];
        $msg_type = $res['success'] ? 'success' : 'error';
        $path     = WP_CONTENT_DIR . '/debug.log';
        $active_key = 'wp';
    } elseif ($action === 'disable') {
        $res = Phpinfo_WP_Error_Log::disable_logging();
        $message  = $res['message'];
        $msg_type = $res['success'] ? 'success' : 'error';
    }
}

// Refresh data after action
$available_logs = Phpinfo_WP_Error_Log::get_available_logs();
$path           = $path ?: Phpinfo_WP_Error_Log::get_active_log_path();
$active_key     = Phpinfo_WP_Error_Log::get_active_log_key();
$is_logging_on  = Phpinfo_WP_Error_Log::is_logging_enabled();
$lines          = $path ? Phpinfo_WP_Error_Log::tail($path) : [];
$size           = $path ? Phpinfo_WP_Error_Log::format_bytes(Phpinfo_WP_Error_Log::size($path)) : '0 B';
$search         = sanitize_text_field($_GET['log_search'] ?? '');
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1>
                <?php _e('PHP Error Log', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Browse, live stream, filter, and clear your PHP error log — without FTP, SSH, or host cPanels.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
            <?php if ($path): ?>
                <div style="margin:4px 0 0; font-size:12px; color:#64748b;">
                    <?php printf(__('Active log: %s &middot; %s', 'phpinfo-wp'), '<code style="font-size:11px; color:#1e293b; background:#f1f5f9; padding:2px 6px; border-radius:4px;">' . esc_html(Phpinfo_WP_Error_Log::mask_path($path)) . '</code>', '<strong>' . esc_html($size) . '</strong>'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($path && $is_pro): ?>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="text" id="phpinfowp-log-search" placeholder="<?php echo esc_attr__('Filter lines...', 'phpinfo-wp'); ?>"
                       value="<?php echo esc_attr($search); ?>" class="regular-text" style="max-width:240px; margin:0;"
                       oninput="phpinfowpFilterLog(this.value)">
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field('phpinfowp_log_nonce'); ?>
                    <?php if ($is_logging_on): ?>
                        <input type="hidden" name="phpinfowp_log_action" value="disable">
                        <button type="submit" class="button button-secondary" style="height:32px; line-height:30px;">
                            <?php _e('Disable WP Logging', 'phpinfo-wp'); ?>
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="phpinfowp_log_action" value="enable">
                        <button type="submit" class="button button-primary" style="background:#6366f1; border-color:#6366f1; height:32px; line-height:30px; font-weight:600;">
                            <?php _e('Enable WP Logging', 'phpinfo-wp'); ?>
                        </button>
                    <?php endif; ?>
                </form>
                <form method="post" style="margin:0;"
                      data-confirm="<?php esc_attr_e('Clear the entire error log file? This cannot be undone.', 'phpinfo-wp'); ?>"
                      data-confirm-title="<?php esc_attr_e('Clear Error Log', 'phpinfo-wp'); ?>"
                      data-confirm-btn="<?php esc_attr_e('Clear Log File', 'phpinfo-wp'); ?>">
                    <?php wp_nonce_field('phpinfowp_log_nonce'); ?>
                    <input type="hidden" name="phpinfowp_log_action" value="clear">
                    <input type="hidden" name="clear_stream" value="<?php echo esc_attr($active_key); ?>">
                    <button type="submit" class="button button-secondary"><?php _e('Clear Log', 'phpinfo-wp'); ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if (count($available_logs) > 1): ?>
        <!-- Multi-Log Stream Switcher -->
        <div style="display:flex; align-items:center; gap:8px; margin:0 0 16px; padding:6px 10px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; flex-wrap:wrap;">
            <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-right:4px;">
                <span class="dashicons dashicons-database" style="font-size:14px; width:14px; height:14px; vertical-align:middle; margin-top:-2px;"></span>
                <?php _e('Log Stream:', 'phpinfo-wp'); ?>
            </span>
            <?php foreach ($available_logs as $key => $info): 
                $is_current = ($key === $active_key || $info['path'] === $path);
                $tab_url    = add_query_arg('log', $key, remove_query_arg(['log_file']));
            ?>
                <a href="<?php echo esc_url($tab_url); ?>" 
                   style="display:inline-flex; align-items:center; gap:7px; padding:6px 13px; border-radius:6px; font-size:12.5px; font-weight:600; text-decoration:none; transition:all .15s ease; <?php echo $is_current ? 'background:#fff; color:#0f172a; border:1px solid #cbd5e1; box-shadow:0 1px 3px rgba(0,0,0,0.06);' : 'color:#64748b; border:1px solid transparent;'; ?>">
                    <span style="width:8px; height:8px; border-radius:50%; background:<?php echo $info['lines_count'] > 0 ? '#ef4444' : '#10b981'; ?>;"></span>
                    <span><?php echo esc_html($info['label']); ?></span>
                    <span style="font-size:11px; color:<?php echo $is_current ? '#334155' : '#64748b'; ?>; font-weight:600; background:<?php echo $is_current ? '#f1f5f9' : '#e2e8f0'; ?>; padding:1px 7px; border-radius:10px;">
                        <?php echo esc_html($info['size_human']); ?> &middot; <?php echo (int) $info['lines_count']; ?> <?php echo _n('line', 'lines', $info['lines_count'], 'phpinfo-wp'); ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        <script>
        (function() {
            if (window.history && window.history.replaceState && window.location.search.includes('log_file=')) {
                var cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('log_file');
                <?php if (!empty($active_key)): ?>
                cleanUrl.searchParams.set('log', <?php echo json_encode($active_key); ?>);
                <?php endif; ?>
                window.history.replaceState({}, '', cleanUrl.toString());
            }
        })();
        </script>
    <?php endif; ?>



    <?php if ($message): 
        $is_ok      = $msg_type === 'success';
        $bg_col     = $is_ok ? '#f0fdf4' : '#fef2f2';
        $border_col = $is_ok ? '#bbf7d0' : '#fecaca';
        $accent_col = $is_ok ? '#16a34a' : '#dc2626';
        $text_col   = $is_ok ? '#166534' : '#991b1b';
        $icon       = $is_ok ? 'dashicons-yes-alt' : 'dashicons-warning';
    ?>
        <div style="background:<?php echo $bg_col; ?>; border:1px solid <?php echo $border_col; ?>; border-left:4px solid <?php echo $accent_col; ?>; border-radius:8px; padding:12px 16px; margin:0 0 20px 0; display:flex; align-items:center; gap:10px; color:<?php echo $text_col; ?>; font-size:13.5px; font-weight:500; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            <span class="dashicons <?php echo $icon; ?>" style="color:<?php echo $accent_col; ?>; font-size:20px; width:20px; height:20px; flex-shrink:0;"></span>
            <div style="flex:1;"><?php echo esc_html($message); ?></div>
        </div>
    <?php endif; ?>

    <?php if (!$path):
        $diag = Phpinfo_WP_Error_Log::diagnose();
        $verdict_color = $diag['verdict'] === 'empty' ? '#00a32a' : ($diag['verdict'] === 'disabled' ? '#dba617' : '#d63638');
        $verdict_label = $diag['verdict'] === 'empty' ? 'No errors yet' : ($diag['verdict'] === 'disabled' ? 'Logging disabled' : 'Not found');
        $card_title    = ($diag['verdict'] === 'empty' || $is_logging_on) ? __('Logging Active — No Errors Recorded Yet', 'phpinfo-wp') : __('No Log File Discovered', 'phpinfo-wp');
    ?>
        <div class="phpinfowp-errlog-diag">
            <div class="phpinfowp-errlog-diag-head" style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
                <div>
                    <span class="phpinfowp-errlog-diag-pill" style="background:<?php echo $verdict_color; ?>20;color:<?php echo $verdict_color; ?>;border-color:<?php echo $verdict_color; ?>40">
                        <?php echo esc_html($verdict_label); ?>
                    </span>
                    <h2 style="margin:8px 0 4px;font-size:17px;color:#1d2327"><?php echo esc_html($card_title); ?></h2>
                </div>
                <div>
                    <?php if ($is_pro): ?>
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field('phpinfowp_log_nonce'); ?>
                            <?php if ($is_logging_on): ?>
                                <input type="hidden" name="phpinfowp_log_action" value="disable">
                                <button type="submit" class="button button-secondary" style="display:inline-flex; align-items:center; gap:6px; font-weight:600; padding:0 14px; height:34px; line-height:32px;">
                                    <span class="dashicons dashicons-no-alt" style="font-size:15px; width:15px; height:15px; color:#d63638; margin-top:2px;"></span>
                                    <?php _e('Disable Error Logging', 'phpinfo-wp'); ?>
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="phpinfowp_log_action" value="enable">
                                <button type="submit" class="button button-primary" style="display:inline-flex; align-items:center; gap:6px; background:#6366f1; border-color:#6366f1; font-weight:600; padding:0 16px; height:34px; line-height:32px; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                                    <span class="dashicons dashicons-shield" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span>
                                    <?php _e('Enable Error Logging', 'phpinfo-wp'); ?>
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-license')); ?>" class="button button-primary" style="display:inline-flex; align-items:center; gap:6px; background:#6366f1; border-color:#6366f1; font-weight:600; padding:0 14px; height:34px; line-height:32px; color:#fff; border-radius:6px; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                            <span class="dashicons dashicons-shield" style="font-size:15px; width:15px; height:15px; margin-top:2px;"></span>
                            <span><?php _e('Enable Error Logging', 'phpinfo-wp'); ?></span>
                            <span class="phpinfowp-pro-badge" style="background:#fff; color:#6366f1; font-size:10px; font-weight:700; padding:1px 5px; border-radius:3px; margin-left:3px;">PRO</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <p style="margin:6px 0 18px;color:#3c434a;line-height:1.5"><?php echo esc_html($diag['summary']); ?></p>

            <h3 style="font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#646970;margin:0 0 8px"><?php _e('WordPress debug constants', 'phpinfo-wp'); ?></h3>
            <table class="phpinfowp-errlog-diag-table">
                <tr>
                    <td><code>WP_DEBUG</code></td>
                    <td><?php echo $diag['constants']['WP_DEBUG'] ? '<span style="color:#00a32a">✓ on</span>' : '<span style="color:#d63638">✗ off</span>'; ?></td>
                    <td>Master switch — must be on for WP_DEBUG_LOG to work.</td>
                </tr>
                <tr>
                    <td><code>WP_DEBUG_LOG</code></td>
                    <td><?php echo $diag['constants']['WP_DEBUG_LOG'] ? '<span style="color:#00a32a">✓ on</span>' : '<span style="color:#d63638">✗ off</span>'; ?></td>
                    <td><?php
                        if ($diag['wp_debug_log_value'] && is_string($diag['wp_debug_log_value']) && $diag['wp_debug_log_value'] !== '1') {
                            echo 'Custom path: <code>' . esc_html(Phpinfo_WP_Error_Log::mask_path($diag['wp_debug_log_value'])) . '</code>';
                        } elseif ($diag['constants']['WP_DEBUG_LOG']) {
                            echo 'Standard path: <code>wp-content/debug.log</code>';
                        } else {
                            echo 'Errors are not written to a file.';
                        }
                    ?></td>
                </tr>
                <tr>
                    <td><code>WP_DEBUG_DISPLAY</code></td>
                    <td><?php echo $diag['constants']['WP_DEBUG_DISPLAY'] ? '<span style="color:#d63638">⚠ on</span>' : '<span style="color:#00a32a">✓ off</span>'; ?></td>
                    <td>Should be <strong>off</strong> in production so visitors don't see raw PHP errors.</td>
                </tr>
            </table>

            <h3 style="font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#646970;margin:18px 0 8px"><?php _e('Log locations checked', 'phpinfo-wp'); ?></h3>
            <table class="phpinfowp-errlog-diag-table">
                <?php foreach ($diag['checks'] as $c):
                    $is_target = ($c['status'] === 'target_pending');
                    $sym = ($c['status'] === 'found' || $is_target) ? '✓' : ($c['status'] === 'unreadable' ? '!' : '✗');
                    $col = ($c['status'] === 'found' || $is_target) ? '#00a32a' : ($c['status'] === 'unreadable' ? '#dba617' : '#a7aaad');
                ?>
                    <tr>
                        <td style="width:24px;text-align:center;color:<?php echo $col; ?>;font-weight:700"><?php echo $sym; ?></td>
                        <td>
                            <code style="font-size:11px;color:#3c434a"><?php echo esc_html(Phpinfo_WP_Error_Log::mask_path($c['path'])); ?></code>
                            <?php if ($is_target): ?>
                                <span style="background:#dcfce7; color:#15803d; font-size:10px; font-weight:700; padding:1px 6px; border-radius:3px; margin-left:6px;"><?php _e('ACTIVE DESTINATION', 'phpinfo-wp'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#646970;font-size:12px"><?php echo esc_html($c['note'] ?: ucfirst($c['status'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php else: ?>

        <?php if ($is_free_preview): ?>
            <!-- Free preview: contextual preview cards + centered upgrade card -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Background contextual preview cards (Terminal style) -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:8px;">
                    <?php
                    $preview_lines = [];
                    if (!empty($log_path) && file_exists($log_path) && is_readable($log_path)) {
                        $tail = Phpinfo_WP_Error_Log::tail($log_path, 3);
                        if (!empty($tail)) {
                            $preview_lines = $tail;
                        }
                    }
                    ?>
                    <div style="background:#0f172a; border:1px solid #1e293b; border-radius:8px; padding:14px 16px; font-family:monospace; font-size:11.5px; line-height:1.6; color:#94a3b8; display:flex; flex-direction:column; gap:8px;">
                        <?php if (!empty($preview_lines)): ?>
                            <?php foreach ($preview_lines as $pline): 
                                $is_fatal = stripos($pline, 'Fatal') !== false || stripos($pline, 'Error') !== false;
                                $is_warn  = stripos($pline, 'Warning') !== false || stripos($pline, 'Notice') !== false || stripos($pline, 'Deprecated') !== false;
                                $badge_bg = $is_fatal ? '#fee2e2' : ($is_warn ? '#fef3c7' : '#e0e7ff');
                                $badge_fg = $is_fatal ? '#dc2626' : ($is_warn ? '#d97706' : '#4338ca');
                                $badge_txt = $is_fatal ? 'PHP Fatal/Error:' : ($is_warn ? 'PHP Warning:' : 'PHP Log:');
                            ?>
                                <div>
                                    <span style="color:#64748b;">[<?php echo esc_html(wp_date('d-M-Y H:i:s')); ?> UTC]</span> 
                                    <span style="background:<?php echo $badge_bg; ?>; color:<?php echo $badge_fg; ?>; padding:1px 5px; border-radius:3px; font-weight:700; font-size:10.5px;"><?php echo $badge_txt; ?></span> 
                                    <span style="color:#e2e8f0; word-break:break-all;"><?php echo esc_html($pline); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div>
                                <span style="color:#64748b;">[<?php echo esc_html(wp_date('d-M-Y H:i:s')); ?> UTC]</span> 
                                <span style="background:#fef3c7; color:#d97706; padding:1px 5px; border-radius:3px; font-weight:700; font-size:10.5px;">PHP Deprecated:</span> 
                                <span style="color:#e2e8f0;">Creation of dynamic property is deprecated in <?php echo esc_html(WP_CONTENT_DIR); ?>/themes/functions.php on line 42</span>
                            </div>
                            <div>
                                <span style="color:#64748b;">[<?php echo esc_html(wp_date('d-M-Y H:i:s', time() - 45)); ?> UTC]</span> 
                                <span style="background:#fee2e2; color:#dc2626; padding:1px 5px; border-radius:3px; font-weight:700; font-size:10.5px;">PHP Fatal error:</span> 
                                <span style="color:#e2e8f0;">Maximum execution time of <?php echo (int) ini_get('max_execution_time') ?: 30; ?> seconds exceeded in <?php echo esc_html(ABSPATH); ?>wp-includes/class-wp-http-curl.php on line 124</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

                <!-- Floating Upgrade Card -->
                <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                    <span class="dashicons dashicons-media-text" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                    <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Live PHP Error Log Viewer Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                        <?php _e('Stream, filter, and diagnose PHP runtime errors, fatal crashes, and unhandled exceptions in real time without FTP.', 'phpinfo-wp'); ?>
                    </p>
                    <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🚨</span>
                            <span><strong><?php _e('Real-Time Fatal Crash Streaming:', 'phpinfo-wp'); ?></strong> <?php _e('Instantly see fatal errors, deprecation notices, and stack traces the moment they happen.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🔍</span>
                            <span><strong><?php _e('Instant Text &amp; Regex Search:', 'phpinfo-wp'); ?></strong> <?php _e('Filter thousands of log lines by plugin name, file path, error severity, or timestamp.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🧹</span>
                            <span><strong><?php _e('1-Click Log Truncation &amp; Rotation:', 'phpinfo-wp'); ?></strong> <?php _e('Safely wipe or download multi-gigabyte debug.log files without SSH or cPanel access.', 'phpinfo-wp'); ?></span>
                        </div>
                    </div>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                        <?php _e('Unlock Error Log Viewer with Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php else: ?>

            <div id="phpinfowp-log-viewer">
                <?php if (empty($lines)): ?>
                    <div style="padding:48px 24px; text-align:center; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                        <div style="width:52px; height:52px; border-radius:50%; background:#f0fdf4; color:#16a34a; display:inline-flex; align-items:center; justify-content:center; margin-bottom:14px;">
                            <span class="dashicons dashicons-yes-alt" style="font-size:28px; width:28px; height:28px;"></span>
                        </div>
                        <h3 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#0f172a;"><?php _e('Log File Active — No Errors Recorded', 'phpinfo-wp'); ?></h3>
                        <p style="margin:0; color:#64748b; font-size:13px; max-width:440px; margin-left:auto; margin-right:auto; line-height:1.5;">
                            <?php printf(__('Monitoring %s. Your site is running cleanly with zero logged PHP warnings or errors.', 'phpinfo-wp'), '<code>' . esc_html(basename($path)) . '</code>'); ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($lines as $line): ?><div class="log-line <?php echo esc_attr(Phpinfo_WP_Error_Log::classify($line)); ?>" data-line="<?php echo esc_attr(strtolower($line)); ?>"><?php echo esc_html($line); ?></div><?php endforeach; ?>
                <?php endif; ?>
            </div>

            <script>
            function phpinfowpFilterLog(q) {
                q = q.toLowerCase();
                document.querySelectorAll('#phpinfowp-log-viewer .log-line').forEach(function(el) {
                    el.style.display = (!q || el.dataset.line.includes(q)) ? '' : 'none';
                });
            }
            <?php if ($search): ?>
            phpinfowpFilterLog(<?php echo json_encode($search); ?>);
            <?php endif; ?>
            </script>

        <?php endif; ?>

    <?php endif; ?>
</div>
