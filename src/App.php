<?php
namespace Phi;
class App {

public $errors = array();

public static $DEBUG_LOG = "./debug.log";
public $TEMP_DIR  = "/com.lakehawksolutions.Phi";

private $SESSION_LIFE = 43200; # 12 hours
public  $SESSION_PATH = '/'; # all paths on domain
private $ROUTE_BASE = "";
private $ROUTES_INI = "";
private $DB_CONFIG = null;
private $AUTH_CONFIG = null;
private $ALLOW_ORIGIN = null;

private $configurable = ['SESSION_LIFE', 'SESSION_PATH', 'ROUTE_BASE', 'ROUTES_INI', 'DB_CONFIG', 'AUTH_CONFIG', 'ALLOW_ORIGIN', 'JWT_CONFIG'];
private $autoloadDirs = [];
private $request = null;
private $response = null;
private $db = null;
private $session = null;
private $auth = null;
private $file = null;

public $config = array();

private static $_instance = null;

/**
 * Constructor
 * @param {string} $configFile - Configuration filename relative to the Document Root.
 */
public function __construct ( $configFile=null ) {
  static::$_instance = $this;

  # Register Autoloader
  spl_autoload_register(function($className){
    $classFile = "/" . str_replace("\\", "/", $className) . ".php";
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
      $secureOnly = ( array_key_exists( 'AUTH_CONFIG', $this->config ) && array_key_exists( 'REQUIRE_HTTPS', $this->config['AUTH_CONFIG'] ) ) ? $this->config['AUTH_CONFIG']['REQUIRE_HTTPS'] : true;
      if ( $this->session === null ) $this->session = new \Phi\Session( $this->SESSION_LIFE, $this->SESSION_PATH, $secureOnly );
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

/**
 * Magic callable methods
 * Forwards function calls for Phi Tools.
 * @param string $name - The method name.
 * @param array  $args - The method arguments.
 * @return mixed
 */
public function __call ( $name, $args ) {

  $fullMethodName = '\Phi\Tools::' . $name;
  if (is_callable($fullMethodName)) {
    return call_user_func_array($fullMethodName, $args);
  }

  // switch ( $name ) {

  //   case "log":
  //     return call_user_func_array('\Phi\Tools::log', $args);

  //   case "log_json":
  //     return call_user_func_array('\Phi\Tools::log_json', $args);

  //   default:
  //     return null;
  // }
}

public static function instance () {
  return static::$_instance;
}

public function configure ( $configFile=null ) {

  # Read config file
  if ( is_string($configFile) ) {
    $configFile = \Phi\Tools::pathTo( $configFile );
    if ( file_exists($configFile) ) $config = parse_ini_file( $configFile, true );
  }
  elseif (is_array($configFile)) {
    $config = $configFile;
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
    $this->ROUTES_INI = \Phi\Tools::pathTo( $this->ROUTES_INI );
    $this->TEMP_DIR = sys_get_temp_dir() . $this->TEMP_DIR;
    if (! is_dir( $this->TEMP_DIR ) ) mkdir( $this->TEMP_DIR, 0777, true );
  }

}

public function addAutoloadDir ( $dirname, $toFront=false ) {
  if ( $toFront ) {
    array_unshift( $this->autoloadDirs, \Phi\Tools::pathTo( $dirname ) );
  } else {
    array_push( $this->autoloadDirs, \Phi\Tools::pathTo( $dirname ) );
  }
}

public function loadRoutes ( $routesIniFile=null, $routeBase="" ) {
  if ( $this->request === null ) $this->request = new \Phi\Request( $this );
  if ( file_exists( $routesIniFile ) ) $this->request->loadRoutes( $routesIniFile, $routeBase );
  return $this->request;
}

public function run ( $uri=null, $method=null ) {
  if ( $this->request === null ) $this->loadRoutes();
  if ( ! $this->request->isAllowedOrigin() ) {
    self::log('Rejecting request. '.$this->request->sourceOrigin().' is not in allowed origins: '.json_encode($this->allowedOrigins));
    return false;
  }
  return $this->request->run( $uri, $method );
}

public function lastError () {
  return (count($this->errors)) ? $this->errors[count($this->errors)-1] : null;
}

}?>