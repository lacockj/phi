<?php namespace Phi; class Session implements \ArrayAccess {

private $_id = null;
private $_check = null;
private $_data = null;
private $_changed = false;

public function __construct ( $sessionlife=0, $path='/', $secureOnly=true, $httpOnly=false ) {
  $sessionlife = (int)$sessionlife;
  session_set_cookie_params($sessionlife, $path, null, $secureOnly, $httpOnly);
  session_cache_limiter('');
  session_start();
  $this->_id = session_id();
  $this->_check = ( array_key_exists( 'phiCheck', $_SESSION ) ) ? $_SESSION['phiCheck'] : time();
  $this->_data = ( array_key_exists( 'phiData', $_SESSION ) ) ? unserialize( $_SESSION['phiData'] ) : array();
  session_write_close();
}

public function __destruct () {
  if ($this->_changed) {
    @session_start();
    $_SESSION['phiCheck'] = time();
    $_SESSION['phiData'] = serialize( $this->_data );
    session_write_close();
  }
}

public function save () {
  if ($this->_changed) {
    @session_start();
    $_SESSION['phiCheck'] = time();
    $_SESSION['phiData'] = serialize( $this->_data );
    session_write_close();
  }
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
  $this->_changed = true;
}

public function toArray () {
  return $this->_data;
}

####################################
# Interface Implementation Methods #
####################################

# ArrayAccess #
public function offsetExists ( $offset ): bool {
  return isset( $this->_data[$offset] );
}
public function offsetGet ( $offset ): mixed {
  return isset( $this->_data[$offset] ) ? $this->_data[$offset] : null;
}
public function offsetSet( $offset, $value ): void {
  if ( is_null($offset) ) {
    $this->_data[] = $value;
  } else {
    $this->_data[$offset] = $value;
  }
  $this->_changed = true;
}
public function offsetUnset ( $offset ): void {
  unset( $this->_data[$offset] );
  $this->_changed = true;
}

} # end of class
