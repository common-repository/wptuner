=== WP Tuner ===
Contributors: Mr Pete
Donate link: http://blogs.icta.net/plugins/tipjar
Tags: performance, database, tuning, slow, speed, plugins
Requires at least: 2.0.6
Tested up to: 2.8.4
Stable tag: trunk

Easily, powerfully, discover why your blog or plugin is slow or cranky. Comprehensive time and database access analyzer. WPmu. multi-lingual.

== Description ==

The (mu-compatible) WP Tuner plugin for WordPress is a powerful and easy to use way to answer many hard questions about why your blog is slow or cranky. What's causing the slowdown? Is it a plugin? Is it your host?

Used with a bit of common sense, this powerful plugin will help blog administrators as well as software developers improve their WordPress blog performance.

Perfect for WordPress site administrators, plugin and theme designers, developers

Fully translatable.

== Installation ==

1. Upload the *wptuner* folder (inside the zip file) to your */wp-content/plugins/* directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin management page _(Settings->WPtuner)_ provides settings, hints, and troubleshooting if the plugin will not fully auto-install.

Please report any install errors to me, at [my site](http://blogs.icta.net/plugins/wptuner). It will help if you can copy the _wpTuner Diagnostics_ produced when you set the WP Tuner Debug Level to 1.

= Updating =

1. Deactive the existing plugin, to remove its hooks (in *wp-config.php*, etc)
2. Copy the new files to replace the old (usually the plugins/wptuner folder)
3. Activate the new plugin

== Frequently Asked Questions ==

= I'm having trouble getting it to work =

1. Have you looked at the settings page (Settings->WP Tuner)? You'll find installer error notes there. Most common: your WP root folder needs write permission (at least 0755), and _WP Tuner_ must be able to set permissions on your `wp-config.php` file to make it editable.
2. Are you logged in with Administrator permissions (level 10)? Normally, only admins see anything.
4. If you continue to have trouble, please set the Debug Level to 1, copy the _wpTuner Diagnostics_ and paste them into an error report at [my site](http://blogs.icta.net/plugins/wptuner).

= How do I customize WP Tuner? =

There's little reason to bother changing the CSS. This is an admin-only tool; your readers will not see anything. However, there's some CSS at the top of `wptunershow.php`, and further down in `wptunersetup.php`.

This is a fully translatable/localizable plugin. See the `wptuner.pot` file, and the online *gettext* tools such as poedit. Please send me your translations; I'll make them part of the distribution with credit to you!


= How do I examine performance in more detail? =

WP Tuner is an entire performance analysis toolkit. The built-in admin settings provide a set of default measures that can be enabled without manual intervention.

See the [Advanced Use](http://wordpress.org/extend/plugins/wptuner/other_notes/) hints section for some examples

== Screenshots ==

1. One of the available analysis results. (This summarizes database queries, by plugin, widget, theme, or section of the core code in WordPress.)
2. The top portion of the WP Tuner admin page. (It includes simple one-click presets on the left, with detailed settings available on the right.)

== Advanced Use ==

Here are more advanced ways to make use of _WP Tuner_. Please [share your questions and/or stories](http://blogs.icta.net/plugins/wptuner "(Visit the WP Tuner home page)") examples to share with others!

1) Hook any WordPress action

In wptuner.php, you will find a default set of action hooks. You can add as many hooks as you like for other actions. They look like this (just change "admin_footer" to the name of the action you want to measure):

    add_action('admin_footer', 'wpTuneFilterTime' );

Each hook adds a line to the _WP Tuner_ performance analysis table, when that action is triggered.

2) Time anything at all in WordPress

WP Tuner contains a function that causes performance analysis for any section of WordPress code. Just use
  wpTuneMarkTime('My Marker string');

...at the beginning of the code you want analyzed. Add another marker at the end if needed.

For example, suppose you want to know how much time is spent loading each of the plugins you have enabled. Here's how to do it, by adding two simple lines to wp-settings.php:

    if (function_exists(wpTuneMarkTime)) wpTuneMarkTime('Load Plugins'); // ** Add THIS line **
    
    if ( get_option('active_plugins') ) {
      $current_plugins = get_option('active_plugins');
      if ( is_array($current_plugins) ) {
        foreach ($current_plugins as $plugin) {
          if (function_exists(wpTuneMarkTime)) wpTuneMarkTime('Plugin: '.$plugin); // ** Add THIS line **
          if ( '' != $plugin && 0 == validate_file($plugin) && file_exists(WP_PLUGIN_DIR . '/' . $plugin) )
            include_once(WP_PLUGIN_DIR . '/' . $plugin);
        }
      }
    }

== Credits ==

Many thanks to the WP Tuner translation team!

Русский: [Кактусу](http://lecactus.ru) (updated through WP Tuner 0.9.3)

== Readme Validator ==

This readme was validated using:
<http://wordpress.org/extend/plugins/about/validator/>