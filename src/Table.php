<?php namespace Phi; class Table {

public $lastError = "";

protected $phi;
protected $tablename;
protected $fieldTypes = array(
  'bool' => array(),
  'json' => array(),
  'exclude' => array()
);

protected $dbFieldJsTypes = array(
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

function __construct ( $phi, $tablename, $fieldTypes=null ) {
  $this->phi = $phi;
  $this->tablename = str_replace( "`", "", $tablename );
  if ( is_array($fieldTypes) ) {
    foreach( $this->fieldTypes as $type => $fields ) {
      if ( array_key_exists( $type, $fieldTypes ) ) {
        if ( is_array( $fieldTypes[$type] ) ) {
          $this->fieldTypes[$type] = $fieldTypes[$type];
        } elseif ( is_string( $fieldTypes[$type] ) ) {
          $this->fieldTypes[$type] = array( $fieldTypes[$type] );
        }
      }
    }
  }
}

public function select ( $conditions=array() ) {
  $qFields = array();
  $qValues = array();
  if ( is_array( $conditions ) ) {
    foreach ( $conditions as $field => $value ) {
      $qFields[] = "`" . str_replace( "`", "", $field ) . "`=?";
      $qValues[] = $value;
    }
  }
  $query = "SELECT * FROM `" . $this->tablename . "`" . ( count($qFields) ? (" WHERE " . implode( ", ", $qFields) ) : "" );
  $result = $this->phi->db->pq( $query, $qValues );
  if ( $result === false ) {
    $this->lastError = "Not Found";
    return null;
  }
  $result->fieldTypes( $this->fieldTypes );
  $users = $result->fetch_all();
  $result->close();
  return $users;
}

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
