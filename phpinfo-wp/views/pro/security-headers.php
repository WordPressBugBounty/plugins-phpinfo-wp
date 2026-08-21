<?php
defined('ABSPATH') or die('Unauthorized Access');

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

if ($is_pro && isset($_POST['phpinfowp_recheck']) && check_admin_referer('phpinfowp_sec_nonce')) {
    Phpinfo_WP_Security_Headers::bust_cache();
}

$audit = Phpinfo_WP_Security_Headers::get_cached();
?>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1>Security Headers <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('HTTP response header audit — graded against OWASP recommendations', 'phpinfo-wp'); ?></p>
        </div>
        <?php if ($is_pro): ?>
            <form method="post">
                <?php wp_nonce_field('phpinfowp_sec_nonce'); ?>
                <input type="hidden" name="phpinfowp_recheck" value="1">
                <button type="submit" class="button button-secondary"><?php _e('Re-check headers', 'phpinfo-wp'); ?></button>
            </form>
        <?php endif; ?>
    </div>



    <?php if (isset($audit['error'])): ?>
        <div class="notice notice-error inline">
            <p>Could not fetch headers: <strong><?php echo esc_html($audit['error']); ?></strong></p>
        </div>
    <?php else: ?>

        <!-- Score card (Visible to all users) -->
        <div class="phpinfowp-score-card">
            <div class="phpinfowp-grade-circle grade-<?php echo esc_attr(strtolower(str_replace('+', 'plus', $audit['grade'] ?? 'f'))); ?>">
                <?php echo esc_html($audit['grade'] ?? '—'); ?>
            </div>
            <div class="phpinfowp-score-card-body">
                <div class="phpinfowp-score-card-value"><?php echo esc_html($audit['score'] ?? 0); ?><span>/100</span></div>
                <div class="phpinfowp-score-card-meta">
                    <?php
                    $passed  = count(array_filter($audit['results'] ?? [], function ($r) { return !empty($r['present']); }));
                    $total   = count($audit['results'] ?? []);
                    $missing = $total - $passed;
                    ?>
                    <span style="color:#00a32a"><strong><?php echo $passed; ?></strong> headers set</span>
                    &nbsp;&middot;&nbsp;
                    <span style="color:<?php echo $missing ? '#d63638' : '#00a32a'; ?>"><strong><?php echo $missing; ?></strong> missing</span>
                    &nbsp;&middot;&nbsp;
                    <code style="font-size:11px"><?php echo esc_html($audit['url'] ?? ''); ?></code>
                    <?php if (!empty($audit['cached'])): ?>
                        &nbsp;&middot;&nbsp; <em style="color:#888"><?php _e('cached', 'phpinfo-wp'); ?></em>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($is_free_preview): ?>
            <!-- Free preview: compact skeleton rows + centered upgrade card (fits in single viewpoint) -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:10px; min-height:260px; display:flex; align-items:center; justify-content:center;">
                
                <!-- Skeleton background preview -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.6;">
                    <?php for ($sk = 0; $sk < 3; $sk++): ?>
                    <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #cbd5e1; border-radius:6px; padding:12px 16px; margin-bottom:8px; display:flex; gap:12px; align-items:center;">
                        <div style="width:20px; height:20px; border-radius:50%; background:#e2e8f0; flex-shrink:0;"></div>
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                <div style="height:12px; width:<?php echo [35, 45, 30][$sk]; ?>%; background:#cbd5e1; border-radius:4px;"></div>
                                <div style="height:10px; width:40px; background:#e2e8f0; border-radius:4px;"></div>
                            </div>
                            <div style="height:10px; width:80%; background:#f1f5f9; border-radius:4px;"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(246,249,252,0.4) 0%, rgba(246,249,252,0.95) 40%, #f6f9fc 100%); pointer-events:none;"></div>

                <!-- Upgrade card floating directly in view -->
                <div style="position:relative; z-index:2; width:100%; max-width:480px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px 28px; box-shadow:0 6px 20px -4px rgba(0,0,0,0.07); text-align:center; margin:12px auto;">
                    <span class="dashicons dashicons-lock" style="font-size:30px; width:30px; height:30px; color:#777BB3; display:inline-block; margin-bottom:6px;"></span>
                    <h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#1d2327;"><?php _e('Security Header Directives & Fixes Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 4px; font-size:13px; color:#475569; line-height:1.5; max-width:400px; margin-left:auto; margin-right:auto;">
                        <?php _e('Unlock exact OWASP header recommendations, 1-click .htaccess/nginx snippets, CSP generator, and real-time security alerts.', 'phpinfo-wp'); ?>
                    </p>
                    <p style="margin:0 0 16px; font-size:12px; color:#94a3b8;">
                        <?php _e('Your security score and header counts above are real — upgrade to get the exact code fixes for missing headers.', 'phpinfo-wp'); ?>
                    </p>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3; height:38px; line-height:36px; font-size:13.5px; font-weight:600; padding:0 22px; border-radius:6px; text-decoration:none; display:inline-block;">
                        <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>

        <?php else: ?>

            <!-- Header rows -->
            <div class="phpinfowp-sec-rows" style="margin-top:20px">
                <?php foreach (($audit['results'] ?? []) as $row):
                    $present = $row['present'];
                    $border  = $present ? ($row['warning'] ? '#dba617' : '#00a32a') : '#d63638';
                    $bg      = $present ? ($row['warning'] ? '#fffbf0' : '#f0faf2') : '#fff4f4';
                ?>
                <div class="phpinfowp-sec-row" style="border-left-color:<?php echo $border; ?>;background:<?php echo $bg; ?>">
                    <div class="phpinfowp-sec-row-icon">
                        <?php if ($present): ?>
                            <span class="dashicons <?php echo $row['warning'] ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>"
                                  style="color:<?php echo $row['warning'] ? '#dba617' : '#00a32a'; ?>"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color:#d63638"></span>
                        <?php endif; ?>
                    </div>
                    <div class="phpinfowp-sec-row-body">
                        <div class="phpinfowp-sec-row-title">
                            <strong><?php echo esc_html($row['label']); ?></strong>
                            <span class="phpinfowp-sec-row-points" style="color:<?php echo $present ? '#00a32a' : '#d63638'; ?>">
                                <?php echo $present ? '+' . esc_html($row['points']) : '0'; ?> pts
                            </span>
                        </div>
                        <div class="phpinfowp-sec-row-desc"><?php echo esc_html($row['desc']); ?></div>
                        <?php if ($row['value']): ?>
                            <code class="phpinfowp-sec-row-value"><?php echo esc_html($row['value']); ?></code>
                        <?php else: ?>
                            <span class="phpinfowp-sec-row-missing"><?php _e('not set', 'phpinfo-wp'); ?></span>
                        <?php endif; ?>
                        <?php if ($row['warning']): ?>
                            <div class="phpinfowp-sec-row-warn">⚠️ <?php echo esc_html($row['warning']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>
