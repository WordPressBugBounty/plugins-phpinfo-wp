<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;
$message         = '';
$msg_type        = 'success';

if ($is_pro && isset($_POST['phpinfowp_log_action']) && check_admin_referer('phpinfowp_log_nonce')) {
    if ($_POST['phpinfowp_log_action'] === 'clear') {
        $path = Phpinfo_WP_Error_Log::find_path();
        if ($path && Phpinfo_WP_Error_Log::clear($path)) {
            $message = 'Log file cleared.';
        } else {
            $message  = 'Could not clear the log file (check file permissions).';
            $msg_type = 'error';
        }
    }
}

$path   = Phpinfo_WP_Error_Log::find_path();
$lines  = $path ? Phpinfo_WP_Error_Log::tail($path, 200) : [];
$size   = $path ? Phpinfo_WP_Error_Log::format_bytes(Phpinfo_WP_Error_Log::size($path)) : '0 B';
$search = sanitize_text_field($_GET['log_search'] ?? '');
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1>PHP Error Log <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Browse, filter, and clear your PHP error log — without FTP, SSH, or asking your host.', 'phpinfo-wp'); ?></p>
        </div>
    </div>



    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if (!$path):
        $diag = Phpinfo_WP_Error_Log::diagnose();
        $verdict_color = $diag['verdict'] === 'empty' ? '#00a32a' : ($diag['verdict'] === 'disabled' ? '#dba617' : '#d63638');
        $verdict_label = $diag['verdict'] === 'empty' ? 'No errors yet' : ($diag['verdict'] === 'disabled' ? 'Logging disabled' : 'Not found');
    ?>
        <div class="phpinfowp-errlog-diag">
            <div class="phpinfowp-errlog-diag-head">
                <div>
                    <span class="phpinfowp-errlog-diag-pill" style="background:<?php echo $verdict_color; ?>20;color:<?php echo $verdict_color; ?>;border-color:<?php echo $verdict_color; ?>40">
                        <?php echo esc_html($verdict_label); ?>
                    </span>
                    <h2 style="margin:8px 0 4px;font-size:17px;color:#1d2327"><?php _e('No log file discovered', 'phpinfo-wp'); ?></h2>
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
                    <td>Writes errors to <code>wp-content/debug.log</code> (or a custom path if you set one).</td>
                </tr>
                <tr>
                    <td><code>WP_DEBUG_DISPLAY</code></td>
                    <td><?php echo $diag['constants']['WP_DEBUG_DISPLAY'] ? '<span style="color:#d63638">✗ on</span>' : '<span style="color:#00a32a">✓ off</span>'; ?></td>
                    <td><?php _e('Should be <strong>off</strong> in production — leaking errors to visitors is a security risk.', 'phpinfo-wp'); ?></td>
                </tr>
            </table>

            <h3 style="font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#646970;margin:18px 0 8px"><?php _e('Paths checked', 'phpinfo-wp'); ?></h3>
            <table class="phpinfowp-errlog-diag-table phpinfowp-errlog-diag-paths">
                <?php foreach ($diag['checks'] as $c):
                    $sym = $c['status'] === 'found' ? '✓' : ($c['status'] === 'unreadable' ? '!' : '✗');
                    $col = $c['status'] === 'found' ? '#00a32a' : ($c['status'] === 'unreadable' ? '#dba617' : '#a7aaad');
                ?>
                    <tr>
                        <td style="width:24px;text-align:center;color:<?php echo $col; ?>;font-weight:700"><?php echo $sym; ?></td>
                        <td><code style="font-size:11px;color:#3c434a"><?php echo esc_html($c['path']); ?></code></td>
                        <td style="color:#646970;font-size:12px"><?php echo esc_html($c['note'] ?: ucfirst($c['status'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <details style="margin-top:18px">
                <summary style="cursor:pointer;font-weight:600;color:#1d2327">Enable WordPress logging</summary>
                <p style="margin:8px 0 6px;color:#3c434a">Add to <code>wp-config.php</code> <em>above</em> the <code>/* That's all, stop editing! */</code> line:</p>
                <pre style="background:#f6f7f7;border:1px solid #e0e0e6;border-radius:6px;padding:12px 14px;font-size:12px;color:#1d2327;margin:0">define('WP_DEBUG',         true);
define('WP_DEBUG_LOG',     true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', '0');</pre>
                <p style="margin:10px 0 0;color:#646970;font-size:12px">The log file (<code>wp-content/debug.log</code>) only appears once an error has been logged. On a healthy site that may take days. Many managed hosts (SiteGround, Kinsta, WP Engine, Cloudways) also expose a log viewer in their control panel — that's a separate file from this one.</p>
            </details>
        </div>
    <?php else: ?>

        <!-- Toolbar / Meta info (Visible to all users) -->
        <div class="phpinfowp-log-toolbar">
            <div class="phpinfowp-log-meta">
                <strong><?php _e('File:', 'phpinfo-wp'); ?></strong> <code><?php echo esc_html($path); ?></code>
                &nbsp;&middot;&nbsp; <strong><?php _e('Size:', 'phpinfo-wp'); ?></strong> <?php echo esc_html($size); ?>
                &nbsp;&middot;&nbsp; <strong><?php _e('Recorded:', 'phpinfo-wp'); ?></strong> <?php echo count($lines); ?> lines
            </div>
            <?php if ($is_pro): ?>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input type="text" id="phpinfowp-log-search" placeholder="<?php echo esc_attr__('Filter lines...', 'phpinfo-wp'); ?>"
                           value="<?php echo esc_attr($search); ?>" class="regular-text"
                           oninput="phpinfowpFilterLog(this.value)">
                    <form method="post" onsubmit="return confirm('Clear the entire log file? This cannot be undone.')">
                        <?php wp_nonce_field('phpinfowp_log_nonce'); ?>
                        <input type="hidden" name="phpinfowp_log_action" value="clear">
                        <button type="submit" class="button button-secondary"><?php _e('Clear Log', 'phpinfo-wp'); ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_free_preview): ?>
            <!-- Free preview: compact skeleton rows + centered upgrade card (fits in single viewpoint) -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:10px; min-height:260px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Skeleton content -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; background:#1e1e1e; padding:16px; border-radius:6px; opacity:0.4;">
                    <?php for ($sk = 0; $sk < 4; $sk++): ?>
                    <div style="display:flex; gap:10px; margin-bottom:8px; align-items:center;">
                        <div style="height:10px; width:110px; background:#444; border-radius:3px;"></div>
                        <div style="height:10px; width:<?php echo [70, 90, 60, 80][$sk]; ?>px; background:<?php echo ['#d63638', '#dba617', '#569cd6', '#d63638'][$sk]; ?>; border-radius:3px;"></div>
                        <div style="height:10px; width:<?php echo [40, 55, 35, 50][$sk]; ?>%; background:#333; border-radius:3px;"></div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(246,249,252,0.4) 0%, rgba(246,249,252,0.95) 40%, #f6f9fc 100%); pointer-events:none;"></div>

                <!-- Upgrade card floating over skeleton -->
                <div style="position:relative; z-index:2; width:100%; max-width:480px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px 28px; box-shadow:0 6px 20px -4px rgba(0,0,0,0.07); text-align:center; margin:12px auto;">
                    <span class="dashicons dashicons-lock" style="font-size:30px; width:30px; height:30px; color:#777BB3; display:inline-block; margin-bottom:6px;"></span>
                    <h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#1d2327;"><?php _e('Live PHP Error Log Viewer Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 4px; font-size:13px; color:#475569; line-height:1.5; max-width:400px; margin-left:auto; margin-right:auto;">
                        <?php _e('Unlock real-time log streaming, full text & regex search filters, stack trace inspection, and 1-click log reset without FTP or SSH.', 'phpinfo-wp'); ?>
                    </p>
                    <p style="margin:0 0 16px; font-size:12px; color:#94a3b8;">
                        <?php _e('Your active log path and file size above are real — upgrade to stream and filter errors.', 'phpinfo-wp'); ?>
                    </p>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3; height:38px; line-height:36px; font-size:13.5px; font-weight:600; padding:0 22px; border-radius:6px; text-decoration:none; display:inline-block;">
                        <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php else: ?>

            <div id="phpinfowp-log-viewer">
                <?php if (empty($lines)): ?>
                    <p style="padding:20px;color:#666;text-align:center"><?php _e('Log is empty — no errors recorded.', 'phpinfo-wp'); ?></p>
                <?php else: ?>
                    <?php foreach ($lines as $line): ?>
                        <div class="log-line <?php echo esc_attr(Phpinfo_WP_Error_Log::classify($line)); ?>" data-line="<?php echo esc_attr(strtolower($line)); ?>">
                            <?php echo esc_html($line); ?>
                        </div>
                    <?php endforeach; ?>
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
