<?php namespace Phi; class Datastream implements \Iterator, \JsonSerializable {

# Properties #

public $num_rows = 0;
public $errors = array();

protected $fieldTypes = array(
  'int' => array(),
  'float' => array(),
  'bool' => array(),
  'json' => array(),
  'exclude' => array()
);

private $stmt;
private $position = 0;
private $row = array();
private $allowReset = true;

function __construct ( \mysqli_stmt $stmt, array $settings=array() ) {
  $this->num_rows = $stmt->num_rows;

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
  if ( is_array($settings) ) {
    if ( array_key_exists( 'fieldTypes', $settings ) && is_array( $settings['fieldTypes'] ) ) {
      $this->fieldTypes( $settings['fieldTypes'] );
    }
  }

  $this->stmt = $stmt;

}

function __destruct () {
  if ( $this->stmt ) $this->stmt->close();
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
  $this->_revertFields();
  return $this->row;
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

public function close () {
  $this->stmt->close();
  $this->stmt = null;
}

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

public function fetch_all ( $resulttype=MYSQLI_ASSOC ) {
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

public function addExclusionFields ( $fields ) {
  if ( is_scalar( $fields ) ) $fields = array( $fields );
  foreach ( $fields as $field ) {
    if ( is_string($field) && $field && !in_array( $field, $this->fieldTypes['exclude'] ) ) {
      $this->fieldTypes['exclude'][] = $field;
    }
  }
}

public function fieldTypes ( $newFieldTypes=null ) {
  if ( is_array( $newFieldTypes ) ) {
    $allowedFieldTypes = array_keys( $this->fieldTypes );
    foreach ( $allowedFieldTypes as $thisType ) {
      if ( array_key_exists( $thisType, $newFieldTypes ) ) {
        if ( is_array( $newFieldTypes[$thisType] ) ) {
          $this->fieldTypes[$thisType] = $newFieldTypes[$thisType];
        } elseif ( is_string( $newFieldTypes[$thisType] ) ) {
          $this->fieldTypes[$thisType] = array( $newFieldTypes[$thisType] );
  } } } }
  return $this->fieldTypes;
}

private function _revertFields ( &$row ) {
  if ( $row === null ) $row = &$this->row;
  foreach ( $this->fieldTypes['int'] as $field ) {
    if ( array_key_exists( $field, $row ) )
      $row[$field] = ( $row[$field] === null ) ? null : intval( $row[$field] );
  }
  foreach ( $this->fieldTypes['float'] as $field ) {
    if ( array_key_exists( $field, $row ) )
      $row[$field] = ( $row[$field] === null ) ? null : floatval( $row[$field] );
  }
  foreach ( $this->fieldTypes['bool'] as $field ) {
    if ( array_key_exists( $field, $row ) )
      $row[$field] = ( $row[$field] === null ) ? null : (bool)( intval( $row[$field] ) );
  }
  foreach ( $this->fieldTypes['json'] as $field ) {
    if ( array_key_exists( $field, $row ) )
      $row[$field] = ( $row[$field] !== null ) ? json_decode($row[$field], true) : null;
  }
  foreach ( $this->fieldTypes['exclude'] as $field ) {
    if ( array_key_exists( $field, $row ) )
      unset($row[$field]);
  }
  return $row;
}

} ?>