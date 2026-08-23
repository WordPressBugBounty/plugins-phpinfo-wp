<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$content_dir = WP_CONTENT_DIR;
$log_dir     = "$content_dir/logs/phpinfo-WP";
$log_file    = "$log_dir/log.txt";

if (!file_exists($log_dir)) wp_mkdir_p($log_dir);
if (!file_exists($log_file)) file_put_contents($log_file, '');

$notice = '';

// Parse entries — stored as "message<br />" lines, newest first
$lines = [];
try {
    $obj = new SplFileObject($log_file, 'r');
    $obj->seek(PHP_INT_MAX);
    $total_lines = $obj->key();
    $start = max(0, $total_lines - 500);
    $obj->seek($start);
    while (!$obj->eof()) {
        $chunk = $obj->fgets();
        $parts = array_filter(array_map('trim', explode('<br />', $chunk)));
        $lines = array_merge($lines, $parts);
    }
} catch (Exception $e) {
    // Fallback if SplFileObject is disabled on the server
    $raw = file_exists($log_file) ? file_get_contents($log_file) : '';
    $lines = array_filter(array_map('trim', explode('<br />', $raw)));
}
$entries = array_reverse(array_values($lines));

function phpinfowp_parse_log_entry(string $raw): array {
    $action = 'unknown';
    $file   = '';
    $detail = '';
    $dt     = '';
    $user   = '';

    if (strpos($raw, 'backed up') !== false)                  $action = 'backup';
    elseif (strpos($raw, 'restored') !== false)               $action = 'restore';
    elseif (strpos($raw, 'autofix applied') !== false)        $action = 'autofix';
    elseif (strpos($raw, 'autofix block reverted') !== false) $action = 'autofix-revert';
    elseif (strpos($raw, 'edited') !== false)                 $action = 'edit';

    if (strpos($raw, '.user.ini') !== false)                                        $file = '.user.ini';
    elseif (strpos($raw, '.htaccess') !== false || strpos($raw, 'htaccess') !== false)  $file = '.htaccess';

    if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $raw, $m)) $dt   = $m[1];
    if (preg_match('/by\s+(\S+)\s*$/', $raw, $m))                       $user = $m[1];

    if ($action === 'autofix' && preg_match('/autofix applied:\s*(.+?)\s+on\s+\d{4}-\d{2}-\d{2}/i', $raw, $m)) {
        $detail = trim($m[1]);
    }

    switch ($action) {
        case 'edit':
            $description = $file ? sprintf(__('Edited %s', 'phpinfo-wp'), $file) : __('Edited config', 'phpinfo-wp');
            break;
        case 'backup':
            $description = $file ? sprintf(__('Backed up %s', 'phpinfo-wp'), $file) : __('Backed up config', 'phpinfo-wp');
            break;
        case 'restore':
            $description = $file ? sprintf(__('Restored %s from backup', 'phpinfo-wp'), $file) : __('Restored from backup', 'phpinfo-wp');
            break;
        case 'autofix':
            if ($detail) {
                $dir_count = substr_count($detail, ',') + 1;
                $description = sprintf(
                    _n('Auto-fix applied to %1$d directory: %2$s', 'Auto-fix applied to %1$d directories: %2$s', $dir_count, 'phpinfo-wp'),
                    $dir_count,
                    $detail
                );
            } else {
                $description = __('Config Grader auto-fix applied', 'phpinfo-wp');
            }
            break;
        case 'autofix-revert':
            $description = __('Config Grader auto-fix block reverted', 'phpinfo-wp');
            break;
        default:
            $description = trim(preg_replace([
                '/\s+on\s+\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+by\s+\S+\s*$/i',
                '/<br\s*\/?>/i',
            ], '', $raw)) ?: $raw;
            break;
    }

    return compact('action', 'file', 'detail', 'description', 'dt', 'user', 'raw');
}

function phpinfowp_relative_time(string $dt): string {
    if (!$dt) return '—';
    $diff = time() - (int) strtotime($dt . ' UTC');
    if ($diff < 60)     return __('just now', 'phpinfo-wp');
    if ($diff < 3600)   return sprintf(__('%dm ago', 'phpinfo-wp'), round($diff / 60));
    if ($diff < 86400)  return sprintf(__('%dh ago', 'phpinfo-wp'), round($diff / 3600));
    if ($diff < 604800) return sprintf(__('%dd ago', 'phpinfo-wp'), round($diff / 86400));
    return gmdate('M j, Y', strtotime($dt . ' UTC'));
}

$parsed  = array_map('phpinfowp_parse_log_entry', $entries);
$counts  = ['all' => count($parsed), 'edit' => 0, 'backup' => 0, 'restore' => 0, 'autofix' => 0];
foreach ($parsed as $e) {
    if ($e['action'] === 'autofix' || $e['action'] === 'autofix-revert') {
        $counts['autofix']++;
    } elseif (isset($counts[$e['action']])) {
        $counts[$e['action']]++;
    }
}

$filter = sanitize_key($_GET['log_filter'] ?? 'all');
if (!in_array($filter, ['all', 'edit', 'backup', 'restore', 'autofix'])) $filter = 'all';
$search = sanitize_text_field($_GET['log_search'] ?? '');

$filtered = array_filter($parsed, function ($e) use ($filter, $search) {
    if ($filter !== 'all') {
        if ($filter === 'autofix') {
            if ($e['action'] !== 'autofix' && $e['action'] !== 'autofix-revert') return false;
        } elseif ($e['action'] !== $filter) {
            return false;
        }
    }
    if ($search) {
        $hay = strtolower($e['raw'] . ' ' . $e['description']);
        if (strpos($hay, strtolower($search)) === false) return false;
    }
    return true;
});
?>

<div class="phpinfowp-pro-page phpinfowp-log-page">

    <div class="phpinfowp-page-header">
        <div>
            <h1><?php _e('Activity Log', 'phpinfo-wp'); ?></h1>
            <p class="phpinfowp-page-subtitle">
                <?php _e('Tracks every PHP Config change made through phpinfo() WP', 'phpinfo-wp'); ?> &middot;
                <strong><?php echo count($parsed); ?></strong> <?php echo _n('entry', 'entries', count($parsed), 'phpinfo-wp'); ?>
            </p>
        </div>
    </div>

    <?php if ($notice): ?>
        <div class="notice notice-success inline is-dismissible" style="margin:0 0 16px"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <!-- Filter + Search bar -->
    <div class="phpinfowp-log-controls">
        <div class="phpinfowp-log-filters">
            <?php
            $tabs = [
                'all'     => ['label' => __('All', 'phpinfo-wp'),       'count' => $counts['all']],
                'edit'    => ['label' => __('Edits', 'phpinfo-wp'),     'count' => $counts['edit']],
                'backup'  => ['label' => __('Backups', 'phpinfo-wp'),   'count' => $counts['backup']],
                'restore' => ['label' => __('Restores', 'phpinfo-wp'),  'count' => $counts['restore']],
                'autofix' => ['label' => __('Auto-fixes', 'phpinfo-wp'),'count' => $counts['autofix']],
            ];
            foreach ($tabs as $key => $tab):
                $active = $filter === $key;
                $url    = add_query_arg(['log_filter' => $key, 'log_search' => $search], admin_url('admin.php?page=piwp-log'));
            ?>
                <a href="<?php echo esc_url($url); ?>"
                   class="phpinfowp-log-filter-pill <?php echo $active ? 'active' : ''; ?>">
                    <?php echo esc_html($tab['label']); ?>
                    <span class="phpinfowp-log-filter-count"><?php echo esc_html($tab['count']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div style="position:relative">
            <input type="text" id="phpinfowp-log-search-input"
                   value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php echo esc_attr__('Search entries…', 'phpinfo-wp'); ?>"
                   class="regular-text"
                   style="padding-right:32px"
                   oninput="phpinfowpLogSearch(this.value)">
            <button type="button" id="phpinfowp-log-search-clear"
                    style="display:<?php echo $search ? '' : 'none'; ?>;position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#999;font-size:18px;line-height:1;padding:0"
                    onclick="phpinfowpLogSearchClear()">&times;</button>
        </div>
    </div>

    <!-- Timeline -->
    <?php if (empty($parsed)): ?>
        <div class="phpinfowp-log-empty">
            <span class="dashicons dashicons-list-view" style="font-size:40px;width:40px;height:40px;color:#c0c0c0"></span>
            <p style="margin:12px 0 4px;font-size:15px;color:#444"><?php _e('No activity recorded yet', 'phpinfo-wp'); ?></p>
            <p style="margin:0;font-size:13px;color:#888"><?php
                printf(
                    __('Use the %s to start tracking changes.', 'phpinfo-wp'),
                    '<a href="' . esc_url(admin_url('admin.php?page=piwp-htaccess')) . '">' . __('PHP Config editor', 'phpinfo-wp') . '</a>'
                );
            ?></p>
        </div>
    <?php elseif (empty($filtered)): ?>
        <div class="phpinfowp-log-empty">
            <span class="dashicons dashicons-search" style="font-size:40px;width:40px;height:40px;color:#c0c0c0"></span>
            <p style="margin:12px 0 4px;font-size:15px;color:#444"><?php _e('No entries match your filter', 'phpinfo-wp'); ?></p>
        </div>
    <?php else: ?>
        <div class="phpinfowp-timeline" id="phpinfowp-timeline">
            <?php foreach ($filtered as $e):
                switch ($e['action']) {
                    case 'edit':
                        $action_meta = ['label' => __('EDIT', 'phpinfo-wp'),     'color' => '#777BB3', 'bg' => '#f3f0ff', 'icon' => 'dashicons-edit'];
                        break;
                    case 'backup':
                        $action_meta = ['label' => __('BACKUP', 'phpinfo-wp'),   'color' => '#0073aa', 'bg' => '#e8f4fc', 'icon' => 'dashicons-upload'];
                        break;
                    case 'restore':
                        $action_meta = ['label' => __('RESTORE', 'phpinfo-wp'),  'color' => '#dba617', 'bg' => '#fff8e5', 'icon' => 'dashicons-undo'];
                        break;
                    case 'autofix':
                        $action_meta = ['label' => __('AUTO-FIX', 'phpinfo-wp'), 'color' => '#7c3aed', 'bg' => '#f5f0ff', 'icon' => 'dashicons-admin-tools'];
                        break;
                    case 'autofix-revert':
                        $action_meta = ['label' => __('REVERTED', 'phpinfo-wp'), 'color' => '#9b6bf2', 'bg' => '#f5f0ff', 'icon' => 'dashicons-undo'];
                        break;
                    default:
                        $action_meta = ['label' => __('EVENT', 'phpinfo-wp'),    'color' => '#666',    'bg' => '#f6f7f7', 'icon' => 'dashicons-info'];
                        break;
                }
            ?>
                <div class="phpinfowp-timeline-entry" style="border-left-color:<?php echo $action_meta['color']; ?>"
                     data-raw="<?php echo esc_attr(strtolower($e['raw'] . ' ' . $e['description'])); ?>">

                    <div class="phpinfowp-timeline-icon" style="background:<?php echo $action_meta['bg']; ?>;color:<?php echo $action_meta['color']; ?>">
                        <span class="dashicons <?php echo $action_meta['icon']; ?>" style="font-size:16px;width:16px;height:16px;line-height:1"></span>
                    </div>

                    <div class="phpinfowp-timeline-body">
                        <div class="phpinfowp-timeline-top">
                            <span class="phpinfowp-timeline-badge" style="background:<?php echo $action_meta['color']; ?>">
                                <?php echo esc_html($action_meta['label']); ?>
                            </span>
                            <?php if ($e['file']): ?>
                                <code class="phpinfowp-timeline-file"><?php echo esc_html($e['file']); ?></code>
                            <?php endif; ?>
                            <?php if ($e['user']): ?>
                                <span class="phpinfowp-timeline-user">
                                    <span class="dashicons dashicons-admin-users" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:2px;color:#999"></span>
                                    <?php echo esc_html($e['user']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="phpinfowp-timeline-desc" style="font-size:14px;color:#1d2327;margin:4px 0 2px;line-height:1.4">
                            <?php echo esc_html($e['description']); ?>
                        </div>
                        <div class="phpinfowp-timeline-meta">
                            <?php if ($e['dt']): ?>
                                <span title="<?php echo esc_attr($e['dt']); ?> UTC"><?php echo esc_html(phpinfowp_relative_time($e['dt'])); ?></span>
                                <span style="color:#ccc">&middot;</span>
                                <span style="color:#aaa"><?php echo esc_html($e['dt']); ?> UTC</span>
                            <?php else: ?>
                                <span style="color:#aaa"><?php echo esc_html($e['raw']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
function phpinfowpLogSearch(q) {
    q = q.toLowerCase().trim();
    document.getElementById('phpinfowp-log-search-clear').style.display = q ? '' : 'none';
    var entries = document.querySelectorAll('#phpinfowp-timeline .phpinfowp-timeline-entry');
    entries.forEach(function(el) {
        var match = !q || el.dataset.raw.includes(q);
        el.style.display = match ? '' : 'none';
    });
}

function phpinfowpLogSearchClear() {
    document.getElementById('phpinfowp-log-search-input').value = '';
    phpinfowpLogSearch('');
}
<?php if ($search): ?>
phpinfowpLogSearch(<?php echo json_encode($search); ?>);
<?php endif; ?>
</script>