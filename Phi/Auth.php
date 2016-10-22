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

  public function logIn ( $user, $pass ) {
    $this->user = $this->getUser( $user );
    if ( password_verify( $pass, $this->user['pass'] ) ) {
      $this->session['USER'] = $this->user;
      return true;
    } else {
      unset( $this->session['USER'] );
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

}

?>