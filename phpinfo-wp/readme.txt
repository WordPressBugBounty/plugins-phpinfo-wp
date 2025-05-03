=== phpinfo() WP ===
Contributors: exeebit
Tags: phpinfo, server info, htaccess, apache, phpinfo()
Requires at least: 4.7
Tested up to: 6.8.1
Stable tag: 6.1
Requires PHP: 5.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Donate link: http://exeebit.com/wordpress-plugins/phpinfo-wp/donate

A simple plugin to look up server info and manage server configuration of wordpress site

== Description ==

A simple wordpress plugin to look up information about server and PHP's configuration and manage server configurations.

This plugin gives a large amount of information about the current state of PHP. This includes information about PHP compilation options and extensions, the PHP version, server information and environment (if compiled as a module), the PHP environment, OS version information, paths, master and local values of configuration options, HTTP headers. For making information concise, PHP license has been removed.

You can see what extensions are enabled in your server through this plugin. Except these, you can edit or set Server configuration values like max_file_uploads, upload_max_filesize, etc. You can edit or set any directive values through this plugin easily.

== Updates ==

Added an option to look up some basic information like root directory size, a number of activated and installed plugin, media directory size etc.

== Screenshots ==

1. PHP Info
2. htaccess editor
3. Extensions
4. Basic Information
5. Log

== Installation ==

You can [download](https://downloads.wordpress.org/plugin/phpinfo-wp.zip) and upload the plugin via Admin > Plugins > Add New > Upload Plugin. Or,
Go to your website's admin panel. Select Plugins > Add New. search for **phpinfo() WP** by [Exeebit](https://exeebit.com/wordpress-plugins/phpinfo-wp). Click **Install Now button**. Then simply click **Active**.

== Frequently Asked Questions ==

= What is the requirements to active this plugin? =

Nothing is needed. But sometimes, hosting providers disable [server setting](http://php.net/manual/en/ini.core.php#ini.disable-functions). In that case, you need to contact with your hosting provider.
See screenshot #1. Make sure site's root directory is writable to set or edit server configuration's directive values. 

= Why my site is showing Internal Server Error 500 after editing .htaccess file? =

Relax, nothing has happend. Safe edit feature for editing .htaccess file has been added from version 2.0. In case you are using older version than 2.0. Don't worry. Enter to your hosting site. Go to File Manager. Edit .htaccess file which is located at the root folder of you site's directory. Delete the code which is faulty. Save and site will be ok. 

== Change Log ==

= 6.1 =
Add blueprint for live preview
Fix minor bugs
UI enhancement

= 6.0 =
Fix CSRF vulnerability issues
UI enhancement

= 5.0 =
Fix CSRF vulnerability
Fix htaccess directive editing issues
UI enhancement

= 4.0 =
Fixed freezing issue

= 3.0 =
Added an option to look up some basic information
Fixed PHP errors

= 2.2.3 =
Fixed JS errors
Fixed major PHP errors

= 2.2.2 =
Editing htaccess file securely
Fixed minor CSS errors
Fixed minor PHP errors

= 2.2.1 =
Fixed PHP errors
Fixed CSS errors

= 2.2 =
Fixed editor issues
Added log tab
Fixed JS issues
Fixed page appearance related issues

= 2.1 =
Fixed script and editor issues

= 2.0 =
Through this version, you can edit or set server configuration values

= 1.0.5 =
Added Extension page in which enabled extensions for wordpress can be seen along with disabled extensions

= 1.0.4 =
Added Extension page in which enabled extensions for wordpress can be seen

= 1.0.3 =
Added donation information and redesigned plugin's interface

= 1.0.2 =
Changed style and added scroll to top button

= 1.0.1 =
Updated position in admin menu. It it currently under tools in admin menu

= 1.0.0 =
First release

== Upgrade Notice ==

Make sure your [server setting](http://php.net/manual/en/ini.core.php#ini.disable-functions) is **enabled**. Editing .htaccess. **Make sure root directory is writable. Except write permission, server configuration values can not be edited or set through this plugin**.
