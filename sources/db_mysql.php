<?php

/*
	Copyright (C) 2003-2005 UseBB Team
	http://www.usebb.net
	
	$Header$
	
	This file is part of UseBB.
	
	UseBB is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.
	
	UseBB is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with UseBB; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * MySQL database driver
 *
 * Contains the db class for MySQL handling.
 *
 * @author	UseBB Team
 * @link	http://www.usebb.net
 * @license	GPL-2
 * @version	$Revision$
 * @copyright	Copyright (C) 2003-2005 UseBB Team
 * @package	UseBB
 * @subpackage Core
 */

//
// Die when called directly in browser
//
if ( !defined('INCLUDED') )
	exit();

if ( !extension_loaded('mysql') )
	trigger_error('Unable to load module for database server "mysql": PHP mysql extension not available!');

@ini_set('mysql.trace_mode', '0');

/**
 * MySQL database driver
 *
 * Performs database handling for MySQL.
 *
 * @author	UseBB Team
 * @link	http://www.usebb.net
 * @license	GPL-2
 * @version	$Revision$
 * @copyright	Copyright (C) 2003-2005 UseBB Team
 * @package	UseBB
 * @subpackage Core
 */
class db {
	
	//
	// Variables in this class
	//
	var $connection;
	var $queries = array();
	
	//
	// Make a connection to the MySQL server
	//
	function connect($config) {
		
		//
		// Connect to server
		//
		$this->connection = @mysql_connect($config['server'], $config['username'], $config['passwd']) or trigger_error('SQL: '.mysql_error());
		
		//
		// Select database
		//
		@mysql_select_db($config['dbname'], $this->connection) or trigger_error('SQL: '.mysql_error());
		
	}
	
	//
	// Execute database queries
	//
	function query($query, $return_error=false) {
		
		global $functions;
		
		$this->queries[] = preg_replace('#\s+#', ' ', $query);
		$result = @mysql_query($query, $this->connection) or $error = mysql_error();
		if ( isset($error) ) {
			
			if ( $return_error ) 
				return $error;
			else
				trigger_error('SQL: '.$error);
			
		}
		return $result;
		
	}
	
	//
	// Fetch query results
	//
	function fetch_result($result) {
		
		return mysql_fetch_array($result, MYSQL_ASSOC);
		
	}
	
	//
	// Count row number
	//
	function num_rows($result) {
		
		return mysql_num_rows($result);
		
	}
	
	//
	// Last ID
	//
	function last_id() {
		
		return mysql_insert_id($this->connection);
		
	}
	
	//
	// Get used queries array
	//
	function get_used_queries() {
		
		return $this->queries;
		
	}
	
	//
	// Get server version info
	//
	function get_server_info() {
		
		return array(
			'MySQL',
			mysql_get_server_info($this->connection)
		);
		
	}
	
	//
	// Disconnect the database connection
	//
	function disconnect() {
		
		@mysql_close($this->connection);
		
	}
	
}

?>
