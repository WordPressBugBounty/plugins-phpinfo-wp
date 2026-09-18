<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;
$message         = '';
$msg_type        = 'success';

if ($is_pro && isset($_POST['phpinfowp_objectcache_reset']) && check_admin_referer('phpinfowp_objectcache_nonce')) {
    $ok       = Phpinfo_WP_Object_Cache::flush();
    $message  = $ok ? __('Object Cache cleared successfully.', 'phpinfo-wp') : __('Could not clear Object Cache.', 'phpinfo-wp');
    $msg_type = $ok ? 'success' : 'error';
}

$has_redis     = Phpinfo_WP_Object_Cache::is_redis_extension_loaded();
$has_memcached = Phpinfo_WP_Object_Cache::is_memcached_extension_loaded();
$has_dropin    = Phpinfo_WP_Object_Cache::has_dropin();

$s = Phpinfo_WP_Object_Cache::status();
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1>
                <?php _e('Object Cache Diagnostics', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Monitor persistent object cache drop-ins, connection speed, and hit/miss ratios.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>



    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible piwp-notice"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if (!$has_dropin): ?>
        <div class="phpinfowp-action-banner" style="margin-top:20px;">
            <div>
                <strong style="font-size:14px; color:#2c2f48; display:flex; align-items:center; gap:6px;">
                    🗄️ <?php _e('Persistent Object Caching is not active', 'phpinfo-wp'); ?>
                </strong>
                <p style="margin:4px 0 0; color:#555870; font-size:12.5px; line-height:1.5;">
                    <?php if ($has_redis): ?>
                        <?php _e('The Redis PHP extension is installed. Install the Redis drop-in plugin to cache database queries in RAM.', 'phpinfo-wp'); ?>
                    <?php elseif ($has_memcached): ?>
                        <?php _e('The Memcached PHP extension is installed. Install a Memcached drop-in plugin to cache database queries in RAM.', 'phpinfo-wp'); ?>
                    <?php else: ?>
                        <?php _e('The <code>wp-content/object-cache.php</code> drop-in is missing. Ask your host to enable the Redis PHP extension.', 'phpinfo-wp'); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <?php if ($has_redis): ?>
                    <a href="<?php echo esc_url(admin_url('plugin-install.php?tab=search&type=term&s=redis+object+cache')); ?>" class="button button-primary" style="background:#777BB3; border-color:#777BB3; color:#fff; font-weight:600; padding:6px 16px; border-radius:6px;">
                        <?php _e('Install Redis Plugin →', 'phpinfo-wp'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=piwp-extensions')); ?>" class="button button-primary" style="background:#777BB3; border-color:#777BB3; color:#fff; font-weight:600; padding:6px 16px; border-radius:6px;">
                        <?php _e('View Extensions →', 'phpinfo-wp'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif (!$s): ?>
        <div class="phpinfowp-action-banner" style="margin-top:20px;">
            <div>
                <strong style="font-size:14px; color:#2c2f48; display:flex; align-items:center; gap:6px;">
                    ℹ️ <?php _e('Object Cache is active, but stats are unavailable', 'phpinfo-wp'); ?>
                </strong>
                <p style="margin:4px 0 0; color:#555870; font-size:12.5px; line-height:1.5;">
                    <?php _e('The active drop-in does not expose connection metrics or hit/miss counters to PHP.', 'phpinfo-wp'); ?>
                </p>
            </div>
        </div>
    <?php else: ?>

        <!-- Hit rate & Status Cards (Visible to all users) -->
        <div class="phpinfowp-opcache-grid">
            
            <!-- Card 1: Hit Rate & Efficiency -->
            <div class="phpinfowp-opcache-card">
                <div>
                    <div class="phpinfowp-opcache-card-head">
                        <h3 class="phpinfowp-opcache-card-title">
                            <span>⚡</span> <?php _e('Hit Rate & Ratio', 'phpinfo-wp'); ?>
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

                <div class="phpinfowp-opcache-meta">
                    <span><strong><?php echo number_format($s['hits']); ?></strong> <?php _e('hits', 'phpinfo-wp'); ?></span>
                    <span>&bull;</span>
                    <span><strong><?php echo number_format($s['misses']); ?></strong> <?php _e('misses', 'phpinfo-wp'); ?></span>
                    <span>&bull;</span>
                    <span><?php _e('Engine:', 'phpinfo-wp'); ?> <strong><?php echo esc_html(ucfirst($s['type'])); ?></strong></span>
                </div>
            </div>

            <!-- Card 2: Memory Usage -->
            <div class="phpinfowp-opcache-card">
                <div>
                    <div class="phpinfowp-opcache-card-head">
                        <h3 class="phpinfowp-opcache-card-title">
                            <span>🧠</span> <?php _e('Memory Allocation', 'phpinfo-wp'); ?>
                        </h3>
                        <?php 
                        $mem_pct = (float)($s['memory_pct'] ?? 0);
                        $mem_pill = $mem_pct > 85 ? 'phpinfowp-opcache-pill-red' : ($mem_pct > 70 ? 'phpinfowp-opcache-pill-yellow' : 'phpinfowp-opcache-pill-purple');
                        ?>
                        <span class="phpinfowp-opcache-pill <?php echo $mem_pill; ?>"><?php echo $mem_pct; ?>% <?php _e('Used', 'phpinfo-wp'); ?></span>
                    </div>

                    <div class="phpinfowp-opcache-card-metric">
                        <?php echo esc_html(Phpinfo_WP_Object_Cache::format_bytes($s['memory_used'])); ?>
                        <?php if ($s['memory_total'] > 0): ?>
                            <span class="phpinfowp-opcache-card-metric-sub">/ <?php echo esc_html(Phpinfo_WP_Object_Cache::format_bytes($s['memory_total'])); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($s['memory_total'] > 0): ?>
                    <div class="phpinfowp-opcache-track">
                        <div class="phpinfowp-opcache-fill" style="width:<?php echo min(100, $s['memory_pct']); ?>%; background:<?php echo $s['memory_pct'] > 85 ? '#ef4444' : '#777BB3'; ?>;"></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="phpinfowp-opcache-meta">
                    <span><?php _e('Connection:', 'phpinfo-wp'); ?> <strong><?php echo esc_html($s['connection']); ?></strong></span>
                    <?php if (strpos($s['connection'], 'TCP') !== false && $s['type'] === 'redis'): ?>
                        <span style="color:#d97706; font-size:11.5px;">(UNIX socket is ~20% faster)</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 3: Keys & Evictions -->
            <div class="phpinfowp-opcache-card">
                <div>
                    <div class="phpinfowp-opcache-card-head">
                        <h3 class="phpinfowp-opcache-card-title">
                            <span>🔑</span> <?php _e('Keys & Evictions', 'phpinfo-wp'); ?>
                        </h3>
                        <span class="phpinfowp-opcache-pill <?php echo $s['evictions'] > 0 ? 'phpinfowp-opcache-pill-red' : 'phpinfowp-opcache-pill-green'; ?>">
                            <?php echo $s['evictions'] > 0 ? __('Evicting Keys', 'phpinfo-wp') : __('Healthy', 'phpinfo-wp'); ?>
                        </span>
                    </div>

                    <div class="phpinfowp-opcache-card-metric">
                        <?php echo number_format($s['keys']); ?>
                        <span class="phpinfowp-opcache-card-metric-sub"><?php _e('keys stored', 'phpinfo-wp'); ?></span>
                    </div>
                </div>

                <div>
                    <div class="phpinfowp-opcache-meta">
                        <span><?php _e('Evictions:', 'phpinfo-wp'); ?> <strong><?php echo number_format($s['evictions']); ?></strong></span>
                    </div>

                    <?php if ($s['evictions'] > 0): ?>
                        <div class="phpinfowp-opcache-warn-banner">
                            <span class="dashicons dashicons-warning" style="font-size:16px; width:16px; height:16px;"></span>
                            <span><?php _e('Keys are being evicted — consider increasing maxmemory.', 'phpinfo-wp'); ?></span>
                        </div>
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
                    global $wpdb;
                    $pfx = $wpdb->prefix;
                    $oc_engine = $has_dropin ? ($s['driver'] ?? 'Object Cache Drop-In') : ($has_redis ? 'Redis Extension Available' : ($has_memcached ? 'Memcached Extension Available' : 'PHP Runtime Memory Cache'));
                    ?>
                    <!-- Preview Row 1 -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #6366f1; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;"><?php echo esc_html($pfx); ?>options:alloptions</strong>
                                <span style="background:#e0e7ff; color:#4338ca; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">HOT KEY</span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php printf(__('Engine: %s · Group: options · Read-Heavy Core Option Key', 'phpinfo-wp'), esc_html($oc_engine)); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px; color:#15803d; font-weight:600;">⚡ RAM Cache</span>
                        </div>
                    </div>

                    <!-- Preview Row 2 -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #00a32a; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;"><?php echo esc_html($pfx); ?>transients:site_transient_update_core</strong>
                                <span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">CACHED</span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php printf(__('Group: site-transient · Status: %s · Prevents Redundant Remote Calls', 'phpinfo-wp'), $has_dropin ? __('Persisted in RAM', 'phpinfo-wp') : __('Database Bound', 'phpinfo-wp')); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px; color:#15803d; font-weight:600;">✓ In Memory</span>
                        </div>
                    </div>

                    <!-- Preview Row 3 -->
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #00a32a; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <strong style="font-size:13.5px; color:#0f172a; font-family:monospace;"><?php echo esc_html($pfx); ?>posts:last_changed</strong>
                                <span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">CACHED</span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">
                                <?php _e('Group: posts · Invalidation tracking key for WP_Query database query caching', 'phpinfo-wp'); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:12px; color:#15803d; font-weight:600;">✓ In Memory</span>
                        </div>
                    </div>

                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

                <!-- Floating Upgrade Card -->
                <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                    <span class="dashicons dashicons-database" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                    <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Object Cache Management Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                        <?php _e('Unlock live Redis &amp; Memcached key inspection, latency profiling, eviction alerts, and 1-click query cache purging.', 'phpinfo-wp'); ?>
                    </p>
                    <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">⚡</span>
                            <span><strong><?php _e('Redis &amp; Memcached Key Profiler:', 'phpinfo-wp'); ?></strong> <?php _e('Inspect live cache keys, memory footprint per query group, and identify runaway transient bloat.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">📈</span>
                            <span><strong><?php _e('Eviction &amp; Latency Analytics:', 'phpinfo-wp'); ?></strong> <?php _e('Real-time metrics on memory pressure, cache churn, and sub-millisecond memory read times.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🔄</span>
                            <span><strong><?php _e('Targeted 1-Click Cache Flush:', 'phpinfo-wp'); ?></strong> <?php _e('Selectively purge query groups or perform a safe full object cache rebuild without downtime.', 'phpinfo-wp'); ?></span>
                        </div>
                    </div>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                        <?php _e('Unlock Object Cache with Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php else: ?>

            <!-- Reset button -->
            <form method="post" style="margin-top:20px"
                  data-confirm="<?php esc_attr_e('Clear Object Cache? This will flush all cached database queries from memory.', 'phpinfo-wp'); ?>"
                  data-confirm-title="<?php esc_attr_e('Flush Object Cache', 'phpinfo-wp'); ?>"
                  data-confirm-btn="<?php esc_attr_e('Flush Cache', 'phpinfo-wp'); ?>">
                <?php wp_nonce_field('phpinfowp_objectcache_nonce'); ?>
                <input type="hidden" name="phpinfowp_objectcache_reset" value="1">
                <button type="submit" class="button button-secondary"><?php _e('Flush Cache', 'phpinfo-wp'); ?></button>
                <span style="margin-left:8px;font-size:12px;color:#666"><?php _e('Forces the cache to be entirely rebuilt.', 'phpinfo-wp'); ?></span>
            </form>

        <?php endif; ?>

    <?php endif; ?>
</div>
