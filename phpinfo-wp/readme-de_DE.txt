=== phpinfo() WP - Site Health, PHP Compatibility & Server Audit ===
Contributors: exeebit
Tags: site health, health check, php compatibility, troubleshooting, phpinfo
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 7.2.7
Requires PHP: 7.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Stoppen Sie stillschweigende Seitenbrüche. Das ultimative In-Admin-Server-Audit- und benutzerspezifische Fehlerbehebungstool für Agenturen und professionelle Entwickler.

== Description ==

**phpinfo() WP** ist ein modernes, aktiv gepflegtes WordPress-Plugin für den Zustand der Website und die Serverprüfung. Es handelt sich um das exakte In-Administrator-Tool, das Freiberufler und Agenturen auf jeder neuen Website installieren, um sofort zu sehen, *was falsch ist, was bald kaputt geht und was repariert werden muss*, alles ohne SaaS-Abonnement, ohne externe Dashboards und ohne das WordPress-Admin-Panel zu verlassen.

Betrachten Sie es als das offizielle **„Health Check & Troubleshooting“-Plugin**, das jedoch für professionelle Produktionsstandorte entwickelt wurde. Unser Fehlerbehebungsmodus läuft vollständig in einer Sitzung pro Benutzer. Besucher und Kunden sehen die Live-Site weiterhin normal, während Sie Konflikte ohne eine einzige Ausfallzeit sicher debuggen und isolieren.

= The free version covers what every WordPress site owner actually needs: =

* **phpinfo()-Viewer**: Sauber, durchsuchbar und modern (die ursprüngliche Funktion, komplett neu gestaltet).
* **Hostfreundlicher Kompatibilitätsscanner**: Scannen Sie alle Plugins und Themes vor dem Upgrade auf PHP-Versionskonflikte und funktionieren Sie auch auf streng verwalteten Hosts reibungslos.
* **Update Guard**: Vollständige Sicherheits-Suite vor und nach Aktualisierungen. Prüfen Sie die Core-Upgrade-Stabilität, scannen Sie anstehende Plugin-/Theme-Updates auf Inkompatibilitäten und überprüfen Sie den Website-Zustand 60 Sekunden nach jedem Update automatisch.
* **Fehlerbehebung ohne Ausfallzeiten**: Beheben Sie Theme- und Plugin-Konflikte sicher in Ihrer eigenen Admin-Sitzung, ohne Besucher oder Live-Verkäufe zu beeinträchtigen.
* **PHP EOL-Zeitleiste**: End-of-Life-Datum, aktueller Status und verbleibende Tage jeder PHP-Version.
* **Config Grader-Zusammenfassung**: Gesamtnote A–F Ihrer PHP-Konfiguration im Vergleich zu den Best Practices von WordPress.
* **PHP-Konfigurationseditor (.htaccess / .user.ini)**: Legen Sie php.ini-Anweisungen sicher über Ihr Dashboard fest oder ändern Sie sie, mit automatischen Backups und Sicherheits-Rollback.
* **Anzeigetafel für den Zustand der Admin-Leiste**: Live-Bewertung und dringlichstes Problem auf jeder Admin-Seite, z. B. PageSpeed ​​für Ihren Server.
* **Dashboard-Widget**: Der Website-Zustand auf einen Blick, sobald Sie sich anmelden.
* **Aktivitätsprotokoll, Erweiterungen und grundlegende Informationen**: Alles, was das ursprüngliche Plugin tat, komplett neu gestaltet.
* **AI-Ready API (WP 7.0)**: Stellt Prüfdaten zur Verfügung, damit KI-Assistenten und Plugins den Serverzustand über eine Standard-Kernschnittstelle überprüfen können.
* **AI-Erklärungen (WP 7.0)**: Erklären Sie fehlgeschlagene Prüfungen sofort in einfachem Englisch über die Kern-AI-Client-Verbindung.

= Pro adds the tooling agencies and serious site owners actually need: =

**Vorsicht: Beschädigen Sie Ihre Website nicht**

* **1-Klick-Auto-Fix mit Rollback**: Beheben Sie Konfigurationsprobleme sofort. Schreibt Optimierungsregeln in .htaccess oder .user.ini und setzt sie automatisch zurück, wenn der Server auf einen 500-Fehler stößt.
* **PHP-Prüfung vor dem Update**: Scannen Sie Plugin-Updates vor dem Upgrade, um sicherzustellen, dass sie keine PHP-Version erfordern, die Sie nicht haben.
* **Update Guard Pro**: Automatisches Abfangen auf der Seite „WordPress-Updates“, Risikobewertung von Changelog-Änderungen, Warnmeldungen zu verwaisten Plugins, automatische 60-Sekunden-Zustandsprüfungen nach Updates (Loopback, Fehlerprotokoll, Cron) und von der KI geschriebene Korrekturschritte.
* **Konfigurations-Snapshots**: Wöchentliche automatische Snapshots jeder php.ini-Direktive mit visuellen Unterschieden.
* **Security Headers Auditor**: Bewerten Sie Ihre HTTP-Antwortheader (CSP, HSTS, X-Frame-Optionen) mit Korrekturvorschlägen.
* **Webserver-Snippet-Bibliothek**: Optimierte Nginx- und Apache-Konfigurationsblöcke für Caching, Sicherheit und Blockierung fehlerhafter Bots, mit 1-Klick-Injection für Apache und LiteSpeed.
* **SSL-Zertifikatmonitor**: Verfolgen Sie den Ablauf von Zertifikaten und Domänenkonflikte, um Sicherheitswarnungen zu vermeiden.

**Einblick: Wissen Sie, was falsch ist, bevor Kunden anrufen**

* **Full Config Grader**: Detaillierte Bewertung mit den genauen empfohlenen Werten und warum jede Direktive wichtig ist.
* **Datenbankzustand**: Engine-Version, EOL-Status, Größe und Autoload-Bloat-Erkennung.
* **OPcache-Dashboard**: Trefferquote, Speichernutzung, zwischengespeicherte Skripte und OPcache-Löschen mit einem Klick.
* **PHP-Fehlerprotokoll-Viewer**: Durchsuchen, durchsuchen und löschen Sie Ihr PHP-Fehlerprotokoll direkt über das Dashboard.
* **WP Cron Monitor**: Finden Sie überfällige Ereignisse, verwaiste Hooks und kürzlich ausgeführte Cron-Aufgaben.
* **Outbound API Latency Tracker**: Erkennen Sie langsame Verbindungen von Drittanbietern (Zahlungsgateways, CRMs, Webhooks), die zu Engpässen bei den Seitenladezeiten führen.
* **Mail-Zustellbarkeit**: Sendetest, SPF- und DKIM-Validierung.

**Liefern: Für Kunden professionell wirken**

* **Auditbericht**: Einseitiger PDF-Auditbericht zur Aushändigung an Kunden (vollständig weiß gekennzeichnet bei Unlimited oder Lifetime, gebrandet bei Single).
* **E-Mail-Benachrichtigungen**: Lassen Sie sich über PHP-EOL, Konfigurationsdrift, OPcache-Drops und SSL-Ablauf benachrichtigen.
* **Wöchentliche Übersicht**: Vollständige Zusammenfassung des Serverzustands wird jeden Montag in Ihren Posteingang geliefert (unbegrenzt oder lebenslang).
* **Integrationen**: Slack-, Discord- und Webhook-Unterstützung für Echtzeitwarnungen (unbegrenzt oder lebenslang).
* **Multi-Site-Dashboard**: Netzwerkweite Dashboard-Widget-Unterstützung auf jeder Unterseite.

= Why use this instead of 5 different plugins? =

Die meisten WordPress-Site-Health-Tools zwingen Sie zur Installation separater Plugins für PHP-Kompatibilität, SSL-Überwachung, Sicherheitsheader, OPcache, Fehlerprotokolle, Cron und Berichte. Jedes davon ist ein weiteres zu aktualisierendes Plugin, ein weiterer Menüpunkt und ein weiterer Satz an Optionen.

phpinfo() WP bietet Ihnen **ein In-Admin-Plugin**, das alles abdeckt, mit einem einzigen Dashboard-Widget und einem einzigen PDF-Auditbericht. Kein externes SaaS-Dashboard, keine monatlichen Gebühren pro Site und keine separaten Anmeldungen.

= Pricing =

* **Einzelne Site**: 29 $/Jahr (1 Site, wesentliche Pro-Funktionen, gebrandetes PDF, 3 Snapshots, 1 API-Monitor)
* **Unbegrenzte Sites**: 69 $/Jahr (die beliebte Wahl, funktioniert auf jeder Site, vollständig weiß gekennzeichnet, unbegrenzte Snapshots und API-Monitore)
* **Lebenslang**: einmalig 149 $ (Gründerpreis, erste 50 Käufer, unbegrenzte Websites, vollständig White-Label)

14-tägige Geld-zurück-Garantie. Sofortige Lizenzlieferung. Site-gesperrte Lizenzschlüssel.

Kaufen Sie bei [exeebit.com/phpinfo-wp](https://exeebit.com/phpinfo-wp#pricing).

== Installation ==

1. Install from your WordPress admin: **Plugins -> Add New** and search for *"phpinfo() WP"*.
2. Activate.
3. Find the **phpinfo() WP** menu in your admin sidebar.
4. (Optional) Purchase a Pro license and activate it from **phpinfo() WP -> License**.

Sie können die ZIP-Datei auch [herunterladen](https://downloads.wordpress.org/plugin/phpinfo-wp.zip) und über **Plugins -> Neu hinzufügen -> Plugin hochladen** hochladen.

= Server requirements =

* PHP 7.3 oder höher. PHP 7.4 hat im November 2022 das Ende seiner Lebensdauer erreicht und wird nicht mehr unterstützt.
* WordPress 5.9 oder höher.
* Einige vom Plugin verwendete PHP-Funktionen sind möglicherweise von Ihrem Host deaktiviert. Wenden Sie sich an Ihren Gastgeber, wenn Funktionen als nicht verfügbar angezeigt werden.
* Für die .htaccess-Bearbeitung muss Ihr Site-Stammverzeichnis beschreibbar sein.

== Frequently Asked Questions ==

= Is the free version still free? =

Ja. Der kostenlose phpinfo-Viewer, der .htaccess-Editor, die Erweiterungsliste, das Aktivitätsprotokoll, die PHP-EOL-Timeline und die Config Grader-Zusammenfassung sind 100 % kostenlos und werden es auch immer bleiben. Pro ist ein optionales Upgrade für Agenturen und ernsthafte Websitebesitzer.

= Does the Pro version send my data anywhere? =

Nein. Alle Audits laufen auf Ihrem eigenen Server. Die Pro-Lizenzprüfung pingt einmal pro Woche unseren Lizenzserver, um zu bestätigen, dass Ihr Schlüssel noch gültig ist und nichts anderes übertragen wird.

= Can I use one license on multiple sites? =

Die Single-Site-Lizenz funktioniert auf einer Site. Die Unlimited-Lizenz funktioniert auf jeder Site, die Sie besitzen oder verwalten. Die Lifetime-Lizenz ist ebenfalls unbegrenzt.

= How does white-label work? =

Klicken Sie auf der Seite „Pro-Audit-Bericht“ auf **White-Label**, um Ihren Firmennamen, den Berichtstitel, die Akzentfarbe und die Fußzeile festzulegen. Das von Ihnen generierte PDF verwendet Ihr Branding, nicht unseres.

= What if my license server is unreachable? =

Das Plugin funktioniert 14 Tage lang einwandfrei, auch wenn unser Lizenzserver nicht erreichbar ist. Danach werden die Pro-Funktionen gesperrt und Sie müssen die Lizenz erneut aktivieren. Sie erhalten rechtzeitig vorher eine klare Mitteilung.

= Will this slow down my site? =

Nein. Alle Prüfungen werden nur im Admin-Dashboard ausgeführt und haben keinerlei Auswirkungen auf die Leistung Ihres Front-Ends. Schwere Prüfungen (Sicherheitsheader, SSL) werden zwischengespeichert.

= How is this different from Health Check & Troubleshooting? =

Health Check & Troubleshooting ist das offizielle WordPress.org-Plugin für diese Art von Arbeit und ein solides Werkzeug. Der Fehlerbehebungsmodus ist standortweit gültig. Wenn diese Option aktiviert ist, sieht jeder Besucher das Standardthema mit deaktivierten Plugins, bis Sie den Modus über einen Ein-Klick-Link in der Admin-Leiste deaktivieren.

phpinfo() WP verfolgt einen anderen Ansatz. Unser Fehlerbehebungsmodus ist pro Benutzer: Nur Ihre aktuelle Admin-Sitzung sieht deaktivierte Plugins und das Standarddesign. Jeder andere Besucher und Administrator sieht die Live-Site weiterhin wie gewohnt. Dies ist die Design-Wahl, die wir zum Debuggen von Plugin-Konflikten auf stark ausgelasteten Produktionsstandorten getroffen haben, z. B. in einem Live-WooCommerce-Shop oder einem stark frequentierten Verlag, bei dem es keine Option ist, das Front-End offline zu schalten.

Wir fügen auch Funktionen hinzu, die Health Check nicht bietet: PHP-EOL-Tracking, A-F-Konfigurationsbewertung mit Ein-Klick-Korrekturen (Pro), eine Gesundheitsanzeigetafel in der Admin-Leiste, SSL-/Header-Überwachung und einen clientfertigen PDF-Auditbericht.

Verwenden Sie Health Check, wenn Sie das offizielle Tool mit einem einfachen, standortweiten Debugmodus wünschen. Verwenden Sie phpinfo() WP, wenn Sie eine Debug-Sitzung pro Benutzer auf einer Live-Site sowie die umfassendere Audit-Suite benötigen.

= How is this different from Query Monitor / WP Umbrella? =

Query Monitor ist ein Entwicklertool zum Debuggen einzelner Seitenladevorgänge, was eine andere Aufgabe ist. WP Umbrella, ManageWP und MainWP sind externe SaaS-Dashboards, die pro Standort und Monat abrechnen, was für Agenturen nützlich ist, die alles in einer externen Konsole haben möchten.

phpinfo() WP Pro ist für Websitebesitzer oder Freiberufler gedacht, die ein In-Administrator-Tool wünschen, das den PHP-Zustand, die Konfiguration, Sicherheitsheader, SSL und einen clientfertigen Prüfbericht abdeckt, ohne ein monatliches SaaS-Abonnement und ohne den WordPress-Administrator zu verlassen.

= Does this PHP compatibility scanner actually work on my managed host? =

Ja. Im Gegensatz zu Scannern, die auf PHP_CodeSniffer oder die Funktion „exec()“ basieren, verwendet unser Scanner eine statische Analyse, die in WordPress selbst ausgeführt wird. Es funktioniert auf Kinsta, WP Engine, SiteGround, Cloudways, Pantheon und jedem anderen verwalteten Host, der den Shell-Zugriff einschränkt.

= Where do I get support? =

* Kostenlos: [WordPress.org-Supportforum](https://wordpress.org/support/plugin/phpinfo-wp/).
* Pro: Senden Sie eine E-Mail an support@exeebit.com mit Ihrem Lizenzschlüssel, um eine vorrangige Antwort zu erhalten.

== Screenshots ==

1. Dashboard widget: PHP version, EOL status, and Config Grade at a glance.
2. phpinfo() viewer: clean, searchable, and modern.
3. Config Grader summary: your site's A-F grade across Performance, Security, and OPcache.
4. PHP EOL Timeline: every PHP version's end-of-life date and days remaining.
5. Troubleshooting Mode: per-user safe-mode that disables plugins only for your admin session, with a one-click "End and restore" button.
6. Config Grader full breakdown (Pro): every failing directive with the exact recommended value and a one-click "Fix this" button.
7. Audit Report (Pro): single-page white-label PDF you can hand to clients.

== Changelog ==

= 7.2.7 =
* **Funktion**: Erweiterter Update Guard — Kompatibilitätsscans vor dem Update für Plugins & Themes (PHP/WP-Anforderungen, Changelog-Risikoanalyse, Erkennung verwaister Plugins), automatische 60-Sekunden-Zustandsprüfungen nach Updates (Loopback, Admin-Erreichbarkeit, Fehlerprotokolldelta, Cron-Integrität) und Stabilitätsbewertung hinzugefügt.
* **Behoben**: Admin-Seiten-Slugs wurden in `piwp-*` umbenannt, um 403-Forbidden-Fehlalarme durch 8G/7G-Firewalls, BBQ und LiteSpeed-Regeln zu verhindern, die den Begriff `phpinfo` blockieren. Besonderer Dank geht an **Simon Richards** für das Entdecken und Melden dieses Problems.
* **Verbesserung**: Nahtlose AJAX-Lizenzaktivierung und -deaktivierung mit direkter Validierung und Ladeanzeige hinzugefügt.
* **Verbesserung**: Technischer Support und Serverdiagnose für alle Benutzer mit Live-Umgebungssignalen zugänglich gemacht.
* **Behoben**: Vertikale Ausrichtung des Schaltflächentextes auf den Admin-Seiten korrigiert.
* **Behoben**: Leerzeichenformatierung und Zeilenumbruch im PHP-Fehlerprotokoll-Viewer korrigiert.

= 7.2.6 =
* **Kompatibilität**: Getestet bis WordPress 7.1.

= 7.2.5 =
* **Verbessert**: Preiserhöhungserinnerung für den Single-Site-Plan zum 31. August.
* **Verbessert**: Upgrade-Aufforderungen zeigen nun reale Konfigurationsprobleme Ihrer Website an.
* **Verbessert**: Visuelle Optimierungen der Admin-Oberfläche.

= 7.2.4 =
* **NEU**: Vollständige visuelle Plugin-Lokalisierung und Übersetzungsdateien für Französisch, Deutsch, Spanisch, Italienisch und Niederländisch.
* **Verbessert**: Optimierte Server-EOL-Lebenszyklusanzeigen und HTTP-Header-Audits.

= 7.2.3 =
* **Pro**: Die Funktionseinschränkungen des Single-Site-Plans wurden an die Preisstufengrenzen angepasst. Ausgehende API-Monitore auf 1, Konfigurations-Snapshots auf 3 begrenzt, Slack/Discord-Webhooks und wöchentliche Digests gesperrt und PDF-Prüfberichte auf das standardmäßige Markendesign eingestellt.
* **Pro**: Lizenz-Grandfathering hinzugefügt, um sicherzustellen, dass bestehende Single-Site-Lizenzinhaber uneingeschränkten Zugriff auf alle Funktionen behalten.
* **Verbessert**: Standardisierte und optimierte CSS-Layoutränder und vertikale Auffüllung auf der Zielseite für visuelle Konsistenz auf Desktop- und Mobilbildschirmen.

== Upgrade Notice ==

= 7.0.0 =
Hauptveröffentlichung. phpinfo() WP ist jetzt ein vollständiges WordPress-Site-Health- und Server-Audit-Plugin, das eine moderne, aktiv gepflegte Version des Health Check & Troubleshooting-Workflows darstellt. Free bietet einen Fehlerbehebungsmodus (sicherer Modus pro Benutzer, der Ihre Website nicht kaputt machen kann), einen PHP-Kompatibilitätsscanner, der auf verwalteten Hosts funktioniert, PHP-Versionswarnungen vor dem Update, eine PHP-EOL-Zeitleiste, eine Config Grader-Zusammenfassung, eine Gesundheitsanzeigetafel in der Admin-Leiste, die WordPress 7.0 Abilities API-Integration für KI-Assistenten und KI-Erklärungen bei fehlgeschlagenen Config Grader-Prüfungen. Pro bietet One-Click-Config-Auto-Fix, Sicherheitsheader, SSL-Monitor, OPcache-Dashboard, White-Label-PDF-Auditberichte und mehr.
