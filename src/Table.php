<?php namespace Phi; class Table {

public $lastError = "";

protected $phi;
protected $tablename;
protected $fields;
protected $fieldTypes = array(
  'bool' => array(),
  'json' => array(),
  'exclude' => array()
);

protected $dbFieldJsTypes = array(
  'INTEGER'    => 'number',
  'INT'        => 'number',
  'SMALLINT'   => 'number',
  'TINYINT'    => 'number',
  'MEDIUMINT'  => 'number',
  'BIGINT'     => 'number',
  'DECIMAL'    => 'number',
  'NUMERIC'    => 'number',
  'FLOAT'      => 'number',
  'DOUBLE'     => 'number',
  'BIT'        => 'number',
  'DATE'       => 'date',
  'DATETIME'   => 'date',
  'TIMESTAMP'  => 'date',
  'TIME'       => 'date',
  'CHAR'       => 'string',
  'VARCHAR'    => 'string',
  'TEXT'       => 'string',
  'TINYTEXT'   => 'string',
  'MEDIUMTEXT' => 'string',
  'LONGTEXT'   => 'string',
  'ENUM'       => 'string',
  'BLOB'       => 'binary',
  'TINYBLOB'   => 'binary',
  'MEDIUMBLOB' => 'binary',
  'LONGBLOB'   => 'binary'
);

function __construct ( $tablename ) {
  $this->phi = \Phi\App::instance();
  $this->tablename = str_replace( "`", "", $tablename );
}

/**
 * Magic Getters for Private Properties
 * 
 * @param string $key - The property to get.
 * @return mixed - The property's value, or null if not found.
 */
function __get ($key) {
  switch ($key) {
    # Allowed to Read
    case 'tablename':
    case 'fields':
      return $this->{$key};
    
    default:
      if (isset($this->{$key})) {
        return $this->{$key};
      }
  }
}

/**
 * Magic Setters for Private Properties
 * 
 * @param string $key   - The property name.
 * @param mixed  $value - The property value.
 * @return mixed - Returns the set value for assignment chaining (e.g. $a = $obj->b = "shared_value").
 */
function __set ($key, $value) {
  switch ($key) {
    # Allowed to Write
    case 'tablename':
      if (is_string($value)) {
        $this->tablename = $value;
      }
      break;
  }
  return $value;
}

/**
 * Describe Table
 */
public function describe () {
  if ($this->fields) return $this->fields;
  $result = $this->phi->db->pq("DESCRIBE `{$this->tablename}`");
  $fields = $result->fetch_all();
  $this->fields = [];
  foreach ($fields as $field) {
    preg_match('/^\w+/', $field['Type'], $matches);
    $type = strtoupper($matches[0]);
    $field['JsType'] = $this->dbFieldJsTypes[$type];
    $field['Requried'] = (bool)($field['Null'] === 'NO' && $field['Default'] === null);
    $this->fields[] = $field;
  }
  return $this->fields;
}

/**
 * Select Rows From Table
 * 
 * @param array $opts['select']   - (optional) What columns of data to return.
 * @param array $opts['where']    - (optional) What conditions rows must meet.
 * @param array $opts['order_by'] - (optional) Order results by (key) column -> (value) direction.
 * @return array|null
 */
public function select ($opts) {
  $format = 'SELECT %s FROM %s WHERE %s';
  $tablename = $this->tablename;
  $select    = [];
  $where     = [];
  $params    = [];
  $order_by  = [];

  if (is_array($opts)) {
    # SELECT
    if (array_key_exists('select', $opts)) {
      if (is_array($opts['select'])) {
        $select = $opts['select'];
      }
      if (is_string($opts['select'])) {
        $select = explode(',', $opts['select']);
      }
      array_walk($select, 'trim');
    }
    # WHERE
    if (array_key_exists('where', $opts)) {
      foreach ($opts['where'] as $key=>$value) {
        $key = str_replace('`', '', $key);
        if ($value === null) {
          $where[] = "`$key` IS NULL";
        }
        else {
          $where[] = "`$key`=?";
          $params[] = $value;
        }
      }
    }
    # ORDER BY
    if (array_key_exists('order_by', $opts)) {
      foreach ($opts['order_by'] as $key=>$value) {
        $key = str_replace('`', '', $key);
        $order_by[] = "`$key` $value";
      }
    }
  }

  $select_expr = (count($select)) ? ('`'.implode('`,`', $select).'`') : '*';
  $where_condition = (count($where)) ? implode(' AND ', $where) : '1';
  $query = sprintf($format, $select_expr, $tablename, $where_condition);
  if (count($order_by)) {
    $query .= ' ORDER BY ' . implode(',', $order_by);
  }

  $result = $this->phi->db->pq($query, $params);
  if ( $result === false ) {
    $this->lastError = $this->phi->db->lastError();
    return null;
  }
  // $result->fieldTypes( $this->fieldTypes );
  $rows = $result->fetch_all();
  $result->close();
  return $rows;
}

/**
 * Insert Row
 * 
 * @param array $newData - Key->Value pairs of data.
 * @return int - New row ID.
 */
public function insert ( $newData ) {
  $set = array();
  $where = array();
  $qParams = array();
  if ( ! is_array( $newData ) ) {
    $this->lastError = "\Phi\Table::insert expects parameter 1 to be an associative array.";
    return false;
  }
  foreach ( $newData as $key => $value ) {
    $set[] = "`" . str_replace( "`", "", $key ) . "`=?";
    $qParams[] = $value;
  }
  if ( ! count( $set ) ) {
    $this->lastError = "Nothing to insert";
    return null;
  }
  $query = "INSERT INTO `" . $this->tablename . "` SET " . implode( ", ", $set );
  $result = $this->phi->db->pq( $query, $qParams );
  if ( $result === false ) {
    $this->lastError = "Could Not Update Table";
    $this->phi->log( $this->phi->db->lastError );
    return false;
  }
  return $result;
}

/**
 * Update Matching Rows
 * CAUTION: Without limiting conditions, will update ALL ROWS!
 * 
 * @param $newData    - Key->Value pairs of data.
 * @param $conditions - (optional) What existing values to match to identify rows to update.
 * @return int - Number of updates performed.
 */
public function update ( $newData, $conditions=null ) {
  $set = array();
  $where = array();
  $qParams = array();
  if ( ! is_array( $newData ) ) {
    $this->lastError = "\Phi\Table::update expects parameter 1 to be an associative array.";
    return false;
  }
  foreach ( $newData as $key => $value ) {
    $set[] = "`" . str_replace( "`", "", $key ) . "`=?";
    $qParams[] = $value;
  }
  if ( ! count( $set ) ) {
    $this->lastError = "Nothing to Update";
    return null;
  }
  if ( is_array( $conditions ) ) {
    foreach ( $conditions as $key => $value ) {
      $where[] = $key;
      $qParams[] = $value;
    }
  }
  $query = "UPDATE `" . $this->tablename . "` SET " . implode( ", ", $set ) . ( count($where) ? ( " WHERE `" . implode( "`=?, `", $where ) . "`=?" ) : "" );
  $result = $this->phi->db->pq( $query, $qParams );
  if ( $result === false ) {
    $this->lastError = "Could Not Update Table";
    $this->phi->log( $this->phi->db->lastError );
    return false;
  }
  return $result;
}

} # end of class
