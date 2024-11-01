<?php
/*  Copyright 2008 ICTA / Mr Pete (email : WP-Tuner at ICTA dot net)

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
// This file contains the working code for WP Tuner
//
//  Installation Hints (temporary)

// this wptunertop module must be included VERY early on, before ANY db calls.
// best place: near the top of the wp-config.php file, but AFTER
// the database constants are defined.

// $wpTunerStart = microtime();					// get WP start time as early as we can
// include_once(dirname(__FILE__).'/wp-content/plugins/wptuner/wptunertop.php'); // fire up debug tools

define('WPTUNER_NOTCONFIG',((defined('ABSPATH')&&defined('WP_MEMORY_LIMIT')) ? 1 : 0 )); // if ABSPATH is set, either we're loaded way too late (in an older rev of WP), or (if WP_MEMORY_LIMIT is also set) in a new rev.

define('WPTUNER_BADCONFIG',(defined('DB_HOST') ? 0 : 1 )); // if DB_HOST is not set, we're loaded *too* early. No way to extend the db class

if (!defined('SAVEQUERIES'))
{
	define('SAVEQUERIES',1);
	define('WPTUNER_NOQUERIES',0);
} else {
	define('WPTUNER_NOQUERIES',1); 	// If WPDB is already loaded, we cannot save queries, because this constant is already set.
}

class wptuner {
	// Live performance tracking
	var $aOBMarks = array(0 => ''); // Track output buffer level at each time mark
	var $aMarkNotes = array();      // Other notes can be added and output...
	var $aTimeMarks = array();      // Overall time markers
	var $curTimeMark = 'Start';
	var $nTimeMarks = 0;            // Provide an array index for time marks. Stablizes 'current' function
	// Live logging
	var $aLog = array();	// Generalized debug log (only seen during debug)
	var $bLogToFile = false;
	var $bShowLog = true;

//
// Constructor. Can't depend on much of anything yet!
//

	function wptuner() {
		global $wpTunerStart;

		if (WPTUNER_NOTCONFIG) {
			$wpTunerStart = microtime(); // If we're not properly configured, then at least start timing now.
		}

		$this->aTimeMarks[0]=array(
		'Index' => 0,
		'Marker' => 'Start',
		'%Time' => 0,
		'%DB Time' => 0,
		'%DB Count' => 0,
		'Time' => ($wpTunerStart),
		'DB Time' => 0,
		'DB Count' => 0,
		'Memory'   => 0
		);
			}
			
	function Show_All($bBgDark) 
	{

//
// This is a placeholder, only used if WP Tuner is not activated
//
		$this->Mark_Time(__('Stop','wptuner'));
		$wptTimeStart=microtime(); // Show how long our calculations took...
		
    // Top level admin sees a ton
    

    //=======================================================
    // Show header
    if ($bBgDark)
    {
    	$cBg = '#acd6ea';
    } else {
    	$cBg = '#c6e1e6';
    }

			print "\n
\n<style type='text/css'>
\ndiv.wptuner { 
\nposition: relative; width: 800px; font-size: 10px; margin: 0 auto; padding: 0.2em;
\nbackground-color: {$cBg}; border: 1px solid #eee; text-align: left;
\n}
\n.wptuner .head { position: relative; top:0; width:100%; padding: 0; border-bottom: 1px solid black; }
\n.wptuner h1 { margin-top: 0; }
\n.wptuner table { position: relative; margin:0 auto; width: 98%; border: 2px solid black; border-collapse:collapse; }
\n.wptuner table.dbtime { width: 80%; }
\n.wptuner .l    { text-align: left }
\n.wptuner .r    { text-align: right }
\n.wptuner .error { background-color: #fcc }
\n.wptuner .slow  { background-color: #ff9 }
\n.wptuner td { border: 1px solid gray;}
\n</style>
\n<br clear='all'>
\n<div class='wptuner'><div class='head'>
\n<h3>".__('WP Tuner (Injected, not activated)','wptuner')."</h3>
\n".__('This message only visible to site admin, and will disappear if WP Tuner plugin is active or uninstalled.','wptuner');
		print $this->Show_Log()."
\n</div>";
		print "\n</div><!-- wptuner -->\n";
	}
	
//
// Temp functions: debug before wpTuner is activated
//
	function table_headrow( $ary, $bUseBlanks =false, $extra ='')
	{
		// Translate header texts as needed
		
		if ($extra)
			$extra = '<th scope="col">'.$extra.'</th>';
		if ($bUseBlanks)
			$b = '&nbsp;';
		else
			$b = '';
		return 	'<tr class="r"><th class="l" scope="col">'.implode($b."</th><th scope='col'>", $ary).$b.'</th>'.$extra.'</tr>'."\n";
	}
	function table_datarow( $ary, $bUseBlanks =false, $sRowClass = '' )
	{
		$b = '';
		if ($bUseBlanks)
			$xb = '&nbsp;';

		return 	"<tr class='{$sRowClass} r'><td class='l'>".implode($b.'</td><td>', $ary).$b.'</td></tr>'."\n";
	}

	
	function Show_Log($bNeedHeader=false){
		global $wptuner;

		if (!$this->bShowLog || !count($wptuner->aLog)){
			return FALSE;
		}
		//
		// Dump the debug log
		//
		
		// Decide what the log dump will look like
		// bSendTags is NOT just about tags. File output is done in real-time, i.e. line-by-line
		$bSendHeader = (!$wptuner->bLogToFile || $bNeedHeader); // Need some kind of header for normal log and first file log
		$bSendTags = (!$wptuner->bLogToFile); // Send HTML tags for normal log dumps

		$text = '';
		if ($bSendTags)		$text .= "\n<table>\n";

		foreach ($wptuner->aLog as $curLog) 
		{
			if ($bSendHeader) {
				$bSendHeader=FALSE; // Only once in any case
				if ($bSendTags) {
					$text .= $this->table_headrow(array_keys($curLog));
				} else {
					$text .= "\nMessage [method(), file(line) ]\n";
				}
			}

			if ($bSendTags) {
				$text .= $this->table_datarow(array_values($curLog), true );
			} else {
				$text .= implode("", array_values($curLog))."\n";
			}
		}

		if ($bSendTags)
		{
			$text .= "</table><br/>\n";
		}

		return $text;
	}

	
	
	function SetFileOutput( $bSetOn )
	{
		$this->bLogToFile = $bSetOn;
	}

//
// Utility Functions
//
	/**
	* @return float         Time difference
	* @param time $tStart   Start time - unexploded microtime result
	* @param time $tStop    Finish time - unexploded microtime result
	* @desc Calculate time difference between two microtimes
	* @access public
	*/
	function TimeDelta($tStart, $tFinish ) {
		$tFrom = explode(' ', $tStart);
		$tTo = explode(' ', $tFinish);
		$tTot = ((float)$tTo[0] + (float)$tTo[1]) - ((float)$tFrom[0] + (float)$tFrom[1]);
		return $tTot;
	}

	/**
	* @return float         Absolute time from microtime
	* @param time $tStart   time - unexploded microtime result
	* @desc Return absolute time
	* @access public
	*/
	function TimeAbs($tStart ) {
		$tFrom = explode(' ', $tStart);
		return (float)$tFrom[0] + (float)$tFrom[1];
	}
	

//
// Performance Tracking
//

//
// This function needs high performance:
// It is run "live" in the middle of generating the page
//
//
// use $wptuner->Mark_Time('foo') to mark the beginning of a new section of code, such as startup, each plugin, etc
//
	function Mark_Time($sMarker) {
		global $wpdb;

global $wptunerct, $wptunershow;

		$timeNow=microtime();
		$queriesNow=$wpdb->num_queries;

		global $wpTunerEndCPU;
		if ( function_exists( 'getrusage' ) )
			$wpTunerEndCPU = getrusage();
		global $timeend; // use the WP standard variable for clock time
		$timeend = $timeNow;


		$nMarks=++$this->nTimeMarks; // # completed. Start is #0
	
		if (!strlen($sMarker)) {
			$sMarker = "Mark not set";
		}
	
		$this->aTimeMarks[$nMarks]=array(
		'Index' => $nMarks, // for display
		'Marker' => $sMarker,
		'%Time' => 0,
		'%DB Time' => 0,
		'%DB Count' => 0,
		'Time' => $timeNow,
		'DB Time' => 0,
		'DB Count' => $queriesNow,
		'Memory'   => ((function_exists("memory_get_usage"))? memory_get_usage() : 0)
		);
	
		$this->aOBMarks[$nMarks]=ob_get_level().'('.ob_get_length().')';
		$this->curTimeMark=$sMarker;
		
	
		// Add any desired notes to $this->aMarkNotes[$nMarks]... e.g.
		//global $wpTunerStart;
		//$this->aMarkNotes[$nMarks] .= "verify start: ".$wpTunerStart."<br/>";
	}

	
	function logtofile($message)
	{
		$fname = ABSPATH."wptunerlog.txt";
		$fh = fopen($fname,"a+");
		fwrite($fh,$message);
		fclose($fh);
	}
	
//
// Simple debug-level 'console' log
// Record a "nice" debug message with
// $wptuner->log("message");
//
// $TraceLev -- 0 = show who called log(); 1= show one level back on stack
// $bContextFunc = TRUE: show name of function that caller is in
//                           FALSE: show name of function called at $TraceLev line
//
	function log($message,$TraceLev=0,$bContextFunc=true)
	{
		static $bNeedHeader = true;
		
		if (!$this->bLogToFile) // HTML logs need multiple spaces expanded
		{
			$message = str_replace(' ','&nbsp;',$message);
		}
		
		if ($TraceLev == -1) {
			// dump full backtrace -- live debug only
			$btarray = debug_backtrace();
			foreach ($btarray as $bt) {
				$func = $bt['class'].$bt['type'].$bt['function'].'()';
				$file = substr($bt['file'], strlen(ABSPATH));
				$line = $bt['line'];
				$amsg[] = $file.'('.$line.'): '.$func;
			}
			
			$this->aLog[] =	array (
				'Message'   => '<pre>'.implode("\n",$amsg).'</pre>',
				'Function'	=> 'BackTrace',
				'File'	=> '',
				'Line'	=> ''
			);
			$this->aLog[] =	array (
				'Message'   => $message,
				'Function'	=> '',
				'File'	=> '',
				'Line'	=> ''
			);
			return;
		}
		
		if ($TraceLev!=='')
		{
			$funcLev = ($bContextFunc ? $TraceLev+1 : $TraceLev);
			$bt = debug_backtrace();
			$func = (isset($bt[$funcLev]['type']) && ($bt[$funcLev]['type'] == '::' || $bt[$funcLev]['type'] == '->') ? $bt[$funcLev]['class'].$bt[$funcLev]['type'].$bt[$funcLev]['function'] : $bt[$funcLev]['function']).'()';
			$file = substr($bt[$TraceLev]['file'], strlen(ABSPATH));;
			$line = $bt[$TraceLev]['line'];
			if ($this->bLogToFile)
			{
				$func = ' ['.$func;
				$file = ', '.$file;
				$line = '('.$line.') ]';
			}
			$this->aLog[] =	array (
				'Message'   => $message,
				'Function'	=> $func,
				'File'	=> $file,
				'Line'	=> $line
			);
		} else {
			$this->aLog[] =	array (
				'Message'   => $message,
				'Function' => '',
				'File' => '',
				'Line' => ''
			);
		}
		
		if ($this->bLogToFile) // Log immediately?
		{
			$this->logtofile($this->Show_Log($bNeedHeader)); // first time gets header plus first record
			$bNeedHeader = false;
			$this->aLog = array(); // reset array so later entries are "just dumped"
		}
	}

}

// Preload the DB. Can't assume I know much of anything, because WP just got going
// Assume I'm in a folder under plugins. Later, we can add code to work this out
//

@require_once( realpath(dirname(__FILE__). '/../../../wp-includes').'/wp-db.php');
@require_once( realpath(dirname(__FILE__). '/../../../wp-includes').'/version.php');

//
// From 2.0.6 to 2.3.2 we need, and can use this alternate version of the query method
// this one uses get_caller()... 
// Before 2.0.6: no filters
// After 2.3.2: the query() code is ok as-is (but get_caller() still needs my fix)
//
	global $wp_version;
	
	if (isset($wp_version) && version_compare($wp_version, '2.0.6', '>=') && version_compare($wp_version, '2.3.2', '<') )
	{
		// 2.0.6 forward
		define( 'WPTUNER_USING_ALTQUERY', 1);
		@require_once( dirname(__FILE__).'/wptunerdb206.php');
	}	else {
		// 2.3.2 forward (just fix get_caller())
		@require_once( dirname(__FILE__).'/wptunerdb232.php');
	}

//
// Replace $wpdb with the repaired object
// This will not be needed once the working get_caller() function is in place
//

global $wpdb,$wptuner,$wptunershow;
if (isset($wpdb))
{
	$tmp = new wpdb_wptunerversion();
	$wpdb = NULL;
	$wpdb = $tmp;
}	else {
	$wpdb = new wpdb_wptunerversion(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
}

//
// Now create the wptuner object
//

if ( ! isset($wptuner) )
	$wptuner = new wptuner();

$wptunershow = $wptuner;		// Temporary, and in case not activated

function wpTuneLog( $str, $level=1 )
{
	global $wptuner;
	$wptuner->log( $str, $level );
}
function wptL( $str, $level=1) {
	global $wptuner;
	$wptuner->log( $str, $level );
}

global $wptct;
$wptunerct=0;
// Add to any filter or action to mark it in the time log
function wpTuneFilterTime() {
		global $wptuner,$wptunershow,$wptct;
		if (function_exists(current_filter))
		{
			$wptuner->Mark_Time( current_filter() ); // Mark time for any filter or action that we hook
		} else {
			// Before 2.5 there was no easy way... so we do it the hard way.
			$bt = debug_backtrace();
			$wptuner->Mark_Time( (isset($bt[2]) && isset($bt[2]['args'])) ? $bt[2]['args'][0] : '??');
		}
	}

// Add anywhere to mark the time log
function wpTuneMarkTime($str="Marker") {
		global $wptuner;
			$wptuner->Mark_Time( $str );
	}
	

?>