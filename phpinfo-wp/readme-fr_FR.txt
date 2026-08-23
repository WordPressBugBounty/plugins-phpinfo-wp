=== phpinfo() WP - Site Health, PHP Compatibility & Server Audit ===
Contributors: exeebit
Tags: site health, health check, php compatibility, troubleshooting, phpinfo
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 7.2.7
Requires PHP: 7.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Arrêtez les ruptures de sites silencieuses. L'outil ultime d'audit de serveur intégré et de dépannage par utilisateur, conçu pour les agences et les développeurs professionnels.

== Description ==

**phpinfo() WP** est un plugin WordPress moderne et activement entretenu pour la santé des sites et l'audit des serveurs. Il s'agit de l'outil d'administration exact que les indépendants et les agences installent sur chaque nouveau site pour voir instantanément *ce qui ne va pas, ce qui est sur le point de casser et ce qu'il faut réparer*, le tout sans abonnement SaaS, sans tableaux de bord externes et sans quitter le panneau d'administration WordPress.

Considérez-le comme le plugin officiel **"Health Check & Troubleshooting"**, mais conçu pour les sites de production professionnels. Notre mode de dépannage s'exécute entièrement dans une session par utilisateur. Les visiteurs et les clients continuent de voir le site en direct normalement pendant que vous déboguez et isolez les conflits en toute sécurité sans une seule seconde de temps d'arrêt.

= The free version covers what every WordPress site owner actually needs: =

* **Visionneuse phpinfo()** : propre, consultable et moderne (la fonctionnalité originale, entièrement repensée).
* **Scanner de compatibilité adapté aux hôtes** : analysez tous les plugins et thèmes pour détecter les conflits de version PHP avant la mise à niveau, conçu pour fonctionner sans problème même sur des hôtes gérés strictement.
* **Update Guard** : Suite complète de sécurité avant et après mise à jour. Prévisualisez la stabilité du cœur, analysez les mises à jour en attente des extensions/thèmes contre les ruptures de compatibilité et vérifiez automatiquement l'état du site 60s après chaque mise à jour.
* **Dépannage sans temps d'arrêt** : déboguez en toute sécurité les conflits de thèmes et de plugins dans votre propre session d'administration sans affecter les visiteurs ou les ventes en direct.
* **Chronologie PHP EOL** : date de fin de vie, état actuel et jours restants de chaque version PHP.
* **Résumé de Config Grader** : note globale A-F de votre configuration PHP par rapport aux meilleures pratiques WordPress.
* **Éditeur de configuration PHP (.htaccess / .user.ini)** : définissez ou modifiez les directives php.ini en toute sécurité depuis votre tableau de bord, avec des sauvegardes automatiques et une restauration de sécurité.
* **Tableau de bord de santé de la barre d'administration** : note en direct et problème le plus urgent sur chaque page d'administration, comme PageSpeed ​​pour votre serveur.
* **Widget de tableau de bord** : l'état du site en un coup d'œil dès votre connexion.
* **Journal d'activité, extensions et informations de base** : tout ce que faisait le plugin d'origine, entièrement repensé.
* **API AI-Ready (WP 7.0)** : expose les données d'audit afin que les assistants et plugins IA puissent inspecter l'état du serveur via une interface principale standard.
* **Explications AI (WP 7.0)** : expliquez instantanément les échecs des vérifications en anglais simple à l'aide de la connexion principale du client AI.

= Pro adds the tooling agencies and serious site owners actually need: =

**Sauvegarde : ne cassez pas votre site**

* **Correction automatique en 1 clic avec restauration** : résolvez instantanément les problèmes de configuration. Écrit des règles d'optimisation dans .htaccess ou .user.ini et revient automatiquement si le serveur rencontre une erreur 500.
* **Vérification PHP préalable à la mise à jour** : analysez les mises à jour du plugin avant la mise à niveau pour vérifier qu'elles ne nécessitent pas une version PHP que vous n'avez pas.
* **Update Guard Pro** : Interception automatique sur la page des mises à jour WordPress, analyse des risques de changelog, alertes de plugins abandonnés, diagnostics automatiques de santé 60s après mise à jour (loopback, erreurs, cron) et étapes de correction générées par l'IA.
* **Config Snapshots** : instantanés automatiques hebdomadaires de chaque directive php.ini, avec différences visuelles.
* **Auditeur d'en-têtes de sécurité** : notez vos en-têtes de réponse HTTP (CSP, HSTS, X-Frame-Options) avec des suggestions de correctifs.
* **Bibliothèque d'extraits de serveur Web** : blocs de configuration Nginx et Apache optimisés pour la mise en cache, la sécurité et le blocage des robots malveillants, avec injection en 1 clic pour Apache et LiteSpeed.
* **SSL Certificate Monitor** : suivez l'expiration des certificats et les incompatibilités de domaine pour éviter les avertissements de sécurité.

**Aperçu : sachez ce qui ne va pas avant que les clients n'appellent**

* **Full Config Grader** : notation détaillée avec les valeurs exactes recommandées et pourquoi chaque directive est importante.
* **Santé de la base de données** : version du moteur, état EOL, taille et détection de ballonnement du chargement automatique.
* **Tableau de bord OPcache** : taux de réussite, utilisation de la mémoire, scripts mis en cache et effacement OPcache en un clic.
* **Visionneuse du journal des erreurs PHP** : parcourez, recherchez et effacez votre journal des erreurs PHP directement depuis le tableau de bord.
* **WP Cron Monitor** : recherchez les événements en retard, les hooks orphelins et les tâches cron récemment exécutées.
* **Outbound API Latency Tracker** : identifiez les connexions tierces lentes (passerelles de paiement, CRM, webhooks) qui gênent les temps de chargement des pages.
* **Délivrabilité du courrier** : test d'envoi, validation SPF et DKIM.

**Livrer : avoir l'air professionnel auprès des clients**

* **Rapport d'audit** : rapport d'audit PDF d'une seule page à remettre aux clients (entièrement en marque blanche sur Unlimited ou Lifetime, marqué sur Single).
* **Alertes par e-mail** : soyez averti en cas de fin de vie PHP, de dérive de configuration, de suppression d'OPcache et d'expiration de SSL.
* **Weekly Digest** : résumé complet de l'état du serveur envoyé dans votre boîte de réception chaque lundi (illimité ou à vie).
* **Intégrations** : prise en charge de Slack, Discord et Webhook pour les alertes en temps réel (illimité ou à vie).
* **Tableau de bord multi-sites** : prise en charge des widgets de tableau de bord à l'échelle du réseau sur chaque sous-site.

= Why use this instead of 5 different plugins? =

La plupart des outils de santé des sites WordPress vous obligent à installer des plugins distincts pour la compatibilité PHP, la surveillance SSL, les en-têtes de sécurité, OPcache, les journaux d'erreurs, le cron et les rapports. Chacun est un autre plugin à mettre à jour, un autre élément de menu et un autre ensemble d'options.

phpinfo() WP vous offre **un plugin intégré à l'administrateur** qui couvre tout cela, avec un seul widget de tableau de bord et un seul rapport d'audit PDF. Pas de tableau de bord SaaS externe, pas de frais mensuels par site et pas de connexions séparées.

= Pricing =

* **Site unique** : 29 $/an (1 site, fonctionnalités Pro essentielles, PDF de marque, 3 instantanés, 1 moniteur API)
* **Sites illimités** : 69 $/an (le choix populaire, fonctionne sur tous les sites, instantanés et moniteurs API illimités, entièrement en marque blanche)
* **À vie** : 149 $ une fois (tarif fondateurs, 50 premiers acheteurs, sites illimités, entièrement en marque blanche)

Garantie de remboursement de 14 jours. Livraison instantanée de la licence. Clés de licence verrouillées sur site.

Achetez sur [exeebit.com/phpinfo-wp](https://exeebit.com/phpinfo-wp#pricing).

== Installation ==

1. Install from your WordPress admin: **Plugins -> Add New** and search for *"phpinfo() WP"*.
2. Activate.
3. Find the **phpinfo() WP** menu in your admin sidebar.
4. (Optional) Purchase a Pro license and activate it from **phpinfo() WP -> License**.

Vous pouvez également [télécharger le zip](https://downloads.wordpress.org/plugin/phpinfo-wp.zip) et le télécharger depuis **Plugins -> Ajouter un nouveau -> Télécharger un plugin**.

= Server requirements =

* PHP 7.3 ou supérieur. PHP 7.4 est arrivé en fin de vie en novembre 2022 et n'est plus pris en charge.
* WordPress 5.9 ou supérieur.
* Certaines fonctions PHP utilisées par le plugin peuvent être désactivées par votre hébergeur. Contactez votre hébergeur si les fonctionnalités apparaissent comme indisponibles.
* Pour l'édition .htaccess, la racine de votre site doit être accessible en écriture.

== Frequently Asked Questions ==

= Is the free version still free? =

Oui. La visionneuse phpinfo gratuite, l'éditeur .htaccess, la liste d'extensions, le journal d'activité, la chronologie PHP EOL et le résumé Config Grader sont 100 % gratuits et le seront toujours. Pro est une mise à niveau facultative pour les agences et les propriétaires de sites sérieux.

= Does the Pro version send my data anywhere? =

Non. Tous les audits sont exécutés sur votre propre serveur. La vérification de la licence Pro envoie une requête ping à notre serveur de licences une fois par semaine pour confirmer que votre clé est toujours valide et que rien d'autre n'est transmis.

= Can I use one license on multiple sites? =

La licence Single Site fonctionne sur 1 site. La licence illimitée fonctionne sur tous les sites que vous possédez ou gérez. La licence à vie est également illimitée.

= How does white-label work? =

Sur la page Pro Audit Report, cliquez sur **White-label** pour définir le nom de votre entreprise, le titre du rapport, la couleur d'accentuation et la note de pied de page. Le PDF que vous générez utilise votre marque, pas la nôtre.

= What if my license server is unreachable? =

Le plugin fonctionne correctement pendant 14 jours même si notre serveur de licences est inaccessible. Après cela, les fonctionnalités Pro se verrouillent et vous devrez réactiver la licence. Vous recevrez un avis clair bien avant que cela ne se produise.

= Will this slow down my site? =

Non. Toutes les vérifications s'effectuent uniquement dans le tableau de bord d'administration, et il n'y a aucun impact sur vos performances frontales. Les contrôles lourds (en-têtes de sécurité, SSL) sont mis en cache.

= How is this different from Health Check & Troubleshooting? =

Health Check & Troubleshooting est le plugin WordPress.org officiel pour ce type de travail et constitue un outil solide. Son mode de dépannage s'étend à l'ensemble du site. Lorsqu'il est activé, chaque visiteur voit le thème par défaut avec vos plugins désactivés jusqu'à ce que vous désactiviez le mode via un lien en un clic dans la barre d'administration.

phpinfo() WP adopte une approche différente. Notre mode de dépannage est par utilisateur : seule votre session d'administration actuelle voit les plugins désactivés et le thème par défaut. Tous les autres visiteurs et administrateurs continuent de voir le site en direct normalement. C'est le choix de conception que nous avons fait pour déboguer les conflits de plugins sur des sites de production très fréquentés, tels qu'une boutique WooCommerce en direct ou un éditeur à fort trafic, où la mise hors ligne du front-end n'est pas une option.

Nous ajoutons également des fonctionnalités que Health Check n'offre pas : suivi PHP EOL, évaluation de la configuration A-F avec correctifs en un clic (Pro), un tableau de bord de santé de la barre d'administration, surveillance SSL/en-têtes et un rapport d'audit PDF prêt pour le client.

Utilisez Health Check si vous souhaitez l'outil officiel avec un mode de débogage simple à l'échelle du site. Utilisez phpinfo() WP si vous avez besoin d'une session de débogage par utilisateur sur un site en direct, ainsi que de la suite d'audit plus large.

= How is this different from Query Monitor / WP Umbrella? =

Query Monitor est un outil de développement permettant de déboguer le chargement de pages individuelles, ce qui est un travail différent. WP Umbrella, ManageWP et MainWP sont des tableaux de bord SaaS externes qui facturent par site et par mois, ce qui est utile pour les agences qui veulent tout dans une seule console externe.

phpinfo() WP Pro est destiné au propriétaire du site ou au pigiste qui souhaite un outil intégré à l'administrateur couvrant la santé PHP, la configuration, les en-têtes de sécurité, SSL et un rapport d'audit prêt pour le client, sans abonnement SaaS mensuel et sans quitter l'administrateur WordPress.

= Does this PHP compatibility scanner actually work on my managed host? =

Oui. Contrairement aux scanners qui s'appuient sur PHP_CodeSniffer ou la fonction `exec()`, notre scanner utilise une analyse statique qui s'exécute dans WordPress lui-même. Il fonctionne sur Kinsta, WP Engine, SiteGround, Cloudways, Pantheon et tout autre hôte géré qui restreint l'accès au shell.

= Where do I get support? =

* Gratuit : [Forum d'assistance WordPress.org](https://wordpress.org/support/plugin/phpinfo-wp/).
* Pro : envoyez un e-mail à support@exeebit.com avec votre clé de licence pour une réponse prioritaire.

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
* **Fonctionnalité** : Update Guard enrichi — Ajout de l'audit de compatibilité préalable pour les extensions et thèmes (prérequis PHP/WP, analyse des risques de changelog, détection des abandons), diagnostics de santé automatiques 60s après chaque mise à jour (loopback, accès admin, delta d'erreurs, intégrité cron) et suivi de la stabilité.
* **Correctif** : Renommage des identifiants d'URL (slugs) en `piwp-*` pour éviter les faux positifs 403 Forbidden causés par les pare-feu 8G/7G, BBQ et LiteSpeed bloquant le terme `phpinfo`. Un grand merci à **Simon Richards** pour avoir découvert et signalé ce problème.
* **Amélioration** : Ajout de l'activation et de la désactivation de licence en AJAX fluide avec validation en direct et indicateur de chargement.
* **Amélioration** : Support technique et diagnostics de serveur désormais accessibles à tous les utilisateurs avec signaux d'environnement en temps réel.
* **Correctif** : Correction de l'alignement vertical du texte des boutons sur toutes les pages d'administration.
* **Correctif** : Correction du formatage des espaces et du retour à la ligne dans la visionneuse de journaux d'erreurs PHP.

= 7.2.6 =
* **Compatibilité** : Testé jusqu'à WordPress 7.1.

= 7.2.5 =
* **Amélioration** : Rappel d'augmentation de prix pour le plan Single Site au 31 août.
* **Amélioration** : Les invites de mise à niveau affichent désormais les vrais problèmes de configuration détectés sur votre site.
* **Amélioration** : Retouches visuelles de l'interface d'administration.

= 7.2.4 =
* **NOUVEAU** : Fichiers complets de localisation et de traduction du plugin visuel pour le français, l'allemand, l'espagnol, l'italien et le néerlandais.
* **Amélioré** : optimisation des jauges de cycle de vie EOL du serveur et des audits d'en-tête HTTP.

= 7.2.3 =
* **Pro** : restrictions des fonctionnalités du plan de site unique alignées avec les limites des niveaux tarifaires. Moniteurs d'API sortants plafonnés à 1, instantanés de configuration à 3, webhooks Slack/Discord verrouillés et résumés hebdomadaires, et définition des rapports d'audit PDF sur le style de marque par défaut.
* **Pro** : ajout de droits acquis de licence pour garantir que les titulaires de licences de site unique existants conservent un accès illimité à toutes les fonctionnalités.
* **Amélioré** : marges de mise en page CSS standardisées et optimisées et remplissage vertical sur la page de destination pour une cohérence visuelle sur les écrans de bureau et mobiles.

== Upgrade Notice ==

= 7.0.0 =
Version majeure. phpinfo() WP est désormais un plugin WordPress complet de santé de site et d'audit de serveur, représentant une version moderne et activement maintenue du flux de travail de contrôle de santé et de dépannage. Free ajoute le mode de dépannage (mode sans échec par utilisateur qui ne peut pas laisser votre site cassé), le scanner de compatibilité PHP qui fonctionne sur les hôtes gérés, les avertissements de pré-mise à jour de la version PHP, la chronologie PHP EOL, le résumé de Config Grader, le tableau de bord de santé de la barre d'administration, l'intégration de l'API de capacités WordPress 7.0 pour les assistants IA et des explications d'IA sur les échecs des vérifications de Config Grader. Pro ajoute Config Auto-Fix en un clic, des en-têtes de sécurité, un moniteur SSL, un tableau de bord OPcache, des rapports d'audit PDF en marque blanche, et bien plus encore.
