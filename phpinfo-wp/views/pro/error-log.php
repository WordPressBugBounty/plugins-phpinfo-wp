<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) {
    phpinfowp_render_feature_lock([
        'feature'  => 'PHP Error Log Viewer',
        'icon'     => 'dashicons-warning',
        'tagline'  => 'Browse, filter, and clear your PHP error log — without FTP, SSH, or asking your host.',
        'previews' => [
            '<strong>[—] PHP Warning:</strong> Undefined variable $foo in /wp-content/…',
            '<strong>[—] PHP Deprecated:</strong> strpos() expects string …',
            '<strong>[—] PHP Fatal error:</strong> Allowed memory size exhausted …',
        ],
    ]);
    return;
}

$message  = '';
$msg_type = 'success';

if (isset($_POST['phpinfowp_log_action']) && check_admin_referer('phpinfowp_log_nonce')) {
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

        <div class="phpinfowp-log-toolbar">
            <div class="phpinfowp-log-meta">
                <strong><?php _e('File:', 'phpinfo-wp'); ?></strong> <code><?php echo esc_html($path); ?></code>
                &nbsp;&middot;&nbsp; <strong><?php _e('Size:', 'phpinfo-wp'); ?></strong> <?php echo esc_html($size); ?>
                &nbsp;&middot;&nbsp; <strong><?php _e('Showing:', 'phpinfo-wp'); ?></strong> last <?php echo count($lines); ?> lines
            </div>
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
        </div>

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
</div>
