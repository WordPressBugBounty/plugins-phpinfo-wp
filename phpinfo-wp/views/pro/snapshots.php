<?php
defined('ABSPATH') or die('Unauthorized Access');

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
$current_caps = Phpinfo_WP_Snapshots::capture();
$view_snap    = null;
$view_snap_id = (int) ($_GET['snap_view'] ?? 0);
if ($is_pro && $view_snap_id > 0) {
    $view_snap = Phpinfo_WP_Snapshots::get($view_snap_id);
}
?>

<div class="phpinfowp-pro-page">
    <div class="phpinfowp-page-header">
        <div>
            <h1>Config Snapshots <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span></h1>
            <p class="phpinfowp-page-subtitle"><?php _e('Track php.ini changes over time. Automatic weekly snapshots run via WP Cron.', 'phpinfo-wp'); ?></p>
        </div>
    </div>



    <?php if ($message): ?>
        <div class="notice notice-<?php echo $msg_type; ?> inline is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <!-- Real Hero Status Card (Visible to all users) -->
    <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:20px 24px; display:flex; gap:32px; box-shadow:0 1px 1px rgba(0,0,0,0.04); margin-bottom:28px; flex-wrap:wrap;">
        <div>
            <div style="font-size:12px; color:#666; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;"><?php _e('PHP Version Active', 'phpinfo-wp'); ?></div>
            <div style="font-size:22px; font-weight:700; color:#1d2327; margin-top:4px;"><?php echo esc_html(PHP_VERSION); ?></div>
        </div>
        <div style="border-left:1px solid #eee; padding-left:32px;">
            <div style="font-size:12px; color:#666; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;"><?php _e('Tracked Directives', 'phpinfo-wp'); ?></div>
            <div style="font-size:22px; font-weight:700; color:#1d2327; margin-top:4px;"><?php echo count(Phpinfo_WP_Snapshots::TRACKED); ?> <?php _e('directives', 'phpinfo-wp'); ?></div>
        </div>
        <div style="border-left:1px solid #eee; padding-left:32px;">
            <div style="font-size:12px; color:#666; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;"><?php _e('Weekly Auto-Backup', 'phpinfo-wp'); ?></div>
            <div style="font-size:22px; font-weight:700; color:<?php echo $is_pro ? '#00a32a' : '#777BB3'; ?>; margin-top:4px;">
                <?php echo $is_pro ? __('Active (Cron)', 'phpinfo-wp') : __('Pro Only', 'phpinfo-wp'); ?>
            </div>
        </div>
    </div>

    <?php if ($is_free_preview): ?>
        <!-- Free preview: compact skeleton rows + centered upgrade card (fits in single viewpoint) -->
        <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:10px; min-height:260px; display:flex; align-items:center; justify-content:center;">
            
            <!-- Skeleton content -->
            <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.5;">
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">
                        <div style="height:11px; width:25%; background:#cbd5e1; border-radius:4px;"></div>
                        <div style="height:11px; width:20%; background:#cbd5e1; border-radius:4px;"></div>
                        <div style="height:11px; width:15%; background:#cbd5e1; border-radius:4px;"></div>
                    </div>
                    <?php for ($sk = 0; $sk < 3; $sk++): ?>
                    <div style="display:flex; justify-content:space-between; padding:6px 0;">
                        <div style="height:10px; width:<?php echo [35, 45, 30][$sk]; ?>%; background:#e2e8f0; border-radius:4px;"></div>
                        <div style="height:10px; width:20%; background:#e2e8f0; border-radius:4px;"></div>
                        <div style="height:10px; width:10%; background:#e2e8f0; border-radius:4px;"></div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Gradient fade-out overlay -->
            <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(246,249,252,0.4) 0%, rgba(246,249,252,0.95) 40%, #f6f9fc 100%); pointer-events:none;"></div>

            <!-- Upgrade card floating over skeleton -->
            <div style="position:relative; z-index:2; width:100%; max-width:480px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px 28px; box-shadow:0 6px 20px -4px rgba(0,0,0,0.07); text-align:center; margin:12px auto;">
                <span class="dashicons dashicons-lock" style="font-size:30px; width:30px; height:30px; color:#777BB3; display:inline-block; margin-bottom:6px;"></span>
                <h3 style="margin:0 0 6px; font-size:18px; font-weight:600; color:#1d2327;"><?php _e('Config Snapshots & Diff Inspector Locked', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 4px; font-size:13px; color:#475569; line-height:1.5; max-width:400px; margin-left:auto; margin-right:auto;">
                    <?php _e('Unlock automated weekly configuration backups, visual side-by-side diff tool, silent PHP drift detection, and historical rollback logs.', 'phpinfo-wp'); ?>
                </p>
                <p style="margin:0 0 16px; font-size:12px; color:#94a3b8;">
                    <?php _e('Your active PHP directives and runtime values above are live — upgrade to take snapshots and detect drift.', 'phpinfo-wp'); ?>
                </p>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#777BB3; border-color:#777BB3; height:38px; line-height:36px; font-size:13.5px; font-weight:600; padding:0 22px; border-radius:6px; text-decoration:none; display:inline-block;">
                    <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
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

        <!-- Snapshot viewer -->
        <?php if ($view_snap): ?>
            <div style="margin-top:24px;padding:20px;background:#f9f9f9;border:1px solid #ddd;border-radius:6px;max-width:720px">
                <h2 style="margin-top:0">
                    Snapshot #<?php echo esc_html($view_snap->id); ?>: <?php echo esc_html($view_snap->label); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-snapshots')); ?>" class="button button-small" style="margin-left:12px;vertical-align:middle">← Back</a>
                </h2>
                <p style="font-size:12px;color:#666;margin-top:-12px"><?php echo esc_html($view_snap->created_at); ?> UTC</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th style="width:280px"><?php _e('Directive', 'phpinfo-wp'); ?></th><th><?php _e('Value', 'phpinfo-wp'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($view_snap->snapshot_data as $key => $val): ?>
                        <tr>
                            <td><code><?php echo esc_html($key); ?></code></td>
                            <td><?php echo $val !== null ? '<code>' . esc_html($val) . '</code>' : '<em style="color:#999">not set</em>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Snapshot list -->
        <h2 style="margin-top:32px">Saved Snapshots (<?php echo count($snapshots); ?>)</h2>
        <?php if (empty($snapshots)): ?>
            <p style="color:#666"><?php _e('No snapshots yet. Take one above — weekly auto-snapshots will accumulate here over time.', 'phpinfo-wp'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th style="width:50px">#</th><th><?php _e('Label', 'phpinfo-wp'); ?></th><th><?php _e('Created', 'phpinfo-wp'); ?></th><th style="width:160px"><?php _e('Actions', 'phpinfo-wp'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($snapshots as $s): ?>
                    <tr <?php if ($view_snap_id === (int)$s->id) echo 'style="background:#f0f4ff"'; ?>>
                        <td><?php echo esc_html($s->id); ?></td>
                        <td><?php echo esc_html($s->label); ?></td>
                        <td><?php echo esc_html($s->created_at); ?> UTC</td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=phpinfowp-snapshots&snap_view=' . $s->id)); ?>"
                               class="button button-small">View</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this snapshot?')">
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
        <?php endif; ?>

    <?php endif; ?>
</div>
