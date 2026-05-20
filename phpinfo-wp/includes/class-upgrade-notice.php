<?php
defined('ABSPATH') or die('Unauthorized Access');

// Plugins-screen upgrade notice. WordPress shows the stock
// "There is a new version available. View details or update now."
// line; this hook appends a marketing block underneath it that
// explains what's actually new in the upcoming release — same UX
// pattern Elementor, WooCommerce, and Yoast use to make people
// actually click Update.
//
// To refresh the copy each release, edit highlights_for() below.

class Phpinfo_WP_Upgrade_Notice {

    public static function register(): void {
        add_action(
            'in_plugin_update_message-' . plugin_basename(PHPINFOWP_DIR . 'phpinfo-wp.php'),
            [self::class, 'render'],
            10, 2
        );
    }

    // $plugin_data — current installed plugin headers.
    // $response    — WP_Plugin_Update object with ->new_version, ->url, etc.
    public static function render(array $plugin_data, object $response): void {
        $new_version = isset($response->new_version) ? (string) $response->new_version : '';
        $highlights  = self::highlights_for($new_version);
        if (!$highlights) return;

        // The hook output is injected inside the stock <p> WP wraps the
        // "View details / update now" sentence in. Close that <p> so our
        // block sits as a sibling, then reopen one so WP's closing tag
        // stays balanced. Without this dance the markup nests badly and
        // breaks the row layout under the default admin theme.
        ?>
        </p>
        <div class="phpinfowp-upgrade-callout" style="
            margin: 8px 0 4px;
            padding: 14px 16px 12px;
            background: linear-gradient(135deg, #f3f4ff 0%, #eaf0ff 100%);
            border-left: 4px solid #777BB3;
            border-radius: 0 4px 4px 0;
            color: #1d2327;
            font-weight: 400;
            line-height: 1.55;
        ">
            <div style="font-size:13.5px;font-weight:600;color:#1a3a72;margin-bottom:6px">
                <span style="display:inline-block;background:#777BB3;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;letter-spacing:.4px;vertical-align:middle;margin-right:8px">
                    v<?php echo esc_html($new_version); ?>
                </span>
                <?php echo esc_html($highlights['headline']); ?>
            </div>
            <ul style="margin:0 0 0 18px;padding:0;font-size:12.5px;color:#3a567c;list-style:disc">
                <?php foreach ($highlights['bullets'] as $b): ?>
                    <li style="margin:2px 0"><?php echo wp_kses(
                        $b,
                        [
                            'strong' => [],
                            'code'   => [],
                            'a'      => ['href' => [], 'target' => [], 'rel' => []],
                        ]
                    ); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($highlights['cta_url'])): ?>
                <div style="margin-top:8px">
                    <a href="<?php echo esc_url($highlights['cta_url']); ?>"
                       target="_blank" rel="noopener"
                       style="font-size:12px;font-weight:600;color:#777BB3;text-decoration:none">
                        <?php echo esc_html($highlights['cta_label'] ?? 'See full changelog'); ?> &rarr;
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <p style="display:none">
        <?php
    }

    // Per-version copy. Latest release first; older entries can stay so
    // anyone updating across multiple versions sees their relevant block.
    private static function highlights_for(string $new_version): array {
        $catalog = [
            '7.0.0' => [
                'headline' => 'Major release — full WordPress site-health & server-audit suite, WP 7.0 ready.',
                'bullets'  => [
                    '<strong>New free tools:</strong> PHP EOL Timeline, Config Grader summary, Troubleshooting Mode, PHP Compatibility Scanner, admin-bar health scoreboard, dashboard widget.',
                    '<strong>New Pro tools:</strong> one-click Config Auto-Fix, Security Headers, SSL Monitor, OPcache dashboard, Error Log viewer, white-label PDF audit reports, email alerts.',
                    '<strong>WordPress 7.0 integration:</strong> exposes 6 audit abilities via the new Abilities API for AI assistants, and adds an <code>Explain with AI</code> button on failing Config Grader checks (uses core AI Client + Connectors API — no keys handled here).',
                    'Repositioned as a maintained alternative to the abandoned Health Check & Troubleshooting plugin.',
                ],
                'cta_url'   => 'https://exeebit.com/phpinfo-wp',
                'cta_label' => 'See what\'s new',
            ],
        ];

        if (isset($catalog[$new_version])) return $catalog[$new_version];

        // For any future version we haven't written copy for yet, return
        // empty so WordPress just shows its default message — no broken
        // placeholder.
        return [];
    }
}
