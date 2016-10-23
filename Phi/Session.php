<?php namespace Phi; class Session implements \ArrayAccess {

private $_id = null;
private $_data = null;

public function __construct ( $sessionlife ) {
  session_set_cookie_params(
    0,     # Session life (to be overridden by setcookie)
    '/',   # All paths
    '.'.$_SERVER['SERVER_NAME'], # Domain
    true,  # Cookie only sent over secure connections
    false  # Not only HTTP
  );
  session_cache_limiter('');
  session_start();
  setcookie(session_name(),session_id(),time()+$sessionlife);
  $this->_id = session_id();
  $this->_data = self::array_copy( $_SESSION );
  session_write_close();
}

public function __destruct () {
  @session_start();
  session_unset();
  foreach ( $this->_data as $key => $val ) {
    $_SESSION[$key] = $val;
  }
  session_write_close();
}

public function id () {
  return $this->_id;
}

public function clear () {
  foreach ( $this->_data as $key => $val ) {
    unset( $this->_data[$key] );
  }
}

public function offsetSet( $offset, $value ) {
  if ( is_null($offset) ) {
    $this->_data[] = $value;
  } else {
    $this->_data[$offset] = $value;
  }
}

public function offsetExists ( $offset ) {
  return isset( $this->_data[$offset] );
}

public function offsetUnset ( $offset ) {
  unset( $this->_data[$offset] );
}

public function offsetGet ( $offset ) {
  return isset( $this->_data[$offset] ) ? $this->_data[$offset] : null;
}

public function toArray () {
  return $this->_data;
}

public function array_copy ( array $original ) {
  $copy = array();
  foreach( $original as $key => $val ) {
    if( is_array( $val ) ) {
      $copy[$key] = self::array_copy( $val );
    } elseif ( is_object( $val ) ) {
      $copy[$key] = clone $val;
    } else {
      $copy[$key] = $val;
    }
  }
  return $copy;
}

}?>