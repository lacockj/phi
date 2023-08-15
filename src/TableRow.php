<?php namespace Phi; class TableRow implements \ArrayAccess, \JsonSerializable {

/** @var array[] $fields - Definitions for each field in the table row. */
protected $fields = [];
/** @var string $priKey - The row's primary key fieldname. */
protected $priKey = null;

protected $dbFieldPhpTypes = array(
  'INTEGER'    => 'int',
  'INT'        => 'int',
  'SMALLINT'   => 'int',
  'TINYINT'    => 'int',
  'MEDIUMINT'  => 'int',
  'BIGINT'     => 'int',
  'DECIMAL'    => 'float',
  'NUMERIC'    => 'float',
  'FLOAT'      => 'float',
  'DOUBLE'     => 'float',
  'BIT'        => 'binary',
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
  'BLOB'       => 'blob',
  'TINYBLOB'   => 'blob',
  'MEDIUMBLOB' => 'blob',
  'LONGBLOB'   => 'blob'
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

/** @var mixed[] $data - Associative array of row values. */
protected $data = null;
/** @var bool[] $changed - Associative array of which row values changed from initial. */
protected $changed = null;
/** @var bool[] $valid - Associative array of which row values are valid according to field definition. */
protected $valid = null;

/**
 * Class Constructor
 * 
 * @param string $tablename
 * @return Phi\Table
 */
function __construct ($data=null, $tableDefinition=null) {
  if ($tableDefinition) $this->defineFields($tableDefinition);
  if ($data) $this->set($data);
}

/**
 * Magic Getters for Private Properties
 * 
 * @param string $key - The property to get.
 * @return mixed - The property's value, or null if not found.
 */
function __get ($key) {
  if (isset($this->{$key})) {
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
  $this->set([$key=>$value]);
  return $value;
}

/**
 * Define Table Fields
 * Aids in type-checking and validation when setting row values.
 * 
 * Example Table Definition Field:
 * [
 *   {
 *     "Field": "company_id",
 *     "Type": "int(11)",
 *     "Null": "NO",
 *     "Key": "",
 *     "Default": null,
 *     "Extra": ""
 *   }, ...
 * ]
 * @param array $tableDefinition - The table definition of all relevant fields.
 * @return Phi\TableRow
 */
public function defineFields ($tableDefinition) {
  $fields = [];
  $priKey = null;
  if (is_array($tableDefinition)) {
    foreach ($tableDefinition as $field) {
      if ($field['Key'] === 'PRI') $priKey = $field['Field'];
      preg_match('/^\w+/', $field['Type'], $matches);
      $type = strtoupper($matches[0]);
      $field['PhpType']  = $this->dbFieldPhpTypes[$type];
      $field['JsType']   = $this->dbFieldJsTypes[$type];
      $field['Required'] = (bool)($field['Null'] === 'NO' && $field['Default'] === null);
      $fields[$field['Field']] = $field;
    }  
  }
  $this->fields = $fields;
  $this->priKey = $priKey;
}

/**
 * Set Value
 * 
 * @param array $row - An array of key->value pairs to set.
 * @return Phi\TableRow
 */
public function set ($row) {
  $data = [];
  $changed = [];
  $valid = [];
  foreach ($row as $key=>$value) {
    if (array_key_exists($key, $this->fields)) {
      $field = $this->fields[$key];
      # Match PHP to SQL data type.
      switch ($field['PhpType']) {
        case 'int':
          if (is_int($value) || is_numeric($value)) {
            $value = (int) $value;
          } else {
            $value = null;
          }
          break;
        case 'float':
          if (is_int($value) || is_numeric($value)) {
            $value = (float) $value;
          } else {
            $value = null;
          }
          break;
        case 'binary':
          if (is_string($value)) {
            $value = "b'$value'";
          } else {
            $value = null;
          }
          break;
        case 'date':
          if (is_int($value)) {
            $value = date('Y-m-d H:i:s', $value);
          } elseif (!(is_string($value) && $value)) {
            $value = null;
          }
          break;
        case 'string':
          if (is_scalar($value)) {
            $value = (string) $value;
          } else {
            $value = null;
          }
          break;
        default:
          $value = null;
          break;
      }
      $isValid = !($value === null && $field['Required']);
    } else {
      $isValid = null;
    }
    $data[$key]    = $value;
    $changed[$key] = !( $this->data === null || $value === $this->data[$key] );
    $valid[$key]   = $isValid;
  }
  if ($this->data === null) {
    $this->data    = $data;
    $this->changed = $changed;
    $this->valid   = $valid;
  } else {
    $this->data    = array_merge($this->data, $data);
    $this->changed = array_merge($this->changed, $changed);
    $this->valid   = array_merge($this->valid, $valid);
  }
}

/**
 * Get Row Status
 * The "change" and "valid" status for each field, as well as it's current value.
 * 
 * @return array
 */
public function getRowStatus () {
  $status = [];
  foreach ($this->data as $key=>$value) {
    $status[$key] = [
      'value'   => $value,
      'changed' => $this->changed[$key],
      'valid'   => $this->valid[$key]
    ];
  }
  return $status;
}

/**
 * Check If All Values Are Valid
 * 
 * @return bool
 */
public function isValid () {
  return array_sum($this->valid) === count($this->valid);
}

/**
 * Check If Row Values Match Given Values
 * 
 * @param array $where - Conditions to match as key=>value pairs.
 * @return bool
 */
public function matches ($where) {
  foreach ($where as $key=>$value) {
    if (!(isset($this->data[$key]) && $value === $this->data[$key])) return false;
  }
  return true;
}

/**
 * Check If Any Values Are Changed
 * 
 * @return bool
 */
public function isChanged () {
  return (bool) array_sum($this->changed);
}

/**
 * Check If Primary Key Changed
 * 
 * @return bool
 */
public function priKeyChanged () {
  return $this->changed[$this->priKey];
}

/**
 * Get Changed Values
 * 
 * @return array
 */
public function getChanges () {
  $changes = [];
  if ($this->isChanged()) {
    foreach ($this->changed as $key=>$isChanged) {
      if ($isChanged) {
        $changes[$key] = $this->data[$key];
      }
    }
  }
  return $changes;
}


####################################
# Interface Implementation Methods #
####################################

# ArrayAccess #
public function offsetExists(mixed $offset): bool {
  return isset($this->data[$offset]);
}
public function offsetGet(mixed $offset): mixed {
  return isset($this->data[$offset]) ? $this->data[$offset] : null;
}
public function offsetSet(mixed $offset, mixed $value): void {
  $this->set([$offset => $value]);
}
public function offsetUnset(mixed $offset): void {
  $this->set([$offset => null]);
}

# JsonSerializable #
public function jsonSerialize(): mixed {
  return $this->data;
}

} # end of class
