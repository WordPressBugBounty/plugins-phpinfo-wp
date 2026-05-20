=== phpinfo() WP — Site Health, PHP Compatibility & Server Audit ===
Contributors: exeebit
Tags: site health, health check, php compatibility, troubleshooting, phpinfo
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 7.0.1
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Modern WordPress site health — PHP compatibility scanner, troubleshooting mode, config grader, EOL timeline. Maintained Health Check alternative.

== Description ==

**phpinfo() WP** is a modern, actively-maintained WordPress site-health and server-audit plugin. It's the in-admin tool freelancers and agencies install on a fresh site to instantly see *what's wrong, what's about to break, and what to fix* — without a SaaS subscription, without external dashboards, and without leaving the WordPress admin.

Think of it as **"Health Check & Troubleshooting" — but maintained, modern, and built for the way WordPress actually works in 2026**: PHP 8.x, SSL everywhere, OPcache by default, agencies running multiple sites. Same diagnostics, plus PHP EOL timeline, A–F config grading, security headers, SSL monitor, and a one-page PDF audit report you can hand to a client.

= The free version covers what every WordPress site owner actually needs =

*   **phpinfo() viewer** — clean, searchable, modern (the original feature, restyled).
*   **PHP Compatibility Scanner** — scan every plugin and theme for PHP version breakages *before* you upgrade. (Free, no signup. Most "PHP compatibility checker" plugins on WP.org are abandoned or only work in dev environments — this one runs on managed hosts.)
*   **Troubleshooting Mode** — safely disable plugins *only for your own admin session* to debug conflicts. Time-limited cookie, undo button, auto-restore on logout. **Fixes the "broke my entire site" problem Health Check is famous for.**
*   **PHP EOL Timeline** — every PHP version's end-of-life date, current status, days remaining.
*   **Config Grader summary** — overall A–F grade of your PHP config against WordPress best practices.
*   **PHP Config editor (.htaccess / .user.ini)** — set or change php.ini directives safely from your dashboard, with automatic backups and a site-health check that aborts a save if your site starts returning HTTP 500.
*   **Admin bar health scoreboard** — live grade + most-urgent issue on every admin page, like PageSpeed for your server.
*   **Dashboard widget** — site health at a glance the moment you log in.
*   **Activity log + Extensions + Basic info** — everything the original plugin did, restyled.
*   **WordPress 7.0 Abilities API** — exposes the audit data as named server abilities (`phpinfowp/get-audit-summary`, `phpinfowp/get-php-version`, `phpinfowp/get-config-grade`, `phpinfowp/get-config-issues`, `phpinfowp/get-directive`, `phpinfowp/list-extensions`) so AI assistants and other plugins on your site can introspect server health through a standard interface instead of scraping screens.
*   **AI explanations (WP 7.0)** — when the new AI Client is configured, every failing Config Grader check gets an *Explain with AI* button that turns the directive into a plain-English explanation. Uses the core Connectors API for credentials — we never touch your API keys.

= Pro adds the tooling agencies and serious site owners actually need =

**Safeguard** — don't break your site

*   **One-click Config Auto-Fix** — every failing Grader check gets a "Fix it" button that writes the recommended value to .htaccess (or your php.ini override) safely, with automatic rollback if anything breaks.
*   **Pre-update PHP compatibility check** — before you click "Update Plugin," see if the new version requires a PHP version you don't have.
*   **Config Snapshots** — weekly automatic snapshots of every php.ini directive, with visual diffs.
*   **Security Headers Auditor** — grade your HTTP response headers (CSP, HSTS, X-Frame-Options, etc.) with fix suggestions.
*   **SSL Certificate Monitor** — track expiry for your site and any additional domains.

**Insight** — know what is wrong before clients call

*   **Full Config Grader** — see every failing directive with the exact recommended value and a one-line explanation of why it matters.
*   **Database Health** — engine version, EOL status, total size, autoload bloat detection.
*   **OPcache Dashboard** — hit rate, memory usage, cached scripts, one-click clear.
*   **PHP Error Log Viewer** — browse, search, and clear your PHP error log from the dashboard.
*   **WP Cron Monitor** — find overdue events, orphan hooks, and recently-run cron tasks.
*   **Mail Deliverability** — send-test, SPF & DKIM lookup.

**Deliver** — look professional to clients

*   **Audit Report** — single-page, white-label PDF you can print or hand to clients. Custom company name, tagline, footer note, and accent color.
*   **Email Alerts** — get notified on PHP EOL, config drift, OPcache drops, and SSL expiry.
*   **Weekly Digest** — full server health summary delivered to your inbox every Monday.
*   **Slack / Discord / Webhook** integration for real-time alerts.
*   **Multi-site (Network) support** — dashboard widget on every site.

= Why use this instead of 5 different plugins? =

Most WordPress site-health tools force you to install separate plugins for PHP compatibility, SSL monitoring, security headers, OPcache, error logs, cron, and reports. Each one is another plugin to update, another set of menus, another set of credentials.

phpinfo() WP gives you **one in-admin plugin** that covers all of it, with a single dashboard widget and a single PDF audit report. No external SaaS dashboard, no per-site monthly fees, no separate logins.

= Pricing =

*   **Single Site** — $29/year
*   **Unlimited Sites** — $69/year (the popular pick — works on every site you own or manage)
*   **Lifetime** — $149 once (founders pricing, first 50 buyers)

14-day money-back guarantee. Instant license delivery. Site-locked license keys.

Buy at [exeebit.com/phpinfo-wp](https://exeebit.com/phpinfo-wp#pricing).

== Installation ==

1. Install from your WordPress admin: **Plugins → Add New** → search for *"phpinfo() WP"*.
2. Activate.
3. Find the **phpinfo() WP** menu in your admin sidebar.
4. (Optional) Purchase a Pro license and activate it from **phpinfo() WP → License**.

You can also [download the zip](https://downloads.wordpress.org/plugin/phpinfo-wp.zip) and upload it from **Plugins → Add New → Upload Plugin**.

= Server requirements =

*   PHP 7.4 or higher (PHP 8.x recommended).
*   Some PHP functions used by the plugin may be disabled by your host. Contact your host if features show as unavailable.
*   For .htaccess editing, your site root must be writable.

== Frequently Asked Questions ==

= Is the free version still free? =

Yes. The free phpinfo viewer, .htaccess editor, extension list, activity log, PHP EOL Timeline, and Config Grader summary are 100% free and always will be. Pro is an optional upgrade for agencies and serious site owners.

= Does the Pro version send my data anywhere? =

No. All audits run on your own server. The Pro license check pings our license server once per week to confirm your key is still valid — nothing else is transmitted.

= Can I use one license on multiple sites? =

The Single Site license works on 1 site. The Unlimited license works on every site you own or manage. The Lifetime license is also unlimited.

= How does white-label work? =

In the Pro Audit Report page, click **White-label** to set your company name, report title, accent color, and footer note. The PDF you generate uses your branding, not ours.

= What if my license server is unreachable? =

The plugin works fine for 14 days even if our license server is unreachable. After that, Pro features lock and you'll need to re-activate the license. You'll get a clear notice well before that happens.

= Will this slow down my site? =

No. All checks run inside the admin dashboard only — there's zero impact on your front-end performance. Heavy checks (security headers, SSL) are cached.

= How is this different from Health Check & Troubleshooting? =

Health Check & Troubleshooting was the official WordPress.org plugin for this kind of work, but it's been unmaintained for two years and its "Troubleshooting Mode" has a long-standing bug that leaves users with all their plugins disabled and no way to recover.

phpinfo() WP rebuilds the core ideas — phpinfo viewer, server debug info, plugin conflict troubleshooting, PHP compatibility checking — in a maintained, modern plugin. Our Troubleshooting Mode is per-user, time-limited, fully reversible, and includes an explicit "End and restore" button. You can't lock yourself out.

It also adds what Health Check never had: PHP EOL tracking, A–F config grading with one-click fixes (Pro), an admin-bar health scoreboard, SSL/headers monitoring, and a client-ready PDF audit report.

= How is this different from Query Monitor / WP Umbrella? =

Query Monitor is a developer tool for debugging individual page loads — different job. WP Umbrella, ManageWP, and MainWP are external SaaS dashboards that bill per site, per month — useful for agencies that want everything in one external console.

phpinfo() WP Pro is for **the site owner or freelancer who wants one in-admin tool** that covers PHP health, config, security headers, SSL, and a client-ready audit report — without a monthly SaaS subscription and without leaving the WordPress admin.

= Does this PHP compatibility scanner actually work on my managed host? =

Yes. Unlike scanners that rely on PHP_CodeSniffer or the `exec()` function, our scanner uses static analysis that runs inside WordPress itself. It works on Kinsta, WP Engine, SiteGround, Cloudways, Pantheon, and every other managed host that restricts shell access.

= Where do I get support? =

*   Free: [WordPress.org support forum](https://wordpress.org/support/plugin/phpinfo-wp/).
*   Pro: email support@exeebit.com with your license key for priority response.

== Screenshots ==

1. Dashboard widget — PHP version, EOL status, Config Grade at a glance.
2. phpinfo() viewer — clean, searchable, modern.
3. Config Grader summary — your site's A–F grade across Performance, Security, and OPcache.
4. PHP EOL Timeline — every PHP version's end-of-life date and days remaining.
5. Troubleshooting Mode — per-user safe-mode that disables plugins only for your admin session, with a one-click undo.
6. Config Grader full breakdown (Pro) — every failing directive with the exact recommended value and a one-click "Fix this" button.
7. Audit Report (Pro) — single-page white-label PDF you can hand to clients.

== Changelog ==

= 7.0.1 =
*   **NEW**: Config Grader now detects when a directive was written to `.user.ini`/`.htaccess` but the host is still overriding it (parent `.user.ini`, hosting panel PHP options, php.ini lock). Shows a clear warning table with what was written vs what PHP reports, plus actionable next steps.
*   **Improved**: Auto-fix success notice now explains the 5-minute `.user.ini` cache so users don't think the fix is broken when values take a moment to apply.

= 7.0.0 =
*   **MAJOR**: Repositioned as a WordPress server health audit suite.
*   **NEW (Free)**: WordPress 7.0 Abilities API integration. Registers six server-side abilities under the `phpinfowp/audit` category — `get-php-version`, `get-config-grade`, `get-config-issues`, `get-directive`, `list-extensions`, `get-audit-summary` — so AI assistants and other plugins can read server health through a standard interface.
*   **NEW (Free)**: AI explanations on failing Config Grader checks. Uses the WP 7.0 AI Client (`wp_ai_client_prompt()`) with credentials managed by the core Connectors API — no API keys handled by this plugin.
*   **NEW (Free)**: PHP EOL Timeline showing end-of-life dates for every PHP version.
*   **NEW (Free)**: Config Grader summary — overall A–F grade visible without Pro.
*   **NEW (Free)**: Admin bar indicator — PHP version + EOL + memory on every page.
*   **NEW (Free)**: Dashboard widget — site health at a glance.
*   **NEW (Free)**: Troubleshooting Mode — per-user safe-mode that disables plugins only for your admin session, with a built-in undo button. Fixes the "Health Check broke my site" problem.
*   **NEW (Free)**: PHP Compatibility Scanner — check plugins and themes before PHP upgrades. Works on managed hosts.
*   **NEW (Free)**: Pre-update PHP-version warning — flags plugin updates that require a newer PHP than your site runs, on the Plugins screen.
*   **NEW (Free)**: Admin bar health scoreboard — live grade + most-urgent issue on every admin page.
*   **NEW (Pro)**: One-click Config Auto-Fix — writes recommended values to .htaccess/.user.ini with automatic rollback if your site returns HTTP 500.
*   **NEW (Pro)**: Config Snapshots with weekly auto-snapshots and visual diffs.
*   **NEW (Pro)**: Security Headers Auditor with grading and fix suggestions.
*   **NEW (Pro)**: SSL Certificate Monitor for your site and extra domains.
*   **NEW (Pro)**: Database Health — engine, EOL, size, autoload bloat.
*   **NEW (Pro)**: OPcache Dashboard with hit rate, memory, and one-click clear.
*   **NEW (Pro)**: PHP Error Log Viewer with search and filters.
*   **NEW (Pro)**: WP Cron Monitor — overdue, orphan, recently-run events.
*   **NEW (Pro)**: Mail Deliverability — send-test, SPF/DKIM lookup.
*   **NEW (Pro)**: White-label Audit Report — single-page PDF with custom branding.
*   **NEW (Pro)**: Email Alerts + Weekly Digest + Slack/Discord/Webhook integrations.
*   **NEW (Pro)**: Multi-site (Network) support.
*   Compatibility: tested up to WordPress 6.8.5.

= 6.1 =
*   Add blueprint for live preview.
*   Fix minor bugs.
*   UI enhancement.

= 6.0 =
*   Fix CSRF vulnerability issues.
*   UI enhancement.

= 5.0 =
*   Fix CSRF vulnerability.
*   Fix htaccess directive editing issues.
*   UI enhancement.

= 4.0 =
*   Fixed freezing issue.

= 3.0 =
*   Added an option to look up some basic information.
*   Fixed PHP errors.

= 2.0 =
*   Edit or set server configuration values via .htaccess.

= 1.0.0 =
*   First release.

== Upgrade Notice ==

= 7.0.0 =
Major release. phpinfo() WP is now a full WordPress site-health and server-audit plugin — a maintained alternative to the abandoned Health Check & Troubleshooting plugin. Free adds Troubleshooting Mode (per-user safe-mode that cannot leave your site broken), PHP Compatibility Scanner that works on managed hosts, pre-update PHP-version warnings, PHP EOL Timeline, Config Grader summary, admin-bar health scoreboard, WordPress 7.0 Abilities API integration for AI assistants, and AI explanations on failing Config Grader checks. Pro adds one-click Config Auto-Fix, security headers, SSL monitor, OPcache dashboard, white-label PDF audit reports, and more.
