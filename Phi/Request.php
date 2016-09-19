<?php namespace Phi; class Request {

public $lastError = null;

const IP_REMOTE    = 1;
const IP_FORWARDED = 2;
const IP_BOTH      = 3;

private $phi;
private $routes;
private $allowedMethods = array( 'OPTIONS', 'GET', 'HEAD', 'POST', 'PUT', 'DELETE' );
private $defaultRouteMethod = 'GET';

public function __construct ( \Phi $phi ) {
  $this->phi = $phi;
  self::loadRoutes( $phi->ROUTES_INI );
}

public function routes ( $routes ) {
  if ( is_array($routes) ) $this->routes = $routes;
  return $this->routes;
}

public function loadRoutes ( $routesINI ) {

  $routesJSON = $this->phi->TEMP_DIR . "/" . md5( $routesINI );
  //$phi->log( "$routesINI\n$routesJSON\n" );
  //if ( true ) {
  if ( file_exists($routesINI) && !( file_exists($routesJSON) && filectime($routesJSON) >= filectime($routesINI) ) ) {
    //echo "Loading from INI<br>";
    $this->routes = array(
      '_m_' => array(),
      '_r_' => array()
    );
    $preRoutes = parse_ini_file( $routesINI );
    //$phi->log_json( $preRoutes );
    foreach ( $preRoutes as $route => $handler ) {
      $here = &$this->routes;
      $path = self::path( $route );
      //$phi->log_json( $path );
      for ( $i=0, $c=count($path); $i<=$c; $i++ ) {
        if ( $i === $c ) {
          if ( !array_key_exists('_m_', $here) ) $here['_m_'] = array();
          if ( is_array($handler) ) {
            foreach ( $handler as $method => $methodHandler ) {
              $here['_m_'][$method] = $methodHandler;
            }
          } else {
            $here['_m_'][$this->defaultRouteMethod] = $handler;
          }
        } else {
          if ( !array_key_exists('_r_', $here) ) $here['_r_'] = array();
          if ( strpos($path[$i], '@') === 0 ) {
            if ( !array_key_exists('*', $here['_r_']) ) $here['_r_']['*'] = array( '_v_' => substr($path[$i], 1) );
            $here = &$here['_r_']['*'];
          } else {
            if ( !array_key_exists($path[$i], $here['_r_']) ) $here['_r_'][$path[$i]] = array();
            $here = &$here['_r_'][$path[$i]];
          }
        }
      }
    }
    //echo "put: ", json_encode( file_put_contents( $routesJSON, json_encode($this->routes) ) ), "\n";
    file_put_contents( $routesJSON, json_encode($this->routes) );
  }
  elseif ( file_exists($routesJSON) ) {
    //echo "Loading from JSON<br>";
    $this->routes = json_decode( file_get_contents( $routesJSON ), true );
  } else {
    throw new \Exception('Routes config file does not exist.');
  }
}

public function run ( $uri=null, $method=null ) {
  $debug = false;
  if ( $uri===null ) $uri = self::uri();
  if ( is_string($uri) ) $path = self::path( $uri );
  if ( $method===null ) $method = self::method();
  $method = strtoupper( $method );
  $uriParams = array();
  $here = &$this->routes;
  if ( is_array($path) ) {
    for ( $i=0, $c=count($path); $i<=$c; $i++ ) {

      # End of request path.
      if ( $i === $c ) {
        if ( array_key_exists('_m_', $here) ) {
          if ( array_key_exists($method, $here['_m_']) ) {
            $handler = $here['_m_'][$method];
          } elseif ( array_key_exists('*', $here['_m_']) ) {
            $handler = $here['_m_']['*'];
          } elseif ( $method === 'OPTIONS' ) {
            $routeMethods = array_keys( $here['_m_'] );
            if ( count($routeMethods) === 1 && $routeMethods[0] === '*' ) $routeMethods = $this->allowedMethods;
            $this->phi->response->allow( $routeMethods );
          } else {
            $this->lastError = 405;
            $routeMethods = array_keys( $here['_m_'] );
            if ( count($routeMethods) === 1 && $routeMethods[0] === '*' ) $routeMethods = $this->allowedMethods;
            $this->phi->response->method_not_allowed( $routeMethods );
            return false;
          }
          if ( $debug ) $this->phi->log("Checking if $handler is callable...");
          if ( isset($handler) && is_callable($handler) ) {
            if ( $debug ) $this->phi->log("  It is.");
            call_user_func( $handler, $uriParams, $this->input() );
          } else {
            if ( $debug ) $this->phi->log("  It isn't.");
            $this->lastError = 500;
            return false;
          }
        }
      }

      # Comparing nodes of defined routes with request path.
      else {
        if ( array_key_exists( '_r_', $here ) && array_key_exists( $path[$i], $here['_r_'] ) ) {
          $here = &$here['_r_'][$path[$i]];
        } elseif ( array_key_exists( '_r_', $here ) && array_key_exists( '*', $here['_r_'] ) ) {
          $here = &$here['_r_']['*'];
          if ( array_key_exists( '_v_', $here ) ) {
            $uriParams[$here['_v_']] = $path[$i];
          }
        } else {
          $this->lastError = 404;
          return false;
        }
      }
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
