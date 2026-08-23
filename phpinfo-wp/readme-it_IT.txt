=== phpinfo() WP - Site Health, PHP Compatibility & Server Audit ===
Contributors: exeebit
Tags: site health, health check, php compatibility, troubleshooting, phpinfo
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 7.2.7
Requires PHP: 7.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Interrompi le interruzioni silenziose del sito. Lo strumento definitivo per il controllo del server interno e la risoluzione dei problemi per utente creato per agenzie e sviluppatori professionisti.

== Description ==

**phpinfo() WP** è un plugin moderno e gestito attivamente per lo stato del sito WordPress e il controllo del server. È lo strumento di amministrazione esatto che i freelance e le agenzie installano su ogni nuovo sito per vedere immediatamente *cosa c'è che non va, cosa sta per rompersi e cosa riparare*, il tutto senza un abbonamento SaaS, senza dashboard esterni e senza uscire dal pannello di amministrazione di WordPress.

Consideralo come il plugin ufficiale **"Controllo dello stato e risoluzione dei problemi"**, ma creato per siti di produzione professionali. La nostra modalità di risoluzione dei problemi viene eseguita completamente in una sessione per utente. Visitatori e clienti continuano a vedere normalmente il sito live mentre esegui il debug e isoli i conflitti in modo sicuro senza un solo secondo di inattività.

= The free version covers what every WordPress site owner actually needs: =

* **visualizzatore phpinfo()**: pulito, ricercabile e moderno (la funzionalità originale, completamente rinnovata).
* **Scansione compatibilità host**: scansiona tutti i plugin e i temi per individuare eventuali conflitti tra versioni PHP prima dell'aggiornamento, realizzato per funzionare senza problemi anche su host gestiti in modo rigido.
* **Update Guard**: Suite completa di sicurezza pre e post-aggiornamento. Visualizza in anteprima la stabilità del core, scansiona gli aggiornamenti di plugin/temi in sospeso per rilevare modifiche critiche e verifica automaticamente l'integrità del sito 60 secondi dopo ogni aggiornamento.
* **Risoluzione dei problemi con tempi di inattività pari a zero**: esegui il debug in modo sicuro dei conflitti di temi e plug-in nella tua sessione di amministrazione senza influire sui visitatori o sulle vendite in tempo reale.
* **Cronologia EOL PHP**: data di fine vita di ogni versione PHP, stato attuale e giorni rimanenti.
* **Riepilogo del valutatore di configurazione**: voto complessivo A-F della configurazione PHP rispetto alle migliori pratiche di WordPress.
* **Editor di configurazione PHP (.htaccess / .user.ini)**: imposta o modifica le direttive php.ini in modo sicuro dalla dashboard, con backup automatici e rollback di sicurezza.
* **Quadro di valutazione dello stato della barra di amministrazione**: valutazione in tempo reale e problema più urgente su ogni pagina di amministrazione, come PageSpeed ​​per il tuo server.
* **Widget dashboard**: stato del sito a colpo d'occhio nel momento in cui accedi.
* **Registro attività, estensioni e informazioni di base**: tutto ciò che faceva il plugin originale, completamente rinnovato.
* **API AI-Ready (WP 7.0)**: espone i dati di controllo in modo che gli assistenti e i plug-in AI possano controllare l'integrità del server tramite un'interfaccia core standard.
* **Spiegazioni AI (WP 7.0)**: spiega istantaneamente i controlli falliti in un inglese semplice utilizzando la connessione client AI principale.

= Pro adds the tooling agencies and serious site owners actually need: =

**Protezione: non danneggiare il tuo sito**

* **Correzione automatica con rollback in 1 clic**: risolvi istantaneamente i problemi di configurazione. Scrive le regole di ottimizzazione su .htaccess o .user.ini e si ripristina automaticamente se il server rileva un errore 500.
* **Verifica PHP pre-aggiornamento**: esegui la scansione degli aggiornamenti dei plugin prima dell'aggiornamento per verificare che non richiedano una versione PHP che non disponi.
* **Update Guard Pro**: Intercettazione automatica nella pagina degli aggiornamenti di WordPress, analisi dei rischi nel changelog, avvisi per plugin abbandonati, diagnostica automatica dello stato 60s dopo l'aggiornamento (loopback, errori, cron) e passaggi di correzione scritti dall'IA.
* **Istantanee di configurazione**: istantanee automatiche settimanali di ogni direttiva php.ini, con differenze visive.
* **Revisore delle intestazioni di sicurezza**: valuta le intestazioni di risposta HTTP (CSP, HSTS, X-Frame-Options) con suggerimenti di correzione.
* **Libreria di frammenti di server Web**: blocchi di configurazione Nginx e Apache ottimizzati per memorizzazione nella cache, sicurezza e blocco di bot dannosi, con iniezione in 1 clic per Apache e LiteSpeed.
* **Monitoraggio certificato SSL**: monitora la scadenza del certificato e le mancate corrispondenze del dominio per evitare avvisi di sicurezza.

**Insight: sapere cosa c'è che non va prima che i clienti chiamino**

* **Valutatore di configurazione completo**: valutazione dettagliata con i valori esatti consigliati e il motivo per cui ogni direttiva è importante.
* **Integrità del database**: versione del motore, stato EOL, dimensioni e rilevamento del sovraccarico del caricamento automatico.
* **Dashboard OPcache**: percentuale di successo, utilizzo della memoria, script memorizzati nella cache e cancellazione di OPcache con un clic.
* **Visualizzatore registro errori PHP**: sfoglia, cerca e cancella il registro errori PHP direttamente dalla dashboard.
* **WP Cron Monitor**: trova eventi scaduti, hook orfani e attività cron eseguite di recente.
* **Tracciatore latenza API in uscita**: individua le connessioni lente di terze parti (gateway di pagamento, CRM, webhook) che rallentano i tempi di caricamento delle pagine.
* **Recapitabilità della posta**: test di invio, convalida SPF e DKIM.

**Consegna: aspetto professionale ai clienti**

* **Rapporto di audit**: rapporto di audit PDF di una pagina da consegnare ai clienti (completamente con etichetta bianca su Unlimited o Lifetime, con marchio su Single).
* **Avvisi e-mail**: ricevi notifiche su PHP EOL, deriva della configurazione, cadute di OPcache e scadenza SSL.
* **Digest settimanale**: riepilogo completo dello stato del server consegnato nella tua casella di posta ogni lunedì (illimitato o a vita).
* **Integrazioni**: supporto Slack, Discord e Webhook per avvisi in tempo reale (illimitato o a vita).
* **Dashboard multisito**: supporto widget dashboard a livello di rete su ogni sito secondario.

= Why use this instead of 5 different plugins? =

La maggior parte degli strumenti per la salute dei siti WordPress ti obbliga a installare plugin separati per compatibilità PHP, monitoraggio SSL, intestazioni di sicurezza, OPcache, log degli errori, cron e report. Ognuno è un altro plugin da aggiornare, un'altra voce di menu e un altro set di opzioni.

phpinfo() WP ti offre **un plugin interno** che copre tutto, con un unico widget dashboard e un unico rapporto di controllo PDF. Nessuna dashboard SaaS esterna, nessuna tariffa mensile per sito e nessun accesso separato.

= Pricing =

* **Sito singolo**: $ 29/anno (1 sito, funzionalità Pro essenziali, PDF brandizzato, 3 istantanee, 1 monitor API)
* **Siti illimitati**: $ 69/anno (la scelta più popolare, funziona su ogni sito, completamente white label, istantanee e monitor API illimitati)
* **A vita**: $ 149 una volta (prezzi per i fondatori, primi 50 acquirenti, siti illimitati, completamente white label)

Garanzia di rimborso di 14 giorni. Consegna immediata della licenza. Chiavi di licenza bloccate dal sito.

Acquista su [exeebit.com/phpinfo-wp](https://exeebit.com/phpinfo-wp#pricing).

== Installation ==

1. Install from your WordPress admin: **Plugins -> Add New** and search for *"phpinfo() WP"*.
2. Activate.
3. Find the **phpinfo() WP** menu in your admin sidebar.
4. (Optional) Purchase a Pro license and activate it from **phpinfo() WP -> License**.

Puoi anche [scaricare il file zip](https://downloads.wordpress.org/plugin/phpinfo-wp.zip) e caricarlo da **Plugins -> Aggiungi nuovo -> Carica plugin**.

= Server requirements =

*PHP 7.3 o versione successiva. PHP 7.4 ha raggiunto la fine del suo ciclo di vita nel novembre 2022 e non è più supportato.
* WordPress 5.9 o versione successiva.
* Alcune funzioni PHP utilizzate dal plugin potrebbero essere disabilitate dal tuo host. Contatta il tuo host se le funzionalità risultano non disponibili.
* Per la modifica di .htaccess, la root del tuo sito deve essere scrivibile.

== Frequently Asked Questions ==

= Is the free version still free? =

SÌ. Il visualizzatore phpinfo gratuito, l'editor .htaccess, l'elenco delle estensioni, il registro delle attività, la sequenza temporale EOL di PHP e il riepilogo del valutatore di configurazione sono gratuiti al 100% e lo saranno sempre. Pro è un aggiornamento opzionale per agenzie e proprietari di siti seri.

= Does the Pro version send my data anywhere? =

No. Tutti gli audit vengono eseguiti sul tuo server. Il controllo della licenza Pro esegue il ping del nostro server delle licenze una volta alla settimana per confermare che la chiave è ancora valida e non viene trasmesso nient'altro.

= Can I use one license on multiple sites? =

La licenza per sito singolo funziona su 1 sito. La licenza Illimitata funziona su ogni sito che possiedi o gestisci. Anche la licenza Lifetime è illimitata.

= How does white-label work? =

Nella pagina Pro Audit Report, fai clic su **White-label** per impostare il nome della tua azienda, il titolo del report, il colore in risalto e la nota a piè di pagina. Il PDF che generi utilizza il tuo marchio, non il nostro.

= What if my license server is unreachable? =

Il plugin funziona bene per 14 giorni anche se il nostro server delle licenze non è raggiungibile. Successivamente, le funzionalità Pro si bloccheranno e dovrai riattivare la licenza. Riceverai un avviso chiaro molto prima che ciò accada.

= Will this slow down my site? =

No. Tutti i controlli vengono eseguiti solo all'interno del dashboard di amministrazione e non hanno alcun impatto sulle prestazioni front-end. I controlli pesanti (intestazioni di sicurezza, SSL) vengono memorizzati nella cache.

= How is this different from Health Check & Troubleshooting? =

Health Check & Risoluzione dei problemi è il plugin ufficiale di WordPress.org per questo tipo di lavoro ed è uno strumento solido. La sua modalità di risoluzione dei problemi è estesa a tutto il sito. Quando abilitato, ogni visitatore vede il tema predefinito con i plugin disattivati ​​finché non disabiliti la modalità tramite un collegamento con un clic nella barra di amministrazione.

phpinfo() WP adotta un approccio diverso. La nostra modalità di risoluzione dei problemi è per utente: solo la sessione di amministrazione corrente vede i plugin disattivati ​​e il tema predefinito. Tutti gli altri visitatori e amministratori continuano a vedere il sito live normalmente. Questa è la scelta progettuale che abbiamo fatto per il debug dei conflitti dei plugin su siti di produzione molto affollati, come un negozio WooCommerce attivo o un editore ad alto traffico, dove portare offline il front-end non è un'opzione.

Aggiungiamo anche funzionalità che Health Check non offre: tracciamento EOL PHP, valutazione della configurazione A-F con correzioni con un clic (Pro), un quadro di valutazione dello stato della barra di amministrazione, monitoraggio SSL/intestazioni e un rapporto di controllo PDF pronto per il client.

Utilizza Health Check se desideri lo strumento ufficiale con una semplice modalità di debug a livello di sito. Utilizza phpinfo() WP se hai bisogno di una sessione di debug per utente su un sito live, oltre alla suite di audit più ampia.

= How is this different from Query Monitor / WP Umbrella? =

Query Monitor è uno strumento di sviluppo per il debug dei caricamenti di singole pagine, che è un lavoro diverso. WP Umbrella, ManageWP e MainWP sono dashboard SaaS esterni che fatturano per sito, al mese, il che è utile per le agenzie che desiderano tutto in un'unica console esterna.

phpinfo() WP Pro è per il proprietario del sito o il libero professionista che desidera uno strumento di amministrazione che copra l'integrità di PHP, la configurazione, le intestazioni di sicurezza, SSL e un rapporto di controllo pronto per il cliente, senza un abbonamento SaaS mensile e senza lasciare l'amministratore di WordPress.

= Does this PHP compatibility scanner actually work on my managed host? =

SÌ. A differenza degli scanner che si basano su PHP_CodeSniffer o sulla funzione `exec()`, il nostro scanner utilizza l'analisi statica che viene eseguita all'interno di WordPress stesso. Funziona su Kinsta, WP Engine, SiteGround, Cloudways, Pantheon e ogni altro host gestito che limita l'accesso alla shell.

= Where do I get support? =

* Gratuito: [Forum di supporto di WordPress.org](https://wordpress.org/support/plugin/phpinfo-wp/).
* Pro: invia un'e-mail a support@exeebit.com con la chiave di licenza per una risposta prioritaria.

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
* **Funzionalità**: Update Guard arricchito — Aggiunta scansione di compatibilità pre-aggiornamento per plugin e temi (requisiti PHP/WP, analisi dei rischi nel changelog, controllo plugin abbandonati), diagnostica automatica dello stato del sito 60s dopo l'aggiornamento (loopback, accesso admin, delta errori, integrità cron) e punteggio di stabilità.
* **Risolto**: Ridenominati gli slug delle pagine di amministrazione in `piwp-*` per prevenire falsi positivi 403 Forbidden causati da firewall 8G/7G, BBQ e LiteSpeed che bloccano il termine `phpinfo`. Un ringraziamento speciale a **Simon Richards** per aver scoperto e segnalato questo problema.
* **Miglioramento**: Aggiunta attivazione e disattivazione licenza tramite AJAX fluido con validazione istantanea e indicatore di caricamento.
* **Miglioramento**: Supporto tecnico e diagnostica del server resi accessibili a tutti gli utenti con segnali di ambiente in tempo reale.
* **Risolto**: Corretto l'allineamento verticale del testo nei pulsanti nelle schermate di amministrazione.
* **Risolto**: Corretta la formattazione degli spazi vuoti e l'a capo nel visualizzatore del log degli errori PHP.

= 7.2.6 =
* **Compatibilità**: Testato fino a WordPress 7.1.

= 7.2.5 =
* **Migliorato**: Promemoria aumento di prezzo per il piano Single Site al 31 agosto.
* **Migliorato**: I messaggi di aggiornamento ora mostrano i problemi di configurazione reali rilevati sul tuo sito.
* **Migliorato**: Ritocchi visivi all'interfaccia di amministrazione.

= 7.2.4 =
* **NOVITÀ**: localizzazione completa dei plugin visivi e file di traduzione per francese, tedesco, spagnolo, italiano e olandese.
* **Migliorato**: indicatori del ciclo di vita EOL del server ottimizzati e controlli delle intestazioni HTTP.

= 7.2.3 =
* **Pro**: restrizioni sulle funzionalità del piano per sito singolo allineate con i limiti del livello di prezzo. Monitor API in uscita limitati a 1, istantanee di configurazione a 3, webhook Slack/Discord bloccati e digest settimanali e impostazione dei report di controllo PDF sullo stile predefinito del marchio.
* **Pro**: aggiunta la clausola di salvaguardia della licenza per garantire che i titolari della licenza per sito singolo esistenti mantengano l'accesso illimitato a tutte le funzionalità.
* **Migliorato**: margini di layout CSS standardizzati e ottimizzati e riempimento verticale sulla pagina di destinazione per coerenza visiva su schermi desktop e mobili.

== Upgrade Notice ==

= 7.0.0 =
Rilascio importante. phpinfo() WP è ora un plug-in completo per l'integrità del sito e il controllo del server WordPress, che rappresenta una versione moderna e mantenuta attivamente del flusso di lavoro di controllo dello stato e risoluzione dei problemi. Aggiunge gratuitamente la modalità di risoluzione dei problemi (modalità sicura per utente che non può lasciare il tuo sito danneggiato), scanner di compatibilità PHP che funziona su host gestiti, avvisi di versione PHP pre-aggiornamento, sequenza temporale EOL PHP, riepilogo di Config Grader, quadro di valutazione dello stato della barra di amministrazione, integrazione API WordPress 7.0 Abilities per assistenti AI e spiegazioni AI sui controlli di Config Grader non riusciti. Pro aggiunge la correzione automatica della configurazione con un clic, intestazioni di sicurezza, monitoraggio SSL, dashboard OPcache, report di controllo PDF con etichetta bianca e altro ancora.
