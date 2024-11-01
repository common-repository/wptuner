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
// This file contains the display code for WP Tuner
//
class wptunershow extends wptuner {
	var $wpts_aSQLdetails = array();     // DB query analysis (in pieces for further analysis)
	var $wpts_aDBbyTable = array();
	var $wpts_aDBbyCode  = array();
	var $wpts_aGoodQueries = array();
	var $wpts_aBadQueries = array();
	var $wpts_slowCount = 0;


//
// Config options
//
  var $bShowLog = true;			// show the debug log
  var $bShowTime = true;		// show perf timings
  var $bShowSQL  = true;		// show sql queries
  var $bShowAll = true;					// True: all queries shown; False: only slow and bad queries shown
  var $bShowDetail = false;			// True: show detailed query analysis for any that are shown
  var $bShowOverview = true;		// True: show a one-line overview
	var	$bChargePlugins = true;		// True: charge DB queries in WP core to any plugin that uses the function
  var $fSlowTime = 0.1;				// Any element slower than this number of seconds will be highlighted
  var $iDebugLevel = 0;			// internal wpTuner diagnostics when users are having trouble
  var $bAvoidQueryTesting = FALSE; // If this is set to TRUE, we don't examine queries. Can't tell if any are bad, show table usage, or explain details

	function wptunershow()
	{
		global $wptuner, $wptuner_options;
		
		foreach($wptuner_options as $key => $val) {
			$optval = get_option(WPTOPTBASE.$key);
			if ($optval === null) {
				$optval = $val;
				add_option(WPTOPTBASE.$key, $val);
			}
			$this->{$key} = $optval;
		}
	}
  
	function Show_All($bBgDark) 
	{
    global $wptuner;

		$wptuner->Mark_Time(__('Stop','wptuner'));
		$wptTimeStart=microtime(); // Show how long our calculations took...

    if ($bBgDark)
    {
    	$cBg = '#acd6ea';
    } else {
    	$cBg = '#c6e1e6';
    }
    
    if ((!($this->iDebugLevel & 128) && (!current_user_can('level_10')) ) )
    {
    	// Debug 128 allows non-admins
    		return; // Show nothing to normal people
    }
    
    // Top level admin sees a ton
    
    //=======================================================
    // Collect the analysis before display.
    // Allows us to evaluate our own performance!

		$this->Calc_SQL_Details(); // Used by performance and sql displays

    $strPerformance = $this->Show_Performance();
    $strSQLDetails  = $this->Show_SQL_Details();
    $strOverview    = $this->Show_Overview();
    $strDebugLog    = $this->Show_Log();

 		$wptTimeTotal=$this->TimeDelta($wptTimeStart, microtime());

    //=======================================================
    // Show header

			print "\n
\n<style type='text/css'>
\ndiv.wptuner { 
\nposition: relative; width: 800px; font-size: 10px; margin: 0 auto; padding: 0.2em;
\nbackground-color: {$cBg}; color: #444; border: 1px solid #eee; text-align: left;
\n}
\n.wptuner > a { color: blue; }
\n.wptuner .head { position: relative; top:0; width:100%; padding: 0; border-bottom: 1px solid black; }
\n.wptuner h1 { margin: 0; padding: 0; color: black; font-size: 2em;}
\n.wptuner h3 { margin: 0.5em; }
\n.wptuner table { position: relative; margin:0 auto; width: 98%; border: 2px solid black; border-collapse:collapse; }
\n.wptuner table.dbtime { width: 80%; }
\n.wptuner .l    { text-align: left }
\n.wptuner .r    { text-align: right }
\n.wptuner .error { background-color: #fcc }
\n.wptuner .slow  { background-color: #ff9 }
\n.wptuner td { border: 1px solid gray; vertical-align: top;}
\n.wptuner pre { width: 90%; }
\n.wptuner .technote { margin: -0.5em 0.5em 0.5em; padding: 0; }
\n</style>
\n<br clear='all'/>
\n<div class='wptuner'><div class='head'>
\n<h1 id='wptuner'>".__('WP Tuner','wptuner')."</h1>
\n".
sprintf(__("<em><b>This plugin is only visible to site admins.</b></em>
(Update settings %shere%s; Visit %sMrPete at ICTA%s for bouquets/brickbats/help. Love it? Fill the %sTip Jar%s.)<br /> 
Analysed in %.3f seconds. ",'wptuner'),
"<a href='".WPTUNER_SITEURL."/wp-admin/options-general.php?page=wptunersetup.php'>","</a>",
"<a href='http://blogs.icta.net/plugins/wptuner'>","</a>", 
"<a href='http://blogs.icta.net/plugins/tipjar'>",
"</a>", $wptTimeTotal);

  if ($this->iDebugLevel) {
  	include_once("wptunerdebug.php");
  	wpTuneDebug($this);
  	if ($this->iDebugLevel & 128) {
  		print "\n</div></div>\n";
  		return;
  	}
	}  	
  	
  if (!($this->bShowAll || $this->wpts_slowCount || $this->badCount || $this->bShowTime))
  	_e('Display of detailed analysis is disabled.','wptuner');
	print "\n</div>";
			//=====================================================
			// Show analysis
			
		if (WPTUNER_NOQUERIES || WPTUNER_BADCONFIG || WPTUNER_NOTCONFIG)
		{
				print '<b><em>'.__('WP Tuner is not correctly configured. Please go to the Settings page using the link above, and set it up correctly.','wptuner')."</em></b>\n";
		}	
		
		$this->ShowIf('', $strOverview );
		$this->ShowIf(__('Debug Log','wptuner'), $strDebugLog);
		//$this->ShowIf('Traffic Counters', $wpTraffic->Display());
		$this->ShowIf(__('Performance Analysis','wptuner'), $strPerformance);
		$this->ShowIf(__('SQL Query Analysis','wptuner'), $strSQLDetails);
		
		
		print "\n</div><!-- wptuner -->\n";
	}
	
	function ShowIf($title,$str)
	{
		if (strlen($str)) {
			if (strlen($title))
				print "<h2>$title</h2>\n";
			print $str;
		}
	}

//
// Utility Functions
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
	
	// ==================================================================
	//  Raw Query for debug/analysis
	//  THIS IS AN UNFILTERED, UNPROTECTED, UNRECORDED QUERY.
	//  NEVER USE THIS FOR PRODUCTION PURPOSES!!!  **NEVER**
	
	function debug_query($query) {
		global $wpdb;
		
		if ( ! $wpdb || (isset($wpdb->ready) && ! $wpdb->ready) )
			return false;

		// initialise return
		$return_val = 0;
		$wpdb->flush();

		unset( $dbh );
		$dbh =& $wpdb->dbh;
		$wpdb->last_db_used = "other/read";

		return @mysql_query($query, $dbh);
	}
	
	//
	// N-dimensional array sort
	//
	function multisort($data,$keys){
  // (From php.net)
  // List As Columns
  foreach ($data as $key => $row) {
    foreach ($keys as $k){
      $cols[$k['key']][$key] = $row[$k['key']];
    }
  }
  // List original keys
  $idkeys=array_keys($data);
  // Sort Expression
  $i=0;
  foreach ($keys as $k){
    if($i>0){$sort.=',';}
    $sort.='$cols['."'".$k['key']."']";
    if($k['sort']){$sort.=',SORT_'.strtoupper($k['sort']);}
    if($k['type']){$sort.=',SORT_'.strtoupper($k['type']);}
    $i++;
  }
  $sort.=',$idkeys';
  // Perform the sort
  $sort='array_multisort('.$sort.');';
  eval($sort);
  // Rebuild Full Array
  foreach($idkeys as $idkey){
    $result[$idkey]=$data[$idkey];
  }
  return $result;
}

//
// SQL Analysis
//
// WP collects queries and query times during page generation, if SAVEQUERIES is true.
// At the bottom of the page, we parse the collected data for display.
//

//
// NOTE: This function is only used at the end of page generation!
//

	function Parse_Query($cQuery) {
		global $wpdb;

		if (! $wpdb || (isset($wpdb->ready) && ! $wpdb->ready) )  // wpdb->ready not in all versions
			return;
			
		list($query, $time, $where) = $cQuery;

		if (!$this->bAvoidQueryTesting) {
			// Decide which table is primarily involved (might be multiples)
			if(preg_match(
			   "/^\\s*(set|truncate|select found_rows\(\)|describe|desc|optimize table|analyze table|backup table|check table|checksum table|repair table|restore table|alter table|create table(?: if not exists)?|show (?:tables|variables)(?: like '.*')|show table status|insert( into|)|delete (table|.*from)|update|replace|[\s(]*(?:show|(select))(?:.*from|(?:.*)))[\\s'`(]*([^'`(\\s]*)/is",
			   $query,$matches)) {
				$curtable=$matches[5]; // got the table name for almost all queries
				$qtype=$matches[4];    // 'select' if this is a select query
				if (!strlen($curtable)||!strcasecmp('set',$matches[1])) // 'set' commands are never table-related
					$curtable='(N/A - Non-Table Query)';
				//wpTuneLog('<pre>Match: '.htmlspecialchars(print_r($matches, true)).'</pre>');
			} else {
				$curtable='-Other-';
				$qtype='';
				wpTuneLog(__('<b>wpTuner Report</b><br/>Please report to MrPete.  WP Tuner could not match this unusual (or possibly invalid) database query. (WP Tuner and your system are still working ok):<br/>','wptuner').'<pre>'.htmlspecialchars(print_r($query, true)).'</pre><br/>'.$where);
			}
		} else {
			$curtable='-Other-';
			$qtype='';
		}
		
		// Decide which code section is involved (this can be misleading)
		$codetype=$codeplugin=$codemodule='';
		if( preg_match(
"#^\\s*(?:wp-content[/\\\\](?:mu-)?plugins[/\\\\](?:(?P<plugbase>[^/\\\\]+)\.php|(?P<plugfolder>[^/\\\\]+)[/\\\\])|wp-content[/\\\\]themes[/\\\\](?P<theme>[^/\\\\]+)|wp-includes[/\\\\](widgets)\\.php|wp-admin|require_once|[^/\\\\]+\\.php)#i",
		   $where,$matches) ) {
		   	// Found a useful site section
		   	switch (count($matches)) {
		   		case 3: $codetype=__('plugin','wptuner'); $codename=$matches['plugbase']; break;
		   		case 5: $codetype=__('plugin','wptuner'); $codename=$matches['plugfolder']; break;
		   		case 7: $codetype=__('theme','wptuner') ; $codename=$matches['theme']; break;
		   		case 8: $codetype=__('-Core-','wptuner'); $codename='widgets'; break;
		   		case 1: $codetype=__('-Core-','wptuner'); $codename=$matches[0]; break;
		   		default: 			//wpTuneLog('<pre>'.htmlspecialchars(print_r($matches,true)).'</pre>');
		   						$codetype=__('-Core-','wptuner'); $codename=$matches[0]; break;
		   	}
		} else {
			//wpTuneLog('No match for: '.$where);
			$codetype=__('-Core-','wptuner'); $codename=__('(Other)','wptuner');
		}
		if ($codename=='require_once') {
			// A query slipped in before we could replace the get_caller function
			$codename = $where = __('(DB Constructor/Startup)','wptuner');
		}
		$codekey = $codetype.$codename;
		//wpTuneLog("Count: ".count($matches).", Code type: $codetype, name: $codename");

		$nFields=0;
		$bExplained = FALSE;
		$bIsSelect = FALSE;
		
		if (!$this->bAvoidQueryTesting) {
			if (!strcasecmp($qtype,'SELECT')) {
				$bIsSelect = TRUE;
				$sQryRes= @$this->debug_query("EXPLAIN {$query}");
				if ($sQryRes) { // There's something to explain
				$nFields = @mysql_num_fields($sQryRes);
				$bExplained = TRUE;
				}
			} else {
				$bExplained = FALSE;
				$nFields=0;
			}
		}

		$this->db_time += $time;

		// Record Basic query info
		$t = array();
		//$t['marker']=''; // later: page build phase
		$t['caller']=$where;
		$t['query']=$query;
		$t['ok']=$bIsSelect ? ($sQryRes ? TRUE : FALSE) : TRUE; // assume all non-select queries are OK
		$t['error']=$bIsSelect ? ($sQryRes ? '' : mysql_error()) : '';
		$t['nFields']=$nFields;
		$t['timetot']=$this->db_time;
		$t['time']=$time;
		$t['slow']=false;
		if ($time > $this->fSlowTime)
		{
			$t['slow']=true;
			$this->wpts_slowCount++;
		}
			
		if ($bExplained) {
			$bRowHeaders=FALSE;
			$t['explain'] = "";
			while ($row = mysql_fetch_assoc($sQryRes)) {
				if (!$bRowHeaders) {
					$bRowHeaders=TRUE;
					$t['explain'].=$this->table_headrow(array_keys($row));
				}

				$t['explain'] .= $this->table_datarow(array_values($row),true);
			}
		} else {
			$t['explain'] = '';
		}

		if (array_key_exists($curtable, $this->wpts_aDBbyTable)) {
			$this->wpts_aDBbyTable[$curtable]['DB Time'] += $time;
			$this->wpts_aDBbyTable[$curtable]['DB Count']++;
		} else {
			$this->wpts_aDBbyTable[$curtable]['Table']=$curtable;
			$this->wpts_aDBbyTable[$curtable]['%DB Time']=0;  // placeholder
			$this->wpts_aDBbyTable[$curtable]['%DB Count']=0; // placeholder
			$this->wpts_aDBbyTable[$curtable]['DB Time']=$time;
			$this->wpts_aDBbyTable[$curtable]['DB Count']=1;
		}

		if (array_key_exists($codekey, $this->wpts_aDBbyCode)) {
			$this->wpts_aDBbyCode[$codekey]['DB Time'] += $time;
			$this->wpts_aDBbyCode[$codekey]['DB Count']++;
		} else {
			$this->wpts_aDBbyCode[$codekey]['Code']=$codetype;
			$this->wpts_aDBbyCode[$codekey]['Name']=$codename;
			$this->wpts_aDBbyCode[$codekey]['%DB Time']=0;  // placeholder
			$this->wpts_aDBbyCode[$codekey]['%DB Count']=0; // placeholder
			$this->wpts_aDBbyCode[$codekey]['DB Time']=$time;
			$this->wpts_aDBbyCode[$codekey]['DB Count']=1;
		}


		@mysql_free_result($sQryRes);
		
		return $t;
	}

	function Calc_SQL_Details() {
		global $wpdb,$wpdbtmp;
		//
		// Calculate stats from query array
		//
		$this->nQueries=$wpdb->num_queries;

		if (WPTUNER_NOQUERIES || !$this->nQueries) return '';

		//
		// ALWAYS summarize query errors
		//
		$this->badCount=0;
		$this->okCount=0;
		if (!isset($wpdb->queries) )
		{
			wpTuneLog(__('WP Tuner Report: (please report) I could find no queries at all!!??','wptuner') );
			$this->nQueries=0;
			return;
		}
			
		foreach ($wpdb->queries as $idx => $cQuery) {
			$parsedQuery = $this->Parse_Query($cQuery,TRUE);
			$this->qList[$idx] = $parsedQuery;
			if ($parsedQuery['ok']) {
				$this->okCount++;
			} else {
				$this->badCount++;
			}
		}
	}
	
	//
	// Display an overview. This must be called after all other calculations are complete
	//
	
	function Show_Overview() {
		global $wpdb,$wpTunerStartCPU,$wpTunerEndCPU;
		if (!$this->bShowOverview)
			return '';
		$rinfo = '';

		if ( function_exists( 'getrusage' ) && isset($wpTunerStartCPU) ) {
			$cpuUTime = $wpTunerEndCPU['ru_utime.tv_sec'] + ($wpTunerEndCPU['ru_utime.tv_usec'] * 1e-6); // User CPU
			$cpuSTime = $wpTunerEndCPU['ru_stime.tv_sec'] + ($wpTunerEndCPU['ru_stime.tv_usec'] * 1e-6); // System CPU
			$cpuUStart = $wpTunerStartCPU['ru_utime.tv_sec'] + ($wpTunerStartCPU['ru_utime.tv_usec'] * 1e-6);
			$cpuSStart = $wpTunerStartCPU['ru_stime.tv_sec'] + ($wpTunerStartCPU['ru_stime.tv_usec'] * 1e-6);
			$cpuStart = $cpuUStart + $cpuSStart; // CPU used before wp-config.php
			$cpuTot  = $cpuUTime + $cpuSTime;    // Total (User + System) CPU time from start
			$cpuTime = $cpuTot - $cpuStart;      // CPU while we were measuring clock time
			if ($cpuTime > $this->tot_time)			// CPU time can exceed clock time due to rounding
			{
				$cpuTime = $this->tot_time; // avoid confusing users
				$bCPUrounded = true;
			}
			$cpuPct = 100.0 * $cpuTime / $this->tot_time; // CPU load while we were measuring
			$rinfo = sprintf(__(' %.3f cpu sec (%d%% load%s, %.3f startup). Clock: ','wptuner'),$cpuTime, $cpuPct, ($bCPUrounded ? '*' : ''),$cpuStart);
		}
		//
		// Here's a good place to log CPU usage in case you want graphs and/or your host cares about that
		// e.g. (on a typical vhosted linux host)
		// 
		//	$logname = "/home/mysite/public_html/queryspeed.log";
		//	$logfp = fopen($logname, 'a+'); fwrite($logfp, "$cpuTot,$cpuPct,$cpuStart,$this->tot_time,$this->db_time,$this->nQueries\n");
		//  fclose($logfp);
		
		$minfo = '';
		if (function_exists("memory_get_usage")) 
			$minfo = sprintf(__(' Memory: %.1fMB','wptuner'), memory_get_usage()/(1024*1024) );
		$dbPercent = 100.0 * $this->db_time / $this->tot_time;
		
		if ($this->bAvoidQueryTesting) {
			$sBadCount = ''; // We know nothing about query validity
		} else {
			if ($this->badCount) {
				$sBadCount = ' '.sprintf(__('%d defective','wptuner'),$this->badCount).',';
			} else {
				$sBadCount = ' '.__('none defective','wptuner').',';
			}
		}
		if (!$this->wpts_slowCount && !$this->badCount)
			return sprintf(__('Render Time:%s %.3f sec (%.1f%% for queries). DB queries: %d,%s none &gt; %.3f sec.%s','wptuner'),$rinfo, $this->tot_time, $dbPercent, $this->nQueries, $sBadCount, $this->fSlowTime,$minfo);

		return sprintf(__('Render Time:%s %.3f sec (%.1f%% for queries). DB queries: %d,%s %d slow (&gt; %.3f sec)%s','wptuner'),$rinfo, $this->tot_time, $dbPercent, $this->nQueries, $sBadCount, $this->wpts_slowCount, $this->fSlowTime,$minfo);
	}
		

	function Show_SQL_Details() {
		global $wpdb;
		//
		// Show stats from query array
		//
		$text='';
		$nQueries=$this->nQueries;

		if ( (!$this->bShowSQL) || WPTUNER_NOQUERIES || !$nQueries) return $text;
		
		$badCount = $this->badCount;
		$okCount = $this->okCount;
		
		if (!($this->bShowAll || $this->wpts_slowCount || $this->badCount))
			return "<h3>".sprintf(__('%d Valid queries. None slow, none invalid. <em>Display of normal queries is disabled.</em>','wptuner'),$nQueries)."</h3>\n";

		$text .= __('Queries triggered by plugins calling Core functions are marked (file, line) at: ','wptuner');
		if ($this->bChargePlugins) {
			$text .= __('the plugin code that triggered the query','wptuner');
		} else {
			$text .= __('the actual location of the query in WP Core', 'wptuner');
		}
			
		if ($this->badCount) {
			$text .= "<h3>".sprintf(__('%d Query Errors','wptuner'),$badCount)."</h3>\n<table>\n";
			$text .= '<thead>'.$this->table_headrow(
				array(__('Index','wptuner'),
				__('Qtime<br/>(msec)','wptuner'),
				__('<span style="float:left">&nbsp;&nbsp;Query / Error</span>','wptuner'))).'</thead>';

			foreach ($this->qList as $idx => $cQuery) {
				if (!$cQuery['ok']) {
					$text .= "<tr class='error'><th scope='row' rowspan='2'>{$idx}</th>".
					($cQuery['slow'] ? "<td rowspan='2' class='slow'>" : "<td rowspan='2'>").number_format($cQuery['time'] * 1000.0, 4).
					"</td><td>".htmlspecialchars($cQuery['query']).'<br/>['. /* $cQuery['marker']." - ". */ $cQuery['caller'].
					"]</td></tr>\n<tr class='error'><td>".$cQuery['error']."</td></tr>\n";
				}
			}
			$text .= "\n</table><br/>\n";
		}

		//
		// Optionally list good queries
		//
		if ($okCount && ($this->bShowAll || $this->wpts_slowCount)) {
			$text .= "<h3>$okCount ".($this->bAvoidQueryTesting ? '' : __('Valid ','wptuner')).__('Queries (Order: Query Time)','wptuner').($this->wpts_slowCount ? " ({$this->wpts_slowCount} ".__('slow','wptuner').')' :'')."</h3>\n<table>\n";
			if ($this->bAvoidQueryTesting) {
				$text .= '<div class="technote">('.sprintf(__('This list does <em>not</em> highlight invalid queries, because "%s" is set.','wptuner'),__('Ignore Query Contents','wptuner')).")</div>";
			}				
			
			$text .= '<thead>'.$this->table_headrow(array('Index','Qtime<br/>(msec)','<span style="float:left">&nbsp;&nbsp;Query</span>'));

		$mySortKey='time'; $mySortType='desc'; //'asc'
		$mySort=$this->multisort($this->qList,array(array('key'=>$mySortKey,'sort'=>$mySortType)));

			foreach ($mySort as $idx => $cQuery) {
				if ($cQuery['ok']) {
					if ($this->bShowAll || $cQuery['slow'])
					{
						$text .= ($cQuery['slow'] ? "<tr class='slow'>" : "<tr>")."<th scope='row'>{$idx}</th>
	       	        <td>".number_format($cQuery['time'] * 1000.0, 3)."</td><td>".htmlspecialchars($cQuery['query']).'<br/>['. /* $cQuery['marker']." - ". */ $cQuery['caller']."]</td></tr>\n";
	       	}
				}
			}
			$text .= "\n</table><br/>\n";
		}


		//
		// Optionally list query details
		//
		if ($this->bShowDetail) {
			$text .= "<h3>Detailed Query Analysis</h3>\n";
				
			if ($this->bAvoidQueryTesting) {
				$text .= '<div class="technote"><b>'.sprintf(__('This section disabled, because "%s" is set.','wptuner'),__('Ignore Query Contents','wptuner'))."</b></div>";
				
			} else {
				$text .= '<p>'.sprintf(__(' The <a href="%s">MySQL Site</a> provides helpful hints on the details in this section.','wptuner'),__('http://dev.mysql.com/doc/refman/5.0/en/using-explain.html','wptuner'))."</p>\n";
				foreach ($this->qList as $idx => $cQuery) {
					$trClass = (strlen($cQuery['error']) ? " class='error'" : ($cQuery['slow'] ? " class='slow'" : '' ));
					if ($this->bShowAll || strlen($trClass))
					{
						$text .= "<table{$trClass}>\n";
						$text .= "<tr><td colspan='".$cQuery['nFields']."'><b>".$idx.") ".__('Query','wptuner').":</b> [". /*$cQuery['marker']." - ". */ $cQuery['caller']."]<br/>".htmlspecialchars($cQuery['query'])."</td></tr>\n";
						if (isset($cQuery['explain'])) {
							$text .= $cQuery['explain'];
						}
						if (strlen($cQuery['error'])) {
							$text .= '<tr><td><b>'.__('Error in query','wptuner').":</b></td></tr>\n<tr{$trClass}><td>".$cQuery['error']."</td></tr>\n";
						}
		
						$text .= "<tr><td colspan='".$cQuery['nFields']."'>".__(sprintf('<b>Query time:</b> %.4f (ms)',1000.0 *$cQuery['time']),'wptuner').'</td></tr></table><br />'."\n";
					}
				}
			}
		}

		return $text;
	}
	

//
// Performance Tracking
//

	function Show_Performance() {
		//
		// Stats by Time Marker
		//
		global $wptuner,$wpTunerStart, $timeend;
		$nQueries = $this->nQueries;
		$db_time = $this->db_time;
		$totTime=$this->tot_time = $this->TimeDelta($wpTunerStart, $timeend);
		$sStop = __('Stop','wptuner'); // Stop marker string -- may be translated

		if (!$this->bShowTime) return '';

		if (!$nQueries)
		{
			return __('No queries seen??? Perhaps this version of WP is incompatible with WP TUner.', 'wptuner');
		}
		

		$text = __('Queries triggered by plugins calling Core functions are charged to: ','wptuner');
			if ($this->bChargePlugins) {
				$text .=__('The Plugin','wptuner');
			} else {
				$text .=__('WP Core', 'wptuner');
			}
		$text .= sprintf(__('<br/>Yellow-highlighted rows indicate slow elements (more than %.3f seconds)','wptuner'),
							$this->fSlowTime);
		$text .= __('<h3>Page Generation Performance (Order: Chronological)</h3>','wptuner');
		$text .= '<div class="technote"><em>('.__('Tech note: If output buffering (OB) is not enabled at init, WP Tuner enables it so output size can be tracked.','wptuner').")</em></div>";
		$text .= "\n<table class='dbtime'>\n";
		$bRowHeaders=FALSE;
		reset($wptuner->aTimeMarks);
		$aSum=$wptuner->aTimeMarks[0]; // create a template from the 'real' array
		$aSum['Index']='';
		$aSum['Marker']='Total';
		$aSum['Time']=0;
		$aSum['DB Time']=0;
		$aSum['DB Count']=0;
		$aSum['Memory']='';

		while (list($tKey, $tMarker) = each($wptuner->aTimeMarks)) {
			$sSlowRowClass = '';
			if (!$bRowHeaders) {
				// First time: emit headers
				$bRowHeaders=TRUE;
				$text .= '<thead>'.$this->table_headrow( array_keys($tMarker),true,'Output');

				$aUnits = $tMarker;
				foreach ($aUnits as $key=>$val) {
					switch ($key) {
						case 'DB Time':
						case 'Time':
						$aUnits[$key] = __('(msec)','wptuner');
						break;
						default:
						$aUnits[$key] = '';
						break;
					}
				}
				$aUnits['Output'] = 'lev(bytes)';
				$aUnits['Memory'] = '(kb)';
				$text .= $this->table_headrow( $aUnits, true ).'</thead>';
			}

			$tMem = $tMarker['Memory'];
			$tMarker['Memory'] = ($tMem ? number_format($tMem/1024.0, 1) : '?'); // display if known
			if ($tMarker['Marker'] == $sStop) {
				$tMarker['Time']='&nbsp;';
				$tMarker['%Time']='&nbsp;';
				$tMarker['%DB Count']='&nbsp;';
				$tMarker['%DB Time']='&nbsp;';
				$tMarker['DB Time']='&nbsp;';
				$tMarker['Output']=$wptuner->aOBMarks[$tKey];
				$tMarker['DB Count']='&nbsp;';
			} else {
				// Convert from start time to delta time, i.e. from now to next entry
				$nextMarker=current($wptuner->aTimeMarks);
				$aNextT=$nextMarker['Time'];
				$aThisT=$tMarker['Time'];
				$thisDelta=$this->TimeDelta($aThisT, $aNextT);
				
				$aNextDB=$nextMarker['DB Count']; // nQueries at start of next
				$aThisDB=$tMarker['DB Count'];		// nQueries at start of this
				$aNextTimeTot=($aNextDB ? $this->qList[$aNextDB-1]['timetot'] : 0);
				$aThisTimeTot=($aThisDB ? $this->qList[$aThisDB-1]['timetot'] : 0);
				$tMarker['DB Time']=$this->TimeDelta($aThisTimeTot, $aNextTimeTot);
				$tMarker['DB Count']= $aNextDB - $aThisDB;
				$sSlowRowClass = (($tMarker['DB Time'] > $this->fSlowTime)||($thisDelta > $this->fSlowTime)) ? 'slow' : '';
				
				$aSum['Time'] += $thisDelta;
				$aSum['DB Time'] += $tMarker['DB Time'];
				$aSum['DB Count'] += $tMarker['DB Count'];
				$tMarker['Time']=number_format($thisDelta*1000.0, 1);
				$tMarker['%Time']=$totTime ? number_format(100.0 * ($thisDelta / $totTime), 0) : 0;
				$tMarker['%DB Count']=number_format(100.0 * $tMarker['DB Count'] / ($nQueries), 0);
				$tMarker['%DB Time']=$db_time ? number_format(100.0 * $tMarker['DB Time'] / $db_time, 0) : 0;
				$tMarker['DB Time']=number_format($tMarker['DB Time']*1000.0, 1);
				
				$tMarker['Output']=$wptuner->aOBMarks[$tKey];
			}

			$text .= $this->table_datarow( array_values($tMarker), true, $sSlowRowClass);
			if (isset($wptuner->aMarkNotes[$tKey])) {
				$text .= "<tr><td>&nbsp;</td><td colspan='4'>". $wptuner->aMarkNotes[$tKey]."</td></tr>\n";
			}
			if ($tMarker['Marker'] == $sStop) break;
		}

		$aSum['%Time']=$totTime ? number_format(100.0 * ($aSum['Time'] / $totTime), 0) : 0;
		$aSum['%DB Time']=$db_time ? number_format(100.0 * ($aSum['DB Time'] / $db_time), 0) : 0;
		$aSum['%DB Count']=($nQueries) ? number_format(100.0 * ($aSum['DB Count'] / ($nQueries)), 0) : 0;
		$aSum['Time']=number_format($aSum['Time']*1000.0, 1);
		$aSum['DB Time']=number_format($aSum['DB Time']*1000.0, 1);

		$text .= '<tfoot style="text-align:right">'.$this->table_headrow($aSum).'</tfoot>';
		$text .= "\n</table><br/>\n";

		if (WPTUNER_NOQUERIES || !$nQueries)
		{
			$text .= __('<h3>Cannot display query analysis. WP Tuner is not correctly configured.</h3>','wptuner');
			return $text;
		}

		//
		// Stats by Code Section
		//
		$text .= __('<h3>Plugin / Theme SQL Query Performance (Order: DB Time)</h3>','wptuner');
		$text .= "\n<table class='dbtime'>\n";

		$bRowHeaders=FALSE;
		//$mySort=ksort($this->wpts_aDBbyCode);
		$mySortKey='DB Time'; $mySortType='desc'; //'asc'
		$mySort=$this->multisort($this->wpts_aDBbyCode,array(array('key'=>$mySortKey,'sort'=>$mySortType)));
		$aSum=$mySort[0]; // create a template from the 'real' array
		$aSum['Code']='';
		$aSum['Name']='Total';
		$aSum['%DB Count']=0;
		$aSum['%DB Time']=0;
		$aSum['DB Time']=0;
		$aSum['DB Count']=0;

		foreach ($mySort as $curTable) {
			$sSlowRowClass = '';
			if (!$bRowHeaders) {
				$bRowHeaders=TRUE;
				$text .= 	'<thead>'.$this->table_headrow( array_keys($curTable) );
				$aUnits = $curTable;
				foreach ($aUnits as $key=>$val) {
					switch ($key) {
						case 'Code':    $aUnits[$key] = __('Category','wptuner'); break;
						case 'DB Time':	$aUnits[$key] = '(msec)';	break;
						default:				$aUnits[$key] = '';
						break;
					}
				}
				$text .= $this->table_headrow( $aUnits ).'</thead>';
			}

			$aSum['DB Time'] += $curTable['DB Time'];
			$aSum['DB Count'] += $curTable['DB Count'];
			$sSlowRowClass = ($curTable['DB Time'] > $this->fSlowTime) ? 'slow' : '';
			$curTable['%DB Count']=number_format(100.0 * $curTable['DB Count'] / $nQueries, 0);
			$curTable['%DB Time']=number_format(100.0 * $curTable['DB Time'] / $db_time, 0);
			$curTable['DB Time']=number_format($curTable['DB Time']*1000.0, 1);
			$text .= $this->table_datarow(array_values($curTable), true, $sSlowRowClass);
		}

		$aSum['%DB Time']=$db_time ? number_format(100.0 * ($aSum['DB Time'] / $db_time), 0) : 0;
		$aSum['%DB Count']=($nQueries) ? number_format(100.0 * ($aSum['DB Count'] / ($nQueries)), 0) : 0;
		$aSum['DB Time']=number_format($aSum['DB Time']*1000.0, 1);
		$text .= '<tfoot>'.$this->table_headrow( $aSum ).'</tfoot>';
		$text .= "\n</table><br/>\n";

		//
		// Stats by Table
		//

		$text .= __('<h3>SQL Table Performance  (Order: DB Time)</h3>','wptuner');
		
		if ($this->bAvoidQueryTesting) {
			$text .= '<div class="technote">('.sprintf(__('This section disabled, because "%s" is set.','wptuner'),__('Ignore Query Contents','wptuner')).")</div><br/>";
		} else {

			$text .= '<div class="technote"><em>('.__('Tech note: The first table in a query is recorded here; complex queries may reference many tables.','wptuner').")</em></div>";
	
			$text .= "\n<table class='dbtime'>\n";
	
			$bRowHeaders=FALSE;
			$aSum=$this->wpts_aDBbyTable['core']; // create a template from the 'real' array
			$aSum['Table']='Total';
			$aSum['%DB Count']=0;
			$aSum['%DB Time']=0;
			$aSum['DB Time']=0;
			$aSum['DB Count']=0;
	
			$mySortKey='DB Time'; $mySortType='desc'; //'asc'
			$mySort=$this->multisort($this->wpts_aDBbyTable,array(array('key'=>$mySortKey,'sort'=>$mySortType)));
	
	
			foreach ($mySort as $curTable) {
				$sSlowRowClass = '';
				if (!$bRowHeaders) {
					$bRowHeaders=TRUE;
					$text .= 	'<thead>'.$this->table_headrow( array_keys($curTable) );
					$aUnits = $curTable;
					foreach ($aUnits as $key=>$val) {
						switch ($key) {
							case 'DB Time':
							$aUnits[$key] = __('(msec)','wptuner');
							break;
							default:
							$aUnits[$key] = '';
							break;
						}
					}
					$text .= $this->table_headrow( $aUnits ).'</thead>';
				}
	
				$aSum['DB Time'] += $curTable['DB Time'];
				$aSum['DB Count'] += $curTable['DB Count'];
				$sSlowRowClass = ($curTable['DB Time'] > $this->fSlowTime) ? 'slow' : '';
				$curTable['%DB Count']=number_format(100.0 * $curTable['DB Count'] / $nQueries, 0);
				$curTable['%DB Time']=number_format(100.0 * $curTable['DB Time'] / $db_time, 0);
				$curTable['DB Time']=number_format($curTable['DB Time']*1000.0, 1);
				$text .= $this->table_datarow(array_values($curTable), true, $sSlowRowClass);
			}
	
			$aSum['%DB Time']=$db_time ? number_format(100.0 * ($aSum['DB Time'] / $db_time), 0) : 0;
			$aSum['%DB Count']=($nQueries) ? number_format(100.0 * ($aSum['DB Count'] / ($nQueries)), 0) : 0;
			$aSum['DB Time']=number_format($aSum['DB Time']*1000.0, 1);
			$text .= '<tfoot>'.$this->table_headrow( $aSum ).'</tfoot>';
			$text .= "\n</table><br/>\n";
		}
	
		return $text;
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
}

global $wptuner;
global $wptunershow;

$wptunershow = new wptunershow;

?>
