<?php
defined('ABSPATH') or die('Unauthorized Access');

class Phpinfo_WP_Report {

    const OPT_BRANDING = 'phpinfowp_report_branding';

    private static function _pro(): bool { return Phpinfo_WP_License::is_valid(); }

    // ─────────────────────────────────────────────────────────────────
    //  Branding (white-label)
    // ─────────────────────────────────────────────────────────────────

    public static function get_branding(): array {
        return wp_parse_args(get_option(self::OPT_BRANDING, []), [
            'enabled'     => false,
            'company'     => '',
            'tagline'     => '',
            'footer_note' => '',
            'accent'      => '#777BB3',
            'logo_url'    => '',
            'logo_id'     => 0,
        ]);
    }

    public static function save_branding(array $b): void {
        if (!self::_pro()) return;
        $accent = trim((string)($b['accent'] ?? ''));
        if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $accent)) $accent = '#777BB3';

        // Resolve a logo: prefer the attachment_id (re-derives URL each time
        // so a moved/CDN-routed media file still points at the right asset).
        // Fall back to a raw URL if the user pasted one and we have no ID.
        $logo_id  = (int) ($b['logo_id'] ?? 0);
        $logo_url = esc_url_raw(trim((string) ($b['logo_url'] ?? '')));
        if ($logo_id > 0) {
            $resolved = wp_get_attachment_image_url($logo_id, 'medium');
            if ($resolved) $logo_url = $resolved;
            else $logo_id = 0; // attachment got deleted — drop the stale id
        }

        update_option(self::OPT_BRANDING, [
            'enabled'     => !empty($b['enabled']),
            'company'     => sanitize_text_field($b['company'] ?? ''),
            'tagline'     => sanitize_text_field($b['tagline'] ?? ''),
            'footer_note' => sanitize_textarea_field($b['footer_note'] ?? ''),
            'accent'      => $accent,
            'logo_url'    => $logo_url,
            'logo_id'     => $logo_id,
        ], false);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Main report build — aggregates every subsystem + scores + priorities
    // ─────────────────────────────────────────────────────────────────

    public static function build(): array {
        if (!self::_pro()) return [];

        $eol      = Phpinfo_WP_EOL::status();
        $grader   = Phpinfo_WP_Config_Grader::run();
        $headers  = Phpinfo_WP_Security_Headers::get_cached();
        $ssl      = Phpinfo_WP_SSL::check_all();
        $opcache  = Phpinfo_WP_OPcache::is_available() ? Phpinfo_WP_OPcache::status() : null;
        $db       = Phpinfo_WP_DB_Health::server_info();
        $autoload = Phpinfo_WP_DB_Health::autoload_size();
        $db_size  = Phpinfo_WP_DB_Health::db_size();
        $cron     = Phpinfo_WP_Cron_Monitor::summary();
        $compat   = Phpinfo_WP_Compat::get_result();

        // Per-category subscores (each 0-100, with null = not applicable so
        // we can recompute weights instead of penalising for missing data).
        $sub = [
            'config'     => isset($grader['score'])  ? (int) $grader['score']  : null,
            'headers'    => !isset($headers['error']) && isset($headers['score']) ? (int) $headers['score'] : null,
            'php_eol'    => self::eol_score($eol),
            'db_eol'     => $db ? self::db_eol_score($db) : null,
            'opcache'    => $opcache ? self::opcache_score($opcache) : null,
            'ssl'        => $ssl ? self::ssl_score($ssl) : null,
            'cron'       => $cron ? self::cron_score($cron) : null,
        ];

        $weights = [
            'config'  => 30,
            'headers' => 20,
            'php_eol' => 15,
            'db_eol'  => 10,
            'opcache' => 10,
            'ssl'     => 10,
            'cron'    => 5,
        ];

        $overall_score = self::weighted_average($sub, $weights);
        [$verdict, $verdict_label, $verdict_color] = self::score_to_verdict($overall_score);

        // Bar chart data (just the named categories the user expects on a card)
        $bars = [];
        if ($sub['config']  !== null) $bars[] = ['label' => 'PHP Config',       'score' => $sub['config']];
        if ($sub['headers'] !== null) $bars[] = ['label' => 'Security Headers', 'score' => $sub['headers']];
        if ($sub['opcache'] !== null) $bars[] = ['label' => 'OPcache',          'score' => $sub['opcache']];
        if ($sub['php_eol'] !== null) $bars[] = ['label' => 'PHP Support',      'score' => $sub['php_eol']];
        if ($sub['ssl']     !== null) $bars[] = ['label' => 'SSL',              'score' => $sub['ssl']];
        if ($sub['db_eol']  !== null) $bars[] = ['label' => 'Database Support', 'score' => $sub['db_eol']];

        // Extra free-tier-flavored checks the user asked for explicitly
        $extras = [
            'wp_debug'    => self::wp_debug_state(),
            'https'       => self::https_state(),
            'updates'     => self::updates_state(),
            'backup'      => self::detect_backup(),
            'permissions' => self::permission_state(),
        ];

        // Compute issues — anything ≥ critical surfaces in the banner;
        // critical + warning together feed the "Top 3 Actions" picker.
        $issues = self::collect_issues($eol, $grader, $headers, $ssl, $opcache, $db, $autoload, $cron, $extras);
        $criticals  = array_values(array_filter($issues, function ($i) {
            return $i['urgency'] === 'critical';
        }));
        $warnings   = array_values(array_filter($issues, function ($i) {
            return $i['urgency'] === 'warning';
        }));
        $priorities = self::prioritize($issues, 3);

        return [
            'site'         => get_bloginfo('name'),
            'url'          => get_site_url(),
            'generated_at' => time(),
            'php'          => PHP_VERSION,
            'wp'           => get_bloginfo('version'),
            'overall'      => [
                'score'         => $overall_score,
                'grade'         => self::score_to_grade($overall_score),
                'verdict'       => $verdict,
                'verdict_label' => $verdict_label,
                'verdict_color' => $verdict_color,
            ],
            'bars'         => $bars,
            'criticals'    => $criticals,
            'warnings'     => $warnings,
            'priorities'   => $priorities,
            'extras'       => $extras,
            // Raw subsystem data (kept for the existing report sections)
            'eol'          => $eol,
            'grader'       => $grader,
            'headers'      => $headers,
            'ssl'          => $ssl,
            'opcache'      => $opcache,
            'db'           => $db,
            'autoload'     => $autoload,
            'db_size'      => $db_size,
            'cron'         => $cron,
            'compat'       => $compat,
            'branding'     => self::get_branding(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Scoring helpers
    // ─────────────────────────────────────────────────────────────────

    public static function score_to_grade(int $s): string {
        if ($s >= 95) return 'A+';
        if ($s >= 85) return 'A';
        if ($s >= 75) return 'B';
        if ($s >= 60) return 'C';
        if ($s >= 45) return 'D';
        return 'F';
    }

    /** @return array{0:string,1:string,2:string} verdict, label, hex color */
    public static function score_to_verdict(int $s): array {
        if ($s >= 85) return ['ok',       'Excellent',       '#00a32a'];
        if ($s >= 70) return ['ok',       'Good',            '#5e9b1e'];
        if ($s >= 50) return ['warn',     'Needs attention', '#dba617'];
        return                ['critical', 'Critical',        '#d63638'];
    }

    private static function weighted_average(array $scores, array $weights): int {
        $sum_w = 0;
        $sum   = 0;
        foreach ($weights as $key => $w) {
            if (!isset($scores[$key]) || $scores[$key] === null) continue;
            $sum_w += $w;
            $sum   += $scores[$key] * $w;
        }
        if ($sum_w === 0) return 0;
        return (int) round($sum / $sum_w);
    }

    private static function eol_score(array $eol): ?int {
        if (!isset($eol['status'])) return null;
        if ($eol['status'] === 'eol')     return 0;
        if ($eol['status'] === 'unknown') return null;
        $days = (int) ($eol['days'] ?? 0);
        if ($days < 90)  return 25;
        if ($days < 180) return 55;
        if ($days < 365) return 80;
        return 100;
    }

    private static function db_eol_score(array $db): ?int {
        if (empty($db['eol'])) return null;
        $eol_ts = strtotime($db['eol']);
        if (!$eol_ts) return null;
        $days = (int) floor(($eol_ts - time()) / DAY_IN_SECONDS);
        if ($days < 0)    return 0;
        if ($days < 90)   return 25;
        if ($days < 180)  return 55;
        if ($days < 365)  return 80;
        return 100;
    }

    private static function opcache_score(array $op): int {
        if (!($op['enabled'] ?? false)) return 0;
        $rate = (float) ($op['hit_rate'] ?? 0);
        if ($rate >= 95) return 100;
        if ($rate >= 90) return 90;
        if ($rate >= 70) return 70;
        if ($rate >= 50) return 45;
        return max(10, (int) $rate); // anything below 50% is broken-looking
    }

    private static function ssl_score(array $ssl): ?int {
        $min = null;
        foreach ($ssl as $c) {
            if (!empty($c['error'])) continue;
            $days = (int) ($c['days'] ?? 0);
            if ($min === null || $days < $min) $min = $days;
        }
        if ($min === null) return null;
        if ($min < 0)   return 0;
        if ($min < 14)  return 30;
        if ($min < 30)  return 60;
        if ($min < 90)  return 85;
        return 100;
    }

    private static function cron_score(array $cron): int {
        if (empty($cron)) return 100;
        $score = 100;
        $score -= min(50, ((int) ($cron['overdue'] ?? 0)) * 8);
        $score -= min(30, ((int) ($cron['orphan']  ?? 0)) * 4);
        return max(0, $score);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Extra checks (added in 7.0.3 for the report overhaul)
    // ─────────────────────────────────────────────────────────────────

    private static function wp_debug_state(): array {
        return [
            'debug'         => defined('WP_DEBUG') && WP_DEBUG,
            'debug_log'     => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG !== false,
            'debug_display' => defined('WP_DEBUG_DISPLAY') ? (bool) WP_DEBUG_DISPLAY : true,
        ];
    }

    private static function https_state(): array {
        $siteurl = (string) get_option('siteurl', '');
        $home    = (string) get_option('home', '');
        $site_https = strncmp($siteurl, 'https://', strlen('https://')) === 0;
        $home_https = strncmp($home, 'https://', strlen('https://')) === 0;
        return [
            'is_https'      => is_ssl(),
            'siteurl_https' => $site_https,
            'home_https'    => $home_https,
            'mixed_risk'    => !($site_https && $home_https),
        ];
    }

    private static function updates_state(): array {
        // wp_get_update_data is in WP 3.5+ — safe to call directly
        $data = function_exists('wp_get_update_data') ? wp_get_update_data() : ['counts' => []];
        $c    = $data['counts'] ?? [];
        return [
            'core'         => (int) ($c['wordpress']    ?? 0),
            'plugins'      => (int) ($c['plugins']      ?? 0),
            'themes'       => (int) ($c['themes']       ?? 0),
            'translations' => (int) ($c['translations'] ?? 0),
        ];
    }

    private static function detect_backup(): array {
        // Map of plugin_basename → display name. We check active_plugins
        // because a backup plugin that's installed-but-deactivated isn't
        // running any scheduled backup.
        $known = [
            'updraftplus/updraftplus.php'                                => 'UpdraftPlus',
            'backwpup/backwpup.php'                                      => 'BackWPup',
            'duplicator/duplicator.php'                                  => 'Duplicator',
            'duplicator-pro/duplicator-pro.php'                          => 'Duplicator Pro',
            'wpvivid-backuprestore/wpvivid-backuprestore.php'            => 'WPvivid Backup',
            'all-in-one-wp-migration/all-in-one-wp-migration.php'        => 'All-in-One WP Migration',
            'backupbuddy/backupbuddy.php'                                => 'Solid Backups',
            'wp-time-capsule/wp-time-capsule.php'                        => 'WP Time Capsule',
            'akeeba-backup-core-for-wordpress/akeeba-solo-wp.php'        => 'Akeeba Backup',
            'wp-staging/wp-staging.php'                                  => 'WP Staging',
            'jetpack/jetpack.php'                                        => 'Jetpack (VaultPress)',
            'blogvault-real-time-backup/blogvault.php'                   => 'BlogVault',
        ];
        $active = (array) get_option('active_plugins', []);
        foreach ($known as $path => $name) {
            if (in_array($path, $active, true)) {
                return ['detected' => true, 'plugin' => $name];
            }
        }
        return ['detected' => false, 'plugin' => null];
    }

    private static function permission_state(): array {
        $files = [
            'wp-config.php' => ABSPATH . 'wp-config.php',
            '.htaccess'     => ABSPATH . '.htaccess',
        ];
        $out = [];
        foreach ($files as $name => $path) {
            if (!file_exists($path)) { $out[$name] = ['exists' => false]; continue; }
            $raw   = @fileperms($path);
            $perms = $raw === false ? '?' : substr(sprintf('%o', $raw), -3);
            // Last octal digit > 0 = world-perms set. For wp-config we want
            // 600/640 ideally; 644 is the WP default and is widely tolerated.
            $world = is_string($perms) && strlen($perms) === 3 ? (int) $perms[2] : 0;
            $verdict = 'ok';
            if ($name === 'wp-config.php' && $world > 4) $verdict = 'warn';
            if ($world > 5)                              $verdict = 'critical';
            $out[$name] = ['exists' => true, 'perms' => $perms, 'verdict' => $verdict];
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Issue collection — produces the criticals banner + priority cards
    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array<int, array{title:string,reason:string,how:string,urgency:string,category:string}>
     */
    private static function collect_issues(
        array $eol, array $grader, array $headers, array $ssl, ?array $opcache,
        array $db, array $autoload, array $cron, array $extras
    ): array {
        $out = [];

        // PHP EOL
        if (($eol['status'] ?? '') === 'eol') {
            $out[] = self::issue('critical', 'Compatibility',
                'PHP has reached end-of-life',
                sprintf('You are running PHP %s, which is past its support window. New security patches will not be issued.', PHP_VERSION),
                'Ask your host to upgrade to PHP 8.2 or newer. Most managed hosts (Kinsta, WP Engine, SiteGround, Cloudways) have a one-click PHP switcher in the control panel.'
            );
        } elseif (($eol['status'] ?? '') === 'warning' && ($eol['days'] ?? 9999) < 90) {
            $out[] = self::issue('critical', 'Compatibility',
                sprintf('PHP nears end-of-life in %d days', (int) $eol['days']),
                sprintf('PHP %s loses official security support on %s.', $eol['minor'] ?? PHP_VERSION, $eol['eol'] ?? '—'),
                'Plan a PHP upgrade with your host this month. Run the Compatibility Scanner first to catch plugins that need PHP 8.x.'
            );
        } elseif (($eol['status'] ?? '') === 'warning') {
            $out[] = self::issue('warning', 'Compatibility',
                sprintf('PHP loses support in %d days', (int) $eol['days']),
                sprintf('PHP %s EOL date is %s. After that, no more security fixes.', $eol['minor'] ?? PHP_VERSION, $eol['eol'] ?? '—'),
                'Schedule a PHP upgrade in your maintenance window before that date.'
            );
        }

        // Database EOL
        if (!empty($db['eol'])) {
            $eol_ts = strtotime($db['eol']);
            if ($eol_ts) {
                $days = (int) floor(($eol_ts - time()) / DAY_IN_SECONDS);
                if ($days < 0) {
                    $out[] = self::issue('critical', 'Database',
                        sprintf('%s %s is past end-of-life', $db['engine'], $db['version']),
                        sprintf('Your database server reached EOL on %s. New security patches are no longer issued.', $db['eol']),
                        sprintf('Ask your host to migrate to a supported %s version, or move to MySQL 8 / MariaDB 10.11 LTS.', $db['engine'])
                    );
                } elseif ($days < 90) {
                    $out[] = self::issue('critical', 'Database',
                        sprintf('%s %s EOL in %d days', $db['engine'], $db['version'], $days),
                        sprintf('Your database loses official support on %s — that\'s within the next 90 days.', $db['eol']),
                        sprintf('Coordinate a %s upgrade with your host this month. Test a staging copy first.', $db['engine'])
                    );
                } elseif ($days < 365) {
                    $out[] = self::issue('warning', 'Database',
                        sprintf('%s %s EOL in %d days', $db['engine'], $db['version'], $days),
                        sprintf('Database EOL date is %s. Plan an upgrade well before then.', $db['eol']),
                        sprintf('Move to a supported %s version on your host.', $db['engine'])
                    );
                }
            }
        }

        // OPcache
        if ($opcache) {
            $rate = (float) ($opcache['hit_rate'] ?? 0);
            if (($opcache['enabled'] ?? false) === false) {
                $out[] = self::issue('critical', 'Performance',
                    'OPcache is disabled',
                    'PHP\'s bytecode cache is off. Every request re-compiles all PHP files from scratch.',
                    'Set <code>opcache.enable=1</code> in php.ini, or ask your host to enable OPcache. Most modern hosts ship it on by default.'
                );
            } elseif (!empty($opcache['full'])) {
                $out[] = self::issue('critical', 'Performance',
                    'OPcache memory is full',
                    'The bytecode cache filled up — new scripts can\'t be cached, so they recompile on every hit.',
                    'Increase <code>opcache.memory_consumption</code> (try 256MB) and <code>opcache.max_accelerated_files</code> (try 20000) in php.ini.'
                );
            } elseif ($rate > 0 && $rate < 50) {
                $out[] = self::issue('critical', 'Performance',
                    sprintf('OPcache hit rate is %.1f%%', $rate),
                    sprintf('A healthy site sits at 95%%+. Yours is at %.1f%% — meaning %d%% of PHP requests recompile from scratch.', $rate, 100 - (int) $rate),
                    'Usually means the cache is too small or being reset constantly. Raise <code>opcache.memory_consumption</code>, check <code>opcache.validate_timestamps</code>, and stop any plugin that calls opcache_reset() on schedule.'
                );
            } elseif ($rate > 0 && $rate < 90) {
                $out[] = self::issue('warning', 'Performance',
                    sprintf('OPcache hit rate is %.1f%% (target: 95%%+)', $rate),
                    'Some PHP requests are recompiling. Usually fine on busy sites just after a deploy, but persistent low rates mean the cache is undersized.',
                    'Increase <code>opcache.memory_consumption</code> and re-check after 24 hours.'
                );
            }
        }

        // Config grader (the overall score is more useful than per-check noise here)
        if (!empty($grader) && isset($grader['score'])) {
            $s = (int) $grader['score'];
            if ($s < 60) {
                $out[] = self::issue('critical', 'Configuration',
                    sprintf('PHP config grade is %s', $grader['grade'] ?? 'F'),
                    sprintf('Several PHP directives are misconfigured (score %d/100). This usually means slow page loads or weakened security.', $s),
                    'Open <strong>Config Grader</strong> and click <em>Fix it</em> on each failing row — auto-fix writes the recommended value to .htaccess with rollback if anything breaks.'
                );
            } elseif ($s < 80) {
                $out[] = self::issue('warning', 'Configuration',
                    sprintf('PHP config grade is %s', $grader['grade'] ?? 'C'),
                    sprintf('%d/100 — a handful of directives are not at recommended values.', $s),
                    'Open Config Grader and apply auto-fixes for the failing rows.'
                );
            }
        }

        // Security headers
        if (!isset($headers['error']) && isset($headers['score'])) {
            $s = (int) $headers['score'];
            $missing = array_filter($headers['results'] ?? [], function ($h) {
                return !$h['present'];
            });
            $n = count($missing);
            if ($s < 50) {
                $out[] = self::issue('critical', 'Security',
                    sprintf('Missing %d security header%s', $n, $n === 1 ? '' : 's'),
                    sprintf('Score %d/100. Missing headers like Content-Security-Policy and Strict-Transport-Security let attackers downgrade HTTPS, embed your pages in iframes, or run injected scripts.', $s),
                    'Open <strong>Security Headers</strong> in the plugin → click each missing header for the exact .htaccess/Nginx snippet to add.'
                );
            } elseif ($s < 80) {
                $out[] = self::issue('warning', 'Security',
                    sprintf('%d security header%s missing', $n, $n === 1 ? '' : 's'),
                    sprintf('Score %d/100. Some lower-impact headers are absent.', $s),
                    'Add the recommended headers from Security Headers → fix list.'
                );
            }
        }

        // SSL
        foreach ($ssl as $cert) {
            if (!empty($cert['error'])) continue;
            $days = (int) ($cert['days'] ?? 0);
            $host = $cert['host'] ?? 'site';
            if ($days < 0) {
                $out[] = self::issue('critical', 'Security',
                    sprintf('SSL certificate for %s has expired', $host),
                    sprintf('Expired %d day(s) ago. Browsers now show a full-page warning before letting visitors continue.', abs($days)),
                    'Renew the certificate via your host\'s SSL panel or Let\'s Encrypt. Most managed hosts auto-renew — check the SSL settings page.'
                );
            } elseif ($days < 14) {
                $out[] = self::issue('critical', 'Security',
                    sprintf('SSL expires in %d day(s) for %s', $days, $host),
                    'You are inside the 14-day expiry window. Visitors will get a browser warning when this lapses.',
                    'Trigger a renewal now (most hosts have a "Renew" button), or check that Let\'s Encrypt auto-renewal is healthy.'
                );
            } elseif ($days < 30) {
                $out[] = self::issue('warning', 'Security',
                    sprintf('SSL expires in %d days for %s', $days, $host),
                    'Renewal hasn\'t fired yet. If your host auto-renews, this should clear automatically within the next 7 days.',
                    'Confirm with your host that auto-renewal is on. Otherwise queue a manual renewal.'
                );
            }
        }

        // WP_DEBUG_DISPLAY on (security)
        if (!empty($extras['wp_debug']['debug']) && !empty($extras['wp_debug']['debug_display'])) {
            $out[] = self::issue('critical', 'Security',
                'WP_DEBUG_DISPLAY is on in production',
                'PHP errors and notices are being printed to every visitor. Stack traces can leak credentials, table prefixes, and plugin paths.',
                'In wp-config.php set <code>define(\'WP_DEBUG_DISPLAY\', false);</code> and <code>@ini_set(\'display_errors\', \'0\');</code>. Keep WP_DEBUG_LOG on so you still see errors in the log.'
            );
        }

        // HTTPS mismatch
        if (!empty($extras['https']) && $extras['https']['mixed_risk']) {
            $out[] = self::issue('warning', 'Security',
                'WordPress URLs are not fully on HTTPS',
                sprintf('siteurl=%s, home=%s. Browsers will block mixed-content resources and search engines treat HTTP as a downgrade.', $extras['https']['siteurl_https'] ? 'HTTPS' : 'HTTP', $extras['https']['home_https'] ? 'HTTPS' : 'HTTP'),
                'Update both URLs in Settings → General to use https://. If the site is already serving HTTPS, also run a search-replace in the database (with a tool like wp-cli search-replace or Better Search Replace).'
            );
        }

        // Autoload bloat
        if (!empty($autoload) && (int) $autoload['bytes'] > 1024 * 1024) {
            $size = size_format((int) $autoload['bytes']);
            $out[] = self::issue('warning', 'Performance',
                sprintf('Autoload data is %s', $size),
                sprintf('WordPress loads %s of options on every page request. Anything above ~1 MB starts to slow down each page load measurably.', $size),
                'Open <strong>Database Health</strong> and prune the largest autoloaded options — they\'re usually leftover from removed plugins.'
            );
        }

        // Outdated WP / plugins / themes
        $u = $extras['updates'] ?? null;
        if ($u) {
            if ($u['core'] > 0) {
                $out[] = self::issue('warning', 'Maintenance',
                    'WordPress core has an update available',
                    'Running an outdated WP version means missing security patches and bug fixes.',
                    'Open <strong>Dashboard → Updates</strong> and update WordPress. Back up first — your backup plugin (if installed) can do this in one click.'
                );
            }
            $pt = $u['plugins'] + $u['themes'];
            if ($pt >= 5) {
                $out[] = self::issue('warning', 'Maintenance',
                    sprintf('%d plugin/theme updates pending', $pt),
                    'Updates often include security patches. Letting them pile up grows the surface area for attacks.',
                    'Update in batches: back up, run the updates, smoke-test the site, repeat. Consider enabling auto-updates for plugins from trusted vendors.'
                );
            }
        }

        // Backups
        if (!empty($extras['backup']) && empty($extras['backup']['detected'])) {
            $out[] = self::issue('warning', 'Maintenance',
                'No backup plugin detected',
                'We couldn\'t find UpdraftPlus, BackWPup, Duplicator, BlogVault, or any other major backup plugin running on this site.',
                'Install a backup plugin and schedule daily off-site backups. Without one, a single bad update can mean a manual restore from your host\'s nightly snapshot — if they keep one.'
            );
        }

        // File permissions
        foreach (($extras['permissions'] ?? []) as $name => $info) {
            if (empty($info['exists'])) continue;
            if ($info['verdict'] === 'critical') {
                $out[] = self::issue('critical', 'Security',
                    sprintf('%s is world-writable (perms %s)', $name, $info['perms']),
                    'Any user on the server can rewrite this file. On a shared host this is an immediate compromise risk.',
                    sprintf('SSH or use your host\'s file manager to <code>chmod 600 %s</code> (or 640).', $name)
                );
            } elseif ($info['verdict'] === 'warn') {
                $out[] = self::issue('warning', 'Security',
                    sprintf('%s permissions are loose (perms %s)', $name, $info['perms']),
                    'Tightening permissions on this file reduces the blast radius if another user account on the server is compromised.',
                    sprintf('Run <code>chmod 600 %s</code> for the strictest setting, or 640 if your hosting setup needs group read.', $name)
                );
            }
        }

        // Cron
        if (!empty($cron)) {
            $overdue = (int) ($cron['overdue'] ?? 0);
            $orphan  = (int) ($cron['orphan']  ?? 0);
            if ($overdue >= 10) {
                $out[] = self::issue('critical', 'Maintenance',
                    sprintf('%d overdue cron events', $overdue),
                    'Scheduled tasks aren\'t running. Backups, email digests, transient cleanup, plugin-defined jobs — all stalled.',
                    'WP-Cron usually fires when someone visits the site. If traffic is low, set up a real system cron calling <code>wp-cron.php</code> every 5 min, or set <code>DISABLE_WP_CRON</code> and call it from your host\'s scheduler.'
                );
            } elseif ($overdue > 0) {
                $out[] = self::issue('warning', 'Maintenance',
                    sprintf('%d cron event(s) overdue', $overdue),
                    'Some scheduled tasks didn\'t fire on time. Usually fine on busy sites — just means cron processed the queue slowly.',
                    'If this persists, switch to a system cron triggering wp-cron.php on a fixed interval.'
                );
            }
            if ($orphan > 0) {
                $out[] = self::issue('warning', 'Maintenance',
                    sprintf('%d orphan cron hook(s)', $orphan),
                    'These cron events have no callback registered — usually leftovers from plugins you removed. They fire forever doing nothing.',
                    'Open <strong>WP Cron Monitor</strong> → click <em>Purge hook</em> on orphan rows.'
                );
            }
        }

        return $out;
    }

    private static function issue(string $urgency, string $category, string $title, string $reason, string $how): array {
        return compact('urgency', 'category', 'title', 'reason', 'how');
    }

    /**
     * Build the top-N action list. Pull all criticals (sorted as they came in
     * — collect_issues lists them by impact), then top up with the highest
     * warnings until we reach N.
     */
    private static function prioritize(array $issues, int $n): array {
        $crit = array_values(array_filter($issues, function ($i) {
            return $i['urgency'] === 'critical';
        }));
        $warn = array_values(array_filter($issues, function ($i) {
            return $i['urgency'] === 'warning';
        }));
        $out  = array_merge($crit, $warn);
        return array_slice($out, 0, $n);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Plain-English captions for tech jargon in the report
    // ─────────────────────────────────────────────────────────────────

    public static function plain_english(string $key): string {
        static $map = [
            'php_version'      => 'The PHP version running your site. Newer = faster and safer.',
            'wp_version'       => 'WordPress core version. Older versions miss security patches.',
            'config_grade'     => 'A–F grade of your PHP config against best practices.',
            'headers'          => 'HTTP response headers that tell browsers how to protect users.',
            'opcache'          => "PHP's bytecode cache — speeds up every page load.",
            'opcache_hit'      => '% of requests that found pre-compiled code in cache. Target: 95%+.',
            'autoload'         => 'Data WordPress loads on every page request. Big = slow.',
            'db_engine'        => 'Database server. Each version has a security support window.',
            'overdue_cron'     => 'Scheduled tasks that should have run but didn\'t.',
            'orphan_cron'      => 'Scheduled tasks whose plugin is gone — they run forever doing nothing.',
            'mixed_content'    => 'HTTPS pages loading HTTP resources — browser blocks them.',
            'wp_debug_display' => 'Whether PHP errors are printed to visitors (should be off in production).',
            'memory_peak'      => 'Highest RAM PHP used today. If it nears the limit, raise memory_limit.',
        ];
        return $map[$key] ?? '';
    }

    // ─────────────────────────────────────────────────────────────────
    //  Small UI helper used by the view to render a score donut
    // ─────────────────────────────────────────────────────────────────

    public static function render_donut(int $score, int $size = 160, string $color = '#7c3aed', string $bg = '#eef0f4', int $stroke = 14): string {
        $score  = max(0, min(100, $score));
        $r      = ($size - $stroke) / 2;
        $circ   = 2 * M_PI * $r;
        $dash   = $circ * (100 - $score) / 100;
        $half   = $size / 2;
        // Slightly bigger if score < 50 — emphasises the gap visually.
        ob_start();
        ?>
        <svg width="<?php echo (int) $size; ?>" height="<?php echo (int) $size; ?>" viewBox="0 0 <?php echo (int) $size; ?> <?php echo (int) $size; ?>" class="phpinfowp-donut" role="img" aria-label="Score <?php echo (int) $score; ?> out of 100">
            <circle cx="<?php echo $half; ?>" cy="<?php echo $half; ?>" r="<?php echo $r; ?>" fill="none" stroke="<?php echo esc_attr($bg); ?>" stroke-width="<?php echo (int) $stroke; ?>"></circle>
            <circle cx="<?php echo $half; ?>" cy="<?php echo $half; ?>" r="<?php echo $r; ?>" fill="none"
                    stroke="<?php echo esc_attr($color); ?>" stroke-width="<?php echo (int) $stroke; ?>"
                    stroke-dasharray="<?php echo $circ; ?>" stroke-dashoffset="<?php echo $dash; ?>"
                    stroke-linecap="round" transform="rotate(-90 <?php echo $half; ?> <?php echo $half; ?>)"></circle>
        </svg>
        <?php
        return (string) ob_get_clean();
    }
}
