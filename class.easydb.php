<?php

	/*

	EasyDB	
	Database Abstraction Layer
	Version: 0.3
	
	Author: Robert McLeod
	
	*/
	
	/* CHANGELOG
	
	***** Version 0.4 *****
	* Added dsn function to set login details on the fly	

	***** Version 0.3 *****
	* Fixed a bug in the getWhereString and escape functions
	
	***** Version 0.2 *****
	* Added a field argument to the find and findall function so that different id field
	*	names can be used.
	* Added a fetch function to return rows depending on the RETURN_OBJECT constant
	*	and the number of rows returned
	* Added RETURN_OBJECT constant to set returning objects (mysql_fetch_object)
	*	or return associative arrays (mysql_fetch_assoc)
	
	***** Version 0.1 *****
	* Converted from a function based library to a class library.
	
	*/
	
	/* Account info */
	define('DB_HOST','localhost');
	define('DB_USER','your_username');
	define('DB_PASS','your_password');
	define('DB_NAME','database_name');
	
	/* Default settings in constants */
	define('RETURN_OBJECT', 1); // Set to one to return objects instead of associative arrays
	define('DIE_ON_ERROR', 0);
	define('ECHO_ERRORS', 1);
	define('DEBUG_MODE', 1);
	define('DEBUG_MODE_DIE', 0); // Die after first query
	define('UPDATE_SAFETY', 1); // Protect against an update modifying all rows!!
	define('DELETE_SAFETY', 1); // Protect against deleting all rows
	define('ONE_QUERY_AT_A_TIME', 1); // You will get a warning if you have 2 queries going into DB::query()


	/** Class */
	class EasyDB
	{

		/* Allow changing of the connection details in the object */
		public $host = DB_HOST;
		public $user = DB_USER;
		private $password = DB_PASS; // Can set but not get the password
		public $db = DB_NAME;

		/* Count the number of queries in the object */
		private $queries = 0;
		
		/* Security options */
		private $update_safety = UPDATE_SAFETY;
		private $delete_safety = DELETE_SAFETY;
		private $one_query_at_a_time = ONE_QUERY_AT_A_TIME;
		
		/* Set the error behaviour and error variables */
		public $e = ECHO_ERRORS;
		public $doe = DIE_ON_ERROR;
		public $error = '';
		public $errno = 0;
		
		/* Error messages */
		private $errors = array(
			0 => 'Everything worked.',
			1 => 'Could not connect to the database server.',
			2 => 'Could not select database {DB}.',
			3 => 'No query was defined for EasyDB::read()',
			4 => 'No query was defined for EasyDB::query()',
			5 => 'Error escaping value in EasyDB::escape() - no string data returned.',
			6 => 'No WHERE defined for EasyDB:update() and UPDATE_SAFETY is on.',
			7 => 'WARNING SQL injection attempt detected!',
			8 => 'No WHERE defined for EasyDB::delete() and DELETE_SAFETY is on.',
			9 => 'An Error Occurred.',
			10 => 'Error escaping value in EasyDB::escape() - no array data returned.',
			11 => 'Error escaping value in EasyDB::escape() - invalid type argument.'
		);
		
		/* Define Object variables */
		private $dbh = false;
		private $resource = false;
		private $lastid = false;
		public $sql = false;

		function __construct() {
			$this->connect();
		}
		
		function __destroy() {
			$this->free();
			mysql_close($this->dbh);
		}

		public function dsn($dsn) {
			
			if (preg_match('#mysql://(\w+):(.+)@(.+)/(.+)#', $dsn, $m)) {
				$this->user = $m[1];
				$this->pass = $m[2];
				$this->host = $m[3];
				$this->db = $m[4];
			} else {
				return false;
			}

		}

		/**********************************************************
		***********************************************************
		************								***************
		************		SIMPLE FUNCTIONS		***************
		************	  AND PRIVATE VARIABLE		***************
		************		   MODIFIERS			***************
		************								***************
		***********************************************************
		**********************************************************/


		/**
		* Because the password should be private and not read
		* from outside the class we have a function to set it.
		*
		* @param string
		* @return bool
		*/
		public function setPassword($pw) {
			$this->password = $pw;
			return true;
		}
		
		/**
		* Return the number of queries that have been executed
		* so far.
		*
		* @return integer
		*/
		public function queries() {
			return $this->queries;
		}
	
		/**
		* Return the id of the last id inserted
		*
		* @return integer
		*/
		public function lastid() {
			return $this->lastid;
		}
	
		/**
		* Returns the type of query that is being executed
		*
		* @return string
		*/
		private function queryType() {
			return strtolower(substr($this->sql,0,6));
		}

		
		/**
		* Gives the number of rows returned from the last query
		*
		* @return integer
		*/
		function rows() {
		
			if(!$this->resource) {
				return false;
			}
			
			return mysql_num_rows($this->resource);
		}
		
		/**
		* Gives the number of rows returned from the last query
		*
		* @return integer
		*/
		public function fields() {
			
			if(!$this->resource) {
				return false;
			}
			
			return mysql_num_fields($this->resource);
		}
		
		/**
		* Disables the update safety for the NEXT QUERY ONLY
		*
		*/
		public function updateSafetyOff() {
			$this->update_safety == 0;
		}
		
		/**
		* Disables the delete safety for the NEXT QUERY ONLY
		*
		*/
		public function deleteSafetyOff() {
			// This means a delete without a where will
			// delete a whole table
			$this->delete_safety == 0;
		}
		
		/**
		* Allows multiple queries to be called for THE NEXT
		* DB::query() CALL ONLY.  This way you can put this before
		* a double query going into DB::query() that you know
		* is safe.
		*
		*/
		public function allowMultipleQueries() {
			$this->one_query_at_a_time == 0;
		}
		
		/**
		* Frees the results of the last query to clear up memory
		*
		*/
		public function free() {
			mysql_free_results($this->resource);
		}
		
		/**
		* Checks if the database is connected
		*
		* @return bool
		*/
		public function is_connected() {
			if($this->dbh) {
				return true;
			} else {
				return false;
			}
		}


		/**********************************************************
		***********************************************************
		************								***************
		************	MORE ADVANCED FUNCTIONS		***************
		************								***************
		***********************************************************
		**********************************************************/

		/**
		* Connects to the database server and selects the database
		*
		*/
		private function connect() {
			
			// Connect if we are not connected
			if(!$this->is_connected()) {
				$this->dbh = @mysql_connect($this->host,$this->user,$this->password) or $this->error(1);
				@mysql_select_db($this->db) or $this->error(2);
			} else {
				return true;
			}
			
			// Check if we are connected and return as such
			if(!$this->is_connected()) {
				return $this->error(1);
			}
		}

		/**
		* Prints out an error message to the page
		* Always returns false so a calling function
		* can return false at the when this function
		* is finished
		*
		* @param integer		optional
		* @return bool			ALWAYS FALSE!!!
		*/
		private function error($errno = 9) {
			
			// Get the error message form the defined ones in the class
			$message = str_replace('{DB}', $this->db, $this->errors[$errno]);
			
			// Set the error variable
			$this->error = $message;
			$this->errno = $errno;
				
			
			// Always print the mysql_error if there is one
			if(mysql_errno() > 0) {
				
				// Print the error number, error description, and query
				$message .= '<br/><br/>A MySQL Error occured.<br/>'
							.'Error Number: <strong>'.mysql_errno().'</strong><br/>'
							.'Error String: <strong>'.mysql_error().'</strong><br/>'
							.'Query: <strong>'.$this->sql.'</strong>';
							
			}
			
			// Echo the message
			if($this->e) {
				echo "<p style=\"color: red; border: 2px solid red; padding: 10px;\">$message</p>";
			}
			
			// Die if requested
			if($this->doe) {
				die();
			}
			
			// Should die if we can't get access to the database
			if(substr(mysql_error(), 0, 22) == 'Access denied for user') {
				die();
			}
			
			// Always return false so a calling function can return false
			// after this function runs in 1 line
			return false;
		}

		/**
		* Escapes array keys and values or a string
		* Returns string or array respective to the data argument
		*
		* @param mixed
		* @param string
		* @return mixed
		*/
		public function escape($data) {
		
			// Make sure we are connected
			if($this->connect() == false) {
				return false;
			}
		
			// Don't want to escape it twice
			$gpc_on = @ini_set('magic_quotes_gpc');
			
			// Check if we need to process a string or array
			if(is_string($data)) {
			
				if($gpc_on) {
					$data = stripslashes($data);
				}
			
				// Escape into string
				$string = @mysql_real_escape_string($data);
			
				// Return if it worked
				if($string) {
					return $string;
				} else {
					return $this->error(5);
				}
			
			} elseif(is_array($data)) {
				
				if($gpc_on) {
					foreach ($data as &$value) {
						$value = stripslashes($value); // Strip slashes
					}
				}
				
				// It is an array so:
				// Escape each key and value
				foreach ($data as $key => $value) {
					
					// Don't escape numbers
					if(is_numeric($value)) {
						$value = $value;
					} else {
						// Escape string
						$value = @mysql_real_escape_string($value);
					}
					
					// Put the escaped data into return
					$return[@mysql_real_escape_string($key)] = $value;
				}
				
				// Check that stuff was put into return
				if(!$return) {			
					// Error out
					return $this->error(10);
				}
				
				// Drop it
				return $return;
				
			} elseif(is_numeric($data)) {
				
				// We don't need to escape numbers
				return $data;
				
			}
			
			// Error out data given was not array, string, or number
			return $this->error(11);
			
		}

		/**
		* Return data fetched from a result set either in object or
		* associative array form
		*
		* A single row returned from the query results in a returning just that
		* type (object/array) whereas multiple rows returns an array of those types
		* (an array of objects/arrays)
		*
		* @since 1.0RC2
		* @return mixed
		*/
		private function fetch() {
			
			// Check if there is actually results
			if($this->rows() < 1)  {
				return false;
			}
			
			// Check if we want to return objects or associative arrays
			if(RETURN_OBJECT) {
				// Objects \\
				while($o = mysql_fetch_object($this->resource)) {
					$rows[] = $o; // Drop all the objects in with the objects function
				}
			} else {
				// Arrays \\
				while($a = mysql_fetch_assoc($this->resource)) {
					$rows[] = $a; // Drop all the arrays in with the assoc function
				}
			}
			
			// Check if we can return one row instead of an array of rows
			if(count($rows) > 1) {
				return $rows;
			} else {
				return $rows[0]; // Drop the single array or object returned
			}
			
			// Something borked
			return false;
			
		}

		/**
		* Process the array of a where statement and return
		* it as a string.
		*
		* Must be an array or string
		*
		* @param mixed
		* @return string;
		*/
		private function getWhereString($where) {
		
			// Return false if the argument is not an array or string
			if(!is_string($where) && !is_array($where)) {
				return false;
			}
			
			// Return the string we don't need to run this function
			if (is_string($where)) {
				return $where;
			}
			
			// Make a where string from the given array
			if(is_array($where)) {
			
				// Iterate through all the where values
				foreach ($where as $k => $v) 
				{
					// Check if the value is an array
					if(is_array($v)) {
						
						// Check if it is a match array
						if(strtoupper($v[0]) != 'MATCH') {
						
							// Set the closing var to nothing if there is no 4th element
							$closing = ($v[3]) ? ' ' . $this->escape($v[3]) : '';
							
							// Save the string
							$tmp[] = '`' . $this->escape($v[0]) . '` ' . $this->escape(strtoupper($v[1])) . ' \'' . $this->escape($v[2]) . '\'' . $closing;
							
						} else {
							
							// Default to boolean mode if it is a match
							$mode = ($v[3]) ? $this->escape($v[3]) : 'IN BOOLEAN MODE';
						
							$search = str_replace('\"','"',$this->escape($v[2]));
						
							// Save the match string
							$tmp[] = 'MATCH( `' . $this->escape($v[1]) . "` ) AGAINST( '$search' $mode )";
						
						}
					
					} elseif($v == 'OR') {
					
						// Someone put an OR here, add it in
						$tmp[] = $v;
						
					} else {
					
						// This is a normal key value pair
						$tmp[] = '`' . $this->escape(trim($k)) . '` = \'' . $this->escape($v) . '\'';
						
					}
				}
				
				$where = '';
				
				// Check that $tmp actually got started
				$c = 0;
				foreach($tmp as $t) {
					
					// Make sure we don't print an AND where an OR is
					if($t != 'OR') {
						// assign $t if it isn't and OR
						$where .= $t;
						
						// assign an AND if before and after aren't ORs
						if($tmp[$c++] != 'OR' && $tmp[$c--] != 'OR') {
							$where .= ' AND ';
						}
					} else {
						// Else assign the OR with spaces
						$where .= ' OR ';
					}
					
					++$c;
					
				}
				
				// Return the where string
				return ' WHERE ' . substr($where,0,-5);
				
			}
		}

		/**********************************************************
		***********************************************************
		************								***************
		************	  THE QUERY FUNCTION		***************
		************								***************
		***********************************************************
		**********************************************************/
		
		/**
		* Query function to query the database
		*
		* @param string		optional
		* @param resource
		* @return bool
		*/
		private function query($sql = false) {
		
			// Try to get the query from the argument
			// but use the the classes sql variable otherwise
			if(!$sql) {
				if(!$this->sql) {
					return $this->error(4);
				} else {
					$sql = $this->sql;
				}
			} else {
				$this->sql = $sql;
			}
		
			// Connect to the database
			$this->connect();
			
			// Check if DEBUG is on
			if(DEBUG_MODE) {
				echo "<p style='color: red; border: 2px solid red; padding: 10px;'>$sql</p>";
				if(DEBUG_MODE_DIE) {
					die('<br/><br/>debug...');
				}
			}
			
			// Check that we have the semicolon on the end
			if (!strpos($sql,';')) {
				$sql .= ';';
			}
			
			// SQL Injection checking
			if($this->one_query_at_a_time && preg_match_all('/;/',$sql,$m) > 1) {
				return $this->error(7);
			}
		
			// Run the query
			$this->resource = mysql_query($sql);
			$this->queries++;
			
			// Reset the update safety and single query running
			$this->update_safety = UPDATE_SAFETY;
			$this->delete_safety = DELETE_SAFETY;
			$this->one_query_at_a_time = ONE_QUERY_AT_A_TIME;
			
			// Check that no errors occured
			if(mysql_errno() > 0) {
				
				// Error out and return false
				return $this->error();
				
			} else {
			
				// Check the query type and return data as such
				switch ($this->queryType($sql)) {
					case 'insert';
						$this->lastid = mysql_insert_id();
						return mysql_insert_id();				// Return the id of the new row for an insert
						break;
					default:
						if($this->resource) {
							return true;						// Return true if the query was at least successful
						} else {
							return false;						// Otherwise thats a falseburger
						}
						break;
				
				} // End switch
			
			} // End error number check
			
		}

		/**********************************************************
		***********************************************************
		************								***************
		************	FUNCTIONS THAT SIMPLIFY		***************
		************  CALLS TO THE QUERY FUNCTION	***************
		************								***************
		***********************************************************
		**********************************************************/

		/**
		* Function to execute a select query and return the resultset as an array
		*
		* Returns an array on success, false on failure
		*
		* @param string 	optional
		* @return array
		* @return mixed
		*/
		public function read($sql = false) {
			
			// Try to get the query from the argument
			// but use the the $this->sql variable otherwise
			if(!$sql) {
				if(!$this->sql) {
					return$this->error(3);
				} else {
					$sql = $this->sql;
				}
			}
			
			// This is for select queries only
			if($this->queryType() != 'select') {
				return false;
			}
			
			// Run the query
			$this->query($sql);
			
			// Return the rows
			return $this->fetch();
			
		}
	
		/**
		* Function to insert stuff into the database
		* taking a table name and associative array
		* of fields and values to insert.
		*
		* @param string
		* @param array
		* @return integer	id of the inserted row
		*/
		public function insert($table,$data) {
			
			// Escape the array
			$data = $this->escape($data);
			
			// Get all our fields and values
			$values = '\'' . implode('\',\'', $data) . '\'';
			$fields = '`' . implode('`,`', array_keys($data)) . '`';
			
			// Run the query and return the insert id
			return $this->query("INSERT INTO `$table` ($fields) VALUES($values);");
			
		}
		
		/**
		* This updates and array with the associative array data
		* in array and where in either array or string form
		* 
		* @param string
		* @param array
		* @param mixed
		* @return bool
		*/
		public function update($table,$data,$where = false) {
			
			// Check that array is actually an array
			if(!is_array($data)) {
				return false;
			}
			
			// Check the update safety and if this has a where definition
			if(!$where && $this->update_safety == 1) {
				return $this->error(6);
			}
			
			// Put all the updated data into a update string format
			foreach($data as $f => $v) {
				$changes[] = '`' . $this->escape($f) . '`=\'' . $this->escape($v) . '\'';
			}
			$changes = implode(',',$changes);
			
			// Get the wherestring
			$where = $this->getWhereString($where);
			if($where == false && $this->update_safety == 1) {
				return $this->error(6);
			}
			
			// Run the query and return the status
			return $this->query("UPDATE `$table` SET $changes" . $where . ';');
			
		}
		
		/**
		* Deletes from the database.
		*
		* @param string
		* @param mixed
		*/
		function delete($table,$where) {
			
			// Check the delete safety and if this has a where definition
			if(!$where && $this->delete_safety == 1) {
				return $this->error(8);
			}
			
			// Get the wherestring
			$where = $this->getWhereString($where);
			if($where == false && $this->update_safety == 1) {
				return $this->error(8);
			}
			
			// Run the query and return the status
			return $this->query("DELETE FROM `$table`" . $where . ';');
			
		}
		
		/**
		* Returns id(s) dependant on the where and limit
		* Field defaults to id but you can change it per query if you
		* call your id fields model_id and user_id and such
		*
		* @param string
		* @param mixed
		* @param integer
		* @param integer
		* @param string
		* @return bool
		*/
		public function findall($table, $where, $limit = false, $offset = false, $field = 'id') {
			
			// Get the wherestring
			$where = $this->getWhereString($where);
			
			// Sanitize the field
			$field = $this->escape($field);
		
			// Get the limit
			$limit = (is_numeric($limit)) ? " LIMIT $limit" : '';
			$limit .= ($limit && is_numeric($offset)) ? ",$offset" : '';
		
			// Run read but return false if it dies			
			$arrays = $this->read("SELECT `$field` FROM `$table`" . $where . $limit);
			
			// Depending on how many rows come back set ids
			if($this->rows() == 1) {
				$ids = $arrays[0]['id']; // One row
			} else {
				foreach($arrays as $a) { // Lots, run through the ids
					$ids[] = $a['id'];
				}
			}
			
			// Drop the ids out
			$this->lastfound = array($table,$ids);
			return $ids;
		
		}
		
		/**
		* Function to grab a single ID from a table
		*
		* @param string
		* @param mixed
		* @return bool
		*/
		public function find($table,$where,$field = 'id') {
			
			// Bounce off to findall
			return $this->findall($table,$where,1,false,$field);
			
		}
		
		/**
		* Gets the rows from a table depending on the where and limits
		*
		* @param string
		* @param mixed
		* @param integer
		* @param integer
		* @return mixed
		*/
		function getRows($table = false,$where = false,$limit = false,$offset = false) {
			
			// If there is a where
			if($where) {
				$where = $this->getWhereString($where);
			}
			
			// No arguments, no problem!
			if(!$table) {
				// No lastfound... we have a problem
				if(!$this->lastfound) {
					return false;
				}
				
				// Set the table
				$table = $this->lastfound[0];
				
				// Set the wherestring
				if(is_numeric($this->lastfound[1])) { // Single id
					$where = ' WHERE `id` = \'' . $this->lastfound[1] . '\'';
				} elseif (is_array($this->lastfound[1])) { // Array of ids
					
					// Make a where array to give to the wherestring function first
					foreach($this->lastfound[1] as $id) {
						$where[] = array('id' => $id);
					}
					
					// Get the wherestring
					$where = $this->getWhereString($where);
				}
			}
			
			// Get the limit
			$limit = (is_numeric($limit)) ? " LIMIT $limit" : '';
			$limit .= ($limit && is_numeric($offset)) ? ",$offset" : '';
			
			// Set the SQL
			$this->sql = "SELECT * FROM `$table`". $where . $limit;
			
			// Return the resource
			return $this->read();
		}
		
		/**
		* Gets a single row dependant on the where.  No table
		* or where defaults to using $this->lastfound
		*
		* @param string
		* @param string
		* @return array
		*/
		function getRow($table = false,$where = false) {
			
			// Bounce off to getRows
			return $this->getRows($table,$where,1);
		
		}
		
	}

?>
