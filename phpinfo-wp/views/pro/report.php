<?php
defined('ABSPATH') or die('Unauthorized Access');

if (!Phpinfo_WP_License::is_valid()) {
    phpinfowp_render_feature_lock([
        'feature'  => 'Audit Report PDF',
        'icon'     => 'dashicons-media-document',
        'tagline'  => 'One-click white-label PDF with your logo — A–F grades, fix list, server fingerprint. Hand it to clients.',
        'previews' => [
            'Sections: Config · PHP · SSL · Security · DB',
            'Brand: <strong>Your logo + company name</strong>',
            'Export: <strong>One click</strong>',
        ],
    ]);
    return;
}

// Handle branding form save before building report
if (isset($_POST['phpinfowp_report_branding']) && check_admin_referer('phpinfowp_report_branding')) {
    Phpinfo_WP_Report::save_branding([
        'enabled'     => isset($_POST['branding_enabled']),
        'company'     => $_POST['branding_company']     ?? '',
        'tagline'     => $_POST['branding_tagline']     ?? '',
        'footer_note' => $_POST['branding_footer_note'] ?? '',
        'accent'      => $_POST['branding_accent']      ?? '#777BB3',
        'logo_id'     => (int) ($_POST['branding_logo_id']  ?? 0),
        'logo_url'    => $_POST['branding_logo_url']  ?? '',
    ]);
    echo '<div class="notice notice-success inline" style="margin:0 0 20px"><p>Report branding saved.</p></div>';
}

// Media library is needed for the logo picker. Only loaded on this view.
wp_enqueue_media();

$r = Phpinfo_WP_Report::build();
$b = $r['branding'];
$accent     = $b['enabled'] && $b['accent'] ? esc_attr($b['accent']) : '#777BB3';
$brand_name = $b['enabled'] && $b['company'] ? esc_html($b['company']) : 'phpinfo() WP';
$brand_tag  = $b['enabled'] && $b['tagline'] ? esc_html($b['tagline']) : 'Server Health Audit';
?>

<style>.phpinfowp-report .phpinfowp-report-brand,.phpinfowp-report .phpinfowp-report-h{color:<?php echo $accent; ?> !important}.phpinfowp-report .phpinfowp-report-cover{border-bottom-color:<?php echo $accent; ?> !important}</style>

<div class="phpinfowp-pro-page">

    <div class="phpinfowp-page-header no-print">
        <div>
            <h1>Audit Report <span class="phpinfowp-pro-badge">PRO</span></h1>
            <p class="phpinfowp-page-subtitle">A single-page server health report — print to PDF or share with clients</p>
        </div>
        <div>
            <button type="button" class="button" onclick="document.getElementById('phpinfowp-report-branding').style.display='block';return false;">
                <span class="dashicons dashicons-admin-customizer" style="vertical-align:middle"></span> White-label
            </button>
            <button type="button" class="button button-primary" onclick="window.print()">
                <span class="dashicons dashicons-printer" style="vertical-align:middle"></span> Print / Save as PDF
            </button>
        </div>
    </div>

    <!-- White-label branding form. Always hidden on initial render; the
         "White-label" button in the page header toggles it open. The form
         stays hidden after save so it doesn't crowd the report — the
         success notice above confirms the change was applied. -->
    <div id="phpinfowp-report-branding" class="phpinfowp-report-branding-form no-print" style="display:none">
        <form method="post">
            <?php wp_nonce_field('phpinfowp_report_branding'); ?>
            <input type="hidden" name="phpinfowp_report_branding" value="1">
            <h3>Report Branding</h3>
            <p class="description">Customize the report cover and footer with your own brand. Toggle off to restore the default look.</p>
            <table class="form-table">
                <tr>
                    <th><label for="branding_enabled">Enable white-label</label></th>
                    <td><label><input type="checkbox" id="branding_enabled" name="branding_enabled" value="1" <?php checked($b['enabled']); ?>> Use my branding instead of "phpinfo() WP"</label></td>
                </tr>
                <tr>
                    <th><label for="branding_company">Company name</label></th>
                    <td><input type="text" id="branding_company" name="branding_company" class="regular-text" value="<?php echo esc_attr($b['company']); ?>" placeholder="Acme Digital Studio"></td>
                </tr>
                <tr>
                    <th><label for="branding_tagline">Report title</label></th>
                    <td><input type="text" id="branding_tagline" name="branding_tagline" class="regular-text" value="<?php echo esc_attr($b['tagline']); ?>" placeholder="Quarterly Site Health Audit"></td>
                </tr>
                <tr>
                    <th><label for="branding_footer_note">Footer note</label></th>
                    <td><textarea id="branding_footer_note" name="branding_footer_note" class="regular-text" rows="2" placeholder="Prepared by Acme Digital Studio · support@acme.com"><?php echo esc_textarea($b['footer_note']); ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Company logo</label></th>
                    <td>
                        <div class="phpinfowp-logo-picker" id="phpinfowp-logo-picker">
                            <div class="phpinfowp-logo-preview" id="phpinfowp-logo-preview">
                                <?php if (!empty($b['logo_url'])): ?>
                                    <img src="<?php echo esc_url($b['logo_url']); ?>" alt="Company logo">
                                <?php else: ?>
                                    <span class="phpinfowp-logo-empty">No logo set</span>
                                <?php endif; ?>
                            </div>
                            <div class="phpinfowp-logo-actions">
                                <button type="button" class="button" id="phpinfowp-logo-choose">
                                    <?php echo !empty($b['logo_url']) ? 'Change logo' : 'Upload logo'; ?>
                                </button>
                                <button type="button" class="button button-link-delete" id="phpinfowp-logo-remove" <?php echo empty($b['logo_url']) ? 'style="display:none"' : ''; ?>>
                                    Remove
                                </button>
                                <p class="description" style="margin-top:6px">
                                    PNG or SVG with a transparent background works best. Renders on the report cover at about 60×60.
                                </p>
                            </div>
                        </div>
                        <input type="hidden" id="branding_logo_id"  name="branding_logo_id"  value="<?php echo (int) ($b['logo_id'] ?? 0); ?>">
                        <input type="hidden" id="branding_logo_url" name="branding_logo_url" value="<?php echo esc_attr($b['logo_url']); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="branding_accent">Accent color</label></th>
                    <td><input type="color" id="branding_accent" name="branding_accent" value="<?php echo esc_attr($b['accent']); ?>"></td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary">Save branding</button></p>
        </form>
    </div>

    <script>
    /* Logo picker wiring.
       Previous version bailed out at script-parse time if `wp.media` wasn't
       defined yet — but wp_enqueue_media() prints its JS in the admin footer,
       so wp.media is undefined while this script is being parsed and the
       guard silently disabled the entire feature. Fix: always attach the
       click handler; check wp.media at click time (footer has loaded by then). */
    (function () {
        var idField   = document.getElementById('branding_logo_id');
        var urlField  = document.getElementById('branding_logo_url');
        var preview   = document.getElementById('phpinfowp-logo-preview');
        var chooseBtn = document.getElementById('phpinfowp-logo-choose');
        var removeBtn = document.getElementById('phpinfowp-logo-remove');
        if (!chooseBtn || !idField || !urlField || !preview) return;
        var frame;

        function setLogo(id, url) {
            idField.value  = id;
            urlField.value = url;
            preview.innerHTML = url
                ? '<img src="' + url + '" alt="Company logo">'
                : '<span class="phpinfowp-logo-empty">No logo set</span>';
            chooseBtn.textContent = url ? 'Change logo' : 'Upload logo';
            if (removeBtn) removeBtn.style.display = url ? '' : 'none';
        }

        chooseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!window.wp || !wp.media) {
                window.alert('The WordPress media library is still loading. Wait one second and try again.');
                return;
            }
            if (frame) { frame.open(); return; }
            frame = wp.media({
                title: 'Choose company logo',
                button: { text: 'Use this logo' },
                library: { type: ['image'] },
                multiple: false
            });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                var url = (att.sizes && att.sizes.medium && att.sizes.medium.url) || att.url;
                setLogo(att.id, url);
            });
            frame.open();
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                setLogo(0, '');
            });
        }
    }());
    </script>

    <div class="phpinfowp-report">

        <?php
        $overall = $r['overall'];
        $crit_n  = count($r['criticals']);
        $warn_n  = count($r['warnings']);

        // Helper to render a verdict pill inline anywhere.
        $verdict_pill = static function (string $v, string $text = '') {
            $svg_ok       = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;display:inline-block;vertical-align:-1px"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>';
            $svg_warn     = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;display:inline-block;vertical-align:-1px"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/></svg>';
            $svg_critical = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;display:inline-block;vertical-align:-1px"><path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 01-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg>';

            $map = [
                'ok'       => ['ok',       '#0a7d2e', '#e8f6ec', '#a7d8b3', $svg_ok],
                'warn'     => ['warn',     '#8a6500', '#fff5d6', '#e9c977', $svg_warn],
                'critical' => ['critical', '#8e1414', '#fbe7e8', '#ecb4b5', $svg_critical],
            ];
            [$cls, $fg, $bg, $br, $ic] = $map[$v] ?? $map['ok'];
            $label = $text ?: ($v === 'ok' ? 'OK' : ($v === 'warn' ? 'Warning' : 'Critical'));
            return sprintf(
                '<span class="phpinfowp-rv phpinfowp-rv-%s" style="color:%s;background:%s;border-color:%s"><span class="phpinfowp-rv-ic">%s</span>%s</span>',
                $cls, $fg, $bg, $br, $ic, esc_html($label)
            );
        };

        // Small helpers to classify a value into a verdict.
        $verdict_eol = static function (array $eol) {
            if (($eol['status'] ?? '') === 'eol') return 'critical';
            if (($eol['status'] ?? '') === 'warning' && (int) ($eol['days'] ?? 0) < 90) return 'critical';
            if (($eol['status'] ?? '') === 'warning') return 'warn';
            return 'ok';
        };
        $verdict_score = static function (?int $s) {
            if ($s === null) return 'ok';
            if ($s < 50) return 'critical';
            if ($s < 80) return 'warn';
            return 'ok';
        };
        $verdict_hit = static function ($rate) {
            $rate = (float) $rate;
            if ($rate < 50) return 'critical';
            if ($rate < 90) return 'warn';
            return 'ok';
        };
        ?>

        <!-- Cover -->
        <div class="phpinfowp-report-cover">
            <?php if ($b['enabled'] && !empty($b['logo_url'])): ?>
                <img src="<?php echo esc_url($b['logo_url']); ?>" alt="<?php echo esc_attr($brand_name); ?> logo"
                     class="phpinfowp-report-logo">
            <?php endif; ?>
            <div class="phpinfowp-report-brand"><?php echo $brand_name; ?></div>
            <h2 class="phpinfowp-report-title"><?php echo $brand_tag; ?></h2>
            <div class="phpinfowp-report-site"><?php echo esc_html($r['site']); ?></div>
            <div class="phpinfowp-report-url"><?php echo esc_html($r['url']); ?></div>
            <div class="phpinfowp-report-date">Generated <?php echo esc_html(wp_date('F j, Y · H:i', $r['generated_at'])); ?></div>
        </div>

        <!-- Overall site health (one number, page-1 hero) -->
        <div class="phpinfowp-report-overall">
            <div class="phpinfowp-report-overall-donut">
                <?php echo Phpinfo_WP_Report::render_donut(
                    $overall['score'], 180, $overall['verdict_color'], '#eef0f4', 14
                ); ?>
                <div class="phpinfowp-report-overall-num">
                    <div class="phpinfowp-report-overall-num-big" style="color:<?php echo esc_attr($overall['verdict_color']); ?>"><?php echo (int) $overall['score']; ?></div>
                    <div class="phpinfowp-report-overall-num-sm">/ 100</div>
                </div>
            </div>
            <div class="phpinfowp-report-overall-info">
                <div class="phpinfowp-report-overall-label">Overall Site Health</div>
                <div class="phpinfowp-report-overall-verdict" style="color:<?php echo esc_attr($overall['verdict_color']); ?>"><?php echo esc_html($overall['verdict_label']); ?></div>
                <div class="phpinfowp-report-overall-grade">Grade <strong><?php echo esc_html($overall['grade']); ?></strong></div>
                <?php if ($crit_n || $warn_n): ?>
                    <div class="phpinfowp-report-overall-counts">
                        <?php if ($crit_n): ?><span class="phpinfowp-rc phpinfowp-rc-crit"><?php echo $crit_n; ?> critical</span><?php endif; ?>
                        <?php if ($warn_n): ?><span class="phpinfowp-rc phpinfowp-rc-warn"><?php echo $warn_n; ?> warning<?php echo $warn_n === 1 ? '' : 's'; ?></span><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="phpinfowp-report-overall-counts"><span class="phpinfowp-rc phpinfowp-rc-ok">No critical issues</span></div>
                <?php endif; ?>
                <p class="phpinfowp-report-overall-note">A weighted score across PHP config, security headers, OPcache, SSL, PHP support window, and database health. <strong>Aim for 85+.</strong></p>
            </div>
        </div>

        <!-- Category bar chart -->
        <?php if (!empty($r['bars'])): ?>
        <div class="phpinfowp-report-bars">
            <h3 class="phpinfowp-report-h">Subscores</h3>
            <div class="phpinfowp-report-bars-grid">
                <?php foreach ($r['bars'] as $bar):
                    [$bv, $bvl, $bvc] = Phpinfo_WP_Report::score_to_verdict((int) $bar['score']); ?>
                    <div class="phpinfowp-report-bar">
                        <div class="phpinfowp-report-bar-top">
                            <span class="phpinfowp-report-bar-lbl"><?php echo esc_html($bar['label']); ?></span>
                            <span class="phpinfowp-report-bar-num" style="color:<?php echo esc_attr($bvc); ?>"><?php echo (int) $bar['score']; ?></span>
                        </div>
                        <div class="phpinfowp-report-bar-track">
                            <div class="phpinfowp-report-bar-fill" style="width:<?php echo (int) max(2, $bar['score']); ?>%;background:<?php echo esc_attr($bvc); ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Critical issues banner -->
        <?php if ($crit_n): ?>
        <div class="phpinfowp-report-critbar">
            <div class="phpinfowp-report-critbar-head">
                <span class="phpinfowp-report-critbar-ic"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;display:block"><path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 01-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg></span>
                <div>
                    <div class="phpinfowp-report-critbar-title"><?php echo $crit_n; ?> critical issue<?php echo $crit_n === 1 ? '' : 's'; ?> need immediate attention</div>
                    <div class="phpinfowp-report-critbar-sub">Anything here is impacting security, performance, or breaks within the next 90 days.</div>
                </div>
            </div>
            <ul class="phpinfowp-report-critbar-list">
                <?php foreach ($r['criticals'] as $c): ?>
                    <li>
                        <strong><?php echo esc_html($c['title']); ?></strong>
                        <span class="phpinfowp-report-critbar-cat"><?php echo esc_html($c['category']); ?></span>
                        <div class="phpinfowp-report-critbar-reason"><?php echo esc_html($c['reason']); ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Top 3 actions this week -->
        <?php if (!empty($r['priorities'])): ?>
        <h3 class="phpinfowp-report-h phpinfowp-report-h-page2">Top <?php echo count($r['priorities']); ?> action<?php echo count($r['priorities']) === 1 ? '' : 's'; ?> this week</h3>
        <p class="phpinfowp-report-h-sub">Tackle these in order. Each card explains why it matters and how to fix it.</p>
        <ol class="phpinfowp-report-priorities">
            <?php foreach ($r['priorities'] as $i => $p): ?>
                <li class="phpinfowp-report-priority phpinfowp-report-priority-<?php echo esc_attr($p['urgency']); ?>">
                    <div class="phpinfowp-report-priority-num"><?php echo $i + 1; ?></div>
                    <div class="phpinfowp-report-priority-body">
                        <div class="phpinfowp-report-priority-head">
                            <?php echo $verdict_pill($p['urgency'], $p['urgency'] === 'critical' ? 'Critical' : 'High'); ?>
                            <span class="phpinfowp-report-priority-cat"><?php echo esc_html($p['category']); ?></span>
                        </div>
                        <div class="phpinfowp-report-priority-title"><?php echo esc_html($p['title']); ?></div>
                        <div class="phpinfowp-report-priority-why">
                            <strong>Why it matters:</strong>
                            <?php echo esc_html($p['reason']); ?>
                        </div>
                        <div class="phpinfowp-report-priority-how">
                            <strong>How to fix:</strong>
                            <?php echo wp_kses($p['how'], ['code' => [], 'strong' => [], 'em' => []]); ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php else: ?>
        <h3 class="phpinfowp-report-h">No urgent actions</h3>
        <p>The site is in good shape. Keep an eye on the subscores above and review again next month.</p>
        <?php endif; ?>

        <!-- Snapshot grid with verdict pills + plain-English captions -->
        <h3 class="phpinfowp-report-h">Snapshot</h3>
        <div class="phpinfowp-report-grid">
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">PHP version <?php echo $verdict_pill($verdict_eol($r['eol'])); ?></div>
                <div class="phpinfowp-report-stat-value"><?php echo esc_html($r['php']); ?></div>
                <div class="phpinfowp-report-stat-sub">
                    <?php if ($r['eol']['status'] === 'eol'): ?>
                        End of life
                    <?php elseif ($r['eol']['status'] === 'warning'): ?>
                        EOL in <?php echo (int) $r['eol']['days']; ?> days (<?php echo esc_html($r['eol']['eol']); ?>)
                    <?php else: ?>
                        Supported until <?php echo esc_html($r['eol']['eol']); ?>
                    <?php endif; ?>
                </div>
                <div class="phpinfowp-report-stat-cap"><?php echo esc_html(Phpinfo_WP_Report::plain_english('php_version')); ?></div>
            </div>
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">WordPress <?php echo $verdict_pill(($r['extras']['updates']['core'] ?? 0) > 0 ? 'warn' : 'ok'); ?></div>
                <div class="phpinfowp-report-stat-value"><?php echo esc_html($r['wp']); ?></div>
                <div class="phpinfowp-report-stat-sub">
                    <?php if (($r['extras']['updates']['core'] ?? 0) > 0): ?>
                        Update available
                    <?php else: ?>
                        Up to date
                    <?php endif; ?>
                </div>
                <div class="phpinfowp-report-stat-cap"><?php echo esc_html(Phpinfo_WP_Report::plain_english('wp_version')); ?></div>
            </div>
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">PHP Config <?php echo $verdict_pill($verdict_score((int) ($r['grader']['score'] ?? 0))); ?></div>
                <div class="phpinfowp-report-stat-value"><?php echo esc_html($r['grader']['grade']); ?> <span class="phpinfowp-report-stat-value-sub"><?php echo (int) $r['grader']['score']; ?>/100</span></div>
                <div class="phpinfowp-report-stat-cap"><?php echo esc_html(Phpinfo_WP_Report::plain_english('config_grade')); ?></div>
            </div>
            <?php if (!isset($r['headers']['error'])): ?>
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">Security Headers <?php echo $verdict_pill($verdict_score((int) ($r['headers']['score'] ?? 0))); ?></div>
                <div class="phpinfowp-report-stat-value"><?php echo esc_html($r['headers']['grade']); ?> <span class="phpinfowp-report-stat-value-sub"><?php echo (int) $r['headers']['score']; ?>/100</span></div>
                <div class="phpinfowp-report-stat-cap"><?php echo esc_html(Phpinfo_WP_Report::plain_english('headers')); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($r['db']):
                $db_v = !empty($r['db']['eol']) && ($db_ts = strtotime($r['db']['eol']))
                    ? (($db_ts - time()) / DAY_IN_SECONDS < 0 ? 'critical' : (($db_ts - time()) / DAY_IN_SECONDS < 90 ? 'critical' : (($db_ts - time()) / DAY_IN_SECONDS < 365 ? 'warn' : 'ok')))
                    : 'ok';
            ?>
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">Database <?php echo $verdict_pill($db_v); ?></div>
                <div class="phpinfowp-report-stat-value"><?php echo esc_html($r['db']['engine'] . ' ' . $r['db']['version']); ?></div>
                <div class="phpinfowp-report-stat-sub">
                    <?php if (!empty($r['db']['eol'])): ?>
                        EOL <?php echo esc_html($r['db']['eol']); ?>
                    <?php else: ?>
                        <?php echo size_format($r['db_size']['total']); ?> total
                    <?php endif; ?>
                </div>
                <div class="phpinfowp-report-stat-cap"><?php echo esc_html(Phpinfo_WP_Report::plain_english('db_engine')); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($r['opcache']): ?>
            <div class="phpinfowp-report-stat">
                <div class="phpinfowp-report-stat-label">OPcache hit rate <?php echo $verdict_pill($verdict_hit($r['opcache']['hit_rate'] ?? 0)); ?></div>
                <div class="phpinfowp-report-stat-value"><?php echo $r['opcache']['hit_rate'] !== null ? esc_html($r['opcache']['hit_rate']) . '%' : '—'; ?></div>
                <div class="phpinfowp-report-stat-sub">Target 95% +</div>
                <div class="phpinfowp-report-stat-cap"><?php echo esc_html(Phpinfo_WP_Report::plain_english('opcache_hit')); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Site configuration (new section) -->
        <?php $ex = $r['extras']; ?>
        <h3 class="phpinfowp-report-h">Site configuration</h3>
        <table class="phpinfowp-report-table">
            <tbody>
                <tr>
                    <td style="width:40%">HTTPS</td>
                    <td><?php echo $ex['https']['is_https'] && !$ex['https']['mixed_risk']
                        ? $verdict_pill('ok', 'Fully on HTTPS')
                        : $verdict_pill('warn', $ex['https']['is_https'] ? 'Mixed-content risk' : 'Not on HTTPS'); ?></td>
                </tr>
                <tr>
                    <td>WP_DEBUG_DISPLAY <span class="phpinfowp-report-inline-cap">(<?php echo esc_html(Phpinfo_WP_Report::plain_english('wp_debug_display')); ?>)</span></td>
                    <td>
                        <?php if ($ex['wp_debug']['debug_display']): ?>
                            <?php echo $verdict_pill('critical', 'On — errors visible to visitors'); ?>
                        <?php else: ?>
                            <?php echo $verdict_pill('ok', 'Off (safe)'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Pending updates</td>
                    <td>
                        <?php
                        $u = $ex['updates'];
                        $tot = $u['core'] + $u['plugins'] + $u['themes'];
                        if ($tot === 0) {
                            echo $verdict_pill('ok', 'All up to date');
                        } else {
                            $bits = [];
                            if ($u['core'])    $bits[] = 'WP core';
                            if ($u['plugins']) $bits[] = "{$u['plugins']} plugin" . ($u['plugins'] === 1 ? '' : 's');
                            if ($u['themes'])  $bits[] = "{$u['themes']} theme"  . ($u['themes']  === 1 ? '' : 's');
                            echo $verdict_pill($u['core'] ? 'warn' : 'warn', implode(' · ', $bits) . ' pending');
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td>Backup plugin</td>
                    <td>
                        <?php if ($ex['backup']['detected']): ?>
                            <?php echo $verdict_pill('ok', esc_html($ex['backup']['plugin']) . ' detected'); ?>
                        <?php else: ?>
                            <?php echo $verdict_pill('warn', 'None detected'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php foreach ($ex['permissions'] as $name => $info):
                    if (empty($info['exists'])) continue; ?>
                    <tr>
                        <td><code><?php echo esc_html($name); ?></code> permissions</td>
                        <td>
                            <?php echo $verdict_pill($info['verdict'], $info['perms']); ?>
                            <?php if ($info['verdict'] !== 'ok'): ?>
                                <span class="phpinfowp-report-inline-cap">— consider <code>chmod <?php echo $name === 'wp-config.php' ? '600' : '644'; ?> <?php echo esc_html($name); ?></code></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Failing config checks -->
        <?php $failing = array_filter($r['grader']['checks'], function ($c) {
                        return $c['status'] === 'fail';
                    }); ?>
        <?php if ($failing): ?>
            <h3 class="phpinfowp-report-h">Config issues (<?php echo count($failing); ?>)</h3>
            <table class="phpinfowp-report-table">
                <thead><tr><th>Directive</th><th>Current</th><th>Recommended</th><th style="width:90px">Verdict</th></tr></thead>
                <tbody>
                    <?php foreach ($failing as $c): ?>
                        <tr>
                            <td><code><?php echo esc_html($c['key']); ?></code><?php if (!empty($c['why'])): ?><div class="phpinfowp-report-inline-cap"><?php echo esc_html($c['why']); ?></div><?php endif; ?></td>
                            <td style="color:#8e1414"><?php echo esc_html($c['value']); ?></td>
                            <td style="color:#0a7d2e"><?php echo esc_html($c['good']); ?></td>
                            <td><?php echo $verdict_pill('critical', 'Fix'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Missing security headers -->
        <?php if (!isset($r['headers']['error'])):
            $missing = array_filter($r['headers']['results'], function ($h) {
                return !$h['present'];
            });
        ?>
            <?php if ($missing): ?>
                <h3 class="phpinfowp-report-h">Missing security headers (<?php echo count($missing); ?>)</h3>
                <ul class="phpinfowp-report-list">
                    <?php foreach ($missing as $h): ?>
                        <li>
                            <?php echo $verdict_pill('warn', 'Missing'); ?>
                            <strong><?php echo esc_html($h['label']); ?></strong> &mdash; <?php echo esc_html($h['desc']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>

        <!-- SSL summary -->
        <?php if ($r['ssl']): ?>
            <h3 class="phpinfowp-report-h">SSL certificates</h3>
            <table class="phpinfowp-report-table">
                <thead><tr><th>Host</th><th>Issuer</th><th>Expires</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($r['ssl'] as $c):
                        if (!empty($c['error'])) continue;
                        $sv = $c['days'] < 0 ? 'critical' : ($c['days'] < 14 ? 'critical' : ($c['days'] < 30 ? 'warn' : 'ok'));
                    ?>
                        <tr>
                            <td><?php echo esc_html($c['host']); ?></td>
                            <td><?php echo esc_html($c['issuer']); ?></td>
                            <td><?php echo esc_html($c['expiry']); ?></td>
                            <td><?php echo $verdict_pill($sv, $c['days'] < 0 ? 'Expired' : $c['days'] . ' days'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- DB summary -->
        <?php if ($r['db']): ?>
            <h3 class="phpinfowp-report-h">Database</h3>
            <table class="phpinfowp-report-table">
                <tbody>
                    <tr><td style="width:40%">Engine</td><td><?php echo esc_html($r['db']['engine'] . ' ' . $r['db']['version']); ?></td></tr>
                    <tr><td>End-of-life</td><td><?php echo esc_html($r['db']['eol'] ?? '—'); ?></td></tr>
                    <tr><td>Total size</td><td><?php echo size_format($r['db_size']['total']); ?> across <?php echo (int) $r['db_size']['tables']; ?> tables</td></tr>
                    <tr>
                        <td>Autoload data</td>
                        <td>
                            <?php
                            $al_bytes = (int) $r['autoload']['bytes'];
                            $al_v     = $al_bytes > 1048576 ? 'warn' : 'ok';
                            ?>
                            <?php echo size_format($al_bytes); ?> across <?php echo (int) $r['autoload']['count']; ?> options
                            <?php echo $verdict_pill($al_v, $al_v === 'ok' ? 'Tiny (good)' : 'Large'); ?>
                            <div class="phpinfowp-report-inline-cap"><?php echo esc_html(Phpinfo_WP_Report::plain_english('autoload')); ?></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Cron -->
        <?php if ($r['cron'] && ($r['cron']['overdue'] || $r['cron']['orphan'])): ?>
            <h3 class="phpinfowp-report-h">Cron health</h3>
            <ul class="phpinfowp-report-list">
                <li><?php echo (int) $r['cron']['total']; ?> total scheduled events</li>
                <?php if ($r['cron']['overdue']): ?>
                    <li><?php echo $verdict_pill('critical', 'Overdue'); ?> <?php echo (int) $r['cron']['overdue']; ?> event(s) <span class="phpinfowp-report-inline-cap">— <?php echo esc_html(Phpinfo_WP_Report::plain_english('overdue_cron')); ?></span></li>
                <?php endif; ?>
                <?php if ($r['cron']['orphan']): ?>
                    <li><?php echo $verdict_pill('warn', 'Orphan'); ?> <?php echo (int) $r['cron']['orphan']; ?> hook(s) <span class="phpinfowp-report-inline-cap">— <?php echo esc_html(Phpinfo_WP_Report::plain_english('orphan_cron')); ?></span></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <!-- Compat -->
        <?php if ($r['compat'] && !isset($r['compat']['error']) && !empty($r['compat']['total'])): ?>
            <h3 class="phpinfowp-report-h">PHP compatibility (target PHP <?php echo esc_html($r['compat']['target']); ?>)</h3>
            <p><?php echo $verdict_pill('warn', $r['compat']['total'] . ' issues'); ?> across <?php echo (int) $r['compat']['with_issues']; ?> plugins/themes. Open the <strong>PHP Compatibility</strong> page for the full file list.</p>
        <?php endif; ?>

        <div class="phpinfowp-report-footer">
            <?php if ($b['enabled'] && $b['footer_note']): ?>
                <?php echo nl2br(esc_html($b['footer_note'])); ?>
            <?php else: ?>
                Report generated by <strong><?php echo $brand_name; ?></strong> &middot; <?php echo esc_html(get_site_url()); ?>
            <?php endif; ?>
        </div>

    </div>
</div>
