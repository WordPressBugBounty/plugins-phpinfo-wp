<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_free_preview = !$is_pro;
$message         = '';
$msg_type        = 'success';
$diff            = null;
$snap_a          = null;
$snap_b          = null;

if ($is_pro && isset($_POST['phpinfowp_snap_action']) && check_admin_referer('phpinfowp_snap_nonce')) {
    $action = sanitize_text_field($_POST['phpinfowp_snap_action']);

    if ($action === 'take') {
        if (!Phpinfo_WP_License::is_unlimited() && count(Phpinfo_WP_Snapshots::list(50)) >= 3) {
            $message = 'Single Site plan limit reached (max 3 snapshots). Delete older snapshots or upgrade to take more.';
            $msg_type = 'error';
        } else {
            $label = sanitize_text_field($_POST['snap_label'] ?? '');
            $id    = Phpinfo_WP_Snapshots::take($label ?: 'Manual snapshot');
            $message = "Snapshot #$id saved.";
        }
    } elseif ($action === 'delete' && !empty($_POST['snap_id'])) {
        Phpinfo_WP_Snapshots::delete((int) $_POST['snap_id']);
        $message = 'Snapshot deleted.';
        $msg_type = 'info';
    } elseif ($action === 'diff' && !empty($_POST['snap_a']) && !empty($_POST['snap_b'])) {
        $snap_a = Phpinfo_WP_Snapshots::get((int) $_POST['snap_a']);
        $snap_b = Phpinfo_WP_Snapshots::get((int) $_POST['snap_b']);
        if ($snap_a && $snap_b) {
            $diff = Phpinfo_WP_Snapshots::diff($snap_a->snapshot_data, $snap_b->snapshot_data);
        }
    }
}

$snapshots    = $is_pro ? Phpinfo_WP_Snapshots::list(50) : [];
$current_caps = $is_pro ? Phpinfo_WP_Snapshots::capture() : [];
$view_snap    = null;
$view_snap_id = (int) ($_GET['snap_view'] ?? 0);
if ($is_pro && $view_snap_id > 0) {
    $view_snap = Phpinfo_WP_Snapshots::get($view_snap_id);
}
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1>
                <?php _e('Config Snapshots', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('Track php.ini and directive changes over time with visual side-by-side diffing and automatic snapshots.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
    </div>



    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php if ($is_free_preview): ?>
        <!-- Free preview: contextual preview cards + centered upgrade card -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">
            
            <!-- Background contextual preview cards -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                
                <!-- Snapshot Item 1 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #6366f1; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; width:65%; align-self:flex-end;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a;"><?php printf(__('WordPress %s Baseline Snapshot', 'phpinfo-wp'), esc_html(get_bloginfo('version'))); ?></strong>
                            <span style="background:#e0e7ff; color:#4338ca; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">BASELINE</span>
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            <?php printf(__('Captured: %s · PHP %s Directives &amp; Environment Constants Logged', 'phpinfo-wp'), esc_html(wp_date('M j, Y H:i')), esc_html(PHP_VERSION)); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:12px; color:#6366f1; font-weight:600;">📷 Snapshot #1</span>
                    </div>
                </div>

                <!-- Snapshot Item 2 -->
                <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #d63638; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; width:65%; align-self:flex-start;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <strong style="font-size:13.5px; color:#0f172a;"><?php _e('Runtime Drift Detection Diff', 'phpinfo-wp'); ?></strong>
                            <span style="background:#fee2e2; color:#dc2626; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">DRIFT DETECTED</span>
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            <?php printf(__('Tracked: memory_limit (%s), max_execution_time (%ss), upload_max_filesize (%s)', 'phpinfo-wp'), esc_html(ini_get('memory_limit')), esc_html(ini_get('max_execution_time')), esc_html(ini_get('upload_max_filesize'))); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:12px; color:#dc2626; font-weight:600;">⚠️ Drift Guard</span>
                    </div>
                </div>

            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

            <!-- Floating Upgrade Card -->
            <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                <span class="dashicons dashicons-camera" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Config Snapshots & Diff Inspector Locked', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                    <?php _e('Capture full snapshots of your PHP directives, server variables, and WordPress environment to instantly detect silent server drift.', 'phpinfo-wp'); ?>
                </p>
                <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">📸</span>
                        <span><strong><?php _e('Automated Pre-Update Snapshots:', 'phpinfo-wp'); ?></strong> <?php _e('Auto-save PHP configs before core/plugin updates to easily diagnose what broke.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">🔍</span>
                        <span><strong><?php _e('Side-by-Side Visual Diff Tool:', 'phpinfo-wp'); ?></strong> <?php _e('Compare two runtime snapshots side-by-side to highlight changed directives and altered limits.', 'phpinfo-wp'); ?></span>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="color:#6366f1; font-size:15px; line-height:1;">🛡️</span>
                        <span><strong><?php _e('Host Migration Drift Alerts:', 'phpinfo-wp'); ?></strong> <?php _e('Catch unwanted changes introduced by your web host or hosting environment updates immediately.', 'phpinfo-wp'); ?></span>
                    </div>
                </div>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                    <?php _e('Unlock Snapshots with Pro &rarr;', 'phpinfo-wp'); ?>
                </a>
            </div>
        </div>

    <?php else: ?>

        <div style="display:flex;gap:32px;align-items:flex-start;flex-wrap:wrap">

            <!-- Take snapshot -->
            <div class="phpinfowp-snap-card">
                <h3 style="margin-top:0"><?php _e('Take Snapshot Now', 'phpinfo-wp'); ?></h3>
                <?php if (!Phpinfo_WP_License::is_unlimited() && count($snapshots) >= 3): ?>
                    <div style="background:#fff9e6;border:1px solid #ffe599;border-radius:4px;padding:12px;font-size:13px;color:#7f6000;margin-bottom:8px;max-width:280px">
                        Single Site tier limit reached (3/3 snapshots). Delete older snapshots or <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" style="font-weight:600;color:#777BB3;text-decoration:none"><?php _e('Upgrade to Unlimited', 'phpinfo-wp'); ?></a> for unlimited snapshot history.
                    </div>
                <?php else: ?>
                    <form method="post">
                        <?php wp_nonce_field('phpinfowp_snap_nonce'); ?>
                        <input type="hidden" name="phpinfowp_snap_action" value="take">
                        <input type="text" name="snap_label" placeholder="<?php echo esc_attr__('Label (optional)', 'phpinfo-wp'); ?>" class="regular-text" style="margin-bottom:8px;display:block">
                        <button type="submit" class="button button-primary"><?php _e('Take Snapshot', 'phpinfo-wp'); ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Diff picker -->
            <?php if (count($snapshots) >= 2): ?>
            <div class="phpinfowp-snap-card">
                <h3 style="margin-top:0"><?php _e('Compare Two Snapshots', 'phpinfo-wp'); ?></h3>
                <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                    <?php wp_nonce_field('phpinfowp_snap_nonce'); ?>
                    <input type="hidden" name="phpinfowp_snap_action" value="diff">
                    <div>
                        <label style="display:block;font-size:12px;margin-bottom:3px"><?php _e('From (older)', 'phpinfo-wp'); ?></label>
                        <select name="snap_a" class="phpinfowp-snap-select">
                            <?php foreach ($snapshots as $s): ?>
                                <option value="<?php echo esc_attr($s->id); ?>"><?php echo esc_html("#{$s->id} {$s->label} — {$s->created_at}"); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;margin-bottom:3px"><?php _e('To (newer)', 'phpinfo-wp'); ?></label>
                        <select name="snap_b" class="phpinfowp-snap-select">
                            <?php foreach ($snapshots as $i => $s): ?>
                                <option value="<?php echo esc_attr($s->id); ?>" <?php selected($i, 0); ?>><?php echo esc_html("#{$s->id} {$s->label} — {$s->created_at}"); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="button button-secondary" style="margin-bottom:1px"><?php _e('Compare →', 'phpinfo-wp'); ?></button>
                </form>
            </div>
            <?php endif; ?>

        </div>

        <!-- Diff result -->
        <?php if ($diff !== null): ?>
            <div style="margin-top:24px">
                <h2 style="margin-bottom:8px">
                    Diff: <em><?php echo esc_html("#{$snap_a->id} {$snap_a->label}"); ?></em>
                    &rarr; <em><?php echo esc_html("#{$snap_b->id} {$snap_b->label}"); ?></em>
                </h2>
                <?php if (empty($diff)): ?>
                    <div class="notice notice-success inline"><p><?php _e('No changes detected between these two snapshots.', 'phpinfo-wp'); ?></p></div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped phpinfowp-diff-table">
                        <thead><tr><th style="width:120px"><?php _e('Change', 'phpinfo-wp'); ?></th><th><?php _e('Directive', 'phpinfo-wp'); ?></th><th><?php _e('Old Value', 'phpinfo-wp'); ?></th><th><?php _e('New Value', 'phpinfo-wp'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($diff as $item): ?>
                            <tr class="diff-<?php echo esc_attr($item['type']); ?>">
                                <td><span class="diff-badge diff-badge-<?php echo $item['type']; ?>"><?php echo strtoupper($item['type']); ?></span></td>
                                <td><code><?php echo esc_html($item['key']); ?></code></td>
                                <td><?php echo $item['old'] !== null ? '<code>' . esc_html($item['old']) . '</code>' : '—'; ?></td>
                                <td><?php echo $item['new'] !== null ? '<code>' . esc_html($item['new']) . '</code>' : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Snapshot list -->
        <h2 style="margin-top:32px"><?php printf(__('Saved Snapshots (%d)', 'phpinfo-wp'), count($snapshots)); ?></h2>
        <?php if (empty($snapshots)): ?>
            <p style="color:#666"><?php _e('No snapshots yet. Take one above — weekly auto-snapshots will accumulate here over time.', 'phpinfo-wp'); ?></p>
        <?php else: 
            $snap_per_page    = 15;
            $snap_total       = count($snapshots);
            $snap_total_pages = max(1, (int) ceil($snap_total / $snap_per_page));
            $snap_paged       = max(1, min($snap_total_pages, (int) ($_GET['paged'] ?? 1)));
            $paged_snapshots  = array_slice($snapshots, ($snap_paged - 1) * $snap_per_page, $snap_per_page);
        ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th style="width:50px">#</th><th><?php _e('Label', 'phpinfo-wp'); ?></th><th><?php _e('Created', 'phpinfo-wp'); ?></th><th style="width:160px"><?php _e('Actions', 'phpinfo-wp'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($paged_snapshots as $s): ?>
                    <tr>
                        <td><?php echo esc_html($s->id); ?></td>
                        <td><strong><?php echo esc_html($s->label); ?></strong></td>
                        <td><?php echo esc_html($s->created_at); ?> UTC</td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap">
                            <button type="button"
                                    class="button button-small phpinfowp-view-snap-btn"
                                    data-snap-id="<?php echo esc_attr($s->id); ?>"
                                    data-snap-label="<?php echo esc_attr($s->label); ?>"
                                    data-snap-date="<?php echo esc_attr($s->created_at); ?> UTC">
                                <?php _e('View', 'phpinfo-wp'); ?>
                            </button>
                            <script type="application/json" id="phpinfowp-snap-json-<?php echo (int)$s->id; ?>">
                            <?php 
                            $snap_arr = is_array($s->snapshot_data) ? $s->snapshot_data : (json_decode($s->snapshot_data ?? '', true) ?: []);
                            echo wp_json_encode($snap_arr); 
                            ?>
                            </script>
                            <form method="post" style="display:inline"
                                  data-confirm="<?php esc_attr_e('Delete this snapshot? This cannot be undone.', 'phpinfo-wp'); ?>"
                                  data-confirm-title="<?php esc_attr_e('Delete Snapshot', 'phpinfo-wp'); ?>"
                                  data-confirm-btn="<?php esc_attr_e('Delete Snapshot', 'phpinfo-wp'); ?>">
                                <?php wp_nonce_field('phpinfowp_snap_nonce'); ?>
                                <input type="hidden" name="phpinfowp_snap_action" value="delete">
                                <input type="hidden" name="snap_id" value="<?php echo esc_attr($s->id); ?>">
                                <button type="submit" class="button button-small"><?php _e('Delete', 'phpinfo-wp'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination Controls -->
            <?php if ($snap_total_pages > 1): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px;">
                    <div style="font-size:13px; color:#64748b;">
                        <?php printf(
                            __('Showing %1$d &ndash; %2$d of %3$s snapshots', 'phpinfo-wp'),
                            ($snap_paged - 1) * $snap_per_page + 1,
                            min($snap_total, $snap_paged * $snap_per_page),
                            number_format($snap_total)
                        ); ?>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <?php if ($snap_paged > 2): ?>
                            <a href="<?php echo esc_url(add_query_arg(['paged' => 1])); ?>" class="button button-secondary" title="<?php esc_attr_e('First page', 'phpinfo-wp'); ?>">&laquo;&laquo;</a>
                        <?php endif; ?>
                        <?php if ($snap_paged > 1): ?>
                            <a href="<?php echo esc_url(add_query_arg(['paged' => $snap_paged - 1])); ?>" class="button button-secondary">&laquo; <?php _e('Previous', 'phpinfo-wp'); ?></a>
                        <?php endif; ?>
                        <span style="font-size:13px; line-height:30px; padding:0 8px; color:#334155; font-weight:600;">
                            <?php printf(__('Page %d of %d', 'phpinfo-wp'), $snap_paged, $snap_total_pages); ?>
                        </span>
                        <?php if ($snap_paged < $snap_total_pages): ?>
                            <a href="<?php echo esc_url(add_query_arg(['paged' => $snap_paged + 1])); ?>" class="button button-secondary"><?php _e('Next', 'phpinfo-wp'); ?> &raquo;</a>
                        <?php endif; ?>
                        <?php if ($snap_paged < $snap_total_pages - 1): ?>
                            <a href="<?php echo esc_url(add_query_arg(['paged' => $snap_total_pages])); ?>" class="button button-secondary" title="<?php esc_attr_e('Last page', 'phpinfo-wp'); ?>">&raquo;&raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Snapshot Viewer Modal Popup (Matches .phpinfowp-dm design) -->
        <div id="phpinfowp-snapshot-dm" class="phpinfowp-dm" aria-hidden="true" role="dialog" aria-labelledby="phpinfowp-snap-dm-title">
            <div class="phpinfowp-dm-backdrop"></div>
            <div class="phpinfowp-dm-dialog" role="document" style="max-width:680px;">
                <button type="button" class="phpinfowp-dm-close" aria-label="<?php esc_attr_e('Close', 'phpinfo-wp'); ?>">&times;</button>

                <div class="phpinfowp-dm-header">
                    <div style="width:48px;height:48px;border-radius:10px;background:rgba(124,58,237,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <span class="dashicons dashicons-camera" style="font-size:24px;width:24px;height:24px;color:#7c3aed"></span>
                    </div>
                    <div>
                        <h2 id="phpinfowp-snap-dm-title" class="phpinfowp-dm-title"><?php _e('Snapshot Details', 'phpinfo-wp'); ?></h2>
                        <p id="phpinfowp-snap-dm-sub" class="phpinfowp-dm-sub"></p>
                    </div>
                </div>

                <div style="margin-bottom:12px;">
                    <input type="text" id="phpinfowp-snap-modal-filter" placeholder="<?php esc_attr_e('Search directives & values...', 'phpinfo-wp'); ?>" style="width:100%; font-size:13px; height:36px; border-radius:8px; border:1px solid #d8dae5; padding:0 12px; box-sizing:border-box;">
                </div>

                <div class="phpinfowp-dm-body" style="grid-template-columns:1fr; margin-bottom:14px;">
                    <div class="phpinfowp-dm-col phpinfowp-dm-col-pro" style="padding:14px; display:flex; flex-direction:column;">
                        <div class="phpinfowp-dm-col-label" style="margin-bottom:10px; flex-shrink:0;"><?php _e('Captured Directives & Runtime Values', 'phpinfo-wp'); ?></div>
                        
                        <div style="max-height:300px; overflow-y:auto; border-radius:6px; border:1px solid rgba(124,58,237,0.15);">
                            <table class="wp-list-table widefat fixed striped" style="margin:0; border:none; background:#fff;" id="phpinfowp-snap-modal-table">
                                <thead style="position:sticky; top:0; z-index:10; background:#f5f3ff; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                                    <tr>
                                        <th style="width:45%; font-weight:700; color:#4c1d95; background:#f5f3ff; border-bottom:1px solid rgba(124,58,237,0.2); padding:8px 10px;"><?php _e('Directive', 'phpinfo-wp'); ?></th>
                                        <th style="width:55%; font-weight:700; color:#4c1d95; background:#f5f3ff; border-bottom:1px solid rgba(124,58,237,0.2); padding:8px 10px;"><?php _e('Value', 'phpinfo-wp'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="phpinfowp-snap-modal-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="phpinfowp-dm-warn" style="margin-bottom:16px;">
                    <span class="dashicons dashicons-info-outline" style="color:#0073aa"></span>
                    <div>
                        <strong><?php _e('Safe Snapshot:', 'phpinfo-wp'); ?></strong> <?php _e('This snapshot is a saved point-in-time record of your PHP runtime configuration.', 'phpinfo-wp'); ?>
                    </div>
                </div>

                <div class="phpinfowp-dm-actions">
                    <button type="button" class="button button-primary button-large phpinfowp-dm-keep phpinfowp-snap-dm-close-btn" style="background:#777BB3; border-color:#777BB3;"><?php _e('Close Snapshot', 'phpinfo-wp'); ?></button>
                    <span id="phpinfowp-snap-modal-count" style="font-size:12.5px; color:#64748b;"></span>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('phpinfowp-snapshot-dm');
            if (!modal) return;

            var modalTitle = document.getElementById('phpinfowp-snap-dm-title');
            var modalSub = document.getElementById('phpinfowp-snap-dm-sub');
            var modalTbody = document.getElementById('phpinfowp-snap-modal-tbody');
            var modalCount = document.getElementById('phpinfowp-snap-modal-count');
            var filterInput = document.getElementById('phpinfowp-snap-modal-filter');

            function openModal(id, label, date) {
                var jsonScript = document.getElementById('phpinfowp-snap-json-' + id);
                if (!jsonScript) return;

                var data = {};
                try {
                    data = JSON.parse(jsonScript.textContent || '{}');
                } catch(e) {
                    data = {};
                }

                modalTitle.textContent = 'Snapshot #' + id + ': ' + label;
                modalSub.textContent = 'Recorded on ' + date + ' — safe historical snapshot';
                modalTbody.innerHTML = '';
                if (filterInput) filterInput.value = '';

                var keys = Object.keys(data);
                keys.forEach(function(key) {
                    var val = data[key];
                    var tr = document.createElement('tr');
                    tr.className = 'phpinfowp-snap-row';
                    tr.dataset.key = key.toLowerCase();
                    
                    var tdKey = document.createElement('td');
                    tdKey.style.background = 'transparent';
                    var codeKey = document.createElement('code');
                    codeKey.textContent = key;
                    tdKey.appendChild(codeKey);

                    var tdVal = document.createElement('td');
                    tdVal.style.background = 'transparent';
                    if (val !== null && val !== undefined) {
                        var codeVal = document.createElement('code');
                        codeVal.textContent = val;
                        tdVal.appendChild(codeVal);
                    } else {
                        var emVal = document.createElement('em');
                        emVal.style.color = '#94a3b8';
                        emVal.textContent = 'not set';
                        tdVal.appendChild(emVal);
                    }

                    tr.appendChild(tdKey);
                    tr.appendChild(tdVal);
                    modalTbody.appendChild(tr);
                });

                if (modalCount) {
                    modalCount.textContent = keys.length + ' directives captured';
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            // View buttons click
            document.querySelectorAll('.phpinfowp-view-snap-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var id = this.dataset.snapId;
                    var label = this.dataset.snapLabel;
                    var date = this.dataset.snapDate;
                    openModal(id, label, date);
                });
            });

            // Close buttons click
            modal.querySelectorAll('.phpinfowp-dm-close, .phpinfowp-snap-dm-close-btn, .phpinfowp-dm-backdrop').forEach(function(el) {
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeModal();
                });
            });

            // ESC key to close
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });

            // Live filter input
            if (filterInput) {
                filterInput.addEventListener('input', function() {
                    var q = this.value.toLowerCase().trim();
                    var rows = modalTbody.querySelectorAll('.phpinfowp-snap-row');
                    var visible = 0;
                    rows.forEach(function(row) {
                        var key = row.dataset.key || '';
                        var val = row.children[1].textContent.toLowerCase();
                        if (!q || key.indexOf(q) !== -1 || val.indexOf(q) !== -1) {
                            row.style.display = '';
                            visible++;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    if (modalCount) {
                        modalCount.textContent = visible + ' matching directives';
                    }
                });
            }

            // If URL has ?snap_view=ID, auto-open modal
            var urlParams = new URLSearchParams(window.location.search);
            var autoViewId = urlParams.get('snap_view');
            if (autoViewId) {
                var targetBtn = document.querySelector('.phpinfowp-view-snap-btn[data-snap-id="' + autoViewId + '"]');
                if (targetBtn) {
                    targetBtn.click();
                }
            }
        });
        </script>

    <?php endif; ?>
</div>
