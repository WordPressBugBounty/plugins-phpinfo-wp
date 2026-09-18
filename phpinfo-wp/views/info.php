<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

global $wpdb;

function phpinfowp_fmt_bytes(int $bytes): string {
    if ($bytes >= GB_IN_BYTES) return round($bytes / GB_IN_BYTES, 2) . ' GB';
    if ($bytes >= MB_IN_BYTES) return round($bytes / MB_IN_BYTES, 1) . ' MB';
    if ($bytes >= KB_IN_BYTES) return round($bytes / KB_IN_BYTES, 1) . ' KB';
    return $bytes . ' B';
}

function phpinfowp_dirsize_safe(string $path): string {
    if (!is_dir($path)) return '—';
    $cache_key = 'phpinfowp_dirsize_' . md5($path);
    $size = get_transient($cache_key);
    if ($size === false) {
        $size = (int) get_dirsize($path);
        set_transient($cache_key, $size, 12 * HOUR_IN_SECONDS);
    }
    return $size ? phpinfowp_fmt_bytes((int)$size) : '—';
}

// EOL status for the PHP badge
$eol = Phpinfo_WP_EOL::status();
$eol_colors = ['ok' => '#00a32a', 'warning' => '#996800', 'eol' => '#d63638', 'unknown' => '#666'];
$eol_color  = $eol_colors[$eol['status']] ?? '#666';

// Memory
$mem_limit   = ini_get('memory_limit');
$mem_used    = memory_get_usage(true);
$mem_limit_b = wp_convert_hr_to_bytes($mem_limit);
$mem_pct     = $mem_limit_b > 0 ? round($mem_used / $mem_limit_b * 100) : 0;

// DB
$db_version  = $wpdb->get_var('SELECT VERSION()') ?? '—';
$db_size_raw = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = %s",
    DB_NAME
));
$db_size = $db_size_raw ? phpinfowp_fmt_bytes((int)$db_size_raw) : '—';

// cURL
$curl_ver = function_exists('curl_version') ? (curl_version()['version'] ?? '—') : __('not loaded', 'phpinfo-wp');

// Server
$server_soft = $_SERVER['SERVER_SOFTWARE'] ?? '—';
?>

<div class="phpinfowp-info-page">

  <div class="phpinfowp-page-header">
    <div>
      <h1>
        <?php _e('Server Overview', 'phpinfo-wp'); ?>
        <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('PHP, WordPress, server, and database environment details', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
      </h1>
    </div>
  </div>

  <table class="wp-list-table widefat fixed striped phpinfowp-info-table">
    <tbody>

      <tr><th colspan="2" class="phpinfowp-info-section-head"><?php _e('WordPress', 'phpinfo-wp'); ?></th></tr>
      <tr><td><?php _e('Site URL', 'phpinfo-wp'); ?></td><td><code><?php echo esc_html(get_site_url()); ?></code></td></tr>
      <tr><td><?php _e('Home URL', 'phpinfo-wp'); ?></td><td><code><?php echo esc_html(get_home_url()); ?></code></td></tr>
      <tr><td><?php _e('WP Version', 'phpinfo-wp'); ?></td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
      <tr><td><?php _e('Active Theme', 'phpinfo-wp'); ?></td><td><?php echo esc_html(wp_get_theme()->get('Name')); ?> <?php echo esc_html(wp_get_theme()->get('Version')); ?></td></tr>
      <tr><td><?php _e('Active Plugins', 'phpinfo-wp'); ?></td><td>
        <?php
        $active_count = count(get_option('active_plugins'));
        $installed_count = count(get_plugins());
        printf(
            /* translators: 1: active plugins count, 2: installed plugins count */
            __('%1$s of %2$s installed', 'phpinfo-wp'),
            $active_count,
            $installed_count
        );
        ?>
      </td></tr>
      <tr><td><?php _e('Active Themes', 'phpinfo-wp'); ?></td><td>
        <?php
        $themes_count = count(wp_get_themes());
        printf(
            _n('%s installed', '%s installed', $themes_count, 'phpinfo-wp'),
            number_format_i18n($themes_count)
        );
        ?>
      </td></tr>
      <tr><td><?php _e('Debug Mode', 'phpinfo-wp'); ?></td>
          <td><?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <span style="color:#d63638;font-weight:600"><?php _e('ON', 'phpinfo-wp'); ?></span> — <?php _e('disable in production', 'phpinfo-wp'); ?>
              <?php else: ?>
                <span style="color:#00a32a"><?php _e('Off', 'phpinfo-wp'); ?></span>
              <?php endif; ?></td></tr>
      <tr><td><?php _e('Admin Email', 'phpinfo-wp'); ?></td><td><?php echo esc_html(get_option('admin_email')); ?></td></tr>

      <tr><th colspan="2" class="phpinfowp-info-section-head"><?php _e('PHP', 'phpinfo-wp'); ?></th></tr>
      <tr><td><?php _e('PHP Version', 'phpinfo-wp'); ?></td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
      <tr><td><?php _e('PHP SAPI', 'phpinfo-wp'); ?></td><td><?php echo esc_html(PHP_SAPI); ?></td></tr>
      <tr><td><?php _e('Memory Limit', 'phpinfo-wp'); ?></td><td><?php echo esc_html(ini_get('memory_limit')); ?></td></tr>
      <tr><td><?php _e('Max Execution Time', 'phpinfo-wp'); ?></td><td><?php echo esc_html(ini_get('max_execution_time')); ?>s</td></tr>
      <tr><td><?php _e('Upload Max Filesize', 'phpinfo-wp'); ?></td><td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td></tr>
      <tr><td><?php _e('Post Max Size', 'phpinfo-wp'); ?></td><td><?php echo esc_html(ini_get('post_max_size')); ?></td></tr>
      <tr><td><?php _e('Max Input Vars', 'phpinfo-wp'); ?></td><td><?php echo esc_html(ini_get('max_input_vars')); ?></td></tr>
      <tr><td><?php _e('Display Errors', 'phpinfo-wp'); ?></td><td><?php echo ini_get('display_errors') ? '<span style="color:#d63638">' . __('On', 'phpinfo-wp') . '</span>' : '<span style="color:#00a32a">' . __('Off', 'phpinfo-wp') . '</span>'; ?></td></tr>
      <tr><td><?php _e('cURL Version', 'phpinfo-wp'); ?></td><td><?php echo esc_html($curl_ver); ?></td></tr>
      <tr><td><?php _e('Loaded Extensions', 'phpinfo-wp'); ?></td><td><?php echo count(get_loaded_extensions()); ?></td></tr>

      <tr><th colspan="2" class="phpinfowp-info-section-head"><?php _e('Server', 'phpinfo-wp'); ?></th></tr>
      <tr><td><?php _e('Server Software', 'phpinfo-wp'); ?></td><td><?php echo esc_html($server_soft); ?></td></tr>
      <tr><td><?php _e('Server Name', 'phpinfo-wp'); ?></td><td><?php echo esc_html($_SERVER['SERVER_NAME'] ?? '—'); ?></td></tr>
      <tr><td><?php _e('Document Root', 'phpinfo-wp'); ?></td><td><code><?php echo esc_html($_SERVER['DOCUMENT_ROOT'] ?? ABSPATH); ?></code></td></tr>
      <tr><td><?php _e('Operating System', 'phpinfo-wp'); ?></td><td><?php echo esc_html(PHP_OS_FAMILY . ' ' . php_uname('r')); ?></td></tr>
      <tr><td><?php _e('Hostname', 'phpinfo-wp'); ?></td><td><?php echo esc_html(gethostname() ?: '—'); ?></td></tr>

      <tr><th colspan="2" class="phpinfowp-info-section-head"><?php _e('Database', 'phpinfo-wp'); ?></th></tr>
      <tr><td><?php _e('MySQL Version', 'phpinfo-wp'); ?></td><td><?php echo esc_html($db_version); ?></td></tr>
      <tr><td><?php _e('Database Name', 'phpinfo-wp'); ?></td><td><?php echo esc_html(DB_NAME); ?></td></tr>
      <tr><td><?php _e('Database Host', 'phpinfo-wp'); ?></td><td><?php echo esc_html(DB_HOST); ?></td></tr>
      <tr><td><?php _e('Table Prefix', 'phpinfo-wp'); ?></td><td><code><?php echo esc_html($wpdb->prefix); ?></code></td></tr>
      <tr><td><?php _e('Database Size', 'phpinfo-wp'); ?></td><td><?php echo esc_html($db_size); ?></td></tr>
      <tr><td><?php _e('Charset', 'phpinfo-wp'); ?></td><td><?php echo esc_html(DB_CHARSET); ?></td></tr>

      <tr><th colspan="2" class="phpinfowp-info-section-head"><?php _e('Disk', 'phpinfo-wp'); ?></th></tr>
      <tr><td><?php _e('Root Directory', 'phpinfo-wp'); ?></td><td><?php echo esc_html(phpinfowp_dirsize_safe(ABSPATH)); ?></td></tr>
      <tr><td><?php _e('Uploads Directory', 'phpinfo-wp'); ?></td><td><?php echo esc_html(phpinfowp_dirsize_safe(WP_CONTENT_DIR . '/uploads')); ?></td></tr>

    </tbody>
  </table>
</div>