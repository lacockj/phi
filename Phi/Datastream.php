<?php namespace Phi; class Datastream implements \Iterator {

# Properties #

public $errors = array();
public $data = array();

private $stmt;
private $position = 0;
private $row = array();
private $allowReset = true;

function __construct ( \mysqli_stmt $stmt ) {

    # Identify the columns in the result set. #
    $fields = array();
    foreach ( $stmt->result_metadata()->fetch_fields() as $field) {
      $fields[] = $field->name;
    }
    $result_bindings = array();
    foreach ($fields as $fieldName) {
      $this->row[$fieldName] = null;
      $result_bindings[] = &$this->row[$fieldName];
    }
    if (! call_user_func_array( array($stmt, "bind_result"), $result_bindings )) {
      $this->errors[] = array(
        'operation' => 'mysqli bind_result',
        'errno' => $this->errno,
        'error' => $this->error
      );
      return false;
    }
    $this->stmt = $stmt;

}

function __destruct () {
  $this->stmt->close();
}


# Iterator Interface Methods #

function rewind() {
  $this->position = 0;
}

function next() {
  ++$this->position;
}

function valid() {
  if ( $this->stmt->fetch() ) {
    $thisRow = array();
    foreach ( $this->row as $key => $value ) {
      $thisRow[$key] = $value;
    }
    return true;
  }
  return false;
}

function current() {
  return $this->row;
}

function key() {
  return $this->position;
}


# MySQLi Statement-like Methods #

public function fetch_row () {
  if ( $this->stmt->fetch() ) {
    $thisRow = array();
    foreach ( $this->row as $key => $value ) {
      $thisRow[] = $value;
    }
    return $thisRow;
  }
  return false;
}

public function fetch_assoc () {
  if ( $this->stmt->fetch() ) {
    $thisRow = array();
    foreach ( $this->row as $key => $value ) {
      $thisRow[$key] = $value;
    }
    return $thisRow;
  }
  return false;
}

public function fetch_all ( $resulttype=MYSQLI_ASSOC) {
  $rows = array();
  while ( $this->stmt->fetch() ) {
    $thisRow = array();
    foreach ( $this->row as $key => $value ) {
      if ( $resulttype & MYSQLI_ASSOC ) $thisRow[$key] = $value;
      if ( $resulttype & MYSQLI_NUM ) $thisRow[] = $value;
    }
    $rows[] = $thisRow;
  }
  return $rows;
}

} ?>