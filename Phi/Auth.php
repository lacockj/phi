<?php namespace Phi; class Auth {

  private $phi;
  private $session;

  public function __construct ( \Phi $phi, \Phi\Database $db ) {
    $this->phi = $phi;
    $this->session = $phi->session;
    $this->db = $db;
  }

  public function logIn ( $user, $pass ) {
    $userRecord = $this->getUser( $user );
    if ( password_verify( $pass, $userRecord['pass'] ) ) {
      $this->session['user'] = $userRecord;
      return true;
    } else {
      unset( $this->session['user'] );
      return false;
    }
  }

  public function logOut () {
    if ( isset( $this->session['user'] ) ) unset( $this->session['user'] );
    return true;
  }

  public function inSession () {
    return ( isset( $this->session ) ) ? $this->session['user'] : false;
  }

  public function sessionUser () {
    return $this->inSession();
  }

  public function getUser ( $user ) {
    $datastream = $this->db->pq( 'SELECT * FROM `users` WHERE `user`=?', $user );
    return $datastream->fetch_assoc();
  }

  public function loggedIn () {
    $sessionUser = $this->sessionUser();
    if ( $sessionUser && isset( $sessionUser['user'] ) ) {
      $userRecord = $this->getUser( $sessionUser['user'] );
    }
    if ( isset( $sessionUser['pass'] ) && $sessionUser['pass'] === $userRecord['pass'] ) {
      return $sessionUser;
    }
    return false;
  }

}

?>