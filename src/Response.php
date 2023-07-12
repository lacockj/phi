<?php namespace Phi; class Response {

# Core Methods #

public static function status ( $code=200, $reason="" ) {
  header( $_SERVER['SERVER_PROTOCOL'] . " $code $reason" );
}

public static function headers ( $headers, $replace=true ) {
  if ( is_string($headers) ) {
    header( $headers, $replace );
  } elseif ( is_array($headers) ) {
    foreach ( $headers as $key => $value ) {
      header( trim($key) . ": " . trim($value), $replace );
    }
  }
}


# Convenient HTTP Response Methods #

public static function allow ( $allowedMethods ) {
  if ( is_string($allowedMethods) ) {
    header( "Allow: " . $allowedMethods );
    header( "Access-Control-Allow-Methods: " . $allowedMethods );
  } elseif ( is_array($allowedMethods) ) {
    header( "Allow: " . implode( ", ", $allowedMethods ) );
    header( "Access-Control-Allow-Methods: " . implode( ", ", $allowedMethods ) );
  }
}

public static function method_not_allowed ( $allowedMethods ) {
  self::status( 405 );
  self::allow( $allowedMethods );
}

public static function content_type ( $type ) {
  header( "Content-type: $type" );
}

public static function content_html () {
  header( "Content-type: text/html" );
}

public static function content_text () {
  header( "Content-type: text/plain" );
}

public static function content_json () {
  header( "Content-type: application/json" );
}

public static function content_ndjson () {
  header( "Content-type: application/x-ndjson" );
}

public static function allow_origin ( $origin ) {
  header( "Access-Control-Allow-Origin: $origin" );
  header( "Access-Control-Allow-Headers: Authorization, Cache-Control, Content-Type, If-Modified-Since, If-None-Match, X-Api-Key, X-Guest-Key" );
  header( "Access-Control-Allow-Credentials: true" );
}


# Cache-Control Methods #

public static function no_cache () {
  header('Cache-Control: no-store, no-cache, must-revalidate');
}

public static function allow_cache ( $mtime, $etag="", $maxAge=0, $private=false ) {
  $privacy = ($private) ? 'private' : 'public';
  header( "Cache-Control: $privacy, max-age=$maxAge" );
  header( "Last-Modified: ".gmdate("D, d M Y H:i:s", $mtime)." GMT");
  header( "Access-Control-Expose-Headers: ETag" );
  header( "ETag: $etag" );
}

public static function not_modified ( $mtime, $etag="", $maxAge=0 ) {
  self::status( 304 );
  self::allow_cache( $mtime, $etag, $maxAge );
}


# Response Formatting Methods #

public static function no_content ( $code=204, $reason="", $headers=null ) {
  self::status( $code, $reason );
  if ( $headers ) self::headers( $headers );
}

public static function asType ( $type, $data, $code=200, $reason=null) {
  switch ( strtolower( $type ) ) {

    case 'csv':
      \Output::csv( $data, $code, $reason );
      break;

    case 'json':
      \Output::json( $data, $code, $reason );
      break;

    case 'html':
    case 'htm':
      self::htmlTableRows( $data, $code, $reason );
      break;

  }
}

public static function json ( $data, $code=200, $reason="", $headers=null ) {
  self::status( $code, $reason );
  header('Content-type: application/json');
  if ( $headers ) self::headers( $headers );
  if (version_compare(phpversion(), '7.1', '>=')) {
    ini_set( 'serialize_precision', -1 );
  }
  if ( is_object( $data ) ) {
    # Object implements Iterator
    if($data instanceof \Iterator) {
      $first = true;
      echo '[';
      foreach ( $data as $i => $row ) {
        if ($first) $first = false;
        else echo ',';
        echo json_encode( $row );
      }
      echo ']';
    }
    # SQL Result
    else {
      switch ( get_class( $data ) ) {

        case "Phi\Datastream":
          ob_start( null, 1048576 );
          echo '[';
          foreach ( $data as $i => $row ) {
            if ( $i != 0 ) echo ",";
            echo json_encode( $row );
          }
          echo ']';
          ob_end_flush();
          break;

        case "mysqli_result":
          ob_start( null, 1048576 );
          echo '[';
          $i = 0;
          while ( $row = $data->fetch_assoc() ) {
            if ( $i != 0 ) echo ",";
            echo json_encode( $row );
            $i++;
          }
          echo ']';
          ob_end_flush();
          break;

        default:
          if (method_exists($data, 'jsonSerialize')) {
            echo json_encode($data);
          }
      }
    }
  } else {
    echo json_encode( $data );
  }
}

public static function ndjson ( $data, $code=200, $reason="", $headers=null ) {
  self::status( $code, $reason );
  header('Content-type: application/x-ndjson');
  if ( $headers ) self::headers( $headers );
  if (version_compare(phpversion(), '7.1', '>=')) {
    ini_set( 'serialize_precision', -1 );
  }
  if ( is_object( $data ) ) {
    # Object implements Iterator
    if($data instanceof \Iterator) {
      foreach ($data as $row) {
        echo json_encode( $row ), "\n";
      }
    }
    # SQL Result
    else {
      switch ( get_class( $data ) ) {

        case "Phi\Datastream":
        case "mysqli_result":
          while ( $row = $data->fetch_assoc() ) {
            echo json_encode( $row ), "\n";
          }
          break;

        default:
        throw new \Exception('Output must be array, mysqli_result, or Phi\Datastream object. Got: ' . get_class( $data ));
      }
    }
  } elseif (is_array($data)) {
    foreach ($data as $row) {
      echo json_encode( $data ), "\n";
    }
  } else {
    throw new \Exception('Output must be array, mysqli_result, or Phi\Datastream object.');
  }
}

public static function csv ( $data, $code=200, $reason=null ) {
  self::status( $code, $reason );
  header('Content-type: text/csv');

  # Stream Database Output #
  if ( is_object( $data ) ) {
    # Object implements Iterator
    if($data instanceof \Iterator) {
      foreach ($data as $i => $row) {
        if ($i === 0) {
          echo self::csvRow( array_keys($row) ), PHP_EOL;
        }
        echo self::csvRow( array_values($row) ), PHP_EOL;
      }
    }
    # SQL Result
    else {
      switch ( get_class( $data ) ) {

        case "Phi\Datastream":
        case "mysqli_result":
          ob_start( null, 1048576 );
          $i = 0;
          while ( $row = $data->fetch_assoc() ) {
            if ($i === 0) {
              echo self::csvRow( array_keys($row) ), PHP_EOL;
            }
            echo self::csvRow( array_values($row) ), PHP_EOL;
            $i++;
          }
          ob_end_flush();
          break;

        default:
          throw new Exception('Unsupported data type for CSV response.');
      }
    }
  }

  # Output Array as CSV Rows
  elseif (is_array($data)) {
    if (! is_array($data[0]) ) $data = array( $data );
    $keys = array_keys( $data[0] );
    echo self::csvRow( $keys ) . PHP_EOL;
    foreach ($data as $row) {
      echo self::csvRow( $row ) . PHP_EOL;
    }
  }
}

public static function text ( $text, $code=200, $reason="" ) {
  self::status( $code, $reason );
  header('Content-type: text/plain');
  if (is_scalar($text)) {
    echo $text;
  } else {
    echo json_encode( $text, JSON_PRETTY_PRINT );
  }
}

public static function html ( $html="", $code=200, $reason="" ) {
  self::status( $code, $reason );
  header('Content-type: text/html');
  echo $html;
}

public static function htmlTableRows ( $data, $code=200, $text="" ) {
  if ( is_array( $data ) ) {
    self::status( $code, $reason );
    header('Content-type: text/html');
    if (! is_array($data[0]) ) $data = array( $data );
    $keys = array_keys( $data[0] );
    echo '<tr><th>' . implode('</th><th>', $keys) . '</th></tr>' . PHP_EOL;
    foreach ($data as $row) {
      echo '<tr><td>' . implode('</td><td>', $row) . '</td></tr>' . PHP_EOL;
    }
  }
}

public static function file ( $filename, $code=200, $reason="" ) {
  if (file_exists($filename) && is_file($filename) && is_readable($filename)) {
    self::status( $code, $reason );
    header('Content-Type: ' . mime_content_type($filename));
    header('Content-Length: ' . filesize($filename));
    readfile($filename);
  }
}


# Data Management Methods #

public static function is_assoc ( $array ) {
  return is_array($array) ? (bool)count(array_filter(array_keys($array),'is_string')) : false;
}

public static function csvRow ( $a ) {
  $b = array();
  foreach ( $a as $f ) {
    if ( preg_match('/\W/', $f) ) {
      $f = '"' . preg_replace('/\"/', '\"', $f) . '"';
    }
    $b[] = $f;
  }
  return implode(',', $b);
}


# Friendly Time Formats #

public static function friendlyTime( $s, $abbr=false ) {
  if ( $s >= 1209600 ) {
    $t = round( $s / 604800 );
    return ($abbr) ? $t."wk" : ( ($t==1) ? "$t week" : "$t weeks" );
  } elseif ( $s >= 172800 ) {
    $t = round( $s / 86400 );
    return ($abbr) ? $t."d" : ( ($t==1) ? "$t day" : "$t days" );
  } elseif ( $s >= 7200 ) {
    $t = round( $s / 3600 );
    return ($abbr) ? $t."h" : ( ($t==1) ? "$t hour" : "$t hours" );
  } elseif ( $s >= 120 ) {
    $t = round( $s / 60 );
    return ($abbr) ? $t."m" : ( ($t==1) ? "$t minute" : "$t minutes" );
  } else {
    return ($abbr) ? $s."s" : ( ($s==1) ? "$s second" : "$s seconds" );
  }
}


# Event Stream Methods (experimental) #

public static function openEventStream () {
  set_time_limit(0);
  header('Content-Type: text/event-stream');
  header('Cache-Control: no-cache');
}

public static function sendEvent ( $event, $id=null ) {
  if ($id && is_scalar($id)) echo "id: " . $id . PHP_EOL;
  if ($event && is_scalar($event)) echo "event: " . $event . PHP_EOL . PHP_EOL;
  ob_end_flush();
  flush();
}

public static function sendEventText ( $text, $event=null, $id=null ) {
  if ($id && is_scalar($id)) echo "id: " . $id . PHP_EOL;
  if ($event && is_scalar($event)) echo "event: " . $event . PHP_EOL;
  echo "data: " . $text . PHP_EOL . PHP_EOL;
  ob_end_flush();
  flush();
}

public static function sendEventJson ( $data, $event=null, $id=null ) {
  if ($id && is_scalar($id)) echo "id: " . $id . PHP_EOL;
  if ($event && is_scalar($event)) echo "event: " . $event . PHP_EOL;
  echo "data: " . json_encode( $data ) . PHP_EOL . PHP_EOL;
  ob_end_flush();
  flush();
}

} # end of class
