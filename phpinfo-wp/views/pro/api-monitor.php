<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;

$stats = Phpinfo_WP_API_Monitor::get_stats();

$total_time = 0;
$total_reqs = 0;
$slowest_domain = null;
$slowest_max = 0;

foreach ($stats as $host => $data) {
    $total_time += $data['total_time'];
    $total_reqs += $data['count'];
    if ($data['max_time'] > $slowest_max) {
        $slowest_max = $data['max_time'];
        $slowest_domain = $host;
    }
}

$per_page    = 15;
$total       = count($stats);
$total_pages = max(1, (int) ceil($total / $per_page));
$paged       = max(1, min($total_pages, (int) ($_GET['paged'] ?? 1)));
$paged_stats = array_slice($stats, ($paged - 1) * $per_page, $per_page, true);
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1>
                <?php _e('API Monitor', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Track slow outbound API requests that silently block page loads across all frontend and admin traffic.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>

    <?php if ($is_free_preview): ?>
        <!-- Free preview: contextual preview table + centered upgrade card -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
            
            <!-- Background contextual preview table -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
                    <table style="width:100%; border-collapse:collapse; font-size:12.5px; text-align:left;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#475569; font-size:11px; text-transform:uppercase; font-weight:700;">
                                <th style="padding:10px 14px;"><?php _e('Domain (Endpoint)', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px;"><?php _e('Requests', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px;"><?php _e('Avg Time', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px;"><?php _e('Max Time', 'phpinfo-wp'); ?></th>
                                <th style="padding:10px 14px;"><?php _e('Errors/Timeouts', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $preview_stats = [];
                            if (!empty($stats)) {
                                $count = 0;
                                foreach ($stats as $host => $data) {
                                    $avg = $data['count'] > 0 ? ($data['total_time'] / $data['count']) : 0;
                                    $preview_stats[] = [
                                        'host'    => $host,
                                        'count'   => $data['count'],
                                        'avg'     => number_format($avg, 2) . 's',
                                        'max'     => number_format($data['max_time'], 2) . 's',
                                        'errors'  => (int) ($data['errors'] ?? 0),
                                    ];
                                    if (++$count >= 3) break;
                                }
                            }
                            if (empty($preview_stats)) {
                                $preview_stats = [
                                    ['host' => 'api.wordpress.org', 'count' => 14, 'avg' => '0.42s', 'max' => '1.18s', 'errors' => 0],
                                    ['host' => 'downloads.wordpress.org', 'count' => 6, 'avg' => '0.65s', 'max' => '1.42s', 'errors' => 0],
                                    ['host' => 'wordpress.org', 'count' => 8, 'avg' => '0.31s', 'max' => '0.78s', 'errors' => 0],
                                ];
                            }
                            foreach ($preview_stats as $ps):
                            ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:10px 14px;"><strong><code><?php echo esc_html($ps['host']); ?></code></strong></td>
                                    <td style="padding:10px 14px;"><?php echo (int) $ps['count']; ?></td>
                                    <td style="padding:10px 14px;"><?php echo esc_html($ps['avg']); ?></td>
                                    <td style="padding:10px 14px; color:<?php echo (float)$ps['max'] > 1.5 ? '#d63638' : '#00a32a'; ?>; font-weight:600;"><?php echo esc_html($ps['max']); ?></td>
                                    <td style="padding:10px 14px; color:<?php echo $ps['errors'] > 0 ? '#d63638' : '#64748b'; ?>; font-weight:<?php echo $ps['errors'] > 0 ? '700' : '400'; ?>;"><?php echo $ps['errors'] > 0 ? sprintf(__('%d Errors', 'phpinfo-wp'), $ps['errors']) : '0'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

            <!-- Floating Upgrade Card -->
            <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                <span class="dashicons dashicons-rest-api" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('External API Bottleneck Monitor Locked', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                    <?php _e('Identify slow 3rd-party HTTP requests and silent timeouts that secretly block page loads and degrade TTFB.', 'phpinfo-wp'); ?>
                </p>
                <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">⏱️</span>
                        <span><strong><?php _e('Outbound Latency Profiler:', 'phpinfo-wp'); ?></strong> <?php _e('Tracks real-time HTTP response times for 3rd-party services that block synchronous PHP execution.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">⚠️</span>
                        <span><strong><?php _e('Timeout & Error Diagnostics:', 'phpinfo-wp'); ?></strong> <?php _e('Logs silent connection timeouts and HTTP 5xx failures across plugins and themes.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">📊</span>
                        <span><strong><?php _e('24-Hour Domain Call Breakdown:', 'phpinfo-wp'); ?></strong> <?php _e('Pinpoints exactly which external services consume the most page load execution time.', 'phpinfo-wp'); ?></span>
                    </div>
                </div>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                    <?php _e('Unlock API Monitor with Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>

    <?php else: ?>

        <?php if (!$stats): ?>
            <div class="notice notice-info inline">
                <p><strong><?php _e('Monitoring Active.', 'phpinfo-wp'); ?></strong> <?php _e('We are tracking outbound HTTP requests across your site. No external API calls have been recorded in the last 24 hours yet.', 'phpinfo-wp'); ?></p>
            </div>
            <p style="color:#555; font-size:13px; margin-top:10px;"><?php _e('You can trigger a plugin update check or click "Test Slow API" in the sidebar to generate test requests.', 'phpinfo-wp'); ?></p>
        <?php else: ?>

            <!-- Details Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width:30%;"><?php _e('Domain (Endpoint)', 'phpinfo-wp'); ?></th>
                        <th scope="col"><?php _e('Requests', 'phpinfo-wp'); ?></th>
                        <th scope="col"><?php _e('Avg Time', 'phpinfo-wp'); ?></th>
                        <th scope="col"><?php _e('Max Time', 'phpinfo-wp'); ?></th>
                        <th scope="col"><?php _e('Total Time', 'phpinfo-wp'); ?></th>
                        <th scope="col"><?php _e('Errors/Timeouts', 'phpinfo-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paged_stats as $host => $data):
                        $avg = $data['count'] > 0 ? $data['total_time'] / $data['count'] : 0;
                        $err_color = $data['errors'] > 0 ? '#d63638' : '#555';
                        $max_color = $data['max_time'] > 2.0 ? '#d63638' : ($data['max_time'] > 1.0 ? '#dba617' : '#00a32a');
                    ?>
                    <tr>
                        <td><strong><code><?php echo esc_html($host); ?></code></strong></td>
                        <td><?php echo number_format($data['count']); ?></td>
                        <td><?php echo number_format($avg, 3); ?>s</td>
                        <td style="color:<?php echo $max_color; ?>; font-weight:600;"><?php echo number_format($data['max_time'], 3); ?>s</td>
                        <td><?php echo number_format($data['total_time'], 2); ?>s</td>
                        <td style="color:<?php echo $err_color; ?>; font-weight:<?php echo $data['errors'] > 0 ? '600' : 'normal'; ?>;">
                            <?php echo number_format($data['errors']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px;">
                    <div style="font-size:13px; color:#64748b;">
                        <?php printf(
                            __('Showing %1$d &ndash; %2$d of %3$s API endpoints', 'phpinfo-wp'),
                            ($paged - 1) * $per_page + 1,
                            min($total, $paged * $per_page),
                            number_format($total)
                        ); ?>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <?php if ($paged > 2): ?>
                            <a href="<?php echo esc_url(add_query_arg(['paged' => 1])); ?>" class="button button-secondary" title="<?php esc_attr_e('First page', 'phpinfo-wp'); ?>">&laquo;&laquo;</a>
                        <?php endif; ?>
                        <?php if ($paged > 1): ?>
                            <a href="<?php echo esc_url(add_query_arg(['paged' => $paged - 1])); ?>" class="button button-secondary">&laquo; <?php _e('Previous', 'phpinfo-wp'); ?></a>
                        <?php endif; ?>
                        <span style="font-size:13px; line-height:30px; padding:0 8px; color:#334155; font-weight:600;">
                            <?php printf(__('Page %d of %d', 'phpinfo-wp'), $paged, $total_pages); ?>
                        </span>
                        <?php if ($paged < $total_pages): ?>
                            <a href="<?php echo esc_url(add_query_arg(['paged' => $paged + 1])); ?>" class="button button-secondary"><?php _e('Next', 'phpinfo-wp'); ?> &raquo;</a>
                        <?php endif; ?>
                        <?php if ($paged < $total_pages - 1): ?>
                            <a href="<?php echo esc_url(add_query_arg(['paged' => $total_pages])); ?>" class="button button-secondary" title="<?php esc_attr_e('Last page', 'phpinfo-wp'); ?>">&raquo;&raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <p class="description" style="margin-top:12px;">
                This table tracks all external HTTP requests made via <code>wp_remote_get</code>, <code>wp_remote_post</code>, and the core HTTP API. 
                Because PHP is synchronous, a 3-second API call blocks your WordPress page load for 3 entire seconds. 
                <strong><?php _e('Any API with a max time over 2.0s should be investigated.', 'phpinfo-wp'); ?></strong>
            </p>

        <?php endif; ?>

    <?php endif; ?>
</div>

<?php if ($is_pro): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btnClear = document.getElementById('phpinfowp-clear-api-stats');
    if (btnClear) {
        btnClear.addEventListener('click', function(e) {
            e.preventDefault();
            window.phpinfowpConfirm({
                title: '<?php echo esc_js(__('Clear API Statistics', 'phpinfo-wp')); ?>',
                message: '<?php echo esc_js(__('Clear all API stats? This cannot be undone.', 'phpinfo-wp')); ?>',
                confirmText: '<?php echo esc_js(__('Clear Stats', 'phpinfo-wp')); ?>',
                isDanger: true
            }).then(function(confirmed) {
                if (!confirmed) return;
                btnClear.disabled = true;
                btnClear.textContent = '<?php echo esc_js(__('Clearing...', 'phpinfo-wp')); ?>';
                
                var data = new FormData();
                data.append('action', 'phpinfowp_clear_api_stats');
                data.append('nonce', '<?php echo wp_create_nonce("phpinfowp_api_nonce"); ?>');
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                }).then(res => res.json()).then(function(res) {
                    if (res.success) {
                        window.location.reload();
                    } else {
                        btnClear.disabled = false;
                        btnClear.textContent = '<?php echo esc_js(__('Reset Stats', 'phpinfo-wp')); ?>';
                    }
                });
            });
        });
    }

    var btnTest = document.getElementById('phpinfowp-test-api');
    if (btnTest) {
        btnTest.addEventListener('click', function(e) {
            e.preventDefault();
            
            btnTest.disabled = true;
            btnTest.textContent = 'Testing (Waiting 1.5s)...';
            
            var data = new FormData();
            data.append('action', 'phpinfowp_test_api');
            data.append('nonce', '<?php echo wp_create_nonce("phpinfowp_api_nonce"); ?>');
            
            fetch(ajaxurl, {
                method: 'POST',
                body: data
            }).then(res => res.json()).then(function(res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert('Failed to run test.');
                    btnTest.disabled = false;
                    btnTest.textContent = 'Trigger Dummy Slow Request (1.5s)';
                }
            });
        });
    }
});
</script>
<?php endif; ?>
