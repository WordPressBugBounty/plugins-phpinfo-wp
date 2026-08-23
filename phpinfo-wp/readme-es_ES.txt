=== phpinfo() WP - Site Health, PHP Compatibility & Server Audit ===
Contributors: exeebit
Tags: site health, health check, php compatibility, troubleshooting, phpinfo
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 7.2.7
Requires PHP: 7.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Detenga las averías silenciosas del sitio. La herramienta definitiva de auditoría de servidores de administración y solución de problemas por usuario creada para agencias y desarrolladores profesionales.

== Description ==

**phpinfo() WP** es un complemento moderno y de mantenimiento activo para la auditoría del servidor y el estado del sitio de WordPress. Es la misma herramienta de administración que los autónomos y las agencias instalan en cada sitio nuevo para ver instantáneamente *qué está mal, qué está a punto de fallar y qué arreglar*, todo sin una suscripción SaaS, sin paneles externos y sin salir del panel de administración de WordPress.

Piense en ello como el complemento oficial **"Comprobación de estado y solución de problemas"**, pero creado para sitios de producción profesionales. Nuestro modo de resolución de problemas se ejecuta completamente en una sesión por usuario. Los visitantes y clientes continúan viendo el sitio en vivo normalmente mientras usted depura y aísla los conflictos de forma segura sin un solo segundo de tiempo de inactividad.

= The free version covers what every WordPress site owner actually needs: =

* **visor phpinfo()**: limpio, con capacidad de búsqueda y moderno (la característica original, completamente rediseñada).
* **Escáner de compatibilidad compatible con hosts**: analiza todos los complementos y temas en busca de conflictos de versiones de PHP antes de actualizar, diseñado para funcionar sin problemas incluso en hosts administrados estrictamente.
* **Update Guard**: Suite completa de seguridad previa y posterior a las actualizaciones. Obtenga una vista previa de la estabilidad del núcleo, analice las actualizaciones pendientes de plugins y temas en busca de cambios incompatibles y verifique automáticamente el estado del sitio 60 segundos después de cada actualización.
* **Solución de problemas sin tiempo de inactividad**: depure de forma segura conflictos de temas y complementos en su propia sesión de administración sin afectar a los visitantes ni a las ventas en vivo.
* **Cronología de fin de vida de PHP**: fecha de fin de vida útil de cada versión de PHP, estado actual y días restantes.
* **Resumen de Config Grader**: calificación general A-F de su configuración de PHP frente a las mejores prácticas de WordPress.
* **Editor de configuración PHP (.htaccess / .user.ini)**: configure o cambie directivas php.ini de forma segura desde su panel, con copias de seguridad automáticas y reversión de seguridad.
* **Marcador de estado de la barra de administración**: calificación en vivo y problema más urgente en cada página de administración, como PageSpeed ​​para su servidor.
* **Widget del panel**: el estado del sitio de un vistazo en el momento en que inicias sesión.
* **Registro de actividad, extensiones e información básica**: todo lo que hacía el complemento original, completamente rediseñado.
* **API lista para IA (WP 7.0)**: expone datos de auditoría para que los asistentes y complementos de IA puedan inspeccionar el estado del servidor a través de una interfaz central estándar.
* **Explicaciones de IA (WP 7.0)**: Explique instantáneamente las comprobaciones fallidas en inglés sencillo utilizando la conexión principal del Cliente de IA.

= Pro adds the tooling agencies and serious site owners actually need: =

**Protección: no rompas tu sitio**

* **Reparación automática con 1 clic con reversión**: soluciona problemas de configuración al instante. Escribe reglas de optimización en .htaccess o .user.ini y se revierte automáticamente si el servidor alcanza un error 500.
* **Comprobación de PHP previa a la actualización**: analice las actualizaciones del complemento antes de actualizar para verificar que no requieran una versión de PHP que no tenga.
* **Update Guard Pro**: Intercepción automática en la página de actualizaciones de WordPress, análisis de riesgos en el changelog, alertas de plugins abandonados, diagnósticos automáticos del estado del sitio 60s después de actualizar (loopback, log de errores, cron) y pasos de corrección generados por IA.
* **Instantáneas de configuración**: instantáneas automáticas semanales de cada directiva php.ini, con diferencias visuales.
* **Auditor de encabezados de seguridad**: Califique sus encabezados de respuesta HTTP (CSP, HSTS, X-Frame-Options) con sugerencias de corrección.
* **Biblioteca de fragmentos de servidor web**: bloques de configuración optimizados de Nginx y Apache para almacenamiento en caché, seguridad y bloqueo de bots dañinos, con inyección con 1 clic para Apache y LiteSpeed.
* **Monitor de certificados SSL**: realice un seguimiento de la caducidad del certificado y las discrepancias de dominio para evitar advertencias de seguridad.

**Información: sepa qué está mal antes de que los clientes llamen**

* **Calificador de configuración completa**: calificación detallada con los valores recomendados exactos y por qué es importante cada directiva.
* **Estado de la base de datos**: versión del motor, estado EOL, tamaño y detección de exceso de carga automática.
* **Panel de OPcache**: tasa de aciertos, uso de memoria, scripts en caché y borrado de OPcache con un solo clic.
* **Visor de registros de errores de PHP**: explore, busque y borre su registro de errores de PHP directamente desde el panel.
* **WP Cron Monitor**: busque eventos vencidos, ganchos huérfanos y tareas cron ejecutadas recientemente.
* **Rastreador de latencia de API saliente**: identifique conexiones lentas de terceros (pasarelas de pago, CRM, webhooks) que obstaculizan los tiempos de carga de las páginas.
* **Capacidad de entrega de correo**: Prueba de envío, validación de SPF y DKIM.

**Entrega: luzca profesional ante los clientes**

* **Informe de auditoría**: Informe de auditoría en PDF de una sola página para entregar a los clientes (completamente con etiqueta blanca en Unlimited o Lifetime, con marca en Single).
* **Alertas por correo electrónico**: reciba notificaciones sobre PHP EOL, cambios de configuración, caídas de OPcache y caducidad de SSL.
* **Resumen semanal**: resumen completo del estado del servidor enviado a su bandeja de entrada todos los lunes (ilimitado o de por vida).
* **Integraciones**: compatibilidad con Slack, Discord y Webhook para alertas en tiempo real (ilimitadas o de por vida).
* **Panel de control multisitio**: compatibilidad con widgets de panel de toda la red en cada subsitio.

= Why use this instead of 5 different plugins? =

La mayoría de las herramientas de salud del sitio de WordPress lo obligan a instalar complementos separados para compatibilidad con PHP, monitoreo SSL, encabezados de seguridad, OPcache, registros de errores, cron e informes. Cada uno es otro complemento para actualizar, otro elemento de menú y otro conjunto de opciones.

phpinfo() WP le ofrece **un complemento de administración** que lo cubre todo, con un único widget de panel y un único informe de auditoría en PDF. Sin panel de control SaaS externo, sin tarifas mensuales por sitio y sin inicios de sesión separados.

= Pricing =

* **Sitio único**: $29/año (1 sitio, funciones Pro esenciales, PDF personalizado, 3 instantáneas, 1 monitor API)
* **Sitios ilimitados**: $69/año (la elección popular, funciona en todos los sitios, totalmente con etiqueta blanca, instantáneas ilimitadas y monitores API)
* **Vida útil**: $149 una vez (precio para fundadores, primeros 50 compradores, sitios ilimitados, marca blanca completa)

Garantía de devolución de dinero de 14 días. Entrega de licencia instantánea. Claves de licencia bloqueadas en el sitio.

Compre en [exeebit.com/phpinfo-wp](https://exeebit.com/phpinfo-wp#pricing).

== Installation ==

1. Install from your WordPress admin: **Plugins -> Add New** and search for *"phpinfo() WP"*.
2. Activate.
3. Find the **phpinfo() WP** menu in your admin sidebar.
4. (Optional) Purchase a Pro license and activate it from **phpinfo() WP -> License**.

También puede [descargar el zip](https://downloads.wordpress.org/plugin/phpinfo-wp.zip) y cargarlo desde **Complementos -> Agregar nuevo -> Cargar complemento**.

= Server requirements =

*PHP 7.3 o superior. PHP 7.4 llegó al final de su vida útil en noviembre de 2022 y ya no es compatible.
*WordPress 5.9 o superior.
* Es posible que su proveedor de alojamiento deshabilite algunas funciones PHP utilizadas por el complemento. Comuníquese con su anfitrión si las funciones aparecen como no disponibles.
* Para editar .htaccess, la raíz de su sitio debe poder escribirse.

== Frequently Asked Questions ==

= Is the free version still free? =

Sí. El visor phpinfo gratuito, el editor .htaccess, la lista de extensiones, el registro de actividad, la línea de tiempo PHP EOL y el resumen de Config Grader son 100% gratuitos y siempre lo serán. Pro es una actualización opcional para agencias y propietarios de sitios serios.

= Does the Pro version send my data anywhere? =

No. Todas las auditorías se ejecutan en su propio servidor. La verificación de la licencia Pro hace ping a nuestro servidor de licencias una vez por semana para confirmar que su clave aún es válida y no se transmite nada más.

= Can I use one license on multiple sites? =

La licencia de sitio único funciona en 1 sitio. La licencia ilimitada funciona en todos los sitios que posee o administra. La licencia de por vida también es ilimitada.

= How does white-label work? =

En la página Informe de auditoría profesional, haga clic en **Etiqueta blanca** para configurar el nombre de su empresa, el título del informe, el color de acento y la nota al pie de página. El PDF que genera utiliza su marca, no la nuestra.

= What if my license server is unreachable? =

El complemento funciona bien durante 14 días incluso si no se puede acceder a nuestro servidor de licencias. Después de eso, las funciones Pro se bloquean y deberá reactivar la licencia. Recibirá un aviso claro mucho antes de que eso suceda.

= Will this slow down my site? =

No. Todas las comprobaciones se ejecutan únicamente dentro del panel de administración y no tienen ningún impacto en el rendimiento del front-end. Los controles pesados ​​(encabezados de seguridad, SSL) se almacenan en caché.

= How is this different from Health Check & Troubleshooting? =

Health Check & Troubleshooting es el complemento oficial de WordPress.org para este tipo de trabajo y es una herramienta sólida. Su modo de solución de problemas es para todo el sitio. Cuando está habilitado, cada visitante ve el tema predeterminado con sus complementos desactivados hasta que deshabilite el modo a través de un enlace de un clic en la barra de administración.

phpinfo() WP adopta un enfoque diferente. Nuestro modo de solución de problemas es por usuario: solo su sesión de administrador actual ve los complementos desactivados y el tema predeterminado. Todos los demás visitantes y administradores siguen viendo el sitio en vivo con normalidad. Esa es la elección de diseño que tomamos para depurar conflictos de complementos en sitios de producción ocupados, como una tienda WooCommerce activa o un editor con mucho tráfico, donde desconectar el front-end no es una opción.

También agregamos características que Health Check no ofrece: seguimiento PHP EOL, calificación de configuración A-F con correcciones con un solo clic (Pro), un marcador de estado de la barra de administración, monitoreo de SSL/encabezados y un informe de auditoría en PDF listo para el cliente.

Utilice Health Check si desea la herramienta oficial con un modo de depuración simple en todo el sitio. Utilice phpinfo() WP si necesita una sesión de depuración por usuario en un sitio en vivo, además del conjunto de auditoría más amplio.

= How is this different from Query Monitor / WP Umbrella? =

Query Monitor es una herramienta de desarrollo para depurar cargas de páginas individuales, lo cual es un trabajo diferente. WP Umbrella, ManageWP y MainWP son paneles de control SaaS externos que facturan por sitio, por mes, lo cual es útil para agencias que quieren todo en una consola externa.

phpinfo() WP Pro es para el propietario del sitio o el profesional independiente que desea una herramienta de administración que cubra el estado de PHP, la configuración, los encabezados de seguridad, SSL y un informe de auditoría listo para el cliente, sin una suscripción mensual a SaaS y sin salir del administrador de WordPress.

= Does this PHP compatibility scanner actually work on my managed host? =

Sí. A diferencia de los escáneres que dependen de PHP_CodeSniffer o la función `exec()`, nuestro escáner utiliza un análisis estático que se ejecuta dentro del propio WordPress. Funciona en Kinsta, WP Engine, SiteGround, Cloudways, Pantheon y cualquier otro host administrado que restrinja el acceso al shell.

= Where do I get support? =

* Gratis: [foro de soporte de WordPress.org] (https://wordpress.org/support/plugin/phpinfo-wp/).
* Ventaja: envíe un correo electrónico a support@exeebit.com con su clave de licencia para obtener una respuesta prioritaria.

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
* **Característica**: Update Guard enriquecido — Se agregaron escaneos de compatibilidad previos a la actualización para plugins y temas (requisitos de PHP/WP, análisis de riesgos en changelog, detección de abandonos), diagnósticos de salud automáticos a los 60 segundos posteriores a la actualización (loopback, acceso al admin, delta de errores, integridad de cron) y puntuación de estabilidad.
* **Corrección**: Se renombraron los identificadores de página (slugs) a `piwp-*` para evitar falsos positivos 403 Forbidden causados por cortafuegos 8G/7G, BBQ y LiteSpeed que bloquean el término `phpinfo`. Muchas gracias a **Simon Richards** por descubrir e informar este problema.
* **Mejora**: Activación y desactivación de licencias mediante AJAX con validación instantánea e indicador de carga.
* **Mejora**: Soporte técnico y diagnóstico del servidor accesibles para todos los usuarios con señales del entorno en tiempo real.
* **Corrección**: Corrección de la alineación vertical del texto en los botones del panel de administración.
* **Corrección**: Corrección del formato de espacios y saltos de línea en el visor de registros de errores PHP.

= 7.2.6 =
* **Compatibilidad**: Probado hasta WordPress 7.1.

= 7.2.5 =
* **Mejora**: Recordatorio de aumento de precio para el plan Single Site el 31 de agosto.
* **Mejora**: Los avisos de actualización ahora muestran problemas reales detectados en la configuración de su sitio.
* **Mejora**: Ajustes visuales en la interfaz del panel de administración.

= 7.2.4 =
* **NUEVO**: Localización completa de complementos visuales y archivos de traducción para francés, alemán, español, italiano y holandés.
* **Mejorado**: indicadores optimizados del ciclo de vida EOL del servidor y auditorías de encabezado HTTP.

= 7.2.3 =
* **Pro**: Restricciones de funciones del plan de sitio único alineadas con límites de niveles de precios. Monitores de API salientes limitados en 1, instantáneas de configuración en 3, webhooks y resúmenes semanales de Slack/Discord bloqueados y configuración de informes de auditoría en PDF con el estilo de marca predeterminado.
* **Pro**: Se agregó protección de licencias para garantizar que los titulares de licencias de sitio único existentes conserven acceso ilimitado a todas las funciones.
* **Mejorado**: Márgenes de diseño CSS estandarizados y optimizados y relleno vertical en la página de destino para lograr coherencia visual en las pantallas de escritorio y móviles.

== Upgrade Notice ==

= 7.0.0 =
Lanzamiento importante. phpinfo() WP es ahora un complemento completo de auditoría de servidor y estado del sitio de WordPress, que representa una versión moderna y mantenida activamente del flujo de trabajo de verificación de estado y solución de problemas. Gratis agrega el Modo de solución de problemas (modo seguro por usuario que no puede dejar su sitio roto), Escáner de compatibilidad de PHP que funciona en hosts administrados, advertencias de versión de PHP previas a la actualización, Línea de tiempo de EOL de PHP, resumen de Config Grader, marcador de estado de la barra de administración, integración de API de capacidades de WordPress 7.0 para asistentes de IA y explicaciones de IA sobre las comprobaciones fallidas de Config Grader. Pro agrega Config Auto-Fix con un solo clic, encabezados de seguridad, monitor SSL, panel de OPcache, informes de auditoría en PDF de marca blanca y más.
