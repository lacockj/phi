<?php namespace Phi; class Table implements \Countable, \Iterator, \JsonSerializable {

public $lastError = "";

protected $phi;
protected $tablename;

protected $fields;

protected $rows = null;
protected $changed = null;

protected $keysAllowedToRead = ['tablename','fields','rows','changed'];

/**
 * Class Constructor
 * 
 * @param string $tablename
 * @return Phi\Table
 */
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
  if (in_array($key, $this->keysAllowedToRead)) {
    return $this->{$key};
  }
  return null;
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
  $this->fields = $result->fetch_all();
  $result->close();
  return $this->fields;
}

/**
 * Select Rows From Table
 * 
 * @param array $opts['select']   - (optional) What columns of data to return.
 * @param array $opts['where']    - (optional) What conditions rows must meet.
 * @param array $opts['group_by'] - (optional) Group results by like values in given columns.
 * @param array $opts['order_by'] - (optional) Order results by (key) column -> (value) direction.
 * @return array|null
 */
public function select ($opts) {
  $format = 'SELECT %s FROM %s WHERE %s';
  $tablename = $this->tablename;
  $select    = [];
  $where     = [];
  $params    = [];
  $group_by  = [];
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
    # GROUP BY
    if (array_key_exists('group_by', $opts)) {
      if (is_scalar($opts['group_by'])) $opts['group_by'] = [$opts['group_by']];
      foreach ($opts['group_by'] as $col) {
        $col = str_replace('`', '', $col);
        $group_by[] = "`$col`";
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
  if (count($group_by)) {
    $query .= ' GROUP BY ' . implode(',', $group_by);
  }
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

/**
 * Load Rows
 * 
 * @param array $where - (optional) What conditions rows must meet.
 * @return array|null
 */
public function load ($where=null) {
  # Compile Options
  $selectOpts = [];
  if (is_array($where)) {
    $selectOpts['where'] = $where;
  }
  $tableDescription = $this->describe();
  # Read Rows
  $selected = $this->select($selectOpts);
  $rows = [];
  $changed = [];
  if (is_array($selected)) {
    if (count($selected)) {
      $colCount = count($selected[0]);
      foreach ($selected as $row) {
        $rows[] = new \Phi\TableRow($row, $tableDescription);
        $changed[] = array_fill(0, $colCount, false);
      }
    }
  }
  # Save to Instance
  $this->rows = $rows;
  $this->changed = $changed;
  # Return Instance for Chaining
  return $this;
}

/**
 * Check If Rows Are Loaded
 * 
 * @return bool - If a table is loaded or not.
 */
public function isLoaded () {
  return (is_array($this->rows) && count($this->rows));
}

####################################
# Interface Implementation Methods #
####################################

# Countable #
public function count(): int {
  return count($this->rows);
}

# Iterator #
private $iteratorPosition;
public function current(): mixed {
  return $this->rows[$this->iteratorPosition];
}
public function key(): mixed {
  return $this->iteratorPosition;
}
public function next(): void {
  ++$this->iteratorPosition;
}
public function rewind(): void {
  $this->iteratorPosition = 0;
}
public function valid(): bool {
  return $this->isLoaded() && $this->iteratorPosition >= 0 && $this->iteratorPosition < count($this->rows);
}

# JsonSerializable #
public function jsonSerialize(): mixed {
  return $this->isLoaded() ? $this->rows : null;
}

} # end of class
