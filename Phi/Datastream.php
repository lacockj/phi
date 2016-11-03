<?php namespace Phi; class Datastream implements \Iterator, \JsonSerializable {

# Properties #

public $errors = array();

protected $fieldTypes = array( 'bool' => array(), 'json' => array() );

private $stmt;
private $position = 0;
private $row = array();
private $allowReset = true;

function __construct ( \mysqli_stmt $stmt, array $settings=array() ) {

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

  # Settings
  if ( is_array($settings) && array_key_exists( 'fieldTypes', $settings ) && is_array( $settings['fieldTypes'] ) ) {
    if ( array_key_exists( 'bool', $settings['fieldTypes'] ) && is_array( $settings['fieldTypes']['bool'] ) ) {
      $this->fieldTypes['bool'] = $settings['fieldTypes']['bool'];
    } elseif ( is_string( $settings['fieldTypes']['bool'] ) ) {
      $this->fieldTypes['bool'] = array( $settings['fieldTypes']['bool'] );
    }
    if ( array_key_exists( 'json', $settings['fieldTypes'] ) && is_array( $settings['fieldTypes']['json'] ) ) {
      $this->fieldTypes['json'] = $settings['fieldTypes']['json'];
    } elseif ( is_string( $settings['fieldTypes']['json'] ) ) {
      $this->fieldTypes['json'] = array( $settings['fieldTypes']['json'] );
    }
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
  return (bool)( $this->stmt->fetch() );
}

function current() {
  return $this->_revertFields( $this->row );
}

function key() {
  return $this->position;
}


# JsonSerializable Interface Methods #

function jsonSerialize() {
  $this->stmt->store_result();
  $allRows = array();
  while ( $this->stmt->fetch() ) {
    $thisRow = array();
    foreach ( $this->row as $key => $value ) {
      $thisRow[$key] = $value;
    }
    $allRows[] = $this->_revertFields( $thisRow );
  }
  $this->stmt->data_seek( 0 );
  return $allRows;
}


# MySQLi Statement-like Methods #

public function fetch_row () {
  if ( $this->stmt->fetch() ) {
    $this->_revertFields( $this->row );
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
    $this->_revertFields( $this->row );
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
    $this->_revertFields( $this->row );
    $thisRow = array();
    foreach ( $this->row as $key => $value ) {
      if ( $resulttype & MYSQLI_ASSOC ) $thisRow[$key] = $value;
      if ( $resulttype & MYSQLI_NUM ) $thisRow[] = $value;
    }
    $rows[] = $thisRow;
  }
  return $rows;
}

public function addBoolField ( $field ) {
  if ( is_string($field) && $field && !in_array( $field, $this->fieldTypes['bool'] ) )
    $this->fieldTypes['bool'][] = $field;
}

public function addBoolFields ( $fields ) {
  foreach ( $fields as $field ) $this->addBoolField( $field );
}

public function addJsonField ( $field ) {
  if ( is_string($field) && $field && !in_array( $field, $this->fieldTypes['json'] ) )
    $this->fieldTypes['json'][] = $field;
}

public function addJsonFields ( $fields ) {
  foreach ( $fields as $field ) $this->addJsonField( $field );
}

private function _revertFields ( $row ) {
  foreach ( $this->fieldTypes['bool'] as $field ) {
    if ( array_key_exists( $field, $row ) )
      $row[$field] = ( $row[$field] === null ) ? null : (bool)( intval( $row[$field] ) );
  }
  foreach ( $this->fieldTypes['json'] as $field ) {
    if ( array_key_exists( $field, $row ) )
      $row[$field] = ( $row[$field] !== null ) ? json_decode($row[$field], true) : null;
  }
  return $row;
}

} ?>