<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) {
    phpinfowp_render_feature_lock([
        'feature'  => 'Object Cache Monitor',
        'icon'     => 'dashicons-database-view',
        'tagline'  => 'Redis or Memcached hit rate, memory footprint, and key statistics from your persistent cache.',
        'previews' => [
            'Backend: <strong>—</strong>',
            'Hit rate: <strong>— %</strong>',
            'Memory used: <strong>— MB</strong>',
        ],
    ]);
    return;
}

$message  = '';
$msg_type = 'success';

if (isset($_POST['phpinfowp_objectcache_reset']) && check_admin_referer('phpinfowp_objectcache_nonce')) {
    $ok       = Phpinfo_WP_Object_Cache::flush();
    $message  = $ok ? 'Object Cache cleared successfully.' : 'Could not clear Object Cache.';
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
            <h1>Object Cache Diagnostics <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Monitor persistent object cache drop-ins, connection speed, and hit/miss ratios.', 'phpinfo-wp'); ?></p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if (!$has_dropin): ?>
        <div class="notice notice-warning inline">
            <p><strong><?php _e('Object Caching is not active.', 'phpinfo-wp'); ?></strong> The <code>wp-content/object-cache.php</code> drop-in is missing.</p>
            <?php if ($has_redis): ?>
                <p>💡 However, the <strong><?php _e('Redis', 'phpinfo-wp'); ?></strong> PHP extension is installed on your server! You should install a plugin like <a href="<?php echo esc_url(admin_url('plugin-install.php?tab=search&type=term&s=redis+object+cache')); ?>">Redis Object Cache</a> to instantly speed up database queries.</p>
            <?php elseif ($has_memcached): ?>
                <p>💡 However, the <strong><?php _e('Memcached', 'phpinfo-wp'); ?></strong> PHP extension is installed on your server! You can use it to speed up database queries.</p>
            <?php else: ?>
                <p><?php _e('Neither Redis nor Memcached PHP extensions are installed. Ask your host to enable one (Redis is recommended).', 'phpinfo-wp'); ?></p>
            <?php endif; ?>
        </div>
    <?php elseif (!$s): ?>
        <div class="notice notice-warning inline">
            <p><strong><?php _e('Object Cache is active, but stats are unavailable.', 'phpinfo-wp'); ?></strong> The drop-in might not expose connection details or metrics.</p>
        </div>
    <?php else: ?>

        <!-- Hit rate card -->
        <div class="phpinfowp-opcache-grid">
            <div class="phpinfowp-opcache-card phpinfowp-opcache-hitrate">
                <div class="phpinfowp-grade-circle <?php echo esc_attr(Phpinfo_WP_Object_Cache::hit_rate_class($s['hit_rate'] ?? 0)); ?>">
                    <?php echo $s['hit_rate'] !== null ? esc_html($s['hit_rate']) . '%' : 'N/A'; ?>
                </div>
                <div>
                    <h3 style="margin:0 0 4px"><?php _e('Hit Rate', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0;color:#666;font-size:13px">
                        <?php echo number_format($s['hits']); ?> hits /
                        <?php echo number_format($s['misses']); ?> misses
                    </p>
                    <p style="margin:6px 0 0;font-size:13px;color:#444">
                        <strong><?php _e('Engine:', 'phpinfo-wp'); ?></strong> <?php echo esc_html(ucfirst($s['type'])); ?>
                    </p>
                </div>
            </div>

            <div class="phpinfowp-opcache-card">
                <h3 style="margin-top:0"><?php _e('Memory Usage', 'phpinfo-wp'); ?></h3>
                <?php if ($s['memory_total'] > 0): ?>
                <div class="phpinfowp-progress-bar-wrap">
                    <div class="phpinfowp-progress-bar" style="width:<?php echo min(100, $s['memory_pct']); ?>%;background:<?php echo $s['memory_pct'] > 85 ? '#d63638' : '#777BB3'; ?>"></div>
                </div>
                <?php endif; ?>
                <p style="margin:6px 0 0;font-size:13px;color:#444">
                    Used: <strong><?php echo esc_html(Phpinfo_WP_Object_Cache::format_bytes($s['memory_used'])); ?></strong>
                    <?php if ($s['memory_total'] > 0): ?>
                    / Total: <?php echo esc_html(Phpinfo_WP_Object_Cache::format_bytes($s['memory_total'])); ?>
                    <?php endif; ?>
                </p>
                <p style="margin:6px 0 0;font-size:12px;color:#666">
                    Connection: <?php echo esc_html($s['connection']); ?>
                </p>
                <?php if (strpos($s['connection'], 'TCP') !== false && $s['type'] === 'redis'): ?>
                    <p style="margin:4px 0 0;font-size:12px;color:#dba617">
                        <em>Tip: UNIX sockets are ~20% faster than TCP.</em>
                    </p>
                <?php endif; ?>
            </div>

            <div class="phpinfowp-opcache-card">
                <h3 style="margin-top:0"><?php _e('Keys & Evictions', 'phpinfo-wp'); ?></h3>
                <p style="margin:6px 0 0;font-size:13px;color:#444">
                    <strong><?php _e('Keys in Memory:', 'phpinfo-wp'); ?></strong> <?php echo number_format($s['keys']); ?>
                </p>
                <p style="margin:6px 0 0;font-size:13px;color:#444">
                    <strong><?php _e('Evictions:', 'phpinfo-wp'); ?></strong> <?php echo number_format($s['evictions']); ?>
                </p>
                <?php if ($s['evictions'] > 0): ?>
                    <p style="margin:6px 0 0;font-size:12px;color:#d63638">
                        Keys are being evicted. Consider increasing <code>maxmemory</code>.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reset button -->
        <form method="post" style="margin-top:20px" onsubmit="return confirm('Clear Object Cache? This will flush all database queries from memory.')">
            <?php wp_nonce_field('phpinfowp_objectcache_nonce'); ?>
            <input type="hidden" name="phpinfowp_objectcache_reset" value="1">
            <button type="submit" class="button button-secondary"><?php _e('Flush Cache', 'phpinfo-wp'); ?></button>
            <span style="margin-left:8px;font-size:12px;color:#666"><?php _e('Forces the cache to be entirely rebuilt.', 'phpinfo-wp'); ?></span>
        </form>

    <?php endif; ?>
</div>
