=== phpinfo() WP — Site Health, PHP Compatibility & Server Audit ===
Contributors: exeebit
Tags: site health, health check, php compatibility, troubleshooting, phpinfo
Requires at least: 5.9
Tested up to: 7.0
Stable tag: 7.2.2
Requires PHP: 7.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Stop silent site breakages. The ultimate in-admin server audit & per-user troubleshooting tool built for agencies and professional developers.

== Description ==

**phpinfo() WP** is a modern, actively-maintained WordPress site-health and server-audit plugin. It's the in-admin tool freelancers and agencies install on a fresh site to instantly see *what's wrong, what's about to break, and what to fix* — without a SaaS subscription, without external dashboards, and without leaving the WordPress admin.

Think of it as the official **"Health Check & Troubleshooting"** plugin — but built for production sites. Our Troubleshooting Mode runs completely in a per-user session: visitors and clients see the live site normally while you safely debug and isolate conflicts without any downtime.

= The free version covers what every WordPress site owner actually needs =

*   **phpinfo() viewer** — Clean, searchable, modern (the original feature, restyled).
*   **Host-Friendly Compatibility Scanner** — Scan all plugins and themes for PHP version conflicts before upgrading, built to work smoothly even on strict managed hosts.
*   **Update Guard** — Preview core upgrade stability. Detect deprecated calls and removed APIs to get a clear Safe/Caution/Risky rating before clicking update.
*   **Zero-Downtime Troubleshooting** — Safely debug theme and plugin conflicts in your own admin session without affecting visitors or live sales.
*   **PHP EOL Timeline** — Every PHP version's end-of-life date, current status, days remaining.
*   **Config Grader summary** — Overall A–F grade of your PHP config against WordPress best practices.
*   **PHP Config editor (.htaccess / .user.ini)** — Set or change php.ini directives safely from your dashboard, with automatic backups and safety rollback.
*   **Admin bar health scoreboard** — Live grade + most-urgent issue on every admin page, like PageSpeed for your server.
*   **Dashboard widget** — Site health at a glance the moment you log in.
*   **Activity log + Extensions + Basic info** — Everything the original plugin did, restyled.
*   **AI-Ready API (WP 7.0)** — Exposes audit data so AI assistants and plugins can inspect server health through a standard core interface.
*   **AI Explanations (WP 7.0)** — Instantly explain failing checks in plain English using the core AI Client connection.

= Pro adds the tooling agencies and serious site owners actually need =

**Safeguard** — Don't break your site

*   **1-Click Auto-Fix with Rollback** — Fix config issues instantly. Writes optimization rules to .htaccess/.user.ini and auto-reverts if the server hits a 500 error.
*   **Pre-Update PHP check** — Scan plugin updates before upgrading to verify they don't require a PHP version you don't have.
*   **Update Guard Pro** — Automatic interception on the WordPress Updates page, tested-up-to alerts, abandonment alerts, AI-written remediation steps, and full line-by-line inspection.
*   **Config Snapshots** — Weekly automatic snapshots of every php.ini directive, with visual diffs.
*   **Security Headers Auditor** — Grade your HTTP response headers (CSP, HSTS, X-Frame-Options) with fix suggestions.
*   **Web Server Snippet Library** — Optimized Nginx/Apache configuration blocks for caching, security, and bad bot blocking, with 1-click injection for Apache/LiteSpeed.
*   **SSL Certificate Monitor** — Track certificate expiry and domain mismatches to avoid security warnings.

**Insight** — Know what is wrong before clients call

*   **Full Config Grader** — Detailed grading with the exact recommended values and why each directive matters.
*   **Database Health** — Engine version, EOL status, size, and autoload bloat detection.
*   **OPcache Dashboard** — Hit rate, memory usage, cached scripts, and one-click OPcache clearing.
*   **PHP Error Log Viewer** — Browse, search, and clear your PHP error log directly from the dashboard.
*   **WP Cron Monitor** — Find overdue events, orphan hooks, and recently-run cron tasks.
*   **Outbound API Latency Tracker** — Pinpoint slow 3rd-party connections (payment gateways, CRMs, webhooks) that bottleneck page load times.
*   **Mail Deliverability** — Send-test, SPF & DKIM validation.

**Deliver** — Look professional to clients

*   **Audit Report** — Single-page, white-label PDF with custom branding, layout options, and custom brand colors to hand to clients.
*   **Email Alerts** — Get notified on PHP EOL, config drift, OPcache drops, and SSL expiry.
*   **Weekly Digest** — Full server health summary delivered to your inbox every Monday.
*   **Integrations** — Slack, Discord, and Webhook support for real-time alerts.
*   **Multi-Site Dashboard** — Network-wide dashboard widget support on every subsite.


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

*   PHP 7.3 or higher. PHP 7.4 reached end-of-life in November 2022 and is no longer supported.
*   WordPress 5.9 or higher.
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

Health Check & Troubleshooting is the official WordPress.org plugin for this kind of work and a solid tool. Its Troubleshooting Mode is site-wide — when enabled, every visitor sees the default theme with your plugins deactivated until you disable the mode (one click in the admin bar).

phpinfo() WP takes a different approach. Our Troubleshooting Mode is per-user: only your current admin session sees deactivated plugins and the default theme. Every other visitor and admin keeps seeing the live site as normal. That's the design choice we made for debugging plugin conflicts on busy production sites (a live WooCommerce store, a high-traffic publisher) where taking the front-end offline isn't an option.

We also add features Health Check doesn't offer: PHP EOL tracking, A–F config grading with one-click fixes (Pro), an admin-bar health scoreboard, SSL/headers monitoring, and a client-ready PDF audit report.

Use Health Check if you want the official tool with a simple, site-wide debug mode. Use phpinfo() WP if you need a per-user debug session on a live site, plus the broader audit suite.

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
5. Troubleshooting Mode — per-user safe-mode that disables plugins only for your admin session, with a one-click "End and restore".
6. Config Grader full breakdown (Pro) — every failing directive with the exact recommended value and a one-click "Fix this" button.
7. Audit Report (Pro) — single-page white-label PDF you can hand to clients.

== Changelog ==

= 7.2.2 =
*  fixed bugs

= 7.2.1 =
*   **Fixed (Free)**: Config Grader score and grade are now correctly calculated and visible on the free tier dashboard and summary screens, rather than showing a misleading "F (0/100)".
*   **NEW (Pro)**: Web Server Snippet Library now displays a "Code is Injected" status indicator on already applied snippets.
*   **Improved**: Added clear cache callout notifications and messages to the Web Server Snippet Library, prompting you to clear plugin, server, and browser caches to see configuration changes.

= 7.2.0 =
*   **NEW (Pro)**: Web Server Snippet Library — optimized Nginx and Apache configuration blocks for caching, security, and bad bot blocking. Features 1-click injection for Apache/LiteSpeed.
*   **NEW (Pro)**: Outbound API Monitor — track slow 3rd-party services (like Stripe or Mailchimp) that silently slow down your page loads.
*   **NEW (Pro)**: SSL Certificate Monitor — automatically alerts you before your SSL certificates expire so you never lose visitors to a security warning.
*   **NEW (Pro)**: OPcache Dashboard — easily check and clear your PHP OPcache with one click to keep your site's loading speed optimized.
*   **Improved (Free)**: PHP Compatibility Scanner upgraded.
*   **Improved**: Complete dashboard and sidebar menu UI overhaul.
*   **Improved**: Polished and cleaned up the visual layout of the PHP Config Editor and Troubleshooting Mode pages, making margins, spacing, and text sizes consistent and much easier to read.

= 7.1.0 =
*   **Fixed (Pro)**: Config Grader auto-fix no longer shows a false "your host is overriding the auto-fix" warning immediately after applying a fix. `.user.ini` changes can't take effect in the same request and are cached by PHP for a few minutes, so the override check now pauses until the values can actually be observed, then runs automatically.
*   **Fixed (Pro)**: Config Grader no longer offers a one-click fix for `realpath_cache_size` and `realpath_cache_ttl`. These are PHP_INI_SYSTEM directives that can only be set in php.ini — they're now shown as manual steps instead of being written to `.user.ini`/`.htaccess` where PHP silently ignores them.
*   **NEW (Free)**: Update Guard — a pre-update WordPress core audit. Set the core version you plan to upgrade to and scan every plugin and theme for code that breaks on it: removed jQuery APIs and deprecated WordPress functions. Returns a single Safe / Caution / Risky verdict, with per-plugin findings.
*   **NEW (Pro)**: Update Guard adds automatic interception on the WordPress Updates screen before every core update, WordPress.org "tested up to" and abandonment scoring, AI-written fix explanations per finding, uncapped scanning, full per-file/line drill-down, and a cloud rule feed that delivers new WordPress deprecation rules without a plugin update.

= 7.0.5 =
*   **Fixed**: Clicking "Deactivate anyway" inside the retention modal now actually deactivates the plugin instead of reopening the modal in an infinite loop.

= 7.0.4 =
*   **Updated**: The codebase has been fully transpiled downward to support PHP 7.3, allowing legacy servers to run the plugin without fatal syntax errors while maintaining all modern functionality.
*   **Fixed**: "Reports" group menu item in the WordPress sidebar no longer disappears randomly. Fixed an overly broad CSS substring match that caused the "Audit Report" tab's hiding-logic to hide the parent "Reports" menu.
*   **Fixed**: The "Deactivate" retention modal now correctly displays and styles on the main Plugins screen. Fixed an issue where the plugin's stylesheet was artificially blocked from loading on `plugins.php`, breaking the modal's layout.

= 7.0.3 =
*   **NEW**: License page now includes a four-tier comparison table (Free / Single / Unlimited / Lifetime) above the "What Pro unlocks" section so site owners can see exactly what each tier includes.
*   **NEW**: WP Cron Monitor adds a "Purge hook" action on orphan rows (uses `wp_unschedule_hook()`) so removing an orphan actually sticks. Single-row delete couldn't stop recurring orphans because WP reschedules the next instance on fire — the explainer banner in the view now documents this.
*   **NEW**: Activity Log row template now surfaces a plain-English description for every entry. Config Grader auto-fix entries used to render as a generic "EVENT" badge — they now show as "AUTO-FIX" with the directive list. New "Auto-fixes" filter pill.
*   **NEW**: White-label Audit Report now accepts a company logo (Media Library picker, stored as attachment ID + URL) and renders it above the brand name on the report cover.
*   **NEW**: Plugins-screen retention modal — clicking "Deactivate" on the phpinfo() WP row now shows what features stop working immediately, so site owners don't accidentally remove the safety net. Pro users see both Free and Pro feature lists.
*   **UI**: Removed the redundant "✓ Pro active" badge from the Dashboard page (still shown in the admin top bar).
*   **Fixed**: White-label branding form on the Audit Report page used to stay open after save when "Enable white-label" was checked. It now collapses back after save — the success notice confirms the change instead.
*   **Fixed**: Print / Save as PDF on the Audit Report page used to produce a blank page in Chrome and Safari. The print CSS was relying on `visibility:hidden` + `position:absolute` which doesn't escape WP's nested layout containers. Rewrote it with explicit `display:none` on the admin chrome, neutralized `#wpwrap` / `#wpcontent` / `#wpbody-content` margins, added `print-color-adjust:exact` so backgrounds actually render, and added `@page` rules for A4 with 14×12 mm margins.
*   **NEW**: License page Deactivate button now opens a retention modal listing the Pro features that will lock, with a clear "Nothing is deleted" reassurance about what stays on the site.
*   **NEW**: PHP Error Log page now shows a full diagnostic when no log file is found, instead of a generic warning. Tells you which of `WP_DEBUG` / `WP_DEBUG_LOG` / `WP_DEBUG_DISPLAY` are set, the exact paths the plugin checked and why each failed, plus a host-aware summary (most often the truthful answer is "your site has had no PHP errors recently — that's a good thing").
*   **MAJOR**: Config Grader overhauled — it's now a context-aware audit instead of a static checklist. Detects WooCommerce, Elementor, LearnDash, BuddyPress, page builders, big-import tools, and the hosting environment (Kinsta, WP Engine, SiteGround, Cloudways, Pantheon, Flywheel, LiquidWeb, LiteSpeed), then tunes recommendations to that workload — a WooCommerce site now sees `memory_limit ≥ 512M` while a plain blog still sees `≥ 256M`, and an Elementor site sees `max_input_vars ≥ 5000`. Added **cross-directive consistency rules** that catch real foot-guns the per-directive checks miss (`post_max_size ≥ upload_max_filesize`, `memory_limit ≥ post_max_size + 64M headroom`, `max_input_time ≤ max_execution_time`). Added **live-data corroboration** — the grader now reads recent error-log signatures and OPcache stats, escalating severity when reality contradicts the static rule (memory_limit "passes" the threshold but the error log shows OOM kills → escalated to Critical; OPcache memory looks fine but `cache_full` is true → escalated). Replaced the weight 1/2/3 scoring with a **Critical / High / Medium / Low severity matrix** rendered as colour-coded pills. Added new directives: `opcache.jit`, `opcache.jit_buffer_size`, `opcache.huge_code_pages`, `realpath_cache_size`, `realpath_cache_ttl`, `date.timezone`, `output_buffering`, `max_file_uploads`. Added **host-aware remediation** — on managed hosts where the user can't edit php.ini, each failing directive now links to the host's PHP-settings panel with explicit "On SiteGround: Site Tools → Devs → PHP Manager →…" instructions. Added **trend tracking** — every Pro page-load records the score in a 30-day rolling history and the grade card now shows "↓ -3 vs last reading" so weekly reports can tell a regression story. The Fix-all button and per-directive auto-fix now use the context-aware recommendations.
*   **MAJOR**: Audit Report overhauled. The report now opens with an **Overall Site Health score** (one number out of 100, big donut on page 1) — a weighted average across PHP config, security headers, OPcache, SSL, PHP support window, and database health. A **critical-issues banner** at the top promotes things that used to be buried (MariaDB EOL, low OPcache hit rates, expired SSL, world-writable wp-config). A new **"Top 3 actions this week"** section ranks issues by urgency and gives each one a plain-English "Why it matters" + "How to fix" pair. Every metric in the snapshot grid now carries a coloured **✓ OK / ⚠ Warning / ✗ Critical** verdict pill, plus a plain-English caption translating jargon ("Autoload — data WordPress loads on every page request"). Added a new **Site Configuration** section that audits HTTPS, WP_DEBUG_DISPLAY, pending updates (core/plugins/themes), backup-plugin detection (UpdraftPlus / BackWPup / Duplicator / BlogVault / WPvivid / etc.), and file permissions on wp-config.php and .htaccess. Subscores render as a colour-coded bar chart so the strong and weak categories are visible at a glance.

= 7.0.2 =
*   **Fixed**: Sidebar flyout menu (Audit / Tools / Reports hover panels) no longer clips below the viewport when the parent item sits near the bottom of the screen. Flyout now repositions on hover and resize, flips upward when there's no room below, and scrolls internally if it would still overflow.
*   **Fixed**: Readme short description shortened to fit WordPress.org's 150-character limit.

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
*   **NEW (Free)**: Troubleshooting Mode — per-user safe-mode that disables plugins only for your admin session, with a built-in "End and restore" button. Your visitors and other admins continue seeing the live site normally while you debug.
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
Major release. phpinfo() WP is now a full WordPress site-health and server-audit plugin — a modern, actively-maintained take on the Health Check & Troubleshooting workflow. Free adds Troubleshooting Mode (per-user safe-mode that cannot leave your site broken), PHP Compatibility Scanner that works on managed hosts, pre-update PHP-version warnings, PHP EOL Timeline, Config Grader summary, admin-bar health scoreboard, WordPress 7.0 Abilities API integration for AI assistants, and AI explanations on failing Config Grader checks. Pro adds one-click Config Auto-Fix, security headers, SSL monitor, OPcache dashboard, white-label PDF audit reports, and more.
