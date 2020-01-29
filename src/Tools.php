<?php
namespace Phi;
class Tools {

public static $DEBUG_LOG = "./debug.log";
public static $TEMP_DIR  = "/com.lakehawksolutions.Phi";

/**
 * Get Path to File
 * 
 * Returns absolute path to given relative file name.
 * Takes current environment into account: HTTP server or system command.
 * HTTP file names are relative to document root.
 * System file names are relative to execution directory.
 * 
 * @param string $relativeFileName
 * @return string
 */
public static function pathTo ( $relativeFileName ) {
  if ( !empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
    return realpath( $_SERVER['DOCUMENT_ROOT'] . "/" . ltrim( $relativeFileName, "/" ) );
  } else {
    return realpath( "./" . ltrim( $relativeFileName, "/" ) );
  }
}

/**
 * String Pop
 * 
 * Pops a word off the end (right-side) of a source string,
 * and removes the word from the source string in-place.
 * 
 * Example:
 * $sourceString = "One Two Three";
 * $word = str_pop($sourceString);
 * $word === "Three";
 * $sourceString === "One Two";
 * 
 * @param string $str The source string.
 * @param string $sep (optional) The word separator. Defaults to a single space, " ".
 * @return string The first word from the source string.
 */
public static function str_pop ( &$str, $sep=" " ) {
  if ( !( is_string($str) && $str ) ) return false;
  $pos = strrpos( $str, $sep );
  if ( $pos === false ) {
    $word = $str;
    $str = "";
  } else {
    $word = substr( $str, $pos+strlen($sep) );
    $str = substr( $str, 0, $pos );
  }
  return $word;
}

/**
 * String Shift
 * 
 * Shifts a word off the beginning (left side) of a source string,
 * and removes the word from the source string in-place.
 * 
 * Example:
 * $sourceString = "One Two Three";
 * $word = str_shift($sourceString);
 * $word === "One";
 * $sourceString === "Two Three";
 * 
 * @param string $str The source string.
 * @param string $sep (optional) The word separator. Defaults to a single space, " ".
 * @return string The first word from the source string.
 */
public static function str_shift ( &$str, $sep=" " ) {
  if ( !( is_string($str) && $str ) ) return false;
  $pos = strpos( $str, $sep );
  if ( $pos === false ) {
    $word = $str;
    $str = "";
  } else {
    $word = substr( $str, 0, $pos );
    $str = substr( $str, $pos+strlen($sep) );
  }
  return $word;
}

/**
 * Array Copy
 * 
 * Create a new, deep copy of an array, without any entagled references.
 * 
 * @param array $original
 * @return array
 */
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

/**
 * All Set?
 * 
 * @param array The array to test.
 * @param string[]|string What keys must be set in the array.
 * @return bool|null Returns true if all keys exist and are not empty, false if exists but empty, null if key does not exist.
 */
public static function all_set () {
  $args = func_get_args();
  if ( !( count($args) && is_array( $args[0] ) ) ) return null;
  $subject = array_shift( $args );
  while ( count($args) ) {
    $comp = array_shift( $args );
    if ( is_string( $comp ) ) {
      if ( empty( $subject[$comp] ) ) return false;
    } elseif ( is_array( $comp ) ) {
      foreach ( $comp as $subcomp ) {
        if ( is_string( $subcomp ) ) {
          if ( empty( $subject[$subcomp] ) ) return false;
        } else {
          return null;
        }
      }
    }
  }
  return true;
}

# Logging Functions #

public static function log ( $text, $overwrite=false ) {
  if ( $overwrite ) {
    file_put_contents( self::$DEBUG_LOG, $text.PHP_EOL );
  } else {
    file_put_contents( self::$DEBUG_LOG, $text.PHP_EOL, FILE_APPEND );
  }
}

public static function log_json ( $data ) {
  file_put_contents( self::$DEBUG_LOG, json_encode( $data, JSON_PRETTY_PRINT ).PHP_EOL, FILE_APPEND );
}


# Fetch contents of URL. #
public function fetch ( $url, $headers = array() ) {
  // Use cURL to fetch data by default
  if ( function_exists('curl_init') ) {

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

    // Parse response headers if user asked for them.
    if ( $options[CURLOPT_HEADER] == true ) {
      $responseHeaders = [];
      $responseHeaders['Request-URL'] = $url;
      $responseHeaders['Status-Code'] = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      while ( $thisHeader = self::strpop($response, "\r\n") ) {
        if ( preg_match( '/(\S+)\:\s*(.+)/', $thisHeader, $matches) ) {
          $responseHeaders[$matches[1]] = $matches[2];
        } else {
          $responseHeaders[] = $thisHeader;
        }
      }
      $return = [
        'headers' => $responseHeaders,
        'body'    => $response
      ];
    } else {
      $return = $response;
    }
    curl_close ($ch);
    return $return;
  }
  // Fall back to fopen() if cURL is not available
  else if ( ini_get('allow_url_fopen') ) {
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