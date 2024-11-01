<?php
//
// Internal wpTuner diagnostic module
// This is only used if wpTuner Debug Level is enabled
//

function wpTuneDebug($wptObject) {
	global $user_level,$wp_version; // already pulled in by show code

	print '<br/><b>'.__('wpTuner Diagnostics','wptuner')."</b><br/>\n";
	print __('Debug level: ','wptuner').$wptObject->iDebugLevel."<br/>\n";
	print __('User level: ','wptuner').$user_level."<br/>\n";
	print __('WP version: ','wptuner').$wp_version."<br/>\n";
	print __('PHP version: ','wptuner').phpversion()."<br/>\n";
	print __('MySQL version: ','wptuner').mysql_get_server_info()."<br/>\n";
	print __('WP theme: ','wptuner').get_current_theme()."<br/>\n";
	if ($wptObject->iDebugLevel & 16) {
		if (function_exists('get_plugins'))	// Only list plugins if on an admin page
		{
			print __('WP plugins: ','wptuner');
			
			if (!function_exists('is_plugin_active')) {
				function is_plugin_active($plugin) {
					return in_array($plugin, get_option('active_plugins'));
				}
			}
	
			$all_plugins = get_plugins();
			$active_plugins = array();
			$inactive_plugins = array();
			
			foreach ( (array)$all_plugins as $plugin_file => $plugin_data) {
				//Filter into individual sections
				if ( is_plugin_active($plugin_file) ) {
					$active_plugins[ $plugin_file ] = $plugin_data['Name'].' ['.$plugin_data['Version'].']';
				} else {
					$inactive_plugins[ $plugin_file ] = $plugin_data['Name'].' ['.$plugin_data['Version'].']';
				}
			}
	
			if ( is_array($active_plugins) ) {
				print implode(', ',$active_plugins);
			}
			print " // ";
			if ( is_array($inactive_plugins) ) {
				print implode(', ',$inactive_plugins);
			}
			
			print "<br/>\n";
		}
	}
	if ($wptObject->iDebugLevel & 32) {
		print __('wpTuner Object: ','wptuner');
		print '<pre>'.htmlspecialchars(print_r($wptObject,TRUE)).'</pre>';
		print "<br/>\n";
	}
	if ($wptObject->iDebugLevel & 64) {
		print __('PHP Info: ','wptuner');
		ob_start();
		phpinfo();
		
		preg_match ('%<style type="text/css">(.*?)</style>.*?<body>(.*?)</body>%s', ob_get_clean(), $matches);
		
		# $matches [1]; # Style information
		# $matches [2]; # Body information
		
		echo "<div class='phpinfodisplay'><style type='text/css'>\n",
		    join( "\n",
		        array_map(
		            create_function(
		                '$i',
		                'return ".phpinfodisplay " . preg_replace( "/,/", ",.phpinfodisplay ", $i );'
		                ),
		            preg_split( '/\n/', trim(preg_replace( "/\nbody/", "\n", $matches[1])) )
		            )
		        ),
		    "</style>\n",
		    $matches[2],
		    "\n</div>\n";

		print "<br/>\n";
	}
}	
  	
?>