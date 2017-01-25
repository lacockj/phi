<?php namespace Phi; class Database extends \mysqli {

# Properties #

public $errors = array();

protected $safeFunctions = array("CURRENT_TIMESTAMP()", "NOW()");
protected $storeResult = false;

public $dbFieldJsTypes = array(
  'INTEGER'   => 'number',
  'INT'       => 'number',
  'SMALLINT'  => 'number',
  'TINYINT'   => 'number',
  'MEDIUMINT' => 'number',
  'BIGINT'    => 'number',
  'DECIMAL'   => 'number',
  'NUMERIC'   => 'number',
  'FLOAT'     => 'number',
  'DOUBLE'    => 'number',
  'BIT'       => 'number',
  'DATE'      => 'date',
  'DATETIME'  => 'date',
  'TIMESTAMP' => 'date',
  'TIME'      => 'date',
  'CHAR'      => 'string',
  'VARCHAR'   => 'string',
  'TEXT'      => 'string',
  'ENUM'      => 'string'
);

/**
 * Connect to a database.
 * @param {string} $HOST - Database host address.
 * @param {string} $USER - Database username.
 * @param {string} $PASS - Database password.
 * @param {string} $NAME - Database name.
 * @return {object} - Your connection to the database and it's methods.
 */
function __construct () {

  # Accept either one config array argument, or four separate arguments. #
  switch ( func_num_args() ) {

    case 1:
      $config = func_get_arg(0);
      if ( \Phi::all_set( $config, 'HOST', 'USER', 'PASS', 'NAME' ) ) {
        $HOST = $config['HOST'];
        $USER = $config['USER'];
        $PASS = $config['PASS'];
        $NAME = $config['NAME'];
      } else {
        throw new \Exception("Configuration Array must have HOST, USER, PASS, and NAME", 1);
      }
      break;

    case 4:
      $config = func_get_args();
      $HOST = $config[0];
      $USER = $config[1];
      $PASS = $config[2];
      $NAME = $config[3];
      break;

  }

  # Connect to the MySQL server. #
  parent::__construct($HOST, $USER, $PASS, $NAME);
  if ($this->connect_error) {
    $this->errors[] = $this->connect_errno.": ".$this->connect_error;
    return false;
  }

  # Change character set to utf8 #
  if (!parent::set_charset("utf8")) {
    $this->errors[] = "Error loading character set utf8: " . $this->error;
  }

}

public function storeResult ( $storeResult=true ) {
  $this->storeResult = (bool)$storeResult;
}

public function lastError () {
  return ( count($this->errors) ) ? $this->errors[count($this->errors)-1] : null;
}

/**
 * Prepare and execute a query on the database.
 * @param {string} $sql      - The SQL query to execute, with '?' placeholders for parameters.
 * @param {array}  [$params] - The parameters to safely fill into the query.
 * @param {string} [$types]  - Data types of the parameters, one character per parameter.
 *                             ('s':string, 'i':integer, 'd':double, 'b':blob)
 * @return {Datastream|number|FALSE} - A Datastream object for retrieving results a row at a time,
 *                                     or the insert_id of a single INSERT query,
 *                                     or the number of affected_rows for multiple INSERT and UPDATE queries,
 *                                     or FALSE if there was an error.
 */
public function pq ( $sql, $params=null, $types=null ) {

  # Prepare the query. #
  if (! is_string($sql) ) {
    $this->errors[] = array(
      'operation' => 'mysqlez sql is_string',
      'errno' => null,
      'error' => "Expecting first parameter to be an SQL string."
    );
    return false;
  }
  $sql = trim( $sql );
  if (! $stmt = parent::prepare($sql) ) {
    $this->errors[] = array(
      'operation' => 'mysqli prepare',
      'errno' => $this->errno,
      'error' => $this->error
    );
    return false;
  }

  # Bind the parameters. #
  if ( $params ) {
    if ( is_scalar( $params ) ) $params = array( $params ); # Recast single, scalar param as single-element array. #
    if (! $types) $types = str_repeat('s', count($params)); # Default to string parameters. #
    $bind_params = array();
    foreach ( $params as &$p ) $bind_params[] = &$p; # Bound parameters must be passed by reference. #
    array_unshift( $bind_params, $types );
    if (! call_user_func_array( array($stmt, "bind_param"), $bind_params )) {
      $this->errors[] = array(
        'operation' => 'mysqli bind_params',
        'errno' => $this->errno,
        'error' => $this->error
      );
      return false;
    }
  }

  # Execute the query. #
  if (! $stmt->execute()) {
    $this->errors[] = array(
      'operation' => 'mysqli execute',
      'errno' => $this->errno,
      'error' => $this->error
    );
    return false;
  }
  if ( $this->storeResult ) {
    $stmt->store_result();
  }

  # Return results based on query type. #
  $verb = strtoupper( preg_replace( '/^(\w+).*$/s', '$1', $sql ) );
  switch ( $verb ) {

    # Return Datastream of SELECTed data. #
    case "SELECT":
    case "SHOW":
    case "DESCRIBE":
      return new \Phi\Datastream( $stmt );
      break;

    # Return the ID of the inserted row. #
    case "INSERT":
      if ( $stmt->insert_id ) {
        return $stmt->insert_id;
      } elseif ( $stmt->affected_rows ) {
        return true;
      }
      break;

    # Return the number of rows affected by UPDATE, or DELETE. #
    case "UPDATE":
    case "DELETE":
      return $stmt->affected_rows;
      break;

    # Return true for CREATE and DROP because if it didn't work it would have errored at execute step. #
    case "CREATE":
    case "DROP":
      return true;
      break;

    # Default to returning the mysqli_stmt object. #
    default:
      return $stmt;
  }
}

# Full-name alias of 'pq' method. #
function parameterized_query(){ 
  return call_user_func_array('self::pq', func_get_args());
}

/**
 * Create an SQL string from an array of fields.
 * @param  {array}  $params            - The SQL query parameters.
 * @param  {string} $params['op']      - The operation to perform, i.e. "SELECT" or "INSERT".
 * @param  {string} $params['table']   - The table in which to perform the operation.
 * @param  {array}  $params['columns'] - The table columns (fields) to include in the SQL.
 * @param  {bool}   $params['update']  - SQL includes "ON DUPLICATE KEY UPDATE..." commands when set to true.
 * @param  {bool}   $params['ignore']  - SQL includes "IGNORE" duplicate keys command in INSERT operations when set to true.
 * @return {string} - The resulting SQL.
 */
public function compile_sql ($params) {
  $op      = isset($params['op'])      ? $params['op']      : 'SELECT';
  $table   = isset($params['table'])   ? $params['table']   : null;
  $columns = isset($params['columns']) ? $params['columns'] : null;
  $update  = isset($params['update'])  ? $params['update']  : false;
  $ignore  = isset($params['ignore'])  ? $params['ignore']  : false;

  if (! is_string($op)) throw new Exception("'op' must be a string.");
  $op = strtoupper($op);

  if (! $table) throw new Exception("You must provide a 'table' name.");

  # Column List #
  if (is_string($columns)) {
    if (strpos($columns, ',')) {
      $columns = explode(',', $columns);
    } else {
      $columns = array($columns);
    }
  }

  $sql = array();
  switch ($op) {
    case 'INSERT':
      if ($ignore) {
        $sql[] = "INSERT IGNORE INTO `$table`";
      } else {
        $sql[] = "INSERT INTO `$table`";
      }
      if (! is_array($columns)) throw new Exception("Expecting array of 'columns' for INSERT operation.");
      $sql[] = "(" . $this->compile_columns($columns) . ")";
      $sql[] = "VALUES (" . implode(',', array_fill(0, count($columns), "?")) . ")";
      if ($update) {
        $sql[] = "ON DUPLICATE KEY UPDATE " . $this->compile_columns($columns, true);
      }
      break;
  }

  return implode(' ', $sql);
}

/**
 * Create an SQL-compatible list of columns, optionally as a list of value updates for duplicate keys.
 * @param  {array} $columns  - The columns.
 * @param  {bool}  $asUpdate - When true, return in format of "`column_name`=VALUES(`column_name`)".
 * @return {string} - The resulting SQL-compatible string.
 */
public function compile_columns ($columns, $asUpdate=false) {
  if (count($columns) == 1 && $columns[0] === '*') throw new Exception("Cannot compile '*' as column list.");
  foreach ($columns as &$column) {
    $column = ($asUpdate) ? "`$column`=VALUES(`$column`)" : "`$column`";
  }
  return implode(',', $columns);
}


# Convenience Methods (not to include user input) #

public function table_fields ( $table ) {
  $rows = $this->pq("DESCRIBE $table");
  $fields = array();
  if ( is_array($rows) ) {
    foreach ( $rows as $row ) {
      $fields[] = $row['Field'];
    }
  }
  return $fields;
}

} ?>