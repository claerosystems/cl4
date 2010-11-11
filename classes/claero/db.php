<?php
/**
*   This file has the class ClaeroDb which is used for doing db queries
*
*   Example:
*   $claeroDb = new ClaeroDb(array());
*   if ($claeroDb->GetStatus()) {
*       // continue
*
*       $res =& $claeroDb->Query('select...');
*       if ($res === false) {
*       } else {
*           if ($res->NumRows() > 0) {
*               while ($res->FetchInto($var)) {
*                   ...
*               }
*           }
*       }
*   }
*
*   @author     Claero Systems <craig.nakamoto@claero.com> / XM Media Inc <dhein@xmmedia.net>
*   @copyright  Claero Systems / XM Media Inc  2004-2009
*   @version    $Id: class-claero_db.php 769 2010-06-26 20:44:00Z dhein $
*/

//$libLoc = str_replace('/class-claero_db.php', '', __FILE__);
//require_once($libLoc . '/claero_config.php');
//require_once($libLoc . '/common.php');
//require_once($libLoc . '/class-claero_db.php');
//require_once($libLoc . '/class-claero_error.php');

/**
*   Connects to database, runs db queries and returns results
*   Does a large portion of the error processing related to the database event
*
*   @author     Claero Systems <craig.nakamoto@claero.com> / XM Media Inc <dhein@xmmedia.net>
*   @copyright  Claero Systems / XM Media Inc  2004-2009
*
*   @see    class ClaeroError
*
*   // user ClaeroError to get the error messages
*/
class Claero_Db {
    /**
    *   contains the db connection resource
    *   @var    resource
    */
    public $connection;

    /**
    *   host of db (default: localhost)
    *   @var    string
    */
    private $host = 'localhost';

    /**
    *   username to connect to database with (default: root)
    *   @var    string
    */
    private $user = 'root';

    /**
    *   password to connect to dtabase with (default: empty)
    *   @var    string
    */
    private $pass;

    /**
    *   port to connect to database
    *   @var    int or string
    */
    private $port = 3306;

    /**
    *   name of database to use
    *   @var    string
    */
    private $dbName;

    /**
    *   create a new link instead of returning the current one
    *   @var    bool
    */
    private $newLink = false;

    /**
    *   current fetch mode
    *   @var    constant/string
    */
    public $fetchMode = DB_FETCH_MODE_ASSOC;

    /**
    *   holds last error message from MySQL
    *   @var    string
    */
    private $errorMsg;

    /**
    *   user id of current user
    *   @var    int
    */
    private $userId;

    /**
    *   The string from mysql_info for the last query
    *   @var    string
    */
    private $lastQueryInfo = '';

    /**
    *   An array of queries that are were turn
    *   @var    array
    */
    private $queries = array();

    /**
    *   Variable that determines if the query logging will take place
    *   @var    bool
    */
    private $logQueries = true;

    /**
    *   An array of prepared queries
    *   @var    array
    */
    private $preparedTokens = array();

    /**
    *   The number of rows affected in the last query (for INSERT, UPDATE, REPLACE, DELETE, etc)
    *   @var    int
    */
    private $affectedRows = null;

    /**
    *   Set to false if you don't want the object to keep track of all the queries run
    *   @var    bool
    */
    private $storeQueries = true;

    /**
    *   Connects to db and prepares db object, selecting database
    *   To open a new connection with the same username/password, send the 6th value of the dsn array as true
    *
    *   @param  array   $dsn    array of connection values, ie:
    *                               array(host [locahost], username [root], password [], port [3306], database [], new link [false])
    *                               example: array('localhost', 'root', '12345', 3306, 'db_name')
    *                           if any of the variables can be removed and then the default is used (in [])
    *   @param  int     $userId     the id of the user currently running the script
    *   @param  array   $options    array of options for database queries
    *                               db_fetch_mode => default fetch mode (default: DB_FETCH_MODE_ASSOC)
    *                               'log_queries' => sets the value for all queries to be logged or not logged, overridden by the argument on ClaeroDb::Query() (default: true)
    */
    public function __construct($dsn, $userId = 0, $options = array()) {
        // look through DSN array for connection variables
        if (isset($dsn[0]) && $dsn[0]) $this->host = $dsn[0];
        if (isset($dsn[1]) && $dsn[1]) $this->user = $dsn[1];
        if (isset($dsn[2]) && $dsn[2]) $this->pass = $dsn[2];
        if (isset($dsn[3]) && $dsn[3] > 0) $this->port = $dsn[3];
        if (isset($dsn[4]) && $dsn[4]) $this->dbName = $dsn[4];
        if (isset($dsn[5]) && $dsn[5]) $this->newLink = $dsn[5];

        $this->userId = $userId;

        if (isset($options['log_queries'])) $this->logQueries = (bool) $options['log_queries'];

        // now connect to database
        $this->Connect();

        // set the db fetch mode if connected
        if ($this->GetStatus() && isset($options['db_fetch_mode']) && $options['db_fetch_mode']) {
            $this->SetFetchMode($options['db_fetch_mode']);
        } // if
    } // function __construct

    /**
    *   Checks the status of the current db connection
    *
    *   @return     bool        status of db connection
    */
    function GetStatus() {
        $status = $this->connection ? true : false;
        if (!$status) {
            trigger_error('Not connected to database.');
        } // if

        return $status;
    } // function GetStatus

    /**
    *   Connects to database and sets variable
    *   Uses variables within object, set within constructor
    *   If $this->newLink is true, then it will open a new connection no matter what (not just return the last connection if it exists)
    */
    private function Connect() {
        $this->connection = mysql_connect($this->host . ':' . $this->port, $this->user, $this->pass, $this->newLink);
        if ($this->connection === false) {
            trigger_error('MySQL connect failed: ' . $this->GetError());
        } else {
            $this->SelectDb($this->dbName);
        }
    } // function Connect

    /**
    *   Runs mysql_select_db, if it fails, the connection becomes false
    *
    *   @return     bool        false or true if failed or succeeded
    */
    public function SelectDb($dbName) {
        if (!mysql_select_db($dbName, $this->connection)) {
            $this->connection = false;
            trigger_error('MySQL database selection failed: ' . $this->GetError());
            return false;
        } else {
            $this->dbName = $dbName;
        }

        return true;
    } // function SelectDb

    /**
    *   Returns the name of the current db
    *
    *   @return     string      The db name, false otherwise
    */
    public function GetDbName() {
        $query = $this->Query("SELECT DATABASE();");
        if ($query !== false) {
            $data = $query->FetchRow(DB_FETCH_MODE_NUMBERED);
            return $data[0];
        }

        return false;
    } // function GetDbName

    /**
    *   Return error from last db event
    *
    *   @return     string      textual error message for error reporting
    */
    public function GetError() {
        $this->errorMsg = $this->ErrorNo() . ': ' . $this->Error();

        return $this->errorMsg;
    } // function GetError

    /**
    *   Gets the error from the last database event, used for error reporting
    *   Uses mysql_error(), $this->ErrorNo() & ClaeroError class/object
    */
    public function Error() {
        if ($this->connection) {
            return mysql_error($this->connection);
        } else {
            return mysql_error();
        }
    } // function Error

    /**
    *   Gets the mysql error number of last db event
    *   Users mysql_errno() & Claero Error class/object
    */
    public function ErrorNo() {
        if ($this->connection) {
            return mysql_errno($this->connection);
        } else {
            return mysql_errno();
        }
    } // function ErrorNo

    /**
    *   Runs a SQL statement of any type, checks for errors after complete
    *   If there are errors, runs $this->Error and populates $this->errorMsg
    *   Sets $this->lastQueryType
    *
    *   @param      string      $query      sql (query) statement to execute
    *   @param      bool        $logQuery   determines if this query should be logged or not, if not sent, then uses ClaeroDb::logQueries
    *
    *   @return     object      false if failed (use $this->GetError() to get error message)
    *                           if successful INSERT, then the insert id
    *                           if successful DELETE or UPDATE or REPLACE, then the number of affected rows
    *                           if successful SELECT or any other query type, then the ClaeroQuery object
    *
    *   @see        ClaeroQuery
    */
    public function &Query($query, $logQuery = null) {
        $return = false;

        $this->affectedRows = null; // reset the number of rows affected in the last query

        if ($logQuery === null) $logQuery = $this->logQueries;

        // determine the query type
        $queryStart = strtoupper(substr(ltrim($query),0,6));
        switch ($queryStart) {
            case 'SELECT' :
                $queryType = 'SELECT';
                break;
            case 'INSERT' :
                $queryType = 'INSERT';
                break;
            case 'UPDATE' :
                $queryType = 'UPDATE';
                break;
            case 'DELETE' :
                $queryType = 'DELETE';
                break;
            case 'DESCRI' :
                $queryType = 'DESCRIBE';
                break;
            case 'REPLAC' :
                $queryType = 'REPLACE';
                break;
            case 'TRUNCA' :
                $queryType = 'TRUNCATE';
                break;
            case 'COMMIT' :
                $queryType = 'COMMIT';
                break;
            case 'FLUSH ' :
                $queryType = 'FLUSH';
                break;
            case 'OPTIMI' :
                $queryType = 'OPTIMIZE';
                break;
            case 'SHOW T' :
            case 'SHOW D' :
            case 'SHOW S' :
                $queryType = 'SHOW';
                break;
            case 'ALTER ' :
                $queryType = 'ALTER';
                break;
            case 'DROP V' :
            case 'DROP T' :
            case 'DROP I' :
            case 'DROP P' :
            case 'DROP F' :
                $queryType = 'DROP';
                break;
            case 'CREATE' :
                $queryType = 'CREATE';
                break;
            case 'LOAD D' :
                $queryType = 'LOAD';
                break;
            default:
                // set is too short for the first 6 characters
                if (substr(ltrim($query),0,3) == 'SET') $queryType = 'SET';
                else $queryType = null;
                break;
        } // switch

        if ($this->GetStatus() && $query) {
            if (CLAERO_DEBUG && CLAERO_DEBUG_DETAIL) {
                trigger_error('Claero Notice: MySQL Query: ' . $query, E_USER_NOTICE);

                // perform explain when select query
                if ($queryType == 'SELECT') {
                    // it's not any else, so it must be a SELECT
                    $result = $this->RunQuery('EXPLAIN ' . $query);
                    if ($result === false) {
                        trigger_error('Query Error: Explain query failed: ' . $this->GetError());
                    } else {
                        $res = new ClaeroQuery($this, $result);
                        // loop through explain result formatting data
                        $explainText = '';
                        while ($res->FetchInto($debugRow)) {
                            $explainArray = array();
                            foreach ($debugRow as $key => $value) {
                                $explainArray[] = strtoupper($key) . '=>' . $value;
                            }
                            $explainText .= "** " . implode(', ', $explainArray) . "\n";
                        } // while

                        trigger_error('Claero Notice: Query Explained: ' . $explainText, E_USER_NOTICE);
                    } // if
                } // if
            } // if debug

            // remember the time the query started
            $queryStart = microtime(true);

            // run the actual query
            $result = $this->RunQuery($query);
            // record the mysql_info for this query if debug is on or the query failed
            if (CLAERO_DEBUG || $result === false) $this->lastQueryInfo = mysql_info($this->connection);
            if ($result === false) {
                trigger_error('Query Error: ' . $this->GetError() . ' Query: ' . $query, E_USER_ERROR);
            } else {
                // defaults for record id and table name
                $recordId = null;
                $tableName = null;
                $rowCount = null;

                // Determine what should be returned based on the query type
                switch ($queryType) {
                    case 'INSERT' :
                        $return = $this->InsertId();

                        $tableName = claero::GetTableFromQuery($query, 'INSERT INTO');
                        $recordId = $return; // record id is the return (insert id)
                        $rowCount = $this->AffectedRows();
                        $this->affectedRows = $rowCount;
                        break;

                    case 'UPDATE' :
                    case 'DELETE' :
                    case 'REPLACE' :
                    case 'COMMIT' :
                    case 'FLUSH' :
                    case 'OPTIMIZE' :
                    case 'LOAD':
                        $return = $this->AffectedRows();

                        if ($queryType == 'DELETE') {
                            $tableName = claero::GetTableFromQuery($query, 'DELETE FROM');
                        } else {
                            $tableName = claero::GetTableFromQuery($query, $queryType);
                        }
                        if ($queryType != 'REPLACE') {
                            $recordId = claero::GetIdFromQuery($query, stripos($query, 'WHERE') + 6);
                        }
                        $rowCount = $return; // row count is the return (affected rows)
                        $this->affectedRows = $rowCount;
                        break;
                    case 'SET' :
                    case 'DROP' :
                    case 'CREATE' :
                        $return = true;
                        $tableName = null;
                        $recordId = null;
                        $rowCount = null;
                        break;
                    // this is for all the queries that return a ClaeroQuery object so the data can be retrieved
                    default :
                        $return = new ClaeroQuery($this, $result);

                        if ($queryType == 'DESCRIBE') {
                            $tableName = claero::GetTableFromQuery($query, 'DESCRIBE');
                            $recordId = null;
                            $rowCount = $return->NumRows();
                        } else if ($queryType == 'ALTER') {
                            $tableName = claero::GetTableFromQuery($query, 'ALTER TABLE');
                            $recordId = null;
                            $rowCount = null;
                        } else if ($queryType == 'TRUNCATE') {
                            $tableName = claero::GetTableFromQuery($query, 'TRUNCATE');
                            $recordId = null;
                            $rowCount = $this->AffectedRows();
                            $this->affectedRows = $rowCount;
                        } else if ($queryType == 'SHOW') {
                            $tableName = null;
                            $recordId = null;
                            $rowCount = $return->NumRows();
                        } else if (CLAERO_DEBUG) {
                            $tableName = claero::GetTableFromQuery($query, 'FROM');
                            $recordId = claero::GetIdFromQuery($query, stripos($query, 'WHERE') + 6);
                            $rowCount = $return->NumRows();
                        }
                        break;
                }

                if ($logQuery) {
                    if ($queryType == 'SELECT') {
                        // NEVER log SELECT's
                    } else {
                        // get the actual query time in milliseconds
                        $queryTime = (microtime(true) - $queryStart) * 1000;
                        $logSql = "INSERT INTO `" . CLAERO_CHANGE_LOG_TABLE . "` VALUES (NULL, '" . $this->EscapeString($this->userId) . "', NOW(), '" . $this->EscapeString(trim($query)) . "', '{$tableName}', '{$recordId}', '{$queryType}', '{$rowCount}', '{$queryTime}')";
                        //echo $logSql;
                        $result = $this->RunQuery($logSql);
                        if ($result === false) {
                            trigger_error('Query Error: Failed to add change log entry: ' . $this->GetError(), E_USER_NOTICE);
                        } else {
                            if (CLAERO_DEBUG && CLAERO_DEBUG_DETAIL) trigger_error('Claero Notice: Change log entry added id: ' . $this->InsertId(), E_USER_NOTICE);
                        }
                    }
                }
            }
        } else {
            if (!$query) trigger_error('Input Error: No SQL statement received.');
        }

        return $return;
    } // function Query

    /**
    *   Prepares a query to be run, storing the data in $this->preparedTokens
    *   Use the following characters to indicate how the data is to be put into SQL statement
    *   ? -> escaped and quoted (with single quotes) before inserting
    *   ^ -> inserted as is
    *   & -> implodes the array escpaping each value
    *   @ -> implodes the array (no escaping)
    *
    *   @param      string      $sql        The SQL statement to prepare
    *
    *   @return     int         The key of prepare sql query to be passed to $this->Execute()
    */
    public function Prepare($sql) {
        $tokens = preg_split('/((?<!\\\)[@&?^])/', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);

        // loop through removing any escaped values
        foreach ($tokens as $key => $val) {
            switch ($val) {
                case '?' :
                case '&' :
                case '@' :
                    break;
                default :
                    $tokens[$key] = preg_replace('/\\\([@&?^])/', "\\1", $val);
                    break;
            } // switch
        } // foreach

        $this->preparedTokens[] = $tokens;
        end($this->preparedTokens);
        return key($this->preparedTokens);
    } // function Prepare

    /**
    *   Creates the SQL placing the data in the appropriate places and then runs the sql
    *
    *   @param      int         $preparedKey        The key of the prepared sql
    *   @param      array       $data               The array of data to put into the query (the count of this array must match that of the prepared query)
    *   @param      bool        $logQuery           determines if this query should be logged or not, if not sent, then uses ClaeroDb::logQueries
    *
    *   @return     object      false if the $preparedKey does not exist in $this->preparedTokens
    *                           false if count of needed values in sql statement does not equal the number of keys in the data array
    *                           otherwise, the result of $this->Query()
    */
    public function Execute($preparedKey, $data, $logQuery = true) {
        if (isset($this->preparedTokens[$preparedKey])) {
            $tokens = $this->preparedTokens[$preparedKey];
            $query = '';
            $dataKey = 0;
            $count = 0;

            // count the number of tokens we have
            $validTokens = array('?', '^', '&', '@');
            foreach ($tokens as $val) {
                if (in_array($val, $validTokens)) {
                    ++$count;
                } // if
            } // foreach

            // check to ensure we have the same number of tokens as data keys
            if ($count != count($data)) {
                trigger_error('Input Error: The number of values received (' . count($data) . ') in execute does not equal the number of values needed for the query (' . $count . ')', E_USER_ERROR);
                return false;
            } // if

            // loop through the tokens creating the sql statement
            foreach ($tokens as $val) {
                switch ($val) {
                    case '?' :
                        $query .= "'" . $this->EscapeString($data[$dataKey++]) . "'";
                        break;
                    case '^' :
                        $query .= $data[$dataKey++];
                        break;
                    case '&' :
                        $query .= $this->ImplodeEscape($data[$dataKey++]);
                        break;
                    case '@' :
                        $query .= implode(',', $data[$dataKey++]);
                        break;
                    default :
                        $query .= $val;
                        break;
                } // switch
            } // foreach

            return $this->Query($query, $logQuery);

        } else {
            trigger_error('Input Error: The prepared key doesn\'t match any currently prepared queries', E_USER_ERROR);
            return false;
        } // if
    } // function Execute

    /**
    *   Runs $this->Prepare() then $this->Execute() for the sql and the data
    *   Use the following characters to indicate how the data is to be put into SQL statement
    *   ? -> escaped and quoted (with single quotes) before inserting
    *   ^ -> inserted as is
    *   & -> implodes the array escpaping each value
    *   @ -> implodes the array (no escaping)
    *
    *   @param      string      $sql        The SQL statement to prepare
    *   @param      array       $data       The array of data to put into the query (the count of this array must match that of the prepared query)
    *
    *   @return     object      returns value from $this->Query() if Execute was successful
    *                           otherwise it'll be false
    */
    public function PrepareExecute($sql, $data) {
        return $this->Execute($this->Prepare($sql), $data);
    } // function PrepareExecute

    /**
    *   Deternines the type of the string and then returns the escape version
    *   Arrays will be imploded and escaped with ' around each value
    *
    *   @param      mixed       $var        The variable to escape
    *
    *   @return     string      the escaped value
    */
    public function Escape($var) {
        if (is_string($var)) {
            return $this->EscapeString($var);
        } else if (is_array($var)) {
            return $this->ImplodeEscape($var);
        } else if (is_int($var)) {
            return $var;
        } else {
            trigger_error('Input Error: Unable to escape passed value as it\'s an ' . gettype($var), E_USER_ERROR);
            return $var;
        }
    } // function Escape

    /**
    *   Escapes the received string using mysql_real_escape_string
    *
    *   @param      var     $str        The string or other type to be escaped
    *
    *   @return     string      The escaped string
    */
    public function EscapeString($str) {
        if (is_array($str) || is_object($str) || is_resource($str)) trigger_error('Input Error: A ' . gettype($str) . ' was passed to ClaeroDb::EscapeString() instead of a string ' . print_r($str, true), E_USER_ERROR);
        return mysql_real_escape_string($str, $this->connection);
    } // function EscapeString

    /**
    *   Escapes and puts single quotes every value of an array and then implodes the array with commas
    *
    *   @param      array       $array      Array of values to escape and implode
    *   @param      string      $quoteChar  The character to put around each value in the array; default is a single quote
    *
    *   @return     string      String of values to be used within MySQL
    */
    public function ImplodeEscape($array, $quoteChar = "'") {
        $resArray = array();
        foreach ($array as $value) {
            $resArray[] = $quoteChar . $this->EscapeString($value) . $quoteChar;
        }

        return implode(',', $resArray);
    } // function ImplodeEscape

    /**
    *   Runs ClaeroDb::ImplodeEscape() on $array
    *
    *   @see ClaeroDb::ImplodeEscape()
    */
    public function EscapeImplode($array) {
        return $this->ImplodeEscape($array);
    } // function EscapeImplode

    /**
    *   Runs mysql_query on the received query and returns the result
    *
    *   @param      string      $query      The SQL statement (query) to execute
    *
    *   @return     resource    The query recourse
    */
    private function RunQuery($query) {
        if ($this->storeQueries) $this->queries[] = $query;
        return mysql_query($query, $this->connection);
    } // function RunQuery

    /**
    *   Runs mysql_info()
    *
    *   @return     string      The string returned from mysql_info
    */
    public function Info() {
        return $this->lastQueryInfo;
    } // function Info

    /**
    *   Runs mysql_client_encoding() if available
    *
    *   @return     string      The string returned from mysql_client_encoding()
    */
    public function GetEncoding() {
        return (function_exists('mysql_client_encoding')) ? mysql_client_encoding($this->connection) : false;
    } // function GetEncoding

    /**
    *   Sets the connection charset (encoding)
    *   Listing charset MySQL accepts: http://dev.mysql.com/doc/refman/5.1/en/charset-charsets.html
    *
    *   @param  string  $charset    The charset to set it to; defaults to utf8 if no value is passed
    *
    *   @return bool    If the charset was set successfully
    */
    public function SetCharSet($charset = 'utf8') {
        return mysql_set_charset($charset, $this->connection);
    } // function SetCharSet

    /**
    *   Returns an HTML formated string of the queries that have been executed through the object up to this point
    *
    *   @return     string      HTML formatted string
    */
    public function GetQueryList() {
        $str = '';

        if (CLAERO_DEBUG) {
            foreach ($this->queries as $query) {
                $str .= $query . HEOL;
            }
        } else {
            $str = 'No queries stored as debug is off.';
        }

        return $str;
    } // function GetQueryList

    /**
    *   Returns the SQL for the last query
    *
    *   @return     string      The SQL for the last query
    */
    public function GetLastQuery() {
        end($this->queries);
        $lastQuery = $this->queries[key($this->queries)];
        if (strpos($lastQuery, "INSERT INTO `" . CLAERO_CHANGE_LOG_TABLE . "`") !== false) {
            $lastQuery = $this->queries[key($this->queries) - 1];
        }
        return $lastQuery;
    } // function GetLastQuery

    /**
    *   Sets the fetch mody within the object
    *
    *   @param      string      $mode       a fetch mode (must be valid constant)
    *
    *   @return     bool/string     false if failed (invalid fetch mode) or the fetch mode choosen if successful
    */
    public function SetFetchMode($mode) {
        if (in_array($mode, array(DB_FETCH_MODE_ASSOC, DB_FETCH_MODE_OBJECT, DB_FETCH_MODE_ORDERED, true))) {
            return $this->fetchMode = $mode;
        }

        return false;
    } // function SetFetchMode

    /**
    *   Gets the current fetch mode, therefore the default for subsequent queries
    */
    public function GetFetchMode() {
        return $this->fetchMode;
    } // function GetFetchMode

    /**
    *   Disconnects from database
    *
    *   @return     bool    true if successful, false if error (use $this->GetError() to get error message)
    */
    public function Disconnect() {
        if (!mysql_close($this->connection)) {
            trigger_error('Failed while disconnecting from database: ' . $this->GetError());
            return false;
        }

        return true;
    } // function Disconnect

    /**
    *   Retrieves the last insert id from the last db event
    *
    *   @return     int/bool    the id of last inserted row or false if failed (last query probably wasn't an insert)
    */
    private function InsertId() {
        return mysql_insert_id($this->connection);
    } // function InsertId

    /**
    *   Retrives the number of affected rows
    *
    *   @return     int/bool    the number of affected rows or false if failed
    */
    private function AffectedRows() {
        return mysql_affected_rows($this->connection);
    } // function AffectedRows

    /**
    *   Retrieves the number of affected rows in the last query
    *   This must be used instead of mysql_affected_rows as a change log record was inserted afterward and therefore changed the affected row count
    *
    *   @return     int/null        the number of affected rows in the last query or null if it was not set
    */
    public function GetAffectedRows() {
        return $this->affectedRows;
    } // function GetAffectedRows

    /**
    *   Sets the user id in the object
    *
    *   @param      int     the user id to set the variable in the object to
    */
    public function SetUserId($userId) {
        $this->userId = $userId;
    } // function SetUserId

    /**
    *   Creates the following id != 43, id != 54, id != 4212 ... for use in an order by clause when you want to order by your ids within an IN() statement
    *
    *   @param      array       $ids        the array of ids (array(key => id))
    *   @param      string      $pre        the prefix to be added before the id
    *
    *   @return     string      the string to be put in the order by clause
    */
    public function CreateOrderById($ids, $pre = '') {
        $sql = '';
        foreach ($ids as $key => $thisId) {
            $sql .= ($key > 0 ? ', ' : '') . $pre . "id != '" . $this->EscapeString($thisId) . "' ";
        } // foreach
        return $sql;
    } // function CreateOrderById

    /**
    *   Sets the $this->storeQueries property which determines if the queries are stored within the object (in an array)
    *
    *   @param      bool    $value      The value to set it to, if nothing passed, then it will set it to the opposite of it's current value
    *
    *   @return     bool    The value after the change
    */
    public function SetStoreQueries($value = null) {
        if ($value === null) $this->storeQueries = !$this->storeQueries;
        else $this->storeQueries = (bool) $value;

        return $this->storeQueries;
    } // function SetStoreQueries

    /**
    *   Saves multiple records into an association table from checkbox, mutliple selects, etc
    *   Example table setup: user --> user_group <-- group, saving the groups the user is in (user_group)
    *
    *   @param      string      $tableName          the name of the association table (in example user_group)
    *   @param      string      $constantField      the name of the constant field (in example user_id)
    *   @param      string      $constantFieldValue the value of the constant field
    *   @param      string      $changingField      the name of the changing field (in example group_id)
    *   @param      string/array $requestLoc        the location of the values within the post, if string, then is will use ProcessRequest() if array, then it will use ProcessRequestArray()
    *   @param      array       $options            optional options
    *       'expire_flag' => if set to true, then expiry functionality will be used
    *
    *   @return     array       array with 4 keys: removed_count, added_count, current_ids, post_value or false (bool) on failure
    */
    function SaveMultiple($tableName, $constantField, $constantFieldValue, $changingField, $requestLoc, $options = array()) {
        global $claeroMetaDataStore;

        // store all the the meta data for the table we are updating to save time
        if ($claeroMetaDataStore !== true) $claeroMetaDataStore[] = $tableName;

        $possibleOptions = array(
            'expire_flag' => false,
        );
        $options = SetFunctionOptions($options, $possibleOptions);

        $return = array(
            'removed_count' => 0,
            'added_count' => 0,
            'current_ids' => array(),
            'post_value' => null,
            'status' => true,
        );

        // get existing ones first
        $getSql = "SELECT id, `" . $this->EscapeString($changingField) . "` FROM `" . $this->EscapeString($tableName) . "` WHERE `" . $this->EscapeString($constantField) . "` = '" . $this->EscapeString($constantFieldValue) . "'";
        if ($options['expire_flag']) $getSql .= " AND (date_expired = 0 OR date_expired > NOW()) ";

        $getQuery = $this->Query($getSql);
        if ($getQuery === false) {
            trigger_error('Query Error: Failed to retrieve existing items ' . $this->GetLastQuery(), E_USER_ERROR);
            return false;
        } else {
            $existingArray = $getQuery->GetAllKeyed();
            $count = 0;

            if (is_array($requestLoc)) {
                $return['post_value'] = ProcessRequestArray($requestLoc, false);
            } else {
                $return['post_value'] = ProcessRequest($requestLoc, false);
            }

            if ($return['post_value'] !== false && is_array($return['post_value'])) {
                foreach($return['post_value'] as $saveId) {
                    if ($saveId > 0) {
                        $existingId = array_search($saveId, $existingArray);
                        if ($existingId > 0) { // already an existing, so remove it from the list of existing
                            $return['current_ids'][] = $existingId;
                            unset($existingArray[$existingId]);
                        } else { // new, so add it
                            ++$count;

                            $insertOptions = array(
                                'data' => array(
                                    $tableName => array(
                                        $constantField => $constantFieldValue,
                                        $changingField => $saveId,
                                    ),
                                ),
                            );
                            $insert = new ClaeroEdit($tableName, $insertOptions);
                            if (!$insert->GetStatus()) {
                                trigger_error('Query Error: Failed to save a multiple field', E_USER_ERROR);
                                return false;
                            } else {
                                $return['current_ids'][] = $insert->GetInsertId($tableName);
                            } // if
                        } // if
                    } // if
                } // foreach

                $return['added_count'] = $count;

                if (count($existingArray) > 0) {
                    // we still have existing disabilities, so we need to expired all remaining ones
                    if ($options['expire_flag']) {
                        $removeSql = "UPDATE `" . $this->EscapeString($tableName) . "` SET date_expired = NOW() ";
                    } else {
                        $removeSql = "DELETE FROM `" . $this->EscapeString($tableName) . "` ";
                    }
                    $removeSql .= " WHERE `" . $this->EscapeString($constantField) . "` = '" . $this->EscapeString($constantFieldValue) . "' AND id IN (" . $this->ImplodeEscape(array_flip($existingArray)) . ") ";

                    $removeQuery = $this->Query($removeSql);
                    if ($removeQuery === false || $removeQuery === 0) {
                        if ($removeQuery === false) trigger_error('Query Error: Query failed to remove or expire removed items ' . $this->GetLastQuery(), E_USER_ERROR);
                        else trigger_error('Input Error: Removal or expiration of existing multiple items did not remove any items ' . $this->GetLastQuery(), E_USER_ERROR);
                        return false;
                    } else {
                        $return['removed_count'] = $removeQuery;
                    } // if
                } // if

            } else if ($getQuery->NumRows() > 0) {
                // remove all existing records as none were received in post
                if ($options['expire_flag']) {
                    $removeSql = "UPDATE `" . $this->EscapeString($tableName) . "` SET date_expired = NOW() ";
                } else {
                    $removeSql = "DELETE FROM `" . $this->EscapeString($tableName) . "` ";
                }
                $removeSql .= " WHERE `" . $this->EscapeString($constantField) . "` = '" . $this->EscapeString($constantFieldValue) . "' AND id IN (" . $this->ImplodeEscape(array_flip($existingArray)) . ") ";

                $removeQuery = $this->Query($removeSql);
                if ($removeQuery === false || $removeQuery === 0) {
                    if ($removeQuery === false) trigger_error('Query Error: Query failed to remove or expire all items ' . $this->GetLastQuery(), E_USER_ERROR);
                    else trigger_error('Input Error: Removal or expiration of all existing multiple items did not remove any items ' . $this->GetLastQuery(), E_USER_ERROR);
                    return false;
                } else {
                    $return['removed_count'] = $removeQuery;
                } // if expire query
            } // if num checked
        } // if get query

        return $return;
    } // function SaveMultiple

    /**
    *   Generates a temp table with only valid characters for MySQL
    *   No checking is done to ensure the table does not exist
    *
    *   @param  string  $prefix     The string to put infront of the table name (empty by default)
    *
    *   @return string  The temp table name
    */
    public function GetTempTableName($prefix = '') {
        return $prefix . preg_replace(array("/[^a-zA-Z0-9]/", "/_+/", "/_$/"), '', crypt(time()));
    } // function GetTempTableName
} // class ClaeroDb

/**
*   Contains the query and functionality to pull results from the query
*   Will be instantiated by ClaeroDb
*
*   @author     Claero Systems <craig.nakamoto@claero.com> / XM Media Inc <dhein@xmmedia.net>
*   @copyright  Claero Systems / XM Media Inc  2004-2009
*
*   @see    class ClaeroDb
*/
class ClaeroQuery {
    /**
    *   The ClaerDb object (with db connection)
    *   @var    object
    */
    protected $claeroDb = false;

    /**
    *   current fetch mode
    *   @var    constant/string
    */
    private $fetchMode = DB_FETCH_MODE_ASSOC;

    /**
    *   Query resource
    *   @var    resource
    */
    private $query;

    /**
    *   Number of rows, populated after NumRows() is run
    *   @var    int
    */
    private $numRows;

    /**
    *   Number of fields in the result, populated after NumFields() is run
    *   @var    int
    */
    private $numFields;

    /**
    *   Received the ClaeroDb object and a query result and prepares the object
    *
    *   @param      object      $claeroDb       The ClaeroDb object which ran the query
    *   @param      resouce     $queryResult    The result of the mysql_query function
    */
    public function __construct(&$claeroDb, $queryResult) {
        // get the fetch mode out of the ClaeroDb object
        $this->fetchMode = $claeroDb->fetchMode;
        $this->query = $queryResult;
    } // function __construct

    /**
    *   Fetches the next row into the first parameter
    *
    *   @param      array/object    &$arr   array or object of data retrieved in last db event
    *   @param      constant        $fetechMode     fetch mode to use, if not set, uses $this->fetchMode
    *
    *   @return     int/null        returns 1 if successful, null if end of result set
    */
    public function FetchInto(&$row, $fetchMode = null) {
        $row = $this->FetchRow($fetchMode);

        if ($row === false) {
            return null;
        } else {
            return 1; // successful
        } // if
    } // function FetchInto

    /**
    *   Runs mysql_data_seek on the query
    *
    *   @return     bool        return of mysql_data_seek (true if successful, false otherwise)
    */
    public function DataSeek($row = 0) {
        return mysql_data_seek($this->query, $row);
    } // function DataSeek

    /**
    *   Returns the next rows within a result set, formatted as fetchMode
    *
    *   @param      constant        $fetechMode     fetch mode to use, if not set, uses $this->fetchMode
    *
    *   @return     array/object    whatever mysql returned
    */
    public function FetchRow($fetchMode = null) {
        if ($fetchMode != null) {
            $this->SetFetchMode($fetchMode);
        }

        if ($this->fetchMode == DB_FETCH_MODE_ASSOC) {
            $row = mysql_fetch_assoc($this->query);
        } else if ($this->fetchMode == DB_FETCH_MODE_OBJECT) {
            $row = mysql_fetch_object($this->query);
        } else if ($this->fetchMode == DB_FETCH_MODE_ARRAY) {
            $row = mysql_fetch_array($this->query);
        } else if ($this->fetchMode == DB_FETCH_MODE_NUMBERED) {
            $row = mysql_fetch_row($this->query);
        }

        return $row;
    } // function FetchRow

    /**
    *   Retrieves the number of rows return or affect in last query
    *   Will only run mysql_num_rows() once; populates $this->numRows
    *
    *   @return     int/bool    the number of rows or false if failed
    */
    public function NumRows() {
        if ($this->numRows === null) $this->numRows = mysql_num_rows($this->query);
        return $this->numRows;
    } // function NumRows

    /**
    *   Retrives the number of fields returned in last query
    *   Will only run mysql_num_fields() once; populates $this->numFields
    *
    *   @return     int/bool    the number of rows or false if failed
    */
    public function NumFields() {
        if ($this->numFields === null) $this->numFields = mysql_num_fields($this->query);
        return $this->numFields;
    } // function NumFields


    /**
    *   Runs function to empty out memory of last query
    *
    *   @return     bool    true if successful, false if failed
    */
    public function FreeResult() {
        if (!mysql_free_result($this->query)) {
            trigger_error('Failed to free query result: ' . $this->GetError());
            return false;
        }

        return false;
    } // function FreeResult

    /**
    *   Sets the fetch mody within the object
    *
    *   @param      string      $mode       a fetch mode (must be valid constant)
    *
    *   @return     bool/string     false if failed (invalid fetch mode) or the fetch mode choosen if successful
    */
    public function SetFetchMode($mode) {
        if (in_array($mode, array(DB_FETCH_MODE_ASSOC, DB_FETCH_MODE_OBJECT, DB_FETCH_MODE_NUMBERED, DB_FETCH_MODE_ARRAY, true))) {
            return $this->fetchMode = $mode;
        }

        return false;
    } // function SetFetchMode

    /**
    *   Gets all the rows within the query and returns as an array with sub arrays
    *
    *   @return     array       Array of rows, which the key of the top array being the row number minus 1
    */
    public function GetAll() {
        $return = array();
        while ($this->FetchInto($row)) {
            $return[] = $row;
        }
        return $return;
    } // function GetAll

    /**
    *   Returns an array of the row number => $col column value
    *
    *   @param      int         $col        The column to return
    *
    *   @return     array       Array of rows and the $col column value
    */
    public function GetAllRows($col = 0) {
        $return = array();
        $col = (int) $col;

        // set fetch mode so we have numberical keys
        $this->SetFetchMode(DB_FETCH_MODE_NUMBERED);

        while ($this->FetchInto($row)) {
            $return[] = $row[$col];
        }

        return $return;
    } // function GetAllRows

    /**
    *   Gets all the rows within the query and returns as an array with sub arrays, but the array uses the optional column value for the index
    *
    *   @param      string      $columnName        The column to use as the keys for the return array
    *
    *   @return     array       Array of rows, which the key of the top array being the row number minus 1
    */
    public function GetAssoc($columnName = 'id') {
        $return = array();
        while ($this->FetchInto($row)) {
            if (isset($row[$columnName])) {
                $return[$row[$columnName]] = $row;
            } else {
                $return[] = $row;
            } // if
        }
        return $return;
    } // function GetAssoc

    /**
    *   Returns and array of $col1 => $col2 (ie id => name)
    *
    *   @param      int     $col1       The key column (default: 0)
    *   @param      int     $col2       The value column (default: 1)
    *   @param      array   $return     Send this if you want to add values before the results
    *
    *   @return     array       Array of rows as $col1 => $col2
    */
    public function GetAllKeyed($col1 = 0, $col2 = 1, $return = array()) {
        $return = (array) $return;
        $col1 = (int) $col1;
        $col2 = (int) $col2;

        // set fetch mode so we have numberical keys
        $this->SetFetchMode(DB_FETCH_MODE_NUMBERED);

        while ($this->FetchInto($row)) {
            $return[$row[$col1]] = $row[$col2];
        }

        return $return;
    } // function GetAllKeyed

    /**
    *   Gets the first key of the first row in the result
    *
    *   @return     string      The value of the first key in the first row
    */
    public function GetOne() {
        $this->FetchInto($data, DB_FETCH_MODE_NUMBERED);

        return $data[0];
    } // function GetOne
} // class ClaeroQuery