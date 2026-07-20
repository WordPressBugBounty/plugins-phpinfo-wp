<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!function_exists('get_home_path')) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}
$root_dir    = get_home_path();
$content_dir = WP_CONTENT_DIR;
$log_dir     = "$content_dir/logs/phpinfo-WP";
$log_file    = "$log_dir/log.txt";

if (!file_exists($log_dir)) wp_mkdir_p($log_dir);
if (!file_exists($log_file)) file_put_contents($log_file, '');

global $current_user;
$user        = $current_user->user_login;
$notice      = '';
$notice_type = 'success';
$writable    = is_writable($root_dir);

// Detect which method applies to this server
$sapi            = php_sapi_name();
$server_software = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
$is_litespeed    = strpos($server_software, 'litespeed') !== false;
$is_nginx        = strpos($server_software, 'nginx') !== false;

// apache2handler = Apache + mod_php → .htaccess php_value works
// Everything else (fpm-fcgi, litespeed, cgi, etc.) → .user.ini
$mode = ($sapi === 'apache2handler' && !$is_litespeed) ? 'htaccess' : 'userini';

$target_file  = $root_dir . ($mode === 'htaccess' ? '.htaccess' : '.user.ini');
$cache_file   = $root_dir . ($mode === 'htaccess' ? 'htaccess-phpinfo.txt' : 'userini-phpinfo.txt');
$user_ini_ttl = (int) ini_get('user_ini.cache_ttl') ?: 300;

// Determine Server Type for Snippets
$snippet_server = $is_nginx ? 'nginx' : 'apache'; // LiteSpeed uses Apache syntax (.htaccess)

$snippets = [
    'gzip' => [
        'title' => 'Aggressive GZIP / Brotli Compression',
        'desc'  => 'Compresses HTML, CSS, JS, and JSON before sending it to the browser. Massively reduces page size and improves TTFB.',
        'apache'=> "<IfModule mod_deflate.c>\n    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json\n</IfModule>",
        'nginx' => "gzip on;\ngzip_comp_level 5;\ngzip_min_length 256;\ngzip_proxied any;\ngzip_vary on;\ngzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;"
    ],
    'browser_cache' => [
        'title' => 'Browser Caching (Expires Headers)',
        'desc'  => 'Instructs browsers to save static assets (images, fonts, css) locally for 1 year, drastically speeding up repeat visits.',
        'apache'=> "<IfModule mod_expires.c>\n    ExpiresActive On\n    ExpiresByType image/jpg \"access 1 year\"\n    ExpiresByType image/jpeg \"access 1 year\"\n    ExpiresByType image/gif \"access 1 year\"\n    ExpiresByType image/png \"access 1 year\"\n    ExpiresByType image/webp \"access 1 year\"\n    ExpiresByType text/css \"access 1 month\"\n    ExpiresByType application/javascript \"access 1 month\"\n    ExpiresByType font/woff2 \"access 1 year\"\n    ExpiresDefault \"access 1 month\"\n</IfModule>",
        'nginx' => "location ~* \\.(jpg|jpeg|gif|png|webp|ico|css|js|woff2|woff|ttf)$ {\n    expires 365d;\n    add_header Cache-Control \"public, no-transform\";\n}"
    ],
    'security' => [
        'title' => 'Security Headers (Basic)',
        'desc'  => 'Prevents clickjacking (X-Frame-Options) and MIME-type sniffing (X-Content-Type-Options).',
        'apache'=> "<IfModule mod_headers.c>\n    Header always set X-Frame-Options \"SAMEORIGIN\"\n    Header always set X-Content-Type-Options \"nosniff\"\n    Header set X-XSS-Protection \"1; mode=block\"\n</IfModule>",
        'nginx' => "add_header X-Frame-Options \"SAMEORIGIN\" always;\nadd_header X-Content-Type-Options \"nosniff\" always;\nadd_header X-XSS-Protection \"1; mode=block\" always;"
    ],
    'bots' => [
        'title' => 'Block Bad Bots (Basic)',
        'desc'  => 'Blocks common aggressive scrapers and vulnerability scanners to save server CPU.',
        'apache'=> "<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteCond %{HTTP_USER_AGENT} (SemrushBot|AhrefsBot|DotBot|MJ12bot) [NC]\n    RewriteRule .* - [F,L]\n</IfModule>",
        'nginx' => "if (\$http_user_agent ~* (SemrushBot|AhrefsBot|DotBot|MJ12bot)) {\n    return 403;\n}"
    ]
];

if ($writable && isset($_POST['phpinfo_nonce']) && wp_verify_nonce($_POST['phpinfo_nonce'], 'phpinfo_nonce')) {

    if (isset($_POST['append_snippet'])) {
        $snippet_id = sanitize_text_field($_POST['append_snippet']);
        if (isset($snippets[$snippet_id]) && $snippet_server === 'apache') {
            if (!Phpinfo_WP_License::is_valid()) {
                $notice = 'Snippet injection requires phpinfo() WP Pro.';
                $notice_type = 'error';
            } else {
                $code = $snippets[$snippet_id]['apache'];
                $current = file_exists("$root_dir.htaccess") ? file_get_contents("$root_dir.htaccess") : '';
                $block_name = strtoupper($snippet_id);
                // Remove existing block if present so we don't duplicate
                $current = preg_replace("/\n?# BEGIN phpinfo-wp-{$block_name}.*?# END phpinfo-wp-{$block_name}\n?/s", "\n", $current);
                $new = rtrim($current) . "\n\n# BEGIN phpinfo-wp-{$block_name}\n" . $code . "\n# END phpinfo-wp-{$block_name}\n";
                file_put_contents("$root_dir.htaccess", $new);
                $notice = "Successfully appended <strong>{$snippets[$snippet_id]['title']}</strong> to .htaccess.<br /><span style='display:inline-block; margin-top: 6px; font-size:12.5px; opacity:0.9;'>⚡ <strong>Note:</strong> You must clear your cache (plugin cache, CDN, and browser cache) to see the results!</span>";
                file_put_contents($log_file, ".htaccess snippet {$snippet_id} added on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);
            }
        }

    } elseif (isset($_POST['rollback_snippet'])) {
        $snippet_id = sanitize_text_field($_POST['rollback_snippet']);
        if (isset($snippets[$snippet_id]) && $snippet_server === 'apache') {
            if (!Phpinfo_WP_License::is_valid()) {
                $notice = 'Snippet rollback requires phpinfo() WP Pro.';
                $notice_type = 'error';
            } else {
                $block_name = strtoupper($snippet_id);
                $current = file_exists("$root_dir.htaccess") ? file_get_contents("$root_dir.htaccess") : '';
                $new = preg_replace("/\n?# BEGIN phpinfo-wp-{$block_name}.*?# END phpinfo-wp-{$block_name}\n?/s", "\n", $current);
                file_put_contents("$root_dir.htaccess", $new);
                $notice = "Rolled back <strong>{$snippets[$snippet_id]['title']}</strong> — block removed from .htaccess.";
                file_put_contents($log_file, ".htaccess snippet {$snippet_id} rolled back on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);
            }
        }

    } elseif (isset($_POST['inject_all_snippets'])) {
        if (!Phpinfo_WP_License::is_valid()) {
            $notice = 'Snippet injection requires phpinfo() WP Pro.';
            $notice_type = 'error';
        } elseif ($snippet_server !== 'apache') {
            $notice = 'Bulk injection is only available on Apache/LiteSpeed servers.';
            $notice_type = 'error';
        } else {
            $current = file_exists("$root_dir.htaccess") ? file_get_contents("$root_dir.htaccess") : '';
            foreach ($snippets as $sid => $snip) {
                $block_name = strtoupper($sid);
                $current = preg_replace("/\n?# BEGIN phpinfo-wp-{$block_name}.*?# END phpinfo-wp-{$block_name}\n?/s", "\n", $current);
                $current = rtrim($current) . "\n\n# BEGIN phpinfo-wp-{$block_name}\n" . $snip['apache'] . "\n# END phpinfo-wp-{$block_name}\n";
            }
            file_put_contents("$root_dir.htaccess", $current);
            $notice = "All 4 snippets injected into .htaccess.<br /><span style='display:inline-block; margin-top: 6px; font-size:12.5px; opacity:0.9;'>⚡ <strong>Note:</strong> Clear your cache (plugin, CDN, browser) to see the results!</span>";
            file_put_contents($log_file, "All .htaccess snippets injected on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);
        }

    } elseif (isset($_POST['rollback_all_snippets'])) {
        if (!Phpinfo_WP_License::is_valid()) {
            $notice = 'Snippet rollback requires phpinfo() WP Pro.';
            $notice_type = 'error';
        } else {
            $current = file_exists("$root_dir.htaccess") ? file_get_contents("$root_dir.htaccess") : '';
            foreach ($snippets as $sid => $snip) {
                $block_name = strtoupper($sid);
                $current = preg_replace("/\n?# BEGIN phpinfo-wp-{$block_name}.*?# END phpinfo-wp-{$block_name}\n?/s", "\n", $current);
            }
            file_put_contents("$root_dir.htaccess", $current);
            $notice = 'All 4 snippets have been rolled back and removed from .htaccess.';
            file_put_contents($log_file, "All .htaccess snippets rolled back on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);
        }
    } elseif ($mode === 'htaccess') {

        if (isset($_POST['backup'])) {
            file_put_contents("$root_dir.htaccess.bak", '#BACKED UP by phpinfo() WP' . PHP_EOL . file_get_contents("$root_dir.htaccess"));
            $notice = 'Backup created: <code>.htaccess.bak</code>';
            file_put_contents($log_file, ".htaccess backed up on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);

        } elseif (isset($_POST['restore'])) {
            if (!file_exists("$root_dir.htaccess.bak")) {
                $notice      = 'No backup file found. Take a backup first.';
                $notice_type = 'error';
            } else {
                file_put_contents("$root_dir.htaccess", file_get_contents("$root_dir.htaccess.bak"));
                $notice = '.htaccess restored from backup.';
                file_put_contents($log_file, ".htaccess restored on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);
            }

        } elseif (isset($_POST['save'])) {
            $custom_raw = $_POST['htaccess'] ?? '';
            $php_lines  = '';
            foreach (explode("\n", $custom_raw) as $line) {
                $line = trim($line);
                if ($line !== '' && strncmp($line, '#', strlen('#')) !== 0) {
                    $php_lines .= 'php_value ' . $line . "\n";
                }
            }

            $current = file_exists("$root_dir.htaccess") ? file_get_contents("$root_dir.htaccess") : '';
            $base    = preg_replace('/\n?# BEGIN phpinfo-wp\n.*?# END phpinfo-wp\n?/s', '', $current); // Fixed regex
            // If the old regex left it without END, let's just append
            $base = str_replace('# BEGIN phpinfo-wp', '', $base); 
            $new     = rtrim($base) . "\n\n# BEGIN phpinfo-wp\n" . $php_lines . "# END phpinfo-wp\n";

            file_put_contents("$root_dir.htaccess", $new);

            $test = wp_remote_get(get_site_url(), ['timeout' => 8, 'sslverify' => false]);
            if (!is_wp_error($test) && wp_remote_retrieve_response_code($test) === 500) {
                file_put_contents("$root_dir.htaccess", $current);
                $notice      = 'Save aborted — site returned HTTP 500. Original .htaccess restored automatically.';
                $notice_type = 'error';
            } else {
                file_put_contents($cache_file, $custom_raw);
                $notice = '.htaccess saved successfully.';
                file_put_contents($log_file, ".htaccess edited on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);
            }
        }

    } else { // .user.ini mode

        if (isset($_POST['save'])) {
            $custom_raw = $_POST['htaccess'] ?? '';
            $ini_lines  = '';
            foreach (explode("\n", $custom_raw) as $line) {
                $line = trim($line);
                if ($line === '' || strncmp($line, ';', strlen(';')) === 0) continue;
                if (strpos($line, '=') !== false) {
                    $ini_lines .= $line . "\n";
                } else {
                    [$k, $v]    = array_pad(explode(' ', $line, 2), 2, '');
                    $ini_lines .= trim($k) . ' = ' . trim($v) . "\n";
                }
            }

            $current = file_exists($target_file) ? file_get_contents($target_file) : '';
            $base    = preg_replace('/\n?; BEGIN phpinfo-wp.*?; END phpinfo-wp\n?/s', '', $current);
            $new     = rtrim($base) . "\n\n; BEGIN phpinfo-wp\n" . $ini_lines . "; END phpinfo-wp\n";

            file_put_contents($target_file, $new);
            file_put_contents($cache_file, $custom_raw);
            $notice = ".user.ini saved. Changes take effect within {$user_ini_ttl} seconds (PHP-FPM cache TTL).";
            file_put_contents($log_file, ".user.ini edited on " . current_time('mysql') . " by {$user}<br />", FILE_APPEND);
        }
    }
}

$existing = file_exists($cache_file) ? file_get_contents($cache_file) : '';
$preview  = file_exists($target_file) ? file_get_contents($target_file) : '';

Phpinfo_wp::thankyou();
?>

<div class="phpinfowp-info-page phpinfowp-htaccess-page">

  <?php
  // Mode banner properties
  $mode_label     = $mode === 'htaccess' ? 'Apache + mod_php' : 'PHP-FPM / Nginx / LiteSpeed';
  $mode_color     = $mode === 'htaccess' ? '#ef4444' : '#10b981';
  $mode_bg        = $mode === 'htaccess' ? '#fef2f2' : '#ecfdf5';
  $mode_border    = $mode === 'htaccess' ? '#fee2e2' : '#d1fae5';
  $mode_icon_bg   = $mode === 'htaccess' ? '#fecaca' : '#a7f3d0';
  $mode_file      = $mode === 'htaccess' ? '.htaccess' : '.user.ini';
  ?>

  <style>
  /* Environment Cards Grid */
  .phpinfowp-env-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 14px;
      margin-bottom: 24px;
  }
  .phpinfowp-env-card {
      background: #fff;
      border: 1px solid #dcdcde;
      border-radius: 6px;
      padding: 16px 18px;
      box-shadow: none;
      display: flex;
      align-items: flex-start;
      gap: 14px;
      transition: all 0.2s ease;
  }
  .phpinfowp-env-card:hover {
      border-color: #ccd0d4;
  }
  .phpinfowp-env-icon-box {
      width: 40px;
      height: 40px;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
  }
  .phpinfowp-env-details {
      flex: 1;
      min-width: 0;
  }
  .phpinfowp-env-label {
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #666;
      margin-bottom: 4px;
  }
  .phpinfowp-env-value {
      font-size: 14px;
      font-weight: 700;
      color: #1d2327;
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 6px;
  }
  .phpinfowp-env-sub {
      font-size: 12px;
      color: #666;
      line-height: 1.4;
  }
  
  /* Active Status badge with pulsing dot */
  @keyframes phpinfowp-pulse {
      0% { transform: scale(0.95); opacity: 0.5; }
      50% { transform: scale(1.15); opacity: 1; }
      100% { transform: scale(0.95); opacity: 0.5; }
  }
  .phpinfowp-pulse-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #10b981;
      display: inline-block;
      animation: phpinfowp-pulse 2s infinite ease-in-out;
  }

  /* Mock IDE / Terminal Window Wrapper */
  .phpinfowp-ide-window {
      background: #1e1e2e;
      border-radius: 6px;
      border: 1px solid #313244;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      overflow: hidden;
      margin-bottom: 16px;
  }
  .phpinfowp-ide-header {
      background: #181825;
      padding: 10px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #313244;
  }
  .phpinfowp-ide-footer {
      background: #181825;
      padding: 12px 16px;
      border-top: 1px solid #313244;
      font-size: 12px;
      color: #a6adc8 !important;
      line-height: 1.5;
  }
  .phpinfowp-ide-footer strong {
      color: #cdd6f4 !important;
  }
  .phpinfowp-ide-footer code {
      background: #313244 !important;
      color: #cdd6f4 !important;
      border: 1px solid #45475a !important;
      padding: 2px 4px !important;
      font-size: 11px !important;
  }
  .phpinfowp-ide-dots {
      display: flex;
      gap: 6px;
      align-items: center;
  }
  .phpinfowp-ide-dot {
      width: 11px;
      height: 11px;
      border-radius: 50%;
      display: inline-block;
  }
  .phpinfowp-ide-dot.red { background: #ff5f56; }
  .phpinfowp-ide-dot.yellow { background: #ffbd2e; }
  .phpinfowp-ide-dot.green { background: #27c93f; }
  
  .phpinfowp-ide-tabs {
      display: flex;
      margin-bottom: -13px;
      margin-left: 16px;
  }
  .phpinfowp-ide-tab {
      background: #1e1e2e;
      color: #cdd6f4;
      padding: 6px 14px;
      border-radius: 6px 6px 0 0;
      font-size: 12px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      display: flex;
      align-items: center;
      gap: 6px;
      border: 1px solid #313244;
      border-bottom: none;
  }
  .phpinfowp-ide-body {
      position: relative;
  }

  /* IDE Editor Textarea */
  .phpinfowp-ide-textarea {
      width: 100%;
      min-height: 280px;
      background: #1e1e2e !important;
      color: #cdd6f4 !important;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Fira Code", monospace !important;
      font-size: 13px !important;
      line-height: 1.6 !important;
      padding: 16px !important;
      border: none !important;
      outline: none !important;
      box-shadow: none !important;
      resize: vertical;
      display: block;
      box-sizing: border-box;
  }
  .phpinfowp-ide-textarea::placeholder {
      color: #585b70;
      font-style: italic;
  }
  .phpinfowp-ide-textarea:focus {
      outline: none !important;
      box-shadow: none !important;
  }

  /* IDE Read-only Preview */
  .phpinfowp-ide-preview {
      width: 100%;
      height: 280px;
      background: #1e1e2e;
      color: #cdd6f4;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Fira Code", monospace;
      font-size: 12px;
      line-height: 1.6;
      padding: 16px;
      margin: 0;
      overflow-y: auto;
      overflow-x: auto;
      white-space: pre-wrap;
      word-break: break-all;
      box-sizing: border-box;
  }

  /* custom scrollbars */
  .phpinfowp-ide-textarea::-webkit-scrollbar,
  .phpinfowp-ide-preview::-webkit-scrollbar {
      width: 8px;
      height: 8px;
  }
  .phpinfowp-ide-textarea::-webkit-scrollbar-track,
  .phpinfowp-ide-preview::-webkit-scrollbar-track {
      background: #181825;
  }
  .phpinfowp-ide-textarea::-webkit-scrollbar-thumb,
  .phpinfowp-ide-preview::-webkit-scrollbar-thumb {
      background: #313244;
      border-radius: 4px;
  }
  .phpinfowp-ide-textarea::-webkit-scrollbar-thumb:hover,
  .phpinfowp-ide-preview::-webkit-scrollbar-thumb:hover {
      background: #45475a;
  }

  /* Page columns grid layout */
  .phpinfowp-htaccess-layout {
      display: grid;
      grid-template-columns: minmax(0, 7fr) minmax(0, 3fr);
      gap: 20px;
      align-items: flex-start;
  }
  @media (max-width: 960px) {
      .phpinfowp-htaccess-layout {
          grid-template-columns: 1fr;
      }
  }


  .phpinfowp-section-desc {
      font-size: 13px;
      color: #666;
      line-height: 1.5;
      margin: 0 0 14px 0;
  }

  /* Action Buttons bar */
  .phpinfowp-action-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 14px;
      flex-wrap: wrap;
      gap: 10px;
  }
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
      background: #fff;
      color: #ef4444;
      border-color: #fca5a5;
  }
  .phpinfowp-btn-danger:hover {
      background: #fef2f2;
      border-color: #ef4444;
      color: #dc2626;
  }

  /* Snippet Library cards */
  .phpinfowp-snippet-card {
      background: #fff;
      border: 1px solid #dcdcde;
      border-radius: 6px;
      padding: 16px 18px;
      margin-bottom: 14px;
      box-shadow: none;
      transition: all 0.2s ease;
  }
  .phpinfowp-snippet-card:hover {
      border-color: #ccd0d4;
  }
  .phpinfowp-snippet-title-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
      flex-wrap: wrap;
      gap: 8px;
  }
  .phpinfowp-badge {
      display: inline-flex;
      align-items: center;
      font-size: 10px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
  }
  .phpinfowp-badge-pro {
      background: #e0e7ff;
      color: #4f46e5;
  }
  .phpinfowp-badge-neutral {
      background: #f1f5f9;
      color: #475569;
  }
  .phpinfowp-badge-success {
      background: #d1fae5;
      color: #059669;
  }

  /* Inline Notification Boxes */
  .phpinfowp-custom-alert {
      margin-bottom: 20px;
      padding: 12px 16px;
      border-radius: 6px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
  }
  .phpinfowp-custom-alert p {
      margin: 0;
      font-size: 13px;
      line-height: 1.5;
  }
  </style>

  <!-- Title Header -->
  <div class="phpinfowp-page-header">
      <div>
          <h1><?php _e('PHP Config Editor', 'phpinfo-wp'); ?></h1>
          <p class="phpinfowp-page-subtitle"><?php _e('Configure php.ini directives and web server rules safely from your WordPress dashboard.', 'phpinfo-wp'); ?></p>
      </div>
  </div>

  <!-- Detected Environment Status Cards -->
  <div class="phpinfowp-env-grid">
      <!-- Environment Card -->
      <div class="phpinfowp-env-card">
          <div class="phpinfowp-env-icon-box" style="background: <?php echo $mode_icon_bg; ?>; color: <?php echo $mode_color; ?>;">
              <span class="dashicons <?php echo $mode === 'htaccess' ? 'dashicons-admin-generic' : 'dashicons-networking'; ?>" style="font-size:20px; width:20px; height:20px;"></span>
          </div>
          <div class="phpinfowp-env-details">
              <div class="phpinfowp-env-label"><?php _e('Detected Server', 'phpinfo-wp'); ?></div>
              <div class="phpinfowp-env-value">
                  <?php echo esc_html($mode_label); ?>
                  <span class="phpinfowp-badge" style="background: <?php echo $mode_color; ?>15; color: <?php echo $mode_color; ?>; padding: 2px 6px;">
                      <span class="phpinfowp-pulse-dot" style="background: <?php echo $mode_color; ?>; margin-right: 4px;"></span>
                      Active
                  </span>
              </div>
              <div class="phpinfowp-env-sub">Web server settings handled via <?php echo esc_html($mode_file); ?> directives.</div>
          </div>
      </div>

      <!-- Target File Card -->
      <div class="phpinfowp-env-card">
          <div class="phpinfowp-env-icon-box" style="background: #f1f5f9; color: #475569;">
              <span class="dashicons dashicons-editor-code" style="font-size:20px; width:20px; height:20px;"></span>
          </div>
          <div class="phpinfowp-env-details">
              <div class="phpinfowp-env-label"><?php _e('Target Config File', 'phpinfo-wp'); ?></div>
              <div class="phpinfowp-env-value" style="font-family: monospace; font-size: 13.5px;"><?php echo esc_html($mode_file); ?></div>
              <div class="phpinfowp-env-sub" style="word-break: break-all; font-family: monospace; font-size: 11px; background: #f8fafc; padding: 4px 6px; border-radius: 4px; border: 1px solid #e2e8f0; margin-top: 4px;">
                  <?php echo esc_html($target_file); ?>
              </div>
          </div>
      </div>

      <!-- Cache TTL Card -->
      <div class="phpinfowp-env-card">
          <?php if ($mode === 'userini'): ?>
              <div class="phpinfowp-env-icon-box" style="background: #fef3c7; color: #d97706;">
                  <span class="dashicons dashicons-clock" style="font-size:20px; width:20px; height:20px;"></span>
              </div>
              <div class="phpinfowp-env-details">
                  <div class="phpinfowp-env-label"><?php _e('Propagation Time', 'phpinfo-wp'); ?></div>
                  <div class="phpinfowp-env-value">~<?php echo esc_html($user_ini_ttl); ?> Seconds</div>
                  <div class="phpinfowp-env-sub"><?php _e('Changes apply after FPM reload (cache TTL).', 'phpinfo-wp'); ?></div>
              </div>
          <?php else: ?>
              <div class="phpinfowp-env-icon-box" style="background: #ecfdf5; color: #10b981;">
                  <span class="dashicons dashicons-yes-alt" style="font-size:20px; width:20px; height:20px;"></span>
              </div>
              <div class="phpinfowp-env-details">
                  <div class="phpinfowp-env-label"><?php _e('Propagation Time', 'phpinfo-wp'); ?></div>
                  <div class="phpinfowp-env-value"><?php _e('Instant', 'phpinfo-wp'); ?></div>
                  <div class="phpinfowp-env-sub"><?php _e('Directives are parsed and applied immediately.', 'phpinfo-wp'); ?></div>
              </div>
          <?php endif; ?>
      </div>
  </div>

  <!-- Notifications / Alerts -->
  <?php if (!$writable): ?>
      <div class="phpinfowp-custom-alert" style="background: #fef2f2; border: 1px solid #fca5a5; border-left: 4px solid #ef4444;">
          <span class="dashicons dashicons-dismiss" style="color: #ef4444; font-size: 20px; width: 20px; height: 20px; flex-shrink: 0;"></span>
          <p style="color: #991b1b;">
              <strong><?php _e('Write permission denied.', 'phpinfo-wp'); ?></strong> The directory <code><?php echo esc_html($root_dir); ?></code> is not writable. Settings cannot be saved.
          </p>
      </div>
  <?php elseif ($notice): ?>
      <div class="phpinfowp-custom-alert" style="background: <?php echo $notice_type === 'success' ? '#f0fdf4' : '#fef2f2'; ?>; border: 1px solid <?php echo $notice_type === 'success' ? '#bbf7d0' : '#fca5a5'; ?>; border-left: 4px solid <?php echo $notice_type === 'success' ? '#22c55e' : '#ef4444'; ?>;">
          <span class="dashicons <?php echo $notice_type === 'success' ? 'dashicons-yes' : 'dashicons-dismiss'; ?>" style="color: <?php echo $notice_type === 'success' ? '#22c55e' : '#ef4444'; ?>; font-size: 20px; width: 20px; height: 20px; flex-shrink: 0;"></span>
          <p style="color: <?php echo $notice_type === 'success' ? '#166534' : '#991b1b'; ?>;">
              <?php echo wp_kses($notice, ['code' => [], 'strong' => [], 'br' => []]); ?>
          </p>
      </div>
  <?php endif; ?>

  <!-- Two Column Layout: Editor & Live File View -->
  <div class="phpinfowp-htaccess-layout">

    <!-- Left Column: Editor -->
    <div class="phpinfowp-htaccess-editor-col">
      <h2 class="phpinfowp-section-heading"><?php _e('PHP Directives', 'phpinfo-wp'); ?></h2>

      <?php
      $placeholder = $mode === 'htaccess'
          ? "upload_max_filesize 64M\npost_max_size 64M\nmax_execution_time 120\nmax_input_vars 3000"
          : "upload_max_filesize = 64M\npost_max_size = 64M\nmax_execution_time = 120\nmax_input_vars = 3000";
      ?>

      <form method="post" style="margin-bottom: 40px;">
        <input type="hidden" name="phpinfo_nonce" value="<?php echo wp_create_nonce('phpinfo_nonce'); ?>">
        
        <!-- Mock IDE Code Window -->
        <div class="phpinfowp-ide-window">
          <div class="phpinfowp-ide-header">
            <div class="phpinfowp-ide-dots">
              <span class="phpinfowp-ide-dot red"></span>
              <span class="phpinfowp-ide-dot yellow"></span>
              <span class="phpinfowp-ide-dot green"></span>
              <div class="phpinfowp-ide-tabs">
                <div class="phpinfowp-ide-tab">
                  <span class="dashicons dashicons-editor-code" style="font-size: 14px; width: 14px; height: 14px; margin-top: 2px;"></span>
                  <span><?php echo esc_html($mode_file); ?></span>
                </div>
              </div>
            </div>
            <span class="phpinfowp-badge phpinfowp-badge-neutral"><?php _e('Editor', 'phpinfo-wp'); ?></span>
          </div>
          <div class="phpinfowp-ide-body">
            <textarea name="htaccess" id="htaccess-editor" class="phpinfowp-ide-textarea"
                      placeholder="<?php echo esc_attr($placeholder); ?>"
                      spellcheck="false"><?php echo esc_textarea($existing); ?></textarea>
          </div>
          <div class="phpinfowp-ide-footer">
            <?php if ($mode === 'htaccess'): ?>
              One directive per line &mdash; write the name and value only, without the <code>php_value</code> prefix.
              We will wrap your configuration inside a <code># BEGIN phpinfo-wp</code> block.<br>
              <strong><?php _e('Auto-rollback:', 'phpinfo-wp'); ?></strong> If the site returns HTTP 500 after saving, your original <code>.htaccess</code> will be restored automatically.
            <?php else: ?>
              One directive per line in standard <code>php.ini</code> format: <strong>directive = value</strong>.<br>
              Shorthands like <strong><?php _e('directive value', 'phpinfo-wp'); ?></strong> are normalized automatically.
              Your settings are placed in a <code>; BEGIN phpinfo-wp</code> block inside <code>.user.ini</code>.
            <?php endif; ?>
          </div>
        </div>

        <div class="phpinfowp-action-row">
          <button type="submit" name="save" class="phpinfowp-btn phpinfowp-btn-primary">
            <span class="dashicons dashicons-saved" style="font-size: 16px; width: 16px; height: 16px;"></span>
            Save PHP Settings
          </button>
          
          <?php if ($mode === 'htaccess'): ?>
          <div style="display:flex; gap:8px">
            <button type="submit" name="backup" class="phpinfowp-btn phpinfowp-btn-secondary"
                    onclick="return confirm('Create a .htaccess.bak backup file?')">
              <span class="dashicons dashicons-backup" style="font-size: 16px; width: 16px; height: 16px;"></span>
              Backup
            </button>
            <button type="submit" name="restore" class="phpinfowp-btn phpinfowp-btn-danger"
                    onclick="return confirm('Restore .htaccess from the last backup? Current changes will be lost.')">
              <span class="dashicons dashicons-undo" style="font-size: 16px; width: 16px; height: 16px;"></span>
              Restore Backup
            </button>
          </div>
          <?php endif; ?>
        </div>
      </form>

      <!-- Snippet Library -->
      <hr style="margin: 40px 0 30px; border:0; border-top: 1px solid #e2e8f0;">
      <h2 class="phpinfowp-section-heading"><?php _e('Web Server Snippet Library', 'phpinfo-wp'); ?></h2>
      
      <?php if ($snippet_server === 'nginx'): ?>
          <p class="phpinfowp-section-desc">
              <strong><?php _e('Nginx Detected.', 'phpinfo-wp'); ?></strong> <?php _e('Nginx does not use <code>.htaccess</code>. Applying these rules requires root access to edit your <code>nginx.conf</code>.', 'phpinfo-wp'); ?>
          </p>
          <div class="phpinfowp-custom-alert" style="background: #f8fafc; border: 1px solid #cbd5e1; border-left: 4px solid #64748b; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
              <span class="dashicons dashicons-shield" style="color: #64748b; font-size: 20px; width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px;"></span>
              <p style="color: #334155; font-size: 13px; line-height: 1.5; margin: 0; font-weight: 500;">
                  <strong><?php _e('No root access?', 'phpinfo-wp'); ?></strong> <?php _e('If you are on managed WordPress hosting, caching is usually handled for you automatically. Otherwise, we highly recommend using a free CDN like <strong>Cloudflare</strong> to handle caching and compression automatically without editing server files.', 'phpinfo-wp'); ?>
              </p>
          </div>
      <?php else: ?>
          <p class="phpinfowp-section-desc">
              <strong><?php _e('Apache/LiteSpeed Detected.', 'phpinfo-wp'); ?></strong> Click any button below to instantly inject the optimized rules directly into your <code>.htaccess</code> file.
          </p>
      <?php endif; ?>

      <!-- Cache warning banner above snippets -->
      <div class="phpinfowp-custom-alert" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
          <span class="dashicons dashicons-info" style="color: #3b82f6; font-size: 20px; width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px;"></span>
          <p style="color: #1e3a8a; font-size: 13px; line-height: 1.5; margin: 0; font-weight: 500;">
              <strong><?php _e('Clear Cache:', 'phpinfo-wp'); ?></strong> You must clear your cache (plugin cache, server cache, CDN, and browser cache) after injecting or setting the code manually to see the results!
          </p>
      </div>

      <?php
      // Pre-calculate which snippets are injected for the bulk bar
      $preview_for_bulk = file_exists("$root_dir.htaccess") ? file_get_contents("$root_dir.htaccess") : '';
      $all_injected = true;
      $none_injected = true;
      foreach ($snippets as $_sid => $_snip) {
          $block_check = strtoupper($_sid);
          if (strpos($preview_for_bulk, "# BEGIN phpinfo-wp-{$block_check}") !== false) {
              $none_injected = false;
          } else {
              $all_injected = false;
          }
      }
      $is_pro_bulk = Phpinfo_WP_License::is_valid();
      ?>

      <?php if ($snippet_server === 'apache'): ?>
      <!-- Bulk Action Bar -->
      <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:20px; padding:14px 18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
          <div>
              <p style="margin:0; font-size:13px; font-weight:700; color:#0f172a;"><?php _e('Bulk Actions', 'phpinfo-wp'); ?></p>
              <p style="margin:2px 0 0; font-size:12px; color:#64748b;"><?php _e('Inject or roll back all 4 snippets at once.', 'phpinfo-wp'); ?></p>
          </div>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
              <?php if ($is_pro_bulk): ?>
                  <form method="post" style="margin:0;">
                      <input type="hidden" name="phpinfo_nonce" value="<?php echo wp_create_nonce('phpinfo_nonce'); ?>">
                      <button type="submit" name="inject_all_snippets" value="1"
                          class="phpinfowp-btn phpinfowp-btn-primary"
                          <?php echo $all_injected ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>
                          onclick="return confirm('Inject all 4 optimization snippets into .htaccess?')">
                          <span class="dashicons dashicons-upload" style="font-size:16px; width:16px; height:16px;"></span>
                          Inject All 4
                      </button>
                  </form>
                  <form method="post" style="margin:0;">
                      <input type="hidden" name="phpinfo_nonce" value="<?php echo wp_create_nonce('phpinfo_nonce'); ?>">
                      <button type="submit" name="rollback_all_snippets" value="1"
                          class="phpinfowp-btn phpinfowp-btn-danger"
                          <?php echo $none_injected ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>
                          onclick="return confirm('Remove ALL 4 injected snippets from .htaccess?')">
                          <span class="dashicons dashicons-undo" style="font-size:16px; width:16px; height:16px;"></span>
                          Rollback All 4
                      </button>
                  </form>
              <?php else: ?>
                  <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="phpinfowp-btn phpinfowp-btn-primary" style="background:#4f46e5; border-color:#4338ca;">
                      <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px;"></span>
                      Upgrade to Pro
                  </a>
              <?php endif; ?>
          </div>
      </div>
      <?php endif; ?>

      <div class="phpinfowp-snippets">
          <?php 
          $is_pro = Phpinfo_WP_License::is_valid();
          foreach ($snippets as $id => $snippet): 
              $real_code = $snippet_server === 'nginx' ? $snippet['nginx'] : $snippet['apache'];
              $snippet_code = $is_pro ? $real_code : ($snippet_server === 'nginx' 
                  ? "# Nginx configuration rule\n# Unlock phpinfo() WP Pro to copy this optimization rule."
                  : "# Apache/LiteSpeed .htaccess rule\n# Unlock phpinfo() WP Pro to copy this optimization rule.");
              $is_injected = ($snippet_server === 'apache' && strpos($preview, "# BEGIN phpinfo-wp-" . strtoupper($id)) !== false);
          ?>
              <div class="phpinfowp-snippet-card" style="<?php echo !$is_pro ? 'border-color: #e2e8f0; background: #fafafa;' : ''; ?>">
                  <div class="phpinfowp-snippet-title-row">
                      <h3 style="margin:0; font-size:15px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                          <?php echo esc_html($snippet['title']); ?>
                          <?php if (!$is_pro): ?>
                              <span class="dashicons dashicons-lock" style="font-size:16px; width:16px; height:16px; color:#6366f1; margin-top:2px;"></span>
                          <?php endif; ?>
                      </h3>
                      <div style="display:flex; gap:6px;">
                          <?php if (!$is_pro): ?>
                              <span class="phpinfowp-badge phpinfowp-badge-pro" style="background:#e0e7ff; color:#4f46e5;"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                          <?php endif; ?>
                          <span class="phpinfowp-badge phpinfowp-badge-neutral"><?php echo $snippet_server === 'nginx' ? 'Nginx' : 'Apache'; ?></span>
                      </div>
                  </div>
                  <p class="phpinfowp-snippet-desc" style="margin-bottom: 12px;"><?php echo esc_html($snippet['desc']); ?></p>
                  
                  <div style="margin-bottom: 14px; position: relative;">
                      <div class="phpinfowp-ide-window" style="margin-bottom:0; box-shadow:none; border-radius:6px; <?php echo !$is_pro ? 'filter: blur(4px); opacity: 0.6; pointer-events: none; user-select: none;' : ''; ?>">
                          <div class="phpinfowp-ide-header" style="padding: 6px 12px; background: #181825;">
                              <div class="phpinfowp-ide-dots">
                                  <span class="phpinfowp-ide-dot red" style="width:7px; height:7px;"></span>
                                  <span class="phpinfowp-ide-dot yellow" style="width:7px; height:7px;"></span>
                                  <span class="phpinfowp-ide-dot green" style="width:7px; height:7px;"></span>
                              </div>
                              <span style="font-size:11px; color:#585b70; font-family:monospace;"><?php echo $snippet_server === 'nginx' ? 'nginx.conf' : '.htaccess'; ?></span>
                          </div>
                          <div class="phpinfowp-ide-body">
                              <pre style="margin:0; padding:10px 14px; background:#1e1e2e; color:#a6adc8; font-family:ui-monospace,SFMono-Regular,Consolas,monospace; font-size:11.5px; line-height:1.5; overflow-x:auto; max-height:120px;"><?php echo esc_html($snippet_code); ?></pre>
                          </div>
                      </div>
                  </div>
                  
                  <?php if (!$is_pro): ?>
                      <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 12px 14px; display: flex; flex-direction: column; gap: 8px; align-items: flex-start;">
                          <p style="margin: 0; font-size: 12.5px; color: #475569; line-height: 1.4;">
                              ⚡ <strong><?php _e('These settings can make your site up to 2x faster!', 'phpinfo-wp'); ?></strong> Unlock this optimization snippet and boost your performance instantly.
                          </p>
                          <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; flex-wrap: wrap; gap: 10px;">
                              <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="phpinfowp-btn phpinfowp-btn-primary" style="padding: 6px 12px; font-size: 12.5px; background: #4f46e5; border-color: #4338ca;">
                                  <span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                  Upgrade to Pro
                              </a>
                              <span style="font-size: 11px; color: #64748b; font-style: italic;"><?php _e('14-day risk-free refund guarantee', 'phpinfo-wp'); ?></span>
                          </div>
                      </div>
                  <?php else: ?>
                      <?php if ($snippet_server === 'nginx'): ?>
                          <button type="button" class="phpinfowp-btn phpinfowp-btn-secondary" onclick="phpinfowp_copy_snippet(this)">
                              <span class="dashicons dashicons-admin-page" style="font-size:16px; width:16px; height:16px;"></span>
                              Copy to Clipboard
                          </button>
                      <?php else: ?>
                          <?php if ($is_injected): ?>
                              <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                  <button type="button" class="phpinfowp-btn" style="background: #ecfdf5; color: #059669; border-color: #a7f3d0; cursor: default;" disabled>
                                      <span class="dashicons dashicons-yes" style="font-size:16px; width:16px; height:16px;"></span>
                                      Injected
                                  </button>
                                  <form method="post" style="margin: 0;">
                                      <input type="hidden" name="phpinfo_nonce" value="<?php echo wp_create_nonce('phpinfo_nonce'); ?>">
                                      <button type="submit" name="rollback_snippet" value="<?php echo esc_attr($id); ?>" class="phpinfowp-btn phpinfowp-btn-danger"
                                          onclick="return confirm('Remove the &quot;<?php echo esc_js($snippet['title']); ?>&quot; block from .htaccess?')">
                                          <span class="dashicons dashicons-undo" style="font-size:16px; width:16px; height:16px;"></span>
                                          Rollback
                                      </button>
                                  </form>
                              </div>
                          <?php else: ?>
                              <form method="post" style="margin: 0;">
                                  <input type="hidden" name="phpinfo_nonce" value="<?php echo wp_create_nonce('phpinfo_nonce'); ?>">
                                  <button type="submit" name="append_snippet" value="<?php echo esc_attr($id); ?>" class="phpinfowp-btn phpinfowp-btn-secondary">
                                      <span class="dashicons dashicons-upload" style="font-size:16px; width:16px; height:16px;"></span>
                                      Inject into .htaccess
                                  </button>
                              </form>
                          <?php endif; ?>
                      <?php endif; ?>
                  <?php endif; ?>
              </div>
          <?php endforeach; ?>
      </div>

    </div>

    <!-- Right Column: Live file preview -->
    <div class="phpinfowp-htaccess-preview-col">
      <h2 class="phpinfowp-section-heading">Current <code><?php echo esc_html($mode_file); ?></code></h2>
      
      <!-- Mock IDE Preview Window -->
      <div class="phpinfowp-ide-window">
        <div class="phpinfowp-ide-header">
          <div class="phpinfowp-ide-dots">
            <span class="phpinfowp-ide-dot red"></span>
            <span class="phpinfowp-ide-dot yellow"></span>
            <span class="phpinfowp-ide-dot green"></span>
            <div class="phpinfowp-ide-tabs">
              <div class="phpinfowp-ide-tab">
                <span class="dashicons dashicons-visibility" style="font-size: 14px; width: 14px; height: 14px; margin-top: 2px;"></span>
                <span><?php echo esc_html($mode_file); ?></span>
              </div>
            </div>
          </div>
          <span class="phpinfowp-badge phpinfowp-badge-pro"><?php _e('Read-Only', 'phpinfo-wp'); ?></span>
        </div>
        <div class="phpinfowp-ide-body">
          <?php if ($preview): ?>
            <pre class="phpinfowp-ide-preview"><?php echo esc_html($preview); ?></pre>
          <?php else: ?>
            <div class="phpinfowp-ide-preview" style="display:flex; align-items:center; justify-content:center; color:#585b70; font-style:italic;"><?php _e('File does not exist yet — it will be created on first save.', 'phpinfo-wp'); ?></div>
          <?php endif; ?>
        </div>
        <div class="phpinfowp-ide-footer"><?php _e('Active directives on disk.', 'phpinfo-wp'); ?></div>
      </div>
      
    </div>

  </div>
</div>

<script>
function phpinfowp_copy_snippet(btn) {
    const card = btn.closest('.phpinfowp-snippet-card');
    const code = card.querySelector('pre').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="dashicons dashicons-yes" style="font-size:16px; width:16px; height:16px;"></span> Copied!';
        btn.style.background = '#d1fae5';
        btn.style.borderColor = '#059669';
        btn.style.color = '#047857';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 2000);
    }).catch(err => {
        alert('Failed to copy text: ' + err);
    });
}
</script>

