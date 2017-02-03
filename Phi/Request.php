<?php namespace Phi; class Request {

public $lastError = null;

const IP_REMOTE    = 1;
const IP_FORWARDED = 2;
const IP_BOTH      = 3;

private $phi;
private $routes = null;
private $allowedMethods = array( 'GET','POST','PATCH','DELETE','PUT','HEAD','OPTIONS' );
private $defaultRouteMethod = 'GET';

public function __construct ( \Phi $phi ) {
  $this->phi = $phi;
  self::loadRoutes( $phi->routesINI, $phi->routeBase );
}

  /**
 * Magic property getter
 * Waits to instanciate object classes until needed.
 * @param {string} $name - The property to get.
 * @return {mixed}       - The property's value.
 */
public function __get ( $name ) {
  switch ( $name ) {

    case "routes":
      return $this->routes;

    default:
      return null;
  }
}

public function loadRoutes ( $routesINI, $routeBase="" ) {
  $debug = false;
  $routesSER = $this->phi->tempDir . "/" . md5( $routesINI );

  # New/Updated Routes INI #
  if ( file_exists($routesINI) && !( file_exists($routesSER) && filectime($routesSER) >= filectime($routesINI) ) ) {
    if ( $this->routes === null ) $this->routes = array();
    $routeDefs = parse_ini_file( $routesINI );
    foreach ( $routeDefs as $route => $handler ) {

      # URI Path Nodes #
      $here = &$this->routes;
      $path = self::path( rtrim( $routeBase, "/" ) . "/" . ltrim( $route, "/" ) );
      foreach ( $path as $node ) {
        if ( $node ) {
          if ( !array_key_exists('_r_', $here) ) $here['_r_'] = array();
          if ( strpos($node, '@') === 0 ) {
            if ( !array_key_exists('@', $here['_r_']) ) $here['_r_']['@'] = array( '_v_' => substr($node, 1) );
            $here = &$here['_r_']['@'];
          } else {
            if ( !array_key_exists($node, $here['_r_']) ) $here['_r_'][$node] = array();
            $here = &$here['_r_'][$node];
          }
        }
      }

      # Methods #
      if ( !array_key_exists('_m_', $here) ) $here['_m_'] = array();
      # Specific-Mathod Handlers #
      if ( is_array($handler) ) {
        foreach ( $handler as $method => $methodHandler ) {
          if ( strpos( $methodHandler, "->" ) ) { // not false nor 0
            $methodHandler = explode( "->", $methodHandler );
          }
          $here['_m_'][$method] = $methodHandler;
        }
      }
      # All-Methods Handler #
      else {
        if ( strpos( $handler, "->" ) ) { // not false nor 0
          $handler = explode( "->", $handler );
        }
        $here['_m_']['@'] = $handler;
      }
    }

    if ( $debug ) $this->phi->log_json( $this->routes );
    file_put_contents( $routesSER, serialize($this->routes) );
  }

  # Up-to-date Pre-Parsed Routes #
  elseif ( file_exists($routesSER) ) {
    $this->routes = unserialize( file_get_contents( $routesSER ) );
  }

  # No Routes #
  else {
    throw new \Exception('Routes config file does not exist.');
  }
}

public function run ( $uri=null, $method=null ) {
  $debug = false;
  if ( $uri===null ) $uri = self::uri();
  if ( is_string($uri) ) $path = self::path( $uri );
  if ( $method===null ) $method = self::method();
  $method = strtoupper( $method );
  if ( $debug ) {
    $this->phi->log( "URI: $uri" );
    $this->phi->log_json( $path );
    $this->phi->log( "Method: $method" );
  }
  $uriParams = array();
  $here = &$this->routes;

  # Request Path Nodes #
  foreach ( $path as $node ) {
    if ( $debug ) $this->phi->log( "Node: $node" );
    if ( array_key_exists( '_r_', $here ) && array_key_exists( $node, $here['_r_'] ) ) {
      if ( $debug ) $this->phi->log( "- named route" );
      $here = &$here['_r_'][$node];
    } elseif ( array_key_exists( '_r_', $here ) && array_key_exists( '@', $here['_r_'] ) ) {
      if ( $debug ) $this->phi->log( "- wild route" );
      $here = &$here['_r_']['@'];
      if ( array_key_exists( '_v_', $here ) ) {
        $uriParams[$here['_v_']] = $node;
      }
    } else {
      if ( $debug ) $this->phi->log( "- no route" );
      $this->phi->response->status( 404 );
      $this->lastError = 404;
      return false;
    }
  }

  # Missing Handlers? #
  if ( ! array_key_exists('_m_', $here) ) {
    if ( $debug ) {
      $this->phi->log( "Method not found." );
      $this->phi->log_json( $here );
    }
    $this->phi->response->method_not_allowed( "" );
    return false;
  }

  # Request Handler #
  if ( array_key_exists($method, $here['_m_']) ) {
    $handler = $here['_m_'][$method];
  } elseif ( array_key_exists('@', $here['_m_']) ) {
    $handler = $here['_m_']['@'];
  } elseif ( $method === 'OPTIONS' ) {
    $routeMethods = array_keys( $here['_m_'] );
    if ( count($routeMethods) === 1 && $routeMethods[0] === '@' ) $routeMethods = $this->allowedMethods;
    $this->phi->response->allow( $routeMethods );
  } else {
    $this->lastError = 405;
    $routeMethods = array_keys( $here['_m_'] );
    if ( count($routeMethods) === 1 && $routeMethods[0] === '@' ) $routeMethods = $this->allowedMethods;
    $this->phi->response->method_not_allowed( $routeMethods );
    return false;
  }
  # Class/Method Handler #
  if ( is_array( $handler ) ) {
    if ( $debug ) $this->phi->log("Handler is a (class,method) array...");
    try {
      $classInstance = new $handler[0]( $this->phi );
    } catch (Exception $e) {
      $this->phi->log( "Error creating new instance of class " . $handler[0] );
      $this->phi->response->no_content( 500 );
      $this->lastError = 500;
      return false;
    }
    if ( ! method_exists( $classInstance, $handler[1] ) ) {
      $this->phi->log( 'Error: Method "' . $handler[1] . '" does not exist in class "' . $handler[0] . '"' );
      $this->phi->response->no_content( 500 );
      $this->lastError = 500;
      return false;
    }
    $classInstance->$handler[1]( $uriParams, $this->input() );
  }
  # Static Function Handler #
  else {
    if ( $debug ) $this->phi->log("Checking if $handler is callable...");
    if ( isset($handler) && is_callable($handler) ) {
      call_user_func( $handler, $this->phi, $uriParams, $this->input() );
    } else {
      $this->lastError = 500;
      return false;
    }
  }

}

/**
 * Get Request Headers (Apache version only).
 * @return {array} Associative array of headers.
 */
public static function headers ( $key=null ) {
  if ( $key === null ) {
    return getallheaders();
  } else {
    $headers = getallheaders();
    if ( is_array($headers) && is_string($key) && isset($headers[$key]) ) {
      return $headers[$key];
    }
  }
  return null;
}

/**
 * Get Request Source Origin.
 * @return {string} The source origin.
 */
public static function sourceOrigin () {

  # Prefer 'Origin' Header Field
  $origin = self::headers( 'Origin' );
  if ( $origin ) {
    if ( preg_match( '/^https?\:\/\/([^\/]+)/', $origin, $matches ) ) {
      $origin = $matches[1];
    }
    return $origin;
  }

  # Parse 'Referer' Header Field
  $referer = self::headers( 'Referer' );
  if ( preg_match( '/^https?\:\/\/([^\/]+)/', $referer, $matches ) ) {
    return $matches[1];
  }

  # Fallback Default 'Host' Header Field
  return self::headers( 'Host' );
}

/**
 * Get Request Target Origin.
 * @return {string} The target origin.
 */
public static function targetOrigin () {
  # Use 'X-Forwarded-Host' Header Field, if present
  $target = self::headers( 'X-Forwarded-Host' );
  if ( $target ) return $target;
  # Default to 'Host' Header Field
  return self::headers( 'Host' );
}

/**
 * Verify Request Source Origin Matches Target Origin.
 * A step toward protecting against Cross-Site Scripting (XSS).
 * @return {bool} Request source and target origins match.
 */
public static function isSameOrigin () {
  $source = self::sourceOrigin();
  $target = self::targetOrigin();
  return ( $source !== null && $target !== null && $source === $target );
}

/**
 * Get the IP address of the incoming request
 * @return {string} The IP address.
 */
public static function ip ( $ipFor=self::IP_REMOTE ) {
  if ( $ipFor == self::IP_REMOTE ) return $_SERVER['REMOTE_ADDR'];
  $headers = getallheaders();
  $forwarded = isset($headers['Client-IP']) ? $headers['Client-IP'] : ( isset($headers['X-Forwarded-For']) ? $headers['X-Forwarded-For'] : null );
  if ( $ipFor == self::IP_FORWARDED ) return $forwarded;
  if ( $ipFor == self::IP_BOTH ) return array( $_SERVER['REMOTE_ADDR'], $forwarded );
  return false;
}

public static function isXHR () {
  return ( self::headers('X-Requested-With') === "XMLHttpRequest" ) ? true : false;
}

public static function isAJAX () {
  return self::isXHR();
}

public static function isHTTPS () {
  return ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === "on" );
}

public static function isSSL () {
  return self::isHTTPS();
}

/**
 * Get the request method (aka. verb)
 * @return {string} The request method.
 */
public static function method () {
  return $_SERVER['REQUEST_METHOD'];
}

/**
 * Get the entire request URI.
 * @return {string} The URI.
 */
public static function uri () {
  return $_SERVER['REQUEST_URI'];
}

/**
 * Split a URI path into array elements, excluding query (?) parameters and hash (#) anchors.
 * @return {array.string} URI path components.
 */
public static function path ( $uri=null ) {
  if ( $uri === null ) $uri = $_SERVER['REQUEST_URI'];
  $pos = strpos($uri, '#');
  if ( $pos !== false ) $uri = substr($uri, 0, $pos);
  $pos = strpos($uri, '?');
  if ( $pos !== false ) $uri = substr($uri, 0, $pos);
  $uri = trim( $uri, "/" );
  return ($uri) ? explode("/", $uri ) : array();
}

/**
 * Get Request Input, whether by GET, POST, or passed in a JSON payload)
 * @return {array} Request input.
 */
public static function input () {
  $method = $_SERVER['REQUEST_METHOD'];
  $contentType = self::headers('Content-Type');
  if ( strpos( $contentType, "application/json" ) !== false ) {
    $params = json_decode( file_get_contents("php://input"), true );
  } else {
    if ( $method === "GET" ) {
      $params = $_GET;
    } elseif ( $method === "POST" ) {
      $params = $_POST;
    } else {
      $params = $_REQUEST;
    }
  }
  if ( get_magic_quotes_gpc() ) {
    $params = self::stripslashes_deep( $params );
  }
  return $params;
}

protected static function stripslashes_deep ( $value ) {
  return is_array($value) ? array_map('self::stripslashes_deep', $value) : stripslashes($value);
}

public static function not_modified ( $mtime, $etag ) {
  return ( ( $reqEtag = self::headers( 'If-None-Match' ) )
          ? ( $reqEtag == $etag )
          : ( @strtotime(self::headers( 'If-Modified-Since' ) ) == $mtime ) );
}

/**
 * Get media types acceptable to the client, in priority order.
 * @return {array} Media types.
 */
public static function accept ( $test=null ) {
  $headers = getallheaders();
  $media = preg_split( '/\s*,\s*/', $headers['Accept'] );
  $acceptQ = array();
  foreach ( $media as $mediaRange ) {
    $mediaRange = trim( $mediaRange );
    if ( strpos( $mediaRange, ';' ) !== false ) {
      $matches = preg_split( '/\s*;\s*/', $mediaRange );
      $mediaRange = $matches[0];
      $acceptParams = $matches[1];
      if ( preg_match( '/q\s*\=\s*(\d+\.?\d*)/', $acceptParams, $matches ) ) {
        $q = $matches[1] + 0;
      } else {
        $q = 1;
      }
      $acceptQ[] = array( $mediaRange, $q );
    } else {
      $acceptQ[] = array( $mediaRange, 1 );
    }
    usort( $acceptQ, 'self::compareAcceptQ');
    $accept = array();
    foreach ( $acceptQ as $value ) {
      $accept[] = $value[0];
    }
  }
  if ( $test ) {
    return in_array( $test, $accept );
  }
  return $accept;
}

protected static function compareAcceptQ ( $a, $b ) {
  return ($a[1]==$b[1])?1:(($a[1]>$b[1])?-1:1);
}

}?>
