<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) {
    phpinfowp_render_feature_lock([
        'feature'  => 'External API Monitor',
        'icon'     => 'dashicons-networking',
        'tagline'  => 'Track response times and uptime of 3rd-party services your site depends on.',
        'previews' => [
            'api.stripe.com: <strong>— ms</strong>',
            'api.mailchimp.com: <strong>— ms</strong>',
            'fonts.googleapis.com: <strong>— ms</strong>',
        ],
    ]);
    return;
}

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
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>API Monitor <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Track slow outbound API requests that silently block page loads (last 24 hours).', 'phpinfo-wp'); ?></p>
        </div>
        <?php if ($stats): ?>
            <button id="phpinfowp-clear-api-stats" class="button button-secondary"><?php _e('Reset Stats', 'phpinfo-wp'); ?></button>
        <?php endif; ?>
    </div>

    <?php if (!$stats): ?>
        <div class="notice notice-info inline">
            <p><strong><?php _e('Monitoring Active.', 'phpinfo-wp'); ?></strong> We are now tracking outbound HTTP requests. No external API calls have been made yet.</p>
        </div>
        <p><?php _e('To test this, you can trigger a plugin update check or wait for normal site traffic to generate API calls.', 'phpinfo-wp'); ?></p>
        <button id="phpinfowp-test-api" class="button button-primary"><?php _e('Trigger Dummy Slow Request (1.5s)', 'phpinfo-wp'); ?></button>
    <?php else: ?>

        <!-- Summary Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-bottom:24px;">
            <div style="background:#fff; border:1px solid #ccd0d4; border-left:4px solid #007cba; padding:16px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size:13px; color:#555; font-weight:600; text-transform:uppercase; margin-bottom:6px;"><?php _e('Total Waiting Time', 'phpinfo-wp'); ?></div>
                <div style="font-size:28px; font-weight:300; color:#1d2327;">
                    <?php echo number_format($total_time, 2); ?>s
                </div>
                <div style="font-size:12px; color:#777; margin-top:4px;">Across <?php echo number_format($total_reqs); ?> outbound requests</div>
            </div>

            <div style="background:#fff; border:1px solid #ccd0d4; border-left:4px solid <?php echo $slowest_max > 2.0 ? '#d63638' : '#dba617'; ?>; padding:16px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size:13px; color:#555; font-weight:600; text-transform:uppercase; margin-bottom:6px;"><?php _e('Slowest API Response', 'phpinfo-wp'); ?></div>
                <div style="font-size:28px; font-weight:300; color:#1d2327;">
                    <?php echo number_format($slowest_max, 2); ?>s
                </div>
                <div style="font-size:12px; color:#777; margin-top:4px;">Domain: <code><?php echo esc_html($slowest_domain); ?></code></div>
            </div>
        </div>
        
        <div style="margin-bottom:16px;">
            <button id="phpinfowp-test-api" class="button button-secondary"><?php _e('Trigger Dummy Slow Request (1.5s)', 'phpinfo-wp'); ?></button>
        </div>

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
                <?php foreach ($stats as $host => $data):
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
        
        <p class="description" style="margin-top:12px;">
            This table tracks all external HTTP requests made via <code>wp_remote_get</code>, <code>wp_remote_post</code>, and the core HTTP API. 
            Because PHP is synchronous, a 3-second API call blocks your WordPress page load for 3 entire seconds. 
            <strong><?php _e('Any API with a max time over 2.0s should be investigated.', 'phpinfo-wp'); ?></strong>
        </p>

    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var btnClear = document.getElementById('phpinfowp-clear-api-stats');
    if (btnClear) {
        btnClear.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm('Clear all API stats? This cannot be undone.')) return;
            
            btnClear.disabled = true;
            btnClear.textContent = 'Clearing...';
            
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
                    alert('Failed to clear stats.');
                    btnClear.disabled = false;
                    btnClear.textContent = 'Reset Stats';
                }
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
