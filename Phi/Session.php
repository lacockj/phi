<?php namespace Phi; class Session implements \ArrayAccess {

private $_id = null;
private $_check = null;
private $_data = null;

public function __construct ( $sessionlife=null, $secureOnly=true ) {
  $sessionlife = (int)$sessionlife;
  session_set_cookie_params(
    0,     # Session life (to be overridden by setcookie)
    '/',   # All paths
    '.'.$_SERVER['SERVER_NAME'], # Domain
    $secureOnly,  # Cookie only sent over secure connections
    false  # Not only HTTP
  );
  session_cache_limiter('');
  session_start();
  if (is_numeric($sessionlife)) {
    setcookie(session_name(),session_id(),time()+$sessionlife,"/");
  } else {
    setcookie(session_name(),session_id());
  }
  $this->_id = session_id();
  $this->_check = ( array_key_exists( 'phiCheck', $_SESSION ) ) ? $_SESSION['phiCheck'] : time();
  $this->_data = ( array_key_exists( 'phiData', $_SESSION ) ) ? unserialize( $_SESSION['phiData'] ) : array();
  session_write_close();
}

public function __destruct () {
  @session_start();
  $_SESSION['phiCheck'] = time();
  $_SESSION['phiData'] = serialize( $this->_data );
  session_write_close();
}

public function save () {
  @session_start();
  $_SESSION['phiCheck'] = time();
  $_SESSION['phiData'] = serialize( $this->_data );
  session_write_close();
}

public function destroy () {
  session_start();
  $_SESSION = array();
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"],
      $params["secure"], $params["httponly"]
    );
  }
  session_destroy();
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

}?>