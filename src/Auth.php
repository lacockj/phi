<?php

namespace Phi;

use \Firebase\JWT\JWT;

class Auth {

protected $REQUIRE_HTTPS = true;
protected $TABLE = array(
  'NAME' => 'users',
  'USER' => 'userID',
  'PASS' => 'hashedPassword'
);
protected $API_TABLE = array(
  'NAME' => 'groups',
  'KEY'  => 'api_key'
);

private $phi;
private $db;
private $user;

public function __construct ($phi, $config) {
  $this->phi = $phi;
  if ( isset($config) ) {
    if ( isset($config['DB']) && \Phi\Tools::all_set( $config['DB'], 'HOST', 'USER', 'PASS', 'NAME' ) ) {
      $this->db = new \Phi\Database( $config['DB'] );
    } else {
      $this->db = $this->phi->db;
    }
    if ( isset($config['REQUIRE_HTTPS']) ) {
      $this->REQUIRE_HTTPS = (bool) $config['REQUIRE_HTTPS'];
    }
    if ( isset($config['TABLE']) && \Phi\Tools::all_set( $config['TABLE'], 'NAME', 'USER', 'PASS' ) ) {
      $this->TABLE = $config['TABLE'];
    }
    if ( isset($config['API_TABLE']) && \Phi\Tools::all_set( $config['API_TABLE'], 'NAME', 'KEY' ) ) {
      $this->API_TABLE = $config['API_TABLE'];
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

/**
 * Magic property setter
 * @param {string} $name  - The property to set.
 * @param {string} $value - The property's new value.
 * @return {bool}         - If the operation succeeded.
 */
public function __set ( $name, $value ) {
  switch ( $name ) {

    case "TABLE":
      if (is_array($value)) {
        if (array_key_exists('NAME', $value) && is_string($value['NAME']) && $value['NAME'])
          $this->TABLE['NAME'] = $value['NAME'];
        if (array_key_exists('USER', $value) && is_string($value['USER']) && $value['USER'])
          $this->TABLE['USER'] = $value['USER'];
        if (array_key_exists('PASS', $value) && is_string($value['PASS']) && $value['PASS'])
          $this->TABLE['PASS'] = $value['PASS'];
        return true;
      } else {
        return false;
      }

    default:
      return false;
  }
}

/**
 * Create New User
 * @param {string} $userID - A unique User ID.
 * @param {string} $pass   - An initial password for the new User.
 * @param {array}  $extras - Optional array of key=>value pairs to insert with User record.
 * @returns {bool} - If the operation succeeded.
 */
public function createUser ( $userID, $pass, $extras=null ) {
  $columns = array(
    $this->TABLE['USER'],
    $this->TABLE['PASS']
  );
  $placeholders = array("?","?");
  $values = array(
    $userID,
    password_hash( $pass, PASSWORD_DEFAULT )
  );
  if ( is_array( $extras ) ) {
    foreach ( $extras as $key => $value ) {
      $columns[] = $key;
      $placeholders[] = "?";
      $values[] = $value;
    }
  }
  $result = $this->db->pq( 'INSERT INTO `'.$this->TABLE['NAME'].'` (`'.implode('`,`', $columns).'`) VALUES ('.implode(',', $placeholders).')', $values );
  if ( !$result ) {
    \Phi\Tools::log_json( $this->db->lastError() );
  }
  return (bool) $result;
}

/**
 * Update User Password
 * @param {string} $userID - A unique User ID.
 * @param {string} $pass   - An initial password for the new User.
 * @returns {bool} - If the operation succeeded.
 */
public function updateUserPass ( $userID, $pass ) {
  $result = $this->db->pq(
    'UPDATE `'.$this->TABLE['NAME'].'` SET `'.$this->TABLE['PASS'].'`=? WHERE `'.$this->TABLE['USER'].'`=?',
    array(
      password_hash( $pass, PASSWORD_DEFAULT ),
      $userID
    )
  );
  if ( !$result ) {
    \Phi\Tools::log_json( $this->db->lastError() );
  }
  return (bool) $result;
}

/**
 * Get Existing User Record
 * @param {string} $userID - Unique ID of the User to get.
 * @returns {array|bool} The User record array, or false if not found.
 */
public function getUser ( $userID ) {
  $result = $this->db->pq( 'SELECT * FROM `'.$this->TABLE['NAME'].'` WHERE `'.$this->TABLE['USER'].'`=?', $userID );
  return ( $result ) ? $result->fetch_assoc() : false;
}

/**
 * Delete Existing User
 * @param {string} $userID - Unique ID of the User to delete.
 * @returns {bool} - If the operation succeeded.
 */
public function deleteUser ( $userID ) {
  $result = $this->db->pq( 'DELETE FROM `'.$this->TABLE['NAME'].'` WHERE `'.$this->TABLE['USER'].'`=?', $userID );
  if ( !$result ) {
    \Phi\Tools::log_json( $this->db->lastError() );
  }
  return (bool) $result;
}

public function challenge ( $realm="standard" ) {
  if ( !( is_string($realm) && $realm ) ) throw new \Exception( "Realm must be a string." );
  $this->phi->response->status( 401 );
  $this->phi->response->headers( 'WWW-Authenticate: Phi realm="' . $realm . '"' );
}

/**
 * @return {bool|null} - Returns TRUE is "Authorization" header is valid, FALSE if it is invalid, or NULL if it is missing.
 */
public function checkAuthorization ( $authorization=null ) {
  if (!$authorization) $authorization = $this->phi->request->headers('Authorization');
  if (!$authorization) {
    \Phi\Tools::log(date('c').' Failed login attempt from '.\Phi\Request::ip().' (missing authenticaiton header)');
    \Phi\Tools::log_json( $this->phi->request->headers() );
    return null;
  }
  $authScheme = \Phi\Tools::str_shift( $authorization );
  switch ( strtolower($authScheme) ) {
    case "basic":
    case "phi":
      $credentials = base64_decode( $authorization );
      $username = \Phi\Tools::str_shift( $credentials, ":" );
      $pass = $credentials;
      $user = $this->getUser( $username );
      # Verify User's Credentials
      if ( $user ) {
        if ( password_verify( $pass, $user[ $this->TABLE['PASS'] ] ) ) {
          $this->user = $user;
          return $user;
        } else {
          \Phi\Tools::log(date('c').' Failed login attempt from '.\Phi\Request::ip().' as "'.$username.'" (incorrect password)');
          return false;
        }
      }
      # Pretend To Verify Nonexistant User's Credentials
      else {
        password_verify( 'Missing User', '$2a$08$nqWza8jri7gZmOKYubrLrOVbEZTbEzXnbkJ.ad/2.RlbsbMQxPVO.' );
        \Phi\Tools::log(date('c').' Failed login attempt from '.\Phi\Request::ip().' as "'.$username.'" (unknown username)');
        return false;
      }
      break;
    case "bearer":
      try {
        $payload = $this->verifyJwt($authorization, $this->phi->config['JWT_CONFIG']['AUDIENCE']);
        $this->user = $payload;
        return $this->user;
      }
      catch (\Exception $e) {
        return false;
      }
      break;
    default:
      \Phi\Tools::log(date('c').' Failed login attempt from '.\Phi\Request::ip().' (unsupported authenticaiton scheme)');
      \Phi\Tools::log_json([
        $this->phi->request->headers(),
        $this->phi->request->headers('Authorization'),
        $authorization,
        $authScheme
      ]);
      return false;
  }
}

public function checkConnectionSecurity () {
  if ( $this->REQUIRE_HTTPS && !( $this->phi->request->isLocalhost() || $this->phi->request->isHTTPS() ) ){
    \Phi\Tools::log(date('c').' Request refused from '.\Phi\Request::ip().' (required HTTPS)');
    return false;
  }
  if ( !$this->phi->request->isAllowedOrigin() ){
    \Phi\Tools::log(date('c').' Request refused from '.\Phi\Request::ip().' (not allowed origin)');
    return false;
  }
  return true;
}

public function inSession () {
  return ( isset( $this->phi->session['phiSessionUser'] ) ) ? true : false;
}

public function isAuthorized () {
  if ( ! $this->checkConnectionSecurity() ) return false;
  $authorization = $this->checkAuthorization();
  return ( $authorization === null ) ? $this->loggedIn() : $authorization;
}

public function logIn ( $username=null, $password=null ) {
  if ( ! $this->checkConnectionSecurity() ) return false;
  if ( $username && $password ) {
    # Temp workaround for non-JavaScript login form POST
    $user = $this->checkAuthorization( "Basic ".base64_encode($username.":".$password) );
  } else {
    # Check 'Authorization' field in request header
    $user = $this->checkAuthorization();
  }
  # Good: Start session
  if ( $user ) {
    //$this->user = $user;
    $this->phi->session['phiSessionUser'] = $this->user;
    $this->phi->session->save();
    return $user;
  # Bad: No session change
  } else {
    return false;
  }
}

public function forceLogIn ( $username=null ) {
  $user = $this->getUser( $username );
  if ( $user ) {
    $this->user = $user;
    $this->phi->session['phiSessionUser'] = $this->user;
    $this->phi->session->save();
    return true;
  } else {
    return false;
  }
}

public function loggedIn () {
  $sessionUser = $this->sessionUser();
  if ( $sessionUser && isset( $sessionUser[ $this->TABLE['USER'] ] ) ) {
    $this->user = $this->getUser( $sessionUser[ $this->TABLE['USER'] ] );
    if (
      ( $sessionUser[ $this->TABLE['PASS'] ] === null && $this->user[ $this->TABLE['PASS'] ] === null )
        ||
      ( isset( $sessionUser[ $this->TABLE['PASS'] ] ) && $sessionUser[ $this->TABLE['PASS'] ] === $this->user[ $this->TABLE['PASS'] ] )
    ) {
      $this->phi->session['phiSessionUser'] = $this->user;
      return $this->user;
    }
  }
  return false;
}

public function logOut () {
  //$this->phi->session->destroy();
  if ( isset( $this->phi->session['phiSessionUser'] ) ) unset( $this->phi->session['phiSessionUser'] );
  $this->phi->session->save();
  return true;
}

public function sessionUser () {
  return ( isset( $this->phi->session['phiSessionUser'] ) ) ? $this->phi->session['phiSessionUser'] : false;
}


#################################
# API Key Authorization Methods #
#################################

/**
 * @return array|null The UserGroup record array if API Key is found, or NULL if not.
 */
public function checkApiKey ( $apiKey=null ) {
  if ( !$apiKey ) $apiKey = $this->phi->request->headers('X-Api-Key');
  if ( !$apiKey ) {
    $input = $this->phi->request->input();
    if (array_key_exists('key', $input)) {
      $apiKey = $input['key'];
    }
  }
  if ( !$apiKey ) return null;
  $group = $this->getGroupByApiKey($apiKey);
  return $group;
}

/**
 * Get Group Record by API Key
 * @param string $apiKey
 * @return array|null The UserGroup record array, or null if not found.
 */
public function getGroupByApiKey ($apiKey) {
  $result = $this->db->pq('SELECT * FROM `'.$this->API_TABLE['NAME'].'` WHERE `'.$this->API_TABLE['KEY'].'`=?', $apiKey);
  if ($result) {
    $row = $result->fetch_assoc();
    $result->close();
    return $row;
  }
  return null;
}


########################################
# JSON Web Token Authorization Methods #
########################################

public static function getPublicKeys () {
  $phi = \Phi\App::instance();
  // First, check the database for current keys.
  try {
    $now = time();
    $result = $phi->db->query("SELECT `id`,`certificate` FROM `PublicKeys` WHERE `expires`>$now");
    if ( $result && $result->num_rows ) {
      $publicKeys = [];
      while ( $row = $result->fetch_assoc() ) {
        $publicKeys[$row['id']] = $row['certificate'];
      }
      // $phi->log('retrieved keys from database');
      return $publicKeys;
    }
  }
  catch (\Exception $e) {
    // ignore errors
  }
  // Need new keys? Get from public listing.
  try {
    $response = $phi->fetch($phi->config['JWT_CONFIG']['PUBLIC_KEY_LISTING'], [CURLOPT_HEADER => true]);
    // \Phi\Tools::log_json(['fetchJwtResponse' => $response]);
    if (array_key_exists('Expires', $response['headers'])) {
      $expires = strtotime($response['headers']['Expires']);
    } elseif (array_key_exists('expires', $response['headers'])) {
      $expires = strtotime($response['headers']['expires']);
    } else {
      $expires = date('r'); // Now
    }
    $publicKeys = json_decode($response['body'], true);
  }
  catch (\Exception $e) {
    // Failed to get or decode keys.
    return null;
  }
  // Save new keys in database.
  foreach( $publicKeys as $id => $certificate ) {
    $phi->db->pq(
      'INSERT INTO `PublicKeys` (`id`, `certificate`, `expires`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `certificate`=VALUES(`certificate`), `expires`=VALUES(`expires`)',
      [ $id, $certificate, $expires ],
      'ssi'
    );
  }
  // Return keys.
  // $phi->log('retrieved new keys from public listing');
  return $publicKeys;
}

public static function verifyJwt ($token, $projectName) {
  $phi = \Phi\App::instance();

  $publicKeys = self::getPublicKeys();
  if (!is_array($publicKeys)) {
    throw new \Exception('Could not retrieve public keys for verifying JSON Web Token');
  }

  // Attempt to Decode Token
  try {
    $payload = JWT::decode($token, $publicKeys, ['RS256']);
    $payload = (array) $payload;
  }
  catch(\Exception $e) {
    throw $e;
  }
  // \Phi\Tools::log_json($payload);

  // Verify Token
  $now = time();
  if (!(true
    && $payload['aud'] === $projectName
    && $payload['iss'] === 'https://securetoken.google.com/'.$projectName
    && is_string($payload['sub']) && $payload['sub'] !== ''
    && $payload['sub'] === $payload['user_id']
    && $payload['auth_time'] <= $now
  )){
    throw new \Exception('Invalid token');
  }

  return $payload;
}

}?>