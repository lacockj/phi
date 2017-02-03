<?php namespace Phi; class Auth {

protected $REQUIRE_HTTPS = true;
protected $TABLE = array(
  'NAME' => 'users',
  'USER' => 'userID',
  'PASS' => 'hashedPassword'
);

private $phi;
private $session;
private $db;
private $user;

public function __construct ( \Phi $phi, $config ) {
  $this->phi = $phi;
  $this->session = &$this->phi->session;
  if ( isset($config) ) {
    if ( isset($config['DB']) && $phi->all_set( $config['DB'], 'HOST', 'USER', 'PASS', 'NAME' ) ) {
      $this->db = new \Phi\Database( $config['DB'] );
    } else {
      $this->db = $this->phi->db;
    }
    if ( isset($config['TABLE']) && $phi->all_set( $config['TABLE'], 'NAME', 'USER', 'PASS' ) ) {
      $this->TABLE = $config['TABLE'];
    }
    if ( isset($config['REQUIRE_HTTPS']) ) {
      $this->REQUIRE_HTTPS = (bool) $config['REQUIRE_HTTPS'];
    }
  }
}

/**
 * Magic property getter
 * @param {string} $name - The property to get.
 * @return {mixed}       - The property's value.
 */
public function __get ( $name ) {

  switch ( $name ) {

    case "user":
      return $this->user;

    default:
      return null;
  }
}

public function challenge ( $realm="standard" ) {
  if ( !( is_string($realm) && $realm ) ) throw new \Exception( "Realm must be a string." );
  $this->phi->response->status( 401 );
  $this->phi->response->headers( 'WWW-Authenticate: Phi realm="' . $realm . '"' );
}

//public function newUser

/**
 * @return {bool|null} - Returns TRUE is "Authorization" header is valid, FALSE if it is invalid, or NULL if it is missing.
 */
public function checkAuthorization () {
  $authorization = $this->phi->request->headers('Authorization');
  if ( ! $authorization ) return null;
  $authScheme = \Phi::strpop( $authorization );
  switch ( strtolower($authScheme) ) {
    case "phi":
      $credentials = base64_decode( $authorization );
      $username = \Phi::strpop( $credentials, ":" );
      $pass = $credentials;
      $user = $this->getUser( $username );
      # Verify User's Credentials
      if ( $user ) {
        if ( password_verify( $pass, $user[ $this->TABLE['PASS'] ] ) ) {
          return $user;
        } else {
          \Phi::log("Failed login attempt by $username");
          return false;
        }
      }
      # Pretend To Verify Nonexistant User's Credentials
      else {
        password_verify( 'Missing User', '$2a$08$nqWza8jri7gZmOKYubrLrOVbEZTbEzXnbkJ.ad/2.RlbsbMQxPVO.' );
        return false;
      }
      break;
    default:
      return false;
  }
}

public function checkConnectionSecurity () {
  return ( ( $this->REQUIRE_HTTPS ? $this->phi->request->isHTTPS() : true ) && $this->phi->request->isSameOrigin() );
}

public function createUser ( $userID, $pass ) {
  $hashword = password_hash( $pass, PASSWORD_DEFAULT );
  $result = $this->db->pq( 'INSERT INTO `'.$this->TABLE['NAME'].'` (`'.$this->TABLE['USER'].'`,`'.$this->TABLE['PASS'].'`) VALUES (?,?)', array( $userID, $hashword ) );
  if ( $result === false ) {
    $this->phi->log_json( $this->db->lastError() );
  }
  return (bool) $result;
}

public function getUser ( $userID ) {
  $result = $this->db->pq( 'SELECT * FROM `'.$this->TABLE['NAME'].'` WHERE `'.$this->TABLE['USER'].'`=?', $userID );
  return ( $result ) ? $result->fetch_assoc() : false;
}

public function inSession () {
  return ( isset( $this->session['phiSessionUser'] ) ) ? true : false;
}

public function isAuthorized () {
  if ( false ) {
    $this->phi->log( "Security Check:", true );
    $this->phi->log_json( array(
      'connectionSecurity' => $this->checkConnectionSecurity(),
      'authorization'      => $this->checkAuthorization(),
      'session'            => $this->phi->session->toArray(),
      'sessionUser'        => $this->sessionUser(),
      'loggedIn'           => $this->loggedIn()
    ));
  }
  if ( ! $this->checkConnectionSecurity() ) return false;
  $authorization = $this->checkAuthorization();
  return ( $authorization === null ) ? $this->loggedIn() : $authorization;
}

public function logIn () {

  if ( ! $this->checkConnectionSecurity() ) return false;

  # Check 'Authorization' field in request header
  $user = $this->checkAuthorization();
  # Good: Start session
  if ( $user ) {
    $this->user = $user;
    $this->session['phiSessionUser'] = $this->user;
    return true;
  # Bad: No session change
  } else {
    return false;
  }
}

public function loggedIn () {
  $sessionUser = $this->sessionUser();
  if ( $sessionUser && isset( $sessionUser[ $this->TABLE['USER'] ] ) ) {
    $this->user = $this->getUser( $sessionUser[ $this->TABLE['USER'] ] );
    if ( isset( $sessionUser[ $this->TABLE['PASS'] ] ) && $sessionUser[ $this->TABLE['PASS'] ] === $this->user[ $this->TABLE['PASS'] ] ) {
      return $sessionUser;
    }
  }
  return false;
}

public function logOut () {
  //$this->phi->session->destroy();
  if ( isset( $this->session['phiSessionUser'] ) ) unset( $this->session['phiSessionUser'] );
  return true;
}

public function sessionUser () {
  return ( isset( $this->session['phiSessionUser'] ) ) ? $this->session['phiSessionUser'] : false;
}

}?>