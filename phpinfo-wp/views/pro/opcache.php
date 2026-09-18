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
            <h1>
                <?php _e('OPcache Dashboard', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Check OPcache memory usage, hit rates, configuration directives, and list cached scripts.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>



    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if (!Phpinfo_WP_OPcache::is_available()): ?>
        <div class="phpinfowp-action-banner" style="margin-top:20px;">
            <div>
                <strong style="font-size:14px; color:#2c2f48; display:flex; align-items:center; gap:6px;">
                    ⚡ <?php _e('OPcache is not available on this server', 'phpinfo-wp'); ?>
                </strong>
                <p style="margin:4px 0 0; color:#555870; font-size:12.5px; line-height:1.5;">
                    <?php _e('Ask your host to enable the <code>opcache</code> PHP extension — it is free and reduces PHP CPU load by 50%–80%.', 'phpinfo-wp'); ?>
                </p>
            </div>
            <div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-extensions')); ?>" class="button button-primary" style="background:#777BB3; border-color:#777BB3; color:#fff; font-weight:600; padding:6px 16px; border-radius:6px;">
                    <?php _e('View Extensions →', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>
    <?php elseif (!$s || !$s['enabled']): ?>
        <div class="phpinfowp-action-banner" style="margin-top:20px;">
            <div>
                <strong style="font-size:14px; color:#2c2f48; display:flex; align-items:center; gap:6px;">
                    ⚙️ <?php _e('OPcache is installed but disabled', 'phpinfo-wp'); ?>
                </strong>
                <p style="margin:4px 0 0; color:#555870; font-size:12.5px; line-height:1.5;">
                    <?php _e('Enable it in <code>php.ini</code> with <code>opcache.enable=1</code> and restart PHP-FPM / Apache.', 'phpinfo-wp'); ?>
                </p>
            </div>
            <div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-htaccess')); ?>" class="button button-primary" style="background:#777BB3; border-color:#777BB3; color:#fff; font-weight:600; padding:6px 16px; border-radius:6px;">
                    <?php _e('Open Config Editor →', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>
    <?php else: ?>

        <!-- Hit rate & Live Hero Cards (Visible to all users) -->
        <div class="phpinfowp-opcache-grid">
            
            <!-- Card 1: Hit Rate & Efficiency -->
            <div class="phpinfowp-opcache-card">
                <div>
                    <div class="phpinfowp-opcache-card-head">
                        <h3 class="phpinfowp-opcache-card-title">
                            <span>⚡</span> <?php _e('Hit Rate & Efficiency', 'phpinfo-wp'); ?>
                        </h3>
                        <?php 
                        $rate = $s['hit_rate'] ?? 0;
                        if ($rate >= 90) {
                            $pill_cls = 'phpinfowp-opcache-pill-green';
                            $pill_txt = __('Optimal', 'phpinfo-wp');
                        } elseif ($rate >= 70) {
                            $pill_cls = 'phpinfowp-opcache-pill-yellow';
                            $pill_txt = __('Good', 'phpinfo-wp');
                        } else {
                            $pill_cls = 'phpinfowp-opcache-pill-red';
                            $pill_txt = __('Low', 'phpinfo-wp');
                        }
                        ?>
                        <span class="phpinfowp-opcache-pill <?php echo $pill_cls; ?>"><?php echo esc_html($pill_txt); ?></span>
                    </div>

                    <div class="phpinfowp-opcache-card-metric" style="color:<?php echo $rate >= 90 ? '#059669' : ($rate >= 70 ? '#d97706' : '#dc2626'); ?>;">
                        <?php echo $s['hit_rate'] !== null ? esc_html($s['hit_rate']) . '%' : 'N/A'; ?>
                    </div>

                    <div class="phpinfowp-opcache-track">
                        <div class="phpinfowp-opcache-fill" style="width:<?php echo min(100, (float)($s['hit_rate'] ?? 0)); ?>%; background:<?php echo $rate >= 90 ? '#10b981' : ($rate >= 70 ? '#f59e0b' : '#ef4444'); ?>;"></div>
                    </div>
                </div>

                <div>
                    <div class="phpinfowp-opcache-meta">
                        <span><strong><?php echo number_format($s['hits']); ?></strong> <?php _e('hits', 'phpinfo-wp'); ?></span>
                        <span>&bull;</span>
                        <span><strong><?php echo number_format($s['misses']); ?></strong> <?php _e('misses', 'phpinfo-wp'); ?></span>
                    </div>

                    <?php if ($s['full']): ?>
                        <div class="phpinfowp-opcache-warn-banner">
                            <span class="dashicons dashicons-warning" style="font-size:16px; width:16px; height:16px;"></span>
                            <span><?php _e('Buffer Full — increase <code>opcache.memory_consumption</code>', 'phpinfo-wp'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 2: Memory Allocation -->
            <div class="phpinfowp-opcache-card">
                <div>
                    <div class="phpinfowp-opcache-card-head">
                        <h3 class="phpinfowp-opcache-card-title">
                            <span>🧠</span> <?php _e('Memory Allocation', 'phpinfo-wp'); ?>
                        </h3>
                        <?php 
                        $mem_pct = $s['memory_pct'];
                        $mem_pill = $mem_pct > 85 ? 'phpinfowp-opcache-pill-red' : ($mem_pct > 70 ? 'phpinfowp-opcache-pill-yellow' : 'phpinfowp-opcache-pill-purple');
                        ?>
                        <span class="phpinfowp-opcache-pill <?php echo $mem_pill; ?>"><?php echo (float)$mem_pct; ?>% <?php _e('Used', 'phpinfo-wp'); ?></span>
                    </div>

                    <div class="phpinfowp-opcache-card-metric">
                        <?php echo esc_html(Phpinfo_WP_OPcache::format_bytes($s['memory_used'])); ?>
                        <span class="phpinfowp-opcache-card-metric-sub">/ <?php echo esc_html(Phpinfo_WP_OPcache::format_bytes($s['memory_total'])); ?></span>
                    </div>

                    <div class="phpinfowp-opcache-track">
                        <div class="phpinfowp-opcache-fill" style="width:<?php echo min(100, $s['memory_pct']); ?>%; background:<?php echo $s['memory_pct'] > 85 ? '#ef4444' : '#777BB3'; ?>;"></div>
                    </div>
                </div>

                <div class="phpinfowp-opcache-meta">
                    <span><?php _e('Free:', 'phpinfo-wp'); ?> <strong><?php echo esc_html(Phpinfo_WP_OPcache::format_bytes($s['memory_free'])); ?></strong></span>
                    <span>&bull;</span>
                    <span><?php _e('Wasted:', 'phpinfo-wp'); ?> <strong><?php echo esc_html(Phpinfo_WP_OPcache::format_bytes($s['memory_wasted'])); ?></strong> (<?php echo round($s['wasted_pct'], 1); ?>%)</span>
                </div>
            </div>

            <!-- Card 3: Cached Scripts & State -->
            <div class="phpinfowp-opcache-card">
                <div>
                    <div class="phpinfowp-opcache-card-head">
                        <h3 class="phpinfowp-opcache-card-title">
                            <span>📄</span> <?php _e('Cached Scripts', 'phpinfo-wp'); ?>
                        </h3>
                        <?php $script_pct = $s['max_scripts'] > 0 ? round($s['cached_scripts'] / $s['max_scripts'] * 100, 1) : 0; ?>
                        <span class="phpinfowp-opcache-pill <?php echo $script_pct > 90 ? 'phpinfowp-opcache-pill-red' : 'phpinfowp-opcache-pill-green'; ?>">
                            <?php echo $script_pct; ?>% <?php _e('Capacity', 'phpinfo-wp'); ?>
                        </span>
                    </div>

                    <div class="phpinfowp-opcache-card-metric">
                        <?php echo number_format($s['cached_scripts']); ?>
                        <span class="phpinfowp-opcache-card-metric-sub">/ <?php echo number_format($s['max_scripts']); ?> <?php _e('max', 'phpinfo-wp'); ?></span>
                    </div>

                    <div class="phpinfowp-opcache-track">
                        <div class="phpinfowp-opcache-fill" style="width:<?php echo min(100, $script_pct); ?>%; background:<?php echo $script_pct > 90 ? '#ef4444' : '#10b981'; ?>;"></div>
                    </div>
                </div>

                <div class="phpinfowp-opcache-meta">
                    <?php if ($s['start_time']): ?>
                        <span>🕒 <?php _e('Uptime since:', 'phpinfo-wp'); ?> <strong><?php echo esc_html(gmdate('Y-m-d H:i', $s['start_time'])); ?> UTC</strong></span>
                    <?php else: ?>
                        <span>⚡ <?php _e('Compiled scripts in RAM', 'phpinfo-wp'); ?></span>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <?php if ($is_free_preview): ?>
            <!-- Free preview: contextual preview cards + centered upgrade card -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Background contextual preview cards -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                    
                <?php
                $mem_limit_mb = ini_get('opcache.memory_consumption') ?: ($s['memory_total'] ? round($s['memory_total'] / 1048576) : 128);
                if ($s && $s['memory_total'] > 0) {
                    $mem_used_str   = Phpinfo_WP_OPcache::format_bytes($s['memory_used']);
                    $mem_free_str   = Phpinfo_WP_OPcache::format_bytes($s['memory_free']);
                    $mem_wasted_str = Phpinfo_WP_OPcache::format_bytes($s['memory_wasted']);
                    $mem_sub = sprintf(__('Used: %s (%s%%) | Free: %s | Wasted: %s (%s%%)', 'phpinfo-wp'), $mem_used_str, $s['memory_pct'], $mem_free_str, $mem_wasted_str, $s['wasted_pct']);
                } else {
                    $mem_sub = sprintf(__('Memory Buffer: %s MB | Dedicated PHP bytecode accelerator cache', 'phpinfo-wp'), $mem_limit_mb);
                }

                $max_files = (int) (ini_get('opcache.max_accelerated_files') ?: ($s['max_scripts'] ?? 10000));
                if ($s) {
                    $scripts_count = number_format($s['cached_scripts']);
                    $hit_rate_str  = $s['hit_rate'] !== null ? $s['hit_rate'] . '%' : 'N/A';
                    $files_sub     = sprintf(__('Cached Scripts: %s files | Hit Rate: %s | Revalidate: %ss', 'phpinfo-wp'), $scripts_count, $hit_rate_str, ini_get('opcache.revalidate_freq') ?: '2');
                } else {
                    $files_sub     = sprintf(__('Max Cached Scripts: %s | Revalidate: %ss', 'phpinfo-wp'), number_format($max_files), ini_get('opcache.revalidate_freq') ?: '2');
                }

                $jit_val   = ini_get('opcache.jit');
                $jit_buf   = ini_get('opcache.jit_buffer_size');
                $jit_label = $jit_val ? strtoupper($jit_val) : (empty($jit_buf) || $jit_buf === '0' ? 'DISABLED' : 'ENABLED');
                if ($s && ($s['hits'] > 0 || $s['misses'] > 0)) {
                    $jit_sub = sprintf(__('Hits: %s | Misses: %s | JIT Buffer: %s', 'phpinfo-wp'), number_format($s['hits']), number_format($s['misses']), $jit_buf ?: '0 MB');
                } else {
                    $jit_sub = sprintf(__('Buffer Size: %s | PHP Version: %s | Validate Timestamps: %s', 'phpinfo-wp'), $jit_buf ?: '0 MB', PHP_VERSION, ini_get('opcache.validate_timestamps') ? 'On' : 'Off');
                }
                ?>
                <!-- Preview Row 1 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #6366f1; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;">opcache.memory_consumption</strong>
                            <span style="background:#e0e7ff; color:#4338ca; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo esc_html($mem_limit_mb); ?> MB</span>
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            <?php echo esc_html($mem_sub); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:12px; color:#15803d; font-weight:600;">✓ Healthy Buffer</span>
                    </div>
                </div>

                <!-- Preview Row 2 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #00a32a; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;">opcache.max_accelerated_files</strong>
                            <span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo esc_html(number_format($max_files)); ?> KEYS</span>
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            <?php echo esc_html($files_sub); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:12px; color:#15803d; font-weight:600;">✓ Optimal</span>
                    </div>
                </div>

                <!-- Preview Row 3 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #6366f1; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;">opcache.jit</strong>
                            <span style="background:#e0e7ff; color:#4338ca; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;"><?php echo esc_html($jit_label); ?></span>
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            <?php echo esc_html($jit_sub); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:12px; color:#6366f1; font-weight:600;">⚡ JIT Config</span>
                    </div>
                </div>

                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

                <!-- Floating Upgrade Card -->
                <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                    <span class="dashicons dashicons-performance" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                    <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('OPcache Directives & Memory Control Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                        <?php _e('Unlock detailed directive audits, JIT compiler performance metrics, memory waste analysis, and 1-click OPcache reset.', 'phpinfo-wp'); ?>
                    </p>
                    <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">⚡</span>
                            <span><strong><?php _e('JIT &amp; Bytecode Acceleration Stats:', 'phpinfo-wp'); ?></strong> <?php _e('Real-time metrics on PHP 8+ JIT compiler performance, buffer utilization, and compiled opcodes.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🧠</span>
                            <span><strong><?php _e('Memory Waste &amp; Fragmentation Analysis:', 'phpinfo-wp'); ?></strong> <?php _e('Detect wasted RAM from invalidated scripts and get recommendations to optimize hit rate.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🔄</span>
                            <span><strong><?php _e('Safe 1-Click OPcache Invalidation:', 'phpinfo-wp'); ?></strong> <?php _e('Reset cached PHP bytecode instantly across web workers without restarting PHP-FPM or Apache.', 'phpinfo-wp'); ?></span>
                        </div>
                    </div>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                        <?php _e('Unlock OPcache with Pro &rarr;', 'phpinfo-wp'); ?>
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
            <form method="post" style="margin-top:20px"
                  data-confirm="<?php esc_attr_e('Clear OPcache? PHP will recompile all cached files on the next request.', 'phpinfo-wp'); ?>"
                  data-confirm-title="<?php esc_attr_e('Clear OPcache', 'phpinfo-wp'); ?>"
                  data-confirm-btn="<?php esc_attr_e('Clear OPcache', 'phpinfo-wp'); ?>">
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
