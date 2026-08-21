<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

$scan_results = Phpinfo_WP_Permissions::scan();
if (empty($scan_results)) return;

$wp_config = $scan_results['wp_config'];
$dangerous = $scan_results['dangerous'];
$mismatch  = $scan_results['mismatch'];
$scanned   = $scan_results['scanned'];
$php_user  = $scan_results['php_user'];
$php_uid   = $scan_results['php_uid'];

$has_issues = !empty($dangerous) || !empty($mismatch) || ($wp_config && $wp_config['is_dangerous']);
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1>Permissions & Ownership Auditor <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Deep scan of core files, plugins, and themes to detect security risks and auto-update failures.', 'phpinfo-wp'); ?></p>
        </div>
    </div>



    <!-- Overview Card (Visible to all users) -->
    <div style="background:#fff; border:1px solid #ccd0d4; padding:24px; border-radius:4px; display:flex; gap:32px; box-shadow:0 1px 1px rgba(0,0,0,0.04); margin-bottom:32px; flex-wrap:wrap;">
        <div>
            <div style="font-size:12px; color:#666; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;"><?php _e('PHP Execution User', 'phpinfo-wp'); ?></div>
            <div style="font-size:24px; font-weight:700; margin-top:4px;"><code><?php echo esc_html($php_user); ?></code></div>
            <div style="font-size:12px; color:#888; margin-top:4px;">UID: <?php echo esc_html($php_uid); ?></div>
        </div>
        <div style="border-left:1px solid #eee; padding-left:32px;">
            <div style="font-size:12px; color:#666; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;"><?php _e('Files Scanned', 'phpinfo-wp'); ?></div>
            <div style="font-size:24px; font-weight:700; margin-top:4px;"><?php echo number_format($scanned); ?></div>
            <div style="font-size:12px; color:#888; margin-top:4px;"><?php _e('Capped at 5,000 for safety', 'phpinfo-wp'); ?></div>
        </div>
        <div style="border-left:1px solid #eee; padding-left:32px;">
            <div style="font-size:12px; color:#666; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;"><?php _e('Status', 'phpinfo-wp'); ?></div>
            <?php if ($has_issues): ?>
                <div style="font-size:24px; font-weight:700; margin-top:4px; color:#d63638;"><?php _e('Action Required', 'phpinfo-wp'); ?></div>
            <?php else: ?>
                <div style="font-size:24px; font-weight:700; margin-top:4px; color:#00a32a;"><?php _e('Secure', 'phpinfo-wp'); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_free_preview): ?>
        <!-- Free preview: compact skeleton rows + centered upgrade card (fits in single viewpoint) -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:10px; min-height:260px; display:flex; align-items:center; justify-content:center;">
            
            <!-- Skeleton content -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.5;">
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                    <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:8px; margin-bottom:8px;">
                        <div style="height:11px; width:45%; background:#cbd5e1; border-radius:4px;"></div>
                        <div style="height:11px; width:20%; background:#cbd5e1; border-radius:4px;"></div>
                    </div>
                    <?php for ($sk = 0; $sk < 3; $sk++): ?>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;">
                        <div style="height:10px; width:<?php echo [55, 45, 60][$sk]; ?>%; background:#e2e8f0; border-radius:4px;"></div>
                        <div style="height:10px; width:15%; background:#e2e8f0; border-radius:4px;"></div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(246,249,252,0.4) 0%, rgba(246,249,252,0.95) 40%, #f6f9fc 100%); pointer-events:none;"></div>

            <!-- Upgrade card floating over skeleton -->
            <div style="position:relative; z-index:2; width:100%; max-width:480px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px 28px; box-shadow:0 6px 20px -4px rgba(0,0,0,0.07); text-align:center; margin:12px auto;">
                <span class="dashicons dashicons-lock" style="font-size:30px; width:30px; height:30px; color:#777BB3; display:inline-block; margin-bottom:6px;"></span>
                <h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#1d2327;"><?php _e('Permission & Ownership Details Locked', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 4px; font-size:13px; color:#475569; line-height:1.5; max-width:400px; margin-left:auto; margin-right:auto;">
                    <?php _e('Unlock the exact file paths of 0777 writeable files, ownership mismatches causing update failures, and 1-click SSH chmod/chown fix commands.', 'phpinfo-wp'); ?>
                </p>
                <p style="margin:0 0 16px; font-size:12px; color:#94a3b8;">
                    <?php _e('Your PHP execution user and file scan stats above are real — upgrade to identify vulnerable files.', 'phpinfo-wp'); ?>
                </p>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3; height:38px; line-height:36px; font-size:13.5px; font-weight:600; padding:0 22px; border-radius:6px; text-decoration:none; display:inline-block;">
                    <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>

    <?php else: ?>

        <?php if (!$has_issues): ?>
            <div style="background:#e8f5e9; border:1px solid #c8e6c9; padding:24px; border-radius:4px; display:flex; align-items:center; gap:16px;">
                <span class="dashicons dashicons-shield-alt" style="color:#00a32a; font-size:40px; width:40px; height:40px;"></span>
                <div>
                    <strong style="color:#1b5e20; font-size:16px;"><?php _e('Filesystem is Secure', 'phpinfo-wp'); ?></strong>
                    <p style="margin:4px 0 0; color:#2e7d32; font-size:14px;"><?php _e('No dangerous 777 permissions were found, and file ownership matches the PHP process (meaning native plugin updates will work perfectly).', 'phpinfo-wp'); ?></p>
                </div>
            </div>
        <?php else: ?>

            <!-- wp-config.php Check -->
            <h2 class="phpinfowp-section-heading" style="display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-admin-generic" style="color:#2271b1;"></span> Core Configuration
            </h2>
            <?php if ($wp_config): ?>
                <table class="wp-list-table widefat striped" style="border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,0.04); margin-bottom:32px;">
                    <thead><tr><th><?php _e('File', 'phpinfo-wp'); ?></th><th><?php _e('Permissions', 'phpinfo-wp'); ?></th><th><?php _e('Owner', 'phpinfo-wp'); ?></th><th><?php _e('Status', 'phpinfo-wp'); ?></th></tr></thead>
                    <tbody>
                        <tr>
                            <td><code><?php echo esc_html($wp_config['path']); ?></code></td>
                            <td><code style="font-weight:700; color:<?php echo $wp_config['is_dangerous'] ? '#d63638' : '#00a32a'; ?>"><?php echo esc_html($wp_config['perms']); ?></code></td>
                            <td><?php echo esc_html($wp_config['owner']); ?></td>
                            <td>
                                <?php if ($wp_config['is_dangerous']): ?>
                                    <span style="color:#d63638; font-weight:600;"><span class="dashicons dashicons-warning" style="font-size:16px; margin-top:2px;"></span> CRITICAL RISK</span>
                                <?php else: ?>
                                    <span style="color:#00a32a; font-weight:600;"><span class="dashicons dashicons-yes" style="font-size:16px; margin-top:2px;"></span> SECURE</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p><code>wp-config.php</code> could not be located.</p>
            <?php endif; ?>

            <!-- Dangerous Permissions List -->
            <?php if (!empty($dangerous)): ?>
                <h2 class="phpinfowp-section-heading" style="display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-unlock" style="color:#d63638;"></span> Dangerous Permissions Found
                </h2>
                <p class="description" style="margin-bottom:12px;"><?php _e('These files or folders are world-writable (777 or 666). Any other user on the server can modify them.', 'phpinfo-wp'); ?></p>
                <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; max-height:400px; overflow-y:auto; margin-bottom:32px;">
                    <table class="wp-list-table widefat striped" style="border:none; margin:0;">
                        <thead><tr><th><?php _e('Path', 'phpinfo-wp'); ?></th><th><?php _e('Type', 'phpinfo-wp'); ?></th><th><?php _e('Permissions', 'phpinfo-wp'); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($dangerous as $item): ?>
                                <tr>
                                    <td><code><?php echo esc_html(str_replace(ABSPATH, '', $item['path'])); ?></code></td>
                                    <td><?php echo $item['type'] === 'dir' ? 'Directory' : 'File'; ?></td>
                                    <td><code style="color:#d63638; font-weight:700;"><?php echo esc_html($item['perms']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Ownership Mismatches List -->
            <?php if (!empty($mismatch)): ?>
                <h2 class="phpinfowp-section-heading" style="display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-admin-users" style="color:#dba617;"></span> Ownership Mismatches
                </h2>
                <p class="description" style="margin-bottom:12px;">These files are owned by a different user than the PHP process (<code><?php echo esc_html($php_user); ?></code>). This usually causes WordPress to ask for FTP credentials when installing plugins.</p>
                <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; max-height:400px; overflow-y:auto; margin-bottom:32px;">
                    <table class="wp-list-table widefat striped" style="border:none; margin:0;">
                        <thead><tr><th><?php _e('Path', 'phpinfo-wp'); ?></th><th><?php _e('Current Owner', 'phpinfo-wp'); ?></th><th><?php _e('Required Owner', 'phpinfo-wp'); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($mismatch as $item): ?>
                                <tr>
                                    <td><code><?php echo esc_html(str_replace(ABSPATH, '', $item['path'])); ?></code></td>
                                    <td><code style="color:#dba617;"><?php echo esc_html($item['owner']); ?></code></td>
                                    <td><code style="color:#00a32a;"><?php echo esc_html($php_user); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($mismatch) >= 20): ?>
                        <div style="padding:12px 16px; background:#f9f9f9; border-top:1px solid #ddd; font-size:12px; color:#666;">
                            <em><?php _e('Showing first 20 examples only. Fixing the root folder recursively will solve the rest.', 'phpinfo-wp'); ?></em>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Fix Instructions -->
            <h2 class="phpinfowp-section-heading" style="margin-top:40px; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-editor-code" style="color:#2271b1;"></span> How to Fix (SSH Commands)
            </h2>
            <p class="description" style="margin-bottom:16px; max-width:800px;"><?php _e('Log into your server via SSH. Copy and paste the following bash commands to automatically set the correct, secure ownership and permissions for your entire WordPress installation.', 'phpinfo-wp'); ?></p>

            <div style="background:#1e1e1e; padding:20px; border-radius:6px; font-family:monospace; font-size:13px; line-height:1.6; color:#d4d4d4; overflow-x:auto;">
                <div style="color:#6a9955; margin-bottom:8px;"># 1. Fix Ownership (Allow native WP updates)</div>
                <div><span style="color:#569cd6;"><?php _e('chown', 'phpinfo-wp'); ?></span> -R <?php echo esc_html($php_user . ':' . $php_user); ?> <?php echo esc_html(ABSPATH); ?></div>
                
                <div style="color:#6a9955; margin-top:16px; margin-bottom:8px;"># 2. Fix Directory Permissions (755)</div>
                <div><span style="color:#569cd6;"><?php _e('find', 'phpinfo-wp'); ?></span> <?php echo esc_html(ABSPATH); ?> -type d -exec <span style="color:#569cd6;"><?php _e('chmod', 'phpinfo-wp'); ?></span> 755 {} \;</div>
                
                <div style="color:#6a9955; margin-top:16px; margin-bottom:8px;"># 3. Fix File Permissions (644)</div>
                <div><span style="color:#569cd6;"><?php _e('find', 'phpinfo-wp'); ?></span> <?php echo esc_html(ABSPATH); ?> -type f -exec <span style="color:#569cd6;"><?php _e('chmod', 'phpinfo-wp'); ?></span> 644 {} \;</div>
                
                <?php if ($wp_config): ?>
                    <div style="color:#6a9955; margin-top:16px; margin-bottom:8px;"># 4. Lock down wp-config.php (600)</div>
                    <div><span style="color:#569cd6;"><?php _e('chmod', 'phpinfo-wp'); ?></span> 600 <?php echo esc_html($wp_config['path']); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
