<?php
defined('ABSPATH') or die('Unauthorized Access');
if (!current_user_can('manage_options')) wp_die(__('Unauthorized.', 'phpinfo-wp'));

$is_pro          = Phpinfo_WP_License::is_valid();
$is_unlimited    = Phpinfo_WP_License::is_unlimited();
$is_free_preview = !$is_pro;

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

$force_refresh = !empty($_GET['rebuild']) && (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'phpinfowp_report_rebuild') || wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'phpinfowp_report_rebuild'));
if ($force_refresh) {
    Phpinfo_WP_Report::bust_cache();
}
$r = Phpinfo_WP_Report::build($force_refresh);

$b = $r['branding'];
$is_unlimited = Phpinfo_WP_License::is_unlimited();
$accent     = $is_unlimited && $b['enabled'] && $b['accent'] ? esc_attr($b['accent']) : '#777BB3';
$brand_name = $is_unlimited && $b['enabled'] && $b['company'] ? esc_html($b['company']) : 'phpinfo() WP';
$brand_tag  = $is_unlimited && $b['enabled'] && $b['tagline'] ? esc_html($b['tagline']) : 'Server Health Audit';
?>

<style>.phpinfowp-report .phpinfowp-report-brand,.phpinfowp-report .phpinfowp-report-h{color:<?php echo $accent; ?> !important}.phpinfowp-report .phpinfowp-report-cover{border-bottom-color:<?php echo $accent; ?> !important}</style>

<div class="phpinfowp-pro-page">

    <?php if (isset($_GET['trial_activated']) && $_GET['trial_activated'] == 1): ?>
        <div class="notice notice-success inline is-dismissible" style="margin:0 0 20px">
            <p><strong><?php _e('Success!', 'phpinfo-wp'); ?></strong> <?php _e('Your 7-day Pro Trial is now active. All Pro features are unlocked.', 'phpinfo-wp'); ?></p>
        </div>
    <?php endif; ?>



    <div class="phpinfowp-page-header no-print">
        <div>
            <h1>
                <?php _e('Audit Report', 'phpinfo-wp'); ?>
                <span class="phpinfowp-pro-badge"><?php _e('PRO', 'phpinfo-wp'); ?></span>
                <span class="piwp-page-info" data-tooltip="<?php esc_attr_e('A single-page comprehensive server health audit report — print to PDF or export for clients.', 'phpinfo-wp'); ?>" tabindex="0" aria-label="<?php esc_attr_e('About this page', 'phpinfo-wp'); ?>"><span class="dashicons dashicons-info-outline"></span></span>
            </h1>
        </div>
        <div>
            <?php if ($is_free_preview): ?>
                <button type="button" class="button" disabled style="opacity: 0.6; cursor: not-allowed;">
                    <span class="dashicons dashicons-lock" style="vertical-align:middle"></span> <?php _e('White-label', 'phpinfo-wp'); ?>
                </button>
                <button type="button" class="button button-primary" disabled style="opacity: 0.6; cursor: not-allowed;">
                    <span class="dashicons dashicons-lock" style="vertical-align:middle"></span> <?php _e('Print / Save as PDF', 'phpinfo-wp'); ?>
                </button>
            <?php else: ?>
                <button type="button" id="phpinfowp-report-rebuild-btn" class="button button-secondary" style="margin-right:6px;">
                    <span class="dashicons dashicons-update" style="vertical-align:middle; font-size:15px; width:15px; height:15px; margin-top:-1px;"></span> <?php _e('Re-generate Report', 'phpinfo-wp'); ?>
                </button>
                <button type="button" class="button" onclick="document.getElementById('phpinfowp-report-branding').style.display='block';return false;">
                    <span class="dashicons dashicons-admin-customizer" style="vertical-align:middle"></span> <?php _e('White-label', 'phpinfo-wp'); ?>
                </button>
                <button type="button" class="button button-primary" onclick="window.print()">
                    <span class="dashicons dashicons-printer" style="vertical-align:middle"></span> <?php _e('Print / Save as PDF', 'phpinfo-wp'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- White-label branding form. Always hidden on initial render; the
         "White-label" button in the page header toggles it open. The form
         stays hidden after save so it doesn't crowd the report — the
         success notice above confirms the change was applied. -->
    <div id="phpinfowp-report-branding" class="phpinfowp-report-branding-form no-print" style="display:none">
        <?php if (!Phpinfo_WP_License::is_unlimited()): ?>
            <div class="phpinfowp-upgrade-banner" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:28px 24px;text-align:center;margin-bottom:16px;box-shadow:0 4px 12px rgba(0,0,0,0.04)">
                <span class="dashicons dashicons-lock" style="font-size:36px;width:36px;height:36px;color:#6366f1;display:inline-block;margin-bottom:10px"></span>
                <h3 style="margin:0 0 8px;font-size:18px;font-weight:700;color:#0f172a"><?php _e('White-Label Branding is an Unlimited Tier Feature', 'phpinfo-wp'); ?></h3>
                <p style="margin:0 0 16px;color:#475569;font-size:13.5px;max-width:500px;margin-left:auto;margin-right:auto;line-height:1.5"><?php _e('Upload your own company logo, set a custom accent color, and hide the "phpinfo() WP" branding from client PDF reports.', 'phpinfo-wp'); ?></p>
                <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1;border-color:#6366f1;height:38px;line-height:36px;font-size:13.5px;font-weight:600;padding:0 22px;border-radius:6px;text-decoration:none;display:inline-block;box-shadow:0 2px 6px rgba(99,102,241,0.25)"><?php _e('Upgrade License →', 'phpinfo-wp'); ?></a>
            </div>
        <?php else: ?>
            <form method="post">
            <?php wp_nonce_field('phpinfowp_report_branding'); ?>
            <input type="hidden" name="phpinfowp_report_branding" value="1">
            
            <div class="phpinfowp-branding-header">
                <div>
                    <h3 style="margin:0 0 4px; font-size:17px; font-weight:700; color:#0f172a;"><?php _e('Report Branding', 'phpinfo-wp'); ?></h3>
                    <p class="description" style="margin:0; font-size:13px; color:#64748b;"><?php _e('Customize the report cover and footer with your own brand. Toggle off to restore the default look.', 'phpinfo-wp'); ?></p>
                </div>
                <button type="button" class="phpinfowp-branding-close" onclick="document.getElementById('phpinfowp-report-branding').style.display='none';" title="<?php esc_attr_e('Close', 'phpinfo-wp'); ?>">&times;</button>
            </div>

            <div class="phpinfowp-branding-grid">
                <!-- Enable white-label -->
                <div class="phpinfowp-branding-row">
                    <label class="phpinfowp-branding-label"><?php _e('Enable white-label', 'phpinfo-wp'); ?></label>
                    <div class="phpinfowp-branding-control">
                        <label class="phpinfowp-switch-label">
                            <input type="checkbox" id="branding_enabled" name="branding_enabled" value="1" <?php checked($b['enabled']); ?>>
                            <span><?php _e('Use my branding instead of "phpinfo() WP"', 'phpinfo-wp'); ?></span>
                        </label>
                    </div>
                </div>

                <!-- Company name -->
                <div class="phpinfowp-branding-row">
                    <label for="branding_company" class="phpinfowp-branding-label"><?php _e('Company name', 'phpinfo-wp'); ?></label>
                    <div class="phpinfowp-branding-control">
                        <input type="text" id="branding_company" name="branding_company" class="phpinfowp-branding-input" value="<?php echo esc_attr($b['company']); ?>" placeholder="<?php echo esc_attr__('Acme Digital Studio', 'phpinfo-wp'); ?>">
                    </div>
                </div>

                <!-- Report title -->
                <div class="phpinfowp-branding-row">
                    <label for="branding_tagline" class="phpinfowp-branding-label"><?php _e('Report title', 'phpinfo-wp'); ?></label>
                    <div class="phpinfowp-branding-control">
                        <input type="text" id="branding_tagline" name="branding_tagline" class="phpinfowp-branding-input" value="<?php echo esc_attr($b['tagline']); ?>" placeholder="<?php echo esc_attr__('Quarterly Site Health Audit', 'phpinfo-wp'); ?>">
                    </div>
                </div>

                <!-- Footer note -->
                <div class="phpinfowp-branding-row">
                    <label for="branding_footer_note" class="phpinfowp-branding-label"><?php _e('Footer note', 'phpinfo-wp'); ?></label>
                    <div class="phpinfowp-branding-control">
                        <textarea id="branding_footer_note" name="branding_footer_note" class="phpinfowp-branding-textarea" rows="2" placeholder="<?php echo esc_attr__('Prepared by Acme Digital Studio · support@acme.com', 'phpinfo-wp'); ?>"><?php echo esc_textarea($b['footer_note']); ?></textarea>
                    </div>
                </div>

                <!-- Company logo -->
                <div class="phpinfowp-branding-row">
                    <label class="phpinfowp-branding-label"><?php _e('Company logo', 'phpinfo-wp'); ?></label>
                    <div class="phpinfowp-branding-control">
                        <div class="phpinfowp-logo-picker" id="phpinfowp-logo-picker">
                            <div class="phpinfowp-logo-preview" id="phpinfowp-logo-preview">
                                <?php if (!empty($b['logo_url'])): ?>
                                    <img src="<?php echo esc_url($b['logo_url']); ?>" alt="Company logo">
                                <?php else: ?>
                                    <span class="phpinfowp-logo-empty"><?php _e('No logo set', 'phpinfo-wp'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="phpinfowp-logo-actions">
                                <div class="phpinfowp-logo-btn-group">
                                    <button type="button" class="button" id="phpinfowp-logo-choose">
                                        <?php echo !empty($b['logo_url']) ? __('Change logo', 'phpinfo-wp') : __('Upload logo', 'phpinfo-wp'); ?>
                                    </button>
                                    <button type="button" class="button button-link-delete" id="phpinfowp-logo-remove" <?php echo empty($b['logo_url']) ? 'style="display:none"' : ''; ?>>
                                        <?php _e('Remove', 'phpinfo-wp'); ?>
                                    </button>
                                </div>
                                <p class="description" style="margin:4px 0 0; font-size:12px; color:#64748b;">
                                    <?php _e('PNG or SVG with a transparent background works best. Renders on the report cover at about 60×60px.', 'phpinfo-wp'); ?>
                                </p>
                            </div>
                        </div>
                        <input type="hidden" id="branding_logo_id"  name="branding_logo_id"  value="<?php echo (int) ($b['logo_id'] ?? 0); ?>">
                        <input type="hidden" id="branding_logo_url" name="branding_logo_url" value="<?php echo esc_attr($b['logo_url']); ?>">
                    </div>
                </div>

                <!-- Accent color -->
                <div class="phpinfowp-branding-row">
                    <label for="branding_accent" class="phpinfowp-branding-label"><?php _e('Accent color', 'phpinfo-wp'); ?></label>
                    <div class="phpinfowp-branding-control phpinfowp-color-control">
                        <input type="color" id="branding_accent" name="branding_accent" class="phpinfowp-color-picker-input" value="<?php echo esc_attr($b['accent']); ?>" oninput="var h = document.getElementById('branding_accent_hex'); if(h) h.value = this.value;">
                        <input type="text" id="branding_accent_hex" class="phpinfowp-color-hex" value="<?php echo esc_attr($b['accent']); ?>" oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) { var p = document.getElementById('branding_accent'); if(p) p.value = this.value; }">
                    </div>
                </div>
            </div>

            <div class="phpinfowp-branding-footer">
                <button type="submit" class="button button-primary button-large"><?php _e('Save Branding', 'phpinfo-wp'); ?></button>
                <button type="button" class="button button-secondary" onclick="document.getElementById('phpinfowp-report-branding').style.display='none';"><?php _e('Cancel', 'phpinfo-wp'); ?></button>
            </div>
        </form>
        <?php endif; ?>
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
                : '<span class="phpinfowp-logo-empty"><?php _e('No logo set', 'phpinfo-wp'); ?></span>';
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
            <?php if ($is_unlimited && $b['enabled'] && !empty($b['logo_url'])): ?>
                <img src="<?php echo esc_url($b['logo_url']); ?>" alt="<?php echo esc_attr($brand_name); ?> logo"
                     class="phpinfowp-report-logo">
            <?php endif; ?>
            <div class="phpinfowp-report-brand"><?php echo $brand_name; ?></div>
            <h2 class="phpinfowp-report-title"><?php echo $brand_tag; ?></h2>
            <div class="phpinfowp-report-site"><?php echo esc_html($r['site']); ?></div>
            <div class="phpinfowp-report-url"><?php echo esc_html($r['url']); ?></div>
            <?php
            $tz_name = wp_timezone_string();
            $time_str = wp_date('F j, Y · H:i', $r['generated_at']);
            ?>
            <div class="phpinfowp-report-date">Generated <?php echo esc_html($time_str); ?> (<?php echo esc_html($tz_name); ?>)</div>
        </div>

        <!-- Historical Audit Diff Strip -->
        <?php $diff = $r['diff'] ?? null; ?>
        <?php if ($diff !== null): ?>
            <div class="phpinfowp-report-diff-bar">
                <div class="phpinfowp-report-diff-title">
                    <span class="dashicons dashicons-backup" style="font-size:17px;width:17px;height:17px;color:#6366f1;"></span>
                    <span><strong><?php _e('Changes Since Last Audit', 'phpinfo-wp'); ?></strong> (<?php echo sprintf(_n('%d day ago', '%d days ago', $diff['days_ago'], 'phpinfo-wp'), $diff['days_ago']); ?>)</span>
                </div>
                <div class="phpinfowp-report-diff-metrics">
                    <?php if ($diff['score_delta'] > 0): ?>
                        <span class="phpinfowp-diff-pill phpinfowp-diff-pill-up" title="<?php esc_attr_e('Score improved', 'phpinfo-wp'); ?>">
                            ▲ +<?php echo (int) $diff['score_delta']; ?> pts (was <?php echo (int) $diff['prev_score']; ?>)
                        </span>
                    <?php elseif ($diff['score_delta'] < 0): ?>
                        <span class="phpinfowp-diff-pill phpinfowp-diff-pill-down" title="<?php esc_attr_e('Score dropped', 'phpinfo-wp'); ?>">
                            ▼ <?php echo (int) $diff['score_delta']; ?> pts (was <?php echo (int) $diff['prev_score']; ?>)
                        </span>
                    <?php else: ?>
                        <span class="phpinfowp-diff-pill phpinfowp-diff-pill-neutral">
                            = Score unchanged (<?php echo (int) $diff['prev_score']; ?>)
                        </span>
                    <?php endif; ?>

                    <?php if ($diff['crit_delta'] < 0): ?>
                        <span class="phpinfowp-diff-pill phpinfowp-diff-pill-up">
                            ✓ <?php echo abs($diff['crit_delta']); ?> critical resolved
                        </span>
                    <?php elseif ($diff['crit_delta'] > 0): ?>
                        <span class="phpinfowp-diff-pill phpinfowp-diff-pill-down">
                            ⚠ +<?php echo (int) $diff['crit_delta']; ?> new critical
                        </span>
                    <?php endif; ?>

                    <?php if ($diff['auto_delta'] !== 0): ?>
                        <span class="phpinfowp-diff-pill <?php echo $diff['auto_delta'] < 0 ? 'phpinfowp-diff-pill-up' : 'phpinfowp-diff-pill-neutral'; ?>">
                            <?php echo $diff['auto_delta'] < 0 ? '▼ ' . size_format(abs($diff['auto_delta'])) . ' autoload freed' : '▲ ' . size_format($diff['auto_delta']) . ' autoload growth'; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="phpinfowp-report-diff-bar" style="background:#f1f5f9; border-color:#e2e8f0; color:#64748b; font-size:12px;">
                <div class="phpinfowp-report-diff-title" style="color:#475569; font-weight:500;">
                    <span class="dashicons dashicons-flag" style="font-size:16px;width:16px;height:16px;color:#94a3b8;"></span>
                    <span><strong><?php _e('Baseline Audit Recorded', 'phpinfo-wp'); ?></strong> &mdash; <?php _e('Subsequent audits will automatically calculate score progression, resolved issues, and data footprint changes.', 'phpinfo-wp'); ?></span>
                </div>
            </div>
        <?php endif; ?>

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
                <div class="phpinfowp-report-overall-label"><?php _e('Overall Site Health', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-report-overall-verdict" style="color:<?php echo esc_attr($overall['verdict_color']); ?>"><?php echo esc_html($overall['verdict_label']); ?></div>
                <div class="phpinfowp-report-overall-grade"><?php _e('Grade', 'phpinfo-wp'); ?> <strong style="color:<?php echo esc_attr($overall['verdict_color']); ?>; font-size:17px;"><?php echo esc_html($overall['grade']); ?></strong></div>
                <?php if ($crit_n || $warn_n): ?>
                    <div class="phpinfowp-report-overall-counts">
                        <?php if ($crit_n): ?><span class="phpinfowp-rc phpinfowp-rc-crit"><?php echo $crit_n; ?> critical</span><?php endif; ?>
                        <?php if ($warn_n): ?><span class="phpinfowp-rc phpinfowp-rc-warn"><?php echo $warn_n; ?> warning<?php echo $warn_n === 1 ? '' : 's'; ?></span><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="phpinfowp-report-overall-counts"><span class="phpinfowp-rc phpinfowp-rc-ok"><?php _e('No critical issues', 'phpinfo-wp'); ?></span></div>
                <?php endif; ?>
                <p class="phpinfowp-report-overall-note"><?php _e('A weighted score across PHP config, security headers, OPcache, SSL, PHP support window, and database health. <strong>Aim for 85+.</strong>', 'phpinfo-wp'); ?></p>
            </div>
        </div>

        <!-- Executive 4-Pillar Business Risk Matrix -->
        <?php if (!empty($r['risk_matrix'])): ?>
            <div style="margin:24px 0 28px;">
                <h3 class="phpinfowp-report-h" style="margin-bottom:4px;"><?php _e('Executive Business Risk Summary', 'phpinfo-wp'); ?></h3>
                <p class="phpinfowp-report-h-sub" style="margin:0 0 14px; font-size:13px; color:#64748b;"><?php _e('Real-world consequences of current technical configurations across uptime, security, search visibility, and workflow continuity.', 'phpinfo-wp'); ?></p>
                <div class="phpinfowp-report-risk-grid">
                    <?php foreach ($r['risk_matrix'] as $pillar_key => $pillar):
                        $lvl = $pillar['level']; // 'low', 'moderate', 'high'
                    ?>
                        <div class="phpinfowp-report-risk-card risk-<?php echo esc_attr($lvl); ?>">
                            <div class="phpinfowp-risk-card-head">
                                <h4 class="phpinfowp-risk-card-title"><?php echo esc_html($pillar['title']); ?></h4>
                                <span class="phpinfowp-risk-badge phpinfowp-risk-badge-<?php echo esc_attr($lvl); ?>">
                                    <?php
                                    if ($lvl === 'high') {
                                        _e('Elevated Risk', 'phpinfo-wp');
                                    } elseif ($lvl === 'moderate') {
                                        _e('Moderate Risk', 'phpinfo-wp');
                                    } else {
                                        _e('Low Risk', 'phpinfo-wp');
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="phpinfowp-risk-card-summary">
                                <?php echo esc_html($pillar['summary']); ?>
                            </div>
                            <?php if (!empty($pillar['drivers'])): ?>
                                <ul class="phpinfowp-risk-drivers">
                                    <?php foreach ($pillar['drivers'] as $driver): ?>
                                        <li><?php echo esc_html($driver); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Category bar chart -->
        <?php if (!empty($r['bars'])): ?>
        <div class="phpinfowp-report-bars">
            <h3 class="phpinfowp-report-h"><?php _e('Subscores', 'phpinfo-wp'); ?></h3>
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

        <?php if ($is_free_preview): ?>
            <?php
            // Build dynamic preview items from real report data
            $preview_items = [];
            if (!empty($r['priorities'])) {
                foreach (array_slice($r['priorities'], 0, 3) as $p) {
                    $is_crit = ($p['urgency'] ?? '') === 'critical';
                    $preview_items[] = [
                        'title'    => $p['title'] ?? '',
                        'subtitle' => $p['reason'] ?? '',
                        'badge'    => $is_crit ? 'CRITICAL' : 'WARNING',
                        'border'   => $is_crit ? '#d63638' : '#dba617',
                        'bg'       => $is_crit ? '#fee2e2' : '#fefce8',
                        'fg'       => $is_crit ? '#dc2626' : '#a16207',
                    ];
                }
            } elseif (!empty($r['criticals']) || !empty($r['warnings'])) {
                $combined = array_merge($r['criticals'], $r['warnings']);
                foreach (array_slice($combined, 0, 3) as $c) {
                    $is_crit = ($c['urgency'] ?? '') === 'critical';
                    $preview_items[] = [
                        'title'    => $c['title'] ?? '',
                        'subtitle' => $c['reason'] ?? '',
                        'badge'    => $is_crit ? 'CRITICAL' : 'WARNING',
                        'border'   => $is_crit ? '#d63638' : '#dba617',
                        'bg'       => $is_crit ? '#fee2e2' : '#fefce8',
                        'fg'       => $is_crit ? '#dc2626' : '#a16207',
                    ];
                }
            } else {
                // Real live system runtime checks when no critical/warning issues are flagged
                $preview_items[] = [
                    'title'    => sprintf(__('PHP %s Runtime Environment', 'phpinfo-wp'), esc_html($r['php'])),
                    'subtitle' => !empty($r['eol']['eol']) ? sprintf(__('Official support window: %s • System Status Active', 'phpinfo-wp'), esc_html($r['eol']['eol'])) : __('Active Runtime Environment • System Status Active', 'phpinfo-wp'),
                    'badge'    => 'HEALTHY',
                    'border'   => '#00a32a',
                    'bg'       => '#dcfce7',
                    'fg'       => '#15803d',
                ];
                $preview_items[] = [
                    'title'    => sprintf(__('PHP Config Directives (Score %d/100, Grade %s)', 'phpinfo-wp'), (int) ($r['grader']['score'] ?? 0), esc_html($r['grader']['grade'] ?? 'A')),
                    'subtitle' => sprintf(__('Memory Limit: %s • Max Execution Time: %s', 'phpinfo-wp'), ini_get('memory_limit'), ini_get('max_execution_time') . 's'),
                    'badge'    => 'AUDITED',
                    'border'   => '#6366f1',
                    'bg'       => '#e0e7ff',
                    'fg'       => '#4338ca',
                ];
            }
            ?>
            <!-- Free preview: dynamic preview cards + centered upgrade card -->
            <div style="position:relative; margin-top:16px; overflow:hidden; border-radius:12px; min-height:380px; display:flex; align-items:center; justify-content:center;">

                <!-- Background dynamic preview cards -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; user-select:none; padding:12px; opacity:0.65; filter:blur(0.5px); display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($preview_items as $item): ?>
                        <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid <?php echo esc_attr($item['border']); ?>; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong style="color:#0f172a; font-size:13.5px;"><?php echo esc_html($item['title']); ?></strong>
                                <div style="font-size:12px; color:#64748b;"><?php echo esc_html($item['subtitle']); ?></div>
                            </div>
                            <span style="background:<?php echo esc_attr($item['bg']); ?>; color:<?php echo esc_attr($item['fg']); ?>; font-size:11px; font-weight:700; padding:2px 7px; border-radius:4px;"><?php echo esc_html($item['badge']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Gradient fade-out overlay -->
                <div style="position:absolute; top:0; bottom:0; left:0; right:0; background:linear-gradient(to bottom, rgba(248,250,252,0.3) 0%, rgba(248,250,252,0.94) 38%, #f8fafc 100%); pointer-events:none;"></div>

                <!-- Floating Upgrade Card -->
                <div style="position:relative; z-index:2; width:100%; max-width:540px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.09); text-align:center; margin:16px auto;">
                    <span class="dashicons dashicons-lock" style="font-size:36px; width:36px; height:36px; color:#6366f1; display:inline-block; margin-bottom:10px;"></span>
                    <h3 style="margin:0 0 8px; font-size:19px; font-weight:700; color:#0f172a;"><?php _e('Full Diagnostics & Action Items Locked', 'phpinfo-wp'); ?></h3>
                    <p style="margin:0 0 16px; font-size:13.5px; color:#475569; line-height:1.5;">
                        <?php _e('Unlock detailed issue breakdowns, security headers, SSL, OPcache, database health, and 1-click auto-fixes.', 'phpinfo-wp'); ?>
                    </p>
                    <div style="text-align:left; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin:0 0 20px; font-size:12.5px; color:#334155; line-height:1.6; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">📑</span>
                            <span><strong><?php _e('White-Label Client PDF Exports:', 'phpinfo-wp'); ?></strong> <?php _e('Generate client-ready health audits branded with your company name and logo.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">🛠️</span>
                            <span><strong><?php _e('1-Click Auto-Fix Engine:', 'phpinfo-wp'); ?></strong> <?php _e('Resolve memory limits, missing security headers, and OPcache directives instantly.', 'phpinfo-wp'); ?></span>
                        </div>
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="color:#6366f1; font-size:15px; line-height:1;">📊</span>
                            <span><strong><?php _e('Deep Subsystem Diagnostics:', 'phpinfo-wp'); ?></strong> <?php _e('Full audits across Database, Mail deliverability, Cron queue, and SSL configuration.', 'phpinfo-wp'); ?></span>
                        </div>
                    </div>
                    <a href="https://exeebit.com/phpinfo-wp#pricing" target="_blank" rel="noopener" class="button button-primary button-large" style="background:#6366f1; border-color:#6366f1; height:40px; line-height:38px; font-size:14px; font-weight:600; padding:0 26px; border-radius:6px; text-decoration:none; display:inline-block; box-shadow:0 2px 6px rgba(99,102,241,0.25);">
                        <?php _e('Upgrade to Pro &rarr;', 'phpinfo-wp'); ?>
                    </a>
                </div>
            </div>
        <?php else: // Pro users get the full detail HTML below ?>

        <!-- Critical issues banner -->
        <?php if ($crit_n): ?>
        <div class="phpinfowp-report-critbar">
            <div class="phpinfowp-report-critbar-head">
                <span class="phpinfowp-report-critbar-ic"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;display:block"><path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 01-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg></span>
                <div>
                    <div class="phpinfowp-report-critbar-title"><?php echo $crit_n; ?> critical issue<?php echo $crit_n === 1 ? '' : 's'; ?> need immediate attention</div>
                    <div class="phpinfowp-report-critbar-sub"><?php _e('Anything here is impacting security, performance, or breaks within the next 90 days.', 'phpinfo-wp'); ?></div>
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

        <!-- Priority Remediation Plan -->
        <?php if (!empty($r['priorities'])): ?>
        <h3 class="phpinfowp-report-h phpinfowp-report-h-page2"><?php _e('Priority Remediation Plan', 'phpinfo-wp'); ?></h3>
        <p class="phpinfowp-report-h-sub"><?php _e('Ordered by severity and operational impact. Each item outlines the root cause, business consequence, and recommended technical fix.', 'phpinfo-wp'); ?></p>
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
                            <strong><?php _e('Why it matters:', 'phpinfo-wp'); ?></strong>
                            <?php echo esc_html($p['reason']); ?>
                        </div>
                        <div class="phpinfowp-report-priority-how">
                            <strong><?php _e('How to fix:', 'phpinfo-wp'); ?></strong>
                            <?php echo wp_kses($p['how'], ['code' => [], 'strong' => [], 'em' => []]); ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php else: ?>
        <h3 class="phpinfowp-report-h"><?php _e('No Immediate Remediation Required', 'phpinfo-wp'); ?></h3>
        <p class="phpinfowp-report-h-sub"><?php _e('The site is in good health with no critical vulnerabilities or urgent misconfigurations detected.', 'phpinfo-wp'); ?></p>
        <?php endif; ?>

        <!-- Snapshot grid with verdict pills + plain-English captions -->
        <h3 class="phpinfowp-report-h"><?php _e('Snapshot', 'phpinfo-wp'); ?></h3>
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
                <div class="phpinfowp-report-stat-sub"><?php _e('Target 95% +', 'phpinfo-wp'); ?></div>
                <div class="phpinfowp-report-stat-cap"><?php echo esc_html(Phpinfo_WP_Report::plain_english('opcache_hit')); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Site configuration (new section) -->
        <?php $ex = $r['extras']; ?>
        <h3 class="phpinfowp-report-h"><?php _e('Site configuration', 'phpinfo-wp'); ?></h3>
        <table class="phpinfowp-report-table">
            <tbody>
                <tr>
                    <td style="width:40%"><?php _e('HTTPS', 'phpinfo-wp'); ?></td>
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
                    <td><?php _e('Pending updates', 'phpinfo-wp'); ?></td>
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
                    <td><?php _e('Backup plugin', 'phpinfo-wp'); ?></td>
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
                <thead><tr><th><?php _e('Directive', 'phpinfo-wp'); ?></th><th><?php _e('Current', 'phpinfo-wp'); ?></th><th><?php _e('Recommended', 'phpinfo-wp'); ?></th><th style="width:90px"><?php _e('Verdict', 'phpinfo-wp'); ?></th></tr></thead>
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
            <h3 class="phpinfowp-report-h"><?php _e('SSL certificates', 'phpinfo-wp'); ?></h3>
            <table class="phpinfowp-report-table">
                <thead><tr><th><?php _e('Host', 'phpinfo-wp'); ?></th><th><?php _e('Issuer', 'phpinfo-wp'); ?></th><th><?php _e('Expires', 'phpinfo-wp'); ?></th><th><?php _e('Status', 'phpinfo-wp'); ?></th></tr></thead>
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
            <h3 class="phpinfowp-report-h"><?php _e('Database', 'phpinfo-wp'); ?></h3>
            <table class="phpinfowp-report-table">
                <tbody>
                    <tr><td style="width:40%"><?php _e('Engine', 'phpinfo-wp'); ?></td><td><?php echo esc_html($r['db']['engine'] . ' ' . $r['db']['version']); ?></td></tr>
                    <tr><td><?php _e('End-of-life', 'phpinfo-wp'); ?></td><td><?php echo esc_html($r['db']['eol'] ?? '—'); ?></td></tr>
                    <tr><td><?php _e('Total size', 'phpinfo-wp'); ?></td><td><?php echo size_format($r['db_size']['total']); ?> across <?php echo (int) $r['db_size']['tables']; ?> tables</td></tr>
                    <tr>
                        <td><?php _e('Autoload data', 'phpinfo-wp'); ?></td>
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

        <!-- PHP Compatibility -->
        <?php if (!empty($r['compat']) && !isset($r['compat']['error'])):
            $target_v           = esc_html($r['compat']['target'] ?? '8.2');
            $compat_total       = (int) ($r['compat']['total'] ?? 0);
            $compat_removed     = (int) ($r['compat']['total_removed'] ?? 0);
            $compat_deprecated  = (int) ($r['compat']['total_deprecated'] ?? 0);
            $compat_with_issues = (int) ($r['compat']['with_issues'] ?? 0);
            $owners_scanned     = (int) ($r['compat']['owners'] ?? 0);
            if ($owners_scanned <= 0) {
                $owners_scanned = count((array) get_option('active_plugins', []));
            }
        ?>
            <h3 class="phpinfowp-report-h"><?php echo sprintf(__('PHP Compatibility & Upgrade Readiness (Target PHP %s)', 'phpinfo-wp'), $target_v); ?></h3>
            <table class="phpinfowp-report-table">
                <tbody>
                    <tr>
                        <td style="width:40%"><?php _e('Compatibility Scan Result', 'phpinfo-wp'); ?></td>
                        <td>
                            <?php if ($compat_removed > 0): ?>
                                <?php echo $verdict_pill('critical', sprintf(_n('%d Fatal Breaking Change', '%d Fatal Breaking Changes', $compat_removed, 'phpinfo-wp'), $compat_removed)); ?>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php echo sprintf(__('Detected across %d active component(s). These functions or syntax constructs will cause fatal errors on PHP %s.', 'phpinfo-wp'), $compat_with_issues, $target_v); ?>
                                </div>
                            <?php elseif ($compat_deprecated > 0): ?>
                                <?php echo $verdict_pill('warn', sprintf(_n('%d Deprecation Warning', '%d Deprecation Warnings', $compat_deprecated, 'phpinfo-wp'), $compat_deprecated)); ?>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php echo sprintf(__('Non-fatal notices detected across %d component(s). Safe to run on PHP %s, but should be updated.', 'phpinfo-wp'), $compat_with_issues, $target_v); ?>
                                </div>
                            <?php else: ?>
                                <?php echo $verdict_pill('ok', __('Fully Compatible', 'phpinfo-wp')); ?>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php _e('Zero syntax errors or deprecated functions detected for target PHP version.', 'phpinfo-wp'); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Audit Scope & Coverage', 'phpinfo-wp'); ?></td>
                        <td>
                            <strong><?php echo sprintf(__('%d active plugins & active theme scanned', 'phpinfo-wp'), $owners_scanned); ?></strong>
                            <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                <?php echo sprintf(
                                    __('Breaking changes: %d fatal · Deprecations: %d notices · Target runtime: PHP %s', 'phpinfo-wp'),
                                    $compat_removed,
                                    $compat_deprecated,
                                    $target_v
                                ); ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Upgrade Feasibility Assessment', 'phpinfo-wp'); ?></td>
                        <td>
                            <?php if ($compat_removed > 0): ?>
                                <span style="color:#b91c1c; font-weight:700;"><?php _e('Upgrade Blocked (Action Required)', 'phpinfo-wp'); ?></span>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php echo sprintf(__('Do not upgrade your hosting environment to PHP %s until the flagged plugins or theme functions are resolved or replaced.', 'phpinfo-wp'), $target_v); ?>
                                </div>
                            <?php elseif ($compat_deprecated > 0): ?>
                                <span style="color:#b45309; font-weight:700;"><?php _e('Safe with Warnings', 'phpinfo-wp'); ?></span>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php echo sprintf(__('Site can safely run on PHP %s without fatal crashes, provided WP_DEBUG_DISPLAY is disabled.', 'phpinfo-wp'), $target_v); ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#15803d; font-weight:700;"><?php _e('Safe to Upgrade ✓', 'phpinfo-wp'); ?></span>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php echo sprintf(__('The active codebase is verified clean. Your web host PHP version can be safely upgraded to PHP %s with no breaking incompatibilities.', 'phpinfo-wp'), $target_v); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- REST API & Attack Surface Audit -->
        <?php if (!empty($r['rest_api'])):
            $rest = $r['rest_api'];
            $user_enum_pill = $rest['user_enum_exposed']
                ? $verdict_pill('critical', __('Exposed to Public', 'phpinfo-wp'))
                : $verdict_pill('ok', __('Protected / Hidden', 'phpinfo-wp'));
        ?>
            <h3 class="phpinfowp-report-h"><?php _e('REST API & Attack Surface Audit', 'phpinfo-wp'); ?></h3>
            <table class="phpinfowp-report-table">
                <tbody>
                    <tr>
                        <td style="width:40%"><?php _e('User Enumeration (/wp/v2/users)', 'phpinfo-wp'); ?></td>
                        <td>
                            <?php echo $user_enum_pill; ?>
                            <?php if ($rest['user_enum_exposed']): ?>
                                <div class="phpinfowp-report-inline-cap" style="color:#b91c1c; margin-top:4px;">
                                    <?php _e('Unauthenticated visitors can list all author usernames, exposing targets for password brute-force.', 'phpinfo-wp'); ?>
                                </div>
                            <?php else: ?>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php _e('Public user enumeration endpoint is restricted or requires authentication.', 'phpinfo-wp'); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Registered REST Routes', 'phpinfo-wp'); ?></td>
                        <td>
                            <strong><?php echo (int) $rest['total_routes']; ?></strong> <?php _e('total routes', 'phpinfo-wp'); ?>
                            <span class="phpinfowp-report-inline-cap">(<?php echo (int) $rest['custom_routes']; ?> <?php _e('from third-party plugins/themes', 'phpinfo-wp'); ?>)</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if (!empty($rest['public_custom'])): ?>
                <div style="margin-top:12px;">
                    <div style="font-size:12.5px; font-weight:600; color:#334155; margin-bottom:6px;">
                        <?php echo sprintf(__('%d Custom REST Endpoints With Public or Missing Permission Callbacks:', 'phpinfo-wp'), count($rest['public_custom'])); ?>
                    </div>
                    <table class="phpinfowp-report-table" style="font-size:12.5px;">
                        <thead>
                            <tr>
                                <th><?php _e('Route', 'phpinfo-wp'); ?></th>
                                <th style="width:110px"><?php _e('Methods', 'phpinfo-wp'); ?></th>
                                <th style="width:220px"><?php _e('Access / Exposure', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rest['public_custom'] as $ep): ?>
                                <tr>
                                    <td><code><?php echo esc_html($ep['route']); ?></code></td>
                                    <td><span class="phpinfowp-report-tag"><?php echo esc_html($ep['methods']); ?></span></td>
                                    <td>
                                        <span class="phpinfowp-report-tag phpinfowp-report-tag-danger">
                                            <?php echo esc_html($ep['reason']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Cron Execution & Background Scheduler -->
        <?php if (!empty($r['cron_diag'])):
            $cd = $r['cron_diag'];
            $cron_mode = $cd['disable_wp_cron']
                ? __('Server Crontab (DISABLE_WP_CRON active)', 'phpinfo-wp')
                : __('Default Web-Traffic Triggered WP-Cron', 'phpinfo-wp');
        ?>
            <h3 class="phpinfowp-report-h"><?php _e('Cron Scheduler & Background Queue', 'phpinfo-wp'); ?></h3>
            <table class="phpinfowp-report-table">
                <tbody>
                    <tr>
                        <td style="width:40%"><?php _e('Execution Mode', 'phpinfo-wp'); ?></td>
                        <td>
                            <strong><?php echo esc_html($cron_mode); ?></strong>
                            <?php if ($cd['disable_wp_cron']): ?>
                                <?php echo $verdict_pill('ok', 'System Crontab'); ?>
                            <?php else: ?>
                                <?php echo $verdict_pill('warn', 'Web-Traffic Dependent'); ?>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php _e('WP-Cron only triggers when visitors browse the site. On low-traffic sites, scheduled tasks stall.', 'phpinfo-wp'); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Active Queue Count', 'phpinfo-wp'); ?></td>
                        <td>
                            <strong><?php echo (int) $cd['total_events']; ?></strong> <?php _e('scheduled events registered', 'phpinfo-wp'); ?>
                        </td>
                    </tr>
                    <?php if (!empty($r['cron']['orphan'])): ?>
                    <tr>
                        <td><?php _e('Orphaned Scheduled Hooks', 'phpinfo-wp'); ?></td>
                        <td>
                            <?php echo $verdict_pill('warn', sprintf(_n('%d orphan hook', '%d orphan hooks', $r['cron']['orphan'], 'phpinfo-wp'), $r['cron']['orphan'])); ?>
                            <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                <?php echo esc_html(Phpinfo_WP_Report::plain_english('orphan_cron')); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><?php _e('Overdue Tasks (>15 min)', 'phpinfo-wp'); ?></td>
                        <td>
                            <?php if ($cd['overdue_count'] > 0): ?>
                                <?php echo $verdict_pill('critical', sprintf(_n('%d task overdue', '%d tasks overdue', $cd['overdue_count'], 'phpinfo-wp'), $cd['overdue_count'])); ?>
                                <?php if ($cd['severe_count'] > 0): ?>
                                    <span class="phpinfowp-report-tag phpinfowp-report-tag-danger" style="margin-left:6px;">
                                        <?php echo sprintf(__('%d delayed > 1 hour', 'phpinfo-wp'), $cd['severe_count']); ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php echo $verdict_pill('ok', __('All tasks running on schedule', 'phpinfo-wp')); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if (!empty($cd['overdue_events'])): ?>
                <div style="margin-top:12px;">
                    <div style="font-size:12.5px; font-weight:600; color:#334155; margin-bottom:6px;">
                        <?php _e('Stalled Background Tasks:', 'phpinfo-wp'); ?>
                    </div>
                    <table class="phpinfowp-report-table" style="font-size:12.5px;">
                        <thead>
                            <tr>
                                <th><?php _e('Hook Name', 'phpinfo-wp'); ?></th>
                                <th style="width:140px"><?php _e('Due Since', 'phpinfo-wp'); ?></th>
                                <th style="width:100px"><?php _e('Status', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cd['overdue_events'] as $ev): ?>
                                <tr>
                                    <td><code><?php echo esc_html($ev['hook']); ?></code></td>
                                    <td><?php echo esc_html($ev['due_ago']); ?> ago</td>
                                    <td>
                                        <?php echo $verdict_pill($ev['severely'] ? 'critical' : 'warn', $ev['severely'] ? 'Severe Delay' : 'Delayed'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Database Architecture & Meta Bloat -->
        <?php if (!empty($r['db_bloat'])):
            $dbb = $r['db_bloat'];
            $ratio_v = $dbb['ratio'] > 80 ? 'critical' : ($dbb['ratio'] > 40 ? 'warn' : 'ok');
        ?>
            <h3 class="phpinfowp-report-h"><?php _e('Database Architecture & Meta Bloat', 'phpinfo-wp'); ?></h3>
            <table class="phpinfowp-report-table">
                <tbody>
                    <tr>
                        <td style="width:40%"><?php _e('Postmeta-to-Post Ratio', 'phpinfo-wp'); ?></td>
                        <td>
                            <strong><?php echo (float) $dbb['ratio']; ?></strong> <?php _e('meta entries / post', 'phpinfo-wp'); ?>
                            <?php echo $verdict_pill($ratio_v, $ratio_v === 'ok' ? 'Lean (Healthy)' : ($ratio_v === 'warn' ? 'Elevated' : 'Heavy Bloat')); ?>
                            <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                <?php echo sprintf(__('%s total postmeta rows across %s total posts. Ratios above 50 significantly slow WP_Query lookups.', 'phpinfo-wp'), number_format_i18n($dbb['postmeta']), number_format_i18n($dbb['posts'])); ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Storage Engine Architecture', 'phpinfo-wp'); ?></td>
                        <td>
                            <?php if (empty($dbb['myisam_tables'])): ?>
                                <?php echo $verdict_pill('ok', __('Modern InnoDB (All Tables)', 'phpinfo-wp')); ?>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php _e('All audited database tables utilize row-level locking with crash recovery support.', 'phpinfo-wp'); ?>
                                </div>
                            <?php else: ?>
                                <?php echo $verdict_pill('critical', sprintf(__('%d Legacy MyISAM Tables Detected', 'phpinfo-wp'), count($dbb['myisam_tables']))); ?>
                                <div class="phpinfowp-report-inline-cap" style="color:#b91c1c; margin-top:4px;">
                                    <?php _e('MyISAM tables use whole-table locking during writes and risk data corruption upon unexpected server restarts.', 'phpinfo-wp'); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if (!empty($dbb['myisam_tables'])): ?>
                <div style="margin-top:12px;">
                    <div style="font-size:12.5px; font-weight:600; color:#334155; margin-bottom:6px;">
                        <?php _e('Legacy MyISAM Tables Requiring Conversion to InnoDB:', 'phpinfo-wp'); ?>
                    </div>
                    <table class="phpinfowp-report-table" style="font-size:12.5px;">
                        <thead>
                            <tr>
                                <th><?php _e('Table Name', 'phpinfo-wp'); ?></th>
                                <th style="width:100px"><?php _e('Rows', 'phpinfo-wp'); ?></th>
                                <th style="width:110px"><?php _e('Size', 'phpinfo-wp'); ?></th>
                                <th style="width:100px"><?php _e('Recommendation', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dbb['myisam_tables'] as $tbl): ?>
                                <tr>
                                    <td><code><?php echo esc_html($tbl['name']); ?></code></td>
                                    <td><?php echo number_format_i18n((int) $tbl['rows']); ?></td>
                                    <td><?php echo size_format((int) $tbl['bytes']); ?></td>
                                    <td><span class="phpinfowp-report-tag phpinfowp-report-tag-danger">ALTER ENGINE=InnoDB</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($dbb['top_autoload'])): ?>
                <div style="margin-top:12px;">
                    <div style="font-size:12.5px; font-weight:600; color:#334155; margin-bottom:6px;">
                        <?php _e('Top 5 Largest Autoload Options (Loaded on Every Page Request):', 'phpinfo-wp'); ?>
                    </div>
                    <table class="phpinfowp-report-table" style="font-size:12.5px;">
                        <thead>
                            <tr>
                                <th><?php _e('Option Name', 'phpinfo-wp'); ?></th>
                                <th style="width:120px"><?php _e('Data Size', 'phpinfo-wp'); ?></th>
                                <th style="width:100px"><?php _e('Impact', 'phpinfo-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dbb['top_autoload'] as $opt):
                                $bytes = (int) $opt['length_bytes'];
                                $opt_v = $bytes > 200 * 1024 ? 'critical' : ($bytes > 80 * 1024 ? 'warn' : 'ok');
                            ?>
                                <tr>
                                    <td><code><?php echo esc_html($opt['option_name']); ?></code></td>
                                    <td><strong><?php echo size_format($bytes); ?></strong></td>
                                    <td>
                                        <?php echo $verdict_pill($opt_v, $opt_v === 'ok' ? 'Normal' : ($opt_v === 'warn' ? 'Heavy' : 'Bloat')); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Server Loopback & Network Connectivity -->
        <?php if (!empty($r['network'])):
            $net = $r['network'];
            $loop_v = !$net['loopback_ok'] ? 'critical' : (($net['loopback_latency_ms'] ?? 0) > 400 ? 'warn' : 'ok');
            $out_v  = $net['outbound_ok'] ? 'ok' : 'critical';
        ?>
            <h3 class="phpinfowp-report-h"><?php _e('Server Loopback & Network Connectivity', 'phpinfo-wp'); ?></h3>
            <table class="phpinfowp-report-table">
                <tbody>
                    <tr>
                        <td style="width:40%"><?php _e('Local Loopback Request', 'phpinfo-wp'); ?></td>
                        <td>
                            <?php if ($net['loopback_ok']): ?>
                                <strong><?php echo (float) $net['loopback_latency_ms']; ?> ms</strong>
                                <?php echo $verdict_pill($loop_v, $loop_v === 'ok' ? 'Fast Response' : 'Slow Loopback'); ?>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php _e('Measures server internal latency to admin-ajax.php. Essential for background jobs and cron reliability.', 'phpinfo-wp'); ?>
                                </div>
                            <?php else: ?>
                                <?php echo $verdict_pill('critical', __('Loopback Blocked', 'phpinfo-wp')); ?>
                                <div class="phpinfowp-report-inline-cap" style="color:#b91c1c; margin-top:4px;">
                                    <?php echo sprintf(__('Loopback connection failed (%s). WP-Cron and background processes cannot execute.', 'phpinfo-wp'), esc_html($net['loopback_error'] ?? 'Unknown error')); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Outbound WordPress.org HTTPS', 'phpinfo-wp'); ?></td>
                        <td>
                            <?php if ($net['outbound_ok']): ?>
                                <strong><?php echo (float) $net['outbound_latency_ms']; ?> ms</strong>
                                <?php echo $verdict_pill('ok', __('Connected', 'phpinfo-wp')); ?>
                                <div class="phpinfowp-report-inline-cap" style="margin-top:4px;">
                                    <?php _e('Outbound TLS handshake successful. Core, plugin, and security update feeds are operating normally.', 'phpinfo-wp'); ?>
                                </div>
                            <?php else: ?>
                                <?php echo $verdict_pill('critical', __('Outbound Blocked', 'phpinfo-wp')); ?>
                                <div class="phpinfowp-report-inline-cap" style="color:#b91c1c; margin-top:4px;">
                                    <?php _e('Unable to establish HTTPS handshake with WordPress.org. Updates and licensing checks will fail.', 'phpinfo-wp'); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('cURL & TLS Stack', 'phpinfo-wp'); ?></td>
                        <td>
                            <code>cURL <?php echo esc_html($net['curl_version']); ?></code> &middot;
                            <code><?php echo esc_html($net['ssl_version']); ?></code> &middot;
                            <span class="phpinfowp-report-tag phpinfowp-report-tag-success"><?php echo esc_html($net['http_version']); ?></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <?php endif; // end Pro-only detail block ?>


        <div class="phpinfowp-report-footer">
            <?php if ($b['enabled'] && $b['footer_note']): ?>
                <?php echo nl2br(esc_html($b['footer_note'])); ?>
            <?php else: ?>
                Report generated by <strong><?php echo $brand_name; ?></strong> &middot; <?php echo esc_html(get_site_url()); ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
(function() {
    var rebuildBtn = document.getElementById('phpinfowp-report-rebuild-btn');
    if (rebuildBtn) {
        rebuildBtn.addEventListener('click', function(e) {
            e.preventDefault();
            rebuildBtn.disabled = true;
            rebuildBtn.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin" style="vertical-align:middle; font-size:15px; width:15px; height:15px; margin-top:-1px;"></span> <?php echo esc_js(__('Re-generating...', 'phpinfo-wp')); ?>';

            if (window.phpinfowpShowScanBanner) {
                window.phpinfowpShowScanBanner('<?php echo esc_js(__('Compiling Executive Report...', 'phpinfo-wp')); ?>', '<?php echo esc_js(__('Compiling diagnostics across all subsystems — you can leave anytime', 'phpinfo-wp')); ?>');
            }

            var data = new FormData();
            data.append('action', 'phpinfowp_report_build');
            data.append('nonce', '<?php echo esc_js(wp_create_nonce('phpinfowp_report_nonce')); ?>');

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function() { window.location.reload(); })
                .catch(function() { window.location.reload(); });
        });
    }

    // Clean up rebuild query params from browser address bar if present
    if (window.location.search.indexOf('rebuild=1') !== -1) {
        var cleanUrl = window.location.href.replace(/([&?]rebuild=1(&_wpnonce=[^&]*)?)/, '');
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }
})();
</script>
