<?php class Phi {

public $errors = array();

private $TEMP_DIR = "/com.lakehawksolutions.Phi";
private $SESSION_LIFE = 43200; # 12 hours
private $ROUTE_BASE = "";
private $ROUTES_INI = "";
private $DB_CONFIG = null;
private $AUTH_CONFIG = null;
private $ALLOW_ORIGIN = null;

private $configurable = array( 'SESSION_LIFE', 'ROUTE_BASE', 'ROUTES_INI', 'DB_CONFIG', 'AUTH_CONFIG', 'ALLOW_ORIGIN' );
private $autoloadDirs = array();
private $request = null;
private $response = null;
private $db = null;
private $session = null;
private $auth = null;
private $file = null;

public $config = array();

/**
 * Constructor
 * @param {string} $configFile - Configuration filename relative to the Document Root.
 */
public function __construct ( $configFile=null ) {

  # Standard Autoload Directories
  $this->autoloadDirs = array(
    dirname( dirname(__FILE__) ), # Same as Phi
    //self::pathTo( "." )           # Same as Execution
  );

  # Register Autoloader
  spl_autoload_register(function($className){
    $classFile = "/" . str_replace("\\", "/", $className) . ".php";
    if ( __NAMESPACE__ ) $classFile = "/" . str_replace("\\", "/", __NAMESPACE__) . $classFile;
    foreach( $this->autoloadDirs as $thisDir ) {
      $source = $thisDir . $classFile;
      if ( file_exists( $source ) ) {
        include_once( $source );
        return;
      }
    }
  });

  # Initial Configuration (all but SESSION_LIFE can be changed later)
  if ( $configFile ) $this->configure( $configFile );

}

/**
 * Magic property getter
 * Waits to instanciate object classes until needed.
 * @param {string} $name - The property to get.
 * @return {mixed}       - The property's value.
 */
public function __get ( $name ) {

  switch ( $name ) {

    case "tempDir":
      return $this->TEMP_DIR;

    case "autoloadDirs":
      return $this->autoloadDirs;

    case "routeBase":
      return $this->ROUTE_BASE;

    case "routesINI":
      return $this->ROUTES_INI;

    case "allowedOrigins":
      return $this->ALLOW_ORIGIN;

    case "request":
      if ( $this->request === null ) $this->loadRoutes();
      return $this->request;

    case "response":
      if ( $this->response === null ) $this->response = new \Phi\Response( $this );
      return $this->response;

    case "session":
      if ( $this->session === null ) $this->session = new \Phi\Session( $this->SESSION_LIFE );
      return $this->session;

    case "db":
    case "database":
      if ( $this->db === null ) $this->db = new \Phi\Database( $this->DB_CONFIG );
      return $this->db;

    case "auth":
      if ( $this->auth === null ) $this->auth = new \Phi\Auth( $this, $this->AUTH_CONFIG );
      return $this->auth;

    case "file":
      if ( $this->file === null ) $this->file = new \Phi\File();
      return $this->file;

    default:
      return null;
  }
}

public function configure ( $configFile=null ) {

  # Read config file
  if ( is_string($configFile) ) {
    $configFile = self::pathTo( $configFile );
    if ( file_exists($configFile) ) $config = parse_ini_file( $configFile, true );
  }

  # Overwrite defaults
  if ( is_array( $config ) ) {
    foreach ( $config as $key => $value ) {
      $this->config[$key] = $value;
      if ( in_array( $key, $this->configurable ) ) {
        $this->{$key} = $value;
      }
    }
  }

  # Auto-load directories
  if ( array_key_exists( 'AUTOLOAD_DIR', $config ) ) {
    if ( is_string( $config['AUTOLOAD_DIR'] ) ) {
      $this->addAutoloadDir( $config['AUTOLOAD_DIR'] );
    } elseif ( is_array( $config['AUTOLOAD_DIR'] ) ) {
      foreach ( $config['AUTOLOAD_DIR'] as $thisDir ) {
        if ( is_string( $thisDir ) ) $this->addAutoloadDir( $thisDir );
      }
    }
  }

  # Use full, real paths
  if ( is_string( $this->ROUTES_INI ) && $this->ROUTES_INI ) {
    $this->ROUTES_INI = self::pathTo( $this->ROUTES_INI );
    $this->TEMP_DIR = sys_get_temp_dir() . $this->TEMP_DIR;
    if (! is_dir( $this->TEMP_DIR ) ) mkdir( $this->TEMP_DIR, 0777, true );
  }

}

public function addAutoloadDir ( $dirname, $toFront=false ) {
  if ( $toFront ) {
    array_unshift( $this->autoloadDirs, self::pathTo( $dirname ) );
  } else {
    array_push( $this->autoloadDirs, self::pathTo( $dirname ) );
  }
}

public function loadRoutes ( $routesIniFile=null, $routeBase="" ) {
  if ( $this->request === null ) $this->request = new \Phi\Request( $this );
  if ( file_exists( $routesIniFile ) ) $this->request->loadRoutes( $routesIniFile, $routeBase );
  return $this->request;
}

public function run ( $uri=null, $method=null ) {
  if ( $this->request === null ) $this->loadRoutes();
  if ( ! $this->request->isAllowedOrigin() ) return false;
  return $this->request->run( $uri, $method );
}

public function lastError () {
  return (count($this->errors)) ? $this->errors[count($this->errors)-1] : null;
}

public static function pathTo ( $relativeFileName ) {
  if ( !empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
    return realpath( $_SERVER['DOCUMENT_ROOT'] . "/" . ltrim( $relativeFileName, "/" ) );
  } else {
    return realpath ( "./" . ltrim( $relativeFileName, "/" ) );
  }
}

# Utility Functions #

public static function strpop ( &$str, $sep=" " ) {
  if ( !( is_string($str) && $str ) ) return false;
  $pos = strpos( $str, $sep );
  if ( $pos === false ) {
    $pop = $str;
    $str = "";
  } else {
    $pop = substr( $str, 0, $pos );
    $str = substr( $str, $pos+1 );
  }
  return $pop;
}

public static function array_copy ( array $original ) {
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

public static function is_assoc ( $array ) {
  return is_array($array) ? (bool)count(array_filter(array_keys($array),'is_string')) : false;
}

public static function all_set () {
  $args = func_get_args();
  if ( !( count($args) && is_array( $args[0] ) ) ) return null;
  $subject = array_shift( $args );
  while ( count($args) ) {
    if ( empty( $subject[array_shift( $args )] ) ) return false;
  }
  return true;
}

# Logging Functions #

public static function log ( $text, $overwrite=false ) {
  if ( $overwrite ) {
    file_put_contents( 'debug.log', $text.PHP_EOL );
  } else {
    file_put_contents( 'debug.log', $text.PHP_EOL, FILE_APPEND );
  }
}

public static function log_json ( $data ) {
  file_put_contents( 'debug.log', json_encode( $data, JSON_PRETTY_PRINT ).PHP_EOL, FILE_APPEND );
}


# Fetch contents of URL. #
public function fetch ( $url, $headers = array() ) {
  if ( function_exists('curl_init') ) {
    //use cURL to fetch data

    // Defatult Options //
    $options = array(
      CURLOPT_RETURNTRANSFER => true,    // return content
      CURLOPT_HEADER         => false,   // don't return headers
      CURLOPT_ENCODING       => "",      // handle all encodings
      CURLOPT_CONNECTTIMEOUT => 30,      // timeout on connect
      CURLOPT_TIMEOUT        => 30,      // timeout on response
      CURLOPT_SSL_VERIFYPEER => false    // Disabled SSL Cert checks
    );
    // Custom Options //
    foreach($headers as $key => $value) {
      $options[$key] = $value;
    }

    $ch = curl_init( $url );
    curl_setopt_array( $ch, $options );

    $response = curl_exec($ch);
    if ($response === false) {
      $this->log("cURL error ".curl_errno($ch)." ".curl_error($ch)." getting $url HTTP code ".curl_getinfo($ch, CURLINFO_HTTP_CODE));
    }
    curl_close ($ch);
    return $response;
  } else if ( ini_get('allow_url_fopen') ) {
    //fall back to fopen()
    $response = file_get_contents($url, 'r');
    return $response;
  }
  return false;
}


# Generate a string to random letters (upper and lower-case) and numbers. #
public static function randomAlphanumeric ($length=1) {
  $chars = array();
  for ($i = 0; $i < $length; $i++) {
    switch (rand(0,2)) {
      # number
      case 0:
        $chars[] = chr(rand(48,57));
        break;

      # upper-case
      case 1:
        $chars[] = chr(rand(65,90));
        break;

      # lower-case
      case 2:
        $chars[] = chr(rand(97,122));
        break;
    }
  }
  return implode('', $chars);
}

}?>