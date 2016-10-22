<?php

class Phi {

  public $errors = array();

  public $SESSION_LIFE = 43200; # 12 hours
  public $ROUTES_INI = "/routes.ini";
  public $TEMP_DIR = "/com.lakehawksolutions.Phi";

  private $configurable = array( 'SESSION_LIFE', 'ROUTES_INI' );
  private $autoloadDirs = array();
  private $request = null;
  private $response = null;
  private $db = null;
  private $session = null;
  private $auth = null;

  /**
   * Constructor
   * @param {string} $configFile - Configuration filename relative to the Document Root.
   */
  public function __construct ( $configFile=null ) {

    # Autoloading
    array_unshift( $this->autoloadDirs, dirname( dirname(__FILE__) ) );
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

    switch ( strtolower($name) ) {

      case "request":
        if ( $this->request === null ) $this->loadRoutes();
        return $this->request;
        break;

      case "response":
        if ( $this->response === null ) $this->response = new \Phi\Response( $this );
        return $this->response;
        break;

      case "session":
        return $this->session;
        break;

      case "db":
      case "database":
        $this->db = new \Phi\Database("../etc/phi-db-phish.ini");
        return $this->db;
        break;

      case "auth":
        if ( $this->auth === null ) $this->auth = new \Phi\Auth( $this );
        return $this->auth;
        break;

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
      foreach ( $this->configurable as $configVar ) {
        if ( array_key_exists( $configVar, $config ) ) $this->{$configVar} = $config[$configVar];
      }
    }

    # Auto-load directories
    if ( is_string( $config['AUTOLOAD_DIR'] ) ) {
      $this->addAutoloadDir( $config['AUTOLOAD_DIR'] );
    } elseif ( is_array( $config['AUTOLOAD_DIR'] ) ) {
      foreach ( $config['AUTOLOAD_DIR'] as $thisDir ) {
        $this->addAutoloadDir( $thisDir );
      }
    }

    # Use full, real paths
    $this->ROUTES_INI = self::pathTo( $this->ROUTES_INI );
    $this->TEMP_DIR = sys_get_temp_dir() . $this->TEMP_DIR;
    if (! is_dir( $this->TEMP_DIR ) ) mkdir( $this->TEMP_DIR, 0777, true );

    # Start Session
    if ( $this->session === null ) $this->session = new \Phi\Session( $this->SESSION_LIFE );

  }

  public function addAutoloadDir ( $dirname, $toFront=false ) {
    if ( $toFront ) {
      array_unshift( $this->autoloadDirs, self::pathTo( $dirname ) );
    } else {
      array_push( $this->autoloadDirs, self::pathTo( $dirname ) );
    }
  }

  public function loadRoutes ( $routesIniFile=null ) {
    if ( $this->request === null ) $this->request = new \Phi\Request( $this );
    if ( file_exists( $routesIniFile ) ) $this->request->loadRoutes( $routesIniFile );
    return $this->request;
  }

  public function run ( $uri=null, $method=null ) {
    if ( $this->request === null ) $this->loadRoutes();
    return $this->request->run( $uri, $method );
  }

  public function lastError () {
    return (count($this->errors)) ? $this->errors[count($this->errors)-1] : null;
  }

  public static function pathTo ( $relativeFileName ) {
    return realpath( $_SERVER['DOCUMENT_ROOT'] . "/" . ltrim( $relativeFileName, "/" ) );
  }

  public static function log ( $text ) {
    file_put_contents( 'debug.log', $text.PHP_EOL, FILE_APPEND );
  }

  public static function log_json ( $data ) {
    file_put_contents( 'debug.log', json_encode( $data, JSON_PRETTY_PRINT ).PHP_EOL, FILE_APPEND );
  }

}

?>