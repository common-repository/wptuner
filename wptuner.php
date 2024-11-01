<?php
/*
Plugin Name: WP Tuner
Plugin URI: http://blogs.icta.net/plugins/wptuner
Description: Easily, powerfully, discover why your blog or plugin is slow or cranky. It's a comprehensive time and database access analyzer. WPmu compatible, fully translatable. <a href="options-general.php?page=wptunersetup.php">Click for settings</a>
Version: 0.9.6
Author: Mr Pete
Author URI: http://blogs.icta.net/plugins/wptuner
*/
?>
<?php
/*  Copyright 2008-2009 ICTA / Mr Pete (email : WPTuner at ICTA dot net)

	I have not yet chosen a license for this software.
	
	For now, if you have received specific written permission from me
	to use this software, then you are free to use it personally according
	to the terms I gave you. You may not redistribute it to others.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
  I.e., for now it is UNSUPPORTED. If you get in trouble, YOU ARE ON YOUR OWN
  unless I decide to help (which is not at all guaranteed.)
*/
?>
<?php

if (defined('WPTOPTBASE')) return; // Apparently some variants of WordPress can include this more than once!

require_once( dirname(__FILE__).'/wptunertop.php'); // In case it is not yet configured anywhere

//****************************************************************************
//****************************************************************************
//
// PRIMARY DEFINITIONS
//
define('WPTOPTBASE', 'wptuner_');					// All my options begin this way
define('WPTUNER_VERSION', '0.9.6');				// Primary version
define('WPTUNER_MINOR_VERSION', '01');	// Slipstreaming and testing
define('WPTUNER_DEBUG', false);					// Internal debugging. Debug code typically NOT in realeases
define('WPTUNER_PATH', dirname(__FILE__));
define('WPTUNER_PLUG_NAME', dirname(plugin_basename(__FILE__)));
define('WPTUNER_PLUG_ACTIVE', WPTUNER_PLUG_NAME.'/wptuner.php');
define('WPTUNER_SITEURL', get_option('siteurl'));
define('WPTUNER_URL_BASE', get_option('siteurl').'/wp-content/plugins/' . WPTUNER_PLUG_NAME);


$upload_path = get_option('upload_path');
$wptuner_log_dir = ABSPATH."{$upload_path}/wptuner_log/";
define('WPTUNER_LOG_DIR', $wptuner_log_dir);



//****************************************************************************
//****************************************************************************
//
// LOCALIZE
//

// Language localization/translation (call wptuner_localize() before emitting text!)
$wptuner_domain = 'wptuner'; // my 'domain' of translation (see online 'gettext' documentation)
$wptuner_is_localized = 0;   // have we loaded the localization data yet?

function wptuner_localize()
{
   global $wptuner_domain, $wptuner_is_localized;

   if($wptuner_is_localized) {
      return;
   }
   $wptuner_is_localized=true;
  $plugin_dir = basename(dirname(__FILE__)).'/languages';

//echo "<pre>loc: ".$plugin_dir."</pre>";
   load_plugin_textdomain($wptuner_domain, 'wp-content/plugins/'.$plugin_dir, $plugin_dir ); 
}

//****************************************************************************
//****************************************************************************
//
// ACTIVATE, DEACTIVATE, UPDATE
//

$current_version_number = get_option(WPTOPTBASE.'version');
include_once(WPTUNER_PATH."/wptunersetup.php");
add_action('activate_'.WPTUNER_PLUG_ACTIVE, 'wptuner_install');
add_action('deactivate_'.WPTUNER_PLUG_ACTIVE, 'wptuner_uninstall');

if(
		(  $current_version_number <  WPTUNER_VERSION ) || 
		(	($current_version_number == WPTUNER_VERSION ) && 
			(get_option(WPTOPTBASE.'minor_version') <= WPTUNER_MINOR_VERSION)
		)
	) {
	include_once(WPTUNER_PATH."/wptunersetup.php");
  add_action('init', 'wptuner_auto_update');
}


//****************************************************************************
//****************************************************************************
//
// ADMIN PAGE
//
if (is_admin()) {
	add_action('admin_menu', 'wptuner_add_admin_menu');
	add_filter( 'plugin_action_links', 'wptuner_plugin_actions', -10, 2);
	
	include_once(WPTUNER_PATH.'/wptunersetup.php');
	function wptuner_add_admin_menu() {
	  // Add a new menu under Manage:
	  add_options_page('WP Tuner', 'WP Tuner', 10, 'wptunersetup.php', 'wptuner_show_admin_page');
	}
	// Add the 'Settings' link to the plugin page
	function wptuner_plugin_actions($links, $file) {
		static $this_plugin;
		if( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

		if( $file == $this_plugin ){
			$link = "<a href='options-general.php?page=wptunersetup'><b>Settings</b></a>";
			array_unshift( $links, $link ); // before other links
			// $links[] = $link; // after other links
		}
		return $links;
	}
}	


//****************************************************************************
//****************************************************************************
//
// HOOKS (this module only gets loaded if we're activated)
//

add_action('plugins_loaded', 	'wpTuneFilterTime' );
add_action('admin_init', 			'wpTuneFilterTime' );
add_action('admin_head', 	'wpTuneFilterTime' );
add_action('admin_notices', 	'wpTuneFilterTime' );
add_action('admin_footer', 	'wpTuneFilterTime' );
add_action('activity_box_end', 	'wpTuneFilterTime' );
add_action('load_feed_engine','wpTuneFilterTime' );
add_action('wp_authenticate', 'wpTuneFilterTime' );
add_action('wp_login', 		'wpTuneFilterTime' );
add_action('init', 				'wpTuneFilterTime' );
add_action('widgets_init', 		'wpTuneFilterTime' );
add_action('posts_selection', 		'wpTuneFilterTime' );
add_action('wp_head', 		'wpTuneFilterTime' );
add_action('get_sidebar', 		'wpTuneFilterTime' );
add_action('loop_start', 		'wpTuneFilterTime' );
add_action('get_footer', 		'wpTuneFilterTime' );

// If nothing else has started output buffering, we will, to capture output size
add_action('init','wptuner_buf');
function wptuner_buf()
{
	if (!ob_get_level())
		ob_start();
}

require_once( dirname(__FILE__).'/wptunershow.php'); // In case it is not yet configured anywhere

// negative priority: do it earlier // high prio == later. We want display to be the very last item.
add_action('wp_footer', 'wptuner_foot', 9999999999);
add_action('admin_footer', 'wptuner_foot', 999999999);
function wptuner_foot()
{
	global $wptunershow;
	wptuner_localize();
	
	$wptunershow->Show_All(1);
}

?>