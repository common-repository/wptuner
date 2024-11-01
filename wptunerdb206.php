<?php
//
// Part of the WP Tuner Plugin, Copyright 2008 ICTA / Mr Pete
//
// This subclasses wpdb, with replacement get_caller() and query()
// Used for 2.0.6 and after

class wpdb_wptunerversion extends wpdb {
	function wpdb_wptunerversion() {
		global $wpdb;
		if (isset($wpdb))
		{
			// A bit complicated: we're not properly installed, and $wpdb already exists. so,
			// we need to replicate it! Otherwise WP will die a horrible death...
			$oldVars = get_object_vars($wpdb);
			foreach ($oldVars as $key => $val)
			{
				$this->{$key} = $val;
			}
			
			// For backwards compatiblity before WP 2.3.2, we must fill in the third query record for any existing DB traces
			if (isset($this->queries)) {
				foreach ($this->queries as $key=>$val)
				{
					if (!isset($this->queries[$key][2]))
						$this->queries[$key][2]='';
				}
			}
		}
	}
	

		function query($query) {
		// filter the query, if filters are available
		// NOTE: some queries are made before the plugins have been loaded, and thus cannot be filtered with this method
		if ( function_exists('apply_filters') )
			$query = apply_filters('query', $query);

		// initialise return
		$return_val = 0;
		$this->flush();

		// Log how the function was called
		$this->func_call = "\$db->query(\"$query\")";

		// Keep track of the last query for debug..
		$this->last_query = $query;

		// Perform the query via std mysql_query function..
		if (SAVEQUERIES)
			$this->timer_start();

		$this->result = @mysql_query($query, $this->dbh);
		++$this->num_queries;

		if (SAVEQUERIES)
			$this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );

		// If there is an error then take note of it..
		if ( mysql_error($this->dbh) ) {
			$this->print_error();
			return false;
		}

		if ( preg_match("/^\\s*(insert|delete|update|replace) /i",$query) ) {
			$this->rows_affected = mysql_affected_rows($this->dbh);
			// Take note of the insert_id
			if ( preg_match("/^\\s*(insert|replace) /i",$query) ) {
				$this->insert_id = mysql_insert_id($this->dbh);
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} else {
			$i = 0;
			while ($i < @mysql_num_fields($this->result)) {
				$this->col_info[$i] = @mysql_fetch_field($this->result);
				$i++;
			}
			$num_rows = 0;
			while ( $row = @mysql_fetch_object($this->result) ) {
				$this->last_result[$num_rows] = $row;
				$num_rows++;
			}

			@mysql_free_result($this->result);

			// Log number of rows the query returned
			$this->num_rows = $num_rows;

			// Return number of rows selected
			$return_val = $this->num_rows;
		}

		return $return_val;
	}

	/**
	 * Get the name of the function that called wpdb.
	 * @return string: file, linenumber and name of the calling function
	 * 
	 * This version actually provides helpful info. The original simply says things like 'require_once'
	 */
	function get_caller() {
		global $wptunershow;
		$lastDBidx = 0;
		$bChargedToPlugin = FALSE;
		
		// requires PHP 4.3+
		if ( !is_callable('debug_backtrace') )
			return '';
		$bt = debug_backtrace();
		$caller = '';
		
//
// Scan for the first non-wpdb location on the stack
//
		foreach ( $bt as $idx => $trace ) {
			if (isset($wptunershow) && ($wptunershow->iDebugLevel & 256)) {
				wpTuneLog($idx.': '.$trace['file']."({$trace['line']}): {$trace['class']}{$trace['type']}{$trace['function']}()");
			}

			$parse=substr($trace['file'],strlen(ABSPATH),11);
			if (isset($trace['class'])) {
				switch ($trace['class']) {
					case __CLASS__: 
					case 'wpdb':		
						$lastDBidx = $idx;	// Find the outermost call into WPDB
						if ($idx && substr($parse,0,10) == 'wp-content') break 2; // If not the first (get_caller) item, break out of the search -- we found a plugin or theme
					continue 2; // continue the foreach
				}
			}

			if (substr($parse,0,10) == 'wp-content') 
			{
				break; // we found a plugin or theme, so stop here
			}			
			if (isset($wptunershow) && $wptunershow->bChargePlugins && $parse == 'wp-includes') {
				$bChargedToPlugin = TRUE;
				continue; // all include-functions get charged to the calling plugin!
			}
				
			break;
		}
		
		if ($idx && !strlen($bt[$idx]['file'])) {
			if (isset($wptunershow) && ($wptunershow->iDebugLevel & 512)) {
				wpTuneLog($idx.': backing off from '.$trace['file']."({$trace['line']}): {$trace['class']}{$trace['type']}{$trace['function']}()");
			}

			$idx--; // somehow we're in a black hole. Charge to whatever this one called
		}
		if (isset($wptunershow) && !($wptunershow->bChargePlugins)) {
			$idx = $lastDBidx;	// resets both if we're not charging plugins, AND during first few queries during startup
		}
		
		$trace = $bt[$idx]; // If not charging to the plugin, we want the final item from the foreach rather than the item after that
		$trace['file'] = substr($trace['file'],strlen(ABSPATH));
		if (strlen($trace['class'])) {
			if (substr($trace['class'],0,4)=='wpdb')
				$trace['class'] = 'wpdb';
			$trace['class'] .= '->';
		}
		$caller = $trace['file']."({$trace['line']}): {$trace['class']}{$trace['function']}()";
		return $caller;
	}
}
?>