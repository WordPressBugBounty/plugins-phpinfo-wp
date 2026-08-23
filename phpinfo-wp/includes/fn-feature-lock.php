<?php
defined('ABSPATH') or die('Unauthorized Access');

/**
 * Inline feature-lock teaser.
 *
 * Usage — replace the hard redirect at the top of any Pro view with:
 *
 *   if (!Phpinfo_WP_License::is_valid()) {
 *       phpinfowp_render_feature_lock([
 *           'feature'  => 'OPcache Dashboard',
 *           'icon'     => 'dashicons-performance',
 *           'tagline'  => 'Hit rate, memory usage, cached scripts, one-click clear.',
 *           'previews' => [
 *               'Hit rate: <strong>—</strong>',
 *               'Memory used: <strong>—</strong>',
 *               'Cached scripts: <strong>—</strong>',
 *           ],
 *       ]);
 *       return;
 *   }
 *
 * The function is defined once and guarded so it can be required from
 * multiple views without a fatal redeclaration error.
 */

if (!function_exists('phpinfowp_render_feature_lock')):

function phpinfowp_render_feature_lock(array $args = []): void {
    $feature  = $args['feature']  ?? 'This feature';
    $icon     = $args['icon']     ?? 'dashicons-lock';
    $tagline  = $args['tagline']  ?? 'Available in phpinfo() WP Pro.';
    $previews = $args['previews'] ?? [];
    $buy_url  = 'https://exeebit.com/phpinfo-wp#pricing';
    $lic_url  = admin_url('admin.php?page=piwp-license');
    ?>
    <div class="phpinfowp-pro-page">
        <div style="
            max-width: 560px;
            margin: 48px auto;
            text-align: center;
            padding: 40px 32px;
            background: #fff;
            border: 1px solid #dde0e4;
            border-top: 4px solid #777BB3;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        ">
            <!-- Icon + lock badge -->
            <div style="position:relative;display:inline-block;margin-bottom:20px;">
                <span class="dashicons <?php echo esc_attr($icon); ?>"
                      style="font-size:48px;width:48px;height:48px;color:#777BB3;opacity:.9;"></span>
                <span style="
                    position:absolute;bottom:-4px;right:-10px;
                    background:#1d2327;color:#fff;
                    font-size:9px;font-weight:700;letter-spacing:.5px;
                    padding:2px 5px;border-radius:3px;
                    line-height:1.5;
                "><?php _e('PRO', 'piwp'); ?></span>
            </div>

            <h2 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#1d2327;">
                <?php echo esc_html($feature); ?>
            </h2>
            <p style="margin:0 0 24px;font-size:14px;color:#555;line-height:1.55;">
                <?php echo esc_html($tagline); ?>
            </p>

            <?php if (!empty($previews)): ?>
            <!-- Skeleton data preview -->
            <div style="
                background:#f6f7f7;
                border:1px solid #e5e7ea;
                border-radius:4px;
                padding:16px 20px;
                margin-bottom:24px;
                user-select:none;
                pointer-events:none;
            " aria-hidden="true">
                <?php 
                $skel_widths = [ ['50%', '20%'], ['65%', '15%'], ['40%', '25%'], ['55%', '20%'] ];
                $i = 0;
                $count = count($previews);
                foreach ($previews as $row): 
                    $w1 = $skel_widths[$i % 4][0];
                    $w2 = $skel_widths[$i % 4][1];
                    $b_bot = (++$i === $count) ? 'none' : '1px solid #eee';
                ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:<?php echo $b_bot; ?>;">
                    <div style="height:11px; width:<?php echo $w1; ?>; background:#cbd5e1; border-radius:4px;"></div>
                    <div style="height:11px; width:<?php echo $w2; ?>; background:#cbd5e1; border-radius:4px;"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <p style="margin:-16px 0 20px;font-size:12px;color:#888;font-style:italic;">
                ↑ Sample data — unlock to see your real numbers
            </p>
            <?php endif; ?>

            <!-- CTAs -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:10px;">
                <a href="<?php echo esc_url($buy_url); ?>" target="_blank" rel="noopener"
                   style="
                       display:inline-block;width:100%;max-width:280px;
                       background:#777BB3;color:#fff;
                       padding:11px 24px;border-radius:4px;
                       font-size:14px;font-weight:600;text-decoration:none;
                       box-sizing:border-box;
                   ">
                    Unlock <?php echo esc_html($feature); ?> — from $29/yr
                </a>
                <a href="<?php echo esc_url($lic_url); ?>"
                   style="font-size:12.5px;color:#777;text-decoration:underline;">
                    I already have a license
                </a>
            </div>

            <p style="margin:20px 0 0;font-size:11.5px;color:#aaa;">
                14-day refund · Instant delivery · All Pro features included
            </p>
        </div>
    </div>
    <?php
}

endif;
