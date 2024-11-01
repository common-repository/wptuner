<?php
/*  Copyright 2008 ICTA / Mr Pete (email : WPTuner at ICTA dot net)

	I have not yet chosen a license for this software.
	
	For now, if you have received specific written permission from me
	to use this software, then you are free to use it personally according
	to the terms I gave you. You may not redistribute it to others.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/
?>
<?php

wptuner_localize();  // Activate localization on admin/settings page

if (!function_exists('file_put_contents')) {				// Backwards php4 compatibility
    function file_put_contents($filename, $data) {
        $f = @fopen($filename, 'w');
        if (!$f) {
            return false;
        } else {
            $bytes = fwrite($f, $data);
            fclose($f);
            return $bytes;
        }
    }
}

//
// If an option begins with certain letters, it can be used for admin settings
// b = boolean
// f = float
// i = integer
// s = string
global $wptuner_options, $wptuner_presets, $wptuner_preset_names;
//
// Here are the available WP options -- this array controls adding/removing from the DB, and reset to defaults
$wptuner_options = array(
	'version' => WPTUNER_VERSION,					
	'minor_version' => WPTUNER_MINOR_VERSION,
	'bShowLog' => true,								// show the debug log
	'bShowTime' => true,							// show performance stats
	'bShowSQL'  => true,              // show sql queries
	'bShowAll' => false,								// True: all queries shown; False: only slow and bad queries shown
	'bShowDetail' => true,						// True: show detailed query analysis for any queries shown
	'bShowOverview' => true,					// True: show one line overview
	'bChargePlugins' => true,					// True: charge core DB queries to plugins; False: charge to core
	'bUninstallOnDeactivate' => true, 		// True: Uninstall (remove all traces) upon deactivation
	'fSlowTime'	=> 0.5,								// Any element slower than this (seconds) will be highlighted
	'iDebugLevel' => 0,								// wpTuner debugging. Normally zero; can set to higher values to discover why wpTuner is misbehaving
	'bAvoidQueryTesting' => false				// True: never examine queries to see if valid, to record table usage, or (optionally) examine for optimization hints
	);

// Same set as above, except these are presets. Hitting a preset button will redo all of these
$wptuner_presets = array(
//                                   Minimize  Warn   Times  Slow   Dev   All
	'bShowLog'               => array( false,    true,  true,  true,  true, true ),	// show the debug log
	'bShowTime'              => array( false,    false, true,  true,  true, true ),	// show performance stats
	'bShowSQL'               => array( false,    true,  true,  true,  true, true ),  // show SQL queries
	'bShowAll'               => array( false,    false, false, false, false,true ),	// True: all queries shown; False: only slow and bad queries shown
	'bShowDetail'            => array( false,    false, false, true,  true, true ),	// True: show detailed query analysis for any queries shown
	'bShowOverview'          => array( true,     true,  true,  true,  true, true ),	// True: show one line overview
	'bChargePlugins'         => array( true,     true,  true,  true,  true, true ) 	// True: charge core DB queries to plugins; False: charge to core
	);

// Key is the name, in order to be displayed
// Value is the array index above, from 0 to n
$wptuner_preset_names = array(
	__('Minimal (Admin) Footer','wptuner') => 0,
	__('Errors and Warnings', 'wptuner') => 1,
	__('Analyze Timing','wptuner') => 2,
	__('Find Slow Elements', 'wptuner') => 3,
	__('Developer Basic', 'wptuner') => 4,
	__('Show Everything', 'wptuner') => 5
	);
	
function wptuner_auto_update() {
  global $wpdb;
  
  // Update sets... not needed here actually
  
  //if(get_option(WPTOPTBASE.'version') <= 1.0) {
    //include_once('updates/update-to-something.php');
	//}

  
  if((get_option(WPTOPTBASE.'version') < WPTUNER_VERSION) || 
  	(get_option(WPTOPTBASE.'version') == WPTUNER_VERSION) && 
  	(get_option(WPTOPTBASE.'minor_version') < WPTUNER_MINOR_VERSION)) {
    	update_option(WPTOPTBASE.'version', WPTUNER_VERSION);
    	update_option(WPTOPTBASE.'minor_version', WPTUNER_MINOR_VERSION);
	}
}

function wptuner_install()
   {
   global $wptuner_options,$wpdb, $user_level, $wp_rewrite, $wp_version;

   if($wp_version < 2.1) {
     get_currentuserinfo();
     if($user_level < 8) {
       return;
			}
    }

		foreach($wptuner_options as $key => $val) {
			if (get_option(WPTOPTBASE.$key) == null)
				add_option(WPTOPTBASE.$key, $val);
		}

		wptuner_wpconfig_inject();
  
}
  
function wptuner_uninstall() {
  global $wpdb, $wptuner_options;
  if(current_user_can('edit_plugins')) {

		if (get_option(WPTOPTBASE.'bUninstallOnDeactivate'))
		{  	
			foreach($wptuner_options as $key => $val) {
				delete_option(WPTOPTBASE.$key);
			}
			
			wptuner_wpconfig_remove(); // remove the wpconfig code
		}
	}
}

function wptuner_wpconfig_perm() {
	return substr(sprintf('%o', fileperms(ABSPATH.'wp-config.php')), -4);
}
function wptuner_root_perm() {
	return substr(sprintf('%o', fileperms(ABSPATH)), -4);
}


function wptuner_wpconfig_check() {
	$cfgName = ABSPATH.'wp-config.php';
	$configFile = file($cfgName);
	$insLine=-1;
	$startLine=0;
	$endLine=0;
	$bOK = true;
	
	// First pass: discover if any bits of WP Tuner are in place, and where it should be installed
	//
	foreach ($configFile as $line_num => $line) {
		$test=trim( substr($line,0,16));
		if (substr($test,0,7)=='$base =')
				$test = '$base =';
		switch ($test) {
			case "define('DB_HOST'":
			case "define('DB_CHARS":
			case "define('DB_COLLA":
			case "define('VHOST',":
			case "define ('WPLANG'";
			case "define('WPLANG',";
			case '$table_prefix  =':
			case '$table_prefix =':
			case '$table_prefix':
			case '$base =':
				//echo '<pre>'.$line.'</pre><br/>';
				$insLine = max( $line_num, $insLine ); break;
			case "/* That's all, s": // This is the MOST preferred place. Right before the end...
			case "/* That's all,":
				$insLine = max( $line_num-1, $insLine ); break;
			case "//-WP Tuner Plug";
				$startLine=$line_num; break;
			case "//-END WP Tuner";
				$endLine  =$line_num; break;
			default:
				break;
		}
	}
	
	if ($insLine < 0)
	{
		define('WPTUNER_ERR_NOCFGFILE',1); // can't read config file
		$bOK=false;
	} else {
		if ($startLine || $endLine)
		{
			define('WPTUNER_ERR_WPT_TRASH',1); // wp-config has leftover WP Tuner trash (might be good trash)
			$bOK=false;
		}
		if (!@chmod($cfgName, 0777))
		{
			define('WPTUNER_ERR_CFG_NOWRITE',1); // wp-config can't be written
			$bOK=false;
		}
		@chmod($cfgName, 0644);
	}

	if ($bOK)
		return $insLine;
	else
		return false;
}

// Returns TRUE if injected ok, FALSE if any problem
function wptuner_wpconfig_inject() {
	$configFile = file( ABSPATH.'wp-config.php' );
	$outFile = ABSPATH.'wp-config.php';
	$configCopy = ABSPATH.'wp-config.WPTunerOrig.php';
	$insLine= wptuner_wpconfig_check();
	
	if (!$insLine)
		return false;
		
	// Copy the config file as-is
	if (!file_put_contents( $configCopy, $configFile ))
	{
		define('WPTUNER_ERR_CFG_NOBACKUP',1); // can't back up wp-config
		return false;
	}
	$myStr = <<<EOT
//-WP Tuner Plugin by MrPete------------
//--------------------------------------
\$wpTunerStart = microtime();					// get start time as early as we can
if ( function_exists( 'getrusage' ) ) { \$wpTunerStartCPU = getrusage(); }
@include_once(dirname(__FILE__).'/wp-content/plugins/wptuner/wptunertop.php'); // fire up WPTuner
//--------------------------------------
//-END WP Tuner Plugin------------------
EOT;

	$outStr = implode(array_slice($configFile,0,$insLine+1)) . $myStr . "\n".implode(array_slice($configFile, $insLine+1));
	
	@chmod($outFile, 0777);
	if (!file_put_contents($outFile, $outStr))
	{
		define('WPTUNER_ERR_CFG_NODATA',1); // can't write data into config file; like NOWRITE but data level
		return false;
	}
	chmod($outFile, 0644);

}

function wptuner_wpconfig_removecheck() {
	$cfgName = ABSPATH.'wp-config.php';
	$configFile = file($cfgName);
	$startLine=0;
	$endLine=0;
	$bOK = true;
	
	// Find start and end of WP Tuner in wp-config
	//
	foreach ($configFile as $line_num => $line) {
		$test=trim( substr($line,0,16));
		switch ($test) {
			case "//-WP Tuner Plug";
				$startLine=$line_num; break;
			case "//-END WP Tuner";
				$endLine  =$line_num; break;
			default:
				break;
		}
	}
	
	if (!$startLine || !$endLine)
	{
		define('WPTUNER_ERR_WPT_NOTFUND',1); // strange. Can't remove WPT from wp-config; markers not found
		$bOK=false;
	}
	if (!@chmod($cfgName, 0777))
	{
		define('WPTUNER_ERR_CFG_NOWRITE',1); // wp-config can't be written
		$bOK=false;
	}
	@chmod($cfgName, 0644);


	if ($bOK)
		return array($startLine,$endLine);
	else
		return null;
}

// Returns TRUE if removed ok, FALSE if any problem
function wptuner_wpconfig_remove() {
	$configFile = file( ABSPATH.'wp-config.php' );
	$outFile = ABSPATH.'wp-config.php';
	$configCopy = ABSPATH.'wp-config.WPTunerFinal.php';
	$wptLines= wptuner_wpconfig_removecheck();
	
	if (!isset($wptLines))
		return false;
		
	// Copy the config file as-is
	if (!file_put_contents( $configCopy, $configFile ))
	{
		define('WPTUNER_ERR_CFG_NOBACKUP',1); // can't back up wp-config
		return false;
	}

	$outStr = implode(array_slice($configFile,0,$wptLines[0])) .implode(array_slice($configFile, $wptLines[1]+1));
	
	@chmod($outFile, 0777);
	if (!file_put_contents($outFile, $outStr))
	{
		define('WPTUNER_ERR_CFG_NODATA',1); // can't write data into config file; like NOWRITE but data level
		return false;
	}
	chmod($outFile, 0644);

}

function wptuner_show_admin_page() {
  global $wptuner_options, $wptuner_preset_names, $wptuner_presets, $wptunershow;
  
//
// Every time admin page shows, attempt to auto-inject into wp-config, if necessary
//
	if (WPTUNER_NOTCONFIG+WPTUNER_BADCONFIG+WPTUNER_NOQUERIES)
	{
		wptuner_wpconfig_inject();
	}
	
	$sText = "";
 
// Debugging queries from users...
//$wpt_dbgqry = array("q1","q2")
// foreach ( $wpt_dbgqry as $qry)
// {
//  	global $wpdb;
// 		$wpdb->query($qry);
//}
 
//	foreach ($wptuner_options as $key => $val) {
// 		$wptunershow->{$key} = get_option(WPTOPTBASE.$key);
//	}
	
  if(isset($_POST["wptuner_action"])) {
		foreach ($wptuner_options as $key => $val) {
			if (FALSE===strpos('bfis',$key[0]))
				continue; // skip options not settable by users
	 		if ($_POST[$key] !== NULL) {
				$val = $_POST[$key];
				switch ($key[0] ) {
				case 'i':
				case 'b':	$val = intval($val); break;
				case 'f': $val = floatval($val); break;
				case 's': $val = $val; break; // clean up once we have a str to save
				}
		 		$wptunershow->{$key} = $val;
				update_option(WPTOPTBASE.$key, $val);
			} else {
				if (0) { // there should be no reason to do this. Bad side effect: disabled items in UI get reset!
					if ($key[0] == 'b')
					{
						$val = 0;
						$wptunershow->{$key} = $val;
						update_option(WPTOPTBASE.$key, $val);
					}
				}
			}
		}

    $sText .= "<div id='message' class='updated fade'><p>".__('WP Tuner options successfully updated.','wptuner')."</p></div>";
  }
  
  if(isset($_POST["wptuner_default"])) {
		foreach ($wptuner_options as $key => $val) {
			if (FALSE===strpos('bfis',$key[0]))
				continue; // skip options not settable by users
	 		$wptunershow->{$key} = $val;
			update_option(WPTOPTBASE.$key, $val);
		}

    $sText .= "<div id='message' class='updated fade'><p>".__('WP Tuner options successfully reset to default values.','wptuner')."</p></div>";
  }
  
  if(isset($_POST["wptuner_preset"])) {
  	$preset_val = $_POST["wptuner_preset"];		// value is array key
  	if (isset($wptuner_preset_names[$preset_val]))
  	{
  		$preset_idx = $wptuner_preset_names[$preset_val]; // convert from name to index
  		foreach($wptuner_presets as $key=> $ary)
  		{
  			if (FALSE===strpos('bfis',$key[0]))
  				continue; // skip nonusable rose
  			$wptunershow->{$key} = $ary[$preset_idx];
  			update_option(WPTOPTBASE.$key, $ary[$preset_idx]);
  		}
  	}
    $sText .= "<div id='message' class='updated fade'><p>".sprintf(__('WP Tuner options successfully preset to <em>%s</em>.','wptuner'), $preset_val)."</p></div>";
  }
  
  echo <<<EOT
    <div class="wrap">
    <h2>WP Tuner Options and Hints</h2>
EOT;
    if ($sText <> "") {
			print "<p>\n";
      print $sText;
			print "</p>\n";
    } 

	_e("<p>At minimum, WP Tuner does a basic performance analysis: time, database use, memory use and server load.</p><p><em>Note:</em> If your pages take a long time to generate, yet the server load is <em>low</em>, something <em>else</em> is slowing down your site! A plugin may be trying to get data from a slow or missing site, or your web host may by slowed by other sites running on the same CPU.",'wptuner');
    
	$cSlow = $wptunershow->fSlowTime;
	$strReset =__('Reset to defaults','wptuner');
	$strUpdate =__('Save Changes','wptuner');

  function wpt_emit_radioval( $key, $val, $cVal, $sPrompt, $sHint )
  {
  	$sText = "<label><input name='$key' type='radio' value='".intval($val)."'";
  	if (intval($val) == intval($cVal))
  		$sText.= ' CHECKED="CHECKED" ';
  	$sText .= " />&nbsp;{$sPrompt}</label><br />\n";
  	if (strlen($sHint))
  		$sText .= "&nbsp;<small>($sHint)</small><br/>\n";
  	return $sText;
  }
	function wpt_show_radio( $key, $sTitle, $sFalsePrompt, $sFalseHint,$sTruePrompt, $sTrueHint )
	{
		global $wptuner_options, $wptunershow;
		
		$cVal = $wptunershow->{$key};
		
		print "<tr valign='top'>\n<th scope='row'>{$sTitle}</th>\n<td>\n";
		print wpt_emit_radioval( $key, 0, $cVal, $sFalsePrompt, $sFalseHint );
		print wpt_emit_radioval( $key, 1, $cVal, $sTruePrompt, $sTrueHint );
		print "</td></tr>\n";	
	}

	print '<form method="post" action="">'."\n";
	print "<style type='text/css'>
	.wptuner_presets { float: left; width: 19%; }
	.wptuner_options { float: right; width:79%; padding: 0 0 10px 10px; border-left: 1px solid black;}
	.wptuner_options a.notsubmit { color: #2583ad; }
	.wptuner_options ul.condensed { margin-top: 0; }
	.wptuner_foot    { clear: both; border-top: 1px solid black; margin-top: 2px;}
	</style>\n";
	print '<div><div class="wptuner_presets">';
	//
	// First column: Easy Presets
	_e('<h3>Presets</h3>','wptuner');
   echo '<p class="submit"><input type="submit" name="wptuner_default" value="'.$strReset.'" /><br/><br/>';
   
   foreach ($wptuner_preset_names as $key => $val)
   {
	   echo '<input type="submit" name="wptuner_preset" value="'.$key.'" /><br/><br/>';
	  }

	print '</p></div><div class="wptuner_options">';
   
  // 
  // Second column: Custom settings
  //
	_e('<h3>Custom Settings</h3>','wptuner');
	
   echo "<p class='submit'>
        <input type='submit' name='wptuner_action' value='".$strUpdate."' />
        <em>(<a class='notsubmit' href='#wptuner'>".__('View current WP Tuner output','wptuner')."</a>)</em></p>\n";

  print '<table class="form-table">'."\n";

	print "<tr valign='top'>\n<th scope='row'>".__('Install / Uninstall','wptuner')."</th>\n
<td>";

	$configErrCount= WPTUNER_NOTCONFIG+WPTUNER_BADCONFIG+WPTUNER_NOQUERIES;
	$ru_available =function_exists('getrusage');
	$mem_available=function_exists("memory_get_usage");
	$missing = '';

	if (!$configErrCount) {
		_e('WP Tuner is correctly installed.<br/>','wptuner');
	}

	$bUninstallOnDeactivate = get_option(WPTOPTBASE.'bUninstallOnDeactivate');
	print "<label><input name='bUninstallOnDeactivate' type='checkbox' value='1'";
 	if (intval($bUninstallOnDeactivate))
 		print ' CHECKED="CHECKED" ';
 	print " />&nbsp;".__('Uninstall (remove all settings) when plugin is deactivated.','wptuner').' <em>['.__('Not affected by Presets','wptuner')."]</em></label>\n";

	$iDebugLevel = get_option(WPTOPTBASE.'iDebugLevel');
	print '<br/><label><input type="number" name="iDebugLevel" value="'.$iDebugLevel.'" maxlength="3" size="3" />&nbsp;';
 	print __('Debug Level (normally 0, ask MrPete for other values if wpTuner is misbehaving)','wptuner').
 	        "</label>\n";

	if (!$configErrCount) {
	//
	// No errors; give some extra notes for tech-minded people
	//
		if (!($ru_available && $mem_available))
		{
			print '<br/><br/>'.__("<em>Note:</em> Your web environment is unable to provide me with the following information:",'wptuner'). "<ul class='condensed'>\n";
			if (!$ru_available)
				print '<li>&nbsp;&nbsp;'.__('<em>Server load (CPU time):</em> probably a windows server?','wptuner')."</li>\n";
			if (!$mem_available)
				print '<li>&nbsp;&nbsp;'.__('<em>Memory used:</em> probably running older PHP (before 5.2) on a system with no memory limit configured.','wptuner')."</li>\n";
			print "</ul>\n";
		} 
	
		global $wp_version;
		print '<br/><br/>'.sprintf(__('<em>Note:</em> Because you are running WP version %s, WP Tuner has enhanced the following built-in WordPress function(s):','wptuner'),$wp_version). "<ul class='condensed'>\n";
		if (!function_exists('current_filter')) {
			print '<li>&nbsp;&nbsp;'.__('<em>current_filter()</em> function emulation, so filters can be tracked (WP versions before 2.5)','wptuner')."</li>\n";
		}
		if (defined('WPTUNER_USING_ALTQUERY')) {
			print '<li>&nbsp;&nbsp;'.__('<em>DB query()</em> function, so performance can be tracked (WP versions 2.0.6 to 2.3.2)','wptuner')."</li>\n";
		}
		print '<li>&nbsp;&nbsp;'.__('<em>DB get_caller()</em> function, so specific DB access can be tracked (WP versions 2.0.6 to present)','wptuner')."</li>\n";
		print "</ul>\n";

 } else {
	// There's at least one problem.
	
		print '<br/><b>'.__('Configuration Incomplete. Number of errors: ','wptuner').$configErrCount."</b>
		<br/><em>(".__('Fix issues in the order presented. One issue may be the actual cause of all errors. Reload this page two+ times after making any change. If the problem is not resolved, deactivate and reactivate again.','wptuner').")</em><ul>\n";
		$errFound = false;
		if (WPTUNER_NOTCONFIG) {
			if (defined('WPTUNER_ERR_NOCFGFILE')) // can't read config file
			{
				printf(__("<li>wp-config.php can't be read. Permissions are %s, and should be at least 0644. Check your wordpress folder permissions as well.</li>\n",'wptuner'),wptuner_wpconfig_perm());
				$errFound=true;
			}
			if (defined('WPTUNER_ERR_WPT_TRASH')) // wp-config has leftover WP Tuner trash (might be good trash)
			{
				_e("<li>wp-config.php contains WP Tuner markers, but they are <i>after</i> the ABSPATH definition. Please clean up the file and reinstall WP Tuner.</li>\n",'wptuner');
				$errFound=true;
			}
			if (defined('WPTUNER_ERR_CFG_NOWRITE')) // wp-config can't be written - write permission
			{
				printf(__("<li>wp-config.php can't be written. Permissions are %s, and should be at least 0644. Check your wordpress folder permissions as well.</li>\n",'wptuner'),wptuner_wpconfig_perm());
				$errFound=true;
			}
			if (defined('WPTUNER_ERR_CFG_NOBACKUP')) // wp-config can't be backed up (for inject or remove)
			{
				printf(__("<li>wp-config.php can't be backed up. Your wordpress directory (%s) permissions are %s, and should be at least 0755.</li>\n",'wptuner'),ABSPATH,wptuner_root_perm());
				$errFound=true;
			}
			if (defined('WPTUNER_ERR_CFG_NODATA')) // wp-config can't be written - data not written
			{
				_e("<li>No data can be written to wp-config.php -- Please check disk space.</li>\n",'wptuner');
				$errFound=true;
			}

			$errFound=true;
			_e("<li>If Auto-config remains broken, please find a way to add the following code after your DB_* definitions in wp-config.php:<br/>
	<pre style='padding:3px;line-height:11px;background-color:#eee'>
//-WP Tuner Plugin by MrPete------------
//--------------------------------------
\$wpTunerStart = microtime();		// get start time as early as we can
if ( function_exists( 'getrusage' ) ) { \$wpTunerStartCPU = getrusage(); }
@include_once(dirname(__FILE__).'/wp-content/plugins/wptuner/wptunertop.php'); // fire up WPTuner
//--------------------------------------
//-END WP Tuner Plugin------------------
</pre></li>\n",'wptuner');
		}
		if (WPTUNER_BADCONFIG) {
			$errFound=true;
			_e("<li>WP-Config code badly placed: please check your wp-config.php file. wptunertop.php is loaded <em>too early</em>. The DB_* constants must be defined before wptunertop is included.</li>\n",'wptuner');
		}
		if (!$errFound && WPTUNER_NOQUERIES){
			_e("<li>Technical issue: Configuration appears correct, yet WP Tuner could not be loaded before wp-db.php, so SAVEQUERIES is not defined. WP Tuner is unable to analyze your database use. If you need further help, please contact MrPete.</li>\n",'wptuner');
		}	
		print '</ul>';
	}
	print "</td></tr>\n";
		
		
		
if (0) { // hide this option for now-- always set!
	//wpt_show_radio( 'bShowOverview',__('Show WP Tuner Overview','wptuner'), 
	//	__('No.','wptuner'), '',
	//	__('Yes, show a one-line performance summary','wptuner'), __('Turn this on, and everything else off, to ensure WP Tuner\'s output is minimal. (Slow queries will still be shown.)','wptuner') );
	}

	echo '
      <tr valign="top">
				<th scope="row">'.__('Slow Query Threshold','wptuner').'</th>
				<td>'.
				sprintf(__('Highlight anything that takes longer than %s seconds.','wptuner'),
				'<input type="number" name="fSlowTime" value="'.$cSlow.'" maxlength="10" size="3" />').
				' <em>['.__('Not affected by Presets','wptuner')."]</em><br/>\n".
					__('<small>(Normally, 0.5 seconds is a good setting. If your whole site is slow, set a higher number.)</small>','wptuner').
				'</td></tr>';
				
	wpt_show_radio( 'bAvoidQueryTesting',__('Ignore Query Contents','wptuner'), 
		__('No.','wptuner'), '',
		__('Yes, cut WP Tuner overhead to bare minimum.','wptuner'), __('Disables query optimization hints, per-table stats, and invalid query detection. Slow queries will still be seen.','wptuner').' <em>['.__('Not affected by Presets','wptuner')."]</em>" );

	echo '</table>';
				
	print '<h3>'.__('Performance Analysis','wptuner')."</h3>". __('This section summarizes: overall WordPress performance, per-plugin DB performance, and per-table performance. Helps find plugins and tables that need optimization or better indexes.','wptuner')."\n";

  print '<table class="form-table">'."\n";
	wpt_show_radio( 'bShowTime',__('Show Performance Analysis','wptuner'), 
		__('No.','wptuner'), '',
		 __('Yes, display time and database performance','wptuner'), '' );

	print '</table><h3>'.__('SQL Query Analysis','wptuner')."</h3>".__('This section shows each query: what is it, how quickly it runs, detailed explanation usable for optimization. From these you may learn details of speeding up specific database queries. <em>This is mostly of use to developers.</em>','wptuner')."\n";

  print '<table class="form-table">'."\n";
	wpt_show_radio( 'bShowSQL',__('Show SQL Query Analysis','wptuner'), 
		__('No.','wptuner'), '',
		__('Yes, display query analysis','wptuner'), '' );

	wpt_show_radio( 'bShowAll',__('Show All Queries','wptuner'), 
		__('No, only display slow or invalid database queries','wptuner'), '',
		__('Yes, always show every query','wptuner'), __('Typically used by developers to understand database use in more detail.','wptuner') );

	wpt_show_radio( 'bShowDetail',__('Explain Each Query, With Optimization Hints','wptuner'), 
		__('No. Show query text, but no detailed analysis','wptuner'), '',
		__('Yes, show which indexes are used and more','wptuner'), __('This helps when trying to speed up a slow query. <a href="http://dev.mysql.com/doc/refman/5.0/en/using-explain.html">The MySQL Site</a> provides helpful hints on using the detail you see.','wptuner') );

	wpt_show_radio( 'bChargePlugins',__('"Charge" queries to Plugins or Core?','wptuner'), 
		__('<em>Core:</em> when plugins trigger DB queries through core functions, allocate the time used to Core.','wptuner'),
		__('Developers sometimes find this setting helpful: it always records the exact file/line where queries are made.','wptuner'),
		__('<em>Plugins:</em> time is allocated to the plugin for any DB query it triggers, even if indirectly.','wptuner'),
		__('This is the best way to determine overhead cost of a plugin.','wptuner'), '' );

	print '</table><h3>'.__('Debug Log','wptuner')."</h3>".__('Displays WP Tuner error messages. Also available for developer use: <code>wpTuneLog(\'text\');</code> will capture anything you like, and tie it to file and line number.','wptuner') ."\n";

  print '<table class="form-table">'."\n";
	wpt_show_radio( 'bShowLog',__('Show Debug Log','wptuner'), 
		__('No','wptuner'), '',
		__('Yes, display WP Tuner log entries','wptuner'),'' );

   echo '</table>';
   echo '<p class="submit">
        <input type="submit" name="wptuner_action" value="'.$strUpdate.'" /> 
				</p>';
	 echo "</div></div>\n"; // End of two columns

	 echo "<div class='wptuner_foot'>\n";			
 	print '<p id="pluginauthor">'.sprintf(__("<em><b>This plugin is only visible to site admins.</b></em><br/>
Like it? Tell a friend. Hate it? Tell %sMr Pete%s. Did it help? Tell %syour story%s. Love it? Help fill the %sTip Jar%s. Your generosity helps Mr Pete and his team meet people's needs around the world!",'wptuner'),
"<a href='http://blogs.icta.net/plugins/wptuner'>","</a>", 
"<a href='http://blogs.icta.net/plugins/wptuner'>","</a>", 
"<a href='http://blogs.icta.net/plugins/tipjar'>","</a>").
 ((__('Translator Name','wptuner') == 'Translator Name') ? '' : '<br/>'.sprintf(__('Credit: MrPete is grateful to <a href="%s">%s</a> for this %s translation!','wptuner'),__('http://Translator.Website','wptuner'),__('Translator Name','wptuner'),__('Translator Language','wptuner'))).'
			</p> 
			</div> 
    </form>
  </div>';

} 

?>