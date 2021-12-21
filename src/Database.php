<?php namespace Phi; class Database extends \mysqli {

# Properties #

public $errors = array();

protected $safeFunctions = array("CURRENT_TIMESTAMP()", "NOW()");
protected $storeResult = false;
protected $returnDataStream = true;

/**
 * Connect to a database.
 * @param string $HOST - Database host address.
 * @param string $USER - Database username.
 * @param string $PASS - Database password.
 * @param string $NAME - Database name.
 * @param string $PORT - Database port. Optional; default ini_get("mysqli.default_port").
 * @return self - Your connection to the database and it's methods.
 */
function __construct () {

  # Accept either one config array argument, or four separate arguments. #
  switch ( func_num_args() ) {

    case 1:
      $config = func_get_arg(0);
      if ( \Phi\Tools::all_set( $config, 'HOST', 'USER', 'PASS', 'NAME' ) ) {
        $HOST = $config['HOST'];
        $USER = $config['USER'];
        $PASS = $config['PASS'];
        $NAME = $config['NAME'];
        $PORT = array_key_exists('POST', $config) ? $config['PORT'] : ini_get("mysqli.default_port");
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
      $PORT = (count($config)>=5) ? $config[4] : ini_get("mysqli.default_port");
      break;

  }

  # Connect to the MySQL server. #
  parent::__construct($HOST, $USER, $PASS, $NAME, $PORT);
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

public function returnDataStream ( $returnDataStream=true ) {
  $this->returnDataStream = (bool)$returnDataStream;
}

public function lastError () {
  return ( count($this->errors) ) ? $this->errors[count($this->errors)-1] : null;
}

/**
 * Prepare and execute a query on the database.
 * @param string $sql      - The SQL query to execute, with '?' placeholders for parameters.
 * @param array  [$params] - The parameters to safely fill into the query.
 * @param string [$types]  - Data types of the parameters, one character per parameter.
 *                           ('s':string, 'i':integer, 'd':double, 'b':blob)
 * @return \Phi\Datastream|number|false - A Datastream object for retrieving results a row at a time,
 *                                        or the insert_id of a single INSERT query,
 *                                        or the number of affected_rows for multiple INSERT and UPDATE queries,
 *                                        or FALSE if there was an error.
 */
public function pq ( $sql, $params=null, $types=null ) {
  if (!parent::ping()) {
    $this->errors[] = array(
      'operation' => 'mysqli ping',
      'errno' => null,
      'error' => "Lost connection to database and couldn't reconnect."
    );
    return false;
  }

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
    foreach ( $params as &$p ) {
      if (!is_scalar($p)) $p = json_encode($p);
      $bind_params[] = &$p; # Bound parameters must be passed by reference. #
    }
    unset($p);
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

  # Return results based on query type. #
  $verb = strtoupper( preg_replace( '/^(\w+).*$/s', '$1', $sql ) );
  switch ( $verb ) {

    # Return Datastream of SELECTed data. #
    case "SELECT":
    case "SHOW":
    case "DESCRIBE":
      if ( $this->storeResult ) {
        $stmt->store_result();
      }
      $dataStream = new \Phi\Datastream( $stmt );
      if ( $this->returnDataStream ) {
        return $dataStream;
      } else {
        $data = $dataStream->fetch_all();
        $dataStream->close();
        return $data;
      }
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
 * @param  array  $params            - The SQL query parameters.
 * @param  string $params['op']      - The operation to perform, i.e. "SELECT" or "INSERT".
 * @param  string $params['table']   - The table in which to perform the operation.
 * @param  array  $params['columns'] - The table columns (fields) to include in the SQL.
 * @param  bool   $params['update']  - SQL includes "ON DUPLICATE KEY UPDATE..." commands when set to true.
 * @param  bool   $params['ignore']  - SQL includes "IGNORE" duplicate keys command in INSERT operations when set to true.
 * @return string - The resulting SQL.
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
 * @param  array $columns  - The columns.
 * @param  bool  $asUpdate - When true, return in format of "`column_name`=VALUES(`column_name`)".
 * @return string - The resulting SQL-compatible string.
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
  if ( $rows ) {
    foreach ( $rows as $row ) {
      $fields[] = $row['Field'];
    }
  }
  return $fields;
}

/**
 * Prepare and execute a bulk-insert query on the database.
 * @param array  $opts                   - Bulk-insert options...
 * @param string $opts['tbl_name']       - Table name
 * @param array  $opts['col_names']      - Column names
 * @param array  $opts['data']           - The data to insert, an array or associative arrays.
 * @param string $opts['value_markers']  - (optional) Custom inser value markers; defaults to one '?' for each column.
 * @param string $opts['types']          - (optional) Data types of the parameters, one character per parameter;
 *                                         defaults to one 's' for each column;
 *                                         ('s':string, 'i':integer, 'd':double, 'b':blob)
 * @param int    $opts['max_block_size'] - (optional) Max number of rows per INSERT query; defaults to 1000.
 * @param int    $opts['update_dups']    - (optional) Update records with duplicate keys; defaults to FALSE.
 * @return array  - Inserted row IDs.
 */
public function bulk_insert($opts) {

  # Validate Options

  # Table Name
  if (array_key_exists('tbl_name', $opts) && is_string($opts['tbl_name']) && $opts['tbl_name']) {
    $tbl_name = $opts['tbl_name'];
  } else {
    throw new Exception("Option tbl_name must be included, and be a string.");
  }
  # Column Names
  if (array_key_exists('col_names', $opts) && is_array($opts['col_names']) && count($opts['col_names'])) {
    $col_name_list = '`' . implode('`,`', $opts['col_names']) . '`';
  } else {
    throw new Exception("Option col_names must be included, and be an array of strings.");
  }
  # Value Markers
  if (array_key_exists('value_markers', $opts)) {
    $value_markers = $opts['value_markers'];
  } else {
    $value_markers = implode(',', array_fill(0, count($opts['col_names']), "?"));
  }
  # Value Types
  if (array_key_exists('types', $opts)) {
    $types = $opts['types'];
  } else {
    $types = str_repeat('s', count($opts['col_names']));
  }
  # Max Block Size
  if (array_key_exists('max_block_size', $opts)) {
    $max_block_size = $opts['max_block_size'];
  } else {
    $max_block_size = 1000;
  }
  # Update Duplicates
  if (array_key_exists('update_dups', $opts)) {
    $update_dups = $opts['update_dups'];
  } else {
    $update_dups = false;
  }
  if ($update_dups) {
    # ON DUPLICATE KEY UPDATE `col1`=VALUES(`col1`), `col2`=VALUES(`col2`)
    $update_list = [];
    foreach ($opts['col_names'] as $col_name) {
      $update_list[] = "`$col_name`=VALUES(`$col_name`)";
    }
    $update_stmt = ' ON DUPLICATE KEY UPDATE ' . implode(',', $update_list);
  } else {
    $update_stmt = '';
  }
  # Data
  if (!(array_key_exists('data', $opts) && is_array($opts['data']) && count($opts['data']))) {
    throw new Exception("Option data must be included, and be an array of arrays.");
  }

  # While data remains
  $inserted = 0;
  for ($sliceOffset = 0; $sliceOffset < count($opts['data']); $sliceOffset += $max_block_size) {

    # Get next slice of data
    $dataSlice = array_slice($opts['data'], $sliceOffset, $max_block_size);
    if (!count($dataSlice)) break;

    # Build query blocks.
    $value_markers_block = '(' . implode('),(', array_fill(0, count($dataSlice), $value_markers)) . ')';
    $types_block = implode('', array_fill(0, count($dataSlice), $types));
    
    # Prepare the query.
    $query = "INSERT INTO `$tbl_name` ($col_name_list) VALUES $value_markers_block $update_stmt";
    if (! $stmt = parent::prepare($query) ) {
      throw new \Exception($this->error);
    }

    # Bind the parameters. #
    $bound_params = [];
    foreach ($dataSlice as &$row) {
      foreach ($row as &$val) $bound_params[] = &$val;
    }
    array_unshift($bound_params, $types_block);
    if (! call_user_func_array( array($stmt, "bind_param"), $bound_params )) {
      $this->errors[] = array(
        'operation' => 'Database->bulk_insert bind_param',
        'errno' => $this->errno,
        'error' => $this->error
      );
      return false;
    }

    # Execute the query. #
    if ($stmt->execute()) {
      if ( $stmt->affected_rows ) {
        $inserted += $stmt->affected_rows;
      }
    }

  }

  return $inserted;
}

/**
 * Prepare and execute bulk-inserts from source file.
 * @param array  $opts                   - Bulk-insert options...
 * @param string $opts['tbl_name']       - Table name
 * @param array  $opts['col_names']      - Column names
 * @param object #opts['filehandle']     - The input file, a CSV, first line is column headings.
 * @param string $opts['value_markers']  - (optional) Custom inser value markers; defaults to one '?' for each column.
 * @param string $opts['types']          - (optional) Data types of the parameters, one character per parameter;
 *                                         defaults to one 's' for each column;
 *                                         ('s':string, 'i':integer, 'd':double, 'b':blob)
 * @param int    $opts['max_block_size'] - (optional) Max number of rows per INSERT query; defaults to 100.
 * 
 * @return array  - Inserted row IDs.
 */
public function bulk_insert_csv($opts) {

  # Validate Options

  # Table Name
  if (array_key_exists('tbl_name', $opts) && is_string($opts['tbl_name']) && $opts['tbl_name']) {
    $tbl_name = $opts['tbl_name'];
  } else {
    throw new Exception("Option tbl_name must be included, and be a string.");
  }
  # Column Names
  if (array_key_exists('col_names', $opts) && is_array($opts['col_names']) && count($opts['col_names'])) {
    $col_name_list = '`' . implode('`,`', $opts['col_names']) . '`';
  } else {
    throw new Exception("Option col_names must be included, and be an array of strings.");
  }
  # Value Markers
  if (array_key_exists('value_markers', $opts)) {
    $value_markers = $opts['value_markers'];
  } else {
    $value_markers = implode(',', array_fill(0, count($opts['col_names']), "?"));
  }
  # Value Types
  if (array_key_exists('types', $opts)) {
    $types = $opts['types'];
  } else {
    $types = str_repeat('s', count($opts['col_names']));
  }
  # Max Block Size
  if (array_key_exists('max_block_size', $opts)) {
    $max_block_size = $opts['max_block_size'];
  } else {
    $max_block_size = 1000;
  }
  # Data
  if (!(array_key_exists('filehandle', $opts))) {
    throw new Exception("Option filehandle must be included, and be a filehandle.");
  }

  # Get Column Headings
  $csv = $opts['filehandle'];
  rewind($csv);
  $headings = fgetcsv($csv);
  
  # While data remains
  $inserted = 0;
  while (!feof($csv)) {
    
    # Read data until slice full, or eof reached.
    $dataSlice = [];
    while ($row = fgetcsv($csv)) {
      if (count($headings) === count($row)) {
        $dataSlice[] = array_combine($headings, $row);
      }
      if (count($dataSlice) >= $max_block_size) break;
    }

    if ($dataSlice) {

      # Build query blocks.
      $value_markers_block = '(' . implode('),(', array_fill(0, count($dataSlice), $value_markers)) . ')';
      $types_block = implode('', array_fill(0, count($dataSlice), $types));
      
      # Prepare the query.
      $query = "INSERT INTO `$tbl_name` ($col_name_list) VALUES $value_markers_block";
      if (! $stmt = parent::prepare($query) ) {
        throw new \Exception($this->error);
      }

      # Bind the parameters. #
      $bound_params = [];
      foreach ($dataSlice as &$row) {
        foreach ($opts['col_names'] as $col_name) {
          if (!array_key_exists($col_name, $row)) {
            $row[$col_name] = null;
          }
          $bound_params[] = &$row[$col_name];
        }
      }
      array_unshift($bound_params, $types_block);
      if (! call_user_func_array( array($stmt, "bind_param"), $bound_params )) {
        $this->errors[] = array(
          'operation' => 'Database->bulk_insert bind_param',
          'errno' => $this->errno,
          'error' => $this->error
        );
        return false;
      }

      # Execute the query. #
      if ($stmt->execute()) {
        if ( $stmt->affected_rows ) {
          $inserted += $stmt->affected_rows;
        }
      }
    }
  }

  return $inserted;
}

} ?>