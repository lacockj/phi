<?php namespace Phi; class Auth {

private $TABLE = array(
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
  $this->session = $this->phi->session;
  if ( isset($config) ) {
    if ( isset($config['DB']) && $phi->all_set( $config['DB'], 'HOST', 'USER', 'PASS', 'NAME' ) ) {
      $this->db = new \Phi\Database( $config['DB'] );
    } else {
      $this->db = $this->phi->db;
    }
    if ( isset($config['TABLE']) && $phi->all_set( $config['TABLE'], 'NAME', 'USER', 'PASS' ) ) {
      $this->TABLE = $config['TABLE'];
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

public function logIn () {
  $authorization = $this->phi->request->headers('Authorization');
  $authScheme = \Phi::strpop( $authorization );
  switch ( strtolower($authScheme) ) {
    case "phi":
      $credentials = base64_decode( $authorization );
      $user = \Phi::strpop( $credentials, ":" );
      $pass = $credentials;
      $this->user = $this->getUser( $user );
      # Verify User's Credentials
      if ( $this->user ) {
        if ( password_verify( $pass, $this->user[ $this->TABLE['PASS'] ] ) ) {
          $this->session['sessionUser'] = $this->user;
          return true;
        } else {
          $this->phi->log("Failed login attempt by ".$this->user[ $this->TABLE['USER'] ]);
          return false;
        }
      }
      # Simulate Verifying Non-user's Credentials
      else {
        password_hash( "UserID doesn't exist, but don't reveal that fact.", PASSWORD_DEFAULT );
        return false;
      }
      break;
    default:
      return false;
  }
}

public function logOut () {
  if ( isset( $this->session['sessionUser'] ) ) unset( $this->session['sessionUser'] );
  return true;
}

public function inSession () {
  return ( isset( $this->session['sessionUser'] ) ) ? true : false;
}

public function sessionUser () {
  return ( isset( $this->session['sessionUser'] ) ) ? $this->session['sessionUser'] : false;
}

public function loggedIn () {
  $sessionUser = $this->sessionUser();
  if ( $sessionUser && isset( $sessionUser['id'] ) ) {
    $this->user = $this->getUser( $sessionUser['id'] );
    if ( isset( $sessionUser[ $this->TABLE['PASS'] ] ) && $sessionUser[ $this->TABLE['PASS'] ] === $this->user[ $this->TABLE['PASS'] ] ) {
      return $sessionUser;
    }
  }
  return false;
}

public function getUser ( $userID ) {
  $datastream = $this->db->pq( 'SELECT * FROM `'.$this->TABLE['NAME'].'` WHERE `'.$this->TABLE['USER'].'`=?', $userID );
  return ( $datastream ) ? $datastream->fetch_assoc() : false;
}

}?>