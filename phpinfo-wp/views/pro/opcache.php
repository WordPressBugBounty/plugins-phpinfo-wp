<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;
$message         = '';
$msg_type        = 'success';

if ($is_pro && isset($_POST['phpinfowp_opcache_reset']) && check_admin_referer('phpinfowp_opcache_nonce')) {
    $ok       = Phpinfo_WP_OPcache::reset();
    $message  = $ok ? 'OPcache cleared successfully.' : 'Could not clear OPcache — function not available.';
    $msg_type = $ok ? 'success' : 'error';
}

$s = Phpinfo_WP_OPcache::status();
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1>OPcache Dashboard <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Check OPcache memory usage, hit rates, and list cached scripts.', 'phpinfo-wp'); ?></p>
        </div>
    </div>



    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if (!Phpinfo_WP_OPcache::is_available()): ?>
        <div class="notice notice-warning inline">
            <p><strong><?php _e('OPcache is not available', 'phpinfo-wp'); ?></strong> on this server. Ask your host to enable the <code>opcache</code> PHP extension — it's free and can cut PHP CPU usage by 50–80%.</p>
        </div>
    <?php elseif (!$s || !$s['enabled']): ?>
        <div class="notice notice-warning inline">
            <p><strong><?php _e('OPcache extension is installed but disabled.', 'phpinfo-wp'); ?></strong> Enable it in <code>php.ini</code>: <code>opcache.enable=1</code></p>
        </div>
    <?php else: ?>

        <!-- Hit rate & Live Hero Cards (Visible to all users) -->
        <div class="phpinfowp-opcache-grid">
            <div class="phpinfowp-opcache-card phpinfowp-opcache-hitrate">
                <div class="phpinfowp-grade-circle <?php echo esc_attr(Phpinfo_WP_OPcache::hit_rate_class($s['hit_rate'] ?? 0)); ?>">
                    <?php echo $s['hit_rate'] !== null ? esc_html($s['hit_rate']) . '%' : 'N/A'; ?>
                </div>
                <div>
                    <h3 style="margin:0 0 4px"><?php _e('Hit Rate', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0;color:#666;font-size:13px">
                        <?php echo number_format($s['hits']); ?> hits /
                        <?php echo number_format($s['misses']); ?> misses
                    </p>
                    <?php if ($s['full']): ?>
                        <p style="color:#d63638;margin:6px 0 0;font-size:13px">
                            Cache is full — increase <code>opcache.memory_consumption</code>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="phpinfowp-opcache-card">
                <h3 style="margin-top:0"><?php _e('Memory Usage', 'phpinfo-wp'); ?></h3>
                <div class="phpinfowp-progress-bar-wrap">
                    <div class="phpinfowp-progress-bar" style="width:<?php echo min(100, $s['memory_pct']); ?>%;background:<?php echo $s['memory_pct'] > 85 ? '#d63638' : '#777BB3'; ?>"></div>
                </div>
                <p style="margin:6px 0 0;font-size:13px;color:#444">
                    Used: <strong><?php echo esc_html(Phpinfo_WP_OPcache::format_bytes($s['memory_used'])); ?></strong>
                    / Total: <?php echo esc_html(Phpinfo_WP_OPcache::format_bytes($s['memory_total'])); ?>
                    &nbsp;&middot;&nbsp; Wasted: <?php echo esc_html(Phpinfo_WP_OPcache::format_bytes($s['memory_wasted'])); ?>
                    (<?php echo round($s['wasted_pct'], 1); ?>%)
                </p>
            </div>

            <div class="phpinfowp-opcache-card">
                <h3 style="margin-top:0"><?php _e('Cached Scripts', 'phpinfo-wp'); ?></h3>
                <div class="phpinfowp-progress-bar-wrap">
                    <?php $script_pct = $s['max_scripts'] > 0 ? round($s['cached_scripts'] / $s['max_scripts'] * 100, 1) : 0; ?>
                    <div class="phpinfowp-progress-bar" style="width:<?php echo min(100, $script_pct); ?>%;background:<?php echo $script_pct > 90 ? '#d63638' : '#00a32a'; ?>"></div>
                </div>
                <p style="margin:6px 0 0;font-size:13px;color:#444">
                    <strong><?php echo number_format($s['cached_scripts']); ?></strong>
                    / <?php echo number_format($s['max_scripts']); ?> max
                    (<?php echo $script_pct; ?>%)
                </p>
                <?php if ($s['start_time']): ?>
                    <p style="margin:6px 0 0;font-size:12px;color:#666">
                        Running since: <?php echo esc_html(gmdate('Y-m-d H:i', $s['start_time'])); ?> UTC
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_free_preview): ?>
            <!-- Free preview: compact skeleton rows + centered upgrade card (fits in single viewpoint) -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:10px; min-height:260px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Skeleton content -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.5;">
                    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px; border-bottom:1px solid #f1f5f9; padding-bottom:6px;">
                            <div style="height:11px; width:30%; background:#cbd5e1; border-radius:4px;"></div>
                            <div style="height:11px; width:15%; background:#cbd5e1; border-radius:4px;"></div>
                        </div>
                        <?php for ($sk = 0; $sk < 3; $sk++): ?>
                        <div style="display:flex; justify-content:space-between; padding:6px 0;">
                            <div style="height:10px; width:<?php echo [45, 55, 40][$sk]; ?>%; background:#e2e8f0; border-radius:4px;"></div>
                            <div style="height:10px; width:<?php echo [12, 18, 10][$sk]; ?>%; background:#e2e8f0; border-radius:4px;"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(246,249,252,0.4) 0%, rgba(246,249,252,0.95) 40%, #f6f9fc 100%); pointer-events:none;"></div>

                <!-- Upgrade card floating over skeleton -->
                <div style="position:relative; z-index:2; width:100%; max-width:480px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px 28px; box-shadow:0 6px 20px -4px rgba(0,0,0,0.07); text-align:center; margin:12px auto;">
                    <span class="dashicons dashicons-lock" style="font-size:30px; width:30px; height:30px; color:#777BB3; display:inline-block; margin-bottom:6px;"></span>
                    <h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#1d2327;"><?php _e('OPcache Directives & Flush Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 4px; font-size:13px; color:#475569; line-height:1.5; max-width:400px; margin-left:auto; margin-right:auto;">
                        <?php _e('Unlock detailed directive audits, JIT acceleration metrics, memory waste analysis, and 1-click OPcache clearing.', 'phpinfo-wp'); ?>
                    </p>
                    <p style="margin:0 0 16px; font-size:12px; color:#94a3b8;">
                        <?php _e('Your live hit rate and memory stats above are real — upgrade to manage cache and optimize performance.', 'phpinfo-wp'); ?>
                    </p>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3; height:38px; line-height:36px; font-size:13.5px; font-weight:600; padding:0 22px; border-radius:6px; text-decoration:none; display:inline-block;">
                        <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php else: ?>

            <!-- Key directives -->
            <h2 style="margin-top:28px"><?php _e('Key Directives', 'phpinfo-wp'); ?></h2>
            <table class="wp-list-table widefat fixed striped" style="max-width:700px">
                <thead><tr><th><?php _e('Directive', 'phpinfo-wp'); ?></th><th><?php _e('Value', 'phpinfo-wp'); ?></th></tr></thead>
                <tbody>
                <?php
                $show = [
                    'opcache.enable', 'opcache.memory_consumption', 'opcache.max_accelerated_files',
                    'opcache.validate_timestamps', 'opcache.revalidate_freq', 'opcache.save_comments',
                    'opcache.enable_cli', 'opcache.jit', 'opcache.jit_buffer_size',
                ];
                foreach ($show as $d):
                    $val = $s['directives'][$d] ?? ini_get($d);
                    if ($val === false || $val === null) continue;
                ?>
                    <tr>
                        <td><code><?php echo esc_html($d); ?></code></td>
                        <td><?php echo esc_html((string)$val); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Reset button -->
            <?php
            $reset_disabled = !function_exists('opcache_reset') || strpos(ini_get('disable_functions'), 'opcache_reset') !== false;
            $restrict_api   = ini_get('opcache.restrict_api');
            $is_restricted  = $restrict_api && stripos(__FILE__, $restrict_api) !== 0;
            ?>
            <form method="post" style="margin-top:20px" onsubmit="return confirm('Clear OPcache? PHP will recompile all files on next request.')">
                <?php wp_nonce_field('phpinfowp_opcache_nonce'); ?>
                <input type="hidden" name="phpinfowp_opcache_reset" value="1">
                <button type="submit" class="button button-secondary" <?php if ($reset_disabled || $is_restricted) echo 'disabled'; ?>>Clear OPcache</button>
                <span style="margin-left:8px;font-size:12px;color:#666"><?php _e('Forces recompilation of all cached PHP files.', 'phpinfo-wp'); ?></span>
                <?php if ($reset_disabled): ?>
                    <p style="color:#d63638;font-size:12px;margin-top:8px">⚠️ <strong><?php _e('Clear OPcache disabled:', 'phpinfo-wp'); ?></strong> Your host has added <code>opcache_reset</code> to the PHP <code>disable_functions</code> list.</p>
                <?php elseif ($is_restricted): ?>
                    <p style="color:#d63638;font-size:12px;margin-top:8px">⚠️ <strong><?php _e('Clear OPcache disabled:', 'phpinfo-wp'); ?></strong> Your host has restricted OPcache API access via <code>opcache.restrict_api</code>.</p>
                <?php endif; ?>
            </form>

        <?php endif; ?>

    <?php endif; ?>
</div>
