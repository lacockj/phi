<?php namespace Phi; class Auth {

  protected $user;

  private $phi;
  private $session;
  private $db;

  public function __construct ( \Phi $phi ) {
    $this->phi = $phi;
    $this->session = $this->phi->session;
    $this->db = $this->phi->db;
  }

  public function challenge ( $realm="standard" ) {
    if ( !( is_string($realm) && $realm ) ) throw new \Exception( "Realm must be a string." );
    $this->phi->response->status( 401 );
    $this->phi->response->headers( 'WWW-Authenticate: Phi realm="' . $realm . '"' );
  }

  public function logIn () {
    $authorization = $this->phi->request->headers('Authorization');
    $authScheme = self::strpop( $authorization );
    switch ( strtolower($authScheme) ) {
      case "phi":
        $credentials = base64_decode( $authorization );
        $user = self::strpop( $credentials, ":" );
        $pass = $credentials;
        $this->user = $this->getUser( $user );
        # Verify User's Credentials
        if ( $this->user ) {
          if ( password_verify( $pass, $this->user['pass'] ) ) {
            $this->session['USER'] = $this->user;
            return true;
          } else {
            return false;
          }
        }
        # Simulate Verifying Non-user's Credentials
        else {
          password_hash( "User doesn't exist, but don't tell anyone.", PASSWORD_DEFAULT );
          return false;
        }
        break;
      default:
        return false;
    }
  }

  public function logOut () {
    if ( isset( $this->session['USER'] ) ) unset( $this->session['USER'] );
    return true;
  }

  public function inSession () {
    return ( isset( $this->session['USER'] ) ) ? $this->session['USER'] : false;
  }

  public function sessionUser () {
    return $this->inSession();
  }

  public function getUser ( $user ) {
    $datastream = $this->db->pq( 'SELECT * FROM `users` WHERE `id`=?', $user );
    return ( $datastream ) ? $datastream->fetch_assoc() : false;
  }

  public function loggedIn () {
    $sessionUser = $this->sessionUser();
    if ( $sessionUser && isset( $sessionUser['id'] ) ) {
      $this->user = $this->getUser( $sessionUser['id'] );
      if ( isset( $sessionUser['pass'] ) && $sessionUser['pass'] === $this->user['pass'] ) {
        return $sessionUser;
      }
    }
    return false;
  }

  # Utility Functions #

  private static function strpop ( &$str, $sep=" " ) {
    $pos = strpos( $str, $sep );
    $pop = substr( $str, 0, $pos );
    $str = substr( $str, $pos+1 );
    return $pop;
  }

}?>