=== phpinfo() WP - Site Health, PHP Compatibility & Server Audit ===
Contributors: exeebit
Tags: site health, health check, php compatibility, troubleshooting, phpinfo
Requires at least: 5.9
Tested up to: 7.1.1
Stable tag: 8.0.0
Requires PHP: 7.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: phpinfo-wp

Stop silent site breakages. The ultimate in-admin server audit & per-user troubleshooting tool built for agencies and professional developers.

== Description ==

**phpinfo() WP** is a modern, actively-maintained WordPress site health and server audit plugin. It is the exact in-admin tool freelancers and agencies install on every fresh site to instantly see *what is wrong, what is about to break, and what to fix*, all without a SaaS subscription, without external dashboards, and without leaving the WordPress admin panel.

Think of it as the official **"Health Check & Troubleshooting"** plugin, but built for professional production sites. Our Troubleshooting Mode runs completely in a per-user session. Visitors and clients continue to see the live site normally while you safely debug and isolate conflicts without a single second of downtime.

= The free version covers what every WordPress site owner actually needs: =

*   **phpinfo() viewer**: Clean, searchable, and modern (the original feature, completely restyled).
*   **Host-Friendly Compatibility Scanner**: Scan all plugins and themes for PHP version conflicts before upgrading, built to work smoothly even on strict managed hosts.
*   **Update Guard**: Complete pre-update & post-update safety suite. Preview core upgrade stability, scan pending plugin and theme updates for breaking changes, and auto-verify site health 60s after every update.
*   **Zero-Downtime Troubleshooting**: Safely debug theme and plugin conflicts in your own admin session without affecting visitors or live sales.
*   **PHP EOL Timeline**: Every PHP version's end-of-life date, current status, and days remaining.
*   **Config Grader summary**: Overall A-F grade of your PHP config against WordPress best practices.
*   **PHP Config editor (.htaccess / .user.ini)**: Set or change php.ini directives safely from your dashboard, with automatic backups and safety rollback.
*   **Full Admin Security Activity Log**: Real-time security audit trail tracking user logins, failed authentication attempts (with real IP detection), plugin/theme changes, and core settings updates with 1-click CSV export.
*   **Live Server Telemetry Dashboard**: Instant overview of peak RAM consumption, memory ceilings, database engine stats, and OPcache health right from your dashboard.
*   **Admin bar health scoreboard**: Live grade and most-urgent issue on every admin page, like PageSpeed for your server.
*   **Dashboard widget**: Site health at a glance the moment you log in.
*   **Activity log, Extensions, and Basic info**: Everything the original plugin did, completely restyled.
*   **AI-Ready API (WP 7.0)**: Exposes audit data so AI assistants and plugins can inspect server health through a standard core interface.
*   **AI Explanations (WP 7.0)**: Instantly explain failing checks in plain English using the core AI Client connection.

= Pro adds the tooling agencies and serious site owners actually need: =

**Safeguard: Don't break your site**

*   **1-Click Auto-Fix with Rollback**: Fix config issues instantly. Writes optimization rules to .htaccess or .user.ini and auto-reverts if the server hits a 500 error.
*   **Pre-Update PHP check**: Scan plugin updates before upgrading to verify they do not require a PHP version you do not have.
*   **Smart PHP 7.4–8.4 Scanner (Zero False Alarms)**: Scans deep for real breaking changes in your plugins and themes while ignoring harmless backward-compatibility polyfills.
*   **Update Guard Pro**: Automatic interception on the WordPress Updates page, changelog breaking-change risk analysis, WP.org abandonment alerts, post-update diagnostic health checks (loopback, error log delta, cron), and AI-written remediation steps.
*   **Config Snapshots**: Weekly automatic snapshots of every php.ini directive, with visual diffs.
*   **Security Headers Auditor**: Grade your HTTP response headers (CSP, HSTS, X-Frame-Options) with fix suggestions.
*   **Web Server Snippet Library**: Optimized Nginx and Apache configuration blocks for caching, security, and bad bot blocking, with 1-click injection for Apache and LiteSpeed.
*   **SSL Certificate Monitor**: Track certificate expiry and domain mismatches to avoid security warnings.

**Insight: Know what is wrong before clients call**

*   **Full Config Grader**: Detailed grading with the exact recommended values and why each directive matters.
*   **Native AI Plain-English Fix Explanations**: Instant plain-language explanations of failing server directives and PHP error logs, with exact steps to resolve them.
*   **Autoload Bloat & Database Index Scanner**: Scan wp_options for bloated autoloaded entries and missing MySQL indexes that slow down your database queries.
*   **Database Health**: Engine version, EOL status, size, and autoload bloat detection.
*   **External API Monitor**: Track response times, status codes, and SSL expiry for third-party endpoints your site depends on.
*   **Permissions Audit**: Recursive file and directory permission auditor.
*   **OPcache Dashboard**: Memory usage, hit rate, cached scripts, and one-click reset.
*   **Object Cache Monitor**: Status, backend detection, hit/miss metrics, and Redis/Memcached verification.
*   **Live Error Log Viewer**: Filterable error stream directly inside wp-admin.
*   **WP-Cron Monitor**: Catch missed tasks, runaway jobs, and stuck schedules.
*   **Mail Deliverability**: Test wp_mail(), inspect PHPMailer transport, and send test emails.
*   **Health Alerts**: Real-time email notifications and Slack/Discord webhook alerts when critical issues occur.

**Deliver: Look professional to clients**

*   **Email Alerts**: Get notified on PHP EOL, config drift, OPcache drops, and SSL expiry.
*   **Weekly Digest**: Full server health summary delivered to your inbox every Monday (Unlimited or Lifetime).
*   **Integrations**: Slack, Discord, and Webhook support for real-time alerts (Unlimited or Lifetime).
*   **Multi-Site Dashboard**: Network-wide dashboard widget support on every subsite.

= Why use this instead of 5 different plugins? =

Most WordPress site health tools force you to install separate plugins for PHP compatibility, SSL monitoring, security headers, OPcache, error logs, cron, and reports. Each one is another plugin to update, another menu item, and another set of options.

phpinfo() WP gives you **one in-admin plugin** that covers all of it, with a single dashboard widget and a single PDF audit report. No external SaaS dashboard, no per-site monthly fees, and no separate logins.

= Pricing =

*   **Single Site**: $39/year (1 site, essential Pro features, branded PDF, 3 snapshots, 1 API monitor)
*   **Unlimited Sites**: $69/year (the popular pick, works on every site, fully white-labeled, unlimited snapshots and API monitors)
*   **Lifetime**: $149 once (founders pricing, limited spots, unlimited sites, fully white-labeled forever)

14-day money-back guarantee. Instant license delivery. Site-locked license keys.

Buy at [exeebit.com/phpinfo-wp](https://exeebit.com/phpinfo-wp#pricing).

== Installation ==

1. Install from your WordPress admin: **Plugins -> Add New** and search for *"phpinfo() WP"*.
2. Activate.
3. Find the **phpinfo() WP** menu in your admin sidebar.
4. (Optional) Purchase a Pro license and activate it from **phpinfo() WP -> License**.

You can also [download the zip](https://downloads.wordpress.org/plugin/phpinfo-wp.zip) and upload it from **Plugins -> Add New -> Upload Plugin**.

= Server requirements =

*   PHP 7.3 or higher. PHP 7.4 reached end-of-life in November 2022 and is no longer supported.
*   WordPress 5.9 or higher.
*   Some PHP functions used by the plugin may be disabled by your host. Contact your host if features show as unavailable.
*   For .htaccess editing, your site root must be writable.

== Frequently Asked Questions ==

= Is the free version still free? =

Yes. The free phpinfo viewer, .htaccess editor, extension list, activity log, PHP EOL Timeline, and Config Grader summary are 100% free and always will be. Pro is an optional upgrade for agencies and serious site owners.

= Does the Pro version send my data anywhere? =

No. All audits run on your own server. The Pro license check pings our license server once per week to confirm your key is still valid, and nothing else is transmitted.

= Can I use one license on multiple sites? =

The Single Site license works on 1 site. The Unlimited license works on every site you own or manage. The Lifetime license is also unlimited.

= How does white-label work? =

In the Pro Audit Report page, click **White-label** to set your company name, report title, accent color, and footer note. The PDF you generate uses your branding, not ours.

= What if my license server is unreachable? =

The plugin works fine for 14 days even if our license server is unreachable. After that, Pro features lock and you will need to re-activate the license. You will get a clear notice well before that happens.

= Will this slow down my site? =

No. All checks run inside the admin dashboard only, and there is zero impact on your front-end performance. Heavy checks (security headers, SSL) are cached.

= How is this different from Health Check & Troubleshooting? =

Health Check & Troubleshooting is the official WordPress.org plugin for this kind of work and is a solid tool. Its Troubleshooting Mode is site-wide. When enabled, every visitor sees the default theme with your plugins deactivated until you disable the mode via a one-click link in the admin bar.

phpinfo() WP takes a different approach. Our Troubleshooting Mode is per-user: only your current admin session sees deactivated plugins and the default theme. Every other visitor and admin keeps seeing the live site as normal. That is the design choice we made for debugging plugin conflicts on busy production sites, such as a live WooCommerce store or a high-traffic publisher, where taking the front-end offline is not an option.

We also add features Health Check does not offer: PHP EOL tracking, A-F config grading with one-click fixes (Pro), an admin-bar health scoreboard, SSL/headers monitoring, and a client-ready PDF audit report.

Use Health Check if you want the official tool with a simple, site-wide debug mode. Use phpinfo() WP if you need a per-user debug session on a live site, plus the broader audit suite.

= How is this different from Query Monitor / WP Umbrella? =

Query Monitor is a developer tool for debugging individual page loads, which is a different job. WP Umbrella, ManageWP, and MainWP are external SaaS dashboards that bill per site, per month, which is useful for agencies that want everything in one external console.

phpinfo() WP Pro is for the site owner or freelancer who wants one in-admin tool that covers PHP health, config, security headers, SSL, and a client-ready audit report, without a monthly SaaS subscription and without leaving the WordPress admin.

= Does this PHP compatibility scanner actually work on my managed host? =

Yes. Unlike scanners that rely on PHP_CodeSniffer or the `exec()` function, our scanner uses static analysis that runs inside WordPress itself. It works on Kinsta, WP Engine, SiteGround, Cloudways, Pantheon, and every other managed host that restricts shell access.

= Where do I get support? =

*   Free: [WordPress.org support forum](https://wordpress.org/support/plugin/phpinfo-wp/).
*   Pro: email support@exeebit.com with your license key for priority response.

== Screenshots ==

1. Server Overview Dashboard: Real-time server telemetry, peak RAM consumption, memory limits, database size, and overall health grade at a glance.
2. Update Guard Suite: Pre-update risk analysis across plugins and themes, plus automated 60-second post-update health diagnostics to stop silent site crashes.
3. PHP Compatibility Scanner (PHP 7.4–8.4): Host-friendly static scanner checking plugins and themes for compatibility before you upgrade, built for strict managed hosts.
4. Zero-Downtime Troubleshooting Mode: Isolate plugin conflicts in a private, per-user admin session while live visitors and customers browse normally without downtime.
5. phpinfo() Viewer & Server Info: Full phpinfo() output inside wp-admin — searchable, colour-coded, and safe. Includes .htaccess editor and raw server variable browser.
6. In-Admin PHP Multi-File Error Log (Pro): Real-time filterable error log viewer with stack traces and instant plain-English explanations of failing code.
7. Config Grader & 1-Click Auto-Fix (Pro): A-F configuration scoring with instant directive optimization and automated rollback if the server triggers a 500 error.
8. Database Health & Autoload Bloat Scanner (Pro): Detect TTFB-killing autoload data bloat in wp_options, identify missing MySQL indexes, and clean expired transients.
9. Executive White-Label PDF Audit Report (Pro): Generate client-ready site audit reports with your agency logo, custom accent branding, and server health breakdown.

== Changelog ==

= 8.0.0 =
* New: Server Overview Dashboard — Live look at peak RAM usage, memory limits, database autoload size, and OPcache health right inside wp-admin.
* New: Admin Security Activity Log — Track logins, failed login attempts (with real IP detection behind Cloudflare and proxies), plugin/theme changes, and settings updates. Includes automatic log cleanup and 1-click CSV export.
* New: Smarter PHP Compatibility Scanner — Scan plugins and themes for compatibility up to PHP 8.4 without false alarms from old fallback code.
* New: Autoload Bloat & MySQL Index Scanner — Quickly check your database for oversized autoloaded options and missing table indexes that cause slow queries.
* New: Plain-English AI Explanations — Failing server checks now include instant, easy-to-understand explanations of what went wrong and how to fix it.
* Improved: 1-Click Server Directive Fixes now intelligently detect your server environment (.htaccess or .user.ini) and automatically roll back if anything goes wrong.
* Improved: Cleaner screens by hiding distracting third-party plugin notices on phpinfo() WP diagnostic pages.
* Improved: Security Headers audit now makes it much easier to spot and fix missing protections like CSP, HSTS, and X-Frame-Options.
* Compatibility: Confirmed full compatibility with WordPress 7.1.1.

= 7.2.7 =
* Improved: Update Guard now checks plugins and themes for breaking changes before you update, and runs health diagnostics 60 seconds after updating to make sure your site is still running smoothly.
* Fix: Renamed admin URL parameters to prevent false-positive blocks from security firewalls and LiteSpeed rules. Special thanks to Simon Richards for reporting this.
* Improved: License activation now validates instantly without reloading the page.
* Improved: Made technical diagnostics and support tools accessible with live server signals.
* Fix: Corrected button alignment and error log line wrapping across admin screens.

= 7.2.6 =
* Compatibility: Confirmed compatibility with WordPress 7.1.

= 7.2.5 =
* Improved: Added advance reminder for the Single Site price update to $39/year.
* Improved: Upgrade prompts now highlight your site's actual server issues so you know exactly what Pro fixes.
* Improved: Visual polish across dashboard and feature screens.

= 7.2.4 =
* New: Added translation files for French, German, Spanish, Italian, and Dutch.
* Improved: Cleaner PHP end-of-life status badges and HTTP security header checks.

= 7.2.3 =
* Improved: Updated Single Site tier limits (1 Outbound API Monitor, 3 Config Snapshots, branded PDF reports). Existing license holders retain unlimited access.
* Improved: Layout alignment and mobile responsiveness across the landing page.

== Upgrade Notice ==

= 7.0.0 =
Major release. phpinfo() WP is now a full WordPress site-health and server-audit plugin, representing a modern, actively-maintained take on the Health Check & Troubleshooting workflow. Free adds Troubleshooting Mode (per-user safe-mode that cannot leave your site broken), PHP Compatibility Scanner that works on managed hosts, pre-update PHP-version warnings, PHP EOL Timeline, Config Grader summary, admin-bar health scoreboard, WordPress 7.0 Abilities API integration for AI assistants, and AI explanations on failing Config Grader checks. Pro adds one-click Config Auto-Fix, security headers, SSL monitor, OPcache dashboard, white-label PDF audit reports, and more.
